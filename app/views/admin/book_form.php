<?php
$b = $book ?? [];
$isNew = !$book;
$v = fn($k, $d = '') => old($k, $b[$k] ?? $d);
$action = $isNew ? turl('/admin/buku/baru') : turl('/admin/buku/' . $b['id']);
[$cbg, $cfg] = cover_color((string)($b['title'] ?? 'Buku'));
$availCount = count(array_filter($copies, fn($c) => $c['status'] === 'tersedia' && $c['kondisi'] !== 'hilang'));
?>
<div class="page-head">
  <div><span class="crumb"><a href="<?= e(turl('/admin/buku')) ?>">Katalog buku</a> / <?= $isNew ? 'Tambah buku' : 'Ubah buku' ?></span><h1><?= e($isNew ? 'Tambah buku' : $b['title']) ?></h1></div>
  <div class="page-actions">
    <?php if (!$isNew): ?>
      <form method="post" action="<?= e(turl('/admin/buku/' . $b['id'] . '/hapus')) ?>" data-confirm="Hapus buku ini beserta semua eksemplar dan riwayat pinjamnya?"><?= csrf_field() ?><button class="btn btn-danger-outline" type="submit"><?= icon('trash', 16) ?>Hapus</button></form>
    <?php endif; ?>
    <a class="btn btn-secondary" href="<?= e(turl('/admin/buku')) ?>">Batal</a>
    <button class="btn" type="submit" form="bookForm">Simpan buku</button>
  </div>
</div>

<section class="card card-pad row" style="gap:18px;align-items:center">
  <span class="stat-icon" style="width:48px;height:48px;background:var(--blue-100);color:var(--blue-600)"><?= icon('scan', 24) ?></span>
  <div class="stack-sm" style="flex:1 1 260px;gap:4px"><strong>Isi otomatis dari ISBN</strong><span class="muted small">Pindai barcode di sampul belakang atau ketik ISBN. Judul, pengarang, dan penerbit terisi sendiri.</span></div>
  <div class="row" style="flex:1 1 360px;flex-wrap:nowrap">
    <input class="input" id="isbnLookup" placeholder="Pindai atau ketik ISBN" value="<?= e($b['isbn'] ?? '') ?>" aria-label="ISBN" data-url="<?= e(turl('/admin/buku/isbn')) ?>">
    <button class="btn btn-secondary" type="button" id="isbnBtn">Cari</button>
  </div>
  <span id="isbnMsg" class="small" style="flex-basis:100%;font-weight:600" hidden></span>
</section>

