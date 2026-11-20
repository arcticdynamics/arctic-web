# Arctic Dynamics Web

Arctic Dynamics web sitesi (statik `index.html` + waitlist API). Aynı site iki farklı ortam için ayrı klasörlerde tutulur.

| Klasör | Ortam | Backend | Durum |
|---|---|---|---|
| [`php/`](php/) | Apache / cPanel hosting (`public_html`) | PHP + MySQL + SMTP | Hazır |
| [`vercel/`](vercel/) | Vercel (tanıtım sürümü) | Node serverless + Neon Postgres, mail yok | Kod hazır, deploy bekliyor |
| [`docs/`](docs/) | Dokümantasyon | - | - |

Her klasör kendi başına yayınlanabilir; biri diğerine bağımlı değildir.

## PHP sürümü (`php/`)

- Yayın: `php/` içeriği sunucuda `public_html`'e yüklenir. Adımlar: [docs/YAYIN_REHBERI.md](docs/YAYIN_REHBERI.md)
- Ayarlar: `php/api/config.sample.php` şablonu doldurulup `public_html`'in **üstüne** `arctic-config.php` olarak konur.
- Yerel test:
  ```bash
  cp php/api/config.local-test.sample.php php/api/config.php
  php -S localhost:8000 -t php
  ```
- Mimari ve kararlar: [docs/PROJE_DOKUMANTASYON.md](docs/PROJE_DOKUMANTASYON.md), test sonuçları: [docs/TEST_RAPORU.md](docs/TEST_RAPORU.md)

## Vercel sürümü (`vercel/`)

Tanıtım amaçlı sürümdür. Şirket/info maili olmadığı için **hiçbir e-posta gönderilmez**: ziyaretçi e-postasını girince kayıt veritabanına yazılır ve ekranda "Wishliste kaydedildiniz!" mesajı çıkar. Çift onay (confirm) adımı bu sürümde yoktur.

- **Yayın:** Vercel'de projeyi bu repodan içe aktarın, **Root Directory** olarak `vercel/` seçin. Marketplace'ten **Neon (Postgres)** ekleyin (`DATABASE_URL` otomatik gelir). `SALT` ve `ADMIN_TOKEN` değişkenlerini [`vercel/.env.example`](vercel/.env.example) dosyasına göre Vercel panelinden girin.
- **Excel çıktısı:** `https://<site>/api/export` adresine `Authorization: Bearer <ADMIN_TOKEN>` başlığıyla (`?token=` desteklenmez) istek atınca `waitlist-YYYY-MM-DD.xlsx` iner. CSV için `?format=csv` ekleyin.
- **Yerel geliştirme:** `cd vercel && npm install && npx vercel dev` (`.env` dosyasını `.env.example`'dan oluşturun). Birim testleri: `npm test`.
- Tablolar ilk kayıtta otomatik oluşur, elle SQL gerekmez.

## Gizli bilgiler

Gerçek `config.php`, `arctic-config.php`, `.env`, veritabanı ve dışa aktarılmış waitlist listeleri repoya **girmemeli** (`.gitignore` bunları dışarıda tutar). Repoda yalnızca placeholder içeren `*.sample.php` dosyaları bulunur.

## Sürümleme

Tag'ler sürüm tipini içerir: `php-v1.0.0`, `vercel-v1.0.0`.
