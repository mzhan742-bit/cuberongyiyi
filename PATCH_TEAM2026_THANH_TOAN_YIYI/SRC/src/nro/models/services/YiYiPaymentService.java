package nro.models.services;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import nro.models.data.LocalManager;
import nro.models.item.Item;
import nro.models.player.Player;

/**
 * Lớp tương thích cơ chế thanh toán/quy đổi YiYi cho Team2026.
 *
 * Không xử lý cổng thanh toán. Website/autobank chỉ cần cộng vào account.vnd
 * hoặc account.thoi_vang như hiện tại; lớp này xử lý phần nhận/quy đổi trong game.
 */
public class YiYiPaymentService {

    private static YiYiPaymentService I;

    private static final int[] VND_PACKAGES = {
        5_000, 10_000, 20_000, 50_000, 100_000, 200_000,
        500_000, 1_000_000, 2_000_000, 5_000_000, 10_000_000
    };

    // Bảng giá đang chạy trong YiYi (Laogia.java), hệ số x1.
    private static final int[] GOLD_BAR_PACKAGES = {
        30, 70, 150, 400, 850, 1_800,
        4_700, 10_000, 22_000, 75_000, 200_000
    };

    private static final int[] RUBY_PACKAGES = {
        160, 330, 680, 1_800, 3_800, 7_500,
        20_000, 45_000, 100_000, 300_000, 1_000_000
    };

    private static final short GOLD_BAR_ITEM_ID = 457;
    private static final int RUBY_PER_GOLD_BAR = 100;

    public static YiYiPaymentService gI() {
        if (I == null) {
            I = new YiYiPaymentService();
        }
        return I;
    }

