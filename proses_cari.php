<?php
// ============================================================
//  proses_cari.php - Backend Handler Pencarian AJAX
//  Hanya menerima request dari XMLHttpRequest (Fetch API)
// ============================================================
require_once 'koneksi.php';
require_login();

// Pastikan ini request AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    exit('Forbidden');
}

// Selalu kembalikan JSON
header('Content-Type: application/json; charset=utf-8');

// Ambil & validasi keyword
$keyword = trim($_GET['q'] ?? '');

if (mb_strlen($keyword) < 2) {
    echo json_encode(['pasien' => []]);
    exit;
}

// Batasi panjang keyword untuk keamanan
$keyword = mb_substr($keyword, 0, 100);

// ---- Query 1: Cari pasien yang namanya mengandung keyword ----
// Menggunakan prepared statement untuk mencegah SQL Injection
$like = '%' . $keyword . '%';

$sql_pasien = 'SELECT id, nama, telepon,
                      DATE_FORMAT(tanggal_lahir, \'%d %M %Y\') AS tanggal_lahir,
                      DATE_FORMAT(created_at, \'%d %M %Y\') AS created_at
               FROM pasien
               WHERE nama LIKE ?
               ORDER BY nama ASC
               LIMIT 20';

$stmt = $koneksi->prepare($sql_pasien);
$stmt->bind_param('s', $like);
$stmt->execute();
$result_pasien = $stmt->get_result();
$stmt->close();

$output = ['pasien' => []];

while ($pasien = $result_pasien->fetch_assoc()) {
    // ---- Query 2: Ambil semua treatment + divisi ----
    $sql_treatment = 'SELECT rt.id,
                             DATE_FORMAT(rt.tanggal_treatment, \'%d %M %Y\') AS tanggal_treatment,
                             rt.nama_treatment,
                             rt.catatan,
                             d.id    AS divisi_id,
                             d.kode  AS divisi_kode,
                             d.nama  AS divisi_nama,
                             d.warna AS divisi_warna
                      FROM riwayat_treatment rt
                      LEFT JOIN divisi d ON d.id = rt.divisi_id
                      WHERE rt.pasien_id = ?
                      ORDER BY rt.tanggal_treatment DESC, rt.id DESC';

    $stmt2 = $koneksi->prepare($sql_treatment);
    $stmt2->bind_param('i', $pasien['id']);
    $stmt2->execute();
    $result_treatment = $stmt2->get_result();
    $stmt2->close();

    $treatments = [];
    while ($row = $result_treatment->fetch_assoc()) {
        $treatment_id = (int)$row['id'];

        // ---- Query 3: Ambil semua foto dari treatment_foto ----
        $stmt3 = $koneksi->prepare(
            'SELECT tipe, nama_file, urutan FROM treatment_foto
             WHERE treatment_id = ? ORDER BY tipe ASC, urutan ASC'
        );
        $stmt3->bind_param('i', $treatment_id);
        $stmt3->execute();
        $result_foto = $stmt3->get_result();
        $stmt3->close();

        $foto_before = [];
        $foto_after  = [];
        while ($f = $result_foto->fetch_assoc()) {
            if ($f['tipe'] === 'before') $foto_before[] = $f['nama_file'];
            else                          $foto_after[]  = $f['nama_file'];
        }

        $treatments[] = [
            'id'                => $treatment_id,
            'tanggal_treatment' => $row['tanggal_treatment'],
            'nama_treatment'    => $row['nama_treatment'],
            'catatan'           => $row['catatan'],
            'foto_before'       => $foto_before,
            'foto_after'        => $foto_after,
            'divisi_id'         => $row['divisi_id'] ? (int)$row['divisi_id'] : null,
            'divisi_kode'       => $row['divisi_kode'],
            'divisi_nama'       => $row['divisi_nama'],
            'divisi_warna'      => $row['divisi_warna'],
        ];
    }

    $output['pasien'][] = [
        'id'             => (int)$pasien['id'],
        'nama'           => $pasien['nama'],
        'telepon'        => $pasien['telepon'],
        'tanggal_lahir'  => $pasien['tanggal_lahir'],
        'created_at'     => $pasien['created_at'],
        'treatments'     => $treatments,
    ];
}

// Kembalikan data sebagai JSON
echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
