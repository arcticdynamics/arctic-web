// Saf (yan etkisiz) yardimcilar: kolay test edilsin diye db/http'den ayri tutuldu.
import { createHash, timingSafeEqual } from 'node:crypto';

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

export function normalizeEmail(value) {
  return String(value ?? '').trim().toLowerCase();
}

export function isValidEmail(email) {
  return email.length > 0 && email.length <= 254 && EMAIL_RE.test(email);
}

// Ad soyad: bosluklar sadelestirilir; en az iki sozcuk (ad + soyad), her biri harf icermeli, toplam 3-100 karakter.
export function normalizeName(value) {
  return String(value ?? '').normalize('NFC').replace(/\s+/g, ' ').trim();
}

export function isValidName(name) {
  const parts = name.split(' ');
  return name.length >= 3 && name.length <= 100 && parts.length >= 2 && parts.every((p) => /\p{L}/u.test(p));
}

// Ayni site disindan gelen istekleri reddet (basit CSRF/abuse korumasi).
export function isSameOrigin(origin, host) {
  if (!origin) return true;
  try {
    return new URL(origin).host === host;
  } catch {
    return false;
  }
}

// Vercel'in kendi koydugu basliklar once gelir (istemci bunlari sahteleyemez); x-forwarded-for
// yalnizca yerel gelistirme / baska bir proxy arkasi icin yedek.
export function clientIp(req) {
  const h = req.headers;
  const first = (v) => String(v ?? '').split(',')[0].trim();
  return first(h['x-vercel-forwarded-for']) || first(h['x-real-ip']) || first(h['x-forwarded-for']) || 'unknown';
}

// IP duz saklanmaz; tuzlu hash. Salt zayifsa sessizce zayif hash uretme: hata ver.
export function ipHash(ip, salt) {
  if (typeof salt !== 'string' || salt.length < 16) {
    throw new Error('SALT env degiskeni en az 16 karakter olmali');
  }
  return createHash('sha256').update(`${ip}|${salt}`).digest('hex');
}

export function safeEqual(a, b) {
  const x = Buffer.from(String(a));
  const y = Buffer.from(String(b));
  return x.length === y.length && timingSafeEqual(x, y);
}
