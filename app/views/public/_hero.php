<header class="hero">
  <div class="row-between">
    <a class="brand" href="<?= e(turl()) ?>" style="color:#fff"><?= brand_mark(36) ?><span class="brand-text"><span class="eyebrow">Perpustakaan</span><strong><?= e(Tenant::get('school_name')) ?></strong></span></a>
    <?php if (!empty($back)): ?><a href="<?= e($back) ?>" class="btn btn-sm btn-ghost" style="color:#fff" aria-label="Kembali"><?= icon('left', 20) ?></a><?php endif; ?>
  </div>
  <?php if (!empty($heading)): ?>
  <div class="stack-sm" style="gap:10px">
    <h1><?= e($heading) ?></h1>
    <?php if (!empty($lead)): ?><p><?= e($lead) ?></p><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($steps)): ?>
  <ol class="steps">
    <?php foreach ($steps as $i => $label): ?>
      <li class="<?= $i < ($step ?? 0) ? 'is-done' : ($i === ($step ?? 0) ? 'is-active' : '') ?>"><?= ($i + 1) . ' · ' . e($label) ?></li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>
</header>
