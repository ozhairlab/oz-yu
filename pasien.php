<?php
// ============================================================
//  pasien.php — Daftar Semua Pasien + Filter CRM
// ============================================================
require_once 'koneksi.php';
require_login();

$page_title  = 'Daftar Pasien';
$active_menu = 'pasien';

$keyword = trim($_GET['q']      ?? '');
$filter  = $_GET['filter']      ?? 'semua'; // semua | pasien | crm
$page    = max(1, (int)($_GET['page'] ?? 1));
$per     = 20;
$offset  = ($page - 1) * $per;
$like    = '%' . $keyword . '%';

// ---- WHERE clause berdasar filter ----
$where_parts = [];
$bind_types  = '';
$bind_vals   = [];

if ($keyword !== '') {
    $where_parts[] = 'p.nama LIKE ?';
    $bind_types   .= 's';
    $bind_vals[]   = $like;
}

if ($filter === 'crm') {
    $where_parts[] = "p.sumber_pasien = 'CRM'";
} elseif ($filter === 'pasien') {
    $where_parts[] = "(p.sumber_pasien IS NULL OR p.sumber_pasien != 'CRM')";
}

$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// ---- Hitung total ----
$sql_count = "SELECT COUNT(*) FROM pasien p $where_sql";
$stmt_c = $koneksi->prepare($sql_count);
if ($bind_types) $stmt_c->bind_param($bind_types, ...$bind_vals);
$stmt_c->execute();
$total = $stmt_c->get_result()->fetch_row()[0];
$stmt_c->close();
$total_pages = max(1, (int)ceil($total / $per));

// ---- Query data ----
$sort_sql = $filter === 'crm'
    ? 'ORDER BY p.created_at DESC'
    : ($keyword ? 'ORDER BY p.nama ASC' : 'ORDER BY p.created_at DESC');

$sql_data = "SELECT p.id, p.nama, p.telepon, p.sumber_pasien, p.created_at,
                    COUNT(rt.id) AS jumlah_treatment,
                    MAX(rt.tanggal_treatment) AS kunjungan_terakhir
             FROM pasien p
             LEFT JOIN riwayat_treatment rt ON rt.pasien_id = p.id
             $where_sql
             GROUP BY p.id
             $sort_sql
             LIMIT ? OFFSET ?";

