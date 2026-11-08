<?php
$q = ['jenis' => $type, 'bulan' => $ym];
$pct = fn($a, $b) => $b > 0 ? round($a / $b * 100) : 0;
?>
<div class="page-head">
  <div><span class="crumb">Lainnya</span><h1>Laporan</h1></div>
  <div class="page-actions">
    <a class="btn btn-secondary" href="<?= e(turl('/admin/laporan/ekspor') . qs([], $q)) ?>"><?= icon('download') ?>Ekspor Excel</a>
    <button class="btn" type="button" data-print><?= icon('pdf') ?>Cetak / PDF</button>
  </div>
</div>

<form class="card card-pad no-print" method="get" style="padding:16px 20px;display:flex;flex-wrap:wrap;gap:12px;align-items:center">
  <div class="pills grow" role="tablist" aria-label="Jenis laporan">
    <?php foreach (REPORT_TYPES as $k => [$lbl, $ic]): ?>
      <a class="pill <?= $type === $k ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $type === $k ? 'true' : 'false' ?>" href="<?= e(qs(['jenis' => $k], $q)) ?>"><?= icon($ic, 16) ?><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="jenis" value="<?= e($type) ?>">
  <select class="select" name="bulan" aria-label="Periode" data-autosubmit style="width:auto;font-weight:600"><?= select_options($months, $ym, true) ?></select>
</form>

<div class="stack-sm" style="gap:2px"><span class="eyebrow"><?= e($s['library_name']) ?></span><h2 style="font-size:22px">Laporan <?= e(strtolower(REPORT_TYPES[$type][0])) ?> · <?= e($label) ?></h2></div>

