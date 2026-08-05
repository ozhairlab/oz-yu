<?php
// ============================================================
//  pos.php — Modul Point of Sale (Kasir)
// ============================================================
require_once 'koneksi.php';
require_role(['superadmin', 'kasir']);

$page_title  = 'POS Kasir';
$active_menu = 'pos';

$errors = [];

// Proses Checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'checkout') {
    $pasien_id   = (int)($_POST['pasien_id'] ?? 0);
    $kasir_id    = (int)$_SESSION['admin_id'];
    $diskon      = (float)($_POST['diskon'] ?? 0);
    $metode      = $_POST['metode_pembayaran'] ?? 'Cash';
    
    $cart_data   = json_decode($_POST['cart_data'] ?? '[]', true);
    
    if ($pasien_id <= 0) $errors[] = 'Silakan pilih pasien.';
    if (empty($cart_data)) $errors[] = 'Keranjang belanja kosong.';
    
    if (empty($errors)) {
        // Hitung total dari server
        $subtotal = 0;
        $items_to_insert = [];
        
        foreach ($cart_data as $item) {
            $pid = (int)$item['id'];
            $qty = (int)$item['qty'];
            
            if ($qty > 0) {
                // Ambil harga asli dari database untuk keamanan
                $stmt = $koneksi->prepare('SELECT nama, harga FROM master_perawatan WHERE id = ?');
                $stmt->bind_param('i', $pid);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if ($res) {
                    $harga = (float)$res['harga'];
                    $item_subtotal = $harga * $qty;
                    $subtotal += $item_subtotal;
                    
                    $items_to_insert[] = [
                        'perawatan_id' => $pid,
                        'nama'         => $res['nama'],
                        'harga'        => $harga,
                        'qty'          => $qty,
                        'subtotal'     => $item_subtotal
                    ];
                }
            }
        }
        
        $grand_total = max(0, $subtotal - $diskon);
        
        if (!empty($items_to_insert)) {
            // Generate No Transaksi: TRX-YYYYMMDD-His
            $no_transaksi = 'TRX-' . date('Ymd-His') . '-' . rand(10,99);
            
            // Insert transaksi
            $stmt = $koneksi->prepare('INSERT INTO transaksi (no_transaksi, pasien_id, kasir_id, subtotal, diskon, grand_total, metode_pembayaran, status) VALUES (?, ?, ?, ?, ?, ?, ?, "Lunas")');
            $stmt->bind_param('siiddds', $no_transaksi, $pasien_id, $kasir_id, $subtotal, $diskon, $grand_total, $metode);
            
            if ($stmt->execute()) {
                $trx_id = $stmt->insert_id;
                $stmt->close();
                
                // Insert detail
                $stmt_dtl = $koneksi->prepare('INSERT INTO transaksi_detail (transaksi_id, perawatan_id, nama_perawatan, harga_satuan, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)');
                foreach ($items_to_insert as $it) {
                    $stmt_dtl->bind_param('iisdid', $trx_id, $it['perawatan_id'], $it['nama'], $it['harga'], $it['qty'], $it['subtotal']);
                    $stmt_dtl->execute();
                }
                $stmt_dtl->close();
                
                // Redirect ke struk
                header("Location: struk.php?id=$trx_id");
                exit;
            } else {
                $errors[] = 'Gagal menyimpan transaksi: ' . $stmt->error;
            }
        } else {
            $errors[] = 'Tidak ada item valid di keranjang.';
        }
    }
}

