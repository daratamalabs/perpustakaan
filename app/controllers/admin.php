<?php
declare(strict_types=1);

function admin_login_form(): string
{
    if ($u = Auth::staff()) redirect(Auth::homeFor($u));
    return view('admin/login', ['error' => null, 'title' => 'Masuk petugas'], 'bare');
}

function admin_login(): string
{
    $username = strtolower((string)p('username'));
    $error = Auth::throttle('staff-login', 8);
    if (!$error) {
        $u = DB::one('SELECT * FROM users WHERE tenant_id = ? AND username = ?', [Tenant::id(), $username]);
        if ($u && $u['active'] && password_verify((string)p('password'), $u['password_hash'])) {
            Auth::loginStaff($u, (bool)p('remember'));
            Auth::clearThrottle('staff-login');
            $next = (string)g('next');
            redirect($next && str_starts_with($next, turl('/')) ? $next : Auth::homeFor($u));
        }
        $error = $u && !$u['active'] ? 'Akun ini dinonaktifkan. Hubungi kepala perpustakaan.' : 'Username atau kata sandi salah.';
    }
    return view('admin/login', ['error' => $error, 'username' => $username, 'title' => 'Masuk petugas'], 'bare');
}

function admin_logout(): never
{
    Auth::logoutStaff();
    redirect(turl('/admin/login'));
}

function admin_dashboard(): string
{
    $u = Auth::requireStaff();
    if (!Auth::can('dashboard')) redirect(Auth::homeFor($u));
    $tid = Tenant::id();
    $today = today();
    $yesterdayDays = recent_school_days(2);
    $prev = count($yesterdayDays) === 2 && $yesterdayDays[1] === $today ? $yesterdayDays[0] : ($yesterdayDays[1] ?? $today);
    $counts = stats_visit_counts($today, $today);
    $prevCounts = stats_visit_counts($prev, $prev);
    $days = recent_school_days(6);
    $daily = $days ? stats_daily($days[0], end($days)) : [];
    $hourly = stats_hourly($today, $today);
    $peak = $hourly && max($hourly) > 0 ? array_search(max($hourly), $hourly, true) : null;
    $monday = date('Y-m-d', strtotime('monday this week'));
    $pending = DB::one("SELECT COUNT(*) n, MIN(created_at) oldest FROM members WHERE tenant_id = ? AND status = 'pending'", [$tid]);
    $todo = DB::q("SELECT l.*, m.name, m.kelas, m.type, m.subject, m.phone, b.title, b.collection_type FROM loans l
        JOIN members m ON m.id = l.member_id JOIN copies c ON c.id = l.copy_id JOIN books b ON b.id = c.book_id
        WHERE l.tenant_id = ? AND l.returned_at IS NULL AND b.collection_type <> 'paket' AND l.due_date <= ? ORDER BY l.due_date LIMIT 6", [$tid, add_days($today, 3)]);
    return view('admin/dashboard', [
        'counts' => $counts, 'prevCounts' => $prevCounts, 'inside' => stats_inside_now(), 'loans' => stats_loans(), 'peak' => $peak,
        'days' => $days, 'daily' => $daily, 'classes' => stats_top_classes($monday, $today, 5), 'recent' => stats_recent_visits(6),
        'pending' => $pending, 'todo' => $todo, 'title' => 'Dashboard', 'active' => 'dashboard',
    ], 'admin');
}
