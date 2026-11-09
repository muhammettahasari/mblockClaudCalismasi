<?php
/* ==========================================================
   Dosya Yolu: api/arena_durum_kontrol.php
   Kullanıcı: Öğrenci
   Halk dilinde: Öğrencinin sınıf arenası açık mı?
   [2026-11] Kapıcı ogrenciZorunluApi(); öğretmen SINIFTAN türetilir.
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';
$ogrenci_id = ogrenciZorunluApi();
header('Content-Type: application/json');

try {
    // Öğrencinin öğretmenini bul ve arena saatini kontrol et
    $stmt = $pdo->prepare("SELECT ogr.arena_bitis FROM ogrenciler o JOIN siniflar s ON s.id = o.sinif_id JOIN ogretmenler ogr ON ogr.id = s.ogretmen_id WHERE o.id = ?");
    $stmt->execute([$ogrenci_id]);
    $arena_bitis = $stmt->fetchColumn();

    // Şu anki saat, bitiş saatinden küçükse arena AÇIKTIR (true)
    $arena_acik_mi = ($arena_bitis && strtotime($arena_bitis) > time());

    echo json_encode(['success' => true, 'acik' => $arena_acik_mi]);
} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
?>