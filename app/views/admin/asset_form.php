<?php
$v = fn(string $k, $d = '') => old($k, $a[$k] ?? $d);
?>
<div class="page-head">
  <div><span class="crumb"><a href="<?= e(turl('/admin/aset')) ?>">Aset &amp; inventaris</a> / <?= $a ? 'Ubah' : 'Tambah' ?></span><h1><?= $a ? e($a['name']) : 'Tambah aset' ?></h1></div>
  <?php if ($a): ?>
    <div class="page-actions">
      <a class="btn btn-secondary" href="<?= e(turl('/admin/aset/label?ids=' . $a['id'])) ?>" target="_blank"><?= icon('qr') ?>Cetak label QR</a>
    </div>
  <?php endif; ?>
</div>

<form class="split" method="post" enctype="multipart/form-data" action="<?= e($a ? turl('/admin/aset/' . $a['id'] . '/ubah') : turl('/admin/aset/baru')) ?>">
  <?= csrf_field() ?>
  <section class="card card-pad stack wide">
    <h2>Data barang</h2>
    <div class="grid-2">
      <label class="field span-all">Nama barang<input class="input" name="name" required value="<?= e($v('name')) ?>" placeholder="Mis. PC katalog 2, Rak buku besi 5 susun"></label>
      <label class="field">Kode inventaris<input class="input mono" name="code" value="<?= e($v('code', $nextCode)) ?>" style="text-transform:uppercase"><span class="hint">Boleh diganti dengan kode dari tata usaha.</span></label>
      <label class="field">Kategori<select class="select" name="category"><?= select_options($s['asset_categories'], $v('category', $s['asset_categories'][0] ?? '')) ?></select></label>
      <label class="field">Lokasi<input class="input" name="location" list="locList" value="<?= e($v('location')) ?>" placeholder="Mis. Ruang baca"><datalist id="locList"><?php foreach ($locations as $l): ?><option value="<?= e($l) ?>"><?php endforeach; ?></datalist></label>
      <label class="field">Kondisi<select class="select" name="kondisi"><?= select_options(array_intersect_key(LABELS['kondisi'], array_flip(ASSET_KONDISI)), $v('kondisi', 'baik'), true) ?></select></label>
      <label class="field span-all"><span>Spesifikasi / merek <span class="opt">(opsional)</span></span><input class="input" name="spec" value="<?= e($v('spec')) ?>" placeholder="Mis. Core i3, RAM 8 GB · Merek X"></label>
      <label class="field">Tanggal perolehan<input class="input" type="date" name="acquired_at" value="<?= e($v('acquired_at')) ?>"></label>
      <label class="field">Nilai perolehan<span class="input-group"><span class="addon">Rp</span><input class="input" name="value" inputmode="numeric" value="<?= e($v('value')) ?>" placeholder="0"></span></label>
      <label class="field">Sumber dana<input class="input" name="fund_source" list="fundList" value="<?= e($v('fund_source')) ?>" placeholder="Mis. Dana BOS"><datalist id="fundList"><?php foreach ($funds as $f): ?><option value="<?= e($f) ?>"><?php endforeach; ?></datalist></label>
      <label class="field span-all"><span>Catatan <span class="opt">(opsional)</span></span><textarea class="textarea" name="notes" rows="3"><?= e($v('notes')) ?></textarea></label>
    </div>
  </section>
  <div class="side stack">
    <section class="card card-pad stack">
      <h2>Foto barang</h2>
      <label class="dropzone">
        <span class="thumb" id="photoPrev"><?php if ($a && $a['photo']): ?><img src="<?= e(media_url($a['photo'])) ?>" alt=""><?php else: ?><?= icon('camera', 26) ?><?php endif; ?></span>
        <span class="stack-sm" style="gap:4px"><strong style="color:var(--blue-600)">Ambil atau unggah foto</strong><span class="muted small">JPG/PNG, otomatis diperkecil.</span></span>
        <input type="file" name="photo" accept="image/*" capture="environment" data-resize="1200" data-preview="#photoPrev" hidden>
      </label>
      <?php if ($a && $a['photo']): ?><label class="check"><input type="checkbox" name="remove_photo" value="1"> Hapus foto</label><?php endif; ?>
    </section>
    <section class="card card-pad stack">
      <button class="btn btn-lg btn-block" type="submit"><?= $a ? 'Simpan perubahan' : 'Simpan aset' ?></button>
      <a class="btn btn-secondary btn-block" href="<?= e($a ? turl('/admin/aset?id=' . $a['id']) : turl('/admin/aset')) ?>">Batal</a>
    </section>
  </div>
</form>
<?php if ($a): ?>
  <form method="post" action="<?= e(turl('/admin/aset/' . $a['id'] . '/hapus')) ?>" data-confirm="Hapus aset <?= e($a['code']) ?> beserta riwayatnya? Untuk barang yang dimusnahkan, sebaiknya ubah kondisi menjadi Dihapuskan agar riwayatnya tetap ada.">
    <?= csrf_field() ?><button class="btn btn-danger-outline btn-sm" type="submit"><?= icon('trash', 15) ?>Hapus permanen</button>
  </form>
<?php endif; ?>
