<?php
$f = $f ?? ['type' => 'siswa'];
$err = fn($k) => isset($errors[$k]) ? '<span class="error">' . e($errors[$k]) . '</span>' : '';
$inv = fn($k) => isset($errors[$k]) ? ' is-invalid' : '';
$type = $f['type'] ?? 'siswa';
$steps = $otp ? ['Data diri', 'Verifikasi', 'Kartu QR'] : ['Data diri', 'Persetujuan', 'Kartu QR'];
?>
<?= partial('public/_hero', ['heading' => 'Daftar jadi member perpustakaan', 'lead' => !empty($s['reg']['require_approval']) ? 'Setelah disetujui petugas, kamu dapat kartu QR untuk masuk dan meminjam buku.' : 'Kartu QR langsung aktif setelah pendaftaran selesai.', 'steps' => $steps, 'step' => 0, 'back' => turl()]) ?>
<?php if (empty($s['reg']['open'])): ?>
  <div class="public-body"><div class="alert alert-warn"><?= icon('alert') ?><span>Pendaftaran mandiri sedang ditutup. Silakan datang ke meja petugas perpustakaan.</span></div></div>
<?php else: ?>
<form class="public-body" method="post" action="<?= e(turl('/daftar')) ?>" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <?php if ($errors): ?><div class="alert alert-bad"><?= icon('alert') ?><span>Periksa lagi isian yang ditandai merah.</span></div><?php endif; ?>
  <fieldset style="border:0;margin:0;padding:0" class="stack-sm">
    <legend class="field" style="padding:0;margin-bottom:8px">Saya mendaftar sebagai</legend>
    <div class="segmented" id="typeSwitch">
      <label><input type="radio" name="type" value="siswa" <?= $type === 'siswa' ? 'checked' : '' ?>>Siswa</label>
      <label><input type="radio" name="type" value="guru" <?= $type === 'guru' ? 'checked' : '' ?>>Guru</label>
      <label><input type="radio" name="type" value="tendik" <?= $type === 'tendik' ? 'checked' : '' ?>>Tendik</label>
    </div>
  </fieldset>

  <label class="field">Nama lengkap<input class="input<?= $inv('name') ?>" name="name" value="<?= e($f['name'] ?? '') ?>" required autocomplete="name"><?= $err('name') ?></label>

  <div data-for="siswa" class="stack" style="gap:18px">
    <label class="field">NISN<input class="input<?= $inv('nisn') ?>" name="nisn" inputmode="numeric" maxlength="10" value="<?= e($f['nisn'] ?? '') ?>" style="letter-spacing:.04em"><span class="hint">10 digit, lihat di kartu pelajar atau rapor</span><?= $err('nisn') ?></label>
  </div>
  <div data-for="staf" class="stack" style="gap:18px">
    <label class="field"><span>NIP / NUPTK <span class="opt">(opsional)</span></span><input class="input<?= $inv('nip') ?>" name="nip" inputmode="numeric" value="<?= e($f['nip'] ?? '') ?>"><?= $err('nip') ?></label>
    <label class="field"><span data-guru>Mata pelajaran</span><span data-tendik>Jabatan / bagian</span> <span class="opt">(opsional)</span><input class="input" name="subject" value="<?= e($f['subject'] ?? '') ?>"></label>
  </div>

  <div class="grid-2" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
    <label class="field" data-for="siswa">Kelas
      <select class="select<?= $inv('kelas') ?>" name="kelas"><option value="">Pilih</option><?= select_options($s['kelas_list'], $f['kelas'] ?? '') ?></select><?= $err('kelas') ?>
    </label>
    <label class="field">Jenis kelamin
      <select class="select" name="gender"><option value="">Pilih</option><?= select_options(['L' => 'Laki-laki', 'P' => 'Perempuan'], $f['gender'] ?? '', true) ?></select>
    </label>
  </div>

  <label class="field">No. WhatsApp<input class="input<?= $inv('phone') ?>" type="tel" name="phone" inputmode="tel" value="<?= e($f['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx" required autocomplete="tel">
    <span class="hint"><?= $otp ? 'Kode verifikasi dan kartu QR dikirim ke nomor ini' : 'Pemberitahuan pinjaman dan kartu dikirim ke nomor ini' ?></span><?= $err('phone') ?></label>
  <label class="field"><span>Email <span class="opt">(opsional)</span></span><input class="input<?= $inv('email') ?>" type="email" name="email" value="<?= e($f['email'] ?? '') ?>" placeholder="nama@email.com" autocomplete="email"><?= $err('email') ?></label>

  <div class="field">Foto profil <span class="opt">(opsional)</span>
    <label class="dropzone">
      <span class="thumb" id="photoPrev"><?= icon('camera', 26) ?></span>
      <span class="stack-sm" style="gap:4px"><strong style="color:var(--blue-600);font-size:14px">Ambil atau unggah foto</strong><span class="hint">Wajah terlihat jelas, latar polos.</span></span>
      <input type="file" name="photo" accept="image/*" data-resize="800" data-preview="#photoPrev" hidden>
    </label><?= $err('photo') ?>
  </div>

  <div class="grid-2" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
    <label class="field">Buat PIN<input class="input<?= $inv('pin') ?>" type="password" name="pin" inputmode="numeric" maxlength="6" autocomplete="new-password" placeholder="6 angka"><?= $err('pin') ?></label>
    <label class="field">Ulangi PIN<input class="input<?= $inv('pin2') ?>" type="password" name="pin2" inputmode="numeric" maxlength="6" autocomplete="new-password"><?= $err('pin2') ?></label>
  </div>
  <span class="hint" style="margin-top:-10px;font-size:12px;color:var(--muted)">PIN dipakai untuk masuk dan melihat kartu QR di HP.</span>

  <label class="check alert alert-gold" style="font-weight:500">
    <input type="checkbox" name="agree" value="1" <?= !empty($f['agree']) ? 'checked' : '' ?>>
    <span>Saya sudah membaca dan setuju dengan <a href="<?= e(turl('/tata-tertib')) ?>" target="_blank">tata tertib perpustakaan</a>.</span>
  </label><?= $err('agree') ?>

  <button class="btn btn-lg btn-block" type="submit"><?= $otp ? 'Kirim kode verifikasi' : 'Daftar sekarang' ?><?= icon('arrow') ?></button>
  <p class="center small muted">Sudah terdaftar? <a href="<?= e(turl('/masuk')) ?>" style="font-weight:700">Masuk</a></p>
</form>
<script>
(function () {
  var radios = document.querySelectorAll('#typeSwitch input');
  function apply() {
    var t = document.querySelector('#typeSwitch input:checked').value;
    document.querySelectorAll('[data-for="siswa"]').forEach(function (el) { el.hidden = t !== 'siswa'; });
    document.querySelectorAll('[data-for="staf"]').forEach(function (el) { el.hidden = t === 'siswa'; });
    document.querySelectorAll('[data-guru]').forEach(function (el) { el.hidden = t !== 'guru'; });
    document.querySelectorAll('[data-tendik]').forEach(function (el) { el.hidden = t !== 'tendik'; });
  }
  radios.forEach(function (r) { r.addEventListener('change', apply); });
  apply();
})();
</script>
<?php endif; ?>
