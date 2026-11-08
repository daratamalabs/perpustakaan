<?php $masked = substr($m['phone'], 0, 4) . str_repeat('•', max(0, strlen($m['phone']) - 7)) . substr($m['phone'], -3); ?>
<?= partial('public/_hero', ['heading' => 'Masukkan kode verifikasi', 'lead' => 'Kode 6 angka sudah dikirim lewat WhatsApp ke ' . $masked . '.', 'steps' => ['Data diri', 'Verifikasi', 'Kartu QR'], 'step' => 1]) ?>
<div class="public-body">
  <?= flashes_html() ?>
  <?php if ($error): ?><div class="alert alert-bad"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
  <form method="post" action="<?= e(turl('/daftar/verifikasi')) ?>" class="stack">
    <?= csrf_field() ?>
    <label class="field">Kode verifikasi<input class="input input-lg center" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required autofocus style="font-size:28px;letter-spacing:.4em;font-weight:800"></label>
    <button class="btn btn-lg btn-block" type="submit">Verifikasi</button>
  </form>
  <form method="post" action="<?= e(turl('/daftar/kirim-ulang')) ?>" class="center" data-no-loading="1">
    <?= csrf_field() ?>
    <button class="btn btn-ghost" type="submit" id="resend" <?= $wait > 0 ? 'disabled' : '' ?> data-wait="<?= (int)$wait ?>">Kirim ulang kode<?= $wait > 0 ? ' (' . $wait . ' dtk)' : '' ?></button>
  </form>
  <form method="post" action="<?= e(turl('/keluar')) ?>" class="row" style="justify-content:center;gap:4px"><?= csrf_field() ?><span class="small muted">Nomor salah?</span><button class="btn btn-ghost btn-sm" type="submit">Daftar ulang</button></form>
</div>
<script>
(function () {
  var b = document.getElementById('resend'); var w = parseInt(b.dataset.wait, 10);
  if (w <= 0) return;
  var t = setInterval(function () { w--; if (w <= 0) { clearInterval(t); b.disabled = false; b.textContent = 'Kirim ulang kode'; } else b.textContent = 'Kirim ulang kode (' + w + ' dtk)'; }, 1000);
})();
</script>
