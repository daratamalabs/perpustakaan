<?php
declare(strict_types=1);

/* ------------------------------------------------------------------ */
/* Kiosk meja depan                                                    */
/* ------------------------------------------------------------------ */

function kiosk_page(): string
{
    Auth::requireStaff('kiosk');
    $s = Tenant::settings();
    return view('kiosk/kiosk', ['s' => $s, 'open' => library_open_now(), 'title' => 'Kiosk',
        'scripts' => ['vendor/jsQR.js', 'js/scanner.js', 'js/kiosk.js']], 'bare');
}

/** Shared response for a member check-in attempt. */
function kiosk_checkin_response(?array $m, string $method): array
{
    $u = Auth::staff();
    $s = Tenant::settings();
    if (!empty($s['enforce_hours']) && !library_open_now()['open']) {
        return ['ok' => false, 'tone' => 'warn', 'title' => 'Perpustakaan sedang tutup', 'message' => 'Check-in hanya bisa dilakukan pada jam layanan.'];
    }
    if (!$m) return ['ok' => false, 'tone' => 'bad', 'title' => 'Kartu tidak dikenali', 'message' => 'QR ini tidak terdaftar di perpustakaan ini. Hubungi petugas.'];
    $base = ['name' => $m['name'], 'initials' => initials($m['name']), 'meta' => member_meta($m)];
    if ($m['status'] !== 'active') {
        $msg = match ($m['status']) {
            'pending', 'otp' => 'Pendaftaran belum disetujui petugas.',
            'graduated' => 'Kartu alumni sudah tidak aktif.',
            default => 'Kartu sedang nonaktif. Hubungi petugas.',
        };
        return $base + ['ok' => false, 'tone' => 'bad', 'title' => 'Kartu belum bisa dipakai', 'message' => $msg];
    }
    if ($m['valid_until'] && $m['valid_until'] < today()) {
        return $base + ['ok' => false, 'tone' => 'bad', 'title' => 'Masa berlaku kartu habis', 'message' => 'Hubungi petugas untuk memperpanjang kartu.'];
    }
    $r = visit_checkin_member($m, ['method' => $method, 'staff_id' => $u['id'] ?? null]);
    $v = $r['visit'];
    return match ($r['status']) {
        'in' => $base + ['ok' => true, 'tone' => 'ok', 'title' => 'Berhasil masuk', 'time' => fmt_time($v['check_in']),
            'message' => 'Kunjungan ke-' . $r['count'] . ' semester ini'],
        'out' => $base + ['ok' => true, 'tone' => 'out', 'title' => 'Sampai jumpa', 'time' => fmt_time($v['check_out']),
            'message' => 'Lama kunjungan ' . fmt_duration(minutes_between($v['check_in'], $v['check_out']))],
        default => $base + ['ok' => true, 'tone' => 'warn', 'title' => 'Sudah tercatat masuk', 'time' => fmt_time($v['check_in']),
            'message' => 'Masuk tercatat pukul ' . fmt_time($v['check_in']) . '. Pindai lagi saat pulang.'],
    };
}

function kiosk_scan(): never
{
    Auth::requireStaff('kiosk');
    $code = (string)p('code');
    json_out(kiosk_checkin_response(member_by_scan($code), 'qr'));
}

function kiosk_search(): never
{
    Auth::requireStaff('kiosk');
    $q = trim((string)g('q'));
    if (mb_strlen($q) < 3) json_out(['ok' => true, 'items' => []]);
    $rows = DB::q("SELECT * FROM members WHERE tenant_id = ? AND status = 'active' AND (name LIKE ? OR nisn = ? OR member_no = ?) ORDER BY name LIMIT 8",
        [Tenant::id(), '%' . $q . '%', $q, strtoupper($q)]);
    json_out(['ok' => true, 'items' => array_map(fn($m) => ['id' => (int)$m['id'], 'name' => $m['name'], 'meta' => member_meta($m), 'initials' => initials($m['name'])], $rows)]);
}

function kiosk_checkin_id(): never
{
    Auth::requireStaff('kiosk');
    $m = DB::one('SELECT * FROM members WHERE id = ? AND tenant_id = ?', [(int)p('id'), Tenant::id()]);
    json_out(kiosk_checkin_response($m, 'cari'));
}

function kiosk_guest_form(): string
{
    Auth::requireStaff('kiosk');
    return view('kiosk/guest', ['s' => Tenant::settings(), 'errors' => [], 'f' => [], 'title' => 'Buku tamu'], 'bare');
}

