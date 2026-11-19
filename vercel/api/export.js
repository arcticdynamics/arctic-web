// GET /api/export  -> waitlist.xlsx (Excel'de acilir)
// Yetki: "Authorization: Bearer <ADMIN_TOKEN>" basligi veya ?token=<ADMIN_TOKEN>
// Excel: iki sekme (Waitlist, Testers). CSV icin: ?format=csv (&list=testers veya &list=waitlist)
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
    const rows = await getSql()`SELECT full_name, email, is_tester, created_at FROM waitlist ORDER BY id`;
    const day = new Date().toISOString().slice(0, 10);

    // Tester olarak kaydolanlar ayri listede; sadece bekleme listesine yazilanlar ayri.
    const testers = rows.filter((r) => r.is_tester);
    const waiters = rows.filter((r) => !r.is_tester);

    if (req.query?.format === 'csv') {
      // ?list=testers | ?list=waitlist ; verilmezse hepsi (tester sutunuyla)
      const list = req.query?.list;
      const picked = list === 'testers' ? testers : list === 'waitlist' ? waiters : rows;
      const lines = picked.map((r) => `${csvCell(r.full_name ?? '')},${csvCell(r.email)},${r.is_tester ? 'yes' : 'no'},${new Date(r.created_at).toISOString()}`);
      res.setHeader('Content-Type', 'text/csv; charset=utf-8');
      res.setHeader('Content-Disposition', `attachment; filename="${list === 'testers' ? 'testers' : list === 'waitlist' ? 'waitlist' : 'all'}-${day}.csv"`);
      return res.status(200).send('﻿full_name,email,tester,created_at\n' + lines.join('\n'));
    }

    // Excel: ayni dosyada iki sekme (sheet): "Waitlist" ve "Testers".
    const wb = new ExcelJS.Workbook();
    const addSheet = (name, list) => {
      const ws = wb.addWorksheet(name);
      ws.columns = [
        { header: 'Ad Soyad', key: 'full_name', width: 32 },
        { header: 'Email', key: 'email', width: 42 },
        { header: 'Kayit zamani (UTC)', key: 'created_at', width: 24, style: { numFmt: 'yyyy-mm-dd hh:mm' } },
      ];
      ws.getRow(1).font = { bold: true };
      ws.views = [{ state: 'frozen', ySplit: 1 }];
      // Hucreler string olarak yazilir; =, +, -, @ ile baslayan degerler formul olarak calismaz.
      for (const r of list) ws.addRow({ full_name: r.full_name ?? '', email: r.email, created_at: new Date(r.created_at) });
    };
    addSheet('Waitlist', waiters);
    addSheet('Testers', testers);

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
