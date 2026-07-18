<?php
// ============================================================
//  crm_bulk.php — Import Bulk Kontak CRM (Nama + Telepon)
// ============================================================
require_once 'koneksi.php';
require_login();

$page_title  = 'Import Bulk CRM';
$active_menu = 'pasien';

$results  = [];   // ['nama'=>..,'telepon'=>..,'status'=>'ok'|'skip'|'err','msg'=>..]
$done     = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw   = trim($_POST['kontak_bulk'] ?? '');
    $baris = preg_split('/\r?\n/', $raw);
    $saved = 0;
    $skipped = 0;

    $stmt_ins = $koneksi->prepare(
        'INSERT INTO pasien (nama, telepon, sumber_pasien) VALUES (?,?,?)'
    );
    $sumber = 'CRM';

    foreach ($baris as $line) {
        $line = trim($line);
        if ($line === '') continue;

        // Format: "Nama, Telepon" atau "Nama;Telepon" atau "Nama|Telepon" atau hanya "Nama"
        $parts = preg_split('/[,;|\t]+/', $line, 2);
        $nama  = trim($parts[0] ?? '');
        $telp  = trim($parts[1] ?? '') ?: null;

        if ($nama === '') {
            $results[] = ['nama'=>$line,'telepon'=>'','status'=>'skip','msg'=>'Nama kosong, dilewati'];
            $skipped++;
            continue;
        }
        if (mb_strlen($nama) > 150) {
            $results[] = ['nama'=>$nama,'telepon'=>$telp??'','status'=>'skip','msg'=>'Nama terlalu panjang'];
            $skipped++;
            continue;
        }
        if ($telp !== null && !preg_match('/^[\d\s\+\-\(\)]{6,20}$/', $telp)) {
            $telp = null; // simpan nama saja, abaikan nomor tidak valid
        }

        $stmt_ins->bind_param('sss', $nama, $telp, $sumber);
        if ($stmt_ins->execute()) {
            $results[] = ['nama'=>$nama,'telepon'=>$telp??'','status'=>'ok','msg'=>'Tersimpan'];
            $saved++;
        } else {
            $results[] = ['nama'=>$nama,'telepon'=>$telp??'','status'=>'err','msg'=>'Gagal: '.$stmt_ins->error];
        }
    }
    $stmt_ins->close();
    $done = true;
}

