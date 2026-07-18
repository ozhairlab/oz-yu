-- ============================================================
--  migrate_divisi.sql
--  Jalankan SEKALI di phpMyAdmin atau MySQL CLI
--  untuk menambahkan fitur divisi & master perawatan
-- ============================================================

USE `klinik_kecantikan`;

-- ------------------------------------------------------------
-- 1. Tabel divisi
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `divisi` (
  `id`        TINYINT(4)   NOT NULL AUTO_INCREMENT,
  `kode`      VARCHAR(20)  NOT NULL UNIQUE,   -- 'ozthetique' | 'ozhairlab'
  `nama`      VARCHAR(100) NOT NULL,
  `warna`     VARCHAR(7)   NOT NULL DEFAULT '#e91e63', -- hex color untuk badge
  `urutan`    TINYINT(4)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `divisi` (`kode`, `nama`, `warna`, `urutan`) VALUES
  ('ozthetique', 'Ozthetique',  '#e91e63', 1),
  ('ozhairlab',  'OzHairLab',   '#7c4dff', 2);

-- ------------------------------------------------------------
-- 2. Tabel master_perawatan
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `master_perawatan` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `divisi_id` TINYINT(4)   NOT NULL,
  `nama`      VARCHAR(200) NOT NULL,
  `deskripsi` TEXT         DEFAULT NULL,
  `aktif`     TINYINT(1)   NOT NULL DEFAULT 1,
  `urutan`    INT(11)      NOT NULL DEFAULT 0,
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_divisi` (`divisi_id`),
  CONSTRAINT `fk_mp_divisi`
    FOREIGN KEY (`divisi_id`) REFERENCES `divisi` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed data awal Ozthetique (divisi_id = 1)
INSERT IGNORE INTO `master_perawatan` (`divisi_id`, `nama`, `urutan`) VALUES
  (1, 'Facial Brightening',      1),
  (1, 'Facial Deep Cleansing',   2),
  (1, 'Chemical Peeling',        3),
  (1, 'Laser Rejuvenation',      4),
  (1, 'Filler Hidung',           5),
  (1, 'Filler Bibir',            6),
  (1, 'Botox Dahi',              7),
  (1, 'Microneedling',           8),
  (1, 'LED Therapy',             9),
  (1, 'Aqua Peeling',           10);

-- Seed data awal OzHairLab (divisi_id = 2)
INSERT IGNORE INTO `master_perawatan` (`divisi_id`, `nama`, `urutan`) VALUES
  (2, 'Hair Spa',                1),
  (2, 'Scalp Treatment',         2),
  (2, 'Keratin Treatment',       3),
  (2, 'Hair Botox',              4),
  (2, 'Smoothing',               5),
  (2, 'Coloring',                6),
  (2, 'Highlight',               7),
  (2, 'Balayage',                8),
  (2, 'Cutting & Styling',       9),
  (2, 'PRP Hair Treatment',     10);

-- ------------------------------------------------------------
-- 3. Tambah kolom divisi_id ke riwayat_treatment
--    (IF NOT EXISTS agar aman jika dijalankan ulang)
-- ------------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'klinik_kecantikan'
      AND TABLE_NAME   = 'riwayat_treatment'
      AND COLUMN_NAME  = 'divisi_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `riwayat_treatment`
     ADD COLUMN `divisi_id` TINYINT(4) NULL DEFAULT NULL AFTER `pasien_id`,
     ADD CONSTRAINT `fk_rt_divisi`
       FOREIGN KEY (`divisi_id`) REFERENCES `divisi` (`id`)
       ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "kolom divisi_id sudah ada, dilewati" AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
