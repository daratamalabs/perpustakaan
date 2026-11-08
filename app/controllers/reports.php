<?php
declare(strict_types=1);

const REPORT_TYPES = [
    'kunjungan' => ['Kunjungan', 'users'],
    'peminjaman' => ['Peminjaman', 'sirk'],
    'koleksi' => ['Koleksi', 'book'],
    'aset' => ['Aset', 'asset'],
    'member' => ['Member', 'member'],
];

/** Month from ?bulan=YYYY-MM → [first day, last day, label]. */
function report_month(?string $ym = null): array
{
    $ym = $ym ?? (string)g('bulan');
    if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $mm) || (int)$mm[2] < 1 || (int)$mm[2] > 12) $ym = date('Y-m');
    $from = $ym . '-01';
    $to = date('Y-m-t', strtotime($from));
    return [$from, $to, fmt_date($from, 'month'), $ym];
}

function report_data(string $type, string $from, string $to): array
{
    $tid = Tenant::id();
    $range = ["$from 00:00:00", "$to 23:59:59"];
    switch ($type) {
        case 'kunjungan':
            $prevFrom = date('Y-m-01', strtotime("$from -1 month"));
            $prevTo = date('Y-m-t', strtotime($prevFrom));
            $daily = stats_daily($from, $to);
            $hours = Tenant::get('hours');
            $days = [];
            for ($t = strtotime($from); $t <= strtotime(min($to, today())); $t += 86400) {
                $d = date('Y-m-d', $t);
                if (!empty($hours[(int)date('w', $t)]['open']) || isset($daily[$d])) $days[$d] = $daily[$d]['total'] ?? 0;
            }
            return [
                'counts' => stats_visit_counts($from, $to),
                'prev' => stats_visit_counts($prevFrom, $prevTo),
                'days' => $days,
                'hourly' => stats_hourly($from, $to, 7, 15),
                'levels' => stats_by_level($from, $to),
                'classes' => stats_top_classes($from, $to, 10),
                'purposes' => DB::q("SELECT purpose, COUNT(*) n FROM visits WHERE tenant_id = ? AND check_in BETWEEN ? AND ? AND purpose IS NOT NULL AND purpose <> '' GROUP BY purpose ORDER BY n DESC LIMIT 8", array_merge([$tid], $range)),
                'methods' => DB::q('SELECT method, COUNT(*) n FROM visits WHERE tenant_id = ? AND check_in BETWEEN ? AND ? GROUP BY method ORDER BY n DESC', array_merge([$tid], $range)),
            ];
        case 'peminjaman':
            return [
                'borrowed' => (int)DB::val('SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND loaned_at BETWEEN ? AND ?', array_merge([$tid], $range)),
                'returned' => (int)DB::val('SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND returned_at BETWEEN ? AND ?', array_merge([$tid], $range)),
                'late_returns' => (int)DB::val('SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND returned_at BETWEEN ? AND ? AND DATE(returned_at) > due_date', array_merge([$tid], $range)),
                'fines' => (int)DB::val('SELECT COALESCE(SUM(fine_amount),0) FROM loans WHERE tenant_id = ? AND returned_at BETWEEN ? AND ?', array_merge([$tid], $range)),
                'fines_paid' => (int)DB::val('SELECT COALESCE(SUM(fine_amount),0) FROM loans WHERE tenant_id = ? AND returned_at BETWEEN ? AND ? AND fine_paid = 1', array_merge([$tid], $range)),
                'open' => stats_loans(),
                'borrowers' => (int)DB::val('SELECT COUNT(DISTINCT member_id) FROM loans WHERE tenant_id = ? AND loaned_at BETWEEN ? AND ?', array_merge([$tid], $range)),
                'top_books' => DB::q('SELECT b.title, b.author, COUNT(*) n FROM loans l JOIN copies c ON c.id = l.copy_id JOIN books b ON b.id = c.book_id
                    WHERE l.tenant_id = ? AND l.loaned_at BETWEEN ? AND ? GROUP BY b.id ORDER BY n DESC, b.title LIMIT 10', array_merge([$tid], $range)),
                'top_members' => DB::q('SELECT m.name, m.type, m.kelas, COUNT(*) n FROM loans l JOIN members m ON m.id = l.member_id
                    WHERE l.tenant_id = ? AND l.loaned_at BETWEEN ? AND ? GROUP BY m.id ORDER BY n DESC, m.name LIMIT 10', array_merge([$tid], $range)),
                'by_category' => DB::q('SELECT b.category, COUNT(*) n FROM loans l JOIN copies c ON c.id = l.copy_id JOIN books b ON b.id = c.book_id
                    WHERE l.tenant_id = ? AND l.loaned_at BETWEEN ? AND ? GROUP BY b.category ORDER BY n DESC', array_merge([$tid], $range)),
            ];
        case 'koleksi':
            return [
                'by_category' => DB::q("SELECT b.category, COUNT(DISTINCT b.id) titles, COUNT(c.id) copies, SUM(c.status = 'dipinjam') borrowed,
                    SUM(c.kondisi IN ('rusak_ringan','rusak_berat')) damaged, SUM(c.kondisi = 'hilang') lost
                    FROM books b LEFT JOIN copies c ON c.book_id = b.id WHERE b.tenant_id = ? GROUP BY b.category ORDER BY titles DESC", [$tid]),
                'new_books' => DB::q('SELECT b.*, (SELECT COUNT(*) FROM copies c WHERE c.book_id = b.id) copies FROM books b WHERE b.tenant_id = ? AND b.created_at BETWEEN ? AND ? ORDER BY b.created_at DESC LIMIT 50', array_merge([$tid], $range)),
                'new_copies' => (int)DB::val('SELECT COUNT(*) FROM copies WHERE tenant_id = ? AND created_at BETWEEN ? AND ?', array_merge([$tid], $range)),
                'never' => (int)DB::val('SELECT COUNT(*) FROM books b WHERE b.tenant_id = ? AND b.collection_type <> \'baca\' AND NOT EXISTS (SELECT 1 FROM copies c JOIN loans l ON l.copy_id = c.id WHERE c.book_id = b.id)', [$tid]),
                'by_source' => DB::q("SELECT COALESCE(NULLIF(source,''),'Tidak dicatat') source, COUNT(*) n FROM copies WHERE tenant_id = ? GROUP BY 1 ORDER BY n DESC LIMIT 8", [$tid]),
            ];
        case 'aset':
            return [
                'by_category' => DB::q("SELECT category, COUNT(*) n, COALESCE(SUM(value),0) v, SUM(kondisi = 'baik') baik, SUM(kondisi IN ('rusak_ringan','rusak_berat')) rusak, SUM(kondisi = 'perbaikan') perbaikan
                    FROM assets WHERE tenant_id = ? AND kondisi <> 'dihapus' GROUP BY category ORDER BY n DESC", [$tid]),
                'by_location' => DB::q("SELECT COALESCE(NULLIF(location,''),'Belum diisi') location, COUNT(*) n FROM assets WHERE tenant_id = ? AND kondisi <> 'dihapus' GROUP BY 1 ORDER BY n DESC", [$tid]),
                'logs' => DB::q('SELECT l.*, a.code, a.name FROM asset_logs l JOIN assets a ON a.id = l.asset_id WHERE l.tenant_id = ? AND l.date BETWEEN ? AND ? ORDER BY l.date DESC, l.id DESC', [$tid, $from, $to]),
                'cost' => (int)DB::val('SELECT COALESCE(SUM(cost),0) FROM asset_logs WHERE tenant_id = ? AND date BETWEEN ? AND ?', [$tid, $from, $to]),
            ];
        default: // member
            return [
                'by_status' => DB::q('SELECT status, type, COUNT(*) n FROM members WHERE tenant_id = ? GROUP BY status, type', [$tid]),
                'new' => (int)DB::val('SELECT COUNT(*) FROM members WHERE tenant_id = ? AND created_at BETWEEN ? AND ?', array_merge([$tid], $range)),
                'approved' => (int)DB::val('SELECT COUNT(*) FROM members WHERE tenant_id = ? AND approved_at BETWEEN ? AND ?', array_merge([$tid], $range)),
                'by_kelas' => DB::q("SELECT kelas, COUNT(*) n, (SELECT COUNT(*) FROM students_ref r WHERE r.tenant_id = members.tenant_id AND r.kelas = members.kelas) ref
                    FROM members WHERE tenant_id = ? AND status = 'active' AND type = 'siswa' AND kelas IS NOT NULL GROUP BY kelas ORDER BY kelas", [$tid]),
                'active_visitors' => (int)DB::val('SELECT COUNT(DISTINCT member_id) FROM visits WHERE tenant_id = ? AND member_id IS NOT NULL AND check_in BETWEEN ? AND ?', array_merge([$tid], $range)),
            ];
    }
}

function reports_index(): string
{
    Auth::requireStaff('laporan');
    $type = array_key_exists((string)g('jenis'), REPORT_TYPES) ? g('jenis') : 'kunjungan';
    [$from, $to, $label, $ym] = report_month();
    $months = [];
    for ($i = 0; $i < 18; $i++) { $t = strtotime(date('Y-m-01') . " -$i month"); $months[date('Y-m', $t)] = fmt_date(date('Y-m-d', $t), 'month'); }
    return view('admin/reports', ['type' => $type, 'from' => $from, 'to' => $to, 'label' => $label, 'ym' => $ym, 'months' => $months,
        'd' => report_data($type, $from, $to), 's' => Tenant::settings(), 'title' => 'Laporan', 'active' => 'laporan'], 'admin');
}

function reports_export(): never
{
    Auth::requireStaff('laporan');
    $type = array_key_exists((string)g('jenis'), REPORT_TYPES) ? g('jenis') : 'kunjungan';
    [$from, $to, $label] = report_month();
    $d = report_data($type, $from, $to);
    $tid = Tenant::id();
    $title = [['Laporan ' . strtolower(REPORT_TYPES[$type][0]) . ' · ' . Tenant::get('library_name')], ['Periode: ' . $label], []];
    $sheets = [];
    switch ($type) {
        case 'kunjungan':
            $c = $d['counts'];
            $sheets['Ringkasan'] = array_merge($title, [['Total kunjungan', (int)$c['total']], ['Siswa', (int)$c['siswa']], ['Guru & tendik', (int)$c['staf']], ['Tamu', (int)$c['tamu']],
                ['Dicatat manual', (int)$c['manual']], ['Rata-rata lama (menit)', round((float)$c['avg_min'])], ['Bulan sebelumnya', (int)$d['prev']['total']]]);
            $daily = [['Tanggal', 'Hari', 'Kunjungan']];
            foreach ($d['days'] as $day => $n) $daily[] = [$day, day_name($day), $n];
            $sheets['Harian'] = $daily;
            $cls = [['Kelas', 'Kunjungan']];
            foreach ($d['classes'] as $r) $cls[] = [$r['kelas'], (int)$r['n']];
            $sheets['Kelas teraktif'] = $cls;
            $rows = DB::q('SELECT * FROM visits WHERE tenant_id = ? AND check_in BETWEEN ? AND ? ORDER BY check_in', [$tid, "$from 00:00:00", "$to 23:59:59"]);
            $det = [['Tanggal', 'Masuk', 'Keluar', 'Nama', 'Kategori', 'Kelas / bagian', 'Asal', 'Keperluan', 'Metode']];
            foreach ($rows as $v) $det[] = [substr($v['check_in'], 0, 10), fmt_time($v['check_in']), $v['check_out'] ? fmt_time($v['check_out']) : '', $v['name'], label('category', $v['category']), $v['kelas'], $v['origin'], $v['purpose'], label('method', $v['method'])];
            $sheets['Data kunjungan'] = $det;
            break;
        case 'peminjaman':
            $sheets['Ringkasan'] = array_merge($title, [['Buku dipinjam', $d['borrowed']], ['Buku dikembalikan', $d['returned']], ['Dikembalikan terlambat', $d['late_returns']],
                ['Jumlah peminjam', $d['borrowers']], ['Denda (Rp)', $d['fines']], ['Denda dibayar (Rp)', $d['fines_paid']], ['Masih dipinjam saat ini', $d['open']['open']], ['Terlambat saat ini', $d['open']['overdue']]]);
            $tb = [['Judul', 'Pengarang', 'Dipinjam']];
            foreach ($d['top_books'] as $r) $tb[] = [$r['title'], $r['author'], (int)$r['n']];
            $sheets['Buku terpopuler'] = $tb;
            $rows = DB::q('SELECT l.*, m.name, m.kelas, b.title, c.code FROM loans l JOIN members m ON m.id = l.member_id JOIN copies c ON c.id = l.copy_id JOIN books b ON b.id = c.book_id
                WHERE l.tenant_id = ? AND (l.loaned_at BETWEEN ? AND ? OR l.returned_at BETWEEN ? AND ?) ORDER BY l.loaned_at', [$tid, "$from 00:00:00", "$to 23:59:59", "$from 00:00:00", "$to 23:59:59"]);
            $det = [['Dipinjam', 'Jatuh tempo', 'Dikembalikan', 'Peminjam', 'Kelas', 'Judul', 'Kode', 'Denda (Rp)', 'Lunas']];
            foreach ($rows as $l) $det[] = [substr($l['loaned_at'], 0, 10), $l['due_date'], $l['returned_at'] ? substr($l['returned_at'], 0, 10) : '', $l['name'], $l['kelas'], $l['title'], $l['code'], (int)$l['fine_amount'], $l['fine_amount'] ? ($l['fine_paid'] ? 'Ya' : 'Belum') : ''];
            $sheets['Data peminjaman'] = $det;
            break;
        case 'koleksi':
            $cat = [['Kategori', 'Judul', 'Eksemplar', 'Dipinjam', 'Rusak', 'Hilang']];
            foreach ($d['by_category'] as $r) $cat[] = [$r['category'], (int)$r['titles'], (int)$r['copies'], (int)$r['borrowed'], (int)$r['damaged'], (int)$r['lost']];
            $sheets['Per kategori'] = array_merge($title, $cat);
            $nb = [['Ditambahkan', 'Judul', 'Pengarang', 'Kategori', 'Eksemplar']];
            foreach ($d['new_books'] as $b) $nb[] = [substr($b['created_at'], 0, 10), $b['title'], $b['author'], $b['category'], (int)$b['copies']];
            $sheets['Buku baru'] = $nb;
            break;
        case 'aset':
            $cat = [['Kategori', 'Jumlah', 'Nilai (Rp)', 'Baik', 'Rusak', 'Dalam perbaikan']];
            foreach ($d['by_category'] as $r) $cat[] = [$r['category'], (int)$r['n'], (int)$r['v'], (int)$r['baik'], (int)$r['rusak'], (int)$r['perbaikan']];
            $sheets['Per kategori'] = array_merge($title, $cat);
            $lg = [['Tanggal', 'Kode', 'Barang', 'Catatan', 'Biaya (Rp)']];
            foreach ($d['logs'] as $l) $lg[] = [$l['date'], $l['code'], $l['name'], $l['text'], $l['cost'] === null ? '' : (int)$l['cost']];
            $sheets['Perawatan'] = $lg;
            break;
        default:
            $st = [['Status', 'Jenis', 'Jumlah']];
            foreach ($d['by_status'] as $r) $st[] = [label('member_status', $r['status']), label('member_type', $r['type']), (int)$r['n']];
            $sheets['Ringkasan'] = array_merge($title, [['Pendaftar baru', $d['new']], ['Disetujui', $d['approved']], ['Member yang berkunjung', $d['active_visitors']], []], $st);
            $kl = [['Kelas', 'Member aktif', 'Siswa di data induk']];
            foreach ($d['by_kelas'] as $r) $kl[] = [$r['kelas'], (int)$r['n'], (int)$r['ref']];
            $sheets['Per kelas'] = $kl;
    }
    xlsx_download('laporan-' . $type . '-' . App::$tenant['slug'] . '-' . substr($from, 0, 7) . '.xlsx', $sheets);
}
