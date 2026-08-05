<?php
// ============================================================
//  inventory.php — Manajemen Stok Bahan (Inventory) Premium
// ============================================================
require_once 'koneksi.php';
require_role(['superadmin', 'admin_medis']);

$page_title  = 'Inventory Bahan';
$active_menu = 'inventory';

$errors = [];
$sukses = '';

// --- PROSES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // TAMBAH BARANG
    if ($aksi === 'tambah') {
        $kode         = trim($_POST['kode'] ?? '');
        $nama         = trim($_POST['nama'] ?? '');
        $satuan       = trim($_POST['satuan'] ?? 'Pcs');
        $stok_awal    = (int)($_POST['stok_awal'] ?? 0);
        $stok_minimal = (int)($_POST['stok_minimal'] ?? 0);
        
        if ($kode === '') $errors[] = 'Kode barang wajib diisi.';
        if ($nama === '') $errors[] = 'Nama barang wajib diisi.';

        if (empty($errors)) {
            $stmt = $koneksi->prepare('SELECT id FROM inventory_barang WHERE kode = ?');
            $stmt->bind_param('s', $kode);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = 'Kode barang sudah digunakan.';
            }
            $stmt->close();

            if (empty($errors)) {
                $koneksi->begin_transaction();
                try {
                    $stmt = $koneksi->prepare('INSERT INTO inventory_barang (kode, nama, satuan, stok_sekarang, stok_minimal) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bind_param('sssii', $kode, $nama, $satuan, $stok_awal, $stok_minimal);
                    $stmt->execute();
                    $barang_id = $stmt->insert_id;
                    $stmt->close();

                    if ($stok_awal > 0) {
                        $admin_id = $_SESSION['admin_id'] ?? null;
                        $ket = 'Stok awal saat barang ditambahkan';
                        $tipe = 'masuk';
                        $stmt2 = $koneksi->prepare('INSERT INTO inventory_riwayat (barang_id, tipe, jumlah, stok_akhir, keterangan, user_id) VALUES (?, ?, ?, ?, ?, ?)');
                        $stmt2->bind_param('isiisi', $barang_id, $tipe, $stok_awal, $stok_awal, $ket, $admin_id);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                    $koneksi->commit();
                    $sukses = 'Barang berhasil ditambahkan.';
                } catch (Exception $e) {
                    $koneksi->rollback();
                    $errors[] = 'Gagal menyimpan barang: ' . $e->getMessage();
                }
            }
        }
    }

    // EDIT BARANG
    if ($aksi === 'edit') {
        $id           = (int)($_POST['id'] ?? 0);
        $nama         = trim($_POST['nama'] ?? '');
        $satuan       = trim($_POST['satuan'] ?? 'Pcs');
        $stok_minimal = (int)($_POST['stok_minimal'] ?? 0);

        if ($id <= 0) $errors[] = 'ID barang tidak valid.';
        if ($nama === '') $errors[] = 'Nama barang wajib diisi.';

        if (empty($errors)) {
            $stmt = $koneksi->prepare('UPDATE inventory_barang SET nama = ?, satuan = ?, stok_minimal = ? WHERE id = ?');
            $stmt->bind_param('ssii', $nama, $satuan, $stok_minimal, $id);
            if ($stmt->execute()) {
                $sukses = 'Data barang berhasil diperbarui.';
            } else {
                $errors[] = 'Gagal memperbarui barang.';
            }
            $stmt->close();
        }
    }

    // PENYESUAIAN STOK (IN/OUT)
    if ($aksi === 'adjust_stok') {
        $id       = (int)($_POST['id'] ?? 0);
        $tipe     = $_POST['tipe'] ?? '';
        $jumlah   = (int)($_POST['jumlah'] ?? 0);
        $ket      = trim($_POST['keterangan'] ?? '');
        $admin_id = $_SESSION['admin_id'] ?? null;

        if ($id <= 0) $errors[] = 'ID barang tidak valid.';
        if ($jumlah <= 0) $errors[] = 'Jumlah harus lebih besar dari 0.';
        if (!in_array($tipe, ['masuk', 'keluar', 'penyesuaian'])) $errors[] = 'Tipe penyesuaian tidak valid.';

        if (empty($errors)) {
            $koneksi->begin_transaction();
            try {
                $stmt = $koneksi->prepare('SELECT stok_sekarang FROM inventory_barang WHERE id = ? FOR UPDATE');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows === 0) throw new Exception('Barang tidak ditemukan.');
                $row = $res->fetch_assoc();
                $stok_lama = (int)$row['stok_sekarang'];
                $stmt->close();

                $stok_baru = $stok_lama;
                if ($tipe === 'masuk') {
                    $stok_baru += $jumlah;
                } else if ($tipe === 'keluar' || $tipe === 'penyesuaian') {
                    if ($stok_lama < $jumlah && $tipe === 'keluar') {
                        throw new Exception('Stok saat ini tidak mencukupi untuk dikeluarkan.');
                    }
                    $stok_baru -= $jumlah;
                }

                $stmt2 = $koneksi->prepare('UPDATE inventory_barang SET stok_sekarang = ? WHERE id = ?');
                $stmt2->bind_param('ii', $stok_baru, $id);
                $stmt2->execute();
                $stmt2->close();

                $stmt3 = $koneksi->prepare('INSERT INTO inventory_riwayat (barang_id, tipe, jumlah, stok_akhir, keterangan, user_id) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt3->bind_param('isiisi', $id, $tipe, $jumlah, $stok_baru, $ket, $admin_id);
                $stmt3->execute();
                $stmt3->close();

                $koneksi->commit();
                $sukses = 'Stok berhasil diperbarui.';
            } catch (Exception $e) {
                $koneksi->rollback();
                $errors[] = $e->getMessage();
            }
        }
    }

    // HAPUS BARANG
    if ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $chk = $koneksi->prepare('SELECT COUNT(*) FROM komposisi_perawatan WHERE barang_id = ?');
            $chk->bind_param('i', $id);
            $chk->execute();
            $used = $chk->get_result()->fetch_row()[0];
            $chk->close();

            if ($used > 0) {
                $errors[] = 'Tidak bisa dihapus, barang ini masih digunakan dalam komposisi Master Perawatan.';
            } else {
                $stmt = $koneksi->prepare('DELETE FROM inventory_barang WHERE id = ?');
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) $sukses = 'Barang berhasil dihapus.';
                else $errors[] = 'Gagal menghapus barang.';
                $stmt->close();
            }
        }
    }
}

