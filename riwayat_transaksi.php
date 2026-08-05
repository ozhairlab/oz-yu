<?php
// ============================================================
//  riwayat_transaksi.php — Laporan Transaksi POS
// ============================================================
require_once 'koneksi.php';
require_role(['superadmin', 'kasir']);

$page_title  = 'Riwayat Transaksi';
$active_menu = 'transaksi';

// Filter Tanggal
$filter_tgl = $_GET['tanggal'] ?? date('Y-m-d');

// Ambil data transaksi
$stmt = $koneksi->prepare('
    SELECT t.id, t.no_transaksi, t.grand_total, t.metode_pembayaran, t.created_at, t.status,
           p.nama AS nama_pasien, a.username AS kasir
    FROM transaksi t
    JOIN pasien p ON t.pasien_id = p.id
    JOIN admin_klinik a ON t.kasir_id = a.id
    WHERE DATE(t.created_at) = ?
    ORDER BY t.id DESC
');
$stmt->bind_param('s', $filter_tgl);
$stmt->execute();
$res = $stmt->get_result();
$transaksi = [];
$total_pendapatan = 0;
while ($row = $res->fetch_assoc()) {
    $transaksi[] = $row;
    if ($row['status'] === 'Lunas') {
        $total_pendapatan += $row['grand_total'];
    }
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> — Ozthetique</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        :root {
            --bg: #0a0a0f; --surface: #12121a; --surface-hover: #1c1c28;
            --border: rgba(255,255,255,0.08); --text: #ffffff; --muted: rgba(255,255,255,0.5);
            --gold: #c9a96e; --radius: 12px;
        }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; overflow-x: hidden; }
        
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .header-action { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .page-title { font-size: 1.8rem; margin: 0; font-weight: 700; letter-spacing: -0.03em; }
        
        .filter-box { display: flex; align-items: center; gap: 10px; background: var(--surface); padding: 10px 16px; border-radius: 8px; border: 1px solid var(--border); }
        .filter-input { background: var(--bg); border: 1px solid var(--border); color: var(--text); padding: 8px 12px; border-radius: 6px; font-family: inherit; color-scheme: dark; }
        .btn-filter { background: var(--surface-hover); border: 1px solid var(--border); color: var(--text); padding: 8px 16px; border-radius: 6px; cursor: pointer; }
        .btn-filter:hover { background: var(--border); }
        
        .stat-card { background: linear-gradient(135deg, rgba(201,169,110,0.1), transparent); border: 1px solid rgba(201,169,110,0.3); padding: 24px; border-radius: var(--radius); margin-bottom: 30px; display: inline-block; min-width: 250px; }
        .stat-label { font-size: 0.85rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
        .stat-value { font-size: 2rem; font-weight: 800; color: var(--gold); }
        
        .table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th, td { padding: 16px 20px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: rgba(255,255,255,0.02); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); }
        td { font-size: 0.95rem; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; background: var(--surface-hover); border: 1px solid var(--border); color: var(--text); border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-sm:hover { background: var(--border); }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .badge-lunas { background: rgba(16,185,129,0.15); color: #6ee7b7; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px; margin-top: 60px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content">
        <div class="header-action">
            <h1 class="page-title">Riwayat Transaksi</h1>
            
            <form method="GET" class="filter-box">
                <label style="font-size:0.85rem; color:var(--muted)">Tanggal:</label>
                <input type="date" name="tanggal" value="<?= htmlspecialchars($filter_tgl) ?>" class="filter-input">
                <button type="submit" class="btn-filter">Tampilkan</button>
            </form>
        </div>
        
        <div class="stat-card">
            <div class="stat-label">Total Pendapatan (<?= htmlspecialchars(date('d M Y', strtotime($filter_tgl))) ?>)</div>
            <div class="stat-value">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
        </div>
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>No Transaksi</th>
                        <th>Pasien</th>
                        <th>Total</th>
                        <th>Metode</th>
                        <th>Kasir</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($transaksi)): ?>
                        <tr><td colspan="8" style="text-align:center; color:var(--muted); padding:30px;">Tidak ada transaksi pada tanggal ini.</td></tr>
                    <?php else: ?>
                        <?php foreach($transaksi as $t): ?>
                        <tr>
                            <td><?= date('H:i', strtotime($t['created_at'])) ?></td>
                            <td style="font-family:monospace; color:var(--gold);"><?= htmlspecialchars($t['no_transaksi']) ?></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($t['nama_pasien']) ?></td>
                            <td style="font-weight:700;">Rp <?= number_format($t['grand_total'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars($t['metode_pembayaran']) ?></td>
                            <td style="color:var(--muted)"><?= htmlspecialchars($t['kasir']) ?></td>
                            <td><span class="badge badge-lunas"><?= $t['status'] ?></span></td>
                            <td><a href="struk.php?id=<?= $t['id'] ?>" target="_blank" class="btn-sm">Lihat Struk</a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
