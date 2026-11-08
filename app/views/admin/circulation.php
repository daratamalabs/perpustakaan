<?php
$tabs = [
    '' => ['Semua dipinjam', $counts['semua']],
    'minggu' => ['Jatuh tempo 7 hari', $counts['minggu']],
    'terlambat' => ['Terlambat', $counts['terlambat']],
    'denda' => ['Denda belum lunas', $counts['denda']],
];
$isFine = $filter === 'denda';
?>
<div class="page-head">
  <div><span class="crumb">Koleksi</span><h1>Sirkulasi</h1></div>
  <div class="page-actions">
    <?php if ($counts['terlambat'] > 0): ?>
      <form method="post" action="<?= e(turl('/admin/sirkulasi/ingatkan-semua')) ?>" data-confirm="Kirim pengingat WhatsApp ke semua peminjam yang terlambat?">
        <?= csrf_field() ?><input type="hidden" name="filter" value="<?= e($filter) ?>">
        <button class="btn btn-secondary" type="submit" <?= wa_enabled() ? '' : 'disabled title="Atur gateway WhatsApp di Pengaturan"' ?>><?= icon('chat') ?>Ingatkan semua yang terlambat</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="split">
  <section class="card card-pad stack side" id="circ" style="flex:1 1 380px"
           data-member-url="<?= e(turl('/admin/sirkulasi/member')) ?>"
           data-copy-url="<?= e(turl('/admin/sirkulasi/eksemplar')) ?>"
           data-borrow-url="<?= e(turl('/admin/sirkulasi/pinjam')) ?>"
           data-return-url="<?= e(turl('/admin/sirkulasi/kembali')) ?>"
           data-extend-url="<?= e(turl('/admin/sirkulasi/__ID__/perpanjang')) ?>">
    <div class="segmented" role="tablist" aria-label="Jenis transaksi">
      <button type="button" class="is-active" data-mode="pinjam" role="tab" aria-selected="true">Pinjam</button>
      <button type="button" data-mode="kembali" role="tab" aria-selected="false">Kembalikan</button>
      <button type="button" data-mode="perpanjang" role="tab" aria-selected="false">Perpanjang</button>
    </div>

    <div class="stack" data-panel="pinjam">
      <div class="stack-sm">
        <span class="eyebrow">Langkah 1 · Member</span>
        <div class="row" style="flex-wrap:nowrap;gap:8px">
          <label class="input-icon grow"><?= icon('qr') ?><input class="input" id="memberInput" autocomplete="off" placeholder="Pindai QR member, NISN, atau nama" aria-label="Pindai QR member, NISN, atau nama"></label>
          <button class="btn btn-secondary" type="button" data-camera="memberInput" aria-label="Pindai dengan kamera" title="Pindai dengan kamera" style="width:44px;padding:0"><?= icon('camera') ?></button>
        </div>
        <div id="memberBox"></div>
      </div>
      <div class="stack-sm">
        <span class="eyebrow">Langkah 2 · Buku</span>
        <div class="row" style="flex-wrap:nowrap;gap:8px">
          <label class="input-icon grow"><?= icon('scan') ?><input class="input" id="copyInput" autocomplete="off" placeholder="Pindai barcode eksemplar" aria-label="Pindai barcode eksemplar" disabled></label>
          <button class="btn btn-secondary" type="button" data-camera="copyInput" aria-label="Pindai dengan kamera" title="Pindai dengan kamera" style="width:44px;padding:0" disabled><?= icon('camera') ?></button>
        </div>
        <div id="copyList" class="stack-sm"></div>
      </div>
      <button class="btn btn-lg btn-block" type="button" id="borrowBtn" disabled>Proses peminjaman</button>
    </div>

    <div class="stack" data-panel="kembali" hidden>
      <div class="stack-sm">
        <span class="eyebrow">Pindai buku yang dikembalikan</span>
        <div class="row" style="flex-wrap:nowrap;gap:8px">
          <label class="input-icon grow"><?= icon('scan') ?><input class="input" id="returnInput" autocomplete="off" placeholder="Pindai barcode eksemplar" aria-label="Pindai barcode eksemplar yang dikembalikan"></label>
          <button class="btn btn-secondary" type="button" data-camera="returnInput" aria-label="Pindai dengan kamera" title="Pindai dengan kamera" style="width:44px;padding:0"><?= icon('camera') ?></button>
        </div>
      </div>
      <div id="returnBox"></div>
    </div>

    <div class="stack" data-panel="perpanjang" hidden>
      <div class="stack-sm">
        <span class="eyebrow">Pindai buku yang diperpanjang</span>
        <div class="row" style="flex-wrap:nowrap;gap:8px">
          <label class="input-icon grow"><?= icon('scan') ?><input class="input" id="extendInput" autocomplete="off" placeholder="Pindai barcode eksemplar" aria-label="Pindai barcode eksemplar yang diperpanjang"></label>
          <button class="btn btn-secondary" type="button" data-camera="extendInput" aria-label="Pindai dengan kamera" title="Pindai dengan kamera" style="width:44px;padding:0"><?= icon('camera') ?></button>
        </div>
      </div>
      <div id="extendBox"></div>
    </div>
  </section>

  <section class="card wide" style="flex:2 1 600px;overflow:hidden">
    <div class="card-head" style="padding-bottom:4px"><h2><?= $isFine ? 'Denda belum lunas' : 'Peminjaman aktif' ?></h2></div>
    <div class="pills" style="padding:12px 20px 0">
      <?php foreach ($tabs as $key => [$lbl, $n]): ?>
        <a class="pill <?= $filter === $key ? 'is-active' : '' ?>" href="<?= e(qs(['filter' => $key ?: null, 'hal' => null, 'q' => null])) ?>"><?= e($lbl) ?><small><?= num($n) ?></small></a>
      <?php endforeach; ?>
    </div>
    <form class="card-toolbar" method="get">
      <?php if ($filter !== ''): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
      <label class="input-icon"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e(g('q')) ?>" placeholder="Cari peminjam, judul, kode, atau kelas" aria-label="Cari peminjaman"></label>
      <button class="btn btn-secondary" type="submit">Cari</button>
    </form>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Peminjam</th><th>Buku</th><th>Dipinjam</th><th><?= $isFine ? 'Dikembalikan' : 'Kembali' ?></th><th><?= $isFine ? 'Denda' : 'Status' ?></th><th class="actions"></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="6"><div class="empty"><?= icon($isFine ? 'check' : 'sirk', 34) ?><?= $isFine ? 'Tidak ada denda yang belum dilunasi.' : (g('q') ? 'Tidak ada peminjaman yang cocok.' : 'Belum ada buku yang dipinjam.') ?></div></td></tr><?php endif; ?>
        <?php foreach ($rows as $l):
          [$st, $tone] = loan_status($l);
          $who = $l['type'] === 'siswa' ? ('Siswa · ' . ($l['kelas'] ?: '—')) : (label('member_type', $l['type']) . ($l['subject'] ? ' · ' . $l['subject'] : ''));
          $late = !$isFine && $l['due_date'] < today(); ?>
          <tr class="<?= $late ? 'is-danger' : '' ?>">
            <td><strong><?= e($l['name']) ?></strong><span class="sub"><?= e($who) ?></span></td>
            <td><?= e($l['title']) ?><span class="sub mono" style="font-size:12px"><?= e($l['code']) ?></span></td>
            <td class="muted nowrap"><?= e(fmt_date($l['loaned_at'], 'short')) ?></td>
            <td class="nowrap" style="font-weight:700;color:<?= $late ? 'var(--bad)' : 'inherit' ?>"><?= e($isFine ? fmt_date($l['returned_at'], 'short') : fmt_date($l['due_date'], 'short')) ?><?= !$isFine && $l['extended'] ? '<span class="sub" style="font-weight:500">diperpanjang</span>' : '' ?></td>
            <td><?= $isFine ? chip(rupiah($l['fine_amount']), 'bad') : chip($st, $tone) ?></td>
            <td class="actions">
              <?php if ($isFine): ?>
                <form method="post" action="<?= e(turl('/admin/sirkulasi/' . $l['id'] . '/lunas')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="filter" value="denda"><button class="btn btn-sm btn-secondary" type="submit"><?= icon('check', 15) ?>Tandai lunas</button></form>
              <?php else: ?>
                <button class="btn btn-sm btn-secondary" type="button" data-return-code="<?= e($l['code']) ?>">Kembalikan</button>
                <?php if (!$late && (int)$l['extended'] < (int)$loan['extensions'] && $l['collection_type'] !== 'paket'): ?>
                  <form method="post" action="<?= e(turl('/admin/sirkulasi/' . $l['id'] . '/perpanjang')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><button class="btn btn-sm btn-ghost" type="submit">Perpanjang</button></form>
                <?php endif; ?>
                <?php if ($l['phone']): ?>
                  <form method="post" action="<?= e(turl('/admin/sirkulasi/' . $l['id'] . '/ingatkan')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><button class="btn-icon" type="submit" aria-label="Kirim pengingat WhatsApp ke <?= e($l['name']) ?>" title="Kirim pengingat WhatsApp" style="display:inline-flex;align-items:center;justify-content:center;vertical-align:middle"><?= icon('chat', 17) ?></button></form>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pager_html($pg, $isFine ? 'denda' : 'peminjaman') ?>
    <div class="card-foot" style="background:var(--paper-2);justify-content:flex-start">
      <?= icon('info', 16) ?>
      <span>Siswa maks. <?= (int)$loan['max_siswa'] ?> buku selama <?= (int)$loan['days_siswa'] ?> hari, guru &amp; tendik maks. <?= (int)$loan['max_guru'] ?> buku selama <?= (int)$loan['days_guru'] ?> hari, perpanjangan <?= (int)$loan['extensions'] ?> kali, denda <?= e(rupiah($loan['fine_per_day'])) ?> per hari. Buku paket tidak dihitung dalam batas pinjam.</span>
      <?php if (Auth::can('pengaturan')): ?><a href="<?= e(turl('/admin/pengaturan#aturan')) ?>" style="font-weight:700;text-decoration:none">Ubah aturan</a><?php endif; ?>
    </div>
  </section>
</div>

<div class="modal" id="camModal" hidden role="dialog" aria-modal="true" aria-labelledby="camTitle">
  <div class="modal-box" style="max-width:440px;align-items:center">
    <div class="row-between" style="width:100%"><h2 id="camTitle" style="font-size:18px">Pindai dengan kamera</h2><button class="btn-icon" type="button" id="camClose" aria-label="Tutup" style="display:inline-flex;align-items:center;justify-content:center"><?= icon('x') ?></button></div>
    <div class="viewfinder" id="camView" style="width:100%">
      <video playsinline muted></video>
      <span class="corner c1"></span><span class="corner c2"></span><span class="corner c3"></span><span class="corner c4"></span>
      <span class="cam-status"><i></i><span id="camStatus">Menyalakan kamera…</span></span>
    </div>
    <p class="muted small center">Arahkan QR member atau barcode buku ke kamera. Barcode garis paling mudah dibaca dengan pemindai USB.</p>
  </div>
</div>
