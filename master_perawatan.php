<?php
// ============================================================
//  master_perawatan.php — CRUD Master Perawatan per Divisi
// ============================================================
require_once 'koneksi.php';
require_login();

$page_title  = 'Master Perawatan';
$active_menu = 'master';

// --- Ambil semua divisi ---
$divisi_list = [];
$rd = $koneksi->query('SELECT id, kode, nama, warna FROM divisi ORDER BY urutan');
while ($row = $rd->fetch_assoc()) $divisi_list[] = $row;

$errors = [];
$sukses = '';

// ============================================================
//  PROSES POST: tambah / edit / hapus / toggle aktif
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // --- TAMBAH ---
    if ($aksi === 'tambah') {
        $divisi_id  = (int)($_POST['divisi_id'] ?? 0);
        $nama       = trim($_POST['nama'] ?? '');
        $deskripsi  = trim($_POST['deskripsi'] ?? '');

        if ($divisi_id <= 0)   $errors[] = 'Pilih divisi.';
        if ($nama === '')       $errors[] = 'Nama perawatan wajib diisi.';
        elseif (mb_strlen($nama) > 200) $errors[] = 'Nama terlalu panjang.';

        if (empty($errors)) {
            // urutan = max+1 dalam divisi yang sama
            $stmt_max = $koneksi->prepare(
                'SELECT COALESCE(MAX(urutan),0)+1 FROM master_perawatan WHERE divisi_id=?'
            );
            $stmt_max->bind_param('i', $divisi_id);
            $stmt_max->execute();
            $urutan = $stmt_max->get_result()->fetch_row()[0];
            $stmt_max->close();

            $stmt = $koneksi->prepare(
                'INSERT INTO master_perawatan (divisi_id,nama,deskripsi,urutan) VALUES (?,?,?,?)'
            );
            $desk_val = $deskripsi ?: null;
            $stmt->bind_param('issi', $divisi_id, $nama, $desk_val, $urutan);
            if ($stmt->execute()) {
                $sukses = 'Perawatan berhasil ditambahkan.';
            } else {
                $errors[] = 'Gagal menyimpan: ' . $stmt->error;
            }
            $stmt->close();
        }
    }

    // --- EDIT ---
    if ($aksi === 'edit') {
        $id         = (int)($_POST['id'] ?? 0);
        $divisi_id  = (int)($_POST['divisi_id'] ?? 0);
        $nama       = trim($_POST['nama'] ?? '');
        $deskripsi  = trim($_POST['deskripsi'] ?? '');

        if ($id <= 0)           $errors[] = 'ID tidak valid.';
        if ($divisi_id <= 0)    $errors[] = 'Pilih divisi.';
        if ($nama === '')       $errors[] = 'Nama perawatan wajib diisi.';

        if (empty($errors)) {
            $stmt = $koneksi->prepare(
                'UPDATE master_perawatan SET divisi_id=?,nama=?,deskripsi=? WHERE id=?'
            );
            $desk_val = $deskripsi ?: null;
            $stmt->bind_param('issi', $divisi_id, $nama, $desk_val, $id);
            if ($stmt->execute()) {
                $sukses = 'Perawatan berhasil diperbarui.';
            } else {
                $errors[] = 'Gagal memperbarui: ' . $stmt->error;
            }
            $stmt->close();
        }
    }

    // --- HAPUS ---
    if ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // cek apakah sudah dipakai di riwayat_treatment
            $chk = $koneksi->prepare(
                'SELECT COUNT(*) FROM riwayat_treatment rt
                 JOIN master_perawatan mp ON mp.nama = rt.nama_treatment
                 WHERE mp.id = ?'
            );
            $chk->bind_param('i', $id);
            $chk->execute();
            $used = $chk->get_result()->fetch_row()[0];
            $chk->close();

            if ($used > 0) {
                $errors[] = 'Tidak bisa dihapus — perawatan ini sudah digunakan di '.$used.' riwayat treatment.';
            } else {
                $stmt = $koneksi->prepare('DELETE FROM master_perawatan WHERE id=?');
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) $sukses = 'Perawatan berhasil dihapus.';
                else $errors[] = 'Gagal menghapus.';
                $stmt->close();
            }
        }
    }

    // --- TOGGLE AKTIF ---
    if ($aksi === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $koneksi->prepare(
                'UPDATE master_perawatan SET aktif = 1 - aktif WHERE id = ?'
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $sukses = 'Status berhasil diubah.';
        }
    }
}

