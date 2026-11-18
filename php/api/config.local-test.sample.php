<?php
// SADECE YEREL TEST. Bunu "config.php" olarak kopyala (php/api/config.php).
// MySQL ve gercek mail gerekmez: veritabani dosyaya, mailler log dosyasina yazilir.
// Bu dosyayi/ayarlari canli sunucuya YUKLEME.
return [
    'site_url'    => 'http://localhost:8000',
    'salt'        => 'yerel-test-tuzu-en-az-16-karakter',          // en az 16 karakter
    'admin_token' => 'yerel-test-token-en-az-32-karakter-uzunlugunda',  // en az 32 karakter
    'mail_mode'   => 'log',                                   // gercek mail gitmez
    'mail_log'    => __DIR__ . '/../../local-test/mail.log',  // gidecek mailler burada gorunur
    'db' => [
        'driver' => 'sqlite',
        'file'   => __DIR__ . '/../../local-test/test.sqlite',
    ],
];
