<?php
/**
 * Test SePay + team2026.
 * KHÔNG cộng tiền thật.
 * Không chứa API key/cron key trong patch: tự đọc từ CronMBBank.php hiện có.
 */
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once '../../Controllers/Configs.php';
require_once 'MBBank.php';

$cronSource = @file_get_contents(__DIR__ . '/CronMBBank.php');
if ($cronSource === false) {
    http_response_code(500);
    die('Không đọc được CronMBBank.php');
}

if (!preg_match('/\$MBBANK_API_KEY\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $cronSource, $apiMatch)) {
    http_response_code(500);
    die('Không tìm thấy API key trong CronMBBank.php');
}
if (!preg_match('/\$_GET\[\'key\'\]\s*!==\s*[\'"]([^\'"]+)[\'"]/', $cronSource, $cronMatch)) {
    http_response_code(500);
    die('Không tìm thấy cron key trong CronMBBank.php');
}

$MBBANK_API_KEY = $apiMatch[1];
$CRON_KEY = $cronMatch[1];

if (!isset($_GET['key']) || !hash_equals($CRON_KEY, (string)$_GET['key'])) {
    http_response_code(403);
    die('Không có quyền truy cập!');
}

$Connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$mbBank = new MBBank($MBBANK_API_KEY, $Connect);

echo '<meta charset="utf-8">';
echo '<h2>TEST SEPAY AUTOBANK - TEAM2026</h2>';
echo '<p><b>Database:</b> ' . htmlspecialchars($servers[$currentServer]['name']) . '</p>';
echo '<p style="color:#b65d2f"><b>Trang này chỉ kiểm tra, không cộng tiền. Muốn xử lý giao dịch hãy chạy CronMBBank.php.</b></p>';

$history = $mbBank->getTransactionHistory();
if ($history === false || !isset($history['data'])) {
    echo '<p style="color:red"><b>FAIL API</b></p>';
    exit;
}

echo '<p style="color:green"><b>API OK</b></p>';

$matched = [];
foreach ($history['data'] as $transaction) {
    if (
        !empty($transaction['description']) &&
        stripos($transaction['description'], 'cauberongyiyi') !== false &&
        (int)($transaction['amount'] ?? 0) > 0
    ) {
        $matched[] = $transaction;
    }
}

echo '<p>Giao dịch phù hợp: <b>' . count($matched) . '</b></p>';
echo '<table border="1" cellpadding="6" cellspacing="0">';
echo '<tr>'
    . '<th>Code SePay</th>'
    . '<th>Số tiền</th>'
    . '<th>Nội dung</th>'
    . '<th>Account ID</th>'
    . '<th>Username</th>'
    . '<th>VND</th>'
    . '<th>Tổng nạp</th>'
    . '<th>history_bank</th>'
    . '</tr>';

foreach ($matched as $transaction) {
    $accountId = $mbBank->extractUsernameFromDescription($transaction['description']);
    $user = false;

    if ($accountId) {
        $stmt = $Connect->prepare(
            'SELECT id, username, vnd, tongnap
             FROM account
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => (int)$accountId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $exists = false;
    if (!empty($transaction['tid'])) {
        $check = $Connect->prepare(
            'SELECT id FROM history_bank WHERE code = :code LIMIT 1'
        );
        $check->execute([':code' => (string)$transaction['tid']]);
        $exists = (bool)$check->fetch(PDO::FETCH_ASSOC);
    }

    echo '<tr>';
    echo '<td>' . htmlspecialchars((string)($transaction['tid'] ?? '')) . '</td>';
    echo '<td>' . number_format((int)($transaction['amount'] ?? 0)) . '</td>';
    echo '<td>' . htmlspecialchars((string)($transaction['description'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($accountId ?: '')) . '</td>';
    echo '<td>' . ($user ? htmlspecialchars($user['username']) : '<span style="color:red">Không tìm thấy</span>') . '</td>';
    echo '<td>' . ($user ? number_format((int)$user['vnd']) : '-') . '</td>';
    echo '<td>' . ($user ? number_format((int)$user['tongnap']) : '-') . '</td>';
    echo '<td>' . ($exists ? '<span style="color:green">ĐÃ XỬ LÝ</span>' : '<span style="color:orange">CHƯA XỬ LÝ</span>') . '</td>';
    echo '</tr>';
}

echo '</table>';
