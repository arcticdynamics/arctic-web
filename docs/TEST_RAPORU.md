# Arctic Dynamics — Test Raporu ve Gereklilikler

**Test tarihi:** 2026-11-14
**Test edilen:** `php/` klasörü (index.html + api/)
**Ortam:** Windows 11, Docker Desktop 29.9.2, tarayıcı simülasyonu

> Testlerde yalnızca sahte test değerleri kullanıldı (test şifreleri, `@example.com` / `@arctic.test` adresleri). Gerçek şifre, domain veya mail kutusu kullanılmadı. Proje dosyalarında hiçbir değişiklik yapılmadı.

---

## 1. Responsive testi

Yöntem: yerel HTTP sunucusu + tarayıcıda görünüm boyutu simülasyonu. Gerçek telefon/tablet ile test **yapılmadı**.

| Genişlik | Yatay taşma | Notlar |
|---|---|---|
| 1440 px | Yok | Görseller yüklendi |
| 1024 px | Yok | Hamburger menü gizli |
| 768 px | Yok | Hamburger menü gizli |
| 375 px | Yok | Hamburger menü görünür ve açılıp kapanıyor |
| 320 px | Yok | Hamburger menü görünür |

- `manifesto-visual` görseli ekran dışına birkaç piksel uzanıyor ama kırpılıyor, görünürde sorun yok.
- Modal 375 px'de ekrana sığıyor; e-posta alanı 16 px (iOS otomatik zoom sorunu yok).
- Menü bağlantılarının (`#platform`, `#workflow`, `#security`, `#about`) hepsi geçerli bölümlere gidiyor.

## 2. Ön yüz (dinamik) testi

| Test | Sonuç |
|---|---|
| Çalışan animasyon sayısı | 136 (kum saati, vurgu animasyonları vb.) |
| Mobil menü aç/kapa | Geçti |
| Modal butonla açılıyor, Esc ile kapanıyor | Geçti |
| Geçersiz e-posta mesajı | Geçti |
| Başarı mesajı ve alanın temizlenmesi | Geçti |

## 3. Tasarım değişti mi?

`php/index.html`, ChatGPT'de yayındaki özgün sürümle (`arctic-dynamics-web.earnedgame4.chatgpt.site`) satır satır karşılaştırıldı.

**Değişenler (yalnızca form ve backend bağlantısı):**
- Form alanları: gizli honeypot alanı (`website`), `maxlength="254"`, buton `id`'si.
- Form gönderme script'i: artık `api/subscribe.php`'ye `fetch` ile istek atıyor; yükleniyor / hata / başarı durumları var.
- Gizlilik metni ve başarı mesajı (eski: "preview e-postanı saklamaz").
- 3 küçük CSS kuralı: mobil `font-size:16px`, `.hp`, `.email-form button:disabled`.
- Cloudflare'in otomatik eklediği script çıkarıldı.

**Değişmeyenler:** renkler, yerleşim, metinler, animasyonlar, kırılım noktaları, görseller.

## 4. Backend testi (Docker: PHP 8.3 + MySQL 8 + Mailpit)

Gerçek `subscribe.php` ve `export.php`, gerçek MySQL 8 ve gerçek bir SMTP sunucusuyla (Mailpit, şifrelemesiz 1025 portu) test edildi.

| # | Test | Beklenen | Sonuç |
|---|---|---|---|
| 1 | Geçerli kayıt | 200, DB'ye yazılır, `mail_status=sent` | Geçti (e-posta küçük harfe çevrildi) |
| 2 | Aynı e-posta tekrar | 200, ikinci kayıt ve mail yok | Geçti |
| 3 | Geçersiz e-posta | 422 `invalid_email` | Geçti |
| 4 | Honeypot dolu | 200, sessizce yok sayılır | Geçti |
| 5 | Farklı `Origin` başlığı | 403 `forbidden` | Geçti |
| 6 | GET isteği | 405 | Geçti |
| 7 | Hız sınırı | 6. denemede 429 | Geçti |
| 8 | Onay maili | Ziyaretçiye "You're on the Arctic waitlist", şirkete "New waitlist signup" | Geçti |
| 9 | SMTP kapalıyken kayıt | Kayıt kaybolmaz, `mail_status=failed`, log'a neden yazılır | Geçti |
| 10 | CSV dışa aktarma (doğru token) | CSV, Excel için UTF-8 BOM | Geçti |
| 11 | CSV dışa aktarma (yanlış token) | 403 | Geçti |
| 12 | `api/lib/bootstrap.php` doğrudan erişim | 403 | Geçti |
| 13 | `api/config.sample.php` doğrudan erişim | 403 | Geçti |
| 14 | Tarayıcıdan gerçek form gönderimi | Başarı, tekrarda başarı, geçersizde hata; DB'ye kayıt, mail gönderimi | Geçti |

