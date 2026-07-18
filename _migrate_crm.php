<?php
// Migrasi CRM — hapus setelah selesai
error_reporting(E_ALL);
$db = new mysqli('localhost','root','','klinik_kecantikan');
if ($db->connect_error) die("DB gagal: ".$db->connect_error);
$db->set_charset('utf8mb4');

$cols = [
    'jenis_kelamin' => "ALTER TABLE pasien ADD COLUMN `jenis_kelamin` ENUM('P','L') NULL DEFAULT NULL AFTER `nama`",
    'email'         => "ALTER TABLE pasien ADD COLUMN `email` VARCHAR(150) NULL DEFAULT NULL AFTER `telepon`",
    'sumber_pasien' => "ALTER TABLE pasien ADD COLUMN `sumber_pasien` VARCHAR(60) NULL DEFAULT NULL AFTER `tanggal_lahir`",
    'catatan_crm'   => "ALTER TABLE pasien ADD COLUMN `catatan_crm` TEXT NULL DEFAULT NULL AFTER `sumber_pasien`",
];

foreach ($cols as $col => $sql) {
    $chk = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA='klinik_kecantikan' AND TABLE_NAME='pasien' AND COLUMN_NAME='$col'");
    if ($chk->num_rows === 0) {
        if ($db->query($sql)) echo "[OK] Kolom '$col' ditambahkan\n";
        else echo "[ERR] $col: ".$db->error."\n";
    } else {
        echo "[SKIP] Kolom '$col' sudah ada\n";
    }
}

// Tampilkan struktur tabel pasien terbaru
echo "\n[INFO] Struktur tabel pasien:\n";
$r = $db->query("SHOW COLUMNS FROM pasien");
while ($c = $r->fetch_assoc())
    echo "  - {$c['Field']} ({$c['Type']}) ".($c['Null']==='YES'?'NULL':'NOT NULL')."\n";

$db->close();
echo "\n=== MIGRASI CRM SELESAI ===\n";
