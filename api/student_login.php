<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

$veri = json_decode(file_get_contents("php://input"), true);
$sinif_id = $veri['sinif_id'] ?? '';
$kullanici = $veri['kullanici_adi'] ?? '';
$sifre = $veri['sifre'] ?? '';

if(empty($sinif_id) || empty($kullanici) || empty($sifre)) {
    echo json_encode(["success" => false, "message" => "Lütfen sınıfınızı seçin, kullanıcı adı ve şifrenizi girin."]);
    exit;
}

try {
    // YENİ: onay_durumu da veritabanından çekiliyor.
    $sorgu = $pdo->prepare("SELECT id, ad_soyad, ogretmen_id, sinif_id, onay_durumu FROM ogrenciler WHERE kullanici_adi = ? AND sifre = ? AND sinif_id = ?");
    $sorgu->execute([$kullanici, $sifre, $sinif_id]);
    $ogrenci = $sorgu->fetch();

    if ($ogrenci) {
        
        // YENİ: ONAY KONTROLÜ BURADA YAPILIYOR
        if (isset($ogrenci['onay_durumu']) && $ogrenci['onay_durumu'] == 0) {
            echo json_encode(["success" => false, "message" => "Öğretmeniniz tarafından onaylanması bekleniyor, lütfen öğretmeninizle iletişime geçiniz."]);
            exit;
        }

        $_SESSION['ogrenci_id'] = $ogrenci['id'];
        $_SESSION['ogrenci_ad'] = $ogrenci['ad_soyad'];
        $_SESSION['ogretmen_id'] = $ogrenci['ogretmen_id'];
        $_SESSION['sinif_id'] = $ogrenci['sinif_id'];

        // YENİ: 1 HAFTALIK CAN VE ENERJİ DOLUM KONTROLÜ
        // Öğrencinin son dolum tarihini çek
        $zaman_kontrol = $pdo->prepare("SELECT son_dolum_tarihi FROM ogrenciler WHERE id = ?");
        $zaman_kontrol->execute([$ogrenci['id']]);
        $zaman_verisi = $zaman_kontrol->fetch();
        
        // Eğer 7 gün (168 Saat) geçmişse veya tarih boşsa, Can ve Enerjiyi 100 yap!
        if (empty($zaman_verisi['son_dolum_tarihi']) || strtotime($zaman_verisi['son_dolum_tarihi']) <= strtotime('-7 days')) {
            $doldur = $pdo->prepare("UPDATE ogrenciler SET can = 100, enerji = 100, son_dolum_tarihi = NOW() WHERE id = ?");
            $doldur->execute([$ogrenci['id']]);
        }

        echo json_encode(["success" => true, "message" => "Giriş başarılı! Görev paneline yönlendiriliyorsunuz..."]);
    } else {
        echo json_encode(["success" => false, "message" => "Sınıf, kullanıcı adı veya şifre hatalı!"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Veritabanı bağlantı hatası!"]);
}
?>