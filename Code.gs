/** PLN Gudang Inventory - backend Google Apps Script (versi 11)
 *  Container-bound ke spreadsheet "inventory". Spreadsheet boleh diedit langsung; web membaca ulang isinya.
 *  Sheet: Data_Barang, Barang_Masuk, Barang_Keluar, Pengambilan_Ritel, Pengambilan_Ggn, Pengambilan_ICRM,
 *         Pengambilan_Aktivasi, Pengambilan_HAR, Pengambilan_Pembangunan, Master_SN. */
const SPREADSHEET_ID = ''; // kosongkan jika script dibuat dari Ekstensi > Apps Script di dalam spreadsheet
const API_KEY = PropertiesService.getScriptProperties().getProperty('API_KEY') || ''; // kosong = tanpa pemeriksaan kunci
const DATEFMT = 'dddd, mmmm d, yyyy';
const HEAD_BG = '#035B71';
const VALTYPE = ['EX-PROJECT', 'DISMANTLE', 'RUSAK-L', 'RUSAK-TL', 'BAIK'];

// [kunci, judul kolom, alias judul lain yang dikenali]
const S = {
  Data_Barang: [['nama', 'Nama Material', 'Nama Barang', 'Material'], ['valtype', 'Valtype'], ['satuan', 'Satuan'], ['awal', 'Stok Awal'], ['min', 'Stok Minimum', 'Stok Min'], ['ket', 'Keterangan', 'Ket']],
  Barang_Masuk: [['no', 'No'], ['tgl', 'Tanggal'], ['nama', 'Nama Material', 'Material', 'Nama Barang', 'Nama Deskripsi', 'Deskripsi'], ['valtype', 'Valtype'], ['sn', 'SN', 'SN (Qty)', 'Serial Number'], ['ket', 'Keterangan', 'Ket'], ['divisi', 'Divisi']],
  Barang_Keluar: [['tgl', 'Tanggal'], ['pengambil', 'Yang Mengambil', 'Pengambil'], ['material', 'Material', 'Nama Material', 'Nama Deskripsi', 'Deskripsi'], ['sn', 'SN', 'SN (Qty)', 'Serial Number'], ['ket', 'Keterangan', 'Ket'], ['mitra', 'Mitra']],
  Pengambilan_Ritel: [['no', 'No'], ['tgl', 'Tanggal'], ['mitra', 'Mitra'], ['bidang', 'Bidang'], ['nama', 'Nama'], ['material', 'Material', 'Nama Material', 'Nama Deskripsi', 'Deskripsi'], ['sn', 'SN', 'SN (Qty)', 'Serial Number'], ['lokasi', 'Lokasi'], ['ket', 'Keterangan', 'Ket']],
  Pengambilan_Ggn: [['no', 'No'], ['tgl', 'Tanggal'], ['mitra', 'Mitra'], ['bidang', 'Bidang'], ['nama', 'Nama'], ['material', 'Material', 'Nama Material', 'Nama Deskripsi', 'Deskripsi'], ['sn', 'SN', 'SN (Qty)', 'Serial Number'], ['lokasi', 'Lokasi'], ['ket', 'Keterangan', 'Ket']],
  Pengambilan_ICRM: [['no', 'No'], ['tgl', 'Tanggal'], ['wo', 'WO'], ['mitra', 'Mitra'], ['nama', 'Nama'], ['lokasi', 'Lokasi'], ['perangkat', 'Perangkat'], ['sn', 'SN', 'SN (Qty)', 'Serial Number']],
  Pengambilan_Aktivasi: [['no', 'No'], ['tgl', 'Tanggal'], ['pa', 'PA'], ['mitra', 'Mitra'], ['bidang', 'Bidang'], ['sn', 'SN', 'SN (Qty)', 'Serial Number']],
  Pengambilan_HAR: [['no', 'No'], ['tgl', 'Tanggal'], ['pengambil', 'Yang Mengambil', 'Pengambil'], ['bidang', 'Bidang'], ['nama', 'Nama'], ['material', 'Material', 'Nama Material', 'Nama Deskripsi', 'Deskripsi'], ['sn', 'SN', 'SN (Qty)', 'Serial Number'], ['lokasi', 'Lokasi'], ['ket', 'Keterangan', 'Ket']],
  Pengambilan_Pembangunan: [['no', 'No'], ['tgl', 'Tanggal'], ['bidang', 'Bidang'], ['nama', 'Nama'], ['material', 'Material', 'Nama Material', 'Nama Deskripsi', 'Deskripsi'], ['sn', 'SN', 'SN (Qty)', 'Serial Number'], ['lokasi', 'Lokasi'], ['ket', 'Keterangan', 'Ket']],
  Master_SN: [['kode', 'Kode Material', 'Material'], ['nama', 'Deskripsi Material', 'Material Description'], ['valtype', 'Valtype', 'Batch'], ['plnt', 'Plnt'], ['sloc', 'SLoc'], ['sn', 'Serial Number', 'SN'], ['ket', 'Keterangan', 'Ket'], ['lokasi', 'Lokasi']]
};
const NUM = ['no', 'awal', 'min'];
const TEXTK = ['sn', 'wo', 'pa', 'kode', 'plnt', 'sloc'];
const OUT = ['Barang_Keluar', 'Pengambilan_Ritel', 'Pengambilan_Ggn', 'Pengambilan_HAR', 'Pengambilan_Pembangunan']; // SN tidak boleh keluar dua kali
const AUTO_NO = ['Barang_Masuk', 'Pengambilan_Ritel', 'Pengambilan_Ggn', 'Pengambilan_ICRM', 'Pengambilan_Aktivasi', 'Pengambilan_HAR', 'Pengambilan_Pembangunan'];
const QTY = ['Barang_Masuk'].concat(OUT);                 // boleh tanpa SN + jumlah
const SNFREE = ['Pengambilan_ICRM', 'Pengambilan_Aktivasi'];  // kolom SN berupa teks bebas
const REQ = {
  Barang_Masuk: [['tgl', 'Tanggal'], ['nama', 'Deskripsi'], ['valtype', 'Valtype']],
  Barang_Keluar: [['tgl', 'Tanggal'], ['pengambil', 'Yang Mengambil'], ['material', 'Deskripsi']],
  Pengambilan_Ritel: [['tgl', 'Tanggal'], ['mitra', 'Mitra'], ['material', 'Material']],
  Pengambilan_Ggn: [['tgl', 'Tanggal'], ['material', 'Material']],
  Pengambilan_ICRM: [['tgl', 'Tanggal'], ['wo', 'WO'], ['perangkat', 'Perangkat']],
  Pengambilan_Aktivasi: [['tgl', 'Tanggal'], ['pa', 'PA']],
  Pengambilan_HAR: [['tgl', 'Tanggal'], ['material', 'Material']],
  Pengambilan_Pembangunan: [['tgl', 'Tanggal'], ['material', 'Material']],
  Data_Barang: [['nama', 'Nama Material']]
};

