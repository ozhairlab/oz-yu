<?php
// ============================================================
//  index.php — Dashboard Utama
// ============================================================
require_once 'koneksi.php';
require_login();

$page_title  = 'Dashboard';
$active_menu = 'dashboard';

// ── Stat cards ──
$total_pasien    = (int)$koneksi->query('SELECT COUNT(*) FROM pasien')->fetch_row()[0];
$total_crm       = (int)$koneksi->query("SELECT COUNT(*) FROM pasien WHERE sumber_pasien='CRM'")->fetch_row()[0];
$total_treatment = (int)$koneksi->query('SELECT COUNT(*) FROM riwayat_treatment')->fetch_row()[0];
$treatment_bulan = (int)$koneksi->query(
    "SELECT COUNT(*) FROM riwayat_treatment
     WHERE MONTH(tanggal_treatment)=MONTH(CURDATE())
     AND YEAR(tanggal_treatment)=YEAR(CURDATE())"
)->fetch_row()[0];

// ── Grafik: treatment per bulan (6 bulan terakhir) ──
$chart_labels = [];
$chart_data   = [];
for ($i = 5; $i >= 0; $i--) {
    $y = date('Y', strtotime("-$i months"));
    $m = date('m', strtotime("-$i months"));
    $lbl = date('M Y', strtotime("-$i months"));
    $cnt = (int)$koneksi->query(
        "SELECT COUNT(*) FROM riwayat_treatment
         WHERE YEAR(tanggal_treatment)=$y AND MONTH(tanggal_treatment)=$m"
    )->fetch_row()[0];
    $chart_labels[] = $lbl;
    $chart_data[]   = $cnt;
}

// ── Grafik: treatment per divisi (pie) ──
$divisi_res = $koneksi->query(
    "SELECT d.nama, d.warna, COUNT(rt.id) AS total
     FROM divisi d
     LEFT JOIN riwayat_treatment rt ON rt.divisi_id = d.id
     GROUP BY d.id ORDER BY total DESC"
);
$pie_labels = [];
$pie_data   = [];
$pie_colors = [];
while ($row = $divisi_res->fetch_assoc()) {
    $pie_labels[] = $row['nama'];
    $pie_data[]   = (int)$row['total'];
    $pie_colors[] = $row['warna'];
}

