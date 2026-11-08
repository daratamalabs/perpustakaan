<?php
declare(strict_types=1);

/* ------------------------------------------------------------------ */
/* Request / response                                                  */
/* ------------------------------------------------------------------ */

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function e(mixed $s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function url(string $path = '/'): string { return App::$base . '/' . ltrim($path, '/'); }

/** URL inside the current school, e.g. turl('/admin') => /sman3pemalang/admin */
function turl(string $path = ''): string
{
    $slug = App::$tenant['slug'] ?? '';
    return App::$base . '/' . $slug . ($path === '' || $path === '/' ? '' : '/' . ltrim($path, '/'));
}

function abs_url(string $relative): string
{
    $base = rtrim((string)(App::$config['base_url'] ?? ''), '/');
    if ($base === '') {
        $base = (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $base . $relative;
    }
    // base_url already contains the install path; strip it from the relative URL.
    if (App::$base !== '' && str_starts_with($relative, App::$base)) $relative = substr($relative, strlen(App::$base));
    return $base . $relative;
}

function asset(string $path): string { return App::$base . '/assets/' . ltrim($path, '/') . '?v=' . APP_VERSION; }

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function p(string $k, mixed $d = ''): mixed
{
    $v = $_POST[$k] ?? $d;
    return is_string($v) ? trim($v) : $v;
}

function g(string $k, mixed $d = ''): mixed
{
    $v = $_GET[$k] ?? $d;
    return is_string($v) ? trim($v) : $v;
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function abort(int $code, string $msg = ''): never
{
    throw new HttpException($msg ?: ($code === 404 ? 'Halaman tidak ditemukan.' : 'Akses ditolak.'), $code);
}

/* ------------------------------------------------------------------ */
/* Flash messages, old input, CSRF                                     */
/* ------------------------------------------------------------------ */

function flash(string $type, string $msg): void { $_SESSION['_flash'][] = [$type, $msg]; }

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function keep_old(array $data): void { $_SESSION['_old'] = $data; }

function old(string $k, mixed $d = ''): mixed
{
    static $old = null;
    if ($old === null) { $old = $_SESSION['_old'] ?? []; unset($_SESSION['_old']); }
    return $old[$k] ?? $d;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(20));
    return $_SESSION['_csrf'];
}

function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }

function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        throw new HttpException('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.', 400);
    }
}

/* ------------------------------------------------------------------ */
/* Views                                                               */
/* ------------------------------------------------------------------ */

function view(string $name, array $data = [], ?string $layout = null): string
{
    $file = APP . '/views/' . $name . '.php';
    if (!is_file($file)) throw new RuntimeException("View $name tidak ada");
    $render = static function (string $__file, array $__data): string {
        extract($__data, EXTR_SKIP);
        ob_start();
        include $__file;
        return (string)ob_get_clean();
    };
    $content = $render($file, $data);
    if ($layout === null) return $content;
    return $render(APP . '/views/layout/' . $layout . '.php', $data + ['content' => $content]);
}

function partial(string $name, array $data = []): string { return view($name, $data); }

/* ------------------------------------------------------------------ */
/* Dates (Indonesian)                                                  */
/* ------------------------------------------------------------------ */

const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const BLN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const HR = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

function now(): string { return date('Y-m-d H:i:s'); }
function today(): string { return date('Y-m-d'); }

function ts(?string $s): ?int
{
    if (!$s) return null;
    $t = strtotime($s);
    return $t === false ? null : $t;
}

function fmt_date(?string $s, string $mode = 'default'): string
{
    $t = ts($s);
    if ($t === null) return '—';
    $d = (int)date('j', $t); $m = (int)date('n', $t) - 1; $y = date('Y', $t);
    return match ($mode) {
        'short' => "$d " . BLN[$m],
        'long' => HARI[(int)date('w', $t)] . ", $d " . BULAN[$m] . " $y",
        'month' => BULAN[$m] . " $y",
        'full' => "$d " . BULAN[$m] . " $y",
        default => "$d " . BLN[$m] . " $y",
    };
}

function fmt_time(?string $s): string { $t = ts($s); return $t === null ? '—' : date('H.i', $t); }
function fmt_dt(?string $s): string { return $s ? fmt_date($s) . ', ' . fmt_time($s) : '—'; }
function day_name(?string $s, bool $short = false): string { $t = ts($s); return $t === null ? '' : ($short ? HR : HARI)[(int)date('w', $t)]; }
function add_days(string $date, int $n): string { return date('Y-m-d', strtotime(substr($date, 0, 10) . " $n day")); }
function diff_days(string $a, string $b): int { return (int)round((strtotime(substr($b, 0, 10)) - strtotime(substr($a, 0, 10))) / 86400); }
function minutes_between(string $a, string $b): int { return (int)floor((strtotime($b) - strtotime($a)) / 60); }

function fmt_duration(int $mins): string
{
    if ($mins < 60) return $mins . ' mnt';
    return intdiv($mins, 60) . ' j ' . ($mins % 60) . ' mnt';
}

