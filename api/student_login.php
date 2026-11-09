<?php
/* ==========================================================
   Dosya Yolu: api/student_login.php
   Kullanıcı: Giriş ekranındaki öğrenci (index.php)
   Halk dilinde: Sınıf + kullanıcı adı + şifre ile öğrenciyi içeri alır,
   haftalık can/enerji dolumunu yapar.

   YENİ EKLENEN ÖZELLİKLER:
   - [2026-11] KRİTİK: Oturuma artık 'ogretmen_id' YAZILMIYOR. Eskiden
     yazıldığı için öğretmen kapıcıları öğrenciyi öğretmen sanıyor, öğrenci
     öğretmen API'lerini (şifre listesi, öğrenci silme…) çağırabiliyordu.
     Öğrencinin öğretmeni gerekirse: $_SESSION['ogrencinin_ogretmen_id'].
   - [2026-11] girisRoluAyarla('ogrenci'): oturum kimliği yenilenir
     (session fixation), varsa öğretmen anahtarları silinir.
   - [2026-11] Kaba kuvvet kilidi: aynı hesaba 5 hatalı deneme → 5 dk;
     aynı IP'den 10 dakikada 80 hata → 10 dk (bütün sınıf tek IP'den çıkar).
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';

guvenliOturumBaslat();
header('Content-Type: application/json; charset=utf-8');

function girisCevap(bool $ok, string $mesaj): void
{
    echo json_encode(["success" => $ok, "message" => $mesaj], JSON_UNESCAPED_UNICODE);
    exit;
}

$veri      = istekVerisi();
$sinif_id  = (int) ($veri['sinif_id'] ?? 0);
$kullanici = trim((string) ($veri['kullanici_adi'] ?? ''));
$sifre     = (string) ($veri['sifre'] ?? '');

if ($sinif_id === 0 || $kullanici === '' || $sifre === '') {
    girisCevap(false, "Lütfen sınıfınızı seçin, kullanıcı adı ve şifrenizi girin.");
}

$hesapAnahtari = 'ogrenci|sinif:' . $sinif_id . '|' . mb_strtolower($kullanici);
$ipAnahtari    = 'ogrenci|ip:' . istemciIp();
girisDenemesiKontrol($hesapAnahtari, 5, 300);
girisDenemesiKontrol($ipAnahtari, 80, 600);

try {
    $sorgu = $pdo->prepare("SELECT id, ad_soyad, ogretmen_id, sinif_id, onay_durumu, son_dolum_tarihi FROM ogrenciler WHERE kullanici_adi = ? AND sifre = ? AND sinif_id = ?");
    $sorgu->execute([$kullanici, $sifre, $sinif_id]);
    $ogrenci = $sorgu->fetch();

    if (!$ogrenci) {
        girisDenemesiKaydet($hesapAnahtari, false, 5, 300);
        girisDenemesiKaydet($ipAnahtari, false, 80, 600);
        girisCevap(false, "Sınıf, kullanıcı adı veya şifre hatalı!");
    }

    girisDenemesiKaydet($hesapAnahtari, true);

    if (isset($ogrenci['onay_durumu']) && (int) $ogrenci['onay_durumu'] === 0) {
        girisCevap(false, "Öğretmeniniz tarafından onaylanması bekleniyor, lütfen öğretmeninizle iletişime geçiniz.");
    }

    girisRoluAyarla('ogrenci');
    $_SESSION['ogrenci_id']             = (int) $ogrenci['id'];
    $_SESSION['ogrenci_ad']             = $ogrenci['ad_soyad'];
    $_SESSION['ogrencinin_ogretmen_id'] = (int) $ogrenci['ogretmen_id'];
    $_SESSION['sinif_id']               = (int) $ogrenci['sinif_id'];

    // 1 HAFTALIK CAN VE ENERJİ DOLUMU
    if (empty($ogrenci['son_dolum_tarihi']) || strtotime($ogrenci['son_dolum_tarihi']) <= strtotime('-7 days')) {
        $pdo->prepare("UPDATE ogrenciler SET can = 100, enerji = 100, son_dolum_tarihi = NOW() WHERE id = ?")
            ->execute([$ogrenci['id']]);
    }

    girisCevap(true, "Giriş başarılı! Görev paneline yönlendiriliyorsunuz...");
} catch (PDOException $e) {
    error_log('[student_login] ' . $e->getMessage());
    girisCevap(false, "Veritabanı bağlantı hatası!");
}
