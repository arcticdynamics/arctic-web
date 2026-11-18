# Arctic Dynamics — Web Sitesi: Proje, Mimari ve Kurulum Dokümanı

## 1. Proje nedir?
Arctic Dynamics, yerel çalışan (local-first) bir yapay zeka kodlama ajanı geliştiren erken aşama bir girişim. Bu repo, ürünün **tanıtım ve erken erişim (waitlist) sitesidir**. Ziyaretçi e-postasını bırakır; sistem adresi kaydeder ve şirket mail kutusundan "waitlist'e alındınız" onay maili gönderir.

Tasarım ChatGPT ile üretildi (koyu lacivert/buz mavisi tema, hourglass animasyonu) ve **olduğu gibi korunuyor**. Önceden site tamamen statikti: form sahteydi, hiçbir şey kaydetmiyor, mail göndermiyordu. Bu çalışmada gerçek bir backend eklendi.

**Kaynak geçmişi:** İlk statik sürüm ChatGPT Sites'ta yayındaydı (v17, commit `a471c23b`, `https://arctic-dynamics-web.earnedgame4.chatgpt.site/`). Bu adres eski statik halidir; form çalışmaz. Güncel kaynak `dist/` klasörüdür. Klasördeki `.openai/hosting.json` dosyaları o ortamın manifestidir, Spaceship'te kullanılmaz.

