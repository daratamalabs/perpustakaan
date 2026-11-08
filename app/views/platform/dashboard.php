<?= partial('platform/_top', ['admin' => $admin]) ?>
<div style="max-width:1180px;margin:0 auto;padding:28px 24px 48px" class="stack">
  <?= flashes_html() ?>
  <div class="page-head">
    <div><span class="crumb">Platform</span><h1>Daftar sekolah</h1></div>
    <div class="page-actions"><a class="btn" href="<?= e(url('/superadmin/sekolah/baru')) ?>"><?= icon('plus', 16) ?>Tambah sekolah</a></div>
  </div>
  <div class="strip">
    <div><span>Sekolah</span><strong><?= num(count($schools)) ?></strong></div>
    <div><span>Aktif</span><strong><?= num(count(array_filter($schools, fn($s) => $s['active']))) ?></strong></div>
    <div><span>Member aktif</span><strong><?= num(array_sum(array_column($schools, 'members'))) ?></strong></div>
    <div><span>Kunjungan bulan ini</span><strong><?= num(array_sum(array_column($schools, 'visits_month'))) ?></strong></div>
  </div>
  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Sekolah</th><th>Alamat</th><th class="right">Member</th><th class="right">Judul buku</th><th class="right">Kunjungan bulan ini</th><th>Terakhir dipakai</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if (!$schools): ?><tr><td colspan="8"><div class="empty"><?= icon('school', 36) ?>Belum ada sekolah. Tambahkan sekolah pertama.</div></td></tr><?php endif; ?>
        <?php foreach ($schools as $s): ?>
          <tr>
            <td><strong><?= e($s['name']) ?></strong><span class="sub"><?= e($s['city'] ?: '—') ?></span></td>
            <td><a href="<?= e(url('/' . $s['slug'])) ?>" target="_blank" rel="noopener" class="mono small">/<?= e($s['slug']) ?></a></td>
            <td class="right num"><?= num($s['members']) ?></td>
            <td class="right num"><?= num($s['books']) ?></td>
            <td class="right num"><?= num($s['visits_month']) ?></td>
            <td class="small muted"><?= $s['last_visit'] ? e(fmt_dt($s['last_visit'])) : 'Belum ada' ?></td>
            <td><?= $s['active'] ? chip('Aktif', 'ok') : chip('Nonaktif', 'mute') ?></td>
            <td class="actions"><a class="btn btn-sm btn-secondary" href="<?= e(url('/superadmin/sekolah/' . $s['id'])) ?>">Kelola</a> <a class="btn btn-sm btn-ghost" href="<?= e(url('/' . $s['slug'] . '/admin')) ?>" target="_blank" rel="noopener">Admin</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
