<?php $cat = (string)g('kategori'); ?>
<div class="page-head">
  <div><span class="crumb">Koleksi</span><h1>Katalog buku</h1></div>
  <div class="page-actions">
    <a class="btn btn-secondary" href="<?= e(turl('/admin/buku/impor')) ?>"><?= icon('upload') ?>Impor Excel</a>
    <a class="btn btn-secondary" href="<?= e(turl('/admin/buku/ekspor')) ?>"><?= icon('download') ?>Ekspor</a>
    <a class="btn" href="<?= e(turl('/admin/buku/baru')) ?>"><?= icon('plus', 16) ?>Tambah buku</a>
  </div>
</div>

<div class="strip">
  <div><span>Judul</span><strong><?= num($summary['titles']) ?></strong></div>
  <div><span>Eksemplar</span><strong><?= num($summary['copies']) ?></strong></div>
  <div><span>Sedang dipinjam</span><strong style="color:var(--blue-600)"><?= num($summary['borrowed']) ?></strong></div>
  <div><span>Rusak atau hilang</span><strong style="color:var(--warn)"><?= num($summary['bad']) ?></strong></div>
</div>

<section class="card" style="overflow:hidden">
  <div style="padding:18px 20px 0" class="pills">
    <a class="pill <?= $cat === '' ? 'is-active' : '' ?>" href="<?= e(qs(['kategori' => null, 'hal' => null])) ?>">Semua<small><?= num($summary['titles']) ?></small></a>
    <?php foreach ($cats as $c): ?>
      <a class="pill <?= $cat === $c ? 'is-active' : '' ?>" href="<?= e(qs(['kategori' => $c, 'hal' => null])) ?>"><?= e($c) ?><small><?= num($catCounts[$c] ?? 0) ?></small></a>
    <?php endforeach; ?>
  </div>
  <form class="card-toolbar" method="get">
    <?php if ($cat !== ''): ?><input type="hidden" name="kategori" value="<?= e($cat) ?>"><?php endif; ?>
    <label class="input-icon"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e(g('q')) ?>" placeholder="Cari judul, pengarang, ISBN, atau kode eksemplar" aria-label="Cari buku"></label>
    <select class="select" name="status" aria-label="Status" data-autosubmit style="width:auto"><?= select_options(['' => 'Semua status', 'tersedia' => 'Ada yang tersedia', 'habis' => 'Semua dipinjam', 'rusak' => 'Ada rusak/hilang', 'kosong' => 'Belum ada eksemplar'], g('status'), true) ?></select>
    <select class="select" name="rak" aria-label="Rak" data-autosubmit style="width:auto"><option value="">Semua rak</option><?= select_options($shelves, g('rak')) ?></select>
    <select class="select" name="urut" aria-label="Urutkan" data-autosubmit style="width:auto"><?= select_options(['baru' => 'Terbaru ditambahkan', 'judul' => 'Judul A–Z', 'pengarang' => 'Pengarang A–Z', 'rak' => 'Lokasi rak'], g('urut', 'baru'), true) ?></select>
    <button class="btn btn-secondary" type="submit">Cari</button>
  </form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th style="width:28px"><input type="checkbox" aria-label="Pilih semua" data-check-all=".book-check" style="width:16px;height:16px;accent-color:var(--blue-600)"></th><th>Buku</th><th>Kategori</th><th>Rak</th><th>Eksemplar</th><th>Status</th><th class="actions">Aksi</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="7"><div class="empty"><?= icon('book', 36) ?><?= g('q') || $cat || g('status') ? 'Tidak ada buku yang cocok dengan filter.' : 'Belum ada buku. Tambahkan satu per satu atau impor dari Excel.' ?></div></td></tr><?php endif; ?>
      <?php foreach ($rows as $b):
        $total = (int)$b['total']; $avail = (int)$b['avail'];
        if ($b['collection_type'] === 'baca') { $st = ['Baca di tempat', 'mute']; }
        elseif ($total === 0) { $st = ['Belum ada eksemplar', 'mute']; }
        elseif ($avail === 0) { $st = ['Semua dipinjam', 'info']; }
        elseif ((int)$b['lost'] > 0 || (int)$b['damaged'] > 0) { $st = [((int)$b['lost'] ? $b['lost'] . ' hilang' : $b['damaged'] . ' rusak'), 'warn']; }
        elseif ($total > 4 && $avail / $total < 0.35) { $st = ['Stok menipis', 'warn']; }
        else { $st = ['Tersedia', 'ok']; } ?>
        <tr>
          <td><input type="checkbox" class="book-check" value="<?= (int)$b['id'] ?>" aria-label="Pilih <?= e($b['title']) ?>" style="width:16px;height:16px;accent-color:var(--blue-600)"></td>
          <td><a href="<?= e(turl('/admin/buku/' . $b['id'])) ?>" style="display:flex;align-items:center;gap:14px;text-decoration:none;color:inherit">
            <?= cover_html($b) ?>
            <span><strong><?= e($b['title']) ?></strong><span class="sub"><?= e($b['author'] ?: '—') ?><?= $b['year'] ? ' · ' . e($b['year']) : '' ?></span></span>
          </a></td>
          <td><?= e($b['category']) ?></td>
          <td class="mono small" style="font-weight:600"><?= e($b['shelf'] ?: '—') ?></td>
          <td style="min-width:150px"><div class="stack-sm" style="gap:6px"><span class="small" style="font-weight:600"><?= $avail ?> dari <?= $total ?> tersedia</span><span class="meter"><i style="width:<?= $total ? round($avail / $total * 100) : 0 ?>%"></i></span></div></td>
          <td><?= chip($st[0], $st[1]) ?></td>
          <td class="actions">
            <a class="btn-icon" style="display:inline-flex;align-items:center;justify-content:center;color:var(--blue-600)" href="<?= e(turl('/admin/buku/' . $b['id'])) ?>" aria-label="Ubah <?= e($b['title']) ?>"><?= icon('edit', 17) ?></a>
            <a class="btn-icon" style="display:inline-flex;align-items:center;justify-content:center" href="<?= e(turl('/admin/buku/label?buku=' . $b['id'])) ?>" target="_blank" aria-label="Cetak label <?= e($b['title']) ?>"><?= icon('printer', 17) ?></a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager_html($pg, 'judul') ?>
</section>
<div class="row muted small">
  <span>Cetak label untuk buku terpilih:</span>
  <button class="btn btn-sm btn-secondary" type="button" id="printSelected"><?= icon('printer', 15) ?>Cetak label terpilih</button>
  <a class="btn btn-sm btn-ghost" href="<?= e(turl('/admin/buku/label?baru=7')) ?>" target="_blank">Label eksemplar 7 hari terakhir</a>
</div>
<script>
document.getElementById('printSelected').addEventListener('click', function () {
  var ids = Array.prototype.map.call(document.querySelectorAll('.book-check:checked'), function (c) { return c.value; });
  if (!ids.length) { toast('Centang buku dulu.', 'bad'); return; }
  window.open('<?= e(turl('/admin/buku/label?buku=')) ?>' + ids.join(','), '_blank');
});
</script>