/* ------------------------------------------------------------------ */
/* Numbers, text                                                       */
/* ------------------------------------------------------------------ */

function num(mixed $n): string { return number_format((float)$n, 0, ',', '.'); }
function rupiah(mixed $n): string { return 'Rp' . num($n); }

function initials(?string $name): string
{
    $n = preg_replace('/\b(Bpk|Pak|Bu|Ibu|Drs|Dr|Hj|H|S\.Pd|M\.Pd)\b\.?/iu', ' ', (string)$name);
    $n = preg_replace("/[^\p{L}' ]/u", ' ', explode(',', $n)[0]);
    $parts = array_values(array_filter(preg_split('/\s+/', trim($n))));
    if (!$parts) return '?';
    $a = mb_substr($parts[0], 0, 1);
    $b = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($a . $b);
}

function display_name(?string $name, string $format = 'first_initial'): string
{
    $clean = trim(explode(',', (string)$name)[0]);
    $parts = array_values(array_filter(preg_split('/\s+/', $clean)));
    if ($format === 'full' || count($parts) < 2) return $clean;
    if ($format === 'first') return $parts[0];
    return $parts[0] . ' ' . mb_strtoupper(mb_substr($parts[1], 0, 1)) . '.';
}

function kelas_level(?string $kelas): string
{
    return preg_match('/^(XII|XI|X)\b/i', (string)$kelas, $m) ? strtoupper($m[1]) : '';
}

function norm_phone(?string $p): string
{
    $s = preg_replace('/[^0-9+]/', '', (string)$p);
    if (str_starts_with($s, '+62')) $s = '0' . substr($s, 3);
    elseif (str_starts_with($s, '62')) $s = '0' . substr($s, 2);
    return $s;
}

function random_token(int $bytes = 18): string { return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '='); }
function random_digits(int $len = 6): string { return str_pad((string)random_int(0, 10 ** $len - 1), $len, '0', STR_PAD_LEFT); }

function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return substr($s, 0, 60);
}

/* ------------------------------------------------------------------ */
/* Pagination & query strings                                          */
/* ------------------------------------------------------------------ */

function paginate(int $total, mixed $page, int $per = 20): array
{
    $pages = max(1, (int)ceil($total / $per));
    $p = min(max(1, (int)$page), $pages);
    $list = [];
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === 1 || $i === $pages || abs($i - $p) <= 1) $list[] = $i;
        elseif (end($list) !== '…') $list[] = '…';
    }
    return ['page' => $p, 'pages' => $pages, 'per' => $per, 'offset' => ($p - 1) * $per, 'total' => $total, 'list' => $list,
        'from' => $total ? ($p - 1) * $per + 1 : 0, 'to' => min($total, $p * $per)];
}

function qs(array $over = [], ?array $base = null): string
{
    $all = array_merge($base ?? $_GET, $over);
    $all = array_filter($all, fn($v) => $v !== null && $v !== '');
    return $all ? '?' . http_build_query($all) : '';
}

/* ------------------------------------------------------------------ */
/* Icons & labels                                                      */
/* ------------------------------------------------------------------ */

const ICONS = [
    'logo' => '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/>',
    'dash' => '<path d="M3 3h7v9H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 16h7v5H3z"/>',
    'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14zM20 17v4H6.5A2.5 2.5 0 0 1 4 18.5"/>',
    'sirk' => '<path d="M17 1l4 4-4 4M3 11V9a4 4 0 0 1 4-4h14M7 23l-4-4 4-4M21 13v2a4 4 0 0 1-4 4H3"/>',
    'asset' => '<path d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8"/>',
    'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'member' => '<path d="M3 5h18v14H3zM7 9h4v4H7zM14 10h4M14 14h4"/>',
    'report' => '<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>',
    'settings' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
    'display' => '<path d="M2 4h20v13H2zM8 21h8M12 17v4"/>',
    'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    'search' => '<path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.35-4.35"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'qr' => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h3v3h-3zM18 18h3v3h-3zM14 20h2M20 14v2"/>',
    'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
    'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
    'check' => '<path d="M20 6L9 17l-5-5"/>',
    'x' => '<path d="M18 6L6 18M6 6l12 12"/>',
    'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    'edit' => '<path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>',
    'scan' => '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 8v8M10 8v8M13 8v8M17 8v8"/>',
    'clock' => '<path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 6v6l4 2"/>',
    'printer' => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/>',
    'calendar' => '<path d="M3 4h18v18H3zM16 2v4M8 2v4M3 10h18"/>',
    'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/>',
    'userplus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM20 8v6M23 11h-6"/>',
    'alert' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/>',
    'info' => '<path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 16v-4M12 8h.01"/>',
    'camera' => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><path d="M12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/>',
    'left' => '<path d="M15 18l-6-6 6-6"/>',
    'right' => '<path d="M9 18l6-6-6-6"/>',
    'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'up' => '<path d="M12 19V5M5 12l7-7 7 7"/>',
    'more' => '<path d="M12 12h.01M19 12h.01M5 12h.01"/>',
    'trash' => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
    'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4"/>',
    'chat' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
    'sun' => '<path d="M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10zM12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/>',
    'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6"/>',
    'key' => '<path d="M21 2l-2 2m-7.6 7.6a5.5 5.5 0 1 1-7.78 7.78 5.5 5.5 0 0 1 7.78-7.78zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
    'refresh' => '<path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
    'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
    'help' => '<path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>',
    'pdf' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M12 18v-6M9 15l3 3 3-3"/>',
    'school' => '<path d="M22 10L12 5 2 10l10 5 10-5zM6 12v5c3 2 9 2 12 0v-5"/>',
    'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    'wrench' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
    'database' => '<path d="M12 8c4.97 0 9-1.34 9-3s-4.03-3-9-3-9 1.34-9 3 4.03 3 9 3zM21 12c0 1.66-4 3-9 3s-9-1.34-9-3M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
];

