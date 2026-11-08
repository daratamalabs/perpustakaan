<?php
declare(strict_types=1);

function loan_rules(array $m): array
{
    $l = Tenant::get('loan');
    $isStudent = $m['type'] === 'siswa';
    return [
        'max' => (int)($isStudent ? $l['max_siswa'] : $l['max_guru']),
        'days' => (int)($isStudent ? $l['days_siswa'] : $l['days_guru']),
        'extensions' => (int)$l['extensions'],
        'fine' => (int)$l['fine_per_day'],
    ];
}

/** Late fine for a loan (accrued so far if still open). */
function loan_fine(array $loan): int
{
    $end = $loan['returned_at'] ? substr($loan['returned_at'], 0, 10) : today();
    $late = diff_days($loan['due_date'], $end);
    if ($late <= 0) return 0;
    return $late * (int)Tenant::get('loan')['fine_per_day'];
}

function loan_late_days(array $loan): int
{
    $end = $loan['returned_at'] ? substr($loan['returned_at'], 0, 10) : today();
    return max(0, diff_days($loan['due_date'], $end));
}

/** Count of open, non-textbook loans that count toward the member limit. */
function loan_open_count(int $memberId): int
{
    return (int)DB::val("SELECT COUNT(*) FROM loans l JOIN copies c ON c.id = l.copy_id JOIN books b ON b.id = c.book_id
        WHERE l.member_id = ? AND l.returned_at IS NULL AND b.collection_type <> 'paket'", [$memberId]);
}

function loan_unpaid_fines(int $memberId): int
{
    return (int)DB::val('SELECT COALESCE(SUM(fine_amount),0) FROM loans WHERE member_id = ? AND returned_at IS NOT NULL AND fine_paid = 0 AND fine_amount > 0', [$memberId]);
}

function loan_due_date(array $m, array $book): string
{
    $l = Tenant::get('loan');
    if ($book['collection_type'] === 'paket' && !empty($l['paket_semester']) && !empty($l['semester_end']) && $l['semester_end'] >= today()) {
        return $l['semester_end'];
    }
    return add_days(today(), loan_rules($m)['days']);
}

function copy_by_code(string $code): ?array
{
    return DB::one('SELECT c.*, b.title, b.author, b.collection_type, b.shelf, b.cover, b.id AS book_id
        FROM copies c JOIN books b ON b.id = c.book_id WHERE c.tenant_id = ? AND c.code = ?', [Tenant::id(), strtoupper(trim($code))]);
}

/** Validate that a copy can be lent to a member. Returns an error message or null. */
function loan_check_copy(array $m, ?array $copy): ?string
{
    if (!$copy) return 'Kode eksemplar tidak ditemukan.';
    if ($copy['collection_type'] === 'baca') return 'Buku ini hanya untuk dibaca di tempat.';
    if ($copy['kondisi'] === 'hilang') return 'Eksemplar ini tercatat hilang.';
    if ($copy['status'] === 'dipinjam') return 'Eksemplar ini sedang dipinjam.';
    return null;
}

/**
 * Lend copies to a member. @param string[] $codes
 * @return array{ok:bool,message:string,loans?:array}
 */
function loan_borrow(int $memberId, array $codes, ?int $staffId): array
{
    $tid = Tenant::id();
    $m = DB::one('SELECT * FROM members WHERE id = ? AND tenant_id = ?', [$memberId, $tid]);
    if (!$m) return ['ok' => false, 'message' => 'Member tidak ditemukan.'];
    if ($m['status'] !== 'active') return ['ok' => false, 'message' => 'Status member ' . strtolower(label('member_status', $m['status'])) . ', tidak bisa meminjam.'];
    if ($m['valid_until'] && $m['valid_until'] < today()) return ['ok' => false, 'message' => 'Masa berlaku kartu sudah habis.'];
    $l = Tenant::get('loan');
    if (!empty($l['block_unpaid']) && ($unpaid = loan_unpaid_fines($memberId)) > 0) {
        return ['ok' => false, 'message' => 'Masih ada denda ' . rupiah($unpaid) . ' yang belum dilunasi.'];
    }
    $overdue = (int)DB::val('SELECT COUNT(*) FROM loans WHERE member_id = ? AND returned_at IS NULL AND due_date < ?', [$memberId, today()]);
    if ($overdue > 0) return ['ok' => false, 'message' => "Masih ada $overdue buku yang terlambat dikembalikan."];

    $codes = array_values(array_unique(array_filter(array_map(fn($c) => strtoupper(trim((string)$c)), $codes))));
    if (!$codes) return ['ok' => false, 'message' => 'Belum ada buku yang dipindai.'];
    $rules = loan_rules($m);
    $count = loan_open_count($memberId);
    $copies = [];
    foreach ($codes as $code) {
        $c = copy_by_code($code);
        if ($err = loan_check_copy($m, $c)) return ['ok' => false, 'message' => "$code: $err"];
        if ($c['collection_type'] !== 'paket') $count++;
        $copies[] = $c;
    }
    if ($count > $rules['max']) return ['ok' => false, 'message' => "Melebihi batas pinjam ({$rules['max']} buku)."];

    $made = DB::tx(function () use ($copies, $m, $staffId, $tid) {
        $out = [];
        foreach ($copies as $c) {
            $due = loan_due_date($m, $c);
            $updated = DB::run("UPDATE copies SET status = 'dipinjam' WHERE id = ? AND status = 'tersedia'", [$c['id']]);
            if ($updated !== 1) throw new HttpException($c['code'] . ' baru saja dipinjam orang lain.', 409);
            DB::insert('loans', ['tenant_id' => $tid, 'copy_id' => $c['id'], 'member_id' => $m['id'], 'loaned_at' => now(), 'due_date' => $due, 'staff_id' => $staffId]);
            $out[] = ['title' => $c['title'], 'code' => $c['code'], 'due' => fmt_date($due, 'long')];
        }
        return $out;
    });
    return ['ok' => true, 'message' => count($made) . ' buku berhasil dipinjamkan ke ' . $m['name'] . '.', 'loans' => $made];
}

/** @return array{ok:bool,message:string,fine?:int,late?:int,loan_id?:int} */
function loan_return(string $code, ?int $staffId, bool $markPaid = false): array
{
    $c = copy_by_code($code);
    if (!$c) return ['ok' => false, 'message' => 'Kode eksemplar tidak ditemukan.'];
    $loan = DB::one('SELECT l.*, m.name FROM loans l JOIN members m ON m.id = l.member_id WHERE l.copy_id = ? AND l.returned_at IS NULL ORDER BY l.id DESC LIMIT 1', [$c['id']]);
    if (!$loan) {
        if ($c['status'] === 'dipinjam') DB::run("UPDATE copies SET status = 'tersedia' WHERE id = ?", [$c['id']]);
        return ['ok' => false, 'message' => 'Eksemplar ini tidak sedang dipinjam.'];
    }
    $now = now();
    $loan['returned_at'] = $now;
    $fine = loan_fine($loan);
    $late = loan_late_days($loan);
    DB::tx(function () use ($loan, $c, $fine, $staffId, $now, $markPaid) {
        DB::update('loans', ['returned_at' => $now, 'fine_amount' => $fine, 'fine_paid' => ($fine === 0 || $markPaid) ? 1 : 0, 'return_staff_id' => $staffId], 'id = ?', [$loan['id']]);
        DB::run("UPDATE copies SET status = 'tersedia' WHERE id = ?", [$c['id']]);
    });
    $msg = "{$c['title']} dikembalikan oleh {$loan['name']}.";
    if ($late > 0) $msg .= " Terlambat $late hari, denda " . rupiah($fine) . ($markPaid ? ' (lunas).' : ' (belum dibayar).');
    return ['ok' => true, 'message' => $msg, 'fine' => $fine, 'late' => $late, 'loan_id' => (int)$loan['id'], 'title' => $c['title'], 'member' => $loan['name']];
}

function loan_extend(int $loanId): array
{
    $loan = DB::one('SELECT l.*, m.type, m.kelas FROM loans l JOIN members m ON m.id = l.member_id WHERE l.id = ? AND l.tenant_id = ?', [$loanId, Tenant::id()]);
    if (!$loan || $loan['returned_at']) return ['ok' => false, 'message' => 'Peminjaman tidak ditemukan atau sudah selesai.'];
    $rules = loan_rules($loan);
    if ((int)$loan['extended'] >= $rules['extensions']) return ['ok' => false, 'message' => 'Batas perpanjangan sudah habis.'];
    if ($loan['due_date'] < today()) return ['ok' => false, 'message' => 'Buku sudah terlambat. Kembalikan dulu, lalu pinjam ulang.'];
    $due = add_days(max($loan['due_date'], today()), $rules['days']);
    DB::run('UPDATE loans SET due_date = ?, extended = extended + 1 WHERE id = ?', [$due, $loanId]);
    return ['ok' => true, 'message' => 'Diperpanjang sampai ' . fmt_date($due, 'long') . '.', 'due' => $due];
}

/** Human status for an open loan: [label, tone]. */
function loan_status(array $loan): array
{
    if ($loan['returned_at']) return ['Dikembalikan', 'ok'];
    $d = diff_days(today(), $loan['due_date']);
    if (($loan['collection_type'] ?? '') === 'paket') return [$d < 0 ? 'Paket · terlambat ' . -$d . ' hari' : 'Buku paket semester', $d < 0 ? 'bad' : 'mute'];
    if ($d < 0) return ['Terlambat ' . -$d . ' hari · ' . rupiah(-$d * (int)Tenant::get('loan')['fine_per_day']), 'bad'];
    if ($d === 0) return ['Jatuh tempo hari ini', 'warn'];
    if ($d === 1) return ['Jatuh tempo besok', 'warn'];
    if ($d <= 3) return [$d . ' hari lagi', 'warn'];
    return ['Aktif', 'info'];
}
