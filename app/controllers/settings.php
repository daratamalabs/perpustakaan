<?php
declare(strict_types=1);

const BACKUP_TABLES = ['settings', 'users', 'members', 'students_ref', 'books', 'copies', 'loans', 'visits', 'assets', 'asset_logs'];

function settings_index(): string
{
    $u = Auth::requireStaff('pengaturan');
    $users = Auth::can('petugas') ? DB::q("SELECT * FROM users WHERE tenant_id = ? ORDER BY active DESC, FIELD(role, 'kepala', 'admin', 'piket'), name", [Tenant::id()]) : [];
    return view('admin/settings', ['s' => Tenant::settings(), 'users' => $users, 'me' => $u, 'title' => 'Pengaturan', 'active' => 'pengaturan',
        'scripts' => ['vendor/qrcode.js']], 'admin');
}

/** Lines of a textarea → clean unique list. */
function lines_list(string $text, bool $upper = false): array
{
    $out = [];
    foreach (preg_split('/\r?\n|,/', $text) as $l) {
        $l = trim(preg_replace('/\s+/', ' ', $l));
        if ($l === '') continue;
        if ($upper) $l = strtoupper($l);
        $out[$l] = true;
    }
    return array_keys($out);
}

function int_between(mixed $v, int $min, int $max, int $default): int
{
    if (!is_numeric($v)) return $default;
    return max($min, min($max, (int)$v));
}

function settings_save(string $section): never
{
    $u = Auth::requireStaff('pengaturan');
    $s = Tenant::settings();
    $anchor = $section;
    switch ($section) {
        case 'profil':
            foreach (['library_name' => 120, 'school_name' => 120, 'short_name' => 40, 'npsn' => 12, 'address' => 255, 'head_name' => 120] as $k => $max) {
                Tenant::set($k, mb_substr(trim((string)p($k)), 0, $max));
            }
            if (trim((string)p('library_name')) === '') Tenant::set('library_name', 'Perpustakaan ' . App::$tenant['name']);
            if (trim((string)p('short_name')) === '') Tenant::set('short_name', App::$tenant['short_name'] ?: App::$tenant['name']);
            $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)p('member_prefix')));
            if ($prefix !== '') Tenant::set('member_prefix', substr($prefix, 0, 6));
            $logo = save_upload('logo', 'logo', 2_000_000);
            if ($logo) { delete_upload($s['logo']); Tenant::set('logo', $logo); }
            elseif (p('remove_logo')) { delete_upload($s['logo']); Tenant::set('logo', ''); }
            break;

        case 'jam':
            $hours = [];
            foreach (range(0, 6) as $d) {
                $from = preg_match('/^\d{2}:\d{2}$/', (string)($_POST['from'][$d] ?? '')) ? $_POST['from'][$d] : '07:00';
                $to = preg_match('/^\d{2}:\d{2}$/', (string)($_POST['to'][$d] ?? '')) ? $_POST['to'][$d] : '15:00';
                if ($to < $from) [$from, $to] = [$to, $from];
                $hours[$d] = ['open' => !empty($_POST['open'][$d]), 'from' => $from, 'to' => $to];
            }
            Tenant::set('hours', $hours);
            Tenant::set('enforce_hours', (bool)p('enforce_hours'));
            break;

        case 'aturan':
            Tenant::set('loan', [
                'max_siswa' => int_between(p('max_siswa'), 1, 50, 3),
                'max_guru' => int_between(p('max_guru'), 1, 100, 5),
                'days_siswa' => int_between(p('days_siswa'), 1, 365, 7),
                'days_guru' => int_between(p('days_guru'), 1, 365, 14),
                'extensions' => int_between(p('extensions'), 0, 10, 1),
                'fine_per_day' => int_between(preg_replace('/\D/', '', (string)p('fine_per_day')), 0, 1_000_000, 0),
                'paket_semester' => (bool)p('paket_semester'),
                'semester_end' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)p('semester_end')) ? p('semester_end') : '',
                'block_unpaid' => (bool)p('block_unpaid'),
            ]);
            break;

        case 'registrasi':
            Tenant::set('reg', [
                'open' => (bool)p('open'),
                'require_otp' => (bool)p('require_otp'),
                'require_approval' => (bool)p('require_approval'),
                'validity' => in_array(p('validity'), ['lulus', '1y', 'none'], true) ? p('validity') : 'lulus',
            ]);
            break;

        case 'display':
            $d = $s['display'];
            Tenant::set('display', [
                'name_format' => in_array(p('name_format'), ['first_initial', 'first', 'full'], true) ? p('name_format') : 'first_initial',
                'show_classes' => (bool)p('show_classes'),
                'show_new_books' => (bool)p('show_new_books'),
                'welcome_seconds' => int_between(p('welcome_seconds'), 3, 60, 8),
                'announcements' => mb_substr(trim(preg_replace('/\s*\n\s*/', ' · ', (string)p('announcements'))), 0, 600),
                'key' => $d['key'],
            ]);
            Tenant::set('kiosk', [
                'cooldown_minutes' => int_between(p('cooldown_minutes'), 1, 240, 5),
                'scan_out' => (bool)p('scan_out'),
                'allow_nisn' => (bool)p('allow_nisn'),
            ]);
            break;

        case 'display-key':
            $d = $s['display'];
            $d['key'] = random_token(12);
            Tenant::set('display', $d);
            flash('ok', 'Tautan layar display diganti. Buka tautan baru di monitor meja depan.');
            redirect(turl('/admin/pengaturan') . '#tautan');

        case 'wa':
            $token = trim((string)p('token'));
            $wa = $s['wa'];
            if (p('remove')) $wa['token'] = '';
            elseif ($token !== '' && !str_contains($token, '•')) $wa['token'] = $token;
            Tenant::set('wa', $wa);
            break;

        case 'wa-tes':
            $phone = norm_phone((string)p('phone'));
            $r = wa_send($phone, 'Tes pesan dari ' . $s['library_name'] . '. Gateway WhatsApp sudah tersambung.');
            flash($r['ok'] ? 'ok' : 'error', $r['ok'] ? "Pesan tes terkirim ke $phone." : 'Pesan tes gagal: ' . ($r['reason'] ?? ''));
            redirect(turl('/admin/pengaturan') . '#wa');

        case 'daftar':
            $kelas = lines_list((string)p('kelas_list'), true);
            if ($kelas) Tenant::set('kelas_list', $kelas);
            foreach (['categories', 'asset_categories', 'locations'] as $k) {
                $list = lines_list((string)p($k));
                if ($list) Tenant::set($k, $list);
            }
            break;

        case 'tata-tertib':
            Tenant::set('rules_extra', mb_substr(trim((string)p('rules_extra')), 0, 3000));
            break;

        default:
            abort(404);
    }
    flash('ok', 'Pengaturan disimpan.');
    redirect(turl('/admin/pengaturan') . '#' . $anchor);
}