// --- Ambil perawatan dikelompokkan per divisi ---
$perawatan_per_divisi = [];
foreach ($divisi_list as $d) {
    $stmt = $koneksi->prepare(
        'SELECT id, nama, deskripsi, aktif, urutan FROM master_perawatan
         WHERE divisi_id = ? ORDER BY urutan ASC, nama ASC'
    );
    $stmt->bind_param('i', $d['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    $perawatan_per_divisi[$d['id']] = $rows;
}

// Tab aktif dari query string
$active_tab = (int)($_GET['tab'] ?? $divisi_list[0]['id'] ?? 1);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Rekam Medis Klinik</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* ---- Tab divisi ---- */
        .divisi-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .divisi-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 20px;
            border-radius: 50px;
            font-size: .86rem;
            font-weight: 700;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all .2s;
            text-decoration: none;
            background: #f0f0f5;
            color: #666;
        }

        .divisi-tab .tab-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: currentColor;
            opacity: .4;
        }

        .divisi-tab.active-oz  { background: var(--primary-100); color: var(--primary); border-color: var(--primary-200); }
        .divisi-tab.active-oz .tab-dot  { opacity: 1; background: var(--primary); }

        .divisi-tab.active-hair { background: #ede7f6; color: #7c4dff; border-color: #d1c4e9; }
        .divisi-tab.active-hair .tab-dot { opacity: 1; background: #7c4dff; }

        /* ---- Tabel master ---- */
        .master-table { width: 100%; border-collapse: collapse; }

        .master-table thead th {
            background: #f8f8fc;
            padding: 10px 14px;
            text-align: left;
            font-size: .78rem;
            font-weight: 700;
            color: #888;
            letter-spacing: .5px;
            text-transform: uppercase;
            border-bottom: 2px solid var(--border);
        }

        .master-table tbody tr {
            border-bottom: 1px solid #f5f5f5;
            transition: background .15s;
        }

        .master-table tbody tr:hover { background: #fafafa; }

        .master-table tbody td {
            padding: 11px 14px;
            font-size: .88rem;
            vertical-align: middle;
        }

        .badge-aktif {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: .72rem;
            font-weight: 700;
        }

        .badge-aktif.on  { background: #e8f5e9; color: #2e7d32; }
        .badge-aktif.off { background: #fafafa; color: #bbb; border: 1px solid #eee; }

        .badge-divisi {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: .72rem;
            font-weight: 700;
        }

        .badge-divisi .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
        }

        .tbl-actions { display: flex; gap: 6px; }

        .btn-edit   { background: #e8f0fe; color: #1a73e8; border: none; }
        .btn-edit:hover { background: #c8dafb; }

        .btn-hapus  { background: #fce8e6; color: #d32f2f; border: none; }
        .btn-hapus:hover { background: #fad2cf; }

        .btn-toggle { background: #f0f0f5; color: #666; border: none; }
        .btn-toggle:hover { background: #e0e0eb; }

        /* ---- Form tambah / edit ---- */
        .form-tambah-wrap {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 22px;
            align-items: start;
        }

        .add-card {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            padding: 22px 24px;
        }

        .add-card h3 {
            font-size: .95rem;
            font-weight: 700;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ---- Stat kecil ---- */
        .divisi-stat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 0;
        }

        .divisi-stat {
            border-radius: var(--radius-sm);
            padding: 14px 16px;
            text-align: center;
        }

        .divisi-stat .val { font-size: 1.4rem; font-weight: 700; }
        .divisi-stat .lbl { font-size: .72rem; margin-top: 2px; opacity: .75; }

        .ds-oz   { background: var(--primary-100); color: var(--primary-dark); }
        .ds-hair { background: #ede7f6; color: #6a1b9a; }

        /* ---- Modal edit (sederhana) ---- */
        .modal-backdrop {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.5);
            z-index: 400;
            align-items: center;
            justify-content: center;
        }

        .modal-backdrop.show { display: flex; }

        .modal-box {
            background: #fff;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            padding: 28px 26px;
            width: 100%;
            max-width: 460px;
        }

        .modal-box h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
        }

        .modal-actions { display: flex; gap: 10px; margin-top: 20px; }

        @media (max-width: 860px) {
            .form-tambah-wrap { grid-template-columns: 1fr; }
        }

        @media (max-width: 600px) {
            .tbl-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h1>Master Perawatan</h1>
            <p>Kelola daftar perawatan untuk setiap divisi klinik</p>
        </div>
    </div>

    <!-- Alert -->
    <?php if ($sukses): ?>
        <div class="alert alert-success" role="alert" style="margin-bottom:18px">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48
                 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg>
            <?= htmlspecialchars($sukses) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert" style="margin-bottom:18px">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48
                 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            <div><ul><?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?></ul></div>
        </div>
    <?php endif; ?>

    <div class="form-tambah-wrap">

        <!-- Kiri: Tabel per divisi -->
        <div>
            <!-- Tab divisi -->
            <div class="divisi-tabs">
                <?php foreach ($divisi_list as $d):
                    $is_oz   = $d['kode'] === 'ozthetique';
                    $cls_tab = ($active_tab == $d['id'])
                        ? ($is_oz ? 'active-oz' : 'active-hair')
                        : '';
                    $total   = count($perawatan_per_divisi[$d['id']] ?? []);
                ?>
                    <a href="?tab=<?= $d['id'] ?>"
                       class="divisi-tab <?= $cls_tab ?>">
                        <span class="tab-dot" style="background:<?= htmlspecialchars($d['warna']) ?>"></span>
                        <?= htmlspecialchars($d['nama']) ?>
                        <span style="font-size:.7rem;opacity:.7">(<?= $total ?>)</span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Tabel perawatan tab aktif -->
            <?php foreach ($divisi_list as $d):
                if ($d['id'] != $active_tab) continue;
                $is_oz = $d['kode'] === 'ozthetique';
                $rows  = $perawatan_per_divisi[$d['id']] ?? [];
                $warna = $d['warna'];
            ?>
                <div class="card" style="padding:0;overflow:hidden;">
                    <div style="padding:16px 20px;border-bottom:1px solid var(--border);
                                display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:10px;height:10px;border-radius:50%;
                                        background:<?= htmlspecialchars($warna) ?>"></div>
                            <span style="font-weight:700;font-size:.95rem">
                                <?= htmlspecialchars($d['nama']) ?>
                            </span>
                            <span style="font-size:.78rem;color:#aaa">
                                — <?= count($rows) ?> perawatan
                            </span>
                        </div>
                    </div>

                    <?php if (empty($rows)): ?>
                        <div class="empty-state" style="padding:32px 20px;">
                            <div class="empty-state-icon">
                                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9
                                     2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5
                                     1.4-1.4L12 14.2l7.6-7.6L21 8l-9 9z"/></svg>
                            </div>
                            <h3>Belum ada perawatan</h3>
                            <p>Tambahkan menggunakan form di sebelah kanan.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="master-table">
                                <thead>
                                    <tr>
                                        <th style="width:40px">#</th>
                                        <th>Nama Perawatan</th>
                                        <th>Deskripsi</th>
                                        <th style="width:90px">Status</th>
                                        <th style="width:160px">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $i => $p): ?>
                                    <tr>
                                        <td style="color:#ccc;font-size:.8rem"><?= $p['urutan'] ?></td>
                                        <td style="font-weight:600"><?= htmlspecialchars($p['nama']) ?></td>
                                        <td style="color:#888;font-size:.83rem">
                                            <?= $p['deskripsi']
                                                ? htmlspecialchars(mb_substr($p['deskripsi'], 0, 60)).(mb_strlen($p['deskripsi']) > 60 ? '…' : '')
                                                : '<span style="color:#ddd">—</span>' ?>
                                        </td>
                                        <td>
                                            <span class="badge-aktif <?= $p['aktif'] ? 'on' : 'off' ?>">
                                                <?= $p['aktif'] ? 'Aktif' : 'Nonaktif' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="tbl-actions">
                                                <button type="button"
                                                    class="btn btn-sm btn-edit"
                                                    onclick="bukaEdit(<?= $p['id'] ?>,<?= $d['id'] ?>,
                                                        '<?= addslashes(htmlspecialchars($p['nama'])) ?>',
                                                        '<?= addslashes(htmlspecialchars($p['deskripsi'] ?? '')) ?>')">
                                                    Edit
                                                </button>
                                                <form method="POST" style="display:inline"
                                                      onsubmit="return confirm('Ubah status perawatan ini?')">
                                                    <input type="hidden" name="aksi" value="toggle">
                                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-toggle">
                                                        <?= $p['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display:inline"
                                                      onsubmit="return confirm('Hapus perawatan ini?')">
                                                    <input type="hidden" name="aksi" value="hapus">
                                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-hapus">Hapus</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Kanan: Form tambah + statistik -->
        <div>
            <!-- Form Tambah -->
            <div class="add-card">
                <h3>
                    <svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:var(--primary)">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                    Tambah Perawatan
                </h3>
                <form method="POST" action="master_perawatan.php?tab=<?= $active_tab ?>" novalidate>
                    <input type="hidden" name="aksi" value="tambah">

                    <div class="form-group">
                        <label class="form-label" for="divisi_id">
                            Divisi <span class="req">*</span>
                        </label>
                        <select name="divisi_id" id="divisi_id" class="form-control" required>
                            <option value="">— Pilih divisi —</option>
                            <?php foreach ($divisi_list as $d): ?>
                                <option value="<?= $d['id'] ?>"
                                    <?= ($d['id'] == $active_tab) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="nama_baru">
                            Nama Perawatan <span class="req">*</span>
                        </label>
                        <input type="text" id="nama_baru" name="nama"
                               class="form-control" maxlength="200"
                               placeholder="Contoh: Facial Glow" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="deskripsi_baru">Deskripsi</label>
                        <textarea id="deskripsi_baru" name="deskripsi"
                                  class="form-control" rows="3"
                                  placeholder="Keterangan singkat (opsional)"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">
                        <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                        Simpan Perawatan
                    </button>
                </form>
            </div>

            <!-- Statistik divisi -->
            <div class="add-card" style="margin-top:16px;">
                <h3 style="font-size:.88rem;color:#888;border-bottom:1px solid var(--border);margin-bottom:12px;padding-bottom:8px;">
                    📊 Statistik
                </h3>
                <div class="divisi-stat-grid">
                    <?php foreach ($divisi_list as $d):
                        $rows = $perawatan_per_divisi[$d['id']] ?? [];
                        $aktif_count = count(array_filter($rows, fn($r) => $r['aktif']));
                        $cls = $d['kode'] === 'ozthetique' ? 'ds-oz' : 'ds-hair';
                    ?>
                        <div class="divisi-stat <?= $cls ?>">
                            <div class="val"><?= $aktif_count ?> / <?= count($rows) ?></div>
                            <div class="lbl"><?= htmlspecialchars($d['nama']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div><!-- /form-tambah-wrap -->
</div><!-- /main-content -->

<!-- Modal Edit -->
<div class="modal-backdrop" id="modalEdit">
    <div class="modal-box">
        <h3>✏️ Edit Perawatan</h3>
        <form method="POST" action="master_perawatan.php?tab=<?= $active_tab ?>" novalidate>
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" id="edit_id">

            <div class="form-group">
                <label class="form-label">Divisi <span class="req">*</span></label>
                <select name="divisi_id" id="edit_divisi_id" class="form-control" required>
                    <?php foreach ($divisi_list as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Nama Perawatan <span class="req">*</span></label>
                <input type="text" name="nama" id="edit_nama"
                       class="form-control" maxlength="200" required>
            </div>

            <div class="form-group">
                <label class="form-label">Deskripsi</label>
                <textarea name="deskripsi" id="edit_deskripsi"
                          class="form-control" rows="3"></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="tutupModal()">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');
    toggleBtn.addEventListener('click', function () {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    });
    overlay.addEventListener('click', function () {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    });
}());

function bukaEdit(id, divisiId, nama, deskripsi) {
    document.getElementById('edit_id').value        = id;
    document.getElementById('edit_divisi_id').value = divisiId;
    document.getElementById('edit_nama').value      = nama;
    document.getElementById('edit_deskripsi').value = deskripsi;
    document.getElementById('modalEdit').classList.add('show');
}

function tutupModal() {
    document.getElementById('modalEdit').classList.remove('show');
}

document.getElementById('modalEdit').addEventListener('click', function (e) {
    if (e.target === this) tutupModal();
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') tutupModal();
});
</script>
</body>
</html>
