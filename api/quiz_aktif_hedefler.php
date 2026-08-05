<?php
/* ====================================================================
 * MODÜL: Quiz Arenasındaki Hedef Listesi
 * DOSYA: api/quiz_aktif_hedefler.php
 * BAĞLI OLDUĞU ANA DOSYA: quiz_arena.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğrenci kılıç/glitch/saat atarken "kime atayım?" diye sorduğunda,
 * arenadaki diğer oyuncuları puan sırasına göre listeler.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Oturum üyeliği doğrulanıyor; başka sınıfın oyuncuları
 *    hedef listesine düşmüyor (Bilinen Sorunlar #6).
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();
$oturum_id  = isset($_GET['oturum_id']) ? (int) $_GET['oturum_id'] : 0;

if ($oturum_id <= 0) {
    apiJson([]);
}

try {
    // Güvenlik: öğrenci gerçekten bu oturumun oyuncusu mu?
    $st = $pdo->prepare("SELECT id FROM quiz_ogrenci WHERE oturum_id = ? AND ogrenci_id = ?");
    $st->execute([$oturum_id, $ogrenci_id]);
    if (!$st->fetch()) {
        apiJson([]);
    }

    $st = $pdo->prepare(
        "SELECT o.id, o.ad_soyad, qo.puan
           FROM quiz_ogrenci qo
           JOIN ogrenciler o ON qo.ogrenci_id = o.id
          WHERE qo.oturum_id = ? AND qo.ogrenci_id <> ?
          ORDER BY qo.puan DESC"
    );
    $st->execute([$oturum_id, $ogrenci_id]);

    apiJson($st->fetchAll(PDO::FETCH_ASSOC));

} catch (Throwable $e) {
    apiJson([]);
}
