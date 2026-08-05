<?php
session_set_cookie_params(0); // YENİ: Tarayıcı kapandığında oturumu yok et
session_start();
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

$veri = json_decode(file_get_contents("php://input"), true);
$kadi = trim($veri['kullanici_adi'] ?? '');
$sifre = trim($veri['sifre'] ?? '');

if (empty($kadi) || empty($sifre)) {
    echo json_encode(['success' => false, 'message' => 'Lütfen kullanıcı adı ve şifre girin.']); 
    exit;
}

$sorgu = $pdo->prepare("SELECT * FROM ogretmenler WHERE kullanici_adi = ?");
$sorgu->execute([$kadi]);
$ogretmen = $sorgu->fetch();

if ($ogretmen && $ogretmen['sifre'] === $sifre) {
    
    // 1. KONTROL: Eğer zaten onay bekliyorsa içeri alma
    if ($ogretmen['durum'] === 'onay_bekliyor') {
        echo json_encode(['success' => false, 'reason' => 'pending', 'message' => 'Hesabınız veya süre talebiniz şu an Süper Admin onayında bekliyor.']);
        exit;
    }

    // 2. KONTROL: Süresi dolmuş mu?
   if (!empty($ogretmen['bitis_tarihi'])) {
    $bitis_tarihi = new DateTime($ogretmen['bitis_tarihi']);
        $bugun = new DateTime();
        
        $bitis_tarihi->setTime(0,0,0);
        $bugun->setTime(0,0,0);

        if ($bugun > $bitis_tarihi) {
            // Süre bitmişse özel 'expired' kodu gönderiyoruz ki ana ekranda buton çıksın
            echo json_encode(['success' => false, 'reason' => 'expired', 'message' => 'Üyelik süreniz dolmuştur! Sisteme giriş yapamazsınız.']);
            exit;
        }
    }

    // Her şey yolundaysa içeri al
    $_SESSION['ogretmen_id'] = $ogretmen['id'];
    $_SESSION['ogretmen_ad'] = $ogretmen['ad_soyad'];
    $_SESSION['ogretmen_pin'] = $ogretmen['pin_kodu'];
    $_SESSION['okul_adi'] = $ogretmen['okul_adi'];

    echo json_encode(['success' => true, 'message' => 'Giriş başarılı! Yönlendiriliyorsunuz...']);
} else {
    echo json_encode(['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı!']);
}
?>