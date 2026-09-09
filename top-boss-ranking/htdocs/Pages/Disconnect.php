<?php
include '../Controllers/Header.php';
?>

<div class="body" style="padding: 20px; max-width: 900px; margin: auto;">

    <div style="text-align:center; margin-bottom: 15px;">
        <h2 style="color:#ff5601; margin-bottom: 5px;">🏆 BẢNG XẾP HẠNG</h2>
        <p style="font-size:14px; color:#444;">Xem top người chơi nổi bật trên máy chủ.</p>
    </div>

    <style>
        .bxh-box {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            margin-top: 25px;
            animation: fadeIn 0.5s ease;
        }
        .bxh-box h3 {
            background: linear-gradient(90deg, #ff5601, #ff7a00);
            color: #fff;
            padding: 8px;
            border-radius: 10px 10px 0 0;
            text-align: center;
            margin: 0;
            text-shadow: 1px 1px 2px #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            font-size: 15px;
        }
        th {
            background: #ff7a00;
            color: white;
            padding: 8px;
        }
        td {
            padding: 7px;
            border-bottom: 1px solid #eee;
        }
        tr:hover {
            background: #fff2e5;
        }
        @keyframes fadeIn {
            from {opacity: 0;}
            to {opacity: 1;}
        }
    </style>

    <!-- ===================== TOP NẠP ===================== -->
    <div class="bxh-box">
        <h3>💰 TOP NẠP COIN</h3>
        <div class="p-2">
            <table>
                <thead>
                    <tr>
                        <th>TOP</th>
                        <th>Tên nhân vật</th>
                        <th>Tổng nạp (VNĐ)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT player.name, account.tongnap
                              FROM player 
                              INNER JOIN account ON account.id = player.account_id
                              WHERE account.isFounder= 0 AND account.ban = 0 AND account.tongnap > 0
                              ORDER BY account.tongnap DESC LIMIT 15";
                    $result = $Connect->query($query);
                    $top = 1;
                    if ($result && $result->rowCount() > 0) {
                        foreach ($result as $row) {
                            echo "<tr>
                                <td>{$top}</td>
                                <td>" . htmlspecialchars($row['name']) . "</td>
                                <td>" . number_format($row['tongnap']) . "</td>
                            </tr>";
                            $top++;
                        }
                    } else {
                        echo '<tr><td colspan="3"><i>Chưa có dữ liệu</i></td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===================== TOP SỨC MẠNH ===================== -->
    <div class="bxh-box">
        <h3>💪 TOP SỨC MẠNH</h3>
        <div class="p-2">
            <table>
                <thead>
                    <tr>
                        <th>TOP</th>
                        <th>Tên nhân vật</th>
                        <th>Sức mạnh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT p.name,
                                     LEAST(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(p.data_point, ',', 2), ',', -1) AS UNSIGNED), 502000000000) AS sm
                              FROM player p
                              INNER JOIN account a ON a.id = p.account_id
                              WHERE a.isFounder = 0 AND a.ban = 0
                              ORDER BY sm DESC LIMIT 15";
                    $result = $Connect->query($query);
                    $top = 1;
                    if ($result && $result->rowCount() > 0) {
                        foreach ($result as $row) {
                            echo "<tr>
                                <td>{$top}</td>
                                <td>" . htmlspecialchars($row['name']) . "</td>
                                <td>" . number_format($row['sm']) . "</td>
                            </tr>";
                            $top++;
                        }
                    } else {
                        echo '<tr><td colspan="3"><i>Không có dữ liệu</i></td></tr>';
                    }
					// === TÍNH TỔNG SỨC MẠNH SERVER ===
$totalQuery = "SELECT SUM(LEAST(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(p.data_point, ',', 2), ',', -1) AS UNSIGNED), 502000000000)) AS total_sm
               FROM player p
               INNER JOIN account a ON a.id = p.account_id
               WHERE a.isFounder= 0 AND a.ban = 0";
$totalResult = $Connect->query($totalQuery);
$totalSm = $totalResult->fetchColumn() ?? 0;

