<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 31556926);
    ini_set('session.cookie_lifetime', 31556926);
    session_set_cookie_params(31556926);
    session_start();
}

// Load file .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

// Hàm kết nối database
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

// HOTFIX TEAM2026:
// Source cũ dùng DB_NAME=awnv3. Nếu gặp đúng tên cũ này thì tự chuyển sang team2026.
// Nếu sau này bạn đặt một DB_NAME khác rõ ràng trong .env thì vẫn tôn trọng giá trị đó.
$dbName = trim((string)(getenv('DB_NAME') ?: ''));
if ($dbName === '' || strtolower($dbName) === 'awnv3') {
    $dbName = 'team2026';
}

// Cấu hình server
$servers = [
    1 => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'name' => $dbName,
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'partner_key' => getenv('PARTNER_KEY_S1') ?: '',
        'partner_id' => getenv('PARTNER_ID_S1') ?: ''
    ]
];

$currentServer = 1;
$Connect = connectDatabase($servers[$currentServer]);

// Config API, Gmail, Domain
const Domain = 'http://cauberongyiyi.online';
const APP_NAME = 'Cậu Bé Rồng YiYi';
const SUPPORT_EMAIL = 'support@example.com';
const FGmail = '';
const FPGmail = '';
const FTitle = 'Cậu Bé Rồng YiYi';
const FName = 'Cậu Bé Rồng YiYi';

// Link tải game
const Android   = '/downloads/cau-be-rong-yiyi-android.apk';
const Android_2 = Android;
const PC        = '/downloads/cau-be-rong-yiyi-pc.rar';
const TestFlight = '/downloads/cau-be-rong-yiyi-ios.ipa';
const TestFlight_2 = '/downloads/cau-be-rong-yiyi-ios.ipa';
const Java = 'http://cauberongyiyi.online/Trang-Chu';

const ZALO   = 'https://zalo.me/g/is0anporaqff3iaxhzxd';
const ZALO_2 = 'https://zalo.me/g/2ien298n99nl8rt92yfc';

// API nạp thẻ đã tắt ở patch web trước; giữ constant để tương thích source.
const API_CARD_URL = '/downloads/cau-be-rong-yiyi-ios.ipa';
define('PARTNER_KEY', $servers[$currentServer]['partner_key']);
define('PARTNER_ID',  $servers[$currentServer]['partner_id']);

// ========== Helper Functions ==========
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
        'năm'   => 31536000,
        'tháng' => 2592000,
        'tuần'  => 604800,
        'ngày'  => 86400,
        'giờ'   => 3600,
        'phút'  => 60,
        'giây'  => 1
    ];

    foreach ($units as $unit => $value) {
        if ($diff >= $value) {
            $count = floor($diff / $value);
            return "$count $unit trước";
        }
    }
    return "Vừa xong";
}

// ========== Check login session ==========
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
        echo "Lỗi hệ thống: " . $e->getMessage();
    }
}
