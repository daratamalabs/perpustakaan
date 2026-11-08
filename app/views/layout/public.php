<?php
$m = Auth::member();
$tab = $tab ?? null;
echo partial('layout/head', ['title' => $title ?? 'Perpustakaan']);
?>
<body class="<?= e($bodyClass ?? '') ?>">
<div class="public">
  <div class="public-wrap"<?= !empty($dark) ? ' style="background:var(--navy-900)"' : '' ?>>
    <?= $content ?>
    <?php if ($m && $m['status'] === 'active' && $tab): ?>
      <nav class="tabbar" aria-label="Menu member">
        <a href="<?= e(turl('/kartu')) ?>" class="<?= $tab === 'kartu' ? 'is-active' : '' ?>"><?= icon('qr', 22) ?>Kartu</a>
        <a href="<?= e(turl('/beranda')) ?>" class="<?= $tab === 'beranda' ? 'is-active' : '' ?>"><?= icon('clock', 22) ?>Riwayat</a>
        <a href="<?= e(turl('/profil')) ?>" class="<?= $tab === 'profil' ? 'is-active' : '' ?>"><?= icon('user', 22) ?>Profil</a>
      </nav>
    <?php endif; ?>
  </div>
</div>
<div class="toast-stack" id="toasts" aria-live="polite"></div>
<script src="<?= asset('js/app.js') ?>"></script>
<?php foreach (($scripts ?? []) as $s): ?><script src="<?= asset($s) ?>"></script><?php endforeach; ?>
</body>
</html>
