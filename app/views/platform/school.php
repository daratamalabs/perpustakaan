<?php $s = $school ?? []; $isNew = !$school; ?>
<?= partial('platform/_top', ['admin' => $admin]) ?>
<div style="max-width:1180px;margin:0 auto;padding:28px 24px 48px" class="stack">
  <?= flashes_html() ?>
  <div class="page-head">
    <div><span class="crumb"><a href="<?= e(url('/superadmin')) ?>">Daftar sekolah</a> / <?= $isNew ? 'Tambah' : 'Kelola' ?></span><h1><?= e($isNew ? 'Tambah sekolah' : $s['name']) ?></h1></div>
    <?php if (!$isNew): ?><div class="page-actions"><a class="btn btn-secondary" target="_blank" rel="noopener" href="<?= e(url('/' . $s['slug'])) ?>"><?= icon('link', 16) ?>Buka halaman sekolah</a><a class="btn" target="_blank" rel="noopener" href="<?= e(url('/' . $s['slug'] . '/admin')) ?>">Buka admin sekolah</a></div><?php endif; ?>
  </div>
  <?php if ($newPassword): ?>
    <div class="alert alert-gold"><?= icon('key') ?><span>Kata sandi baru untuk <b><?= e($newPassword['user']) ?></b>: <b class="mono" style="font-size:16px"><?= e($newPassword['pass']) ?></b>. Berikan ke pemilik akun dan minta segera diganti.</span></div>
  <?php endif; ?>
  <div class="split">
    <form class="card card-pad stack wide" method="post" action="<?= e(url($isNew ? '/superadmin/sekolah/baru' : '/superadmin/sekolah/' . $s['id'])) ?>">
      <?= csrf_field() ?>
      <h2>Data sekolah</h2>
      <div class="grid-2">
        <label class="field span-all">Nama sekolah<input class="input" name="name" required value="<?= e(old('name', $s['name'] ?? '')) ?>" placeholder="SMA Negeri 3 Pemalang"></label>
        <label class="field">Alamat (path)
          <div class="input-group"><span class="addon"><?= e(parse_url((string)(App::$config['base_url'] ?? ''), PHP_URL_HOST) ?: 'domain') ?>/</span><input class="input" name="slug" required pattern="[a-z0-9][a-z0-9\-]{1,59}" value="<?= e(old('slug', $s['slug'] ?? '')) ?>" placeholder="sman3pemalang" style="font-weight:600"></div>
          <span class="hint">Huruf kecil, angka, tanda minus.</span>
        </label>
        <label class="field">Nama singkat<input class="input" name="short_name" value="<?= e(old('short_name', $s['short_name'] ?? '')) ?>" placeholder="SMAN 3 Pemalang"></label>
        <label class="field">Kota/kabupaten<input class="input" name="city" value="<?= e(old('city', $s['city'] ?? '')) ?>" placeholder="Pemalang"></label>
        <label class="field">Kontak (nama)<input class="input" name="contact_name" value="<?= e(old('contact_name', $s['contact_name'] ?? '')) ?>"></label>
        <label class="field">Kontak (WhatsApp)<input class="input" name="contact_phone" value="<?= e(old('contact_phone', $s['contact_phone'] ?? '')) ?>"></label>
        <label class="field span-all">Catatan<textarea class="textarea" name="notes" rows="2"><?= e(old('notes', $s['notes'] ?? '')) ?></textarea></label>
      </div>
      <?php if ($isNew): ?>
        <h2 style="margin-top:8px">Akun kepala perpustakaan</h2>
        <p class="muted small">Akun pertama dengan akses penuh. Petugas lain bisa ditambahkan sendiri oleh sekolah.</p>
        <div class="grid-2">
          <label class="field">Nama<input class="input" name="admin_name" required value="<?= e(old('admin_name')) ?>"></label>
          <label class="field">Username<input class="input" name="admin_user" required pattern="[a-z0-9._\-]{3,40}" value="<?= e(old('admin_user')) ?>" autocomplete="off"></label>
          <label class="field">Kata sandi awal<input class="input" type="text" name="admin_pass" minlength="8" required autocomplete="off" value="<?= e(substr(str_replace(['-', '_'], '', random_token(12)), 0, 10)) ?>"><span class="hint">Catat dan berikan ke sekolah.</span></label>
        </div>
      <?php else: ?>
        <label class="toggle-row"><span class="grow"><strong>Sekolah aktif</strong><span class="muted">Jika dimatikan, semua halaman sekolah ini tidak bisa dibuka.</span></span><span class="switch"><input type="checkbox" name="active" value="1" <?= $s['active'] ? 'checked' : '' ?>><span></span></span></label>
      <?php endif; ?>
      <div><button class="btn" type="submit"><?= $isNew ? 'Buat sekolah' : 'Simpan' ?></button></div>
    </form>
    <?php if (!$isNew): ?>
      <section class="card side">
        <div class="card-head"><h2>Akun petugas</h2></div>
        <div style="padding:8px 0">
          <?php foreach ($staff as $u): ?>
            <div class="list-row">
              <?= avatar_html($u['name']) ?>
              <div class="grow"><strong><?= e($u['name']) ?></strong><span><?= e($u['username']) ?> · <?= e(label('role', $u['role'])) ?><?= $u['active'] ? '' : ' · nonaktif' ?></span></div>
              <form method="post" action="<?= e(url('/superadmin/sekolah/' . $s['id'] . '/reset-sandi')) ?>" data-confirm="Reset kata sandi <?= e($u['username']) ?>?"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><button class="btn btn-sm btn-secondary" type="submit">Reset sandi</button></form>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>
</div>
