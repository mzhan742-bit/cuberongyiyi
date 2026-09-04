package nro.models.npc_list;

import java.time.LocalDate;
import java.time.LocalDateTime;
import nro.models.consts.ConstNpc;
import nro.models.consts.ConstTask;
import nro.models.item.Item;
import nro.models.services.AchievementService;
import nro.models.npc.Npc;
import nro.models.player.Player;
import nro.models.services.InventoryService;
import nro.models.services.YiYiPaymentService;
import nro.models.services.ItemService;
import nro.models.services.PlayerService;
import nro.models.services.Service;
import nro.models.services.TaskService;
import nro.models.services_func.Input;

/**
 *
 * @author By Mr Blue
 *
 */
public class BoMong extends Npc {

    private static final int MENU_YIYI_PAYMENT = 91001;
    private static final int MENU_YIYI_GOLDBAR = 91002;
    private static final int MENU_YIYI_RUBY = 91003;

    public BoMong(int mapId, int status, int cx, int cy, int tempId, int avartar) {
        super(mapId, status, cx, cy, tempId, avartar);
    }

    @Override
    public void openBaseMenu(Player player) {
        if (!TaskService.gI().checkDoneTaskTalkNpc(player, this)) {
            if (canOpenNpc(player)) {
                if (this.mapId == 47 || this.mapId == 84) {
                    this.createOtherMenu(player, ConstNpc.BASE_MENU,
                            "Ngươi muốn có thêm ngọc thì chịu khó làm vài nhiệm vụ sẽ được ngọc thưởng", "Nhiệm vụ\nhàng ngày", "Nhiệm vụ\nthành tích", "Nạp /\nQuy đổi", "Điểm danh", "Từ chối");
                }
            }
        }
    }

    @Override
    public void confirmMenu(Player player, int select) {
        if (canOpenNpc(player)) {
            if (this.mapId == 47 || this.mapId == 84) {
                if (player.idMark.isBaseMenu()) {
                    switch (select) {
                        case 0 -> {
                            if (player.playerTask.sideTask.template != null) {
                                String npcSay = "Nhiệm vụ hiện tại: " + player.playerTask.sideTask.getName() + " ("
                                        + player.playerTask.sideTask.getLevel() + ")"
                                        + "\nHiện tại đã hoàn thành: " + player.playerTask.sideTask.count + "/"
                                        + player.playerTask.sideTask.maxCount + " ("
                                        + player.playerTask.sideTask.getPercentProcess() + "%)\nSố nhiệm vụ còn lại trong ngày: "
                                        + player.playerTask.sideTask.leftTask + "/" + ConstTask.MAX_SIDE_TASK;
                                this.createOtherMenu(player, ConstNpc.MENU_OPTION_PAY_SIDE_TASK,
                                        npcSay, "Trả nhiệm\nvụ", "Hủy nhiệm\nvụ");
                            } else {
                                this.createOtherMenu(player, ConstNpc.MENU_OPTION_LEVEL_SIDE_TASK,
                                        "Tôi có vài nhiệm vụ theo cấp bậc, "
                                        + "sức cậu có thể làm được cái nào?",
                                        "Dễ", "Bình thường", "Khó", "Từ chối");
                            }
                        }
                        case 1 -> {
                            AchievementService.gI().openAchievementUI(player);
                        }
                        case 2 -> {
                            openYiYiPaymentMenu(player);
                        }
                        case 3 -> {
                            if (player.lastCheckIn != null) {
                                LocalDate last = player.lastCheckIn.toLocalDate();
                                LocalDate today = LocalDate.now();
                                if (last.isEqual(today)) {
                                    Service.gI().sendThongBao(player, "Bạn đã điểm danh hôm nay rồi!");
                                    return;
                                }
                            }
                            player.lastCheckIn = LocalDateTime.now();
                            player.inventory.gem += 10000;
                            Item item457 = ItemService.gI().createNewItem((short) 457);
                            item457.quantity = 100;
                            item457.itemOptions.add(new Item.ItemOption(30, 0));
                            InventoryService.gI().addItemBag(player, item457);
                            PlayerService.gI().sendInfoHpMpMoney(player);
                            InventoryService.gI().sendItemBags(player);

                            Service.gI().sendThongBao(player, "Điểm danh thành công! Bạn nhận được 10.000 ngọc và 100 thỏi vàng.");
                        }

                    }
                } else if (player.idMark.getIndexMenu() == MENU_YIYI_PAYMENT) {
                    switch (select) {
                        case 0 -> openYiYiGoldBarMenu(player);
                        case 1 -> openYiYiRubyMenu(player);
                        case 2 -> Input.gI().createFormTradeRubyFromGoldBar(player);
                        case 3 -> YiYiPaymentService.gI().claimPendingGoldBars(player);
                        default -> { }
                    }
                } else if (player.idMark.getIndexMenu() == MENU_YIYI_GOLDBAR) {
                    if (select >= 0 && select < 11) {
                        YiYiPaymentService.gI().buyGoldBarPackage(player, select);
                    }
                } else if (player.idMark.getIndexMenu() == MENU_YIYI_RUBY) {
                    if (select >= 0 && select < 11) {
                        YiYiPaymentService.gI().buyRubyPackage(player, select);
                    }
                } else if (player.idMark.getIndexMenu() == ConstNpc.MENU_OPTION_LEVEL_SIDE_TASK) {
                    switch (select) {
                        case 0, 1, 2 ->
                            TaskService.gI().changeSideTask(player, (byte) select);
                    }
                } else if (player.idMark.getIndexMenu() == ConstNpc.MENU_OPTION_PAY_SIDE_TASK) {
                    switch (select) {
                        case 0 ->
                            TaskService.gI().paySideTask(player);
                        case 1 ->
                            TaskService.gI().removeSideTask(player);
                    }
                }
            }
        }
    }

