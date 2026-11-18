<?php
// Bu dosyayi "config.php" olarak kopyala ve degerleri doldur.
// ONERILEN: dosyayi public_html'in USTUNE, "arctic-config.php" adiyla koy (web'den hic erisilemez).
// Bu dosyayi ASLA herkese acik bir yere / git'e koyma. Sifreleri sohbete yazma.
return [
    'site_url'   => 'https://senindomain.com',
    'salt'       => 'rastgele-uzun-bir-metin-yaz',      // IP hash'i icin
    'admin_token'=> 'uzun-rastgele-bir-anahtar',        // export.php icin
    'reply_to'   => null,                               // opsiyonel
    'notify_to'  => null,                               // opsiyonel: sirket kutusu, yeni kayitta bildirim

    'db' => [
        'host' => 'localhost',
        'name' => 'cpanel_veritabani_adi',
        'user' => 'cpanel_kullanici',
        'pass' => 'veritabani_sifresi',
    ],

    'smtp' => [
        'host'      => 'mail.spacemail.com',
        'port'      => 465,                             // SSL
        'user'      => 'hello@senindomain.com',         // tam mail adresi
        'pass'      => 'mail_kutusu_sifresi',
        'from_name' => 'Arctic Dynamics',
    ],
];
