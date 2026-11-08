<header style="background:var(--navy-900);color:#fff">
  <div style="max-width:1180px;margin:0 auto;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <a class="brand" href="<?= e(url('/superadmin')) ?>" style="color:#fff"><span class="mark"><?= icon('logo', 22) ?></span><span class="brand-text"><span class="eyebrow">Daratama Labs</span><strong>Super admin perpustakaan</strong></span></a>
    <div class="row">
      <a class="btn btn-sm btn-ghost" style="color:#fff" href="<?= e(url('/superadmin/akun')) ?>"><?= icon('user', 16) ?><?= e($admin['name']) ?></a>
      <form method="post" action="<?= e(url('/superadmin/logout')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-secondary" type="submit"><?= icon('logout', 16) ?>Keluar</button></form>
    </div>
  </div>
</header>
