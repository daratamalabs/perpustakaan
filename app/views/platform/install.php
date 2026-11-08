<div class="auth-wrap">
  <section class="auth-side">
    <span class="brand"><span class="mark"><?= icon('logo', 22) ?></span><span class="brand-text"><span class="eyebrow">Daratama Labs</span><strong><?= app_wordmark() ?></strong></span></span>
    <div class="stack" style="gap:24px">
      <h1>Pasang aplikasi perpustakaan untuk banyak sekolah.</h1>
      <ul class="auth-points">
        <li><b><?= icon('check', 15) ?></b>Buat database MySQL kosong di hPanel atau cPanel</li>
        <li><b><?= icon('check', 15) ?></b>Isi data koneksi di formulir ini</li>
        <li><b><?= icon('check', 15) ?></b>Masuk sebagai super admin, lalu tambahkan sekolah</li>
      </ul>
    </div>
    <span class="muted" style="color:var(--muted-on-navy)">Versi <?= APP_VERSION ?></span>
  </section>
  <section class="auth-form-col">
    <?php if ($done): ?>
      <div class="auth-form" style="max-width:640px">
        <h2>Hampir selesai</h2>
        <div class="alert alert-warn"><?= icon('alert') ?><span>Server tidak mengizinkan menulis <b>config.php</b>. Buat berkas <b>config.php</b> di folder aplikasi dengan isi berikut, lalu buka <a href="<?= e(url('/superadmin/login')) ?>">/superadmin</a>.</span></div>
        <textarea class="textarea mono" rows="16" readonly><?= e($done['manual']) ?></textarea>
      </div>
    <?php else: ?>
    <form class="auth-form" method="post" style="max-width:520px">
      <?= csrf_field() ?>
      <div class="stack-sm"><h2>Instalasi</h2><p class="muted small">Tabel dibuat otomatis. Installer ini terkunci setelah super admin pertama dibuat.</p></div>
      <?php foreach ($errors as $err): ?><div class="alert alert-bad"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endforeach; ?>
      <span class="label-caps">Pemeriksaan server</span>
      <ul class="req-list">
        <?php foreach (install_requirements() as [$lbl, $ok, $req]): ?>
          <li class="<?= $ok ? 'is-ok' : ($req ? 'is-bad' : 'is-warn') ?>"><?= icon($ok ? 'check' : ($req ? 'x' : 'alert'), 15) ?><span><?= e($lbl) ?><?= !$ok && !$req ? ' <span class="opt">· opsional</span>' : '' ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php if (!$installed): ?>
        <span class="label-caps">Database MySQL</span>
        <div class="grid-2">
          <label class="field">Host<input class="input" name="db_host" value="<?= e($f['db_host']) ?>" required></label>
          <label class="field">Port<input class="input" name="db_port" value="<?= e($f['db_port']) ?>" inputmode="numeric"></label>
        </div>
        <label class="field">Nama database<input class="input" name="db_name" value="<?= e($f['db_name']) ?>" required placeholder="u123456789_perpustakaan"></label>
        <div class="grid-2">
          <label class="field">User database<input class="input" name="db_user" value="<?= e($f['db_user']) ?>" required autocomplete="off"></label>
          <label class="field">Password database<input class="input" type="password" name="db_pass" value="<?= e($f['db_pass']) ?>" autocomplete="new-password"></label>
        </div>
        <label class="field">Alamat aplikasi<input class="input" name="base_url" value="<?= e($f['base_url']) ?>"><span class="hint">Mis. https://satuperpus.daratamalabs.com</span></label>
      <?php else: ?>
        <div class="alert alert-info"><?= icon('info') ?><span>config.php sudah ada. Tinggal membuat tabel dan akun super admin.</span></div>
      <?php endif; ?>
      <span class="label-caps">Akun super admin</span>
      <label class="field">Nama<input class="input" name="admin_name" value="<?= e($f['admin_name']) ?>" required></label>
      <div class="grid-2">
        <label class="field">Username<input class="input" name="admin_user" value="<?= e($f['admin_user']) ?>" required autocomplete="username"></label>
        <label class="field">Kata sandi<input class="input" type="password" name="admin_pass" minlength="8" required autocomplete="new-password"></label>
      </div>
      <button class="btn btn-lg" type="submit">Pasang sekarang</button>
    </form>
    <?php endif; ?>
  </section>
</div>
