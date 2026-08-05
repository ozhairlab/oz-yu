<?php
// ============================================================
//  komposisi.php — Mengatur bahan yang digunakan per treatment
// ============================================================
require_once 'koneksi.php';
require_role(['superadmin']);

$perawatan_id = (int)($_GET['id'] ?? 0);
if ($perawatan_id <= 0) {
    die("ID Perawatan tidak valid.");
}

// Ambil info perawatan
$stmt = $koneksi->prepare('SELECT p.id, p.nama, d.nama AS divisi FROM master_perawatan p JOIN divisi d ON d.id = p.divisi_id WHERE p.id = ?');
$stmt->bind_param('i', $perawatan_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) die("Perawatan tidak ditemukan.");
$perawatan = $res->fetch_assoc();
$stmt->close();

$page_title  = 'Komposisi: ' . $perawatan['nama'];
$active_menu = 'master';

$errors = [];
$sukses = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $barang_id    = (int)($_POST['barang_id'] ?? 0);
        $jumlah_pakai = (int)($_POST['jumlah_pakai'] ?? 1);

        if ($barang_id <= 0) $errors[] = "Pilih bahan/barang.";
        if ($jumlah_pakai <= 0) $errors[] = "Jumlah pakai minimal 1.";

        if (empty($errors)) {
            // Cek apakah sudah ada di komposisi ini
            $chk = $koneksi->prepare('SELECT id FROM komposisi_perawatan WHERE perawatan_id = ? AND barang_id = ?');
            $chk->bind_param('ii', $perawatan_id, $barang_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors[] = "Bahan ini sudah ada di daftar komposisi.";
            } else {
                $stmt = $koneksi->prepare('INSERT INTO komposisi_perawatan (perawatan_id, barang_id, jumlah_pakai) VALUES (?, ?, ?)');
                $stmt->bind_param('iii', $perawatan_id, $barang_id, $jumlah_pakai);
                if ($stmt->execute()) {
                    $sukses = 'Bahan berhasil ditambahkan ke komposisi.';
                } else {
                    $errors[] = 'Gagal menyimpan.';
                }
                $stmt->close();
            }
            $chk->close();
        }
    }

    if ($aksi === 'hapus') {
        $komposisi_id = (int)($_POST['komposisi_id'] ?? 0);
        if ($komposisi_id > 0) {
            $stmt = $koneksi->prepare('DELETE FROM komposisi_perawatan WHERE id = ? AND perawatan_id = ?');
            $stmt->bind_param('ii', $komposisi_id, $perawatan_id);
            if ($stmt->execute()) {
                $sukses = 'Bahan dihapus dari komposisi.';
            }
            $stmt->close();
        }
    }
}

// Ambil daftar bahan untuk dropdown
$semua_bahan = [];
$res = $koneksi->query('SELECT id, nama, satuan FROM inventory_barang ORDER BY nama ASC');
if ($res) {
    while ($row = $res->fetch_assoc()) $semua_bahan[] = $row;
}

