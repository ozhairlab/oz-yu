-- ============================================================
--  klinik_kecantikan_FULL.sql
--  Schema + Data lengkap sistem rekam medis Ozthetique Jakarta
--
--  Generated : 2026-07-18
--  Charset   : utf8mb4 / utf8mb4_unicode_ci
--
--  ┌─ Cara Import ──────────────────────────────────────────┐
--  │  phpMyAdmin (hosting):                                 │
--  │  1. Pilih database Anda di panel kiri                  │
--  │  2. Klik tab "SQL"                                     │
--  │  3. Paste seluruh isi file ini                         │
--  │  4. Klik "Go"                                          │
--  │                                                        │
--  │  PENTING: Jangan jalankan di database lain!            │
--  │  Pilih database yang benar dulu di panel kiri.         │
--  └────────────────────────────────────────────────────────┘
--
--  ┌─ Login Default ────────────────────────────────────────┐
--  │  Username : admin                                      │
--  │  Password : Admin1234!                                 │
--  └────────────────────────────────────────────────────────┘
-- ============================================================

SET NAMES utf8mb4;
SET TIME_ZONE = '+07:00';
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ============================================================
-- DROP tables (urutan terbalik agar FK tidak konflik)
-- ============================================================
DROP TABLE IF EXISTS `treatment_foto`;
DROP TABLE IF EXISTS `riwayat_treatment`;
DROP TABLE IF EXISTS `master_perawatan`;
DROP TABLE IF EXISTS `pasien`;
DROP TABLE IF EXISTS `divisi`;
DROP TABLE IF EXISTS `admin_klinik`;

-- ============================================================
-- TABEL 1: admin_klinik
-- ============================================================
CREATE TABLE `admin_klinik` (
  `id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB
  AUTO_INCREMENT=2
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- username: admin | password: Admin1234!
INSERT INTO `admin_klinik` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$qjdX3Gp/dxBKMXVDmm6n6e1TvN4ggnPwnl9pQMt/m7VQtSFBW75jW');

-- ============================================================
-- TABEL 2: divisi
-- ============================================================
CREATE TABLE `divisi` (
  `id`     TINYINT(4)   NOT NULL AUTO_INCREMENT,
  `kode`   VARCHAR(20)  NOT NULL,
  `nama`   VARCHAR(100) NOT NULL,
  `warna`  VARCHAR(7)   NOT NULL DEFAULT '#e91e63',
  `urutan` TINYINT(4)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kode` (`kode`)
) ENGINE=InnoDB
  AUTO_INCREMENT=4
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO `divisi` (`id`, `kode`, `nama`, `warna`, `urutan`) VALUES
(1, 'ozthetique', 'Ozthetique', '#e91e63', 1),
(2, 'ozhairlab',  'OzHairLab',  '#7c4dff', 2),
(3, 'dental',     'Dental',     '#0ea5e9', 3);

