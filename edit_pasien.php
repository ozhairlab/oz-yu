<?php
// ============================================================
//  edit_pasien.php — Edit Data Pasien
// ============================================================
require_once 'koneksi.php';
require_login();

$page_title  = 'Edit Pasien';
$active_menu = 'pasien';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: pasien.php'); exit; }

// Ambil data pasien
$stmt = $koneksi->prepare('SELECT * FROM pasien WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$pasien = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$pasien) { header('Location: pasien.php'); exit; }

$errors = [];
$sukses = false;
$old    = $pasien; // isi awal dari DB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama          = trim($_POST['nama']          ?? '');
    $telepon       = trim($_POST['telepon']       ?? '');
    $email         = trim($_POST['email']         ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $sumber_pasien = trim($_POST['sumber_pasien'] ?? '');
    $catatan_crm   = trim($_POST['catatan_crm']   ?? '');

    $old = compact('nama','telepon','email','tanggal_lahir','jenis_kelamin','sumber_pasien','catatan_crm');
    $old['id'] = $id;

    // Validasi
    if ($nama === '')
        $errors[] = 'Nama pasien wajib diisi.';
    elseif (mb_strlen($nama) > 150)
        $errors[] = 'Nama terlalu panjang (maks 150 karakter).';

    if ($telepon !== '' && !preg_match('/^[\d\s\+\-\(\)]{6,20}$/', $telepon))
        $errors[] = 'Format nomor telepon tidak valid.';

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Format email tidak valid.';

    if ($tanggal_lahir !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_lahir))
        $errors[] = 'Format tanggal lahir tidak valid.';

    if (empty($errors)) {
        $telepon_val       = $telepon       ?: null;
        $email_val         = $email         ?: null;
        $tanggal_lahir_val = $tanggal_lahir ?: null;
        $jenis_kelamin_val = in_array($jenis_kelamin, ['P','L']) ? $jenis_kelamin : null;
        $sumber_val        = $sumber_pasien ?: null;
        $catatan_val       = $catatan_crm   ?: null;

        $stmt = $koneksi->prepare(
            'UPDATE pasien SET nama=?, telepon=?, email=?, tanggal_lahir=?,
             jenis_kelamin=?, sumber_pasien=?, catatan_crm=? WHERE id=?'
        );
        $stmt->bind_param('sssssssi',
            $nama, $telepon_val, $email_val, $tanggal_lahir_val,
            $jenis_kelamin_val, $sumber_val, $catatan_val, $id
        );

        if ($stmt->execute()) {
            $stmt->close();
            header('Location: detail_pasien.php?id=' . $id . '&updated=1');
            exit;
        } else {
            $errors[] = 'Gagal menyimpan perubahan. Coba lagi.';
            error_log('Update pasien gagal: ' . $stmt->error);
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Ozthetique</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .edit-layout { display:grid; grid-template-columns:1fr 300px; gap:22px; align-items:start; }

        .input-icon-wrap { position:relative; }
        .input-icon-wrap svg.ic {
            position:absolute; left:13px; top:50%;
            transform:translateY(-50%);
            width:15px; height:15px; fill:var(--text-light);
            pointer-events:none; transition:fill var(--transition);
        }
        .input-icon-wrap:focus-within svg.ic { fill:var(--gold-dark); }
        .input-icon-wrap .form-control { padding-left:40px; }

        .form-aside {
            background:var(--ink-3);
            border-radius:var(--radius-md);
            padding:24px 20px;
            color:#fff;
            border:1px solid rgba(201,169,110,.12);
            position:sticky;
            top:calc(var(--topbar-h) + 16px);
        }

        .aside-h { font-size:.78rem; font-weight:800; color:var(--gold-light);
                   margin-bottom:12px; letter-spacing:.06em; text-transform:uppercase; }

        .aside-info-row { display:flex; flex-direction:column; gap:10px; }

        .aside-info-item { background:rgba(255,255,255,.05); border-radius:10px; padding:12px 14px; }
        .aside-info-label { font-size:.65rem; color:rgba(255,255,255,.35); font-weight:700;
                            text-transform:uppercase; letter-spacing:.1em; margin-bottom:4px; }
        .aside-info-val   { font-size:.88rem; color:#fff; font-weight:600; }

        .jk-group { display:flex; gap:10px; }
        .jk-option { flex:1; }
        .jk-option input[type="radio"] { display:none; }
        .jk-option label {
            display:flex; align-items:center; justify-content:center; gap:7px;
            padding:10px 12px; border-radius:10px;
            border:1.5px solid var(--border); background:var(--surface);
            font-size:.85rem; font-weight:700; color:var(--text-muted);
            cursor:pointer; transition:all var(--transition);
        }
        .jk-option label svg { width:16px; height:16px; fill:currentColor; }
        .jk-option input:checked + label {
            border-color:var(--gold); background:rgba(201,169,110,.08);
            color:var(--gold-dark);
            box-shadow:0 0 0 3px rgba(201,169,110,.12);
        }

        @media (max-width:860px) {
            .edit-layout { grid-template-columns:1fr; }
            .form-aside { position:static; }
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">

    <div class="page-header">
        <div class="page-header-left">
            <h1>Edit Pasien</h1>
            <p>Ubah data profil <strong><?= htmlspecialchars($pasien['nama']) ?></strong></p>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="detail_pasien.php?id=<?= $id ?>" class="btn btn-secondary">
                <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Batal
            </a>
        </div>
    </div>

    <div class="edit-layout">
        <div class="card">
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <div><strong>Terdapat kesalahan:</strong>
                    <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" action="edit_pasien.php?id=<?= $id ?>" novalidate>

                <!-- Nama -->
                <div class="form-group">
                    <label class="form-label" for="nama">Nama Lengkap <span class="req">*</span></label>
                    <div class="input-icon-wrap">
                        <svg class="ic" viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v2h20v-2c0-3.3-6.7-5-10-5z"/></svg>
                        <input type="text" id="nama" name="nama" class="form-control"
                               value="<?= htmlspecialchars($old['nama']) ?>"
                               maxlength="150" required autocomplete="name">
                    </div>
                </div>

                <!-- Jenis Kelamin -->
                <div class="form-group">
                    <label class="form-label">Jenis Kelamin</label>
                    <div class="jk-group">
                        <div class="jk-option">
                            <input type="radio" id="jk-p" name="jenis_kelamin" value="P"
                                   <?= ($old['jenis_kelamin'] ?? '') === 'P' ? 'checked' : '' ?>>
                            <label for="jk-p">
                                <svg viewBox="0 0 24 24"><path d="M12 2a5 5 0 1 1 0 10A5 5 0 0 1 12 2zm0 12c5.33 0 8 2.67 8 4v2H4v-2c0-1.33 2.67-4 8-4z"/></svg>
                                Perempuan
                            </label>
                        </div>
                        <div class="jk-option">
                            <input type="radio" id="jk-l" name="jenis_kelamin" value="L"
                                   <?= ($old['jenis_kelamin'] ?? '') === 'L' ? 'checked' : '' ?>>
                            <label for="jk-l">
                                <svg viewBox="0 0 24 24"><path d="M12 2a5 5 0 1 1 0 10A5 5 0 0 1 12 2zm0 12c5.33 0 8 2.67 8 4v2H4v-2c0-1.33 2.67-4 8-4z"/></svg>
                                Laki-laki
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Telepon & Email -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="telepon">Nomor Telepon</label>
                        <div class="input-icon-wrap">
                            <svg class="ic" viewBox="0 0 24 24"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/></svg>
                            <input type="tel" id="telepon" name="telepon" class="form-control"
                                   value="<?= htmlspecialchars($old['telepon'] ?? '') ?>"
                                   placeholder="0812-3456-7890" maxlength="20">
                        </div>
                        <p class="form-hint">Opsional</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <div class="input-icon-wrap">
                            <svg class="ic" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                                   placeholder="email@contoh.com" maxlength="150">
                        </div>
                        <p class="form-hint">Opsional</p>
                    </div>
                </div>

                <!-- Tgl Lahir & Sumber -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="tanggal_lahir">Tanggal Lahir</label>
                        <div class="input-icon-wrap">
                            <svg class="ic" viewBox="0 0 24 24"><path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5c-1.1 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/></svg>
                            <input type="date" id="tanggal_lahir" name="tanggal_lahir" class="form-control"
                                   value="<?= htmlspecialchars($old['tanggal_lahir'] ?? '') ?>"
                                   max="<?= date('Y-m-d') ?>">
                        </div>
                        <p class="form-hint">Opsional</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="sumber_pasien">Sumber Pasien</label>
                        <div class="input-icon-wrap">
                            <svg class="ic" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                            <select id="sumber_pasien" name="sumber_pasien" class="form-control">
                                <option value="">— Pilih sumber —</option>
                                <?php
                                $sumber_opts = ['CRM','Instagram','TikTok','WhatsApp','Referral','Walk-in','Website','Lainnya'];
                                foreach ($sumber_opts as $s):
                                    $sel = ($old['sumber_pasien'] ?? '') === $s ? 'selected' : '';
                                ?>
                                <option value="<?= $s ?>" <?= $sel ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="form-hint">Opsional</p>
                    </div>
                </div>

                <!-- Catatan CRM -->
                <div class="form-group">
                    <label class="form-label" for="catatan_crm">Catatan CRM</label>
                    <textarea id="catatan_crm" name="catatan_crm" class="form-control" rows="4"
                              placeholder="Preferensi, riwayat kontak, catatan follow-up…"
                    ><?= htmlspecialchars($old['catatan_crm'] ?? '') ?></textarea>
                    <p class="form-hint">Opsional — catatan internal untuk keperluan follow-up</p>
                </div>

                <div style="display:flex;gap:10px;margin-top:8px;">
                    <a href="detail_pasien.php?id=<?= $id ?>" class="btn btn-secondary btn-lg" style="flex:1;justify-content:center;">Batal</a>
                    <button type="submit" class="btn btn-primary btn-lg" style="flex:2;">
                        <svg viewBox="0 0 24 24"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>

        <!-- Aside info pasien -->
        <div class="form-aside">
            <div class="aside-h">📋 Data Saat Ini</div>
            <div class="aside-info-row">
                <div class="aside-info-item">
                    <div class="aside-info-label">ID Pasien</div>
                    <div class="aside-info-val">#<?= $id ?></div>
                </div>
                <div class="aside-info-item">
                    <div class="aside-info-label">Terdaftar</div>
                    <div class="aside-info-val"><?= date('d M Y', strtotime($pasien['created_at'])) ?></div>
                </div>
                <?php if ($pasien['sumber_pasien']): ?>
                <div class="aside-info-item">
                    <div class="aside-info-label">Sumber</div>
                    <div class="aside-info-val"><?= htmlspecialchars($pasien['sumber_pasien']) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <div style="margin-top:20px;padding-top:18px;border-top:1px solid rgba(255,255,255,.07);">
                <a href="detail_pasien.php?id=<?= $id ?>" class="btn btn-secondary"
                   style="width:100%;justify-content:center;font-size:.82rem;margin-bottom:8px;">
                    Lihat Profil Lengkap
                </a>
                <a href="tambah_treatment.php?pasien_id=<?= $id ?>" class="btn btn-primary"
                   style="width:100%;justify-content:center;font-size:.82rem;">
                    + Tambah Treatment
                </a>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    document.getElementById('sidebarToggle').addEventListener('click', function(){
        sidebar.classList.toggle('open'); overlay.classList.toggle('show');
    });
    overlay.addEventListener('click', function(){
        sidebar.classList.remove('open'); overlay.classList.remove('show');
    });
}());
</script>
</body>
</html>
