<?php
// ============================================================
//  hapus_pasien.php — Handler Hapus Pasien
// ============================================================
require_once 'koneksi.php';
require_login();

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pasien.php');
    exit;
}

$id       = (int)($_POST['id'] ?? 0);
$redirect = $_POST['redirect'] ?? 'pasien.php';

// Whitelist redirect — hanya boleh ke halaman internal
if (!preg_match('/^[a-zA-Z0-9_\?\=\&\.\/\-]+$/', $redirect) || strpos($redirect, '//') !== false) {
    $redirect = 'pasien.php';
}

if ($id <= 0) {
    header('Location: pasien.php?err=invalid');
    exit;
}

// Pastikan pasien ada
$chk = $koneksi->prepare('SELECT id, nama FROM pasien WHERE id = ? LIMIT 1');
$chk->bind_param('i', $id);
$chk->execute();
$pasien = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$pasien) {
    header('Location: pasien.php?err=notfound');
    exit;
}

// Hapus file foto fisik dari disk sebelum hapus DB
$foto_res = $koneksi->prepare(
    'SELECT tf.nama_file FROM treatment_foto tf
     JOIN riwayat_treatment rt ON rt.id = tf.treatment_id
     WHERE rt.pasien_id = ?'
);
$foto_res->bind_param('i', $id);
$foto_res->execute();
$foto_rows = $foto_res->get_result()->fetch_all(MYSQLI_ASSOC);
$foto_res->close();

foreach ($foto_rows as $f) {
    $path = __DIR__ . '/uploads/' . $f['nama_file'];
    if (file_exists($path)) {
        @unlink($path);
    }
}

// Hapus folder pasien jika kosong
$folder = __DIR__ . '/uploads/pasien_' . $id . '/';
if (is_dir($folder)) {
    @rmdir($folder); // hanya hapus kalau sudah kosong
}

// Hapus pasien — CASCADE akan hapus riwayat_treatment & treatment_foto di DB
$stmt = $koneksi->prepare('DELETE FROM pasien WHERE id = ?');
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    // Redirect dengan flash message sukses
    // Arahkan ke pasien.php agar tidak redirect ke detail pasien yang sudah dihapus
    header('Location: pasien.php?deleted=1&nama=' . urlencode($pasien['nama']));
} else {
    error_log('Hapus pasien gagal id=' . $id . ': ' . $koneksi->error);
    header('Location: pasien.php?err=delete');
}
exit;
