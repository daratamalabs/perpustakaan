<?php
$diff = (int)$counts['total'] - (int)$prevCounts['total'];
$max = 10;
foreach ($days as $d) $max = max($max, $daily[$d]['total'] ?? 0);
$k = 190 / $max;
$topClass = $classes[0]['n'] ?? 1;
?>
<div class="page-head">
  <div><span class="crumb"><?= e(fmt_date(today(), 'long')) ?> · <?= date('H.i') ?></span><h1>Dashboard</h1></div>
  <div class="page-actions">
    <a class="btn btn-secondary" href="<?= e(display_url()) ?>" target="_blank" rel="noopener"><?= icon('display') ?>Buka layar display</a>
    <a class="btn" href="<?= e(turl('/admin/kunjungan')) ?>#manual"><?= icon('plus', 16) ?>Input kunjungan</a>
  </div>
</div>

<?php if ((int)$pending['n'] > 0 && Auth::can('member')): ?>
  <div class="alert alert-gold" style="align-items:center">
    <span class="stat-icon" style="background:#fff;color:var(--gold-700)"><?= icon('userplus') ?></span>
    <span class="grow"><b><?= (int)$pending['n'] ?> pendaftar baru menunggu persetujuan.</b> Pendaftar paling lama masuk <?= e(fmt_date($pending['oldest'], 'full')) ?>.</span>
    <a href="<?= e(turl('/admin/member')) ?>">Tinjau sekarang</a>
  </div>
<?php endif; ?>

<div class="grid-auto">
  <div class="stat">
    <div class="stat-top"><span class="stat-label">Pengunjung hari ini</span><span class="stat-icon" style="background:var(--blue-100);color:var(--blue-600)"><?= icon('users') ?></span></div>
    <span class="stat-value"><?= num($counts['total']) ?></span>
    <span class="stat-note" style="color:<?= $diff >= 0 ? 'var(--ok)' : 'var(--muted)' ?>"><?= ($diff >= 0 ? '+' : '') . num($diff) ?> dari hari sekolah sebelumnya</span>
  </div>
  <div class="stat">
    <div class="stat-top"><span class="stat-label">Sedang di perpustakaan</span><span class="stat-icon" style="background:var(--gold-100);color:var(--gold-700)"><?= icon('clock') ?></span></div>
    <span class="stat-value"><?= num($inside) ?></span>
    <span class="stat-note muted"><?= $peak !== null ? 'Paling ramai sekitar pukul ' . str_pad((string)$peak, 2, '0', STR_PAD_LEFT) . '.00' : 'Belum ada kunjungan' ?></span>
  </div>
  <div class="stat">
    <div class="stat-top"><span class="stat-label">Buku sedang dipinjam</span><span class="stat-icon" style="background:var(--blue-100);color:var(--blue-600)"><?= icon('book') ?></span></div>
    <span class="stat-value"><?= num($loans['open']) ?></span>
    <span class="stat-note" style="color:var(--warn)"><?= num($loans['due_week']) ?> jatuh tempo 7 hari ke depan</span>
  </div>
  <div class="stat">
    <div class="stat-top"><span class="stat-label">Terlambat dikembalikan</span><span class="stat-icon" style="background:var(--bad-bg);color:var(--bad)"><?= icon('alert') ?></span></div>
    <span class="stat-value"><?= num($loans['overdue']) ?></span>
    <?php if ($loans['overdue'] > 0): ?><a class="stat-note" style="color:var(--bad)" href="<?= e(turl('/admin/sirkulasi?filter=terlambat')) ?>">Lihat & kirim pengingat</a><?php else: ?><span class="stat-note muted">Semua tepat waktu</span><?php endif; ?>
  </div>
</div>

