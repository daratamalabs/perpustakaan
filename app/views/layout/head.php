<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="theme-color" content="#10284A">
<meta name="robots" content="noindex">
<title><?= e(($title ?? 'Perpustakaan') . (App::$tenant ? ' · ' . Tenant::shortName() : ' · Perpustakaan Digital')) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='6' fill='%2310284A'/%3E%3Cpath d='M5 7h4a2 2 0 0 1 2 2v8a2 2 0 0 0-2-2H5zM19 7h-4a2 2 0 0 0-2 2v8a2 2 0 0 1 2-2h4z' fill='none' stroke='%23D4A63A' stroke-width='1.6'/%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
