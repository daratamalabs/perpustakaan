<?php
declare(strict_types=1);

const MANUAL_REASONS = ['Lupa membawa kartu', 'Kartu hilang atau rusak', 'Pemindai bermasalah', 'Belum punya kartu', 'Lainnya'];

/** Date range from the query string (dari/sampai), defaulting to today. */
function visits_range(): array
{
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)g('dari')) ? g('dari') : today();
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)g('sampai')) ? g('sampai') : $from;
    if ($to < $from) [$from, $to] = [$to, $from];
    return [$from, $to];
}

function visits_where(string $from, string $to): array
{
    $where = ['v.tenant_id = ?', 'v.check_in BETWEEN ? AND ?'];
    $params = [Tenant::id(), "$from 00:00:00", "$to 23:59:59"];
    $cat = (string)g('kategori');
    if ($cat === 'siswa' || $cat === 'tamu') { $where[] = 'v.category = ?'; $params[] = $cat; }
    if ($cat === 'staf') $where[] = "v.category IN ('guru','tendik')";
    if (in_array(g('metode'), ['qr', 'manual', 'tamu', 'cari'], true)) { $where[] = 'v.method = ?'; $params[] = g('metode'); }
    if (g('q') !== '') { $where[] = '(v.name LIKE ? OR v.kelas = ? OR v.origin LIKE ?)'; array_push($params, '%' . g('q') . '%', strtoupper((string)g('q')), '%' . g('q') . '%'); }
    if (g('di_dalam')) $where[] = 'v.check_out IS NULL';
    return [implode(' AND ', $where), $params];
}

