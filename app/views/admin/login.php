<div class="auth-wrap">
  <section class="auth-side">
    <?= brand_block('Perpustakaan', Tenant::get('school_name'), 48) ?>
    <div class="stack" style="gap:28px">
      <h1>Koleksi, kunjungan, dan aset sekolah dalam satu meja kerja.</h1>
      <ul class="auth-points">
        <li><b><?= icon('check', 15) ?></b>Katalog buku, eksemplar, dan aset tercatat rapi</li>
        <li><b><?= icon('check', 15) ?></b>Kunjungan siswa terekam otomatis lewat QR</li>
        <li><b><?= icon('check', 15) ?></b>Laporan bulanan siap ekspor untuk sekolah</li>
      </ul>
    </div>
    <div class="row-between">
      <span class="small" style="color:var(--muted-on-navy)">© <?= date('Y') ?> <?= e(Tenant::get('library_name')) ?></span>
      <?= spines_html() ?>
    </div>
  </section>
  <section class="auth-form-col">
    <form class="auth-form" method="post" action="<?= e(turl('/admin/login') . (g('next') ? '?next=' . rawurlencode((string)g('next')) : '')) ?>">
      <?= csrf_field() ?>
      <div class="stack-sm"><h2>Masuk petugas</h2><p class="muted small">Gunakan akun yang dibuat oleh kepala perpustakaan.</p></div>
      <?= flashes_html() ?>
      <?php if ($error): ?><div class="alert alert-bad"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
      <label class="field">Username<input class="input" name="username" value="<?= e($username ?? '') ?>" required autocomplete="username" autofocus></label>
      <label class="field">Kata sandi<input class="input" type="password" name="password" required autocomplete="current-password"></label>
      <label class="check"><input type="checkbox" name="remember" value="1" checked>Ingat perangkat ini (30 hari)</label>
      <button class="btn btn-lg" type="submit">Masuk</button>
      <div class="divider">atau</div>
      <a class="btn btn-lg btn-secondary" href="<?= e(turl()) ?>">Halaman member</a>
    </form>
  </section>
</div>
