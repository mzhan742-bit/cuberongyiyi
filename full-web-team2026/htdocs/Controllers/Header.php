<?php
require_once 'Configs.php';
if ($Login && str_contains($_SERVER['REQUEST_URI'], '/Auth/')) {
    die('<script>window.location.href = "/Forum";</script>');
}
if (
    !$Login &&
    str_contains($_SERVER['REQUEST_URI'], '/Users') &&
    !str_contains($_SERVER['REQUEST_URI'], '/Users/Post')
) {
    die('<script>window.location.href = "/Auth/Lor#login";</script>');
}

if (!$Login && str_contains($_SERVER['REQUEST_URI'], '/Users/Uploads') && (!isset($ImS['isAdmin']) || $ImS['isAdmin'] != 1)) {
    die('<script>window.location.href = "/Forum";</script>');
}

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Ngọc Rồng Lùa Gà - Trang Chủ Game Ngọc Rồng Online</title>
    <meta name="keywords" content="Chú Bé Rồng Online,ngoc rong mobile, game ngoc rong, game 7 vien ngoc rong, game bay vien ngoc rong" />
    <meta name="description" content="Website chính thức của Chú Bé Rồng Online – Game Bay Vien Ngoc Rong Mobile nhập vai trực tuyến trên máy tính và điện thoại về Game 7 Viên Ngọc Rồng hấp dẫn nhất hiện nay!" />
    <meta http-equiv="refresh" content="600" />
    <meta name="robots" content="INDEX,FOLLOW" />

    <link rel="apple-touch-icon" href="/images/favicon-48x48.ico" />
    <link rel="icon" href='/images/favicon-48x48.ico' type="image/x-icon" />
    <link rel="shortcut icon" href='/images/favicon-48x48.ico' type="image/x-icon" />
    <link rel="icon" href="/images/favicon-48x48.ico">
    <link rel="icon" type="image/png" href="/images/favicon-32x32.png" sizes="32x32">
    <link rel="icon" type="image/png" href="/images/favicon-64x64.png" sizes="64x64">
    <link rel="icon" type="image/png" href="/images/favicon-128x128.png" sizes="128x128">
    <link rel="icon" type="image/png" href="/images/favicon-48x48.png" sizes="48x48">
    <!-- Css -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/view/static/css/template.css?v=1.10">
    <link rel="stylesheet" href="/view/static/css/eff.css?v=1.00">
    <link rel="stylesheet" href="/view/static/css/w3.css?v=1.01">
    <link rel="stylesheet" href="/view/static/css/styleSheet.css?v=1.1">
     <script src="https://www.google.com/recaptcha/api.js"></script>
    
</head>

