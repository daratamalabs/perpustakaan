<?php
$isBook = $kind === 'buku';
$base = $isBook ? '/admin/buku' : '/admin/member';
?>
<div class="page-head">
  <div><span class="crumb"><a href="<?= e(turl($base)) ?>"><?= $isBook ? 'Katalog buku' : 'Member' ?></a> / Impor</span><h1><?= $isBook ? 'Impor buku dari Excel' : 'Impor data siswa' ?></h1></div>
  <div class="page-actions"><a class="btn btn-secondary" href="<?= e(turl($base . '/templat')) ?>"><?= icon('download') ?>Unduh templat</a></div>
</div>
<?php if ($result): ?>
  <div class="alert alert-ok"><?= icon('check') ?><span><?= e($result['ok']) ?></span></div>
  <?php if (!empty($result['skipped'])): ?>
    <div class="alert alert-warn"><?= icon('alert') ?><span><b><?= count($result['skipped']) ?> baris dilewati:</b><br><?= implode('<br>', array_map('e', array_slice($result['skipped'], 0, 30))) ?><?= count($result['skipped']) > 30 ? '<br>…' : '' ?></span></div>
  <?php endif; ?>
<?php endif; ?>
<div class="split">
  <form class="card card-pad stack wide" method="post" action="<?= e(turl($base . '/impor')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <h2>Unggah berkas</h2>
    <label class="dropzone" style="padding:24px">
      <span class="thumb" style="width:56px;height:56px"><?= icon('file', 26) ?></span>
      <span class="stack-sm" style="gap:4px"><strong style="color:var(--blue-600)" id="fileName">Pilih berkas .xlsx atau .csv</strong><span class="muted small">Baris pertama harus berisi nama kolom seperti di templat.</span></span>
      <input type="file" name="file" accept=".xlsx,.csv" required hidden onchange="document.getElementById('fileName').textContent = this.files[0] ? this.files[0].name : 'Pilih berkas'">
    </label>
    <?php if (!$isBook): ?>
      <label class="option-card"><input type="radio" name="mode" value="ref" checked><span><strong>Simpan sebagai data induk saja</strong><span>Dipakai untuk mencocokkan pendaftar. Siswa tetap mendaftar sendiri lewat HP.</span></span></label>
      <label class="option-card"><input type="radio" name="mode" value="member"><span><strong>Sekaligus buat akun member aktif</strong><span>Semua siswa langsung punya kartu QR. PIN awal = 6 digit terakhir NISN, minta siswa menggantinya.</span></span></label>
    <?php endif; ?>
    <div><button class="btn" type="submit"><?= icon('upload') ?>Impor sekarang</button></div>
  </form>
  <section class="card card-pad stack side">
    <h2>Kolom yang dikenali</h2>
    <?php if ($isBook): ?>
      <ul class="muted small" style="margin:0;padding-left:18px;line-height:1.8">
        <li><b>Judul</b> (wajib)</li><li>Pengarang, Penerbit, Tahun, ISBN</li><li>Kategori, DDC, Rak</li>
        <li>Jenis: dapat dipinjam / baca di tempat / buku paket</li><li>Jumlah eksemplar (default 1), Sumber</li>
      </ul>
      <p class="muted small">Jika ISBN sudah ada di katalog, baris itu hanya menambah eksemplar.</p>
    <?php else: ?>
      <ul class="muted small" style="margin:0;padding-left:18px;line-height:1.8">
        <li><b>NISN</b> dan <b>Nama</b> (wajib)</li><li>Kelas (mis. X-1, XI-4)</li><li>Jenis kelamin (L/P)</li><li>No. WhatsApp (opsional)</li>
      </ul>
      <p class="muted small">Data bisa diambil dari ekspor Dapodik atau daftar siswa tata usaha. NISN yang sudah ada akan diperbarui kelasnya.</p>
    <?php endif; ?>
  </section>
</div>
