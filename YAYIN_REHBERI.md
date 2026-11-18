# Arctic Dynamics — Spaceship'te Yayına Alma Rehberi

Kodlama gerektirmez; her şey Spaceship ve hosting panelinden yapılır. **Sıra önemli.** Panel menü adları zamanla değişebilir; aşağıdaki adımlarda arama kutusuna yazılacak anahtar kelimeler verildi.

> Önce neyin test edildiğini bilmek için: [TEST_RAPORU.md](TEST_RAPORU.md). Kod Docker'da gerçek MySQL ve SMTP ile test edildi. **Henüz gerçek Space Mail ve gerçek hostingde denenmedi**; Adım 8 bu yüzden önemli.

---

## 0. Satın almadan önce kontrol listesi
Hosting paketini seçerken (veya destekten sorarak) şunları doğrula:

- [ ] **PHP 8.0 veya üstü** (PHP sürümü panelden seçilebilir olmalı)
- [ ] **MySQL** veritabanı
- [ ] **PDO MySQL** eklentisi (PHP ayarlarında `pdo_mysql`)
- [ ] **`.htaccess` desteği** (Apache veya LiteSpeed) ve `mod_headers` / `mod_rewrite`
- [ ] **Ücretsiz SSL** (https)
- [ ] Sunucudan **SMTP ile dışarı bağlantı** izni (`mail.spacemail.com` port **465**)
- [ ] File Manager veya FTP erişimi

Alacakların: **domain**, **hosting paketi**, **Space Mail kutusu**.

---

## 1. Domain'i hostinge bağla
1. Domain ve hosting aynı Spaceship hesabındaysa genelde otomatik bağlanır. Hosting panelinde domain'in **Primary domain** (ana alan adı) olarak göründüğünü kontrol et.
2. Domain'in DNS'i Spaceship'in kendi nameserver'larındaysa ek bir şey yapma. Başka yerdeyse DNS'i hostingin IP'sine yönlendirmen gerekir.
3. DNS değişikliği bazen birkaç saat sürer. Bu sırada diğer adımlara devam edebilirsin.

## 2. Mail kutusunu aç (Space Mail)
1. Panelde **Space Mail** (veya Email) bölümünden domain'ine bir kutu ekle, örn. `hello@senindomain.com`.
2. Şifreyi **kimseyle paylaşma** (sohbete de yazma). Yalnızca 5. adımdaki ayar dosyasına yazılacak.
3. Webmail'e girip kendi kişisel adresine bir test maili gönder ve al. Kutunun çalıştığından emin ol.
4. **SPF ve DKIM kayıtları:** Space Mail panelinde domain için bu kayıtların eklendiğini/etkin olduğunu kontrol et (genellikle panel ekranında "DNS kayıtları" olarak listelenir). Bunlar yoksa onay mailleri spam'e düşebilir. Bu adım teslim edilebilirlik için kritik.

## 3. MySQL veritabanı oluştur
Hosting panelinde (cPanel) **MySQL Databases** (veya "MySQL Veritabanları") bölümü:
1. Yeni veritabanı oluştur.
2. Yeni kullanıcı oluştur, **güçlü şifre** ver (şifre üretici kullan).
3. Kullanıcıyı veritabanına **ALL PRIVILEGES** ile ekle.
4. Şunları not al (cPanel genelde ada hesap önekini ekler, örn. `hesap_arctic`):
   - veritabanı adı (tam, önekli hali)
   - kullanıcı adı (tam, önekli hali)
   - şifre
5. **Tablo oluşturmana gerek yok**, ilk kayıtta otomatik oluşur.
6. Veritabanı sunucusu çoğu zaman `localhost`'tur. Panelde farklı bir host yazıyorsa onu kullan.

## 4. Dosyaları yükle
Yüklenecek paket: **`arctic-site.zip`** (içinde gizli ayar veya gereksiz dosya yok).

1. Hosting panelinde **File Manager**'ı aç.
2. Ayarlardan **Show Hidden Files (dotfiles)** seçeneğini aç. Aksi halde `.htaccess` dosyalarını göremezsin.
3. `public_html` klasörüne gir. İçinde panelin koyduğu bir `index.html` / `default.html` varsa sil veya yeniden adlandır.
4. `arctic-site.zip` dosyasını `public_html`'e yükle ve **Extract** de.
5. Çıkarma bitince `public_html` içinde şunlar **doğrudan** görünmeli (bir alt klasörün içine değil):
   ```
   public_html/
   ├─ .htaccess
   ├─ index.html
   ├─ assets/
   └─ api/
      ├─ .htaccess
      ├─ confirm.php
      ├─ export.php
      ├─ subscribe.php
      ├─ config.sample.php
      └─ lib/ (.htaccess, bootstrap.php, smtp.php, templates.php)
   ```
