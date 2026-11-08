<?php
$cat = (string)g('kategori');
$isToday = $from === today() && $to === today();
$multiDay = $from !== $to;
$mode = old('mode', 'member');
$catTabs = ['' => 'Semua', 'siswa' => 'Siswa', 'staf' => 'Guru & tendik', 'tamu' => 'Tamu'];
$rangeLabel = $multiDay ? fmt_date($from) . ' – ' . fmt_date($to) : ($isToday ? 'Hari ini' : fmt_date($from, 'long'));
?>
<div class="page-head">
  <div><span class="crumb">Pengunjung</span><h1>Kunjungan</h1></div>
  <div class="page-actions">
    <a class="btn btn-secondary" href="<?= e(turl('/admin/kunjungan/ekspor') . qs()) ?>"><?= icon('download') ?>Ekspor Excel</a>
    <a class="btn btn-secondary" href="<?= e(display_url()) ?>" target="_blank" rel="noopener"><?= icon('display') ?>Layar display</a>
    <a class="btn" href="#manual"><?= icon('plus', 16) ?>Input manual</a>
  </div>
</div>

<div class="strip">
  <div><span><?= e($rangeLabel) ?></span><strong><?= num($counts['total']) ?></strong></div>
  <div><span>Siswa</span><strong><?= num($counts['siswa']) ?></strong></div>
  <div><span>Guru &amp; tendik</span><strong><?= num($counts['staf']) ?></strong></div>
  <div><span>Tamu</span><strong><?= num($counts['tamu']) ?></strong></div>
  <div><span>Rata-rata lama</span><strong><?= num($counts['avg_min']) ?><small> menit</small></strong></div>
  <?php if ($isToday): ?><div><span>Di dalam sekarang</span><strong style="color:var(--ok)"><?= num($inside) ?></strong></div><?php endif; ?>
</div>

