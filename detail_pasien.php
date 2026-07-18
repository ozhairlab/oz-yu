<?php
// ============================================================
//  detail_pasien.php — Profil & Riwayat Treatment Pasien
// ============================================================
require_once 'koneksi.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: pasien.php'); exit; }

// Ambil data pasien
$stmt = $koneksi->prepare(
    'SELECT id, nama, telepon, email, jenis_kelamin,
            DATE_FORMAT(tanggal_lahir,\'%d %M %Y\') AS tgl_lahir_fmt,
            tanggal_lahir,
            sumber_pasien, catatan_crm,
            DATE_FORMAT(created_at,\'%d %M %Y\') AS terdaftar
     FROM pasien WHERE id = ? LIMIT 1'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$pasien = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$pasien) { header('Location: pasien.php'); exit; }

// Hitung umur
$umur = null;
if ($pasien['tanggal_lahir']) {
    $umur = (int)(new DateTime($pasien['tanggal_lahir']))->diff(new DateTime())->y;
}

// Ambil semua treatment pasien ini beserta foto
$stmt2 = $koneksi->prepare(
    'SELECT rt.id, rt.nama_treatment, rt.catatan,
            DATE_FORMAT(rt.tanggal_treatment,\'%d %M %Y\') AS tanggal_fmt,
            rt.tanggal_treatment,
            d.kode AS divisi_kode, d.nama AS divisi_nama, d.warna AS divisi_warna
     FROM riwayat_treatment rt
     LEFT JOIN divisi d ON d.id = rt.divisi_id
     WHERE rt.pasien_id = ?
     ORDER BY rt.tanggal_treatment DESC, rt.id DESC'
);
$stmt2->bind_param('i', $id);
$stmt2->execute();
$treatments_raw = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

// Pasang foto per treatment
$treatments = [];
foreach ($treatments_raw as $t) {
    $tid   = (int)$t['id'];
    $stmt3 = $koneksi->prepare(
        'SELECT tipe, nama_file, urutan FROM treatment_foto
         WHERE treatment_id = ? ORDER BY tipe ASC, urutan ASC'
    );
    $stmt3->bind_param('i', $tid);
    $stmt3->execute();
    $foto_rows = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt3->close();

    $before = array_filter($foto_rows, fn($f) => $f['tipe'] === 'before');
    $after  = array_filter($foto_rows, fn($f) => $f['tipe'] === 'after');
    $t['foto_before'] = array_values($before);
    $t['foto_after']  = array_values($after);
    $treatments[] = $t;
}

$total_treatment = count($treatments);
$page_title      = $pasien['nama'];
$active_menu     = 'pasien';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Rekam Medis Klinik</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* ---- Profile hero ---- */
        .profile-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
            border-radius: var(--radius-md);
            padding: 36px 36px 28px;
            display: flex;
            align-items: flex-start;
            gap: 28px;
            flex-wrap: wrap;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
        }

        .profile-hero::before {
            content: '';
            position: absolute;
            width: 320px; height: 320px;
            border-radius: 50%;
            background: rgba(236,72,153,.12);
            top: -120px; right: -80px;
        }

        .profile-avatar-lg {
            width: 80px; height: 80px;
            border-radius: 9999px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            font-size: 2rem;
            font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 6px 20px rgba(236,72,153,.4);
            border: 3px solid rgba(255,255,255,.15);
            position: relative; z-index: 1;
        }

        .profile-info { flex: 1; min-width: 200px; position: relative; z-index: 1; }

        .profile-nama {
            font-size: 1.6rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -.03em;
            line-height: 1.2;
            margin-bottom: 10px;
        }

        .profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 20px;
            font-size: .83rem;
            color: rgba(255,255,255,.6);
            font-weight: 500;
        }

        .profile-meta span {
            display: flex; align-items: center; gap: 5px;
        }

        .profile-meta svg { width: 13px; height: 13px; fill: rgba(255,255,255,.4); flex-shrink: 0; }

        .profile-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-start;
            position: relative; z-index: 1;
        }

        .btn-hero-primary {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 11px 20px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            font-weight: 700;
            font-size: .88rem;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 4px 16px rgba(236,72,153,.4);
            transition: all var(--transition);
        }

        .btn-hero-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(236,72,153,.5); }
        .btn-hero-primary svg { width: 16px; height: 16px; fill: #fff; }

        .btn-hero-ghost {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 11px 20px;
            background: rgba(255,255,255,.1);
            color: rgba(255,255,255,.85);
            font-weight: 700;
            font-size: .88rem;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,.15);
            cursor: pointer;
            text-decoration: none;
            transition: all var(--transition);
        }

        .btn-hero-ghost:hover { background: rgba(255,255,255,.18); }
        .btn-hero-ghost svg { width: 16px; height: 16px; fill: currentColor; }

        /* ---- Stat strip ---- */
        .stat-strip {
            display: flex;
            gap: 1px;
            background: var(--border);
            border-radius: var(--radius-sm);
            overflow: hidden;
            margin-bottom: 28px;
            box-shadow: var(--shadow-sm);
        }

        .stat-strip-item {
            flex: 1;
            background: var(--white);
            padding: 18px 20px;
            text-align: center;
        }

        .stat-strip-val   { font-size: 1.5rem; font-weight: 800; color: var(--text); letter-spacing: -.03em; }
        .stat-strip-label { font-size: .75rem; color: var(--text-muted); font-weight: 600; margin-top: 4px; text-transform: uppercase; letter-spacing: .06em; }

        /* ---- Timeline ---- */
        .timeline-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 20px; flex-wrap: wrap; gap: 10px;
        }

        .timeline-title {
            font-size: 1.05rem; font-weight: 800; color: var(--text);
            display: flex; align-items: center; gap: 8px;
        }

        .timeline-title svg { width: 18px; height: 18px; fill: var(--primary); }

        .visit-card {
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            overflow: hidden;
            margin-bottom: 20px;
            transition: all var(--transition);
            background: var(--white);
        }

        .visit-card:hover { border-color: var(--primary-200); box-shadow: var(--shadow-md); }

        .visit-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 22px;
            background: linear-gradient(180deg, #f9fafb, #fff);
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 10px;
            cursor: pointer;
        }

        .visit-header-left { display: flex; align-items: center; gap: 14px; }

        .visit-dot {
            width: 12px; height: 12px;
            border-radius: 50%;
            background: var(--primary);
            flex-shrink: 0;
            box-shadow: 0 0 0 4px var(--primary-100);
        }

        .visit-nama  { font-weight: 800; color: var(--text); font-size: .98rem; }
        .visit-tanggal { font-size: .78rem; color: var(--text-muted); font-weight: 600; margin-top: 3px; display: flex; align-items: center; gap: 5px; }
        .visit-tanggal svg { width: 11px; height: 11px; fill: #9ca3af; }

        .visit-badges { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }

        .badge-foto {
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: .7rem;
            font-weight: 800;
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .badge-foto.none {
            background: #f3f4f6;
            color: #9ca3af;
            border-color: var(--border);
        }

        .chevron-icon {
            width: 18px; height: 18px;
            fill: #9ca3af;
            transition: transform .25s;
            flex-shrink: 0;
        }

        .visit-body { padding: 20px 22px; display: none; }
        .visit-body.open { display: block; }

        .visit-catatan {
            font-size: .9rem; color: #4b5563; line-height: 1.7;
            white-space: pre-wrap;
            background: var(--bg);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 18px;
            border: 1px solid var(--border);
        }

        /* foto grid per kunjungan */
        .foto-section-title {
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--text-muted);
            margin-bottom: 10px;
            display: flex; align-items: center; gap: 6px;
        }

        .foto-section-title .badge-tipe {
            padding: 2px 8px; border-radius: 50px; font-size: .65rem; font-weight: 800;
        }

        .badge-before { background: #fce7f3; color: #be185d; }
        .badge-after  { background: #dcfce7; color: #15803d; }

        .foto-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .foto-thumb-item {
            position: relative;
            cursor: pointer;
        }

        .foto-thumb-item img {
            width: 110px; height: 110px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid var(--primary-200);
            display: block;
            transition: all var(--transition);
        }

        .foto-thumb-item img:hover {
            transform: scale(1.05) translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .foto-thumb-num {
            position: absolute;
            top: 5px; left: 7px;
            background: rgba(0,0,0,.55);
            color: #fff;
            font-size: .6rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 50px;
        }

        .foto-empty {
            font-size: .82rem; color: #d1d5db;
            font-style: italic;
        }

        /* empty state timeline */
        .no-treatment {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        .no-treatment-icon {
            width: 72px; height: 72px;
            background: var(--primary-100);
            border-radius: 9999px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }

        .no-treatment-icon svg { width: 32px; height: 32px; fill: var(--primary); }

        @media (max-width: 600px) {
            .profile-hero { padding: 24px 20px 20px; gap: 16px; }
            .profile-nama { font-size: 1.25rem; }
            .profile-avatar-lg { width: 60px; height: 60px; font-size: 1.4rem; }
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">

    <!-- Back breadcrumb -->
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;font-size:.85rem;color:var(--text-muted);">
        <a href="pasien.php" style="color:var(--primary);font-weight:700;display:flex;align-items:center;gap:4px;">
            <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:var(--primary)">
                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
            </svg>
            Daftar Pasien
        </a>
        <span>/</span>
        <span style="color:var(--text);font-weight:600;"><?= htmlspecialchars($pasien['nama']) ?></span>
    </div>

    <?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success" role="alert" style="margin-bottom:20px;">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg>
        <div>
            <strong>Treatment berhasil disimpan.</strong>
            Data kunjungan baru sudah tersimpan di riwayat pasien ini.
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($_GET['registered'])): ?>
    <div class="alert alert-success" role="alert" style="margin-bottom:20px;">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg>
        <div>
            <strong>Pasien berhasil didaftarkan.</strong>
            Silakan catat treatment pertama untuk pasien ini.
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($_GET['updated'])): ?>
    <div class="alert alert-success" role="alert" style="margin-bottom:20px;">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg>
        <div>
            <strong>Data pasien berhasil diperbarui.</strong>
        </div>
    </div>
    <?php endif; ?>
    <!-- Profile Hero -->
    <div class="profile-hero">
        <div class="profile-avatar-lg">
            <?= htmlspecialchars(strtoupper(mb_substr($pasien['nama'], 0, 1))) ?>
        </div>

        <div class="profile-info">
            <div class="profile-nama"><?= htmlspecialchars($pasien['nama']) ?></div>
            <div class="profile-meta">
                <?php if ($pasien['telepon']): ?>
                    <span>
                        <svg viewBox="0 0 24 24"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/></svg>
                        <?= htmlspecialchars($pasien['telepon']) ?>
                    </span>
                <?php endif; ?>
                <?php if ($pasien['email']): ?>
                    <span>
                        <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                        <?= htmlspecialchars($pasien['email']) ?>
                    </span>
                <?php endif; ?>
                <?php if ($umur !== null): ?>
                    <span>
                        <svg viewBox="0 0 24 24"><path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5c-1.1 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/></svg>
                        <?= $umur ?> tahun · <?= htmlspecialchars($pasien['tgl_lahir_fmt']) ?>
                    </span>
                <?php endif; ?>
                <?php if ($pasien['jenis_kelamin']): ?>
                    <span>
                        <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v2h20v-2c0-3.3-6.7-5-10-5z"/></svg>
                        <?= $pasien['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki' ?>
                    </span>
                <?php endif; ?>
                <?php if ($pasien['sumber_pasien']): ?>
                    <span>
                        <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                        <?= htmlspecialchars($pasien['sumber_pasien']) ?>
                    </span>
                <?php endif; ?>
                <span>
                    <svg viewBox="0 0 24 24"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                    Terdaftar <?= htmlspecialchars($pasien['terdaftar']) ?>
                </span>
            </div>
        </div>

        <div class="profile-actions">
            <a href="tambah_treatment.php?pasien_id=<?= $id ?>" class="btn-hero-primary">
                <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Tambah Treatment
            </a>
            <a href="edit_pasien.php?id=<?= $id ?>" class="btn-hero-ghost">
                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                Edit Profil
            </a>
            <a href="pasien.php" class="btn-hero-ghost">
                <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Semua Pasien
            </a>
        </div>
    </div>

    <!-- Stat Strip -->
    <div class="stat-strip">
        <div class="stat-strip-item">
            <div class="stat-strip-val"><?= $total_treatment ?></div>
            <div class="stat-strip-label">Total Kunjungan</div>
        </div>
        <div class="stat-strip-item">
            <?php
            $total_foto = 0;
            foreach ($treatments as $t) {
                $total_foto += count($t['foto_before']) + count($t['foto_after']);
            }
            ?>
            <div class="stat-strip-val"><?= $total_foto ?></div>
            <div class="stat-strip-label">Total Foto</div>
        </div>
        <div class="stat-strip-item">
            <?php
            $kunjungan_terakhir = $total_treatment > 0
                ? $treatments[0]['tanggal_fmt']
                : '—';
            ?>
            <div class="stat-strip-val" style="font-size:1rem;">
                <?= htmlspecialchars($kunjungan_terakhir) ?>
            </div>
            <div class="stat-strip-label">Kunjungan Terakhir</div>
        </div>
    </div>

    <!-- Catatan CRM -->
    <?php if ($pasien['catatan_crm']): ?>
    <div class="card" style="margin-bottom:24px;border-left:4px solid var(--primary);padding:20px 24px;">
        <div style="font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--primary);margin-bottom:8px;">
            Catatan CRM
        </div>
        <div style="font-size:.9rem;color:#374151;line-height:1.7;white-space:pre-wrap;">
            <?= htmlspecialchars($pasien['catatan_crm']) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Timeline Treatment -->
    <div class="card">
        <div class="timeline-header">
            <div class="timeline-title">
                <svg viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                Riwayat Kunjungan
            </div>
            <a href="tambah_treatment.php?pasien_id=<?= $id ?>" class="btn btn-primary btn-sm">
                <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                + Kunjungan Baru
            </a>
        </div>

        <?php if (empty($treatments)): ?>
            <div class="no-treatment">
                <div class="no-treatment-icon">
                    <svg viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                </div>
                <p style="font-weight:700;color:#6b7280;margin-bottom:8px;">Belum ada riwayat kunjungan</p>
                <a href="tambah_treatment.php?pasien_id=<?= $id ?>" class="btn btn-primary btn-sm">
                    Catat treatment pertama
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($treatments as $idx => $t):
                $jml_before = count($t['foto_before']);
                $jml_after  = count($t['foto_after']);
                $jml_foto   = $jml_before + $jml_after;
                $open_class = $idx === 0 ? 'open' : '';
            ?>
            <div class="visit-card">
                <!-- Header kunjungan (klikable) -->
                <div class="visit-header" onclick="toggleVisit(this)">
                    <div class="visit-header-left">
                        <?php if ($t['divisi_kode']): ?>
                            <div class="visit-dot"
                                 style="background:<?= htmlspecialchars($t['divisi_warna'] ?? '#ec4899') ?>;
                                        box-shadow:0 0 0 4px <?= htmlspecialchars($t['divisi_warna'] ?? '#ec4899') ?>22">
                            </div>
                        <?php else: ?>
                            <div class="visit-dot"></div>
                        <?php endif; ?>
                        <div>
                            <div class="visit-nama"><?= htmlspecialchars($t['nama_treatment']) ?></div>
                            <div class="visit-tanggal">
                                <svg viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                                <?= htmlspecialchars($t['tanggal_fmt']) ?>
                            </div>
                        </div>
                    </div>
                    <div class="visit-badges">
                        <?php if ($t['divisi_kode']): ?>
                            <?php $bc = $t['divisi_kode'] === 'ozthetique' ? 'badge-oz' : 'badge-hair'; ?>
                            <span class="divisi-badge-inline <?= $bc ?>">
                                <span class="dot" style="background:<?= htmlspecialchars($t['divisi_warna']) ?>"></span>
                                <?= htmlspecialchars($t['divisi_nama']) ?>
                            </span>
                        <?php endif; ?>
                        <span class="badge-foto <?= $jml_foto === 0 ? 'none' : '' ?>">
                            📷 <?= $jml_foto ?> foto
                        </span>
                        <svg class="chevron-icon" viewBox="0 0 24 24"
                             style="<?= $open_class ? 'transform:rotate(180deg)' : '' ?>">
                            <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                        </svg>
                    </div>
                </div>

                <!-- Body kunjungan -->
                <div class="visit-body <?= $open_class ?>">
                    <?php if ($t['catatan']): ?>
                        <div class="visit-catatan"><?= htmlspecialchars($t['catatan']) ?></div>
                    <?php endif; ?>

                    <?php if ($jml_before > 0): ?>
                        <div class="foto-section-title">
                            <span class="badge-tipe badge-before">BEFORE</span>
                            <?= $jml_before ?> foto
                        </div>
                        <div class="foto-row">
                            <?php foreach ($t['foto_before'] as $bi => $f): ?>
                                <div class="foto-thumb-item">
                                    <img src="uploads/<?= htmlspecialchars($f['nama_file']) ?>"
                                         alt="Before <?= htmlspecialchars($t['nama_treatment']) ?>"
                                         loading="lazy"
                                         onclick="bukaLightbox(this.src)">
                                    <?php if ($jml_before > 1): ?>
                                        <span class="foto-thumb-num"><?= $bi + 1 ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($jml_after > 0): ?>
                        <div class="foto-section-title">
                            <span class="badge-tipe badge-after">AFTER</span>
                            <?= $jml_after ?> foto
                        </div>
                        <div class="foto-row">
                            <?php foreach ($t['foto_after'] as $ai => $f): ?>
                                <div class="foto-thumb-item">
                                    <img src="uploads/<?= htmlspecialchars($f['nama_file']) ?>"
                                         alt="After <?= htmlspecialchars($t['nama_treatment']) ?>"
                                         loading="lazy"
                                         onclick="bukaLightbox(this.src)">
                                    <?php if ($jml_after > 1): ?>
                                        <span class="foto-thumb-num"><?= $ai + 1 ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($jml_foto === 0 && !$t['catatan']): ?>
                        <p class="foto-empty">Tidak ada foto maupun catatan untuk kunjungan ini.</p>
                    <?php elseif ($jml_foto === 0): ?>
                        <p class="foto-empty">Tidak ada foto untuk kunjungan ini.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div><!-- /main-content -->

<!-- Lightbox -->
<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Pratinjau foto">
    <button class="lightbox-close" id="lightbox-close" aria-label="Tutup">&times;</button>
    <img id="lightbox-img" src="" alt="Foto treatment">
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

    /* Lightbox */
    const lightbox = document.getElementById('lightbox');
    const lbImg    = document.getElementById('lightbox-img');
    const lbClose  = document.getElementById('lightbox-close');

    window.bukaLightbox = function (src) {
        lbImg.src = src;
        lightbox.classList.add('aktif');
        document.body.style.overflow = 'hidden';
    };

    function tutupLightbox() {
        lightbox.classList.remove('aktif');
        lbImg.src = '';
        document.body.style.overflow = '';
    }

    lbClose.addEventListener('click', tutupLightbox);
    lightbox.addEventListener('click', function (e) { if (e.target === lightbox) tutupLightbox(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutupLightbox(); });
}());

/* Accordion kunjungan */
function toggleVisit(header) {
    var body    = header.nextElementSibling;
    var chevron = header.querySelector('.chevron-icon');
    var isOpen  = body.classList.contains('open');

    body.classList.toggle('open', !isOpen);
    if (chevron) chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
}
</script>
</body>
</html>
