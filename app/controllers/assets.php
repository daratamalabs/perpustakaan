<?php
declare(strict_types=1);

const ASSET_KONDISI = ['baik', 'rusak_ringan', 'rusak_berat', 'perbaikan', 'dihapus'];

function asset_find(int $id): array
{
    return DB::one('SELECT * FROM assets WHERE id = ? AND tenant_id = ?', [$id, Tenant::id()]) ?? abort(404, 'Aset tidak ditemukan.');
}

/** Next free code like INV-2026-014. */
function asset_next_code(): string
{
    $y = date('Y');
    $last = DB::val("SELECT MAX(CAST(SUBSTRING_INDEX(code, '-', -1) AS UNSIGNED)) FROM assets WHERE tenant_id = ? AND code LIKE ?", [Tenant::id(), "INV-$y-%"]);
    return sprintf('INV-%s-%03d', $y, (int)$last + 1);
}

function assets_index(): string
{
    Auth::requireStaff('aset');
    $tid = Tenant::id();
    $s = Tenant::settings();
    $q = (string)g('q');
    $kondisi = (string)g('kondisi');
    $where = ['tenant_id = ?'];
    $params = [$tid];
    if ($q !== '') { $where[] = '(name LIKE ? OR code LIKE ? OR spec LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
    if (g('kategori') !== '') { $where[] = 'category = ?'; $params[] = g('kategori'); }
    if (g('lokasi') !== '') { $where[] = 'location = ?'; $params[] = g('lokasi'); }
    if (in_array($kondisi, ASSET_KONDISI, true)) { $where[] = 'kondisi = ?'; $params[] = $kondisi; }
    else { $where[] = "kondisi <> 'dihapus'"; }
    $w = implode(' AND ', $where);
    $total = (int)DB::val("SELECT COUNT(*) FROM assets WHERE $w", $params);
    $pg = paginate($total, g('hal', 1), 25);
    $rows = DB::q("SELECT * FROM assets WHERE $w ORDER BY created_at DESC, id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);

    $sum = ['total' => 0, 'value' => 0];
    foreach (ASSET_KONDISI as $k) $sum[$k] = 0;
    foreach (DB::q('SELECT kondisi, COUNT(*) n, COALESCE(SUM(value),0) v FROM assets WHERE tenant_id = ? GROUP BY kondisi', [$tid]) as $r) {
        $sum[$r['kondisi']] = (int)$r['n'];
        if ($r['kondisi'] !== 'dihapus') { $sum['total'] += (int)$r['n']; $sum['value'] += (int)$r['v']; }
    }

    $sel = null;
    $logs = [];
    if (g('id') !== '') {
        $sel = DB::one('SELECT * FROM assets WHERE id = ? AND tenant_id = ?', [(int)g('id'), $tid]);
    } elseif ($rows && !g('q')) {
        $sel = $rows[0];
    }
    if ($sel) {
        $logs = DB::q('SELECT l.*, u.name staff FROM asset_logs l LEFT JOIN users u ON u.id = l.staff_id WHERE l.asset_id = ? ORDER BY l.date DESC, l.id DESC LIMIT 20', [$sel['id']]);
    }
    $locations = array_values(array_unique(array_merge($s['locations'], array_column(DB::q("SELECT DISTINCT location FROM assets WHERE tenant_id = ? AND location IS NOT NULL AND location <> ''", [$tid]), 'location'))));
    return view('admin/assets', ['rows' => $rows, 'pg' => $pg, 'sum' => $sum, 'sel' => $sel, 'logs' => $logs, 's' => $s, 'locations' => $locations,
        'title' => 'Aset & inventaris', 'active' => 'aset', 'scripts' => ['vendor/qrcode.js']], 'admin');
}

function assets_form(string $id = ''): string
{
    Auth::requireStaff('aset');
    $a = $id !== '' ? asset_find((int)$id) : null;
    $s = Tenant::settings();
    $tid = Tenant::id();
    $funds = array_values(array_unique(array_merge(['Dana BOS', 'APBD', 'Komite sekolah', 'Hibah alumni', 'Hibah'], array_column(DB::q("SELECT DISTINCT fund_source FROM assets WHERE tenant_id = ? AND fund_source IS NOT NULL AND fund_source <> ''", [$tid]), 'fund_source'))));
    $locations = array_values(array_unique(array_merge($s['locations'], array_column(DB::q("SELECT DISTINCT location FROM assets WHERE tenant_id = ? AND location IS NOT NULL AND location <> ''", [$tid]), 'location'))));
    return view('admin/asset_form', ['a' => $a, 's' => $s, 'funds' => $funds, 'locations' => $locations, 'nextCode' => $a ? $a['code'] : asset_next_code(),
        'title' => $a ? 'Ubah aset' : 'Tambah aset', 'active' => 'aset'], 'admin');
}

function assets_save(string $id = ''): never
{
    $u = Auth::requireStaff('aset');
    $tid = Tenant::id();
    $a = $id !== '' ? asset_find((int)$id) : null;
    $back = $a ? turl('/admin/aset/' . $a['id'] . '/ubah') : turl('/admin/aset/baru');
    $code = strtoupper(preg_replace('/\s+/', '', (string)p('code'))) ?: asset_next_code();
    $name = trim((string)p('name'));
    if ($name === '') { flash('error', 'Nama barang wajib diisi.'); keep_old($_POST); redirect($back); }
    if (DB::val('SELECT id FROM assets WHERE tenant_id = ? AND code = ? AND id <> ?', [$tid, $code, $a['id'] ?? 0])) {
        flash('error', "Kode $code sudah dipakai aset lain."); keep_old($_POST); redirect($back);
    }
    $kondisi = in_array(p('kondisi'), ASSET_KONDISI, true) ? p('kondisi') : 'baik';
    $value = preg_replace('/[^0-9]/', '', (string)p('value'));
    $d = [
        'code' => $code,
        'name' => $name,
        'category' => (string)p('category') ?: 'Lainnya',
        'location' => (string)p('location') ?: null,
        'spec' => (string)p('spec') ?: null,
        'acquired_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)p('acquired_at')) ? p('acquired_at') : null,
        'value' => $value === '' ? null : (int)$value,
        'fund_source' => (string)p('fund_source') ?: null,
        'kondisi' => $kondisi,
        'notes' => (string)p('notes') ?: null,
        'updated_at' => now(),
    ];
    $photo = save_upload('photo', 'assets', 4_000_000);
    if ($photo) { if ($a) delete_upload($a['photo']); $d['photo'] = $photo; }
    elseif ($a && p('remove_photo')) { delete_upload($a['photo']); $d['photo'] = null; }

    if ($a) {
        if ($a['kondisi'] !== $kondisi) {
            DB::insert('asset_logs', ['tenant_id' => $tid, 'asset_id' => $a['id'], 'date' => today(), 'text' => 'Kondisi diubah: ' . label('kondisi', $a['kondisi']) . ' → ' . label('kondisi', $kondisi), 'staff_id' => $u['id'], 'created_at' => now()]);
        }
        DB::update('assets', $d, 'id = ?', [$a['id']]);
        flash('ok', 'Data aset disimpan.');
        redirect(turl('/admin/aset?id=' . $a['id']));
    }
    $newId = DB::insert('assets', $d + ['tenant_id' => $tid, 'created_at' => now()]);
    DB::insert('asset_logs', ['tenant_id' => $tid, 'asset_id' => $newId, 'date' => $d['acquired_at'] ?: today(), 'text' => 'Aset dicatat' . ($d['fund_source'] ? ' · ' . $d['fund_source'] : ''), 'staff_id' => $u['id'], 'created_at' => now()]);
    flash('ok', 'Aset ' . $code . ' ditambahkan. Cetak label QR lalu tempel di barangnya.');
    redirect(turl('/admin/aset?id=' . $newId));
}

function assets_log(string $id): never
{
    $u = Auth::requireStaff('aset');
    $a = asset_find((int)$id);
    $text = trim((string)p('text'));
    $kondisi = (string)p('kondisi');
    if ($text === '' && ($kondisi === '' || $kondisi === $a['kondisi'])) { flash('error', 'Tulis catatan perawatan dulu.'); redirect(turl('/admin/aset?id=' . $a['id'])); }
    $cost = preg_replace('/[^0-9]/', '', (string)p('cost'));
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)p('date')) ? p('date') : today();
    if (in_array($kondisi, ASSET_KONDISI, true) && $kondisi !== $a['kondisi']) {
        $text = trim($text . ($text ? ' · ' : '') . 'Kondisi: ' . label('kondisi', $a['kondisi']) . ' → ' . label('kondisi', $kondisi));
        DB::update('assets', ['kondisi' => $kondisi, 'updated_at' => now()], 'id = ?', [$a['id']]);
    }
    DB::insert('asset_logs', ['tenant_id' => Tenant::id(), 'asset_id' => $a['id'], 'date' => $date, 'text' => mb_substr($text, 0, 255),
        'cost' => $cost === '' ? null : (int)$cost, 'staff_id' => $u['id'], 'created_at' => now()]);
    flash('ok', 'Catatan perawatan disimpan.');
    redirect(turl('/admin/aset?id=' . $a['id']));
}

