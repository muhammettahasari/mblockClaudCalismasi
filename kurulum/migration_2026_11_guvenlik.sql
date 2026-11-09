-- ============================================================================
-- DOSYA: kurulum/migration_2026_11_guvenlik.sql
-- TARİH: 2026-11
--
-- NE İŞE YARAR? (HALK DİLİNDE):
-- 1. giris_denemeleri tablosu: hatalı şifre / PIN denemelerini sayar.
--    (Kod tablo yoksa kendisi de kurar; burada belge olsun diye var.)
-- 2. ogrenciler.ogretmen_id KOPYASINI HEP DOĞRU TUTAR.
--    Bu sütun kayıt anında yazılan bir kopyaydı; sınıf başka öğretmene
--    devredilince ya da öğrenci başka sınıfa taşınınca BAYATLIYORDU.
--    30'dan fazla sorgu bu sütuna baktığı için hepsini değiştirmek yerine
--    kaynağında düzeltiyoruz:
--      a) Bugünkü bayat değerler siniflar tablosundan düzeltilir.
--      b) Öğrenci eklenirken / sınıfı değişirken öğretmen SINIFTAN alınır.
--      c) Sınıfın öğretmeni değişince sınıftaki bütün öğrencilerinki de değişir.
--    Gerçek zincir yine: ogretmenler -> siniflar -> ogrenciler
--    (Yetki açısından kritik uçlar zaten doğrudan bu zinciri kullanıyor;
--     tetikleyiciler kalan okuma sorgularının doğru sonuç vermesi için.)
--
-- ÇALIŞTIRMA: phpMyAdmin -> mblock_egitim -> SQL sekmesi -> yapıştır -> Git
--   ya da: mysql -u root mblock_egitim < kurulum\migration_2026_11_guvenlik.sql
-- Tekrar çalıştırılabilir (IF NOT EXISTS / DROP TRIGGER IF EXISTS).
-- ============================================================================

-- 1. KABA KUVVET SAYACI -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `giris_denemeleri` (
  `anahtar`     varchar(191) NOT NULL COMMENT 'ör. pin|ip:1.2.3.4  ogrenci|sinif:5|ali',
  `sayi`        int(11)      NOT NULL DEFAULT 0,
  `ilk_deneme`  datetime     NOT NULL,
  `kilit_bitis` datetime     DEFAULT NULL,
  PRIMARY KEY (`anahtar`),
  KEY `idx_ilk_deneme` (`ilk_deneme`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2a. BAYAT KOPYALARI DÜZELT --------------------------------------------------
UPDATE `ogrenciler` o
  JOIN `siniflar` s ON s.id = o.sinif_id
   SET o.ogretmen_id = s.ogretmen_id
 WHERE NOT (o.ogretmen_id <=> s.ogretmen_id);

-- 2b / 2c. TETİKLEYİCİLER -----------------------------------------------------
DROP TRIGGER IF EXISTS `trg_ogrenci_ogretmen_ekle`;
DROP TRIGGER IF EXISTS `trg_ogrenci_ogretmen_guncelle`;
DROP TRIGGER IF EXISTS `trg_sinif_ogretmen_devri`;

DELIMITER $$

CREATE TRIGGER `trg_ogrenci_ogretmen_ekle`
BEFORE INSERT ON `ogrenciler` FOR EACH ROW
BEGIN
    IF NEW.sinif_id IS NOT NULL THEN
        SET NEW.ogretmen_id = (SELECT s.ogretmen_id FROM siniflar s WHERE s.id = NEW.sinif_id);
    END IF;
END$$

CREATE TRIGGER `trg_ogrenci_ogretmen_guncelle`
BEFORE UPDATE ON `ogrenciler` FOR EACH ROW
BEGIN
    IF NOT (NEW.sinif_id <=> OLD.sinif_id) AND NEW.sinif_id IS NOT NULL THEN
        SET NEW.ogretmen_id = (SELECT s.ogretmen_id FROM siniflar s WHERE s.id = NEW.sinif_id);
    END IF;
END$$

CREATE TRIGGER `trg_sinif_ogretmen_devri`
AFTER UPDATE ON `siniflar` FOR EACH ROW
BEGIN
    IF NOT (NEW.ogretmen_id <=> OLD.ogretmen_id) THEN
        UPDATE ogrenciler SET ogretmen_id = NEW.ogretmen_id WHERE sinif_id = NEW.id;
    END IF;
END$$

DELIMITER ;