// ── Aktivitas terbaru (10 treatment terakhir) ──
$recent = $koneksi->query(
    "SELECT rt.id, rt.nama_treatment, rt.tanggal_treatment,
            p.id AS pasien_id, p.nama AS pasien_nama,
            d.nama AS divisi_nama, d.warna AS divisi_warna, d.kode AS divisi_kode
     FROM riwayat_treatment rt
     JOIN pasien p ON p.id = rt.pasien_id
     LEFT JOIN divisi d ON d.id = rt.divisi_id
     ORDER BY rt.id DESC LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

// ── Pasien baru (5 terbaru) ──
$pasien_baru = $koneksi->query(
    "SELECT id, nama, telepon, sumber_pasien,
            DATE_FORMAT(created_at,'%d %b %Y') AS terdaftar
     FROM pasien ORDER BY created_at DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Ozthetique</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ── Clickable stat cards ── */
        .stat-card {
            cursor: pointer;
            text-decoration: none;
            display: flex;
        }

        .stat-card:hover .stat-value {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ── Dashboard grid ── */
        .dash-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 22px;
            margin-top: 22px;
        }

        .dash-grid-left  { display: flex; flex-direction: column; gap: 22px; }
        .dash-grid-right { display: flex; flex-direction: column; gap: 22px; }

        /* ── Chart card ── */
        .chart-card {
            background: var(--surface);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            padding: 24px 26px;
            border: 1px solid rgba(229,231,235,.8);
        }

        .chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .chart-title {
            font-size: .95rem;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -.02em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-title svg { width: 17px; height: 17px; fill: var(--gold); }

        .chart-badge {
            font-size: .72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 50px;
            background: rgba(201,169,110,.1);
            color: var(--gold-dark);
            border: 1px solid rgba(201,169,110,.2);
        }

        .chart-wrap { position: relative; }

        /* ── Activity feed ── */
        .activity-list { display: flex; flex-direction: column; }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
            transition: background var(--transition);
            cursor: pointer;
            border-radius: 8px;
            padding: 11px 10px;
            margin: 0 -10px;
            text-decoration: none;
            color: inherit;
        }

        .activity-item:last-child { border-bottom: none; }
        .activity-item:hover { background: rgba(201,169,110,.05); }

        .activity-dot {
            width: 36px; height: 36px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            background: rgba(201,169,110,.12);
        }

        .activity-dot svg { width: 16px; height: 16px; fill: var(--gold-dark); }

        .activity-body { flex: 1; min-width: 0; }

        .activity-title {
            font-size: .85rem;
            font-weight: 700;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .activity-sub {
            font-size: .75rem;
            color: var(--text-muted);
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .activity-date {
            font-size: .72rem;
            color: var(--text-light);
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* ── Pasien baru list ── */
        .pasien-baru-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            margin: 0 -10px;
            border-radius: 9px;
            cursor: pointer;
            transition: background var(--transition);
            text-decoration: none;
            color: inherit;
        }

        .pasien-baru-item:hover { background: rgba(201,169,110,.06); }

        .pb-avatar {
            width: 34px; height: 34px;
            border-radius: 9999px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--ink);
            font-size: .82rem;
            font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        .pb-avatar.av-crm {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
        }

        .pb-name  { font-size: .85rem; font-weight: 700; color: var(--text); }
        .pb-meta  { font-size: .72rem; color: var(--text-muted); margin-top: 1px; }
        .pb-date  { font-size: .7rem; color: var(--text-light); white-space: nowrap; flex-shrink: 0; margin-left: auto; }

        /* ── Quick actions ── */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .qa-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 18px 12px;
            border-radius: var(--radius-sm);
            font-size: .78rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--transition);
            border: 1.5px solid var(--border);
            background: var(--surface);
            color: var(--text);
            text-align: center;
        }

        .qa-btn:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }

        .qa-icon {
            width: 40px; height: 40px;
            border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
        }

        .qa-icon svg { width: 20px; height: 20px; fill: currentColor; }

        .qa-btn:nth-child(1) .qa-icon { background: rgba(201,169,110,.15); color: var(--gold-dark); }
        .qa-btn:nth-child(2) .qa-icon { background: rgba(236,72,153,.12); color: var(--primary-dark); }
        .qa-btn:nth-child(3) .qa-icon { background: rgba(99,102,241,.12); color: #4f46e5; }
        .qa-btn:nth-child(4) .qa-icon { background: rgba(16,185,129,.12); color: #059669; }

        .qa-btn:nth-child(1):hover { border-color: var(--gold); }
        .qa-btn:nth-child(2):hover { border-color: var(--primary); }
        .qa-btn:nth-child(3):hover { border-color: #6366f1; }
        .qa-btn:nth-child(4):hover { border-color: #10b981; }

        /* ── Stat card link style ── */
        a.stat-card { color: inherit; }

        /* ── Search section ── */
        .search-section {
            background: var(--surface);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            padding: 24px 26px;
            border: 1px solid rgba(229,231,235,.8);
        }

        @media (max-width: 960px) {
            .dash-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h1>Dashboard</h1>
            <p>Selamat datang kembali, <strong><?= htmlspecialchars($_SESSION['admin_username']) ?></strong> 👋
               &nbsp;·&nbsp; <?= date('l, d F Y') ?></p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="tambah_pasien.php" class="btn btn-secondary">
                <svg viewBox="0 0 24 24"><path d="M15 12c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.7 0-8 1.3-8 4v2h16v-2c0-2.7-5.3-4-8-4z"/></svg>
                Pasien Baru
            </a>
            <a href="tambah_treatment.php" class="btn btn-primary">
                <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Catat Treatment
            </a>
        </div>
    </div>

    <!-- Stat Cards — clickable -->
    <div class="stats-grid">
        <a href="pasien.php" class="stat-card">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($total_pasien) ?></div>
                <div class="stat-label">Total Pasien</div>
            </div>
        </a>
        <a href="pasien.php?filter=crm" class="stat-card">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($total_crm) ?></div>
                <div class="stat-label">Kontak CRM</div>
            </div>
        </a>
        <a href="pasien.php" class="stat-card">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.4-1.4L12 14.2l7.6-7.6L21 8l-9 9z"/></svg>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($total_treatment) ?></div>
                <div class="stat-label">Total Treatment</div>
            </div>
        </a>
        <a href="tambah_treatment.php" class="stat-card">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($treatment_bulan) ?></div>
                <div class="stat-label">Treatment Bulan Ini</div>
            </div>
        </a>
    </div>

    <!-- Dashboard Grid -->
    <div class="dash-grid">

        <!-- LEFT -->
        <div class="dash-grid-left">

            <!-- Bar Chart: Treatment 6 bulan -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <svg viewBox="0 0 24 24"><path d="M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z"/></svg>
                        Treatment per Bulan
                    </div>
                    <span class="chart-badge">6 Bulan Terakhir</span>
                </div>
                <div class="chart-wrap" style="height:220px;">
                    <canvas id="chartBar"></canvas>
                </div>
            </div>

            <!-- Search -->
            <div class="search-section">
                <div class="chart-title" style="margin-bottom:16px;">
                    <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    Cari Riwayat Pasien
                </div>
                <div class="search-bar">
                    <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    <input type="text" id="input-cari" class="search-input"
                           placeholder="Ketik nama pasien untuk mencari riwayat treatment…"
                           autocomplete="off" aria-label="Cari nama pasien">
                </div>
                <div class="loading-wrap" id="loading" role="status" aria-live="polite">
                    <div class="spinner"></div><span>Mencari…</span>
                </div>
                <div id="hasil-cari" aria-live="polite"></div>
            </div>

            <!-- Aktivitas terbaru -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <svg viewBox="0 0 24 24"><path d="M13 2.05V4.07c3.39.49 6 3.39 6 6.93 0 3.21-1.81 6-4.72 7.28L13 17v5l5-2.93-1.06-1.05C19.71 16.14 21 13.21 21 11c0-4.97-3.93-9.03-8-9.95zm-2 0C6.93 2.97 3 7.03 3 11c0 3.21 1.29 6.14 3.5 8.14L5.44 20.26 10.5 23l.5-1v-5l-1.28 1.28C7.81 17 6 14.21 6 11c0-3.54 2.61-6.44 6-6.93V2.05z"/></svg>
                        Aktivitas Terbaru
                    </div>
                    <a href="pasien.php" style="font-size:.78rem;color:var(--gold-dark);font-weight:700;">Lihat Semua →</a>
                </div>
                <div class="activity-list">
                    <?php if (empty($recent)): ?>
                        <p style="color:var(--text-light);font-size:.85rem;text-align:center;padding:20px 0;">Belum ada aktivitas treatment.</p>
                    <?php else: ?>
                        <?php foreach ($recent as $r):
                            $divisi_warna = ui_hex($r['divisi_warna'] ?? null, '#64748b');
                            $divisi_badge_style = 'background:' . ui_hex_alpha($divisi_warna, '14') . ';'
                                . 'color:' . $divisi_warna . ';'
                                . 'border:1px solid ' . ui_hex_alpha($divisi_warna, '33') . ';';
                        ?>
                        <a href="detail_pasien.php?id=<?= $r['pasien_id'] ?>" class="activity-item">
                            <div class="activity-dot">
                                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.4-1.4L12 14.2l7.6-7.6L21 8l-9 9z"/></svg>
                            </div>
                            <div class="activity-body">
                                <div class="activity-title"><?= htmlspecialchars($r['nama_treatment']) ?></div>
                                <div class="activity-sub">
                                    <span><?= htmlspecialchars($r['pasien_nama']) ?></span>
                                    <?php if ($r['divisi_nama']): ?>
                                        <span class="divisi-badge-inline" style="padding:1px 8px;font-size:.65rem;<?= htmlspecialchars($divisi_badge_style) ?>">
                                            <span class="dot" style="width:5px;height:5px;background:<?= htmlspecialchars($divisi_warna) ?>"></span>
                                            <?= htmlspecialchars($r['divisi_nama']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="activity-date"><?= date('d M', strtotime($r['tanggal_treatment'])) ?></div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT -->
        <div class="dash-grid-right">

            <!-- Quick Actions -->
            <div class="chart-card" style="padding:20px;">
                <div class="chart-title" style="margin-bottom:14px;">
                    <svg viewBox="0 0 24 24"><path d="M13 3L4 14h7v7l9-11h-7z"/></svg>
                    Aksi Cepat
                </div>
                <div class="quick-actions">
                    <a href="tambah_pasien.php" class="qa-btn">
                        <div class="qa-icon"><svg viewBox="0 0 24 24"><path d="M15 12c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.7 0-8 1.3-8 4v2h16v-2c0-2.7-5.3-4-8-4z"/></svg></div>
                        Pasien Baru
                    </a>
                    <a href="tambah_treatment.php" class="qa-btn">
                        <div class="qa-icon"><svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg></div>
                        Catat Treatment
                    </a>
                    <a href="tambah_pasien.php?tab=crm" class="qa-btn">
                        <div class="qa-icon"><svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg></div>
                        Quick CRM
                    </a>
                    <a href="crm_bulk.php" class="qa-btn">
                        <div class="qa-icon"><svg viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg></div>
                        Import Bulk
                    </a>
                </div>
            </div>

            <!-- Pie Chart: Divisi -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <svg viewBox="0 0 24 24"><path d="M11 2v20c-5.07-.5-9-4.79-9-10s3.93-9.5 9-10zm2.03 0v8.99H22c-.47-4.74-4.24-8.52-8.97-8.99zm0 11.01V22c4.74-.47 8.5-4.25 8.97-8.99h-8.97z"/></svg>
                        Treatment per Divisi
                    </div>
                </div>
                <div class="chart-wrap" style="height:200px;">
                    <canvas id="chartPie"></canvas>
                </div>
                <div id="pie-legend" style="display:flex;flex-direction:column;gap:6px;margin-top:14px;"></div>
            </div>

            <!-- Pasien Baru -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v2h20v-2c0-3.3-6.7-5-10-5z"/></svg>
                        Pasien Terbaru
                    </div>
                    <a href="pasien.php" style="font-size:.78rem;color:var(--gold-dark);font-weight:700;">Lihat Semua →</a>
                </div>
                <?php foreach ($pasien_baru as $pb):
                    $init   = strtoupper(mb_substr($pb['nama'], 0, 1));
                    $is_crm = $pb['sumber_pasien'] === 'CRM';
                ?>
                <a href="detail_pasien.php?id=<?= $pb['id'] ?>" class="pasien-baru-item">
                    <div class="pb-avatar <?= $is_crm ? 'av-crm' : '' ?>"><?= htmlspecialchars($init) ?></div>
                    <div style="flex:1;min-width:0;">
                        <div class="pb-name"><?= htmlspecialchars($pb['nama']) ?>
                            <?php if ($is_crm): ?>
                                <span style="font-size:.65rem;background:rgba(201,169,110,.12);color:var(--gold-dark);padding:1px 6px;border-radius:50px;border:1px solid rgba(201,169,110,.25);font-weight:800;margin-left:4px;">CRM</span>
                            <?php endif; ?>
                        </div>
                        <div class="pb-meta"><?= htmlspecialchars($pb['telepon'] ?: 'Tidak ada telepon') ?></div>
                    </div>
                    <div class="pb-date"><?= htmlspecialchars($pb['terdaftar']) ?></div>
                </a>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

</div><!-- /main-content -->

<!-- Lightbox -->
<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Pratinjau foto">
    <button class="lightbox-close" id="lightbox-close" aria-label="Tutup">&times;</button>
    <img id="lightbox-img" src="" alt="Foto treatment">
</div>

<script>
(function () {
    'use strict';

    /* ── Sidebar ── */
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');
    toggleBtn.addEventListener('click', function () {
        sidebar.classList.toggle('open'); overlay.classList.toggle('show');
    });
    overlay.addEventListener('click', function () {
        sidebar.classList.remove('open'); overlay.classList.remove('show');
    });

    /* ── Chart.js global defaults ── */
    Chart.defaults.font.family = "'Inter', -apple-system, sans-serif";
    Chart.defaults.color       = '#6b7280';

    /* ── Bar Chart: Treatment per Bulan ── */
    const barLabels = <?= json_encode($chart_labels) ?>;
    const barData   = <?= json_encode($chart_data) ?>;

    new Chart(document.getElementById('chartBar'), {
        type: 'bar',
        data: {
            labels: barLabels,
            datasets: [{
                label: 'Treatment',
                data: barData,
                backgroundColor: function(ctx) {
                    var chart = ctx.chart;
                    var gc = chart.ctx.createLinearGradient(0, 0, 0, chart.height);
                    gc.addColorStop(0, 'rgba(201,169,110,.9)');
                    gc.addColorStop(1, 'rgba(201,169,110,.2)');
                    return gc;
                },
                borderColor: 'rgba(201,169,110,1)',
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f0f18',
                    titleColor: '#c9a96e',
                    bodyColor: '#fff',
                    borderColor: 'rgba(201,169,110,.3)',
                    borderWidth: 1,
                    padding: 10,
                    callbacks: {
                        label: function(ctx) { return '  ' + ctx.parsed.y + ' treatment'; }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { font: { size: 11, weight: '600' } }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: { size: 11 }
                    },
                    grid: { color: 'rgba(0,0,0,.05)' },
                    border: { display: false }
                }
            }
        }
    });

    /* ── Pie Chart: Treatment per Divisi ── */
    const pieLabels = <?= json_encode($pie_labels) ?>;
    const pieData   = <?= json_encode($pie_data) ?>;
    const pieColors = <?= json_encode($pie_colors) ?>;

    if (pieData.reduce(function(a,b){return a+b;},0) > 0) {
        new Chart(document.getElementById('chartPie'), {
            type: 'doughnut',
            data: {
                labels: pieLabels,
                datasets: [{
                    data: pieData,
                    backgroundColor: pieColors,
                    borderColor: '#fff',
                    borderWidth: 3,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f0f18',
                        titleColor: '#c9a96e',
                        bodyColor: '#fff',
                        borderColor: 'rgba(201,169,110,.3)',
                        borderWidth: 1,
                        padding: 10
                    }
                }
            }
        });

        // Custom legend
        var legend = document.getElementById('pie-legend');
        pieLabels.forEach(function(lbl, i) {
            var total = pieData.reduce(function(a,b){return a+b;}, 0);
            var pct   = total > 0 ? Math.round(pieData[i]/total*100) : 0;
            var div   = document.createElement('div');
            div.style.cssText = 'display:flex;align-items:center;justify-content:space-between;font-size:.78rem;';
            div.innerHTML =
                '<div style="display:flex;align-items:center;gap:7px;">' +
                    '<span style="width:10px;height:10px;border-radius:3px;background:'+pieColors[i]+';flex-shrink:0;display:inline-block;"></span>' +
                    '<span style="font-weight:600;color:#374151;">' + lbl + '</span>' +
                '</div>' +
                '<span style="font-weight:800;color:#374151;">' + pieData[i] + ' <span style="color:#9ca3af;font-weight:500;">(' + pct + '%)</span></span>';
            legend.appendChild(div);
        });
    } else {
        document.getElementById('chartPie').parentElement.innerHTML =
            '<p style="text-align:center;color:#9ca3af;padding:40px 0;font-size:.85rem;">Belum ada data treatment.</p>';
    }

    /* ── AJAX Pencarian ── */
    const inputCari = document.getElementById('input-cari');
    const hasilCari = document.getElementById('hasil-cari');
    const loading   = document.getElementById('loading');
    let   debounce  = null;

    inputCari.addEventListener('input', function () {
        clearTimeout(debounce);
        var kw = this.value.trim();
        if (!kw) { hasilCari.innerHTML = ''; loading.classList.remove('show'); return; }
        if (kw.length < 2) return;
        loading.classList.add('show');
        hasilCari.innerHTML = '';
        debounce = setTimeout(function () { cariPasien(kw); }, 300);
    });

    function cariPasien(kw) {
        fetch('proses_cari.php?q=' + encodeURIComponent(kw), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function(data) { loading.classList.remove('show'); renderHasil(data); })
        .catch(function(err) {
            loading.classList.remove('show');
            hasilCari.innerHTML = '<p style="color:#c62828;text-align:center;padding:20px">Terjadi kesalahan. Coba lagi.</p>';
        });
    }

    function renderHasil(data) {
        if (!data.pasien || !data.pasien.length) {
            hasilCari.innerHTML =
                '<div class="empty-state">' +
                '<div class="empty-state-icon"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg></div>' +
                '<h3>Pasien tidak ditemukan</h3>' +
                '<p>Coba ketik nama lain atau <a href="tambah_pasien.php" style="color:var(--gold-dark)">daftarkan pasien baru</a>.</p>' +
                '</div>';
            return;
        }

        var html = '';
        data.pasien.forEach(function(p) {
            var inisial = p.nama ? p.nama.charAt(0).toUpperCase() : '?';
            html += '<div class="pasien-card">';
            html += '<div class="pasien-header">';
            html += '<div class="pasien-info">';
            html += '<div class="pasien-avatar">' + escHtml(inisial) + '</div><div>';
            html += '<a href="detail_pasien.php?id=' + p.id + '" class="pasien-nama" style="text-decoration:none;">' + escHtml(p.nama) + '</a>';
            html += '<div class="pasien-meta">';
            if (p.telepon) html += '<span>' + escHtml(p.telepon) + '</span>';
            if (p.tanggal_lahir) html += '<span>' + escHtml(p.tanggal_lahir) + '</span>';
            html += '<span>Terdaftar: ' + escHtml(p.created_at) + '</span>';
            html += '</div></div></div>';
            html += '<a href="detail_pasien.php?id=' + p.id + '" class="btn btn-primary btn-sm">Lihat Profil</a>';
            html += '</div>';

            html += '<div class="timeline">';
            if (!p.treatments || !p.treatments.length) {
                html += '<p style="color:#bbb;font-size:.85rem;text-align:center;padding:8px 0">Belum ada riwayat treatment.</p>';
            } else {
                p.treatments.forEach(function(t) {
                    html += '<div class="timeline-item">';
                    html += '<div class="treatment-head">';
                    html += '<span class="treatment-nama">' + escHtml(t.nama_treatment) + '</span>';
                    html += '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">';
                    if (t.divisi_kode) {
                        var warna = /^#[0-9a-fA-F]{6}$/.test(String(t.divisi_warna || '')) ? t.divisi_warna : '#64748b';
                        html += '<span class="divisi-badge-inline" style="background:'+warna+'14;color:'+warna+';border:1px solid '+warna+'33;">' +
                            '<span class="dot" style="background:'+warna+'"></span>'+escHtml(t.divisi_nama)+'</span>';
                    }
                    html += '<span class="treatment-badge">📅 ' + escHtml(t.tanggal_treatment) + '</span>';
                    html += '</div></div>';
                    if (t.catatan) html += '<div class="treatment-catatan">' + escHtml(t.catatan) + '</div>';
                    var hasFoto = (t.foto_before && t.foto_before.length) || (t.foto_after && t.foto_after.length);
                    if (hasFoto) {
                        html += '<div class="foto-grid">';
                        (t.foto_before || []).forEach(function(f) {
                            html += '<div class="foto-item"><p>Before</p><img src="uploads/'+escHtml(f)+'" loading="lazy" onclick="bukaLightbox(this.src)"></div>';
                        });
                        (t.foto_after || []).forEach(function(f) {
                            html += '<div class="foto-item"><p>After</p><img src="uploads/'+escHtml(f)+'" loading="lazy" onclick="bukaLightbox(this.src)"></div>';
                        });
                        html += '</div>';
                    }
                    html += '</div>';
                });
            }
            html += '</div></div>';
        });
        hasilCari.innerHTML = html;
    }

    function escHtml(s) {
        if (s == null) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ── Lightbox ── */
    var lightbox = document.getElementById('lightbox');
    var lbImg    = document.getElementById('lightbox-img');
    document.getElementById('lightbox-close').addEventListener('click', tutupLightbox);
    lightbox.addEventListener('click', function(e) { if (e.target === lightbox) tutupLightbox(); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') tutupLightbox(); });

    window.bukaLightbox = function(src) {
        lbImg.src = src;
        lightbox.classList.add('aktif');
        document.body.style.overflow = 'hidden';
    };

    function tutupLightbox() {
        lightbox.classList.remove('aktif');
        lbImg.src = '';
        document.body.style.overflow = '';
    }

}());
</script>
</body>
</html>
