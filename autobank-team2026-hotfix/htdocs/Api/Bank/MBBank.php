<?php
/**
 * MBBank API Handler
 * Giữ nguyên tên class/file để tương thích source cũ.
 * Backend: SePay API v2 - OCB.
 *
 * SePay -> cauberongyiyi<ID> -> account.id
 * -> INSERT history_bank
 * -> account.vnd += amount + 10%
 * -> account.tongnap += amount thực nạp
 * -> KHÔNG cộng cash/danap/thoi_vang trực tiếp.
 */
class MBBank
{
    private $apiKey;
    private $apiUrl;
    private $connect;

    public function __construct($apiKey, $connect)
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = 'https://userapi.sepay.vn/v2/transactions'
            . '?transfer_type=in'
            . '&bank_brand_name=OCB'
            . '&q=' . urlencode('cauberongyiyi')
            . '&per_page=100'
            . '&transaction_date_sort=desc';

        $this->connect = $connect;
        $this->connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connect->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function getTransactionHistory()
    {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPGET => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey
                ]
            ]);

            $response = curl_exec($curl);
            $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($response === false || $response === '') {
                throw new Exception($curlError !== '' ? 'Không thể kết nối SePay: ' . $curlError : 'SePay trả về response rỗng');
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Lỗi parse JSON SePay: ' . json_last_error_msg());
            }

            if ($httpCode !== 200) {
                $message = $data['message'] ?? ('HTTP ' . $httpCode);
                throw new Exception('SePay API lỗi: ' . $message);
            }

            if (!isset($data['status']) || $data['status'] !== 'success' || !isset($data['data']) || !is_array($data['data'])) {
                throw new Exception($data['message'] ?? 'API trả về dữ liệu không hợp lệ');
            }

            foreach ($data['data'] as $key => $transaction) {
                $data['data'][$key]['tid'] = isset($transaction['id']) ? (string)$transaction['id'] : '';
                $data['data'][$key]['description'] = isset($transaction['transaction_content']) ? (string)$transaction['transaction_content'] : '';
                $data['data'][$key]['amount'] = isset($transaction['amount_in']) ? (int)$transaction['amount_in'] : 0;
                $data['data'][$key]['date'] = isset($transaction['transaction_date']) ? (string)$transaction['transaction_date'] : '';
                $data['data'][$key]['reference_number'] = isset($transaction['reference_number']) ? (string)$transaction['reference_number'] : '';
            }

            return $data;
        } catch (Throwable $e) {
            error_log('SePay API Error: ' . $e->getMessage());
            return false;
        }
    }

    public function processDonateTransactions()
    {
        $history = $this->getTransactionHistory();
        if ($history === false || !isset($history['data'])) {
            return false;
        }

        $processedCount = 0;

        foreach ($history['data'] as $transaction) {
            $amount = (int)($transaction['amount'] ?? 0);
            $description = (string)($transaction['description'] ?? '');

            // Theo giao diện hiện tại: nạp ATM tối thiểu 10.000đ.
            if ($amount < 10000 || stripos($description, 'cauberongyiyi') === false) {
                continue;
            }

            $accountId = $this->extractUsernameFromDescription($description);
            if ($accountId && $this->processDonateTransaction($transaction, $accountId)) {
                $processedCount++;
            }
        }

        return $processedCount;
    }

    public function extractUsernameFromDescription($description)
    {
        $description = trim((string)$description);
        if (preg_match('/cauberongyiyi\s*([0-9]+)/i', $description, $matches)) {
            return (int)$matches[1];
        }
        return false;
    }

    private function processDonateTransaction($transaction, $accountId)
    {
        try {
            $this->connect->beginTransaction();

            $stmt = $this->connect->prepare(
                'SELECT id, username, vnd, tongnap
                 FROM account
                 WHERE id = :account_id
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([':account_id' => (int)$accountId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception('Không tìm thấy account ID: ' . (int)$accountId);
            }

            $amount = (int)($transaction['amount'] ?? 0);
            if ($amount < 10000) {
                $this->connect->rollBack();
                return false;
            }

            $transactionId = trim((string)($transaction['tid'] ?? ''));
            if ($transactionId === '') {
                throw new Exception('Giao dịch SePay không có ID');
            }

            $description = (string)($transaction['description'] ?? '');
            $transactionDate = !empty($transaction['date']) && strtotime($transaction['date']) !== false
                ? date('Y-m-d H:i:s', strtotime($transaction['date']))
                : date('Y-m-d H:i:s');

            $checkStmt = $this->connect->prepare(
                'SELECT id FROM history_bank WHERE code = :code LIMIT 1 FOR UPDATE'
            );
            $checkStmt->execute([':code' => $transactionId]);

            if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
                $this->connect->rollBack();
                return false;
            }

            $insertStmt = $this->connect->prepare(
                'INSERT INTO history_bank
                    (username, amount_vnd, amount_cash, description, code, created_at)
                 VALUES
                    (:username, :amount_vnd, :amount_cash, :description, :code, :created_at)'
            );
            $insertStmt->execute([
                ':username' => $user['username'],
                ':amount_vnd' => $amount,
                ':amount_cash' => $amount,
                ':description' => $description,
                ':code' => $transactionId,
                ':created_at' => $transactionDate
            ]);

            $webAmount = (int)round($amount * 1.10);

            $updateStmt = $this->connect->prepare(
                'UPDATE account
                 SET vnd = COALESCE(vnd, 0) + :web_amount,
                     tongnap = COALESCE(tongnap, 0) + :raw_amount
                 WHERE id = :account_id'
            );
            $updateStmt->execute([
                ':web_amount' => $webAmount,
                ':raw_amount' => $amount,
                ':account_id' => (int)$user['id']
            ]);

            if ($updateStmt->rowCount() !== 1) {
                throw new Exception('Không cập nhật được account ID: ' . (int)$user['id']);
            }

            $this->connect->commit();

            error_log(
                'Autobank OK'
                . ' - code=' . $transactionId
                . ' - account=' . $user['id']
                . ' - username=' . $user['username']
                . ' - amount=' . $amount
                . ' - webAmount=' . $webAmount
            );

            return true;
        } catch (Throwable $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }

            $message = 'Autobank FAIL - account=' . (int)$accountId . ' - ' . $e->getMessage();
            error_log($message);
            @file_put_contents(
                __DIR__ . '/autobank_error.log',
                '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
                FILE_APPEND
            );
            return false;
        }
    }

    public function testConnection()
    {
        $history = $this->getTransactionHistory();
        if ($history === false) {
            return ['success' => false, 'message' => 'Không thể kết nối đến SePay API'];
        }

        return [
            'success' => true,
            'message' => 'Kết nối SePay API thành công',
            'data_count' => isset($history['data']) ? count($history['data']) : 0,
            'sample' => isset($history['data'][0]) ? $history['data'][0] : null
        ];
    }
}
