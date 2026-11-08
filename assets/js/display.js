/* Layar display meja depan: memperbarui data tiap beberapa detik */
(function () {
  'use strict';
  var tv = document.getElementById('tv');
  if (!tv) return;
  var url = tv.dataset.url;
  var lastWelcomeId = 0;
  var welcomeUntil = 0;
  var data = null;
  var idleHero = null;
  var $ = function (id) { return document.getElementById(id); };
  var esc = window.escapeHtml;

  function setText(id, v) { var el = $(id); if (el && el.textContent !== String(v)) el.textContent = v; }

  function renderRecent(list) {
    var html = (list || []).map(function (r) {
      return '<div><span class="avatar">' + esc(r.initials) + '</span><span style="min-width:0"><strong>' + esc(r.name) + '</strong><span>' + esc(r.meta) + ' · ' + esc(r.time) + '</span></span></div>';
    }).join('');
    $('tvRecent').innerHTML = html || '<div style="grid-column:1/-1;color:var(--muted-on-navy)">Belum ada pengunjung hari ini.</div>';
  }

  function renderBooks(list) {
    $('booksWrap').hidden = !list || !list.length;
    $('tvBooks').innerHTML = (list || []).map(function (b) {
      var cover = b.cover
        ? '<span class="bk has-img"><img src="' + esc(b.cover) + '" alt=""></span>'
        : '<span class="bk" style="background:' + b.bg + ';color:' + b.fg + '"><span style="height:3px;width:50%;background:#D4A63A;border-radius:2px"></span><span>' + esc(b.title) + '</span></span>';
      return '<div>' + cover + '<span style="min-width:0"><strong>' + esc(b.title) + '</strong><span>' + esc(b.author) + '</span>' + (b.shelf ? '<em>Rak ' + esc(b.shelf) + '</em>' : '') + '</span></div>';
    }).join('');
  }

  function renderHours(list) {
    var max = Math.max.apply(null, (list || []).map(function (h) { return h.n; }).concat([10]));
    $('tvHours').innerHTML = (list || []).map(function (h) {
      var pct = h.n ? Math.max(6, Math.round(h.n / max * 82)) : 4;
      var bg = h.now ? '#D4A63A' : (h.n ? '#3A5C8E' : '#1D3A63');
      return '<div><b style="color:' + (h.now ? '#D4A63A' : '') + '">' + (h.n || '') + '</b><i style="height:' + pct + '%;background:' + bg + '"></i></div>';
    }).join('');
    $('tvHoursL').innerHTML = (list || []).map(function (h) { return '<span>' + h.h + '</span>'; }).join('');
  }

  function renderClasses(list, show) {
    $('classWrap').hidden = !show;
    if (!list || !list.length) { $('tvClasses').innerHTML = '<span style="color:var(--muted-on-navy)">Belum ada data minggu ini.</span>'; return; }
    var top = list[0].n || 1;
    $('tvClasses').innerHTML = list.map(function (c, i) {
      return '<div class="tv-rank"><span class="rank' + (i === 0 ? ' first' : '') + '">' + (i + 1) + '</span><b>' + esc(c.kelas) + '</b><span class="meter"><i class="' + (i === 0 ? 'first' : '') + '" style="width:' + Math.round(c.n / top * 100) + '%"></i></span><span>' + c.n + '</span></div>';
    }).join('');
  }

  function setHeroIdle() {
    var hero = $('tvHero');
    if (idleHero === null) idleHero = { avatar: $('heroAvatar').innerHTML };
    hero.classList.remove('is-welcome');
    $('heroAvatar').innerHTML = idleHero.avatar;
    $('heroTag').hidden = true;
    setText('heroHello', 'Selamat datang di');
    var who = $('heroWho');
    who.style.fontSize = '4.4vw';
    who.textContent = (data && data.library) || who.textContent;
    setText('heroMeta', data && !data.open ? (data.hours_today ? 'Jam layanan hari ini ' + data.hours_today : 'Hari ini tidak ada layanan') : 'Pindai kartu QR di meja depan setiap kali datang.');
  }

  function setHeroWelcome(v) {
    var hero = $('tvHero');
    if (idleHero === null) idleHero = { avatar: $('heroAvatar').innerHTML };
    hero.classList.remove('is-welcome'); void hero.offsetWidth; hero.classList.add('is-welcome');
    $('heroAvatar').textContent = v.initials;
    var tag = $('heroTag'); tag.hidden = false;
    tag.innerHTML = '<span style="width:10px;height:10px;border-radius:999px;background:#10284A"></span>Baru saja masuk · ' + esc(v.time);
    setText('heroHello', 'Selamat datang,');
    var who = $('heroWho');
    who.style.fontSize = '';
    who.textContent = v.name;
    setText('heroMeta', v.meta + (v.count ? ' · kunjungan ke-' + v.count + ' semester ini' : ''));
  }

  function render(d) {
    data = d;
    setText('tvDate', d.date);
    setText('tvTotal', d.total);
    setText('tvInside', d.inside);
    renderRecent(d.recent);
    renderBooks(d.books);
    renderHours(d.hourly);
    renderClasses(d.classes, d.show_classes);
    var ticker = $('tvTicker');
    if (ticker.textContent !== d.announcements) ticker.textContent = d.announcements;
    ticker.style.animationDuration = Math.max(18, d.announcements.length / 6) + 's';
    var v = d.latest;
    var now = Date.now();
    if (v && v.id !== lastWelcomeId && v.age < 90) {
      lastWelcomeId = v.id;
      welcomeUntil = now + d.welcome_seconds * 1000;
      setHeroWelcome(v);
    } else if (welcomeUntil && now > welcomeUntil) {
      welcomeUntil = 0;
      setHeroIdle();
    } else if (!welcomeUntil) {
      setHeroIdle();
    }
    if (v && !lastWelcomeId) lastWelcomeId = v.id;
  }

  function clock() {
    var d = new Date();
    setText('tvTime', String(d.getHours()).padStart(2, '0') + '.' + String(d.getMinutes()).padStart(2, '0'));
  }

  function poll() {
    fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' }, credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d && d.ok) render(d); })
      .catch(function () { /* jaringan putus, coba lagi nanti */ })
      .finally(function () { setTimeout(poll, 4000); });
  }

  try {
    var init = JSON.parse(tv.dataset.initial);
    if (init.latest) lastWelcomeId = init.latest.id; // jangan sambut ulang saat halaman dimuat
    render(init);
  } catch (e) { /* ignore */ }
  clock();
  setInterval(clock, 5000);
  setInterval(function () { if (welcomeUntil && Date.now() > welcomeUntil) { welcomeUntil = 0; setHeroIdle(); } }, 1000);
  setTimeout(poll, 3000);
  // Muat ulang tiap 6 jam supaya memori browser tetap ringan.
  setTimeout(function () { location.reload(); }, 6 * 60 * 60 * 1000);
})();
