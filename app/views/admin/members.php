<?php
$statusKey = ['menunggu' => 'pending', 'aktif' => 'active', 'nonaktif' => 'inactive', 'lulus' => 'graduated', 'ditolak' => 'rejected', 'otp' => 'otp'];
$isQueue = in_array($tab, ['menunggu', 'otp'], true);
$refBadge = function (array $m) use ($refs, $hasRef): string {
    if ($m['type'] !== 'siswa' || !$hasRef) return '';
    $r = $refs[$m['nisn']] ?? null;
    if (!$r) return chip('Tidak ada di data induk', 'warn');
    $same = mb_strtolower(trim($r['name'])) === mb_strtolower(trim($m['name'])) && (!$r['kelas'] || $r['kelas'] === $m['kelas']);
    return $same ? chip('Cocok data induk', 'ok') : chip('Beda dengan data induk', 'warn');
};
?>
<div class="page-head">
  <div><span class="crumb">Pengunjung</span><h1>Member</h1></div>
  <div class="page-actions">
    <a class="btn btn-secondary" href="<?= e(turl('/admin/member/kenaikan')) ?>"><?= icon('up') ?>Kenaikan kelas</a>
    <a class="btn btn-secondary" href="<?= e(turl('/admin/member/impor')) ?>"><?= icon('upload') ?>Impor data siswa</a>
    <a class="btn btn-secondary" href="<?= e(turl('/admin/member/ekspor')) ?>"><?= icon('download') ?>Ekspor</a>
    <a class="btn" href="<?= e(turl('/admin/member/baru')) ?>"><?= icon('plus', 16) ?>Tambah member</a>
  </div>
</div>

<nav class="tabs" aria-label="Status member">
  <?php foreach (MEMBER_TABS as $key => [$lbl]):
    $n = $counts[$statusKey[$key]] ?? 0;
    if (in_array($key, ['ditolak', 'otp'], true) && !$n && $tab !== $key) continue; ?>
    <a class="tab <?= $tab === $key ? 'is-active' : '' ?>" href="<?= e(turl('/admin/member') . '?tab=' . $key) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= e($lbl) ?><span class="count"><?= num($n) ?></span></a>
  <?php endforeach; ?>
</nav>