function icon(string $name, int $size = 18, string $extra = ''): string
{
    $body = ICONS[$name] ?? ICONS['info'];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" ' . $extra . '>' . $body . '</svg>';
}

const LABELS = [
    'member_type' => ['siswa' => 'Siswa', 'guru' => 'Guru', 'tendik' => 'Tendik'],
    'member_status' => ['otp' => 'Belum verifikasi', 'pending' => 'Menunggu', 'active' => 'Aktif', 'inactive' => 'Nonaktif', 'rejected' => 'Ditolak', 'graduated' => 'Lulus'],
    'member_status_tone' => ['otp' => 'warn', 'pending' => 'gold', 'active' => 'ok', 'inactive' => 'mute', 'rejected' => 'bad', 'graduated' => 'mute'],
    'kondisi' => ['baik' => 'Baik', 'rusak_ringan' => 'Rusak ringan', 'rusak_berat' => 'Rusak berat', 'hilang' => 'Hilang', 'perbaikan' => 'Dalam perbaikan', 'dihapus' => 'Dihapuskan'],
    'kondisi_tone' => ['baik' => 'ok', 'rusak_ringan' => 'warn', 'rusak_berat' => 'bad', 'hilang' => 'bad', 'perbaikan' => 'info', 'dihapus' => 'mute'],
    'collection' => ['pinjam' => 'Dapat dipinjam', 'baca' => 'Baca di tempat', 'paket' => 'Buku paket'],
    'method' => ['qr' => 'QR', 'manual' => 'Manual', 'tamu' => 'Tamu', 'cari' => 'Kiosk'],
    'method_tone' => ['qr' => 'info', 'manual' => 'gold', 'tamu' => 'mute', 'cari' => 'gold'],
    'category' => ['siswa' => 'Siswa', 'guru' => 'Guru', 'tendik' => 'Tendik', 'tamu' => 'Tamu'],
    'role' => ['kepala' => 'Kepala perpustakaan', 'admin' => 'Pustakawan', 'piket' => 'Petugas piket'],
    'role_access' => ['kepala' => 'Akses penuh', 'admin' => 'Admin (tanpa kelola petugas)', 'piket' => 'Kiosk & input kunjungan'],
];

function label(string $group, ?string $key): string { return LABELS[$group][$key ?? ''] ?? (string)$key; }

function chip(string $text, string $tone = 'mute', string $extra = ''): string
{
    return '<span class="chip chip-' . e($tone) . '" ' . $extra . '>' . e($text) . '</span>';
}

/* ------------------------------------------------------------------ */
/* Uploads                                                             */
/* ------------------------------------------------------------------ */

/**
 * Save an uploaded image for the current school. Returns the relative path
 * (e.g. "3/covers/abc.jpg") or null when no file was sent.
 */
function save_upload(string $field, string $sub, int $maxBytes = 3_000_000): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new HttpException('Unggah berkas gagal (kode ' . $f['error'] . ').', 400);
    if ($f['size'] > $maxBytes) throw new HttpException('Ukuran gambar maksimal ' . round($maxBytes / 1_000_000, 1) . ' MB.', 400);
    $info = @getimagesize($f['tmp_name']);
    $ext = match ($info[2] ?? 0) { IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', default => null };
    if (!$ext) throw new HttpException('Berkas harus berupa gambar JPG, PNG, atau WEBP.', 400);
    $tid = (int)(App::$tenant['id'] ?? 0);
    $dir = STORAGE . "/uploads/$tid/$sub";
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = random_token(12) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) throw new HttpException('Gagal menyimpan berkas.', 500);
    return "$tid/$sub/$name";
}

function delete_upload(?string $rel): void
{
    if (!$rel || str_contains($rel, '..')) return;
    $f = STORAGE . '/uploads/' . $rel;
    if (is_file($f)) @unlink($f);
}

function media_url(?string $rel): string
{
    if (!$rel) return '';
    $parts = explode('/', $rel, 2);
    return turl('/media/' . ($parts[1] ?? $rel));
}