    /**
     * Đồng bộ lại số dư web -> RAM ngay cả khi player đang online.
     */
    public boolean refreshBalances(Player player) {
        if (player == null || player.getSession() == null) {
            return false;
        }
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(
                     "SELECT vnd, thoi_vang FROM account WHERE id = ? LIMIT 1")) {
            ps.setInt(1, player.getSession().userId);
            try (ResultSet rs = ps.executeQuery()) {
                if (!rs.next()) {
                    return false;
                }
                player.getSession().vnd = rs.getInt("vnd");
                player.getSession().goldBar = rs.getInt("thoi_vang");
                return true;
            }
        } catch (Exception e) {
            Service.gI().sendThongBao(player, "Không thể cập nhật số dư từ website, vui lòng thử lại!");
            return false;
        }
    }

    public void buyGoldBarPackage(Player player, int index) {
        if (!validIndex(index)) {
            Service.gI().sendThongBao(player, "Gói quy đổi không hợp lệ!");
            return;
        }
        refreshBalances(player);

        int vnd = VND_PACKAGES[index];
        int quantity = GOLD_BAR_PACKAGES[index];

        if (player.getSession().vnd < vnd) {
            Service.gI().sendThongBao(player, "Số dư không đủ để nạp gói này!");
            return;
        }
        if (InventoryService.gI().getCountEmptyBag(player) == 0) {
            Service.gI().sendThongBao(player, "Hành trang đã đầy, cần ít nhất 1 ô trống!");
            return;
        }
        if (!debitVnd(player, vnd)) {
            Service.gI().sendThongBao(player, "Số dư đã thay đổi hoặc không đủ, vui lòng thử lại!");
            return;
        }

        Item item = ItemService.gI().createNewItem(GOLD_BAR_ITEM_ID, quantity);
        if (!InventoryService.gI().addItemBag(player, item)) {
            refundVnd(player, vnd);
            Service.gI().sendThongBao(player, "Không thể thêm Thỏi vàng vào hành trang. Giao dịch đã được hoàn tiền!");
            return;
        }

        InventoryService.gI().sendItemBags(player);
        Service.gI().sendThongBao(player,
                "Quy đổi thành công " + vnd + " VNĐ → " + quantity + " Thỏi vàng.");
    }

    public void buyRubyPackage(Player player, int index) {
        if (!validIndex(index)) {
            Service.gI().sendThongBao(player, "Gói quy đổi không hợp lệ!");
            return;
        }
        refreshBalances(player);

        int vnd = VND_PACKAGES[index];
        int ruby = RUBY_PACKAGES[index];

        if (player.getSession().vnd < vnd) {
            Service.gI().sendThongBao(player, "Số dư không đủ để nạp gói này!");
            return;
        }
        if ((long) player.inventory.ruby + ruby > Integer.MAX_VALUE) {
            Service.gI().sendThongBao(player, "Hồng ngọc sẽ vượt giới hạn!");
            return;
        }
        if (!debitVnd(player, vnd)) {
            Service.gI().sendThongBao(player, "Số dư đã thay đổi hoặc không đủ, vui lòng thử lại!");
            return;
        }

        player.inventory.ruby += ruby;
        Service.gI().sendMoney(player);
        Service.gI().sendThongBao(player,
                "Quy đổi thành công " + vnd + " VNĐ → " + ruby + " Hồng ngọc.");
    }

    /**
     * Form cũ dự phòng của source YiYi: 1.000 VND = 5 Thỏi vàng.
     */
    public void convertFlexibleToGoldBar(Player player, String raw) {
        int vnd = parsePositiveInt(raw);
        if (vnd < 1_000 || vnd % 1_000 != 0) {
            Service.gI().sendThongBao(player, "Số tiền phải từ 1.000 VNĐ và chia hết cho 1.000!");
            return;
        }
        refreshBalances(player);
        if (player.getSession().vnd < vnd) {
            Service.gI().sendThongBao(player, "Số dư không đủ, vui lòng nạp thêm!");
            return;
        }

        long q = ((long) vnd / 1_000L) * 5L;
        if (q <= 0 || q > Integer.MAX_VALUE) {
            Service.gI().sendThongBao(player, "Số lượng quy đổi vượt giới hạn!");
            return;
        }
        if (InventoryService.gI().getCountEmptyBag(player) == 0) {
            Service.gI().sendThongBao(player, "Hành trang đã đầy!");
            return;
        }
        if (!debitVnd(player, vnd)) {
            Service.gI().sendThongBao(player, "Không thể trừ số dư, vui lòng thử lại!");
            return;
        }

        Item item = ItemService.gI().createNewItem(GOLD_BAR_ITEM_ID, (int) q);
        if (!InventoryService.gI().addItemBag(player, item)) {
            refundVnd(player, vnd);
            Service.gI().sendThongBao(player, "Không thể thêm vật phẩm. Giao dịch đã được hoàn tiền!");
            return;
        }
        InventoryService.gI().sendItemBags(player);
        Service.gI().sendThongBao(player, "Đã đổi " + vnd + " VNĐ thành " + q + " Thỏi vàng.");
    }

    /**
     * Form cũ dự phòng của source YiYi: 1.000 VND = 30 Ngọc xanh.
     */
    public void convertFlexibleToGem(Player player, String raw) {
        int vnd = parsePositiveInt(raw);
        if (vnd < 1_000 || vnd % 1_000 != 0) {
            Service.gI().sendThongBao(player, "Số tiền phải từ 1.000 VNĐ và chia hết cho 1.000!");
            return;
        }
        refreshBalances(player);
        if (player.getSession().vnd < vnd) {
            Service.gI().sendThongBao(player, "Số dư không đủ, vui lòng nạp thêm!");
            return;
        }

        long gem = ((long) vnd / 1_000L) * 30L;
        if (gem <= 0 || (long) player.inventory.gem + gem > Integer.MAX_VALUE) {
            Service.gI().sendThongBao(player, "Ngọc xanh sẽ vượt giới hạn!");
            return;
        }
        if (!debitVnd(player, vnd)) {
            Service.gI().sendThongBao(player, "Không thể trừ số dư, vui lòng thử lại!");
            return;
        }

        player.inventory.gem += (int) gem;
        Service.gI().sendMoney(player);
        Service.gI().sendThongBao(player, "Đã đổi " + vnd + " VNĐ thành " + gem + " Ngọc xanh.");
    }

    /**
     * YiYi runtime: 1 Thỏi vàng = 100 Hồng ngọc.
     */
    public void convertGoldBarToRuby(Player player, String raw) {
        int quantity = parsePositiveInt(raw);
        if (quantity <= 0) {
            Service.gI().sendThongBao(player, "Số lượng Thỏi vàng không hợp lệ!");
            return;
        }

        Item thoiVang = InventoryService.gI().findItemBag(player, GOLD_BAR_ITEM_ID);
        if (thoiVang == null || thoiVang.quantity < quantity) {
            Service.gI().sendThongBao(player, "Bạn không có đủ Thỏi vàng!");
            return;
        }

        long ruby = (long) quantity * RUBY_PER_GOLD_BAR;
        if ((long) player.inventory.ruby + ruby > Integer.MAX_VALUE) {
            Service.gI().sendThongBao(player, "Hồng ngọc sẽ vượt giới hạn!");
            return;
        }

        InventoryService.gI().subQuantityItemsBag(player, thoiVang, quantity);
        player.inventory.ruby += (int) ruby;
        InventoryService.gI().sendItemBags(player);
        Service.gI().sendMoney(player);
        Service.gI().sendThongBao(player,
                "Đã đổi " + quantity + " Thỏi vàng thành " + ruby + " Hồng ngọc.");
    }

    /**
     * Nhận Thỏi vàng đã được website ghi vào account.thoi_vang.
     * Dùng optimistic update để tránh xóa nhầm khoản web vừa cộng cùng lúc.
     */
    public void claimPendingGoldBars(Player player) {
        if (!refreshBalances(player)) {
            return;
        }
        int pending = player.getSession().goldBar;
        if (pending <= 0) {
            Service.gI().sendThongBao(player, "Bạn không có Thỏi vàng chờ nhận từ website!");
            return;
        }

        Item current = InventoryService.gI().findItemBag(player, GOLD_BAR_ITEM_ID);
        if (current == null && InventoryService.gI().getCountEmptyBag(player) == 0) {
            Service.gI().sendThongBao(player, "Hành trang đã đầy, cần ít nhất 1 ô trống!");
            return;
        }

        if (!takePendingFromDatabase(player, pending)) {
            refreshBalances(player);
            Service.gI().sendThongBao(player, "Số Thỏi vàng vừa thay đổi, vui lòng bấm Nhận lại!");
            return;
        }

        Item item = ItemService.gI().createNewItem(GOLD_BAR_ITEM_ID, pending);
        if (!InventoryService.gI().addItemBag(player, item)) {
            restorePendingGoldBars(player, pending);
            refreshBalances(player);
            Service.gI().sendThongBao(player, "Không thể nhận vào hành trang. Thỏi vàng đã được hoàn lại trên website!");
            return;
        }

        player.getSession().goldBar = 0;
        InventoryService.gI().sendItemBags(player);
        Service.gI().sendThongBao(player, "Bạn đã nhận x" + pending + " Thỏi vàng từ website!");
    }

    private boolean validIndex(int index) {
        return index >= 0 && index < VND_PACKAGES.length;
    }

    private int parsePositiveInt(String raw) {
        try {
            long value = Long.parseLong(raw == null ? "" : raw.trim());
            if (value <= 0 || value > Integer.MAX_VALUE) {
                return -1;
            }
            return (int) value;
        } catch (Exception e) {
            return -1;
        }
    }

    private boolean debitVnd(Player player, int amount) {
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(
                     "UPDATE account SET vnd = vnd - ? WHERE id = ? AND vnd >= ?")) {
            ps.setInt(1, amount);
            ps.setInt(2, player.getSession().userId);
            ps.setInt(3, amount);
            if (ps.executeUpdate() != 1) {
                refreshBalances(player);
                return false;
            }
            refreshBalances(player);
            return true;
        } catch (Exception e) {
            refreshBalances(player);
            return false;
        }
    }

    private void refundVnd(Player player, int amount) {
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(
                     "UPDATE account SET vnd = vnd + ? WHERE id = ?")) {
            ps.setInt(1, amount);
            ps.setInt(2, player.getSession().userId);
            ps.executeUpdate();
        } catch (Exception ignored) {
        }
        refreshBalances(player);
    }

    private boolean takePendingFromDatabase(Player player, int pending) {
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(
                     "UPDATE account SET thoi_vang = 0 WHERE id = ? AND thoi_vang = ?")) {
            ps.setInt(1, player.getSession().userId);
            ps.setInt(2, pending);
            return ps.executeUpdate() == 1;
        } catch (Exception e) {
            return false;
        }
    }

    private void restorePendingGoldBars(Player player, int amount) {
        try (Connection con = LocalManager.getConnection();
             PreparedStatement ps = con.prepareStatement(
                     "UPDATE account SET thoi_vang = thoi_vang + ? WHERE id = ?")) {
            ps.setInt(1, amount);
            ps.setInt(2, player.getSession().userId);
            ps.executeUpdate();
        } catch (Exception ignored) {
        }
    }
}
