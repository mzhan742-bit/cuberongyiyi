<?php
/**
 * Các đồng bộ bổ sung sau migration chính.
 */
function ensureTeam2026CompatibilityFix(PDO $db): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $version = 2026090302;
    $check = $db->prepare("SELECT `version` FROM `web_compat_meta` WHERE `name`='team2026_yiyi_web_fix' LIMIT 1");
    $check->execute();
    if ((int)($check->fetchColumn() ?: 0) >= $version) return;

    // Khi ADD category, MariaDB gán default 0 cho bài cũ. Đồng bộ lại đúng trạng thái ghim của team2026.
    $db->exec("UPDATE `posts` SET `category` = CAST(COALESCE(`ghimbai`,0) AS CHAR)");

    // Tài khoản do web tạo vẫn giữ giá trị chuỗi rỗng giống dữ liệu team2026 hiện tại, tránh NULL làm code game cũ khó xử lý.
    $db->exec("UPDATE `account` SET `email`='' WHERE `email` IS NULL");
    $db->exec("UPDATE `account` SET `token`='' WHERE `token` IS NULL");
    $db->exec("UPDATE `account` SET `xsrf_token`='' WHERE `xsrf_token` IS NULL");
    $db->exec("UPDATE `account` SET `newpass`='' WHERE `newpass` IS NULL");
    $db->exec("ALTER TABLE `account`
        MODIFY COLUMN `email` longtext NOT NULL DEFAULT '',
        MODIFY COLUMN `token` text NOT NULL DEFAULT '',
        MODIFY COLUMN `xsrf_token` text NOT NULL DEFAULT '',
        MODIFY COLUMN `newpass` text NOT NULL DEFAULT ''");

    // Đồng bộ referral native team2026 <-> ref_id của source web.
    $db->exec("UPDATE `account` SET `ref_id`=`gioithieu`
        WHERE COALESCE(`ref_id`,0)=0 AND COALESCE(`gioithieu`,0)>0");
    $db->exec("UPDATE `account` SET `gioithieu`=`ref_id`
        WHERE COALESCE(`ref_id`,0)>0 AND COALESCE(`gioithieu`,0)=0");

    // vnd/tongnap là field team2026; sotien/danap là field mà một số module autobank cũ dùng.
    // Trigger giữ 2 cặp luôn đồng bộ để ACB/MBBank/SePay không cộng vào hai ví khác nhau.
    $db->exec("DROP TRIGGER IF EXISTS `trg_account_yiyi_bi`");
    $db->exec("CREATE TRIGGER `trg_account_yiyi_bi` BEFORE INSERT ON `account` FOR EACH ROW
    BEGIN
        IF COALESCE(NEW.`sotien`,0)=0 AND COALESCE(NEW.`vnd`,0)>0 THEN SET NEW.`sotien`=NEW.`vnd`; END IF;
        IF COALESCE(NEW.`vnd`,0)=0 AND COALESCE(NEW.`sotien`,0)>0 THEN SET NEW.`vnd`=NEW.`sotien`; END IF;
        IF COALESCE(NEW.`danap`,0)=0 AND COALESCE(NEW.`tongnap`,0)>0 THEN SET NEW.`danap`=NEW.`tongnap`; END IF;
        IF COALESCE(NEW.`tongnap`,0)=0 AND COALESCE(NEW.`danap`,0)>0 THEN SET NEW.`tongnap`=NEW.`danap`; END IF;
        IF COALESCE(NEW.`ref_id`,0)=0 AND COALESCE(NEW.`gioithieu`,0)>0 THEN SET NEW.`ref_id`=NEW.`gioithieu`; END IF;
        IF COALESCE(NEW.`gioithieu`,0)=0 AND COALESCE(NEW.`ref_id`,0)>0 THEN SET NEW.`gioithieu`=NEW.`ref_id`; END IF;
        IF COALESCE(NEW.`is_admin`,0)=1 OR COALESCE(NEW.`admin`,0)=1 THEN
            SET NEW.`isFounder`=1, NEW.`isQuanTriVien`=1;
        END IF;
    END");

    $db->exec("DROP TRIGGER IF EXISTS `trg_account_yiyi_bu`");
    $db->exec("CREATE TRIGGER `trg_account_yiyi_bu` BEFORE UPDATE ON `account` FOR EACH ROW
    BEGIN
        IF NOT (NEW.`vnd` <=> OLD.`vnd`) AND (NEW.`sotien` <=> OLD.`sotien`) THEN
            SET NEW.`sotien`=NEW.`vnd`;
        ELSEIF NOT (NEW.`sotien` <=> OLD.`sotien`) AND (NEW.`vnd` <=> OLD.`vnd`) THEN
            SET NEW.`vnd`=NEW.`sotien`;
        END IF;

        IF NOT (NEW.`tongnap` <=> OLD.`tongnap`) AND (NEW.`danap` <=> OLD.`danap`) THEN
            SET NEW.`danap`=NEW.`tongnap`;
        ELSEIF NOT (NEW.`danap` <=> OLD.`danap`) AND (NEW.`tongnap` <=> OLD.`tongnap`) THEN
            SET NEW.`tongnap`=NEW.`danap`;
        END IF;

        IF NOT (NEW.`ref_id` <=> OLD.`ref_id`) AND (NEW.`gioithieu` <=> OLD.`gioithieu`) THEN
            SET NEW.`gioithieu`=NEW.`ref_id`;
        ELSEIF NOT (NEW.`gioithieu` <=> OLD.`gioithieu`) AND (NEW.`ref_id` <=> OLD.`ref_id`) THEN
            SET NEW.`ref_id`=NEW.`gioithieu`;
        END IF;

        IF (NOT (NEW.`is_admin` <=> OLD.`is_admin`) AND COALESCE(NEW.`is_admin`,0)=1)
           OR (NOT (NEW.`admin` <=> OLD.`admin`) AND COALESCE(NEW.`admin`,0)=1) THEN
            SET NEW.`isFounder`=1, NEW.`isQuanTriVien`=1;
        END IF;
    END");

    $mark = $db->prepare("INSERT INTO `web_compat_meta` (`name`,`version`) VALUES ('team2026_yiyi_web_fix', :v)
        ON DUPLICATE KEY UPDATE `version`=VALUES(`version`), `updated_at`=CURRENT_TIMESTAMP");
    $mark->execute(['v'=>$version]);
}
