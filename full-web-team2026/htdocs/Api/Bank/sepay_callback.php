<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');

// load đúng file Configs.php
require_once __DIR__ . '/../../Controllers/Configs.php';

try {
    // === log payload để kiểm tra ===
    $input = file_get_contents('php://input');
    file_put_contents(__DIR__ . '/../callback_log.txt', "[" . date('Y-m-d H:i:s') . "]\n$input\n\n", FILE_APPEND);

    $data = json_decode($input, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
        exit;
    }

    $transaction_id   = trim($data['referenceCode'] ?? '');
    $amount           = (float)($data['transferAmount'] ?? 0);
    $content          = trim($data['content'] ?? '');
    $transaction_date = trim($data['transactionDate'] ?? date('Y-m-d H:i:s'));

    // nếu không có referenceCode => dùng id
    if ($transaction_id === '' && isset($data['id'])) {
        $transaction_id = 'SEPAY#' . (int)$data['id'];
    }

    if ($amount < 10000) {
        echo json_encode(['success' => true, 'message' => 'Số tiền nhỏ hơn 10.000 VNĐ, bỏ qua']);
        exit;
    }

    // tìm user theo MEMO
    $prefix = 'ngocrongluaga';
    $username = null;

    if (preg_match('/' . preg_quote($prefix, '/') . '(\d+)/i', $content, $m)) {
        $userId = (int)$m[1];
        $stmt = $Connect->prepare("SELECT username FROM account WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $u = $stmt->fetch();
        if ($u) $username = $u['username'];
    }

    if (!$username) {
        echo json_encode(['success' => true, 'message' => 'Không tìm thấy user hợp lệ trong nội dung chuyển khoản']);
        exit;
    }

    // kiểm tra trùng giao dịch
    $stmt = $Connect->prepare("SELECT id FROM vp_bank WHERE tid = :tid LIMIT 1");
    $stmt->execute(['tid' => $transaction_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Giao dịch đã được xử lý']);
        exit;
    }

    // ghi vào vp_bank
    $stmt = $Connect->prepare("
        INSERT INTO vp_bank (tid, description, amount, username, status, time)
        VALUES (:tid, :description, :amount, :username, 1, :time)
    ");
    $stmt->execute([
        'tid'         => $transaction_id,
        'description' => $content,
        'amount'      => (int)$amount,
        'username'    => $username,
        'time'        => $transaction_date
    ]);

    // tính bonus
    $amountInt       = (int)round($amount);
    $amountWithBonus = (int)round($amountInt * 1.10);

    // cộng ví
    $stmt = $Connect->prepare("
        UPDATE account
        SET
            vnd      = vnd + :bonus,
            tongnap  = COALESCE(tongnap, 0) + :bonus,
            danap    = COALESCE(danap, 0) + :bonus,
            active   = 1
        WHERE username = :username
    ");
    $stmt->execute([
        'bonus'    => $amountWithBonus,
        'username' => $username
    ]);

    echo json_encode(['success' => true, 'message' => 'Đã xử lý giao dịch thành công']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi server',
        'error'   => $e->getMessage()
    ]);
}