function assets_delete(string $id): never
{
    Auth::requireStaff('aset');
    $a = asset_find((int)$id);
    DB::run('DELETE FROM assets WHERE id = ?', [$a['id']]);
    delete_upload($a['photo']);
    flash('ok', 'Aset ' . $a['code'] . ' dihapus dari daftar.');
    redirect(turl('/admin/aset'));
}

function assets_labels(): string
{
    Auth::requireStaff('aset');
    $tid = Tenant::id();
    $ids = array_values(array_filter(array_map('intval', explode(',', (string)g('ids')))));
    $rows = $ids
        ? DB::q('SELECT * FROM assets WHERE tenant_id = ? AND id IN ' . DB::in($ids) . ' ORDER BY code', array_merge([$tid], $ids))
        : DB::q("SELECT * FROM assets WHERE tenant_id = ? AND kondisi <> 'dihapus' ORDER BY code", [$tid]);
    return view('admin/asset_labels', ['rows' => $rows, 'title' => 'Label QR aset', 'back' => turl('/admin/aset'), 'scripts' => ['vendor/qrcode.js']], 'bare');
}

function assets_export(): never
{
    Auth::requireStaff('aset');
    $tid = Tenant::id();
    $rows = DB::q('SELECT * FROM assets WHERE tenant_id = ? ORDER BY code', [$tid]);
    $sheet = [['Kode', 'Nama barang', 'Kategori', 'Lokasi', 'Spesifikasi', 'Tanggal perolehan', 'Nilai (Rp)', 'Sumber dana', 'Kondisi', 'Catatan']];
    foreach ($rows as $a) $sheet[] = [$a['code'], $a['name'], $a['category'], $a['location'], $a['spec'], $a['acquired_at'], $a['value'] === null ? '' : (int)$a['value'], $a['fund_source'], label('kondisi', $a['kondisi']), $a['notes']];
    $logs = DB::q('SELECT l.*, a.code, a.name FROM asset_logs l JOIN assets a ON a.id = l.asset_id WHERE l.tenant_id = ? ORDER BY l.date DESC, l.id DESC', [$tid]);
    $sheet2 = [['Tanggal', 'Kode', 'Nama barang', 'Catatan', 'Biaya (Rp)']];
    foreach ($logs as $l) $sheet2[] = [$l['date'], $l['code'], $l['name'], $l['text'], $l['cost'] === null ? '' : (int)$l['cost']];
    xlsx_download('aset-' . App::$tenant['slug'] . '-' . today() . '.xlsx', ['Aset' => $sheet, 'Riwayat perawatan' => $sheet2]);
}
