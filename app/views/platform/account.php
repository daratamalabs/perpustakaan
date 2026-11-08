<?= partial('platform/_top', ['admin' => $admin]) ?>
<div style="max-width:640px;margin:0 auto;padding:28px 24px 48px" class="stack">
  <?= flashes_html() ?>
  <div class="page-head"><div><span class="crumb"><a href="<?= e(url('/superadmin')) ?>">Daftar sekolah</a> / Akun</span><h1>Akun super admin</h1></div></div>
  <form class="card card-pad stack" method="post" action="<?= e(url('/superadmin/akun')) ?>">
    <?= csrf_field() ?>
    <label class="field">Nama<input class="input" name="name" value="<?= e($admin['name']) ?>"></label>
    <label class="field">Kata sandi lama<input class="input" type="password" name="current" required autocomplete="current-password"></label>
    <label class="field">Kata sandi baru<input class="input" type="password" name="new" minlength="8" required autocomplete="new-password"></label>
    <div><button class="btn" type="submit">Simpan</button></div>
  </form>
</div>