    private void openYiYiPaymentMenu(Player player) {
        YiYiPaymentService.gI().refreshBalances(player);
        this.createOtherMenu(player, MENU_YIYI_PAYMENT,
                "|2|NẠP & QUY ĐỔI YIYI\n"
                + "|0|Số dư: " + player.getSession().vnd + " VNĐ\n"
                + "|7|Thỏi vàng chờ nhận từ web: " + player.getSession().goldBar,
                "Nạp\nThỏi vàng", "Nạp\nHồng ngọc",
                "Thỏi vàng\n→ Hồng ngọc", "Nhận Thỏi\ntừ Web", "Đóng");
    }

    private void openYiYiGoldBarMenu(Player player) {
        YiYiPaymentService.gI().refreshBalances(player);
        this.createOtherMenu(player, MENU_YIYI_GOLDBAR,
                "|2|Bảng Giá Nạp Thỏi Vàng (YiYi)\n\n"
                + "5K → 30 | 10K → 70 | 20K → 150\n"
                + "50K → 400 | 100K → 850 | 200K → 1.800\n"
                + "500K → 4.700 | 1M → 10.000 | 2M → 22.000\n"
                + "5M → 75.000 | 10M → 200.000\n\n"
                + "|0|Số dư: " + player.getSession().vnd + " VNĐ",
                "Nạp [5K]", "Nạp [10K]", "Nạp [20K]", "Nạp [50K]",
                "Nạp [100K]", "Nạp [200K]", "Nạp [500K]", "Nạp [1M]",
                "Nạp [2M]", "Nạp [5M]", "Nạp [10M]", "Đóng");
    }

    private void openYiYiRubyMenu(Player player) {
        YiYiPaymentService.gI().refreshBalances(player);
        this.createOtherMenu(player, MENU_YIYI_RUBY,
                "|2|Bảng Giá Nạp Hồng Ngọc (YiYi)\n\n"
                + "5K → 160 | 10K → 330 | 20K → 680\n"
                + "50K → 1.800 | 100K → 3.800 | 200K → 7.500\n"
                + "500K → 20.000 | 1M → 45.000 | 2M → 100.000\n"
                + "5M → 300.000 | 10M → 1.000.000\n\n"
                + "|0|Số dư: " + player.getSession().vnd + " VNĐ",
                "Nạp [5K]", "Nạp [10K]", "Nạp [20K]", "Nạp [50K]",
                "Nạp [100K]", "Nạp [200K]", "Nạp [500K]", "Nạp [1M]",
                "Nạp [2M]", "Nạp [5M]", "Nạp [10M]", "Đóng");
    }

}
