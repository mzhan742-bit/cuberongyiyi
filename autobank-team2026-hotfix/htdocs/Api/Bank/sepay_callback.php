<?php
/**
 * Webhook cũ tắt để tránh cộng trùng / giả mạo.
 * Autobank chính thức của source đang dùng CronMBBank.php + SePay API pull.
 */
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'Webhook cũ đã tắt. Hệ thống sử dụng CronMBBank.php + SePay API.'
], JSON_UNESCAPED_UNICODE);
exit;
