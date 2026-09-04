package nro.models.services;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.ArrayList;
import java.util.List;
import nro.models.data.LocalManager;
import nro.models.database.PlayerDAO;
import nro.models.item.Item;
import nro.models.player.Player;
import nro.models.server.Client;
import nro.models.utils.Logger;

public class WebExchangeService {

    private static WebExchangeService instance;
    private long lastCheck;

    public static WebExchangeService gI() {
        if (instance == null) instance = new WebExchangeService();
        return instance;
    }

    public void ensureTable() {
        String sql = "CREATE TABLE IF NOT EXISTS web_exchange_queue ("
                + "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,"
                + "account_id INT NOT NULL,username VARCHAR(100) NOT NULL,"
                + "exchange_type VARCHAR(20) NOT NULL,amount_vnd BIGINT NOT NULL,"
                + "reward_amount BIGINT NOT NULL,ticket_amount INT NOT NULL DEFAULT 0,"
                + "event_points INT NOT NULL DEFAULT 0,status VARCHAR(20) NOT NULL DEFAULT 'PENDING',"
                + "error_message VARCHAR(500) DEFAULT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,"
                + "processing_at DATETIME DEFAULT NULL,processed_at DATETIME DEFAULT NULL,"
                + "PRIMARY KEY(id),KEY idx_exchange_status(status,id),KEY idx_exchange_account(account_id,id)"
                + ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        try (Connection con = LocalManager.getConnection(); PreparedStatement ps = con.prepareStatement(sql)) {
            ps.executeUpdate();
        } catch (Exception e) {
            Logger.logException(WebExchangeService.class, e);
        }
    }

    public synchronized void processPending() {
        long now = System.currentTimeMillis();
        if (now - lastCheck < 2000) return;
        lastCheck = now;

        List<Row> rows = new ArrayList<>();
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(
                     "SELECT id,account_id,exchange_type,reward_amount,ticket_amount,event_points "
                     + "FROM web_exchange_queue WHERE status='PENDING' ORDER BY id ASC LIMIT 20");
             ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                Row r = new Row();
                r.id = rs.getLong("id"); r.accountId = rs.getInt("account_id");
                r.type = rs.getString("exchange_type"); r.reward = rs.getLong("reward_amount");
                r.tickets = rs.getInt("ticket_amount"); r.points = rs.getInt("event_points");
                rows.add(r);
            }
        } catch (Exception e) {
            Logger.logException(WebExchangeService.class, e);
            return;
        }
        for (Row r : rows) tryProcess(r);
    }

    private void tryProcess(Row r) {
        Player pl = Client.gI().getPlayerByUser(r.accountId);
        if (pl == null || pl.getSession() == null) return;

        if (r.reward <= 0 || r.reward > Integer.MAX_VALUE) {
            markFailed(r.id, "reward_amount khong hop le"); return;
        }

        int need = 0;
        if ("GOLD_BAR".equals(r.type) && InventoryService.gI().findItemBag(pl, 457) == null) need++;
        if (r.tickets > 0 && InventoryService.gI().findItemBag(pl, 718) == null) need++;
        if (InventoryService.gI().getCountEmptyBag(pl) < need) return;

        if ("GEM".equals(r.type)) {
            if ((long)pl.inventory.gem + r.reward > Integer.MAX_VALUE) {
                markFailed(r.id, "Ngoc xanh vuot gioi han"); return;
            }
        } else if (!"GOLD_BAR".equals(r.type)) {
            markFailed(r.id, "Loai quy doi khong hop le"); return;
        }

        if (!claim(r.id)) return;

        boolean applied = false;
        try {
            if ("GOLD_BAR".equals(r.type)) {
                Item it = ItemService.gI().createNewItem((short)457, (int)r.reward);
                InventoryService.gI().addItemBag(pl, it);
            } else {
                pl.inventory.gem += (int)r.reward;
            }
            if (r.tickets > 0) {
                Item ve = ItemService.gI().createNewItem((short)718, r.tickets);
                InventoryService.gI().addItemBag(pl, ve);
            }
            if (r.points > 0) {
                try { pl.event.addEventPoint(r.points); } catch (Exception ignored) {}
            }
            applied = true;

            PlayerDAO.updatePlayer(pl);
            InventoryService.gI().sendItemBags(pl);
            Service.gI().sendMoney(pl);
            Service.gI().sendThongBao(pl,
                    "Quy doi web thanh cong! Nhan " + r.reward
                    + ("GOLD_BAR".equals(r.type) ? " Thoi vang" : " Ngoc xanh")
                    + (r.tickets > 0 ? ", " + r.tickets + " Ve tang ngoc" : "")
                    + (r.points > 0 ? ", " + r.points + " diem su kien" : "") + ".");
            markDone(r.id);
        } catch (Exception e) {
            Logger.logException(WebExchangeService.class, e);
            if (applied) markProcessingError(r.id, "Da cong thuong, loi luu/hoan tat: " + safe(e));
            else markFailed(r.id, safe(e));
        }
    }

    private boolean claim(long id) {
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(
                     "UPDATE web_exchange_queue SET status='PROCESSING',processing_at=NOW(),error_message=NULL "
                     + "WHERE id=? AND status='PENDING'")) {
            ps.setLong(1,id); return ps.executeUpdate()==1;
        } catch (Exception e) {
            Logger.logException(WebExchangeService.class,e); return false;
        }
    }

    private void markDone(long id){ update(id,"DONE",null,true); }
    private void markFailed(long id,String err){ update(id,"FAILED",err,true); }
    private void markProcessingError(long id,String err){ update(id,"PROCESSING",err,false); }

    private void update(long id,String status,String err,boolean done) {
        String sql = done
                ? "UPDATE web_exchange_queue SET status=?,error_message=?,processed_at=NOW() WHERE id=?"
                : "UPDATE web_exchange_queue SET status=?,error_message=? WHERE id=?";
        try(Connection con=LocalManager.getConnection();PreparedStatement ps=con.prepareStatement(sql)){
            ps.setString(1,status);ps.setString(2,err);ps.setLong(3,id);ps.executeUpdate();
        }catch(Exception e){Logger.logException(WebExchangeService.class,e);}
    }

    private String safe(Exception e){
        String s=e.getMessage(); if(s==null)s=e.getClass().getSimpleName();
        return s.length()>450?s.substring(0,450):s;
    }

    private static class Row {
        long id; int accountId; String type; long reward; int tickets; int points;
    }
}
