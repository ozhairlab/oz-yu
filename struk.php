<?php
// ============================================================
//  struk.php — Tampilan Cetak Struk POS
// ============================================================
require_once 'koneksi.php';
require_login(); // Kasir atau Superadmin

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die('ID Transaksi tidak valid.');
}

// Ambil data transaksi
$stmt = $koneksi->prepare('
    SELECT t.*, p.nama AS nama_pasien, p.telepon, a.username AS kasir
    FROM transaksi t
    JOIN pasien p ON t.pasien_id = p.id
    JOIN admin_klinik a ON t.kasir_id = a.id
    WHERE t.id = ?
');
$stmt->bind_param('i', $id);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trx) {
    die('Transaksi tidak ditemukan.');
}

// Ambil detail
$stmt = $koneksi->prepare('SELECT * FROM transaksi_detail WHERE transaksi_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$details = [];
while ($row = $res->fetch_assoc()) {
    $details[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Transaksi - <?= htmlspecialchars($trx['no_transaksi']) ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        .struk-container {
            width: 100%;
            max-width: 320px; /* Ukuran printer thermal 80mm */
            border: 1px dashed #ccc;
            padding: 20px;
            box-sizing: border-box;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .logo { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
        .divider { border-bottom: 1px dashed #000; margin: 15px 0; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .item-row { margin-bottom: 10px; }
        .item-name { margin-bottom: 3px; }
        .item-calc { display: flex; justify-content: space-between; }
        
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn-print { background: #12121a; color: #fff; border: none; padding: 10px 20px; font-size: 16px; border-radius: 5px; cursor: pointer; }
        
        @media print {
            body { padding: 0; background: #fff; }
            .struk-container { border: none; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div>
        <div class="no-print">
            <button class="btn-print" onclick="window.print()">🖨️ Cetak Struk</button>
            <br><br>
            <a href="riwayat_transaksi.php" style="color:#666; text-decoration:none;">Kembali ke Riwayat</a>
            &nbsp;|&nbsp;
            <a href="pos.php" style="color:#666; text-decoration:none;">POS Baru</a>
        </div>

        <div class="struk-container">
            <div class="text-center logo">OZTHETIQUE</div>
            <div class="text-center" style="font-size:12px;">
                Klinik Kecantikan & Skincare<br>
                Jakarta, Indonesia
            </div>
            
            <div class="divider"></div>
            
            <div class="info-row">
                <span>No: <?= htmlspecialchars($trx['no_transaksi']) ?></span>
            </div>
            <div class="info-row">
                <span>Tgl: <?= date('d/m/Y H:i', strtotime($trx['created_at'])) ?></span>
            </div>
            <div class="info-row">
                <span>Ksr: <?= htmlspecialchars($trx['kasir']) ?></span>
            </div>
            <div class="info-row">
                <span>Plg: <?= htmlspecialchars($trx['nama_pasien']) ?></span>
            </div>
            
            <div class="divider"></div>
            
            <?php foreach($details as $d): ?>
                <div class="item-row">
                    <div class="item-name"><?= htmlspecialchars($d['nama_perawatan']) ?></div>
                    <div class="item-calc">
                        <span><?= $d['qty'] ?> x <?= number_format($d['harga_satuan'], 0, ',', '.') ?></span>
                        <span><?= number_format($d['subtotal'], 0, ',', '.') ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="divider"></div>
            
            <div class="info-row">
                <span>Subtotal</span>
                <span><?= number_format($trx['subtotal'], 0, ',', '.') ?></span>
            </div>
            <div class="info-row">
                <span>Diskon</span>
                <span><?= number_format($trx['diskon'], 0, ',', '.') ?></span>
            </div>
            <div class="info-row bold" style="font-size: 16px; margin-top: 10px;">
                <span>Total</span>
                <span><?= number_format($trx['grand_total'], 0, ',', '.') ?></span>
            </div>
            <div class="info-row" style="margin-top: 10px;">
                <span>Metode</span>
                <span><?= htmlspecialchars($trx['metode_pembayaran']) ?></span>
            </div>
            
            <div class="divider"></div>
            <div class="text-center" style="font-size: 12px; margin-top: 20px;">
                Terima kasih atas kunjungan Anda<br>
                Semoga lekas sembuh dan makin glowing!
            </div>
        </div>
    </div>
    <script>
        window.onload = function() {
            window.print();
        }
        window.onafterprint = function() {
            // Opsional: kembali ke POS otomatis
            // window.location.href = 'pos.php';
        }
    </script>
</body>
</html>
