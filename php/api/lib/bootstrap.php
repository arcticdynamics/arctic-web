<?php
// Ortak yardimcilar: ayar yukleme, veritabani baglantisi, JSON cevap.
declare(strict_types=1);

// Hatalar ziyaretciye gosterilmesin; sunucu error log'una yazilsin.
ini_set('display_errors', '0');

function arctic_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    // Once public_html'in DISINDAKI dosya (daha guvenli), yoksa api/config.php
    $candidates = [
        dirname(__DIR__, 3) . '/arctic-config.php',
        dirname(__DIR__) . '/config.php',
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            $cfg = require $file;
            return $cfg;
        }
    }
    json_out(500, ['ok' => false, 'error' => 'config_missing']);
}

function json_out(int $status, array $data): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = arctic_config()['db'];
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    // Tablolar ilk istekte otomatik olusur: elle SQL calistirmaya gerek yok.
    if (($c['driver'] ?? 'mysql') === 'sqlite') {
        // Sadece YEREL TEST icin: MySQL kurmadan dosya veritabani.
        if (!is_dir(dirname($c['file']))) mkdir(dirname($c['file']), 0777, true);
        $pdo = new PDO('sqlite:' . $c['file'], null, null, $opts);
        $pdo->exec("CREATE TABLE IF NOT EXISTS waitlist (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            token_hash TEXT NOT NULL,
            mail_status TEXT NOT NULL DEFAULT 'pending',
            confirmed_at TEXT NULL,
            created_at TEXT NOT NULL
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS waitlist_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_hash TEXT NOT NULL,
            created_at TEXT NOT NULL
        )");
        return $pdo;
    }
    $pdo = new PDO(
        "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",
        $c['user'],
        $c['pass'],
        $opts
    );
    $pdo->exec("CREATE TABLE IF NOT EXISTS waitlist (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(254) NOT NULL,
        token_hash CHAR(64) NOT NULL,
        mail_status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
        confirmed_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY uq_email (email),
        KEY idx_token (token_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS waitlist_attempts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip_hash CHAR(64) NOT NULL,
        created_at DATETIME NOT NULL,
        KEY idx_ip_time (ip_hash, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    return $pdo;
}

function now_utc(int $offsetSeconds = 0): string {
    return gmdate('Y-m-d H:i:s', time() + $offsetSeconds);
}

// Mail gonderimi: 'smtp' (gercek) veya 'log' (yerel test: mail dosyaya yazilir, gonderilmez).
function send_mail(array $cfg, string $to, string $subject, string $html, string $text): void {
    if (($cfg['mail_mode'] ?? 'smtp') === 'log') {
        $file = $cfg['mail_log'] ?? (sys_get_temp_dir() . '/arctic-mail.log');
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0777, true);
        file_put_contents($file, "=== " . now_utc() . " | To: $to | Subject: $subject ===\n$text\n\n", FILE_APPEND);
        return;
    }
    require_once __DIR__ . '/smtp.php';
    smtp_send($cfg['smtp'], $to, $subject, $html, $text, $cfg['reply_to'] ?? null);
}

function ip_hash(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    // IP'yi duz saklamiyoruz; tuzlu hash. Salt yoksa/zayifsa sessizce zayif hash uretme: hata ver.
    $salt = (string)(arctic_config()['salt'] ?? '');
    if (strlen($salt) < 16 || $salt === 'rastgele-uzun-bir-metin-yaz') {
        throw new RuntimeException('config: "salt" en az 16 karakter ve ornek degerden farkli olmali');
    }
    return hash('sha256', $ip . '|' . $salt);
}
