<?php
declare(strict_types=1);

function member_qr_text(array $m): string { return QR_MEMBER_PREFIX . $m['qr_token']; }

function member_meta(array $m): string
{
    return match ($m['type']) {
        'siswa' => 'Siswa' . ($m['kelas'] ? ' · ' . $m['kelas'] : ''),
        'guru' => 'Guru' . ($m['subject'] ? ' · ' . $m['subject'] : ''),
        default => 'Tendik' . ($m['subject'] ? ' · ' . $m['subject'] : ''),
    };
}

function member_id_label(array $m): string
{
    if ($m['type'] === 'siswa') return $m['nisn'] ? 'NISN ' . $m['nisn'] : '';
    return $m['nip'] ? 'NIP ' . $m['nip'] : '';
}

/** Valid-until date when a member is activated. */
function member_valid_until(array $m): ?string
{
    $mode = Tenant::get('reg')['validity'] ?? 'lulus';
    if ($mode === 'none') return null;
    if ($mode === '1y') return date('Y-m-d', strtotime('+1 year'));
    if ($m['type'] !== 'siswa') return null;
    $startYear = (int)date('n') >= 7 ? (int)date('Y') : (int)date('Y') - 1;
    $left = match (kelas_level($m['kelas'])) { 'X' => 3, 'XI' => 2, 'XII' => 1, default => 3 };
    return ($startYear + $left) . '-06-30';
}

/** Give the member a number like P3-26-00184 (once). */
function member_assign_no(array $m): string
{
    if (!empty($m['member_no'])) return $m['member_no'];
    $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)Tenant::get('member_prefix'))) ?: 'PS';
    $yy = date('y');
    $like = "$prefix-$yy-%";
    $last = DB::val('SELECT member_no FROM members WHERE tenant_id = ? AND member_no LIKE ? ORDER BY member_no DESC LIMIT 1', [Tenant::id(), $like]);
    $n = $last ? (int)substr((string)$last, -5) + 1 : 1;
    $no = sprintf('%s-%s-%05d', $prefix, $yy, $n);
    DB::run('UPDATE members SET member_no = ? WHERE id = ?', [$no, $m['id']]);
    return $no;
}

function member_activate(int $id, ?int $staffId): array
{
    $m = DB::one('SELECT * FROM members WHERE id = ? AND tenant_id = ?', [$id, Tenant::id()]);
    if (!$m) abort(404, 'Member tidak ditemukan.');
    member_assign_no($m);
    DB::update('members', [
        'status' => 'active',
        'approved_at' => now(),
        'approved_by' => $staffId,
        'valid_until' => $m['valid_until'] ?: member_valid_until($m),
        'reject_reason' => null,
        'updated_at' => now(),
    ], 'id = ?', [$id]);
    return DB::one('SELECT * FROM members WHERE id = ?', [$id]);
}

/** WhatsApp text sent after approval. */
function member_card_message(array $m): string
{
    $s = Tenant::settings();
    return "Halo {$m['name']}, pendaftaran member {$s['library_name']} sudah disetujui.\n\n"
        . "No. anggota: {$m['member_no']}\n"
        . "Buka kartu QR kamu di: " . abs_url(turl('/kartu')) . "\n\n"
        . "Tunjukkan QR ke kamera di meja depan setiap datang ke perpustakaan.";
}

function student_ref_match(?string $nisn): ?array
{
    if (!$nisn) return null;
    return DB::one('SELECT * FROM students_ref WHERE tenant_id = ? AND nisn = ?', [Tenant::id(), $nisn]);
}

function validate_pin(string $pin): ?string
{
    if (!preg_match('/^\d{6}$/', $pin)) return 'PIN harus 6 angka.';
    if (preg_match('/^(\d)\1{5}$/', $pin) || in_array($pin, ['123456', '654321', '012345'], true)) return 'PIN terlalu mudah ditebak.';
    return null;
}