<body>
    <div class="snowEffect">
        <canvas id="snowcanvas" height="100%" width="100%"></canvas>
    </div>

    <div style="position: relative;" class="body_body">
        <a href="#" id="backTop"><img id='backTopimg' src='/images/favicon-32x32.png' alt='top' /> </a>

        <div class="div-12">
            <img height=12 src="/images/12.png" style="vertical-align: middle;" />
            <span style="vertical-align: middle;">Dành cho người chơi trên 12 tuổi. Chơi quá 180 phút mỗi ngày sẽ hại sức khỏe.
            </span>
        </div>
        <div class="left_top"></div>
        <div class="bg_top">
            <div class="right_top"></div>
        </div>
        <div class="body-content">
            <div class="bg-content2">
                <h1 class="a">
                    <a href="/" title="game bảy viên Chú Bé Rồng Online">
                        <img height=90 src="/images/logoluaga.png" alt="game bảy viên Chú Bé Rồng Online" /></a>
                </h1>
                <div id="top">
                    <div class="link-more">
                        <div class="h">
                            <div class="bg_noel"></div>
                            <div class="h">
                                <div class="menu2">
                                    <table width="100%" cellspacing="4">
                                        <tr class="menu">
                                            <td>
                                                <a href="/Trang-Chu">Trang Chủ</a>
                                            </td>
                                            <td>
                                                <a href="/Gioi-Thieu">Giới Thiệu</a>
                                            </td>
                                            <td>
                                                <a href="/Forum" title="Diễn Đàn">Diễn Đàn</a>
                                            </td>
                                            <td>
                                                <a href="https://www.facebook.com/">Fanpage</a>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    var currentUrl = window.location.pathname;
                                    document.querySelectorAll(".menu a").forEach(function(link) {
                                        if (link.getAttribute("href") === currentUrl) {
                                            document.querySelector("#selected")?.removeAttribute("id");
                                            link.parentElement.id = "selected";
                                        }
                                    });
                                });
                            </script>
                            <div class="body">
                                <?php $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                                $base_name = basename($current_path);

                                if ($Login == false && !in_array($base_name, ['Trang-Chu', 'Gioi-Thieu'])) {  ?>
                                    <div class="box_inputboxx" style="width:100%">
                                        <div class="box_button_login" style="width:100%; position: relative; text-align:center;">
                                            <a id="tab-login" href="/Auth/Lor#login">
                                                <button class="w3-button w3-red w3-small w3-hover-green">Đăng nhập</button>
                                            </a>
                                            <a id="tab-register" href="/Auth/Lor#register">
                                                <button class="w3-button w3-red w3-small w3-hover-green">Đăng ký</button>
                                            </a>
                                        </div>
                                    </div>
                                    <br>

                                <?php } else { ?>
                                    <div style="width:100%;float:left;">
                                        <table style="margin-left:auto;margin-right:auto;text-align:left;">
                                            <tbody>
                                                <tr>
                                                    <td>
                                                    </td>
                                                    <td colspan="5" align="center">
                                                        <?php if ($Login !== false && $ImS !== null) { ?>
                                                            <?php
                                                            if ($IHero != null) {
                                                                if ($IHero['gender'] == 1) {
                                                                    $imgUrl = '/images/avatar/namec.png';
                                                                } elseif ($IHero['gender'] == 0) {
                                                                    $imgUrl = '/images/avatar/traidat.png';
                                                                } elseif ($IHero['gender'] == 2) {
                                                                    $imgUrl = '/images/avatar/xayda.png';
                                                                }
                                                            } else {
                                                                $imgUrl = '/images/avatar/6101.gif';
                                                            }
                                                            ?>
                                                            <div class="box_welcome_team">
                                                                <span>
                                                                    <img src="<?= $imgUrl ?>" <?= $IHero === null ? 'style="width: 25%"' : '' ?> alt="Avatar">
                                                                    <br>
                                                                    <b style="color:#ad4105"><?= htmlspecialchars($ImS['username'] ?? 'Không xác định') ?></b><br>
                                                                    <b style="color:#ad4105">Số Dư: <?= Money($ImS['vnd'] ?? 0) ?></b>
                                                                    <br>
                                                                    <?php if ($Login && isset($ImS['isFounder']) && $ImS['isFounder'] == 1) { ?>
																	<a class="w3-button w3-red w3-small w3-hover-green" href="/Users/Admin">Thống Kê</a>
                                                                        <a class="w3-button w3-red w3-small w3-hover-green" href="/Users/Uploads">Đăng Bài</a>
                                                                          <a class="w3-button w3-red w3-small w3-hover-green" href="/Users/Thuhoi">Thu hồi vật phẩm</a>
                                                                          <a class="w3-button w3-red w3-small w3-hover-green" href="/Users/Lsgiaodich">Lịch sử giao dịch</a>
                                                                           <a class="w3-button w3-red w3-small w3-hover-green" href="/Users/CheckData">Check dữ liệu</a>
                                                                    <?php } ?>
                                                                      

<a href="/invite.php" class="w3-button w3-red w3-small w3-hover-green" style="font-size:11px;">Mời bạn bè</a>

                                                                    <a class="w3-button w3-red w3-small w3-hover-green" href="/Users/ChangePassword" style="font-size:11px;">Đổi Mật Khẩu</a>
                                                                    <a class="w3-button w3-red w3-small w3-hover-green" href="/Api/Logout">Thoát</a>
                                                                </span>
                                                            </div>
                                                        <?php } ?>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php } ?>


                                <?php if (!str_contains($_SERVER['REQUEST_URI'], '/Auth/')) { ?>
                                    <div id="box_login_ads">
                                        <div id="columns" style="text-align:center">
                                            <figure>
											<a href="<?= Java ?>" target="_blank"> <img height="35"
                                                        src="/images/jar.png" alt="CHÚ BÉ RỒNG ONLINE"></a>

                                                <br>
                                                </a>
                                                <figcaption>
                                                    <span style="color:rgb(209, 9, 50);">240</span> - <a href="#"
                                                        onclick="if (!window.__cfRLUnblockHandlers) return false; openWinjad()"
                                                        title="CHÚ BÉ RỒNG ONLINE" target="_blank""">
                                                        Jar
                                                    </a>
                                                    <br> <br>
                                                </figcaption>
                                            </figure>

                                            <figure>
                                               <a href="<?= Android ?>" title="Cậu Bé Rồng">
                                                    <img height="35" src="/images/android.png" alt="Cậu Bé Rồng">
                                                </a>
                                                <figcaption><span style="color:rgb(209, 9, 50);">2.4.8</span>
                                                    <br>
                                                    <a href="/?c=huong-dan">Hướng dẫn cài</a>
                                                </figcaption>
                                            </figure>
                                            <figure>
                                                <a href="<?= PC ?>" title="Cậu Bé Rồng">
                                                    <img height="35" src="/images/pc.png" alt="Cậu Bé Rồng">
                                                </a>
                                                <figcaption><span style="color:rgb(209, 9, 50);">2.4.8</span>
                                                    <br> <br>
                                                </figcaption>
                                            </figure>
                                            <figure>
											<a href="<?= TestFlight_2 ?>" title="Cậu Bé Rồng">
                                                    <img height="35" src="/images/ip.png" alt="Cậu Bé Rồng">
                                                </a>
                                                <figcaption><span style="color:rgb(209, 9, 50);">IPA</span>
                                                    <br> <br>
                                                </figcaption>
                                            </figure>
                                            <figure>
											<a href="<?= TestFlight ?>" title="Cậu Bé Rồng">
                                                    <img height="35" src="/images/ip.png" alt="Cậu Bé Rồng">
                                                </a>
                                                <figcaption><span style="color:rgb(209, 9, 50);">2.4.8</span>
                                                    <br> <br>
                                                </figcaption>
                                            </figure>
                                        </div>
                                        <div id="columns" class="text-center">
                                            <figure>
                                                <a href="/Users/Payments" title="Cậu Bé Rồng"><img src="/images/napngoc.png" height="35">
                                                </a>
                                                <figcaption>
                                                    <span style="color:rgb(209, 9, 50);"><a href="#">Báo Lỗi SMS</a></span><br>
                                                    <span style="color:rgb(209, 9, 50);"><a href="#">Báo Lỗi thẻ</a></span>
                                                </figcaption>
                                            </figure>
                                            <figure>
											<a href="<?= ZALO ?>" title="Box Zalo"><img src="/images/zalo3.png" height="35">
                                                </a>
                                                <figcaption>
                                                     <span style="color:rgb(209, 9, 50);"><a href="#">Tham gia</a></span><br>
                                                     <span style="color:rgb(209, 9, 50);"><a href="#">Box Chat 1</a></span><br>
										    </figure>
											<figure>
											<a href="<?= ZALO_2 ?>" title="Box Zalo"><img src="/images/zalo3.png" height="35">
                                                </a>
                                                <figcaption>
                                                    <span style="color:rgb(209, 9, 50);"><a href="#">Tham gia</a></span><br>
                                                     <span style="color:rgb(209, 9, 50);"><a href="#">Box Chat 2</a></span><br>
													  
										    </figure>
                                            <figure>											
                                                <a href="https://ngocrongluaga/Users/Giftcode" title="Nhận Code"><img src="/images/codee.png"
                                                        height="35">
                                                </a>
												
                                                <figcaption>
                                                    <br>
                                                    <br>
                                                </figcaption>
                                            </figure>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>