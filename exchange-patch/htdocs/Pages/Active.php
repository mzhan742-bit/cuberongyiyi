<?php
ob_start();
include '../Controllers/Header.php';

if (empty($_SESSION['ImSynZx_Login'])) {
    header('Location: /Auth/Lor#login');
    exit;
}

$username = $_SESSION['ImSynZx_Login'];
$currentTime = date('Y-m-d H:i:s');

function ensureExchangeQueue(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS web_exchange_queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            account_id INT NOT NULL,
            username VARCHAR(100) NOT NULL,
            exchange_type VARCHAR(20) NOT NULL,
            amount_vnd BIGINT NOT NULL,
            reward_amount BIGINT NOT NULL,
            ticket_amount INT NOT NULL DEFAULT 0,
            event_points INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
            error_message VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processing_at DATETIME DEFAULT NULL,
            processed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_exchange_status (status, id),
            KEY idx_exchange_account (account_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
ensureExchangeQueue($Connect);

if (empty($_SESSION['exchange_csrf'])) {
    $_SESSION['exchange_csrf'] = bin2hex(random_bytes(24));
}

$message = $_SESSION['exchange_message'] ?? '';
$messageType = $_SESSION['exchange_message_type'] ?? 'ok';
unset($_SESSION['exchange_message'], $_SESSION['exchange_message_type']);

$stmt = $Connect->prepare("SELECT id, vnd, active FROM account WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$vndBalance = (int)($userData['vnd'] ?? 0);
$accountId = (int)($userData['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exchange_vnd'], $_POST['exchange_type'])) {
    $amount = (int)$_POST['exchange_vnd'];
    $type = $_POST['exchange_type'] === 'gem' ? 'GEM' : 'GOLD_BAR';
    $csrf = (string)($_POST['csrf'] ?? '');

    try {
        if (!hash_equals($_SESSION['exchange_csrf'], $csrf)) {
            throw new RuntimeException('Phiên xác nhận không hợp lệ, vui lòng tải lại trang.');
        }
        if ($amount < 10000 || $amount > 5000000) {
            throw new RuntimeException('Mỗi lần quy đổi từ 10.000₫ đến 5.000.000₫.');
        }

        $Connect->beginTransaction();

        $lock = $Connect->prepare("SELECT id, username, vnd, active FROM account WHERE username = ? FOR UPDATE");
        $lock->execute([$username]);
        $account = $lock->fetch(PDO::FETCH_ASSOC);
        if (!$account) {
            throw new RuntimeException('Tài khoản không tồn tại.');
        }
        if ((int)$account['vnd'] < $amount) {
            throw new RuntimeException('Số dư VNĐ không đủ.');
        }
        if ($type === 'GOLD_BAR' && (int)$account['active'] !== 1) {
            throw new RuntimeException('Hãy kích hoạt tài khoản trước khi đổi Thỏi vàng.');
        }

        $char = $Connect->prepare("SELECT id FROM player WHERE account_id = ? LIMIT 1");
        $char->execute([(int)$account['id']]);
        if (!$char->fetchColumn()) {
            throw new RuntimeException('Bạn cần tạo nhân vật trong game trước khi quy đổi.');
        }

        // Công thức đúng theo Input.java Team2026.
        $rewardAmount = $type === 'GOLD_BAR' ? intdiv($amount, 1000) * 4 : $amount;
        $ticketAmount = intdiv($amount, 10000) * 10;
        $eventPoints = intdiv($amount, 10000) * 50;

        $debit = $Connect->prepare("UPDATE account SET vnd = vnd - ? WHERE id = ? AND vnd >= ?");
        $debit->execute([$amount, (int)$account['id'], $amount]);
        if ($debit->rowCount() !== 1) {
            throw new RuntimeException('Số dư vừa thay đổi, vui lòng thử lại.');
        }

        $insert = $Connect->prepare("
            INSERT INTO web_exchange_queue
            (account_id, username, exchange_type, amount_vnd, reward_amount, ticket_amount, event_points, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', NOW())
        ");
        $insert->execute([
            (int)$account['id'], $account['username'], $type, $amount,
            $rewardAmount, $ticketAmount, $eventPoints
        ]);

        if ($type === 'GOLD_BAR') {
            try {
                $log = $Connect->prepare("
                    INSERT INTO history_exchange (username, amount_vnd, gold_received, rate, created_at)
                    VALUES (?, ?, ?, 4.00, NOW())
                ");
                $log->execute([$account['username'], $amount, $rewardAmount]);
            } catch (Throwable $ignored) {}
        }

        $Connect->commit();
        $_SESSION['exchange_message_type'] = 'ok';
        $_SESSION['exchange_message'] = $type === 'GOLD_BAR'
            ? 'Đã gửi: ' . number_format($amount) . '₫ → ' . number_format($rewardAmount) . ' Thỏi vàng. Mở game để nhận.'
            : 'Đã gửi: ' . number_format($amount) . '₫ → ' . number_format($rewardAmount) . ' Ngọc xanh. Mở game để nhận.';
    } catch (Throwable $e) {
        if ($Connect->inTransaction()) $Connect->rollBack();
        $_SESSION['exchange_message_type'] = 'error';
        $_SESSION['exchange_message'] = $e->getMessage();
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$stmt = $Connect->prepare("SELECT vnd FROM account WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$vndBalance = (int)$stmt->fetchColumn();

$history = $Connect->prepare("
    SELECT exchange_type, amount_vnd, reward_amount, ticket_amount, event_points, status, error_message, created_at
    FROM web_exchange_queue WHERE account_id = ? ORDER BY id DESC LIMIT 10
");
$history->execute([$accountId]);
$rows = $history->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
.exchange-wrap{max-width:850px;margin:0 auto 30px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,.12)}
.exchange-head{background:#fb8c00;color:#fff;text-align:center;padding:15px;font-size:22px;font-weight:700}
.exchange-body{padding:22px}.exchange-info{background:#fff3e0;border-radius:9px;padding:12px;margin-bottom:16px;text-align:center}
.exchange-tabs{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin:15px 0}
.exchange-card{flex:1;min-width:260px;border:1px solid #ffcc80;border-radius:10px;padding:16px;background:#fffaf2}
.exchange-card h3{margin-top:0;color:#e65100}.exchange-card input{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ffb74d;border-radius:7px}
.exchange-card button{margin-top:10px;width:100%;padding:10px;border:0;border-radius:7px;background:#fb8c00;color:white;font-weight:700;cursor:pointer}
.exchange-msg{padding:10px;border-radius:7px;margin-bottom:15px;text-align:center}.exchange-ok{background:#e8f5e9;color:#1b5e20}.exchange-error{background:#ffebee;color:#b71c1c}
.exchange-table{width:100%;border-collapse:collapse;font-size:13px;margin-top:18px}.exchange-table th,.exchange-table td{border-bottom:1px solid #eee;padding:8px;text-align:center}
.status-PENDING{color:#ef6c00;font-weight:700}.status-PROCESSING{color:#1565c0;font-weight:700}.status-DONE{color:#2e7d32;font-weight:700}.status-FAILED{color:#c62828;font-weight:700}
.small-note{font-size:13px;color:#555;line-height:1.5}
</style>

<div class="exchange-wrap">
  <div class="exchange-head">QUY ĐỔI THEO GAMEPLAY TEAM2026</div>
  <div class="exchange-body">
    <?php if ($message !== ''): ?>
      <div class="exchange-msg <?= $messageType === 'error' ? 'exchange-error' : 'exchange-ok' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <div class="exchange-info">
      <b>Tài khoản:</b> <?= htmlspecialchars($username) ?> &nbsp; | &nbsp; <b>Số dư:</b> <?= number_format($vndBalance) ?>₫<br>
      <span class="small-note">Online nhận sau vài giây; offline sẽ tự nhận khi đăng nhập game.</span>
    </div>
    <div class="exchange-tabs">
      <form method="POST" class="exchange-card">
        <h3>Đổi Thỏi vàng</h3><p><b>10.000 VNĐ = 40 Thỏi vàng ID 457</b></p>
        <p class="small-note">Mỗi 10.000 VNĐ kèm 10 Vé ID 718 + 50 điểm sự kiện.</p>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['exchange_csrf']) ?>">
        <input type="hidden" name="exchange_type" value="gold">
        <input type="number" name="exchange_vnd" min="10000" max="5000000" placeholder="10.000 - 5.000.000" required>
        <button type="submit">ĐỔI THỎI VÀNG</button>
      </form>
      <form method="POST" class="exchange-card">
        <h3>Đổi Ngọc xanh</h3><p><b>10.000 VNĐ = 10.000 Ngọc xanh</b></p>
        <p class="small-note">Mỗi 10.000 VNĐ kèm 10 Vé ID 718 + 50 điểm sự kiện.</p>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['exchange_csrf']) ?>">
        <input type="hidden" name="exchange_type" value="gem">
        <input type="number" name="exchange_vnd" min="10000" max="5000000" placeholder="10.000 - 5.000.000" required>
        <button type="submit">ĐỔI NGỌC XANH</button>
      </form>
    </div>
    <table class="exchange-table">
      <thead><tr><th>Loại</th><th>VNĐ</th><th>Nhận</th><th>Vé</th><th>Điểm SK</th><th>Trạng thái</th><th>Thời gian</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= $r['exchange_type'] === 'GOLD_BAR' ? 'Thỏi vàng' : 'Ngọc xanh' ?></td>
          <td><?= number_format((int)$r['amount_vnd']) ?></td><td><?= number_format((int)$r['reward_amount']) ?></td>
          <td><?= number_format((int)$r['ticket_amount']) ?></td><td><?= number_format((int)$r['event_points']) ?></td>
          <td class="status-<?= htmlspecialchars($r['status']) ?>" title="<?= htmlspecialchars((string)($r['error_message'] ?? '')) ?>"><?= htmlspecialchars($r['status']) ?></td>
          <td><?= htmlspecialchars($r['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7">Chưa có giao dịch quy đổi.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include '../Controllers/Footer.php'; ?>
<?php ob_end_flush(); ?>
