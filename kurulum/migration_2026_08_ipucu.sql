-- ============================================================================
-- DOSYA: kurulum/migration_2026_08_ipucu.sql
-- TARİH: 2026-08
--
-- NE İŞE YARAR? (HALK DİLİNDE):
-- "💡 Bilgi Al" düğmesi artık gerçekten çalışıyor. Öğrenci bilgi alırsa o
-- görevden kazanacağı puan ve elmas YARIYA iner. Bu tablo "bu öğrenci bu
-- görevde bilgi aldı" bilgisini tutar.
--
-- ⚠️ NEDEN AYRI TABLO? `v2_ogrenci_ilerleme`'YE SÜTUN EKLENMEDİ:
-- O tablodaki satır, öğrenci görevi ÇÖZDÜĞÜNDE oluşuyor. Bilgi alma ise
-- çözmeden ÖNCE oluyor. Sütun eklemek için görevi çözülmemiş öğrenciye de
-- satır açmak gerekirdi; oysa projede "satır var mı" ve `kazanilan_puan = 0
-- AND sifirlanma_tarihi IS NOT NULL` sorguları ceza durumunu belirliyor.
-- Yarım kayıtlar o mantığı bulandırırdı. Ayrı tablo hiçbir şeye dokunmuyor.
--
-- ⚠️ NEDEN TARAYICIDA TUTULMADI?
-- localStorage'da tutulsaydı öğrenci bilgiyi alır, F5'e basar, ceza silinirdi.
-- Ceza sunucuda duruyor ve puanı yazan yer (v2_gorev_tamamla.php) buraya bakıyor.
--
-- ÇALIŞTIRMA: phpMyAdmin -> mblock_egitim -> SQL sekmesi -> yapıştır -> Git
-- Tekrar çalıştırılabilir (IF NOT EXISTS).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `v2_ipucu_alindi` (
  `id`          int(11)  NOT NULL AUTO_INCREMENT,
  `ogrenci_id`  int(11)  NOT NULL,
  `v2_gorev_id` int(11)  NOT NULL,
  `tarih`       datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  -- Aynı görevde ikinci kez "bilgi aldı" yazılmasın; ceza bir kez uygulanır
  UNIQUE KEY `uq_ogrenci_gorev` (`ogrenci_id`, `v2_gorev_id`),
  KEY `idx_gorev` (`v2_gorev_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------------
-- İPUCU RESİMLERİ İÇİN KLASÖR
-- (SQL değil, elle yapılacak — burada hatırlatma olarak duruyor)
--   mblock_platform/img/gorev_ipuclari/
-- Yoksa v2_gorev_sistemi/api/resim_yukle.php ilk yüklemede kendisi oluşturur.
-- ---------------------------------------------------------------------------


-- ---------------------------------------------------------------------------
-- KONTROL
-- ---------------------------------------------------------------------------
SELECT 'v2_ipucu_alindi tablosu' AS kontrol, COUNT(*) AS bulunan
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME   = 'v2_ipucu_alindi';
-- Beklenen: 1
