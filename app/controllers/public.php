<?php
declare(strict_types=1);

/* ------------------------------------------------------------------ */
/* Landing                                                             */
/* ------------------------------------------------------------------ */

function pub_home(): string
{
    $s = Tenant::settings();
    $open = library_open_now();
    return view('public/home', ['s' => $s, 'open' => $open, 'm' => Auth::member(), 'title' => $s['library_name']], 'public');
}

function pub_rules(): string
{
    return view('public/rules', ['s' => Tenant::settings(), 'title' => 'Tata tertib'], 'public');
}

/* ------------------------------------------------------------------ */
/* Registration                                                        */
/* ------------------------------------------------------------------ */

function pub_register_form(): string
{
    $s = Tenant::settings();
    if ($m = Auth::member()) redirect(turl($m['status'] === 'otp' ? '/daftar/verifikasi' : '/kartu'));
    return view('public/register', ['s' => $s, 'errors' => [], 'title' => 'Daftar member', 'otp' => !empty($s['reg']['require_otp']) && wa_enabled()], 'public');
}

function pub_register(): string
{
    $s = Tenant::settings();
    if (empty($s['reg']['open'])) abort(403, 'Pendaftaran mandiri sedang ditutup. Hubungi petugas perpustakaan.');
    $tid = Tenant::id();
    $type = in_array(p('type'), ['siswa', 'guru', 'tendik'], true) ? p('type') : 'siswa';
    $d = [
        'type' => $type,
        'name' => preg_replace('/\s+/', ' ', (string)p('name')),
        'nisn' => preg_replace('/\D/', '', (string)p('nisn')),
        'nip' => preg_replace('/\s+/', '', (string)p('nip')),
        'kelas' => (string)p('kelas'),
        'subject' => (string)p('subject'),
        'gender' => in_array(p('gender'), ['L', 'P'], true) ? p('gender') : null,
        'phone' => norm_phone((string)p('phone')),
        'email' => strtolower((string)p('email')),
    ];
    $pin = (string)p('pin');
    $errors = [];
    if (mb_strlen($d['name']) < 3) $errors['name'] = 'Isi nama lengkap.';
    if ($type === 'siswa') {
        if (!preg_match('/^\d{10}$/', $d['nisn'])) $errors['nisn'] = 'NISN harus 10 digit angka.';
        if (!in_array($d['kelas'], $s['kelas_list'], true)) $errors['kelas'] = 'Pilih kelas.';
        $d['nip'] = null; $d['subject'] = null;
    } else {
        $d['nisn'] = null; $d['kelas'] = null;
        if ($d['nip'] === '') $d['nip'] = null;
        if ($d['subject'] === '') $d['subject'] = null;
    }
    if (!preg_match('/^08\d{8,12}$/', $d['phone'])) $errors['phone'] = 'Nomor WhatsApp tidak valid (contoh 081234567890).';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Format email tidak valid.';
    if ($e = validate_pin($pin)) $errors['pin'] = $e;
    elseif ($pin !== (string)p('pin2')) $errors['pin2'] = 'Konfirmasi PIN tidak sama.';
    if (!p('agree')) $errors['agree'] = 'Centang persetujuan tata tertib.';

    // Duplicates
    if ($type === 'siswa' && empty($errors['nisn'])) {
        $dup = DB::one('SELECT id, status FROM members WHERE tenant_id = ? AND nisn = ?', [$tid, $d['nisn']]);
        if ($dup && $dup['status'] !== 'rejected') $errors['nisn'] = 'NISN ini sudah terdaftar. Silakan masuk dengan NISN dan PIN.';
        elseif ($dup) DB::run('DELETE FROM members WHERE id = ?', [$dup['id']]);
    }
    if ($type !== 'siswa' && $d['nip']) {
        $dup = DB::one("SELECT id FROM members WHERE tenant_id = ? AND nip = ? AND status <> 'rejected'", [$tid, $d['nip']]);
        if ($dup) $errors['nip'] = 'NIP ini sudah terdaftar.';
    }
    $photo = null;
    if (!$errors) {
        try { $photo = save_upload('photo', 'members', 4_000_000); } catch (HttpException $e) { $errors['photo'] = $e->getMessage(); }
    }
    if ($errors) {
        return view('public/register', ['s' => $s, 'errors' => $errors, 'f' => $d + ['agree' => p('agree')], 'title' => 'Daftar member',
            'otp' => !empty($s['reg']['require_otp']) && wa_enabled()], 'public');
    }

    $useOtp = !empty($s['reg']['require_otp']) && wa_enabled();
    $status = $useOtp ? 'otp' : (!empty($s['reg']['require_approval']) ? 'pending' : 'active');
    $d['email'] = $d['email'] ?: null;
    $id = DB::insert('members', $d + [
        'tenant_id' => $tid,
        'photo' => $photo,
        'pin_hash' => password_hash($pin, PASSWORD_DEFAULT),
        'qr_token' => random_token(18),
        'status' => $status,
        'source' => 'daftar',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $m = DB::one('SELECT * FROM members WHERE id = ?', [$id]);
    Auth::loginMember($m);
    if ($useOtp) {
        pub_send_otp($m);
        redirect(turl('/daftar/verifikasi'));
    }
    if ($status === 'active') {
        member_activate($id, null);
        flash('ok', 'Pendaftaran berhasil. Kartu QR kamu sudah aktif.');
        redirect(turl('/kartu'));
    }
    redirect(turl('/daftar/selesai'));
}

function pub_send_otp(array $m): array
{
    $code = random_digits(6);
    DB::update('members', ['otp_code' => password_hash($code, PASSWORD_DEFAULT), 'otp_expires' => date('Y-m-d H:i:s', time() + 600),
        'otp_attempts' => 0, 'otp_sent_at' => now()], 'id = ?', [$m['id']]);
    $lib = Tenant::get('library_name');
    return wa_send($m['phone'], "Kode verifikasi pendaftaran member $lib: *$code*\n\nBerlaku 10 menit. Jangan berikan kode ini kepada siapa pun.");
}

function pub_verify_form(): string
{
    $m = Auth::requireMember();
    if ($m['status'] !== 'otp') redirect(turl('/kartu'));
    $wait = max(0, 60 - (time() - (ts($m['otp_sent_at']) ?? 0)));
    return view('public/verify', ['m' => $m, 'error' => null, 'wait' => $wait, 'title' => 'Verifikasi WhatsApp'], 'public');
}

function pub_verify(): string
{
    $m = Auth::requireMember();
    if ($m['status'] !== 'otp') redirect(turl('/kartu'));
    $code = preg_replace('/\D/', '', (string)p('code'));
    $error = null;
    if ((int)$m['otp_attempts'] >= 5) $error = 'Terlalu banyak percobaan. Minta kode baru.';
    elseif (!$m['otp_expires'] || $m['otp_expires'] < now()) $error = 'Kode sudah kedaluwarsa. Minta kode baru.';
    elseif (!$m['otp_code'] || !password_verify($code, $m['otp_code'])) {
        DB::run('UPDATE members SET otp_attempts = otp_attempts + 1 WHERE id = ?', [$m['id']]);
        $error = 'Kode salah. Periksa lagi pesan WhatsApp-mu.';
    }
    if ($error) {
        $wait = max(0, 60 - (time() - (ts($m['otp_sent_at']) ?? 0)));
        return view('public/verify', ['m' => $m, 'error' => $error, 'wait' => $wait, 'title' => 'Verifikasi WhatsApp'], 'public');
    }
    $needApproval = !empty(Tenant::get('reg')['require_approval']);
    DB::update('members', ['phone_verified' => 1, 'otp_code' => null, 'otp_expires' => null, 'status' => $needApproval ? 'pending' : 'active', 'updated_at' => now()], 'id = ?', [$m['id']]);
    if (!$needApproval) {
        member_activate((int)$m['id'], null);
        flash('ok', 'Nomor terverifikasi. Kartu QR kamu sudah aktif.');
        redirect(turl('/kartu'));
    }
    redirect(turl('/daftar/selesai'));
}

function pub_verify_resend(): never
{
    $m = Auth::requireMember();
    if ($m['status'] !== 'otp') redirect(turl('/kartu'));
    if (time() - (ts($m['otp_sent_at']) ?? 0) < 60) {
        flash('warn', 'Tunggu sebentar sebelum meminta kode baru.');
    } else {
        $r = pub_send_otp($m);
        flash($r['ok'] ? 'ok' : 'error', $r['ok'] ? 'Kode baru sudah dikirim ke WhatsApp.' : 'Gagal mengirim kode: ' . ($r['reason'] ?? ''));
    }
    redirect(turl('/daftar/verifikasi'));
}

function pub_register_done(): string
{
    $m = Auth::requireMember();
    return view('public/done', ['m' => $m, 'title' => 'Pendaftaran diterima'], 'public');
}

/* ------------------------------------------------------------------ */
/* Login                                                               */
/* ------------------------------------------------------------------ */

function pub_login_form(): string
{
    if (Auth::member()) redirect(turl('/kartu'));
    return view('public/login', ['error' => null, 'title' => 'Masuk member'], 'public');
}

function pub_login(): string
{
    $id = trim((string)p('identifier'));
    $error = Auth::throttle('member-login', 10);
    if (!$error) {
        $tid = Tenant::id();
        $phone = norm_phone($id);
        $m = DB::one("SELECT * FROM members WHERE tenant_id = ? AND (nisn = ? OR member_no = ? OR (nip IS NOT NULL AND nip = ?) OR (phone <> '' AND phone = ?))
            ORDER BY FIELD(status,'active','pending','otp','inactive','graduated','rejected') LIMIT 1", [$tid, $id, strtoupper($id), $id, $phone]);
        if ($m && $m['pin_hash'] && password_verify((string)p('pin'), $m['pin_hash'])) {
            if (in_array($m['status'], ['inactive', 'rejected', 'graduated'], true)) {
                $error = 'Akun kamu berstatus ' . strtolower(label('member_status', $m['status'])) . '. Hubungi petugas perpustakaan.';
            } else {
                Auth::loginMember($m);
                Auth::clearThrottle('member-login');
                $next = (string)g('next');
                redirect($next && str_starts_with($next, turl()) ? $next : turl($m['status'] === 'otp' ? '/daftar/verifikasi' : '/kartu'));
            }
        } else {
            $error = $m && !$m['pin_hash'] ? 'Akun ini belum punya PIN. Minta petugas mengatur PIN kamu.' : 'NISN/NIP atau PIN salah.';
        }
    }
    return view('public/login', ['error' => $error, 'identifier' => $id, 'title' => 'Masuk member'], 'public');
}

function pub_logout(): never
{
    Auth::logoutMember();
    redirect(turl());
}

/* ------------------------------------------------------------------ */
/* Member area                                                         */
/* ------------------------------------------------------------------ */

function pub_card(): string
{
    $m = Auth::requireMember();
    if ($m['status'] === 'otp') redirect(turl('/daftar/verifikasi'));
    return view('public/card', ['m' => $m, 's' => Tenant::settings(), 'title' => 'Kartu member', 'tab' => 'kartu', 'dark' => true, 'scripts' => ['vendor/qrcode.js', 'js/card.js']], 'public');
}

function pub_card_print(): string
{
    $m = Auth::requireMember();
    if ($m['status'] !== 'active') redirect(turl('/kartu'));
    return view('public/card_print', ['members' => [$m], 's' => Tenant::settings(), 'back' => turl('/kartu'), 'title' => 'Cetak kartu', 'scripts' => ['vendor/qrcode.js']], 'bare');
}

function pub_member_home(): string
{
    $m = Auth::requireMember();
    if ($m['status'] === 'otp') redirect(turl('/daftar/verifikasi'));
    $loans = DB::q('SELECT l.*, b.title, b.author, b.cover, b.collection_type, c.code FROM loans l JOIN copies c ON c.id = l.copy_id JOIN books b ON b.id = c.book_id
        WHERE l.member_id = ? AND l.returned_at IS NULL ORDER BY l.due_date', [$m['id']]);
    $visits = DB::q('SELECT * FROM visits WHERE member_id = ? ORDER BY check_in DESC LIMIT 12', [$m['id']]);
    $monthCount = (int)DB::val('SELECT COUNT(*) FROM visits WHERE member_id = ? AND check_in >= ?', [$m['id'], date('Y-m-01') . ' 00:00:00']);
    $history = DB::q('SELECT l.*, b.title FROM loans l JOIN copies c ON c.id = l.copy_id JOIN books b ON b.id = c.book_id
        WHERE l.member_id = ? AND l.returned_at IS NOT NULL ORDER BY l.returned_at DESC LIMIT 5', [$m['id']]);
    return view('public/member_home', [
        'm' => $m, 'loans' => $loans, 'visits' => $visits, 'history' => $history, 'monthCount' => $monthCount,
        'semesterCount' => semester_count((int)$m['id']), 'rules' => loan_rules($m), 'unpaid' => loan_unpaid_fines((int)$m['id']),
        'title' => 'Beranda', 'tab' => 'beranda',
    ], 'public');
}

function pub_extend(string $id): never
{
    $m = Auth::requireMember();
    $loan = DB::one('SELECT id FROM loans WHERE id = ? AND member_id = ?', [(int)$id, $m['id']]) ?? abort(404);
    $r = loan_extend((int)$loan['id']);
    flash($r['ok'] ? 'ok' : 'error', $r['message']);
    redirect(turl('/beranda'));
}

function pub_profile(): string
{
    $m = Auth::requireMember();
    return view('public/profile', ['m' => $m, 'title' => 'Profil', 'tab' => 'profil'], 'public');
}

function pub_profile_save(): never
{
    $m = Auth::requireMember();
    if (p('action') === 'pin') {
        if (!password_verify((string)p('old_pin'), (string)$m['pin_hash'])) flash('error', 'PIN lama salah.');
        elseif ($e = validate_pin((string)p('new_pin'))) flash('error', $e);
        elseif (p('new_pin') !== p('new_pin2')) flash('error', 'Konfirmasi PIN baru tidak sama.');
        else {
            DB::run('UPDATE members SET pin_hash = ?, updated_at = ? WHERE id = ?', [password_hash((string)p('new_pin'), PASSWORD_DEFAULT), now(), $m['id']]);
            flash('ok', 'PIN berhasil diganti.');
        }
    } else {
        $email = strtolower((string)p('email'));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('error', 'Format email tidak valid.');
        else {
            $photo = save_upload('photo', 'members', 4_000_000);
            $upd = ['email' => $email ?: null, 'updated_at' => now()];
            if ($photo) { delete_upload($m['photo']); $upd['photo'] = $photo; }
            DB::update('members', $upd, 'id = ?', [$m['id']]);
            flash('ok', 'Profil disimpan.');
        }
    }
    redirect(turl('/profil'));
}

/* ------------------------------------------------------------------ */
/* Uploaded files                                                      */
/* ------------------------------------------------------------------ */

function pub_media(string $a, string $b): never
{
    if (!in_array($a, ['covers', 'logo', 'members', 'assets'], true) || !preg_match('/^[A-Za-z0-9_-]+\.(jpg|png|webp)$/', $b)) abort(404);
    $tid = Tenant::id();
    if ($a === 'members') {
        $owner = Auth::member();
        $ok = Auth::staff() || ($owner && $owner['photo'] === "$tid/members/$b");
        if (!$ok) abort(403);
    }
    $file = STORAGE . "/uploads/$tid/$a/$b";
    if (!is_file($file)) abort(404);
    $types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    header('Content-Type: ' . $types[pathinfo($b, PATHINFO_EXTENSION)]);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: ' . ($a === 'members' ? 'private' : 'public') . ', max-age=604800');
    readfile($file);
    exit;
}
