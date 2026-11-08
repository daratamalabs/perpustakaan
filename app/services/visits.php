<?php
declare(strict_types=1);

const QR_MEMBER_PREFIX = 'PERPUS:';
const QR_ASSET_PREFIX = 'ASET:';

function semester_start(): string
{
    return (int)date('n') >= 7 ? date('Y') . '-07-01' : date('Y') . '-01-01';
}

function semester_count(int $memberId): int
{
    return (int)DB::val('SELECT COUNT(*) FROM visits WHERE member_id = ? AND check_in >= ?', [$memberId, semester_start() . ' 00:00:00']);
}

/** @return array{open:bool,hours:array} */
function library_open_now(): array
{
    $hours = Tenant::get('hours')[(int)date('w')] ?? ['open' => false];
    if (empty($hours['open'])) return ['open' => false, 'hours' => $hours];
    $t = date('H:i');
    return ['open' => $t >= $hours['from'] && $t <= $hours['to'], 'hours' => $hours];
}

function visit_meta(array $v): string
{
    return match ($v['category']) {
        'siswa' => $v['kelas'] ? 'Kelas ' . $v['kelas'] : 'Siswa',
        'tamu' => $v['origin'] ? 'Tamu · ' . $v['origin'] : 'Tamu',
        default => label('category', $v['category']) . ($v['kelas'] ? ' · ' . $v['kelas'] : ''),
    };
}

function visit_payload(array $v, array $extra = []): array
{
    $fmt = Tenant::get('display')['name_format'] ?? 'first_initial';
    return [
        'id' => (int)$v['id'],
        'name' => display_name($v['name'], $fmt),
        'initials' => initials($v['name']),
        'category' => $v['category'],
        'meta' => visit_meta($v),
        'time' => fmt_time($v['check_in']),
    ] + $extra;
}

/** Find a member of the current school from a scanned or typed code. */
function member_by_scan(string $raw): ?array
{
    $s = trim($raw);
    $tid = Tenant::id();
    if ($s === '') return null;
    if (stripos($s, QR_MEMBER_PREFIX) === 0) {
        return DB::one('SELECT * FROM members WHERE tenant_id = ? AND qr_token = ?', [$tid, substr($s, strlen(QR_MEMBER_PREFIX))]);
    }
    if (preg_match('/^\d{10}$/', $s) && (Tenant::get('kiosk')['allow_nisn'] ?? true)) {
        return DB::one('SELECT * FROM members WHERE tenant_id = ? AND nisn = ?', [$tid, $s]);
    }
    return DB::one('SELECT * FROM members WHERE tenant_id = ? AND (member_no = ? OR (nip <> \'\' AND nip = ?))', [$tid, strtoupper($s), $s]);
}

/**
 * Record a member visit.
 * @return array{status:string,visit:array,count:int} status: in | out | duplicate
 */
function visit_checkin_member(array $m, array $o = []): array
{
    $tid = Tenant::id();
    $kiosk = Tenant::get('kiosk');
    $open = DB::one('SELECT * FROM visits WHERE tenant_id = ? AND member_id = ? AND check_out IS NULL AND check_in >= ? ORDER BY id DESC LIMIT 1',
        [$tid, $m['id'], today() . ' 00:00:00']);
    $now = now();
    if ($open) {
        $mins = minutes_between($open['check_in'], $now);
        $allowOut = ($o['allow_out'] ?? true) && !empty($kiosk['scan_out']);
        if ($mins < (int)$kiosk['cooldown_minutes'] || !$allowOut) {
            return ['status' => 'duplicate', 'visit' => $open, 'count' => semester_count((int)$m['id'])];
        }
        DB::run('UPDATE visits SET check_out = ? WHERE id = ?', [$now, $open['id']]);
        $open['check_out'] = $now;
        return ['status' => 'out', 'visit' => $open, 'count' => semester_count((int)$m['id'])];
    }
    $id = DB::insert('visits', [
        'tenant_id' => $tid,
        'member_id' => $m['id'],
        'name' => $m['name'],
        'category' => $m['type'],
        'kelas' => $m['type'] === 'siswa' ? $m['kelas'] : ($m['subject'] ?: null),
        'purpose' => $o['purpose'] ?? null,
        'method' => $o['method'] ?? 'qr',
        'manual_reason' => $o['reason'] ?? null,
        'check_in' => $o['at'] ?? $now,
        'staff_id' => $o['staff_id'] ?? null,
    ]);
    $v = DB::one('SELECT * FROM visits WHERE id = ?', [$id]);
    return ['status' => 'in', 'visit' => $v, 'count' => semester_count((int)$m['id'])];
}

function visit_guest(array $d): array
{
    $id = DB::insert('visits', [
        'tenant_id' => Tenant::id(),
        'name' => $d['name'],
        'category' => 'tamu',
        'origin' => $d['origin'] ?: null,
        'phone' => $d['phone'] ? norm_phone($d['phone']) : null,
        'purpose' => $d['purpose'] ?: null,
        'method' => 'tamu',
        'check_in' => $d['at'] ?? now(),
        'staff_id' => $d['staff_id'] ?? null,
    ]);
    return DB::one('SELECT * FROM visits WHERE id = ?', [$id]);
}

function visit_checkout(int $id): ?array
{
    $v = DB::one('SELECT * FROM visits WHERE id = ? AND tenant_id = ?', [$id, Tenant::id()]);
    if (!$v || $v['check_out']) return $v;
    DB::run('UPDATE visits SET check_out = ? WHERE id = ?', [now(), $id]);
    return DB::one('SELECT * FROM visits WHERE id = ?', [$id]);
}

const VISIT_PURPOSES = ['Membaca', 'Meminjam buku', 'Mengembalikan buku', 'Mengerjakan tugas', 'Mencari referensi', 'Kunjungan dinas', 'Lainnya'];
const GUEST_PURPOSES = ['Membaca', 'Mencari referensi', 'Kunjungan dinas', 'Menjemput / menemui siswa', 'Lainnya'];