echo "<tr style='background:#fff9f3; font-weight:bold; color:#ff5601;'>
        <td colspan='2'>Tổng sức mạnh toàn server</td>
        <td>" . number_format($totalSm-100000000000) . "</td>
      </tr>";

                    ?>
                </tbody>
            </table>
        </div>
    </div>


    <!-- ===================== TOP SĂN BOSS ===================== -->
    <div class="bxh-box">
        <h3>👹 TOP SĂN BOSS</h3>
        <div class="p-2">
            <table>
                <thead>
                    <tr>
                        <th>TOP</th>
                        <th>Tên nhân vật</th>
                        <th>Boss đã hạ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    /**
                     * data_achievement được game lưu theo dạng:
                     * ["[completed,isReceive]", ...]
                     * Achievement index 19 = TRUM_KET_LIEU_BOSS.
                     */
                    if (!function_exists('yiyiGetBossKillCount')) {
                        function yiyiGetBossKillCount($achievementJson): int
                        {
                            if (!is_string($achievementJson) || trim($achievementJson) === '') {
                                return 0;
                            }

                            $achievementData = json_decode($achievementJson, true);
                            if (!is_array($achievementData) || !array_key_exists(19, $achievementData)) {
                                return 0;
                            }

                            $bossAchievement = $achievementData[19];

                            // Source game lưu từng achievement thành JSON string.
                            if (is_string($bossAchievement)) {
                                $bossAchievement = json_decode($bossAchievement, true);
                            }

                            if (!is_array($bossAchievement)) {
                                return 0;
                            }

                            $completed = $bossAchievement[0] ?? 0;
                            return max(0, (int)$completed);
                        }
                    }

                    try {
                        $bossStmt = $Connect->query("
                            SELECT p.name, p.data_achievement
                            FROM player p
                            INNER JOIN account a ON a.id = p.account_id
                            WHERE a.isFounder = 0
                              AND a.ban = 0
                        ");

                        $bossRanking = [];
                        $totalBossKills = 0;

                        if ($bossStmt) {
                            while ($bossRow = $bossStmt->fetch(PDO::FETCH_ASSOC)) {
                                $bossKills = yiyiGetBossKillCount($bossRow['data_achievement'] ?? '');

                                if ($bossKills <= 0) {
                                    continue;
                                }

                                $bossRanking[] = [
                                    'name' => (string)$bossRow['name'],
                                    'boss_kills' => $bossKills
                                ];

                                $totalBossKills += $bossKills;
                            }
                        }

                        usort($bossRanking, static function ($a, $b) {
                            if ($a['boss_kills'] === $b['boss_kills']) {
                                return strcmp($a['name'], $b['name']);
                            }
                            return $b['boss_kills'] <=> $a['boss_kills'];
                        });

                        $bossRanking = array_slice($bossRanking, 0, 15);

                        if (!empty($bossRanking)) {
                            $topBoss = 1;

                            foreach ($bossRanking as $bossPlayer) {
                                echo "<tr>
                                    <td>{$topBoss}</td>
                                    <td>" . htmlspecialchars($bossPlayer['name'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . number_format($bossPlayer['boss_kills']) . "</td>
                                </tr>";
                                $topBoss++;
                            }

                            echo "<tr style='background:#fff9f3; font-weight:bold; color:#ff5601;'>
                                <td colspan='2'>Tổng Boss đã hạ toàn server</td>
                                <td>" . number_format($totalBossKills) . "</td>
                            </tr>";
                        } else {
                            echo '<tr><td colspan="3"><i>Chưa có dữ liệu săn Boss</i></td></tr>';
                        }
                    } catch (Throwable $e) {
                        echo "<tr><td colspan='3'><i>Lỗi đọc dữ liệu săn Boss: "
                            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                            . "</i></td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===================== TOP NHIỆM VỤ ===================== -->
    <div class="bxh-box">
        <h3>📜 TOP NHIỆM VỤ</h3>
        <div class="p-2">
            <table>
                <thead>
                    <tr>
                        <th>TOP</th>
                        <th>Tên nhân vật</th>
                        <th>Nhiệm vụ chính</th>
                        <th>Nhiệm vụ phụ</th>
                        <th>Hoàn thành</th>
                    </tr>
                </thead>
                <tbody>
                   <?php
$query = "
    SELECT 
        p.name,
        -- Tách thông tin từ data_task
        CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(p.data_task, ',', 1), '[', -1) AS UNSIGNED) AS main_id,
        CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(p.data_task, ',', 2), ',', -1) AS UNSIGNED) AS sub_index,
        CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(p.data_task, ',', 3), ',', -1) AS UNSIGNED) AS completed_count,
        mt.NAME AS main_task_name
    FROM player p
    INNER JOIN account a ON a.id = p.account_id
    LEFT JOIN task_main_template mt 
        ON mt.id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(p.data_task, ',', 1), '[', -1) AS UNSIGNED)
    WHERE a.isFounder = 0 
      AND a.ban = 0
    ORDER BY main_id DESC, sub_index DESC, completed_count DESC
    LIMIT 20;
";

try {
    $result = $Connect->query($query);
    $top = 1;

    if ($result && $result->rowCount() > 0) {
        foreach ($result as $row) {
            $mainId = intval($row['main_id']);
            $subIndex = intval($row['sub_index']);
            $count = intval($row['completed_count']);
            $mainName = htmlspecialchars($row['main_task_name'] ?? 'Không rõ');
            $subName = 'Chưa có';

            // 🔍 Lấy nhiệm vụ phụ tương ứng theo thứ tự index (chính xác)
            $subQuery = $Connect->prepare("
                SELECT NAME 
                FROM task_sub_template 
                WHERE task_main_id = :main_id
                ORDER BY ducvupro ASC 
                LIMIT :offset, 1
            ");
            $subQuery->bindValue(':main_id', $mainId, PDO::PARAM_INT);
            $subQuery->bindValue(':offset', $subIndex, PDO::PARAM_INT);
            $subQuery->execute();
            $subName = $subQuery->fetchColumn() ?: 'Chưa có';

            echo "<tr>
                <td>{$top}</td>
                <td>" . htmlspecialchars($row['name']) . "</td>
                <td>{$mainName}</td>
                <td>{$subName}</td>
                <td>{$count}</td>
            </tr>";
            $top++;
        }
    } else {
        echo '<tr><td colspan="5"><i>Không có dữ liệu</i></td></tr>';
    }
} catch (PDOException $e) {
    echo "<tr><td colspan='5'><i>Lỗi đọc dữ liệu nhiệm vụ: " . htmlspecialchars($e->getMessage()) . "</i></td></tr>";
}
?>


                </tbody>
            </table>
        </div>
       


    </div>

</div>

<?php include '../Controllers/Footer.php'; ?>
