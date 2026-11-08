<?php
$kondisi = (string)g('kondisi');
$cards = [
    ['', 'Total aset', $sum['total'], 'var(--navy-900)'],
    ['baik', 'Kondisi baik', $sum['baik'], 'var(--ok)'],
    ['rusak_ringan', 'Rusak ringan', $sum['rusak_ringan'], 'var(--gold-500)'],
    ['rusak_berat', 'Rusak berat', $sum['rusak_berat'], 'var(--bad)'],
    ['perbaikan', 'Dalam perbaikan', $sum['perbaikan'], 'var(--blue-600)'],
];
?>
<div class="page-head">
  <div><span class="crumb">Koleksi</span><h1>Aset &amp; inventaris</h1></div>
  <div class="page-actions">
    <a class="btn btn-secondary" href="<?= e(turl('/admin/aset/ekspor')) ?>"><?= icon('download') ?>Ekspor</a>
    <a class="btn btn-secondary" href="<?= e(turl('/admin/aset/label')) ?>" target="_blank"><?= icon('qr') ?>Cetak semua label QR</a>
    <a class="btn" href="<?= e(turl('/admin/aset/baru')) ?>"><?= icon('plus', 16) ?>Tambah aset</a>
  </div>
</div>

<div class="grid-auto" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
  <?php foreach ($cards as [$k, $lbl, $n, $c]): ?>
    <a class="stat" href="<?= e(qs(['kondisi' => $k ?: null, 'hal' => null, 'id' => null])) ?>" style="flex-direction:row;align-items:center;gap:14px;padding:16px 18px;text-decoration:none;color:inherit;<?= $kondisi === $k ? 'border-color:var(--blue-600);box-shadow:0 0 0 2px var(--blue-100)' : '' ?>">
      <span style="width:10px;height:40px;border-radius:999px;background:<?= $c ?>;flex-shrink:0"></span>
      <span class="stack-sm" style="gap:2px"><strong style="font-size:26px;line-height:1.1" class="num"><?= num($n) ?></strong><span class="stat-label"><?= e($lbl) ?></span></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="split">
  <section class="card wide" style="overflow:hidden">
    <form class="card-toolbar" method="get">
      <?php if ($kondisi !== ''): ?><input type="hidden" name="kondisi" value="<?= e($kondisi) ?>"><?php endif; ?>
      <label class="input-icon"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e(g('q')) ?>" placeholder="Cari nama, kode, atau spesifikasi" aria-label="Cari aset"></label>
      <select class="select" name="kategori" aria-label="Kategori" data-autosubmit style="width:auto"><option value="">Semua kategori</option><?= select_options($s['asset_categories'], g('kategori')) ?></select>
      <select class="select" name="lokasi" aria-label="Lokasi" data-autosubmit style="width:auto"><option value="">Semua lokasi</option><?= select_options($locations, g('lokasi')) ?></select>
      <button class="btn btn-secondary" type="submit">Cari</button>
    </form>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Kode</th><th>Nama barang</th><th>Lokasi</th><th>Perolehan</th><th>Kondisi</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="5"><div class="empty"><?= icon('asset', 34) ?><?= g('q') || $kondisi ? 'Tidak ada aset yang cocok.' : 'Belum ada aset. Catat komputer, rak, meja, AC, dan perlengkapan lain di sini.' ?></div></td></tr><?php endif; ?>
        <?php foreach ($rows as $a): $isSel = $sel && (int)$sel['id'] === (int)$a['id']; ?>
          <tr class="<?= $isSel ? 'is-selected' : '' ?>" style="<?= $isSel ? 'box-shadow:inset 3px 0 0 var(--blue-600)' : '' ?>">
            <td class="mono small" style="font-weight:600"><?= e($a['code']) ?></td>
            <td><a href="<?= e(qs(['id' => $a['id']])) ?>" style="color:inherit;text-decoration:none"><strong><?= e($a['name']) ?></strong><span class="sub"><?= e($a['category']) ?></span></a></td>
            <td><?= e($a['location'] ?: '—') ?></td>
            <td><strong style="font-weight:600"><?= $a['acquired_at'] ? e(substr($a['acquired_at'], 0, 4)) : '—' ?></strong><span class="sub"><?= e($a['fund_source'] ?: '') ?></span></td>
            <td><?= chip(label('kondisi', $a['kondisi']), LABELS['kondisi_tone'][$a['kondisi']] ?? 'mute') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pager_html($pg, 'aset') ?>
    <div class="card-foot" style="background:var(--paper-2)"><span>Total nilai perolehan aset aktif: <b style="color:var(--ink)"><?= e(rupiah($sum['value'])) ?></b></span>
      <?php if ($sum['dihapus']): ?><a href="<?= e(qs(['kondisi' => 'dihapus', 'id' => null])) ?>" style="font-weight:700;text-decoration:none"><?= num($sum['dihapus']) ?> aset dihapuskan</a><?php endif; ?></div>
  </section>

  <?php if ($sel): ?>
  <aside class="card card-pad stack side" aria-label="Detail aset" style="gap:18px">
    <div class="row-between" style="align-items:flex-start">
      <div class="stack-sm" style="gap:4px"><span class="mono small muted" style="font-weight:600"><?= e($sel['code']) ?></span><h2 style="font-size:20px;font-weight:800"><?= e($sel['name']) ?></h2></div>
      <?= chip(label('kondisi', $sel['kondisi']), LABELS['kondisi_tone'][$sel['kondisi']] ?? 'mute') ?>
    </div>
    <div class="row" style="flex-wrap:nowrap;align-items:stretch">
      <div class="grow" style="height:120px;border-radius:12px;background:var(--paper-3);display:flex;align-items:center;justify-content:center;overflow:hidden;color:var(--muted)">
        <?php if ($sel['photo']): ?><img src="<?= e(media_url($sel['photo'])) ?>" alt="Foto <?= e($sel['name']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?><span class="stack-sm" style="align-items:center;gap:6px"><?= icon('camera', 28) ?><span class="tiny" style="font-weight:600">Belum ada foto</span></span><?php endif; ?>
      </div>
      <div style="width:120px;border:1px solid var(--line);border-radius:12px;padding:10px;display:flex;flex-direction:column;align-items:center;gap:6px;flex-shrink:0">
        <div data-qr="<?= e(QR_ASSET_PREFIX . $sel['code']) ?>" data-qr-label="QR aset <?= e($sel['code']) ?>" style="width:84px;height:84px"></div>
        <a class="tiny" style="font-weight:700;text-decoration:none" href="<?= e(turl('/admin/aset/label?ids=' . $sel['id'])) ?>" target="_blank">Cetak label</a>
      </div>
    </div>
    <dl class="dl">
      <dt>Kategori</dt><dd><?= e($sel['category']) ?></dd>
      <dt>Lokasi</dt><dd><?= e($sel['location'] ?: '—') ?></dd>
      <?php if ($sel['spec']): ?><dt>Spesifikasi</dt><dd><?= e($sel['spec']) ?></dd><?php endif; ?>
      <dt>Tanggal perolehan</dt><dd><?= e($sel['acquired_at'] ? fmt_date($sel['acquired_at'], 'full') : '—') ?></dd>
      <dt>Nilai perolehan</dt><dd><?= $sel['value'] !== null ? e(rupiah($sel['value'])) : '—' ?></dd>
      <dt>Sumber dana</dt><dd><?= e($sel['fund_source'] ?: '—') ?></dd>
      <?php if ($sel['notes']): ?><dt>Catatan</dt><dd style="font-weight:500"><?= e($sel['notes']) ?></dd><?php endif; ?>
    </dl>
    <div class="stack-sm" style="border-top:1px solid var(--line-2);padding-top:16px;gap:12px">
      <strong style="font-size:14px">Riwayat perawatan</strong>
      <?php if (!$logs): ?><span class="muted small">Belum ada catatan.</span><?php endif; ?>
      <?php foreach ($logs as $i => $l): ?>
        <div class="row" style="align-items:flex-start;flex-wrap:nowrap;gap:12px">
          <span style="width:10px;height:10px;border-radius:999px;margin-top:5px;flex-shrink:0;background:<?= $i === 0 ? 'var(--blue-600)' : '#C9C3B5' ?>"></span>
          <span class="stack-sm" style="gap:2px"><strong class="small"><?= e(fmt_date($l['date'], 'full')) ?><?= $l['cost'] !== null ? ' · ' . e(rupiah($l['cost'])) : '' ?></strong><span class="small muted" style="line-height:1.5"><?= e($l['text']) ?><?= $l['staff'] ? ' · ' . e($l['staff']) : '' ?></span></span>
        </div>
      <?php endforeach; ?>
    </div>
    <details class="stack-sm" <?= g('log') ? 'open' : '' ?>>
      <summary class="btn btn-navy btn-block" style="list-style:none"><?= icon('wrench' , 16) ?>Catat perawatan</summary>
      <form method="post" action="<?= e(turl('/admin/aset/' . $sel['id'] . '/log')) ?>" class="stack" style="margin-top:14px;gap:12px">
        <?= csrf_field() ?>
        <label class="field">Catatan<textarea class="textarea" name="text" rows="2" placeholder="Mis. dibawa ke teknisi, ganti kipas"></textarea></label>
        <div class="grid-2" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
          <label class="field">Tanggal<input class="input" type="date" name="date" value="<?= e(today()) ?>"></label>
          <label class="field"><span>Biaya <span class="opt">(opsional)</span></span><input class="input" name="cost" inputmode="numeric" placeholder="Rp"></label>
        </div>
        <label class="field">Ubah kondisi<select class="select" name="kondisi"><option value="">Tetap: <?= e(label('kondisi', $sel['kondisi'])) ?></option><?= select_options(array_intersect_key(LABELS['kondisi'], array_flip(ASSET_KONDISI)), '', true) ?></select></label>
        <button class="btn" type="submit">Simpan catatan</button>
      </form>
    </details>
    <a class="btn btn-secondary btn-block" href="<?= e(turl('/admin/aset/' . $sel['id'] . '/ubah')) ?>"><?= icon('edit', 16) ?>Ubah data aset</a>
  </aside>
  <?php endif; ?>
</div>