$total_crm = $koneksi->query("SELECT COUNT(*) FROM pasien WHERE sumber_pasien='CRM'")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Ozthetique</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .import-layout { display:grid; grid-template-columns:1fr 300px; gap:22px; align-items:start; }

        .textarea-kontak {
            width: 100%;
            min-height: 280px;
            padding: 14px 16px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: .9rem;
            font-family: 'Courier New', monospace;
            color: var(--text);
            background: var(--surface);
            resize: vertical;
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition);
            line-height: 1.7;
        }

        .textarea-kontak:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201,169,110,.12);
        }

        /* Format hint */
        .format-hint {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 14px 16px;
            margin-bottom: 18px;
            font-size: .82rem;
            color: var(--text-muted);
            line-height: 1.8;
        }

        .format-hint code {
            background: rgba(201,169,110,.1);
            color: var(--gold-dark);
            padding: 1px 6px;
            border-radius: 4px;
            font-size: .82rem;
            font-family: 'Courier New', monospace;
        }

        /* Result table */
        .result-table { width:100%; border-collapse:collapse; font-size:.875rem; margin-top:20px; }
        .result-table th {
            text-align:left; padding:10px 14px;
            background:var(--bg); font-size:.72rem; font-weight:800;
            text-transform:uppercase; letter-spacing:.08em;
            color:var(--text-muted); border-bottom:2px solid var(--border);
        }
        .result-table td { padding:10px 14px; border-bottom:1px solid var(--border); }
        .result-table tr:last-child td { border-bottom:none; }

        .status-ok   { color:#15803d; font-weight:700; }
        .status-skip { color:#b45309; font-weight:700; }
        .status-err  { color:#b91c1c; font-weight:700; }

        .summary-bar {
            display: flex; gap: 12px; flex-wrap: wrap;
            padding: 14px 18px;
            background: var(--surface-2);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            margin-bottom: 16px;
            font-size: .875rem; font-weight: 600;
        }

        .sum-item { display:flex; align-items:center; gap:6px; }
        .sum-dot  { width:8px; height:8px; border-radius:50%; flex-shrink:0; }

        /* Aside */
        .form-aside {
            background: var(--ink-3);
            border-radius: var(--radius-md);
            padding: 24px 20px;
            color: #fff;
            border: 1px solid rgba(201,169,110,.12);
            position: sticky;
            top: calc(var(--topbar-h) + 16px);
        }

        .aside-h { font-size:.8rem; font-weight:800; color:var(--gold-light);
                   margin-bottom:12px; letter-spacing:.06em; text-transform:uppercase; }

        .aside-tip { display:flex; align-items:flex-start; gap:8px;
                     margin-bottom:11px; font-size:.79rem;
                     color:rgba(255,255,255,.5); line-height:1.6; }

        .aside-tip-dot { width:5px; height:5px; border-radius:50%;
                         background:var(--gold); flex-shrink:0; margin-top:7px; }

        .aside-stat-box { background:rgba(255,255,255,.05); border-radius:10px;
                          padding:14px; text-align:center; margin-bottom:12px; }

        .aside-stat-val   { font-size:1.8rem; font-weight:800; color:var(--gold-light); letter-spacing:-.04em; }
        .aside-stat-label { font-size:.68rem; color:rgba(255,255,255,.4); font-weight:600;
                            text-transform:uppercase; letter-spacing:.08em; margin-top:3px; }

        @media (max-width:860px) {
            .import-layout { grid-template-columns:1fr; }
            .form-aside { position:static; }
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">

    <div class="page-header">
        <div class="page-header-left">
            <h1>Import Bulk CRM</h1>
            <p>Paste daftar nama &amp; nomor telepon sekaligus — satu kontak per baris</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="tambah_pasien.php?tab=crm" class="btn btn-secondary">
                <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Quick CRM
            </a>
            <a href="pasien.php?filter=crm" class="btn btn-secondary">
                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                Daftar CRM
            </a>
        </div>
    </div>

    <div class="import-layout">
        <div>
            <!-- Format hint -->
            <div class="format-hint">
                <strong style="color:var(--text);">Format penulisan</strong> — satu kontak per baris:<br>
                <code>Nama Lengkap</code> &nbsp;atau&nbsp;
                <code>Nama, 0812-3456-7890</code> &nbsp;atau&nbsp;
                <code>Nama; 08xx</code> &nbsp;atau&nbsp;
                <code>Nama | 08xx</code><br>
                <span style="margin-top:4px;display:block;">Baris kosong otomatis dilewati. Nomor telepon bersifat opsional.</span>
            </div>

            <?php if ($done): ?>
            <!-- Hasil import -->
            <div class="summary-bar">
                <div class="sum-item">
                    <div class="sum-dot" style="background:#15803d"></div>
                    <span><?= $saved ?> berhasil disimpan</span>
                </div>
                <div class="sum-item">
                    <div class="sum-dot" style="background:#b45309"></div>
                    <span><?= $skipped ?> dilewati</span>
                </div>
                <div class="sum-item">
                    <div class="sum-dot" style="background:#6b7280"></div>
                    <span><?= count($results) ?> total baris diproses</span>
                </div>
                <a href="pasien.php?filter=crm" style="margin-left:auto;color:var(--gold-dark);font-weight:700;font-size:.82rem;display:flex;align-items:center;gap:4px;">
                    Lihat di Daftar Pasien →
                </a>
            </div>

            <div class="card" style="padding:0;overflow:hidden;">
                <table class="result-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>Telepon</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $i => $r): ?>
                        <tr>
                            <td style="color:var(--text-light);font-size:.8rem;"><?= $i+1 ?></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($r['nama']) ?></td>
                            <td style="color:var(--text-muted);"><?= htmlspecialchars($r['telepon']) ?: '—' ?></td>
                            <td>
                                <?php if ($r['status']==='ok'): ?>
                                    <span class="status-ok">✓ <?= htmlspecialchars($r['msg']) ?></span>
                                <?php elseif ($r['status']==='skip'): ?>
                                    <span class="status-skip">⚠ <?= htmlspecialchars($r['msg']) ?></span>
                                <?php else: ?>
                                    <span class="status-err">✗ <?= htmlspecialchars($r['msg']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;display:flex;gap:10px;">
                <a href="crm_bulk.php" class="btn btn-secondary">Import Lagi</a>
                <a href="pasien.php?filter=crm" class="btn btn-primary">
                    <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                    Lihat Semua Kontak CRM
                </a>
            </div>

            <?php else: ?>
            <!-- Form input -->
            <div class="card">
                <form method="POST" action="crm_bulk.php">
                    <div class="form-group">
                        <label class="form-label" for="kontak_bulk">
                            Daftar Kontak
                            <span class="form-hint" style="display:inline;margin-left:6px;">(satu per baris)</span>
                        </label>
                        <textarea
                            id="kontak_bulk"
                            name="kontak_bulk"
                            class="textarea-kontak"
                            placeholder="Sari Dewi, 0812-3456-7890&#10;Budi Santoso&#10;Rina Agustina, 0856-1234-5678&#10;..."
                            spellcheck="false"
                        ></textarea>
                        <div style="display:flex;justify-content:space-between;margin-top:6px;">
                            <span class="form-hint" id="line-count">0 baris</span>
                            <button type="button" onclick="document.getElementById('kontak_bulk').value=''"
                                    style="font-size:.75rem;color:var(--text-light);background:none;border:none;cursor:pointer;">
                                Bersihkan
                            </button>
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <a href="tambah_pasien.php" class="btn btn-secondary" style="flex:1;justify-content:center;">Batal</a>
                        <button type="submit" class="btn btn-primary" style="flex:2;">
                            <svg viewBox="0 0 24 24"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                            Import Semua Kontak
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Aside -->
        <div class="form-aside">
            <div class="aside-h">📊 Statistik CRM</div>
            <div class="aside-stat-box">
                <div class="aside-stat-val"><?= number_format($total_crm) ?></div>
                <div class="aside-stat-label">Total Kontak CRM</div>
            </div>

            <div class="aside-h" style="margin-top:16px;">💡 Tips Import</div>
            <div class="aside-tip">
                <div class="aside-tip-dot"></div>
                <span>Bisa paste langsung dari WhatsApp, Excel, atau Google Sheets.</span>
            </div>
            <div class="aside-tip">
                <div class="aside-tip-dot"></div>
                <span>Pisahkan nama dan nomor dengan koma <strong style="color:rgba(255,255,255,.7)">,</strong> titik koma <strong style="color:rgba(255,255,255,.7)">;</strong> atau pipe <strong style="color:rgba(255,255,255,.7)">|</strong></span>
            </div>
            <div class="aside-tip">
                <div class="aside-tip-dot"></div>
                <span>Nomor telepon tidak valid akan diabaikan, nama tetap tersimpan.</span>
            </div>
            <div class="aside-tip">
                <div class="aside-tip-dot"></div>
                <span>Semua kontak yang diimport otomatis diberi tag <strong style="color:var(--gold-light)">CRM</strong> dan terlihat di filter Daftar Pasien.</span>
            </div>

            <div style="margin-top:18px;padding-top:16px;border-top:1px solid rgba(255,255,255,.07);">
                <a href="pasien.php?filter=crm" class="btn btn-secondary"
                   style="width:100%;justify-content:center;font-size:.82rem;">
                    Lihat Daftar CRM
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

    // Hitung baris
    var ta = document.getElementById('kontak_bulk');
    var lc = document.getElementById('line-count');
    if (ta && lc) {
        ta.addEventListener('input', function(){
            var lines = this.value.split('\n').filter(function(l){ return l.trim()!==''; }).length;
            lc.textContent = lines + ' baris';
        });
    }
}());
</script>
</body>
</html>
