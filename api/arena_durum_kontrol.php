<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

$ogrenci_id = $_SESSION['ogrenci_id'] ?? null;

// Sadece giriş yapmış öğrenciler bu veriyi çekebilir
if (!$ogrenci_id) {
    echo json_encode(['success' => false]);
    exit;
}

try {
    // Öğrencinin öğretmenini bul ve arena saatini kontrol et
    $stmt = $pdo->prepare("SELECT ogr.arena_bitis FROM ogretmenler ogr JOIN ogrenciler o ON o.ogretmen_id = ogr.id WHERE o.id = ?");
    $stmt->execute([$ogrenci_id]);
    $arena_bitis = $stmt->fetchColumn();

    // Şu anki saat, bitiş saatinden küçükse arena AÇIKTIR (true)
    $arena_acik_mi = ($arena_bitis && strtotime($arena_bitis) > time());

    echo json_encode(['success' => true, 'acik' => $arena_acik_mi]);
} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
?>