<?php if ($type === 'kunjungan'):
  $c = $d['counts']; $p = $d['prev'];
  $delta = (int)$p['total'] > 0 ? round(((int)$c['total'] - (int)$p['total']) / (int)$p['total'] * 100) : null;
  $schoolDays = count(array_filter($d['days'], fn($n) => $n > 0)) ?: count($d['days']);
  $avg = $schoolDays ? round((int)$c['total'] / $schoolDays) : 0;
  $maxDay = max(1, ...array_values($d['days'] ?: [0]));
  $peakHour = array_search(max($d['hourly'] ?: [0]), $d['hourly'] ?: [0]);
  $hTotal = max(1, array_sum($d['hourly']));
  $lvTotal = max(1, array_sum($d['levels']));
  $topClass = $d['classes'][0] ?? null; ?>
  <div class="grid-auto">
    <div class="stat"><span class="stat-label">Total kunjungan</span><span class="stat-value"><?= num($c['total']) ?></span><span class="stat-note" style="color:<?= $delta === null ? 'var(--muted)' : ($delta >= 0 ? 'var(--ok)' : 'var(--bad)') ?>"><?= $delta === null ? 'Belum ada data bulan lalu' : ($delta >= 0 ? '+' : '') . $delta . '% dari bulan sebelumnya' ?></span></div>
    <div class="stat"><span class="stat-label">Rata-rata per hari</span><span class="stat-value"><?= num($avg) ?></span><span class="stat-note muted">Dari <?= $schoolDays ?> hari layanan</span></div>
    <div class="stat"><span class="stat-label">Jam tersibuk</span><span class="stat-value"><?= $c['total'] ? str_pad((string)$peakHour, 2, '0', STR_PAD_LEFT) . '.00' : '—' ?></span><span class="stat-note muted">Rata-rata lama <?= num($c['avg_min']) ?> menit</span></div>
    <div class="stat"><span class="stat-label">Kelas teraktif</span><span class="stat-value"><?= e($topClass['kelas'] ?? '—') ?></span><span class="stat-note" style="color:var(--gold-700)"><?= $topClass ? num($topClass['n']) . ' kunjungan' : 'Belum ada data' ?></span></div>
  </div>

  <section class="card card-pad stack">
    <div class="row-between"><h2>Kunjungan harian</h2><div class="legend"><span><i style="background:var(--blue-600)"></i>Kunjungan</span><span><i style="background:var(--gold-500)"></i>Tertinggi</span><span><i style="width:16px;height:0;border-top:2px dashed var(--gold-700);border-radius:0"></i>Rata-rata <?= num($avg) ?></span></div></div>
    <?php if (!$d['days']): ?><div class="empty"><?= icon('report', 30) ?>Belum ada hari layanan di periode ini.</div><?php else: ?>
    <div class="table-wrap"><div style="min-width:<?= max(560, count($d['days']) * 34) ?>px">
      <div class="bars" style="gap:8px;height:230px">
        <span class="bars-avg" style="bottom:<?= round($avg / $maxDay * 170) ?>px" aria-hidden="true"></span>
        <?php foreach ($d['days'] as $day => $n): ?>
          <div class="bar"><span class="bar-val" style="font-size:11px"><?= $n ?: '' ?></span><div class="bar-stack" style="max-width:34px"><span style="height:<?= round($n / $maxDay * 170) ?>px;background:<?= $n === $maxDay && $n > 0 ? 'var(--gold-500)' : 'var(--blue-600)' ?>"></span></div></div>
        <?php endforeach; ?>
      </div>
      <div class="bars-labels" style="gap:8px"><?php foreach (array_keys($d['days']) as $day): ?><span title="<?= e(fmt_date($day, 'long')) ?>"><?= (int)substr($day, 8) ?></span><?php endforeach; ?></div>
    </div></div>
    <?php endif; ?>
  </section>

  <div class="split">
    <section class="card card-pad stack half">
      <h2>Komposisi pengunjung</h2>
      <?php $comp = [['Siswa', $c['siswa'], 'var(--navy-900)'], ['Guru & tendik', $c['staf'], 'var(--gold-500)'], ['Tamu', $c['tamu'], '#B9C3D3']]; ?>
      <div style="display:flex;height:16px;border-radius:999px;overflow:hidden;background:var(--mute-bg)"><?php foreach ($comp as [$l, $n, $col]): ?><span style="width:<?= $pct($n, $c['total']) ?>%;background:<?= $col ?>"></span><?php endforeach; ?></div>
      <div class="stack-sm"><?php foreach ($comp as [$l, $n, $col]): ?><div class="row" style="flex-wrap:nowrap"><span style="width:10px;height:10px;border-radius:3px;background:<?= $col ?>"></span><span class="grow"><?= e($l) ?></span><strong class="num"><?= num($n) ?></strong><span class="muted" style="width:44px;text-align:right"><?= $pct($n, $c['total']) ?>%</span></div><?php endforeach; ?></div>
      <div class="stack-sm" style="border-top:1px solid var(--line-2);padding-top:14px">
        <span class="small muted" style="font-weight:700">Siswa per tingkat</span>
        <?php foreach ($d['levels'] as $lvl => $n): ?><div class="rank-row"><strong style="width:40px"><?= e($lvl) ?></strong><span class="meter grow"><i style="width:<?= $pct($n, max($d['levels'] ?: [1]) ?: 1) ?>%;background:var(--navy-900)"></i></span><span class="muted num" style="width:44px;text-align:right"><?= $pct($n, $lvTotal) ?>%</span></div><?php endforeach; ?>
      </div>
      <div class="stack-sm" style="border-top:1px solid var(--line-2);padding-top:14px">
        <span class="small muted" style="font-weight:700">Cara tercatat</span>
        <div class="row"><?php foreach ($d['methods'] as $mm): ?><?= chip(label('method', $mm['method']) . ' · ' . num($mm['n']), LABELS['method_tone'][$mm['method']] ?? 'mute') ?><?php endforeach; ?></div>
      </div>
    </section>
    <section class="card card-pad stack half">
      <h2>Sebaran jam kunjungan</h2>
      <div class="bars" style="height:200px;gap:10px">
        <?php $hm = max(1, max($d['hourly'] ?: [0])); foreach ($d['hourly'] as $h => $n): ?>
          <div class="bar"><span class="bar-val"><?= $pct($n, $hTotal) ?>%</span><div class="bar-stack"><span style="height:<?= round($n / $hm * 140) ?>px;background:<?= $n === $hm && $n > 0 ? 'var(--gold-500)' : 'var(--blue-600)' ?>"></span></div></div>
        <?php endforeach; ?>
      </div>
      <div class="bars-labels" style="gap:10px"><?php foreach (array_keys($d['hourly']) as $h): ?><span><?= str_pad((string)$h, 2, '0', STR_PAD_LEFT) ?></span><?php endforeach; ?></div>
      <?php if ($d['purposes']): ?>
        <div class="stack-sm" style="border-top:1px solid var(--line-2);padding-top:14px"><span class="small muted" style="font-weight:700">Keperluan yang dicatat</span>
          <div class="row"><?php foreach ($d['purposes'] as $pp): ?><?= chip($pp['purpose'] . ' · ' . num($pp['n']), 'mute') ?><?php endforeach; ?></div></div>
      <?php endif; ?>
    </section>
    <section class="card card-pad stack half">
      <h2>Kelas teraktif</h2>
      <?php if (!$d['classes']): ?><div class="empty"><?= icon('report', 30) ?>Belum ada kunjungan siswa.</div><?php endif; ?>
      <?php $top = $d['classes'][0]['n'] ?? 1; foreach ($d['classes'] as $i => $cl): ?>
        <div class="rank-row"><span class="rank <?= $i === 0 ? 'first' : '' ?>"><?= $i + 1 ?></span><strong style="width:56px"><?= e($cl['kelas']) ?></strong><span class="meter grow"><i style="width:<?= $pct($cl['n'], $top) ?>%;<?= $i === 0 ? 'background:var(--gold-500)' : '' ?>"></i></span><strong class="num" style="width:40px;text-align:right"><?= num($cl['n']) ?></strong></div>
      <?php endforeach; ?>
      <p class="muted small">Bisa dijadikan dasar penghargaan kelas gemar membaca tiap bulan.</p>
    </section>
  </div>

