// Google Apps Script web uygulamasi: waitlist/tester kayitlarini sheet'e yazar.
// Repodaki kopya. Canlisi script.google.com'da; burada degisiklik yapinca oraya yapistirip
// "Dagit > Dagitimlari yonet > duzenle > Yeni surum" ile yayinlayin (URL ayni kalir).
//
// Ortak sir: Project Settings > Script Properties > SHEET_SECRET (Vercel'deki SHEET_SECRET ile ayni).
// Tanimli degilse kontrol atlanir (kurulum sirasinda kayitlar kesilmesin diye).

const HEADERS = ["Ad Soyad", "Email", "Kayit zamani (UTC)"];

function getTab(ss, name) {
  let sh = ss.getSheetByName(name);
  if (!sh) sh = ss.insertSheet(name);
  if (sh.getLastRow() === 0) {
    sh.appendRow(HEADERS);
    sh.getRange(1, 1, 1, 3).setFontWeight("bold");
    sh.setFrozenRows(1);
    sh.setColumnWidth(1, 230);
    sh.setColumnWidth(2, 300);
    sh.setColumnWidth(3, 170);
  }
  return sh;
}

// =, +, -, @, TAB, CR ile baslayan degerler formul olarak calismasin.
// Sunucu zaten bastaki ' ile gonderebilir; ikinci kez eklenmez.
function safe(v) {
  v = String(v || "");
  return /^[=+\-@\t\r]/.test(v) ? "'" + v : v;
}

function doPost(e) {
  let data;
  try {
    data = JSON.parse(e.postData.contents);
  } catch (err) {
    return out({ ok: false, error: "bad_request" });
  }

  // Sir dogrulamasi kilitten ONCE: yetkisiz istekler kilidi mesgul etmesin.
  const secret = PropertiesService.getScriptProperties().getProperty("SHEET_SECRET");
  if (secret && data.secret !== secret) return out({ ok: false, error: "unauthorized" });

  const lock = LockService.getScriptLock();
  lock.waitLock(10000);
  try {
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const sh = getTab(ss, data.type === "tester" ? "Testers" : "Waitlist");

    // Tekrar kontrolu icin kacissiz, kucuk harfli e-posta (sunucudan bastaki ' gelmis olabilir)
    const email = String(data.email || "").replace(/^'/, "").toLowerCase();
    const last = sh.getLastRow();
    if (last > 1) {
      const emails = sh.getRange(2, 2, last - 1, 1).getValues().flat();
      if (emails.includes(email)) return out({ ok: true, duplicate: true });
    }

    const when = Utilities.formatDate(new Date(), "UTC", "yyyy-MM-dd HH:mm");
    sh.appendRow([safe(String(data.name || "").replace(/^'/, "")), safe(email), when]);
    return out({ ok: true });
  } catch (err) {
    return out({ ok: false, error: String(err) });
  } finally {
    lock.releaseLock();
  }
}

function out(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
