<?php
declare(strict_types=1);

const COPY_AGG = "COUNT(c.id) total,
    SUM(c.status = 'tersedia' AND c.kondisi <> 'hilang') avail,
    SUM(c.status = 'dipinjam') borrowed,
    SUM(c.kondisi IN ('rusak_ringan','rusak_berat')) damaged,
    SUM(c.kondisi = 'hilang') lost";

function books_index(): string
{
    Auth::requireStaff('buku');
    $tid = Tenant::id();
    $s = Tenant::settings();
    $q = (string)g('q');
    $cat = (string)g('kategori');
    $status = (string)g('status');
    $shelf = (string)g('rak');
    $sort = (string)g('urut', 'baru');

    $where = ['b.tenant_id = ?'];
    $params = [$tid];
    if ($q !== '') {
        $where[] = '(b.title LIKE ? OR b.author LIKE ? OR b.isbn = ? OR b.id IN (SELECT book_id FROM copies WHERE tenant_id = ? AND code = ?))';
        array_push($params, "%$q%", "%$q%", preg_replace('/[^0-9Xx]/', '', $q) ?: $q, $tid, strtoupper($q));
    }
    if ($cat !== '') { $where[] = 'b.category = ?'; $params[] = $cat; }
    if ($shelf !== '') { $where[] = 'b.shelf = ?'; $params[] = $shelf; }
    $having = match ($status) {
        'tersedia' => 'HAVING avail > 0',
        'habis' => 'HAVING total > 0 AND avail = 0',
        'rusak' => 'HAVING damaged > 0 OR lost > 0',
        'kosong' => 'HAVING total = 0',
        default => '',
    };
    $order = match ($sort) { 'judul' => 'b.title ASC', 'pengarang' => 'b.author ASC, b.title ASC', 'rak' => 'b.shelf ASC, b.title ASC', default => 'b.created_at DESC, b.id DESC' };
    $base = 'FROM books b LEFT JOIN copies c ON c.book_id = b.id WHERE ' . implode(' AND ', $where) . ' GROUP BY b.id ' . $having;
    $total = (int)DB::val("SELECT COUNT(*) FROM (SELECT b.id, " . COPY_AGG . " $base) x", $params);
    $pg = paginate($total, g('hal', 1), 20);
    $rows = DB::q('SELECT b.*, ' . COPY_AGG . " $base ORDER BY $order LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);

    $summary = DB::one("SELECT (SELECT COUNT(*) FROM books WHERE tenant_id = ?) titles,
        (SELECT COUNT(*) FROM copies WHERE tenant_id = ?) copies,
        (SELECT COUNT(*) FROM copies WHERE tenant_id = ? AND status = 'dipinjam') borrowed,
        (SELECT COUNT(*) FROM copies WHERE tenant_id = ? AND kondisi <> 'baik') bad", [$tid, $tid, $tid, $tid]);
    $catCounts = [];
    foreach (DB::q('SELECT category, COUNT(*) n FROM books WHERE tenant_id = ? GROUP BY category', [$tid]) as $r) $catCounts[$r['category']] = (int)$r['n'];
    $shelves = array_column(DB::q("SELECT DISTINCT shelf FROM books WHERE tenant_id = ? AND shelf IS NOT NULL AND shelf <> '' ORDER BY shelf", [$tid]), 'shelf');
    return view('admin/books', ['rows' => $rows, 'pg' => $pg, 'summary' => $summary, 'catCounts' => $catCounts, 'shelves' => $shelves,
        'cats' => $s['categories'], 'title' => 'Katalog buku', 'active' => 'buku', 'scripts' => []], 'admin');
}

function books_form(string $id = ''): string
{
    Auth::requireStaff('buku');
    $tid = Tenant::id();
    $book = null;
    $copies = [];
    if ($id !== '') {
        $book = DB::one('SELECT * FROM books WHERE id = ? AND tenant_id = ?', [(int)$id, $tid]) ?? abort(404, 'Buku tidak ditemukan.');
        $copies = DB::q("SELECT c.*, (SELECT m.name FROM loans l JOIN members m ON m.id = l.member_id WHERE l.copy_id = c.id AND l.returned_at IS NULL LIMIT 1) borrower,
            (SELECT l.due_date FROM loans l WHERE l.copy_id = c.id AND l.returned_at IS NULL LIMIT 1) due
            FROM copies c WHERE c.book_id = ? ORDER BY c.code", [$book['id']]);
    }
    $shelves = array_column(DB::q("SELECT DISTINCT shelf FROM books WHERE tenant_id = ? AND shelf IS NOT NULL AND shelf <> '' ORDER BY shelf", [$tid]), 'shelf');
    $sources = array_column(DB::q("SELECT DISTINCT source FROM copies WHERE tenant_id = ? AND source IS NOT NULL AND source <> '' ORDER BY source", [$tid]), 'source');
    return view('admin/book_form', ['book' => $book, 'copies' => $copies, 'shelves' => $shelves, 'sources' => $sources, 's' => Tenant::settings(),
        'title' => $book ? $book['title'] : 'Tambah buku', 'active' => 'buku', 'scripts' => ['vendor/JsBarcode.all.min.js', 'js/book.js']], 'admin');
}

function book_input(): array
{
    $type = in_array(p('collection_type'), ['pinjam', 'baca', 'paket'], true) ? p('collection_type') : 'pinjam';
    $d = [
        'title' => preg_replace('/\s+/', ' ', (string)p('title')),
        'author' => (string)p('author') ?: null,
        'publisher' => (string)p('publisher') ?: null,
        'year' => substr((string)p('year'), 0, 10) ?: null,
        'isbn' => preg_replace('/[^0-9Xx]/', '', (string)p('isbn')) ?: null,
        'language' => (string)p('language') ?: null,
        'category' => (string)p('category') ?: 'Nonfiksi',
        'ddc' => (string)p('ddc') ?: null,
        'shelf' => strtoupper((string)p('shelf')) ?: null,
        'collection_type' => $type,
        'synopsis' => (string)p('synopsis') ?: null,
        'keywords' => implode(', ', array_filter(array_map('trim', explode(',', (string)p('keywords'))))) ?: null,
    ];
    $d['call_number'] = (string)p('call_number') ?: call_number($d['ddc'], $d['author'], $d['title']);
    if ($d['call_number'] === '') $d['call_number'] = null;
    return $d;
}

function books_save(string $id = ''): never
{
    $u = Auth::requireStaff('buku');
    $tid = Tenant::id();
    $d = book_input();
    $book = $id !== '' ? (DB::one('SELECT * FROM books WHERE id = ? AND tenant_id = ?', [(int)$id, $tid]) ?? abort(404)) : null;
    if (mb_strlen($d['title']) < 1) {
        flash('error', 'Judul buku wajib diisi.');
        keep_old($_POST);
        redirect($book ? turl('/admin/buku/' . $book['id']) : turl('/admin/buku/baru'));
    }
    $cover = save_upload('cover', 'covers', 3_000_000);
    if ($cover) { if ($book) delete_upload($book['cover']); $d['cover'] = $cover; }
    elseif ($book && p('remove_cover')) { delete_upload($book['cover']); $d['cover'] = null; }
    $d['updated_at'] = now();
    if ($book) {
        DB::update('books', $d, 'id = ?', [$book['id']]);
        flash('ok', 'Data buku disimpan.');
        redirect(turl('/admin/buku/' . $book['id']));
    }
    $newId = DB::tx(function () use ($d, $tid) {
        $bid = DB::insert('books', $d + ['tenant_id' => $tid, 'created_at' => now()]);
        $n = max(0, min(100, (int)p('copies', 1)));
        copies_create($bid, $n, (string)p('source') ?: null, (string)p('acquired_at') ?: today(), 'baik');
        return $bid;
    });
    flash('ok', 'Buku ditambahkan' . ((int)p('copies', 1) > 0 ? ' beserta ' . (int)p('copies', 1) . ' eksemplar' : '') . '. Cetak label untuk ditempel di punggung buku.');
    redirect(turl('/admin/buku/' . $newId));
}

/** Create N copies with codes B-000412-01, -02 … */
function copies_create(int $bookId, int $n, ?string $source, ?string $date, string $kondisi, ?string $customCode = null): array
{
    $tid = Tenant::id();
    $codes = [];
    if ($customCode) {
        $code = strtoupper(trim($customCode));
        if (DB::val('SELECT id FROM copies WHERE tenant_id = ? AND code = ?', [$tid, $code])) throw new HttpException("Kode $code sudah dipakai.", 400);
        DB::insert('copies', ['tenant_id' => $tid, 'book_id' => $bookId, 'code' => $code, 'source' => $source, 'acquired_at' => $date, 'kondisi' => $kondisi, 'created_at' => now()]);
        return [$code];
    }
    $prefix = sprintf('B-%06d-', $bookId);
    $max = (int)DB::val("SELECT MAX(CAST(SUBSTRING(code, 10) AS UNSIGNED)) FROM copies WHERE tenant_id = ? AND code LIKE ?", [$tid, $prefix . '%']);
    for ($i = 1; $i <= $n; $i++) {
        $code = $prefix . sprintf('%02d', $max + $i);
        DB::insert('copies', ['tenant_id' => $tid, 'book_id' => $bookId, 'code' => $code, 'source' => $source, 'acquired_at' => $date, 'kondisi' => $kondisi, 'created_at' => now()]);
        $codes[] = $code;
    }
    return $codes;
}

function books_delete(string $id): never
{
    Auth::requireStaff('buku');
    $book = DB::one('SELECT * FROM books WHERE id = ? AND tenant_id = ?', [(int)$id, Tenant::id()]) ?? abort(404);
    $open = (int)DB::val('SELECT COUNT(*) FROM loans l JOIN copies c ON c.id = l.copy_id WHERE c.book_id = ? AND l.returned_at IS NULL', [$book['id']]);
    if ($open) {
        flash('error', "Tidak bisa dihapus: masih ada $open eksemplar yang dipinjam.");
        redirect(turl('/admin/buku/' . $book['id']));
    }
    DB::run('DELETE FROM books WHERE id = ?', [$book['id']]);
    delete_upload($book['cover']);
    flash('ok', 'Buku "' . $book['title'] . '" dihapus.');
    redirect(turl('/admin/buku'));
}

function copies_add(string $bookId): never
{
    Auth::requireStaff('buku');
    $book = DB::one('SELECT * FROM books WHERE id = ? AND tenant_id = ?', [(int)$bookId, Tenant::id()]) ?? abort(404);
    $n = max(1, min(100, (int)p('count', 1)));
    $kondisi = in_array(p('kondisi'), ['baik', 'rusak_ringan', 'rusak_berat'], true) ? p('kondisi') : 'baik';
    $codes = copies_create((int)$book['id'], $n, (string)p('source') ?: null, (string)p('acquired_at') ?: today(), $kondisi, $n === 1 ? ((string)p('code') ?: null) : null);
    flash('ok', count($codes) . ' eksemplar ditambahkan: ' . implode(', ', $codes) . '.');
    redirect(turl('/admin/buku/' . $book['id']) . '#eksemplar');
}

function copies_update(string $id): never
{
    Auth::requireStaff('buku');
    $c = DB::one('SELECT * FROM copies WHERE id = ? AND tenant_id = ?', [(int)$id, Tenant::id()]) ?? abort(404);
    $kondisi = in_array(p('kondisi'), ['baik', 'rusak_ringan', 'rusak_berat', 'hilang'], true) ? p('kondisi') : $c['kondisi'];
    DB::update('copies', ['kondisi' => $kondisi, 'source' => (string)p('source') ?: null, 'acquired_at' => (string)p('acquired_at') ?: null, 'notes' => (string)p('notes') ?: null], 'id = ?', [$c['id']]);
    flash('ok', 'Eksemplar ' . $c['code'] . ' diperbarui.');
    redirect(turl('/admin/buku/' . $c['book_id']) . '#eksemplar');
}

function copies_delete(string $id): never
{
    Auth::requireStaff('buku');
    $c = DB::one('SELECT * FROM copies WHERE id = ? AND tenant_id = ?', [(int)$id, Tenant::id()]) ?? abort(404);
    if ($c['status'] === 'dipinjam') {
        flash('error', 'Eksemplar ' . $c['code'] . ' sedang dipinjam, kembalikan dulu sebelum dihapus.');
    } else {
        DB::run('DELETE FROM copies WHERE id = ?', [$c['id']]);
        flash('ok', 'Eksemplar ' . $c['code'] . ' dihapus.');
    }
    redirect(turl('/admin/buku/' . $c['book_id']) . '#eksemplar');
}

function books_isbn(): never
{
    Auth::requireStaff('buku');
    $isbn = preg_replace('/[^0-9Xx]/', '', (string)g('isbn'));
    if ($isbn === '') json_out(['ok' => false, 'message' => 'Isi ISBN dulu.']);
    $existing = DB::one('SELECT id, title FROM books WHERE tenant_id = ? AND isbn = ?', [Tenant::id(), $isbn]);
    $data = isbn_lookup($isbn);
    if (!$data && !$existing) json_out(['ok' => false, 'message' => 'Data ISBN tidak ditemukan di katalog online. Isi manual saja.']);
    json_out(['ok' => true, 'book' => $data, 'existing' => $existing ? ['id' => (int)$existing['id'], 'title' => $existing['title'], 'url' => turl('/admin/buku/' . $existing['id'])] : null]);
}

/* ---------------- import / export / labels ---------------- */

const BOOK_IMPORT_ALIASES = [
    'title' => ['judul', 'judul buku', 'title'],
    'author' => ['pengarang', 'penulis', 'author'],
    'publisher' => ['penerbit', 'publisher'],
    'year' => ['tahun', 'tahun terbit', 'year'],
    'isbn' => ['isbn'],
    'category' => ['kategori', 'category'],
    'ddc' => ['ddc', 'klasifikasi', 'no klasifikasi'],
    'shelf' => ['rak', 'lokasi rak', 'shelf'],
    'collection_type' => ['jenis', 'jenis koleksi'],
    'copies' => ['jumlah', 'jumlah eksemplar', 'eksemplar', 'jml'],
    'source' => ['sumber', 'sumber dana', 'asal'],
];

function books_import_form(): string
{
    Auth::requireStaff('buku');
    $result = $_SESSION['_import'] ?? null;
    unset($_SESSION['_import']);
    return view('admin/import', ['kind' => 'buku', 'title' => 'Impor buku', 'active' => 'buku', 'result' => $result], 'admin');
}

function books_import(): never
{
    Auth::requireStaff('buku');
    if (empty($_FILES['file']['tmp_name'])) { flash('error', 'Pilih berkas Excel atau CSV.'); redirect(turl('/admin/buku/impor')); }
    $rows = sheet_read($_FILES['file']['tmp_name'], $_FILES['file']['name']);
    if (count($rows) < 2) { flash('error', 'Berkas kosong atau tidak ada baris data.'); redirect(turl('/admin/buku/impor')); }
    $map = sheet_header_map(array_shift($rows), BOOK_IMPORT_ALIASES);
    if (!isset($map['title'])) { flash('error', 'Kolom "Judul" tidak ditemukan di baris pertama.'); redirect(turl('/admin/buku/impor')); }
    $tid = Tenant::id();
    $added = 0; $copies = 0; $merged = 0; $skipped = [];
    DB::tx(function () use ($rows, $map, $tid, &$added, &$copies, &$merged, &$skipped) {
        foreach ($rows as $i => $r) {
            $get = fn($k) => isset($map[$k]) ? trim((string)($r[$map[$k]] ?? '')) : '';
            $title = $get('title');
            if ($title === '') { if (array_filter($r)) $skipped[] = 'Baris ' . ($i + 2) . ': judul kosong'; continue; }
            $isbn = preg_replace('/[^0-9Xx]/', '', $get('isbn')) ?: null;
            $n = $get('copies') === '' ? 1 : max(0, min(500, (int)$get('copies')));
            $type = strtolower($get('collection_type'));
            $type = str_contains($type, 'paket') ? 'paket' : (str_contains($type, 'baca') || str_contains($type, 'tempat') || str_contains($type, 'ref') ? 'baca' : 'pinjam');
            $existing = $isbn ? DB::one('SELECT id FROM books WHERE tenant_id = ? AND isbn = ?', [$tid, $isbn]) : null;
            if ($existing) {
                $bid = (int)$existing['id'];
                $merged++;
            } else {
                $author = $get('author') ?: null;
                $ddc = $get('ddc') ?: null;
                $bid = DB::insert('books', [
                    'tenant_id' => $tid, 'title' => $title, 'author' => $author, 'publisher' => $get('publisher') ?: null,
                    'year' => substr($get('year'), 0, 10) ?: null, 'isbn' => $isbn, 'language' => 'Indonesia',
                    'category' => $get('category') ?: ($type === 'paket' ? 'Buku paket' : 'Nonfiksi'), 'ddc' => $ddc,
                    'call_number' => call_number($ddc, $author, $title) ?: null, 'shelf' => strtoupper($get('shelf')) ?: null,
                    'collection_type' => $type, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $added++;
            }
            $copies += count(copies_create($bid, $n, $get('source') ?: null, today(), 'baik'));
        }
    });
    $_SESSION['_import'] = ['ok' => "$added judul baru, $merged judul sudah ada (ditambah eksemplarnya), $copies eksemplar dibuat.", 'skipped' => $skipped];
    redirect(turl('/admin/buku/impor'));
}

function books_template(): never
{
    Auth::requireStaff('buku');
    xlsx_download('templat-impor-buku.xlsx', ['Buku' => [
        ['Judul', 'Pengarang', 'Penerbit', 'Tahun', 'ISBN', 'Kategori', 'DDC', 'Rak', 'Jenis', 'Jumlah eksemplar', 'Sumber'],
        ['Contoh: Laskar Pelangi', 'Andrea Hirata', 'Bentang Pustaka', '2005', '', 'Fiksi', '899.221 3', 'F-02', 'Dapat dipinjam', 3, 'Dana BOS'],
        ['Contoh: Matematika Kelas X', 'Kemendikbudristek', '', '2022', '', 'Buku paket', '510', 'P-01', 'Buku paket', 36, 'Dana BOS'],
    ]]);
}

function books_export(): never
{
    Auth::requireStaff('buku');
    $tid = Tenant::id();
    $books = DB::q('SELECT b.*, ' . COPY_AGG . ' FROM books b LEFT JOIN copies c ON c.book_id = b.id WHERE b.tenant_id = ? GROUP BY b.id ORDER BY b.title', [$tid]);
    $sheet1 = [['Judul', 'Pengarang', 'Penerbit', 'Tahun', 'ISBN', 'Kategori', 'DDC', 'No. panggil', 'Rak', 'Jenis', 'Eksemplar', 'Tersedia', 'Dipinjam', 'Rusak', 'Hilang']];
    foreach ($books as $b) $sheet1[] = [$b['title'], $b['author'], $b['publisher'], $b['year'], $b['isbn'], $b['category'], $b['ddc'], $b['call_number'], $b['shelf'],
        label('collection', $b['collection_type']), (int)$b['total'], (int)$b['avail'], (int)$b['borrowed'], (int)$b['damaged'], (int)$b['lost']];
    $copies = DB::q('SELECT c.*, b.title FROM copies c JOIN books b ON b.id = c.book_id WHERE c.tenant_id = ? ORDER BY c.code', [$tid]);
    $sheet2 = [['Kode', 'Judul', 'Sumber', 'Tanggal masuk', 'Kondisi', 'Status']];
    foreach ($copies as $c) $sheet2[] = [$c['code'], $c['title'], $c['source'], $c['acquired_at'], label('kondisi', $c['kondisi']), ucfirst($c['status'])];
    xlsx_download('katalog-buku-' . App::$tenant['slug'] . '-' . today() . '.xlsx', ['Judul' => $sheet1, 'Eksemplar' => $sheet2]);
}

function books_labels(): string
{
    Auth::requireStaff('buku');
    $tid = Tenant::id();
    $ids = array_values(array_filter(array_map('intval', explode(',', (string)g('eksemplar')))));
    if ($ids) {
        $copies = DB::q('SELECT c.code, b.title, b.call_number, b.ddc, b.author FROM copies c JOIN books b ON b.id = c.book_id WHERE c.tenant_id = ? AND c.id IN ' . DB::in($ids) . ' ORDER BY c.code', array_merge([$tid], $ids));
    } elseif ($bookIds = array_values(array_filter(array_map('intval', explode(',', (string)g('buku')))))) {
        $copies = DB::q('SELECT c.code, b.title, b.call_number, b.ddc, b.author FROM copies c JOIN books b ON b.id = c.book_id WHERE c.tenant_id = ? AND b.id IN ' . DB::in($bookIds) . ' ORDER BY b.title, c.code', array_merge([$tid], $bookIds));
    } elseif (g('baru')) {
        $copies = DB::q('SELECT c.code, b.title, b.call_number, b.ddc, b.author FROM copies c JOIN books b ON b.id = c.book_id WHERE c.tenant_id = ? AND c.created_at >= ? ORDER BY c.code', [$tid, date('Y-m-d H:i:s', strtotime('-' . max(1, (int)g('baru')) . ' days'))]);
    } else {
        $copies = [];
    }
    return view('admin/labels', ['copies' => $copies, 'title' => 'Cetak label buku', 'back' => (string)g('kembali') ?: turl('/admin/buku'),
        'scripts' => ['vendor/JsBarcode.all.min.js']], 'bare');
}
