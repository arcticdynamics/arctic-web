// POST /api/subscribe  { email, website }
// Mail GONDERILMEZ. E-posta dogrudan bekleme listesine (Postgres) yazilir.
import { getSql, ensureSchema } from '../lib/db.js';
import { normalizeEmail, isValidEmail, isSameOrigin, clientIp, ipHash } from '../lib/validate.js';

const json = (res, status, data) => {
  res.setHeader('Cache-Control', 'no-store');
  res.setHeader('X-Content-Type-Options', 'nosniff');
  return res.status(status).json(data);
};

export default async function handler(req, res) {
  if (req.method !== 'POST') return json(res, 405, { ok: false, error: 'method_not_allowed' });

  if (!isSameOrigin(req.headers.origin, req.headers.host)) {
    return json(res, 403, { ok: false, error: 'forbidden' });
  }

  const input = req.body && typeof req.body === 'object' ? req.body : {};

  // Honeypot: botlar bu gizli alani doldurur. Sessizce basarili gibi davran.
  if (input.website) return json(res, 200, { ok: true });

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
    await sql`INSERT INTO waitlist (email, consent_at) VALUES (${email}, now()) ON CONFLICT (email) DO NOTHING`;
    return json(res, 200, { ok: true });
  } catch (err) {
    console.error('waitlist subscribe:', err.message);
    return json(res, 500, { ok: false, error: 'server_error' });
  }
}
