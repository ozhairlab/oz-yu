<?php
// ============================================================
//  users.php — Manajemen Akses Pengguna (Superadmin)
// ============================================================
require_once 'koneksi.php';
require_role('superadmin');

$page_title  = 'Manajemen Pengguna';
$active_menu = 'users';

$errors = [];
$sukses = '';

// --- PROSES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // TAMBAH USER
    if ($aksi === 'tambah') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'kasir';

        if ($username === '') $errors[] = 'Username wajib diisi.';
        if ($password === '') $errors[] = 'Password wajib diisi.';
        if (!in_array($role, ['superadmin', 'kasir', 'admin_medis'])) $errors[] = 'Role tidak valid.';

        if (empty($errors)) {
            // Cek duplikat username
            $stmt = $koneksi->prepare('SELECT id FROM admin_klinik WHERE username = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = 'Username sudah digunakan.';
            }
            $stmt->close();

            if (empty($errors)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $koneksi->prepare('INSERT INTO admin_klinik (username, password, role) VALUES (?, ?, ?)');
                $stmt->bind_param('sss', $username, $hash, $role);
                if ($stmt->execute()) {
                    $sukses = 'Pengguna berhasil ditambahkan.';
                } else {
                    $errors[] = 'Gagal menyimpan data.';
                }
                $stmt->close();
            }
        }
    }

    // EDIT USER (Role / Reset Password)
    if ($aksi === 'edit') {
        $id       = (int)($_POST['id'] ?? 0);
        $role     = $_POST['role'] ?? 'kasir';
        $password = $_POST['password'] ?? '';

        if ($id <= 0) $errors[] = 'ID tidak valid.';
        if (!in_array($role, ['superadmin', 'kasir', 'admin_medis'])) $errors[] = 'Role tidak valid.';
        
        // Proteksi: jangan sampai mengubah dirinya sendiri menjadi bukan superadmin
        if ($id === (int)$_SESSION['admin_id'] && $role !== 'superadmin') {
            $errors[] = 'Anda tidak dapat menghapus akses superadmin dari akun Anda sendiri.';
        }

        if (empty($errors)) {
            if ($password !== '') {
                // Reset password
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $koneksi->prepare('UPDATE admin_klinik SET role = ?, password = ? WHERE id = ?');
                $stmt->bind_param('ssi', $role, $hash, $id);
            } else {
                // Update role saja
                $stmt = $koneksi->prepare('UPDATE admin_klinik SET role = ? WHERE id = ?');
                $stmt->bind_param('si', $role, $id);
            }
            
            if ($stmt->execute()) {
                $sukses = 'Data pengguna berhasil diperbarui.';
            } else {
                $errors[] = 'Gagal memperbarui data.';
            }
            $stmt->close();
        }
    }

    // HAPUS USER
    if ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            if ($id === (int)$_SESSION['admin_id']) {
                $errors[] = 'Anda tidak dapat menghapus akun Anda sendiri.';
            } else {
                // Cek apakah punya relasi dengan transaksi
                $chk = $koneksi->prepare('SELECT COUNT(*) FROM transaksi WHERE kasir_id = ?');
                $chk->bind_param('i', $id);
                $chk->execute();
                $used = $chk->get_result()->fetch_row()[0];
                $chk->close();

                if ($used > 0) {
                    $errors[] = 'Tidak bisa dihapus, pengguna ini sudah memiliki riwayat transaksi POS.';
                } else {
                    $stmt = $koneksi->prepare('DELETE FROM admin_klinik WHERE id = ?');
                    $stmt->bind_param('i', $id);
                    if ($stmt->execute()) $sukses = 'Pengguna berhasil dihapus.';
                    else $errors[] = 'Gagal menghapus.';
                    $stmt->close();
                }
            }
        }
    }
}

// Ambil daftar pengguna
$users = [];
$res = $koneksi->query('SELECT id, username, role FROM admin_klinik ORDER BY role, username');
while ($row = $res->fetch_assoc()) {
    $users[] = $row;
}

