<?php
declare(strict_types=1);

/** Token used for this school: its own, or the platform fallback from config.php. */
function wa_token(): string
{
    $wa = Tenant::get('wa') ?? [];
    $t = trim((string)($wa['token'] ?? ''));
    return $t !== '' ? $t : trim((string)(App::$config['wa_token'] ?? ''));
}

function wa_enabled(): bool { return wa_token() !== '' && function_exists('curl_init'); }

/** Send a WhatsApp message through Fonnte. @return array{ok:bool,reason?:string} */
function wa_send(?string $phone, string $message): array
{
    $target = norm_phone($phone);
    if ($target === '') return ['ok' => false, 'reason' => 'Nomor WhatsApp kosong'];
    if (!wa_enabled()) {
        error_log("[WA nonaktif] ke $target: $message");
        return ['ok' => false, 'reason' => 'Gateway WhatsApp belum diatur'];
    }
    $ch = curl_init('https://api.fonnte.com/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['target' => $target, 'message' => $message, 'countryCode' => '62'],
        CURLOPT_HTTPHEADER => ['Authorization: ' . wa_token()],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) return ['ok' => false, 'reason' => $err ?: 'Gagal terhubung ke Fonnte'];
    $data = json_decode((string)$body, true) ?: [];
    if (($data['status'] ?? false) === true) return ['ok' => true];
    return ['ok' => false, 'reason' => (string)($data['reason'] ?? 'Respons Fonnte tidak dikenal')];
}
