<?php
declare(strict_types=1);

/** Square brand mark: the school logo when uploaded, otherwise the book icon. */
function brand_mark(int $size = 40, string $class = ''): string
{
    $logo = App::$tenant ? (string)(Tenant::get('logo') ?? '') : '';
    $style = "width:{$size}px;height:{$size}px;border-radius:" . round($size * 0.28) . 'px';
    if ($logo) return '<span class="mark ' . e($class) . '" style="' . $style . '"><img src="' . e(media_url($logo)) . '" alt=""></span>';
    return '<span class="mark ' . e($class) . '" style="' . $style . '">' . icon('logo', (int)round($size * 0.55)) . '</span>';
}

function brand_block(string $eyebrow = 'Perpustakaan', ?string $name = null, int $size = 40): string
{
    $name ??= Tenant::shortName();
    return '<span class="brand">' . brand_mark($size) . '<span class="brand-text"><span class="eyebrow">' . e($eyebrow) . '</span><strong>' . e($name) . '</strong></span></span>';
}

function flashes_html(): string
{
    $out = '';
    foreach (take_flashes() as [$type, $msg]) {
        $tone = ['ok' => 'ok', 'error' => 'bad', 'warn' => 'warn', 'info' => 'info'][$type] ?? 'info';
        $ic = ['ok' => 'check', 'bad' => 'alert', 'warn' => 'alert', 'info' => 'info'][$tone];
        $out .= '<div class="alert alert-' . $tone . '" role="status">' . icon($ic, 18) . '<span>' . e($msg) . '</span></div>';
    }
    return $out;
}

function pager_html(array $pg, string $unit = 'data'): string
{
    $html = '<div class="card-foot"><span>Menampilkan ' . num($pg['from']) . '–' . num($pg['to']) . ' dari ' . num($pg['total']) . ' ' . e($unit) . '</span>';
    if ($pg['pages'] > 1) {
        $html .= '<nav class="pager" aria-label="Halaman">';
        if ($pg['page'] > 1) $html .= '<a href="' . e(qs(['hal' => $pg['page'] - 1])) . '" aria-label="Sebelumnya">‹</a>';
        foreach ($pg['list'] as $i) {
            if ($i === '…') $html .= '<span class="gap">…</span>';
            elseif ($i === $pg['page']) $html .= '<span class="is-active" aria-current="page">' . $i . '</span>';
            else $html .= '<a href="' . e(qs(['hal' => $i])) . '">' . $i . '</a>';
        }
        if ($pg['page'] < $pg['pages']) $html .= '<a href="' . e(qs(['hal' => $pg['page'] + 1])) . '" aria-label="Berikutnya">›</a>';
        $html .= '</nav>';
    }
    return $html . '</div>';
}

function cover_html(array $b, int $w = 36, int $h = 50): string
{
    if (!empty($b['cover'])) {
        return '<span class="cover has-img" style="width:' . $w . 'px;height:' . $h . 'px"><img src="' . e(media_url($b['cover'])) . '" alt="" loading="lazy"></span>';
    }
    [$bg] = cover_color((string)$b['title']);
    return '<span class="cover" style="width:' . $w . 'px;height:' . $h . 'px;background:' . $bg . '"></span>';
}

function avatar_html(?string $name, ?string $photo = null, string $class = ''): string
{
    if ($photo) return '<span class="avatar ' . e($class) . '"><img src="' . e(media_url($photo)) . '" alt="" loading="lazy"></span>';
    return '<span class="avatar ' . e($class) . '">' . e(initials($name)) . '</span>';
}

function spines_html(int $n = 14, int $height = 44): string
{
    $hs = [26, 34, 22, 40, 30, 18, 36, 28, 42, 24, 32, 20, 38, 27, 31, 23];
    $out = '<span class="spines" aria-hidden="true" style="height:' . $height . 'px">';
    for ($i = 0; $i < $n; $i++) {
        $c = $i === 8 ? '#D4A63A' : ($i % 2 ? '#1D4275' : '#24569A');
        $out .= '<i style="width:' . (8 + ($i % 3) * 3) . 'px;height:' . round($hs[$i % 16] / 44 * $height) . 'px;background:' . $c . '"></i>';
    }
    return $out . '</span>';
}

function select_options(array $options, mixed $selected, bool $assoc = false): string
{
    $html = '';
    foreach ($options as $k => $v) {
        $val = $assoc ? (string)$k : (string)$v;
        $html .= '<option value="' . e($val) . '"' . ((string)$selected === $val ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    return $html;
}

function nav_badges(): array
{
    $tid = Tenant::id();
    return [
        'member' => (int)DB::val("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND status = 'pending'", [$tid]),
        'sirkulasi' => (int)DB::val('SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND returned_at IS NULL AND due_date < ?', [$tid, today()]),
    ];
}

function display_url(bool $absolute = false): string
{
    $u = turl('/display') . '?key=' . rawurlencode((string)(Tenant::get('display')['key'] ?? ''));
    return $absolute ? abs_url($u) : $u;
}
