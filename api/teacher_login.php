<?php
/* ==========================================================
   Dosya Yolu: api/teacher_login.php
   Kullanıcı: Giriş ekranındaki öğretmen (index.php)
   Halk dilinde: Öğretmeni kullanıcı adı + şifre ile içeri alır; onay
   bekleyen ve süresi dolan hesapları durdurur.

   YENİ EKLENEN ÖZELLİKLER:
   - [2026-11] girisRoluAyarla('ogretmen'): oturum kimliği yenilenir, öğretmen
     rol damgası basılır, varsa öğrenci anahtarları silinir.
   - [2026-11] Kaba kuvvet kilidi: aynı kullanıcı adına 5 hatalı deneme →
     10 dk; aynı IP'den 10 dakikada 30 hata → 10 dk.
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';

guvenliOturumBaslat();
header('Content-Type: application/json; charset=utf-8');

$veri = istekVerisi();
$kadi = trim((string) ($veri['kullanici_adi'] ?? ''));
$sifre = trim((string) ($veri['sifre'] ?? ''));

if ($kadi === '' || $sifre === '') {
    echo json_encode(['success' => false, 'message' => 'Lütfen kullanıcı adı ve şifre girin.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$hesapAnahtari = 'ogretmen|' . mb_strtolower($kadi);
$ipAnahtari    = 'ogretmen|ip:' . istemciIp();
girisDenemesiKontrol($hesapAnahtari, 5, 600);
girisDenemesiKontrol($ipAnahtari, 30, 600);

$sorgu = $pdo->prepare("SELECT * FROM ogretmenler WHERE kullanici_adi = ?");
$sorgu->execute([$kadi]);
$ogretmen = $sorgu->fetch();

if ($ogretmen && hash_equals((string) $ogretmen['sifre'], $sifre)) {
    girisDenemesiKaydet($hesapAnahtari, true);
    
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
            // [2026-11] Şifresi doğrulandı ama süresi bitmiş: süre talebi sadece bu oturumdan kabul edilir
            $_SESSION['_suresi_dolan_ogretmen_id'] = (int) $ogretmen['id'];
            $_SESSION['_suresi_dolan_zaman']       = time();
            // Süre bitmişse özel 'expired' kodu gönderiyoruz ki ana ekranda buton çıksın
            echo json_encode(['success' => false, 'reason' => 'expired', 'message' => 'Üyelik süreniz dolmuştur! Sisteme giriş yapamazsınız.']);
            exit;
        }
    }

    // Her şey yolundaysa içeri al
    girisRoluAyarla('ogretmen');
    $_SESSION['ogretmen_id'] = (int) $ogretmen['id'];
    $_SESSION['ogretmen_ad'] = $ogretmen['ad_soyad'];
    $_SESSION['ogretmen_pin'] = $ogretmen['pin_kodu'];
    $_SESSION['okul_adi'] = $ogretmen['okul_adi'];

    echo json_encode(['success' => true, 'message' => 'Giriş başarılı! Yönlendiriliyorsunuz...']);
} else {
    girisDenemesiKaydet($hesapAnahtari, false, 5, 600);
    girisDenemesiKaydet($ipAnahtari, false, 30, 600);
    echo json_encode(['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı!'], JSON_UNESCAPED_UNICODE);
}
?>