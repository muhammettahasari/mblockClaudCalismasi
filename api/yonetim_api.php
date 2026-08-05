<?php
/* ==========================================================
   Dosya Yolu: api/yonetim_api.php
   İşlev: Süper Adminin silme, sıralama ve bölüm düzenleme işlemleri
   GÜVENLİK: [2026-08] Süper Admin oturumu zorunlu hale getirildi (#1).
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';
superAdminZorunluApi();
header('Content-Type: application/json; charset=utf-8');

$veri = json_decode(file_get_contents("php://input"), true);
$islem = $veri['islem'] ?? '';
$tur = $veri['tur'] ?? ''; // 'bolum' veya 'gorev'
$id = isset($veri['id']) ? (int)$veri['id'] : 0;

try {
    // 1. SİLME İŞLEMİ
    if ($islem === 'sil' && $id > 0) {
        if ($tur === 'bolum') {
            // Veritabanında ON DELETE CASCADE olduğu için bölüm silinince içindeki görevler de otomatik silinir
            $stmt = $pdo->prepare("DELETE FROM bolumler WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("DELETE FROM gorevler WHERE id = ?");
        }
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } 
    
    // 2. BÖLÜM ADI GÜNCELLEME İŞLEMİ
    elseif ($islem === 'bolum_duzenle' && $id > 0) {
        $yeni_ad = $veri['yeni_ad'] ?? '';
        if (!empty($yeni_ad)) {
            $stmt = $pdo->prepare("UPDATE bolumler SET bolum_adi = ? WHERE id = ?");
            $stmt->execute([$yeni_ad, $id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Bölüm adı boş olamaz.']);
        }
    }

    // 3. SIRALAMA DEĞİŞTİRME İŞLEMİ (YUKARI / AŞAĞI)
    elseif ($islem === 'sira_degistir' && $id > 0) {
        $yon = $veri['yon'] ?? ''; // 'yukari' veya 'asagi'
        $tablo = ($tur === 'bolum') ? 'bolumler' : 'gorevler';
        
        // Mevcut öğenin sıra numarasını bul
        $stmt = $pdo->prepare("SELECT sira_no, bolum_id FROM $tablo WHERE id = ?");
        $stmt->execute([$id]);
        $mevcut = $stmt->fetch();
        
        if ($mevcut) {
            $mevcut_sira = $mevcut['sira_no'];
            $hedef_sira = ($yon === 'yukari') ? $mevcut_sira - 1 : $mevcut_sira + 1;
            
            // Eğer görevse, sadece kendi bölümü içindeki sırasını değiştirmeli
            $ek_sart = ($tur === 'gorev') ? "AND bolum_id = " . $mevcut['bolum_id'] : "";

            // Hedef sıradaki diğer öğeyi bul
            $stmtKoms = $pdo->prepare("SELECT id FROM $tablo WHERE sira_no = ? $ek_sart");
            $stmtKoms->execute([$hedef_sira]);
            $komsu = $stmtKoms->fetch();

            if ($komsu) {
                // Sıra numaralarını takas et (Swap)
                $pdo->prepare("UPDATE $tablo SET sira_no = ? WHERE id = ?")->execute([$mevcut_sira, $komsu['id']]);
                $pdo->prepare("UPDATE $tablo SET sira_no = ? WHERE id = ?")->execute([$hedef_sira, $id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Zaten en uçtasınız.']);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Geçersiz işlem.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası.']);
}
?>