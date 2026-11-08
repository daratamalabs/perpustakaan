<?php
$v = fn(string $k, $d = '') => old($k, $m[$k] ?? $d);
$type = $v('type', 'siswa');
$isNew = !$m;
?>
<div class="page-head">
  <div><span class="crumb"><a href="<?= e(turl('/admin/member')) ?>">Member</a> / <?= $m ? 'Detail' : 'Tambah' ?></span><h1><?= $m ? e($m['name']) : 'Tambah member' ?></h1></div>
  <?php if ($m && $m['status'] === 'active'): ?>
    <div class="page-actions">
      <?php if ($m['phone'] && wa_enabled()): ?>
        <form method="post" action="<?= e(turl('/admin/member/' . $m['id'] . '/kirim-kartu')) ?>"><?= csrf_field() ?><button class="btn btn-secondary" type="submit"><?= icon('chat') ?>Kirim kartu ke WhatsApp</button></form>
      <?php endif; ?>
      <a class="btn btn-secondary" href="<?= e(turl('/admin/member/kartu?ids=' . $m['id'])) ?>" target="_blank"><?= icon('printer') ?>Cetak kartu</a>
    </div>
  <?php endif; ?>
</div>

<?php if ($m && in_array($m['status'], ['pending', 'otp'], true)): ?>
  <div class="alert alert-gold" style="align-items:center">
    <?= icon('userplus') ?><span class="grow"><b>Pendaftaran menunggu persetujuan.</b><?= $m['status'] === 'otp' ? ' Nomor WhatsApp belum diverifikasi oleh pendaftar.' : '' ?></span>
    <form method="post" action="<?= e(turl('/admin/member/' . $m['id'] . '/tolak')) ?>" data-confirm="Tolak pendaftaran ini?"><?= csrf_field() ?><input type="hidden" name="back" value="detail"><button class="btn btn-sm btn-danger-outline" type="submit">Tolak</button></form>
    <form method="post" action="<?= e(turl('/admin/member/' . $m['id'] . '/setujui')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="detail"><button class="btn btn-sm" type="submit">Setujui</button></form>
  </div>
<?php endif; ?>

