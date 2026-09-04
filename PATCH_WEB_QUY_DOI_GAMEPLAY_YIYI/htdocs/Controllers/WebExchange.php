<?php
/**
 * Quy đổi trực tiếp từ website theo đúng gameplay Team2026:
 * - 10.000 VNĐ = 40 Thỏi vàng (item 457)
 * - 10.000 VNĐ = 10.000 Ngọc xanh
 * - Mỗi 10.000 VNĐ: +10 Vé tặng ngọc (item 718) +50 điểm sự kiện
 *
 * Website chỉ trừ account.vnd và ghi hàng đợi.
 * Server game là nơi cộng vật phẩm/ngọc vào RAM nhân vật để tránh bị save đè.
 */

function ensureWebExchangeSchema(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS `web_exchange_queue` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `request_key` CHAR(32) NOT NULL,
            `account_id` INT NOT NULL,
            `player_id` INT NULL,
            `username` VARCHAR(100) NOT NULL,
            `exchange_type` VARCHAR(20) NOT NULL,
            `amount_vnd` INT UNSIGNED NOT NULL,
            `reward_amount` BIGINT UNSIGNED NOT NULL,
            `ticket_amount` INT UNSIGNED NOT NULL DEFAULT 0,
            `event_point_amount` INT UNSIGNED NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'PENDING',
            `note` VARCHAR(255) NULL,
            `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `claimed_at` DATETIME NULL,
            `processed_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_web_exchange_request` (`request_key`),
            KEY `idx_web_exchange_account_status` (`account_id`, `status`, `id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $done = true;
}

function webExchangeReward(string $type, int $amount): array
{
    $ticket = intdiv($amount, 10000) * 10;
    $eventPoint = intdiv($amount, 10000) * 50;

    if ($type === 'goldbar') {
        return [
            'reward' => intdiv($amount, 1000) * 4,
            'ticket' => $ticket,
            'event_point' => $eventPoint,
        ];
    }

    if ($type === 'gem') {
        return [
            'reward' => $amount,
            'ticket' => $ticket,
            'event_point' => $eventPoint,
        ];
    }

    throw new InvalidArgumentException('Loại quy đổi không hợp lệ');
}

function webExchangeTypeLabel(string $type): string
{
    return $type === 'goldbar' ? 'Thỏi vàng' : ($type === 'gem' ? 'Ngọc xanh' : $type);
}

function webExchangeStatusLabel(string $status): string
{
    return match ($status) {
        'PENDING' => 'Chờ server nhận',
        'PROCESSING' => 'Đang xử lý',
        'WAITING_BAG' => 'Chờ trống hành trang',
        'WAITING_LIMIT' => 'Chờ giảm số dư ngọc',
        'DONE' => 'Đã nhận',
        'ERROR' => 'Cần kiểm tra',
        default => $status,
    };
}
