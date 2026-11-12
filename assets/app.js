(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };

  // ---- keamanan: token CSRF otomatis ditambahkan ke setiap formulir POST
  (function () {
    var m = $('meta[name=csrf]'); if (!m) return;
    document.addEventListener('submit', function (e) {
      var f = e.target; if (!f || !f.method || f.method.toLowerCase() !== 'post' || $('input[name=_csrf]', f)) return;
      var i = document.createElement('input'); i.type = 'hidden'; i.name = '_csrf'; i.value = m.content; f.appendChild(i);
    }, true);
  })();

  // ---- angka kartu
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) $$('.cnt').forEach(function (el) {
    var v = +el.dataset.v || 0, t0 = performance.now();
    (function f(t) { var k = Math.min(1, (t - t0) / 450); el.textContent = Math.round(v * (1 - Math.pow(1 - k, 3))).toLocaleString('id-ID'); if (k < 1) requestAnimationFrame(f); })(t0);
  });
  var fl = $('.fl'); if (fl) setTimeout(function () { fl.style.opacity = 0; setTimeout(function () { fl.remove(); }, 500); }, 7000);

  // ---- tanpa daftar saran / riwayat isian browser
  $$('input').forEach(function (i) { i.setAttribute('autocomplete', 'off'); });
  document.addEventListener('focusin', function (e) { if (e.target.tagName === 'INPUT') e.target.setAttribute('autocomplete', 'off'); });

  // ---- dialog
  document.addEventListener('click', function (e) { if (e.target.tagName === 'DIALOG') e.target.close(); });
  var auto = $('dialog[data-open]'); if (auto) auto.showModal();
  $$('.sb a').forEach(function (a) { a.addEventListener('click', function () { $('#sb').classList.remove('open'); }); });
  window.addEventListener('pageshow', function () { $$('button:disabled').forEach(function (b) { b.disabled = false; }); });
  document.addEventListener('submit', function (e) { var f = e.target; if (f.method === 'post') { var b = $('button:not([type=button])', f); if (b) setTimeout(function () { b.disabled = true; }, 0); } });

  // ---- detail baris (tombol mata): isi dari atribut data-d pada baris tabel
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-det]'); if (!b) return;
    var tr = b.closest('tr'), d; try { d = JSON.parse(tr.dataset.d || '{}'); } catch (x) { d = {}; }
    var dl = $('#fdl'); dl.innerHTML = '';
    Object.keys(d).forEach(function (k) { var dt = document.createElement('dt'), dd = document.createElement('dd'); dt.textContent = k; dd.textContent = (d[k] === '' || d[k] === null) ? '-' : d[k]; dl.appendChild(dt); dl.appendChild(dd); });
    $('#fd').showModal();
  });

  // ---- tabel: cari, filter, urut, halaman tanpa memuat ulang seluruh halaman (Fetch API)
  var muat = function (u, push) {
    var t = $('#tbl'); if (!t) { location.href = u; return; }
    t.classList.add('loading');
    fetch(u + (u.indexOf('?') >= 0 ? '&' : '?') + 'ajax=1', { headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { if (!r.ok) throw 0; return r.text(); }).then(function (h) {
      var n = document.createElement('div'); n.innerHTML = h; var nt = n.firstElementChild;
      if (!nt || nt.id !== 'tbl') { location.href = u; return; }
      t.replaceWith(nt); if (push !== false) history.pushState({}, '', u);
    }).catch(function () { location.href = u; });
  };
  document.addEventListener('click', function (e) {
    var a = e.target.closest('#tbl .pg a, #tbl th a'); if (!a) return;
    e.preventDefault(); muat(a.href);
  });
  document.addEventListener('submit', function (e) {
    var f = e.target; if (!f.closest || !f.closest('#tbl') || f.method !== 'get') return;
    e.preventDefault(); var q = new URLSearchParams(new FormData(f)); q.delete('ajax');
    muat(location.pathname + '?' + q.toString());
  });
  window.addEventListener('popstate', function () { location.reload(); });

  // ---- formulir: jumlah hanya dipakai bila SN kosong; Valtype otomatis dari Data Material
  $$('dialog form').forEach(function (f) {
    var sn = $('input[name=sn]', f), jm = $('input[name=jumlah]', f);
    if (sn && jm) { var t = function () { var ada = sn.value.trim() !== ''; jm.disabled = ada; if (ada) jm.value = 1; }; sn.addEventListener('input', t); t(); }
    var sh = $('input[name=sheet]', f), nm = $('input[name=nama]', f), vt = $('select[name=valtype]', f);
    if (window.MV && sh && sh.value === 'Barang_Masuk' && nm && vt) {
      var cek = function () { var k = nm.value.toUpperCase().replace(/\s+/g, ' ').trim(), v = window.MV[k]; if (v) { if (![].some.call(vt.options, function (o) { return o.value === v; })) vt.add(new Option(v, v)); vt.value = v; } };
      nm.addEventListener('change', cek); nm.addEventListener('input', cek);
    }
  });

  // ---- grafik (Chart.js), dibuat setelah pustaka selesai dimuat
  function grafik() {
    var C = window.CH, D = '#035B71', T = '#00A2B9', Y = '#f2d600', R = '#FF0000', G = '#9aa7ad';
    Chart.defaults.font.family = 'Poppins, system-ui, sans-serif'; Chart.defaults.maintainAspectRatio = false; Chart.defaults.animation.duration = 300;
    var id = function (v) { return Number(v).toLocaleString('id-ID'); };
    var ints = { beginAtZero: true, ticks: { precision: 0, callback: id } };
    var grup = function (id, S) {
      new Chart($(id), { type: 'bar', data: { labels: S.l, datasets: [
        { label: 'Masuk', data: S.m, backgroundColor: D, borderRadius: 3 }, { label: 'Keluar', data: S.k, backgroundColor: T, borderRadius: 3 },
        { label: 'Ritel', data: S.r, backgroundColor: Y, borderRadius: 3 }, { label: 'Lainnya', data: S.o, backgroundColor: G, borderRadius: 3 }] },
        options: { scales: { y: ints, x: { grid: { display: false } } }, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } } });
    };
    grup('#c1', C.H); grup('#c2', C.B); grup('#c3', C.Y);
    new Chart($('#c4'), { type: 'line', data: { labels: C.G.l, datasets: [{ label: 'Stok akhir', data: C.G.stok, borderColor: D, backgroundColor: 'rgba(0,162,185,.18)', fill: true, tension: .35, borderWidth: 3, pointRadius: C.G.l.length > 15 ? 2 : 4, pointBackgroundColor: T, pointBorderColor: T }] },
      options: { scales: { y: { ticks: { callback: id }, grid: { color: 'rgba(3,91,113,.12)' } }, x: { grid: { color: 'rgba(3,91,113,.08)' } } }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return 'Stok akhir: ' + id(c.parsed.y); } } } } } });
    if (C.vt.length) new Chart($('#c5'), { type: 'doughnut', data: { labels: C.vt, datasets: [{ data: C.vtv, backgroundColor: [D, T, Y, R, '#7fb6c2', G], borderWidth: 2 }] }, options: { cutout: '60%', plugins: { legend: { position: 'bottom' } } } });
    if (C.mt.length) new Chart($('#c6'), { type: 'bar', data: { labels: C.mt, datasets: [{ label: 'Pengambilan', data: C.mtv, backgroundColor: T, borderRadius: 3 }] }, options: { indexAxis: 'y', scales: { x: ints }, plugins: { legend: { display: false } } } });
  }
  if (window.CH) { var tries = 0; (function tunggu() { if (window.Chart) grafik(); else if (tries++ < 100) setTimeout(tunggu, 100); })(); }

  // ---- sinkron dengan spreadsheet: cek ringan tiap 45 detik; halaman dimuat ulang HANYA bila isi spreadsheet berubah
  var sibuk = function () { return $('dialog[open]') || /INPUT|TEXTAREA|SELECT/.test((document.activeElement || {}).tagName || ''); };
  if (window.VER && window.SH) setInterval(function () {
    if (document.hidden) return;
    fetch('sync.php?s=' + encodeURIComponent(window.SH), { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ver || j.ver === window.VER) return;
      if (!sibuk()) { location.reload(); return; }
      if ($('.toast')) return;
      var t = document.createElement('div'); t.className = 'toast'; t.innerHTML = '<i class="bi bi-arrow-repeat"></i> Data spreadsheet berubah. <a href="">Muat ulang</a>'; document.body.appendChild(t);
    }).catch(function () {});
  }, 45000);
})();
