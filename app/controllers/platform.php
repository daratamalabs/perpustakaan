<?php
declare(strict_types=1);

const RESERVED_SLUGS = ['superadmin', 'install', 'assets', 'storage', 'app', 'api', 'admin', 'media', 'login', 'www'];

/** Root page: list of active schools. */
function platform_home(): string
{
    $schools = DB::q('SELECT * FROM tenants WHERE active = 1 ORDER BY name');
    return view('platform/home', ['schools' => $schools, 'title' => 'Perpustakaan Digital Sekolah'], 'bare');
}

function platform_login_form(): string
{
    if (Auth::platform()) redirect(url('/superadmin'));
    return view('platform/login', ['title' => 'Super admin', 'error' => null], 'bare');
}

function platform_login(): string
{
    $u = (string)p('username');
    $err = Auth::throttle('platform', 6);
    if (!$err) {
        $a = DB::one('SELECT * FROM platform_admins WHERE username = ?', [strtolower($u)]);
        if ($a && password_verify((string)p('password'), $a['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['platform'] = (int)$a['id'];
            DB::run('UPDATE platform_admins SET last_login = ? WHERE id = ?', [now(), $a['id']]);
            Auth::clearThrottle('platform');
            redirect(url('/superadmin'));
        }
        $err = 'Username atau kata sandi salah.';
    }
    return view('platform/login', ['title' => 'Super admin', 'error' => $err, 'username' => $u], 'bare');
}

function platform_logout(): never
{
    unset($_SESSION['platform']);
    redirect(url('/superadmin/login'));
}

function platform_dashboard(): string
{
    $admin = Auth::requirePlatform();
    $schools = DB::q("SELECT t.*,
        (SELECT COUNT(*) FROM members m WHERE m.tenant_id = t.id AND m.status = 'active') members,
        (SELECT COUNT(*) FROM books b WHERE b.tenant_id = t.id) books,
        (SELECT COUNT(*) FROM visits v WHERE v.tenant_id = t.id AND v.check_in >= ?) visits_month,
        (SELECT MAX(v.check_in) FROM visits v WHERE v.tenant_id = t.id) last_visit
        FROM tenants t ORDER BY t.name", [date('Y-m-01') . ' 00:00:00']);
    return view('platform/dashboard', ['admin' => $admin, 'schools' => $schools, 'title' => 'Daftar sekolah'], 'bare');
}

function platform_school_form(string $id = ''): string
{
    $admin = Auth::requirePlatform();
    $school = $id ? DB::one('SELECT * FROM tenants WHERE id = ?', [(int)$id]) : null;
    if ($id && !$school) abort(404);
    $staff = $school ? DB::q('SELECT * FROM users WHERE tenant_id = ? ORDER BY role, name', [$school['id']]) : [];
    $newPassword = $_SESSION['_newpass'] ?? null;
    unset($_SESSION['_newpass']);
    return view('platform/school', ['admin' => $admin, 'school' => $school, 'staff' => $staff, 'title' => $school ? $school['name'] : 'Tambah sekolah',
        'newPassword' => $newPassword], 'bare');
}

function platform_school_validate(?int $exceptId): array
{
    $d = [
        'slug' => strtolower((string)p('slug')),
        'name' => (string)p('name'),
        'short_name' => (string)p('short_name') ?: null,
        'city' => (string)p('city') ?: null,
        'contact_name' => (string)p('contact_name') ?: null,
        'contact_phone' => (string)p('contact_phone') ?: null,
        'notes' => (string)p('notes') ?: null,
    ];
    $errors = [];
    if (!preg_match('/^[a-z0-9][a-z0-9-]{1,59}$/', $d['slug'])) $errors[] = 'Alamat hanya boleh huruf kecil, angka, dan tanda minus (mis. sman3pemalang).';
    elseif (in_array($d['slug'], RESERVED_SLUGS, true)) $errors[] = 'Alamat "' . $d['slug'] . '" dipakai sistem. Pilih yang lain.';
    elseif (DB::val('SELECT id FROM tenants WHERE slug = ? AND id <> ?', [$d['slug'], $exceptId ?? 0])) $errors[] = 'Alamat sudah dipakai sekolah lain.';
    if (mb_strlen($d['name']) < 3) $errors[] = 'Nama sekolah wajib diisi.';
    return [$d, $errors];
}

function platform_school_create(): string
{
    Auth::requirePlatform();
    [$d, $errors] = platform_school_validate(null);
    $adminName = (string)p('admin_name');
    $adminUser = strtolower((string)p('admin_user'));
    $adminPass = (string)p('admin_pass');
    if ($adminName === '' || !preg_match('/^[a-z0-9._-]{3,40}$/', $adminUser)) $errors[] = 'Isi nama dan username admin sekolah (huruf kecil/angka).';
    if (strlen($adminPass) < 8) $errors[] = 'Kata sandi admin sekolah minimal 8 karakter.';
    if ($errors) {
        keep_old($_POST);
        foreach ($errors as $e) flash('error', $e);
        redirect(url('/superadmin/sekolah/baru'));
    }
    $tid = DB::tx(function () use ($d, $adminName, $adminUser, $adminPass) {
        $tid = DB::insert('tenants', $d + ['active' => 1, 'created_at' => now()]);
        DB::insert('users', ['tenant_id' => $tid, 'username' => $adminUser, 'name' => $adminName, 'role' => 'kepala',
            'password_hash' => password_hash($adminPass, PASSWORD_DEFAULT), 'active' => 1, 'created_at' => now()]);
        return $tid;
    });
    flash('ok', 'Sekolah ' . $d['name'] . ' dibuat. Alamat: /' . $d['slug']);
    redirect(url('/superadmin/sekolah/' . $tid));
}

function platform_school_update(string $id): string
{
    Auth::requirePlatform();
    $school = DB::one('SELECT * FROM tenants WHERE id = ?', [(int)$id]) ?? abort(404);
    [$d, $errors] = platform_school_validate((int)$id);
    if ($errors) {
        foreach ($errors as $e) flash('error', $e);
        redirect(url('/superadmin/sekolah/' . $id));
    }
    $d['active'] = p('active') ? 1 : 0;
    DB::update('tenants', $d, 'id = ?', [(int)$id]);
    flash('ok', 'Data sekolah disimpan.' . ($school['slug'] !== $d['slug'] ? ' Alamat berubah menjadi /' . $d['slug'] . '.' : ''));
    redirect(url('/superadmin/sekolah/' . $id));
}

function platform_school_reset_password(string $id): string
{
    Auth::requirePlatform();
    $uid = (int)p('user_id');
    $u = DB::one('SELECT * FROM users WHERE id = ? AND tenant_id = ?', [$uid, (int)$id]) ?? abort(404);
    $pass = substr(str_replace(['-', '_'], '', random_token(12)), 0, 10);
    DB::run('UPDATE users SET password_hash = ?, active = 1 WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $uid]);
    $_SESSION['_newpass'] = ['user' => $u['username'], 'pass' => $pass];
    flash('ok', 'Kata sandi ' . $u['username'] . ' direset. Catat kata sandi baru di bawah, hanya tampil sekali.');
    redirect(url('/superadmin/sekolah/' . $id));
}

function platform_account(): string
{
    $admin = Auth::requirePlatform();
    return view('platform/account', ['admin' => $admin, 'title' => 'Akun super admin'], 'bare');
}

function platform_account_save(): string
{
    $admin = Auth::requirePlatform();
    $row = DB::one('SELECT * FROM platform_admins WHERE id = ?', [$admin['id']]);
    if (!password_verify((string)p('current'), $row['password_hash'])) {
        flash('error', 'Kata sandi lama salah.');
    } elseif (strlen((string)p('new')) < 8) {
        flash('error', 'Kata sandi baru minimal 8 karakter.');
    } else {
        DB::run('UPDATE platform_admins SET password_hash = ?, name = ? WHERE id = ?', [password_hash((string)p('new'), PASSWORD_DEFAULT), (string)p('name') ?: $row['name'], $admin['id']]);
        flash('ok', 'Akun diperbarui.');
    }
    redirect(url('/superadmin/akun'));
}
