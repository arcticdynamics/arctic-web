<?php
// Cift onay: maildeki link buraya gelir. GET sadece onay sayfasini gosterir (mail tarayicilari/
// guvenlik programlari linki otomatik acabilir); asil onay butona basinca POST ile olur.
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

function page(int $status, string $title, string $body): void {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $t = htmlspecialchars($title, ENT_QUOTES);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . $t . ' — Arctic Dynamics</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#04111d;color:#f2f7fa;font-family:Arial,Helvetica,sans-serif;padding:20px}'
        . '.card{width:min(100%,480px);background:#071c2a;border:1px solid #1d5568;border-radius:14px;padding:36px;text-align:center}'
        . '.brand{color:#31d7e9;letter-spacing:4px;font-size:13px;font-weight:bold}h1{font-size:26px;margin:18px 0 10px}p{color:#a9bdc8;line-height:1.6;font-size:15px}'
        . 'button,a.btn{display:inline-block;margin-top:14px;padding:13px 22px;border:0;border-radius:7px;background:linear-gradient(100deg,#22ccdd,#8ce9ec);color:#03202a;font-weight:700;font-size:15px;text-decoration:none;cursor:pointer}</style></head>'
        . '<body><main class="card"><div class="brand">ARCTIC DYNAMICS</div><h1>' . $t . '</h1>' . $body . '</main></body></html>';
    exit;
}

$cfg = arctic_config();
$method = $_SERVER['REQUEST_METHOD'] ?? '';
$token = (string)($method === 'POST' ? ($_POST['t'] ?? '') : ($_GET['t'] ?? ''));
$bad = fn() => page(400, 'Link not valid', '<p>This confirmation link is invalid or has expired.</p><a class="btn" href="/">Back to site</a>');

if (!preg_match('/^[0-9a-f]{64}$/', $token)) $bad();
$hash = hash('sha256', $token);

try {
    $pdo = db();
    $st = $pdo->prepare('SELECT email, confirmed_at FROM waitlist WHERE token_hash = ?');
    $st->execute([$hash]);
    $row = $st->fetch();
} catch (Throwable $e) {
    error_log('waitlist confirm db: ' . $e->getMessage());
    page(500, 'Something went wrong', '<p>Please try the link again in a moment.</p>');
}
if (!$row) $bad();

if ($row['confirmed_at'] !== null) {
    page(200, "You're on the list", '<p>Your email is already confirmed. We\'ll write when early access opens.</p><a class="btn" href="/">Back to site</a>');
}

if ($method !== 'POST') {
    page(200, 'Confirm your spot', '<p>One last step: confirm that you want to join the Arctic waitlist.</p>'
        . '<form method="post" action="confirm.php"><input type="hidden" name="t" value="' . htmlspecialchars($token, ENT_QUOTES) . '">'
        . '<button type="submit">Confirm my email</button></form>');
}

try {
    $up = $pdo->prepare('UPDATE waitlist SET confirmed_at = ? WHERE token_hash = ? AND confirmed_at IS NULL');
    $up->execute([now_utc(), $hash]);
    $first = $up->rowCount() === 1;
} catch (Throwable $e) {
    error_log('waitlist confirm: ' . $e->getMessage());
    page(500, 'Something went wrong', '<p>Please try the link again in a moment.</p>');
}

if ($first) {
    try {
        $tpl = require __DIR__ . '/lib/templates.php';
        [$subject, $html, $text] = $tpl['welcome']((string)($cfg['site_url'] ?? ''));
        send_mail($cfg, $row['email'], $subject, $html, $text);
    } catch (Throwable $e) {
        error_log('waitlist welcome mail: ' . $e->getMessage());
    }
    if (!empty($cfg['notify_to'])) {
        try {
            send_mail($cfg, $cfg['notify_to'], 'New confirmed waitlist signup',
                '<p>Confirmed signup: <b>' . htmlspecialchars($row['email'], ENT_QUOTES) . '</b></p>', 'Confirmed signup: ' . $row['email']);
        } catch (Throwable $e) {
            error_log('waitlist notify: ' . $e->getMessage());
        }
    }
}

page(200, "You're on the list", '<p>Thanks, your email is confirmed. We\'ll write as soon as early access opens.</p><a class="btn" href="/">Back to site</a>');
