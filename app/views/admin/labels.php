<div class="sheet-tools">
  <a class="btn btn-sm btn-secondary" href="<?= e($back) ?>"><?= icon('left', 16) ?>Kembali</a>
  <span class="grow small" style="color:var(--soft-on-navy)"><?= count($copies) ?> label · 6 × 3,2 cm. Gunakan kertas label A4 atau kertas biasa lalu gunting.</span>
  <button class="btn btn-sm btn-gold" type="button" data-print><?= icon('printer', 16) ?>Cetak</button>
</div>
<div class="sheet">
  <?php if (!$copies): ?><div class="empty"><?= icon('printer', 36) ?>Tidak ada eksemplar untuk dicetak.</div><?php endif; ?>
  <div class="labels">
    <?php foreach ($copies as $c): $parts = array_slice(explode(' ', trim((string)($c['call_number'] ?: call_number($c['ddc'], $c['author'], $c['title'])))), 0, 4); ?>
      <div class="label">
        <div class="call"><?php foreach ($parts ?: ['—'] as $p): ?><span><?= e($p) ?></span><?php endforeach; ?></div>
        <div class="bc">
          <small><?= e(strtoupper(Tenant::shortName())) ?></small>
          <svg data-barcode="<?= e($c['code']) ?>" data-h="40" data-w="1.4"></svg>
          <b><?= e($c['code']) ?></b>
          <span class="ttl"><?= e($c['title']) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
