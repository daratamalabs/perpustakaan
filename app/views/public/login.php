<?= partial('public/_hero', ['heading' => 'Masuk member', 'lead' => 'Gunakan NISN (siswa) atau NIP/nomor anggota (guru dan tendik), lalu PIN yang kamu buat saat mendaftar.', 'back' => turl()]) ?>
<form class="public-body" method="post" action="<?= e(turl('/masuk') . (g('next') ? '?next=' . rawurlencode((string)g('next')) : '')) ?>">
  <?= csrf_field() ?>
  <?= flashes_html() ?>
  <?php if ($error): ?><div class="alert alert-bad"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
  <label class="field">NISN / NIP / nomor anggota<input class="input" name="identifier" value="<?= e($identifier ?? '') ?>" required autocomplete="username" autofocus></label>
  <label class="field">PIN<input class="input" type="password" name="pin" inputmode="numeric" maxlength="6" required autocomplete="current-password"></label>
  <button class="btn btn-lg btn-block" type="submit">Masuk</button>
  <p class="center small muted">Lupa PIN? Minta petugas perpustakaan untuk mengatur ulang.</p>
  <p class="center small muted">Belum punya kartu? <a href="<?= e(turl('/daftar')) ?>" style="font-weight:700">Daftar</a></p>
</form>
