<?php
declare(strict_types=1);

const APP_VERSION = '1.0.0';
define('ROOT', dirname(__DIR__));
define('APP', __DIR__);
define('STORAGE', ROOT . '/storage');

mb_internal_encoding('UTF-8');

require APP . '/helpers.php';
require APP . '/db.php';
require APP . '/tenant.php';
require APP . '/auth.php';
foreach (glob(APP . '/services/*.php') as $f) require $f;
foreach (glob(APP . '/controllers/*.php') as $f) require $f;
require APP . '/routes.php';

final class HttpException extends RuntimeException {}

final class App
{
    public static array $config = [];
    public static string $base = '';
    public static string $path = '/';
    public static ?array $tenant = null;

    public static function run(): void
    {
        // Under Apache/LiteSpeed SCRIPT_NAME is /index.php (or /subfolder/index.php). The PHP built-in
        // server sets it to the requested file for existing files, so the base is always the root there.
        self::$base = PHP_SAPI === 'cli-server' ? '' : rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (self::$base !== '' && str_starts_with($uri, self::$base)) $uri = substr($uri, strlen(self::$base));
        $uri = '/' . trim(rawurldecode($uri), '/');
        self::$path = $uri;

        // Built-in PHP server: serve /assets directly.
        if (PHP_SAPI === 'cli-server' && str_starts_with($uri, '/assets/') && is_file(ROOT . $uri)) {
            self::serveStatic(ROOT . $uri);
            return;
        }

        $installed = is_file(ROOT . '/config.php');
        if ($installed) self::$config = require ROOT . '/config.php';
        date_default_timezone_set(self::$config['timezone'] ?? 'Asia/Jakarta');
        $debug = (bool)(self::$config['debug'] ?? false);
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting(E_ALL);

        self::startSession();
        set_exception_handler([self::class, 'handleException']);

        $seg = explode('/', ltrim($uri, '/'));
        $first = $seg[0];
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (!$installed || $first === 'install') {
            install_handle($installed);
            return;
        }
        if ($method === 'POST') csrf_check();

        if ($first === 'favicon.ico') {
            header('Content-Type: image/svg+xml');
            header('Cache-Control: public, max-age=604800');
            echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" rx="6" fill="#10284A"/><path d="M5 7h4a2 2 0 0 1 2 2v8a2 2 0 0 0-2-2H5zM19 7h-4a2 2 0 0 0-2 2v8a2 2 0 0 1 2-2h4z" fill="none" stroke="#D4A63A" stroke-width="1.6"/></svg>';
            return;
        }
        if ($first === 'robots.txt') { header('Content-Type: text/plain'); echo "User-agent: *\nDisallow: /\n"; return; }
        if ($first === '') { echo platform_home(); return; }
        if ($first === 'superadmin') {
            Router::dispatch(routes_platform(), $method, '/' . implode('/', array_slice($seg, 1)));
            return;
        }
        $tenant = Tenant::bySlug($first);
        if (!$tenant) throw new HttpException('Alamat perpustakaan tidak ditemukan.', 404);
        if (!$tenant['active']) throw new HttpException('Perpustakaan ini sedang tidak aktif. Hubungi pengelola.', 403);
        self::$tenant = $tenant;
        Router::dispatch(routes_tenant(), $method, '/' . implode('/', array_slice($seg, 1)));
    }

    private static function startSession(): void
    {
        $dir = STORAGE . '/sessions';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (is_writable($dir)) session_save_path($dir);
        ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 30));
        ini_set('session.use_strict_mode', '1');
        session_name('perpus_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (self::$base ?: '') . '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Keep the session cookie for 30 days (used by "ingat perangkat ini"). */
    public static function rememberSession(): void
    {
        setcookie(session_name(), session_id(), [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => (self::$base ?: '') . '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function serveStatic(string $file): void
    {
        $types = ['css' => 'text/css', 'js' => 'application/javascript', 'woff2' => 'font/woff2', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'webp' => 'image/webp', 'ico' => 'image/x-icon'];
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=86400');
        readfile($file);
    }

    public static function handleException(Throwable $e): void
    {
        $code = $e instanceof HttpException ? (int)$e->getCode() : 500;
        if ($code < 400 || $code > 599) $code = 500;
        if ($code === 500) error_log((string)$e);
        if (!headers_sent()) http_response_code($code);
        $msg = $e instanceof HttpException ? $e->getMessage() : 'Terjadi kesalahan di server. Silakan coba lagi.';
        if ($code === 500 && (self::$config['debug'] ?? false)) $msg .= "\n\n" . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine();
        if (wants_json()) {
            json_out(['ok' => false, 'message' => $msg], $code);
            return;
        }
        while (ob_get_level() > 0) ob_end_clean();
        echo view('error', ['title' => $code === 404 ? 'Halaman tidak ditemukan' : ($code === 403 ? 'Akses ditolak' : 'Terjadi kesalahan'), 'message' => $msg, 'code' => $code], 'bare');
    }
}

final class Router
{
    /** @param array<int,array{0:string,1:string,2:callable|string}> $routes */
    public static function dispatch(array $routes, string $method, string $path): void
    {
        $path = '/' . trim($path, '/');
        $allowed = false;
        foreach ($routes as [$m, $pattern, $handler]) {
            $regex = '#^' . preg_replace(['#\{id\}#', '#\{(\w+)\}#'], ['(\d+)', '([^/]+)'], rtrim($pattern, '/') ?: '/') . '$#';
            if (!preg_match($regex, $path, $mm)) continue;
            if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) { $allowed = true; continue; }
            array_shift($mm);
            $out = $handler(...$mm);
            if (is_string($out)) echo $out;
            return;
        }
        throw new HttpException($allowed ? 'Metode tidak diizinkan.' : 'Halaman tidak ditemukan.', $allowed ? 405 : 404);
    }
}