<form id="bookForm" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="split">
  <?= csrf_field() ?>
  <section class="card card-pad stack wide" style="flex:2 1 560px">
    <h2>Informasi buku</h2>
    <div class="grid-2">
      <label class="field span-all">Judul<input class="input" name="title" id="f_title" required value="<?= e($v('title')) ?>"></label>
      <label class="field">Pengarang<input class="input" name="author" id="f_author" value="<?= e($v('author')) ?>"></label>
      <label class="field">Penerbit<input class="input" name="publisher" id="f_publisher" value="<?= e($v('publisher')) ?>"></label>
      <label class="field">Tahun terbit<input class="input" name="year" id="f_year" inputmode="numeric" value="<?= e($v('year')) ?>"></label>
      <label class="field">ISBN<input class="input" name="isbn" id="f_isbn" value="<?= e($v('isbn')) ?>"></label>
      <label class="field">Bahasa<input class="input" name="language" id="f_language" list="langs" value="<?= e($v('language', 'Indonesia')) ?>"><datalist id="langs"><option>Indonesia</option><option>Inggris</option><option>Jawa</option><option>Arab</option></datalist></label>
      <label class="field">Kategori<select class="select" name="category"><?= select_options(array_unique(array_merge($s['categories'], $b ? [$b['category']] : [])), $v('category', 'Nonfiksi')) ?></select></label>
      <label class="field">Klasifikasi DDC<input class="input mono" name="ddc" id="f_ddc" value="<?= e($v('ddc')) ?>" placeholder="Mis. 899.221 3"></label>
      <label class="field"><span>Nomor panggil <span class="opt">(kosongkan = otomatis)</span></span><input class="input mono" name="call_number" id="f_call" value="<?= e($v('call_number')) ?>"></label>
      <label class="field">Lokasi rak<input class="input" name="shelf" list="shelves" value="<?= e($v('shelf')) ?>" placeholder="Mis. F-02"><datalist id="shelves"><?php foreach ($shelves as $sh): ?><option><?= e($sh) ?></option><?php endforeach; ?></datalist></label>
      <fieldset class="span-all" style="border:0;margin:0;padding:0">
        <legend class="field" style="padding:0;margin-bottom:8px">Jenis koleksi</legend>
        <div class="grid-auto" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px">
          <?php foreach (['pinjam' => 'Dibawa pulang sesuai aturan pinjam', 'baca' => 'Hanya dibaca di perpustakaan', 'paket' => 'Dipinjamkan per semester'] as $k => $desc): ?>
            <label class="option-card"><input type="radio" name="collection_type" value="<?= $k ?>" <?= $v('collection_type', 'pinjam') === $k ? 'checked' : '' ?>><span><strong><?= e(label('collection', $k)) ?></strong><span><?= e($desc) ?></span></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <label class="field span-all">Sinopsis<textarea class="textarea" name="synopsis" id="f_synopsis" rows="3"><?= e($v('synopsis')) ?></textarea></label>
      <label class="field span-all"><span>Kata kunci <span class="opt">(pisahkan dengan koma)</span></span><input class="input" name="keywords" value="<?= e($v('keywords')) ?>" placeholder="persahabatan, pendidikan"></label>
    </div>
    <?php if ($isNew): ?>
      <div class="stack" style="border-top:1px solid var(--line-2);padding-top:18px">
        <h2>Eksemplar awal</h2>
        <div class="grid-auto" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
          <label class="field">Jumlah eksemplar<input class="input" type="number" name="copies" min="0" max="100" value="<?= e(old('copies', '1')) ?>"></label>
          <label class="field">Sumber<input class="input" name="source" list="sources" value="<?= e(old('source')) ?>" placeholder="Dana BOS, hibah…"><datalist id="sources"><?php foreach (array_unique(array_merge(['Dana BOS', 'Hibah alumni', 'Hibah komite', 'Sumbangan siswa'], $sources)) as $so): ?><option><?= e($so) ?></option><?php endforeach; ?></datalist></label>
          <label class="field">Tanggal masuk<input class="input" type="date" name="acquired_at" value="<?= e(old('acquired_at', today())) ?>"></label>
        </div>
        <p class="muted small">Kode eksemplar dibuat otomatis (B-nomor buku-urutan) dan bisa langsung dicetak sebagai label.</p>
      </div>
    <?php endif; ?>
  </section>

  <div class="side stack" style="flex:1 1 300px">
    <section class="card card-pad stack">
      <h2>Sampul</h2>
      <div style="align-self:center;width:180px;aspect-ratio:3/4;border-radius:8px;overflow:hidden;box-shadow:0 10px 24px rgba(16,40,74,.18)" id="coverPrev">
        <?php if (!empty($b['cover'])): ?><img src="<?= e(media_url($b['cover'])) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?><div style="width:100%;height:100%;background:<?= $cbg ?>;color:<?= $cfg ?>;padding:20px 16px;display:flex;flex-direction:column;justify-content:space-between"><span style="width:44px;height:3px;background:var(--gold-500);border-radius:2px"></span><span><span class="serif" style="display:block;font-size:24px;line-height:1.05"><?= e($b['title'] ?? 'Judul buku') ?></span><span style="display:block;font-size:12px;font-weight:600;opacity:.8;margin-top:6px"><?= e($b['author'] ?? '') ?></span></span></div><?php endif; ?>
      </div>
      <label class="btn btn-secondary" style="border-style:dashed"><?= icon('camera') ?>Foto atau unggah sampul<input type="file" name="cover" accept="image/*" data-resize="900" data-preview="#coverPrev" hidden></label>
      <?php if (!empty($b['cover'])): ?><label class="check small"><input type="checkbox" name="remove_cover" value="1">Hapus sampul</label><?php endif; ?>
    </section>
    <?php if (!$isNew): ?>
    <section class="card card-pad stack">
      <h2>Label punggung buku</h2>
      <?php $first = $copies[0] ?? null; ?>
      <?php if ($first): ?>
        <div class="label" style="width:100%;height:auto;min-height:110px;padding:14px;gap:14px">
          <div class="call" style="font-size:16px;min-width:90px;padding-right:14px"><?php foreach (array_slice(explode(' ', (string)($b['call_number'] ?: '—')), 0, 4) as $part): ?><span><?= e($part) ?></span><?php endforeach; ?></div>
          <div class="bc"><small style="font-size:10px"><?= e(strtoupper(Tenant::shortName())) ?></small><svg data-barcode="<?= e($first['code']) ?>" data-h="44" style="height:44px;width:100%"></svg><b style="font-size:12px"><?= e($first['code']) ?></b></div>
        </div>
        <a class="btn btn-secondary" href="<?= e(turl('/admin/buku/label?buku=' . $b['id'])) ?>" target="_blank"><?= icon('printer') ?>Cetak semua label buku ini</a>
      <?php else: ?>
        <p class="muted small">Tambahkan eksemplar dulu untuk mencetak label.</p>
      <?php endif; ?>
      <span class="muted small">Label 6 × 3,2 cm, 24 label per lembar A4.</span>
    </section>
    <?php endif; ?>
  </div>
</form>

