-- ============================================================================
-- DOSYA: kurulum/migration_2026_08_duello_sure.sql
-- TARİH: 2026-08
--
-- NE İŞE YARAR? (HALK DİLİNDE):
-- Düelloda kazananı "kim önce sunucuya ulaştı" yerine "kim gerçekten daha
-- hızlı düşündü" ölçüsüne göre belirleyebilmek için gereken sütunlar.
--
-- ÇÖZÜLEN HAKSIZLIK:
-- Eski sistemde cevap.php'ye İLK ULAŞAN kazanıyordu. Ama iki oyuncunun
-- ekranı aynı anda hazır olmuyor:
--   * Biri sayfayı 1 saniye geç açabilir
--   * Biri yavaş internette olabilir (istek 300 ms geç varır)
--   * Tarayıcı/telefon farkı geri sayımı kaydırabilir
-- Sonuçta daha geç tıklayan biri, sırf ağı hızlı diye kazanabiliyordu.
--
-- YENİ ÖLÇÜ:
-- Her oyuncunun tarayıcısı, SORU EKRANDA GÖRÜNDÜĞÜ AN sıfırdan bir kronometre
-- başlatır (performance.now()). Cevabıyla birlikte kaç MİLİSANİYEDE bastığını
-- gönderir. Sunucu iki cevabı da bekler, sonra en küçük süreyi kazanan sayar.
-- Böylece ağ gecikmesi ve ekranın geç açılması sonucu etkilemez.
--
-- ÇALIŞTIRMA: phpMyAdmin -> mblock_egitim -> SQL sekmesi -> yapıştır -> Git
-- ============================================================================

ALTER TABLE `quiz_duellolar`
  ADD COLUMN IF NOT EXISTS `okuyan_cevap` char(1) DEFAULT NULL
      COMMENT 'Meydan okuyanın işaretlediği şık (A/B/C/D)'
      AFTER `soru_id`,
  ADD COLUMN IF NOT EXISTS `okuyan_sure_ms` int(11) DEFAULT NULL
      COMMENT 'Soru ekranda göründükten kaç ms sonra bastı'
      AFTER `okuyan_cevap`,
  ADD COLUMN IF NOT EXISTS `okunan_cevap` char(1) DEFAULT NULL
      COMMENT 'Meydan okunanın işaretlediği şık (A/B/C/D)'
      AFTER `okuyan_sure_ms`,
  ADD COLUMN IF NOT EXISTS `okunan_sure_ms` int(11) DEFAULT NULL
      COMMENT 'Soru ekranda göründükten kaç ms sonra bastı'
      AFTER `okunan_cevap`;

-- "Bu düelloda iki taraf da cevapladı mı?" sorgusu için
ALTER TABLE `quiz_duellolar`
  ADD KEY IF NOT EXISTS `idx_duello_cevaplar` (`id`, `durum`);


-- ---------------------------------------------------------------------------
-- KONTROL
-- ---------------------------------------------------------------------------
SELECT 'quiz_duellolar süre sütunları' AS kontrol, COUNT(*) AS bulunan
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME   = 'quiz_duellolar'
   AND COLUMN_NAME IN ('okuyan_cevap','okuyan_sure_ms','okunan_cevap','okunan_sure_ms');
-- Beklenen: 4
