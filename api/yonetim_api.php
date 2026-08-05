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
            /* [2026-08] v2 GÖREVLERİ DE SİL:
               bolumler -> gorevler ve bolumler -> quiz_sorulari arasında
               ON DELETE CASCADE foreign key'i var, o yüzden v1 görevler ve
               quiz soruları otomatik siliniyor.
               AMA v2_gorevler.bolum_id için foreign key YOK — bölüm silinince
               o bölümün v2 görevleri yetim kalıyor, hiçbir ekranda görünmüyor
               ama veritabanında duruyordu. Artık elle temizleniyor. */
            $pdo->beginTransaction();

            // Önce v2 görev ilerlemelerini, sonra görevleri temizle
            $pdo->prepare(
                "DELETE i FROM v2_ogrenci_ilerleme i
                   JOIN v2_gorevler v ON v.id = i.v2_gorev_id
                  WHERE v.bolum_id = ?"
            )->execute([$id]);

            $pdo->prepare("DELETE FROM v2_gorevler WHERE bolum_id = ?")->execute([$id]);

            // Bölümü sil (v1 görevler + quiz soruları CASCADE ile gider)
            $pdo->prepare("DELETE FROM bolumler WHERE id = ?")->execute([$id]);

            $pdo->commit();
        } else {
            $pdo->prepare("DELETE FROM gorevler WHERE id = ?")->execute([$id]);
        }

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

} catch (Throwable $e) {
    /* [2026-08] Bölüm silme artık transaction içinde yapılıyor.
       Hata çıkarsa yarım kalmış silme bırakmamak için geri sarılır. */
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[yonetim_api] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası.']);
}
