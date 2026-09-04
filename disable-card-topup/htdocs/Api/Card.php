<?php
require_once '../Controllers/Configs.php';

header('Content-Type: application/json; charset=utf-8');
http_response_code(403);

echo json_encode([
    'success' => false,
    'status' => 'disabled',
    'message' => 'Chức năng nạp thẻ cào tự động đã được tắt. Vui lòng nạp bằng chuyển khoản ngân hàng / Autobank.'
], JSON_UNESCAPED_UNICODE);
exit;