const nz = s => String(s == null ? '' : s).toLowerCase().replace(/[^a-z0-9]/g, '');
const mkey = s => String(s == null ? '' : s).toUpperCase().replace(/\s+/g, ' ').trim();
const N = x => Number(x) || 0;
const ss = () => SPREADSHEET_ID ? SpreadsheetApp.openById(SPREADSHEET_ID) : SpreadsheetApp.getActive();
// hash ringan: hanya untuk mendeteksi baris yang berubah antara tampil dan simpan
const sig = a => { const t = a.join('\u0001'); let h = 5381; for (let i = 0; i < t.length; i++) h = ((h << 5) + h + t.charCodeAt(i)) | 0; return (h >>> 0).toString(36) + '.' + t.length.toString(36); };
const p2 = n => (n < 10 ? '0' : '') + n;
// Tanggal selalu dibaca/ditulis menurut zona waktu SPREADSHEET agar tidak bergeser sehari bila zona waktu script berbeda.
// getSpreadsheetTimeZone() bisa kosong (null) pada spreadsheet hasil unggah .xlsx -> pakai zona script, lalu Asia/Jakarta.
let _tz = null; const tz = () => _tz || (_tz = ss().getSpreadsheetTimeZone() || Session.getScriptTimeZone() || 'Asia/Jakarta');
const _dc = {}, _pc = {};
const ymd = d => { const k = d.getTime(); return _dc[k] || (_dc[k] = Utilities.formatDate(d, tz(), 'yyyy-MM-dd')); };
const parseTgl = v => _pc[v] || (_pc[v] = Utilities.parseDate(v, tz(), 'yyyy-MM-dd'));
const snKey = s => String(s || '').replace(/\s+/g, '').toUpperCase();
const snLines = s => String(s || '').split(/[\r\n]+/).map(snKey).filter(Boolean);

