<?php
$spineH = [60, 85, 45, 100, 70, 50, 90, 75, 40, 85, 65, 95, 55, 72, 88, 48, 80, 62, 98, 52];
$spineC = ['#1D4275', '#24569A', '#1A3A68', '#2E5287'];
?>
<div class="sheet-tools">
  <a class="btn btn-sm btn-secondary" href="<?= e($back) ?>"><?= icon('left', 16) ?>Kembali</a>
  <span class="grow small" style="color:var(--soft-on-navy)"><?= count($members) ?> kartu · ukuran KTP 85,6 × 54 mm. Cetak di kertas A4, gunting, lipat di tengah, lalu laminating.</span>
  <button class="btn btn-sm btn-gold" type="button" data-print><?= icon('printer', 16) ?>Cetak / simpan PDF</button>
</div>
<div class="sheet">
  <?php foreach ($members as $m): ?>
  <div class="card-pair">
    <div class="idcard front">
      <div class="spine-row" aria-hidden="true"><?php foreach ($spineH as $i => $h): ?><i style="height:<?= $h ?>%;background:<?= ($i === 7 || $i === 15) ? '#D4A63A' : $spineC[$i % 4] ?>"></i><?php endforeach; ?></div>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:2mm">
        <div style="display:flex;align-items:center;gap:1.8mm">
          <span class="mk"><?= Tenant::get('logo') ? '<img src="' . e(media_url(Tenant::get('logo'))) . '" alt="">' : icon('logo', 12) ?></span>
          <div style="line-height:1.15"><div style="font-size:1.4mm;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#D4A63A">Perpustakaan</div><div style="font-size:2.3mm;font-weight:700"><?= e($s['school_name']) ?></div></div>
        </div>
        <span style="font-size:1.4mm;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#C3CDDC">Kartu anggota</span>
      </div>
      <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:3mm">
        <div style="display:flex;flex-direction:column;gap:2.4mm;min-width:0">
          <div class="nm"><?= e($m['name']) ?></div>
          <div style="display:grid;grid-template-columns:auto auto;gap:1.6mm 4.8mm">
            <div><div class="k"><?= $m['type'] === 'siswa' ? 'Kelas' : 'Status' ?></div><div class="v"><?= e($m['type'] === 'siswa' ? ($m['kelas'] ?: '—') : label('member_type', $m['type'])) ?></div></div>
            <div><div class="k">No. anggota</div><div class="v mono"><?= e($m['member_no'] ?: '—') ?></div></div>
            <div><div class="k"><?= $m['type'] === 'siswa' ? 'NISN' : 'NIP' ?></div><div class="v mono"><?= e(($m['type'] === 'siswa' ? $m['nisn'] : $m['nip']) ?: '—') ?></div></div>
            <div><div class="k">Berlaku s.d.</div><div class="v"><?= e($m['valid_until'] ? fmt_date($m['valid_until'], 'month') : 'Selama aktif') ?></div></div>
          </div>
        </div>
        <div class="photo"><?= $m['photo'] ? '<img src="' . e(media_url($m['photo'])) . '" alt="">' : icon('user', 26) ?></div>
      </div>
    </div>
    <div class="idcard back">
      <div style="display:flex;flex-direction:column;align-items:center;gap:1.2mm">
        <div class="qr" data-qr="<?= e(member_qr_text($m)) ?>" data-qr-margin="1"></div>
        <span class="mono" style="font-size:1.9mm;font-weight:700;letter-spacing:.08em"><?= e($m['member_no']) ?></span>
      </div>
      <div style="display:flex;flex-direction:column;justify-content:space-between;min-width:0;flex-grow:1">
        <div style="display:flex;flex-direction:column;gap:1.4mm">
          <div class="serif" style="font-size:3.2mm;line-height:1.1;color:#10284A">Pindai setiap datang ke perpustakaan.</div>
          <ul style="margin:0;padding-left:3mm;font-size:1.75mm;line-height:1.45;color:#2B3444">
            <li>Kartu hanya berlaku untuk pemilik yang namanya tercantum.</li>
            <li>Wajib ditunjukkan saat meminjam atau mengembalikan buku.</li>
            <li>Kartu hilang? Segera lapor petugas agar QR lama dinonaktifkan.</li>
          </ul>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:2mm;border-top:.3mm solid #E4E0D6;padding-top:1.4mm">
          <span style="font-size:1.5mm;line-height:1.4;color:#586274;max-width:22mm">Menemukan kartu ini? Kembalikan ke <?= e($s['library_name']) ?>.</span>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.4mm">
            <span style="font-size:1.5mm;color:#586274">Kepala Perpustakaan</span>
            <span style="width:14mm;height:3.2mm;border-bottom:.2mm solid #A9A396"></span>
            <span style="font-size:1.6mm;font-weight:700"><?= e($s['head_name'] ?: ' ') ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