<div class="split">
  <section class="card wide" style="overflow:hidden">
    <form class="card-toolbar" method="get">
      <label class="field" style="flex-direction:row;align-items:center;gap:8px"><span class="sr-only">Dari tanggal</span><input class="input" type="date" name="dari" value="<?= e($from) ?>" style="width:auto" aria-label="Dari tanggal"></label>
      <span class="muted small">s.d.</span>
      <input class="input" type="date" name="sampai" value="<?= e($to) ?>" style="width:auto" aria-label="Sampai tanggal">
      <?php if ($cat !== ''): ?><input type="hidden" name="kategori" value="<?= e($cat) ?>"><?php endif; ?>
      <label class="input-icon" style="flex:1 1 180px"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e(g('q')) ?>" placeholder="Cari nama atau kelas" aria-label="Cari nama atau kelas"></label>
      <select class="select" name="metode" aria-label="Metode" data-autosubmit style="width:auto"><option value="">Semua metode</option><?= select_options(['qr' => 'QR', 'manual' => 'Manual', 'tamu' => 'Tamu', 'cari' => 'Cari di kiosk'], g('metode'), true) ?></select>
      <button class="btn btn-secondary" type="submit">Terapkan</button>
      <?php if (!$isToday): ?><a class="btn btn-ghost" href="<?= e(turl('/admin/kunjungan')) ?>">Hari ini</a><?php endif; ?>
    </form>
    <div class="pills" style="padding:14px 20px 0">
      <?php foreach ($catTabs as $k => $lbl): ?><a class="pill <?= $cat === $k ? 'is-active' : '' ?>" href="<?= e(qs(['kategori' => $k ?: null, 'hal' => null])) ?>"><?= e($lbl) ?></a><?php endforeach; ?>
      <?php if ($isToday): ?><a class="pill <?= g('di_dalam') ? 'is-active' : '' ?>" href="<?= e(qs(['di_dalam' => g('di_dalam') ? null : 1, 'hal' => null])) ?>">Masih di dalam</a><?php endif; ?>
    </div>
    <div class="table-wrap" style="margin-top:14px">
      <table class="table">
        <thead><tr><th>Masuk</th><th>Nama</th><th>Keperluan</th><th>Metode</th><th>Keluar</th><th class="actions"></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="6"><div class="empty"><?= icon('users', 34) ?>Belum ada kunjungan pada periode ini.</div></td></tr><?php endif; ?>
        <?php foreach ($rows as $v):
          $tone = $v['category'] === 'tamu' ? 'avatar-mute' : (in_array($v['category'], ['guru', 'tendik'], true) ? 'avatar-gold' : '');
          $open = !$v['check_out'] && substr($v['check_in'], 0, 10) === today(); ?>
          <tr>
            <td class="nowrap"><strong class="num"><?= e(fmt_time($v['check_in'])) ?></strong><?php if ($multiDay || substr($v['check_in'], 0, 10) !== today()): ?><span class="sub"><?= e(fmt_date($v['check_in'], 'short')) ?></span><?php endif; ?></td>
            <td><div class="row" style="flex-wrap:nowrap;gap:12px"><?= avatar_html($v['name'], null, $tone) ?><span style="min-width:0"><strong><?= e($v['name']) ?></strong><span class="sub"><?= e(visit_meta($v)) ?></span></span></div></td>
            <td class="<?= $v['purpose'] ? '' : 'muted' ?>"><?= e($v['purpose'] ?: '—') ?></td>
            <td><?= chip(label('method', $v['method']), LABELS['method_tone'][$v['method']] ?? 'mute', $v['manual_reason'] ? 'title="' . e($v['manual_reason'] . ($v['staff_name'] ? ' · dicatat ' . $v['staff_name'] : '')) . '"' : '') ?></td>
            <td class="nowrap">
              <?php if ($v['check_out']): ?>
                <span class="num" style="font-weight:600"><?= e(fmt_time($v['check_out'])) ?></span><span class="sub"><?= e(fmt_duration(max(0, minutes_between($v['check_in'], $v['check_out'])))) ?></span>
              <?php elseif ($open): ?>
                <span style="color:var(--ok);font-weight:700">Masih di dalam</span>
              <?php else: ?><span class="muted">—</span><?php endif; ?>
            </td>
            <td class="actions">
              <?php if ($open): ?>
                <form method="post" action="<?= e(turl('/admin/kunjungan/' . $v['id'] . '/keluar')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="dari" value="<?= e(g('dari')) ?>"><input type="hidden" name="sampai" value="<?= e(g('sampai')) ?>"><button class="btn btn-sm btn-secondary" type="submit">Catat keluar</button></form>
              <?php endif; ?>
              <?php if (Auth::can('dashboard')): ?>
                <form method="post" action="<?= e(turl('/admin/kunjungan/' . $v['id'] . '/hapus')) ?>" style="display:inline" data-confirm="Hapus kunjungan <?= e($v['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="dari" value="<?= e(g('dari')) ?>"><input type="hidden" name="sampai" value="<?= e(g('sampai')) ?>"><button class="btn-icon" type="submit" aria-label="Hapus kunjungan <?= e($v['name']) ?>" title="Hapus" style="display:inline-flex;align-items:center;justify-content:center;vertical-align:middle"><?= icon('trash', 16) ?></button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pager_html($pg, 'kunjungan') ?>
  </section>

  <section class="card card-pad stack side" id="manual" aria-label="Input kunjungan manual">
    <div class="stack-sm" style="gap:4px"><h2>Input manual</h2><p class="muted small">Untuk pengunjung yang lupa membawa kartu, belum punya kartu, atau saat pemindai bermasalah.</p></div>
    <form method="post" action="<?= e(turl('/admin/kunjungan/manual')) ?>" class="stack" id="manualForm" data-search-url="<?= e(turl('/admin/kunjungan/cari-member')) ?>">
      <?= csrf_field() ?>
      <div class="segmented" role="radiogroup" aria-label="Jenis pengunjung">
        <label><input type="radio" name="mode" value="member" <?= $mode !== 'tamu' ? 'checked' : '' ?>>Member</label>
        <label><input type="radio" name="mode" value="tamu" <?= $mode === 'tamu' ? 'checked' : '' ?>>Tamu</label>
      </div>
      <div class="stack" data-for="member" <?= $mode === 'tamu' ? 'hidden' : '' ?>>
        <div class="stack-sm">
          <label class="field" for="memberSearch">Cari member</label>
          <label class="input-icon"><?= icon('search') ?><input class="input" id="memberSearch" autocomplete="off" placeholder="Nama, NISN, atau no. anggota"></label>
          <input type="hidden" name="member_id" id="memberId" value="">
          <div class="pick-list" id="memberResults" role="listbox" aria-label="Hasil pencarian" hidden></div>
          <div id="memberPicked"></div>
        </div>
        <label class="field">Alasan dicatat manual<select class="select" name="reason"><?= select_options(MANUAL_REASONS, old('reason', MANUAL_REASONS[0])) ?></select></label>
      </div>
      <div class="stack" data-for="tamu" <?= $mode === 'tamu' ? '' : 'hidden' ?>>
        <label class="field">Nama tamu<input class="input" name="guest_name" value="<?= e(old('guest_name')) ?>" placeholder="Nama lengkap"></label>
        <label class="field">Asal / instansi<input class="input" name="origin" value="<?= e(old('origin')) ?>" placeholder="Mis. komite sekolah, orang tua siswa"></label>
        <label class="field"><span>No. HP <span class="opt">(opsional)</span></span><input class="input" name="phone" type="tel" value="<?= e(old('phone')) ?>"></label>
      </div>
      <div class="grid-2" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
        <label class="field">Keperluan<select class="select" name="purpose"><?= select_options(array_values(array_unique(array_merge(VISIT_PURPOSES, GUEST_PURPOSES))), old('purpose', 'Membaca')) ?></select></label>
        <label class="field">Waktu masuk<input class="input" type="time" name="time" value="<?= e(date('H:i')) ?>"></label>
      </div>
      <button class="btn btn-lg btn-block" type="submit">Catat kunjungan</button>
      <p class="tiny muted" style="display:flex;gap:8px"><?= icon('info', 15) ?><span>Kunjungan manual diberi label tersendiri di laporan, lengkap dengan nama petugas yang mencatat.</span></p>
    </form>
  </section>
