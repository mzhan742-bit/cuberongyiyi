<?php
include '../Controllers/Header.php';
require_once __DIR__ . '/../Controllers/WebExchange.php';

try {
    ensureWebExchangeSchema($Connect);
} catch (Throwable $e) {
    echo '<div class="body"><div class="box_list_chuyenmuc"><div class="box_midss"><div class="box_detai" style="padding:10px;color:red;">Không thể khởi tạo chức năng quy đổi. Kiểm tra quyền database.</div></div></div></div>';
    include '../Controllers/Footer.php';
    exit;
}

$accountId = (int)$ImS['id'];
$balance = (int)($ImS['vnd'] ?? 0);
$csrf = generateToken();
$requestKey = bin2hex(random_bytes(16));

$stmt = $Connect->prepare("
    SELECT id, exchange_type, amount_vnd, reward_amount, ticket_amount,
           event_point_amount, status, requested_at, processed_at
    FROM web_exchange_queue
    WHERE account_id = :account_id
    ORDER BY id DESC
    LIMIT 20
");
$stmt->execute([':account_id' => $accountId]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="body">
    <table width="100%" border="0" cellspacing="0">
        <tbody>
            <tr class="menu1">
                <td id="selected" style="background-color:#ff5601;">Quy Đổi Trực Tiếp</td>
            </tr>
        </tbody>
    </table>

    <div class="box_list_chuyenmuc">
        <div class="box_midss">
            <div class="box_detai" style="padding:12px;">
                <div style="text-align:center;margin-bottom:12px;">
                    <b>Số dư hiện tại:</b>
                    <span id="currentBalance" style="color:#d9480f;font-size:18px;font-weight:bold;">
                        <?= number_format($balance) ?> VNĐ
                    </span>
                    <div style="margin-top:6px;">
                        <a href="/Users/Payments">Nạp tiền</a>
                    </div>
                </div>

                <div style="background:#fff7e6;border:1px solid #ffc078;border-radius:8px;padding:10px;margin-bottom:12px;">
                    <b>Cơ chế đúng theo gameplay hiện tại</b><br>
                    • 10.000 VNĐ = <b>40 Thỏi vàng</b> (Item ID 457)<br>
                    • 10.000 VNĐ = <b>10.000 Ngọc xanh</b><br>
                    • Mỗi 10.000 VNĐ quy đổi nhận thêm <b>10 Vé tặng ngọc</b> (ID 718) + <b>50 điểm sự kiện</b><br>
                    • Nhân vật đang online: server tự nhận giao dịch sau vài giây.<br>
                    • Nhân vật offline: tự nhận khi đăng nhập.
                </div>

                <form id="exchangeForm" method="post" action="/Api/Exchange">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="request_key" id="requestKey" value="<?= htmlspecialchars($requestKey) ?>">

                    <table style="width:100%;max-width:560px;margin:0 auto;">
                        <tr>
                            <td style="width:38%;"><b>Đổi sang</b></td>
                            <td>
                                <select name="type" id="exchangeType" style="width:100%;padding:7px;">
                                    <option value="goldbar">Thỏi vàng ID 457</option>
                                    <option value="gem">Ngọc xanh</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td><b>Số VNĐ muốn đổi</b></td>
                            <td>
                                <input
                                    type="number"
                                    name="amount"
                                    id="exchangeAmount"
                                    min="10000"
                                    max="5000000"
                                    step="10000"
                                    value="10000"
                                    style="width:100%;padding:7px;"
                                    required
                                >
                            </td>
                        </tr>
                        <tr>
                            <td><b>Dự kiến nhận</b></td>
                            <td>
                                <div id="previewReward" style="font-weight:bold;color:#d9480f;"></div>
                                <small id="previewBonus"></small>
                            </td>
                        </tr>
                        <tr>
                            <td></td>
                            <td style="padding-top:10px;text-align:center;">
                                <button type="submit" id="exchangeSubmit" class="w3-button w3-red">
                                    Xác nhận quy đổi
                                </button>
                            </td>
                        </tr>
                    </table>
                </form>

                <div id="exchangeMessage" style="text-align:center;font-weight:bold;margin-top:10px;"></div>

                <hr>
                <h3>Lịch sử quy đổi</h3>
                <div style="overflow-x:auto;">
                    <table width="100%" border="1" cellspacing="0"
                           style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;">
                        <thead>
                            <tr style="background:#b65d2f;color:#fff;">
                                <th style="padding:8px;">Mã</th>
                                <th style="padding:8px;">Loại</th>
                                <th style="padding:8px;">VNĐ</th>
                                <th style="padding:8px;">Nhận</th>
                                <th style="padding:8px;">Trạng thái</th>
                                <th style="padding:8px;">Thời gian</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$history): ?>
                            <tr><td colspan="6" style="padding:10px;text-align:center;">Chưa có giao dịch.</td></tr>
                        <?php else: ?>
                            <?php foreach ($history as $row): ?>
                                <tr>
                                    <td style="padding:8px;text-align:center;">#<?= (int)$row['id'] ?></td>
                                    <td style="padding:8px;text-align:center;"><?= htmlspecialchars(webExchangeTypeLabel($row['exchange_type'])) ?></td>
                                    <td style="padding:8px;text-align:right;"><?= number_format((int)$row['amount_vnd']) ?></td>
                                    <td style="padding:8px;text-align:right;"><?= number_format((int)$row['reward_amount']) ?></td>
                                    <td style="padding:8px;text-align:center;font-weight:bold;"><?= htmlspecialchars(webExchangeStatusLabel($row['status'])) ?></td>
                                    <td style="padding:8px;text-align:center;"><?= htmlspecialchars($row['processed_at'] ?: $row['requested_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../Controllers/Footer.php'; ?>

<script>
(function () {
    const type = document.getElementById('exchangeType');
    const amount = document.getElementById('exchangeAmount');
    const reward = document.getElementById('previewReward');
    const bonus = document.getElementById('previewBonus');
    const form = document.getElementById('exchangeForm');
    const message = document.getElementById('exchangeMessage');
    const submit = document.getElementById('exchangeSubmit');

    function fmt(n) {
        return new Intl.NumberFormat('vi-VN').format(n);
    }

    function refreshPreview() {
        const v = Math.max(0, parseInt(amount.value || '0', 10));
        const ticket = Math.floor(v / 10000) * 10;
        const eventPoint = Math.floor(v / 10000) * 50;

        if (type.value === 'goldbar') {
            reward.textContent = fmt(Math.floor(v / 1000) * 4) + ' Thỏi vàng';
        } else {
            reward.textContent = fmt(v) + ' Ngọc xanh';
        }

        bonus.textContent = '+ ' + fmt(ticket) + ' Vé tặng ngọc + ' + fmt(eventPoint) + ' điểm sự kiện';
    }

    type.addEventListener('change', refreshPreview);
    amount.addEventListener('input', refreshPreview);
    refreshPreview();

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        submit.disabled = true;
        message.style.color = '#d9480f';
        message.textContent = 'Đang gửi giao dịch sang server game...';

        try {
            const body = new FormData(form);
            const res = await fetch('/Api/Exchange', {
                method: 'POST',
                body,
                credentials: 'same-origin'
            });

            const data = await res.json();
            if (data.status === 'success') {
                message.style.color = 'green';
                message.textContent = data.message + ' Đang cập nhật...';
                setTimeout(() => window.location.reload(), 1400);
            } else {
                message.style.color = 'red';
                message.textContent = data.message || 'Quy đổi thất bại.';
                submit.disabled = false;
            }
        } catch (err) {
            message.style.color = 'red';
            message.textContent = 'Không kết nối được API quy đổi. Vui lòng thử lại.';
            submit.disabled = false;
        }
    });
})();
</script>
