<?php
declare(strict_types=1);

function circ_index(): string
{
    Auth::requireStaff('sirkulasi');
    $tid = Tenant::id();
    $filter = (string)g('filter');
    $q = (string)g('q');
    $today = today();
    if ($filter === 'denda') {
        $where = 'l.tenant_id = ? AND l.returned_at IS NOT NULL AND l.fine_amount > 0 AND l.fine_paid = 0';
        $params = [$tid];
    } else {
        $where = 'l.tenant_id = ? AND l.returned_at IS NULL';
        $params = [$tid];
        if ($filter === 'minggu') { $where .= ' AND l.due_date BETWEEN ? AND ?'; array_push($params, $today, add_days($today, 7)); }
        if ($filter === 'terlambat') { $where .= ' AND l.due_date < ?'; $params[] = $today; }
    }
    if ($q !== '') {
        $where .= ' AND (m.name LIKE ? OR b.title LIKE ? OR c.code = ? OR m.nisn = ? OR m.kelas = ?)';
        array_push($params, "%$q%", "%$q%", strtoupper($q), $q, strtoupper($q));
    }
    $from = 'FROM loans l JOIN members m ON m.id = l.member_id JOIN copies c ON c.id = l.copy_id JOIN books b ON b.id = c.book_id WHERE ' . $where;
    $total = (int)DB::val("SELECT COUNT(*) $from", $params);
    $pg = paginate($total, g('hal', 1), 25);
    $order = $filter === 'denda' ? 'l.returned_at DESC' : 'l.due_date ASC, l.id';
    $rows = DB::q("SELECT l.*, m.name, m.kelas, m.type, m.subject, m.phone, b.title, b.collection_type, c.code $from ORDER BY $order LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
    $counts = [
        'semua' => (int)DB::val('SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND returned_at IS NULL', [$tid]),
        'minggu' => (int)DB::val('SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND returned_at IS NULL AND due_date BETWEEN ? AND ?', [$tid, $today, add_days($today, 7)]),
        'terlambat' => (int)DB::val('SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND returned_at IS NULL AND due_date < ?', [$tid, $today]),
        'denda' => (int)DB::val('SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND returned_at IS NOT NULL AND fine_amount > 0 AND fine_paid = 0', [$tid]),
    ];
    return view('admin/circulation', ['rows' => $rows, 'pg' => $pg, 'counts' => $counts, 'filter' => $filter, 'loan' => Tenant::get('loan'),
        'title' => 'Sirkulasi', 'active' => 'sirkulasi', 'scripts' => ['vendor/jsQR.js', 'js/scanner.js', 'js/circulation.js']], 'admin');
}

function circ_member_json(array $m): array
{
    $rules = loan_rules($m);
    $open = DB::q('SELECT l.id, l.due_date, l.extended, b.title, b.collection_type, c.code FROM loans l JOIN copies c ON c.id = l.copy_id JOIN books b ON b.id = c.book_id
        WHERE l.member_id = ? AND l.returned_at IS NULL ORDER BY l.due_date', [$m['id']]);
    $count = loan_open_count((int)$m['id']);
    $unpaid = loan_unpaid_fines((int)$m['id']);
    $overdue = count(array_filter($open, fn($l) => $l['due_date'] < today()));
    $reason = null;
    if ($m['status'] !== 'active') $reason = 'Status member: ' . strtolower(label('member_status', $m['status'])) . '.';
    elseif ($m['valid_until'] && $m['valid_until'] < today()) $reason = 'Masa berlaku kartu sudah habis.';
    elseif ($overdue) $reason = "Masih ada $overdue buku terlambat.";
    elseif ($unpaid && !empty(Tenant::get('loan')['block_unpaid'])) $reason = 'Ada denda ' . rupiah($unpaid) . ' belum dibayar.';
    elseif ($count >= $rules['max']) $reason = "Sudah meminjam {$rules['max']} buku (batas).";
    return [
        'id' => (int)$m['id'], 'name' => $m['name'], 'initials' => initials($m['name']), 'meta' => member_meta($m), 'no' => $m['member_no'],
        'status' => label('member_status', $m['status']), 'tone' => LABELS['member_status_tone'][$m['status']] ?? 'mute',
        'count' => $count, 'max' => $rules['max'], 'days' => $rules['days'], 'unpaid' => $unpaid, 'reason' => $reason,
        'due_default' => fmt_date(add_days(today(), $rules['days']), 'long'),
        'loans' => array_map(fn($l) => ['id' => (int)$l['id'], 'title' => $l['title'], 'code' => $l['code'], 'due' => fmt_date($l['due_date']), 'late' => $l['due_date'] < today()], $open),
    ];
}

function circ_member_lookup(): never
{
    Auth::requireStaff('sirkulasi');
    $q = trim((string)g('q'));
    if ($q === '') json_out(['ok' => false, 'message' => 'Pindai QR member atau ketik NISN/nama.']);
    $m = member_by_scan($q);
    if (!$m && mb_strlen($q) >= 3) {
        $list = DB::q("SELECT * FROM members WHERE tenant_id = ? AND status IN ('active','inactive') AND (name LIKE ? OR nip = ?) ORDER BY status = 'active' DESC, name LIMIT 8", [Tenant::id(), "%$q%", $q]);
        if (count($list) === 1) $m = $list[0];
        elseif ($list) json_out(['ok' => true, 'choices' => array_map(fn($x) => ['q' => $x['member_no'] ?: $x['nisn'], 'name' => $x['name'], 'meta' => member_meta($x)], $list)]);
    }
    if (!$m) json_out(['ok' => false, 'message' => 'Member tidak ditemukan.']);
    json_out(['ok' => true, 'member' => circ_member_json($m)]);
}

function circ_copy_lookup(): never
{
    Auth::requireStaff('sirkulasi');
    $code = (string)g('code');
    $c = copy_by_code($code);
    if (!$c) json_out(['ok' => false, 'message' => 'Kode eksemplar ' . strtoupper($code) . ' tidak ditemukan.']);
    $loan = $c['status'] === 'dipinjam'
        ? DB::one('SELECT l.*, m.name, m.type, m.kelas FROM loans l JOIN members m ON m.id = l.member_id WHERE l.copy_id = ? AND l.returned_at IS NULL ORDER BY l.id DESC LIMIT 1', [$c['id']])
        : null;
    $err = null;
    if ($c['collection_type'] === 'baca') $err = 'Buku ini hanya untuk dibaca di tempat.';
    elseif ($c['kondisi'] === 'hilang') $err = 'Eksemplar ini tercatat hilang.';
    elseif ($c['status'] === 'dipinjam') $err = 'Sedang dipinjam' . ($loan ? ' oleh ' . $loan['name'] : '') . '.';
    $memberId = (int)g('member');
    $due = null;
    if ($memberId && ($m = DB::one('SELECT * FROM members WHERE id = ? AND tenant_id = ?', [$memberId, Tenant::id()]))) $due = fmt_date(loan_due_date($m, $c), 'long');
    json_out(['ok' => true, 'copy' => [
        'code' => $c['code'], 'title' => $c['title'], 'author' => $c['author'], 'shelf' => $c['shelf'], 'type' => $c['collection_type'],
        'available' => $err === null, 'error' => $err, 'due' => $due,
        'loan' => $loan ? ['id' => (int)$loan['id'], 'name' => $loan['name'], 'due' => fmt_date($loan['due_date']), 'late' => loan_late_days($loan), 'fine' => loan_fine($loan),
            'extended' => (int)$loan['extended']] : null,
    ]]);
}

function circ_borrow(): never
{
    $u = Auth::requireStaff('sirkulasi');
    $codes = $_POST['codes'] ?? [];
    if (is_string($codes)) $codes = explode(',', $codes);
    $r = loan_borrow((int)p('member_id'), (array)$codes, (int)$u['id']);
    json_out($r);
}

function circ_return(): never
{
    $u = Auth::requireStaff('sirkulasi');
    json_out(loan_return((string)p('code'), (int)$u['id'], (bool)p('paid')));
}

function circ_back(string $msgType, string $msg): never
{
    if (wants_json()) json_out(['ok' => $msgType === 'ok', 'message' => $msg]);
    flash($msgType, $msg);
    $back = (string)p('back');
    redirect($back === 'dashboard' ? turl('/admin') : turl('/admin/sirkulasi') . (p('filter') ? '?filter=' . rawurlencode((string)p('filter')) : ''));
}

function circ_extend(string $id): never
{
    Auth::requireStaff('sirkulasi');
    $r = loan_extend((int)$id);
    circ_back($r['ok'] ? 'ok' : 'error', $r['message']);
}

function circ_paid(string $id): never
{
    Auth::requireStaff('sirkulasi');
    $n = DB::run('UPDATE loans SET fine_paid = 1 WHERE id = ? AND tenant_id = ? AND returned_at IS NOT NULL', [(int)$id, Tenant::id()]);
    circ_back($n ? 'ok' : 'error', $n ? 'Denda ditandai lunas.' : 'Data denda tidak ditemukan.');
}

function circ_reminder_text(array $l): string
{
    $lib = Tenant::get('library_name');
    $d = diff_days(today(), $l['due_date']);
    if ($d < 0) {
        $fine = (int)Tenant::get('loan')['fine_per_day'] * -$d;
        return "Halo {$l['name']}, buku *{$l['title']}* yang kamu pinjam dari $lib sudah terlambat " . (-$d) . " hari (jatuh tempo " . fmt_date($l['due_date'], 'long') . ")."
            . ($fine > 0 ? " Denda sementara " . rupiah($fine) . "." : '') . "\n\nSegera kembalikan ke meja sirkulasi ya. Terima kasih.";
    }
    $when = $d === 0 ? 'hari ini' : ($d === 1 ? 'besok' : fmt_date($l['due_date'], 'long'));
    return "Halo {$l['name']}, pengingat dari $lib: buku *{$l['title']}* jatuh tempo $when. Kembalikan atau perpanjang sebelum jatuh tempo ya. Terima kasih.";
}

function circ_remind(string $id): never
{
    Auth::requireStaff('sirkulasi');
    $l = DB::one('SELECT l.*, m.name, m.phone, b.title FROM loans l JOIN members m ON m.id = l.member_id JOIN copies c ON c.id = l.copy_id JOIN books b ON b.id = c.book_id
        WHERE l.id = ? AND l.tenant_id = ? AND l.returned_at IS NULL', [(int)$id, Tenant::id()]) ?? abort(404);
    if (!$l['phone']) circ_back('error', 'Nomor WhatsApp ' . $l['name'] . ' belum diisi.');
    $r = wa_send($l['phone'], circ_reminder_text($l));
    if ($r['ok']) DB::run('UPDATE loans SET reminded_at = ? WHERE id = ?', [now(), $l['id']]);
    circ_back($r['ok'] ? 'ok' : 'error', $r['ok'] ? 'Pengingat terkirim ke ' . $l['name'] . '.' : 'Pengingat gagal dikirim: ' . ($r['reason'] ?? ''));
}

function circ_remind_all(): never
{
    Auth::requireStaff('sirkulasi');
    if (!wa_enabled()) circ_back('error', 'Gateway WhatsApp belum diatur di Pengaturan.');
    set_time_limit(300);
    $rows = DB::q("SELECT l.*, m.name, m.phone, b.title FROM loans l JOIN members m ON m.id = l.member_id JOIN copies c ON c.id = l.copy_id JOIN books b ON b.id = c.book_id
        WHERE l.tenant_id = ? AND l.returned_at IS NULL AND l.due_date < ? AND m.phone IS NOT NULL AND m.phone <> ''
        AND (l.reminded_at IS NULL OR l.reminded_at < ?) ORDER BY l.due_date LIMIT 40", [Tenant::id(), today(), today() . ' 00:00:00']);
    $sent = 0; $fail = 0;
    foreach ($rows as $l) {
        $r = wa_send($l['phone'], circ_reminder_text($l));
        if ($r['ok']) { $sent++; DB::run('UPDATE loans SET reminded_at = ? WHERE id = ?', [now(), $l['id']]); } else $fail++;
    }
    circ_back($sent ? 'ok' : 'error', $rows ? "$sent pengingat terkirim" . ($fail ? ", $fail gagal" : '') . '.' : 'Semua peminjam terlambat sudah diingatkan hari ini.');
}
