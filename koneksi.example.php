<?php
// ============================================================
//  koneksi.example.php — Template Konfigurasi Database
//
//  CARA PAKAI:
//  1. Salin file ini menjadi koneksi.php
//     cp koneksi.example.php koneksi.php
//  2. Isi nilai DB_USER, DB_PASS, DB_NAME sesuai environment
//  3. koneksi.php TIDAK akan ter-commit ke Git (ada di .gitignore)
// ============================================================

// --- Lokal (Laragon) ---
// define('DB_HOST', 'localhost');
// define('DB_USER', 'root');
// define('DB_PASS', '');
// define('DB_NAME', 'klinik_kecantikan');

// --- Hosting (cPanel) ---
// define('DB_HOST', 'localhost');
// define('DB_USER', 'username_cpanel');
// define('DB_PASS', 'password_cpanel');
// define('DB_NAME', 'nama_database_cpanel');

define('DB_HOST', 'localhost');
define('DB_USER', 'YOUR_DB_USER');
define('DB_PASS', 'YOUR_DB_PASS');
define('DB_NAME', 'YOUR_DB_NAME');
define('DB_CHARSET', 'utf8mb4');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // ubah ke true jika HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

$koneksi = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($koneksi->connect_error) {
    error_log('Koneksi DB gagal: ' . $koneksi->connect_error);
    die('<p style="color:red;font-family:sans-serif;text-align:center;margin-top:50px;">
         Gagal terhubung ke database. Silakan hubungi administrator.</p>');
}

$koneksi->set_charset(DB_CHARSET);

function require_login(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}