<?php elseif ($type === 'peminjaman'): ?>
  <div class="grid-auto">
    <div class="stat"><span class="stat-label">Buku dipinjam</span><span class="stat-value"><?= num($d['borrowed']) ?></span><span class="stat-note muted">oleh <?= num($d['borrowers']) ?> peminjam</span></div>
    <div class="stat"><span class="stat-label">Dikembalikan</span><span class="stat-value"><?= num($d['returned']) ?></span><span class="stat-note" style="color:var(--warn)"><?= num($d['late_returns']) ?> terlambat</span></div>
    <div class="stat"><span class="stat-label">Denda periode ini</span><span class="stat-value" style="font-size:30px"><?= e(rupiah($d['fines'])) ?></span><span class="stat-note muted"><?= e(rupiah($d['fines_paid'])) ?> sudah dibayar</span></div>
    <div class="stat"><span class="stat-label">Masih dipinjam saat ini</span><span class="stat-value"><?= num($d['open']['open']) ?></span><span class="stat-note" style="color:var(--bad)"><?= num($d['open']['overdue']) ?> terlambat</span></div>
  </div>
  <div class="split">
    <section class="card half" style="overflow:hidden"><div class="card-head" style="padding-bottom:14px"><h2>Buku paling banyak dipinjam</h2></div>
      <div class="table-wrap"><table class="table"><thead><tr><th>#</th><th>Judul</th><th class="right">Dipinjam</th></tr></thead><tbody>
      <?php if (!$d['top_books']): ?><tr><td colspan="3"><div class="empty" style="padding:24px">Belum ada peminjaman.</div></td></tr><?php endif; ?>
      <?php foreach ($d['top_books'] as $i => $b): ?><tr><td class="muted"><?= $i + 1 ?></td><td><strong><?= e($b['title']) ?></strong><span class="sub"><?= e($b['author'] ?: '—') ?></span></td><td class="right num"><strong><?= num($b['n']) ?></strong></td></tr><?php endforeach; ?>
      </tbody></table></div></section>
    <section class="card half" style="overflow:hidden"><div class="card-head" style="padding-bottom:14px"><h2>Peminjam paling aktif</h2></div>
      <div class="table-wrap"><table class="table"><thead><tr><th>#</th><th>Nama</th><th class="right">Buku</th></tr></thead><tbody>
      <?php if (!$d['top_members']): ?><tr><td colspan="3"><div class="empty" style="padding:24px">Belum ada peminjaman.</div></td></tr><?php endif; ?>
      <?php foreach ($d['top_members'] as $i => $mm): ?><tr><td class="muted"><?= $i + 1 ?></td><td><strong><?= e($mm['name']) ?></strong><span class="sub"><?= e($mm['type'] === 'siswa' ? $mm['kelas'] : label('member_type', $mm['type'])) ?></span></td><td class="right num"><strong><?= num($mm['n']) ?></strong></td></tr><?php endforeach; ?>
      </tbody></table></div></section>
    <section class="card card-pad stack half"><h2>Per kategori</h2>
      <?php $tot = max(1, array_sum(array_column($d['by_category'], 'n'))); if (!$d['by_category']): ?><div class="empty">Belum ada data.</div><?php endif; ?>
      <?php foreach ($d['by_category'] as $r): ?><div class="rank-row"><strong style="width:120px" class="small"><?= e($r['category']) ?></strong><span class="meter grow"><i style="width:<?= $pct($r['n'], $tot) ?>%"></i></span><strong class="num" style="width:40px;text-align:right"><?= num($r['n']) ?></strong></div><?php endforeach; ?>
    </section>
  </div>

