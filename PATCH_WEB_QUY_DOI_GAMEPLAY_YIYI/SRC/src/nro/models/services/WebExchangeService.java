package nro.models.services;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;
import nro.models.consts.ConstTaskBadges;
import nro.models.data.LocalManager;
import nro.models.database.PlayerDAO;
import nro.models.item.Item;
import nro.models.player.Player;
import nro.models.task.BadgesTaskService;
import nro.models.utils.Logger;

/**
 * Nhận giao dịch quy đổi được tạo từ website.
 *
 * Website chỉ trừ account.vnd và ghi web_exchange_queue.
 * Lớp này chạy trong RAM server game để cộng đúng vào inventory/itemsBag,
 * tránh PHP sửa data_inventory/items_bag khi nhân vật đang online rồi bị save đè.
 */
public class WebExchangeService {

    private static WebExchangeService I;

    private static final long CHECK_INTERVAL = 2_500L;
    private static final long WARN_INTERVAL = 30_000L;

    private static final short GOLD_BAR_ID = 457;
    private static final short BONUS_TICKET_ID = 718;

    private final Map<Integer, Long> lastCheck = new ConcurrentHashMap<>();
    private final Map<Integer, Long> lastWarn = new ConcurrentHashMap<>();

    private volatile long schemaRetryAt = 0L;

    public static WebExchangeService gI() {
        if (I == null) {
            I = new WebExchangeService();
        }
        return I;
    }

    public void update(Player player) {
        if (player == null || player.getSession() == null || !player.isPl()) {
            return;
        }

        int accountId = player.getSession().userId;
        long now = System.currentTimeMillis();

        Long last = lastCheck.get(accountId);
        if (last != null && now - last < CHECK_INTERVAL) {
            return;
        }
        lastCheck.put(accountId, now);

        if (now < schemaRetryAt) {
            return;
        }

        try {
            List<ExchangeRow> rows = loadPending(accountId);
            if (rows.isEmpty()) {
                return;
            }

            // Web đã trừ vnd trước khi ghi queue. Đồng bộ session ngay khi có giao dịch.
            refreshVnd(player);

            for (ExchangeRow row : rows) {
                processOne(player, row);
            }

            refreshVnd(player);
        } catch (SQLException e) {
            // Table chưa được tạo: web sẽ tự tạo ở lần đầu mở /Users/Exchange.
            if ("42S02".equals(e.getSQLState()) || e.getErrorCode() == 1146) {
                schemaRetryAt = now + 60_000L;
                return;
            }
            Logger.logException(WebExchangeService.class, e, "Lỗi đọc web_exchange_queue");
        } catch (Exception e) {
            Logger.logException(WebExchangeService.class, e, "Lỗi xử lý quy đổi từ web");
        }
    }

