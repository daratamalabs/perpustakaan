<?php
$u = Auth::staff();
$badges = nav_badges();
$active = $active ?? '';
$groups = [
    'Utama' => [['dashboard', 'dash', 'Dashboard', '/admin']],
    'Koleksi' => [['buku', 'book', 'Katalog buku', '/admin/buku'], ['sirkulasi', 'sirk', 'Sirkulasi', '/admin/sirkulasi'], ['aset', 'asset', 'Aset & inventaris', '/admin/aset']],
    'Pengunjung' => [['kunjungan', 'users', 'Kunjungan', '/admin/kunjungan'], ['member', 'member', 'Member', '/admin/member']],
    'Lainnya' => [['laporan', 'report', 'Laporan', '/admin/laporan'], ['pengaturan', 'settings', 'Pengaturan', '/admin/pengaturan']],
];
echo partial('layout/head', ['title' => $title ?? 'Admin']);
?>
<body>
<div class="admin">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-top">
      <a class="brand" href="<?= e(Auth::homeFor($u)) ?>"><?= brand_mark(40) ?><span class="brand-text"><span class="eyebrow">Perpustakaan</span><strong><?= e(Tenant::shortName()) ?></strong></span></a>
      <button class="nav-toggle" type="button" aria-label="Buka menu" aria-expanded="false" aria-controls="adminNav" data-nav-toggle><span class="i-open"><?= icon('menu') ?></span><span class="i-close"><?= icon('x') ?></span></button>
    </div>
    <nav class="nav" id="adminNav" aria-label="Menu admin">
      <?php foreach ($groups as $gname => $items):
        $items = array_filter($items, fn($it) => Auth::can($it[0]));
        if (!$items) continue; ?>
        <div class="nav-group">
          <span class="nav-title"><?= e($gname) ?></span>
          <?php foreach ($items as [$key, $ic, $label, $path]): ?>
            <a href="<?= e(turl($path)) ?>" class="<?= $active === $key ? 'is-active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
              <?= icon($ic) ?><span><?= e($label) ?></span>
              <?php if (!empty($badges[$key])): ?><b class="count"><?= (int)$badges[$key] ?></b><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <div class="nav-group">
        <span class="nav-title">Meja depan</span>
        <a href="<?= e(turl('/kiosk')) ?>" target="_blank" rel="noopener"><?= icon('scan') ?><span>Buka kiosk</span></a>
        <a href="<?= e(display_url()) ?>" target="_blank" rel="noopener"><?= icon('display') ?><span>Layar display</span></a>
      </div>
    </nav>
    <div class="userbox">
      <span class="avatar"><?= e(initials($u['name'])) ?></span>
      <div class="grow" style="min-width:0"><strong><?= e($u['name']) ?></strong><span><?= e(label('role', $u['role'])) ?></span></div>
      <form method="post" action="<?= e(turl('/admin/logout')) ?>"><?= csrf_field() ?><button type="submit" aria-label="Keluar" title="Keluar"><?= icon('logout') ?></button></form>
    </div>
  </aside>
  <main class="main" id="main">
    <?= flashes_html() ?>
    <?= $content ?>
  </main>
</div>
<div class="toast-stack" id="toasts" aria-live="polite"></div>
<script src="<?= asset('js/app.js') ?>"></script>
<?php foreach (($scripts ?? []) as $s): ?><script src="<?= asset($s) ?>"></script><?php endforeach; ?>
</body>
</html>
