<?php $active = $m['status'] === 'active'; ?>
<div style="color:#fff;display:flex;flex-direction:column;flex-grow:1">
  <div class="row-between" style="height:60px;padding:0 12px">
    <a href="<?= e(turl()) ?>" class="btn-icon" style="color:#fff;display:flex;align-items:center;justify-content:center;width:44px;height:44px" aria-label="Beranda perpustakaan"><?= icon('left', 22) ?></a>
    <span style="font-size:16px;font-weight:700">Kartu member</span>
    <a href="<?= e(turl('/tata-tertib')) ?>" class="btn-icon" style="color:#fff;display:flex;align-items:center;justify-content:center;width:44px;height:44px" aria-label="Tata tertib"><?= icon('help', 22) ?></a>
  </div>
  <div style="padding:0 20px"><?= flashes_html() ?></div>
  <div class="member-card" style="margin:8px 20px 0">
    <div class="member-card-head">
      <span class="brand"><?= brand_mark(32, 'mark-light') ?><span class="brand-text"><span class="eyebrow" style="color:var(--gold-700)">Perpustakaan</span><strong style="font-size:13px"><?= e(Tenant::shortName()) ?></strong></span></span>
      <?= chip(label('member_status', $m['status']), LABELS['member_status_tone'][$m['status']] ?? 'mute') ?>
    </div>
    <?php if ($active): ?>
      <div style="padding:24px 22px 8px;display:flex;flex-direction:column;align-items:center;gap:14px">
        <div class="qr-box"><div id="memberQr" data-qr="<?= e(member_qr_text($m)) ?>" data-qr-label="Kode QR member <?= e($m['name']) ?>" style="width:225px;height:225px"></div></div>
        <span class="mono" style="font-size:15px;font-weight:700;letter-spacing:.08em"><?= e($m['member_no']) ?></span>
      </div>
    <?php else: ?>
      <div style="padding:32px 22px;display:flex;flex-direction:column;align-items:center;gap:12px;text-align:center">
        <span class="stat-icon" style="width:72px;height:72px;border-radius:999px;background:var(--gold-100);color:var(--gold-700)"><?= icon('clock', 32) ?></span>
        <strong>Kartu QR belum aktif</strong>
        <span class="muted small">Pendaftaranmu sedang ditinjau petugas. Kartu QR muncul di sini setelah disetujui.</span>
      </div>
    <?php endif; ?>
    <div style="margin:10px 22px 0;border-top:1.5px dashed var(--line)"></div>
    <div style="padding:18px 22px 24px;display:flex;flex-direction:column;gap:6px">
      <span class="serif" style="font-size:28px;line-height:1.1"><?= e($m['name']) ?></span>
      <span class="muted small"><?= e(member_meta($m)) ?><?= member_id_label($m) ? ' · ' . e(member_id_label($m)) : '' ?></span>
      <?php if ($active): ?><span class="small" style="font-weight:600;color:var(--gold-700)"><?= $m['valid_until'] ? 'Berlaku hingga ' . e(fmt_date($m['valid_until'], 'month')) : 'Berlaku selama masih aktif di sekolah' ?></span><?php endif; ?>
    </div>
  </div>
  <?php if ($active): ?>
    <div class="row" style="margin:16px 28px 0;color:var(--soft-on-navy);font-size:13px;line-height:1.45;flex-wrap:nowrap">
      <span style="color:var(--gold-500);flex-shrink:0"><?= icon('sun', 20) ?></span>
      <span>Tunjukkan QR ini ke kamera meja depan. Naikkan kecerahan layar agar cepat terbaca.</span>
    </div>
    <div style="margin:18px 20px 24px;display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <button class="btn btn-gold" type="button" id="saveQr" data-name="<?= e($m['member_no']) ?>"><?= icon('download') ?>Simpan QR</button>
      <a class="btn" style="background:transparent;border-color:#3A5C8E" href="<?= e(turl('/kartu/cetak')) ?>"><?= icon('printer') ?>Cetak kartu</a>
    </div>
  <?php else: ?>
    <div style="margin:18px 20px 24px"><a class="btn btn-block" style="background:transparent;border-color:#3A5C8E" href="<?= e(turl()) ?>">Kembali</a></div>
  <?php endif; ?>
  <div style="flex-grow:1"></div>
</div>
