<?php
// Liste disa aktarma: https://siteadresi/api/export.php?token=ADMIN_TOKEN  -> CSV (Excel'de acilir)
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';

$cfg = arctic_config();
$token = (string)($_GET['token'] ?? '');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex');
// Zayif/ornek token ile disa aktarma kapali: en az 32 karakter gerekir.
$adminToken = (string)($cfg['admin_token'] ?? '');
if (strlen($adminToken) < 32 || $adminToken === 'uzun-rastgele-bir-anahtar' || !hash_equals($adminToken, $token)) {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="waitlist-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store');
echo "\xEF\xBB\xBF"; // Excel icin UTF-8 BOM
$out = fopen('php://output', 'w');
fputcsv($out, ['email', 'confirmed', 'confirmed_at', 'mail_status', 'created_at'], ',', '"', '');
foreach (db()->query('SELECT email, mail_status, confirmed_at, created_at FROM waitlist ORDER BY id') as $row) {
    // CSV formula enjeksiyonuna karsi
    $email = preg_match('/^[=+\-@]/', $row['email']) ? "'" . $row['email'] : $row['email'];
    fputcsv($out, [$email, $row['confirmed_at'] !== null ? 'yes' : 'no', $row['confirmed_at'], $row['mail_status'], $row['created_at']], ',', '"', '');
}
