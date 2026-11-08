<?php
declare(strict_types=1);

final class Auth
{
    private static ?array $staff = null;
    private static ?array $member = null;
    private static bool $staffLoaded = false;
    private static bool $memberLoaded = false;

    /** Which admin areas each role may open. */
    public const ROLE_AREAS = [
        'kepala' => ['*'],
        'admin' => ['dashboard', 'buku', 'sirkulasi', 'aset', 'kunjungan', 'member', 'laporan', 'pengaturan', 'kiosk'],
        'piket' => ['kunjungan', 'kiosk'],
    ];

    /* ---------------- staff (per school) ---------------- */

    public static function staff(): ?array
    {
        if (self::$staffLoaded) return self::$staff;
        self::$staffLoaded = true;
        $id = $_SESSION['staff'][Tenant::id()] ?? null;
        if ($id) {
            $u = DB::one('SELECT id, tenant_id, username, name, role, active FROM users WHERE id = ? AND tenant_id = ?', [$id, Tenant::id()]);
            if ($u && $u['active']) self::$staff = $u;
            else unset($_SESSION['staff'][Tenant::id()]);
        }
        return self::$staff;
    }

    public static function can(string $area): bool
    {
        $u = self::staff();
        if (!$u) return false;
        $list = self::ROLE_AREAS[$u['role']] ?? [];
        return in_array('*', $list, true) || in_array($area, $list, true);
    }

    public static function requireStaff(?string $area = null): array
    {
        $u = self::staff();
        if (!$u) {
            if (wants_json()) json_out(['ok' => false, 'message' => 'Sesi berakhir. Silakan masuk lagi.'], 401);
            redirect(turl('/admin/login') . '?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''));
        }
        if ($area && !self::can($area)) abort(403, 'Akun Anda tidak memiliki akses ke halaman ini.');
        return $u;
    }

    public static function loginStaff(array $u, bool $remember): void
    {
        session_regenerate_id(true);
        $_SESSION['staff'][Tenant::id()] = (int)$u['id'];
        DB::run('UPDATE users SET last_login = ? WHERE id = ?', [now(), $u['id']]);
        if ($remember) App::rememberSession();
        self::$staffLoaded = false;
    }

    public static function logoutStaff(): void { unset($_SESSION['staff'][Tenant::id()]); self::$staff = null; }

    /** First page a staff member should land on after login. */
    public static function homeFor(array $u): string
    {
        return $u['role'] === 'piket' ? turl('/admin/kunjungan') : turl('/admin');
    }

    /* ---------------- members (per school) ---------------- */

    public static function member(): ?array
    {
        if (self::$memberLoaded) return self::$member;
        self::$memberLoaded = true;
        $id = $_SESSION['member'][Tenant::id()] ?? null;
        if ($id) {
            $m = DB::one('SELECT * FROM members WHERE id = ? AND tenant_id = ?', [$id, Tenant::id()]);
            if ($m && in_array($m['status'], ['active', 'pending', 'otp'], true)) self::$member = $m;
            else unset($_SESSION['member'][Tenant::id()]);
        }
        return self::$member;
    }

    public static function requireMember(): array
    {
        $m = self::member();
        if (!$m) redirect(turl('/masuk') . '?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''));
        return $m;
    }

    public static function loginMember(array $m): void
    {
        session_regenerate_id(true);
        $_SESSION['member'][Tenant::id()] = (int)$m['id'];
        App::rememberSession();
        self::$memberLoaded = false;
    }

    public static function logoutMember(): void { unset($_SESSION['member'][Tenant::id()]); self::$member = null; }

    /* ---------------- platform super admin ---------------- */

    public static function platform(): ?array
    {
        $id = $_SESSION['platform'] ?? null;
        return $id ? DB::one('SELECT id, username, name FROM platform_admins WHERE id = ?', [$id]) : null;
    }

    public static function requirePlatform(): array
    {
        $a = self::platform();
        if (!$a) redirect(url('/superadmin/login'));
        return $a;
    }

    /* ---------------- brute-force guard ---------------- */

    /** Returns an error message when too many attempts were made for this key. */
    public static function throttle(string $key, int $max = 8, int $window = 600): ?string
    {
        $k = '_th_' . md5($key . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $now = time();
        $h = $_SESSION[$k] ?? ['n' => 0, 'reset' => $now + $window];
        if ($h['reset'] < $now) $h = ['n' => 0, 'reset' => $now + $window];
        $h['n']++;
        $_SESSION[$k] = $h;
        if ($h['n'] > $max) return 'Terlalu banyak percobaan. Coba lagi dalam ' . ceil(($h['reset'] - $now) / 60) . ' menit.';
        return null;
    }

    public static function clearThrottle(string $key): void { unset($_SESSION['_th_' . md5($key . ($_SERVER['REMOTE_ADDR'] ?? ''))]); }
}
