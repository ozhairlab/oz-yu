-- ============================================================
--  SCHEMA DATABASE - Sistem Rekam Medis Klinik Kecantikan
--  Versi lengkap termasuk divisi, master_perawatan, treatment_foto
--  Jalankan file ini di phpMyAdmin (fresh install)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `klinik_kecantikan`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `klinik_kecantikan`;

-- ------------------------------------------------------------
-- 1. admin_klinik
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_klinik` (
  `id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password default: Admin1234!
-- Buat hash baru: php -r "echo password_hash('Admin1234!', PASSWORD_BCRYPT);"
INSERT IGNORE INTO `admin_klinik` (`username`, `password`) VALUES
('admin', '$2y$10$ReplaceWithRealBcryptHashHere000000000000000000000000000');

-- ------------------------------------------------------------
-- 2. divisi
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `divisi` (
  `id`      TINYINT(4)   NOT NULL AUTO_INCREMENT,
  `kode`    VARCHAR(20)  NOT NULL UNIQUE,
  `nama`    VARCHAR(100) NOT NULL,
  `warna`   VARCHAR(7)   NOT NULL DEFAULT '#e91e63',
  `urutan`  TINYINT(4)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `divisi` (`kode`, `nama`, `warna`, `urutan`) VALUES
  ('ozthetique', 'Ozthetique', '#e91e63', 1),
  ('ozhairlab',  'OzHairLab',  '#7c4dff', 2),
  ('dental',     'Dental',     '#0ea5e9', 3);

-- ------------------------------------------------------------
-- 3. pasien
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pasien` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `nama`          VARCHAR(150) NOT NULL,
  `telepon`       VARCHAR(20)  DEFAULT NULL,
  `tanggal_lahir` DATE         DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_nama` (`nama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. master_perawatan
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `master_perawatan` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `divisi_id`  TINYINT(4)   NOT NULL,
  `nama`       VARCHAR(200) NOT NULL,
  `deskripsi`  TEXT         DEFAULT NULL,
  `aktif`      TINYINT(1)   NOT NULL DEFAULT 1,
  `urutan`     INT(11)      NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_divisi` (`divisi_id`),
  CONSTRAINT `fk_mp_divisi`
    FOREIGN KEY (`divisi_id`) REFERENCES `divisi`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `master_perawatan` (`id`,`divisi_id`,`nama`,`urutan`) VALUES
  (1,1,'Facial Brightening',1),(2,1,'Facial Deep Cleansing',2),
  (3,1,'Chemical Peeling',3),(4,1,'Laser Rejuvenation',4),
  (5,1,'Filler Hidung',5),(6,1,'Filler Bibir',6),
  (7,1,'Botox Dahi',7),(8,1,'Microneedling',8),
  (9,1,'LED Therapy',9),(10,1,'Aqua Peeling',10),
  (11,2,'Hair Spa',1),(12,2,'Scalp Treatment',2),
  (13,2,'Keratin Treatment',3),(14,2,'Hair Botox',4),
  (15,2,'Smoothing',5),(16,2,'Coloring',6),
  (17,2,'Highlight',7),(18,2,'Balayage',8),
  (19,2,'Cutting & Styling',9),(20,2,'PRP Hair Treatment',10),
  (21,3,'Dental',1);

-- ------------------------------------------------------------
-- 5. riwayat_treatment
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `riwayat_treatment` (
  `id`                INT(11)      NOT NULL AUTO_INCREMENT,
  `pasien_id`         INT(11)      NOT NULL,
  `divisi_id`         TINYINT(4)   NULL DEFAULT NULL,
  `tanggal_treatment` DATE         NOT NULL,
  `nama_treatment`    VARCHAR(200) NOT NULL,
  `catatan`           TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_pasien` (`pasien_id`),
  CONSTRAINT `fk_rt_pasien`
    FOREIGN KEY (`pasien_id`) REFERENCES `pasien`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rt_divisi`
    FOREIGN KEY (`divisi_id`) REFERENCES `divisi`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. treatment_foto  (multi-foto before/after per treatment)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `treatment_foto` (
  `id`           INT(11)               NOT NULL AUTO_INCREMENT,
  `treatment_id` INT(11)               NOT NULL,
  `tipe`         ENUM('before','after') NOT NULL,
  `nama_file`    VARCHAR(255)          NOT NULL,   -- relatif dari uploads/
  `urutan`       TINYINT(3)            NOT NULL DEFAULT 0,
  `created_at`   DATETIME              NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_treatment` (`treatment_id`),
  CONSTRAINT `fk_tf_treatment`
    FOREIGN KEY (`treatment_id`) REFERENCES `riwayat_treatment`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
