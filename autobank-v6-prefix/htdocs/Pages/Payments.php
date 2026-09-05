<?php
include '../Controllers/Header.php';

$username = $ImS['username'];
$userId   = (int)$ImS['id'];

$limit = 10;
$page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$start = ($page - 1) * $limit;

$Connect->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

$stmt = $Connect->prepare("
    SELECT
        id,
        amount_cash AS amount,
        1 AS status,
        created_at AS time
    FROM history_bank
    WHERE username = :uname
    ORDER BY id DESC
    LIMIT :start, :limit
");
$stmt->bindValue(':uname', $username, PDO::PARAM_STR);
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$totalStmt = $Connect->prepare("
    SELECT COUNT(*) AS total
    FROM history_bank
    WHERE username = :uname
");
$totalStmt->execute([':uname' => $username]);
$totalRows  = (int)$totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = (int)ceil($totalRows / $limit);

// Theo đúng nội dung giao dịch thực tế SePay/OCB hiện đang trả.
$chutaikhoan = 'NGUYEN MINH CHIEN';
$stk         = '0946885442';
$tennh       = 'OCB';

$MEMO_PREFIX = 'lycafethu';
$memo = $MEMO_PREFIX . $userId;

$qrParams = [
    'bank' => 'OCB',
    'acc' => 'SEPMC2104',
    'template' => 'compact',
    'showinfo' => 'false',
    'holder' => $chutaikhoan,
    'des' => $memo,
];

$qrUrl = 'https://vietqr.app/img?' . http_build_query(
    $qrParams,
    '',
    '&',
    PHP_QUERY_RFC3986
);
?>
<div class="body">
    <table width="100%" border="0" cellspacing="0">
        <tbody>
            <tr class="menu1">
                <td id="selected" style="width:100%; background-color:#ff5601;">
                    Nạp Tiền Tự Động
                </td>
            </tr>
        </tbody>
    </table>

    <div class="box_list_chuyenmuc">
        <div class="box_midss">
            <div class="box_detai" style="padding:5px;">
                <center>
                    <b style="color:green">CHUYỂN KHOẢN / AUTOBANK</b><br>
                    <span style="color:#b65d2f;">
                        Hệ thống chỉ nhận nạp qua chuyển khoản ngân hàng.
                    </span>
                </center>

                <div style="max-width:500px;margin:15px auto 0;display:flex;align-items:center;border:1px solid #ccc;border-radius:8px;overflow:hidden;box-shadow:0 4px 10px rgba(0,0,0,.1);">
                    <div style="flex:0 0 40%;background:#f5f5f5;text-align:center;padding:10px;">
                        <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Code" style="max-width:100%;height:auto;">
                    </div>

                    <div style="flex:1;padding:10px;font-family:'Times New Roman',serif;">
                        <p style="margin:5px 0;"><b>Tên Tài Khoản:</b> <?= htmlspecialchars($chutaikhoan) ?></p>
                        <p style="margin:5px 0;"><b>Số Tài Khoản:</b> <?= htmlspecialchars($stk) ?></p>
                        <p style="margin:5px 0;"><b>Ngân Hàng:</b> <?= htmlspecialchars($tennh) ?></p>
                        <p style="margin:5px 0;">
                            <b>Nội Dung:</b>
                            <span id="nd"><?= htmlspecialchars($memo) ?></span>
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('nd').innerText)">Copy</button>
                        </p>

                        <p style="margin:10px 0;"><b>Nạp ATM được cộng thêm 10%</b></p>
                        <p style="margin:10px 0;"><b>Nạp ATM tối thiểu 10.000đ mới cộng</b></p>
                    </div>
                </div>

                <br><hr>

                <h3>Lịch Sử Chuyển Khoản</h3>

                <table width="100%" border="1" cellspacing="0" style="width:100%;border-collapse:collapse;background:#fff;">
                    <thead>
                        <tr style="background:#2a9d8f;color:#fff;font-weight:bold;">
                            <th style="padding:12px;text-align:center;">Số Tiền</th>
                            <th style="padding:12px;text-align:center;">Trạng Thái</th>
                            <th style="padding:12px;text-align:center;">Thời Gian</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td style="padding:12px;text-align:center;"><?= Money($row['amount']) ?></td>
                                <td style="padding:12px;text-align:center;color:#2a9d8f;font-weight:bold;">Thành Công</td>
                                <td style="padding:12px;text-align:center;"><?= htmlspecialchars((string)$row['time']) ?></td>
                            </tr>
                        <?php endwhile; ?>

                        <?php if ($totalRows === 0): ?>
                            <tr><td colspan="3" style="padding:20px;text-align:center;color:#999;">Chưa có giao dịch chuyển khoản.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                    <div style="text-align:center;padding:15px 0;">
                        <?php for ($i=1; $i<=$totalPages; $i++): ?>
                            <a href="?page=<?= $i ?>" style="display:inline-block;padding:6px 12px;margin-right:4px;text-decoration:none;border:1px solid #2a9d8f;border-radius:4px;">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../Controllers/Footer.php'; ?>
