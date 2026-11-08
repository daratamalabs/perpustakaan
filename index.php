<?php
/**
 * Perpustakaan Digital Sekolah — front controller.
 * Semua permintaan (kecuali /assets) masuk ke sini lewat .htaccess.
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

App::run();
