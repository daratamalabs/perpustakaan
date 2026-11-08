<?php
declare(strict_types=1);

/**
 * Minimal XLSX writer/reader (no Composer needed). Requires the PHP zip extension.
 */

function xlsx_col(int $i): string
{
    $s = '';
    for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
    return $s;
}

function xlsx_xml(string $s): string { return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }

/**
 * Send an .xlsx download. @param array<string,array<int,array>> $sheets name => rows (first row = header)
 */
function xlsx_download(string $filename, array $sheets): never
{
    if (!class_exists('ZipArchive')) {
        // Fallback: CSV of the first sheet.
        $rows = reset($sheets) ?: [];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/\.xlsx$/', '.csv', $filename) . '"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        foreach ($rows as $r) fputcsv($out, $r, ';');
        exit;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $names = array_keys($sheets);
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $wbSheets = ''; $wbRels = '';
    foreach ($names as $i => $n) {
        $k = $i + 1;
        $ct .= '<Override PartName="/xl/worksheets/sheet' . $k . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $wbSheets .= '<sheet name="' . xlsx_xml(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $n), 0, 31)) . '" sheetId="' . $k . '" r:id="rId' . $k . '"/>';
        $wbRels .= '<Relationship Id="rId' . $k . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $k . '.xml"/>';
    }
    $ct .= '</Types>';
    $wbRels .= '<Relationship Id="rId' . (count($names) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $zip->addFromString('[Content_Types].xml', $ct);
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wbSheets . '</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wbRels . '</Relationships>');
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF10284A"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
        . '</styleSheet>');
    foreach ($names as $i => $n) {
        $rows = $sheets[$n];
        $widths = [];
        $data = '';
        foreach ($rows as $ri => $row) {
            $data .= '<row r="' . ($ri + 1) . '">';
            foreach (array_values($row) as $ci => $v) {
                $ref = xlsx_col($ci) . ($ri + 1);
                $style = $ri === 0 ? ' s="1"' : '';
                $len = mb_strlen((string)$v);
                $widths[$ci] = min(60, max($widths[$ci] ?? 8, $len + 2));
                if (is_int($v) || is_float($v)) $data .= '<c r="' . $ref . '"' . $style . '><v>' . $v . '</v></c>';
                elseif ($v === null || $v === '') $data .= '<c r="' . $ref . '"' . $style . '/>';
                else $data .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . xlsx_xml((string)$v) . '</t></is></c>';
            }
            $data .= '</row>';
        }
        $cols = '';
        foreach ($widths as $ci => $w) $cols .= '<col min="' . ($ci + 1) . '" max="' . ($ci + 1) . '" width="' . $w . '" customWidth="1"/>';
        $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . ($cols ? '<cols>' . $cols . '</cols>' : '') . '<sheetData>' . $data . '</sheetData></worksheet>');
    }
    $zip->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

/** Read the first sheet of an .xlsx or a .csv into rows of strings. */
function sheet_read(string $path, string $originalName): array
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === 'csv' || $ext === 'txt') {
        $text = (string)file_get_contents($path);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $first = strtok($text, "\n") ?: '';
        $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $rows = [];
        $h = fopen('php://memory', 'r+');
        fwrite($h, $text);
        rewind($h);
        while (($r = fgetcsv($h, 0, $delim)) !== false) $rows[] = array_map(fn($v) => trim((string)$v), $r);
        return $rows;
    }
    if ($ext !== 'xlsx') throw new HttpException('Format berkas harus .xlsx atau .csv.', 400);
    if (!class_exists('ZipArchive')) throw new HttpException('Server tidak mendukung .xlsx. Simpan sebagai .csv lalu unggah lagi.', 400);
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new HttpException('Berkas Excel tidak bisa dibaca.', 400);
    $shared = [];
    if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $x = simplexml_load_string($ss);
        foreach ($x->si as $si) {
            if (isset($si->t)) $shared[] = (string)$si->t;
            else { $t = ''; foreach ($si->r as $r) $t .= (string)$r->t; $shared[] = $t; }
        }
    }
    $sheetPath = 'xl/worksheets/sheet1.xml';
    if (($wb = $zip->getFromName('xl/workbook.xml')) !== false && ($rels = $zip->getFromName('xl/_rels/workbook.xml.rels')) !== false) {
        $wbx = simplexml_load_string($wb);
        $first = $wbx->sheets->sheet[0] ?? null;
        if ($first) {
            $rid = (string)$first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $rx = simplexml_load_string($rels);
            foreach ($rx->Relationship as $r) {
                if ((string)$r['Id'] === $rid) { $sheetPath = 'xl/' . ltrim(str_replace('/xl/', '', (string)$r['Target']), '/'); break; }
            }
        }
    }
    $xml = $zip->getFromName($sheetPath);
    $zip->close();
    if ($xml === false) throw new HttpException('Lembar kerja tidak ditemukan di berkas Excel.', 400);
    $sx = simplexml_load_string($xml);
    $rows = [];
    foreach ($sx->sheetData->row as $row) {
        $out = [];
        foreach ($row->c as $c) {
            preg_match('/^([A-Z]+)/', (string)$c['r'], $m);
            $col = 0;
            foreach (str_split($m[1] ?? 'A') as $ch) $col = $col * 26 + (ord($ch) - 64);
            $col--;
            $t = (string)$c['t'];
            $v = match ($t) {
                's' => $shared[(int)$c->v] ?? '',
                'inlineStr' => (string)($c->is->t ?? ''),
                default => (string)$c->v,
            };
            $out[$col] = trim($v);
        }
        if (!$out) continue;
        $max = max(array_keys($out));
        $line = [];
        for ($i = 0; $i <= $max; $i++) $line[] = $out[$i] ?? '';
        $rows[] = $line;
    }
    return $rows;
}

/** Map a header row to known keys using loose matching. */
function sheet_header_map(array $header, array $aliases): array
{
    $map = [];
    foreach ($header as $i => $h) {
        $norm = strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$h));
        foreach ($aliases as $key => $alts) {
            foreach ($alts as $a) {
                if ($norm === strtolower(preg_replace('/[^a-z0-9]/i', '', $a))) { $map[$key] = $i; break 2; }
            }
        }
    }
    return $map;
}