<div class="split">
  <div class="wide stack">
    <form class="card card-pad stack" method="post" enctype="multipart/form-data" action="<?= e($m ? turl('/admin/member/' . $m['id']) : turl('/admin/member/baru')) ?>" id="memberForm">
      <?= csrf_field() ?>
      <h2>Data member</h2>
      <div class="segmented" role="radiogroup" aria-label="Jenis member" style="max-width:420px">
        <?php foreach (LABELS['member_type'] as $k => $lbl): ?><label><input type="radio" name="type" value="<?= e($k) ?>" <?= $type === $k ? 'checked' : '' ?>><?= e($lbl) ?></label><?php endforeach; ?>
      </div>
      <div class="grid-2">
        <label class="field span-all">Nama lengkap<input class="input" name="name" required value="<?= e($v('name')) ?>"></label>
        <label class="field" data-only="siswa">NISN<input class="input mono" name="nisn" inputmode="numeric" maxlength="10" value="<?= e($v('nisn')) ?>"><span class="hint">10 digit</span></label>
        <label class="field" data-only="siswa">Kelas<select class="select" name="kelas"><option value="">Pilih kelas</option><?= select_options($s['kelas_list'], $v('kelas')) ?></select></label>
        <label class="field" data-only="staf"><span>NIP / NUPTK <span class="opt">(opsional)</span></span><input class="input mono" name="nip" value="<?= e($v('nip')) ?>"></label>
        <label class="field" data-only="staf"><span>Mapel / bagian <span class="opt">(opsional)</span></span><input class="input" name="subject" value="<?= e($v('subject')) ?>" placeholder="Mis. Biologi, Tata usaha"></label>
        <label class="field">Jenis kelamin<select class="select" name="gender"><?= select_options(['' => '—', 'L' => 'Laki-laki', 'P' => 'Perempuan'], $v('gender'), true) ?></select></label>
        <label class="field">No. WhatsApp<input class="input" name="phone" type="tel" value="<?= e($v('phone')) ?>" placeholder="08…"></label>
        <label class="field"><span>Email <span class="opt">(opsional)</span></span><input class="input" name="email" type="email" value="<?= e($v('email')) ?>"></label>
        <label class="field">Berlaku sampai<input class="input" type="date" name="valid_until" value="<?= e($v('valid_until')) ?>"><span class="hint">Kosongkan untuk otomatis (siswa: sampai lulus).</span></label>
        <label class="field"><span><?= $isNew ? 'PIN masuk' : 'Ganti PIN' ?> <span class="opt">(opsional)</span></span><input class="input mono" name="pin" inputmode="numeric" maxlength="6" autocomplete="new-password" placeholder="6 angka"><span class="hint"><?= $isNew ? 'Kosongkan untuk dibuatkan PIN acak.' : 'Kosongkan jika tidak diganti.' ?></span></label>
        <div class="field span-all">Foto
          <label class="dropzone">
            <span class="thumb" id="photoPrev"><?php if ($m && $m['photo']): ?><img src="<?= e(media_url($m['photo'])) ?>" alt=""><?php else: ?><?= icon('camera', 26) ?><?php endif; ?></span>
            <span class="stack-sm" style="gap:4px"><strong style="color:var(--blue-600)">Ambil atau unggah foto</strong><span class="muted small">Dipakai di kartu cetak. Otomatis diperkecil.</span></span>
            <input type="file" name="photo" accept="image/*" data-resize="800" data-preview="#photoPrev" hidden>
          </label>
          <?php if ($m && $m['photo']): ?><label class="check" style="margin-top:6px"><input type="checkbox" name="remove_photo" value="1"> Hapus foto</label><?php endif; ?>
        </div>
      </div>
      <div class="row"><button class="btn" type="submit"><?= $isNew ? 'Simpan &amp; aktifkan member' : 'Simpan perubahan' ?></button><a class="btn btn-secondary" href="<?= e(turl('/admin/member')) ?>">Batal</a></div>
    </form>

    <?php if ($m): ?>
      <section class="card" style="overflow:hidden">
        <div class="card-head" style="padding-bottom:14px"><h2>Peminjaman</h2><?php if ($unpaid): ?><?= chip('Denda belum lunas ' . rupiah($unpaid), 'bad') ?><?php endif; ?></div>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Buku</th><th>Dipinjam</th><th>Kembali</th><th>Status</th></tr></thead>
          <tbody>
          <?php if (!$loans): ?><tr><td colspan="4"><div class="empty" style="padding:24px"><?= icon('book', 28) ?>Belum pernah meminjam.</div></td></tr><?php endif; ?>
          <?php foreach ($loans as $l): [$st, $tone] = loan_status($l); ?>
            <tr><td><strong><?= e($l['title']) ?></strong><span class="sub mono" style="font-size:12px"><?= e($l['code']) ?></span></td>
              <td class="muted small nowrap"><?= e(fmt_date($l['loaned_at'])) ?></td>
              <td class="small nowrap"><?= e($l['returned_at'] ? fmt_date($l['returned_at']) : fmt_date($l['due_date'])) ?></td>
              <td><?= $l['returned_at'] && $l['fine_amount'] > 0 ? chip('Denda ' . rupiah($l['fine_amount']) . ($l['fine_paid'] ? ' · lunas' : ''), $l['fine_paid'] ? 'mute' : 'bad') : chip($st, $tone) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      </section>
      <section class="card" style="padding-bottom:8px">
        <div class="card-head" style="padding-bottom:12px"><h2>Kunjungan terakhir</h2><span class="muted small"><?= num($visitTotal) ?> kunjungan · <?= num($semester) ?> semester ini</span></div>
        <?php if (!$visits): ?><div class="empty" style="padding:24px"><?= icon('users', 28) ?>Belum pernah berkunjung.</div><?php endif; ?>
        <?php foreach ($visits as $vv): ?>
          <div class="list-row"><span class="num small" style="width:110px;font-weight:700"><?= e(fmt_date($vv['check_in'], 'short') . ' · ' . fmt_time($vv['check_in'])) ?></span>
            <span class="grow"><span><?= $vv['check_out'] ? 'Keluar ' . e(fmt_time($vv['check_out'])) . ' · ' . e(fmt_duration(max(0, minutes_between($vv['check_in'], $vv['check_out'])))) : 'Tanpa catatan keluar' ?><?= $vv['purpose'] ? ' · ' . e($vv['purpose']) : '' ?></span></span>
            <?= chip(label('method', $vv['method']), LABELS['method_tone'][$vv['method']] ?? 'mute') ?></div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </div>

  <?php if ($m): ?>
  <aside class="side stack">
    <section class="card card-pad stack" style="gap:14px">
      <div style="background:var(--navy-900);border-radius:16px;padding:18px;color:#fff;display:flex;flex-direction:column;gap:14px;align-items:center">
        <div style="background:#fff;padding:8px;border-radius:10px"><div data-qr="<?= e(member_qr_text($m)) ?>" data-qr-label="QR member <?= e($m['name']) ?>" style="width:150px;height:150px"></div></div>
        <span class="mono" style="font-weight:700;letter-spacing:.08em"><?= e($m['member_no'] ?: 'Belum ada no. anggota') ?></span>
      </div>
      <dl class="dl">
        <dt>Status</dt><dd><?= chip(label('member_status', $m['status']), LABELS['member_status_tone'][$m['status']] ?? 'mute') ?></dd>
        <dt>Berlaku s.d.</dt><dd><?= e($m['valid_until'] ? fmt_date($m['valid_until'], 'full') : 'Selama aktif') ?></dd>
        <dt>Terdaftar</dt><dd><?= e(fmt_date($m['created_at'], 'full')) ?> · <?= e(['daftar' => 'daftar sendiri', 'petugas' => 'oleh petugas', 'impor' => 'impor'][$m['source']] ?? $m['source']) ?></dd>
        <?php if ($m['approved_at']): ?><dt>Disetujui</dt><dd><?= e(fmt_date($m['approved_at'])) ?><?= $approver ? ' · ' . e($approver) : '' ?></dd><?php endif; ?>
        <?php if ($m['reject_reason']): ?><dt>Alasan ditolak</dt><dd><?= e($m['reject_reason']) ?></dd><?php endif; ?>
        <?php if ($ref): ?><dt>Data induk</dt><dd><?= e($ref['name']) ?> · <?= e($ref['kelas'] ?: '—') ?></dd><?php endif; ?>
      </dl>
    </section>
    <section class="card card-pad stack" style="gap:10px">
      <h2>Tindakan</h2>
      <?php if ($m['status'] === 'active'): ?>
        <form method="post" action="<?= e(turl('/admin/member/' . $m['id'] . '/status')) ?>" data-confirm="Nonaktifkan kartu <?= e($m['name']) ?>? Kartu tidak bisa dipakai untuk masuk atau meminjam."><?= csrf_field() ?><input type="hidden" name="status" value="inactive"><button class="btn btn-secondary btn-block" type="submit">Nonaktifkan kartu</button></form>
        <?php if ($m['type'] === 'siswa'): ?><form method="post" action="<?= e(turl('/admin/member/' . $m['id'] . '/status')) ?>" data-confirm="Tandai <?= e($m['name']) ?> sudah lulus?"><?= csrf_field() ?><input type="hidden" name="status" value="graduated"><button class="btn btn-secondary btn-block" type="submit">Tandai lulus</button></form><?php endif; ?>
      <?php elseif (in_array($m['status'], ['inactive', 'graduated', 'rejected'], true)): ?>
        <form method="post" action="<?= e(turl('/admin/member/' . $m['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="active"><button class="btn btn-block" type="submit">Aktifkan kartu</button></form>
      <?php endif; ?>
      <details>
        <summary class="btn btn-secondary btn-block" style="list-style:none"><?= icon('key', 16) ?>Reset PIN</summary>
        <form method="post" action="<?= e(turl('/admin/member/' . $m['id'] . '/reset-pin')) ?>" class="stack" style="margin-top:10px;gap:10px"><?= csrf_field() ?>
          <p class="small muted">PIN acak 6 angka akan dibuat dan ditampilkan sekali.</p>
          <?php if ($m['phone'] && wa_enabled()): ?><label class="check"><input type="checkbox" name="send" value="1" checked> Kirim juga ke WhatsApp</label><?php endif; ?>
          <button class="btn btn-navy" type="submit">Buat PIN baru</button>
        </form>
      </details>
      <form method="post" action="<?= e(turl('/admin/member/' . $m['id'] . '/qr-baru')) ?>" data-confirm="Buat QR baru? Kartu lama (cetak maupun di HP) tidak bisa dipakai lagi. Gunakan jika kartu hilang."><?= csrf_field() ?><button class="btn btn-danger-outline btn-block" type="submit"><?= icon('refresh', 16) ?>Kartu hilang · buat QR baru</button></form>
    </section>
  </aside>
  <?php endif; ?>
</div>
<script>
(function () {
  var f = document.getElementById('memberForm');
  function sync() {
    var t = f.querySelector('input[name=type]:checked').value;
    f.querySelectorAll('[data-only]').forEach(function (el) { el.hidden = (el.dataset.only === 'siswa') !== (t === 'siswa'); });
  }
  f.querySelectorAll('input[name=type]').forEach(function (r) { r.addEventListener('change', sync); });
  sync();
})();
</script>
