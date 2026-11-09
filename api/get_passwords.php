<?php
/* ==========================================================
   Dosya Yolu: api/get_passwords.php
   Kullanıcı: Öğretmen (ogrenci_yonetimi.php → şifre listesi yazdırma)
   Halk dilinde: Sınıfın kullanıcı adı / şifre listesini getirir.

   YENİ EKLENEN ÖZELLİKLER:
   - [2026-11] Kapıcı ogretmenZorunluApi(). Eskiden öğrenci oturumu da
     'ogretmen_id' taşıdığı için öğrenci sınıfının bütün şifrelerini
     çekebiliyordu.
   - [2026-11] Sınıf sahipliği siniflar tablosundan doğrulanır
     (ogrenciler.ogretmen_id bayat kopyasına bakılmaz).
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';

$ogretmen_id = ogretmenZorunluApi();
header('Content-Type: application/json; charset=utf-8');

$sinif_id = (int) ($_GET['sinif_id'] ?? 0);
if ($sinif_id <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

$sorgu = $pdo->prepare(
    "SELECT o.ad_soyad, o.kullanici_adi, o.sifre
       FROM ogrenciler o
       JOIN siniflar s ON s.id = o.sinif_id
      WHERE s.id = ? AND s.ogretmen_id = ?
      ORDER BY o.ad_soyad ASC"
);
$sorgu->execute([$sinif_id, $ogretmen_id]);

echo json_encode(['success' => true, 'data' => $sorgu->fetchAll()], JSON_UNESCAPED_UNICODE);