</div>

<script>
(function () {
  var form = document.getElementById('manualForm');
  form.querySelectorAll('input[name=mode]').forEach(function (r) {
    r.addEventListener('change', function () {
      form.querySelectorAll('[data-for]').forEach(function (b) { b.hidden = b.dataset.for !== r.value; });
    });
  });
  var input = document.getElementById('memberSearch'), list = document.getElementById('memberResults'), picked = document.getElementById('memberPicked'), hid = document.getElementById('memberId');
  var timer = null;
  input.addEventListener('input', function () {
    clearTimeout(timer);
    var q = input.value.trim();
    if (q.length < 2) { list.hidden = true; return; }
    timer = setTimeout(function () {
      api(form.dataset.searchUrl + '?q=' + encodeURIComponent(q)).then(function (d) {
        var items = d.items || [];
        list.innerHTML = items.length ? items.map(function (m) {
          return '<button type="button" role="option" data-id="' + m.id + '" data-name="' + escapeHtml(m.name) + '" data-meta="' + escapeHtml(m.meta) + '"' + (m.inside ? ' data-inside="' + escapeHtml(m.inside) + '"' : '') + '>' +
            '<span class="avatar">' + escapeHtml(m.initials) + '</span><span class="grow"><strong>' + escapeHtml(m.name) + '</strong><span class="sub">' + escapeHtml(m.meta) + (m.inside ? ' · sudah masuk ' + escapeHtml(m.inside) : '') + '</span></span></button>';
        }).join('') : '<div class="muted small" style="padding:12px 14px">Tidak ada member aktif yang cocok.</div>';
        list.hidden = false;
      });
    }, 250);
  });
  list.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-id]');
    if (!b) return;
    hid.value = b.dataset.id;
    list.hidden = true;
    input.value = '';
    picked.innerHTML = '<div class="option-card" style="cursor:default;align-items:center;border:1.5px solid var(--blue-600);background:var(--blue-50)"><span class="grow"><strong>' + escapeHtml(b.dataset.name) + '</strong><span>' + escapeHtml(b.dataset.meta) + '</span></span>' +
      (b.dataset.inside ? '<span class="chip chip-warn">Sudah masuk ' + escapeHtml(b.dataset.inside) + '</span>' : '<span class="chip chip-ok">Dipilih</span>') + '</div>';
  });
  form.addEventListener('submit', function (e) {
    var mode = form.querySelector('input[name=mode]:checked').value;
    if (mode === 'member' && !hid.value) { e.preventDefault(); toast('Cari lalu pilih member dulu.', 'bad'); input.focus(); }
  });
})();
</script>
