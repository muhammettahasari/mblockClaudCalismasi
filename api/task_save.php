<?php
/* ==========================================================
   YENİ KODLARIN BAŞLANGICI
   Dosya Yolu: api/task_save.php
   İşlev: Görev formundan gelen verileri MySQL'e kaydeder
   GÜVENLİK: [2026-08] Süper Admin oturumu zorunlu hale getirildi (#1).
   ========================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
superAdminZorunlu();

// Formdan gelen POST verisi var mı kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $bolum_id = $_POST['bolum_id'] ?? null;
    $baslik = $_POST['baslik'] ?? null;
    $nasil_yaparim = $_POST['nasil_yaparim'] ?? null; // Editörden gelen veri artık nasil_yaparim'a gidiyor
    $gorev_metni = $_POST['gorev_metni'] ?? null;
    $istenen_ekran = $_POST['istenen_ekran'] ?? null;
    $ipucu = $_POST['ipucu'] ?? null;
    $puan = $_POST['puan'] ?? 10;
    $sira_no = $_POST['sira_no'] ?? 1;

    if (!$bolum_id || !$baslik) {
        die("Hata: Lütfen bölüm ve başlık alanlarını doldurun.");
    }

    try {
        $sql = "INSERT INTO gorevler (bolum_id, baslik, nasil_yaparim, gorev_metni, istenen_ekran, ipucu, puan, sira_no) 
                VALUES (:bolum_id, :baslik, :nasil_yaparim, :gorev_metni, :istenen_ekran, :ipucu, :puan, :sira_no)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bolum_id' => $bolum_id,
            ':baslik' => $baslik,
            ':nasil_yaparim' => $nasil_yaparim,
            ':gorev_metni' => $gorev_metni,
            ':istenen_ekran' => $istenen_ekran,
            ':ipucu' => $ipucu,
            ':puan' => $puan,
            ':sira_no' => $sira_no
        ]);

     // BAŞARILI OLDUĞUNDA ÇALIŞACAK KOD:
        echo '
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <meta charset="UTF-8">
            <title>İşlem Başarılı</title>
            <style>
                body { background: #f0f4f8; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
                .modal { background: white; padding: 40px 60px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); text-align: center; animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; transform: scale(0.8); opacity: 0; }
                .icon { font-size: 60px; margin-bottom: 20px; animation: bounce 1s infinite alternate; }
                .title { color: #10b981; font-size: 28px; font-weight: bold; margin-bottom: 10px; }
                .text { color: #64748b; font-size: 16px; margin-bottom: 20px; }
                .loader { border: 4px solid #f3f3f3; border-top: 4px solid #10b981; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 0 auto; }
                
                @keyframes popIn { to { transform: scale(1); opacity: 1; } }
                @keyframes bounce { from { transform: translateY(0px); } to { transform: translateY(-10px); } }
                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            </style>
        </head>
        <body>
            <div class="modal">
                <div class="icon">✅</div>
                <div class="title">Harika!</div>
                <div class="text">Yeni görev sisteme başarıyla kaydedildi.<br>Müfredat listesine yönlendiriliyorsunuz...</div>
                <div class="loader"></div>
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = "../admin/gorev_yonetimi.php";
                }, 2000);
            </script>
        </body>
        </html>
        ';
        exit;
    } catch (PDOException $e) {
        echo "Kayıt Hatası: " . $e->getMessage();
    }
} else {
    echo "Geçersiz istek.";
}

/* ==========================================================
   YENİ KODLARIN BİTİŞİ
   ========================================================== */
?>