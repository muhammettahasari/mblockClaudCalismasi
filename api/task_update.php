<?php
/* ==========================================================
   Dosya Yolu: api/task_update.php
   İşlev: Görev düzenleme formundan gelen verileri MySQL'de günceller
   GÜVENLİK: [2026-08] Süper Admin oturumu zorunlu hale getirildi (#1).
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';
superAdminZorunlu();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['gorev_id'] ?? null;
    $bolum_id = $_POST['bolum_id'] ?? null;
    $baslik = $_POST['baslik'] ?? null;
    
    $nasil_yaparim = $_POST['nasil_yaparim'] ?? null;
    $gorev_metni = $_POST['gorev_metni'] ?? null;
    $istenen_ekran = $_POST['istenen_ekran'] ?? null;
    $ipucu = $_POST['ipucu'] ?? null;
    
    $puan = $_POST['puan'] ?? 10;
    $sira_no = $_POST['sira_no'] ?? 1;

    if (!$id || !$bolum_id || !$baslik) {
        die("Hata: Gerekli alanlar eksik.");
    }

    try {
        $sql = "UPDATE gorevler SET 
                bolum_id = :bolum_id, 
                baslik = :baslik, 
                nasil_yaparim = :nasil_yaparim, 
                gorev_metni = :gorev_metni, 
                istenen_ekran = :istenen_ekran, 
                ipucu = :ipucu, 
                puan = :puan, 
                sira_no = :sira_no 
                WHERE id = :id";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bolum_id' => $bolum_id,
            ':baslik' => $baslik,
            ':nasil_yaparim' => $nasil_yaparim,
            ':gorev_metni' => $gorev_metni,
            ':istenen_ekran' => $istenen_ekran,
            ':ipucu' => $ipucu,
            ':puan' => $puan,
            ':sira_no' => $sira_no,
            ':id' => $id
        ]);
        
        // Başarıyla güncellendiğinde görev yönetimi listesine geri fırlat
        header("Location: ../admin/gorev_yonetimi.php");
        exit;
    } catch (PDOException $e) {
        die("Veritabanı hatası: " . $e->getMessage());
    }
}
?>