$stmt = $koneksi->prepare($sql_data);
$types_data = $bind_types . 'ii';
$vals_data  = array_merge($bind_vals, [$per, $offset]);
$stmt->bind_param($types_data, ...$vals_data);
$stmt->execute();
$pasien_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---- Stat counts ----
$cnt_semua  = $koneksi->query('SELECT COUNT(*) FROM pasien')->fetch_row()[0];
$cnt_crm    = $koneksi->query("SELECT COUNT(*) FROM pasien WHERE sumber_pasien='CRM'")->fetch_row()[0];
$cnt_pasien = $cnt_semua - $cnt_crm;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Ozthetique</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* ── Stat strip ── */
        .stat-strip { display:flex; gap:1px; background:var(--border);
                      border-radius:var(--radius-sm); overflow:hidden;
                      margin-bottom:24px; box-shadow:var(--shadow-sm); }

        .stat-strip-item { flex:1; background:var(--surface);
                           padding:16px 18px; text-align:center; }

        .stat-strip-val   { font-size:1.4rem; font-weight:800; color:var(--text); letter-spacing:-.04em; }
        .stat-strip-label { font-size:.7rem; color:var(--text-muted); font-weight:700;
                            text-transform:uppercase; letter-spacing:.08em; margin-top:3px; }

        /* ── Filter tabs ── */
        .filter-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:20px; }

        .filter-tab {
            padding: 7px 16px;
            border-radius: 9999px;
            font-size: .8rem;
            font-weight: 700;
            border: 1.5px solid var(--border);
            background: var(--surface);
            color: var(--text-muted);
            cursor: pointer;
            text-decoration: none;
            transition: all var(--transition);
            display: inline-flex; align-items: center; gap: 6px;
        }

        .filter-tab:hover { border-color: var(--gold); color: var(--gold-dark); }

        .filter-tab.active-semua {
            background: var(--ink-3);
            color: #fff;
            border-color: var(--ink-3);
        }

        .filter-tab.active-pasien {
            background: linear-gradient(135deg, var(--primary-100), var(--primary-50));
            color: var(--primary-dark);
            border-color: var(--primary-200);
        }

        .filter-tab.active-crm {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--ink);
            border-color: var(--gold);
            box-shadow: 0 4px 12px rgba(201,169,110,.3);
        }

        .tab-count {
            background: rgba(0,0,0,.1);
            border-radius: 50px;
            padding: 1px 7px;
            font-size: .7rem;
        }

        .filter-tab.active-crm .tab-count  { background:rgba(0,0,0,.15); }
        .filter-tab.active-semua .tab-count { background:rgba(255,255,255,.15); color:#fff; }

        /* ── Table ── */
        .pasien-table-wrap { overflow-x:auto; }

        .pasien-table { width:100%; border-collapse:collapse; font-size:.88rem; }

        .pasien-table th {
            text-align:left; padding:12px 16px;
            background:var(--bg); font-size:.7rem; font-weight:800;
            text-transform:uppercase; letter-spacing:.09em;
            color:var(--text-muted); border-bottom:2px solid var(--border);
            white-space:nowrap;
        }

        .pasien-table td { padding:14px 16px; border-bottom:1px solid var(--border); vertical-align:middle; }
        .pasien-table tbody tr { transition:background var(--transition); cursor:pointer; }
        .pasien-table tbody tr:hover { background:rgba(201,169,110,.04); }
        .pasien-table tbody tr:last-child td { border-bottom:none; }

        /* CRM row highlight */
        .pasien-table tbody tr.crm-row:hover { background:rgba(201,169,110,.07); }

        /* Avatar */
        .pt-nama { display:flex; align-items:center; gap:12px; }

        .pt-avatar {
            width:38px; height:38px; border-radius:9999px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--ink); font-size:.88rem; font-weight:800;
            display:flex; align-items:center; justify-content:center; flex-shrink:0;
            box-shadow: 0 2px 8px rgba(201,169,110,.25);
        }

        .pt-avatar.av-pasien {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 2px 8px rgba(236,72,153,.25);
        }

        .pt-nama-text { font-weight:700; color:var(--text); }
        .pt-telepon   { font-size:.78rem; color:var(--text-muted); margin-top:2px; }

        /* Badges */
        .badge-crm {
            display:inline-flex; align-items:center; gap:4px;
            padding:2px 9px; border-radius:50px; font-size:.68rem; font-weight:800;
            background:rgba(201,169,110,.12); color:var(--gold-dark);
            border:1px solid rgba(201,169,110,.3);
        }

        .badge-treatment {
            display:inline-flex; align-items:center; gap:5px;
            padding:4px 11px; border-radius:9999px; font-size:.73rem; font-weight:800;
            background:var(--primary-100); color:var(--primary-dark);
            border:1px solid var(--primary-200);
        }

        .badge-treatment.none { background:#f3f4f6; color:#9ca3af; border-color:var(--border); }

        .kunjungan-text { font-size:.83rem; color:var(--text-muted); font-weight:500; }
        .terdaftar-text { font-size:.78rem; color:var(--text-light); }

        /* Action btn group */
        .btn-detail {
            padding:6px 11px; border-radius:8px; font-size:.74rem; font-weight:700;
            background:rgba(201,169,110,.1); color:var(--gold-dark);
            border:1px solid rgba(201,169,110,.25); white-space:nowrap;
            transition:all var(--transition);
            display:inline-flex; align-items:center; gap:4px;
        }
        .btn-detail:hover {
            background:linear-gradient(135deg, var(--gold), var(--gold-dark));
            color:var(--ink); border-color:var(--gold);
            transform:translateY(-1px); box-shadow:0 4px 12px rgba(201,169,110,.3);
        }
        .btn-detail svg { width:12px; height:12px; fill:currentColor; }

        .btn-edit {
            padding:6px 11px; border-radius:8px; font-size:.74rem; font-weight:700;
            background:rgba(99,102,241,.08); color:#4f46e5;
            border:1px solid rgba(99,102,241,.2); white-space:nowrap;
            transition:all var(--transition);
            display:inline-flex; align-items:center; gap:4px;
            text-decoration:none;
        }
        .btn-edit:hover {
            background:#eef2ff; border-color:#6366f1;
            transform:translateY(-1px); box-shadow:0 4px 12px rgba(99,102,241,.2);
        }
        .btn-edit svg { width:12px; height:12px; fill:currentColor; }

        .btn-hapus {
            padding:6px 11px; border-radius:8px; font-size:.74rem; font-weight:700;
            background:rgba(239,68,68,.07); color:#dc2626;
            border:1px solid rgba(239,68,68,.2); white-space:nowrap;
            transition:all var(--transition);
            display:inline-flex; align-items:center; gap:4px;
            cursor:pointer;
        }
        .btn-hapus:hover {
            background:#fef2f2; border-color:#ef4444;
            transform:translateY(-1px); box-shadow:0 4px 12px rgba(239,68,68,.2);
        }
        .btn-hapus svg { width:12px; height:12px; fill:currentColor; }

        .aksi-group { display:flex; gap:5px; align-items:center; flex-wrap:nowrap; }

        /* Modal hapus */
        .modal-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(5,5,15,.7); backdrop-filter:blur(4px);
            z-index:900; align-items:center; justify-content:center;
        }
        .modal-overlay.show { display:flex; }

        .modal-box {
            background:var(--surface); border-radius:var(--radius-md);
            padding:32px 28px; max-width:400px; width:90%;
            box-shadow:0 32px 80px rgba(0,0,0,.3);
            border:1px solid var(--border);
            animation:modalIn .2s ease;
        }

        @keyframes modalIn {
            from { opacity:0; transform:scale(.95) translateY(10px); }
            to   { opacity:1; transform:scale(1) translateY(0); }
        }

        .modal-icon {
            width:52px; height:52px; border-radius:50%;
            background:#fef2f2; border:1px solid #fecaca;
            display:flex; align-items:center; justify-content:center;
            margin:0 auto 16px;
        }
        .modal-icon svg { width:24px; height:24px; fill:#ef4444; }
        .modal-title { font-size:1.05rem; font-weight:800; color:var(--text); text-align:center; margin-bottom:8px; }
        .modal-sub   { font-size:.85rem; color:var(--text-muted); text-align:center; line-height:1.6; margin-bottom:22px; }
        .modal-nama  { font-weight:700; color:var(--text); }
        .modal-actions { display:flex; gap:10px; }
        .modal-actions .btn { flex:1; justify-content:center; }

        /* Toolbar */
        .toolbar { display:flex; align-items:center; gap:10px; margin-bottom:20px; flex-wrap:wrap; }

        .toolbar-search { position:relative; flex:1; min-width:200px; }

        .toolbar-search svg {
            position:absolute; left:13px; top:50%;
            transform:translateY(-50%);
            width:15px; height:15px; fill:#9ca3af; pointer-events:none;
        }

        .toolbar-search input {
            width:100%; padding:9px 13px 9px 38px;
            border:1.5px solid var(--border); border-radius:9px;
            font-size:.875rem; outline:none; font-family:inherit; font-weight:500;
            transition:all var(--transition); background:var(--surface);
        }

        .toolbar-search input:focus {
            border-color:var(--gold);
            box-shadow:0 0 0 3px rgba(201,169,110,.12);
        }

        .total-info { font-size:.83rem; color:var(--text-muted); font-weight:500; white-space:nowrap; }

        /* Pagination */
        .pagination { display:flex; justify-content:center; align-items:center;
                      gap:5px; padding:22px 0 4px; flex-wrap:wrap; }

        .page-btn {
            padding:7px 13px; border-radius:9px; font-size:.83rem; font-weight:700;
            border:1.5px solid var(--border); background:var(--surface);
            color:var(--text-muted); cursor:pointer; text-decoration:none;
            transition:all var(--transition);
            display:inline-flex; align-items:center; gap:4px;
        }
        .page-btn:hover { border-color:var(--gold); color:var(--gold-dark); }
        .page-btn.active {
            background:linear-gradient(135deg, var(--gold), var(--gold-dark));
            color:var(--ink); border-color:var(--gold);
            box-shadow:0 4px 12px rgba(201,169,110,.3);
        }
        .page-btn.disabled { opacity:.3; pointer-events:none; }

        .empty-row td { text-align:center; padding:60px 20px; color:#9ca3af; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">

    <div class="page-header">
        <div class="page-header-left">
            <h1>Daftar Pasien</h1>
            <p>Kelola seluruh data pasien dan kontak CRM klinik</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="crm_bulk.php" class="btn btn-secondary">
                <svg viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
                Import CRM
            </a>
            <a href="tambah_pasien.php" class="btn btn-primary">
                <svg viewBox="0 0 24 24"><path d="M15 12c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.7 0-8 1.3-8 4v2h16v-2c0-2.7-5.3-4-8-4z"/></svg>
                Tambah Pasien
            </a>
        </div>
    </div>

    <!-- Stat strip -->
    <?php if (!empty($_GET['deleted'])): ?>
    <div class="alert alert-success" style="margin-bottom:18px;">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg>
        <div>Pasien <strong><?= htmlspecialchars($_GET['nama'] ?? '') ?></strong> berhasil dihapus.</div>
    </div>
    <?php endif; ?>
    <div class="stat-strip">
        <div class="stat-strip-item">
            <div class="stat-strip-val"><?= number_format($cnt_semua) ?></div>
            <div class="stat-strip-label">Total Semua</div>
        </div>
        <div class="stat-strip-item">
            <div class="stat-strip-val" style="color:var(--primary-dark);"><?= number_format($cnt_pasien) ?></div>
            <div class="stat-strip-label">Pasien Aktif</div>
        </div>
        <div class="stat-strip-item">
            <div class="stat-strip-val" style="color:var(--gold-dark);"><?= number_format($cnt_crm) ?></div>
            <div class="stat-strip-label">Kontak CRM</div>
        </div>
    </div>

    <div class="card">
        <!-- Filter tabs -->
        <div class="filter-tabs">
            <?php
            $q_str = $keyword ? '&q='.urlencode($keyword) : '';
            ?>
            <a href="pasien.php?filter=semua<?= $q_str ?>"
               class="filter-tab <?= $filter==='semua' ? 'active-semua' : '' ?>">
                Semua
                <span class="tab-count"><?= number_format($cnt_semua) ?></span>
            </a>
            <a href="pasien.php?filter=pasien<?= $q_str ?>"
               class="filter-tab <?= $filter==='pasien' ? 'active-pasien' : '' ?>">
                <svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor">
                    <path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v2h20v-2c0-3.3-6.7-5-10-5z"/>
                </svg>
                Pasien
                <span class="tab-count"><?= number_format($cnt_pasien) ?></span>
            </a>
            <a href="pasien.php?filter=crm<?= $q_str ?>"
               class="filter-tab <?= $filter==='crm' ? 'active-crm' : '' ?>">
                <svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/>
                </svg>
                Kontak CRM
                <span class="tab-count"><?= number_format($cnt_crm) ?></span>
            </a>
        </div>

        <!-- Toolbar pencarian -->
        <form method="GET" action="pasien.php" class="toolbar">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <div class="toolbar-search">
                <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>"
                       placeholder="Cari nama pasien…" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-secondary" style="padding:9px 16px;font-size:.84rem;">Cari</button>
            <?php if ($keyword || $filter !== 'semua'): ?>
                <a href="pasien.php" class="btn btn-secondary" style="padding:9px 16px;font-size:.84rem;">Reset</a>
            <?php endif; ?>
            <span class="total-info"><?= number_format($total) ?> data</span>
        </form>

        <!-- Tabel -->
        <div class="pasien-table-wrap">
            <table class="pasien-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama</th>
                        <th>Status</th>
                        <th>Kunjungan Terakhir</th>
                        <th>Terdaftar</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($pasien_list)): ?>
                    <tr class="empty-row">
                        <td colspan="6">
                            <?php if ($filter === 'crm'): ?>
                                Belum ada kontak CRM.
                                <a href="tambah_pasien.php?tab=crm" style="color:var(--gold-dark);font-weight:700;">Tambah sekarang</a>
                                atau <a href="crm_bulk.php" style="color:var(--gold-dark);font-weight:700;">Import Bulk</a>.
                            <?php elseif ($keyword): ?>
                                Tidak ada hasil untuk "<strong><?= htmlspecialchars($keyword) ?></strong>".
                            <?php else: ?>
                                Belum ada data. <a href="tambah_pasien.php" style="color:var(--gold-dark);">Daftarkan sekarang</a>.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pasien_list as $i => $p):
                        $inisial  = strtoupper(mb_substr($p['nama'], 0, 1));
                        $no       = $offset + $i + 1;
                        $is_crm   = $p['sumber_pasien'] === 'CRM';
                        $av_class = $is_crm ? '' : 'av-pasien';
                        $row_cls  = $is_crm ? 'crm-row' : '';
                    ?>
                    <tr class="<?= $row_cls ?>" onclick="window.location='detail_pasien.php?id=<?= $p['id'] ?>'">
                        <td style="color:var(--text-light);font-size:.8rem;font-weight:600;"><?= $no ?></td>
                        <td>
                            <div class="pt-nama">
                                <div class="pt-avatar <?= $av_class ?>"><?= htmlspecialchars($inisial) ?></div>
                                <div>
                                    <div class="pt-nama-text">
                                        <?= htmlspecialchars($p['nama']) ?>
                                        <?php if ($is_crm): ?>
                                            <span class="badge-crm" style="margin-left:6px;vertical-align:middle;">
                                                <svg viewBox="0 0 24 24" style="width:9px;height:9px;fill:currentColor">
                                                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                                                </svg>
                                                CRM
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($p['telepon']): ?>
                                        <div class="pt-telepon"><?= htmlspecialchars($p['telepon']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($p['jumlah_treatment'] > 0): ?>
                                <span class="badge-treatment">
                                    <svg viewBox="0 0 24 24" style="width:10px;height:10px;fill:currentColor">
                                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.4-1.4L12 14.2l7.6-7.6L21 8l-9 9z"/>
                                    </svg>
                                    <?= $p['jumlah_treatment'] ?> treatment
                                </span>
                            <?php elseif ($is_crm): ?>
                                <span class="badge-crm">Prospek</span>
                            <?php else: ?>
                                <span class="badge-treatment none">Belum ada</span>
                            <?php endif; ?>
                        </td>
                        <td class="kunjungan-text">
                            <?= $p['kunjungan_terakhir']
                                ? date('d M Y', strtotime($p['kunjungan_terakhir']))
                                : '<span style="color:#d1d5db">—</span>' ?>
                        </td>
                        <td class="terdaftar-text"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                        <td>
                            <div class="aksi-group" onclick="event.stopPropagation()">
                                <a href="detail_pasien.php?id=<?= $p['id'] ?>" class="btn-detail">
                                    <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                    Lihat
                                </a>
                                <a href="edit_pasien.php?id=<?= $p['id'] ?>" class="btn-edit">
                                    <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                    Edit
                                </a>
                                <button type="button" class="btn-hapus"
                                        onclick="konfirmasiHapus(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nama']), ENT_QUOTES) ?>')">
                                    <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php $base = "?filter=".urlencode($filter)."&q=".urlencode($keyword); ?>
            <a href="<?= $base ?>&page=<?= $page-1 ?>"
               class="page-btn <?= $page<=1 ? 'disabled' : '' ?>">
                <svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
            </a>
            <?php for ($pg = max(1,$page-2); $pg <= min($total_pages,$page+2); $pg++): ?>
                <a href="<?= $base ?>&page=<?= $pg ?>"
                   class="page-btn <?= $pg===$page ? 'active' : '' ?>"><?= $pg ?></a>
            <?php endfor; ?>
            <a href="<?= $base ?>&page=<?= $page+1 ?>"
               class="page-btn <?= $page>=$total_pages ? 'disabled' : '' ?>">
                <svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div class="modal-overlay" id="modalHapus">
    <div class="modal-box">
        <div class="modal-icon">
            <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
        </div>
        <div class="modal-title">Hapus Pasien?</div>
        <div class="modal-sub">
            Anda akan menghapus pasien <span class="modal-nama" id="modal-nama-text"></span>.<br>
            Seluruh riwayat treatment dan foto akan ikut terhapus.<br>
            <strong style="color:#dc2626;">Tindakan ini tidak bisa dibatalkan.</strong>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="tutupModal()">Batal</button>
            <form method="POST" action="hapus_pasien.php" style="flex:1;">
                <input type="hidden" name="id" id="modal-hapus-id">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                <button type="submit" class="btn" style="width:100%;justify-content:center;background:#dc2626;color:#fff;box-shadow:0 4px 12px rgba(220,38,38,.3);">
                    <svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:#fff"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                    Ya, Hapus
                </button>
            </form>
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

function konfirmasiHapus(id, nama) {
    document.getElementById('modal-hapus-id').value = id;
    document.getElementById('modal-nama-text').textContent = nama;
    document.getElementById('modalHapus').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function tutupModal() {
    document.getElementById('modalHapus').classList.remove('show');
    document.body.style.overflow = '';
}

document.getElementById('modalHapus').addEventListener('click', function(e){
    if (e.target === this) tutupModal();
});

document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') tutupModal();
});
</script>
</body>
</html>
