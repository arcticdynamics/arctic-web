<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';

header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$cfg = arctic_config();

// Ayni site disindan gelen istekleri reddet (basit CSRF/abuse korumasi)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $oh = parse_url($origin, PHP_URL_HOST);
    $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    if ($oh !== $host) json_out(403, ['ok' => false, 'error' => 'forbidden']);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) $input = $_POST;

// Honeypot: botlar bu gizli alani doldurur. Sessizce basarili gibi davran.
if (!empty($input['website'])) json_out(200, ['ok' => true]);

$email = strtolower(trim((string)($input['email'] ?? '')));
if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(422, ['ok' => false, 'error' => 'invalid_email']);
}

try {
    $pdo = db();
    $ip = ip_hash();

    // Hiz siniri: ayni IP'den saatte en fazla 5 deneme (IP hash'i yalniz 24 saat tutulur)
    $pdo->prepare('DELETE FROM waitlist_attempts WHERE created_at < ?')->execute([now_utc(-86400)]);
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM waitlist_attempts WHERE ip_hash = ? AND created_at > ?');
    $cnt->execute([$ip, now_utc(-3600)]);
    if ((int)$cnt->fetchColumn() >= 5) json_out(429, ['ok' => false, 'error' => 'rate_limited']);
    $pdo->prepare('INSERT INTO waitlist_attempts (ip_hash, created_at) VALUES (?, ?)')->execute([$ip, now_utc()]);

    // Genel gunluk tavan: IP degistirerek yapilan toplu kayit/mail istismarini sinirlar.
    $cap = (int)($cfg['daily_signup_cap'] ?? 500);
    $day = $pdo->prepare('SELECT COUNT(*) FROM waitlist WHERE created_at > ?');
    $day->execute([now_utc(-86400)]);
    if ($cap > 0 && (int)$day->fetchColumn() >= $cap) json_out(429, ['ok' => false, 'error' => 'rate_limited']);

    // Cift onay: e-posta, mailindeki linke tiklanana kadar "onaylanmamis" kalir.
    // Rastgele token'in yalniz hash'i saklanir.
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $ignore = (($cfg['db']['driver'] ?? 'mysql') === 'sqlite') ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
    $ins = $pdo->prepare("$ignore INTO waitlist (email, token_hash, created_at) VALUES (?, ?, ?)");
    $ins->execute([$email, $tokenHash, now_utc()]);

    if ($ins->rowCount() === 1) {
        $id = (int)$pdo->lastInsertId();
    } else {
        // Zaten kayitli. Spam'e arac olmasin diye normalde mail GONDERME ve ayni cevabi ver.
        // Tek istisna: onaylanmamis ve onay maili hic gidememis (failed) kayit -> yeniden dene.
        $row = $pdo->prepare('SELECT id, confirmed_at, mail_status FROM waitlist WHERE email = ?');
        $row->execute([$email]);
        $r = $row->fetch();
        if (!$r || $r['confirmed_at'] !== null || $r['mail_status'] !== 'failed') json_out(200, ['ok' => true]);
        $id = (int)$r['id'];
        $pdo->prepare('UPDATE waitlist SET token_hash = ? WHERE id = ?')->execute([$tokenHash, $id]);
    }
} catch (Throwable $e) {
    error_log('waitlist db: ' . $e->getMessage());
    json_out(500, ['ok' => false, 'error' => 'server_error']);
}

// Onay maili. Basarisiz olsa da kayit kaybolmaz; mail_status='failed' olarak isaretlenir.
$status = 'sent';
try {
    $tpl = require __DIR__ . '/lib/templates.php';
    $link = rtrim((string)($cfg['site_url'] ?? ''), '/') . '/api/confirm.php?t=' . $token;
    [$subject, $html, $text] = $tpl['confirm']((string)($cfg['site_url'] ?? ''), $link);
    send_mail($cfg, $email, $subject, $html, $text);
} catch (Throwable $e) {
    $status = 'failed';
    error_log('waitlist mail: ' . $e->getMessage());
}

try {
    $pdo->prepare('UPDATE waitlist SET mail_status = ? WHERE id = ?')->execute([$status, $id]);
} catch (Throwable $e) { /* onemsiz */ }

json_out(200, ['ok' => true]);
