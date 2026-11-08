<div class="auth-wrap">
  <section class="auth-side">
    <span class="brand"><span class="mark"><?= icon('logo', 22) ?></span><span class="brand-text"><span class="eyebrow">Daratama Labs</span><strong><?= app_wordmark() ?></strong></span></span>
    <h1>Kelola semua sekolah dari satu panel.</h1>
    <?= spines_html() ?>
  </section>
  <section class="auth-form-col">
    <form class="auth-form" method="post" action="<?= e(url('/superadmin/login')) ?>">
      <?= csrf_field() ?>
      <div class="stack-sm"><h2>Masuk super admin</h2><p class="muted small">Khusus pengelola platform.</p></div>
      <?= flashes_html() ?>
      <?php if ($error): ?><div class="alert alert-bad"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
      <label class="field">Username<input class="input" name="username" value="<?= e($username ?? '') ?>" required autocomplete="username" autofocus></label>
      <label class="field">Kata sandi<input class="input" type="password" name="password" required autocomplete="current-password"></label>
      <button class="btn btn-lg" type="submit">Masuk</button>
    </form>
  </section>
</div>
