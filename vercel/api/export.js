// GET /api/export  -> waitlist.xlsx (Excel'de acilir)
// Yetki: "Authorization: Bearer <ADMIN_TOKEN>" basligi veya ?token=<ADMIN_TOKEN>
// CSV icin: ?format=csv
import ExcelJS from 'exceljs';
import { getSql, ensureSchema } from '../lib/db.js';
import { safeEqual } from '../lib/validate.js';

export default async function handler(req, res) {
  res.setHeader('Referrer-Policy', 'no-referrer');
  res.setHeader('X-Robots-Tag', 'noindex');
  res.setHeader('Cache-Control', 'no-store');

  // Zayif/eksik token ile disa aktarma kapali: en az 32 karakter gerekir.
  const adminToken = process.env.ADMIN_TOKEN ?? '';
  const bearer = String(req.headers.authorization ?? '').replace(/^Bearer\s+/i, '');
  const given = bearer || String(req.query?.token ?? '');
  if (adminToken.length < 32 || !safeEqual(adminToken, given)) {
    return res.status(403).send('Forbidden');
  }

  try {
    await ensureSchema();
    const rows = await getSql()`SELECT email, created_at FROM waitlist ORDER BY id`;
    const day = new Date().toISOString().slice(0, 10);

    if (req.query?.format === 'csv') {
      const lines = rows.map((r) => `${csvCell(r.email)},${new Date(r.created_at).toISOString()}`);
      res.setHeader('Content-Type', 'text/csv; charset=utf-8');
      res.setHeader('Content-Disposition', `attachment; filename="waitlist-${day}.csv"`);
      return res.status(200).send('﻿email,created_at\n' + lines.join('\n'));
    }

    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('Waitlist');
    ws.columns = [
      { header: 'Email', key: 'email', width: 42 },
      { header: 'Kayit zamani (UTC)', key: 'created_at', width: 24, style: { numFmt: 'yyyy-mm-dd hh:mm' } },
    ];
    ws.getRow(1).font = { bold: true };
    // Hucreler string olarak yazilir; =, +, -, @ ile baslayan degerler formul olarak calismaz.
    for (const r of rows) ws.addRow({ email: r.email, created_at: new Date(r.created_at) });

    const buf = await wb.xlsx.writeBuffer();
    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    res.setHeader('Content-Disposition', `attachment; filename="waitlist-${day}.xlsx"`);
    return res.status(200).send(Buffer.from(buf));
  } catch (err) {
    console.error('waitlist export:', err.message);
    return res.status(500).send('Server error');
  }
}

// CSV'de formul enjeksiyonuna karsi kacis + tirnaklama
function csvCell(v) {
  const s = /^[=+\-@]/.test(v) ? `'${v}` : v;
  return `"${s.replace(/"/g, '""')}"`;
}
