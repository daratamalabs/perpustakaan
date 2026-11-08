<?php
declare(strict_types=1);

function http_get_json(string $url): ?array
{
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'PerpustakaanDigitalSekolah/1.0']);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400) return null;
    $d = json_decode((string)$body, true);
    return is_array($d) ? $d : null;
}

/** Look up book data by ISBN (Google Books, then Open Library). */
function isbn_lookup(string $isbn): ?array
{
    $isbn = preg_replace('/[^0-9Xx]/', '', $isbn);
    if (strlen($isbn) !== 10 && strlen($isbn) !== 13) return null;

    $g = http_get_json('https://www.googleapis.com/books/v1/volumes?q=isbn:' . $isbn);
    if (!empty($g['items'][0]['volumeInfo'])) {
        $v = $g['items'][0]['volumeInfo'];
        return [
            'title' => trim(($v['title'] ?? '') . (isset($v['subtitle']) ? ': ' . $v['subtitle'] : '')),
            'author' => implode(', ', $v['authors'] ?? []),
            'publisher' => $v['publisher'] ?? '',
            'year' => substr((string)($v['publishedDate'] ?? ''), 0, 4),
            'language' => ($v['language'] ?? '') === 'id' ? 'Indonesia' : (($v['language'] ?? '') === 'en' ? 'Inggris' : ''),
            'synopsis' => mb_substr(strip_tags((string)($v['description'] ?? '')), 0, 1000),
            'isbn' => $isbn,
        ];
    }
    $o = http_get_json('https://openlibrary.org/api/books?format=json&jscmd=data&bibkeys=ISBN:' . $isbn);
    $b = $o['ISBN:' . $isbn] ?? null;
    if ($b) {
        return [
            'title' => $b['title'] ?? '',
            'author' => implode(', ', array_map(fn($a) => $a['name'] ?? '', $b['authors'] ?? [])),
            'publisher' => $b['publishers'][0]['name'] ?? '',
            'year' => preg_match('/\d{4}/', (string)($b['publish_date'] ?? ''), $m) ? $m[0] : '',
            'language' => '',
            'synopsis' => '',
            'isbn' => $isbn,
        ];
    }
    return null;
}

/** Call number like "899.221 3 HIR l" from DDC, author and title. */
function call_number(?string $ddc, ?string $author, ?string $title): string
{
    $ddc = trim((string)$ddc);
    $a = '';
    if ($author) {
        $first = trim(explode(',', $author)[0]);
        $parts = preg_split('/\s+/', $first);
        $last = end($parts) ?: '';
        $a = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}]/u', '', $last), 0, 3));
    }
    $t = '';
    if ($title) {
        $w = preg_replace('/^(the|a|an|sang|si)\s+/iu', '', trim($title));
        $t = mb_strtolower(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $w), 0, 1));
    }
    return trim("$ddc $a $t");
}
