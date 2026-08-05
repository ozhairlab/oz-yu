-- ============================================================
--  migrate_inventory.sql — Skema Database Inventory
-- ============================================================

CREATE TABLE IF NOT EXISTS `inventory_barang` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode` VARCHAR(50) NOT NULL UNIQUE,
  `nama` VARCHAR(150) NOT NULL,
  `satuan` VARCHAR(50) NOT NULL DEFAULT 'Pcs',
  `stok_sekarang` INT NOT NULL DEFAULT 0,
  `stok_minimal` INT NOT NULL DEFAULT 0,
  `harga_beli` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inventory_riwayat` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `barang_id` INT NOT NULL,
  `tipe` ENUM('masuk', 'keluar', 'penyesuaian', 'terpakai') NOT NULL,
  `jumlah` INT NOT NULL,
  `stok_akhir` INT NOT NULL,
  `keterangan` TEXT,
  `user_id` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`barang_id`) REFERENCES `inventory_barang`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `komposisi_perawatan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `perawatan_id` INT NOT NULL,
  `barang_id` INT NOT NULL,
  `jumlah_pakai` INT NOT NULL,
  FOREIGN KEY (`perawatan_id`) REFERENCES `master_perawatan`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`barang_id`) REFERENCES `inventory_barang`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
