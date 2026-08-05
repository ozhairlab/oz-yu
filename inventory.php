<?php
// ============================================================
//  inventory.php — Manajemen Stok Bahan (Inventory)
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
            // Cek duplikat kode
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
                // Kunci baris untuk mencegah race condition
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
            // Cek apakah barang ini dipakai di komposisi perawatan
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

// Ambil data inventory
$barang = [];
$res = $koneksi->query('SELECT * FROM inventory_barang ORDER BY nama ASC');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $barang[] = $row;
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
    <style>
        .page-wrap { padding: 30px; margin-left: 260px; }
        .header-action { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .page-title { font-size: 1.8rem; margin: 0; font-weight: 700; letter-spacing: -0.03em; }
        
        .btn {
            background: linear-gradient(135deg, var(--gold), #a07840);
            color: #0a0a0f; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem; transition: transform 0.2s;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .btn-danger { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
        .btn-danger:hover { background: rgba(239,68,68,0.2); }
        .btn-ghost { background: transparent; color: var(--text); border: 1px solid var(--border); }
        .btn-ghost:hover { background: var(--surface-2); }
        
        .table-wrap {
            background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); overflow: hidden; box-shadow: var(--shadow-sm);
        }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px 20px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: var(--surface-2); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); }
        td { font-size: 0.95rem; color: var(--text); }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(0,0,0,0.02); }
        
        .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .badge-danger { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
        .badge-success { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.2); }
        
        .actions { display: flex; gap: 8px; }
        
        /* Modal CSS */
        .modal {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999;
            display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px);
        }
        .modal.is-active { display: flex; }
        .modal-content {
            background: var(--surface); width: 100%; max-width: 450px; border-radius: var(--radius-md); border: 1px solid var(--border); padding: 30px; position: relative; max-height: 90vh; overflow-y: auto;
        }
        .modal-title { margin: 0 0 20px; font-size: 1.2rem; }
        .close-btn { position: absolute; right: 20px; top: 20px; background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.5rem; }
        
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 0.8rem; margin-bottom: 6px; color: var(--text-muted); }
        .form-input {
            width: 100%; padding: 12px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: inherit; font-size: 0.95rem; box-sizing: border-box;
        }
        .form-input:focus { outline: none; border-color: var(--gold); }
        select.form-input { appearance: none; }
        
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
        .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; }
        .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #10b981; }
        
        @media (max-width: 768px) {
            .page-wrap { margin-left: 0; padding: 20px; margin-top: 60px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main class="page-wrap">
        <div class="header-action">
            <h1 class="page-title">Inventory Bahan & Alat</h1>
            <button class="btn" onclick="bukaModalTambah()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Tambah Barang
            </button>
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
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Barang</th>
                        <th>Sisa Stok</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($barang)): ?>
                    <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">Belum ada barang di inventory.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($barang as $b): 
                        $status = '';
                        if ($b['stok_sekarang'] <= 0) $status = '<span class="badge badge-danger">Habis</span>';
                        else if ($b['stok_sekarang'] <= $b['stok_minimal']) $status = '<span class="badge badge-danger">Menipis</span>';
                        else $status = '<span class="badge badge-success">Aman</span>';
                    ?>
                    <tr>
                        <td style="font-family:monospace; color:var(--text-muted);"><?= htmlspecialchars($b['kode']) ?></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($b['nama']) ?></td>
                        <td>
                            <strong style="font-size:1.1rem; color:var(--primary);"><?= number_format($b['stok_sekarang'], 0, ',', '.') ?></strong>
                            <small style="color:var(--text-muted);"><?= htmlspecialchars($b['satuan']) ?></small>
                        </td>
                        <td><?= $status ?></td>
                        <td>
                            <div class="actions">
                                <button class="btn btn-sm btn-ghost" style="color:#3b82f6;" onclick="bukaModalStok(<?= $b['id'] ?>, '<?= htmlspecialchars($b['nama']) ?>', 'masuk')">+ In</button>
                                <button class="btn btn-sm btn-ghost" style="color:#ef4444;" onclick="bukaModalStok(<?= $b['id'] ?>, '<?= htmlspecialchars($b['nama']) ?>', 'keluar')">- Out</button>
                                <button class="btn btn-sm btn-ghost" onclick="bukaModalEdit(<?= $b['id'] ?>, '<?= htmlspecialchars($b['nama']) ?>', '<?= htmlspecialchars($b['satuan']) ?>', <?= $b['stok_minimal'] ?>)">Edit</button>
                                <button class="btn btn-sm btn-danger" onclick="bukaModalHapus(<?= $b['id'] ?>, '<?= htmlspecialchars($b['nama']) ?>')">Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    
    <!-- Modal Tambah/Edit -->
    <div class="modal" id="modalForm">
        <div class="modal-content">
            <button class="close-btn" onclick="tutupModal('modalForm')">&times;</button>
            <h3 class="modal-title" id="modalTitle">Tambah Barang</h3>
            <form method="POST" action="inventory.php">
                <input type="hidden" name="aksi" id="formAksi" value="tambah">
                <input type="hidden" name="id" id="formId" value="0">
                
                <div class="form-group" id="groupKode">
                    <label class="form-label">Kode Barang</label>
                    <input type="text" name="kode" id="formKode" class="form-input" required placeholder="Contoh: BTX-01">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nama Barang</label>
                    <input type="text" name="nama" id="formNama" class="form-input" required placeholder="Contoh: Botox Allergan 100u">
                </div>
                
                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">Satuan</label>
                        <input type="text" name="satuan" id="formSatuan" class="form-input" required placeholder="Contoh: Unit, Ampul, Pcs" value="Pcs">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">Stok Minimal</label>
                        <input type="number" name="stok_minimal" id="formMinimal" class="form-input" required min="0" value="0">
                    </div>
                </div>

                <div class="form-group" id="groupStokAwal">
                    <label class="form-label">Stok Awal</label>
                    <input type="number" name="stok_awal" id="formAwal" class="form-input" min="0" value="0">
                </div>
                
                <button type="submit" class="btn" style="width:100%; justify-content:center; margin-top:10px;">Simpan Barang</button>
            </form>
        </div>
    </div>
    
    <!-- Modal In/Out Stok -->
    <div class="modal" id="modalStok">
        <div class="modal-content">
            <button class="close-btn" onclick="tutupModal('modalStok')">&times;</button>
            <h3 class="modal-title" id="stokTitle">Sesuaikan Stok</h3>
            <form method="POST" action="inventory.php">
                <input type="hidden" name="aksi" value="adjust_stok">
                <input type="hidden" name="id" id="stokId" value="">
                <input type="hidden" name="tipe" id="stokTipe" value="">
                
                <p style="margin-bottom:15px; color:var(--text); font-weight:600;" id="stokNamaBarang"></p>
                
                <div class="form-group">
                    <label class="form-label" id="labelJumlah">Jumlah</label>
                    <input type="number" name="jumlah" id="stokJumlah" class="form-input" required min="1" value="1">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Keterangan (Opsional)</label>
                    <input type="text" name="keterangan" class="form-input" placeholder="Alasan penyesuaian...">
                </div>
                
                <button type="submit" class="btn" id="btnStok" style="width:100%; justify-content:center; margin-top:10px;">Simpan</button>
            </form>
        </div>
    </div>

    <!-- Modal Hapus -->
    <div class="modal" id="modalHapus">
        <div class="modal-content">
            <button class="close-btn" onclick="tutupModal('modalHapus')">&times;</button>
            <h3 class="modal-title">Hapus Barang</h3>
            <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:24px;">Apakah Anda yakin ingin menghapus barang <strong id="hapusNama" style="color:var(--text);"></strong>?</p>
            <form method="POST" action="inventory.php" style="display:flex; gap:10px;">
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" id="hapusId" value="">
                <button type="button" class="btn btn-ghost" style="flex:1; justify-content:center;" onclick="tutupModal('modalHapus')">Batal</button>
                <button type="submit" class="btn btn-danger" style="flex:1; justify-content:center;">Ya, Hapus</button>
            </form>
        </div>
    </div>
    
    <script>
        function tutupModal(id) { document.getElementById(id).classList.remove('is-active'); }
        
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
            document.getElementById('groupKode').style.display = 'none'; // tidak boleh edit kode
            document.getElementById('groupStokAwal').style.display = 'none'; // edit tidak merubah stok awal
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
            
            if (tipe === 'masuk') {
                document.getElementById('stokTitle').textContent = 'Barang Masuk';
                document.getElementById('labelJumlah').textContent = 'Jumlah Masuk';
                document.getElementById('btnStok').style.background = 'var(--gold)';
            } else {
                document.getElementById('stokTitle').textContent = 'Barang Keluar';
                document.getElementById('labelJumlah').textContent = 'Jumlah Keluar';
                document.getElementById('btnStok').style.background = '#ef4444';
                document.getElementById('btnStok').style.color = '#fff';
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
