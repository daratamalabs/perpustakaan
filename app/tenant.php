<?php
declare(strict_types=1);

/** A school (tenant) and its settings. */
final class Tenant
{
    private static array $cache = [];

    public static function bySlug(string $slug): ?array
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,59}$/', $slug)) return null;
        return DB::one('SELECT * FROM tenants WHERE slug = ?', [$slug]);
    }

    public static function id(): int { return (int)(App::$tenant['id'] ?? 0); }

    public static function defaults(array $t): array
    {
        $kelas = [];
        foreach (['X', 'XI', 'XII'] as $lvl) for ($i = 1; $i <= 10; $i++) $kelas[] = "$lvl-$i";
        return [
            'library_name' => 'Perpustakaan ' . $t['name'],
            'school_name' => $t['name'],
            'short_name' => $t['short_name'] ?: $t['name'],
            'npsn' => '',
            'address' => '',
            'head_name' => '',
            'logo' => '',
            // sman3pemalang → S3P, smkn1comal → S1C; otherwise the first two letters.
            'member_prefix' => preg_match('/^([a-z])[a-z]*?(\d+)([a-z])?/', $t['slug'], $pm)
                ? strtoupper($pm[1] . $pm[2] . ($pm[3] ?? ''))
                : (strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $t['slug']), 0, 2)) ?: 'PS'),
            'hours' => [
                1 => ['open' => true, 'from' => '07:00', 'to' => '15:00'],
                2 => ['open' => true, 'from' => '07:00', 'to' => '15:00'],
                3 => ['open' => true, 'from' => '07:00', 'to' => '15:00'],
                4 => ['open' => true, 'from' => '07:00', 'to' => '15:00'],
                5 => ['open' => true, 'from' => '07:00', 'to' => '11:30'],
                6 => ['open' => false, 'from' => '07:00', 'to' => '12:00'],
                0 => ['open' => false, 'from' => '07:00', 'to' => '12:00'],
            ],
            'enforce_hours' => true,
            'loan' => ['max_siswa' => 3, 'max_guru' => 5, 'days_siswa' => 7, 'days_guru' => 14, 'extensions' => 1,
                'fine_per_day' => 500, 'paket_semester' => true, 'semester_end' => '', 'block_unpaid' => true],
            'reg' => ['open' => true, 'require_otp' => true, 'require_approval' => true, 'validity' => 'lulus'],
            'display' => ['name_format' => 'first_initial', 'show_classes' => true, 'show_new_books' => true, 'welcome_seconds' => 8,
                'announcements' => 'Selamat datang di perpustakaan · Kembalikan buku tepat waktu · Jaga ketenangan ruang baca',
                'key' => ''],
            'kiosk' => ['cooldown_minutes' => 5, 'scan_out' => true, 'allow_nisn' => true],
            'wa' => ['provider' => 'fonnte', 'token' => ''],
            'categories' => ['Buku paket', 'Fiksi', 'Nonfiksi', 'Referensi', 'Majalah'],
            'asset_categories' => ['Elektronik', 'Mebel', 'Peralatan', 'Lainnya'],
            'locations' => ['Ruang baca', 'Ruang koleksi', 'Meja depan', 'Meja sirkulasi', 'Gudang'],
            'kelas_list' => $kelas,
            'rules_extra' => "Jaga ketenangan dan kebersihan ruang baca.\nDilarang makan dan minum di area koleksi.\nKembalikan buku ke meja sirkulasi, bukan ke rak.",
            'last_promotion' => '',
        ];
    }

    public static function settings(?int $tid = null): array
    {
        $tid ??= self::id();
        if (isset(self::$cache[$tid])) return self::$cache[$tid];
        $t = $tid === self::id() ? App::$tenant : DB::one('SELECT * FROM tenants WHERE id = ?', [$tid]);
        $def = self::defaults($t);
        $stored = [];
        foreach (DB::q('SELECT k, v FROM settings WHERE tenant_id = ?', [$tid]) as $r) {
            $v = json_decode($r['v'], true);
            if (json_last_error() === JSON_ERROR_NONE) $stored[$r['k']] = $v;
        }
        $out = [];
        foreach ($def as $k => $d) {
            if (!array_key_exists($k, $stored)) $out[$k] = $d;
            elseif (is_array($d) && !array_is_list($d) && is_array($stored[$k])) $out[$k] = array_replace($d, $stored[$k]);
            else $out[$k] = $stored[$k];
        }
        // Display link key is generated once per school.
        if (empty($out['display']['key'])) {
            $out['display']['key'] = random_token(12);
            self::set('display', $out['display'], $tid);
        }
        return self::$cache[$tid] = $out;
    }

    public static function get(string $k): mixed { return self::settings()[$k] ?? null; }

    public static function set(string $k, mixed $v, ?int $tid = null): void
    {
        $tid ??= self::id();
        DB::run('INSERT INTO settings (tenant_id, k, v) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)',
            [$tid, $k, json_encode($v, JSON_UNESCAPED_UNICODE)]);
        unset(self::$cache[$tid]);
    }

    /** Name used in headers, e.g. "SMAN 3 Pemalang". */
    public static function shortName(): string { return (string)(self::get('short_name') ?: App::$tenant['name']); }
}
