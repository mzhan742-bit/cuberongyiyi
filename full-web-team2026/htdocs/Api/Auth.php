<?php
require_once '../Controllers/Configs.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * ================== DEV FLAGS ==================
 */
$DEV_SKIP_CSRF    = true;   // false khi deploy
$DEV_SKIP_CAPTCHA = false;  // true nếu muốn tắt captcha khi test

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Phương thức không được hỗ trợ');
    exit;
}

/**
 * ================== reCAPTCHA ==================
 */
define('RECAPTCHA_SECRET', '6LeXpz4sAAAAAD6HKafF2qPbHZwgGsBxHHViI_bN');

$action = $_POST['action'] ?? '';
$userIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

/**
 * ================== SANITIZE INPUT ==================
 */
$inputs = [];
foreach ($_POST as $k => $v) {
    if ($k === 'ref') {
        $inputs[$k] = is_numeric($v) ? intval($v) : 0;
    } else {
        $inputs[$k] = htmlspecialchars(strip_tags(trim($v)));
    }
}

/**
 * ================== CSRF ==================
 */
if (!$DEV_SKIP_CSRF) {
    if (!verifyToken($inputs['csrf_token'] ?? '')) {
        sendResponse('error', 'Token không hợp lệ hoặc đã hết hạn. Vui lòng tải lại trang.');
    }
} else {
    if (!empty($inputs['csrf_token']) && isset($_SESSION['csrf_tokens'][$inputs['csrf_token']])) {
        unset($_SESSION['csrf_tokens'][$inputs['csrf_token']]);
    }
}

try {
    /**
     * ================== CONNECT DB ==================
     */
    global $servers;
    $serverId = isset($inputs['server']) ? (int)$inputs['server'] : 1;
    $_SESSION['current_server'] = $serverId;

    $serverConfig = $servers[$serverId] ?? $servers[1];
    $Connect = connectDatabase($serverConfig);
    $GLOBALS['Connect'] = $Connect;

    /**
     * ================== CAPTCHA (REGISTER ONLY) ==================
     */
    if (
        !$DEV_SKIP_CAPTCHA &&
        $action === 'register'
    ) {
        verifyRecaptchaOrFail($userIP);
    }

    /**
     * ================== ROUTER ==================
     */
    switch ($action) {
        case 'login':
            handleLogin($inputs, $userIP);
            break;

        case 'register':
            handleRegistration($inputs);
            break;

        case 'forgotpassword':
            handlePasswordReset($inputs);
            break;

        default:
            sendResponse('error', 'Action không hợp lệ');
    }

} catch (PDOException $e) {
    sendResponse('error', 'Database Error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse('error', 'General Error: ' . $e->getMessage());
}

/**
 * =========================================================
 * ===================== HANDLERS ==========================
 * =========================================================
 */

function handleLogin($inputs, $userIP)
{
    if (empty($inputs['Username']) || empty($inputs['Password'])) {
        sendResponse('error', 'Vui lòng nhập đủ thông tin');
    }

    $stmt = $GLOBALS['Connect']->prepare(
        'SELECT id, username, password, ban FROM account WHERE username = :username'
    );
    $stmt->execute(['username' => $inputs['Username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) sendResponse('error', 'Tên tài khoản không tồn tại');
    if ((int)$user['ban'] === 1) sendResponse('error', 'Tài khoản đã bị khóa');

    if ($inputs['Password'] === $user['password']) {
        $_SESSION['ImSynZx_Login'] = $user['username'];
        updateUserIP($user['username'], $userIP);

        sendResponse('success', 'Đăng nhập thành công', [
            'username' => $user['username'],
            'user_id'  => $user['id']
        ]);
    } else {
        sendResponse('error', 'Mật khẩu không chính xác');
    }
}

function handleRegistration($inputs)
{
    if (
        empty($inputs['Username']) ||
        empty($inputs['Password']) ||
        empty($inputs['RePassword'])
    ) {
        sendResponse('error', 'Vui lòng nhập đủ thông tin');
    }

    if ($inputs['Password'] !== $inputs['RePassword']) {
        sendResponse('error', 'Mật khẩu xác nhận không khớp');
    }

    if (usernameExists($inputs['Username'])) {
        sendResponse('error', 'Tên người dùng đã tồn tại');
    }

    /**
     * ===== ref_id (3 tầng) =====
     */
    $ref_id = 0;
    if (!empty($inputs['ref']) && $inputs['ref'] > 0) {
        $ref_id = (int)$inputs['ref'];
    } elseif (!empty($_SESSION['ref_id']) && is_numeric($_SESSION['ref_id'])) {
        $ref_id = (int)$_SESSION['ref_id'];
    }

    /**
     * ===== INSERT =====
     */
    $stmt = $GLOBALS['Connect']->prepare(
        'INSERT INTO account (username, password, ref_id)
         VALUES (:username, :password, :ref_id)'
    );
    $stmt->execute([
        'username' => $inputs['Username'],
        'password' => $inputs['Password'],
        'ref_id'   => $ref_id
    ]);

    $_SESSION['ImSynZx_Login'] = $inputs['Username'];

    sendResponse('success', 'Đăng ký thành công', [
        'user_id'  => $GLOBALS['Connect']->lastInsertId(),
        'username' => $inputs['Username'],
        'ref_id'   => $ref_id
    ]);
}

function handlePasswordReset($inputs)
{
    if (empty($inputs['Email'])) {
        sendResponse('error', 'Vui lòng nhập email');
    }

    $stmt = $GLOBALS['Connect']->prepare(
        'SELECT email FROM account WHERE email = :email'
    );
    $stmt->execute(['email' => $inputs['Email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) sendResponse('error', 'Email không tồn tại');

    $otp = random_int(100000, 999999);
    $expires = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

    $GLOBALS['Connect']->prepare(
        'UPDATE account SET otp = :otp, expires = :expires WHERE email = :email'
    )->execute([
        'otp'     => $otp,
        'expires' => $expires,
        'email'   => $user['email']
    ]);

    sendResetEmail($user['email'], $otp);
    sendResponse('success', 'Đã gửi email xác nhận');
}

/**
 * =========================================================
 * ===================== HELPERS ===========================
 * =========================================================
 */

function verifyRecaptchaOrFail($userIP = null)
{
    $captcha = $_POST['g-recaptcha-response'] ?? '';
    if (empty($captcha)) {
        sendResponse('error', 'Vui lòng xác nhận CAPTCHA');
    }

    $postData = [
        'secret'   => RECAPTCHA_SECRET,
        'response' => $captcha
    ];
    if (!empty($userIP)) {
        $postData['remoteip'] = $userIP;
    }

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postData),
        CURLOPT_TIMEOUT        => 10
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false || empty($resp)) {
        sendResponse('error', 'Không thể xác minh CAPTCHA' . ($err ? ': '.$err : ''));
    }

    $result = json_decode($resp, true);
    if (empty($result['success'])) {
        $codes = isset($result['error-codes'])
            ? implode(', ', (array)$result['error-codes'])
            : '';
        sendResponse('error', 'CAPTCHA không hợp lệ' . ($codes ? " ($codes)" : ''));
    }
}

function updateUserIP($username, $ip)
{
    $GLOBALS['Connect']->prepare(
        'UPDATE account SET ip_address = :ip WHERE username = :username'
    )->execute([
        'ip'       => $ip,
        'username' => $username
    ]);
}

function usernameExists($username)
{
    $stmt = $GLOBALS['Connect']->prepare(
        'SELECT COUNT(*) FROM account WHERE username = :username'
    );
    $stmt->execute(['username' => $username]);
    return $stmt->fetchColumn() > 0;
}
