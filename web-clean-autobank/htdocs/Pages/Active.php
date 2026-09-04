<?php
// Quy đổi trên web đã tắt.
// Nạp tiền/autobank vẫn hoạt động tại trang Payments.
// Người chơi dùng số dư VND để quy đổi trực tiếp trong game.
require_once '../Controllers/Configs.php';

if (!$Login) {
    header('Location: /Auth/Lor#login');
    exit;
}

header('Location: /Users/Payments');
exit;
