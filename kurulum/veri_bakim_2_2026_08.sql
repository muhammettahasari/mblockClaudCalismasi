-- ============================================================================
--  mBlock Akademi — VERİ BAKIMI 2. TUR
--  Tarih: 2026-08
--
--  1. tur (veri_bakim_2026_08.sql) başarıyla uygulandı:
--       cevap anahtarı boş görev  2 -> 0
--       sahipsiz öğrenci          4 -> 0
--       eski quiz oturumu        37 -> 4
--
--  Bu dosya kalan 2 veri sorununu kapatır. Şema değişikliği YOK.
--
--  ÇALIŞTIRMADAN ÖNCE YEDEK AL (phpMyAdmin > Dışa Aktar)
-- ============================================================================


-- ============================================================================
-- 1. EKSİK AVATAR SATIRLARINI OLUŞTUR
-- ----------------------------------------------------------------------------
-- SORUN: 49 öğrenci var ama ogrenci_avatar_durum'da sadece 13 satır.
--        Bu satır eskiden yalnızca öğrenci profil sayfasını AÇTIĞINDA
--        oluşuyordu. Satırı olmayan öğrencide:
--          - Arena "günde en fazla 3 savunma" kalkanı çalışmıyor
--          - Savaş gücü önbelleği tutulamıyor
--
-- KOD TARAFI ARTIK DÜZELDİ: öğrenci kaydında satır otomatik açılıyor
--   (api/student_register.php, teacher_add_student.php, teacher_bulk_add.php)
-- Bu sorgu ise MEVCUT öğrenciler için eksikleri tamamlar.
--
-- ALTERNATİF: Süper Admin > 🔧 Bakım İşlemleri > "Güçleri Yeniden Hesapla"
--             butonu da aynı işi yapar ve ayrıca güçleri hesaplar.
--             (Buton önerilir — bu SQL sadece satırı açar, gücü hesaplamaz.)
-- ============================================================================

-- Önce gör (kaç öğrencinin satırı eksik?):
SELECT COUNT(*) AS eksik_avatar_satiri
  FROM ogrenciler o
  LEFT JOIN ogrenci_avatar_durum a ON a.ogrenci_id = o.id
 WHERE a.ogrenci_id IS NULL;

-- Sonra oluştur:
INSERT IGNORE INTO ogrenci_avatar_durum (ogrenci_id)
SELECT o.id
  FROM ogrenciler o
  LEFT JOIN ogrenci_avatar_durum a ON a.ogrenci_id = o.id
 WHERE a.ogrenci_id IS NULL;

-- Kontrol: iki sayı eşit olmalı
SELECT (SELECT COUNT(*) FROM ogrenciler)            AS ogrenci_sayisi,
       (SELECT COUNT(*) FROM ogrenci_avatar_durum)  AS avatar_satiri;


-- ============================================================================
-- 2. YETİM QUIZ SAVAŞ LOGLARINI TEMİZLE
-- ----------------------------------------------------------------------------
-- SORUN: 1. turda 33 eski quiz oturumu silindi. quiz_ogrenci satırları
--        foreign key CASCADE ile otomatik gitti, AMA quiz_savas_log tablosunun
--        oturum_id sütununda foreign key yok — bu yüzden 32 satır artık
--        var olmayan oturumlara işaret ediyor.
--        Zararsız ama gereksiz; ayrıca öğretmen raporlarını şişirir.
-- ============================================================================

-- Önce gör:
SELECT COUNT(*) AS yetim_savas_logu
  FROM quiz_savas_log l
  LEFT JOIN quiz_oturum q ON q.id = l.oturum_id
 WHERE q.id IS NULL;

-- Sonra sil:
DELETE l FROM quiz_savas_log l
  LEFT JOIN quiz_oturum q ON q.id = l.oturum_id
 WHERE q.id IS NULL;

-- Aynı sorun düello kayıtlarında da olabilir (bitmiş, çok eski maçlar):
SELECT COUNT(*) AS eski_bitmis_duello
  FROM quiz_duellolar
 WHERE durum IN ('bitti', 'reddedildi')
   AND olusturma_tarihi < NOW() - INTERVAL 30 DAY;

DELETE FROM quiz_duellolar
 WHERE durum IN ('bitti', 'reddedildi')
   AND olusturma_tarihi < NOW() - INTERVAL 30 DAY;


-- ============================================================================
-- 3. BÖLÜM 1'DEKİ 36 GÖREVİ GÖZDEN GEÇİR  (SİLMEZ — sadece listeler)
-- ----------------------------------------------------------------------------
-- Öğrencinin bölüm 2'ye geçebilmesi için bölüm 1'deki TÜM yayındaki
-- görevleri çözmesi gerekiyor. Şu an 36 yayında görev var; çoğu
-- geliştirme sırasında denenen test görevleri olabilir.
--
-- Aşağıdaki liste hangilerinin gerçek görev olduğunu ayırmana yardım eder.
-- Gereksizleri v2 Görev Listesi'nden ⏸️ butonuyla taslağa çek.
-- ============================================================================

SELECT id,
       sira_no,
       gorev_adi,
       puan,
       yayinda,
       CHAR_LENGTH(icerik_json) AS icerik_boyutu,
       CHAR_LENGTH(JSON_UNQUOTE(JSON_EXTRACT(icerik_json, '$.blueprint.ana_cozum'))) AS cevap_anahtari_boyutu,
       (SELECT COUNT(*) FROM v2_ogrenci_ilerleme i
         WHERE i.v2_gorev_id = v2_gorevler.id AND i.kazanilan_puan > 0) AS cozen_ogrenci,
       DATE(olusturulma_tarihi) AS olusturma
  FROM v2_gorevler
 WHERE bolum_id = 1
 ORDER BY sira_no, id;

-- Toplu taslağa çekmek istersen (ÖRNEK — id'leri kendin belirle):
-- UPDATE v2_gorevler SET yayinda = 0 WHERE id IN (3,4,5,6,7);


-- ============================================================================
--  BİTTİ. Sonra Süper Admin > 🩺 Veritabanı Raporu'nu tekrar aç:
--    - "Güç önbelleği hesaplanmamış öğrenci" sayısını görmek için
--      🔧 Bakım İşlemleri > "Güçleri Yeniden Hesapla" butonuna bas.
-- ============================================================================
