<?php
$days = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'];
$sw = fn(string $name, bool $on, string $title, string $desc = '') => '<div class="toggle-row"><div class="grow"><strong>' . e($title) . '</strong>' . ($desc ? '<span class="muted">' . e($desc) . '</span>' : '') . '</div>'
    . '<label class="switch"><input type="checkbox" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . ' aria-label="' . e($title) . '"><span></span></label></div>';
$sections = ['profil' => 'Profil perpustakaan', 'tautan' => 'Tautan & QR', 'jam' => 'Jam layanan', 'aturan' => 'Aturan peminjaman', 'registrasi' => 'Registrasi member',
    'display' => 'Kiosk & layar display', 'wa' => 'WhatsApp', 'daftar' => 'Daftar pilihan', 'tata-tertib' => 'Tata tertib'];
if (Auth::can('petugas')) $sections['petugas'] = 'Petugas & hak akses';
$sections['cadangan'] = 'Cadangan data';
$regUrl = abs_url(turl('/daftar'));
$token = (string)($s['wa']['token'] ?? '');
$platformToken = trim((string)(App::$config['wa_token'] ?? '')) !== '';
?>
<div class="page-head">
  <div><span class="crumb">Lainnya</span><h1>Pengaturan</h1></div>
</div>

<div class="split">
  <nav aria-label="Bagian pengaturan" class="card" style="flex:1 1 210px;max-width:100%;padding:10px;display:flex;flex-direction:column;gap:2px">
    <?php foreach ($sections as $id => $lbl): ?>
      <a href="#<?= e($id) ?>" style="display:flex;align-items:center;height:40px;padding:0 12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;color:var(--ink-2)"><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="stack" style="flex:999 1 560px;min-width:0">

    <form id="profil" class="card card-pad stack" method="post" enctype="multipart/form-data" action="<?= e(turl('/admin/pengaturan/profil')) ?>">
      <?= csrf_field() ?>
      <div><h2>Profil perpustakaan</h2><p class="card-sub">Tampil di kartu member, kiosk, layar display, dan kop laporan.</p></div>
      <div class="row" style="align-items:center;gap:18px">
        <span class="mark" style="width:96px;height:96px;border-radius:18px" id="logoPrev"><?= $s['logo'] ? '<img src="' . e(media_url($s['logo'])) . '" alt="Logo">' : icon('logo', 48) ?></span>
        <div class="stack-sm grow" style="min-width:220px">
          <span class="small" style="line-height:1.5">Unggah logo resmi sekolah, sebaiknya PNG dengan latar transparan. Selama belum diunggah, sistem memakai ikon buku.</span>
          <div class="row"><label class="btn btn-secondary btn-sm"><?= icon('upload', 15) ?>Unggah logo<input type="file" name="logo" accept="image/png,image/jpeg,image/webp" hidden onchange="this.closest('form').querySelector('#logoName').textContent = this.files[0] ? this.files[0].name : ''"></label>
            <span class="small muted" id="logoName"></span>
            <?php if ($s['logo']): ?><label class="check small"><input type="checkbox" name="remove_logo" value="1"> Hapus logo</label><?php endif; ?></div>
        </div>
      </div>
      <div class="grid-2">
        <label class="field">Nama perpustakaan<input class="input" name="library_name" value="<?= e($s['library_name']) ?>"></label>
        <label class="field">Nama sekolah<input class="input" name="school_name" value="<?= e($s['school_name']) ?>"></label>
        <label class="field">Nama singkat<input class="input" name="short_name" value="<?= e($s['short_name']) ?>" placeholder="Mis. SMAN 3 Pemalang"><span class="hint">Dipakai di menu dan label yang sempit.</span></label>
        <label class="field">NPSN<input class="input mono" name="npsn" inputmode="numeric" value="<?= e($s['npsn']) ?>" placeholder="8 digit"></label>
        <label class="field">Kepala perpustakaan<input class="input" name="head_name" value="<?= e($s['head_name']) ?>" placeholder="Nama lengkap untuk tanda tangan kartu"></label>
        <label class="field">Awalan nomor anggota<input class="input mono" name="member_prefix" value="<?= e($s['member_prefix']) ?>" maxlength="6" style="text-transform:uppercase"><span class="hint">Contoh hasil: <?= e($s['member_prefix'] . '-' . date('y') . '-00001') ?></span></label>
        <label class="field span-all">Alamat<input class="input" name="address" value="<?= e($s['address']) ?>" placeholder="Alamat lengkap sekolah"></label>
      </div>
      <div><button class="btn" type="submit">Simpan profil</button></div>
    </form>

    <section id="tautan" class="card card-pad stack">
      <div><h2>Tautan &amp; QR</h2><p class="card-sub">Bagikan tautan pendaftaran ke grup kelas, atau cetak QR-nya untuk ditempel di perpustakaan.</p></div>
      <div class="row" style="align-items:flex-start;gap:20px">
        <div style="padding:10px;border:1px solid var(--line);border-radius:14px;flex-shrink:0"><div data-qr="<?= e($regUrl) ?>" data-qr-label="QR pendaftaran member" style="width:132px;height:132px"></div></div>
        <div class="stack grow" style="min-width:240px;gap:12px">
          <?php foreach ([['Pendaftaran member', $regUrl], ['Masuk member', abs_url(turl('/masuk'))], ['Kiosk meja depan', abs_url(turl('/kiosk'))], ['Layar display (tanpa login)', display_url(true)]] as [$lbl, $link]): ?>
            <div class="stack-sm" style="gap:4px"><span class="small" style="font-weight:600"><?= e($lbl) ?></span>
              <div class="row" style="flex-wrap:nowrap;gap:8px"><input class="input mono" value="<?= e($link) ?>" readonly style="font-size:13px;height:40px" aria-label="<?= e($lbl) ?>"><button class="btn btn-secondary btn-sm" type="button" data-copy="<?= e($link) ?>">Salin</button></div></div>
          <?php endforeach; ?>
        </div>
      </div>
      <form method="post" action="<?= e(turl('/admin/pengaturan/display-key')) ?>" data-confirm="Ganti tautan layar display? Monitor yang memakai tautan lama harus dibuka ulang dengan tautan baru."><?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm" type="submit"><?= icon('refresh', 15) ?>Ganti tautan layar display</button></form>
    </section>

    <form id="jam" class="card card-pad stack" method="post" action="<?= e(turl('/admin/pengaturan/jam')) ?>">
      <?= csrf_field() ?>
      <div><h2>Jam layanan</h2><p class="card-sub">Kiosk menolak check-in di luar jam ini, dan layar display menampilkan jadwalnya.</p></div>
      <div>
        <?php foreach ($days as $d => $name): $h = $s['hours'][$d] ?? ['open' => false, 'from' => '07:00', 'to' => '15:00']; ?>
          <div class="toggle-row" style="flex-wrap:wrap">
            <strong style="flex:1 1 120px;font-size:15px"><?= e($name) ?></strong>
            <div class="row" style="gap:8px"><input class="input" type="time" name="from[<?= $d ?>]" value="<?= e($h['from']) ?>" style="width:auto;height:40px" aria-label="Buka hari <?= e($name) ?>"><span class="muted small">sampai</span><input class="input" type="time" name="to[<?= $d ?>]" value="<?= e($h['to']) ?>" style="width:auto;height:40px" aria-label="Tutup hari <?= e($name) ?>"></div>
            <label class="switch"><input type="checkbox" name="open[<?= $d ?>]" value="1" <?= !empty($h['open']) ? 'checked' : '' ?> aria-label="Buka hari <?= e($name) ?>"><span></span></label>
          </div>
        <?php endforeach; ?>
        <?= $sw('enforce_hours', !empty($s['enforce_hours']), 'Tolak check-in di luar jam layanan', 'Petugas tetap bisa mencatat kunjungan manual.') ?>
      </div>
      <div><button class="btn" type="submit">Simpan jam layanan</button></div>
    </form>

    <form id="aturan" class="card card-pad stack" method="post" action="<?= e(turl('/admin/pengaturan/aturan')) ?>">
      <?= csrf_field() ?>
      <div><h2>Aturan peminjaman</h2><p class="card-sub">Dipakai saat petugas memproses pinjam, perpanjang, dan kembali.</p></div>
      <?php $l = $s['loan']; ?>
      <div class="grid-2" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
        <?php foreach ([['max_siswa', 'Maks. buku · siswa', 'buku'], ['max_guru', 'Maks. buku · guru & tendik', 'buku'], ['days_siswa', 'Lama pinjam · siswa', 'hari'], ['days_guru', 'Lama pinjam · guru & tendik', 'hari'], ['extensions', 'Perpanjangan', 'kali']] as [$k, $lbl, $unit]): ?>
          <label class="field"><?= e($lbl) ?><span class="input-group"><input class="input" name="<?= e($k) ?>" inputmode="numeric" value="<?= e($l[$k]) ?>"><span class="addon"><?= e($unit) ?></span></span></label>
        <?php endforeach; ?>
        <label class="field">Denda keterlambatan<span class="input-group"><span class="addon">Rp</span><input class="input" name="fine_per_day" inputmode="numeric" value="<?= e($l['fine_per_day']) ?>"><span class="addon">/ hari</span></span></label>
        <label class="field">Akhir semester<input class="input" type="date" name="semester_end" value="<?= e($l['semester_end']) ?>"><span class="hint">Jatuh tempo buku paket.</span></label>
      </div>
      <div>
        <?= $sw('paket_semester', !empty($l['paket_semester']), 'Buku paket dipinjam per semester', 'Jatuh tempo mengikuti akhir semester, dan tidak dihitung dalam batas pinjam.') ?>
        <?= $sw('block_unpaid', !empty($l['block_unpaid']), 'Tahan peminjaman bila masih ada denda', 'Member dengan denda belum lunas tidak bisa meminjam lagi.') ?>
      </div>
      <div><button class="btn" type="submit">Simpan aturan</button></div>
    </form>

    <form id="registrasi" class="card card-pad stack" method="post" action="<?= e(turl('/admin/pengaturan/registrasi')) ?>">
      <?= csrf_field() ?>
      <div><h2>Registrasi member</h2><p class="card-sub">Atur alur pendaftaran mandiri siswa, guru, dan tendik.</p></div>
      <div>
        <?= $sw('open', !empty($s['reg']['open']), 'Pendaftaran mandiri dibuka', 'Siswa, guru, dan tendik bisa daftar sendiri lewat HP.') ?>
        <?= $sw('require_otp', !empty($s['reg']['require_otp']), 'Wajib verifikasi WhatsApp', wa_enabled() ? 'Kode OTP dikirim sebelum pendaftaran masuk antrean.' : 'Aktif setelah gateway WhatsApp diatur di bawah.') ?>
        <?= $sw('require_approval', !empty($s['reg']['require_approval']), 'Perlu persetujuan petugas', 'Matikan agar pendaftar langsung aktif setelah verifikasi.') ?>
        <label class="toggle-row" style="flex-wrap:wrap"><span class="grow"><strong>Masa berlaku kartu</strong><span class="muted">Untuk member yang baru disetujui.</span></span>
          <select class="select" name="validity" style="width:auto"><?= select_options(['lulus' => 'Siswa sampai lulus, guru selama aktif', '1y' => '1 tahun sejak disetujui', 'none' => 'Tanpa batas'], $s['reg']['validity'], true) ?></select></label>
      </div>
      <div><button class="btn" type="submit">Simpan</button></div>
    </form>

    <form id="display" class="card card-pad stack" method="post" action="<?= e(turl('/admin/pengaturan/display')) ?>">
      <?= csrf_field() ?>
      <div class="row-between" style="align-items:flex-start"><div><h2>Kiosk &amp; layar display</h2><p class="card-sub">Perubahan langsung tampil di layar tanpa memuat ulang.</p></div>
        <a class="btn btn-secondary btn-sm" href="<?= e(display_url()) ?>" target="_blank" rel="noopener"><?= icon('display', 15) ?>Pratinjau</a></div>
      <div>
        <?= $sw('show_classes', !empty($s['display']['show_classes']), 'Tampilkan papan kelas teraktif', 'Peringkat kunjungan per kelas minggu ini.') ?>
        <?= $sw('show_new_books', !empty($s['display']['show_new_books']), 'Tampilkan buku baru', 'Empat judul terbaru yang masuk katalog.') ?>
        <?= $sw('scan_out', !empty($s['kiosk']['scan_out']), 'Pindai kedua dicatat sebagai keluar', 'Supaya lama kunjungan terhitung.') ?>
        <?= $sw('allow_nisn', !empty($s['kiosk']['allow_nisn']), 'Terima barcode NISN dari kartu pelajar', 'Siswa bisa check-in dengan kartu pelajar yang ber-barcode NISN.') ?>
      </div>
      <div class="grid-2">
        <label class="field">Format nama di layar<select class="select" name="name_format"><?= select_options(['first_initial' => 'Nama depan + inisial (Nabila P.)', 'first' => 'Nama depan saja', 'full' => 'Nama lengkap'], $s['display']['name_format'], true) ?></select></label>
        <label class="field">Lama sambutan per pengunjung<span class="input-group"><input class="input" name="welcome_seconds" inputmode="numeric" value="<?= e($s['display']['welcome_seconds']) ?>"><span class="addon">detik</span></span></label>
        <label class="field">Jeda pindai ulang<span class="input-group"><input class="input" name="cooldown_minutes" inputmode="numeric" value="<?= e($s['kiosk']['cooldown_minutes']) ?>"><span class="addon">menit</span></span><span class="hint">Pindai ulang di bawah jeda ini tidak dicatat keluar.</span></label>
        <label class="field span-all">Teks pengumuman berjalan<textarea class="textarea" name="announcements" rows="3"><?= e(str_replace(' · ', "\n", $s['display']['announcements'])) ?></textarea><span class="hint">Satu pengumuman per baris.</span></label>
      </div>
      <div><button class="btn" type="submit">Simpan</button></div>
    </form>

    <section id="wa" class="card card-pad stack">
      <div><h2>WhatsApp</h2><p class="card-sub">Dipakai untuk kode verifikasi, kiriman kartu QR, dan pengingat jatuh tempo. Memakai layanan <a href="https://fonnte.com" target="_blank" rel="noopener">Fonnte</a>.</p></div>
      <div class="alert <?= wa_enabled() ? 'alert-ok' : 'alert-warn' ?>"><?= icon(wa_enabled() ? 'check' : 'alert') ?><span><?= wa_enabled() ? ($token !== '' ? 'Gateway WhatsApp sekolah ini sudah tersambung.' : 'Memakai gateway WhatsApp bawaan platform.') : 'Gateway belum diatur. Pendaftaran tetap berjalan tanpa verifikasi WhatsApp, dan pengingat tidak terkirim.' ?></span></div>
      <form method="post" action="<?= e(turl('/admin/pengaturan/wa')) ?>" class="stack" style="gap:12px">
        <?= csrf_field() ?>
        <label class="field">Token perangkat Fonnte<input class="input mono" name="token" autocomplete="off" value="<?= $token !== '' ? e(str_repeat('•', 8) . substr($token, -4)) : '' ?>" placeholder="<?= $platformToken ? 'Kosongkan untuk memakai gateway platform' : 'Tempel token dari menu Device di Fonnte' ?>"><span class="hint">Token tersimpan tersembunyi. Ketik ulang untuk menggantinya.</span></label>
        <div class="row"><button class="btn" type="submit">Simpan token</button><?php if ($token !== ''): ?><button class="btn btn-danger-outline" type="submit" name="remove" value="1">Hapus token</button><?php endif; ?></div>
      </form>
      <?php if (wa_enabled()): ?>
        <form method="post" action="<?= e(turl('/admin/pengaturan/wa-tes')) ?>" class="row" style="border-top:1px solid var(--line-2);padding-top:16px"><?= csrf_field() ?>
          <input class="input" name="phone" type="tel" placeholder="Nomor tujuan tes, mis. 0812…" style="flex:1 1 220px" required>
          <button class="btn btn-secondary" type="submit"><?= icon('chat', 16) ?>Kirim pesan tes</button>
        </form>
      <?php endif; ?>
    </section>

    <form id="daftar" class="card card-pad stack" method="post" action="<?= e(turl('/admin/pengaturan/daftar')) ?>">
      <?= csrf_field() ?>
      <div><h2>Daftar pilihan</h2><p class="card-sub">Satu item per baris. Daftar kelas dipakai di formulir pendaftaran dan kenaikan kelas.</p></div>
      <div class="grid-2">
        <label class="field">Daftar kelas<textarea class="textarea mono" name="kelas_list" rows="8"><?= e(implode("\n", $s['kelas_list'])) ?></textarea><span class="hint">Format X-1, XI-4, XII-2 agar kenaikan kelas otomatis bekerja.</span></label>
        <label class="field">Kategori buku<textarea class="textarea" name="categories" rows="8"><?= e(implode("\n", $s['categories'])) ?></textarea></label>
        <label class="field">Kategori aset<textarea class="textarea" name="asset_categories" rows="5"><?= e(implode("\n", $s['asset_categories'])) ?></textarea></label>
        <label class="field">Lokasi / ruang<textarea class="textarea" name="locations" rows="5"><?= e(implode("\n", $s['locations'])) ?></textarea></label>
      </div>
      <div><button class="btn" type="submit">Simpan daftar</button></div>
    </form>

    <form id="tata-tertib" class="card card-pad stack" method="post" action="<?= e(turl('/admin/pengaturan/tata-tertib')) ?>">
      <?= csrf_field() ?>
      <div><h2>Tata tertib</h2><p class="card-sub">Tampil di halaman tata tertib yang disetujui pendaftar. Aturan pinjam dan denda ditambahkan otomatis.</p></div>
      <label class="field"><span class="sr-only">Tata tertib</span><textarea class="textarea" name="rules_extra" rows="6"><?= e($s['rules_extra']) ?></textarea><span class="hint">Satu aturan per baris.</span></label>
      <div class="row"><button class="btn" type="submit">Simpan tata tertib</button><a class="btn btn-ghost" href="<?= e(turl('/tata-tertib')) ?>" target="_blank">Lihat halaman</a></div>
    </form>

    <?php if (Auth::can('petugas')): ?>
    <section id="petugas" class="card" style="overflow:hidden">
      <div class="card-head" style="padding-bottom:12px"><div><h2>Petugas &amp; hak akses</h2><p class="card-sub">Kepala perpustakaan: akses penuh. Pustakawan: semua kecuali kelola petugas dan pemulihan data. Petugas piket: kiosk dan input kunjungan.</p></div></div>
      <?php foreach ($users as $usr): ?>
        <details class="list-row" style="display:block;padding:0">
          <summary class="row" style="padding:14px 24px;cursor:pointer;list-style:none;flex-wrap:nowrap">
            <span class="avatar <?= $usr['role'] === 'kepala' ? 'avatar-gold' : ($usr['role'] === 'piket' ? 'avatar-mute' : '') ?>"><?= e(initials($usr['name'])) ?></span>
            <span class="grow" style="min-width:0"><strong><?= e($usr['name']) ?></strong><span class="muted small" style="display:block"><?= e($usr['username']) ?> · masuk terakhir <?= e($usr['last_login'] ? fmt_dt($usr['last_login']) : 'belum pernah') ?></span></span>
            <?= $usr['active'] ? chip(label('role', $usr['role']), $usr['role'] === 'kepala' ? 'gold' : ($usr['role'] === 'admin' ? 'info' : 'mute')) : chip('Nonaktif', 'mute') ?>
            <span class="btn btn-sm btn-secondary">Ubah</span>
          </summary>
          <form method="post" action="<?= e(turl('/admin/petugas/' . $usr['id'])) ?>" class="grid-2" style="padding:0 24px 18px;align-items:end">
            <?= csrf_field() ?>
            <label class="field">Nama<input class="input" name="name" value="<?= e($usr['name']) ?>"></label>
            <label class="field">Hak akses<select class="select" name="role"><?= select_options(LABELS['role'], $usr['role'], true) ?></select></label>
            <label class="field"><span>Kata sandi baru <span class="opt">(opsional)</span></span><input class="input" type="password" name="password" autocomplete="new-password" minlength="8" placeholder="Minimal 8 karakter"></label>
            <label class="check" style="height:44px;align-items:center"><input type="checkbox" name="active" value="1" <?= $usr['active'] ? 'checked' : '' ?>> Akun aktif</label>
            <div class="span-all"><button class="btn btn-sm" type="submit">Simpan</button></div>
          </form>
        </details>
      <?php endforeach; ?>
      <details style="border-top:1px solid var(--line-2)" <?= old('username') ? 'open' : '' ?>>
        <summary class="row" style="padding:14px 24px;cursor:pointer;list-style:none;color:var(--blue-600);font-weight:700"><?= icon('plus', 16) ?>Tambah petugas</summary>
        <form method="post" action="<?= e(turl('/admin/petugas')) ?>" class="grid-2" style="padding:0 24px 20px;align-items:end">
          <?= csrf_field() ?>
          <label class="field">Nama<input class="input" name="name" value="<?= e(old('name')) ?>" required></label>
          <label class="field">Nama pengguna<input class="input" name="username" value="<?= e(old('username')) ?>" autocomplete="off" required placeholder="mis. piket1"></label>
          <label class="field">Kata sandi<input class="input" type="password" name="password" autocomplete="new-password" minlength="8" required></label>
          <label class="field">Hak akses<select class="select" name="role"><?= select_options(LABELS['role'], old('role', 'piket'), true) ?></select></label>
          <div class="span-all"><button class="btn" type="submit">Buat akun</button></div>
        </form>
      </details>
    </section>
    <?php endif; ?>

    <section id="cadangan" class="card card-pad stack">
      <div class="row" style="flex-wrap:nowrap;align-items:flex-start;gap:16px">
        <span class="stat-icon" style="width:44px;height:44px;border-radius:12px;background:var(--ok-bg);color:var(--ok)"><?= icon('shield', 22) ?></span>
        <div class="grow"><h2>Cadangan data</h2><p class="card-sub">Unduh semua data perpustakaan ini (buku, member, kunjungan, sirkulasi, aset, pengaturan) dalam satu berkas. Foto dan logo tidak ikut. Simpan cadangan secara rutin, misalnya tiap akhir bulan.</p></div>
      </div>
      <div><a class="btn btn-secondary" href="<?= e(turl('/admin/cadangan')) ?>"><?= icon('download', 16) ?>Unduh cadangan</a></div>
      <?php if (Auth::can('pulihkan')): ?>
        <details style="border-top:1px solid var(--line-2);padding-top:14px">
          <summary style="cursor:pointer;font-weight:700;color:var(--bad)">Pulihkan dari cadangan</summary>
          <form method="post" action="<?= e(turl('/admin/cadangan/pulihkan')) ?>" enctype="multipart/form-data" class="stack" style="margin-top:12px;gap:12px">
            <?= csrf_field() ?>
            <div class="alert alert-bad"><?= icon('alert') ?><span>Semua data saat ini akan <b>diganti</b> dengan isi berkas cadangan. Unduh cadangan terbaru dulu sebelum memulihkan.</span></div>
            <label class="field">Berkas cadangan (.json)<input class="input" type="file" name="file" accept=".json,application/json" required style="padding-top:9px"></label>
            <label class="field">Ketik <b>PULIHKAN</b> untuk konfirmasi<input class="input" name="confirm" autocomplete="off" required></label>
            <div><button class="btn btn-danger" type="submit">Pulihkan data</button></div>
          </form>
        </details>
      <?php endif; ?>
    </section>
  </div>
</div>
