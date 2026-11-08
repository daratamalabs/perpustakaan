<?= partial('public/_hero', ['heading' => 'Pendaftaran diterima', 'lead' => 'Tinggal menunggu persetujuan petugas perpustakaan.', 'steps' => ['Data diri', 'Persetujuan', 'Kartu QR'], 'step' => 1]) ?>
<div class="public-body">
  <div class="card card-pad stack" style="align-items:center;text-align:center">
    <span class="stat-icon" style="width:64px;height:64px;border-radius:999px;background:var(--gold-100);color:var(--gold-700)"><?= icon('clock', 30) ?></span>
    <strong style="font-size:18px"><?= e($m['name']) ?></strong>
    <p class="muted">Petugas akan mencocokkan datamu dengan data sekolah. Setelah disetujui, kartu QR muncul di halaman kartu<?= wa_enabled() ? ' dan kami kirim pemberitahuan lewat WhatsApp' : '' ?>.</p>
    <?= chip('Menunggu persetujuan', 'gold', 'style="height:30px;padding:0 14px"') ?>
  </div>
  <a class="btn btn-lg btn-block btn-secondary" href="<?= e(turl('/kartu')) ?>">Lihat status kartu</a>
  <a class="btn btn-ghost" href="<?= e(turl()) ?>">Kembali ke beranda perpustakaan</a>
</div>
