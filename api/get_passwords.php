<?php
/* ==========================================================
   Dosya Yolu: api/get_passwords.php
   İşlev: PDF tablosu için sınıfın şifre listesini getirir
   ========================================================== */
session_start();
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

$ogretmen_id = $_SESSION['ogretmen_id'] ?? 0;
$sinif_id = isset($_GET['sinif_id']) ? (int)$_GET['sinif_id'] : 0;

if (!$ogretmen_id || !$sinif_id) {
    echo json_encode(['success' => false]);
    exit;
}

$sorgu = $pdo->prepare("SELECT ad_soyad, kullanici_adi, sifre FROM ogrenciler WHERE ogretmen_id = ? AND sinif_id = ? ORDER BY ad_soyad ASC");
$sorgu->execute([$ogretmen_id, $sinif_id]);
$liste = $sorgu->fetchAll();

echo json_encode(['success' => true, 'data' => $liste]);
?>