// Ambil semua divisi dan perawatan untuk katalog
$katalog = [];
$rd = $koneksi->query('SELECT id, nama, warna FROM divisi ORDER BY urutan');
while ($d = $rd->fetch_assoc()) {
    $stmt = $koneksi->prepare('SELECT id, nama, harga FROM master_perawatan WHERE divisi_id = ? AND aktif = 1 ORDER BY urutan ASC, nama ASC');
    $stmt->bind_param('i', $d['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $perawatan = [];
    while ($p = $res->fetch_assoc()) {
        $perawatan[] = $p;
    }
    $stmt->close();
    
    if (count($perawatan) > 0) {
        $d['perawatan'] = $perawatan;
        $katalog[] = $d;
    }
}

// Data JS
$katalog_json = json_encode($katalog);
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
        body { margin: 0; background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; overflow: hidden; height: 100vh; display: flex; flex-direction: column; }
        
        .pos-layout {
            display: flex; flex: 1; overflow: hidden;
            margin-left: 260px; /* Sidebar width */
        }
        
        /* LEFT: Cart Panel */
        .cart-panel {
            width: 450px; background: var(--surface); border-right: 1px solid var(--border);
            display: flex; flex-direction: column; z-index: 10;
        }
        .cart-header { padding: 20px; border-bottom: 1px solid var(--border); }
        .cart-title { margin: 0 0 16px; font-size: 1.2rem; }
        
        .search-box { position: relative; }
        .search-input { width: 100%; padding: 12px 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 0.95rem; box-sizing: border-box; }
        .search-input:focus { outline: none; border-color: var(--gold); }
        .search-results { position: absolute; top: 100%; left: 0; right: 0; background: var(--surface-hover); border: 1px solid var(--border); border-radius: 8px; max-height: 200px; overflow-y: auto; z-index: 20; display: none; margin-top: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.5); }
        .search-item { padding: 12px 16px; cursor: pointer; border-bottom: 1px solid var(--border); }
        .search-item:hover { background: rgba(255,255,255,0.05); }
        .search-item strong { display: block; color: var(--gold); }
        .search-item small { color: var(--muted); }
        
        .selected-patient { background: rgba(201,169,110,0.1); border: 1px solid rgba(201,169,110,0.3); padding: 12px 16px; border-radius: 8px; display: none; align-items: center; justify-content: space-between; margin-top: 10px; }
        .patient-name { font-weight: 600; color: var(--gold); }
        .btn-clear { background: none; border: none; color: #fca5a5; cursor: pointer; font-size: 0.8rem; }
        
        .cart-items { flex: 1; overflow-y: auto; padding: 20px; }
        .cart-item { display: flex; justify-content: space-between; align-items: center; padding-bottom: 16px; margin-bottom: 16px; border-bottom: 1px dashed var(--border); }
        .item-info { flex: 1; }
        .item-name { font-weight: 600; font-size: 0.95rem; margin-bottom: 4px; }
        .item-price { color: var(--muted); font-size: 0.85rem; }
        .item-controls { display: flex; align-items: center; gap: 12px; }
        .qty-btn { background: var(--surface-hover); border: 1px solid var(--border); color: var(--text); width: 28px; height: 28px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .qty-btn:hover { background: var(--border); }
        .item-sub { font-weight: 700; width: 80px; text-align: right; }
        
        .cart-footer { padding: 20px; background: rgba(0,0,0,0.2); border-top: 1px solid var(--border); }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.9rem; color: var(--muted); }
        .summary-row.total { font-size: 1.2rem; font-weight: 800; color: var(--gold); margin-top: 15px; margin-bottom: 20px; border-top: 1px solid var(--border); padding-top: 15px; }
        
        .form-control { width: 100%; padding: 10px; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font-family: inherit; margin-top: 5px; box-sizing: border-box;}
        
        .btn-pay { width: 100%; padding: 16px; background: linear-gradient(135deg, var(--gold), #a07840); color: #000; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: 800; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: transform 0.2s; }
        .btn-pay:hover { transform: translateY(-2px); }
        .btn-pay:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        
        /* RIGHT: Catalog Panel */
        .catalog-panel { flex: 1; padding: 30px; overflow-y: auto; }
        .divisi-section { margin-bottom: 40px; }
        .divisi-title { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; font-size: 1.3rem; }
        .divisi-dot { width: 12px; height: 12px; border-radius: 50%; }
        
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
        .product-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
            padding: 16px; cursor: pointer; transition: all 0.2s; position: relative; overflow: hidden;
        }
        .product-card:hover { border-color: var(--gold); transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.3); }
        .product-card::before { content: ''; position: absolute; inset: 0; background: linear-gradient(to bottom, transparent, rgba(201,169,110,0.05)); opacity: 0; transition: opacity 0.2s; }
        .product-card:hover::before { opacity: 1; }
        
        .p-name { font-weight: 600; font-size: 0.95rem; margin-bottom: 8px; line-height: 1.4; }
        .p-price { color: var(--gold); font-weight: 700; font-size: 1.1rem; }
        
        /* Alert */
        .alert { padding: 12px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; border-radius: 8px; margin-bottom: 15px; }
        
        @media (max-width: 900px) {
            .pos-layout { flex-direction: column; margin-left: 0; margin-top: 60px; overflow-y: auto; }
            .cart-panel { width: 100%; border-right: none; border-bottom: 2px solid var(--gold); }
            body { overflow: auto; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="pos-layout">
        <!-- LEFT: CART -->
        <div class="cart-panel">
            <div class="cart-header">
                <h2 class="cart-title">Detail Transaksi</h2>
                
                <?php if(!empty($errors)): ?>
                    <div class="alert">
                        <?= htmlspecialchars(implode('<br>', $errors)) ?>
                    </div>
                <?php endif; ?>
                
                <div class="search-box" id="patientSearchBox">
                    <input type="text" class="search-input" id="patientInput" placeholder="Cari nama pasien..." autocomplete="off">
                    <div class="search-results" id="patientResults"></div>
                </div>
                
                <div class="selected-patient" id="selectedPatient">
                    <div>
                        <div class="patient-name" id="pNameDisp">Nama Pasien</div>
                        <small style="color:var(--muted)" id="pIdDisp">ID: -</small>
                    </div>
                    <button class="btn-clear" onclick="clearPatient()">Batal</button>
                </div>
            </div>
            
            <div class="cart-items" id="cartItems">
                <div style="text-align:center; color:var(--muted); margin-top:50px; font-size:0.9rem;">
                    Keranjang kosong.<br>Pilih perawatan di katalog.
                </div>
            </div>
            
            <div class="cart-footer">
                <form id="checkoutForm" method="POST" action="pos.php">
                    <input type="hidden" name="aksi" value="checkout">
                    <input type="hidden" name="pasien_id" id="formPasienId" value="">
                    <input type="hidden" name="cart_data" id="formCartData" value="">
                    
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span id="txtSubtotal">Rp 0</span>
                    </div>
                    <div class="summary-row" style="align-items:center;">
                        <span>Diskon (Rp)</span>
                        <input type="number" name="diskon" id="inputDiskon" class="form-control" style="width:120px; margin:0; text-align:right;" value="0" min="0">
                    </div>
                    <div class="summary-row" style="align-items:center; margin-top:10px;">
                        <span>Pembayaran</span>
                        <select name="metode_pembayaran" class="form-control" style="width:120px; margin:0;">
                            <option value="Cash">Cash</option>
                            <option value="QRIS">QRIS</option>
                            <option value="Transfer">Transfer Bank</option>
                            <option value="Kartu Kredit/Debit">Kartu Kredit/Debit</option>
                        </select>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span id="txtTotal">Rp 0</span>
                    </div>
                    
                    <button type="submit" class="btn-pay" id="btnPay" disabled>Bayar Sekarang</button>
                </form>
            </div>
        </div>
        
        <!-- RIGHT: CATALOG -->
        <div class="catalog-panel">
            <?php foreach ($katalog as $d): ?>
                <div class="divisi-section">
                    <div class="divisi-title">
                        <div class="divisi-dot" style="background: <?= htmlspecialchars($d['warna'] ?? '#ccc') ?>"></div>
                        <strong><?= htmlspecialchars($d['nama']) ?></strong>
                    </div>
                    <div class="product-grid">
                        <?php foreach ($d['perawatan'] as $p): ?>
                            <div class="product-card" onclick="addToCart(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['nama'])) ?>', <?= $p['harga'] ?>)">
                                <div class="p-name"><?= htmlspecialchars($p['nama']) ?></div>
                                <div class="p-price">Rp <?= number_format($p['harga'], 0, ',', '.') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        // Formatter Rp
        const fmt = new Intl.NumberFormat('id-ID');
        
        // --- CART LOGIC ---
        let cart = {};
        
        function addToCart(id, name, price) {
            if (cart[id]) {
                cart[id].qty++;
            } else {
                cart[id] = { id: id, name: name, price: price, qty: 1 };
            }
            renderCart();
        }
        
        function updateQty(id, delta) {
            if (cart[id]) {
                cart[id].qty += delta;
                if (cart[id].qty <= 0) {
                    delete cart[id];
                }
                renderCart();
            }
        }
        
        function renderCart() {
            const container = document.getElementById('cartItems');
            let subtotal = 0;
            let count = 0;
            
            container.innerHTML = '';
            
            for (let id in cart) {
                count++;
                let item = cart[id];
                let itemTotal = item.price * item.qty;
                subtotal += itemTotal;
                
                let div = document.createElement('div');
                div.className = 'cart-item';
                div.innerHTML = `
                    <div class="item-info">
                        <div class="item-name">${item.name}</div>
                        <div class="item-price">Rp ${fmt.format(item.price)}</div>
                    </div>
                    <div class="item-controls">
                        <button class="qty-btn" onclick="updateQty(${id}, -1)">-</button>
                        <span style="font-weight:600; width:20px; text-align:center;">${item.qty}</span>
                        <button class="qty-btn" onclick="updateQty(${id}, 1)">+</button>
                    </div>
                    <div class="item-sub">Rp ${fmt.format(itemTotal)}</div>
                `;
                container.appendChild(div);
            }
            
            if (count === 0) {
                container.innerHTML = `<div style="text-align:center; color:var(--muted); margin-top:50px; font-size:0.9rem;">Keranjang kosong.<br>Pilih perawatan di katalog.</div>`;
            }
            
            document.getElementById('txtSubtotal').textContent = 'Rp ' + fmt.format(subtotal);
            updateTotal(subtotal);
            updateCartDataInput();
            checkPayButton();
        }
        
        function updateTotal(subtotal = null) {
            if (subtotal === null) {
                subtotal = 0;
                for(let k in cart) subtotal += cart[k].price * cart[k].qty;
            }
            let diskon = parseFloat(document.getElementById('inputDiskon').value) || 0;
            let total = Math.max(0, subtotal - diskon);
            document.getElementById('txtTotal').textContent = 'Rp ' + fmt.format(total);
        }
        
        document.getElementById('inputDiskon').addEventListener('input', () => updateTotal());
        
        function updateCartDataInput() {
            let arr = [];
            for(let k in cart) arr.push(cart[k]);
            document.getElementById('formCartData').value = JSON.stringify(arr);
        }
        
        // --- PATIENT SEARCH LOGIC ---
        let searchTimeout;
        const input = document.getElementById('patientInput');
        const resultsBox = document.getElementById('patientResults');
        const searchBox = document.getElementById('patientSearchBox');
        const selectedBox = document.getElementById('selectedPatient');
        
        input.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const val = this.value.trim();
            if (val.length < 2) {
                resultsBox.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch('proses_cari.php?q=' + encodeURIComponent(val))
                .then(res => res.json())
                .then(data => {
                    resultsBox.innerHTML = '';
                    let pasienList = data.pasien || [];
                    if(pasienList.length === 0) {
                        resultsBox.innerHTML = `
                            <div class="search-item" style="text-align:center; padding: 15px;">
                                <div style="margin-bottom: 8px; color: var(--muted);">Pasien tidak ditemukan.</div>
                                <a href="tambah_pasien.php" target="_blank" style="display: inline-block; padding: 6px 12px; background: var(--gold); color: #000; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.85rem;">+ Daftar Pasien Baru</a>
                            </div>
                        `;
                    } else {
                        pasienList.forEach(p => {
                            let div = document.createElement('div');
                            div.className = 'search-item';
                            div.innerHTML = `<strong>${p.nama}</strong><small>${p.telepon || '-'} | Lahir: ${p.tanggal_lahir || '-'}</small>`;
                            div.onclick = () => selectPatient(p.id, p.nama);
                            resultsBox.appendChild(div);
                        });
                    }
                    resultsBox.style.display = 'block';
                });
            }, 300);
        });
        
        function selectPatient(id, name) {
            document.getElementById('formPasienId').value = id;
            document.getElementById('pNameDisp').textContent = name;
            document.getElementById('pIdDisp').textContent = 'ID: ' + id;
            
            searchBox.style.display = 'none';
            selectedBox.style.display = 'flex';
            resultsBox.style.display = 'none';
            input.value = '';
            
            checkPayButton();
        }
        
        function clearPatient() {
            document.getElementById('formPasienId').value = '';
            searchBox.style.display = 'block';
            selectedBox.style.display = 'none';
            checkPayButton();
        }
        
        // Hide search if clicked outside
        document.addEventListener('click', function(e) {
            if(!searchBox.contains(e.target)) resultsBox.style.display = 'none';
        });
        
        function checkPayButton() {
            const pid = document.getElementById('formPasienId').value;
            const hasItems = Object.keys(cart).length > 0;
            document.getElementById('btnPay').disabled = !(pid && hasItems);
        }
        
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            if(!confirm('Proses pembayaran ini? Data yang disimpan tidak dapat dibatalkan.')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
