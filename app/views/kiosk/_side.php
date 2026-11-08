<section class="kiosk-side">
  <?= brand_block('Perpustakaan', Tenant::get('school_name'), 48) ?>
  <div class="stack" style="gap:24px">
    <?= $middle ?>
  </div>
  <div class="stack-sm" style="gap:4px">
    <span class="clock" data-clock><?= date('H.i') ?></span>
    <span style="font-size:16px;font-weight:600;color:var(--soft-on-navy)" data-date><?= e(fmt_date(today(), 'long')) ?></span>
    <?php $h = $open['hours'] ?? []; ?>
    <span style="font-size:14px;font-weight:600;color:var(--gold-500);margin-top:6px"><?= !empty($h['open']) ? ($open['open'] ? 'Layanan buka hingga ' . e(str_replace(':', '.', $h['to'])) : 'Jam layanan ' . e(str_replace(':', '.', $h['from'])) . '–' . e(str_replace(':', '.', $h['to']))) : 'Hari ini tidak ada layanan' ?></span>
  </div>
</section>