function styleHead(r) { r.setBackground(HEAD_BG).setFontColor('#FFFFFF').setFontWeight('bold'); }
function sh(n) {
  let s = ss().getSheetByName(n);
  if (!s) {
    s = ss().insertSheet(n);
    styleHead(s.getRange(1, 1, 1, S[n].length).setValues([S[n].map(c => c[1])]));
    s.setFrozenRows(1);
    const m = cmap(n, s);
    S[n].forEach(c => { const j = m[c[0]]; if (TEXTK.indexOf(c[0]) >= 0) s.getRange(2, j + 1, s.getMaxRows() - 1, 1).setNumberFormat('@'); if (c[0] === 'tgl') s.getRange(2, j + 1, s.getMaxRows() - 1, 1).setNumberFormat(DATEFMT); });
  }
  return s;
}
/** Peta kunci -> nomor kolom (0-based) dari judul di baris 1. Kolom yang hilang ditambahkan di kanan. */
function cmap(n, s) {
  const lc = s.getLastColumn();
  const hd = lc ? s.getRange(1, 1, 1, lc).getValues()[0].map(nz) : [];
  const m = {};
  S[n].forEach(c => {
    const al = c.slice(1).map(nz);
    let j = hd.findIndex(h => h && al.indexOf(h) >= 0);
    if (j < 0) { j = hd.length; hd.push(nz(c[1])); styleHead(s.getRange(1, j + 1).setValue(c[1])); }
    m[c[0]] = j;
  });
  return m;
}
function cell(x) { if (x instanceof Date) return ymd(x); return x === undefined || x === null ? '' : String(x).trim(); }
function mkRow(n, m, r, rowNo) {
  const o = { _r: rowNo }; let any = false;
  S[n].forEach(c => { const x = cell(r[m[c[0]]]); if (x !== '') any = true; o[c[0]] = x; });
  if (!any) return null;
  o._s = sig(S[n].map(c => o[c[0]])); return o;
}
function rows(n) {
  const s = sh(n), m = cmap(n, s), lr = s.getLastRow(), lc = s.getLastColumn();
  if (lr < 2) return [];
  const v = s.getRange(2, 1, lr - 1, lc).getValues(), out = [];
  for (let i = 0; i < v.length; i++) { const o = mkRow(n, m, v[i], i + 2); if (o) out.push(o); }
  return out;
}
function rowAt(n, r) {
  const s = sh(n), m = cmap(n, s);
  if (r < 2 || r > s.getLastRow()) return null;
  return mkRow(n, m, s.getRange(r, 1, 1, s.getLastColumn()).getValues()[0], r);
}
/** Hanya satu kolom (jauh lebih cepat daripada membaca seluruh sheet). Mengembalikan [{r, v}] untuk sel tidak kosong. */
function colVals(n, key) {
  const s = sh(n), m = cmap(n, s), lr = s.getLastRow(); if (lr < 2) return [];
  const v = s.getRange(2, m[key] + 1, lr - 1, 1).getValues(), out = [];
  for (let i = 0; i < v.length; i++) { const x = cell(v[i][0]); if (x !== '') out.push({ r: i + 2, v: x }); }
  return out;
}
function conv(k, v) {
  if (v === '' || v === undefined || v === null) return '';
  if (k === 'tgl' && /^\d{4}-\d{2}-\d{2}$/.test(v)) return parseTgl(v);
  if (NUM.indexOf(k) >= 0 && /^-?\d+(\.\d+)?$/.test(String(v))) return Number(v);
  return String(v);
}
function addRows(n, list) {
  if (!list.length) return;
  const s = sh(n), m = cmap(n, s), lc = s.getLastColumn(), r0 = s.getLastRow() + 1;
  const vals = list.map(o => { const a = new Array(lc).fill(''); S[n].forEach(c => { if (o[c[0]] !== undefined) a[m[c[0]]] = conv(c[0], o[c[0]]); }); return a; });
  if (r0 + list.length - 1 > s.getMaxRows()) s.insertRowsAfter(s.getMaxRows(), r0 + list.length - 1 - s.getMaxRows());
  S[n].forEach(c => { const j = m[c[0]]; if (TEXTK.indexOf(c[0]) >= 0) s.getRange(r0, j + 1, list.length, 1).setNumberFormat('@'); if (c[0] === 'tgl') s.getRange(r0, j + 1, list.length, 1).setNumberFormat(DATEFMT); });
  s.getRange(r0, 1, list.length, lc).setValues(vals);
}
function updRow(n, r, o) {
  const s = sh(n), m = cmap(n, s);
  S[n].forEach(c => {
    const k = c[0]; if (k === 'no' || o[k] === undefined) return;
    const g = s.getRange(r, m[k] + 1);
    if (TEXTK.indexOf(k) >= 0) g.setNumberFormat('@');
    if (k === 'tgl') g.setNumberFormat(DATEFMT);
    g.setValue(conv(k, o[k]));
  });
}