6. Kontrol et:
   - Dosya adlarında ters eğik çizgi (`api\lib\...`) olan **düz dosyalar** olmamalı. `api` ve `lib` gerçek klasör olmalı. (Sorun çıkarsa zip'i yerine dosyaları File Manager ile klasör klasör yükle.)
   - `.htaccess` dosyaları 3 yerde olmalı: `public_html/`, `api/`, `api/lib/`.
7. Zip'i `public_html` içinden sil (gerek kalmadı).

## 5. Ayar dosyasını oluştur (en önemli adım)
1. `public_html/api/config.sample.php` dosyasının içeriğini kopyala.
2. **`public_html`'in bir üstündeki klasörde** (ev dizinin kökü, `public_html` ile yan yana) `arctic-config.php` adıyla yeni dosya oluştur. Orası web'den erişilemez, en güvenlisi budur.
3. Değerleri doldur:

   | Ayar | Ne yazılacak | Kural |
   |---|---|---|
   | `site_url` | `https://senindomain.com` | **https** ile, sonunda `/` yok. Onay linkleri buradan üretilir. |
   | `salt` | uzun rastgele metin | **en az 16 karakter**, örnek değerden farklı |
   | `admin_token` | uzun rastgele metin | **en az 32 karakter**, örnek değerden farklı |
   | `notify_to` | şirket mail adresi (isteğe bağlı) | Her **onaylanan** kayıtta bildirim gelir |
   | `db` → `host`, `name`, `user`, `pass` | 3. adımdaki bilgiler | `name` ve `user` önekli tam hali |
   | `smtp` → `host` | `mail.spacemail.com` | |
   | `smtp` → `port` | `465` | **Yalnızca 465 veya 587** kabul edilir |
   | `smtp` → `user` | `hello@senindomain.com` | tam mail adresi |
   | `smtp` → `pass` | mail kutusu şifresi | |
   | `daily_signup_cap` | (isteğe bağlı) günlük en fazla yeni kayıt | varsayılan 500, `0` kapatır |

4. **Canlı ayarda şunları ASLA koyma:** `mail_mode`, `db.driver`, `smtp.allow_insecure` (bunlar yalnızca yerel test içindir).
5. Rastgele değer üretmek için kendi bilgisayarında PowerShell:
   ```powershell
   -join ((48..57)+(65..90)+(97..122) | Get-Random -Count 48 | ForEach-Object {[char]$_})
   ```
   Her değer için ayrı çalıştır. Üretilen değerleri sohbete veya e-postaya yazma, doğrudan dosyaya yapıştır.
6. `admin_token` değerini bir parola yöneticisine kaydet; CSV indirirken lazım olacak.

## 6. SSL'i etkinleştir
1. Panelde domain için ücretsiz SSL'i (AutoSSL / Let's Encrypt) etkinleştir. Sertifikanın çıkması birkaç dakika sürebilir.
2. `https://senindomain.com` adresinin kilit simgesiyle açıldığını doğrula.
3. `http://senindomain.com` açıldığında otomatik `https://`'e yönlenmeli (kök `.htaccess` bunu yapar).

## 7. Güvenlik kontrolleri (yüklemeden hemen sonra)
Tarayıcıda şu adresleri aç, **hepsi "403 Forbidden" (veya 404) vermeli, içerik göstermemeli**:

| Adres | Beklenen |
|---|---|
| `https://senindomain.com/api/lib/bootstrap.php` | 403 |
| `https://senindomain.com/api/config.sample.php` | 403 |
| `https://senindomain.com/arctic-config.php` | 404 (dosya web kökünde değil) |
| `https://senindomain.com/api/config.php` | 404 (hiç oluşturulmadı) |

403 yerine PHP kodu veya sayfa içeriği görürsen **siteyi hemen kapat**, `.htaccess` desteğini hosting desteğine sor.

Güvenlik başlıklarını Windows'ta kontrol etmek için:
```powershell
curl.exe -sI https://senindomain.com/ | findstr /i "strict content-security x-frame x-content referrer"
```
`Strict-Transport-Security`, `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options` ve `Referrer-Policy` satırları görünmeli. Görünmüyorsa hosting `mod_headers` desteklemiyor olabilir; destekten sor.

## 8. Uçtan uca canlı test (çift onay)
Kayıt artık **iki adımlı**: önce form, sonra maildeki onay linki.

