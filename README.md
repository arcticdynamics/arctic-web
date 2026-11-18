# Arctic Dynamics Web

Arctic Dynamics web sitesi (statik `index.html` + waitlist API). Aynı site iki farklı ortam için ayrı klasörlerde tutulur.

| Klasör | Ortam | Backend | Durum |
|---|---|---|---|
| [`php/`](php/) | Apache / cPanel hosting (`public_html`) | PHP + MySQL + SMTP | Hazır |
| `vercel/` | Vercel | Node serverless fonksiyonlar | Planlanıyor |
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

Henüz eklenmedi. Vercel'de PHP/MySQL olmadığı için `api/` kısmı Node fonksiyonları ve harici bir veritabanıyla yeniden yazılacak. Vercel projesinde **Root Directory** `vercel/` olarak seçilecek.

## Gizli bilgiler

Gerçek `config.php`, `arctic-config.php`, `.env`, veritabanı ve dışa aktarılmış waitlist listeleri repoya **girmemeli** (`.gitignore` bunları dışarıda tutar). Repoda yalnızca placeholder içeren `*.sample.php` dosyaları bulunur.

## Sürümleme

Tag'ler sürüm tipini içerir: `php-v1.0.0`, `vercel-v1.0.0`.
