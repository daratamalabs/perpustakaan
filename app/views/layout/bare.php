<?= partial('layout/head', ['title' => $title ?? 'Perpustakaan']) ?>
<body class="<?= e($bodyClass ?? '') ?>">
<?= $content ?>
<div class="toast-stack" id="toasts" aria-live="polite"></div>
<script src="<?= asset('js/app.js') ?>"></script>
<?php foreach (($scripts ?? []) as $s): ?><script src="<?= asset($s) ?>"></script><?php endforeach; ?>
</body>
</html>
