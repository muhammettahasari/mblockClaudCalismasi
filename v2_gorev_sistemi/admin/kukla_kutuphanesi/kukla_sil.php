<?php
/**
 * Dosya Yolu: v2_gorev_sistemi/admin/kukla_kutuphanesi/kukla_sil.php
 * İşlev: Seçilen kuklaları veritabanından ve fiziksel klasörden kalıcı olarak siler.
 */
/* GÜVENLİK: [2026-08] Süper Admin oturumu zorunlu (Bilinen Sorunlar #1). */
require_once __DIR__ . '/../../../admin/super_auth.php';
superAdminZorunlu();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['silinecekler'])) {
    $silinecekIdler = $_POST['silinecekler'];

    foreach ($silinecekIdler as $id) {
        // 1. Veritabanından kuklayı ve JSON kostüm paketini bul
        $sorgu = $pdo->prepare("SELECT * FROM v2_kukla_kutuphanesi WHERE id = ?");
        $sorgu->execute([$id]);
        $kukla = $sorgu->fetch(PDO::FETCH_ASSOC);

        if ($kukla) {
            $kostumler = json_decode($kukla['kostumler_json'], true);
            
            // 2. Fiziksel SVG/PNG dosyalarını klasörden yok et
            if (is_array($kostumler)) {
                foreach ($kostumler as $kostum) {
                    $resimYolu = isset($kostum['resimYolu']) ? $kostum['resimYolu'] : (isset($kostum['yol']) ? $kostum['yol'] : "");
                    
                    if (!empty($resimYolu)) {
                        // Veritabanındaki kirli yolları (../ veya /mblock_platform/) temizleyip saf yolu alır
                        $temizYol = preg_replace('/^.*(assets\/images\/kuklalar\/.*)$/i', '$1', $resimYolu);
                        
                        // Dosyanın sunucudaki tam adresi
                        $fizikselYol = __DIR__ . "/../../" . $temizYol;
                        
                        // Eğer dosya gerçekten klasördeyse sil (unlink)
                        if (file_exists($fizikselYol) && !is_dir($fizikselYol)) {
                            unlink($fizikselYol);
                        }
                    }
                }
            }

            // 3. Kuklayı veritabanından tamamen kaldır
            $silSorgu = $pdo->prepare("DELETE FROM v2_kukla_kutuphanesi WHERE id = ?");
            $silSorgu->execute([$id]);
        }
    }
    
    // İşlem bitince ana sayfaya "silindi" bildirimiyle dön
    header("Location: index.php?durum=silindi");
    exit();
} else {
    // Hiçbir şey seçilmeden butona basıldıysa direkt geri dön
    header("Location: index.php");
    exit();
}
?>