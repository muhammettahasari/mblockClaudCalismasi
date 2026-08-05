<?php
/**
 * Dosya Yolu: v2_gorev_sistemi/api/kukla_api.php
 * İşlev: Oyun sahnesinin (oynatici.php) veya tasarlayıcının sayfa yenilenmeden veritabanındaki tüm kuklaları JSON formatında çekebilmesini sağlayan köprüdür.
 */
/* GÜVENLİK: [2026-08] Giriş yapmış öğrenci veya Süper Admin zorunlu.
   Kukla kütüphanesi tüm internete açık durmasın. */
require_once __DIR__ . '/../../bilesenler/guvenlik.php';

guvenliOturumBaslat();
if (empty($_SESSION['ogrenci_id']) && empty($_SESSION['super_admin_id'])) {
    apiYetkisiz('Bu listeyi görmek için giriş yapmalısınız.');
}

header('Content-Type: application/json; charset=utf-8');

try {
    $sorgu = $pdo->query("SELECT * FROM v2_kukla_kutuphanesi ORDER BY grup_adi, id DESC");
    $kuklalar = $sorgu->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(["durum" => "basarili", "veri" => $kuklalar]);
} catch (Exception $e) {
    echo json_encode(["durum" => "hata", "mesaj" => $e->getMessage()]);
}
?>