<div class="split">
  <section class="card card-pad stack half" style="flex:2 1 560px">
    <div class="row-between"><h2>Kunjungan <?= count($days) ?> hari layanan terakhir</h2>
      <div class="legend"><span><i style="background:var(--navy-900)"></i>Siswa</span><span><i style="background:var(--gold-500)"></i>Guru &amp; tendik</span><span><i style="background:#B9C3D3"></i>Tamu</span></div>
    </div>
    <div>
      <div class="bars">
        <?php foreach ($days as $d): $x = $daily[$d] ?? ['siswa' => 0, 'staf' => 0, 'tamu' => 0, 'total' => 0]; $isToday = $d === today(); ?>
          <div class="bar">
            <span class="bar-val" style="color:<?= $isToday ? 'var(--blue-600)' : 'var(--ink)' ?>"><?= num($x['total']) ?></span>
            <div class="bar-stack" title="Siswa <?= $x['siswa'] ?>, guru & tendik <?= $x['staf'] ?>, tamu <?= $x['tamu'] ?>">
              <span style="height:<?= round($x['tamu'] * $k) ?>px;background:#B9C3D3"></span>
              <span style="height:<?= round($x['staf'] * $k) ?>px;background:var(--gold-500)"></span>
              <span style="height:<?= round($x['siswa'] * $k) ?>px;background:<?= $isToday ? 'var(--blue-600)' : 'var(--navy-900)' ?>"></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="bars-labels"><?php foreach ($days as $d): ?><span style="<?= $d === today() ? 'color:var(--blue-600);font-weight:800' : '' ?>"><?= $d === today() ? 'Hari ini' : e(day_name($d, true) . ' ' . (int)substr($d, 8)) ?></span><?php endforeach; ?></div>
    </div>
  </section>
  <section class="card card-pad stack side">
    <h2>Kelas teraktif minggu ini</h2>
    <?php if (!$classes): ?><div class="empty"><?= icon('report', 30) ?>Belum ada kunjungan siswa minggu ini.</div><?php endif; ?>
    <?php foreach ($classes as $i => $c): ?>
      <div class="rank-row">
        <span class="rank <?= $i === 0 ? 'first' : '' ?>"><?= $i + 1 ?></span>
        <strong style="width:56px"><?= e($c['kelas']) ?></strong>
        <span class="meter grow"><i style="width:<?= round($c['n'] / $topClass * 100) ?>%;<?= $i === 0 ? 'background:var(--gold-500)' : '' ?>"></i></span>
        <strong class="num" style="width:36px;text-align:right"><?= num($c['n']) ?></strong>
      </div>
    <?php endforeach; ?>
    <p class="muted small">Papan ini juga tampil di layar display untuk memacu minat baca antarkelas.</p>
  </section>
</div>

<div class="split">
  <section class="card half" style="padding-bottom:8px">
    <div class="card-head" style="padding-bottom:12px"><h2>Kunjungan terbaru</h2><a class="small" style="font-weight:700;text-decoration:none" href="<?= e(turl('/admin/kunjungan')) ?>">Lihat semua</a></div>
    <?php if (!$recent): ?><div class="empty"><?= icon('users', 30) ?>Belum ada kunjungan.</div><?php endif; ?>
    <?php foreach ($recent as $v): ?>
      <div class="list-row">
        <span class="num small" style="width:44px;font-weight:700;color:var(--muted)"><?= e(fmt_time($v['check_in'])) ?></span>
        <?= avatar_html($v['name'], null, $v['category'] === 'tamu' ? 'avatar-mute' : (in_array($v['category'], ['guru', 'tendik'], true) ? 'avatar-gold' : '')) ?>
        <div class="grow"><strong><?= e($v['name']) ?></strong><span><?= e(visit_meta($v)) ?><?= substr($v['check_in'], 0, 10) !== today() ? ' · ' . e(fmt_date($v['check_in'], 'short')) : '' ?></span></div>
        <?= chip(label('method', $v['method']), LABELS['method_tone'][$v['method']]) ?>
      </div>
    <?php endforeach; ?>
  </section>
  <section class="card half" style="padding-bottom:8px">
    <div class="card-head" style="padding-bottom:12px"><h2>Perlu ditindak</h2><a class="small" style="font-weight:700;text-decoration:none" href="<?= e(turl('/admin/sirkulasi')) ?>">Buka sirkulasi</a></div>
    <?php if (!$todo): ?><div class="empty"><?= icon('check', 30) ?>Tidak ada pinjaman yang jatuh tempo dalam 3 hari.</div><?php endif; ?>
    <?php foreach ($todo as $l): [$st, $tone] = loan_status($l); ?>
      <div class="list-row">
        <div class="grow"><strong><?= e($l['name']) ?> <span style="font-weight:500;color:var(--muted)">· <?= e($l['type'] === 'siswa' ? $l['kelas'] : label('member_type', $l['type'])) ?></span></strong><span><?= e($l['title']) ?> · jatuh tempo <?= e(fmt_date($l['due_date'], 'short')) ?></span></div>
        <?= chip($st, $tone) ?>
        <?php if (Auth::can('sirkulasi')): ?>
          <form method="post" action="<?= e(turl('/admin/sirkulasi/' . $l['id'] . '/ingatkan')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="dashboard"><button class="btn btn-sm btn-secondary" type="submit" <?= $l['phone'] ? '' : 'disabled title="Nomor WhatsApp kosong"' ?>>Ingatkan</button></form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
</div>
