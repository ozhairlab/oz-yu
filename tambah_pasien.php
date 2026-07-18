<?php
// ============================================================
//  tambah_pasien.php — Registrasi Pasien (Lengkap & Quick CRM)
// ============================================================
require_once 'koneksi.php';
require_login();

$page_title  = 'Tambah Pasien';
$active_menu = 'pasien';

$errors  = [];
$sukses  = false;
$mode    = $_POST['mode'] ?? 'lengkap'; // 'lengkap' | 'crm'
$old     = ['nama'=>'','telepon'=>'','tanggal_lahir'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode    = $_POST['mode'] ?? 'lengkap';
    $nama    = trim($_POST['nama']    ?? '');
    $telepon = trim($_POST['telepon'] ?? '');

    $old['nama']    = $nama;
    $old['telepon'] = $telepon;

    // Validasi nama (wajib di kedua mode)
    if ($nama === '') {
        $errors[] = 'Nama pasien wajib diisi.';
    } elseif (mb_strlen($nama) > 150) {
        $errors[] = 'Nama terlalu panjang (maks 150 karakter).';
    }

    if ($telepon !== '' && !preg_match('/^[\d\s\+\-\(\)]{6,20}$/', $telepon)) {
        $errors[] = 'Format nomor telepon tidak valid.';
    }

    // Field tambahan hanya untuk mode lengkap
    $tanggal_lahir = '';
    if ($mode === 'lengkap') {
        $tanggal_lahir      = trim($_POST['tanggal_lahir'] ?? '');
        $old['tanggal_lahir'] = $tanggal_lahir;
        if ($tanggal_lahir !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_lahir)) {
            $errors[] = 'Format tanggal lahir tidak valid.';
        }
    }

    if (empty($errors)) {
        $telepon_val       = $telepon       ?: null;
        $tanggal_lahir_val = $tanggal_lahir ?: null;
        // Mode CRM: sumber_pasien = 'CRM'
        $sumber_val = ($mode === 'crm') ? 'CRM' : null;

        $stmt = $koneksi->prepare(
            'INSERT INTO pasien (nama, telepon, tanggal_lahir, sumber_pasien) VALUES (?,?,?,?)'
        );
        $stmt->bind_param('ssss', $nama, $telepon_val, $tanggal_lahir_val, $sumber_val);

        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $stmt->close();
            if ($mode === 'crm') {
                // Kembali ke form CRM untuk input berikutnya, bawa flash
                header('Location: tambah_pasien.php?tab=crm&saved=1&last=' . urlencode($nama));
            } else {
                header('Location: detail_pasien.php?id=' . $new_id . '&registered=1');
            }
            exit;
        } else {
            $errors[] = 'Gagal menyimpan data. Silakan coba lagi.';
            error_log('Insert pasien gagal: ' . $stmt->error);
            $stmt->close();
        }
    }
}

$total_pasien = $koneksi->query('SELECT COUNT(*) FROM pasien')->fetch_row()[0];
$total_crm    = $koneksi->query("SELECT COUNT(*) FROM pasien WHERE sumber_pasien='CRM'")->fetch_row()[0];

