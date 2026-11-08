<div class="tv" id="tv" data-url="<?= e(turl('/display/data') . (g('key') ? '?key=' . rawurlencode((string)g('key')) : '')) ?>" data-initial="<?= e(json_encode($data, JSON_UNESCAPED_UNICODE)) ?>">
  <header class="tv-head">
    <?= brand_block('Perpustakaan', Tenant::get('school_name'), 60) ?>
    <div class="tv-clock">
      <span class="date" id="tvDate"><?= e($data['date']) ?></span>
      <span class="sep"></span>
      <span class="time" id="tvTime"><?= e($data['time']) ?></span>
    </div>
  </header>
  <main class="tv-main">
    <div class="tv-left">
      <section class="tv-hero" id="tvHero" aria-live="polite">
        <div class="big-avatar" id="heroAvatar"><?= icon('logo', 80) ?></div>
        <div class="stack-sm" style="gap:.7em;min-width:0">
          <span class="tag" id="heroTag" hidden></span>
          <span class="hello" id="heroHello">Selamat datang di</span>
          <span class="who" id="heroWho" style="font-size:4.4vw"><?= e($s['library_name']) ?></span>
          <span class="meta" id="heroMeta">Pindai kartu QR di meja depan setiap kali datang.</span>
        </div>
      </section>
      <section class="stack-sm" style="gap:1.4vh">
        <span class="tv-label">Pengunjung sebelumnya</span>
        <div class="tv-recent" id="tvRecent"></div>
      </section>
      <section class="stack-sm" style="gap:1.4vh" id="booksWrap">
        <span class="tv-label">Baru di rak</span>
        <div class="tv-books" id="tvBooks"></div>
      </section>
    </div>
    <aside class="tv-right">
      <div class="tv-stats">
        <div style="background:var(--navy-850)"><strong id="tvTotal">0</strong><span style="color:var(--soft-on-navy)">Pengunjung hari ini</span></div>
        <div style="background:var(--gold-500);color:var(--navy-900)"><strong id="tvInside">0</strong><span>Sedang di perpustakaan</span></div>
      </div>
      <div class="tv-card stack-sm" style="gap:1.6vh">
        <span style="font-size:1.15em;font-weight:700">Kunjungan per jam</span>
        <div>
          <div class="tv-hours" id="tvHours"></div>
          <div class="tv-hours-l" id="tvHoursL"></div>
        </div>
      </div>
      <div class="tv-card stack-sm" style="flex-grow:1;gap:1.6vh" id="classWrap">
        <span style="font-size:1.15em;font-weight:700">Kelas paling rajin minggu ini</span>
        <div class="stack-sm" style="gap:1.4vh" id="tvClasses"></div>
      </div>
    </aside>
  </main>
  <footer class="tv-ticker">
    <span class="lbl">Pengumuman</span>
    <div class="track"><span id="tvTicker"><?= e($data['announcements']) ?></span></div>
  </footer>
</div>
