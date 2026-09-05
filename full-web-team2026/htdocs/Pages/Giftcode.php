<?php
include '../Controllers/Header.php';

// ID giftcode muốn ẩn
$hiddenGiftcodeIds = [6, 8];

if (!empty($_GET['hide'])) {
    $idsFromUrl = array_map('intval', explode(',', $_GET['hide']));
    $hiddenGiftcodeIds = array_unique(array_merge($hiddenGiftcodeIds, $idsFromUrl));
}

// team2026 dùng cột "detail"; source web cũ dùng "item".
// SELECT * để tương thích cả hai schema, tránh lỗi Unknown column 'item'.
$gcs = $Connect->query("
    SELECT *
    FROM giftcode
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$itemStmt = $Connect->prepare("
    SELECT NAME AS name, icon_id
    FROM item_template
    WHERE id = ?
    LIMIT 1
");
?>

<div class="body" style="font-family:'Segoe UI',sans-serif; background:#fff3e0; padding:20px;">
  <div style="max-width:1100px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.08);">
    <h2 style="padding:14px 18px; margin:0; background:#f39c12; color:#fff; border-radius:10px 10px 0 0;">
      Giftcode Free
    </h2>

    <table style="width:100%; border-collapse:collapse;">
      <thead>
        <tr style="background:#ffe0b2;">
          <th style="padding:12px; text-align:left; border-bottom:1px solid #e8e8e8;">Giftcode</th>
          <th style="padding:12px; text-align:left; border-bottom:1px solid #e8e8e8;">Danh sách vật phẩm</th>
          <th style="padding:12px; text-align:center; border-bottom:1px solid #e8e8e8;">Lượt còn</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($gcs as $gc): ?>
        <?php
        if (in_array((int)$gc['id'], $hiddenGiftcodeIds, true)) {
            continue;
        }

        $rawItems = $gc['detail'] ?? $gc['item'] ?? '[]';
        $items = json_decode($rawItems, true);
        if (!is_array($items)) {
            $items = [];
        }
        ?>
        <tr>
          <td style="vertical-align:top; padding:12px; border-bottom:1px solid #f2f2f2; font-weight:600; color:#333;">
            <?= htmlspecialchars((string)($gc['code'] ?? '')) ?>
            <div style="font-size:12px; color:#999;">ID: <?= (int)$gc['id'] ?></div>
          </td>

          <td style="padding:12px; border-bottom:1px solid #f2f2f2;">
            <?php if (!$items): ?>
              <em style="color:#999;">(Chưa có vật phẩm)</em>
            <?php else: ?>
              <div style="display:flex; flex-wrap:wrap; gap:10px;">
                <?php foreach ($items as $it): ?>
                  <?php
                  $itemId = (int)($it['id'] ?? -1);
                  $quantity = (int)($it['quantity'] ?? 0);
                  if ($itemId < 0) continue;

                  $itemStmt->execute([$itemId]);
                  $tpl = $itemStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                  $name = $tpl['name'] ?? ('Item ' . $itemId);
                  $icon = (int)($tpl['icon_id'] ?? 0);
                  $iconUrl = "/images/x4/" . $icon . ".png";
                  $fallback = "/images/x4/" . $icon . ".gif";
                  ?>
                  <div style="display:flex;align-items:center;gap:8px;background:#fff8ec;border:1px solid #ffe0b2;border-radius:8px;padding:6px 10px;">
                    <img
                      src="<?= htmlspecialchars($iconUrl) ?>"
                      alt="<?= htmlspecialchars($name) ?>"
                      style="width:28px;height:28px;object-fit:contain;image-rendering:pixelated;"
                      onerror="this.onerror=null;this.src='<?= htmlspecialchars($fallback) ?>';"
                    >
                    <span style="color:#444;">
                      <?= htmlspecialchars($name) ?> x<?= number_format($quantity) ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </td>

          <td style="padding:12px; text-align:center; border-bottom:1px solid #f2f2f2; font-weight:600;">
            <?= isset($gc['count_left']) ? number_format((int)$gc['count_left']) : '-' ?>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (!$gcs): ?>
        <tr><td colspan="3" style="padding:20px;text-align:center;color:#999;">Chưa có giftcode.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../Controllers/Footer.php'; ?>