1. Siteyi aç, "Join the waitlist" ile **kendi** (kişisel) mailini gir. "Almost there…" mesajını görmelisin.
2. Birkaç dakika içinde **"Confirm your spot on the Arctic waitlist"** maili gelmeli (gelmezse spam klasörüne bak).
3. Mailin içindeki linke tıkla. Açılan sayfada **"Confirm my email"** butonuna bas. "You're on the list" sayfasını görmelisin.
4. **"You're on the Arctic waitlist"** hoşgeldin maili gelmeli. `notify_to` ayarlıysa şirket kutusuna da bildirim düşmeli.
5. Aynı maili tekrar gir: yeni mail **gelmemeli**, yine başarı mesajı görünmeli.
6. Mail adresindeki linkin `https://senindomain.com/api/confirm.php?t=...` biçiminde ve **https** ile geldiğini kontrol et (`http://` geliyorsa `site_url` yanlıştır).
7. CSV'yi indir (Adım 9) ve kaydın `confirmed = yes` olarak göründüğünü doğrula.
8. Mobil telefondan siteyi ve onay sayfasını aç; görünümü kontrol et.

### Mail gelmediyse
Kayıt yine veritabanındadır (`mail_status = failed`). Hosting panelindeki **Error Log** dosyasında `waitlist mail:` satırı sebebi yazar:

| Log mesajı | Olası sebep |
|---|---|
| `SMTP connect failed` | Hosting 465 portuna dışarı bağlantıyı engelliyor veya host adı yanlış. Hosting desteğinden SMTP dışarı bağlantıyı sor. |
| `SMTP error: 535` | Mail kullanıcı adı veya şifresi yanlış (`smtp.user` tam adres olmalı). |
| `port ... desteklenmiyor` | `smtp.port` 465 veya 587 değil. |
| `salt ... en az 16 karakter` | Ayar dosyasında `salt` kısa/yanlış. Sitede "server_error" görünür. |
| `config_missing` / `server_error` | `arctic-config.php` yanlış yerde veya veritabanı bilgileri yanlış. |

Bir kayıt `failed` kalırsa, kişi aynı e-postayla formu tekrar gönderdiğinde onay maili **yeniden denenir**.

## 9. Listeyi indir (Excel)
`https://senindomain.com/api/export.php?token=ADMIN_TOKEN` adresi CSV indirir, Excel'de açılır.

- Sütunlar: `email, confirmed, confirmed_at, mail_status, created_at`.
- Duyuru/açılış maili atarken **yalnızca `confirmed = yes`** olanları kullan.
- Token URL'de taşındığı için bu adresi kimseyle paylaşma, ekran görüntüsü alma ve yer imine ekleme. Şüphelenirsen `admin_token`'ı değiştir.

## 10. İşletme ve bakım
- **Silme talebi:** Biri mailine yanıt verip silinmek isterse hosting panelinde **phpMyAdmin**'i aç, veritabanını seç, SQL sekmesinde çalıştır:
  ```sql
  DELETE FROM waitlist WHERE email = 'kisi@ornek.com';
  ```
  (E-posta adresini küçük harfle yaz.) Bu işlem geri alınamaz.
- **Yedek:** Düzenli olarak CSV indirip güvenli bir yerde sakla veya panelden veritabanı yedeği al.
- **Gizlilik:** Formdaki metin gerçek davranışı anlatıyor (e-posta kalıcı, IP hash'i 24 saat, silme için maile yanıt). Ayrı bir gizlilik politikası sayfası ve KVKK/GDPR uyumu için hukuki danışmanlık al.
- **Ayar değiştirmek:** Yalnızca `public_html`'in üstündeki `arctic-config.php`'yi düzenle. Siteyi güncellerken dosyaları yeniden yüklersen ayar dosyası etkilenmez.
- **Site güncelleme:** Yeni `arctic-site.zip` yükleyip aynı yere Extract de. Üzerine yazmayı onayla.

## Güvenlik özeti
- Şifreler yalnızca `public_html` dışındaki `arctic-config.php` içinde; sitede ve zip'te yok.
- E-posta çift onaylı; onay token'ının yalnızca hash'i saklanır.
- IP adresi `waitlist` tablosunda saklanmaz; hız sınırı için tuzlu hash olarak yalnızca 24 saat tutulur.
- Bot ve kötüye kullanım koruması: gizli alan, IP başına saatte 5 deneme, günlük toplam kayıt tavanı.
- HTTPS yönlendirmesi ve güvenlik başlıkları kök `.htaccess` ile sağlanır.
- `export.php` en az 32 karakterlik token ister.

## Hâlâ açık olanlar (yayından sonra değerlendir)
- CAPTCHA yok (çift onay ve günlük tavan koruma sağlıyor).
- Tek tıkla "listeden çık" bağlantısı yok; silme elle yapılıyor.
- Site Cloudflare gibi bir proxy arkasına alınırsa hız sınırı gerçek IP'yi görmeyebilir. O durumda haber ver.
