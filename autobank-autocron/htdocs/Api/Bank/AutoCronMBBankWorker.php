<?php
/**
 * YiYi Autobank AutoCron Worker
 * Chạy nền bằng PHP CLI, mặc định quét SePay mỗi 10 giây.
 *
 * Không chứa API key trong file này.
 * API key được đọc từ CronMBBank.php hiện có của bạn.
 * Database được đọc từ Controllers/.env; nếu DB_NAME=awnv3 hoặc rỗng
 * thì tự dùng team2026.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

const YIYI_AUTOCRON_INTERVAL = 10;
const YIYI_AUTOCRON_ERROR_INTERVAL = 30;

$bankDir = __DIR__;
$controllersDir = dirname(dirname($bankDir)) . DIRECTORY_SEPARATOR . 'Controllers';
$envFile = $controllersDir . DIRECTORY_SEPARATOR . '.env';
$cronFile = $bankDir . DIRECTORY_SEPARATOR . 'CronMBBank.php';
$mbBankFile = $bankDir . DIRECTORY_SEPARATOR . 'MBBank.php';
$lockFile = $bankDir . DIRECTORY_SEPARATOR . 'autocron_worker.lock';
$stopFile = $bankDir . DIRECTORY_SEPARATOR . 'autocron_worker.stop';
$logFile = $bankDir . DIRECTORY_SEPARATOR . 'autocron_worker.log';

function yiyiAutoLog(string $message): void
{
    global $logFile;
    @file_put_contents(
        $logFile,
        '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
        FILE_APPEND
    );

    // Giữ log gọn, tối đa khoảng 2 MB.
    clearstatcache(true, $logFile);
    if (is_file($logFile) && filesize($logFile) > 2 * 1024 * 1024) {
        @rename($logFile, $logFile . '.old');
    }
}

function yiyiLoadEnv(string $path): array
{
    $env = [];
    if (!is_file($path)) {
        return $env;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }

    return $env;
}

function yiyiReadApiKey(string $cronFile): string
{
    $source = @file_get_contents($cronFile);
    if ($source === false) {
        return '';
    }

    if (preg_match('/\$MBBANK_API_KEY\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $source, $m)) {
        return trim($m[1]);
    }

    return '';
}

function yiyiOpenTeam2026(array $env): PDO
{
    $host = trim((string)($env['DB_HOST'] ?? 'localhost'));
    $name = trim((string)($env['DB_NAME'] ?? ''));
    $user = (string)($env['DB_USER'] ?? 'root');
    $pass = (string)($env['DB_PASS'] ?? '');

    if ($name === '' || strtolower($name) === 'awnv3') {
        $name = 'team2026';
    }

    return new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name),
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );
}

if (!is_file($mbBankFile)) {
    yiyiAutoLog('STOP: Không tìm thấy MBBank.php');
    exit(2);
}

if (!is_file($cronFile)) {
    yiyiAutoLog('STOP: Không tìm thấy CronMBBank.php hiện có');
    exit(3);
}

require_once $mbBankFile;

$lockHandle = @fopen($lockFile, 'c+');
if (!$lockHandle) {
    yiyiAutoLog('STOP: Không tạo được lock file');
    exit(4);
}

if (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
    // Đã có worker khác chạy.
    fclose($lockHandle);
    exit(0);
}

@unlink($stopFile);

$apiKey = yiyiReadApiKey($cronFile);
if ($apiKey === '') {
    yiyiAutoLog('STOP: Không đọc được API key từ CronMBBank.php');
    @flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(5);
}

yiyiAutoLog('START: AutoCron Team2026, chu kỳ ' . YIYI_AUTOCRON_INTERVAL . ' giây');

$lastHeartbeat = 0;

while (true) {
    if (is_file($stopFile)) {
        @unlink($stopFile);
        yiyiAutoLog('STOP: nhận lệnh dừng');
        break;
    }

    $sleepSeconds = YIYI_AUTOCRON_INTERVAL;

    try {
        $env = yiyiLoadEnv($envFile);
        $pdo = yiyiOpenTeam2026($env);

        $selectedDb = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        if (strtolower($selectedDb) !== 'team2026') {
            throw new RuntimeException('Worker đang kết nối nhầm database: ' . $selectedDb);
        }

        $mbBank = new MBBank($apiKey, $pdo);
        $processed = $mbBank->processDonateTransactions();

        if ($processed === false) {
            throw new RuntimeException('Không lấy/xử lý được dữ liệu SePay');
        }

        if ((int)$processed > 0) {
            yiyiAutoLog('OK: đã xử lý ' . (int)$processed . ' giao dịch mới');
        }

        $pdo = null;

        // Heartbeat mỗi 60 phút, không spam log mỗi 10 giây.
        if (time() - $lastHeartbeat >= 3600) {
            yiyiAutoLog('HEARTBEAT: worker đang chạy, DB=team2026');
            $lastHeartbeat = time();
        }
    } catch (Throwable $e) {
        yiyiAutoLog('ERROR: ' . $e->getMessage());
        $sleepSeconds = YIYI_AUTOCRON_ERROR_INTERVAL;
    }

    for ($i = 0; $i < $sleepSeconds; $i++) {
        if (is_file($stopFile)) {
            break 2;
        }
        sleep(1);
    }
}

@flock($lockHandle, LOCK_UN);
fclose($lockHandle);
exit(0);
