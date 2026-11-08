<?php
declare(strict_types=1);

function install_run_schema(PDO $pdo): void
{
    $sql = (string)file_get_contents(APP . '/schema.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) $pdo->exec($stmt);
    $pdo->prepare('INSERT INTO meta (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')->execute(['schema_version', '1']);
}

function install_is_complete(): bool
{
    try {
        return (int)DB::val('SELECT COUNT(*) FROM platform_admins') > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/** Server requirements shown on the installer. [label, ok, required] */
function install_requirements(): array
{
    return [
        ['PHP 8.1 atau lebih baru (sekarang ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1.0', '>='), true],
        ['Ekstensi pdo_mysql', extension_loaded('pdo_mysql'), true],
        ['Folder storage bisa ditulis', is_writable(STORAGE) && is_writable(STORAGE . '/uploads') && is_writable(STORAGE . '/sessions'), true],
        ['Ekstensi zip (impor/ekspor Excel)', class_exists('ZipArchive'), false],
        ['Ekstensi curl (WhatsApp & data ISBN)', function_exists('curl_init'), false],
    ];
}

/** Web installer: /install. Works only until the first super admin exists. */
function install_handle(bool $installed): void
{
    if ($installed && install_is_complete()) {
        throw new HttpException('Aplikasi sudah terpasang. Masuk lewat /superadmin.', 404);
    }
    $errors = [];
    $done = null;
    $defaults = [
        'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
        'base_url' => (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . App::$base,
        'admin_name' => '', 'admin_user' => '', 'admin_pass' => '',
    ];
    $f = $defaults;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_check();
        foreach ($defaults as $k => $_) $f[$k] = trim((string)($_POST[$k] ?? ''));
        if (!$installed && ($f['db_name'] === '' || $f['db_user'] === '')) $errors[] = 'Nama database dan user database wajib diisi.';
        if ($f['admin_name'] === '' || !preg_match('/^[a-z0-9._-]{3,40}$/i', $f['admin_user'])) $errors[] = 'Nama dan username super admin wajib diisi (huruf/angka, minimal 3 karakter).';
        if (strlen($f['admin_pass']) < 8) $errors[] = 'Kata sandi super admin minimal 8 karakter.';
        foreach (install_requirements() as [$lbl, $ok, $req]) if ($req && !$ok) $errors[] = 'Syarat server belum terpenuhi: ' . $lbl . '.';
        if (!$errors) {
            try {
                $dbc = $installed ? App::$config['db'] : ['host' => $f['db_host'], 'port' => (int)$f['db_port'], 'name' => $f['db_name'], 'user' => $f['db_user'], 'pass' => $f['db_pass']];
                $pdo = DB::connect($dbc);
                install_run_schema($pdo);
                DB::use($pdo);
                DB::insert('platform_admins', ['username' => strtolower($f['admin_user']), 'name' => $f['admin_name'],
                    'password_hash' => password_hash($f['admin_pass'], PASSWORD_DEFAULT), 'created_at' => now()]);
                if (!$installed) {
                    $config = [
                        'db' => $dbc,
                        'base_url' => rtrim($f['base_url'], '/'),
                        'app_key' => bin2hex(random_bytes(24)),
                        'wa_token' => '',
                        'timezone' => 'Asia/Jakarta',
                        'debug' => false,
                    ];
                    $php = "<?php\n// Dibuat otomatis oleh installer pada " . now() . "\nreturn " . var_export($config, true) . ";\n";
                    if (@file_put_contents(ROOT . '/config.php', $php) === false) {
                        $done = ['manual' => $php];
                    }
                }
                if (!$done) {
                    flash('ok', 'Instalasi selesai. Silakan masuk sebagai super admin.');
                    redirect(url('/superadmin/login'));
                }
            } catch (PDOException $e) {
                $errors[] = 'Gagal terhubung atau membuat tabel: ' . $e->getMessage();
            }
        }
    }
    echo view('platform/install', ['f' => $f, 'errors' => $errors, 'installed' => $installed, 'done' => $done, 'title' => 'Instalasi'], 'bare');
}