<div class="split">
  <section class="card wide" style="overflow:hidden">
    <form class="card-toolbar" method="get">
      <input type="hidden" name="tab" value="<?= e($tab) ?>">
      <label class="input-icon"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e(g('q')) ?>" placeholder="Cari nama, NISN, NIP, no. anggota, atau WhatsApp" aria-label="Cari member"></label>
      <select class="select" name="kelas" aria-label="Kelas" data-autosubmit style="width:auto"><option value="">Semua kelas</option><?= select_options($s['kelas_list'], g('kelas')) ?></select>
      <select class="select" name="jenis" aria-label="Jenis" data-autosubmit style="width:auto"><?= select_options(['' => 'Siswa, guru & tendik', 'siswa' => 'Siswa', 'guru' => 'Guru', 'tendik' => 'Tendik'], g('jenis'), true) ?></select>
      <button class="btn btn-secondary" type="submit">Cari</button>
    </form>

    <form method="post" action="<?= e(turl('/admin/member/massal')) ?>" id="bulkForm">
      <?= csrf_field() ?><input type="hidden" name="tab" value="<?= e($tab) ?>">
      <?php if ($rows): ?>
      <div class="row" style="padding:10px 20px;background:var(--blue-50);border-bottom:1px solid #D5E1F1">
        <label class="check" style="font-weight:600"><input type="checkbox" data-check-all=".row-check"><span id="selCount">Pilih semua</span></label>
        <span class="grow"></span>
        <?php if ($isQueue || $tab === 'ditolak'): ?>
          <?php if ($tab !== 'ditolak'): ?><button class="btn btn-sm btn-danger-outline" type="submit" name="action" value="tolak" onclick="return confirm('Tolak pendaftar yang dicentang?')">Tolak terpilih</button><?php endif; ?>
          <button class="btn btn-sm btn-gold" type="submit" name="action" value="setujui">Setujui terpilih</button>
        <?php elseif ($tab === 'aktif'): ?>
          <button class="btn btn-sm btn-secondary" type="submit" name="action" value="kartu"><?= icon('printer', 15) ?>Cetak kartu terpilih</button>
          <button class="btn btn-sm btn-danger-outline" type="submit" name="action" value="nonaktifkan" onclick="return confirm('Nonaktifkan member yang dicentang?')">Nonaktifkan</button>
        <?php else: ?>
          <button class="btn btn-sm btn-secondary" type="submit" name="action" value="aktifkan">Aktifkan lagi</button>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th style="width:28px"></th>
            <?php if ($isQueue): ?><th>Pendaftar</th><th>Verifikasi</th><th>Mendaftar</th><th class="actions"></th>
            <?php else: ?><th>Member</th><th>No. anggota</th><th>WhatsApp</th><th>Berlaku s.d.</th><th>Status</th><th class="actions"></th><?php endif; ?>
          </tr></thead>
          <tbody>
          <?php if (!$rows): ?><tr><td colspan="7"><div class="empty"><?= icon('member', 34) ?><?= g('q') || g('kelas') ? 'Tidak ada member yang cocok.' : ($tab === 'menunggu' ? 'Tidak ada pendaftar yang menunggu persetujuan.' : 'Belum ada member di daftar ini.') ?></div></td></tr><?php endif; ?>
          <?php foreach ($rows as $m):
            $isSel = $sel && (int)$sel['id'] === (int)$m['id']; ?>
            <tr class="<?= $isSel ? 'is-selected' : '' ?>" style="<?= $isSel ? 'box-shadow:inset 3px 0 0 var(--blue-600)' : '' ?>">
              <td><input type="checkbox" class="row-check" name="ids[]" value="<?= (int)$m['id'] ?>" aria-label="Pilih <?= e($m['name']) ?>" style="width:16px;height:16px;accent-color:var(--blue-600)"></td>
              <td><a href="<?= e($isQueue && $tab === 'menunggu' ? qs(['id' => $m['id']]) : turl('/admin/member/' . $m['id'])) ?>" style="display:flex;align-items:center;gap:12px;color:inherit;text-decoration:none">
                <?= avatar_html($m['name'], $m['photo'], $m['type'] === 'siswa' ? '' : 'avatar-gold') ?>
                <span style="min-width:0"><strong><?= e($m['name']) ?></strong><span class="sub"><?= e(member_meta($m)) ?><?= member_id_label($m) ? ' · ' . e(member_id_label($m)) : '' ?></span></span>
              </a></td>
              <?php if ($isQueue): ?>
                <td><div class="stack-sm" style="gap:4px;align-items:flex-start"><?= $m['phone_verified'] ? chip('WA terverifikasi', 'ok') : chip('WA belum diverifikasi', 'warn') ?><?= $refBadge($m) ?></div></td>
                <td class="nowrap muted small"><?= e(fmt_dt($m['created_at'])) ?></td>
                <td class="actions">
                  <button class="btn btn-sm btn-danger-outline" type="submit" formaction="<?= e(turl('/admin/member/' . $m['id'] . '/tolak')) ?>" onclick="return confirm('Tolak pendaftaran <?= e($m['name']) ?>?')">Tolak</button>
                  <button class="btn btn-sm" type="submit" formaction="<?= e(turl('/admin/member/' . $m['id'] . '/setujui')) ?>">Setujui</button>
                </td>
              <?php else: ?>
                <td class="mono small" style="font-weight:600"><?= e($m['member_no'] ?: '—') ?></td>
                <td class="small"><?= e($m['phone'] ?: '—') ?></td>
                <td class="small <?= $m['valid_until'] && $m['valid_until'] < today() ? '' : 'muted' ?>" style="<?= $m['valid_until'] && $m['valid_until'] < today() ? 'color:var(--bad);font-weight:700' : '' ?>"><?= e($m['valid_until'] ? fmt_date($m['valid_until'], 'month') : 'Selama aktif') ?></td>
                <td><?= chip(label('member_status', $m['status']), LABELS['member_status_tone'][$m['status']] ?? 'mute') ?></td>
                <td class="actions"><a class="btn btn-sm btn-ghost" href="<?= e(turl('/admin/member/' . $m['id'])) ?>">Detail</a></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </form>
    <?= pager_html($pg, 'member') ?>
  </section>

  <?php if ($sel): $ref = $sel['type'] === 'siswa' ? ($refs[$sel['nisn']] ?? student_ref_match($sel['nisn'])) : null; ?>
  <aside class="card card-pad stack side" aria-label="Tinjau pendaftar" style="gap:18px">
    <span class="eyebrow">Tinjau pendaftar</span>
    <div class="row" style="flex-wrap:nowrap;gap:16px">
      <div style="width:76px;height:96px;border-radius:12px;background:var(--blue-100);color:var(--blue-600);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0">
        <?php if ($sel['photo']): ?><img src="<?= e(media_url($sel['photo'])) ?>" alt="Foto <?= e($sel['name']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?><?= icon('user', 32) ?><?php endif; ?>
      </div>
      <div class="stack-sm" style="gap:4px;min-width:0">
        <span class="serif" style="font-size:26px;line-height:1.1"><?= e($sel['name']) ?></span>
        <span class="muted small"><?= e(member_meta($sel)) ?></span>
        <span><?= chip(label('member_status', $sel['status']), LABELS['member_status_tone'][$sel['status']]) ?></span>
      </div>
    </div>
    <dl class="dl">
      <?php if ($sel['type'] === 'siswa'): ?><dt>NISN</dt><dd class="mono"><?= e($sel['nisn']) ?></dd><dt>Kelas</dt><dd><?= e($sel['kelas']) ?></dd>
      <?php else: ?><dt>NIP</dt><dd class="mono"><?= e($sel['nip'] ?: '—') ?></dd><dt>Mapel / bagian</dt><dd><?= e($sel['subject'] ?: '—') ?></dd><?php endif; ?>
      <dt>Jenis kelamin</dt><dd><?= e(['L' => 'Laki-laki', 'P' => 'Perempuan'][$sel['gender']] ?? '—') ?></dd>
      <dt>WhatsApp</dt><dd><?= e($sel['phone'] ?: '—') ?><?= $sel['phone_verified'] ? ' ✓' : '' ?></dd>
      <dt>Email</dt><dd><?= e($sel['email'] ?: '—') ?></dd>
      <dt>Mendaftar</dt><dd><?= e(fmt_dt($sel['created_at'])) ?></dd>
    </dl>
    <?php if ($sel['type'] === 'siswa'): ?>
      <?php if (!$hasRef): ?>
        <div class="alert alert-info"><?= icon('info') ?><span>Impor data induk siswa dari tata usaha agar pendaftar bisa dicocokkan otomatis. <a href="<?= e(turl('/admin/member/impor')) ?>">Impor sekarang</a></span></div>
      <?php elseif (!$ref): ?>
        <div class="alert alert-warn"><?= icon('alert') ?><span>NISN <?= e($sel['nisn']) ?> tidak ada di data induk siswa. Periksa kembali sebelum menyetujui.</span></div>
      <?php else: $same = mb_strtolower(trim($ref['name'])) === mb_strtolower(trim($sel['name'])) && (!$ref['kelas'] || $ref['kelas'] === $sel['kelas']); ?>
        <div class="alert <?= $same ? 'alert-ok' : 'alert-warn' ?>"><?= icon($same ? 'check' : 'alert') ?><span><?= $same ? 'Cocok dengan data induk siswa.' : 'Data induk: <b>' . e($ref['name']) . '</b>, kelas ' . e($ref['kelas'] ?: '—') . '. Pastikan ini orang yang sama.' ?></span></div>
      <?php endif; ?>
    <?php endif; ?>
    <div class="stack-sm">
      <span class="small" style="font-weight:600">Pratinjau kartu</span>
      <div style="background:var(--navy-900);border-radius:14px;padding:14px;display:flex;gap:14px;align-items:center;color:#fff">
        <div style="background:#fff;padding:5px;border-radius:6px;flex-shrink:0"><div data-qr="<?= e(member_qr_text($sel)) ?>" style="width:63px;height:63px"></div></div>
        <div class="stack-sm" style="gap:2px;min-width:0"><span class="eyebrow" style="font-size:10px;color:var(--gold-500)">Kartu anggota</span><span class="serif" style="font-size:19px"><?= e($sel['name']) ?></span><span class="tiny" style="color:var(--soft-on-navy)"><?= e($sel['type'] === 'siswa' ? $sel['kelas'] : label('member_type', $sel['type'])) ?> · no. anggota dibuat saat disetujui</span></div>
      </div>
    </div>
    <form method="post" action="<?= e(turl('/admin/member/' . $sel['id'] . '/setujui')) ?>"><?= csrf_field() ?>
      <button class="btn btn-lg btn-block" type="submit"><?= icon('check', 18) ?>Setujui<?= wa_enabled() && $sel['phone'] ? ' &amp; kirim kartu' : '' ?></button>
    </form>
    <details>
      <summary class="btn btn-danger-outline btn-block" style="list-style:none">Tolak pendaftaran</summary>
      <form method="post" action="<?= e(turl('/admin/member/' . $sel['id'] . '/tolak')) ?>" class="stack" style="margin-top:12px;gap:12px">
        <?= csrf_field() ?>
        <label class="field"><span>Alasan <span class="opt">(opsional)</span></span><input class="input" name="reason" placeholder="Mis. NISN tidak sesuai, foto tidak jelas"></label>
        <?php if ($sel['phone'] && wa_enabled()): ?><label class="check"><input type="checkbox" name="notify" value="1" checked> Kabari lewat WhatsApp</label><?php endif; ?>
        <button class="btn btn-danger btn-block" type="submit">Tolak</button>
      </form>
    </details>
    <p class="tiny muted center"><?= wa_enabled() ? 'Tautan kartu QR dikirim ke WhatsApp pendaftar setelah disetujui.' : 'Gateway WhatsApp belum diatur, jadi pendaftar membuka kartunya sendiri lewat menu Masuk.' ?></p>
  </aside>
  <?php endif; ?>
</div>
<script>
document.addEventListener('selectionchange-rows', function () {
  var n = document.querySelectorAll('.row-check:checked').length;
  var el = document.getElementById('selCount');
  if (el) el.textContent = n ? n + ' dipilih' : 'Pilih semua';
});
</script>
