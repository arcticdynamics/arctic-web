// Saf (yan etkisiz) yardimcilar: kolay test edilsin diye db/http'den ayri tutuldu.
import { createHash, timingSafeEqual } from 'node:crypto';

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

export function normalizeEmail(value) {
  return String(value ?? '').trim().toLowerCase();
}

export function isValidEmail(email) {
  return email.length > 0 && email.length <= 254 && EMAIL_RE.test(email);
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

export function clientIp(req) {
  const fwd = String(req.headers['x-forwarded-for'] ?? '').split(',')[0].trim();
  return fwd || String(req.headers['x-real-ip'] ?? '') || 'unknown';
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