$role_colors = [
    'superadmin'  => ['bg' => 'rgba(239, 68, 68, 0.15)', 'col' => '#fca5a5'],
    'admin_medis' => ['bg' => 'rgba(59, 130, 246, 0.15)', 'col' => '#93c5fd'],
    'kasir'       => ['bg' => 'rgba(16, 185, 129, 0.15)', 'col' => '#6ee7b7']
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> — Ozthetique</title>
    <style>
        /* Gaya dasar diadaptasi dari UI Ozthetique */
        :root {
            --bg: #0a0a0f;
            --surface: #12121a;
            --surface-hover: #1c1c28;
            --border: rgba(255,255,255,0.08);
            --text: #ffffff;
            --muted: rgba(255,255,255,0.5);
            --gold: #c9a96e;
            --radius: 12px;
        }
        
        body { margin: 0; background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; overflow-x: hidden; }
        
        .main-content {
            margin-left: 260px; /* Lebar sidebar */
            padding: 40px;
            min-height: 100vh;
        }
        
        .header-action { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-size: 1.8rem; margin: 0; font-weight: 700; letter-spacing: -0.03em; }
        
        .btn {
            background: linear-gradient(135deg, var(--gold), #a07840);
            color: #0a0a0f;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            transition: transform 0.2s;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .btn-danger { background: rgba(239,68,68,0.1); color: #fca5a5; border: 1px solid rgba(239,68,68,0.2); }
        .btn-danger:hover { background: rgba(239,68,68,0.2); }
        .btn-ghost { background: transparent; color: var(--text); border: 1px solid var(--border); }
        .btn-ghost:hover { background: var(--surface-hover); }
        
        .table-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px 20px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: rgba(255,255,255,0.02); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); }
        td { font-size: 0.95rem; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        
        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .actions { display: flex; gap: 8px; }
        
        /* Modal CSS */
        .modal {
            position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 9999;
            display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px);
        }
        .modal.is-active { display: flex; }
        .modal-content {
            background: var(--surface); width: 100%; max-width: 400px;
            border-radius: var(--radius); border: 1px solid var(--border);
            padding: 30px; position: relative;
        }
        .modal-title { margin: 0 0 20px; font-size: 1.2rem; }
        .close-btn { position: absolute; right: 20px; top: 20px; background: none; border: none; color: var(--muted); cursor: pointer; font-size: 1.5rem; }
        
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 0.8rem; margin-bottom: 6px; color: var(--muted); }
        .form-input {
            width: 100%; padding: 12px; background: var(--bg); border: 1px solid var(--border);
            border-radius: 8px; color: var(--text); font-family: inherit; font-size: 0.95rem;
            box-sizing: border-box;
        }
        .form-input:focus { outline: none; border-color: var(--gold); }
        select.form-input { appearance: none; }
        
        /* Alert */
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
        .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; }
        .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #6ee7b7; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px; margin-top: 60px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content">
        <div class="header-action">
            <h1 class="page-title">Manajemen Pengguna</h1>
            <button class="btn" onclick="bukaModalTambah()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Tambah Pengguna
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
                        <th>Username</th>
                        <th>Peran (Role)</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): 
                        $rc = $role_colors[$u['role']] ?? ['bg'=>'#333', 'col'=>'#fff'];
                    ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($u['username']) ?>
                            <?php if($u['id'] == $_SESSION['admin_id']) echo ' <span style="font-size:0.7rem; color:var(--muted);">(Anda)</span>'; ?>
                        </td>
                        <td>
                            <span class="role-badge" style="background:<?= $rc['bg'] ?>; color:<?= $rc['col'] ?>;">
                                <?= htmlspecialchars(str_replace('_', ' ', $u['role'])) ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="btn btn-sm btn-ghost" onclick="bukaModalEdit(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>', '<?= $u['role'] ?>')">Edit</button>
                                <?php if($u['id'] != $_SESSION['admin_id']): ?>
                                <button class="btn btn-sm btn-danger" onclick="bukaModalHapus(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">Hapus</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    
    <!-- Modal Tambah / Edit -->
    <div class="modal" id="modalForm">
        <div class="modal-content">
            <button class="close-btn" onclick="tutupModal('modalForm')">&times;</button>
            <h3 class="modal-title" id="modalTitle">Tambah Pengguna</h3>
            <form method="POST" action="users.php">
                <input type="hidden" name="aksi" id="formAksi" value="tambah">
                <input type="hidden" name="id" id="formId" value="0">
                
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" id="formUsername" class="form-input" required autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" id="formRole" class="form-input" required>
                        <option value="kasir">Kasir</option>
                        <option value="admin_medis">Admin Medis (Pencatat Treatment)</option>
                        <option value="superadmin">Superadmin (Akses Penuh)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" id="labelPassword">Password</label>
                    <input type="password" name="password" id="formPassword" class="form-input" autocomplete="new-password">
                    <small id="hintPassword" style="color:var(--muted); font-size:0.75rem; display:none; margin-top:4px;">Kosongkan jika tidak ingin mengubah password.</small>
                </div>
                
                <button type="submit" class="btn" style="width:100%; justify-content:center; margin-top:10px;">Simpan</button>
            </form>
        </div>
    </div>
    
    <!-- Modal Hapus -->
    <div class="modal" id="modalHapus">
        <div class="modal-content">
            <button class="close-btn" onclick="tutupModal('modalHapus')">&times;</button>
            <h3 class="modal-title">Hapus Pengguna</h3>
            <p style="color:var(--muted); font-size:0.9rem; margin-bottom:24px;">Apakah Anda yakin ingin menghapus akses untuk pengguna <strong id="hapusNama" style="color:var(--text);"></strong>? Tindakan ini tidak dapat dibatalkan.</p>
            <form method="POST" action="users.php" style="display:flex; gap:10px;">
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
            document.getElementById('modalTitle').textContent = 'Tambah Pengguna';
            document.getElementById('formAksi').value = 'tambah';
            document.getElementById('formId').value = '0';
            document.getElementById('formUsername').value = '';
            document.getElementById('formUsername').readOnly = false;
            document.getElementById('formRole').value = 'kasir';
            document.getElementById('formPassword').required = true;
            document.getElementById('labelPassword').innerHTML = 'Password <span style="color:red">*</span>';
            document.getElementById('hintPassword').style.display = 'none';
            document.getElementById('modalForm').classList.add('is-active');
        }
        
        function bukaModalEdit(id, username, role) {
            document.getElementById('modalTitle').textContent = 'Edit Pengguna';
            document.getElementById('formAksi').value = 'edit';
            document.getElementById('formId').value = id;
            document.getElementById('formUsername').value = username;
            document.getElementById('formUsername').readOnly = true; // Jangan boleh ganti username
            document.getElementById('formRole').value = role;
            document.getElementById('formPassword').required = false;
            document.getElementById('labelPassword').textContent = 'Reset Password (Opsional)';
            document.getElementById('hintPassword').style.display = 'block';
            document.getElementById('modalForm').classList.add('is-active');
        }
        
        function bukaModalHapus(id, username) {
            document.getElementById('hapusId').value = id;
            document.getElementById('hapusNama').textContent = username;
            document.getElementById('modalHapus').classList.add('is-active');
        }
    </script>
</body>
</html>
