<header class="row-between" style="padding:24px 20px 8px">
  <h1 class="serif" style="font-size:32px;line-height:1.1">Profil</h1>
  <form method="post" action="<?= e(turl('/keluar')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-secondary" type="submit"><?= icon('logout', 16) ?>Keluar</button></form>
</header>
<div class="public-body" style="padding-top:16px">
  <?= flashes_html() ?>
  <div class="card card-pad row" style="gap:16px;flex-wrap:nowrap">
    <?= avatar_html($m['name'], $m['photo'], 'avatar-lg') ?>
    <div class="stack-sm" style="gap:2px;min-width:0">
      <strong style="font-size:17px"><?= e($m['name']) ?></strong>
      <span class="small muted"><?= e(member_meta($m)) ?></span>
      <span class="tiny muted mono"><?= e($m['member_no'] ?: label('member_status', $m['status'])) ?></span>
    </div>
  </div>
  <div class="card card-pad">
    <dl class="dl">
      <?php if ($m['nisn']): ?><dt>NISN</dt><dd class="mono"><?= e($m['nisn']) ?></dd><?php endif; ?>
      <?php if ($m['nip']): ?><dt>NIP</dt><dd class="mono"><?= e($m['nip']) ?></dd><?php endif; ?>
      <dt>WhatsApp</dt><dd><?= e($m['phone'] ?: '—') ?></dd>
      <dt>Status</dt><dd><?= e(label('member_status', $m['status'])) ?></dd>
      <dt>Berlaku s.d.</dt><dd><?= e($m['valid_until'] ? fmt_date($m['valid_until'], 'full') : 'Selama aktif') ?></dd>
    </dl>
    <p class="tiny muted" style="margin-top:12px">Data kelas, NISN, dan nomor WhatsApp hanya bisa diubah petugas.</p>
  </div>
  <form class="card card-pad stack" method="post" action="<?= e(turl('/profil')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="action" value="data">
    <h2>Foto & email</h2>
    <label class="dropzone">
      <span class="thumb" id="pp"><?= $m['photo'] ? '<img src="' . e(media_url($m['photo'])) . '" alt="">' : icon('camera', 26) ?></span>
      <span class="stack-sm" style="gap:4px"><strong style="color:var(--blue-600);font-size:14px">Ganti foto</strong><span class="hint small muted">JPG atau PNG</span></span>
      <input type="file" name="photo" accept="image/*" data-resize="800" data-preview="#pp" hidden>
    </label>
    <label class="field">Email<input class="input" type="email" name="email" value="<?= e($m['email'] ?? '') ?>"></label>
    <button class="btn btn-secondary" type="submit">Simpan</button>
  </form>
  <form class="card card-pad stack" method="post" action="<?= e(turl('/profil')) ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="pin">
    <h2>Ganti PIN</h2>
    <label class="field">PIN lama<input class="input" type="password" name="old_pin" inputmode="numeric" maxlength="6" required autocomplete="current-password"></label>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <label class="field">PIN baru<input class="input" type="password" name="new_pin" inputmode="numeric" maxlength="6" required autocomplete="new-password"></label>
      <label class="field">Ulangi<input class="input" type="password" name="new_pin2" inputmode="numeric" maxlength="6" required autocomplete="new-password"></label>
    </div>
    <button class="btn btn-secondary" type="submit">Ganti PIN</button>
  </form>
</div>