function doGet() {
  let info;
  try { info = 'terhubung ke spreadsheet: ' + ss().getName(); } catch (e) { info = 'BELUM terhubung ke spreadsheet (' + e.message + ')'; }
  return ContentService.createTextOutput('PLN Gudang Inventory API aktif - versi 11 - ' + info);
}
function doPost(e) {
  let out;
  try { out = route(JSON.parse(e.postData.contents)); }
  catch (err) { out = { ok: false, error: String(err.message || err) }; }
  return ContentService.createTextOutput(JSON.stringify(out)).setMimeType(ContentService.MimeType.JSON);
}
function route(q) {
  if (API_KEY && String(q.key || '').trim() !== API_KEY) throw new Error('Akses ditolak: kunci API tidak valid');
  if (q.action === 'boot') {
    const d = {}; (q.sheets || Object.keys(S)).forEach(n => { if (S[n]) d[n] = rows(n); });
    return { ok: true, data: d };
  }
  const lock = LockService.getScriptLock();
  lock.waitLock(30000); // cegah dua penyimpanan bersamaan
  try { return q.action === 'import' ? importRows(q) : q.action === 'sync' ? syncRows(q) : write(q); } finally { lock.releaseLock(); }
}

function snDuplikat(n, sn, exceptR) {
  const grup = n === 'Barang_Masuk' ? ['Barang_Masuk'] : OUT, u = snKey(sn);
  for (let i = 0; i < grup.length; i++) {
    const g = grup[i], v = colVals(g, 'sn');
    for (let j = 0; j < v.length; j++) {
      if (g === n && exceptR && v[j].r === exceptR) continue;
      if (snLines(v[j].v).indexOf(u) >= 0) return g.replace('_', ' ') + ' (baris ' + v[j].r + ')';
    }
  }
  return '';
}

function write(q) {
  const a = q.action, n = q.sheet, src = q.data || {};
  if (!S[n] || n === 'Master_SN') throw new Error('Sheet tidak dapat diubah dari web');
  if (a === 'del') { const x = find(n, q._r, q._s); sh(n).deleteRow(x._r); return { ok: true }; }
  if (a !== 'add' && a !== 'save') throw new Error('Aksi tidak dikenal');

  const d = {};
  S[n].forEach(c => {
    const k = c[0]; if (k === 'no') return;
    let v = src[k] === undefined || src[k] === null ? '' : String(src[k]);
    d[k] = k === 'sn' ? v.replace(/\r/g, '').split('\n').map(x => x.trim()).filter(Boolean).join('\n') : v.trim();
  });
  const old = a === 'save' ? find(n, q._r, q._s) : null;
  (REQ[n] || []).forEach(r => { if (!d[r[0]]) throw new Error(r[1] + ' wajib diisi'); });
  if (S[n].some(c => c[0] === 'tgl') && !/^\d{4}-\d{2}-\d{2}$/.test(d.tgl)) throw new Error('Tanggal tidak valid');
  if (S[n].some(c => c[0] === 'valtype') && d.valtype && VALTYPE.indexOf(d.valtype) < 0 && !(old && old.valtype === d.valtype)) throw new Error('Valtype harus dipilih dari daftar: ' + VALTYPE.join(', '));
  if (n === 'Data_Barang') {
    if (rows('Data_Barang').some(x => mkey(x.nama) === mkey(d.nama) && mkey(x.valtype) === mkey(d.valtype) && (!old || x._r !== old._r))) throw new Error('Material "' + d.nama + '" dengan Valtype tersebut sudah ada di Data Material');
    ['awal', 'min'].forEach(k => { if (d[k] !== '' && isNaN(Number(d[k]))) throw new Error('Stok harus berupa angka'); });
    if (old) updRow(n, old._r, d); else addRows(n, [d]);
    return { ok: true, count: 1 };
  }
  if (SNFREE.indexOf(n) < 0 && d.sn) {
    if (/[,;]/.test(d.sn) || (d.sn.indexOf('\n') >= 0 && !(old && old.sn === d.sn))) throw new Error('SN hanya boleh satu per baris (tanpa koma / baris baru). Untuk beberapa unit, tambahkan satu transaksi per SN.');
    const dup = snDuplikat(n, d.sn.split('\n')[0], old && old._r);
    if (dup) throw new Error('SN "' + d.sn + '" sudah tercatat di ' + dup + ' (duplikat)');
  }
  if (old) { updRow(n, old._r, d); return { ok: true, count: 1 }; }

  const qty = (QTY.indexOf(n) >= 0 && !d.sn) ? Math.max(1, Math.min(500, parseInt(src.jumlah, 10) || 1)) : 1;
  let no = AUTO_NO.indexOf(n) >= 0 ? colVals(n, 'no').reduce((m, x) => Math.max(m, N(x.v)), 0) : 0;
  const out = [];
  for (let i = 0; i < qty; i++) { const o = Object.assign({}, d); if (AUTO_NO.indexOf(n) >= 0) o.no = ++no; out.push(o); }
  addRows(n, out);
  return { ok: true, count: qty };
}
function find(n, r, sg) {
  const x = rowAt(n, parseInt(r, 10));
  if (!x || x._s !== sg) throw new Error('Data di spreadsheet sudah berubah atau dihapus. Muat ulang halaman lalu coba lagi.');
  return x;
}

