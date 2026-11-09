<?php
/* ==========================================================
   Dosya Yolu: api/verify_pin.php
   Kullanıcı: Giriş/kayıt ekranındaki öğrenci (index.php)
   Halk dilinde: Öğrencinin yazdığı 4 haneli PIN'e ait öğretmeni ve
   sınıflarını bulur.

   YENİ EKLENEN ÖZELLİKLER:
   - [2026-11] Kaba kuvvet kilidi: aynı IP'den 10 dakikada 20 hatalı PIN
     girilirse 10 dakika durur. (PIN 4 haneli; kilit olmadan 10.000 istekte
     bütün öğretmenler/okullar/sınıflar listelenebiliyordu.) Sınır cömert
     tutuldu çünkü bütün sınıf tek IP'den çıkar.
   - [2026-11] Doğrulanan öğretmen oturuma yazılır; student_register.php
     öğretmen kimliğini istekten DEĞİL buradan alır.
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

guvenliOturumBaslat();
header('Content-Type: application/json; charset=utf-8');

const PIN_MAX_HATA   = 20;
const PIN_KILIT_SN   = 600;

$veri = istekVerisi();
$pin  = trim((string) ($veri['pin'] ?? ''));

if ($pin === '') {
    echo json_encode(["success" => false, "message" => "Lütfen PIN kodunu girin."], JSON_UNESCAPED_UNICODE);
    exit;
}

$ipAnahtari = 'pin|ip:' . istemciIp();
girisDenemesiKontrol($ipAnahtari, PIN_MAX_HATA, PIN_KILIT_SN);

try {
    $sorgu = $pdo->prepare("SELECT id, ad_soyad, okul_adi FROM ogretmenler WHERE pin_kodu = ? AND durum = 'onaylandi'");
    $sorgu->execute([$pin]);
    $ogretmen = $sorgu->fetch();

    if (!$ogretmen) {
        girisDenemesiKaydet($ipAnahtari, false, PIN_MAX_HATA, PIN_KILIT_SN);
        echo json_encode(["success" => false, "message" => "Geçersiz veya süresi dolmuş bir PIN kodu girdiniz!"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ogretmen_id = (int) $ogretmen['id'];

    $sinifSorgu = $pdo->prepare("SELECT id, sinif_adi FROM siniflar WHERE ogretmen_id = ? ORDER BY sinif_adi ASC");
    $sinifSorgu->execute([$ogretmen_id]);
    $siniflar = $sinifSorgu->fetchAll();

    // Kayıt adımı için: bu oturum bu öğretmenin PIN'ini gerçekten bildi
    $_SESSION['_pin_ogretmen_id'] = $ogretmen_id;
    $_SESSION['_pin_zamani']      = time();

    echo json_encode([
        "success"      => true,
        "ogretmen_id"  => $ogretmen_id,
        "ogretmen_ad"  => $ogretmen['ad_soyad'],
        "okul_adi"     => $ogretmen['okul_adi'],
        "siniflar"     => $siniflar
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('[verify_pin] ' . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Veritabanı bağlantı hatası!"], JSON_UNESCAPED_UNICODE);
}
