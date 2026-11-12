"""python tools/buat_inventory_xlsx.py hasil.json inventory.xlsx  -> spreadsheet inventory berisi data hasil normalisasi Excel."""
import json, sys, datetime
from openpyxl import Workbook
from openpyxl.styles import PatternFill, Font, Alignment
from openpyxl.utils import get_column_letter as L

SKEMA = {  # sheet: [(kunci, judul, lebar, jenis)]  jenis: t=teks, d=tanggal, n=angka
 'Data_Barang': [('nama','Nama Material',46,'t'),('valtype','Valtype',14,'t'),('satuan','Satuan',9,'t'),('awal','Stok Awal',11,'n'),('min','Stok Minimum',14,'n'),('ket','Keterangan',26,'t')],
 'Barang_Masuk': [('no','No',7,'n'),('tgl','Tanggal',26,'d'),('nama','Nama Material',32,'t'),('valtype','Valtype',14,'t'),('sn','SN',28,'t'),('ket','Keterangan',24,'t'),('divisi','Divisi',14,'t')],
 'Barang_Keluar': [('tgl','Tanggal',26,'d'),('pengambil','Yang Mengambil',24,'t'),('material','Material',30,'t'),('sn','SN',26,'t'),('ket','Keterangan',32,'t'),('mitra','Mitra',16,'t')],
 'Pengambilan_Ritel': [('no','No',7,'n'),('tgl','Tanggal',26,'d'),('mitra','Mitra',14,'t'),('bidang','Bidang',14,'t'),('nama','Nama',16,'t'),('material','Material',26,'t'),('sn','SN',26,'t'),('lokasi','Lokasi',26,'t'),('ket','Keterangan',28,'t')],
 'Pengambilan_Ggn': [('no','No',7,'n'),('tgl','Tanggal',26,'d'),('mitra','Mitra',14,'t'),('bidang','Bidang',14,'t'),('nama','Nama',16,'t'),('material','Material',26,'t'),('sn','SN',26,'t'),('lokasi','Lokasi',26,'t'),('ket','Keterangan',28,'t')],
 'Pengambilan_ICRM': [('no','No',7,'n'),('tgl','Tanggal',26,'d'),('wo','WO',16,'t'),('mitra','Mitra',12,'t'),('nama','Nama',16,'t'),('lokasi','Lokasi',34,'t'),('perangkat','Perangkat',26,'t'),('sn','SN',26,'t')],
 'Pengambilan_Aktivasi': [('no','No',7,'n'),('tgl','Tanggal',26,'d'),('pa','PA',18,'t'),('mitra','Mitra',12,'t'),('bidang','Bidang',14,'t'),('sn','SN',60,'t')],
 'Pengambilan_HAR': [('no','No',7,'n'),('tgl','Tanggal',26,'d'),('pengambil','Yang Mengambil',18,'t'),('bidang','Bidang',12,'t'),('nama','Nama',18,'t'),('material','Material',26,'t'),('sn','SN',26,'t'),('lokasi','Lokasi',22,'t'),('ket','Keterangan',28,'t')],
 'Pengambilan_Pembangunan': [('no','No',7,'n'),('tgl','Tanggal',26,'d'),('bidang','Bidang',14,'t'),('nama','Nama',16,'t'),('material','Material',22,'t'),('sn','SN',22,'t'),('lokasi','Lokasi',30,'t'),('ket','Keterangan',22,'t')],
 'Master_SN': [('kode','Kode Material',16,'t'),('nama','Deskripsi Material',46,'t'),('valtype','Valtype',14,'t'),('plnt','Plnt',8,'t'),('sloc','SLoc',8,'t'),('sn','Serial Number',24,'t'),('ket','Keterangan',18,'t'),('lokasi','Lokasi',20,'t')],
}
data = json.load(open(sys.argv[1]))['data'] if len(sys.argv) > 1 else {}
# sama dengan import web: baris ber-SN yang persis sama hanya dimasukkan sekali; kolom No disusun ulang berurutan
URUT = ['Barang_Masuk', 'Pengambilan_Ritel', 'Pengambilan_Ggn', 'Pengambilan_HAR', 'Pengambilan_Pembangunan']
for nm, rows in list(data.items()):
    if nm in ('Data_Barang', 'Master_SN'): continue
    seen, out = set(), []
    for r in rows:
        fp = '|'.join(' '.join(str(r.get(k, '')).split()).upper() for k in r if k != 'no')
        if str(r.get('sn', '')).strip():
            if fp in seen: continue
            seen.add(fp)
        out.append(r)
    if nm in URUT:
        for i, r in enumerate(out, 1): r['no'] = i
    data[nm] = out
wb = Workbook(); wb.remove(wb.active)
hf = PatternFill('solid', fgColor='035B71'); hfont = Font(bold=True, color='FFFFFF')
for nm, cols in SKEMA.items():
    ws = wb.create_sheet(nm)
    for i, (k, jd, w, j) in enumerate(cols, 1):
        c = ws.cell(1, i, jd); c.fill = hf; c.font = hfont; c.alignment = Alignment(vertical='center'); ws.column_dimensions[L(i)].width = w
    ws.freeze_panes = 'A2'; ws.auto_filter.ref = f"A1:{L(len(cols))}1"
    for r in data.get(nm, []):
        row = []
        for k, jd, w, j in cols:
            v = r.get(k, '')
            if j == 'd': v = datetime.datetime.strptime(v, '%Y-%m-%d') if v else None
            elif j == 'n': v = int(v) if str(v).strip().lstrip('-').isdigit() else (None if v == '' else v)
            else: v = v if v != '' else None
            row.append(v)
        ws.append(row)
    for i, (k, jd, w, j) in enumerate(cols, 1):
        if j == 'd':
            for c in ws[L(i)][1:]: c.number_format = 'dddd, mmmm d, yyyy'
        elif j == 't':
            for c in ws[L(i)][1:]: c.number_format = '@'
    n = len(data.get(nm, []))
    if j == 't' or True:
        # kolom teks diberi format "@" juga untuk baris kosong berikutnya agar SN baru tidak berubah menjadi angka
        for i, (k, jd, w, j) in enumerate(cols, 1):
            if k in ('sn','wo','pa','kode'):
                for r in range(n + 2, n + 502): ws.cell(r, i).number_format = '@'
            if j == 'd':
                for r in range(n + 2, n + 502): ws.cell(r, i).number_format = 'dddd, mmmm d, yyyy'
wb.save(sys.argv[2] if len(sys.argv) > 2 else 'inventory.xlsx')
print({k: len(v) for k, v in data.items()})
