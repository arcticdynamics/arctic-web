// POST /api/subscribe  { email, website }
// Mail GONDERILMEZ. E-posta dogrudan bekleme listesine (Postgres) yazilir.
import { getSql, ensureSchema } from '../lib/db.js';
import { normalizeEmail, isValidEmail, normalizeName, isValidName, isSameOrigin, clientIp, ipHash } from '../lib/validate.js';

const json = (res, status, data) => {
  res.setHeader('Cache-Control', 'no-store');
  res.setHeader('X-Content-Type-Options', 'nosniff');
  return res.status(status).json(data);
};

// Formul enjeksiyonu savunmasi: =, +, -, @, TAB, CR ile baslayan degerlerin onune ' konur,
// Sheets bunu metin olarak saklar (=HYPERLINK(...) gibi girdiler formul olarak calismaz).
const sheetSafe = (v) => (/^[=+\-@\t\r]/.test(v) ? `'${v}` : v);

// Apps Script web uygulamasina sunucudan POST atar. URL tarayiciya hic cikmaz.
// text/plain: Apps Script JSON content-type'i sevmez (preflight). Sunucudan CORS sorunu yok.
async function pushToSheet(payload) {
  const url = process.env.GOOGLE_SHEET_URL;
  if (!url) return;
  // Ortak sir: Apps Script web uygulamasi herkese acik oldugu icin sahte istekleri bununla ayiklar.
  const secret = process.env.SHEET_SECRET;
  if (secret) payload = { ...payload, secret };
  try {
    const r = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'text/plain;charset=utf-8' },
      body: JSON.stringify(payload),
      signal: AbortSignal.timeout(5000),
    });
    if (!r.ok) console.error('sheet push status:', r.status);
    else {
      // Apps Script hatalari da 200 doner; sir uyusmazligi gibi sorunlari loga dusur.
      const body = await r.json().catch(() => null);
      if (body && body.ok === false) console.error('sheet push rejected:', body.error);
    }
  } catch (err) {
    console.error('sheet push:', err.message);
  }
}

export default async function handler(req, res) {
  if (req.method !== 'POST') return json(res, 405, { ok: false, error: 'method_not_allowed' });

  if (!isSameOrigin(req.headers.origin, req.headers.host)) {
    return json(res, 403, { ok: false, error: 'forbidden' });
  }

  const input = req.body && typeof req.body === 'object' ? req.body : {};

  // Honeypot: botlar bu gizli alani doldurur. Sessizce basarili gibi davran.
  if (input.website) return json(res, 200, { ok: true });

  const tester = input.tester === true; // "Tester olmak ister misiniz" formundan geldi mi
  const fullName = normalizeName(input.fullName);
  if (!isValidName(fullName)) return json(res, 422, { ok: false, error: 'invalid_name' });

  const email = normalizeEmail(input.email);
  if (!isValidEmail(email)) return json(res, 422, { ok: false, error: 'invalid_email' });

  // KVKK: acik riza zorunlu. Sunucu tarafinda da dogrulanir (form atlatilsa bile kayit olmaz).
  if (input.consent !== true) return json(res, 422, { ok: false, error: 'consent_required' });

  try {
    await ensureSchema();
    const sql = getSql();
    const ip = ipHash(clientIp(req), process.env.SALT);

    // Hiz siniri: ayni IP'den saatte en fazla 5 deneme (IP hash'i yalniz 24 saat tutulur)
    await sql`DELETE FROM waitlist_attempts WHERE created_at < now() - interval '24 hours'`;
    const [{ n }] = await sql`
      SELECT COUNT(*)::int AS n FROM waitlist_attempts
      WHERE ip_hash = ${ip} AND created_at > now() - interval '1 hour'`;
    if (n >= 5) return json(res, 429, { ok: false, error: 'rate_limited' });
    await sql`INSERT INTO waitlist_attempts (ip_hash) VALUES (${ip})`;

    // Genel gunluk tavan: toplu kayit istismarini sinirlar.
    const cap = Number(process.env.DAILY_SIGNUP_CAP ?? 500);
    if (cap > 0) {
      const [{ d }] = await sql`
        SELECT COUNT(*)::int AS d FROM waitlist WHERE created_at > now() - interval '24 hours'`;
      if (d >= cap) return json(res, 429, { ok: false, error: 'rate_limited' });
    }

    // Zaten kayitliysa da ayni basarili cevap verilir (liste sizdirilmaz).
    // Saklama suresi: gizlilik metnindeki gibi 12 ay sonra silinir.
    await sql`DELETE FROM waitlist WHERE created_at < now() - interval '12 months'`;
    await sql`INSERT INTO waitlist (email, full_name, is_tester, consent_at) VALUES (${email}, ${fullName}, ${tester}, now())
      ON CONFLICT (email) DO UPDATE SET is_tester = waitlist.is_tester OR EXCLUDED.is_tester`;

    // Google Sheets'e kopya (opsiyonel). Basarisiz olsa da kullanicinin kaydi bozulmaz.
    await pushToSheet({ email: sheetSafe(email), name: sheetSafe(fullName), type: tester ? 'tester' : 'waitlist' });
    return json(res, 200, { ok: true });
  } catch (err) {
    console.error('waitlist subscribe:', err.message);
    return json(res, 500, { ok: false, error: 'server_error' });
  }
}
