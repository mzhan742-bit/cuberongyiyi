<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Controllers/Configs.php';
require_once __DIR__ . '/../Controllers/WebExchange.php';

function exchangeResponse(string $status, string $message, array $data = [])
{
    http_response_code($status === 'success' ? 200 : 400);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exchangeResponse('error', 'Phương thức không hợp lệ.');
}

if (!$Login || !$ImS || empty($ImS['id'])) {
    exchangeResponse('error', 'Vui lòng đăng nhập trước khi quy đổi.');
}

try {
    ensureWebExchangeSchema($Connect);
} catch (Throwable $e) {
    exchangeResponse('error', 'Không thể khởi tạo chức năng quy đổi. Kiểm tra quyền CREATE TABLE của database.');
}

$csrfToken = (string)($_POST['csrf_token'] ?? '');
if ($csrfToken === '' || !isset($_SESSION['csrf_tokens'][$csrfToken])) {
    exchangeResponse('error', 'Phiên quy đổi đã hết hạn. Vui lòng tải lại trang.');
}

$type = strtolower(trim((string)($_POST['type'] ?? '')));
if (!in_array($type, ['goldbar', 'gem'], true)) {
    exchangeResponse('error', 'Loại quy đổi không hợp lệ.');
}

$amountRaw = trim((string)($_POST['amount'] ?? ''));
if (!preg_match('/^\d+$/', $amountRaw)) {
    exchangeResponse('error', 'Số tiền quy đổi không hợp lệ.');
}
$amount = (int)$amountRaw;

if ($amount < 10000 || $amount > 5000000) {
    exchangeResponse('error', 'Mỗi lần quy đổi từ 10.000 đến 5.000.000 VNĐ.');
}

// Thỏi vàng dùng phép chia nguyên theo source game: (VND / 1000) * 4.
// Bắt buộc chia hết 1.000 để không làm mất phần lẻ.
if ($type === 'goldbar' && ($amount % 1000) !== 0) {
    exchangeResponse('error', 'Đổi Thỏi vàng: số tiền phải chia hết cho 1.000 VNĐ.');
}

$requestKey = strtolower(trim((string)($_POST['request_key'] ?? '')));
if (!preg_match('/^[a-f0-9]{32}$/', $requestKey)) {
    $requestKey = bin2hex(random_bytes(16));
}

try {
    $reward = webExchangeReward($type, $amount);
} catch (Throwable $e) {
    exchangeResponse('error', 'Không thể tính phần thưởng quy đổi.');
}

$accountId = (int)$ImS['id'];

try {
    $Connect->beginTransaction();

    // Chống submit lặp cùng một form / double click.
    $dup = $Connect->prepare("
        SELECT id, status
        FROM web_exchange_queue
        WHERE request_key = :request_key AND account_id = :account_id
        LIMIT 1
    ");
    $dup->execute([
        ':request_key' => $requestKey,
        ':account_id' => $accountId,
    ]);
    $old = $dup->fetch(PDO::FETCH_ASSOC);
    if ($old) {
        $Connect->commit();
        exchangeResponse('success', 'Yêu cầu này đã được ghi nhận trước đó.', [
            'exchange_id' => (int)$old['id'],
            'status' => $old['status'],
        ]);
    }

    // Khóa dòng account để không bị trừ tiền 2 lần khi nhiều request chạy đồng thời.
    $stmt = $Connect->prepare("
        SELECT id, username, vnd, active
        FROM account
        WHERE id = :id
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([':id' => $accountId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        throw new RuntimeException('ACCOUNT_NOT_FOUND');
    }

    $currentVnd = (int)($account['vnd'] ?? 0);

    // Source game hiện yêu cầu active khi đổi VND -> Thỏi vàng.
    if ($type === 'goldbar' && (int)($account['active'] ?? 0) !== 1) {
        $Connect->rollBack();
        exchangeResponse('error', 'Tài khoản cần được kích hoạt trước khi đổi Thỏi vàng.');
    }

    if ($currentVnd < $amount) {
        $Connect->rollBack();
        exchangeResponse('error', 'Số dư VNĐ không đủ.');
    }

    $playerId = null;
    $p = $Connect->prepare("SELECT id FROM player WHERE account_id = :account_id LIMIT 1");
    $p->execute([':account_id' => $accountId]);
    $playerIdValue = $p->fetchColumn();
    if ($playerIdValue !== false) {
        $playerId = (int)$playerIdValue;
    }

    // Trừ ví web ngay để số tiền này được giữ cho giao dịch.
    $debit = $Connect->prepare("
        UPDATE account
        SET vnd = vnd - :amount
        WHERE id = :id AND vnd >= :amount
    ");
    $debit->execute([
        ':amount' => $amount,
        ':id' => $accountId,
    ]);

    if ($debit->rowCount() !== 1) {
        throw new RuntimeException('BALANCE_CHANGED');
    }

    $insert = $Connect->prepare("
        INSERT INTO web_exchange_queue
        (
            request_key, account_id, player_id, username,
            exchange_type, amount_vnd, reward_amount,
            ticket_amount, event_point_amount, status
        )
        VALUES
        (
            :request_key, :account_id, :player_id, :username,
            :exchange_type, :amount_vnd, :reward_amount,
            :ticket_amount, :event_point_amount, 'PENDING'
        )
    ");
    $insert->execute([
        ':request_key' => $requestKey,
        ':account_id' => $accountId,
        ':player_id' => $playerId,
        ':username' => (string)$account['username'],
        ':exchange_type' => $type,
        ':amount_vnd' => $amount,
        ':reward_amount' => $reward['reward'],
        ':ticket_amount' => $reward['ticket'],
        ':event_point_amount' => $reward['event_point'],
    ]);

    $exchangeId = (int)$Connect->lastInsertId();
    $remaining = $currentVnd - $amount;

    $Connect->commit();

    $rewardText = $type === 'goldbar'
        ? number_format((int)$reward['reward']) . ' Thỏi vàng'
        : number_format((int)$reward['reward']) . ' Ngọc xanh';

    exchangeResponse('success', 'Quy đổi đã được gửi sang server game: ' . $rewardText . '.', [
        'exchange_id' => $exchangeId,
        'remaining_vnd' => $remaining,
        'reward' => (int)$reward['reward'],
        'ticket' => (int)$reward['ticket'],
        'event_point' => (int)$reward['event_point'],
    ]);
} catch (Throwable $e) {
    if ($Connect->inTransaction()) {
        $Connect->rollBack();
    }

    if ($e->getMessage() === 'BALANCE_CHANGED') {
        exchangeResponse('error', 'Số dư vừa thay đổi. Vui lòng tải lại trang và thử lại.');
    }

    exchangeResponse('error', 'Không thể tạo giao dịch quy đổi. Vui lòng thử lại.');
}
