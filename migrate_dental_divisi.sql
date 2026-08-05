USE `klinik_kecantikan`;

INSERT INTO `divisi` (`kode`, `nama`, `warna`, `urutan`)
SELECT 'dental', 'Dental', '#0ea5e9', 3
WHERE NOT EXISTS (
  SELECT 1
  FROM `divisi`
  WHERE `kode` = 'dental'
);

SET @dental_id := (
  SELECT `id`
  FROM `divisi`
  WHERE `kode` = 'dental'
  LIMIT 1
);

INSERT INTO `master_perawatan` (`divisi_id`, `nama`, `deskripsi`, `aktif`, `urutan`)
SELECT @dental_id, 'Dental', 'Treatment awal untuk divisi Dental.', 1, 1
WHERE @dental_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM `master_perawatan`
    WHERE `divisi_id` = @dental_id
      AND `nama` = 'Dental'
  );
