/* Sirkulasi: pinjam, kembalikan, perpanjang dengan pemindai USB atau kamera */
(function () {
  'use strict';
  var root = document.getElementById('circ');
  if (!root) return;
  var urls = root.dataset;
  var $ = function (id) { return document.getElementById(id); };
  var esc = window.escapeHtml;
  var member = null;
  var copies = [];

  /* ---------- mode tabs ---------- */
  function setMode(mode) {
    root.querySelectorAll('[data-mode]').forEach(function (b) {
      var on = b.dataset.mode === mode;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    root.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== mode; });
    var focus = { pinjam: member ? 'copyInput' : 'memberInput', kembali: 'returnInput', perpanjang: 'extendInput' }[mode];
    setTimeout(function () { $(focus).focus(); }, 30);
  }
  root.querySelectorAll('[data-mode]').forEach(function (b) {
    b.addEventListener('click', function () { setMode(b.dataset.mode); });
  });

  function onEnter(input, fn) {
    input.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      var v = input.value.trim();
      if (v) fn(v);
    });
  }

  function alertBox(tone, html) {
    return '<div class="alert alert-' + tone + '">' + html + '</div>';
  }

  /* ---------- borrow ---------- */
  function renderMember() {
    var box = $('memberBox');
    if (!member) { box.innerHTML = ''; return; }
    var m = member;
    var loans = m.loans.map(function (l) {
      return '<li><span class="mono">' + esc(l.code) + '</span> · ' + esc(l.title) + ' · kembali ' + esc(l.due) + (l.late ? ' <b style="color:var(--bad)">terlambat</b>' : '') + '</li>';
    }).join('');
    box.innerHTML =
      '<div class="option-card" style="cursor:default;align-items:center;' + (m.reason ? 'border-color:#F5C9C3;background:#FFFBFA' : 'border-color:#D5E1F1;background:var(--blue-50)') + '">' +
        '<span class="avatar avatar-lg" style="background:var(--navy-900);color:#fff">' + esc(m.initials) + '</span>' +
        '<span class="grow"><strong>' + esc(m.name) + '</strong><span>' + esc(m.meta) + (m.no ? ' · ' + esc(m.no) : '') + '</span>' +
        '<span>Meminjam ' + m.count + ' dari maks. ' + m.max + ' buku' + (m.unpaid ? ' · denda ' + esc(m.unpaid_text) : '') + '</span></span>' +
        '<span class="chip chip-' + esc(m.tone) + '">' + esc(m.status) + '</span>' +
        '<button type="button" class="btn-icon" data-clear-member aria-label="Ganti member" title="Ganti member" style="display:inline-flex;align-items:center;justify-content:center">×</button>' +
      '</div>' +
      (m.reason ? alertBox('bad', '<span>' + esc(m.reason) + '</span>') : '') +
      (loans ? '<details class="small muted"><summary style="cursor:pointer;font-weight:600">Sedang dipinjam (' + m.loans.length + ')</summary><ul style="margin:6px 0 0;padding-left:18px;line-height:1.7">' + loans + '</ul></details>' : '');
    var blocked = !!m.reason;
    $('copyInput').disabled = blocked;
    root.querySelector('[data-camera="copyInput"]').disabled = blocked;
    if (!blocked) $('copyInput').focus();
    renderCopies();
  }

  function renderCopies() {
    var list = $('copyList');
    list.innerHTML = copies.map(function (c, i) {
      return '<div class="list-row" style="padding:10px 12px;border:1px solid var(--line);border-radius:12px">' +
        '<span class="cover" style="background:var(--blue-600)"></span>' +
        '<span class="grow"><strong>' + esc(c.title) + '</strong><span class="mono" style="font-size:12px">' + esc(c.code) + (c.shelf ? ' · Rak ' + esc(c.shelf) : '') + '</span>' +
        '<span>Kembali ' + esc(c.due || '') + (c.type === 'paket' ? ' · buku paket' : '') + '</span></span>' +
        '<button type="button" class="btn-icon" data-remove="' + i + '" aria-label="Hapus ' + esc(c.code) + ' dari daftar" style="display:inline-flex;align-items:center;justify-content:center">×</button></div>';
    }).join('');
    var btn = $('borrowBtn');
    btn.disabled = !member || !!member.reason || !copies.length;
    btn.textContent = copies.length ? 'Proses peminjaman · ' + copies.length + ' buku' : 'Proses peminjaman';
  }

  function lookupMember(q, pickedName) {
    api(urls.memberUrl + '?q=' + encodeURIComponent(q)).then(function (d) {
      if (!d.ok) { toast(d.message || 'Member tidak ditemukan.', 'bad'); $('memberInput').select(); return; }
      if (d.choices) { showChoices(d.choices); return; }
      member = d.member;
      member.unpaid_text = 'Rp' + Number(member.unpaid || 0).toLocaleString('id-ID');
      $('memberInput').value = pickedName || member.name;
      copies = [];
      renderMember();
    });
  }

  function showChoices(list) {
    $('memberBox').innerHTML = '<div class="pick-list">' + list.map(function (c) {
      return '<button type="button" data-pick="' + esc(c.q) + '" data-name="' + esc(c.name) + '"><span class="grow"><strong>' + esc(c.name) + '</strong><span class="sub">' + esc(c.meta) + '</span></span></button>';
    }).join('') + '</div>';
  }

  function addCopy(code) {
    code = code.toUpperCase();
    $('copyInput').value = '';
    if (!member) return;
    if (copies.some(function (c) { return c.code === code; })) { toast(code + ' sudah ada di daftar.', 'bad'); return; }
    api(urls.copyUrl + '?code=' + encodeURIComponent(code) + '&member=' + member.id).then(function (d) {
      if (!d.ok) { toast(d.message, 'bad'); return; }
      if (!d.copy.available) { toast(d.copy.code + ': ' + d.copy.error, 'bad'); return; }
      copies.push(d.copy);
      renderCopies();
    });
  }

  onEnter($('memberInput'), function (v) { lookupMember(v); });
  onEnter($('copyInput'), addCopy);

  root.addEventListener('click', function (e) {
    var t = e.target.closest('button');
    if (!t) return;
    if (t.dataset.pick) lookupMember(t.dataset.pick, t.dataset.name);
    if (t.hasAttribute('data-clear-member')) { member = null; copies = []; $('memberInput').value = ''; renderMember(); renderCopies(); $('copyInput').disabled = true; $('memberInput').focus(); }
    if (t.dataset.remove !== undefined) { copies.splice(+t.dataset.remove, 1); renderCopies(); }
  });

  $('borrowBtn').addEventListener('click', function () {
    var btn = this;
    if (!member || !copies.length) return;
    btn.disabled = true;
    var fd = new FormData();
    fd.append('member_id', member.id);
    copies.forEach(function (c) { fd.append('codes[]', c.code); });
    api(urls.borrowUrl, { body: fd }).then(function (d) {
      toast(d.message, d.ok ? 'ok' : 'bad');
      if (d.ok) {
        var list = (d.loans || []).map(function (l) { return '<li>' + esc(l.title) + ' — kembali <b>' + esc(l.due) + '</b></li>'; }).join('');
        $('memberBox').innerHTML = alertBox('ok', '<span><b>' + esc(d.message) + '</b><ul style="margin:6px 0 0;padding-left:18px">' + list + '</ul></span>');
        member = null; copies = [];
        $('memberInput').value = ''; $('copyInput').disabled = true;
        renderCopies();
        $('memberInput').focus();
        setTimeout(function () { refreshTable(); }, 600);
      } else {
        btn.disabled = false;
      }
    });
  });

  /* ---------- return ---------- */
  function lookupReturn(code) {
    $('returnInput').value = '';
    api(urls.copyUrl + '?code=' + encodeURIComponent(code)).then(function (d) {
      var box = $('returnBox');
      if (!d.ok) { box.innerHTML = alertBox('bad', '<span>' + esc(d.message) + '</span>'); return; }
      var c = d.copy;
      if (!c.loan) { box.innerHTML = alertBox('warn', '<span><b>' + esc(c.title) + '</b> (' + esc(c.code) + ') tidak sedang dipinjam.</span>'); return; }
      var l = c.loan;
      var fine = l.fine > 0;
      box.innerHTML =
        '<div class="card card-pad stack" style="padding:18px;gap:12px">' +
          '<div><strong style="font-size:16px">' + esc(c.title) + '</strong><div class="muted small mono">' + esc(c.code) + '</div></div>' +
          '<dl class="dl"><dt>Peminjam</dt><dd>' + esc(l.name) + '</dd><dt>Jatuh tempo</dt><dd>' + esc(l.due) + '</dd>' +
          '<dt>Keterlambatan</dt><dd style="color:' + (l.late ? 'var(--bad)' : 'var(--ok)') + '">' + (l.late ? l.late + ' hari' : 'Tepat waktu') + '</dd>' +
          (fine ? '<dt>Denda</dt><dd style="color:var(--bad)">Rp' + Number(l.fine).toLocaleString('id-ID') + '</dd>' : '') + '</dl>' +
          (fine ? '<label class="check"><input type="checkbox" id="finePaid"> Denda dibayar sekarang</label>' : '') +
          '<button type="button" class="btn btn-block" id="doReturn" data-code="' + esc(c.code) + '">' + 'Terima pengembalian</button>' +
        '</div>';
      $('doReturn').focus();
    });
  }
  onEnter($('returnInput'), lookupReturn);

  document.addEventListener('click', function (e) {
    var b = e.target.closest('#doReturn');
    if (!b) return;
    b.disabled = true;
    var paid = $('finePaid') && $('finePaid').checked ? '1' : '';
    api(urls.returnUrl, { body: { code: b.dataset.code, paid: paid } }).then(function (d) {
      toast(d.message, d.ok ? 'ok' : 'bad');
      $('returnBox').innerHTML = alertBox(d.ok ? (d.fine > 0 && !paid ? 'warn' : 'ok') : 'bad', '<span>' + esc(d.message) + '</span>');
      $('returnInput').focus();
      if (d.ok) setTimeout(refreshTable, 600);
    });
  });

  /* ---------- extend ---------- */
  function lookupExtend(code) {
    $('extendInput').value = '';
    api(urls.copyUrl + '?code=' + encodeURIComponent(code)).then(function (d) {
      var box = $('extendBox');
      if (!d.ok) { box.innerHTML = alertBox('bad', '<span>' + esc(d.message) + '</span>'); return; }
      var c = d.copy;
      if (!c.loan) { box.innerHTML = alertBox('warn', '<span><b>' + esc(c.title) + '</b> tidak sedang dipinjam.</span>'); return; }
      box.innerHTML =
        '<div class="card card-pad stack" style="padding:18px;gap:12px">' +
          '<div><strong style="font-size:16px">' + esc(c.title) + '</strong><div class="muted small">' + esc(c.loan.name) + ' · kembali ' + esc(c.loan.due) + (c.loan.extended ? ' · sudah diperpanjang ' + c.loan.extended + '×' : '') + '</div></div>' +
          '<button type="button" class="btn btn-block" id="doExtend" data-id="' + c.loan.id + '">Perpanjang</button>' +
        '</div>';
      $('doExtend').focus();
    });
  }
  onEnter($('extendInput'), lookupExtend);

  document.addEventListener('click', function (e) {
    var b = e.target.closest('#doExtend');
    if (!b) return;
    b.disabled = true;
    api(urls.extendUrl.replace('__ID__', b.dataset.id), { body: {} }).then(function (d) {
      toast(d.message, d.ok ? 'ok' : 'bad');
      $('extendBox').innerHTML = alertBox(d.ok ? 'ok' : 'bad', '<span>' + esc(d.message) + '</span>');
      $('extendInput').focus();
      if (d.ok) setTimeout(refreshTable, 600);
    });
  });

  /* "Kembalikan" button in the table */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-return-code]');
    if (!b) return;
    setMode('kembali');
    lookupReturn(b.dataset.returnCode);
    root.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  /* Reload the loans table without losing the form state. */
  function refreshTable() {
    fetch(location.href, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var fresh = doc.querySelector('section.card.wide');
      var cur = document.querySelector('section.card.wide');
      if (fresh && cur) cur.replaceWith(fresh);
    });
  }

  /* ---------- camera ---------- */
  var modal = $('camModal');
  var scanner = null;
  var target = null;
  function closeCam() { modal.hidden = true; if (scanner) scanner.stop(); }
  root.querySelectorAll('[data-camera]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (typeof Scanner !== 'function') return;
      target = $(b.dataset.camera);
      modal.hidden = false;
      if (!scanner) {
        scanner = new Scanner({
          video: modal.querySelector('video'),
          formats: ['qr_code', 'code_128'],
          onCode: function (code) {
            closeCam();
            target.value = code;
            target.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
          },
          onStatus: function (live, msg) { $('camView').classList.toggle('is-live', live); $('camStatus').textContent = msg; }
        });
      }
      scanner.start();
    });
  });
  $('camClose').addEventListener('click', closeCam);
  modal.addEventListener('click', function (e) { if (e.target === modal) closeCam(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) closeCam(); });

  setMode('pinjam');
})();
