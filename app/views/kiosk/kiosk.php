<?php
$initial = $_SESSION['kiosk_result'] ?? null;
unset($_SESSION['kiosk_result']);
$middle = '<h1>Selamat datang di perpustakaan.</h1>
<ol class="kiosk-steps">
  <li><b>1</b>Buka kartu QR di HP atau siapkan kartu cetak</li>
  <li><b>2</b>Arahkan QR ke kamera di sebelah kanan</li>
  <li><b>3</b>Tunggu bunyi bip dan namamu muncul</li>
</ol>';
?>
<div class="kiosk" id="kiosk"
     data-scan-url="<?= e(turl('/kiosk/scan')) ?>"
     data-search-url="<?= e(turl('/kiosk/cari')) ?>"
     data-checkin-url="<?= e(turl('/kiosk/masuk')) ?>"
     data-initial="<?= e($initial ? json_encode($initial) : '') ?>">
  <?= partial('kiosk/_side', ['middle' => $middle, 'open' => $open]) ?>
  <section class="kiosk-main">
    <?php if (!empty($s['enforce_hours']) && !$open['open']): ?>
      <div class="alert alert-warn" style="max-width:560px;width:100%"><?= icon('clock') ?><span>Di luar jam layanan, check-in otomatis ditolak. Petugas tetap bisa mencatat kunjungan dari panel admin.</span></div>
    <?php endif; ?>
    <div class="viewfinder" id="viewfinder">
      <video id="cam" playsinline muted></video>
      <span class="corner c1"></span><span class="corner c2"></span><span class="corner c3"></span><span class="corner c4"></span>
      <?= icon('qr', 120, 'style="stroke-width:1.2"') ?>
      <span class="scanline"></span>
      <span class="cam-status"><i></i><span id="camText">Menyalakan kamera…</span></span>
    </div>
    <div class="result" id="result" hidden role="status" aria-live="assertive">
      <span class="ico" id="resIcon"><?= icon('check', 28, 'style="stroke-width:2.4"') ?></span>
      <div class="grow stack-sm" style="gap:3px;min-width:0">
        <span class="title" id="resTitle"></span>
        <span class="name" id="resName"></span>
        <span class="muted small" id="resMeta"></span>
      </div>
      <span class="time" id="resTime"></span>
    </div>
    <div class="row" style="justify-content:center">
      <span class="muted" style="font-size:15px;font-weight:600">Tidak bawa kartu?</span>
      <button class="btn btn-lg btn-secondary" type="button" id="openSearch"><?= icon('search', 20) ?>Cari nama / NISN</button>
      <a class="btn btn-lg btn-gold" href="<?= e(turl('/kiosk/tamu')) ?>"><?= icon('edit', 20) ?>Saya tamu</a>
    </div>
    <div class="row no-print" style="justify-content:center;gap:6px">
      <button class="btn btn-sm btn-ghost" type="button" id="switchCam"><?= icon('refresh', 16) ?>Ganti kamera</button>
      <a class="btn btn-sm btn-ghost" href="<?= e(turl('/admin/kunjungan')) ?>"><?= icon('dash', 16) ?>Panel petugas</a>
    </div>
    <input class="wedge" id="wedge" aria-hidden="true" tabindex="-1" autocomplete="off">
  </section>
</div>

<div class="modal" id="searchModal" hidden>
  <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="searchTitle">
    <div class="row-between"><h2 id="searchTitle" style="font-size:22px">Cari nama atau NISN</h2><button class="btn-icon" type="button" id="closeSearch" aria-label="Tutup"><?= icon('x', 20) ?></button></div>
    <div class="input-icon"><?= icon('search', 20) ?><input class="input input-lg" id="searchInput" placeholder="Ketik minimal 3 huruf" autocomplete="off"></div>
    <div class="pick-list" id="searchList" hidden></div>
    <p class="muted small" id="searchHint">Hanya member aktif yang muncul.</p>
  </div>
</div>
