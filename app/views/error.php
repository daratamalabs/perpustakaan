<div class="landing">
  <div class="landing-inner" style="justify-content:center;max-width:720px">
    <span class="mark" style="width:56px;height:56px;border-radius:16px"><?= icon('logo', 30) ?></span>
    <div class="stack">
      <span class="eyebrow" style="color:var(--gold-500)">Kode <?= (int)$code ?></span>
      <h1 style="font-size:48px"><?= e($title) ?></h1>
      <p style="font-size:17px;color:var(--soft-on-navy);white-space:pre-line;line-height:1.6"><?= e($message) ?></p>
    </div>
    <div class="row">
      <a class="btn btn-gold" href="javascript:history.back()"><?= icon('left') ?>Kembali</a>
      <?php if (App::$tenant): ?><a class="btn btn-secondary" href="<?= e(turl()) ?>">Beranda perpustakaan</a><?php endif; ?>
    </div>
  </div>
</div>