/* ---------------- petugas ---------------- */

function staff_save(string $id = ''): never
{
    $me = Auth::requireStaff('petugas');
    $tid = Tenant::id();
    $back = turl('/admin/pengaturan') . '#petugas';
    $role = in_array(p('role'), ['kepala', 'admin', 'piket'], true) ? p('role') : 'admin';
    $name = trim((string)p('name'));
    $password = (string)p('password');
    $user = $id !== '' ? (DB::one('SELECT * FROM users WHERE id = ? AND tenant_id = ?', [(int)$id, $tid]) ?? abort(404)) : null;

    if ($user) {
        $active = (bool)p('active');
        if ((int)$user['id'] === (int)$me['id'] && (!$active || $role !== 'kepala')) {
            flash('error', 'Anda tidak bisa menonaktifkan atau menurunkan akses akun sendiri.'); redirect($back);
        }
        $kepalaLeft = (int)DB::val("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND role = 'kepala' AND active = 1 AND id <> ?", [$tid, $user['id']]);
        if ($user['role'] === 'kepala' && ($role !== 'kepala' || !$active) && $kepalaLeft === 0) {
            flash('error', 'Harus ada minimal satu akun kepala perpustakaan yang aktif.'); redirect($back);
        }
        $upd = ['name' => $name ?: $user['name'], 'role' => $role, 'active' => $active ? 1 : 0];
        if ($password !== '') {
            if (strlen($password) < 8) { flash('error', 'Kata sandi minimal 8 karakter.'); redirect($back); }
            $upd['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        DB::update('users', $upd, 'id = ?', [$user['id']]);
        flash('ok', 'Akun ' . $upd['name'] . ' diperbarui.');
        redirect($back);
    }

    $username = strtolower(preg_replace('/[^a-z0-9._-]/i', '', (string)p('username')));
    if ($name === '' || strlen($username) < 3) { flash('error', 'Isi nama dan nama pengguna (minimal 3 huruf, tanpa spasi).'); keep_old($_POST); redirect($back); }
    if (strlen($password) < 8) { flash('error', 'Kata sandi minimal 8 karakter.'); keep_old($_POST); redirect($back); }
    if (DB::val('SELECT id FROM users WHERE tenant_id = ? AND username = ?', [$tid, $username])) { flash('error', "Nama pengguna $username sudah dipakai."); keep_old($_POST); redirect($back); }
    DB::insert('users', ['tenant_id' => $tid, 'username' => $username, 'name' => $name, 'role' => $role,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'active' => 1, 'created_at' => now()]);
    flash('ok', "Akun petugas $name dibuat. Masuk dengan nama pengguna $username.");
    redirect($back);
}

/* ---------------- cadangan ---------------- */

function backup_download(): never
{
    Auth::requireStaff('pengaturan');
    $tid = Tenant::id();
    $data = ['format' => 'perpustakaan-backup', 'version' => 1, 'app' => APP_VERSION, 'created_at' => now(),
        'tenant' => ['slug' => App::$tenant['slug'], 'name' => App::$tenant['name']], 'tables' => []];
    foreach (BACKUP_TABLES as $t) $data['tables'][$t] = DB::q("SELECT * FROM `$t` WHERE tenant_id = ?", [$tid]);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="cadangan-' . App::$tenant['slug'] . '-' . date('Y-m-d-His') . '.json"');
    header('Content-Length: ' . strlen($json));
    echo $json;
    exit;
}

function backup_restore(): never
{
    $me = Auth::requireStaff('pulihkan');
    $tid = Tenant::id();
    $back = turl('/admin/pengaturan') . '#cadangan';
    if (p('confirm') !== 'PULIHKAN') { flash('error', 'Ketik PULIHKAN untuk konfirmasi.'); redirect($back); }
    if (empty($_FILES['file']['tmp_name'])) { flash('error', 'Pilih berkas cadangan (.json).'); redirect($back); }
    $data = json_decode((string)file_get_contents($_FILES['file']['tmp_name']), true);
    if (!is_array($data) || ($data['format'] ?? '') !== 'perpustakaan-backup' || !is_array($data['tables'] ?? null)) {
        flash('error', 'Berkas bukan cadangan perpustakaan yang valid.'); redirect($back);
    }
    if (($data['tenant']['slug'] ?? '') !== App::$tenant['slug']) {
        flash('error', 'Cadangan ini milik perpustakaan lain (' . ($data['tenant']['name'] ?? '?') . ').'); redirect($back);
    }
    set_time_limit(300);
    try {
        DB::tx(function () use ($data, $tid) {
            foreach (array_reverse(BACKUP_TABLES) as $t) DB::run("DELETE FROM `$t` WHERE tenant_id = ?", [$tid]);
            foreach (BACKUP_TABLES as $t) {
                $cols = array_column(DB::q("SHOW COLUMNS FROM `$t`"), 'Field');
                foreach ($data['tables'][$t] ?? [] as $row) {
                    $row = array_intersect_key((array)$row, array_flip($cols));
                    $row['tenant_id'] = $tid;
                    DB::insert($t, $row);
                }
            }
        });
    } catch (Throwable $e) {
        error_log((string)$e);
        flash('error', 'Pemulihan gagal, data tidak diubah: ' . $e->getMessage());
        redirect($back);
    }
    if (!DB::val('SELECT id FROM users WHERE id = ? AND tenant_id = ? AND active = 1', [$me['id'], $tid])) {
        Auth::logoutStaff();
        flash('ok', 'Data dipulihkan dari cadangan ' . fmt_dt($data['created_at'] ?? '') . '. Silakan masuk lagi.');
        redirect(turl('/admin/login'));
    }
    flash('ok', 'Data dipulihkan dari cadangan ' . fmt_dt($data['created_at'] ?? '') . '.');
    redirect($back);
}
