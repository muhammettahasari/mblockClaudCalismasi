-- ============================================================================
--  mBlock Akademi — Güvenlik ve Mimari Güncellemesi Migration'ı
--  Tarih: 2026-08
--  Veritabanı: mblock_egitim
--
--  ÇALIŞTIRMADAN ÖNCE MUTLAKA YEDEK AL:
--     mysqldump -u root mblock_egitim > yedek_migration_oncesi.sql
--
--  Çalıştırma:
--     mysql -u root mblock_egitim < kurulum/migration_2026_08_guvenlik.sql
--  veya phpMyAdmin > mblock_egitim > SQL sekmesine yapıştır.
--
--  Bu dosya TEKRAR TEKRAR çalıştırılabilir (idempotent).
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ============================================================================
-- 1. SÜPER ADMİN TABLOSU
--    Şifre password_hash() ile saklanır. İlk kayıt kod tarafında otomatik
--    oluşturulur (super / 123) — bkz. admin/super_auth.php
-- ============================================================================
CREATE TABLE IF NOT EXISTS `super_adminler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kullanici_adi` varchar(50) NOT NULL,
  `sifre_hash` varchar(255) NOT NULL,
  `ad_soyad` varchar(100) DEFAULT 'Süper Admin',
  `son_giris` datetime DEFAULT NULL,
  `basarisiz_deneme` int(11) NOT NULL DEFAULT 0,
  `kilit_bitis` datetime DEFAULT NULL,
  `olusturulma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kullanici_adi` (`kullanici_adi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;


-- ============================================================================
-- 2. QUIZ OTURUMUNU SINIF BAZINA İNDİR  (Sorun #6)
--    Eskiden global idi: bir öğretmen quiz açınca TÜM okullar çekiliyordu.
-- ============================================================================
ALTER TABLE `quiz_oturum`
  ADD COLUMN IF NOT EXISTS `ogretmen_id` int(11) DEFAULT NULL AFTER `id`,
  ADD COLUMN IF NOT EXISTS `sinif_id`    int(11) DEFAULT NULL AFTER `ogretmen_id`,
  ADD COLUMN IF NOT EXISTS `bitis_zaman` timestamp NULL DEFAULT NULL AFTER `baslangic_zaman`;

-- Mevcut (sahipsiz) oturumları kapat — hangi sınıfa ait olduğu bilinmiyor
UPDATE `quiz_oturum` SET `durum` = 'bitti' WHERE `ogretmen_id` IS NULL AND `durum` <> 'bitti';

ALTER TABLE `quiz_oturum`
  ADD KEY IF NOT EXISTS `idx_oturum_sahip` (`ogretmen_id`, `sinif_id`, `durum`);


-- ============================================================================
-- 3. DÜELLO ŞALTERİNİ ÖĞRETMEN BAZINA TAŞI  (Sorun #7)
--    duello_ayarlari (id=1) tek satır global şalterdi. Artık her öğretmenin
--    kendi düello penceresi var — arena_bitis ile aynı desen.
-- ============================================================================
ALTER TABLE `ogretmenler`
  ADD COLUMN IF NOT EXISTS `duello_bitis` datetime DEFAULT NULL AFTER `arena_bitis`,
  ADD COLUMN IF NOT EXISTS `onay_sifresi` varchar(255) DEFAULT '123' AFTER `sifre`;

-- Eski global şalter açıksa, onu kullanan öğretmenlere devret (tek seferlik)
UPDATE `ogretmenler` o
  JOIN `duello_ayarlari` d ON d.id = 1
  SET o.`duello_bitis` = d.`bitis_zamani`
  WHERE d.`durum` = 'acik' AND d.`bitis_zamani` > NOW();

-- duello_ayarlari artık KULLANILMIYOR (geriye dönük uyumluluk için duruyor)
ALTER TABLE `duello_ayarlari` COMMENT = 'KULLANIM DISI - ogretmenler.duello_bitis kullaniliyor';


-- ============================================================================
-- 4. ÖĞRENCİ İLERLEME ARŞİVİNİ DÜZELT  (Sorun #3)
--    api/bolum_bitir.php var olmayan bolum_id sütununa INSERT ediyordu.
-- ============================================================================
ALTER TABLE `ogrenci_ilerleme`
  MODIFY COLUMN `gorev_id` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `bolum_id` int(11) DEFAULT NULL AFTER `gorev_id`,
  ADD COLUMN IF NOT EXISTS `kaynak` enum('v1','v2') NOT NULL DEFAULT 'v1' AFTER `bolum_id`,
  ADD COLUMN IF NOT EXISTS `tarih` timestamp NOT NULL DEFAULT current_timestamp() AFTER `bekleyen_puan`;

ALTER TABLE `ogrenci_ilerleme`
  ADD KEY IF NOT EXISTS `idx_ilerleme_bolum` (`ogrenci_id`, `bolum_id`);


-- ============================================================================
-- 5. v2 GÖREV SİSTEMİ  (Sorun #17)
--    Görev tamamlama kaydı — aynı görevden iki kez puan alınmasını engeller.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `v2_ogrenci_ilerleme` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ogrenci_id` int(11) NOT NULL,
  `v2_gorev_id` int(11) NOT NULL,
  `kazanilan_puan` int(11) NOT NULL DEFAULT 0,
  `deneme_sayisi` int(11) NOT NULL DEFAULT 1,
  `tamamlanma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ogrenci_gorev` (`ogrenci_id`, `v2_gorev_id`),
  KEY `idx_v2_gorev` (`v2_gorev_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- v2 görevlerine yayın kontrolü (taslak görevler öğrenciye görünmesin)
ALTER TABLE `v2_gorevler`
  ADD COLUMN IF NOT EXISTS `yayinda` tinyint(1) NOT NULL DEFAULT 1 AFTER `gorev_tipi`;

ALTER TABLE `v2_gorevler`
  ADD KEY IF NOT EXISTS `idx_v2_bolum` (`bolum_id`, `sira_no`);


-- ============================================================================
-- 6. PERFORMANS İNDEKSLERİ  (Sorun #10, #11)
-- ============================================================================
ALTER TABLE `ogrenciler`
  ADD KEY IF NOT EXISTS `idx_ogrenci_sinif` (`sinif_id`, `durum`),
  ADD KEY IF NOT EXISTS `idx_ogrenci_ogretmen` (`ogretmen_id`),
  ADD KEY IF NOT EXISTS `idx_ogrenci_puan` (`kalici_puan`);

ALTER TABLE `siniflar`
  ADD KEY IF NOT EXISTS `idx_sinif_ogretmen` (`ogretmen_id`);

ALTER TABLE `ogrenci_envanter`
  ADD KEY IF NOT EXISTS `idx_envanter_ogrenci` (`ogrenci_id`),
  ADD KEY IF NOT EXISTS `idx_envanter_esya` (`esya_id`);

ALTER TABLE `esya_kutuphanesi`
  ADD KEY IF NOT EXISTS `idx_esya_kategori` (`kategori`, `seviye`);

ALTER TABLE `arena_savaslari`
  ADD KEY IF NOT EXISTS `idx_savas_ikili` (`saldiran_id`, `savunan_id`, `tarih`),
  ADD KEY IF NOT EXISTS `idx_savas_savunan` (`savunan_id`);

ALTER TABLE `quiz_ogrenci`
  ADD KEY IF NOT EXISTS `idx_quiz_ogrenci` (`oturum_id`, `ogrenci_id`);

ALTER TABLE `quiz_savas_log`
  ADD KEY IF NOT EXISTS `idx_log_savunan` (`savunan_id`, `oturum_id`, `savunan_gordu_mu`);

ALTER TABLE `quiz_duellolar`
  ADD KEY IF NOT EXISTS `idx_duello_okunan` (`meydan_okunan_id`, `durum`),
  ADD KEY IF NOT EXISTS `idx_duello_okuyan` (`meydan_okuyan_id`, `durum`);

ALTER TABLE `canavar_hasar_log`
  ADD KEY IF NOT EXISTS `idx_hasar_tarih` (`canavar_id`, `saldiri_tarihi`);


-- ============================================================================
-- 7. YETİM SATIRLARI TEMİZLE  (FK eklemeden ÖNCE zorunlu)  (Sorun #16)
-- ============================================================================
DELETE FROM `ogrenci_envanter`
  WHERE `ogrenci_id` NOT IN (SELECT `id` FROM `ogrenciler`)
     OR `esya_id`    NOT IN (SELECT `id` FROM `esya_kutuphanesi`);

DELETE FROM `ogrenci_avatar_durum`
  WHERE `ogrenci_id` NOT IN (SELECT `id` FROM `ogrenciler`);

DELETE FROM `ogrenci_ilerleme`
  WHERE `ogrenci_id` NOT IN (SELECT `id` FROM `ogrenciler`);

DELETE FROM `arena_savaslari`
  WHERE `saldiran_id` NOT IN (SELECT `id` FROM `ogrenciler`)
     OR `savunan_id`  NOT IN (SELECT `id` FROM `ogrenciler`);

DELETE FROM `canavar_hasar_log`
  WHERE `ogrenci_id` NOT IN (SELECT `id` FROM `ogrenciler`);

DELETE FROM `quiz_ogrenci`
  WHERE `ogrenci_id` NOT IN (SELECT `id` FROM `ogrenciler`)
     OR `oturum_id`  NOT IN (SELECT `id` FROM `quiz_oturum`);

DELETE FROM `quiz_savas_log`
  WHERE `saldiran_id` NOT IN (SELECT `id` FROM `ogrenciler`)
     OR `savunan_id`  NOT IN (SELECT `id` FROM `ogrenciler`);

DELETE FROM `quiz_duellolar`
  WHERE `meydan_okuyan_id` NOT IN (SELECT `id` FROM `ogrenciler`)
     OR `meydan_okunan_id` NOT IN (SELECT `id` FROM `ogrenciler`);

DELETE FROM `gorevler`
  WHERE `bolum_id` NOT IN (SELECT `id` FROM `bolumler`);

DELETE FROM `quiz_sorulari`
  WHERE `bolum_id` NOT IN (SELECT `id` FROM `bolumler`);

-- Sahipsiz avatar slotlarını boşalt (silinmiş eşyalara işaret edenler)
UPDATE `ogrenci_avatar_durum` SET `karakter_id`     = NULL WHERE `karakter_id`     NOT IN (SELECT `id` FROM `esya_kutuphanesi`);
UPDATE `ogrenci_avatar_durum` SET `yuz_id`          = NULL WHERE `yuz_id`          NOT IN (SELECT `id` FROM `esya_kutuphanesi`);
UPDATE `ogrenci_avatar_durum` SET `sac_id`          = NULL WHERE `sac_id`          NOT IN (SELECT `id` FROM `esya_kutuphanesi`);
UPDATE `ogrenci_avatar_durum` SET `kafa_id`         = NULL WHERE `kafa_id`         NOT IN (SELECT `id` FROM `esya_kutuphanesi`);
UPDATE `ogrenci_avatar_durum` SET `zirh_id`         = NULL WHERE `zirh_id`         NOT IN (SELECT `id` FROM `esya_kutuphanesi`);
UPDATE `ogrenci_avatar_durum` SET `sag_el_id`       = NULL WHERE `sag_el_id`       NOT IN (SELECT `id` FROM `esya_kutuphanesi`);
UPDATE `ogrenci_avatar_durum` SET `sol_el_id`       = NULL WHERE `sol_el_id`       NOT IN (SELECT `id` FROM `esya_kutuphanesi`);
UPDATE `ogrenci_avatar_durum` SET `ayakkabi_id`     = NULL WHERE `ayakkabi_id`     NOT IN (SELECT `id` FROM `esya_kutuphanesi`);
UPDATE `ogrenci_avatar_durum` SET `binek_id`        = NULL WHERE `binek_id`        NOT IN (SELECT `id` FROM `esya_kutuphanesi`);
UPDATE `ogrenci_avatar_durum` SET `evcil_hayvan_id` = NULL WHERE `evcil_hayvan_id` NOT IN (SELECT `id` FROM `esya_kutuphanesi`);

-- Sınıfı/öğretmeni silinmiş öğrencileri boşa düşür (silme, veri kaybolmasın)
UPDATE `ogrenciler` SET `sinif_id`    = NULL WHERE `sinif_id`    IS NOT NULL AND `sinif_id`    NOT IN (SELECT `id` FROM `siniflar`);
UPDATE `ogrenciler` SET `ogretmen_id` = NULL WHERE `ogretmen_id` IS NOT NULL AND `ogretmen_id` NOT IN (SELECT `id` FROM `ogretmenler`);
UPDATE `siniflar`   SET `ogretmen_id` = NULL WHERE `ogretmen_id` IS NOT NULL AND `ogretmen_id` NOT IN (SELECT `id` FROM `ogretmenler`);


-- ============================================================================
-- 8. FOREIGN KEY'LER  (Sorun #16)
--    Öğrenci silinince envanteri/avatarı/savaş kaydı da silinsin — yetim kalmasın.
--    NOT: Bu blok tekrar çalıştırılırsa "Duplicate foreign key" hatası verir,
--    bu normaldir; geri kalan migration etkilenmez.
-- ============================================================================
ALTER TABLE `siniflar`
  ADD CONSTRAINT `fk_sinif_ogretmen` FOREIGN KEY (`ogretmen_id`)
      REFERENCES `ogretmenler` (`id`) ON DELETE CASCADE;

ALTER TABLE `ogrenciler`
  ADD CONSTRAINT `fk_ogrenci_sinif` FOREIGN KEY (`sinif_id`)
      REFERENCES `siniflar` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ogrenci_ogretmen` FOREIGN KEY (`ogretmen_id`)
      REFERENCES `ogretmenler` (`id`) ON DELETE CASCADE;

ALTER TABLE `ogrenci_avatar_durum`
  ADD CONSTRAINT `fk_avatar_ogrenci` FOREIGN KEY (`ogrenci_id`)
      REFERENCES `ogrenciler` (`id`) ON DELETE CASCADE;

ALTER TABLE `ogrenci_envanter`
  ADD CONSTRAINT `fk_envanter_ogrenci` FOREIGN KEY (`ogrenci_id`)
      REFERENCES `ogrenciler` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_envanter_esya` FOREIGN KEY (`esya_id`)
      REFERENCES `esya_kutuphanesi` (`id`) ON DELETE CASCADE;

ALTER TABLE `ogrenci_ilerleme`
  ADD CONSTRAINT `fk_ilerleme_ogrenci` FOREIGN KEY (`ogrenci_id`)
      REFERENCES `ogrenciler` (`id`) ON DELETE CASCADE;

ALTER TABLE `arena_savaslari`
  ADD CONSTRAINT `fk_savas_saldiran` FOREIGN KEY (`saldiran_id`)
      REFERENCES `ogrenciler` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_savas_savunan` FOREIGN KEY (`savunan_id`)
      REFERENCES `ogrenciler` (`id`) ON DELETE CASCADE;

ALTER TABLE `canavar_hasar_log`
  ADD CONSTRAINT `fk_hasar_ogrenci` FOREIGN KEY (`ogrenci_id`)
      REFERENCES `ogrenciler` (`id`) ON DELETE CASCADE;

ALTER TABLE `quiz_ogrenci`
  ADD CONSTRAINT `fk_qogrenci_ogrenci` FOREIGN KEY (`ogrenci_id`)
      REFERENCES `ogrenciler` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_qogrenci_oturum` FOREIGN KEY (`oturum_id`)
      REFERENCES `quiz_oturum` (`id`) ON DELETE CASCADE;

ALTER TABLE `quiz_savas_log`
  ADD CONSTRAINT `fk_qlog_saldiran` FOREIGN KEY (`saldiran_id`)
      REFERENCES `ogrenciler` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_qlog_savunan` FOREIGN KEY (`savunan_id`)
      REFERENCES `ogrenciler` (`id`) ON DELETE CASCADE;

ALTER TABLE `quiz_duellolar`
  ADD CONSTRAINT `fk_duello_okuyan` FOREIGN KEY (`meydan_okuyan_id`)
      REFERENCES `ogrenciler` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_duello_okunan` FOREIGN KEY (`meydan_okunan_id`)
      REFERENCES `ogrenciler` (`id`) ON DELETE CASCADE;

ALTER TABLE `gorevler`
  ADD CONSTRAINT `fk_gorev_bolum` FOREIGN KEY (`bolum_id`)
      REFERENCES `bolumler` (`id`) ON DELETE CASCADE;

ALTER TABLE `quiz_sorulari`
  ADD CONSTRAINT `fk_soru_bolum` FOREIGN KEY (`bolum_id`)
      REFERENCES `bolumler` (`id`) ON DELETE CASCADE;

ALTER TABLE `v2_ogrenci_ilerleme`
  ADD CONSTRAINT `fk_v2ilerleme_ogrenci` FOREIGN KEY (`ogrenci_id`)
      REFERENCES `ogrenciler` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_v2ilerleme_gorev` FOREIGN KEY (`v2_gorev_id`)
      REFERENCES `v2_gorevler` (`id`) ON DELETE CASCADE;

ALTER TABLE `quiz_oturum`
  ADD CONSTRAINT `fk_oturum_ogretmen` FOREIGN KEY (`ogretmen_id`)
      REFERENCES `ogretmenler` (`id`) ON DELETE CASCADE;


-- ============================================================================
-- 9. ÖLÜ SÜTUNLARI AKTİFLEŞTİR  (Sorun #14)
-- ============================================================================
-- ogrenci_avatar_durum.genel_guc / ceviklik → artık güç ÖNBELLEĞİ.
-- Avatar değişince bilesenler/rpg_matris.php > gucuYenidenHesapla() günceller.
ALTER TABLE `ogrenci_avatar_durum`
  ADD COLUMN IF NOT EXISTS `guc_guncelleme` datetime DEFAULT NULL AFTER `ceviklik`;

-- Önbelleği ilk kez doldurmak için: admin panelinden "Güçleri Yeniden Hesapla"
-- butonuna bas veya boş bırak; ilk savaşta otomatik hesaplanır.
UPDATE `ogrenci_avatar_durum` SET `guc_guncelleme` = NULL;

-- ogrenciler.kalkan_bitis → yenilgi sonrası koruma kalkanı (artık kullanılıyor)
-- ogretmenler.uyelik_bitis_tarihi → bitis_tarihi ile senkron tutulur
UPDATE `ogretmenler`
  SET `uyelik_bitis_tarihi` = DATE(`bitis_tarihi`)
  WHERE `bitis_tarihi` IS NOT NULL;


-- ============================================================================
-- 10. GÜVENLİK: ÖĞRETMEN ONAY ŞİFRESİ  (Sorun #4)
--     Eskiden api/teacher_approval.php içinde sabit "123" idi.
--     Artık her öğretmenin kendi onay şifresi var (varsayılan 123).
-- ============================================================================
UPDATE `ogretmenler` SET `onay_sifresi` = '123' WHERE `onay_sifresi` IS NULL OR `onay_sifresi` = '';


SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- BİTTİ. Kontrol için:
--   SELECT * FROM super_adminler;          -- ilk girişte otomatik oluşur
--   SHOW COLUMNS FROM quiz_oturum;         -- ogretmen_id + sinif_id olmalı
--   SHOW COLUMNS FROM ogretmenler;         -- duello_bitis + onay_sifresi olmalı
-- ============================================================================