**Not:** Testlerde iki bilgi çıktı, hata değil: (a) Atlanan `id` numaraları (örn. 1, 3) `INSERT IGNORE` kaynaklı normal davranış. (b) Doğrulama hataları (geçersiz e-posta, honeypot) hız sınırı sayacına yazılmıyor, yalnızca geçerli e-posta denemeleri sayılıyor.

## 5. Doğrulanmayanlar (açık maddeler)

| Madde | Neden doğrulanmadı | Ne zaman yapılacak |
|---|---|---|
| Space Mail ile gerçek SMTP (`mail.spacemail.com:465`, SSL) | Test SMTP'si şifrelemesizdi; gerçek mail kutusu yok | Mail kutusu açılınca, ilk canlı kayıtta |
| Spaceship hosting PHP sürümü ve PDO MySQL eklentisi | Hosting henüz yok | Hosting satın almadan önce / sonra |
| SMTP'nin hosting tarafından dışarı bağlantıya açık olması | Hosting henüz yok | Canlıya alınca |
| Gerçek telefon / tablet cihaz testi | Yalnızca tarayıcı simülasyonu yapıldı | Canlıya alındıktan sonra |
| Yanlış SMTP şifresi gibi diğer mail hataları | Yalnızca "SMTP kapalı" durumu denendi | Canlıda gerekirse |

## 6. Canlıya alma gereklilikleri