-- ============================================================
-- TABEL 3: pasien
-- ============================================================
CREATE TABLE `pasien` (
  `id`            INT(11)       NOT NULL AUTO_INCREMENT,
  `nama`          VARCHAR(150)  NOT NULL,
  `jenis_kelamin` ENUM('P','L') NULL DEFAULT NULL,
  `telepon`       VARCHAR(20)   NULL DEFAULT NULL,
  `email`         VARCHAR(150)  NULL DEFAULT NULL,
  `tanggal_lahir` DATE          NULL DEFAULT NULL,
  `sumber_pasien` VARCHAR(60)   NULL DEFAULT NULL,
  `catatan_crm`   TEXT          NULL DEFAULT NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_nama` (`nama`)
) ENGINE=InnoDB
  AUTO_INCREMENT=4
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Data pasien yang sudah ada (ganti nama sesuai data asli jika diperlukan)
INSERT INTO `pasien` (`id`, `nama`, `jenis_kelamin`, `telepon`, `email`, `tanggal_lahir`, `sumber_pasien`, `catatan_crm`, `created_at`) VALUES
(2, 'elit', NULL, '09876543',  NULL, '2026-12-07', NULL, NULL, '2026-07-17 11:25:32'),
(3, 'elit', NULL, '098765432', NULL, '2026-12-07', NULL, NULL, '2026-07-18 12:41:37');

-- ============================================================
-- TABEL 4: master_perawatan
-- ============================================================
CREATE TABLE `master_perawatan` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `divisi_id`  TINYINT(4)   NOT NULL,
  `nama`       VARCHAR(200) NOT NULL,
  `deskripsi`  TEXT         NULL DEFAULT NULL,
  `aktif`      TINYINT(1)   NOT NULL DEFAULT 1,
  `urutan`     INT(11)      NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_divisi` (`divisi_id`),
  CONSTRAINT `fk_mp_divisi`
    FOREIGN KEY (`divisi_id`) REFERENCES `divisi` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  AUTO_INCREMENT=22
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO `master_perawatan` (`id`, `divisi_id`, `nama`, `aktif`, `urutan`) VALUES
-- Ozthetique
( 1, 1, 'Facial Brightening',    1,  1),
( 2, 1, 'Facial Deep Cleansing', 1,  2),
( 3, 1, 'Chemical Peeling',      1,  3),
( 4, 1, 'Laser Rejuvenation',    1,  4),
( 5, 1, 'Filler Hidung',         1,  5),
( 6, 1, 'Filler Bibir',          1,  6),
( 7, 1, 'Botox Dahi',            1,  7),
( 8, 1, 'Microneedling',         1,  8),
( 9, 1, 'LED Therapy',           1,  9),
(10, 1, 'Aqua Peeling',          1, 10),
-- OzHairLab
(11, 2, 'Hair Spa',              1,  1),
(12, 2, 'Scalp Treatment',       1,  2),
(13, 2, 'Keratin Treatment',     1,  3),
(14, 2, 'Hair Botox',            1,  4),
(15, 2, 'Smoothing',             1,  5),
(16, 2, 'Coloring',              1,  6),
(17, 2, 'Highlight',             1,  7),
(18, 2, 'Balayage',              1,  8),
(19, 2, 'Cutting & Styling',     1,  9),
(20, 2, 'PRP Hair Treatment',   1, 10),
-- Dental
(21, 3, 'Dental',               1,  1);

-- ============================================================
-- TABEL 5: riwayat_treatment
-- ============================================================
CREATE TABLE `riwayat_treatment` (
  `id`                INT(11)      NOT NULL AUTO_INCREMENT,
  `pasien_id`         INT(11)      NOT NULL,
  `divisi_id`         TINYINT(4)   NULL DEFAULT NULL,
  `tanggal_treatment` DATE         NOT NULL,
  `nama_treatment`    VARCHAR(200) NOT NULL,
  `catatan`           TEXT         NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_pasien`  (`pasien_id`),
  INDEX `idx_tanggal` (`tanggal_treatment`),
  CONSTRAINT `fk_rt_pasien`
    FOREIGN KEY (`pasien_id`) REFERENCES `pasien` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rt_divisi`
    FOREIGN KEY (`divisi_id`) REFERENCES `divisi` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB
  AUTO_INCREMENT=4
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO `riwayat_treatment` (`id`, `pasien_id`, `divisi_id`, `tanggal_treatment`, `nama_treatment`, `catatan`) VALUES
(3, 3, 1, '2026-07-18', 'Laser Rejuvenation', 'kusam');

-- ============================================================
-- TABEL 6: treatment_foto
-- ============================================================
CREATE TABLE `treatment_foto` (
  `id`           INT(11)                NOT NULL AUTO_INCREMENT,
  `treatment_id` INT(11)                NOT NULL,
  `tipe`         ENUM('before','after') NOT NULL,
  `nama_file`    VARCHAR(255)           NOT NULL,
  `urutan`       TINYINT(3)             NOT NULL DEFAULT 0,
  `created_at`   DATETIME               NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_treatment` (`treatment_id`),
  CONSTRAINT `fk_tf_treatment`
    FOREIGN KEY (`treatment_id`) REFERENCES `riwayat_treatment` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  AUTO_INCREMENT=9
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO `treatment_foto` (`id`, `treatment_id`, `tipe`, `nama_file`, `urutan`, `created_at`) VALUES
(4, 3, 'before', 'pasien_3/1784359708_aeac84b7.jpg', 1, '2026-07-18 14:28:28'),
(5, 3, 'before', 'pasien_3/1784359708_c0e2f006.jpg', 2, '2026-07-18 14:28:28'),
(6, 3, 'after',  'pasien_3/1784359708_aeff8663.jpg', 1, '2026-07-18 14:28:28'),
(7, 3, 'after',  'pasien_3/1784359708_ef438e35.jpg', 2, '2026-07-18 14:28:28'),
(8, 3, 'after',  'pasien_3/1784359708_86abb7db.jpg', 3, '2026-07-18 14:28:28');

-- ============================================================
-- AKTIFKAN KEMBALI FOREIGN KEY CHECKS
-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- RINGKASAN ISI DATABASE
-- ============================================================
-- admin_klinik   : 1 user  (admin / Admin1234!)
-- divisi         : 2 divisi (Ozthetique, OzHairLab)
-- master_perawatan: 20 jenis perawatan
-- pasien         : 2 pasien
-- riwayat_treatment: 1 treatment
-- treatment_foto : 5 foto (2 before + 3 after)
--
-- Setelah import, salin juga folder berikut ke server baru:
--   uploads/pasien_3/   (berisi 5 file foto .jpg)
-- ============================================================
