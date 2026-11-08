<?php
declare(strict_types=1);

const MEMBER_TABS = [
    'menunggu' => ['Menunggu persetujuan', "status = 'pending'"],
    'aktif' => ['Aktif', "status = 'active'"],
    'nonaktif' => ['Nonaktif', "status = 'inactive'"],
    'lulus' => ['Lulus', "status = 'graduated'"],
    'ditolak' => ['Ditolak', "status = 'rejected'"],
    'otp' => ['Belum verifikasi WA', "status = 'otp'"],
];

const MEMBER_IMPORT_ALIASES = [
    'nisn' => ['nisn'],
    'name' => ['nama', 'nama lengkap', 'nama siswa', 'name'],
    'kelas' => ['kelas', 'rombel', 'rombongan belajar'],
    'gender' => ['jk', 'jenis kelamin', 'l/p', 'gender'],
    'phone' => ['no wa', 'no. wa', 'no whatsapp', 'no. whatsapp', 'whatsapp', 'no hp', 'no. hp', 'hp', 'telepon'],
];

function member_find(int $id): array
{
    return DB::one('SELECT * FROM members WHERE id = ? AND tenant_id = ?', [$id, Tenant::id()]) ?? abort(404, 'Member tidak ditemukan.');
}

function members_index(): string
{
    Auth::requireStaff('member');
    $tid = Tenant::id();
    $counts = [];
    foreach (DB::q('SELECT status, COUNT(*) n FROM members WHERE tenant_id = ? GROUP BY status', [$tid]) as $r) $counts[$r['status']] = (int)$r['n'];
    $tab = array_key_exists((string)g('tab'), MEMBER_TABS) ? g('tab') : (($counts['pending'] ?? 0) > 0 ? 'menunggu' : 'aktif');

    $where = ['tenant_id = ?', MEMBER_TABS[$tab][1]];
    $params = [$tid];
    if (($q = (string)g('q')) !== '') {
        $where[] = '(name LIKE ? OR nisn = ? OR nip = ? OR member_no = ? OR phone = ?)';
        array_push($params, "%$q%", $q, $q, strtoupper($q), norm_phone($q) ?: $q);
    }
    if (g('kelas') !== '') { $where[] = 'kelas = ?'; $params[] = g('kelas'); }
    if (in_array(g('jenis'), ['siswa', 'guru', 'tendik'], true)) { $where[] = 'type = ?'; $params[] = g('jenis'); }
    $w = implode(' AND ', $where);
    $total = (int)DB::val("SELECT COUNT(*) FROM members WHERE $w", $params);
    $pg = paginate($total, g('hal', 1), 25);
    $order = in_array($tab, ['menunggu', 'otp'], true) ? 'created_at ASC' : 'name ASC';
    $rows = DB::q("SELECT * FROM members WHERE $w ORDER BY $order LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);

    $sel = null;
    if ($tab === 'menunggu') {
        $sel = g('id') !== '' ? DB::one('SELECT * FROM members WHERE id = ? AND tenant_id = ?', [(int)g('id'), $tid]) : ($rows[0] ?? null);
    }
    $refs = [];
    $nisns = array_values(array_filter(array_column($rows, 'nisn')));
    if ($nisns) foreach (DB::q('SELECT * FROM students_ref WHERE tenant_id = ? AND nisn IN ' . DB::in($nisns), array_merge([$tid], $nisns)) as $r) $refs[$r['nisn']] = $r;
    $hasRef = (bool)DB::val('SELECT 1 FROM students_ref WHERE tenant_id = ? LIMIT 1', [$tid]);
    return view('admin/members', ['rows' => $rows, 'pg' => $pg, 'tab' => $tab, 'counts' => $counts, 'sel' => $sel, 'refs' => $refs, 'hasRef' => $hasRef,
        's' => Tenant::settings(), 'title' => 'Member', 'active' => 'member', 'scripts' => ['vendor/qrcode.js']], 'admin');
}

function members_form(string $id = ''): string
{
    Auth::requireStaff('member');
    $m = $id !== '' ? member_find((int)$id) : null;
    $data = ['m' => $m, 's' => Tenant::settings(), 'title' => $m ? $m['name'] : 'Tambah member', 'active' => 'member', 'scripts' => ['vendor/qrcode.js']];
    if ($m) {
        $data['loans'] = DB::q('SELECT l.*, b.title, b.collection_type, c.code FROM loans l JOIN copies c ON c.id = l.copy_id JOIN books b ON b.id = c.book_id
            WHERE l.member_id = ? ORDER BY l.returned_at IS NULL DESC, l.loaned_at DESC LIMIT 12', [$m['id']]);
        $data['visits'] = DB::q('SELECT * FROM visits WHERE member_id = ? ORDER BY check_in DESC LIMIT 8', [$m['id']]);
        $data['visitTotal'] = (int)DB::val('SELECT COUNT(*) FROM visits WHERE member_id = ?', [$m['id']]);
        $data['semester'] = semester_count((int)$m['id']);
        $data['unpaid'] = loan_unpaid_fines((int)$m['id']);
        $data['ref'] = student_ref_match($m['nisn']);
        $data['approver'] = $m['approved_by'] ? DB::val('SELECT name FROM users WHERE id = ?', [$m['approved_by']]) : null;
    }
    return view('admin/member_form', $data, 'admin');
}

function members_save(string $id = ''): never
{
    $u = Auth::requireStaff('member');
    $tid = Tenant::id();
    $s = Tenant::settings();
    $m = $id !== '' ? member_find((int)$id) : null;
    $back = $m ? turl('/admin/member/' . $m['id']) : turl('/admin/member/baru');
    $type = in_array(p('type'), ['siswa', 'guru', 'tendik'], true) ? p('type') : 'siswa';
    $d = [
        'type' => $type,
        'name' => preg_replace('/\s+/', ' ', (string)p('name')),
        'nisn' => $type === 'siswa' ? (preg_replace('/\D/', '', (string)p('nisn')) ?: null) : null,
        'nip' => $type !== 'siswa' ? (preg_replace('/\s+/', '', (string)p('nip')) ?: null) : null,
        'kelas' => $type === 'siswa' ? ((string)p('kelas') ?: null) : null,
        'subject' => $type !== 'siswa' ? ((string)p('subject') ?: null) : null,
        'gender' => in_array(p('gender'), ['L', 'P'], true) ? p('gender') : null,
        'phone' => norm_phone((string)p('phone')) ?: null,
        'email' => strtolower((string)p('email')) ?: null,
        'valid_until' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)p('valid_until')) ? p('valid_until') : null,
        'updated_at' => now(),
    ];
    $err = null;
    if (mb_strlen($d['name']) < 3) $err = 'Isi nama lengkap.';
    elseif ($type === 'siswa' && !preg_match('/^\d{10}$/', (string)$d['nisn'])) $err = 'NISN harus 10 digit angka.';
    elseif ($type === 'siswa' && !$d['kelas']) $err = 'Pilih kelas.';
    elseif ($d['phone'] && !preg_match('/^08\d{8,12}$/', $d['phone'])) $err = 'Nomor WhatsApp tidak valid.';
    elseif ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err = 'Format email tidak valid.';
    elseif ($d['nisn'] && DB::val('SELECT id FROM members WHERE tenant_id = ? AND nisn = ? AND id <> ?', [$tid, $d['nisn'], $m['id'] ?? 0])) $err = 'NISN ' . $d['nisn'] . ' sudah terdaftar sebagai member lain.';
    $pin = (string)p('pin');
    if (!$err && $pin !== '' && ($pe = validate_pin($pin))) $err = $pe;
    if ($err) { flash('error', $err); keep_old($_POST); redirect($back); }

    $photo = save_upload('photo', 'members', 4_000_000);
    if ($photo) { if ($m) delete_upload($m['photo']); $d['photo'] = $photo; }
    elseif ($m && p('remove_photo')) { delete_upload($m['photo']); $d['photo'] = null; }

    if ($m) {
        if ($pin !== '') $d['pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
        if ($d['phone'] !== $m['phone']) $d['phone_verified'] = 0;
        DB::update('members', $d, 'id = ?', [$m['id']]);
        flash('ok', 'Data member disimpan.' . ($pin !== '' ? ' PIN baru sudah berlaku.' : ''));
        redirect(turl('/admin/member/' . $m['id']));
    }
    $plain = $pin !== '' ? $pin : random_digits(6);
    $newId = DB::insert('members', $d + [
        'tenant_id' => $tid, 'qr_token' => random_token(18), 'status' => 'pending', 'pin_hash' => password_hash($plain, PASSWORD_DEFAULT),
        'source' => 'petugas', 'created_at' => now(),
    ]);
    $new = member_activate($newId, (int)$u['id']);
    flash('ok', "Member {$new['name']} aktif dengan no. anggota {$new['member_no']}. PIN masuk: $plain" . ($pin === '' ? ' (catat dan berikan ke yang bersangkutan).' : '.'));
    redirect(turl('/admin/member/' . $newId));
}

/** Approve one member; returns a short status text for flash messages. */
function member_approve_one(int $id, int $staffId, bool $notify = true): string
{
    $m = member_find($id);
    if ($m['status'] === 'active') return $m['name'] . ' sudah aktif.';
    $m = member_activate($id, $staffId);
    $txt = $m['name'] . ' disetujui (' . $m['member_no'] . ').';
    if ($notify && $m['phone']) {
        $r = wa_send($m['phone'], member_card_message($m));
        if ($r['ok']) $txt .= ' Kartu QR dikirim ke WhatsApp.';
    }
    return $txt;
}

function members_approve(string $id): never
{
    $u = Auth::requireStaff('member');
    flash('ok', member_approve_one((int)$id, (int)$u['id']));
    redirect(p('back') === 'detail' ? turl('/admin/member/' . (int)$id) : turl('/admin/member?tab=menunggu'));
}

function members_reject(string $id): never
{
    Auth::requireStaff('member');
    $m = member_find((int)$id);
    $reason = mb_substr(trim((string)p('reason')), 0, 200) ?: null;
    DB::update('members', ['status' => 'rejected', 'reject_reason' => $reason, 'updated_at' => now()], 'id = ?', [$m['id']]);
    if ($m['phone'] && p('notify')) {
        wa_send($m['phone'], "Halo {$m['name']}, pendaftaran member " . Tenant::get('library_name') . " belum dapat disetujui." . ($reason ? "\nAlasan: $reason" : '') . "\n\nSilakan hubungi petugas perpustakaan.");
    }
    flash('ok', 'Pendaftaran ' . $m['name'] . ' ditolak.');
    redirect(p('back') === 'detail' ? turl('/admin/member/' . $m['id']) : turl('/admin/member?tab=menunggu'));
}

function members_status(string $id): never
{
    $u = Auth::requireStaff('member');
    $m = member_find((int)$id);
    $to = (string)p('status');
    if (!in_array($to, ['active', 'inactive', 'graduated'], true)) abort(400, 'Status tidak dikenal.');
    if ($to === 'active') {
        member_activate((int)$m['id'], (int)$u['id']);
    } else {
        DB::update('members', ['status' => $to, 'updated_at' => now()], 'id = ?', [$m['id']]);
    }
    flash('ok', $m['name'] . ' sekarang berstatus ' . strtolower(label('member_status', $to)) . '.');
    redirect(turl('/admin/member/' . $m['id']));
}

function members_reset_pin(string $id): never
{
    Auth::requireStaff('member');
    $m = member_find((int)$id);
    $pin = random_digits(6);
    DB::run('UPDATE members SET pin_hash = ?, updated_at = ? WHERE id = ?', [password_hash($pin, PASSWORD_DEFAULT), now(), $m['id']]);
    $sent = false;
    if (p('send') && $m['phone']) {
        $sent = wa_send($m['phone'], "Halo {$m['name']}, PIN masuk member " . Tenant::get('library_name') . " kamu sudah direset.\nPIN baru: $pin\n\nMasuk di " . abs_url(turl('/masuk')) . " lalu ganti PIN di menu Profil.")['ok'];
    }
    flash('ok', "PIN baru {$m['name']}: $pin" . ($sent ? ' (sudah dikirim ke WhatsApp).' : '. Berikan langsung ke yang bersangkutan.'));
    redirect(turl('/admin/member/' . $m['id']));
}

function members_send_card(string $id): never
{
    Auth::requireStaff('member');
    $m = member_find((int)$id);
    if ($m['status'] !== 'active') { flash('error', 'Hanya member aktif yang bisa dikirimi kartu.'); redirect(turl('/admin/member/' . $m['id'])); }
    $r = wa_send($m['phone'], member_card_message($m));
    flash($r['ok'] ? 'ok' : 'error', $r['ok'] ? 'Tautan kartu QR dikirim ke WhatsApp ' . $m['name'] . '.' : 'Gagal mengirim: ' . ($r['reason'] ?? ''));
    redirect(turl('/admin/member/' . $m['id']));
}

function members_new_qr(string $id): never
{
    Auth::requireStaff('member');
    $m = member_find((int)$id);
    DB::run('UPDATE members SET qr_token = ?, updated_at = ? WHERE id = ?', [random_token(18), now(), $m['id']]);
    flash('ok', 'QR baru dibuat untuk ' . $m['name'] . '. Kartu lama tidak bisa dipakai lagi, cetak ulang kartunya.');
    redirect(turl('/admin/member/' . $m['id']));
}

function members_bulk(): never
{
    $u = Auth::requireStaff('member');
    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
    $action = (string)p('action');
    $tab = (string)p('tab');
    $back = turl('/admin/member') . ($tab ? '?tab=' . rawurlencode($tab) : '');
    if (!$ids) { flash('error', 'Centang member dulu.'); redirect($back); }
    if ($action === 'kartu') redirect(turl('/admin/member/kartu?ids=' . implode(',', $ids)));
    $n = 0;
    foreach ($ids as $id) {
        $m = DB::one('SELECT * FROM members WHERE id = ? AND tenant_id = ?', [$id, Tenant::id()]);
        if (!$m) continue;
        switch ($action) {
            case 'setujui':
                if (in_array($m['status'], ['pending', 'otp', 'rejected', 'inactive'], true)) { member_approve_one($id, (int)$u['id']); $n++; }
                break;
            case 'tolak':
                if (in_array($m['status'], ['pending', 'otp'], true)) { DB::update('members', ['status' => 'rejected', 'updated_at' => now()], 'id = ?', [$id]); $n++; }
                break;
            case 'nonaktifkan':
                if ($m['status'] === 'active') { DB::update('members', ['status' => 'inactive', 'updated_at' => now()], 'id = ?', [$id]); $n++; }
                break;
            case 'aktifkan':
                if (in_array($m['status'], ['inactive', 'graduated'], true)) { member_activate($id, (int)$u['id']); $n++; }
                break;
        }
    }
    $verb = ['setujui' => 'disetujui', 'tolak' => 'ditolak', 'nonaktifkan' => 'dinonaktifkan', 'aktifkan' => 'diaktifkan'][$action] ?? 'diproses';
    flash($n ? 'ok' : 'warn', "$n member $verb.");
    redirect($back);
}

function members_cards(): string
{
    Auth::requireStaff('member');
    $tid = Tenant::id();
    $ids = array_values(array_filter(array_map('intval', explode(',', (string)g('ids')))));
    if ($ids) {
        $members = DB::q("SELECT * FROM members WHERE tenant_id = ? AND status = 'active' AND id IN " . DB::in($ids) . ' ORDER BY kelas, name', array_merge([$tid], $ids));
    } elseif (g('kelas') !== '') {
        $members = DB::q("SELECT * FROM members WHERE tenant_id = ? AND status = 'active' AND kelas = ? ORDER BY name", [$tid, g('kelas')]);
    } elseif (g('baru')) {
        $members = DB::q("SELECT * FROM members WHERE tenant_id = ? AND status = 'active' AND approved_at >= ? ORDER BY kelas, name", [$tid, date('Y-m-d H:i:s', strtotime('-' . max(1, (int)g('baru')) . ' days'))]);
    } else {
        $members = [];
    }
    return view('public/card_print', ['members' => $members, 's' => Tenant::settings(), 'back' => turl('/admin/member?tab=aktif'), 'title' => 'Cetak kartu member',
        'scripts' => ['vendor/qrcode.js']], 'bare');
}

function members_export(): never
{
    Auth::requireStaff('member');
    $rows = DB::q('SELECT * FROM members WHERE tenant_id = ? ORDER BY status, type, kelas, name', [Tenant::id()]);
    $sheet = [['No. anggota', 'Nama', 'Jenis', 'NISN', 'NIP', 'Kelas', 'Mapel / bagian', 'L/P', 'WhatsApp', 'Email', 'Status', 'Berlaku s.d.', 'Terdaftar', 'Sumber']];
    foreach ($rows as $m) $sheet[] = [$m['member_no'], $m['name'], label('member_type', $m['type']), $m['nisn'], $m['nip'], $m['kelas'], $m['subject'], $m['gender'],
        $m['phone'], $m['email'], label('member_status', $m['status']), $m['valid_until'], substr($m['created_at'], 0, 10), $m['source']];
    xlsx_download('member-' . App::$tenant['slug'] . '-' . today() . '.xlsx', ['Member' => $sheet]);
}

/* ---------------- import data induk siswa ---------------- */

function members_import_form(): string
{
    Auth::requireStaff('member');
    $result = $_SESSION['_import'] ?? null;
    unset($_SESSION['_import']);
    return view('admin/import', ['kind' => 'member', 'title' => 'Impor data siswa', 'active' => 'member', 'result' => $result], 'admin');
}

function members_template(): never
{
    Auth::requireStaff('member');
    xlsx_download('templat-data-siswa.xlsx', ['Siswa' => [
        ['NISN', 'Nama', 'Kelas', 'Jenis kelamin', 'No. WhatsApp'],
        ['0071234567', 'Contoh Nama Siswa', 'X-1', 'P', '081234567890'],
    ]]);
}

function members_import(): never
{
    $u = Auth::requireStaff('member');
    $back = turl('/admin/member/impor');
    if (empty($_FILES['file']['tmp_name'])) { flash('error', 'Pilih berkas Excel atau CSV.'); redirect($back); }
    $rows = sheet_read($_FILES['file']['tmp_name'], $_FILES['file']['name']);
    if (count($rows) < 2) { flash('error', 'Berkas kosong atau tidak ada baris data.'); redirect($back); }
    $map = sheet_header_map(array_shift($rows), MEMBER_IMPORT_ALIASES);
    if (!isset($map['nisn'], $map['name'])) { flash('error', 'Kolom "NISN" dan "Nama" wajib ada di baris pertama.'); redirect($back); }
    $asMember = p('mode') === 'member';
    $tid = Tenant::id();
    $kelasList = Tenant::get('kelas_list');
    $ref = 0; $created = 0; $updated = 0; $skipped = [];
    DB::tx(function () use ($rows, $map, $asMember, $tid, $kelasList, $u, &$ref, &$created, &$updated, &$skipped) {
        foreach ($rows as $i => $r) {
            $get = fn($k) => isset($map[$k]) ? trim((string)($r[$map[$k]] ?? '')) : '';
            $nisn = preg_replace('/\D/', '', $get('nisn'));
            $name = preg_replace('/\s+/', ' ', $get('name'));
            if ($nisn === '' && $name === '') continue;
            if (strlen($nisn) === 9) $nisn = '0' . $nisn; // Excel often drops the leading zero
            if (!preg_match('/^\d{10}$/', $nisn) || mb_strlen($name) < 2) { $skipped[] = 'Baris ' . ($i + 2) . ': NISN/nama tidak valid'; continue; }
            $kelas = strtoupper(str_replace(' ', '-', $get('kelas')));
            if ($kelas !== '' && !in_array($kelas, $kelasList, true)) $kelas = preg_replace('/^(XII|XI|X)[\s.]*/', '$1-', $kelas);
            $g = strtoupper(substr($get('gender'), 0, 1));
            $gender = in_array($g, ['L', 'P'], true) ? $g : null;
            $phone = norm_phone($get('phone')) ?: null;
            DB::run('INSERT INTO students_ref (tenant_id, nisn, name, kelas, gender, imported_at) VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE name = VALUES(name), kelas = VALUES(kelas), gender = VALUES(gender), imported_at = VALUES(imported_at)',
                [$tid, $nisn, $name, $kelas ?: null, $gender, now()]);
            $ref++;
            $existing = DB::one('SELECT * FROM members WHERE tenant_id = ? AND nisn = ?', [$tid, $nisn]);
            if ($existing) {
                if ($kelas !== '' && $existing['kelas'] !== $kelas) { DB::run('UPDATE members SET kelas = ?, updated_at = ? WHERE id = ?', [$kelas, now(), $existing['id']]); $updated++; }
                continue;
            }
            if (!$asMember) continue;
            $id = DB::insert('members', [
                'tenant_id' => $tid, 'type' => 'siswa', 'name' => $name, 'nisn' => $nisn, 'kelas' => $kelas ?: null, 'gender' => $gender, 'phone' => $phone,
                'pin_hash' => password_hash(substr($nisn, -6), PASSWORD_DEFAULT), 'qr_token' => random_token(18), 'status' => 'pending',
                'source' => 'impor', 'created_at' => now(), 'updated_at' => now(),
            ]);
            member_activate($id, (int)$u['id']);
            $created++;
        }
    });
    $msg = "$ref data siswa tersimpan sebagai data induk.";
    if ($created) $msg .= " $created akun member aktif dibuat (PIN awal = 6 digit terakhir NISN).";
    if ($updated) $msg .= " $updated kelas member diperbarui.";
    $_SESSION['_import'] = ['ok' => $msg, 'skipped' => $skipped];
    redirect($back);
}

/* ---------------- kenaikan kelas ---------------- */

/** Next class for X-1 → XI-1, XI-1 → XII-1, XII → null (lulus). */
function kelas_next(string $kelas): ?string
{
    if (preg_match('/^XII\b/i', $kelas)) return null;
    if (preg_match('/^XI(\b.*)$/i', $kelas, $m)) return 'XII' . $m[1];
    if (preg_match('/^X(\b.*)$/i', $kelas, $m)) return 'XI' . $m[1];
    return $kelas;
}

function members_promote_form(): string
{
    Auth::requireStaff('member');
    $rows = DB::q("SELECT kelas, COUNT(*) n FROM members WHERE tenant_id = ? AND type = 'siswa' AND status = 'active' AND kelas IS NOT NULL GROUP BY kelas", [Tenant::id()]);
    usort($rows, fn($a, $b) => [strlen(kelas_level($a['kelas'])), $a['kelas']] <=> [strlen(kelas_level($b['kelas'])), $b['kelas']]);
    return view('admin/promote', ['rows' => $rows, 'last' => Tenant::get('last_promotion'), 'title' => 'Kenaikan kelas', 'active' => 'member'], 'admin');
}

function members_promote(): never
{
    Auth::requireStaff('member');
    if (p('confirm') !== 'NAIK KELAS') { flash('error', 'Ketik NAIK KELAS untuk konfirmasi.'); redirect(turl('/admin/member/kenaikan')); }
    $tid = Tenant::id();
    $graduate = (bool)p('graduate');
    $stats = DB::tx(function () use ($tid, $graduate) {
        $grad = 0; $up = 0;
        if ($graduate) {
            $grad = DB::run("UPDATE members SET status = 'graduated', updated_at = ? WHERE tenant_id = ? AND type = 'siswa' AND status = 'active' AND kelas LIKE 'XII%'", [now(), $tid]);
            DB::run("DELETE FROM students_ref WHERE tenant_id = ? AND kelas LIKE 'XII%'", [$tid]);
        }
        // Move XI → XII first, then X → XI, so nobody jumps two levels.
        foreach (['XI', 'X'] as $lvl) {
            foreach (DB::q("SELECT id, kelas FROM members WHERE tenant_id = ? AND type = 'siswa' AND status = 'active' AND kelas REGEXP ?", [$tid, '^' . $lvl . '([^I]|$)']) as $m) {
                $next = kelas_next($m['kelas']);
                if ($next && $next !== $m['kelas']) { DB::run('UPDATE members SET kelas = ?, updated_at = ? WHERE id = ?', [$next, now(), $m['id']]); $up++; }
            }
            foreach (DB::q('SELECT nisn, kelas FROM students_ref WHERE tenant_id = ? AND kelas REGEXP ?', [$tid, '^' . $lvl . '([^I]|$)']) as $r) {
                $next = kelas_next((string)$r['kelas']);
                if ($next) DB::run('UPDATE students_ref SET kelas = ? WHERE tenant_id = ? AND nisn = ?', [$next, $tid, $r['nisn']]);
            }
        }
        return [$up, $grad];
    });
    Tenant::set('last_promotion', now());
    flash('ok', "{$stats[0]} siswa naik kelas" . ($graduate ? ", {$stats[1]} siswa kelas XII ditandai lulus." : '.'));
    redirect(turl('/admin/member?tab=aktif'));
}
