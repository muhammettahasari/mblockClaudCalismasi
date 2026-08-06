-- ============================================================================
-- DOSYA: kurulum/soru_havuzu_2026_08.sql
-- TARİH: 2026-08
--
-- NE İŞE YARAR? (HALK DİLİNDE):
-- Quiz Arena ve Düello'nun kullandığı soru havuzuna 5 soru ekler.
--
-- ⚠️ ÖNEMLİ — TEK HAVUZ, İKİ OYUN:
-- `quiz_sorulari` tablosu HEM quiz arenası HEM düello tarafından kullanılır.
--   Quiz  : öğretmenin seçtiği bölüm + zorluk kombinasyonuna göre çeker
--           (quiz_arena.php -> WHERE zorluk_seviyesi IN (...) AND bolum_id IN (...))
--   Düello: öğrencinin seviyesine göre çeker, zorluk AYRIMI YAPMAZ
--           (api/duello/yanitla.php -> WHERE b.sira_no <= mevcut_bolum)
-- Yani buraya eklenen her soru iki oyunda da görünür.
--
-- NEDEN 1. BÖLÜME AĞIRLIK VERİLDİ?
-- Düello sorguyu "öğrencinin bulunduğu bölüm ve altı" diye kısıtlıyor.
-- Sorular sadece üst bölümlerde olsaydı yeni başlayan öğrenciler düello
-- kabul ettiğinde "Havuzda seviyenize uygun soru bulunamadı" hatası alır ve
-- düello iptal olurdu. Bu yüzden çoğunluk en alt bölümde.
--
-- ÇALIŞTIRMA: phpMyAdmin -> mblock_egitim -> SQL sekmesi -> yapıştır -> Git
-- Tekrar çalıştırılabilir: aynı soru metni varsa yeniden eklenmez.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Hedef bölümler: sıra numarası en küçük iki bölüm
-- (Bölüm id'leri kurulumdan kuruluma değiştiği için sabit id YAZILMADI.)
-- ---------------------------------------------------------------------------
SET @bolum1 := (SELECT id FROM bolumler ORDER BY sira_no ASC, id ASC LIMIT 1);
SET @bolum2 := (SELECT id FROM bolumler ORDER BY sira_no ASC, id ASC LIMIT 1 OFFSET 1);

-- 2. bölüm yoksa hepsini 1. bölüme koy
SET @bolum2 := COALESCE(@bolum2, @bolum1);


-- ---------------------------------------------------------------------------
-- SORU 1 — Yeşil bayrak (Kolay, Bölüm 1)
-- ---------------------------------------------------------------------------
INSERT INTO quiz_sorulari
    (bolum_id, zorluk_seviyesi, sure, puan, soru_metni,
     cevap_a_metni, cevap_b_metni, cevap_c_metni, cevap_d_metni, dogru_cevap)
SELECT @bolum1, 'Kolay', 20, 20,
       'mBlock''ta bir projeyi çalıştırmak için hangi bloğun altına kod dizeriz?',
       'Yeşil bayrak tıklandığında',
       'Sonsuz tekrarla',
       '10 adım git',
       'Eğer ... ise',
       'A'
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM quiz_sorulari
        WHERE soru_metni = 'mBlock''ta bir projeyi çalıştırmak için hangi bloğun altına kod dizeriz?'
 );


-- ---------------------------------------------------------------------------
-- SORU 2 — Döngü kavramı (Kolay, Bölüm 1)
-- ---------------------------------------------------------------------------
INSERT INTO quiz_sorulari
    (bolum_id, zorluk_seviyesi, sure, puan, soru_metni,
     cevap_a_metni, cevap_b_metni, cevap_c_metni, cevap_d_metni, dogru_cevap)
SELECT @bolum1, 'Kolay', 20, 20,
       'Aynı komutu 4 kez yazmak yerine hangi bloğu kullanmak daha doğrudur?',
       'Bekle 1 saniye',
       '4 kez tekrarla',
       'Sesi çal',
       'Rastgele sayı',
       'B'
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM quiz_sorulari
        WHERE soru_metni = 'Aynı komutu 4 kez yazmak yerine hangi bloğu kullanmak daha doğrudur?'
 );


-- ---------------------------------------------------------------------------
-- SORU 3 — Açı / dönme (Kolay, Bölüm 1)
-- ---------------------------------------------------------------------------
INSERT INTO quiz_sorulari
    (bolum_id, zorluk_seviyesi, sure, puan, soru_metni,
     cevap_a_metni, cevap_b_metni, cevap_c_metni, cevap_d_metni, dogru_cevap)
SELECT @bolum1, 'Kolay', 25, 20,
       'Bir kuklanın kare çizmesi için her köşede kaç derece dönmesi gerekir?',
       '45 derece',
       '60 derece',
       '90 derece',
       '180 derece',
       'C'
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM quiz_sorulari
        WHERE soru_metni = 'Bir kuklanın kare çizmesi için her köşede kaç derece dönmesi gerekir?'
 );


-- ---------------------------------------------------------------------------
-- SORU 4 — Koordinat sistemi (Orta, Bölüm 1)
-- ---------------------------------------------------------------------------
INSERT INTO quiz_sorulari
    (bolum_id, zorluk_seviyesi, sure, puan, soru_metni,
     cevap_a_metni, cevap_b_metni, cevap_c_metni, cevap_d_metni, dogru_cevap)
SELECT @bolum1, 'Orta', 30, 30,
       'Sahnede kuklayı SAĞA doğru hareket ettirmek için hangi değeri artırmalıyız?',
       'x konumunu',
       'y konumunu',
       'boyutunu',
       'renk efektini',
       'A'
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM quiz_sorulari
        WHERE soru_metni = 'Sahnede kuklayı SAĞA doğru hareket ettirmek için hangi değeri artırmalıyız?'
 );


-- ---------------------------------------------------------------------------
-- SORU 5 — Koşul bloğu (Orta, Bölüm 2)
-- ---------------------------------------------------------------------------
INSERT INTO quiz_sorulari
    (bolum_id, zorluk_seviyesi, sure, puan, soru_metni,
     cevap_a_metni, cevap_b_metni, cevap_c_metni, cevap_d_metni, dogru_cevap)
SELECT @bolum2, 'Orta', 30, 30,
       '"Eğer ... ise" bloğu ne işe yarar?',
       'Kuklayı büyütür',
       'Bir koşul doğruysa içindeki kodu çalıştırır',
       'Programı sonsuza kadar tekrarlar',
       'Sahneyi temizler',
       'B'
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM quiz_sorulari
        WHERE soru_metni = '"Eğer ... ise" bloğu ne işe yarar?'
 );


-- ---------------------------------------------------------------------------
-- KONTROL — havuzun son durumu
-- ---------------------------------------------------------------------------
SELECT b.sira_no                AS bolum_sira,
       b.bolum_adi,
       q.zorluk_seviyesi,
       COUNT(*)                 AS soru_sayisi
  FROM quiz_sorulari q
  JOIN bolumler b ON b.id = q.bolum_id
 GROUP BY b.sira_no, b.bolum_adi, q.zorluk_seviyesi
 ORDER BY b.sira_no, q.zorluk_seviyesi;

SELECT COUNT(*) AS toplam_soru FROM quiz_sorulari;