function visits_index(): string
{
    Auth::requireStaff('kunjungan');
    [$from, $to] = visits_range();
    [$w, $params] = visits_where($from, $to);
    $total = (int)DB::val("SELECT COUNT(*) FROM visits v WHERE $w", $params);
    $pg = paginate($total, g('hal', 1), 30);
    $rows = DB::q("SELECT v.*, u.name staff_name FROM visits v LEFT JOIN users u ON u.id = v.staff_id WHERE $w ORDER BY v.check_in DESC, v.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
    $counts = stats_visit_counts($from, $to);
    return view('admin/visits', ['rows' => $rows, 'pg' => $pg, 'counts' => $counts, 'from' => $from, 'to' => $to, 'inside' => stats_inside_now(),
        'title' => 'Kunjungan', 'active' => 'kunjungan'], 'admin');
}

function visits_member_search(): never
{
    Auth::requireStaff('kunjungan');
    $q = trim((string)g('q'));
    if (mb_strlen($q) < 2) json_out(['ok' => true, 'items' => []]);
    $rows = DB::q("SELECT * FROM members WHERE tenant_id = ? AND status = 'active' AND (name LIKE ? OR nisn = ? OR nip = ? OR member_no = ?) ORDER BY name LIMIT 8",
        [Tenant::id(), "%$q%", $q, $q, strtoupper($q)]);
    $inside = [];
    if ($rows) {
        $ids = array_column($rows, 'id');
        foreach (DB::q('SELECT member_id, check_in FROM visits WHERE tenant_id = ? AND check_out IS NULL AND check_in >= ? AND member_id IN ' . DB::in($ids),
            array_merge([Tenant::id(), today() . ' 00:00:00'], $ids)) as $r) $inside[(int)$r['member_id']] = fmt_time($r['check_in']);
    }
    json_out(['ok' => true, 'items' => array_map(fn($m) => [
        'id' => (int)$m['id'], 'name' => $m['name'], 'initials' => initials($m['name']),
        'meta' => member_meta($m) . (member_id_label($m) ? ' · ' . member_id_label($m) : ''),
        'inside' => $inside[(int)$m['id']] ?? null,
    ], $rows)]);
}

function visits_manual(): never
{
    $u = Auth::requireStaff('kunjungan');
    $back = turl('/admin/kunjungan') . '#manual';
    $time = preg_match('/^\d{2}:\d{2}$/', (string)p('time')) ? p('time') : date('H:i');
    $at = today() . ' ' . $time . ':00';
    if ($at > now()) $at = now();
    $purpose = in_array(p('purpose'), VISIT_PURPOSES, true) || in_array(p('purpose'), GUEST_PURPOSES, true) ? p('purpose') : null;

    if (p('mode') === 'tamu') {
        $name = trim((string)p('guest_name'));
        if (mb_strlen($name) < 2) { flash('error', 'Isi nama tamu.'); keep_old($_POST); redirect($back); }
        visit_guest(['name' => $name, 'origin' => (string)p('origin'), 'phone' => (string)p('phone'), 'purpose' => $purpose, 'at' => $at, 'staff_id' => (int)$u['id']]);
        flash('ok', 'Kunjungan tamu ' . $name . ' dicatat pukul ' . fmt_time($at) . '.');
        redirect($back);
    }

    $m = DB::one('SELECT * FROM members WHERE id = ? AND tenant_id = ?', [(int)p('member_id'), Tenant::id()]);
    if (!$m) { flash('error', 'Pilih member dari hasil pencarian dulu.'); keep_old($_POST); redirect($back); }
    if ($m['status'] !== 'active') { flash('error', $m['name'] . ' belum berstatus member aktif.'); redirect($back); }
    $reason = in_array(p('reason'), MANUAL_REASONS, true) ? p('reason') : 'Lainnya';
    $r = visit_checkin_member($m, ['method' => 'manual', 'reason' => $reason, 'purpose' => $purpose, 'at' => $at, 'staff_id' => (int)$u['id'], 'allow_out' => false]);
    if ($r['status'] === 'duplicate') flash('warn', $m['name'] . ' sudah tercatat masuk pukul ' . fmt_time($r['visit']['check_in']) . ' dan belum keluar.');
    else flash('ok', 'Kunjungan ' . $m['name'] . ' dicatat pukul ' . fmt_time($r['visit']['check_in']) . '.');
    redirect($back);
}

function visits_checkout(string $id): never
{
    Auth::requireStaff('kunjungan');
    $v = visit_checkout((int)$id) ?? abort(404);
    if (wants_json()) json_out(['ok' => true, 'message' => $v['name'] . ' keluar pukul ' . fmt_time($v['check_out']) . '.']);
    flash('ok', $v['name'] . ' dicatat keluar pukul ' . fmt_time($v['check_out']) . '.');
    redirect(turl('/admin/kunjungan') . qs([], ['dari' => p('dari'), 'sampai' => p('sampai')]));
}

function visits_delete(string $id): never
{
    Auth::requireStaff('dashboard');
    $v = DB::one('SELECT * FROM visits WHERE id = ? AND tenant_id = ?', [(int)$id, Tenant::id()]) ?? abort(404);
    DB::run('DELETE FROM visits WHERE id = ?', [$v['id']]);
    flash('ok', 'Kunjungan ' . $v['name'] . ' (' . fmt_dt($v['check_in']) . ') dihapus.');
    redirect(turl('/admin/kunjungan') . qs([], ['dari' => p('dari'), 'sampai' => p('sampai')]));
}

function visits_export(): never
{
    Auth::requireStaff('kunjungan');
    [$from, $to] = visits_range();
    [$w, $params] = visits_where($from, $to);
    $rows = DB::q("SELECT v.*, u.name staff_name FROM visits v LEFT JOIN users u ON u.id = v.staff_id WHERE $w ORDER BY v.check_in", $params);
    $sheet = [['Tanggal', 'Masuk', 'Keluar', 'Lama (menit)', 'Nama', 'Kategori', 'Kelas / bagian', 'Asal (tamu)', 'Keperluan', 'Metode', 'Alasan manual', 'Petugas']];
    foreach ($rows as $v) {
        $sheet[] = [substr($v['check_in'], 0, 10), fmt_time($v['check_in']), $v['check_out'] ? fmt_time($v['check_out']) : '',
            $v['check_out'] ? minutes_between($v['check_in'], $v['check_out']) : '', $v['name'], label('category', $v['category']), $v['kelas'],
            $v['origin'], $v['purpose'], label('method', $v['method']), $v['manual_reason'], $v['staff_name']];
    }
    $name = 'kunjungan-' . App::$tenant['slug'] . '-' . $from . ($to !== $from ? '-sd-' . $to : '') . '.xlsx';
    xlsx_download($name, ['Kunjungan' => $sheet]);
}