// --- AMBIL DATA STATISTIK ---
$total_item = 0;
$stok_menipis = 0;
$stok_habis = 0;

$res_stat = $koneksi->query('SELECT stok_sekarang, stok_minimal FROM inventory_barang');
while ($row = $res_stat->fetch_assoc()) {
    $total_item++;
    if ($row['stok_sekarang'] <= 0) {
        $stok_habis++;
    } elseif ($row['stok_sekarang'] <= $row['stok_minimal']) {
        $stok_menipis++;
    }
}

// --- AMBIL DATA INVENTORY DENGAN FILTER ---
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$active_tab = $_GET['tab'] ?? 'barang';

$sql = "SELECT * FROM inventory_barang WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (nama LIKE ? OR kode LIKE ?)";
    $search_like = "%$search%";
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= 'ss';
}

if ($status === 'habis') {
    $sql .= " AND stok_sekarang <= 0";
} elseif ($status === 'menipis') {
    $sql .= " AND stok_sekarang > 0 AND stok_sekarang <= stok_minimal";
} elseif ($status === 'aman') {
    $sql .= " AND stok_sekarang > stok_minimal";
}

$sql .= " ORDER BY nama ASC";

$barang = [];
$stmt = $koneksi->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $barang[] = $row;
}
$stmt->close();

