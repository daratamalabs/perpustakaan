<div class="landing">
  <div class="landing-inner">
    <span class="brand"><span class="mark"><?= icon('logo', 22) ?></span><span class="brand-text"><span class="eyebrow">Daratama Labs</span><strong>Perpustakaan Digital Sekolah</strong></span></span>
    <div class="stack" style="gap:18px">
      <h1>Perpustakaan sekolah yang tertata, dari rak sampai meja depan.</h1>
      <p style="font-size:17px;color:var(--soft-on-navy);max-width:640px;line-height:1.6">Katalog buku, aset, peminjaman, dan kunjungan siswa lewat kartu QR. Pilih perpustakaan sekolahmu di bawah ini.</p>
    </div>
    <?php if ($schools): ?>
      <div class="school-grid">
        <?php foreach ($schools as $s): ?>
          <a class="school-card" href="<?= e(url('/' . $s['slug'])) ?>">
            <span class="mark"><?= icon('school', 24) ?></span>
            <span class="brand-text"><strong style="font-size:16px"><?= e($s['name']) ?></strong><span>/<?= e($s['slug']) ?><?= $s['city'] ? ' · ' . e($s['city']) : '' ?></span></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="color:var(--muted-on-navy)">Belum ada sekolah terdaftar.</p>
    <?php endif; ?>
    <div style="flex-grow:1"></div>
    <div class="row-between" style="color:var(--muted-on-navy);font-size:13px">
      <span>© <?= date('Y') ?> Daratama Labs</span>
      <a href="<?= e(url('/superadmin')) ?>" style="color:var(--muted-on-navy)">Masuk pengelola</a>
    </div>
  </div>
</div>
