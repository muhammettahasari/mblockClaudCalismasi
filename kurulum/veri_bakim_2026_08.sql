-- ============================================================================
--  mBlock Akademi — VERİ BAKIMI (şema değişikliği YOK)
--  Tarih: 2026-08
--
--  DURUM: Şema (tablolar, sütunlar, foreign key'ler, indeksler) EKSİKSİZ.
--         migration_2026_08_guvenlik.sql başarıyla uygulanmış.
--         Bu dosya sadece VERİ tarafındaki 3 sorunu düzeltir.
--
--  ÇALIŞTIRMADAN ÖNCE YEDEK AL:
--      phpMyAdmin > mblock_egitim > Dışa Aktar
--
--  Her bölüm bağımsızdır — istemediğini çalıştırma.
-- ============================================================================


-- ============================================================================
-- 1. CEVAP ANAHTARI OLMAYAN v2 GÖREVLERİNİ YAYINDAN KALDIR
-- ----------------------------------------------------------------------------
-- SORUN: 2 görevin (id 1 ve 2) Blueprint'i (ana_cozum) boş.
--        Öğrenci bu görevi açıp "Görevimi Kontrol Et" dediğinde
--        "Bu görevin cevap anahtarı henüz tanımlanmamış" hatası alır ve
--        bölümü asla bitiremez — çünkü bölüm ilerlemesi TÜM yayındaki
--        görevlerin çözülmesini bekler.
--
-- ÇÖZÜM: Cevap anahtarı boş olanları taslağa çek. Tasarlayıcıdan
--        anahtarı kurduğunda tekrar yayına alırsın.
-- ============================================================================

-- Önce gör (hangi görevler etkilenecek?):
SELECT id, gorev_adi, bolum_id, sira_no, yayinda
  FROM v2_gorevler
 WHERE JSON_EXTRACT(icerik_json, '$.blueprint.ana_cozum') IS NULL
    OR TRIM(JSON_UNQUOTE(JSON_EXTRACT(icerik_json, '$.blueprint.ana_cozum'))) = ''
    OR JSON_UNQUOTE(JSON_EXTRACT(icerik_json, '$.blueprint.ana_cozum')) = 'null';

-- Sonra uygula:
UPDATE v2_gorevler
   SET yayinda = 0
 WHERE JSON_EXTRACT(icerik_json, '$.blueprint.ana_cozum') IS NULL
    OR TRIM(JSON_UNQUOTE(JSON_EXTRACT(icerik_json, '$.blueprint.ana_cozum'))) = ''
    OR JSON_UNQUOTE(JSON_EXTRACT(icerik_json, '$.blueprint.ana_cozum')) = 'null';


-- ============================================================================
-- 2. SAHİPSİZ (ÖĞRETMENSİZ + SINIFSIZ) ÖĞRENCİLERİ TEMİZLE
-- ----------------------------------------------------------------------------
-- SORUN: 4 öğrencinin (Test Öğrenci, Ahmet K., Zeynep Y., Ali Rıza)
--        ogretmen_id ve sinif_id değerleri NULL.
--        Bu öğrenciler:
--          - Giriş yapamaz (giriş PIN + sınıf gerektiriyor)
--          - Quiz/düello/arena hiçbirine katılamaz
--          - AMA "Türkiye Sıralaması" tablosunda görünüp sıralamayı bozuyorlar
--        Bunlar ilk kurulumdan kalma örnek kayıtlar.
--
-- NOT: Foreign key'ler ON DELETE CASCADE olduğu için envanter, avatar,
--      savaş kayıtları da otomatik temizlenir.
-- ============================================================================

-- Önce gör:
SELECT id, ad_soyad, kullanici_adi, kalici_puan, elmas
  FROM ogrenciler
 WHERE ogretmen_id IS NULL AND sinif_id IS NULL;

-- Sonra sil (emin değilsen bu satırı çalıştırma):
DELETE FROM ogrenciler
 WHERE ogretmen_id IS NULL AND sinif_id IS NULL;


-- ============================================================================
-- 3. BİTMİŞ QUIZ OTURUMLARINI ARŞİVLE (isteğe bağlı, performans)
-- ----------------------------------------------------------------------------
-- 35 adet 'bitti' durumunda oturum var. Bunlar quiz_ogrenci ve
-- quiz_savas_log satırlarını da tutuyor. 30 günden eskileri silmek
-- veritabanını hafifletir. Öğrencilerin KALICI PUANI etkilenmez —
-- puanlar zaten savaş bitiminde ogrenciler tablosuna aktarıldı.
-- ============================================================================

-- Önce gör:
SELECT COUNT(*) AS silinecek_oturum
  FROM quiz_oturum
 WHERE durum = 'bitti'
   AND COALESCE(bitis_zaman, baslangic_zaman) < NOW() - INTERVAL 30 DAY;

-- Sonra sil (quiz_ogrenci ve quiz_savas_log CASCADE ile gider):
DELETE FROM quiz_oturum
 WHERE durum = 'bitti'
   AND COALESCE(bitis_zaman, baslangic_zaman) < NOW() - INTERVAL 30 DAY;


-- ============================================================================
--  KONTROL SORGULARI (hiçbir şeyi değiştirmez — durum raporu)
-- ============================================================================

-- Quiz soru havuzu kapsamı: hangi bölümde, hangi zorlukta kaç soru var?
SELECT b.sira_no, b.bolum_adi, q.zorluk_seviyesi, COUNT(*) AS adet
  FROM quiz_sorulari q
  JOIN bolumler b ON b.id = q.bolum_id
 GROUP BY b.id, q.zorluk_seviyesi
 ORDER BY b.sira_no, q.zorluk_seviyesi;

-- v2 görevlerinin bölümlere dağılımı
SELECT b.sira_no, b.bolum_adi,
       COUNT(v.id) AS toplam_gorev,
       SUM(v.yayinda = 1) AS yayinda,
       SUM(v.yayinda = 0) AS taslak
  FROM bolumler b
  LEFT JOIN v2_gorevler v ON v.bolum_id = b.id
 GROUP BY b.id
 ORDER BY b.sira_no;

-- Öğrencilerin bölüm dağılımı (kimse takılıp kalmış mı?)
SELECT mevcut_bolum, COUNT(*) AS ogrenci_sayisi
  FROM ogrenciler
 GROUP BY mevcut_bolum
 ORDER BY mevcut_bolum;

-- Öğretmen lisans durumu
SELECT id, kullanici_adi, okul_adi, pin_kodu, durum,
       DATE(bitis_tarihi) AS lisans_bitis,
       DATEDIFF(bitis_tarihi, NOW()) AS kalan_gun
  FROM ogretmenler
 ORDER BY bitis_tarihi;
