-- ============================================================================
-- DOSYA: kurulum/migration_2026_08_kilit_mesajlari.sql
-- TARİH: 2026-08
--
-- NE İŞE YARAR? (HALK DİLİNDE):
-- Öğretmen sınıfı kilitlerken öğrencilere bir not bırakıyor ("Tahtaya bakın").
-- Aynı notu her seferinde elle yazmasın diye kendi hazır mesajlarını
-- kaydedebilsin. Bu tablo o kayıtlı mesajları tutar.
--
-- HAZIR MESAJLAR NEDEN BURADA DEĞİL?
-- "Tahtaya bakın", "Teneffüs", "Kısa bir mola" gibi HERKESE gelen mesajlar
-- koda gömülü (bilesenler/kilit_modali.php). Onları tabloya yazsaydık her yeni
-- öğretmen için kopyalamak gerekirdi ve metni düzeltmek 500 satır güncellemek
-- olurdu. Bu tabloda SADECE öğretmenin kendi yazdıkları durur.
--
-- NEDEN localStorage DEĞİL?
-- Öğretmen okulda başka bilgisayara oturursa mesajları kaybolurdu. Tarayıcı
-- temizliği de siler. Veritabanında durursa öğretmeni takip eder.
--
-- ÇALIŞTIRMA: phpMyAdmin -> mblock_egitim -> SQL sekmesi -> yapıştır -> Git
-- Tekrar çalıştırılabilir (IF NOT EXISTS).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `kilit_mesajlari` (
  `id`              int(11)      NOT NULL AUTO_INCREMENT,
  `ogretmen_id`     int(11)      NOT NULL
      COMMENT 'Mesaj kime ait — başka öğretmen göremez',
  `mesaj`           varchar(160) NOT NULL
      COMMENT 'Öğrencinin kilit ekranında görünecek metin',
  `kullanim_sayisi` int(11)      NOT NULL DEFAULT 0
      COMMENT 'Kaç kez kullanıldı — çok kullanılan üstte listelenir',
  `son_kullanim`    datetime              DEFAULT NULL,
  `olusturma`       datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  -- Aynı öğretmen aynı metni iki kez kaydedemesin (liste şişmesin)
  UNIQUE KEY `uq_ogretmen_mesaj` (`ogretmen_id`, `mesaj`),
  -- "Bu öğretmenin mesajları, çok kullanılan önce" sorgusu için
  KEY `idx_ogretmen_sira` (`ogretmen_id`, `kullanim_sayisi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------------
-- KONTROL
-- ---------------------------------------------------------------------------
SELECT 'kilit_mesajlari tablosu' AS kontrol, COUNT(*) AS bulunan
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME   = 'kilit_mesajlari';
-- Beklenen: 1
