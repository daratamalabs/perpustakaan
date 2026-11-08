/* SatuPerpus — helper umum */
(function () {
  'use strict';
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  /** fetch JSON with CSRF header. */
  window.api = function (url, opts) {
    opts = opts || {};
    var headers = { 'X-Requested-With': 'fetch', 'Accept': 'application/json', 'X-CSRF-Token': csrf };
    var body = opts.body;
    if (body && !(body instanceof FormData) && typeof body === 'object') {
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      body = new URLSearchParams(body).toString();
    }
    var method = opts.method || (opts.body !== undefined ? 'POST' : 'GET');
    return fetch(url, { method: method, headers: headers, body: method === 'GET' ? undefined : body, credentials: 'same-origin' })
      .then(function (r) {
        return r.json().catch(function () { return { ok: false, message: 'Respons server tidak valid (' + r.status + ').' }; })
          .then(function (d) { if (r.status === 401) { d.ok = false; } return d; });
      })
      .catch(function () { return { ok: false, message: 'Tidak bisa terhubung ke server. Periksa koneksi internet.' }; });
  };

  window.toast = function (msg, tone) {
    var stack = document.getElementById('toasts');
    if (!stack) return alert(msg);
    var el = document.createElement('div');
    el.className = 'toast ' + (tone || '');
    el.setAttribute('role', 'status');
    el.textContent = msg;
    stack.appendChild(el);
    setTimeout(function () { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; }, 4200);
    setTimeout(function () { el.remove(); }, 4600);
  };

  window.escapeHtml = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  };

  /** Render QR codes into elements with data-qr. */
  window.renderQr = function (root) {
    if (typeof qrcode !== 'function') return;
    (root || document).querySelectorAll('[data-qr]').forEach(function (el) {
      if (el.dataset.qrDone) return;
      var q = qrcode(0, 'M');
      q.addData(el.dataset.qr);
      q.make();
      var n = q.getModuleCount();
      var margin = parseInt(el.dataset.qrMargin || '0', 10);
      var cell = 10;
      var size = (n + margin * 2) * cell;
      var c = document.createElement('canvas');
      c.width = size; c.height = size;
      var ctx = c.getContext('2d');
      ctx.fillStyle = '#FFFFFF'; ctx.fillRect(0, 0, size, size);
      ctx.fillStyle = el.dataset.qrColor || '#10284A';
      for (var r = 0; r < n; r++) for (var col = 0; col < n; col++) if (q.isDark(r, col)) ctx.fillRect((col + margin) * cell, (r + margin) * cell, cell, cell);
      c.style.width = '100%'; c.style.height = 'auto'; c.style.display = 'block'; c.style.imageRendering = 'pixelated';
      c.setAttribute('role', 'img');
      c.setAttribute('aria-label', el.dataset.qrLabel || 'Kode QR');
      el.innerHTML = '';
      el.appendChild(c);
      el.dataset.qrDone = '1';
    });
  };

  window.renderBarcodes = function (root) {
    if (typeof JsBarcode !== 'function') return;
    (root || document).querySelectorAll('svg[data-barcode]').forEach(function (el) {
      try { JsBarcode(el, el.dataset.barcode, { format: 'CODE128', displayValue: false, margin: 0, height: parseInt(el.dataset.h || '40', 10), width: parseFloat(el.dataset.w || '1.6') }); } catch (e) { /* ignore */ }
    });
  };

  /** Downscale images before upload (phones produce huge photos). */
  function resizeInput(input) {
    var max = parseInt(input.dataset.resize || '900', 10);
    var file = input.files && input.files[0];
    if (!file || !/^image\//.test(file.type) || typeof DataTransfer === 'undefined') return;
    var img = new Image();
    var url = URL.createObjectURL(file);
    img.onload = function () {
      var s = Math.min(1, max / Math.max(img.width, img.height));
      var c = document.createElement('canvas');
      c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      c.toBlob(function (blob) {
        if (!blob) return;
        var dt = new DataTransfer();
        dt.items.add(new File([blob], (file.name || 'foto').replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }));
        input.files = dt.files;
        var prev = input.dataset.preview && document.querySelector(input.dataset.preview);
        if (prev) prev.innerHTML = '<img src="' + URL.createObjectURL(blob) + '" alt="">';
      }, 'image/jpeg', 0.85);
      URL.revokeObjectURL(url);
    };
    img.src = url;
  }

  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t.matches && t.matches('input[type=file][data-resize]')) resizeInput(t);
    if (t.matches && t.matches('[data-autosubmit]')) t.form && t.form.submit();
    if (t.matches && t.matches('[data-check-all]')) {
      document.querySelectorAll(t.dataset.checkAll).forEach(function (c) { c.checked = t.checked; });
      document.dispatchEvent(new Event('selectionchange-rows'));
    }
    if (t.matches && t.matches('.row-check')) document.dispatchEvent(new Event('selectionchange-rows'));
  });

  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.dataset.confirm && !confirm(f.dataset.confirm)) { e.preventDefault(); return; }
    var btn = f.querySelector('button[type=submit]:not([formaction])');
    if (btn && !f.dataset.noLoading) { setTimeout(function () { btn.classList.add('is-loading'); }, 0); }
  });

  document.addEventListener('click', function (e) {
    var c = e.target.closest && e.target.closest('[data-copy]');
    if (c) {
      e.preventDefault();
      var text = c.dataset.copy;
      (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () { toast('Disalin ke clipboard', 'ok'); }, function () { prompt('Salin teks ini:', text); });
    }
    var pr = e.target.closest && e.target.closest('[data-print]');
    if (pr) { e.preventDefault(); window.print(); }
    var nt = e.target.closest && e.target.closest('[data-nav-toggle]');
    if (nt) {
      var sb = document.getElementById('sidebar');
      var open = sb.classList.toggle('is-open');
      nt.setAttribute('aria-expanded', open ? 'true' : 'false');
      nt.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
    }
  });

  document.addEventListener('DOMContentLoaded', function () { renderQr(); renderBarcodes(); });
})();