<?php elseif ($type === 'koleksi'):
  $titles = array_sum(array_column($d['by_category'], 'titles')); $copies = array_sum(array_column($d['by_category'], 'copies'));
  $bor = array_sum(array_column($d['by_category'], 'borrowed')); $dam = array_sum(array_column($d['by_category'], 'damaged')) + array_sum(array_column($d['by_category'], 'lost')); ?>
  <div class="grid-auto">
    <div class="stat"><span class="stat-label">Judul</span><span class="stat-value"><?= num($titles) ?></span><span class="stat-note" style="color:var(--ok)">+<?= num(count($d['new_books'])) ?> judul bulan ini</span></div>
    <div class="stat"><span class="stat-label">Eksemplar</span><span class="stat-value"><?= num($copies) ?></span><span class="stat-note" style="color:var(--ok)">+<?= num($d['new_copies']) ?> eksemplar bulan ini</span></div>
    <div class="stat"><span class="stat-label">Sedang dipinjam</span><span class="stat-value"><?= num($bor) ?></span><span class="stat-note muted"><?= $pct($bor, $copies) ?>% dari koleksi</span></div>
    <div class="stat"><span class="stat-label">Rusak atau hilang</span><span class="stat-value"><?= num($dam) ?></span><span class="stat-note muted"><?= num($d['never']) ?> judul belum pernah dipinjam</span></div>
  </div>
  <div class="split">
    <section class="card wide" style="overflow:hidden"><div class="card-head" style="padding-bottom:14px"><h2>Koleksi per kategori</h2></div>
      <div class="table-wrap"><table class="table"><thead><tr><th>Kategori</th><th class="right">Judul</th><th class="right">Eksemplar</th><th class="right">Dipinjam</th><th class="right">Rusak</th><th class="right">Hilang</th></tr></thead><tbody>
      <?php if (!$d['by_category']): ?><tr><td colspan="6"><div class="empty" style="padding:24px">Belum ada koleksi.</div></td></tr><?php endif; ?>
      <?php foreach ($d['by_category'] as $r): ?><tr><td><strong><?= e($r['category']) ?></strong></td><td class="right num"><?= num($r['titles']) ?></td><td class="right num"><?= num($r['copies']) ?></td><td class="right num"><?= num($r['borrowed']) ?></td><td class="right num"><?= num($r['damaged']) ?></td><td class="right num"><?= num($r['lost']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div></section>
    <section class="card card-pad stack side"><h2>Sumber eksemplar</h2>
      <?php $st = max(1, array_sum(array_column($d['by_source'], 'n'))); foreach ($d['by_source'] as $r): ?><div class="rank-row"><span class="small grow" style="font-weight:600"><?= e($r['source']) ?></span><span class="meter" style="width:90px"><i style="width:<?= $pct($r['n'], $st) ?>%"></i></span><strong class="num" style="width:44px;text-align:right"><?= num($r['n']) ?></strong></div><?php endforeach; ?>
    </section>
  </div>
  <section class="card" style="overflow:hidden"><div class="card-head" style="padding-bottom:14px"><h2>Buku baru bulan ini</h2><span class="muted small"><?= num(count($d['new_books'])) ?> judul</span></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Ditambahkan</th><th>Judul</th><th>Kategori</th><th>Rak</th><th class="right">Eksemplar</th></tr></thead><tbody>
    <?php if (!$d['new_books']): ?><tr><td colspan="5"><div class="empty" style="padding:24px">Tidak ada buku baru pada periode ini.</div></td></tr><?php endif; ?>
    <?php foreach ($d['new_books'] as $b): ?><tr><td class="muted small nowrap"><?= e(fmt_date($b['created_at'])) ?></td><td><strong><?= e($b['title']) ?></strong><span class="sub"><?= e($b['author'] ?: '—') ?></span></td><td><?= e($b['category']) ?></td><td class="mono small"><?= e($b['shelf'] ?: '—') ?></td><td class="right num"><?= num($b['copies']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>

<?php elseif ($type === 'aset'):
  $n = array_sum(array_column($d['by_category'], 'n')); $val = array_sum(array_column($d['by_category'], 'v'));
  $rusak = array_sum(array_column($d['by_category'], 'rusak')); $fix = array_sum(array_column($d['by_category'], 'perbaikan')); ?>
  <div class="grid-auto">
    <div class="stat"><span class="stat-label">Aset aktif</span><span class="stat-value"><?= num($n) ?></span><span class="stat-note muted"><?= $pct($n - $rusak - $fix, $n) ?>% kondisi baik</span></div>
    <div class="stat"><span class="stat-label">Nilai perolehan</span><span class="stat-value" style="font-size:28px"><?= e(rupiah($val)) ?></span><span class="stat-note muted">Total aset aktif</span></div>
    <div class="stat"><span class="stat-label">Rusak / perbaikan</span><span class="stat-value"><?= num($rusak + $fix) ?></span><span class="stat-note" style="color:var(--warn)"><?= num($fix) ?> sedang diperbaiki</span></div>
    <div class="stat"><span class="stat-label">Biaya perawatan</span><span class="stat-value" style="font-size:28px"><?= e(rupiah($d['cost'])) ?></span><span class="stat-note muted"><?= num(count($d['logs'])) ?> catatan bulan ini</span></div>
  </div>
  <div class="split">
    <section class="card wide" style="overflow:hidden"><div class="card-head" style="padding-bottom:14px"><h2>Aset per kategori</h2></div>
      <div class="table-wrap"><table class="table"><thead><tr><th>Kategori</th><th class="right">Jumlah</th><th class="right">Nilai</th><th class="right">Baik</th><th class="right">Rusak</th><th class="right">Perbaikan</th></tr></thead><tbody>
      <?php if (!$d['by_category']): ?><tr><td colspan="6"><div class="empty" style="padding:24px">Belum ada aset.</div></td></tr><?php endif; ?>
      <?php foreach ($d['by_category'] as $r): ?><tr><td><strong><?= e($r['category']) ?></strong></td><td class="right num"><?= num($r['n']) ?></td><td class="right num nowrap"><?= e(rupiah($r['v'])) ?></td><td class="right num"><?= num($r['baik']) ?></td><td class="right num"><?= num($r['rusak']) ?></td><td class="right num"><?= num($r['perbaikan']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div></section>
    <section class="card card-pad stack side"><h2>Per lokasi</h2>
      <?php $lt = max(1, $n); foreach ($d['by_location'] as $r): ?><div class="rank-row"><span class="small grow" style="font-weight:600"><?= e($r['location']) ?></span><span class="meter" style="width:90px"><i style="width:<?= $pct($r['n'], $lt) ?>%"></i></span><strong class="num" style="width:36px;text-align:right"><?= num($r['n']) ?></strong></div><?php endforeach; ?>
    </section>
  </div>
  <section class="card" style="overflow:hidden"><div class="card-head" style="padding-bottom:14px"><h2>Catatan perawatan</h2></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Tanggal</th><th>Barang</th><th>Catatan</th><th class="right">Biaya</th></tr></thead><tbody>
    <?php if (!$d['logs']): ?><tr><td colspan="4"><div class="empty" style="padding:24px">Tidak ada catatan perawatan pada periode ini.</div></td></tr><?php endif; ?>
    <?php foreach ($d['logs'] as $l): ?><tr><td class="nowrap small"><?= e(fmt_date($l['date'])) ?></td><td><strong><?= e($l['name']) ?></strong><span class="sub mono" style="font-size:12px"><?= e($l['code']) ?></span></td><td class="small"><?= e($l['text']) ?></td><td class="right num nowrap"><?= $l['cost'] !== null ? e(rupiah($l['cost'])) : '—' ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>

<?php else:
  $byStatus = []; foreach ($d['by_status'] as $r) $byStatus[$r['status']][$r['type']] = (int)$r['n'];
  $active = array_sum($byStatus['active'] ?? []); ?>
  <div class="grid-auto">
    <div class="stat"><span class="stat-label">Member aktif</span><span class="stat-value"><?= num($active) ?></span><span class="stat-note muted"><?= num($byStatus['active']['siswa'] ?? 0) ?> siswa · <?= num(($byStatus['active']['guru'] ?? 0) + ($byStatus['active']['tendik'] ?? 0)) ?> guru &amp; tendik</span></div>
    <div class="stat"><span class="stat-label">Pendaftar baru</span><span class="stat-value"><?= num($d['new']) ?></span><span class="stat-note" style="color:var(--ok)"><?= num($d['approved']) ?> disetujui bulan ini</span></div>
    <div class="stat"><span class="stat-label">Member yang berkunjung</span><span class="stat-value"><?= num($d['active_visitors']) ?></span><span class="stat-note muted"><?= $pct($d['active_visitors'], $active) ?>% dari member aktif</span></div>
    <div class="stat"><span class="stat-label">Menunggu persetujuan</span><span class="stat-value"><?= num(array_sum($byStatus['pending'] ?? [])) ?></span><span class="stat-note muted"><?= num(array_sum($byStatus['graduated'] ?? [])) ?> alumni · <?= num(array_sum($byStatus['inactive'] ?? [])) ?> nonaktif</span></div>
  </div>
  <section class="card" style="overflow:hidden"><div class="card-head" style="padding-bottom:14px"><h2>Member aktif per kelas</h2><span class="muted small">Dibandingkan jumlah siswa di data induk</span></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Kelas</th><th class="right">Member aktif</th><th class="right">Siswa di data induk</th><th style="width:40%">Cakupan</th></tr></thead><tbody>
    <?php if (!$d['by_kelas']): ?><tr><td colspan="4"><div class="empty" style="padding:24px">Belum ada siswa yang menjadi member.</div></td></tr><?php endif; ?>
    <?php foreach ($d['by_kelas'] as $r): $cov = $r['ref'] ? min(100, $pct($r['n'], $r['ref'])) : null; ?>
      <tr><td><strong><?= e($r['kelas']) ?></strong></td><td class="right num"><?= num($r['n']) ?></td><td class="right num"><?= $r['ref'] ? num($r['ref']) : '—' ?></td>
        <td><?php if ($cov !== null): ?><div class="row" style="flex-wrap:nowrap"><span class="meter grow"><i style="width:<?= $cov ?>%"></i></span><span class="small num" style="width:40px"><?= $cov ?>%</span></div><?php else: ?><span class="muted small">Impor data induk untuk melihat cakupan</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></section>
<?php endif; ?>
<p class="muted tiny">Dicetak <?= e(fmt_date(today(), 'long')) ?> pukul <?= date('H.i') ?> oleh <?= e(Auth::staff()['name']) ?>.</p>
