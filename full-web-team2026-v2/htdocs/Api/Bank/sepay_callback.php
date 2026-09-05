<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../../Controllers/Configs.php';
require_once __DIR__ . '/../../Controllers/AutobankConfig.php';

$bankCfg = yiyiGetAutobankConfig($Settings ?? []);
$expectedKey = $bankCfg['api_key'];

// Không dùng key mẫu. Nếu chưa có key thật thì không được phép cộng tiền.
if ($expectedKey === '') {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Autobank chưa cấu hình API key thật trong .env.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$receivedKey = '';

$candidates = [
    $_SERVER['HTTP_X_SEPAY_API_KEY'] ?? '',
    $_SERVER['HTTP_X_API_KEY'] ?? '',
    $_SERVER['HTTP_AUTHORIZATION'] ?? '',
    $headers['X-SePay-Api-Key'] ?? '',
    $headers['X-API-Key'] ?? '',
    $headers['Authorization'] ?? '',
];

foreach ($candidates as $candidate) {
    $candidate = trim((string)$candidate);
    if ($candidate === '') continue;
    $candidate = preg_replace('/^(Apikey|Bearer)\s+/i', '', $candidate);
    if ($candidate !== '') {
        $receivedKey = trim($candidate);
        break;
    }
}

if ($receivedKey === '' || !hash_equals($expectedKey, $receivedKey)) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'API key Autobank không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Dữ liệu không hợp lệ'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $transactionId = trim((string)($data['referenceCode'] ?? ''));
    $amount = (float)($data['transferAmount'] ?? 0);
    $content = trim((string)($data['content'] ?? ''));
    $transactionDate = trim((string)($data['transactionDate'] ?? date('Y-m-d H:i:s')));

    if ($transactionId === '' && isset($data['id'])) {
        $transactionId = 'SEPAY#' . (int)$data['id'];
    }

    if ($transactionId === '') {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Thiếu mã giao dịch'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($amount < $bankCfg['min_amount']) {
        echo json_encode(['success'=>true,'message'=>'Giao dịch dưới mức tối thiểu, bỏ qua'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $prefix = $bankCfg['memo_prefix'];
    $username = null;

    if (preg_match('/' . preg_quote($prefix, '/') . '(\d+)/i', $content, $m)) {
        $userId = (int)$m[1];
        $stmt = $Connect->prepare("SELECT username FROM account WHERE id=:id LIMIT 1");
        $stmt->execute(['id'=>$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $username = $row['username'];
    }

    if (!$username) {
        echo json_encode(['success'=>true,'message'=>'Không tìm thấy tài khoản hợp lệ trong nội dung chuyển khoản'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $Connect->beginTransaction();

    $dup = $Connect->prepare("SELECT id FROM vp_bank WHERE tid=:tid LIMIT 1 FOR UPDATE");
    $dup->execute(['tid'=>$transactionId]);
    if ($dup->fetchColumn()) {
        $Connect->rollBack();
        echo json_encode(['success'=>true,'message'=>'Giao dịch đã được xử lý'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $amountInt = (int)round($amount);
    $credited = (int)round($amountInt * (1 + ($bankCfg['bonus_percent'] / 100)));

    $insert = $Connect->prepare("
        INSERT INTO vp_bank (tid,description,amount,username,status,time)
        VALUES (:tid,:description,:amount,:username,1,:time)
    ");
    $insert->execute([
        'tid'=>$transactionId,
        'description'=>$content,
        'amount'=>$amountInt,
        'username'=>$username,
        'time'=>$transactionDate,
    ]);

    $credit = $Connect->prepare("
        UPDATE account
        SET vnd=vnd+:credited,
            tongnap=COALESCE(tongnap,0)+:credited,
            active=1
        WHERE username=:username
    ");
    $credit->execute(['credited'=>$credited,'username'=>$username]);

    $Connect->commit();

    echo json_encode(['success'=>true,'message'=>'Đã xử lý giao dịch thành công'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($Connect) && $Connect instanceof PDO && $Connect->inTransaction()) {
        $Connect->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Lỗi server Autobank'], JSON_UNESCAPED_UNICODE);
}
