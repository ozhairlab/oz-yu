<?php
// ============================================================
//  tambah_treatment.php — Multi-foto Before/After per Pasien
// ============================================================
require_once 'koneksi.php';
require_login();

$page_title  = 'Catat Treatment';
$active_menu = 'treatment';

define('UPLOAD_BASE',     __DIR__ . '/uploads/');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);
define('UPLOAD_ALLOWED',  ['image/jpeg', 'image/png', 'image/webp']);
define('UPLOAD_EXT_MAP',  ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']);
define('MAX_FOTO_PER_TIPE', 10);

if (!is_dir(UPLOAD_BASE)) mkdir(UPLOAD_BASE, 0755, true);

// Daftar pasien
$pasien_list = [];
$res = $koneksi->query('SELECT id, nama FROM pasien ORDER BY nama ASC');
while ($row = $res->fetch_assoc()) $pasien_list[] = $row;

// Daftar divisi
$divisi_list = [];
$rd = $koneksi->query('SELECT id, kode, nama, warna FROM divisi ORDER BY urutan');
while ($row = $rd->fetch_assoc()) $divisi_list[] = $row;

// Perawatan per divisi (aktif saja)
$perawatan_per_divisi = [];
foreach ($divisi_list as $d) {
    $stmt = $koneksi->prepare(
        'SELECT id, nama FROM master_perawatan
         WHERE divisi_id = ? AND aktif = 1 ORDER BY urutan ASC, nama ASC'
    );
    $stmt->bind_param('i', $d['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($r = $result->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    $perawatan_per_divisi[$d['id']] = $rows;
}

$errors           = [];
$sukses           = false;
$new_treatment_id = null;
$preselect_pasien = (int)($_GET['pasien_id'] ?? 0);

$old = [
    'pasien_id'         => $preselect_pasien,
    'divisi_id'         => $divisi_list[0]['id'] ?? 1,
    'tanggal_treatment' => date('Y-m-d'),
    'nama_treatment'    => '',
    'catatan'           => '',
];

// ============================================================
//  Upload satu file, simpan ke folder pasien
//  Kembalikan nama file relatif (tanpa path) atau '' jika skip
// ============================================================
function upload_satu(array $file, string $pasien_dir): string
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return '';
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = [
            UPLOAD_ERR_INI_SIZE   => 'File terlalu besar (batas server).',
            UPLOAD_ERR_FORM_SIZE  => 'File terlalu besar (batas form).',
            UPLOAD_ERR_PARTIAL    => 'Upload tidak lengkap.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara tidak ditemukan.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
        ];
        throw new RuntimeException($msg[$file['error']] ?? 'Error upload (kode '.$file['error'].').');
    }
    if ($file['size'] > UPLOAD_MAX_SIZE)
        throw new RuntimeException(htmlspecialchars($file['name']).' melebihi batas 5 MB.');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, UPLOAD_ALLOWED, true))
        throw new RuntimeException(
            htmlspecialchars($file['name']).' — format tidak didukung. Gunakan JPG, PNG, atau WebP.'
        );

    $ext      = UPLOAD_EXT_MAP[$mime];
    $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest     = $pasien_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest))
        throw new RuntimeException('Gagal menyimpan '.htmlspecialchars($file['name']).'.');

    return $filename;
}

