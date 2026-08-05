<?php
/* ==========================================================
   Dosya Yolu: api/teacher_register.php
   İşlev: Yeni öğretmen kaydını alır (İl, İlçe ve Süre Talebi dahil)
   ========================================================== */
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

$veri = json_decode(file_get_contents("php://input"), true);

if (!$veri) {
    echo json_encode(["success" => false, "message" => "Veri alınamadı."]);
    exit;
}

$ad = trim($veri['ad_soyad'] ?? '');
$email = trim($veri['email'] ?? '');
$il = trim($veri['il'] ?? '');
$ilce = trim($veri['ilce'] ?? '');
$okul = trim($veri['okul_adi'] ?? '');
$kullanici = trim($veri['kullanici_adi'] ?? '');
$sifre = trim($veri['sifre'] ?? ''); 
$tel = trim($veri['telefon'] ?? '');
$kontenjan = (int)($veri['kontenjan'] ?? 0);
$talep_ay = (int)($veri['talep_ay'] ?? 1);

if (empty($ad) || empty($email) || empty($il) || empty($ilce) || empty($kullanici) || empty($sifre)) {
        echo json_encode(["success" => false, "message" => "Lütfen zorunlu alanları (Ad, E-Posta, İl, İlçe, Kullanıcı Adı, Şifre) doldurun."]);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Lütfen geçerli bir e-posta adresi girin."]);
        exit;
    }
// Kullanıcı adı daha önce alınmış mı kontrolü (Tüm Türkiye'de benzersiz olmalı)
$kontrol = $pdo->prepare("SELECT id FROM ogretmenler WHERE kullanici_adi = ?");
$kontrol->execute([$kullanici]);
if ($kontrol->rowCount() > 0) {
    echo json_encode(["success" => false, "message" => "Bu kullanıcı adı daha önce sisteme kayıt olmuş, lütfen farklı bir kullanıcı adı deneyiniz."]);
    exit;
}

try {
    // Veritabanına yeni bilgileri ve talep edilen ayı da ekleyerek kaydediyoruz
  $sorgu = $pdo->prepare("INSERT INTO ogretmenler (ad_soyad, email, il, ilce, kullanici_adi, sifre, okul_adi, telefon, kontenjan, durum, talep_edilen_ay) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'onay_bekliyor', ?)");
        $sorgu->execute([$ad, $email, $il, $ilce, $kullanici, $sifre, $okul, $tel, $kontenjan, $talep_ay]);
        // YENİ EKLENEN KOD BİTİŞİ
    
    echo json_encode(["success" => true, "message" => "Kayıt başarılı!"]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Kayıt sırasında bir hata oluştu."]);
}
?>