<?php
declare(strict_types=1);

function stats_visit_counts(string $from, string $to): array
{
    $r = DB::one("SELECT COUNT(*) total,
            SUM(category = 'siswa') siswa, SUM(category IN ('guru','tendik')) staf, SUM(category = 'tamu') tamu,
            SUM(method = 'manual') manual,
            AVG(CASE WHEN check_out IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, check_in, check_out) END) avg_min
        FROM visits WHERE tenant_id = ? AND check_in BETWEEN ? AND ?", [Tenant::id(), "$from 00:00:00", "$to 23:59:59"]);
    return array_map(fn($v) => $v === null ? 0 : (float)$v, $r ?? []);
}

function stats_inside_now(): int
{
    return (int)DB::val('SELECT COUNT(*) FROM visits WHERE tenant_id = ? AND check_out IS NULL AND check_in >= ?', [Tenant::id(), today() . ' 00:00:00']);
}

/** @return array<string,array{siswa:int,staf:int,tamu:int,total:int}> keyed by Y-m-d */
function stats_daily(string $from, string $to): array
{
    $rows = DB::q("SELECT DATE(check_in) d, SUM(category = 'siswa') siswa, SUM(category IN ('guru','tendik')) staf, SUM(category = 'tamu') tamu, COUNT(*) total
        FROM visits WHERE tenant_id = ? AND check_in BETWEEN ? AND ? GROUP BY DATE(check_in) ORDER BY d", [Tenant::id(), "$from 00:00:00", "$to 23:59:59"]);
    $out = [];
    foreach ($rows as $r) $out[$r['d']] = ['siswa' => (int)$r['siswa'], 'staf' => (int)$r['staf'], 'tamu' => (int)$r['tamu'], 'total' => (int)$r['total']];
    return $out;
}

/** Visits per hour (07–15 by default). */
function stats_hourly(string $from, string $to, int $h1 = 7, int $h2 = 15): array
{
    $out = array_fill_keys(range($h1, $h2), 0);
    foreach (DB::q('SELECT HOUR(check_in) h, COUNT(*) n FROM visits WHERE tenant_id = ? AND check_in BETWEEN ? AND ? GROUP BY HOUR(check_in)',
        [Tenant::id(), "$from 00:00:00", "$to 23:59:59"]) as $r) {
        $h = (int)$r['h'];
        if ($h < $h1) $h = $h1;
        if ($h > $h2) $h = $h2;
        $out[$h] += (int)$r['n'];
    }
    return $out;
}

function stats_top_classes(string $from, string $to, int $limit = 5): array
{
    return DB::q("SELECT kelas, COUNT(*) n FROM visits WHERE tenant_id = ? AND category = 'siswa' AND kelas IS NOT NULL AND kelas <> ''
        AND check_in BETWEEN ? AND ? GROUP BY kelas ORDER BY n DESC, kelas LIMIT " . (int)$limit, [Tenant::id(), "$from 00:00:00", "$to 23:59:59"]);
}

function stats_by_level(string $from, string $to): array
{
    $out = ['X' => 0, 'XI' => 0, 'XII' => 0];
    foreach (DB::q("SELECT kelas, COUNT(*) n FROM visits WHERE tenant_id = ? AND category = 'siswa' AND check_in BETWEEN ? AND ? GROUP BY kelas",
        [Tenant::id(), "$from 00:00:00", "$to 23:59:59"]) as $r) {
        $lvl = kelas_level($r['kelas']);
        if ($lvl) $out[$lvl] += (int)$r['n'];
    }
    return $out;
}

function stats_recent_visits(int $limit = 6): array
{
    return DB::q('SELECT * FROM visits WHERE tenant_id = ? ORDER BY check_in DESC, id DESC LIMIT ' . (int)$limit, [Tenant::id()]);
}

function stats_new_books(int $limit = 4): array
{
    return DB::q('SELECT id, title, author, shelf, cover, category FROM books WHERE tenant_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . (int)$limit, [Tenant::id()]);
}

function stats_loans(): array
{
    $tid = Tenant::id();
    return [
        'open' => (int)DB::val('SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND returned_at IS NULL', [$tid]),
        'overdue' => (int)DB::val('SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND returned_at IS NULL AND due_date < ?', [$tid, today()]),
        'due_week' => (int)DB::val('SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND returned_at IS NULL AND due_date BETWEEN ? AND ?', [$tid, today(), add_days(today(), 7)]),
    ];
}

/** Cover colors for books without a photo, picked from the title. */
function cover_color(string $title): array
{
    $pal = [['#10284A', '#FFFFFF'], ['#1D4275', '#FFFFFF'], ['#24569A', '#FFFFFF'], ['#8C6516', '#FFFFFF'], ['#475063', '#FFFFFF'], ['#F7EDD4', '#10284A'], ['#2E5287', '#FFFFFF']];
    return $pal[abs(crc32($title)) % count($pal)];
}

/** Last N school days (Mon–Fri, skipping closed days per service hours), oldest first, ending today. */
function recent_school_days(int $n): array
{
    $hours = Tenant::get('hours');
    $days = [];
    $t = strtotime(today());
    for ($i = 0; $i < 30 && count($days) < $n; $i++, $t -= 86400) {
        if (!empty($hours[(int)date('w', $t)]['open'])) $days[] = date('Y-m-d', $t);
    }
    return array_reverse($days);
}