<?php if (!$isNew): ?>
<section class="card" id="eksemplar" style="overflow:hidden">
  <div class="card-head" style="padding-bottom:16px;border-bottom:1px solid var(--line)">
    <div class="row"><h2>Eksemplar</h2><?= chip(count($copies) . ' eksemplar · ' . $availCount . ' tersedia', 'mute') ?></div>
    <div class="row">
      <button class="btn btn-sm btn-secondary" type="button" id="printCopies"><?= icon('printer', 15) ?>Cetak label terpilih</button>
    </div>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th style="width:28px"><input type="checkbox" data-check-all=".copy-check" aria-label="Pilih semua eksemplar" style="width:16px;height:16px;accent-color:var(--blue-600)"></th><th>Kode eksemplar</th><th>Sumber</th><th>Tanggal masuk</th><th>Kondisi</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php if (!$copies): ?><tr><td colspan="7"><div class="empty">Belum ada eksemplar.</div></td></tr><?php endif; ?>
      <?php foreach ($copies as $c): ?>
        <tr>
          <td><input type="checkbox" class="copy-check" value="<?= (int)$c['id'] ?>" aria-label="Pilih <?= e($c['code']) ?>" style="width:16px;height:16px;accent-color:var(--blue-600)"></td>
          <td><div class="row" style="gap:12px;flex-wrap:nowrap"><svg data-barcode="<?= e($c['code']) ?>" data-h="26" data-w="1" style="height:26px;width:72px" aria-hidden="true"></svg><strong class="mono small"><?= e($c['code']) ?></strong></div></td>
          <td><?= e($c['source'] ?: '—') ?></td>
          <td><?= e($c['acquired_at'] ? fmt_date($c['acquired_at']) : '—') ?></td>
          <td>
            <form method="post" action="<?= e(turl('/admin/eksemplar/' . $c['id'])) ?>">
              <?= csrf_field() ?><input type="hidden" name="source" value="<?= e($c['source']) ?>"><input type="hidden" name="acquired_at" value="<?= e($c['acquired_at']) ?>"><input type="hidden" name="notes" value="<?= e($c['notes']) ?>">
              <select class="select" name="kondisi" data-autosubmit style="height:34px;font-size:13px;width:auto" aria-label="Kondisi <?= e($c['code']) ?>"><?= select_options(['baik' => 'Baik', 'rusak_ringan' => 'Rusak ringan', 'rusak_berat' => 'Rusak berat', 'hilang' => 'Hilang'], $c['kondisi'], true) ?></select>
            </form>
          </td>
          <td><?= $c['status'] === 'dipinjam' ? chip('Dipinjam' . ($c['borrower'] ? ' · ' . display_name($c['borrower']) : ''), 'info') . ($c['due'] ? '<span class="sub tiny">s.d. ' . e(fmt_date($c['due'], 'short')) . '</span>' : '') : ($c['kondisi'] === 'hilang' ? chip('Hilang', 'bad') : chip('Tersedia', 'ok')) ?></td>
          <td class="actions">
            <form method="post" action="<?= e(turl('/admin/eksemplar/' . $c['id'] . '/hapus')) ?>" data-confirm="Hapus eksemplar <?= e($c['code']) ?>?"><?= csrf_field() ?><button class="btn-icon" type="submit" aria-label="Hapus <?= e($c['code']) ?>" <?= $c['status'] === 'dipinjam' ? 'disabled' : '' ?>><?= icon('trash', 17) ?></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <form class="card-foot" method="post" action="<?= e(turl('/admin/buku/' . $b['id'] . '/eksemplar')) ?>" style="justify-content:flex-start;background:var(--paper-2)">
    <?= csrf_field() ?>
    <strong style="color:var(--ink)">Tambah eksemplar</strong>
    <input class="input" type="number" name="count" min="1" max="100" value="1" style="width:80px;height:38px" aria-label="Jumlah">
    <input class="input" name="source" list="sources2" placeholder="Sumber" style="width:160px;height:38px" aria-label="Sumber"><datalist id="sources2"><?php foreach (array_unique(array_merge(['Dana BOS', 'Hibah alumni', 'Hibah komite', 'Sumbangan siswa'], $sources)) as $so): ?><option><?= e($so) ?></option><?php endforeach; ?></datalist>
    <input class="input" type="date" name="acquired_at" value="<?= e(today()) ?>" style="width:160px;height:38px" aria-label="Tanggal masuk">
    <input class="input mono" name="code" placeholder="Kode sendiri (opsional)" style="width:190px;height:38px" aria-label="Kode eksemplar sendiri">
    <button class="btn btn-sm btn-navy" type="submit"><?= icon('plus', 15) ?>Tambah</button>
  </form>
</section>
<script>
document.getElementById('printCopies').addEventListener('click', function () {
  var ids = Array.prototype.map.call(document.querySelectorAll('.copy-check:checked'), function (c) { return c.value; });
  if (!ids.length) { toast('Centang eksemplar dulu.', 'bad'); return; }
  window.open('<?= e(turl('/admin/buku/label?eksemplar=')) ?>' + ids.join(','), '_blank');
});
</script>
<?php endif; ?>
