<div class="sheet-tools">
  <a class="btn btn-sm btn-secondary" href="<?= e($back) ?>"><?= icon('left', 16) ?>Kembali</a>
  <span class="grow small" style="color:var(--soft-on-navy)"><?= count($rows) ?> label QR aset · 6 × 3,2 cm. Tempel di bagian barang yang mudah dipindai.</span>
  <button class="btn btn-sm btn-gold" type="button" data-print><?= icon('printer', 16) ?>Cetak</button>
</div>
<div class="sheet">
  <?php if (!$rows): ?><div class="empty"><?= icon('qr', 36) ?>Tidak ada aset untuk dicetak.</div><?php endif; ?>
  <div class="labels">
    <?php foreach ($rows as $a): ?>
      <div class="label asset">
        <div class="qr" data-qr="<?= e(QR_ASSET_PREFIX . $a['code']) ?>" data-qr-label="QR <?= e($a['code']) ?>"></div>
        <div class="bc" style="align-items:flex-start;gap:1.2mm">
          <small style="text-align:left"><?= e(strtoupper(Tenant::shortName())) ?></small>
          <b style="font-size:2.8mm"><?= e($a['code']) ?></b>
          <span style="font-size:2.4mm;font-weight:700;line-height:1.2;max-height:6mm;overflow:hidden"><?= e($a['name']) ?></span>
          <span class="ttl" style="max-width:30mm"><?= e($a['location'] ?: $a['category']) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
