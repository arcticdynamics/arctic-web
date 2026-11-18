<?php
// E-posta sablonlari. Metni burada degistirebilirsin.

// Ortak HTML cerceve: $title, $lead (duz metin), $extraHtml (opsiyonel, guvenilir HTML), $footer (duz metin)
$frame = function (string $title, string $lead, string $extraHtml, string $footer): string {
    $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES);
    return '<!doctype html><html><body style="margin:0;background:#04111d;font-family:Arial,Helvetica,sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#04111d;padding:32px 16px;"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#071c2a;border:1px solid #1d5568;border-radius:14px;padding:36px;">'
        . '<tr><td style="color:#31d7e9;letter-spacing:4px;font-size:13px;font-weight:bold;">ARCTIC DYNAMICS</td></tr>'
        . '<tr><td style="color:#f2f7fa;font-size:26px;font-weight:bold;padding:18px 0 10px;">' . $e($title) . '</td></tr>'
        . '<tr><td style="color:#a9bdc8;font-size:15px;line-height:1.6;">' . $e($lead) . '</td></tr>'
        . $extraHtml
        . '<tr><td style="color:#8beaf1;font-size:13px;padding-top:24px;">Your AI. Your device. Your rules.</td></tr>'
        . '<tr><td style="color:#6f8a97;font-size:12px;line-height:1.5;padding-top:18px;">' . $e($footer) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
};

return [
    // Kayit sonrasi: onay linki (cift onay)
    'confirm' => function (string $siteUrl, string $link) use ($frame): array {
        $subject = 'Confirm your spot on the Arctic waitlist';
        $footer = "If you didn't request this, just ignore this email: you won't be added or contacted. "
            . 'To have your address removed at any time, reply to any of our emails.';
        $text = "Hi,\n\nPlease confirm your email to join the Arctic Dynamics waitlist:\n\n$link\n\n$footer\n\n— Arctic Dynamics\n$siteUrl\n";
        $button = '<tr><td style="padding-top:22px;"><a href="' . htmlspecialchars($link, ENT_QUOTES)
            . '" style="display:inline-block;background:#22ccdd;color:#03202a;font-weight:bold;text-decoration:none;padding:13px 22px;border-radius:7px;">Confirm my email</a></td></tr>';
        $html = $frame('Confirm your spot.', 'One click and you are on the Arctic waitlist. We only write once early access opens.', $button, $footer);
        return [$subject, $html, $text];
    },

    // Onay sonrasi: hosgeldin
    'welcome' => function (string $siteUrl) use ($frame): array {
        $subject = "You're on the Arctic waitlist";
        $footer = 'To have your address removed at any time, reply to this email.';
        $text = "Hi,\n\nYou're on the Arctic Dynamics waitlist. We'll email you as soon as early access opens.\n\nYour AI. Your device. Your rules.\n\n$footer\n\n— Arctic Dynamics\n$siteUrl\n";
        $html = $frame("You're on the list.", "Thanks for confirming. We'll email you as soon as early access opens.", '', $footer);
        return [$subject, $html, $text];
    },
];
