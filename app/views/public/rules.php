<?php $l = $s['loan']; ?>
<?= partial('public/_hero', ['heading' => 'Tata tertib perpustakaan', 'back' => turl()]) ?>
<div class="public-body">
  <div class="card card-pad stack" id="tatatertib">
    <div class="stack-sm"><span class="eyebrow">Keanggotaan</span>
      <ul style="margin:0;padding-left:18px;line-height:1.75">
        <li>Kartu member hanya berlaku untuk pemilik yang namanya tercantum.</li>
        <li>Pindai kartu QR di meja depan setiap kali datang.</li>
        <li>Kartu hilang atau HP berganti? Lapor petugas agar QR lama dinonaktifkan.</li>
      </ul>
    </div>
    <div class="stack-sm"><span class="eyebrow">Peminjaman</span>
      <ul style="margin:0;padding-left:18px;line-height:1.75">
        <li>Siswa: maksimal <?= (int)$l['max_siswa'] ?> buku, selama <?= (int)$l['days_siswa'] ?> hari.</li>
        <li>Guru dan tendik: maksimal <?= (int)$l['max_guru'] ?> buku, selama <?= (int)$l['days_guru'] ?> hari.</li>
        <li>Perpanjangan boleh <?= (int)$l['extensions'] ?> kali, sebelum jatuh tempo.</li>
        <?php if (!empty($l['paket_semester'])): ?><li>Buku paket dipinjamkan per semester.</li><?php endif; ?>
        <li>Buku referensi (kamus, ensiklopedia) hanya dibaca di tempat.</li>
        <?php if ((int)$l['fine_per_day'] > 0): ?><li>Denda keterlambatan <?= rupiah($l['fine_per_day']) ?> per hari per buku.</li><?php endif; ?>
        <li>Buku rusak atau hilang wajib diganti dengan judul yang sama.</li>
      </ul>
    </div>
    <?php if (trim((string)$s['rules_extra']) !== ''): ?>
    <div class="stack-sm"><span class="eyebrow">Ruang baca</span>
      <ul style="margin:0;padding-left:18px;line-height:1.75">
        <?php foreach (array_filter(array_map('trim', explode("\n", (string)$s['rules_extra']))) as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</div>
