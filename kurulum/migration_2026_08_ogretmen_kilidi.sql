-- ============================================================================
-- DOSYA: kurulum/migration_2026_08_ogretmen_kilidi.sql
-- TARİH: 2026-08
--
-- NE İŞE YARAR? (HALK DİLİNDE):
-- Öğretmenin ders anlatırken öğrencilerin ekranını dondurabilmesi için.
-- "Herkes bilgisayardan başını kaldırsın, tahtaya bakın" anı.
-- Öğretmen kilidi açana kadar öğrenci hiçbir ilerleme kaydedemez.
--
-- ⚠️ NEDEN AYRI SÜTUN? MEVCUT `durum = 'kilitli'` KULLANILMADI:
-- `ogrenciler.durum` sütunundaki 'kilitli' değeri BAŞKA BİR ŞEY demek:
-- öğrenci bölüm sonu şifresini 3 kez yanlış girmiş demek (bir tür ceza/kilit).
-- Öğretmen o kilidi "Kilidi Aç" ile kaldırıyor.
--
-- İkisini aynı sütunda tutsaydık:
--   * Öğretmen sınıfı derste dondurup açtığında, şifre yüzünden kilitlenmiş
--     öğrencinin cezası da farkında olmadan kalkardı
--   * "Bu öğrenci neden kilitli?" sorusunun cevabı belirsiz olurdu
-- Bu yüzden ders kilidi KENDİ bayrağında duruyor. İkisi birbirini ezmez.
--
-- ÇALIŞTIRMA: phpMyAdmin -> mblock_egitim -> SQL sekmesi -> yapıştır -> Git
-- ============================================================================

ALTER TABLE `ogrenciler`
  ADD COLUMN IF NOT EXISTS `ogretmen_kilidi` tinyint(1) NOT NULL DEFAULT 0
      COMMENT 'Öğretmen ders sırasında ekranı dondurdu mu (1=kilitli)',
  ADD COLUMN IF NOT EXISTS `kilit_zamani` datetime DEFAULT NULL
      COMMENT 'Kilidin konulduğu an — öğrenciye "5 dakikadır kilitli" demek için',
  ADD COLUMN IF NOT EXISTS `kilit_mesaji` varchar(255) DEFAULT NULL
      COMMENT 'Öğretmenin yazdığı not, öğrencinin kilit ekranında görünür';

-- "Bu sınıfta kilitli kaç öğrenci var?" sorgusu için
ALTER TABLE `ogrenciler`
  ADD KEY IF NOT EXISTS `idx_ogretmen_kilidi` (`sinif_id`, `ogretmen_kilidi`);


-- ---------------------------------------------------------------------------
-- KONTROL
-- ---------------------------------------------------------------------------
SELECT 'ogrenciler kilit sütunları' AS kontrol, COUNT(*) AS bulunan
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME   = 'ogrenciler'
   AND COLUMN_NAME IN ('ogretmen_kilidi', 'kilit_zamani', 'kilit_mesaji');
-- Beklenen: 3
