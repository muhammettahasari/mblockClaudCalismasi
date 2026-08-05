<?php
/* ==========================================================
   Dosya Yolu: api/verify_pin.php
   İşlev: Girilen PIN koduna ait öğretmeni ve sınıflarını bulur
   ========================================================== */
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

$veri = json_decode(file_get_contents("php://input"), true);
$pin = $veri['pin'] ?? '';

if(empty($pin)) {
    echo json_encode(["success" => false, "message" => "Lütfen PIN kodunu girin."]);
    exit;
}

try {
    // 1. PIN koduna sahip onaylı öğretmeni bul
    $sorgu = $pdo->prepare("SELECT id, ad_soyad, okul_adi FROM ogretmenler WHERE pin_kodu = ? AND durum = 'onaylandi'");
    $sorgu->execute([$pin]);
    $ogretmen = $sorgu->fetch();

    if ($ogretmen) {
        $ogretmen_id = $ogretmen['id'];
        
        // 2. Bu öğretmenin oluşturduğu sınıfları bul
        $sinifSorgu = $pdo->prepare("SELECT id, sinif_adi FROM siniflar WHERE ogretmen_id = ? ORDER BY sinif_adi ASC");
        $sinifSorgu->execute([$ogretmen_id]);
        $siniflar = $sinifSorgu->fetchAll();

        echo json_encode([
            "success" => true, 
            "ogretmen_id" => $ogretmen_id,
            "ogretmen_ad" => $ogretmen['ad_soyad'],
            "okul_adi" => $ogretmen['okul_adi'],
            "siniflar" => $siniflar
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Geçersiz veya süresi dolmuş bir PIN kodu girdiniz!"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Veritabanı bağlantı hatası!"]);
}
?>