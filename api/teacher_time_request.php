<?php
/* ==========================================================
   Dosya Yolu: api/teacher_time_request.php
   Kullanıcı: Öğretmen (panel üst barı) ya da süresi dolup kapıda kalan öğretmen (index.php)
   Halk dilinde: Öğretmenin "X ay ek süre istiyorum" talebini Süper Admin'e iletir.

   YENİ EKLENEN ÖZELLİKLER:
   - [2026-11] Kapıda kalan öğretmen için kimlik artık kullanıcı adından DEĞİL,
     teacher_login.php'nin şifreyi doğruladıktan sonra oturuma yazdığı
     '_suresi_dolan_ogretmen_id' notundan alınır (15 dk geçerli).
     Eskiden kullanıcı adını bilen herkes, herhangi bir öğretmeni
     'onay_bekliyor' durumuna düşürüp girişini kilitleyebiliyordu.
   - [2026-11] Ay sayısı 1–24 arasına kırpılır.
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';

guvenliOturumBaslat();
istekKokeniDogrula();
header('Content-Type: application/json; charset=utf-8');

$veri = istekVerisi();
$ay   = max(1, min(24, (int) ($veri['ay'] ?? 1)));

$ogretmen_id = (int) ($_SESSION['ogretmen_id'] ?? 0);
if ($ogretmen_id <= 0) {
    $kapidaki = (int) ($_SESSION['_suresi_dolan_ogretmen_id'] ?? 0);
    if ($kapidaki > 0 && (time() - (int) ($_SESSION['_suresi_dolan_zaman'] ?? 0)) <= 900) {
        $ogretmen_id = $kapidaki;
    }
}

if ($ogretmen_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Süre talebi için önce kullanıcı adı ve şifrenizle giriş yapmayı deneyin.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo->prepare("UPDATE ogretmenler SET durum = 'onay_bekliyor', talep_edilen_ay = ? WHERE id = ?")
        ->execute([$ay, $ogretmen_id]);
    unset($_SESSION['_suresi_dolan_ogretmen_id'], $_SESSION['_suresi_dolan_zaman']);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('[teacher_time_request] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası.'], JSON_UNESCAPED_UNICODE);
}
