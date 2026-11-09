<?php
/* ====================================================================
 * DOSYA: api/quiz_puan_guncelle.php
 * KULLANICI: Öğrenci (eski quiz_arena.php sürümleri)
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Eskiden tarayıcının hesapladığı puanı veritabanına MUTLAK değer olarak
 * yazıyordu (puan=999 gönderen 999 alırdı; sunucunun güçlerden eklediği
 * puanlar da eziliyordu).
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-11] YAZMA KALDIRILDI. Puanı artık sadece api/quiz_cevap.php
 *    (sunucu hakemi) ve güç uçları değiştirir. Bu dosya geriye dönük
 *    uyumluluk için oyuncunun GÜNCEL puanını döndürür, gelen değeri yok sayar.
 * ==================================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();
$oturum_id  = (int) (istekVerisi()['oturum_id'] ?? 0);

if ($oturum_id <= 0) {
    apiHata('Oturum bilgisi eksik.');
}

try {
    $st = $pdo->prepare("SELECT puan, cozulen_soru_sayisi FROM quiz_ogrenci WHERE oturum_id = ? AND ogrenci_id = ?");
    $st->execute([$oturum_id, $ogrenci_id]);
    $oyuncu = $st->fetch(PDO::FETCH_ASSOC);
    if (!$oyuncu) {
        apiHata('Bu arenanın oyuncusu değilsiniz.', 403);
    }
    apiBasari(['puan' => (int) $oyuncu['puan'], 'cozulen_soru' => (int) $oyuncu['cozulen_soru_sayisi']]);
} catch (Throwable $e) {
    apiIstisna($e, 'Puan okunamadı.');
}
