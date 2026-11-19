import { test } from 'node:test';
import assert from 'node:assert/strict';
import { normalizeEmail, isValidEmail, normalizeName, isValidName, isSameOrigin, ipHash, safeEqual, clientIp } from '../lib/validate.js';

test('e-posta normalize edilir ve dogrulanir', () => {
  assert.equal(normalizeEmail('  Ali@Ornek.COM '), 'ali@ornek.com');
  assert.ok(isValidEmail('ali@ornek.com'));
  for (const bad of ['', 'ali', 'ali@', '@ornek.com', 'a b@ornek.com', 'ali@ornek', 'a@b.c']) {
    assert.equal(isValidEmail(bad), false, bad);
  }
  assert.equal(isValidEmail('a'.repeat(250) + '@b.com'), false);
});

test('origin kontrolu', () => {
  assert.ok(isSameOrigin('', 'site.vercel.app'));
  assert.ok(isSameOrigin('https://site.vercel.app', 'site.vercel.app'));
  assert.equal(isSameOrigin('https://evil.com', 'site.vercel.app'), false);
  assert.equal(isSameOrigin('not a url', 'site.vercel.app'), false);
});

test('ip hash tuzsuz/zayif salt ile calismaz', () => {
  assert.throws(() => ipHash('1.2.3.4', undefined));
  assert.throws(() => ipHash('1.2.3.4', 'kisa'));
  const salt = 'x'.repeat(16);
  assert.match(ipHash('1.2.3.4', salt), /^[0-9a-f]{64}$/);
  assert.notEqual(ipHash('1.2.3.4', salt), ipHash('1.2.3.5', salt));
});

test('safeEqual ve clientIp', () => {
  assert.ok(safeEqual('abc', 'abc'));
  assert.equal(safeEqual('abc', 'abd'), false);
  assert.equal(safeEqual('abc', 'abcd'), false);
  assert.equal(clientIp({ headers: { 'x-forwarded-for': '9.9.9.9, 1.1.1.1' } }), '9.9.9.9');
  assert.equal(clientIp({ headers: {} }), 'unknown');
});

test('ad soyad normalize edilir ve dogrulanir', () => {
  assert.equal(normalizeName('  Ayşe   Yılmaz '), 'Ayşe Yılmaz');
  assert.ok(isValidName('Ayşe Yılmaz'));
  assert.ok(isValidName('Jo Li'));
  for (const bad of ['', ' ', 'A', 'Ayşe', '1234 5678', '!! ??', 'a b'.repeat(40)]) {
    assert.equal(isValidName(normalizeName(bad)), false, bad);
  }
});
