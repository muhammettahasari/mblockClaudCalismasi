<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

$ogretmen_id = $_SESSION['ogretmen_id'] ?? null;

if (!$ogretmen_id) {
    echo json_encode(['success' => false, 'mesaj' => 'Yetkisiz işlem!']);
    exit;
}

// Javascript'ten gelen işlem türü ve süre bilgilerini alıyoruz
$islem = $_POST['islem'] ?? null;
$sure = $_POST['sure'] ?? null;

try {
    if ($islem === 'kapat') {
        // Arenayı kapatma komutu geldiyse süreyi NULL yap
        $pdo->prepare("UPDATE ogretmenler SET arena_bitis = NULL WHERE id = ?")->execute([$ogretmen_id]);
        echo json_encode(['success' => true, 'durum' => 'kapali']);
        
    } elseif ($islem === 'ac') {
        // Arenayı açma komutu geldiyse süreyi hesapla
        if ($sure === 'suresiz') {
            $yeni_bitis = '2099-12-31 23:59:59'; // Uzak bir gelecek (Süresiz mantığı)
        } else {
            $dakika = (int)$sure;
            $yeni_bitis = date('Y-m-d H:i:s', time() + ($dakika * 60)); // Şu anki saate dakikayı ekle
        }
        
        $pdo->prepare("UPDATE ogretmenler SET arena_bitis = ? WHERE id = ?")->execute([$yeni_bitis, $ogretmen_id]);
        echo json_encode(['success' => true, 'durum' => 'acik']);
    } else {
        echo json_encode(['success' => false, 'mesaj' => 'Geçersiz işlem!']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'mesaj' => 'Veritabanı Hatası: ' . $e->getMessage()]);
}
?>