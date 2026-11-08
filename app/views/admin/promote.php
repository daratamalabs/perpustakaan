<?php
$recent = $last && strtotime($last) > strtotime('-300 days');
$total = array_sum(array_map(fn($r) => (int)$r['n'], $rows));
?>
<div class="page-head">
  <div><span class="crumb"><a href="<?= e(turl('/admin/member')) ?>">Member</a> / Kenaikan kelas</span><h1>Kenaikan kelas</h1></div>
</div>
<?php if ($recent): ?>
  <div class="alert alert-warn"><?= icon('alert') ?><span>Kenaikan kelas terakhir dijalankan <b><?= e(fmt_date($last, 'long')) ?></b>. Pastikan tidak menjalankannya dua kali dalam satu tahun ajaran.</span></div>
<?php endif; ?>
<div class="split">
  <section class="card wide" style="overflow:hidden">
    <div class="card-head" style="padding-bottom:14px"><h2>Perubahan yang akan terjadi</h2><span class="muted small"><?= num($total) ?> siswa aktif</span></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Kelas sekarang</th><th>Jumlah siswa</th><th>Menjadi</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="3"><div class="empty"><?= icon('member', 30) ?>Belum ada siswa aktif.</div></td></tr><?php endif; ?>
      <?php foreach ($rows as $r): $next = kelas_next($r['kelas']); ?>
        <tr><td><strong><?= e($r['kelas']) ?></strong></td><td class="num"><?= num($r['n']) ?></td>
          <td><?= $next === null ? chip('Lulus · kartu jadi alumni', 'gold') : ($next === $r['kelas'] ? '<span class="muted">Tidak berubah</span>' : '<strong>' . e($next) . '</strong>') ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <form class="card card-pad stack side" method="post" action="<?= e(turl('/admin/member/kenaikan')) ?>">
    <?= csrf_field() ?>
    <h2>Jalankan kenaikan kelas</h2>
    <p class="muted small" style="line-height:1.6">Jalankan sekali di awal tahun ajaran baru, setelah daftar kelas di Pengaturan sesuai. Data induk siswa ikut diperbarui. Siswa yang tinggal kelas bisa diubah manual dari halaman detail member.</p>
    <label class="check"><input type="checkbox" name="graduate" value="1" checked> Tandai siswa kelas XII sebagai lulus (kartu tidak bisa dipakai lagi)</label>
    <label class="field">Ketik <b>NAIK KELAS</b> untuk konfirmasi<input class="input" name="confirm" autocomplete="off" required></label>
    <button class="btn btn-lg btn-block" type="submit">Naikkan kelas</button>
  </form>
</div>
