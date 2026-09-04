<?php
// Patch cũ đã bị gỡ. File này cố ý không còn tạo bài viết tự động.
if (!function_exists('ensureGameplayPost')) {
    function ensureGameplayPost(PDO $Connect): void {}
}
