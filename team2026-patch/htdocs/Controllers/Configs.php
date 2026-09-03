<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 31556926);
    ini_set('session.cookie_lifetime', 31556926);
    session_set_cookie_params(31556926);
    session_start();
}

// Load file .env hiện có - không ép đổi host/user/password của máy chủ.
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile) as $line) {
        if (!isset($line[0]) || $line[0] === '#') {
            continue;
        }
        if (str_contains($line, '=')) {
            [$name, $value] = explode('=', trim($line), 2);
            putenv("$name=$value");
        }
    }
}

function connectDatabase($config)
{
    return new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['name']),
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
}

// Chỉ đổi database web sang team2026. Host/user/pass và key vẫn lấy từ .env hiện tại.
$serverBase = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'name' => 'team2026',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: '',
    'partner_key' => getenv('PARTNER_KEY_S1') ?: '',
    'partner_id' => getenv('PARTNER_ID_S1') ?: ''
];

$servers = [
    1 => $serverBase,
    // Source CronAcb có nhánh S2. Khi chỉ dùng một DB team2026, trỏ S2 về cùng DB để không lỗi undefined server.
    2 => $serverBase
];

$currentServer = 1;
$Connect = connectDatabase($servers[$currentServer]);

// Tự tạo lớp tương thích schema đúng một lần; không xóa dữ liệu game.
require_once __DIR__ . '/Team2026Compat.php';
ensureTeam2026Compatibility($Connect);

// Chuẩn hóa biến $Settings mà các file Cron ACB/MBBank cũ đang gọi.
$Settings = [];
try {
    $settingStmt = $Connect->query("SELECT * FROM settings LIMIT 1");
    $Settings = $settingStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!isset($Settings['Username'])) {
        $Settings['Username'] = $Settings['AccountBank'] ?? '';
    }
    if (!isset($Settings['Password'])) {
        $Settings['Password'] = $Settings['PasswordBank'] ?? '';
    }
} catch (Throwable $e) {
    error_log('[YiYi Settings] ' . $e->getMessage());
}

// Config API, Gmail, Domain - giữ nguyên cấu trúc source hiện tại.
const Domain = '160.191.55.0';
const APP_NAME = 'Ngọc Rồng Lùa Gà';
const SUPPORT_EMAIL = 'support@example.com';
const FGmail = '';
const FPGmail = '';
const FTitle = 'Ngọc Rồng Lùa Gà';
const FName = 'Ngọc Rồng Lùa Gà';

const Android = '#';
const Android_2 = Android;
const PC = '#';
const TestFlight = '#';
const TestFlight_2 = '#';
const Java = '#';

const ZALO = '#';
const ZALO_2 = '#';

const API_CARD_URL = 'https://doithe1s.vn/';
define('PARTNER_KEY', $servers[$currentServer]['partner_key']);
define('PARTNER_ID', $servers[$currentServer]['partner_id']);

function sendResponse($status, $message, $data = [])
{
    header('Content-Type: application/json; charset=utf-8');
    $res = ['status' => $status, 'message' => $message];
    if (!empty($data)) {
        $res['data'] = $data;
    }
    echo json_encode($res);
    exit;
}

function generateToken()
{
    if (!isset($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_tokens'][$token] = true;
    return $token;
}

function verifyToken($token)
{
    if (isset($_SESSION['csrf_tokens'][$token])) {
        unset($_SESSION['csrf_tokens'][$token]);
        return true;
    }
    return false;
}

function Money($amount): string
{
    return $amount >= 1000000000 ? number_format($amount / 1000000000, 1) . ' tỷ' :
        ($amount >= 1000000 ? number_format($amount / 1000000, 1) . ' triệu' :
            number_format($amount) . ' VNĐ');
}

function getStatusText($status): string
{
    return $status == 0 ? 'Chưa hoàn chỉnh' : 'Đã hoàn thành';
}

function getStatusCard($status): string
{
    return match ($status) {
        0 => 'Chưa xử lý',
        1 => 'Đã hoàn thành',
        2 => 'Thẻ lỗi/sai',
        3 => 'Thẻ sai',
        99 => 'Chờ duyệt',
        default => 'Không xác định',
    };
}

function timeAgo($datetime)
{
    $ts = strtotime($datetime);
    $diff = time() - $ts;

    $units = [
        'năm' => 31536000,
        'tháng' => 2592000,
        'tuần' => 604800,
        'ngày' => 86400,
        'giờ' => 3600,
        'phút' => 60,
        'giây' => 1
    ];

    foreach ($units as $unit => $value) {
        if ($diff >= $value) {
            $count = floor($diff / $value);
            return "$count $unit trước";
        }
    }
    return 'Vừa xong';
}

$Login = false;
$ImS = null;
$IHero = null;

if (isset($_SESSION['ImSynZx_Login'])) {
    try {
        $stmt = $Connect->prepare("SELECT * FROM account WHERE username = :username");
        $stmt->execute(['username' => $_SESSION['ImSynZx_Login']]);
        $ImS = $stmt->fetch();

        if ($ImS && isset($ImS['id'])) {
            $Login = true;
            $stmt2 = $Connect->prepare("SELECT * FROM player WHERE account_id = :id");
            $stmt2->execute(['id' => $ImS['id']]);
            $IHero = $stmt2->fetch() ?: null;
        } else {
            unset($_SESSION['ImSynZx_Login']);
        }
    } catch (PDOException $e) {
        echo 'Lỗi hệ thống: ' . $e->getMessage();
    }
}
