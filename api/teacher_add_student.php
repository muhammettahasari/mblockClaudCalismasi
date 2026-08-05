<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

$ogretmen_id = $_SESSION['ogretmen_id'] ?? 0;
if (!$ogretmen_id) { echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.']); exit; }

$veri = json_decode(file_get_contents("php://input"), true);
$sinif_id = (int)($veri['sinif_id'] ?? 0);
$orj_ad = trim($veri['ad_soyad'] ?? '');
$orj_kadi = trim($veri['kullanici_adi'] ?? '');
$sifre = trim($veri['sifre'] ?? '');

if (!$sinif_id || empty($orj_ad) || empty($orj_kadi) || empty($sifre)) {
    echo json_encode(['success' => false, 'message' => 'Tüm alanları doldurunuz.']); exit;
}

$ad = $orj_ad;
$kadi = $orj_kadi;
$modifiye_edildi = false;

// İsim Çakışması Kontrolü
$sayac = 2;
$isim_kontrol = $pdo->prepare("SELECT id FROM ogrenciler WHERE ad_soyad = ? AND sinif_id = ?");
while(true) {
    $isim_kontrol->execute([$ad, $sinif_id]);
    if ($isim_kontrol->rowCount() == 0) break;
    $ad = $orj_ad . " " . $sayac;
    $sayac++;
    $modifiye_edildi = true;
}


// Kullanıcı Adı Çakışması Kontrolü
$sayac = 2;
// YENİ: Sadece bu öğretmenin okulunda ara
$kadi_kontrol = $pdo->prepare("SELECT id FROM ogrenciler WHERE kullanici_adi = ? AND ogretmen_id = ?");
while(true) {
    $kadi_kontrol->execute([$kadi, $ogretmen_id]);
    if ($kadi_kontrol->rowCount() == 0) break;
    $kadi = $orj_kadi . $sayac;
    $sayac++;
    $modifiye_edildi = true;
}
try {
    // YENİ: Öğretmen eklediği için onay_durumu direkt 1 (Onaylı) olarak kaydediliyor.
    $sorgu = $pdo->prepare("INSERT INTO ogrenciler (ad_soyad, kullanici_adi, sifre, ogretmen_id, sinif_id, durum, onay_durumu) VALUES (?, ?, ?, ?, ?, 'aktif', 1)");
    $sorgu->execute([$ad, $kadi, $sifre, $ogretmen_id, $sinif_id]);

    /* [2026-08] Avatar satırını KAYIT ANINDA aç.
       Eskiden bu satır sadece öğrenci profil sayfasını açtığında oluşuyordu;
       hiç profiline girmemiş öğrencide arena günlük savunma kalkanı çalışmıyordu. */
    $pdo->prepare("INSERT IGNORE INTO ogrenci_avatar_durum (ogrenci_id) VALUES (?)")
        ->execute([(int) $pdo->lastInsertId()]);

    $mesaj = "Öğrenci başarıyla eklendi ve otomatik onaylandı!";
    if($modifiye_edildi) $mesaj .= " (İsim veya kullanıcı adı çakıştığı için sonuna rakam eklendi).";
    
    echo json_encode(['success' => true, 'message' => $mesaj]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası.']);
}
?>