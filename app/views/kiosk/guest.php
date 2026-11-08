<?php
$err = fn($k) => isset($errors[$k]) ? '<span class="error">' . e($errors[$k]) . '</span>' : '';
$middle = '<span class="chip" style="align-self:flex-start;height:32px;padding:0 14px;background:var(--blue-700);color:var(--gold-500);letter-spacing:.08em;text-transform:uppercase">Buku tamu</span>
<h1>Selamat datang, tamu perpustakaan.</h1>
<p style="font-size:17px;line-height:1.6;color:var(--soft-on-navy);max-width:360px">Isi data singkat ini sebelum masuk. Tidak perlu membuat akun.</p>';
?>
<div class="kiosk">
  <?= partial('kiosk/_side', ['middle' => $middle, 'open' => library_open_now()]) ?>
  <section class="kiosk-main">
    <form class="card stack" method="post" action="<?= e(turl('/kiosk/tamu')) ?>" style="width:100%;max-width:640px;padding:36px;border-radius:22px;gap:20px">
      <?= csrf_field() ?>
      <h2 style="font-size:24px;font-weight:800">Data kunjungan</h2>
      <label class="field" style="font-size:14px;font-weight:700">Nama lengkap<input class="input input-lg" name="name" value="<?= e($f['name'] ?? '') ?>" required autofocus autocomplete="off"><?= $err('name') ?></label>
      <div class="grid-2">
        <label class="field" style="font-size:14px;font-weight:700">Asal / instansi<input class="input input-lg" name="origin" value="<?= e($f['origin'] ?? '') ?>" placeholder="Mis. komite sekolah, orang tua" autocomplete="off"></label>
        <label class="field" style="font-size:14px;font-weight:700"><span>No. HP <span class="opt">(opsional)</span></span><input class="input input-lg" type="tel" name="phone" value="<?= e($f['phone'] ?? '') ?>" autocomplete="off"></label>
      </div>
      <fieldset style="border:0;margin:0;padding:0" class="stack-sm">
        <legend class="field" style="font-size:14px;font-weight:700;padding:0;margin-bottom:10px">Keperluan</legend>
        <div class="need-chips">
          <?php foreach (GUEST_PURPOSES as $p): ?>
            <label><input type="radio" name="purpose" value="<?= e($p) ?>" <?= ($f['purpose'] ?? '') === $p ? 'checked' : '' ?>><span><?= e($p) ?></span></label>
          <?php endforeach; ?>
        </div>
        <?= $err('purpose') ?>
      </fieldset>
      <div class="row-between" style="padding-top:8px">
        <a class="btn btn-ghost btn-lg" href="<?= e(turl('/kiosk')) ?>"><?= icon('left', 20) ?>Kembali ke pemindai</a>
        <button class="btn btn-lg" type="submit">Simpan &amp; masuk</button>
      </div>
    </form>
  </section>
</div>
