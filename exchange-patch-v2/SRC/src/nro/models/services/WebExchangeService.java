package nro.models.services;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.ArrayList;
import java.util.List;
import nro.models.consts.ConstTaskBadges;
import nro.models.data.LocalManager;
import nro.models.database.PlayerDAO;
import nro.models.item.Item;
import nro.models.player.Player;
import nro.models.server.Client;
import nro.models.server.ServerManager;
import nro.models.task.BadgesTaskService;
import nro.models.utils.Functions;
import nro.models.utils.Logger;

/**
 * Xu ly giao dich quy doi duoc tao tu website.
 * Web chi tru account.vnd + tao PENDING.
 * Server game phat Item 457 / Ngoc xanh / Ve 718 / diem su kien.
 */
public class WebExchangeService implements Runnable {

    private static WebExchangeService instance;

    public static WebExchangeService gI() {
        if (instance == null) {
            instance = new WebExchangeService();
        }
        return instance;
    }

    public void ensureTable() {
        String sql = "CREATE TABLE IF NOT EXISTS web_exchange_queue ("
                + "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,"
                + "account_id INT NOT NULL,"
                + "username VARCHAR(100) NOT NULL,"
                + "exchange_type VARCHAR(20) NOT NULL,"
                + "amount_vnd BIGINT NOT NULL,"
                + "reward_amount BIGINT NOT NULL,"
                + "ticket_amount INT NOT NULL DEFAULT 0,"
                + "event_points INT NOT NULL DEFAULT 0,"
                + "status VARCHAR(20) NOT NULL DEFAULT 'PENDING',"
                + "error_message VARCHAR(500) DEFAULT NULL,"
                + "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,"
                + "processing_at DATETIME DEFAULT NULL,"
                + "processed_at DATETIME DEFAULT NULL,"
                + "PRIMARY KEY (id),"
                + "KEY idx_exchange_status (status,id),"
                + "KEY idx_exchange_account (account_id,id)"
                + ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(sql)) {
            ps.executeUpdate();
        } catch (Exception e) {
            Logger.logException(WebExchangeService.class, e);
        }
    }

    @Override
    public void run() {
        while (ServerManager.isRunning) {
            try {
                processPending();
            } catch (Exception e) {
                Logger.logException(WebExchangeService.class, e);
            }
            Functions.sleep(2000);
        }
    }

    private void processPending() {
        List<Row> rows = new ArrayList<>();

        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(
                     "SELECT id,account_id,amount_vnd,exchange_type,reward_amount,ticket_amount,event_points "
                     + "FROM web_exchange_queue WHERE status='PENDING' ORDER BY id ASC LIMIT 20");
             ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                Row r = new Row();
                r.id = rs.getLong("id");
                r.accountId = rs.getInt("account_id");
                r.amountVnd = rs.getLong("amount_vnd");
                r.type = rs.getString("exchange_type");
                r.reward = rs.getLong("reward_amount");
                r.tickets = rs.getInt("ticket_amount");
                r.points = rs.getInt("event_points");
                rows.add(r);
            }
        } catch (Exception e) {
            Logger.logException(WebExchangeService.class, e);
            return;
        }

        for (Row r : rows) {
            processOne(r);
        }
    }

    private void processOne(Row r) {
        Player pl = Client.gI().getPlayerByUser(r.accountId);

        // Offline: giu PENDING, khi player login se tu nhan.
        if (pl == null || pl.getSession() == null) {
            return;
        }

        if (r.reward <= 0 || r.reward > Integer.MAX_VALUE) {
            markFailed(r.id, "reward_amount khong hop le");
            return;
        }

        int needSlots = 0;
        if ("GOLD_BAR".equals(r.type) && InventoryService.gI().findItemBag(pl, 457) == null) {
            needSlots++;
        }
        if (r.tickets > 0 && InventoryService.gI().findItemBag(pl, 718) == null) {
            needSlots++;
        }
        if (InventoryService.gI().getCountEmptyBag(pl) < needSlots) {
            return;
        }

        if ("GEM".equals(r.type)) {
            if ((long) pl.inventory.gem + r.reward > Integer.MAX_VALUE) {
                markFailed(r.id, "Ngoc xanh vuot gioi han");
                return;
            }
        } else if (!"GOLD_BAR".equals(r.type)) {
            markFailed(r.id, "Loai quy doi khong hop le");
            return;
        }

        if (!claim(r.id)) {
            return;
        }

        boolean applied = false;
        try {
            if ("GOLD_BAR".equals(r.type)) {
                Item item457 = ItemService.gI().createNewItem((short) 457, (int) r.reward);
                InventoryService.gI().addItemBag(pl, item457);
            } else {
                pl.inventory.gem += (int) r.reward;
            }

            if (r.tickets > 0) {
                Item item718 = ItemService.gI().createNewItem((short) 718, r.tickets);
                InventoryService.gI().addItemBag(pl, item718);
            }

            if (r.points > 0) {
                pl.event.addEventPoint(r.points);
            }

            int badgeAmount = r.amountVnd > Integer.MAX_VALUE ? Integer.MAX_VALUE : (int) r.amountVnd;
            BadgesTaskService.updateCountBagesTask(pl, ConstTaskBadges.DAI_GIA_MOI_NHU, badgeAmount);
            BadgesTaskService.updateCountBagesTask(pl, ConstTaskBadges.EM_XINH_EM_DEP, badgeAmount);

            applied = true;

            // Web da tru account.vnd trong DB, can cap nhat so du RAM cua session dang online.
            syncSessionVnd(pl, r.accountId);

            // Save reward vao DB truoc khi danh dau DONE.
            PlayerDAO.updatePlayer(pl);

            InventoryService.gI().sendItemBags(pl);
            Service.gI().sendMoney(pl);

            String rewardName = "GOLD_BAR".equals(r.type) ? " Thoi vang" : " Ngoc xanh";
            Service.gI().sendThongBao(pl,
                    "Quy doi web thanh cong! Nhan " + r.reward + rewardName
                    + (r.tickets > 0 ? ", " + r.tickets + " Ve tang ngoc" : "")
                    + (r.points > 0 ? ", " + r.points + " diem su kien" : "") + ".");

            markDone(r.id);
        } catch (Exception e) {
            Logger.logException(WebExchangeService.class, e);
            // Neu da cong thuong vao player thi khong dua lai PENDING de tranh phat trung.
            if (applied) {
                markProcessingError(r.id, "Da cong thuong, loi save/hoan tat: " + safe(e));
            } else {
                markFailed(r.id, safe(e));
            }
        }
    }

    private boolean claim(long id) {
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(
                     "UPDATE web_exchange_queue "
                     + "SET status='PROCESSING',processing_at=NOW(),error_message=NULL "
                     + "WHERE id=? AND status='PENDING'")) {
            ps.setLong(1, id);
            return ps.executeUpdate() == 1;
        } catch (Exception e) {
            Logger.logException(WebExchangeService.class, e);
            return false;
        }
    }

    private void syncSessionVnd(Player pl, int accountId) {
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement("SELECT vnd FROM account WHERE id=? LIMIT 1")) {
            ps.setInt(1, accountId);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    pl.getSession().vnd = rs.getInt("vnd");
                }
            }
        } catch (Exception e) {
            Logger.logException(WebExchangeService.class, e);
        }
    }

    private void markDone(long id) {
        updateStatus(id, "DONE", null, true);
    }

    private void markFailed(long id, String error) {
        updateStatus(id, "FAILED", error, true);
    }

    private void markProcessingError(long id, String error) {
        updateStatus(id, "PROCESSING", error, false);
    }

    private void updateStatus(long id, String status, String error, boolean done) {
        String sql = done
                ? "UPDATE web_exchange_queue SET status=?,error_message=?,processed_at=NOW() WHERE id=?"
                : "UPDATE web_exchange_queue SET status=?,error_message=? WHERE id=?";
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(sql)) {
            ps.setString(1, status);
            ps.setString(2, error);
            ps.setLong(3, id);
            ps.executeUpdate();
        } catch (Exception e) {
            Logger.logException(WebExchangeService.class, e);
        }
    }

    private String safe(Exception e) {
        String s = e.getMessage();
        if (s == null) {
            s = e.getClass().getSimpleName();
        }
        return s.length() > 450 ? s.substring(0, 450) : s;
    }

    private static class Row {
        long id;
        int accountId;
        long amountVnd;
        String type;
        long reward;
        int tickets;
        int points;
    }
}
