<?php $h = $open['hours']; ?>
<?= partial('public/_hero', ['heading' => $s['library_name'], 'lead' => 'Daftar jadi member untuk mendapat kartu QR. Kartu dipakai saat masuk perpustakaan dan meminjam buku.']) ?>
<div class="public-body">
  <?= flashes_html() ?>
  <div class="card card-pad row" style="gap:14px">
    <span class="stat-icon" style="background:<?= $open['open'] ? 'var(--ok-bg)' : 'var(--mute-bg)' ?>;color:<?= $open['open'] ? 'var(--ok)' : 'var(--mute)' ?>"><?= icon('clock') ?></span>
    <div class="grow stack-sm" style="gap:2px">
      <strong><?= $open['open'] ? 'Buka sekarang' : 'Sedang tutup' ?></strong>
      <span class="muted small"><?= !empty($h['open']) ? 'Hari ini ' . e(str_replace(':', '.', $h['from'])) . '–' . e(str_replace(':', '.', $h['to'])) : 'Hari ini tidak ada layanan' ?></span>
    </div>
  </div>
  <?php if ($m): ?>
    <a class="btn btn-lg btn-block" href="<?= e(turl('/kartu')) ?>"><?= icon('qr', 20) ?>Buka kartu QR saya</a>
    <a class="btn btn-lg btn-block btn-secondary" href="<?= e(turl('/beranda')) ?>">Riwayat & pinjaman</a>
  <?php else: ?>
    <?php if (!empty($s['reg']['open'])): ?><a class="btn btn-lg btn-block" href="<?= e(turl('/daftar')) ?>"><?= icon('userplus', 20) ?>Daftar jadi member</a><?php endif; ?>
    <a class="btn btn-lg btn-block btn-secondary" href="<?= e(turl('/masuk')) ?>">Sudah terdaftar? Masuk</a>
  <?php endif; ?>
  <div class="card card-pad stack-sm">
    <strong>Aturan singkat</strong>
    <ul class="muted small" style="margin:0;padding-left:18px;line-height:1.7">
      <li>Siswa boleh meminjam <?= (int)$s['loan']['max_siswa'] ?> buku selama <?= (int)$s['loan']['days_siswa'] ?> hari.</li>
      <li>Guru dan tendik <?= (int)$s['loan']['max_guru'] ?> buku selama <?= (int)$s['loan']['days_guru'] ?> hari.</li>
      <?php if ((int)$s['loan']['fine_per_day'] > 0): ?><li>Terlambat mengembalikan dikenai denda <?= rupiah($s['loan']['fine_per_day']) ?> per hari.</li><?php endif; ?>
    </ul>
    <a href="<?= e(turl('/tata-tertib')) ?>" class="small" style="font-weight:700">Baca tata tertib lengkap</a>
  </div>
  <div style="flex-grow:1"></div>
  <p class="center tiny muted">Petugas? <a href="<?= e(turl('/admin')) ?>">Masuk panel admin</a></p>
</div>
