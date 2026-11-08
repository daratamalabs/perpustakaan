/* Form buku: isi otomatis dari ISBN */
(function () {
  'use strict';
  var input = document.getElementById('isbnLookup');
  var btn = document.getElementById('isbnBtn');
  var msg = document.getElementById('isbnMsg');
  if (!input) return;

  function say(text, tone) {
    msg.hidden = false;
    msg.style.color = tone === 'ok' ? 'var(--ok)' : (tone === 'warn' ? 'var(--warn)' : 'var(--bad)');
    msg.innerHTML = text;
  }

  function set(id, val, force) {
    var el = document.getElementById(id);
    if (!el || !val) return;
    if (force || !el.value) el.value = val;
  }

  function lookup() {
    var isbn = input.value.replace(/[^0-9Xx]/g, '');
    if (!isbn) { say('Isi ISBN dulu.'); return; }
    btn.disabled = true; btn.textContent = 'Mencari…';
    api(input.dataset.url + '?isbn=' + encodeURIComponent(isbn)).then(function (d) {
      btn.disabled = false; btn.textContent = 'Cari';
      if (!d.ok) { say(escapeHtml(d.message)); return; }
      if (d.existing && !document.getElementById('f_title').value) {
        say('Buku dengan ISBN ini sudah ada: <a href="' + d.existing.url + '">' + escapeHtml(d.existing.title) + '</a>. Tambahkan eksemplarnya di sana.', 'warn');
      }
      if (d.book) {
        set('f_title', d.book.title, true);
        set('f_author', d.book.author, true);
        set('f_publisher', d.book.publisher, true);
        set('f_year', d.book.year, true);
        set('f_isbn', d.book.isbn, true);
        set('f_language', d.book.language);
        set('f_synopsis', d.book.synopsis);
        if (!d.existing) say('Data ditemukan. Periksa kembali sebelum menyimpan.', 'ok');
      }
    });
  }

  btn.addEventListener('click', lookup);
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); lookup(); } });
})();