    private List<ExchangeRow> loadPending(int accountId) throws SQLException {
        List<ExchangeRow> rows = new ArrayList<>();

        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(
                     "SELECT id, exchange_type, amount_vnd, reward_amount, ticket_amount, event_point_amount, status "
                     + "FROM web_exchange_queue "
                     + "WHERE account_id = ? AND status IN ('PENDING','WAITING_BAG','WAITING_LIMIT') "
                     + "ORDER BY id ASC LIMIT 3")) {
            ps.setInt(1, accountId);

            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    ExchangeRow row = new ExchangeRow();
                    row.id = rs.getLong("id");
                    row.type = rs.getString("exchange_type");
                    row.amountVnd = rs.getInt("amount_vnd");
                    row.rewardAmount = rs.getLong("reward_amount");
                    row.ticketAmount = rs.getInt("ticket_amount");
                    row.eventPointAmount = rs.getInt("event_point_amount");
                    row.status = rs.getString("status");
                    rows.add(row);
                }
            }
        }

        return rows;
    }

    private void processOne(Player player, ExchangeRow row) {
        int accountId = player.getSession().userId;

        try {
            if (!claim(row.id, accountId)) {
                return;
            }

            int requiredSlots = 0;
            if ("goldbar".equals(row.type)
                    && InventoryService.gI().findItemBag(player, GOLD_BAR_ID) == null) {
                requiredSlots++;
            }
            if (row.ticketAmount > 0
                    && InventoryService.gI().findItemBag(player, BONUS_TICKET_ID) == null) {
                requiredSlots++;
            }

            if (InventoryService.gI().getCountEmptyBag(player) < requiredSlots) {
                setWaiting(row.id, accountId, "WAITING_BAG", "Hành trang chưa đủ ô trống");
                warn(player, "Giao dịch web #" + row.id + " đang chờ vì hành trang chưa đủ ô trống.");
                return;
            }

            if ("gem".equals(row.type)
                    && ((long) player.inventory.gem + row.rewardAmount > Integer.MAX_VALUE)) {
                setWaiting(row.id, accountId, "WAITING_LIMIT", "Ngọc xanh sẽ vượt giới hạn");
                warn(player, "Giao dịch web #" + row.id + " đang chờ vì Ngọc xanh sẽ vượt giới hạn.");
                return;
            }

            if (!"goldbar".equals(row.type) && !"gem".equals(row.type)) {
                setError(row.id, accountId, "Loại quy đổi không hợp lệ");
                return;
            }

            int gemBefore = player.inventory.gem;
            int barAdded = 0;
            int ticketAdded = 0;

            try {
                if ("goldbar".equals(row.type)) {
                    if (row.rewardAmount <= 0 || row.rewardAmount > Integer.MAX_VALUE) {
                        throw new IllegalStateException("Số Thỏi vàng vượt giới hạn");
                    }

                    Item bars = ItemService.gI().createNewItem(GOLD_BAR_ID, (int) row.rewardAmount);
                    if (!InventoryService.gI().addItemBag(player, bars)) {
                        throw new IllegalStateException("Không thể thêm Thỏi vàng vào hành trang");
                    }
                    barAdded = (int) row.rewardAmount;
                } else {
                    player.inventory.gem += (int) row.rewardAmount;
                }

                if (row.ticketAmount > 0) {
                    Item tickets = ItemService.gI().createNewItem(BONUS_TICKET_ID, row.ticketAmount);
                    if (!InventoryService.gI().addItemBag(player, tickets)) {
                        throw new IllegalStateException("Không thể thêm Vé tặng ngọc vào hành trang");
                    }
                    ticketAdded = row.ticketAmount;
                }

                // Giữ nguyên bonus đang có trong Input.TRADE_GOLD / TRADE_GEM.
                BadgesTaskService.updateCountBagesTask(
                        player, ConstTaskBadges.DAI_GIA_MOI_NHU, row.amountVnd);
                BadgesTaskService.updateCountBagesTask(
                        player, ConstTaskBadges.EM_XINH_EM_DEP, row.amountVnd);

                if (row.eventPointAmount > 0 && player.event != null) {
                    player.event.addEventPoint(row.eventPointAmount);
                }

                InventoryService.gI().sendItemBags(player);
                Service.gI().sendMoney(player);

                // Save ngay để phần thưởng web không chỉ nằm trong RAM.
                PlayerDAO.updatePlayer(player);

                if (!markDone(row.id, accountId)) {
                    setError(row.id, accountId,
                            "Phần thưởng đã cộng nhưng không chốt được trạng thái; không tự retry");
                    warn(player, "Giao dịch web #" + row.id
                            + " đã cộng thưởng nhưng cần Admin kiểm tra trạng thái.");
                    return;
                }

                if ("goldbar".equals(row.type)) {
                    Service.gI().sendThongBao(player,
                            "Web #" + row.id + ": +" + row.rewardAmount
                            + " Thỏi vàng, +" + row.ticketAmount
                            + " Vé, +" + row.eventPointAmount + " điểm sự kiện.");
                } else {
                    Service.gI().sendThongBao(player,
                            "Web #" + row.id + ": +" + row.rewardAmount
                            + " Ngọc xanh, +" + row.ticketAmount
                            + " Vé, +" + row.eventPointAmount + " điểm sự kiện.");
                }

            } catch (Exception rewardError) {
                // Cố gắng trả RAM về trạng thái trước giao dịch rồi đưa queue về PENDING.
                player.inventory.gem = gemBefore;

                if (barAdded > 0) {
                    Item current = InventoryService.gI().findItemBag(player, GOLD_BAR_ID);
                    if (current != null && current.quantity >= barAdded) {
                        InventoryService.gI().subQuantityItemsBag(player, current, barAdded);
                    }
                }

                if (ticketAdded > 0) {
                    Item currentTicket = InventoryService.gI().findItemBag(player, BONUS_TICKET_ID);
                    if (currentTicket != null && currentTicket.quantity >= ticketAdded) {
                        InventoryService.gI().subQuantityItemsBag(player, currentTicket, ticketAdded);
                    }
                }

                InventoryService.gI().sendItemBags(player);
                Service.gI().sendMoney(player);

                setWaiting(row.id, accountId, "PENDING",
                        "Thử lại: " + safeMessage(rewardError.getMessage()));
                Logger.logException(WebExchangeService.class, rewardError,
                        "Lỗi cộng thưởng giao dịch web #" + row.id);
            }

        } catch (Exception e) {
            Logger.logException(WebExchangeService.class, e,
                    "Lỗi xử lý giao dịch web #" + row.id);
            try {
                setWaiting(row.id, accountId, "PENDING", "Lỗi tạm thời, server sẽ thử lại");
            } catch (Exception ignored) {
            }
        }
    }

    private boolean claim(long id, int accountId) throws SQLException {
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(
                     "UPDATE web_exchange_queue "
                     + "SET status = 'PROCESSING', claimed_at = NOW(), note = NULL "
                     + "WHERE id = ? AND account_id = ? "
                     + "AND status IN ('PENDING','WAITING_BAG','WAITING_LIMIT')")) {
            ps.setLong(1, id);
            ps.setInt(2, accountId);
            return ps.executeUpdate() == 1;
        }
    }

    private boolean markDone(long id, int accountId) throws SQLException {
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(
                     "UPDATE web_exchange_queue "
                     + "SET status = 'DONE', processed_at = NOW(), note = NULL "
                     + "WHERE id = ? AND account_id = ? AND status = 'PROCESSING'")) {
            ps.setLong(1, id);
            ps.setInt(2, accountId);
            return ps.executeUpdate() == 1;
        }
    }

    private void setWaiting(long id, int accountId, String status, String note) throws SQLException {
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(
                     "UPDATE web_exchange_queue "
                     + "SET status = ?, note = ?, claimed_at = NULL "
                     + "WHERE id = ? AND account_id = ?")) {
            ps.setString(1, status);
            ps.setString(2, safeMessage(note));
            ps.setLong(3, id);
            ps.setInt(4, accountId);
            ps.executeUpdate();
        }
    }

    private void setError(long id, int accountId, String note) throws SQLException {
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(
                     "UPDATE web_exchange_queue "
                     + "SET status = 'ERROR', note = ?, processed_at = NOW() "
                     + "WHERE id = ? AND account_id = ?")) {
            ps.setString(1, safeMessage(note));
            ps.setLong(2, id);
            ps.setInt(3, accountId);
            ps.executeUpdate();
        }
    }

    private void refreshVnd(Player player) {
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(
                     "SELECT vnd FROM account WHERE id = ? LIMIT 1")) {
            ps.setInt(1, player.getSession().userId);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    player.getSession().vnd = rs.getInt("vnd");
                }
            }
        } catch (Exception ignored) {
        }
    }

    private void warn(Player player, String message) {
        int accountId = player.getSession().userId;
        long now = System.currentTimeMillis();
        Long last = lastWarn.get(accountId);

        if (last == null || now - last >= WARN_INTERVAL) {
            lastWarn.put(accountId, now);
            Service.gI().sendThongBao(player, message);
        }
    }

    private String safeMessage(String message) {
        if (message == null || message.isEmpty()) {
            return "Không xác định";
        }
        return message.length() > 240 ? message.substring(0, 240) : message;
    }

    private static class ExchangeRow {
        long id;
        String type;
        int amountVnd;
        long rewardAmount;
        int ticketAmount;
        int eventPointAmount;
        String status;
    }
}
