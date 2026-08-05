<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

$veri = json_decode(file_get_contents("php://input"), true);

$ad = $veri['ad_soyad'] ?? '';
$kullanici = $veri['kullanici_adi'] ?? '';
$sifre = $veri['sifre'] ?? '';
$ogretmen_id = (int)($veri['ogretmen_id'] ?? 0);
$sinif_id = (int)($veri['sinif_id'] ?? 0);

if (empty($ad) || empty($kullanici) || empty($sifre) || $ogretmen_id === 0 || $sinif_id === 0) {
    echo json_encode(["success" => false, "message" => "Lütfen tüm alanları doldurun ve sınıfınızı seçin."]);
    exit;
}

$kontrol = $pdo->prepare("SELECT id FROM ogrenciler WHERE kullanici_adi = ? AND ogretmen_id = ?");
$kontrol->execute([$kullanici, $ogretmen_id]);
if ($kontrol->rowCount() > 0) {
    echo json_encode(["success" => false, "message" => "Bu kullanıcı adı zaten alınmış. Lütfen başka bir ad deneyin."]);
    exit;
}

try {
     
 // YENİ: Öğrenci RPG (Oyun) değerleriyle birlikte kaydediliyor.
    $sorgu = $pdo->prepare("INSERT INTO ogrenciler (ad_soyad, kullanici_adi, sifre, ogretmen_id, sinif_id, kalici_puan, anlik_puan, durum, onay_durumu, elmas, can, enerji, son_dolum_tarihi) VALUES (?, ?, ?, ?, ?, 0, 0, 'aktif', 0, 0, 100, 100, NOW())");
    $sorgu->execute([$ad, $kullanici, $sifre, $ogretmen_id, $sinif_id]);

    /* [2026-08] Avatar satırını KAYIT ANINDA aç.
       Eskiden bu satır sadece öğrenci profil sayfasını ilk açtığında
       oluşuyordu. Profiline hiç girmemiş öğrencide arena "günde en fazla
       3 savunma" kalkanı sessizce çalışmıyordu. */
    $pdo->prepare("INSERT IGNORE INTO ogrenci_avatar_durum (ogrenci_id) VALUES (?)")
        ->execute([(int) $pdo->lastInsertId()]);

    echo json_encode(["success" => true, "message" => "Kaydın alındı! Öğretmenin onayladıktan sonra sisteme giriş yapabilirsin."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Kayıt sırasında bir hata oluştu."]);
}
?>