// Ambil komposisi saat ini
$komposisi = [];
$stmt = $koneksi->prepare('SELECT k.id, k.jumlah_pakai, b.nama, b.satuan, b.kode FROM komposisi_perawatan k JOIN inventory_barang b ON b.id = k.barang_id WHERE k.perawatan_id = ? ORDER BY b.nama ASC');
$stmt->bind_param('i', $perawatan_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    while ($row = $res->fetch_assoc()) $komposisi[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Ozthetique</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .page-wrap { padding: 30px; margin-left: 260px; }
        .header-action { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .page-title { font-size: 1.8rem; margin: 0; font-weight: 700; letter-spacing: -0.03em; }
        .page-subtitle { color: var(--text-muted); font-size: 1rem; margin-top: 5px; }
        
        .btn {
            background: linear-gradient(135deg, var(--gold), #a07840);
            color: #0a0a0f; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem; transition: transform 0.2s; text-decoration: none;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .btn-danger { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
        .btn-danger:hover { background: rgba(239,68,68,0.2); }
        .btn-ghost { background: transparent; color: var(--text); border: 1px solid var(--border); }
        .btn-ghost:hover { background: var(--surface-2); }
        
        .grid-layout { display: grid; grid-template-columns: 1fr 350px; gap: 20px; align-items: start; }
        
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; box-shadow: var(--shadow-sm); }
        .card-title { font-size: 1.1rem; font-weight: 700; margin-top: 0; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: var(--surface-2); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); }
        td { font-size: 0.95rem; color: var(--text); }
        tr:last-child td { border-bottom: none; }
        
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: var(--text); }
        .form-input {
            width: 100%; padding: 10px 12px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: inherit; font-size: 0.95rem; box-sizing: border-box;
        }
        .form-input:focus { outline: none; border-color: var(--gold); }
        
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
        .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; }
        .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #10b981; }
        
        @media (max-width: 768px) {
            .page-wrap { margin-left: 0; padding: 20px; margin-top: 60px; }
            .grid-layout { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main class="page-wrap">
        <div class="header-action">
            <div>
                <h1 class="page-title">Komposisi Bahan</h1>
                <div class="page-subtitle"><?= htmlspecialchars($perawatan['nama']) ?> (Divisi: <?= htmlspecialchars($perawatan['divisi']) ?>)</div>
            </div>
            <a href="master_perawatan.php" class="btn btn-ghost">
                &larr; Kembali ke Master
            </a>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin:0; padding-left:20px;">
                    <?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($sukses): ?>
            <div class="alert alert-success"><?= htmlspecialchars($sukses) ?></div>
        <?php endif; ?>
        
        <div class="grid-layout">
            <!-- Kiri: Tabel Komposisi -->
            <div class="card" style="padding:0; overflow:hidden;">
                <div style="padding:20px; border-bottom: 1px solid var(--border);">
                    <h2 class="card-title" style="border:none; margin:0; padding:0;">Daftar Bahan Digunakan</h2>
                    <p style="margin:5px 0 0; font-size:0.85rem; color:var(--text-muted);">Bahan di bawah ini akan memotong stok otomatis setiap ada transaksi POS.</p>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Bahan</th>
                            <th>Jml Pakai</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($komposisi)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:30px; color:var(--text-muted);">Belum ada bahan. Tambahkan di form sebelah kanan.</td></tr>
                        <?php endif; ?>
                        <?php foreach($komposisi as $k): ?>
                        <tr>
                            <td style="font-family:monospace; color:var(--text-muted); font-size:0.85rem;"><?= htmlspecialchars($k['kode']) ?></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($k['nama']) ?></td>
                            <td><strong style="color:var(--primary); font-size:1.1rem;"><?= $k['jumlah_pakai'] ?></strong> <small><?= htmlspecialchars($k['satuan']) ?></small></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus bahan ini dari komposisi?');">
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="komposisi_id" value="<?= $k['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Kanan: Form Tambah Komposisi -->
            <div class="card">
                <h2 class="card-title">Tambah Bahan</h2>
                <?php if(empty($semua_bahan)): ?>
                    <p style="color:#ef4444; font-size:0.85rem;">Data bahan kosong. Silakan tambahkan bahan terlebih dahulu di menu <strong>Inventory Bahan</strong>.</p>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="aksi" value="tambah">
                        
                        <div class="form-group">
                            <label class="form-label">Pilih Bahan/Alat</label>
                            <select name="barang_id" class="form-input" required>
                                <option value="">— Pilih Bahan —</option>
                                <?php foreach($semua_bahan as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nama']) ?> (<?= htmlspecialchars($b['satuan']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Jumlah Pakai (per 1x tindakan)</label>
                            <input type="number" name="jumlah_pakai" class="form-input" required min="1" value="1">
                        </div>
                        
                        <button type="submit" class="btn" style="width:100%; justify-content:center; margin-top:10px;">+ Tambahkan</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
