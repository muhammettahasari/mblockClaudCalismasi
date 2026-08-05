<?php
session_start();
header('Content-Type: application/json');

// Oturum kontrolü
if (!isset($_SESSION['ogrenci_id'])) {
    echo json_encode(['status' => 'error', 'mesaj' => 'Oturum bulunamadı.']);
    exit;
}

require_once '../db.php';
$ogrenci_id = $_SESSION['ogrenci_id'];
$islem = $_POST['islem'] ?? '';

// --- İŞLEM 1: SİLAH SEÇİMİ İÇİN ENVANTERİ GETİR ---
if ($islem == 'envanter_getir') {
    // Sadece saldırıda kullanılabilecek eşyaları getir (Beden, yüz, saç hariç)
    $stmt = $pdo->prepare("
        SELECT oe.id as envanter_id, e.esya_adi, e.resim_yolu, e.seviye, e.kategori 
        FROM ogrenci_envanter oe 
        JOIN esya_kutuphanesi e ON oe.esya_id = e.id 
        WHERE oe.ogrenci_id = ? AND e.kategori NOT IN ('yuz', 'sac', 'karakter', 'ayakkabi')
    ");
    $stmt->execute([$ogrenci_id]);
    $esyalar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'esyalar' => $esyalar]);
    exit;
}

// --- İŞLEM 2: SALDIRIYI GERÇEKLEŞTİR ---
if ($islem == 'saldir') {
    $envanter_id = $_POST['envanter_id'] ?? 0;

    if (!$envanter_id) {
        echo json_encode(['status' => 'error', 'mesaj' => 'Fırlatılacak eşyayı seçmedin!']);
        exit;
    }

    // 1. Öğrencinin enerjisini kontrol et
    $ogrSorgu = $pdo->prepare("SELECT enerji FROM ogrenciler WHERE id = ?");
    $ogrSorgu->execute([$ogrenci_id]);
    $ogrenci = $ogrSorgu->fetch(PDO::FETCH_ASSOC);

    if ($ogrenci['enerji'] < 33) {
        echo json_encode(['status' => 'error', 'mesaj' => 'Çok yorgunsun! Saldırmak için 33 Enerjiye ihtiyacın var.']);
        exit;
    }

    // 2. Feda edilecek eşyayı kontrol et
    $esyaSorgu = $pdo->prepare("
        SELECT oe.id, e.seviye, e.esya_adi 
        FROM ogrenci_envanter oe 
        JOIN esya_kutuphanesi e ON oe.esya_id = e.id 
        WHERE oe.id = ? AND oe.ogrenci_id = ?
    ");
    $esyaSorgu->execute([$envanter_id, $ogrenci_id]);
    $esya = $esyaSorgu->fetch(PDO::FETCH_ASSOC);

    if (!$esya) {
        echo json_encode(['status' => 'error', 'mesaj' => 'Sandığında böyle bir eşya yok!']);
        exit;
    }

    // 3. Hasar Matematiği (Eşyanın Seviyesi * 20)
    $seviye = $esya['seviye'] > 0 ? $esya['seviye'] : 1;
    $hasar = $seviye * 20;

    // 4. Canavarı bul
    $canavarSorgu = $pdo->query("SELECT id, mevcut_can FROM dunya_canavari WHERE durum = 'aktif' LIMIT 1");
    $canavar = $canavarSorgu->fetch(PDO::FETCH_ASSOC);

    if (!$canavar || $canavar['mevcut_can'] <= 0) {
        echo json_encode(['status' => 'error', 'mesaj' => 'Tebrikler, canavar zaten alt edilmiş!']);
        exit;
    }

    // --- SAVAŞ TRANSACTİON (GÜVENLİ İŞLEM MİMARİSİ) ---
    $pdo->beginTransaction();

    try {
        // Kamikaze: Eşyayı feda et (sil)
        $pdo->prepare("DELETE FROM ogrenci_envanter WHERE id = ?")->execute([$envanter_id]);

        // Bedel: 33 Enerji düşür
        $pdo->prepare("UPDATE ogrenciler SET enerji = enerji - 33 WHERE id = ?")->execute([$ogrenci_id]);

        // Hasar: Canavarın canını azalt
        $yeni_can = $canavar['mevcut_can'] - $hasar;
        if ($yeni_can < 0) $yeni_can = 0;
        $pdo->prepare("UPDATE dunya_canavari SET mevcut_can = ? WHERE id = ?")->execute([$yeni_can, $canavar['id']]);

        // Log: Savaş raporuna işle (Sınıf ortalaması için bu tablo kullanılacak)
        $pdo->prepare("INSERT INTO canavar_hasar_log (canavar_id, ogrenci_id, hasar_miktari) VALUES (?, ?, ?)")->execute([$canavar['id'], $ogrenci_id, $hasar]);

        // Canavar öldüyse durumunu güncelle
        if ($yeni_can == 0) {
            $pdo->prepare("UPDATE dunya_canavari SET durum = 'olu' WHERE id = ?")->execute([$canavar['id']]);
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success', 
            'hasar' => $hasar, 
            'kalan_can' => number_format($yeni_can, 0, ',', '.'),
            'mesaj' => "{$esya['esya_adi']} feda edildi ve canavara {$hasar} hasar verildi!"
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'mesaj' => 'Kılıcın taşa sekti! Sistemsel bir hata oluştu.']);
    }
}
?>