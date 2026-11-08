<?php
$first = explode(' ', trim($m['name']))[0];
$dueSoon = array_values(array_filter($loans, fn($l) => diff_days(today(), $l['due_date']) <= 1));
?>
<header class="row-between" style="padding:24px 20px 8px">
  <div class="stack-sm" style="gap:4px">
    <span class="small muted" style="font-weight:600"><?= e(fmt_date(today(), 'long')) ?></span>
    <h1 class="serif" style="font-size:32px;line-height:1.1">Halo, <?= e($first) ?></h1>
  </div>
  <a href="<?= e(turl('/kartu')) ?>" class="mark" style="width:48px;height:48px;border-radius:14px;background:var(--navy-900)" aria-label="Buka kartu QR"><?= icon('qr', 22) ?></a>
</header>
<div class="public-body" style="padding-top:16px;gap:22px">
  <?= flashes_html() ?>
  <?php if ($m['status'] !== 'active'): ?>
    <div class="alert alert-gold"><?= icon('clock') ?><span>Pendaftaranmu masih menunggu persetujuan petugas.</span></div>
  <?php endif; ?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
    <div style="background:var(--navy-900);color:#fff;border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:6px">
      <span class="small" style="font-weight:600;color:var(--soft-on-navy)">Kunjungan bulan ini</span>
      <span style="font-size:32px;font-weight:800;line-height:1"><?= num($monthCount) ?></span>
      <span class="small" style="font-weight:600;color:var(--gold-500)">Ke-<?= num($semesterCount) ?> semester ini</span>
    </div>
    <div class="card" style="padding:16px;display:flex;flex-direction:column;gap:6px">
      <span class="small muted" style="font-weight:600">Buku dipinjam</span>
      <span style="font-size:32px;font-weight:800;line-height:1"><?= count($loans) ?><span style="font-size:16px;font-weight:600;color:var(--muted)"> / <?= (int)$rules['max'] ?></span></span>
      <span class="small muted" style="font-weight:600">Batas pinjam</span>
    </div>
  </div>
  <?php if ($unpaid > 0): ?>
    <div class="alert alert-bad"><?= icon('alert') ?><span>Ada denda <b><?= rupiah($unpaid) ?></b> yang belum dibayar. Lunasi di meja sirkulasi agar bisa meminjam lagi.</span></div>
  <?php endif; ?>
  <?php foreach ($dueSoon as $l): $d = diff_days(today(), $l['due_date']); ?>
    <div class="alert <?= $d < 0 ? 'alert-bad' : 'alert-warn' ?>"><?= icon('clock') ?><span><b><?= e($l['title']) ?></b> <?= $d < 0 ? 'terlambat ' . (-$d) . ' hari.' : ($d === 0 ? 'jatuh tempo hari ini.' : 'jatuh tempo besok, ' . e(fmt_date($l['due_date'], 'long')) . '.') ?></span></div>
  <?php endforeach; ?>

  <section class="stack-sm" style="gap:12px">
    <h2 style="font-size:17px">Sedang dipinjam</h2>
    <?php if (!$loans): ?><div class="card empty" style="padding:24px"><?= icon('book', 30) ?>Belum ada buku yang dipinjam.</div><?php endif; ?>
    <?php foreach ($loans as $l): [$st, $tone] = loan_status($l); $canExtend = $l['collection_type'] !== 'paket' && (int)$l['extended'] < (int)$rules['extensions'] && $l['due_date'] >= today(); ?>
      <div class="card" style="padding:12px;display:flex;gap:14px;align-items:center">
        <?= cover_html($l, 48, 66) ?>
        <div class="grow stack-sm" style="gap:3px;min-width:0">
          <strong style="font-size:15px"><?= e($l['title']) ?></strong>
          <span class="small muted"><?= e($l['author'] ?: '') ?></span>
          <div class="row" style="gap:8px;margin-top:2px"><?= chip($l['collection_type'] === 'paket' ? 'Buku paket · ' . fmt_date($l['due_date']) : 'Kembali ' . day_name($l['due_date'], true) . ', ' . fmt_date($l['due_date'], 'short'), $tone) ?></div>
        </div>
        <?php if ($canExtend): ?>
          <form method="post" action="<?= e(turl('/pinjaman/' . $l['id'] . '/perpanjang')) ?>" data-confirm="Perpanjang peminjaman <?= e($l['title']) ?> <?= (int)$rules['days'] ?> hari?"><?= csrf_field() ?><button class="btn btn-sm btn-secondary" type="submit">Perpanjang</button></form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="stack-sm" style="gap:12px">
    <h2 style="font-size:17px">Riwayat kunjungan</h2>
    <div class="card" style="overflow:hidden">
      <?php if (!$visits): ?><div class="empty" style="padding:24px"><?= icon('clock', 30) ?>Belum ada kunjungan tercatat.</div><?php endif; ?>
      <?php foreach ($visits as $i => $v):
        $isToday = substr($v['check_in'], 0, 10) === today();
        $dur = $v['check_out'] ? fmt_duration(minutes_between($v['check_in'], $v['check_out'])) : ($isToday ? 'Di dalam' : '—'); ?>
        <div style="display:flex;align-items:center;gap:14px;padding:14px 16px;<?= $i ? 'border-top:1px solid var(--line-2)' : '' ?>">
          <div style="width:44px;height:44px;border-radius:12px;background:<?= $isToday ? 'var(--navy-900)' : 'var(--paper-3)' ?>;color:<?= $isToday ? '#fff' : 'var(--ink)' ?>;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0">
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em"><?= e(day_name($v['check_in'], true)) ?></span>
            <span style="font-size:16px;font-weight:800;line-height:1.1"><?= (int)date('j', ts($v['check_in'])) ?></span>
          </div>
          <div class="grow stack-sm" style="gap:2px;min-width:0">
            <strong style="font-size:14px"><?= e(fmt_time($v['check_in'])) ?> – <?= $v['check_out'] ? e(fmt_time($v['check_out'])) : ($isToday ? 'sekarang' : '?') ?></strong>
            <span class="small muted"><?= e(fmt_date($v['check_in'], 'month')) ?> · <?= $v['method'] === 'qr' ? 'Kartu QR' : 'Dicatat petugas' ?></span>
          </div>
          <span class="small" style="font-weight:700;color:<?= $dur === 'Di dalam' ? 'var(--ok)' : 'var(--muted)' ?>"><?= e($dur) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($history): ?>
  <section class="stack-sm" style="gap:12px">
    <h2 style="font-size:17px">Pernah dipinjam</h2>
    <div class="card" style="overflow:hidden">
      <?php foreach ($history as $i => $hst): ?>
        <div class="row-between" style="padding:12px 16px;<?= $i ? 'border-top:1px solid var(--line-2)' : '' ?>">
          <span class="small" style="font-weight:600"><?= e($hst['title']) ?></span>
          <span class="tiny muted"><?= e(fmt_date($hst['returned_at'])) ?><?= (int)$hst['fine_amount'] > 0 ? ' · denda ' . rupiah($hst['fine_amount']) . ($hst['fine_paid'] ? ' (lunas)' : '') : '' ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>