// Tab aktif dari GET (setelah redirect) atau POST
$active_tab = $_GET['tab'] ?? ($mode === 'crm' ? 'crm' : 'lengkap');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Ozthetique</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .form-layout { display:grid; grid-template-columns:1fr 320px; gap:22px; align-items:start; }

        /* Tab switcher */
        .tab-switcher {
            display: flex;
            background: var(--bg);
            border-radius: var(--radius-sm);
            padding: 4px;
            gap: 4px;
            margin-bottom: 28px;
            border: 1px solid var(--border);
        }

        .tab-btn {
            flex: 1;
            padding: 10px 14px;
            border-radius: 8px;
            border: none;
            font-size: .84rem;
            font-weight: 700;
            cursor: pointer;
            transition: all var(--transition);
            background: transparent;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            font-family: inherit;
        }

        .tab-btn svg { width: 15px; height: 15px; fill: currentColor; flex-shrink: 0; }

        .tab-btn.active-lengkap {
            background: var(--surface);
            color: var(--text);
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        .tab-btn.active-crm {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--ink);
            box-shadow: 0 4px 12px rgba(201,169,110,.3);
        }

        /* Tab panels */
        .tab-panel { display: none; }
        .tab-panel.show { display: block; }

        /* Input icon wrap */
        .input-icon-wrap { position: relative; }
        .input-icon-wrap svg.ic {
            position: absolute; left: 13px; top: 50%;
            transform: translateY(-50%);
            width: 15px; height: 15px; fill: var(--text-light);
            pointer-events: none;
        }
        .input-icon-wrap .form-control { padding-left: 40px; }
        .input-icon-wrap:focus-within svg.ic { fill: var(--gold-dark); }

        /* CRM quick row */
        .crm-quick-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 10px;
            align-items: end;
            margin-bottom: 10px;
        }

        @media (max-width: 640px) {
            .crm-quick-row { grid-template-columns: 1fr; }
        }

        /* Aside dark */
        .form-aside {
            background: var(--ink-3);
            border-radius: var(--radius-md);
            padding: 26px 22px;
            color: #fff;
            border: 1px solid rgba(201,169,110,.12);
            position: sticky;
            top: calc(var(--topbar-h) + 16px);
        }

        .aside-h { font-size:.85rem; font-weight:800; color:var(--gold-light);
                   margin-bottom:14px; letter-spacing:.04em; text-transform:uppercase; }

        .aside-tip { display:flex; align-items:flex-start; gap:10px;
                     margin-bottom:13px; font-size:.8rem;
                     color:rgba(255,255,255,.55); line-height:1.6; }

        .aside-tip-dot { width:6px; height:6px; border-radius:50%;
                         background:var(--gold); flex-shrink:0; margin-top:6px; }

        .aside-stat-row { display:flex; gap:10px; margin-top:18px;
                          padding-top:18px; border-top:1px solid rgba(255,255,255,.07); }

        .aside-stat-box { flex:1; background:rgba(255,255,255,.05);
                          border-radius:10px; padding:12px; text-align:center; }

        .aside-stat-val   { font-size:1.5rem; font-weight:800; color:#fff;
                            letter-spacing:-.04em; }
        .aside-stat-label { font-size:.68rem; color:rgba(255,255,255,.4);
                            font-weight:600; text-transform:uppercase; letter-spacing:.08em; margin-top:3px; }

        .crm-badge { display:inline-flex; align-items:center; gap:5px;
                     padding:3px 10px; border-radius:50px;
                     background:rgba(201,169,110,.15); color:var(--gold-light);
                     font-size:.72rem; font-weight:800; border:1px solid rgba(201,169,110,.25); }

        @media (max-width: 860px) {
            .form-layout { grid-template-columns: 1fr; }
            .form-aside  { position:static; }
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">

    <div class="page-header">
        <div class="page-header-left">
            <h1>Tambah Pasien</h1>
            <p>Daftarkan pasien baru — mode lengkap atau cepat untuk CRM</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="crm_bulk.php" class="btn btn-secondary">
                <svg viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
                Import Bulk CRM
            </a>
            <a href="pasien.php" class="btn btn-secondary">
                <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Daftar Pasien
            </a>
        </div>
    </div>

    <?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success" style="margin-bottom:18px;">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg>
        <div>
            Kontak <strong><?= htmlspecialchars($_GET['last'] ?? '') ?></strong> berhasil disimpan ke CRM.
            Lanjutkan input kontak berikutnya.
        </div>
    </div>
    <?php endif; ?>

    <div class="form-layout">
        <div class="card">

            <!-- Tab Switcher -->
            <div class="tab-switcher" role="tablist">
                <button type="button" role="tab"
                        class="tab-btn <?= $active_tab !== 'crm' ? 'active-lengkap' : '' ?>"
                        id="tab-lengkap" onclick="switchTab('lengkap')">
                    <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
                    Form Lengkap
                </button>
                <button type="button" role="tab"
                        class="tab-btn <?= $active_tab === 'crm' ? 'active-crm' : '' ?>"
                        id="tab-crm" onclick="switchTab('crm')">
                    <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>
                    Quick CRM
                </button>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <div><strong>Kesalahan:</strong>
                    <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- ══ TAB: FORM LENGKAP ══ -->
            <div class="tab-panel <?= $active_tab !== 'crm' ? 'show' : '' ?>" id="panel-lengkap">
                <form method="POST" action="tambah_pasien.php" novalidate>
                    <input type="hidden" name="mode" value="lengkap">

                    <div class="form-group">
                        <label class="form-label" for="nama-l">Nama Lengkap <span class="req">*</span></label>
                        <div class="input-icon-wrap">
                            <svg class="ic" viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v2h20v-2c0-3.3-6.7-5-10-5z"/></svg>
                            <input type="text" id="nama-l" name="nama" class="form-control"
                                   placeholder="Contoh: Sari Dewi"
                                   value="<?= $active_tab !== 'crm' ? htmlspecialchars($old['nama']) : '' ?>"
                                   maxlength="150" required autocomplete="name">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="tel-l">Nomor Telepon</label>
                            <div class="input-icon-wrap">
                                <svg class="ic" viewBox="0 0 24 24"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/></svg>
                                <input type="tel" id="tel-l" name="telepon" class="form-control"
                                       placeholder="0812-3456-7890"
                                       value="<?= $active_tab !== 'crm' ? htmlspecialchars($old['telepon']) : '' ?>"
                                       maxlength="20" autocomplete="tel">
                            </div>
                            <p class="form-hint">Opsional</p>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="tgl-l">Tanggal Lahir</label>
                            <div class="input-icon-wrap">
                                <svg class="ic" viewBox="0 0 24 24"><path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5c-1.1 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/></svg>
                                <input type="date" id="tgl-l" name="tanggal_lahir" class="form-control"
                                       value="<?= $active_tab !== 'crm' ? htmlspecialchars($old['tanggal_lahir'] ?? '') : '' ?>"
                                       max="<?= date('Y-m-d') ?>">
                            </div>
                            <p class="form-hint">Opsional</p>
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;margin-top:8px;">
                        <a href="pasien.php" class="btn btn-secondary btn-lg" style="flex:1;justify-content:center;">Batal</a>
                        <button type="submit" class="btn btn-primary btn-lg" style="flex:2;">
                            <svg viewBox="0 0 24 24"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                            Simpan &amp; Buka Profil
                        </button>
                    </div>
                </form>
            </div>

            <!-- ══ TAB: QUICK CRM ══ -->
            <div class="tab-panel <?= $active_tab === 'crm' ? 'show' : '' ?>" id="panel-crm">
                <div style="background:rgba(201,169,110,.06);border:1px solid rgba(201,169,110,.2);
                            border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px;
                            font-size:.83rem;color:var(--text-muted);line-height:1.6;">
                    <strong style="color:var(--gold-dark);">Mode Quick CRM</strong> —
                    Input hanya Nama &amp; Nomor Telepon. Kontak tersimpan dengan tag <strong>CRM</strong>
                    dan bisa dikonversi ke pasien penuh kapan saja.
                    Gunakan <a href="crm_bulk.php" style="color:var(--gold-dark);font-weight:700;">Import Bulk</a>
                    untuk input banyak sekaligus.
                </div>

                <form method="POST" action="tambah_pasien.php" novalidate>
                    <input type="hidden" name="mode" value="crm">

                    <div class="crm-quick-row">
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label" for="nama-c">Nama <span class="req">*</span></label>
                            <div class="input-icon-wrap">
                                <svg class="ic" viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v2h20v-2c0-3.3-6.7-5-10-5z"/></svg>
                                <input type="text" id="nama-c" name="nama" class="form-control"
                                       placeholder="Nama kontak"
                                       value="<?= $active_tab === 'crm' ? htmlspecialchars($old['nama']) : '' ?>"
                                       maxlength="150" required autocomplete="off" autofocus>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label" for="tel-c">Nomor Telepon</label>
                            <div class="input-icon-wrap">
                                <svg class="ic" viewBox="0 0 24 24"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/></svg>
                                <input type="tel" id="tel-c" name="telepon" class="form-control"
                                       placeholder="0812-xxxx-xxxx"
                                       value="<?= $active_tab === 'crm' ? htmlspecialchars($old['telepon']) : '' ?>"
                                       maxlength="20" autocomplete="off">
                            </div>
                        </div>
                        <div>
                            <label class="form-label" style="opacity:0;pointer-events:none;">Simpan</label>
                            <button type="submit" class="btn btn-primary" style="width:100%;padding:11px 18px;">
                                <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                Simpan
                            </button>
                        </div>
                    </div>
                    <p class="form-hint" style="margin-top:4px;">
                        Setelah simpan, form otomatis reset — langsung input kontak berikutnya.
                    </p>
                </form>
            </div>

        </div>

        <!-- Aside -->
        <div class="form-aside">
            <div class="aside-h">📊 Statistik</div>

            <div class="aside-stat-row">
                <div class="aside-stat-box">
                    <div class="aside-stat-val"><?= number_format($total_pasien) ?></div>
                    <div class="aside-stat-label">Total Pasien</div>
                </div>
                <div class="aside-stat-box">
                    <div class="aside-stat-val" style="color:var(--gold-light);"><?= number_format($total_crm) ?></div>
                    <div class="aside-stat-label">Kontak CRM</div>
                </div>
            </div>

            <div style="margin-top:22px;" class="aside-h">💡 Panduan</div>

            <div class="aside-tip">
                <div class="aside-tip-dot"></div>
                <span><strong style="color:rgba(255,255,255,.8)">Form Lengkap</strong> — untuk pasien baru yang langsung akan dicatat treatmentnya.</span>
            </div>
            <div class="aside-tip">
                <div class="aside-tip-dot"></div>
                <span><strong style="color:rgba(255,255,255,.8)">Quick CRM</strong> — untuk simpan kontak prospek atau walk-in cepat, hanya nama &amp; telepon.</span>
            </div>
            <div class="aside-tip">
                <div class="aside-tip-dot"></div>
                <span><strong style="color:rgba(255,255,255,.8)">Import Bulk</strong> — untuk upload banyak kontak sekaligus dari daftar atau spreadsheet.</span>
            </div>
            <div class="aside-tip">
                <div class="aside-tip-dot"></div>
                <span>Kontak CRM bisa diubah ke profil lengkap kapan saja dari halaman detail pasien.</span>
            </div>

            <div style="margin-top:18px;padding-top:18px;border-top:1px solid rgba(255,255,255,.07);">
                <a href="crm_bulk.php" class="btn btn-primary" style="width:100%;justify-content:center;margin-bottom:8px;">
                    <svg viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
                    Import Bulk CRM
                </a>
                <a href="pasien.php?filter=crm" class="btn btn-secondary" style="width:100%;justify-content:center;font-size:.82rem;">
                    Lihat Semua Kontak CRM
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.getElementById('panel-lengkap').classList.toggle('show', tab === 'lengkap');
    document.getElementById('panel-crm').classList.toggle('show', tab === 'crm');
    document.getElementById('tab-lengkap').className = 'tab-btn' + (tab === 'lengkap' ? ' active-lengkap' : '');
    document.getElementById('tab-crm').className     = 'tab-btn' + (tab === 'crm'     ? ' active-crm'     : '');
    // Fokus ke input pertama
    var target = tab === 'crm' ? 'nama-c' : 'nama-l';
    setTimeout(function(){ var el = document.getElementById(target); if(el) el.focus(); }, 50);
}

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