function kiosk_guest(): string
{
    $u = Auth::requireStaff('kiosk');
    $f = ['name' => preg_replace('/\s+/', ' ', (string)p('name')), 'origin' => (string)p('origin'), 'phone' => (string)p('phone'), 'purpose' => (string)p('purpose')];
    $errors = [];
    if (mb_strlen($f['name']) < 3) $errors['name'] = 'Isi nama lengkap.';
    if ($f['purpose'] === '' || !in_array($f['purpose'], GUEST_PURPOSES, true)) $errors['purpose'] = 'Pilih keperluan.';
    $s = Tenant::settings();
    if (!$errors && !empty($s['enforce_hours']) && !library_open_now()['open']) $errors['name'] = 'Perpustakaan sedang tutup.';
    if ($errors) return view('kiosk/guest', ['s' => $s, 'errors' => $errors, 'f' => $f, 'title' => 'Buku tamu'], 'bare');
    $v = visit_guest($f + ['staff_id' => $u['id']]);
    $_SESSION['kiosk_result'] = ['ok' => true, 'tone' => 'ok', 'title' => 'Selamat datang', 'name' => $v['name'], 'initials' => initials($v['name']),
        'meta' => visit_meta($v), 'time' => fmt_time($v['check_in']), 'message' => 'Data tamu sudah tercatat.'];
    redirect(turl('/kiosk'));
}

/* ------------------------------------------------------------------ */
/* Layar display                                                       */
/* ------------------------------------------------------------------ */

function display_access(): void
{
    $key = (string)g('key');
    $ok = Auth::staff() || ($key !== '' && hash_equals((string)(Tenant::get('display')['key'] ?? ''), $key));
    if (!$ok) {
        if (wants_json()) json_out(['ok' => false, 'message' => 'Tautan layar display tidak valid.'], 403);
        abort(403, 'Tautan layar display tidak valid. Buka layar display dari menu admin, atau minta tautan baru di Pengaturan.');
    }
}

function display_page(): string
{
    display_access();
    return view('kiosk/display', ['s' => Tenant::settings(), 'data' => display_payload(), 'title' => 'Layar display',
        'scripts' => ['js/display.js']], 'bare');
}

function display_data(): never
{
    display_access();
    json_out(['ok' => true] + display_payload());
}

function display_payload(): array
{
    $s = Tenant::settings();
    $today = today();
    $counts = stats_visit_counts($today, $today);
    $monday = date('Y-m-d', strtotime('monday this week'));
    $recent = DB::q('SELECT * FROM visits WHERE tenant_id = ? AND check_in >= ? ORDER BY check_in DESC, id DESC LIMIT 6', [Tenant::id(), $today . ' 00:00:00']);
    $latest = $recent[0] ?? null;
    $latestPayload = null;
    if ($latest) {
        $extra = ['age' => time() - (ts($latest['check_in']) ?? 0)];
        if ($latest['member_id']) $extra['count'] = semester_count((int)$latest['member_id']);
        $latestPayload = visit_payload($latest, $extra);
    }
    $hours = stats_hourly($today, $today, 7, 14);
    $classes = !empty($s['display']['show_classes']) ? stats_top_classes($monday, $today, 5) : [];
    $books = !empty($s['display']['show_new_books']) ? stats_new_books(4) : [];
    $open = library_open_now();
    return [
        'time' => date('H.i'),
        'date' => fmt_date($today, 'long'),
        'open' => $open['open'],
        'hours_today' => !empty($open['hours']['open']) ? str_replace(':', '.', $open['hours']['from']) . '–' . str_replace(':', '.', $open['hours']['to']) : null,
        'total' => (int)$counts['total'],
        'inside' => stats_inside_now(),
        'latest' => $latestPayload,
        'recent' => array_map(fn($v) => visit_payload($v), array_slice($recent, 1, 5)),
        'hourly' => array_map(fn($h, $n) => ['h' => str_pad((string)$h, 2, '0', STR_PAD_LEFT), 'n' => $n, 'now' => (int)date('G') === $h], array_keys($hours), array_values($hours)),
        'classes' => array_map(fn($c) => ['kelas' => $c['kelas'], 'n' => (int)$c['n']], $classes),
        'books' => array_map(function ($b) {
            [$bg, $fg] = cover_color((string)$b['title']);
            return ['title' => $b['title'], 'author' => $b['author'] ?: '', 'shelf' => $b['shelf'] ?: '', 'cover' => $b['cover'] ? media_url($b['cover']) : null, 'bg' => $bg, 'fg' => $fg];
        }, $books),
        'show_classes' => !empty($s['display']['show_classes']),
        'announcements' => (string)$s['display']['announcements'],
        'welcome_seconds' => max(3, (int)$s['display']['welcome_seconds']),
        'library' => $s['library_name'],
    ];
}