/** Impor massal dari web (import.php). Data sudah divalidasi dan di-dedup oleh PHP; di sini hanya ditulis.
 *  mode 'upsert' (Data_Barang): material + valtype yang sudah ada hanya diperbarui Stok Awalnya. */
function importRows(q) {
  const n = q.sheet, list = q.rows || [];
  if (!S[n]) throw new Error('Sheet tidak dikenal');
  if (q.mode === 'upsert' && n === 'Data_Barang') {
    const s = sh(n), m = cmap(n, s), idx = {};
    rows(n).forEach(r => { idx[mkey(r.nama) + '|' + mkey(r.valtype)] = r; });
    const baru = []; let ubah = 0;
    list.forEach(o => {
      const r = idx[mkey(o.nama) + '|' + mkey(o.valtype)];
      if (!r) { baru.push(o); return; }
      if (String(r.awal) !== String(o.awal)) { s.getRange(r._r, m.awal + 1).setValue(Number(o.awal)); ubah++; }
      if (!r.ket && o.ket) s.getRange(r._r, m.ket + 1).setValue(o.ket);
    });
    addRows(n, baru);
    return { ok: true, baru: baru.length, ubah: ubah };
  }
  addRows(n, list);
  return { ok: true, baru: list.length, ubah: 0 };
}

