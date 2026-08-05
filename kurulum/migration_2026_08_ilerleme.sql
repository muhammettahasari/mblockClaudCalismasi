-- ============================================================================
-- DOSYA: kurulum/migration_2026_08_ilerleme.sql
-- TARİH: 2026-08
--
-- NE İŞE YARAR? (HALK DİLİNDE):
-- "İlerleme çubuğu + öğretmenin öğrenciyi geri gönderebilmesi" özelliği için
-- gereken sütunları ekler.
--
-- TASARIM KARARI — NEDEN SATIR SİLMİYORUZ?
-- Öğretmen bir bölümü sıfırladığında v2_ogrenci_ilerleme satırını SİLMİYORUZ.
-- Bunun yerine kazanilan_puan = 0 yapıyoruz. Böylece:
--   * deneme_sayisi (öğrenci kaç denemede çözmüştü) kaybolmaz
--   * "bu görev daha önce sıfırlanmıştı" bilgisi geçmişte kalır
--   * görev otomatik olarak "çözülmemiş" sayılır (her yerdeki kontrol
--     zaten kazanilan_puan > 0 bakıyor)
--
-- KIRMIZI CEZA EFEKTİ İÇİN AYRI BAYRAK YOK:
-- "Öğrencinin cezası var mı?" sorusu şu sorgudan TÜRETİLİR:
--   sifirlanma_tarihi IS NOT NULL AND kazanilan_puan = 0
-- Ayrı bir ogrenciler.cezali sütunu koysaydık iki yerde ilerleme bilgisi
-- olurdu ve senkron bozulabilirdi.
--
-- ÇALIŞTIRMA: phpMyAdmin -> mblock_egitim -> SQL sekmesi -> yapıştır -> Git
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. SIFIRLAMA İZLERİ
-- ---------------------------------------------------------------------------
ALTER TABLE `v2_ogrenci_ilerleme`
  ADD COLUMN IF NOT EXISTS `sifirlanma_tarihi` datetime DEFAULT NULL
      COMMENT 'Öğretmen bu görevi ne zaman sıfırladı (NULL = hiç sıfırlanmadı)'
      AFTER `tamamlanma_tarihi`,
  ADD COLUMN IF NOT EXISTS `sifirlayan_ogretmen_id` int(11) DEFAULT NULL
      COMMENT 'Hangi öğretmen sıfırladı'
      AFTER `sifirlanma_tarihi`,
  ADD COLUMN IF NOT EXISTS `sifirlama_sebebi` varchar(255) DEFAULT NULL
      COMMENT 'Öğretmenin yazdığı gerekçe (öğrenciye gösterilir)'
      AFTER `sifirlayan_ogretmen_id`,
  ADD COLUMN IF NOT EXISTS `sifirlama_sayisi` int(11) NOT NULL DEFAULT 0
      COMMENT 'Bu görev kaç kez sıfırlandı'
      AFTER `sifirlama_sebebi`;

-- "Bu öğrencinin bekleyen cezası var mı?" sorgusu için indeks
ALTER TABLE `v2_ogrenci_ilerleme`
  ADD KEY IF NOT EXISTS `idx_ceza` (`ogrenci_id`, `kazanilan_puan`, `sifirlanma_tarihi`);


-- ---------------------------------------------------------------------------
-- 2. SIFIRLAMA GÜNLÜĞÜ (öğretmen kayıtları — kim, ne zaman, neden, kaç elmas)
--    Öğrenciye "öğretmenin seni geri gönderdi" bildirimi bu tablodan okunur.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `v2_sifirlama_gunlugu` (
  `id`               int(11) NOT NULL AUTO_INCREMENT,
  `ogrenci_id`       int(11) NOT NULL,
  `ogretmen_id`      int(11) NOT NULL,
  `bolum_idler`      varchar(255) NOT NULL COMMENT 'Sıfırlanan bölüm id listesi, virgüllü',
  `gorev_sayisi`     int(11) NOT NULL DEFAULT 0,
  `elmas_cezasi`     int(11) NOT NULL DEFAULT 0 COMMENT 'Geri alınan elmas (pozitif sayı)',
  `sebep`            varchar(255) DEFAULT NULL,
  `okundu`           tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Öğrenci bildirimi gördü mü',
  `tarih`            timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ogrenci_okundu` (`ogrenci_id`, `okundu`),
  KEY `idx_ogretmen` (`ogretmen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;


-- ---------------------------------------------------------------------------
-- 3. ÖĞRENCİNİN AKTİF GÖREVİ (manuel geçiş için)
--    Artık görev bitince otomatik sıradakine atlamıyoruz; öğrenci kendisi
--    tıklıyor. Kaldığı yeri hatırlamak için son açtığı görevi tutuyoruz.
-- ---------------------------------------------------------------------------
ALTER TABLE `ogrenciler`
  ADD COLUMN IF NOT EXISTS `aktif_v2_gorev_id` int(11) DEFAULT NULL
      COMMENT 'Öğrencinin en son açtığı v2 görevi (kaldığın yerden devam)';


-- ---------------------------------------------------------------------------
-- 4. KONTROL — bu sorgular hata vermeden çalışıyorsa migration tamamdır
-- ---------------------------------------------------------------------------
SELECT 'v2_ogrenci_ilerleme sütunları' AS kontrol, COUNT(*) AS bulunan
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME   = 'v2_ogrenci_ilerleme'
   AND COLUMN_NAME IN ('sifirlanma_tarihi','sifirlayan_ogretmen_id','sifirlama_sebebi','sifirlama_sayisi');
-- Beklenen: 4

SELECT 'v2_sifirlama_gunlugu tablosu' AS kontrol, COUNT(*) AS bulunan
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v2_sifirlama_gunlugu';
-- Beklenen: 1

SELECT 'ogrenciler.aktif_v2_gorev_id' AS kontrol, COUNT(*) AS bulunan
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = 'ogrenciler' AND COLUMN_NAME = 'aktif_v2_gorev_id';
-- Beklenen: 1
