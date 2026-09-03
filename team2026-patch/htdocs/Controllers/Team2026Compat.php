<?php
/**
 * Compatibility layer: source web YiYi <-> database team2026.
 * Chỉ bổ sung/điều chỉnh schema mà web đang cần, không xóa dữ liệu và không đổi gameplay.
 */

function ensureTeam2026Compatibility(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $version = 2026090301;

    // Marker để migration chỉ chạy một lần.
    $db->exec("CREATE TABLE IF NOT EXISTS `web_compat_meta` (
        `name` varchar(100) NOT NULL,
        `version` bigint(20) NOT NULL DEFAULT 0,
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $check = $db->prepare("SELECT `version` FROM `web_compat_meta` WHERE `name` = 'team2026_yiyi_web' LIMIT 1");
    $check->execute();
    $installed = (int)($check->fetchColumn() ?: 0);
    if ($installed >= $version) {
        return;
    }

    // =========================
    // ACCOUNT
    // =========================
    $db->exec("ALTER TABLE `account`
        ADD COLUMN IF NOT EXISTS `cash` int(11) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `accountCreatedTimes` timestamp NULL DEFAULT current_timestamp(),
        ADD COLUMN IF NOT EXISTS `accountAgeDays` int(11) NOT NULL DEFAULT 45,
        ADD COLUMN IF NOT EXISTS `isFounder` tinyint(1) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `is_jail` int(11) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `role` int(11) NOT NULL DEFAULT -1,
        ADD COLUMN IF NOT EXISTS `isQuanTriVien` tinyint(1) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `Vip_Point` int(11) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `coin` int(11) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `danap` int(11) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `sotien` int(11) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `mkc2` varchar(100) NOT NULL DEFAULT '[0]',
        ADD COLUMN IF NOT EXISTS `ref_id` int(11) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `commission_claimed` int(11) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `mocnap` varchar(50) NOT NULL DEFAULT '[0,0,0,0,0]',
        ADD COLUMN IF NOT EXISTS `tong_nap2` bigint(20) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `ruby` int(11) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `count_card` int(11) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `vetuan` int(11) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `vethang` int(11) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `vethang_expire` bigint(20) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `vetuan_expire` bigint(20) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `otp` varchar(20) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `expires` datetime DEFAULT NULL");

    // Cho phép API đăng ký hiện tại INSERT username/password/ref_id mà không phải truyền các field của schema team2026.
    $db->exec("ALTER TABLE `account`
        MODIFY COLUMN `email` longtext NULL DEFAULT NULL,
        MODIFY COLUMN `token` text NULL DEFAULT NULL,
        MODIFY COLUMN `xsrf_token` text NULL DEFAULT NULL,
        MODIFY COLUMN `newpass` text NULL DEFAULT NULL,
        MODIFY COLUMN `active` int(11) NOT NULL DEFAULT 0");

    // Giữ số dư hiện có khi đưa schema cũ của web sang team2026.
    $db->exec("UPDATE `account` SET `sotien` = `vnd` WHERE COALESCE(`sotien`,0)=0 AND COALESCE(`vnd`,0)>0");
    $db->exec("UPDATE `account` SET `danap` = `tongnap` WHERE COALESCE(`danap`,0)=0 AND COALESCE(`tongnap`,0)>0");
    $db->exec("UPDATE `account` SET `isFounder`=1, `isQuanTriVien`=1 WHERE COALESCE(`is_admin`,0)=1 OR COALESCE(`admin`,0)=1");

    // =========================
    // POSTS / FORUM
    // =========================
    $db->exec("ALTER TABLE `posts`
        ADD COLUMN IF NOT EXISTS `title` varchar(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `description` longtext DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `category` varchar(50) DEFAULT '0',
        ADD COLUMN IF NOT EXISTS `comments` text DEFAULT NULL");

    // Các cột forum cũ của team2026 không được làm INSERT của source web lỗi.
    $db->exec("ALTER TABLE `posts`
        MODIFY COLUMN `tieude` varchar(75) NOT NULL DEFAULT '',
        MODIFY COLUMN `noidung` text NULL,
        MODIFY COLUMN `username` varchar(50) NOT NULL DEFAULT 'Admin'");

    $db->exec("UPDATE `posts` SET
        `title` = CASE WHEN `title` IS NULL OR `title`='' THEN `tieude` ELSE `title` END,
        `description` = CASE WHEN `description` IS NULL OR `description`='' THEN `noidung` ELSE `description` END,
        `category` = CASE WHEN `category` IS NULL OR `category`='' THEN CAST(`ghimbai` AS CHAR) ELSE `category` END,
        `comments` = CASE WHEN `comments` IS NULL OR `comments`='' THEN '[]' ELSE `comments` END");

    // Đồng bộ hai bộ tên cột để web hiện tại và dữ liệu forum team2026 cùng hoạt động.
    $db->exec("DROP TRIGGER IF EXISTS `trg_posts_yiyi_bi`");
    $db->exec("CREATE TRIGGER `trg_posts_yiyi_bi` BEFORE INSERT ON `posts` FOR EACH ROW
    BEGIN
        IF (NEW.`title` IS NULL OR NEW.`title`='') AND NEW.`tieude`<>'' THEN SET NEW.`title` = NEW.`tieude`; END IF;
        IF (NEW.`tieude` IS NULL OR NEW.`tieude`='') AND NEW.`title` IS NOT NULL THEN SET NEW.`tieude` = LEFT(NEW.`title`,75); END IF;
        IF (NEW.`description` IS NULL OR NEW.`description`='') AND NEW.`noidung` IS NOT NULL THEN SET NEW.`description` = NEW.`noidung`; END IF;
        IF NEW.`noidung` IS NULL AND NEW.`description` IS NOT NULL THEN SET NEW.`noidung` = NEW.`description`; END IF;
        IF NEW.`category` IS NULL OR NEW.`category`='' THEN SET NEW.`category` = CAST(NEW.`ghimbai` AS CHAR); END IF;
        IF COALESCE(NEW.`ghimbai`,0)=0 AND CAST(COALESCE(NEW.`category`,'0') AS SIGNED)=1 THEN SET NEW.`ghimbai`=1; END IF;
        IF NEW.`comments` IS NULL OR NEW.`comments`='' THEN SET NEW.`comments`='[]'; END IF;
        IF NEW.`username` IS NULL OR NEW.`username`='' THEN SET NEW.`username`='Admin'; END IF;
    END");

    $db->exec("DROP TRIGGER IF EXISTS `trg_posts_yiyi_bu`");
    $db->exec("CREATE TRIGGER `trg_posts_yiyi_bu` BEFORE UPDATE ON `posts` FOR EACH ROW
    BEGIN
        IF NOT (NEW.`title` <=> OLD.`title`) THEN
            SET NEW.`tieude` = LEFT(COALESCE(NEW.`title`,''),75);
        ELSEIF NOT (NEW.`tieude` <=> OLD.`tieude`) THEN
            SET NEW.`title` = NEW.`tieude`;
        END IF;
        IF NOT (NEW.`description` <=> OLD.`description`) THEN
            SET NEW.`noidung` = NEW.`description`;
        ELSEIF NOT (NEW.`noidung` <=> OLD.`noidung`) THEN
            SET NEW.`description` = NEW.`noidung`;
        END IF;
        IF NOT (NEW.`category` <=> OLD.`category`) THEN
            SET NEW.`ghimbai` = CAST(COALESCE(NEW.`category`,'0') AS SIGNED);
        ELSEIF NOT (NEW.`ghimbai` <=> OLD.`ghimbai`) THEN
            SET NEW.`category` = CAST(NEW.`ghimbai` AS CHAR);
        END IF;
        IF NEW.`comments` IS NULL OR NEW.`comments`='' THEN SET NEW.`comments`='[]'; END IF;
    END");

    // =========================
    // PAYMENTS / AUTOBANK
    // =========================
    $db->exec("ALTER TABLE `payments`
        ADD COLUMN IF NOT EXISTS `description` varchar(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `amount` int(11) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `status` varchar(50) NOT NULL DEFAULT '0',
        ADD COLUMN IF NOT EXISTS `bank` varchar(200) DEFAULT NULL");

    // Nới các field bắt buộc của schema card mới để code autobank cũ vẫn INSERT được.
    $db->exec("ALTER TABLE `payments`
        MODIFY COLUMN `refNo` varchar(255) NOT NULL DEFAULT '',
        MODIFY COLUMN `date` datetime NOT NULL DEFAULT current_timestamp(),
        MODIFY COLUMN `declared_amount` int(11) NOT NULL DEFAULT 0,
        MODIFY COLUMN `status_text` varchar(255) NOT NULL DEFAULT '',
        MODIFY COLUMN `api_status_code` varchar(50) NOT NULL DEFAULT ''");

    $db->exec("UPDATE `payments` SET
        `amount` = CASE
            WHEN COALESCE(`amount`,0)<>0 THEN `amount`
            WHEN COALESCE(`final_credited_amount`,0)<>0 THEN `final_credited_amount`
            WHEN COALESCE(`detected_value`,0)<>0 THEN `detected_value`
            WHEN COALESCE(`received_amount_from_api`,0)<>0 THEN `received_amount_from_api`
            ELSE COALESCE(`declared_amount`,0)
        END,
        `status` = CASE WHEN COALESCE(`is_credited`,0)=1 THEN '1' ELSE COALESCE(NULLIF(`api_status_code`,''),'0') END,
        `description` = COALESCE(`description`,`api_message`),
        `bank` = COALESCE(`bank`,`card_telco`)");

    $db->exec("DROP TRIGGER IF EXISTS `trg_payments_yiyi_bi`");
    $db->exec("CREATE TRIGGER `trg_payments_yiyi_bi` BEFORE INSERT ON `payments` FOR EACH ROW
    BEGIN
        IF COALESCE(NEW.`amount`,0)=0 AND COALESCE(NEW.`declared_amount`,0)>0 THEN SET NEW.`amount`=NEW.`declared_amount`; END IF;
        IF COALESCE(NEW.`declared_amount`,0)=0 AND COALESCE(NEW.`amount`,0)>0 THEN SET NEW.`declared_amount`=NEW.`amount`; END IF;
        IF NEW.`status_text`='' THEN SET NEW.`status_text`=COALESCE(NEW.`status`,'0'); END IF;
        IF NEW.`api_status_code`='' THEN SET NEW.`api_status_code`=COALESCE(NEW.`status`,'0'); END IF;
        IF NEW.`bank` IS NULL AND NEW.`card_telco` IS NOT NULL THEN SET NEW.`bank`=NEW.`card_telco`; END IF;
    END");

    // =========================
    // TABLES WEB PHỤ TRỢ
    // =========================
    $db->exec("CREATE TABLE IF NOT EXISTS `vp_bank` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tid` varchar(100) NOT NULL,
        `description` varchar(255) DEFAULT NULL,
        `amount` int(11) NOT NULL DEFAULT 0,
        `username` varchar(50) DEFAULT NULL,
        `status` tinyint(4) DEFAULT 1,
        `time` datetime DEFAULT current_timestamp(),
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_vp_bank_tid` (`tid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `history_active` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `created_at` datetime DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `history_bank` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `amount_vnd` double DEFAULT 0,
        `amount_cash` double DEFAULT 0,
        `description` text DEFAULT NULL,
        `code` varchar(100) DEFAULT NULL,
        `created_at` datetime DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `history_exchange` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(100) NOT NULL,
        `amount_vnd` bigint(20) NOT NULL DEFAULT 0,
        `gold_received` bigint(20) NOT NULL DEFAULT 0,
        `rate` decimal(5,2) NOT NULL DEFAULT 1.00,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `moc_nap` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `amount` bigint(20) NOT NULL DEFAULT 0,
        `reward_json` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Số tài khoản ngân hàng không được dùng INT (có thể dài > 10 chữ số).
    $db->exec("ALTER TABLE `settings` MODIFY COLUMN `NumberBank` varchar(50) DEFAULT NULL");

    $mark = $db->prepare("INSERT INTO `web_compat_meta` (`name`,`version`) VALUES ('team2026_yiyi_web', :v)
        ON DUPLICATE KEY UPDATE `version`=VALUES(`version`), `updated_at`=CURRENT_TIMESTAMP");
    $mark->execute(['v' => $version]);
}