## 2. Yapılanlar
| Alan | Önce | Şimdi |
|---|---|---|
| Form | Sadece "preview" mesajı gösteriyordu | `api/subscribe.php`'ye gerçek istek atar; yükleniyor/hata/başarı durumları var |
| Kayıt | Yoktu | MySQL `waitlist` tablosu (e-posta benzersiz) |
| Mail | Yoktu | Spaceship Space Mail üzerinden SMTP ile onay maili (+ isteğe bağlı şirket bildirimi) |
| Gizlilik metni | "Bu önizleme e-postanızı saklamaz" | Gerçek davranışı anlatan metin |
| Güvenlik | – | Honeypot, IP başına saatte 5 deneme, aynı site kontrolü, IP hash'leme, şifreler dışarıda |
| Dışa aktarma | – | `api/export.php` ile CSV (Excel'de açılır) |
| Responsive | Kırılımlar vardı, test edilmemişti | 768 px ve 375 px'de yatay taşma yok doğrulandı; iOS form yakınlaşması düzeltildi |
| Yerel test | – | MySQL ve gerçek mail olmadan test modu (SQLite + mail log) |

**Yerel test sonucu (PHP 8.4.27, SQLite + mail-log modu):** geçerli kayıt, tekrar eden e-posta (ikinci mail gitmiyor), geçersiz e-posta (422), honeypot, hız sınırı (6. denemede 429), yanlış token (403), GET isteği (405) ve tarayıcıdan form gönderimi geçti. CSV dışa aktarma PHP 8.4 uyarısı nedeniyle düzeltildi.

**Henüz doğrulanmayan:** MySQL ile çalışma ve gerçek SMTP gönderimi (hosting ve mail kutusu gerekiyor), gerçek telefon/tablet cihaz testi.

## 3. Mimari

```
Ziyaretçi tarayıcısı
   │  1) Sayfa isteği
   ▼
[ Spaceship Hosting — public_html ]
   ├─ index.html + assets/        (statik tasarım, tek dosya, CSS/JS gömülü)
   └─ api/
        ├─ subscribe.php   ◄── 2) POST {email, website(honeypot)}
        │      │ doğrulama → hız sınırı → kayıt → mail
        │      ├──► MySQL (waitlist, waitlist_attempts)       [aynı hosting]
        │      └──► SMTP mail.spacemail.com:465 (SSL)         [Space Mail kutusu]
        │               ├─ ziyaretçiye: "You're on the list"
        │               └─ şirkete (opsiyonel): "Yeni kayıt"
        ├─ export.php      ◄── yönetici: token ile CSV indirme
        └─ lib/            (bootstrap, smtp, templates — dışarıdan erişim kapalı)

public_html'in ÜSTÜNDE (web'den erişilemez):
   arctic-config.php  → DB ve SMTP şifreleri, salt, admin token
```

### Neden bu seçimler?
- **Spaceship hosting + PHP + MySQL:** domain, hosting ve mail aynı panelde; ek altyapı ve ek ücret yok. Statik siteyi barındıran ChatGPT/Cloudflare ortamı ham SMTP bağlantısı açamadığı için kendi backend'imiz gerekti.
- **Excel / SQL Server değil MySQL:** Excel eşzamanlı yazımda bozulur; SQL Server bu iş için ağır ve lisanslı. MySQL hosting ile hazır gelir, CSV olarak Excel'e aktarılır.
- **SMTP:** Giden otomatik onay maili için gerekir. POP3 yalnızca gelen maili çeker; bu projede kullanılmıyor.
- **Bağımlılıksız SMTP istemcisi:** Composer/PHPMailer kurulumu gerekmesin diye `api/lib/smtp.php` içinde minimal bir istemci yazıldı.
- **Tablolar otomatik:** İlk kayıtta `CREATE TABLE IF NOT EXISTS` çalışır; elle SQL gerekmez.

### Dosya yapısı
```
ArcticWeb/
├─ dist/                         ← yayına giden klasör (public_html'e yüklenir)
│  ├─ index.html
│  ├─ assets/ …
│  └─ api/
│     ├─ subscribe.php
│     ├─ export.php
│     ├─ config.sample.php              (canlı ayar şablonu)
│     ├─ config.local-test.sample.php   (yerel test şablonu)
│     ├─ .htaccess
│     └─ lib/ bootstrap.php, smtp.php, templates.php, .htaccess
├─ YAYIN_REHBERI.md              ← panel adımları
├─ PROJE_DOKUMANTASYON.md        ← bu dosya
└─ arctic-site.zip               ← yayın paketi
```

### Veri modeli
- `waitlist(id, email UNIQUE, token_hash, mail_status[pending|sent|failed], confirmed_at NULL, created_at)` — IP saklanmaz. `token_hash`, onay linkindeki rastgele token'ın SHA-256'sıdır (düz token saklanmaz). `confirmed_at` boşsa e-posta henüz onaylanmamıştır.
- `waitlist_attempts(id, ip_hash, created_at)` — hız sınırı için tuzlu IP hash'i, 1 günden eski kayıtlar silinir.
- Not: `CREATE TABLE IF NOT EXISTS` mevcut bir tabloyu değiştirmez. Önceki şemayla (ip_hash'li) oluşmuş bir test veritabanı varsa `waitlist` tablosunu silip yeniden oluşturun. Canlıda henüz tablo yok, sorun değil.

### Çift onay (double opt-in) akışı
1. Ziyaretçi formu gönderir → `subscribe.php` kaydı **onaysız** oluşturur ve "Confirm your spot" mailini gönderir.
2. Maildeki link `api/confirm.php?t=TOKEN` sayfasını açar. GET yalnızca "Confirm my email" butonu gösterir (mail tarayıcıları linki otomatik açabilir); butona basınca POST ile onaylanır.
3. Onaylanınca `confirmed_at` yazılır, ziyaretçiye "You're on the list" maili gider, `notify_to` varsa şirkete "New confirmed waitlist signup" bildirimi gider. Aynı link ikinci kez kullanılırsa "already confirmed" der, tekrar mail gitmez.
4. Aynı e-posta tekrar kaydedilirse yeni mail gitmez. Tek istisna: onay maili hiç gidememişse (`mail_status=failed`) yeni token üretilip mail yeniden denenir.
5. Genel koruma: IP başına saatte 5 deneme ve günlük toplam yeni kayıt tavanı (`daily_signup_cap`, varsayılan 500; `0` kapatır).
6. Listeden çıkma/silme şimdilik elle: mailde "reply to any of our emails" yazar, talep gelince kayıt veritabanından silinir.

### API
`POST /api/subscribe.php` — gövde: `{"email":"...", "website":""}`
- `200 {ok:true}` başarı (e-posta zaten kayıtlıysa da aynı cevap; mail tekrar gitmez)
- `422 invalid_email`, `429 rate_limited`, `403 forbidden`, `500 server_error`

`GET/POST /api/confirm.php?t=TOKEN` — onay sayfası (HTML). `400` geçersiz/bilinmeyen token, `200` onay veya "zaten onaylı".

`GET /api/export.php?token=ADMIN_TOKEN` — CSV: `email, confirmed, confirmed_at, mail_status, created_at`. Mail atarken yalnızca `confirmed=yes` olanları kullanın.

## 4. Arkadaşın için: ne alınacak?
1. **Domain** (Spaceship'ten alınması en kolayı).
2. **Spaceship hosting paketi.** Satın almadan önce kontrol et: PHP 8.0+, MySQL, ücretsiz SSL, **PDO MySQL** eklentisi, SMTP ile dışarı bağlantı izni, cPanel/File Manager.
3. **Space Mail kutusu** (örn. `hello@domain.com`). Şifresini yalnızca sunucudaki ayar dosyasına yazın; sohbete/e-postaya/repoya yazmayın.

Kurulum adımları: [YAYIN_REHBERI.md](YAYIN_REHBERI.md).

## 5. Ayar dosyası
`dist/api/config.sample.php` şablonunu doldurup `public_html`'in **üstüne** `arctic-config.php` adıyla koyun. Yoksa yedek olarak `dist/api/config.php` okunur (`.htaccess` ile dışarıya kapalı, ama üst klasör daha güvenli). Gerçek ayar dosyası zip'e ve repoya **girmemeli**.

## 6. Yerelde test

Yerel test modunda **MySQL ve gerçek mail gerekmez**: kayıtlar `local-test/test.sqlite` dosyasına, "gönderilen" mailler `local-test/mail.log` dosyasına yazılır.

### Gerekenler
- **PHP 8.0+** (CLI) — `pdo_sqlite` ve `openssl` eklentileri açık olmalı (resmi Windows paketlerinde genelde hazır; bazen `php.ini`'de `extension=pdo_sqlite` ve `extension=sqlite3` satırlarının başındaki `;` kaldırılır).
- Tarayıcı ve PowerShell/terminal.

### PHP kurulumu (Windows)
```powershell
winget install PHP.PHP
# yeni bir terminal aç, sonra:
php -v
php -m | findstr /i "pdo_sqlite openssl"
```
Alternatif: https://windows.php.net/download adresinden "Thread Safe" zip'i indirip bir klasöre açın, o klasörü PATH'e ekleyin.

### Çalıştırma
```powershell
cd C:\Users\ilayd\Desktop\ArcticWeb
copy dist\api\config.local-test.sample.php dist\api\config.php
php -S localhost:8000 -t dist
```
Tarayıcıda http://localhost:8000 açın, "Join the waitlist" ile bir e-posta girin.

### Kontroller
| Test | Beklenen |
|---|---|
| Geçerli e-posta gir | Yeşil "Almost there…" mesajı; `local-test/mail.log`'a "Confirm your spot" maili ve içinde onay linki yazılır |
| Log'taki linki aç, butona bas | "You're on the list" sayfası; log'a hoşgeldin maili düşer |
| Aynı e-postayı tekrar gir | Yine başarı mesajı, log'a **ikinci** mail düşmez |
| Geçersiz e-posta (`abc`) | Tarayıcı veya API hata verir |
| 6. kez art arda dene | "Too many attempts" |
| `http://localhost:8000/api/export.php?token=yerel-test-token-en-az-32-karakter-uzunlugunda` | CSV iner, e-postalar görünür |
| `http://localhost:8000/api/export.php?token=yanlis` | `Forbidden` |
| `http://localhost:8000/api/lib/bootstrap.php` | PHP yerleşik sunucusu `.htaccess`'i okumaz, bu yerelde açılabilir; canlıda (Apache) engellenir. Canlıda ayrıca kontrol edin. |

Komut satırından API testi:
```powershell
curl.exe -s -X POST http://localhost:8000/api/subscribe.php -H "Content-Type: application/json" -d "{\"email\":\"test@example.com\",\"website\":\"\"}"
```

### Gerçek mail testi (canlıda)
Gerçek SMTP yalnızca Space Mail kutusu açıldıktan sonra denenebilir. Canlıda `mail_mode` ve `db.driver` ayarlarını **koymayın** (varsayılanlar gerçek SMTP ve MySQL'dir). Mail gitmezse kayıt yine durur (`mail_status=failed`); hosting error log'unda `waitlist mail:` satırı nedeni yazar.

## 7. Yapılacaklar / açık konular
- [ ] PHP'yi yerelde çalıştırıp Bölüm 6 testlerini geçirmek.
- [ ] Domain + hosting + Space Mail kutusu alınması (arkadaş).
- [ ] Canlıda gerçek onay maili testi; mail spam'e düşüyorsa domain için SPF/DKIM kayıtlarını panelden kontrol etmek.
- [ ] Gerçek telefon/tablette görsel kontrol.
- [ ] Ayrı bir gizlilik politikası sayfası (formdaki kısa metin güncellendi: IP hash'i 24 saat, e-posta kalıcı, silme için mailine yanıt; hukuki danışmanlık önerilir).
- [ ] İsteğe bağlı: tek tıkla "listeden çık" bağlantısı (şimdilik silme talebi mailine yanıtla elle yapılıyor).
- [ ] İsteğe bağlı: CAPTCHA (Cloudflare Turnstile vb.; hesap ve anahtar gerekir). Çift onay ve günlük tavan şimdilik yeterli koruma sağlıyor.
- [ ] Site Cloudflare gibi bir proxy arkasına alınırsa hız sınırı için gerçek IP başlığının güvenli okunması.
- [x] E-posta doğrulama (çift onay).
- [ ] Başarısız mailleri yeniden deneyen bir cron işi (şimdilik `failed` kayıtlar elle takip edilir).