1. **Domain** (Spaceship'ten alınması en kolayı).
2. **Spaceship hosting paketi.** Satın almadan önce kontrol et:
   - PHP 8.0+
   - MySQL
   - Ücretsiz SSL
   - **PDO MySQL** eklentisi
   - SMTP ile dışarı bağlantı izni
   - cPanel / File Manager
3. **Space Mail kutusu** (örn. `hello@senindomain.com`).
4. **Canlı ayar dosyası:** `api/config.sample.php` kopyalanıp doldurulur. En güvenlisi dosyayı `public_html`'in **üstüne** `arctic-config.php` adıyla koymak.
   - `db.driver` ve `mail_mode` ayarlarını **koyma** (varsayılanlar gerçek MySQL ve gerçek SMTP).
   - `salt` ve `admin_token` için uzun, rastgele değerler kullan.
   - Şifreleri sohbete, repoya veya herkese açık bir yere yazma.
5. **MySQL veritabanı** cPanel'de oluşturulur; tablolar ilk kayıtta otomatik oluşur.
6. **Yükleme:** `php/` içeriği `public_html`'e yüklenir. `.openai/hosting.json` Spaceship'te kullanılmaz.
7. **Canlı doğrulama:** gerçek bir e-postayla kayıt yap; mailin gelip gelmediğine ve `mail_status` değerine bak. Mail gitmezse hosting error log'unda `waitlist mail:` satırı nedeni yazar.
8. **Gelen kayıtlar:** `https://siteadresi/api/export.php?token=ADMIN_TOKEN` ile CSV olarak indirilir (Excel'de açılır).

## 7. Testi yeniden çalıştırma

Docker Desktop açıkken, üç konteyner aynı ağda kurulur:

- `mysql:8` (veritabanı `arctic`, test kullanıcısı)
- `axllent/mailpit` (SMTP 1025, mail arayüzü 8025; `MP_SMTP_AUTH_ACCEPT_ANY=1`, `MP_SMTP_AUTH_ALLOW_INSECURE=1`)
- `php:8.3-apache` + `docker-php-ext-install pdo_mysql`, `php/` klasörü `/var/www/html`'e, test ayar dosyası `/var/www/arctic-config.php`'ye salt okunur bağlanır

Test ayar dosyasındaki SMTP bilgisi `port: 1025` olmalı; kod 465 dışındaki portlarda şifrelemesiz bağlanır. Gerçek 465 SSL yolu bu düzenekle test edilemez.

---

## 8. Güvenlik incelemesi ve düzeltmeler (2026-11-14)

Salt okunur bir güvenlik agent'ı tüm projeyi inceledi. Ciddi bir açık çıkmadı; sır sızıntısı yok, tüm SQL sorguları hazırlanmış ifade, token karşılaştırması `hash_equals`. Çıkan eksiklerden aşağıdakiler düzeltildi ve Docker'da (PHP 8.3 + MySQL 8 + Mailpit, `mod_rewrite` ve `mod_headers` açık) yeniden test edildi.

| Bulgu | Düzeltme | Test sonucu |
|---|---|---|
| Kök `.htaccess` yok | `php/.htaccess` eklendi: HTTP→HTTPS yönlendirmesi (localhost hariç), CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS | Başlıklar geldi; localhost dışı host 301 ile `https://`'e yönlendi |
| CSP sayfayı bozabilir | CSP satır içi script/stile izin verecek şekilde yazıldı | Tarayıcıda ihlal 0; animasyon (136), görseller, form gönderimi çalıştı |
| Zip'te `api\lib\...` ters eğik çizgi | Zip `/` ayraçlı yeniden üretildi | Girdi yolları `api/lib/bootstrap.php` biçiminde |
| `.openai/` ve `config.local-test.sample.php` yayın paketinde | Zip'ten çıkarıldı; kök `.htaccess` ile web'den de kapatıldı (kaynak dosyalar yerinde duruyor) | İkisi de 403 |
| SMTP şifresiz portta parolayı açık gönderir | 465 ve 587 dışındaki port reddediliyor; yerel test için ayarda `'allow_insecure' => true` gerekiyor | Port 25 reddedildi; test SMTP'si `allow_insecure` ile çalıştı |
| SMTP başlık enjeksiyonu (savunma derinliği) | `to`, `reply_to`, `user`, `from_name` içinde satır sonu karakteri reddediliyor | Reddedildi |
| Boş / kısa / örnek `salt` sessizce zayıf hash üretiyordu | En az 16 karakter ve örnek değerden farklı olmalı, yoksa hata | Zayıf salt hata verdi |
| `admin_token` zayıf olabilir, `export.php` URL'de token taşıyor | En az 32 karakter zorunlu, örnek değer kabul edilmiyor; `Referrer-Policy: no-referrer` ve `noindex` eklendi | Doğru token 200, yanlış token 403 |

**Yeni ayar gereklilikleri (canlı `arctic-config.php`):**
- `salt`: en az 16 karakter, rastgele.
- `admin_token`: en az 32 karakter, rastgele.
- SMTP portu 465 (SSL) veya 587 (STARTTLS) olmalı; `allow_insecure` canlıda **kullanılmamalı**.

**Bilinçli olarak yapılmayanlar (karar gerektirir):**
- Çift onay (onay linkli kayıt) ve CAPTCHA: başkasının e-postasına onay maili attırma riski sürüyor. Ürün kararı.
- Gizlilik metni: `waitlist.ip_hash` süresiz saklanıyor, saklama süresi ve silme yolu metinde yok. IP hash'i hiç saklamamak da bir seçenek. Gerçek yayında hukuki danışmanlık önerilir.
- Hız sınırı hâlâ `REMOTE_ADDR`'a bağlı: site Cloudflare gibi bir proxy arkasına alınırsa herkes tek IP görünür ve form kilitlenebilir. Proxy kullanılacaksa gerçek IP başlığı güvenli şekilde okunmalı.

**Bu testlerde doğrulanamayanlar:**
- HSTS başlığı: yalnızca gerçek HTTPS isteğinde gönderilir, Docker testi HTTP'ydi.
- Canlıda `api/lib/*.php` ve `config.sample.php`'nin 403 vermesi, hostingin `.htaccess` desteği ve `mod_headers`.
- SPF / DKIM / DMARC ayarı (mail teslim edilebilirliği).

---

## 9. Çift onay (double opt-in) ve gizlilik güncellemesi (2026-11-14)

Güvenlik incelemesinde çıkan "başkasının e-postasına onay maili attırma" riskini azaltmak için kayıt akışı çift onaya çevrildi. Docker'da (PHP 8.3 + MySQL 8 + Mailpit) uçtan uca test edildi.

**Değişiklikler**
- Yeni `api/confirm.php`: maildeki link önce yalnızca "Confirm my email" butonu gösterir; onay butona basınca POST ile olur (mail tarayıcılarının linki otomatik açıp onaylamasını önler).
- `subscribe.php`: kayıt onaysız oluşur, "Confirm your spot" maili gider. Hoşgeldin maili ve şirket bildirimi onaydan sonra gider.
- Onay token'ı rastgele 256 bit; veritabanında yalnızca SHA-256 hash'i tutulur.
- `waitlist.ip_hash` kaldırıldı (IP artık yalnızca `waitlist_attempts` içinde, 24 saat).
- Günlük toplam yeni kayıt tavanı eklendi (`daily_signup_cap`, varsayılan 500).
- Form başarı mesajı ve gizlilik notu gerçek davranışı anlatacak şekilde güncellendi. Silme talebi için mailine yanıt verilir.
- `export.php` CSV'sine `confirmed` ve `confirmed_at` sütunları eklendi.

**Test sonuçları**
| Test | Sonuç |
|---|---|
| Kayıt → onaysız kayıt, yalnızca "Confirm your spot" maili | Geçti |
| Onay linkine GET → butonlu sayfa, **onaylamıyor** | Geçti |
| POST ile onay → `confirmed_at` yazıldı, hoşgeldin maili ve şirket bildirimi gitti | Geçti |
| Linki ikinci kez kullanma → "already confirmed", yeni mail yok | Geçti |
| Geçersiz / bilinmeyen token | 400 |
| Aynı e-postayı tekrar kaydetme → yeni mail yok | Geçti |
| SMTP kapalıyken kayıt (`failed`) sonra aynı e-posta tekrar → onay maili yeniden gönderildi | Geçti |
| Düz token DB'de saklanmıyor, `waitlist` tablosunda `ip_hash` yok | Geçti |
| CSV'de `confirmed=yes` ve zaman damgası | Geçti |
| `confirm.php` başlıkları: `no-referrer`, `noindex`, `no-store` | Geçti |
| Geçersiz e-posta 422, farklı Origin 403, GET 405, honeypot 200, 6. denemede 429 (regresyon) | Geçti |
| Tarayıcı: yeni başarı mesajı ve gizlilik notu görünüyor, CSP ihlali 0 | Geçti |
| Tarayıcı: onay sayfası koyu temada düzgün görünüyor, butona basınca onaylandı | Geçti |

**Doğrulanmayanlar / açık:**
- Günlük tavan (`daily_signup_cap`) bu turda ayrıca test edilmedi; mantığı koddan okundu.
- CAPTCHA eklenmedi (üçüncü taraf hesap gerekir); çift onay ve tavan şimdilik koruma sağlıyor.
- Tek tıkla "listeden çık" bağlantısı yok; silme talebi elle yapılıyor.
- Mevcut bir veritabanı eski şemayla (ip_hash'li) oluşmuşsa `waitlist` tablosu yeniden oluşturulmalı; canlıda henüz tablo yok.
- Gerçek Space Mail üzerinden mail gönderimi ve onay mailinin spam'e düşüp düşmediği (SPF/DKIM) canlıda denenecek.
- Hosting HTTPS'inde linkin `https://` ile gelmesi için canlı `site_url` ayarı `https://alanadi.com` olmalı.