// ============================================================
//  Proses POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pasien_id         = (int)($_POST['pasien_id'] ?? 0);
    $divisi_id         = (int)($_POST['divisi_id'] ?? 0);
    $tanggal_treatment = trim($_POST['tanggal_treatment'] ?? '');
    $nama_treatment    = trim($_POST['nama_treatment'] ?? '');
    $catatan           = trim($_POST['catatan'] ?? '');

    $old = compact('pasien_id','divisi_id','tanggal_treatment','nama_treatment','catatan');

    // --- Validasi teks ---
    if ($pasien_id <= 0) {
        $errors[] = 'Pilih pasien terlebih dahulu.';
    } else {
        $chk = $koneksi->prepare('SELECT id FROM pasien WHERE id=? LIMIT 1');
        $chk->bind_param('i', $pasien_id);
        $chk->execute(); $chk->store_result();
        if ($chk->num_rows === 0) $errors[] = 'Pasien tidak ditemukan.';
        $chk->close();
    }

    if ($divisi_id <= 0)  $errors[] = 'Pilih divisi perawatan.';
    if ($tanggal_treatment === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_treatment))
        $errors[] = 'Tanggal treatment wajib diisi.';
    if ($nama_treatment === '')
        $errors[] = 'Pilih atau ketik nama treatment.';
    elseif (mb_strlen($nama_treatment) > 200)
        $errors[] = 'Nama treatment terlalu panjang.';

    // --- Validasi jumlah foto ---
    $raw_before = $_FILES['foto_before'] ?? null;
    $raw_after  = $_FILES['foto_after']  ?? null;

    // Normalkan struktur FILES ke array-of-files
    function normalkan_files(?array $raw): array {
        if (!$raw) return [];
        // input multiple mengirim array; input single mengirim scalar
        if (is_array($raw['name'])) {
            $out = [];
            foreach ($raw['name'] as $i => $name) {
                $out[] = [
                    'name'     => $name,
                    'type'     => $raw['type'][$i],
                    'tmp_name' => $raw['tmp_name'][$i],
                    'error'    => $raw['error'][$i],
                    'size'     => $raw['size'][$i],
                ];
            }
            return $out;
        }
        return [$raw];
    }

    $files_before = normalkan_files($raw_before);
    $files_after  = normalkan_files($raw_after);

    $real_before = array_filter($files_before, fn($f) => $f['error'] !== UPLOAD_ERR_NO_FILE);
    $real_after  = array_filter($files_after,  fn($f) => $f['error'] !== UPLOAD_ERR_NO_FILE);

    if (count($real_before) > MAX_FOTO_PER_TIPE)
        $errors[] = 'Maksimal '.MAX_FOTO_PER_TIPE.' foto Before.';
    if (count($real_after)  > MAX_FOTO_PER_TIPE)
        $errors[] = 'Maksimal '.MAX_FOTO_PER_TIPE.' foto After.';

    // Inisialisasi agar selalu terdefinisi di scope ini
    $before_names   = [];
    $after_names    = [];
    $uploaded_files = [];

    // --- Upload & simpan ke DB ---
    if (empty($errors)) {
        // Buat folder pasien jika belum ada
        $pasien_dir = UPLOAD_BASE . 'pasien_' . $pasien_id . '/';
        if (!is_dir($pasien_dir)) mkdir($pasien_dir, 0755, true);

        try {
            $urutan = 1;
            foreach ($real_before as $f) {
                $name = upload_satu($f, $pasien_dir);
                if ($name) {
                    $before_names[] = ['file' => $name, 'urutan' => $urutan++];
                    $uploaded_files[] = $pasien_dir . $name;
                }
            }

            $urutan = 1;
            foreach ($real_after as $f) {
                $name = upload_satu($f, $pasien_dir);
                if ($name) {
                    $after_names[] = ['file' => $name, 'urutan' => $urutan++];
                    $uploaded_files[] = $pasien_dir . $name;
                }
            }
        } catch (RuntimeException $e) {
            // Rollback file yang sudah terupload
            foreach ($uploaded_files as $fp) { if (file_exists($fp)) @unlink($fp); }
            $uploaded_files = [];
            $before_names   = [];
            $after_names    = [];
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        // Insert riwayat_treatment (tanpa foto_before/after — sudah di tabel terpisah)
        $catatan_val = $catatan   ?: null;
        $divisi_val  = $divisi_id ?: null;

        $stmt = $koneksi->prepare(
            'INSERT INTO riwayat_treatment
             (pasien_id, divisi_id, tanggal_treatment, nama_treatment, catatan)
             VALUES (?,?,?,?,?)'
        );
        $stmt->bind_param('iisss',
            $pasien_id, $divisi_val, $tanggal_treatment, $nama_treatment, $catatan_val
        );

        if ($stmt->execute()) {
            $new_treatment_id = $stmt->insert_id;
            $stmt->close();

            // Insert semua foto ke treatment_foto
            $ins = $koneksi->prepare(
                'INSERT INTO treatment_foto (treatment_id, tipe, nama_file, urutan) VALUES (?,?,?,?)'
            );

            $rel_dir = 'pasien_' . $pasien_id . '/'; // path relatif dari uploads/
            foreach ($before_names as $b) {
                $tipe = 'before';
                $fname = $rel_dir . $b['file'];
                $ins->bind_param('issi', $new_treatment_id, $tipe, $fname, $b['urutan']);
                $ins->execute();
            }
            foreach ($after_names as $a) {
                $tipe = 'after';
                $fname = $rel_dir . $a['file'];
                $ins->bind_param('issi', $new_treatment_id, $tipe, $fname, $a['urutan']);
                $ins->execute();
            }
            $ins->close();

            // Redirect ke halaman detail pasien setelah berhasil simpan
            header('Location: detail_pasien.php?id=' . $pasien_id . '&saved=1');
            exit;
        } else {
            // Rollback semua file yang sudah diupload
            foreach ($uploaded_files as $fp) { if (file_exists($fp)) @unlink($fp); }
            $errors[] = 'Gagal menyimpan ke database. Coba lagi.';
            error_log('Insert treatment gagal: '.$stmt->error);
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
    <title><?= htmlspecialchars($page_title) ?> — Rekam Medis Klinik</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .form-layout { display:grid; grid-template-columns:1fr 300px; gap:22px; align-items:start; }

        .divisi-switcher { display:flex; border-radius:var(--radius-sm); overflow:hidden;
                           border:1.5px solid var(--border); margin-bottom:22px; }
        .divisi-btn { flex:1; padding:11px 10px; border:none; background:#f8f8fc;
                      font-size:.85rem; font-weight:700; cursor:pointer;
                      display:flex; align-items:center; justify-content:center; gap:8px;
                      transition:background .2s, color .2s; color:#999; }
        .divisi-btn + .divisi-btn { border-left:1.5px solid var(--border); }
        .divisi-btn .d-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
        .divisi-btn.active-oz   { background:#fce4ec; color:#c2185b; }
        .divisi-btn.active-hair { background:#ede7f6; color:#6a1b9a; }

        .perawatan-panel { display:none; }
        .perawatan-panel.show { display:block; }

        .chips { display:flex; flex-wrap:wrap; gap:7px; margin-top:8px; }
        .chip { padding:5px 13px; border-radius:50px; font-size:.76rem; font-weight:600;
                cursor:pointer; border:1.5px solid var(--border); background:#f4f4f8;
                color:#555; transition:all .15s; user-select:none; }
        .chip.oz:hover,  .chip.oz.selected  { background:#fce4ec; color:#e91e63; border-color:#f8bbd0; }
        .chip.hair:hover,.chip.hair.selected { background:#ede7f6; color:#7c4dff; border-color:#d1c4e9; }

        /* ---- Multi-upload area ---- */
        .foto-section { margin-bottom:22px; }
        .foto-section-header {
            display: flex; align-items:center; justify-content:space-between;
            margin-bottom: 10px;
        }
        .foto-section-label {
            font-size:.82rem; font-weight:700; color:#4a4a5a;
            display:flex; align-items:center; gap:6px;
        }
        .foto-section-label .badge-tipe {
            padding:2px 10px; border-radius:50px; font-size:.7rem; font-weight:700;
        }
        .badge-before { background:#fce4ec; color:#c2185b; }
        .badge-after  { background:#e8f5e9; color:#2e7d32; }

        .foto-drop-zone {
            border: 2px dashed var(--primary-200);
            border-radius: var(--radius-md);
            padding: 18px 16px 14px;
            background: var(--primary-50);
            transition: border-color .2s, background .2s;
            cursor: pointer;
            position: relative;
        }
        .foto-drop-zone.drag-over { border-color: var(--primary); background:var(--primary-100); }

        .foto-drop-inner { text-align:center; pointer-events:none; }
        .foto-drop-inner .icon { font-size:1.8rem; margin-bottom:4px; }
        .foto-drop-inner p { font-size:.78rem; color:#bbb; margin-top:2px; }
        .foto-drop-inner .lbl { font-size:.83rem; font-weight:700; color:var(--primary); }

        .foto-input-hidden { display:none; }

        /* Preview grid */
        .foto-preview-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }

        .foto-thumb {
            position: relative;
            width: 90px; height: 90px;
            border-radius: var(--radius-sm);
            overflow: hidden;
            border: 2px solid var(--primary-200);
            background: #f0f0f0;
            flex-shrink: 0;
        }

        .foto-thumb img {
            width: 100%; height: 100%;
            object-fit: cover;
            display: block;
        }

        .foto-thumb .thumb-del {
            position: absolute;
            top: 3px; right: 3px;
            width: 20px; height: 20px;
            background: rgba(0,0,0,.65);
            color: #fff;
            border: none;
            border-radius: 50%;
            font-size: .75rem;
            cursor: pointer;
            display: flex; align-items:center; justify-content:center;
            line-height: 1;
            transition: background .15s;
        }
        .foto-thumb .thumb-del:hover { background: #d32f2f; }

        .foto-thumb .thumb-num {
            position: absolute;
            bottom: 2px; left: 4px;
            font-size: .62rem;
            font-weight: 700;
            color: rgba(255,255,255,.9);
            text-shadow: 0 1px 3px rgba(0,0,0,.7);
        }

        .btn-add-foto {
            display: inline-flex; align-items:center; gap:6px;
            padding: 6px 14px;
            border-radius: var(--radius-sm);
            font-size: .78rem; font-weight:700;
            cursor: pointer;
            border: 1.5px dashed var(--primary-200);
            background: var(--primary-100);
            color: var(--primary);
            transition: all .15s;
        }
        .btn-add-foto:hover { background:var(--primary-200); border-color:var(--primary); }

        /* Aside */
        .form-aside { background:linear-gradient(160deg,#1a1a2e 0%,#16213e 100%);
                      border-radius:var(--radius-md); padding:24px 20px; color:#fff;
                      position:sticky; top:calc(var(--topbar-h) + 16px); }
        .form-aside h3 { font-size:.9rem; font-weight:700; color:#fff; margin-bottom:12px;
                         display:flex; align-items:center; gap:8px; }
        .pasien-preview { background:rgba(255,255,255,.07); border-radius:var(--radius-sm);
                          padding:14px; margin-bottom:16px; display:none; }
        .pasien-preview.show { display:block; }
        .preview-nama { font-size:.95rem; font-weight:700; color:#fff; }
        .aside-info { font-size:.78rem; color:rgba(255,255,255,.5); line-height:1.6; }
        .aside-info li { margin-bottom:8px; list-style:none; }
        .aside-info li::before { content:'✦'; color:var(--pink); margin-right:6px; font-size:.65rem; }

        /* Foto count badge */
        .foto-count {
            font-size:.72rem; font-weight:700;
            background: rgba(255,255,255,.12);
            color: rgba(255,255,255,.7);
            padding: 2px 8px; border-radius:50px;
        }

        @media (max-width:860px) {
            .form-layout { grid-template-columns:1fr; }
            .form-aside { position:static; }
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <div class="page-header-left">
            <h1>Catat Treatment Baru</h1>
            <p>Rekam tindakan perawatan beserta foto before &amp; after (bisa banyak foto)</p>
        </div>
        <a href="index.php" class="btn btn-secondary">
            <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            Kembali
        </a>
    </div>

    <div class="form-layout">
        <div class="card">
            <?php if (!empty($_GET['err'])): ?>
                <div class="alert alert-error" role="alert">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    <div>Terjadi kesalahan, silakan coba lagi.</div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48
                         10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    <div><strong>Terdapat kesalahan:</strong>
                        <ul><?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?></ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="tambah_treatment.php"
                  enctype="multipart/form-data" novalidate id="form-treatment">
                <input type="hidden" name="divisi_id" id="divisi_id_input"
                       value="<?= (int)$old['divisi_id'] ?>">

                <!-- Pilih Pasien -->
                <div class="form-group">
                    <label class="form-label" for="pasien_id">Pasien <span class="req">*</span></label>
                    <select id="pasien_id" name="pasien_id" class="form-control" required>
                        <option value="">— Pilih pasien —</option>
                        <?php foreach ($pasien_list as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"
                                data-nama="<?= htmlspecialchars($p['nama']) ?>"
                                <?= ((int)$old['pasien_id'] === (int)$p['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Divisi Switcher -->
                <div class="form-group">
                    <label class="form-label">Divisi Perawatan <span class="req">*</span></label>
                    <div class="divisi-switcher">
                        <?php foreach ($divisi_list as $d):
                            $isOz   = $d['kode'] === 'ozthetique';
                            $active = ((int)$old['divisi_id'] === (int)$d['id']);
                            $cls    = $active ? ($isOz ? 'active-oz' : 'active-hair') : '';
                        ?>
                            <button type="button" class="divisi-btn <?= $cls ?>"
                                    data-divisi-id="<?= $d['id'] ?>"
                                    data-kode="<?= $d['kode'] ?>"
                                    data-warna="<?= htmlspecialchars($d['warna']) ?>"
                                    onclick="switchDivisi(this)">
                                <span class="d-dot" style="background:<?= htmlspecialchars($d['warna']) ?>"></span>
                                <?= htmlspecialchars($d['nama']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Input tanggal & nama — di luar loop agar hanya ada SATU per form -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tanggal Treatment <span class="req">*</span></label>
                        <input type="date" id="tanggal_treatment" name="tanggal_treatment"
                               class="form-control"
                               value="<?= htmlspecialchars($old['tanggal_treatment']) ?>"
                               max="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Treatment <span class="req">*</span></label>
                        <input type="text" id="nama_treatment" name="nama_treatment"
                               class="form-control"
                               placeholder="Pilih chip di bawah atau ketik"
                               value="<?= htmlspecialchars($old['nama_treatment']) ?>"
                               maxlength="200" required autocomplete="off">
                    </div>
                </div>

                <!-- Panel chips perawatan per divisi -->
                <?php foreach ($divisi_list as $d):
                    $isOz    = $d['kode'] === 'ozthetique';
                    $visible = ((int)$old['divisi_id'] === (int)$d['id']);
                    $list    = $perawatan_per_divisi[$d['id']] ?? [];
                    $chipCls = $isOz ? 'oz' : 'hair';
                ?>
                <div class="perawatan-panel <?= $visible ? 'show' : '' ?>"
                     id="panel-<?= $d['id'] ?>" data-divisi="<?= $d['id'] ?>">
                    <?php if (!empty($list)): ?>
                        <div style="margin-top:-10px;margin-bottom:18px;">
                            <p style="font-size:.74rem;color:#aaa;margin-bottom:6px;">Pilih cepat:</p>
                            <div class="chips">
                                <?php foreach ($list as $item): ?>
                                    <span class="chip <?= $chipCls ?>"
                                          onclick="pilihTreatment(this)">
                                        <?= htmlspecialchars($item['nama']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p style="font-size:.8rem;color:#bbb;margin:-8px 0 18px;">
                            Belum ada perawatan aktif.
                            <a href="master_perawatan.php" style="color:var(--primary)">Tambah di Master Perawatan</a>
                        </p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <!-- Catatan -->
                <div class="form-group">
                    <label class="form-label" for="catatan">Catatan Klinis</label>
                    <textarea id="catatan" name="catatan" class="form-control" rows="4"
                              placeholder="Kondisi awal, produk digunakan, reaksi pasien, rekomendasi lanjutan…"
                    ><?= htmlspecialchars($old['catatan']) ?></textarea>
                    <p class="form-hint">Opsional</p>
                </div>

                <!-- ===== MULTI UPLOAD BEFORE ===== -->
                <div class="foto-section">
                    <div class="foto-section-header">
                        <div class="foto-section-label">
                            <svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:var(--primary)">
                        <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2
                                         2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                    </svg>
                            Foto <span class="badge-tipe badge-before">BEFORE</span>
                        </div>
                        <button type="button" class="btn-add-foto" onclick="triggerUpload('input-before')">
                            + Tambah Foto
                        </button>
                    </div>
                    <div class="foto-drop-zone" id="zone-before"
                         ondragover="dragOver(event,'zone-before')"
                         ondragleave="dragLeave('zone-before')"
                         ondrop="dropFoto(event,'input-before','grid-before')"
                         onclick="triggerUpload('input-before')">
                        <div class="foto-drop-inner">
                            <div class="icon">📷</div>
                            <p class="lbl">Klik atau drag &amp; drop foto Before</p>
                            <p>JPG / PNG / WebP · maks 5 MB per foto · maks <?= MAX_FOTO_PER_TIPE ?> foto</p>
                        </div>
                        <input type="file" id="input-before" name="foto_before[]"
                               class="foto-input-hidden"
                               accept="image/jpeg,image/png,image/webp"
                               multiple
                               onchange="tambahPreview(this,'grid-before')">
                    </div>
                    <div class="foto-preview-grid" id="grid-before"></div>
                </div>

                <!-- ===== MULTI UPLOAD AFTER ===== -->
                <div class="foto-section">
                    <div class="foto-section-header">
                        <div class="foto-section-label">
                            <svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:#2e7d32">
                                <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2
                                         2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                            </svg>
                            Foto <span class="badge-tipe badge-after">AFTER</span>
                        </div>
                        <button type="button" class="btn-add-foto" onclick="triggerUpload('input-after')">
                            + Tambah Foto
                        </button>
                    </div>
                    <div class="foto-drop-zone" id="zone-after"
                         ondragover="dragOver(event,'zone-after')"
                         ondragleave="dragLeave('zone-after')"
                         ondrop="dropFoto(event,'input-after','grid-after')"
                         onclick="triggerUpload('input-after')">
                        <div class="foto-drop-inner">
                            <div class="icon">✨</div>
                            <p class="lbl">Klik atau drag &amp; drop foto After</p>
                            <p>JPG / PNG / WebP · maks 5 MB per foto · maks <?= MAX_FOTO_PER_TIPE ?> foto</p>
                        </div>
                        <input type="file" id="input-after" name="foto_after[]"
                               class="foto-input-hidden"
                               accept="image/jpeg,image/png,image/webp"
                               multiple
                               onchange="tambahPreview(this,'grid-after')">
                    </div>
                    <div class="foto-preview-grid" id="grid-after"></div>
                </div>

                <div style="display:flex;gap:12px;margin-top:8px;">
                    <a href="index.php" class="btn btn-secondary btn-lg"
                       style="flex:1;justify-content:center;">Batal</a>
                    <button type="submit" class="btn btn-primary btn-lg" style="flex:2;">
                        <svg viewBox="0 0 24 24"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2
                             2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34
                             3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                        Simpan Treatment
                    </button>
                </div>
            </form>
        </div>

        <!-- Aside -->
        <div class="form-aside">
            <h3>
                    <svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:var(--primary)">
                        <path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5
                             2.3-5 5 2.3 5 5 5zm0
                             2c-3.3 0-10 1.7-10 5v2h20v-2c0-3.3-6.7-5-10-5z"/>
                    </svg>
                    Pasien Terpilih
                </h3>
            <div class="pasien-preview" id="pasien-preview">
                <div class="preview-nama" id="preview-nama">—</div>
                <div style="font-size:.72rem;color:rgba(255,255,255,.4);margin-top:4px;"
                     id="preview-folder">Folder: —</div>
            </div>
            <div id="pasien-placeholder"
                 style="font-size:.78rem;color:rgba(255,255,255,.3);margin-bottom:16px;">
                Belum ada pasien dipilih.
            </div>

            <h3>🏷️ Divisi Aktif</h3>
            <div id="aside-divisi-badge" style="margin-bottom:16px;"></div>

            <h3>📸 Foto Terpilih</h3>
            <div style="display:flex;gap:10px;margin-bottom:16px;">
                <div style="flex:1;background:rgba(255,255,255,.07);border-radius:8px;padding:10px;text-align:center;">
                    <div style="font-size:1.3rem;font-weight:700;color:#fff" id="count-before">0</div>
                    <div style="font-size:.7rem;color:rgba(255,255,255,.4);">Before</div>
                </div>
                <div style="flex:1;background:rgba(255,255,255,.07);border-radius:8px;padding:10px;text-align:center;">
                    <div style="font-size:1.3rem;font-weight:700;color:#fff" id="count-after">0</div>
                    <div style="font-size:.7rem;color:rgba(255,255,255,.4);">After</div>
                </div>
            </div>

            <h3>💡 Tips</h3>
            <ul class="aside-info">
                <li>Foto disimpan di folder <strong style="color:rgba(255,255,255,.7)">uploads/pasien_ID/</strong> terpisah per pasien.</li>
                <li>Bisa upload banyak foto sekaligus dengan drag &amp; drop.</li>
                <li>Klik ✕ pada thumbnail untuk membatalkan foto tertentu.</li>
                <li>Maks <?= MAX_FOTO_PER_TIPE ?> foto per tipe.</li>
            </ul>
        </div>
    </div>
</div>

<script>
(function () {
    /* Sidebar */
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    document.getElementById('sidebarToggle').addEventListener('click', function () {
        sidebar.classList.toggle('open'); overlay.classList.toggle('show');
    });
    overlay.addEventListener('click', function () {
        sidebar.classList.remove('open'); overlay.classList.remove('show');
    });

    /* Pasien preview */
    var sel     = document.getElementById('pasien_id');
    var pvBox   = document.getElementById('pasien-preview');
    var pvNama  = document.getElementById('preview-nama');
    var pvFoldr = document.getElementById('preview-folder');
    var pvPh    = document.getElementById('pasien-placeholder');

    function updatePasien() {
        var opt = sel.options[sel.selectedIndex];
        if (sel.value) {
            pvNama.textContent  = opt.getAttribute('data-nama') || opt.text;
            pvFoldr.textContent = 'Folder: uploads/pasien_' + sel.value + '/';
            pvBox.classList.add('show');
            pvPh.style.display = 'none';
        } else {
            pvBox.classList.remove('show');
            pvPh.style.display = '';
        }
    }
    sel.addEventListener('change', updatePasien);
    updatePasien();
}());

/* ---- Divisi switcher ---- */
function switchDivisi(btn) {
    var id    = parseInt(btn.getAttribute('data-divisi-id'));
    var kode  = btn.getAttribute('data-kode');
    var warna = btn.getAttribute('data-warna');

    document.getElementById('divisi_id_input').value = id;
    document.querySelectorAll('.divisi-btn').forEach(function (b) {
        b.classList.remove('active-oz','active-hair');
    });
    btn.classList.add(kode === 'ozthetique' ? 'active-oz' : 'active-hair');
    document.querySelectorAll('.perawatan-panel').forEach(function (p) {
        p.classList.toggle('show', parseInt(p.getAttribute('data-divisi')) === id);
    });
    var badge = document.getElementById('aside-divisi-badge');
    badge.innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;' +
        'border-radius:50px;background:rgba(255,255,255,.08);color:#fff;font-size:.78rem;font-weight:700">' +
        '<span style="width:8px;height:8px;border-radius:50%;background:'+warna+'"></span>' +
        escHtml(btn.textContent.trim()) + '</span>';
}

(function initBadge() {
    var ab = document.querySelector('.divisi-btn.active-oz,.divisi-btn.active-hair');
    if (ab) switchDivisi(ab);
}());

/* ---- Chips ---- */
function pilihTreatment(chip) {
    // Semua chip sekarang target ke satu input tunggal #nama_treatment
    var input = document.getElementById('nama_treatment');
    if (input) input.value = chip.textContent.trim();
    // Hapus selected dari semua chip (lintas panel)
    document.querySelectorAll('.chip').forEach(function (c) { c.classList.remove('selected'); });
    chip.classList.add('selected');
}

/* ================================================================
   MULTI-FOTO LOGIC
   DataTransfer dipakai untuk memanipulasi FileList karena FileList
   adalah read-only — kita rebuild setiap kali ada perubahan.
   ================================================================ */
var fotoState = { 'input-before': [], 'input-after': [] };
var MAX_FOTO  = <?= MAX_FOTO_PER_TIPE ?>;

function triggerUpload(inputId) {
    document.getElementById(inputId).click();
}

function tambahPreview(inputEl, gridId) {
    var id    = inputEl.id;
    var files = Array.from(inputEl.files);

    files.forEach(function (file) {
        if (fotoState[id].length >= MAX_FOTO) return;
        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) return;

        var uid = Date.now() + '_' + Math.random().toString(36).substr(2,6);
        fotoState[id].push({ uid: uid, file: file });
    });

    syncInput(id);
    renderGrid(id, gridId);
}

function hapusFoto(inputId, gridId, uid) {
    fotoState[inputId] = fotoState[inputId].filter(function (f) { return f.uid !== uid; });
    syncInput(inputId);
    renderGrid(inputId, gridId);
}

/* Rebuild FileList di <input> dari fotoState */
function syncInput(inputId) {
    var dt = new DataTransfer();
    fotoState[inputId].forEach(function (f) { dt.items.add(f.file); });
    document.getElementById(inputId).files = dt.files;

    /* Update counter di aside */
    var key    = inputId === 'input-before' ? 'count-before' : 'count-after';
    var count  = fotoState[inputId].length;
    document.getElementById(key).textContent = count;
}

function renderGrid(inputId, gridId) {
    var grid = document.getElementById(gridId);
    grid.innerHTML = '';
    fotoState[inputId].forEach(function (item, idx) {
        var reader = new FileReader();
        reader.onload = function (e) {
            var thumb = document.createElement('div');
            thumb.className = 'foto-thumb';
            thumb.innerHTML =
                '<img src="' + e.target.result + '" alt="foto ' + (idx+1) + '">' +
                '<button type="button" class="thumb-del" ' +
                    'onclick="hapusFoto(\'' + inputId + '\',\'' + gridId + '\',\'' + item.uid + '\')" ' +
                    'aria-label="Hapus foto">✕</button>' +
                '<span class="thumb-num">' + (idx+1) + '</span>';
            grid.appendChild(thumb);
        };
        reader.readAsDataURL(item.file);
    });
}

/* Drag & Drop */
function dragOver(e, zoneId) {
    e.preventDefault();
    document.getElementById(zoneId).classList.add('drag-over');
}
function dragLeave(zoneId) {
    document.getElementById(zoneId).classList.remove('drag-over');
}
function dropFoto(e, inputId, gridId) {
    e.preventDefault();
    var zoneId = inputId === 'input-before' ? 'zone-before' : 'zone-after';
    document.getElementById(zoneId).classList.remove('drag-over');

    var files = Array.from(e.dataTransfer.files);
    files.forEach(function (file) {
        if (fotoState[inputId].length >= MAX_FOTO) return;
        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) return;
        var uid = Date.now() + '_' + Math.random().toString(36).substr(2,6);
        fotoState[inputId].push({ uid: uid, file: file });
    });

    syncInput(inputId);
    renderGrid(inputId, gridId);
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
                    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
