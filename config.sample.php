<?php
/**
 * Salin berkas ini menjadi config.php lalu isi sesuai hosting.
 * Atau buka /install di browser: installer akan membuat config.php otomatis.
 */
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'u123456789_perpustakaan',
        'user' => 'u123456789_perpus',
        'pass' => 'ganti-dengan-password-database',
    ],
    // Alamat lengkap aplikasi tanpa garis miring di akhir. Dipakai untuk tautan di pesan WhatsApp.
    'base_url' => 'https://satuperpus.daratamalabs.com',
    // Kunci acak untuk keamanan sesi & tautan. Jangan dibagikan.
    'app_key' => 'ganti-dengan-teks-acak-panjang',
    // Token Fonnte cadangan (opsional). Tiap sekolah bisa memakai token sendiri di Pengaturan.
    'wa_token' => '',
    'timezone' => 'Asia/Jakarta',
    'debug' => false,
];
