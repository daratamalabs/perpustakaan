/* Kiosk check-in */
(function () {
  'use strict';
  var root = document.getElementById('kiosk');
  if (!root) return;
  var scanUrl = root.dataset.scanUrl;
  var result = document.getElementById('result');
  var vf = document.getElementById('viewfinder');
  var camText = document.getElementById('camText');
  var busy = false;
  var clearTimer = null;
  var audio = null;

  var ICONS = {
    ok: '<path d="M20 6L9 17l-5-5"/>',
    out: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    warn: '<path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 6v6l4 2"/>',
    bad: '<path d="M18 6L6 18M6 6l12 12"/>'
  };

  function beep(ok) {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      var o = audio.createOscillator(); var g = audio.createGain();
      o.connect(g); g.connect(audio.destination);
      o.frequency.value = ok ? 1250 : 330;
      g.gain.setValueAtTime(0.18, audio.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + (ok ? 0.18 : 0.45));
      o.start(); o.stop(audio.currentTime + (ok ? 0.2 : 0.5));
    } catch (e) { /* ignore */ }
  }

  function show(d) {
    var tone = d.tone || (d.ok ? 'ok' : 'bad');
    result.className = 'result pop ' + tone;
    result.hidden = false;
    document.getElementById('resIcon').innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[tone] || ICONS.ok) + '</svg>';
    document.getElementById('resTitle').textContent = d.title || '';
    document.getElementById('resName').textContent = d.name || d.message || '';
    document.getElementById('resMeta').textContent = d.name ? [d.meta, d.message].filter(Boolean).join(' · ') : '';
    document.getElementById('resTime').textContent = d.time || '';
    beep(tone === 'ok' || tone === 'out');
    clearTimeout(clearTimer);
    clearTimer = setTimeout(function () { result.hidden = true; }, 9000);
  }

  function send(url, body) {
    if (busy) return;
    busy = true;
    api(url, { body: body }).then(function (d) {
      if (d && d.message && d.title === undefined) d.title = d.ok ? 'Berhasil' : 'Gagal';
      show(d);
      if (d && d.message && /Sesi berakhir/.test(d.message)) setTimeout(function () { location.reload(); }, 2500);
    }).finally(function () { setTimeout(function () { busy = false; }, 600); });
  }

  function onCode(code) { send(scanUrl, { code: code }); }

  var scanner = new Scanner({
    video: document.getElementById('cam'),
    onCode: onCode,
    onStatus: function (live, text) { vf.classList.toggle('is-live', live); camText.textContent = text; }
  });
  scanner.start();
  Scanner.wedge(onCode);
  document.getElementById('switchCam').addEventListener('click', function () { scanner.next(); });

  // Clock
  function tick() {
    var d = new Date();
    var el = document.querySelector('[data-clock]');
    if (el) el.textContent = String(d.getHours()).padStart(2, '0') + '.' + String(d.getMinutes()).padStart(2, '0');
  }
  setInterval(tick, 10000);

  // Keep the session alive and reload at midnight so the date stays correct.
  var startDay = new Date().getDate();
  setInterval(function () { if (new Date().getDate() !== startDay) location.reload(); }, 60000);

  if (root.dataset.initial) { try { show(JSON.parse(root.dataset.initial)); } catch (e) { /* ignore */ } }

  // Search modal
  var modal = document.getElementById('searchModal');
  var input = document.getElementById('searchInput');
  var list = document.getElementById('searchList');
  var hint = document.getElementById('searchHint');
  var t = null;
  function openM() { modal.hidden = false; input.value = ''; list.hidden = true; list.innerHTML = ''; setTimeout(function () { input.focus(); }, 50); }
  function closeM() { modal.hidden = true; }
  document.getElementById('openSearch').addEventListener('click', openM);
  document.getElementById('closeSearch').addEventListener('click', closeM);
  modal.addEventListener('click', function (e) { if (e.target === modal) closeM(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) closeM(); });
  input.addEventListener('input', function () {
    clearTimeout(t);
    var q = input.value.trim();
    if (q.length < 3) { list.hidden = true; hint.textContent = 'Ketik minimal 3 huruf.'; return; }
    t = setTimeout(function () {
      api(root.dataset.searchUrl + '?q=' + encodeURIComponent(q)).then(function (d) {
        var items = (d && d.items) || [];
        hint.textContent = items.length ? 'Pilih namamu.' : 'Tidak ditemukan. Hubungi petugas.';
        list.innerHTML = items.map(function (it) {
          return '<button type="button" data-id="' + it.id + '"><span class="avatar">' + escapeHtml(it.initials) + '</span><span><strong>' + escapeHtml(it.name) + '</strong><span class="sub">' + escapeHtml(it.meta) + '</span></span></button>';
        }).join('');
        list.hidden = !items.length;
      });
    }, 250);
  });
  list.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-id]');
    if (!b) return;
    closeM();
    send(root.dataset.checkinUrl, { id: b.dataset.id });
  });
})();
