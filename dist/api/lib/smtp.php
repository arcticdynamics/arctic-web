<?php
// Bagimliliksiz minimal SMTP istemcisi (Spaceship Space Mail: mail.spacemail.com:465 SSL).
// Composer / PHPMailer kurulumu gerektirmez.
declare(strict_types=1);

function smtp_read($fp): string {
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break; // son satir "250 ..."
    }
    return $data;
}

function smtp_cmd($fp, string $cmd, array $okCodes): string {
    fwrite($fp, $cmd . "\r\n");
    $resp = smtp_read($fp);
    if (!in_array((int)substr($resp, 0, 3), $okCodes, true)) {
        throw new RuntimeException('SMTP error: ' . trim($resp));
    }
    return $resp;
}

function mime_header(string $s): string {
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

/**
 * @param array $smtp  host, port, user, pass, from_name
 */
function smtp_send(array $smtp, string $to, string $subject, string $html, string $text, ?string $replyTo = null): void {
    $host = $smtp['host'];
    $port = (int)$smtp['port'];
    // Savunma derinligi: satir sonu karakterleri baslik enjeksiyonuna yol acar.
    foreach ([$to, $replyTo ?? '', (string)($smtp['user'] ?? ''), (string)($smtp['from_name'] ?? '')] as $v) {
        if (preg_match('/[\r\n]/', $v)) throw new RuntimeException('SMTP: invalid header value');
    }
    // Sifresiz baglanti AUTH parolasini acik gonderir: yalnizca 465 (SSL) ve 587 (STARTTLS) kabul edilir.
    // Yerel test icin ayarda 'allow_insecure' => true verilebilir (canli ayarda KULLANMA).
    if (!in_array($port, [465, 587], true) && empty($smtp['allow_insecure'])) {
        throw new RuntimeException("SMTP: port $port desteklenmiyor (465 veya 587 kullan)");
    }
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client(
        ($port === 465 ? 'ssl://' : 'tcp://') . "$host:$port",
        $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
    );
    if (!$fp) throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
    stream_set_timeout($fp, 20);

    try {
        $greet = smtp_read($fp);
        if ((int)substr($greet, 0, 3) !== 220) throw new RuntimeException('SMTP greeting: ' . trim($greet));
        $name = $_SERVER['SERVER_NAME'] ?? 'localhost';
        smtp_cmd($fp, "EHLO $name", [250]);

        if ($port === 587) { // STARTTLS secenegi
            smtp_cmd($fp, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS failed');
            }
            smtp_cmd($fp, "EHLO $name", [250]);
        }

        smtp_cmd($fp, 'AUTH LOGIN', [334]);
        smtp_cmd($fp, base64_encode($smtp['user']), [334]);
        smtp_cmd($fp, base64_encode($smtp['pass']), [235]);

        smtp_cmd($fp, 'MAIL FROM:<' . $smtp['user'] . '>', [250]);
        smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_cmd($fp, 'DATA', [354]);

        $boundary = 'b' . bin2hex(random_bytes(12));
        $domain = substr(strrchr($smtp['user'], '@'), 1);
        $headers = [
            'Date: ' . date('r'),
            'From: ' . mime_header($smtp['from_name']) . ' <' . $smtp['user'] . '>',
            'To: <' . $to . '>',
            'Subject: ' . mime_header($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"$boundary\"",
        ];
        if ($replyTo) $headers[] = 'Reply-To: <' . $replyTo . '>';

        $body  = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($text)) . "\r\n";
        $body .= "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($html)) . "\r\n--$boundary--\r\n";

        // base64 govdede satir basi '.' olmaz; yine de dot-stuffing uygula.
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $payload = preg_replace('/^\./m', '..', $payload);
        fwrite($fp, $payload . "\r\n.\r\n");
        $resp = smtp_read($fp);
        if ((int)substr($resp, 0, 3) !== 250) throw new RuntimeException('SMTP DATA: ' . trim($resp));
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}