// ============================================================
// SINKRONISASI DARI SPREADSHEET SUMBER (link berbeda)
// Hanya MEMBACA spreadsheet sumber (tidak pernah mengubahnya) dan hanya MENAMBAH baris yang belum ada di sheet web.
// Tidak ada baris web yang dihapus. Baris yang sudah ada dilewati (cocok menurut SN; tanpa SN menurut seluruh isi baris).
// Akun pemilik script harus punya akses (minimal Lihat) ke spreadsheet sumber: tidak perlu dipublikasikan ke publik.
// ============================================================
function srcId(link) {
  const t = String(link || '').trim();
  if (/\/spreadsheets\/d\/e\//.test(t)) throw new Error('Link "Publikasikan ke web" tidak dapat dipakai. Gunakan link biasa dari tombol Bagikan (berisi /spreadsheets/d/ID/edit).');
  const m = t.match(/\/spreadsheets\/d\/([A-Za-z0-9_-]{20,})/) || (/^[A-Za-z0-9_-]{25,}$/.test(t) ? [t, t] : null);
  if (!m) throw new Error('Link Google Sheets tidak valid.');
  return m[1];
}
function srcSheet(book, tab, link) {
  tab = String(tab || '').trim();
  const all = book.getSheets();
  if (tab) {
    const s = book.getSheetByName(tab);
    if (!s) throw new Error('Tab "' + tab + '" tidak ditemukan di spreadsheet sumber. Tab yang ada: ' + all.map(x => x.getName()).join(', '));
    return s;
  }
  const g = String(link || '').match(/[#&?]gid=(\d+)/);
  if (g) { const s = all.find(x => String(x.getSheetId()) === g[1]); if (s) return s; }
  return all.find(x => !x.isSheetHidden()) || all[0];
}
/** Tanggal dari teks: yyyy-mm-dd, dd/mm/yyyy (format Indonesia; m/d/yyyy bila bagian pertama > 12 tidak mungkin, bagian kedua > 12), atau teks seperti "Wednesday, March 11, 2026". */
function tglTeks(v) {
  const t = String(v || '').trim(); if (!t) return '';
  let m = t.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
  if (m) return m[1] + '-' + p2(+m[2]) + '-' + p2(+m[3]);
  m = t.match(/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/);
  if (m) {
    let d = +m[1], mo = +m[2]; if (mo > 12 && d <= 12) { const x = d; d = mo; mo = x; }
    if (mo < 1 || mo > 12 || d < 1 || d > 31) return '';
    return m[3] + '-' + p2(mo) + '-' + p2(d);
  }
  const dt = new Date(t);
  if (!isNaN(dt) && dt.getFullYear() > 2000 && dt.getFullYear() < 2100) return dt.getFullYear() + '-' + p2(dt.getMonth() + 1) + '-' + p2(dt.getDate());
  return '';
}
function syncRows(q) {
  const n = q.sheet;
  if (!S[n] || n === 'Master_SN' || n === 'Data_Barang') throw new Error('Sheet tujuan tidak dapat disinkronkan');
  const id = srcId(q.source), dry = !!q.dry, upd = !!q.update;
  let book;
  try { book = SpreadsheetApp.openById(id); }
  catch (e) { throw new Error('Spreadsheet sumber tidak dapat dibuka. Pastikan link benar, akun pemilik script punya akses ke spreadsheet itu, dan berformat Google Sheets (file .xlsx di Drive harus dibuka lalu Simpan sebagai Google Spreadsheet).'); }
  const src = srcSheet(book, q.tab, q.source);
  if (book.getId() === ss().getId() && src.getName() === n) throw new Error('Sumber sama dengan sheet tujuan. Pilih spreadsheet / tab sumber yang berbeda.');
  const lr = src.getLastRow(), lc = src.getLastColumn();
  if (lr < 2 || lc < 1) throw new Error('Tab sumber "' + src.getName() + '" kosong.');
  const vals = src.getRange(1, 1, lr, lc).getValues(), disp = src.getRange(1, 1, lr, lc).getDisplayValues();

  // cari baris judul (10 baris pertama) dan petakan kolom menurut judul/alias
  const keys = S[n].map(c => c[0]).filter(k => k !== 'no');
  let hr = -1, map = null, best = 0;
  for (let i = 0; i < Math.min(10, lr); i++) {
    const hd = vals[i].map(nz), m = {}; let c = 0;
    S[n].forEach(col => { const al = col.slice(1).map(nz), j = hd.findIndex(h => h && al.indexOf(h) >= 0); if (j >= 0 && col[0] !== 'no') { m[col[0]] = j; c++; } });
    if (c > best) { best = c; hr = i; map = m; }
  }
  if (hr < 0 || best < 2 || map.tgl === undefined) throw new Error('Baris judul kolom tidak ditemukan di tab "' + src.getName() + '". Judul yang dikenali untuk sheet ini: ' + S[n].filter(c => c[0] !== 'no').map(c => c[1]).join(', ') + '.');
  const hilang = (REQ[n] || []).filter(r => map[r[0]] === undefined && !(n === 'Barang_Masuk' && r[0] === 'valtype'));
  if (hilang.length) throw new Error('Kolom wajib tidak ditemukan di sumber: ' + hilang.map(r => r[1]).join(', ') + ' (judul terbaca: ' + disp[hr].filter(String).join(' | ') + ').');

  // data yang sudah ada di web
  const ex = rows(n), grup = n === 'Barang_Masuk' ? ['Barang_Masuk'] : OUT, unikSN = SNFREE.indexOf(n) < 0;
  const fp = o => keys.map(k => mkey(o[k])).join('|');
  const snAda = {}, fpAda = {}, snRow = {};
  if (unikSN) grup.forEach(g => { colVals(g, 'sn').forEach(x => snLines(x.v).forEach(u => { if (!snAda[u]) snAda[u] = g; if (g === n && !snRow[u]) snRow[u] = x.r; })); });
  ex.forEach(r => { if (!unikSN || !snKey(r.sn)) fpAda[fp(r)] = (fpAda[fp(r)] || 0) + 1; });
  const exBy = {}; ex.forEach(r => { exBy[r._r] = r; });
  let mv = null;
  if (n === 'Barang_Masuk') { mv = {}; rows('Data_Barang').forEach(x => { if (x.valtype && mkey(x.nama)) mv[mkey(x.nama)] = x.valtype; }); }

  const baru = [], alasan = []; const st = { total: 0, ada: 0, ganda: 0, lewat: 0, ubah: 0 };
  const catat = (r, t) => { st.lewat++; if (alasan.length < 12) alasan.push('Baris ' + r + ': ' + t); };
  const seenSN = {}, seenFP = {};
  for (let i = hr + 1; i < lr; i++) {
    const o = {}; let any = false;
    keys.forEach(k => {
      const j = map[k]; let v = '';
      if (j !== undefined) {
        if (k === 'tgl') v = vals[i][j] instanceof Date ? ymd(vals[i][j]) : tglTeks(disp[i][j]);
        else if (TEXTK.indexOf(k) >= 0) v = String(disp[i][j] == null ? '' : disp[i][j]).trim();
        else v = cell(vals[i][j]);
      }
      if (k !== 'tgl' && v !== '') any = true; o[k] = v;
    });
    if (!any) continue;   // baris kosong
    st.total++;
    const r = i + 1;
    if (!o.tgl) { catat(r, 'tanggal kosong / tidak terbaca ("' + String(disp[i][map.tgl]).trim() + '")'); continue; }
    if (n === 'Barang_Masuk') {
      o.valtype = String(o.valtype || '').toUpperCase().trim();
      if (!o.valtype && o.nama) o.valtype = mv[mkey(o.nama)] || '';
      if (VALTYPE.indexOf(o.valtype) < 0) { catat(r, 'Valtype "' + o.valtype + '" tidak valid (pilihan: ' + VALTYPE.join(', ') + ')'); continue; }
    }
    const miss = (REQ[n] || []).filter(x => !o[x[0]]); if (miss.length) { catat(r, miss.map(x => x[1]).join(', ') + ' kosong'); continue; }
    const sns = unikSN ? o.sn.split(/[\r\n,;]+/).map(x => x.trim()).filter(Boolean) : [];   // satu SN = satu baris
    const unit = sns.length ? sns : [''];
    unit.forEach(sn => {
      const c = Object.assign({}, o); if (unikSN) c.sn = sn;
      if (unikSN && sn) {
        const u = snKey(sn);
        if (seenSN[u]) { st.ganda++; return; } seenSN[u] = 1;
        if (snAda[u]) {
          st.ada++;
          if (upd && snAda[u] === n && snRow[u] && exBy[snRow[u]]) {
            const e = exBy[snRow[u]], ch = {};
            keys.forEach(k => { if (k !== 'sn' && c[k] !== '' && c[k] !== e[k] && !(k === 'valtype' && VALTYPE.indexOf(c[k]) < 0)) ch[k] = c[k]; });
            if (Object.keys(ch).length) { st.ubah++; if (!dry) updRow(n, e._r, ch); }
          }
          return;
        }
        baru.push(c); return;
      }
      const f = fp(c); seenFP[f] = (seenFP[f] || 0) + 1;
      if (seenFP[f] <= (fpAda[f] || 0)) { st.ada++; return; }
      baru.push(c);
    });
  }
  if (!dry && baru.length) {
    let no = AUTO_NO.indexOf(n) >= 0 ? colVals(n, 'no').reduce((m, x) => Math.max(m, N(x.v)), 0) : 0;
    baru.forEach(o => {
      if (AUTO_NO.indexOf(n) >= 0) o.no = ++no;
      Object.keys(o).forEach(k => { if (TEXTK.indexOf(k) < 0 && k !== 'tgl' && typeof o[k] === 'string' && o[k].charAt(0) === '=') o[k] = "'" + o[k]; });   // cegah isi sumber dibaca sebagai rumus
    });
    addRows(n, baru);
  }
  return { ok: true, dry: dry, tab: src.getName(), header: hr + 1, total: st.total, baru: baru.length, ada: st.ada, ganda: st.ganda, dilewati: st.lewat, diperbarui: st.ubah, alasan: alasan };
}

/** Jalankan SEKALI dari editor: membuat sheet yang belum ada (hanya judul kolom). Data yang sudah ada tidak diubah. */
function setup() {
  ss().rename('inventory');
  if (!ss().getSpreadsheetTimeZone()) ss().setSpreadsheetTimeZone('Asia/Jakarta');   // zona waktu kosong pada spreadsheet hasil unggah .xlsx
  Object.keys(S).forEach(sh);
  const ex = ss().getSheetByName('Sheet1');
  if (ex && ss().getSheets().length > 1 && ex.getLastRow() === 0) ss().deleteSheet(ex);
}

/** OPSIONAL, jalankan SEKALI bila Barang_Keluar Anda masih berformat lama (ada kolom Mitra / Bidang / Lokasi):
 *  baris yang berisi Bidang dipindahkan ke Pengambilan_Ritel. Baris lain tetap di Barang_Keluar. */
function migrasi() {
  setup();
  const s = ss().getSheetByName('Barang_Keluar'), lc = s.getLastColumn(), lr = s.getLastRow();
  if (lr < 2) return 'Tidak ada data';
  const hd = s.getRange(1, 1, 1, lc).getValues()[0].map(nz), v = s.getRange(2, 1, lr - 1, lc).getValues();
  const ix = al => hd.findIndex(h => h && al.indexOf(h) >= 0);
  const iT = ix(['tanggal']), iP = ix(['yangmengambil', 'pengambil']), iM = ix(['mitra']), iB = ix(['bidang']), iN = ix(['nama']), iMat = ix(['material', 'namamaterial']), iS = ix(['sn', 'snqty']), iL = ix(['lokasi']), iK = ix(['keterangan', 'ket']);
  if (iB < 0) return 'Format lama (kolom Bidang) tidak ditemukan';
  const g = (r, i) => i < 0 ? '' : cell(r[i]);
  const list = [], del = [];
  let no = colVals('Pengambilan_Ritel', 'no').reduce((m, x) => Math.max(m, N(x.v)), 0);
  v.forEach((r, i) => {
    if (!g(r, iB)) return;
    list.push({ no: ++no, tgl: g(r, iT), mitra: g(r, iM), bidang: g(r, iB), nama: g(r, iN) || g(r, iP), material: g(r, iMat), sn: g(r, iS), lokasi: g(r, iL), ket: g(r, iK) });
    del.push(i + 2);
  });
  addRows('Pengambilan_Ritel', list);
  del.reverse().forEach(r => s.deleteRow(r));
  return list.length + ' baris dipindahkan ke Pengambilan_Ritel';
}

/** BACKUP OTOMATIS (opsional). Jalankan pasangBackupHarian SEKALI dari editor: setiap hari sekitar pukul 02.00
 *  spreadsheet disalin ke folder Drive "Backup Gudang Inventory"; 30 salinan terakhir disimpan, yang lebih lama dihapus. */
function backupSpreadsheet() {
  const NAMA = 'Backup Gudang Inventory', SIMPAN = 30;
  const it = DriveApp.getFoldersByName(NAMA);
  const folder = it.hasNext() ? it.next() : DriveApp.createFolder(NAMA);
  const stamp = Utilities.formatDate(new Date(), ss().getSpreadsheetTimeZone() || 'Asia/Jakarta', 'yyyy-MM-dd_HHmm');
  DriveApp.getFileById(ss().getId()).makeCopy('inventory_backup_' + stamp, folder);
  const files = [], fi = folder.getFiles();
  while (fi.hasNext()) { const f = fi.next(); if (f.getName().indexOf('inventory_backup_') === 0) files.push(f); }
  files.sort((a, b) => b.getDateCreated() - a.getDateCreated());
  files.slice(SIMPAN).forEach(f => f.setTrashed(true));
  return 'Backup dibuat: inventory_backup_' + stamp;
}
function pasangBackupHarian() {
  ScriptApp.getProjectTriggers().forEach(t => { if (t.getHandlerFunction() === 'backupSpreadsheet') ScriptApp.deleteTrigger(t); });
  ScriptApp.newTrigger('backupSpreadsheet').timeBased().everyDays(1).atHour(2).create();
  return 'Backup harian terpasang.';
}
