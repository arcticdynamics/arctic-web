// Neon Postgres baglantisi. DATABASE_URL, Vercel'de Neon entegrasyonu eklenince otomatik gelir.
import { neon } from '@neondatabase/serverless';

let sql;
let ready;

export function getSql() {
  if (!sql) {
    const url = process.env.DATABASE_URL;
    if (!url) throw new Error('DATABASE_URL tanimli degil');
    sql = neon(url);
  }
  return sql;
}

// Tablolar ilk istekte otomatik olusur: elle SQL calistirmaya gerek yok.
export async function ensureSchema() {
  if (!ready) {
    const q = getSql();
    ready = (async () => {
      await q`CREATE TABLE IF NOT EXISTS waitlist (
        id SERIAL PRIMARY KEY,
        email VARCHAR(254) NOT NULL UNIQUE,
        created_at TIMESTAMPTZ NOT NULL DEFAULT now()
      )`;
      // Acik riza kaniti (KVKK): rizanin verildigi an.
      await q`ALTER TABLE waitlist ADD COLUMN IF NOT EXISTS consent_at TIMESTAMPTZ`;
      await q`CREATE TABLE IF NOT EXISTS waitlist_attempts (
        id SERIAL PRIMARY KEY,
        ip_hash CHAR(64) NOT NULL,
        created_at TIMESTAMPTZ NOT NULL DEFAULT now()
      )`;
      await q`CREATE INDEX IF NOT EXISTS idx_attempts_ip_time ON waitlist_attempts (ip_hash, created_at)`;
    })().catch((err) => {
      ready = undefined; // sonraki istek yeniden denesin
      throw err;
    });
  }
  return ready;
}