// --- AMBIL DATA RIWAYAT MUTASI ---
$riwayat = [];
if ($active_tab === 'mutasi') {
    $sql_riwayat = "SELECT r.*, b.nama AS nama_barang, b.kode, u.username 
                    FROM inventory_riwayat r 
                    JOIN inventory_barang b ON b.id = r.barang_id 
                    LEFT JOIN users u ON u.id = r.user_id 
                    ORDER BY r.created_at DESC LIMIT 500";
    $res_riwayat = $koneksi->query($sql_riwayat);
    while ($row = $res_riwayat->fetch_assoc()) {
        $riwayat[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> — Ozthetique</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .page-wrap { padding: 40px; margin-left: 260px; min-height: 100vh; background: var(--bg, #f8f9fa); }
        .page-title { font-size: 1.8rem; margin: 0; font-weight: 800; letter-spacing: -0.03em; color: var(--text, #111827); }
        .page-subtitle { color: #6b7280; font-size: 1rem; margin-top: 5px; }

        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: #ffffff; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); display: flex; align-items: center; border: 1px solid #f3f4f6; transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-icon { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin-right: 20px; flex-shrink: 0; }
        .stat-icon svg { width: 28px; height: 28px; }
        .stat-content { flex-grow: 1; }
        .stat-label { font-size: 0.875rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        .stat-value { font-size: 1.875rem; font-weight: 800; color: #111827; line-height: 1; }

        .icon-blue { background: #eff6ff; color: #3b82f6; }
        .icon-yellow { background: #fefce8; color: #eab308; }
        .icon-red { background: #fef2f2; color: #ef4444; }

        /* Actions & Filters */
        .header-actions { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .btn-primary { background: linear-gradient(135deg, #d4af37, #b8860b); color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; transition: all 0.2s; box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(212, 175, 55, 0.4); }
        
        .filter-group { display: flex; gap: 15px; flex-wrap: wrap; }
        .filter-input { padding: 10px 16px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 0.95rem; min-width: 250px; background: #fff; }
        .filter-select { padding: 10px 16px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 0.95rem; background: #fff; cursor: pointer; }
        
        /* Tabs */
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #f3f4f6; padding-bottom: 1px; }
        .tab-link {
            padding: 10px 20px; font-weight: 600; font-size: 0.95rem; color: #6b7280; text-decoration: none; border-bottom: 3px solid transparent; transition: all 0.2s; cursor: pointer; margin-bottom: -2px;
        }
        .tab-link:hover { color: #111827; }
        .tab-link.active { color: #b8860b; border-bottom-color: #b8860b; }

        /* Tables */
        .card-table { background: #ffffff; border-radius: 16px; border: 1px solid #f3f4f6; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px 24px; text-align: left; border-bottom: 1px solid #f3f4f6; }
        th { background: #f9fafb; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: #6b7280; font-weight: 700; }
        td { font-size: 0.95rem; color: #374151; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f9fafb; }
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .badge-success { background: #ecfdf5; color: #10b981; border: 1px solid #a7f3d0; }
        .badge-warning { background: #fefce8; color: #eab308; border: 1px solid #fef08a; }
        .badge-danger { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }
        .badge-info { background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe; }
        .badge-gray { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }

        /* Buttons */
        .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; border: 1px solid transparent; transition: all 0.2s; text-decoration: none; display: inline-block; }
        .btn-ghost-blue { background: transparent; color: #3b82f6; border-color: #bfdbfe; }
        .btn-ghost-blue:hover { background: #eff6ff; }
        .btn-ghost-red { background: transparent; color: #ef4444; border-color: #fecaca; }
        .btn-ghost-red:hover { background: #fef2f2; }
        .btn-ghost-gray { background: transparent; color: #6b7280; border-color: #e5e7eb; }
        .btn-ghost-gray:hover { background: #f3f4f6; }
        .actions-group { display: flex; gap: 8px; flex-wrap: wrap; }

        /* Alerts */
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 500; display: flex; align-items: center; gap: 12px; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; }

        /* Modals */
        .modal { position: fixed; inset: 0; background: rgba(17, 24, 39, 0.6); z-index: 9999; display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 20px; }
        .modal.is-active { display: flex; animation: fadeIn 0.2s ease-out; }
        .modal-content { background: #fff; width: 100%; max-width: 500px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); overflow: hidden; transform: scale(0.95); transition: transform 0.2s; display: flex; flex-direction: column; max-height: 90vh; }
        .modal.is-active .modal-content { transform: scale(1); }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; }
        .modal-title { margin: 0; font-size: 1.25rem; font-weight: 700; color: #111827; }
        .close-btn { background: #f3f4f6; border: none; width: 32px; height: 32px; border-radius: 50%; color: #6b7280; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
        .close-btn:hover { background: #e5e7eb; color: #111827; }
        .modal-body { padding: 24px; overflow-y: auto; }
        .modal-footer { padding: 20px 24px; border-top: 1px solid #f3f4f6; background: #f9fafb; display: flex; justify-content: flex-end; gap: 12px; }
        
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 8px; color: #374151; }
        .form-input { width: 100%; padding: 12px 16px; background: #fff; border: 1px solid #d1d5db; border-radius: 8px; color: #111827; font-family: inherit; font-size: 0.95rem; box-sizing: border-box; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-input:focus { outline: none; border-color: #d4af37; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2); }
        .form-hint { font-size: 0.8rem; color: #6b7280; margin-top: 6px; display: block; }
        
        .btn-save { background: #111827; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-save:hover { background: #374151; }
        .btn-cancel { background: #fff; color: #374151; border: 1px solid #d1d5db; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-cancel:hover { background: #f9fafb; }
        .btn-danger-solid { background: #ef4444; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-danger-solid:hover { background: #dc2626; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        @media (max-width: 1024px) {
            .page-wrap { margin-left: 0; padding: 80px 20px 30px; }
            .header-actions { flex-direction: column; align-items: stretch; }
            .filter-group { flex-direction: column; }
            .filter-input { width: 100%; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main class="page-wrap">
        <!-- HEADER -->
        <div style="margin-bottom: 30px;">
            <h1 class="page-title">Inventory Bahan & Alat</h1>
            <p class="page-subtitle">Kelola stok bahan baku, perlengkapan medis, dan catat riwayat pergerakan barang.</p>
        </div>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Item</div>
                    <div class="stat-value"><?= number_format($total_item) ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-yellow">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Stok Menipis</div>
                    <div class="stat-value" style="color: #eab308;"><?= number_format($stok_menipis) ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-red">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Stok Habis</div>
                    <div class="stat-value" style="color: #ef4444;"><?= number_format($stok_habis) ?></div>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <div>
                    <ul style="margin:0; padding-left:15px;">
                        <?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($sukses): ?>
            <div class="alert alert-success">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <div><?= htmlspecialchars($sukses) ?></div>
            </div>
        <?php endif; ?>

        <!-- TABS -->
        <div class="tabs">
            <a href="?tab=barang&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" class="tab-link <?= $active_tab === 'barang' ? 'active' : '' ?>">Daftar Barang</a>
            <a href="?tab=mutasi" class="tab-link <?= $active_tab === 'mutasi' ? 'active' : '' ?>">Riwayat Mutasi (Ledger)</a>
        </div>
        
        <?php if ($active_tab === 'barang'): ?>
            <!-- ACTION & FILTER BAR -->
            <div class="header-actions">
                <form method="GET" class="filter-group">
                    <input type="hidden" name="tab" value="barang">
                    <input type="text" name="search" class="filter-input" placeholder="Cari nama atau kode barang..." value="<?= htmlspecialchars($search) ?>">
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="aman" <?= $status === 'aman' ? 'selected' : '' ?>>Stok Aman</option>
                        <option value="menipis" <?= $status === 'menipis' ? 'selected' : '' ?>>Stok Menipis</option>
                        <option value="habis" <?= $status === 'habis' ? 'selected' : '' ?>>Stok Habis</option>
                    </select>
                    <button type="submit" style="display:none;">Filter</button>
                </form>
                
                <button class="btn-primary" onclick="bukaModalTambah()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    Tambah Barang
                </button>
            </div>

            <!-- TABLE BARANG -->
            <div class="card-table">
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Stok Terkini</th>
                                <th>Status</th>
                                <th>Aksi Stok</th>
                                <th>Opsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($barang)): ?>
                            <tr><td colspan="5" style="text-align:center; padding: 40px; color: #6b7280;">Tidak ada barang yang ditemukan.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($barang as $b): 
                                $status_html = '';
                                if ($b['stok_sekarang'] <= 0) $status_html = '<span class="badge badge-danger">Habis</span>';
                                else if ($b['stok_sekarang'] <= $b['stok_minimal']) $status_html = '<span class="badge badge-warning">Menipis</span>';
                                else $status_html = '<span class="badge badge-success">Aman</span>';
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #111827; margin-bottom: 2px;"><?= htmlspecialchars($b['nama']) ?></div>
                                    <div style="font-family: monospace; font-size: 0.8rem; color: #6b7280;"><?= htmlspecialchars($b['kode']) ?></div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: baseline; gap: 6px;">
                                        <strong style="font-size: 1.25rem; color: #111827; font-weight: 800;"><?= number_format($b['stok_sekarang'], 0, ',', '.') ?></strong>
                                        <span style="color: #6b7280; font-size: 0.85rem; font-weight: 500;"><?= htmlspecialchars($b['satuan']) ?></span>
                                    </div>
                                    <?php if($b['stok_minimal'] > 0): ?>
                                    <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 2px;">Min: <?= number_format($b['stok_minimal']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= $status_html ?></td>
                                <td>
                                    <div class="actions-group">
                                        <button class="btn-action btn-ghost-blue" onclick="bukaModalStok(<?= $b['id'] ?>, '<?= htmlspecialchars($b['nama']) ?>', 'masuk')">+ In</button>
                                        <button class="btn-action btn-ghost-red" onclick="bukaModalStok(<?= $b['id'] ?>, '<?= htmlspecialchars($b['nama']) ?>', 'keluar')">- Out</button>
                                    </div>
                                </td>
                                <td>
                                    <div class="actions-group">
                                        <button class="btn-action btn-ghost-gray" onclick="bukaModalEdit(<?= $b['id'] ?>, '<?= htmlspecialchars($b['nama']) ?>', '<?= htmlspecialchars($b['satuan']) ?>', <?= $b['stok_minimal'] ?>)">Edit</button>
                                        <button class="btn-action btn-ghost-red" style="border:none;" onclick="bukaModalHapus(<?= $b['id'] ?>, '<?= htmlspecialchars($b['nama']) ?>')">
                                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        <?php else: // MUTASI TAB ?>
            <div class="card-table">
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Barang</th>
                                <th>Tipe</th>
                                <th>Qty</th>
                                <th>Sisa</th>
                                <th>Keterangan</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($riwayat)): ?>
                            <tr><td colspan="7" style="text-align:center; padding: 40px; color: #6b7280;">Belum ada riwayat mutasi stok.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($riwayat as $r): 
                                $tipe_badge = '';
                                $qty_color = '';
                                $sign = '';
                                if ($r['tipe'] === 'masuk') {
                                    $tipe_badge = '<span class="badge badge-success">Masuk</span>';
                                    $qty_color = '#10b981'; $sign = '+';
                                } elseif ($r['tipe'] === 'keluar') {
                                    $tipe_badge = '<span class="badge badge-danger">Keluar</span>';
                                    $qty_color = '#ef4444'; $sign = '-';
                                } elseif ($r['tipe'] === 'terpakai') {
                                    $tipe_badge = '<span class="badge badge-info">Terpakai (POS)</span>';
                                    $qty_color = '#ef4444'; $sign = '-';
                                } else {
                                    $tipe_badge = '<span class="badge badge-gray">Adjust</span>';
                                    $qty_color = '#6b7280';
                                }
                            ?>
                            <tr>
                                <td style="font-size: 0.85rem; color: #6b7280; white-space: nowrap;">
                                    <?= date('d M Y', strtotime($r['created_at'])) ?><br>
                                    <strong style="color: #374151;"><?= date('H:i', strtotime($r['created_at'])) ?></strong>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #111827;"><?= htmlspecialchars($r['nama_barang']) ?></div>
                                    <div style="font-family: monospace; font-size: 0.75rem; color: #9ca3af;"><?= htmlspecialchars($r['kode']) ?></div>
                                </td>
                                <td><?= $tipe_badge ?></td>
                                <td style="font-weight: 800; font-size: 1.1rem; color: <?= $qty_color ?>;">
                                    <?= $sign . number_format($r['jumlah']) ?>
                                </td>
                                <td style="font-weight: 700; color: #374151;"><?= number_format($r['stok_akhir']) ?></td>
                                <td style="font-size: 0.85rem; color: #4b5563; max-width: 250px; line-height: 1.4;">
                                    <?= htmlspecialchars($r['keterangan'] ?? '-') ?>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <div style="width:24px; height:24px; background:#f3f4f6; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.7rem; font-weight:700; color:#6b7280;">
                                            <?= strtoupper(substr($r['username'] ?? '?', 0, 1)) ?>
                                        </div>
                                        <span style="font-size:0.85rem; font-weight:500;"><?= htmlspecialchars($r['username'] ?? 'System') ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- MODAL TAMBAH/EDIT -->
    <div class="modal" id="modalForm">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Tambah Barang Baru</h3>
                <button type="button" class="close-btn" onclick="tutupModal('modalForm')">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <form method="POST" action="inventory.php">
                <div class="modal-body">
                    <input type="hidden" name="aksi" id="formAksi" value="tambah">
                    <input type="hidden" name="id" id="formId" value="0">
                    
                    <div class="form-group" id="groupKode">
                        <label class="form-label">Kode Barang</label>
                        <input type="text" name="kode" id="formKode" class="form-input" required placeholder="Contoh: BTX-01">
                        <span class="form-hint">Kode unik untuk identifikasi barang. Tidak bisa diubah nanti.</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nama Barang</label>
                        <input type="text" name="nama" id="formNama" class="form-input" required placeholder="Contoh: Botox Allergan 100u">
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                        <div class="form-group">
                            <label class="form-label">Satuan</label>
                            <input type="text" name="satuan" id="formSatuan" class="form-input" required placeholder="Pcs, Ampul, Gram" value="Pcs">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stok Minimal</label>
                            <input type="number" name="stok_minimal" id="formMinimal" class="form-input" required min="0" value="0">
                            <span class="form-hint">Muncul peringatan jika stok ≤ nilai ini.</span>
                        </div>
                    </div>

                    <div class="form-group" id="groupStokAwal">
                        <label class="form-label">Stok Awal</label>
                        <input type="number" name="stok_awal" id="formAwal" class="form-input" min="0" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="tutupModal('modalForm')">Batal</button>
                    <button type="submit" class="btn-save">Simpan Barang</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- MODAL IN/OUT STOK -->
    <div class="modal" id="modalStok">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="stokTitle">Sesuaikan Stok</h3>
                <button type="button" class="close-btn" onclick="tutupModal('modalStok')">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <form method="POST" action="inventory.php">
                <div class="modal-body">
                    <input type="hidden" name="aksi" value="adjust_stok">
                    <input type="hidden" name="id" id="stokId" value="">
                    <input type="hidden" name="tipe" id="stokTipe" value="">
                    
                    <div style="background: #f3f4f6; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                        <span style="font-size: 0.8rem; color: #6b7280; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Barang</span>
                        <div style="font-size: 1.1rem; font-weight: 700; color: #111827; margin-top: 4px;" id="stokNamaBarang"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" id="labelJumlah">Jumlah</label>
                        <input type="number" name="jumlah" id="stokJumlah" class="form-input" required min="1" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Keterangan (Wajib/Opsional)</label>
                        <input type="text" name="keterangan" class="form-input" placeholder="Misal: Restock dari supplier X / Kedaluwarsa">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="tutupModal('modalStok')">Batal</button>
                    <button type="submit" class="btn-save" id="btnStok">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL HAPUS -->
    <div class="modal" id="modalHapus">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title" style="color: #ef4444; display: flex; align-items: center; gap: 10px;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    Hapus Barang
                </h3>
            </div>
            <form method="POST" action="inventory.php">
                <div class="modal-body">
                    <p style="color: #4b5563; font-size: 0.95rem; margin: 0; line-height: 1.5;">Apakah Anda yakin ingin menghapus barang <strong id="hapusNama" style="color: #111827;"></strong> secara permanen dari inventory? Tindakan ini tidak dapat dibatalkan.</p>
                    
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" id="hapusId" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="tutupModal('modalHapus')">Batal</button>
                    <button type="submit" class="btn-danger-solid">Ya, Hapus</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function tutupModal(id) { 
            document.getElementById(id).classList.remove('is-active'); 
        }
        
        function bukaModalTambah() {
            document.getElementById('modalTitle').textContent = 'Tambah Barang Baru';
            document.getElementById('formAksi').value = 'tambah';
            document.getElementById('formId').value = '0';
            document.getElementById('formKode').value = '';
            document.getElementById('formKode').readOnly = false;
            document.getElementById('groupKode').style.display = 'block';
            document.getElementById('groupStokAwal').style.display = 'block';
            document.getElementById('formNama').value = '';
            document.getElementById('formSatuan').value = 'Pcs';
            document.getElementById('formMinimal').value = '0';
            document.getElementById('modalForm').classList.add('is-active');
        }
        
        function bukaModalEdit(id, nama, satuan, minimal) {
            document.getElementById('modalTitle').textContent = 'Edit Barang';
            document.getElementById('formAksi').value = 'edit';
            document.getElementById('formId').value = id;
            document.getElementById('groupKode').style.display = 'none'; 
            document.getElementById('groupStokAwal').style.display = 'none'; 
            document.getElementById('formKode').readOnly = true;
            document.getElementById('formKode').required = false;
            
            document.getElementById('formNama').value = nama;
            document.getElementById('formSatuan').value = satuan;
            document.getElementById('formMinimal').value = minimal;
            document.getElementById('modalForm').classList.add('is-active');
        }
        
        function bukaModalStok(id, nama, tipe) {
            document.getElementById('stokId').value = id;
            document.getElementById('stokTipe').value = tipe;
            document.getElementById('stokNamaBarang').textContent = nama;
            
            const btnStok = document.getElementById('btnStok');
            if (tipe === 'masuk') {
                document.getElementById('stokTitle').textContent = 'Barang Masuk (+)';
                document.getElementById('labelJumlah').textContent = 'Jumlah Ditambahkan';
                btnStok.style.background = '#3b82f6';
                btnStok.textContent = 'Simpan Barang Masuk';
            } else {
                document.getElementById('stokTitle').textContent = 'Barang Keluar (-)';
                document.getElementById('labelJumlah').textContent = 'Jumlah Dikeluarkan';
                btnStok.style.background = '#ef4444';
                btnStok.textContent = 'Simpan Barang Keluar';
            }
            
            document.getElementById('stokJumlah').value = '1';
            document.getElementById('modalStok').classList.add('is-active');
        }

        function bukaModalHapus(id, nama) {
            document.getElementById('hapusId').value = id;
            document.getElementById('hapusNama').textContent = nama;
            document.getElementById('modalHapus').classList.add('is-active');
        }
    </script>
</body>
</html>
