<?php
/* ==========================================================
   Dosya Yolu: api/bolum_ekle.php
   İşlev: Süper Adminin yeni bölüm (ünite) eklemesi
   GÜVENLİK: [2026-08] Süper Admin oturumu zorunlu hale getirildi (#1).
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';
superAdminZorunluApi();
header('Content-Type: application/json; charset=utf-8');

$veri = json_decode(file_get_contents("php://input"), true);
$bolum_adi = $veri['bolum_adi'] ?? '';

if (!empty($bolum_adi)) {
    try {
        // En yüksek sıra numarasını bul
        $stmt = $pdo->query("SELECT MAX(sira_no) as max_sira FROM bolumler");
        $max = $stmt->fetch()['max_sira'] ?? 0;
        $yeni_sira = $max + 1;

        // Yeni bölümü ekle
        $ins = $pdo->prepare("INSERT INTO bolumler (bolum_adi, sira_no) VALUES (?, ?)");
        $ins->execute([$bolum_adi, $yeni_sira]);
        
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'adi' => $bolum_adi]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>