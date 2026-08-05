<?php
/* ==========================================================
   Dosya Yolu: api/teacher_time_request.php
   İşlev: Öğretmenin girdiği ay bilgisiyle süre talebini iletmesi
   ========================================================== */
session_start();
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

$veri = json_decode(file_get_contents("php://input"), true);
$kadi = trim($veri['kullanici_adi'] ?? '');
$ay = (int)($veri['ay'] ?? 1); // Varsayılan 1 ay
$ogretmen_id = $_SESSION['ogretmen_id'] ?? 0;

if (empty($kadi) && !$ogretmen_id) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz talep.']); 
    exit;
}

try {
    if (!empty($kadi)) {
        // Kapıda kalan öğretmen (Kullanıcı adıyla)
        $sorgu = $pdo->prepare("UPDATE ogretmenler SET durum = 'onay_bekliyor', talep_edilen_ay = ? WHERE kullanici_adi = ?");
        $sorgu->execute([$ay, $kadi]);
    } else {
        // İçerideki öğretmen (Session ID ile)
        $sorgu = $pdo->prepare("UPDATE ogretmenler SET durum = 'onay_bekliyor', talep_edilen_ay = ? WHERE id = ?");
        $sorgu->execute([$ay, $ogretmen_id]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası.']);
}
?>