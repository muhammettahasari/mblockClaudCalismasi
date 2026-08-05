<?php
/* ====================================================================
 * MODÜL: Quiz Canlı Radar API'si (Öğretmen Tarafı)
 * DOSYA: api/quiz_canli_tablo.php
 * BAĞLI OLDUĞU ANA DOSYA: admin/quiz_kontrol_merkezi.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğretmenin ekranına saniyede bir öğrencilerin güncel puanlarını gönderir.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Öğretmen oturumu zorunlu; başka öğretmenin oturumu okunamaz
 *    (Bilinen Sorunlar #6).
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogretmen_id = ogretmenZorunluApi();
$oturum_id   = isset($_GET['oturum_id']) ? (int) $_GET['oturum_id'] : 0;

if ($oturum_id <= 0) {
    apiJson([]);
}

try {
    // Oturum gerçekten bu öğretmenin mi?
    $st = $pdo->prepare("SELECT id FROM quiz_oturum WHERE id = ? AND ogretmen_id = ?");
    $st->execute([$oturum_id, $ogretmen_id]);
    if (!$st->fetch()) {
        apiJson([]);
    }

    $st = $pdo->prepare(
        "SELECT o.ad_soyad, qo.puan, qo.cozulen_soru_sayisi
           FROM quiz_ogrenci qo
           JOIN ogrenciler o ON o.id = qo.ogrenci_id
          WHERE qo.oturum_id = ?
          ORDER BY qo.puan DESC, qo.cozulen_soru_sayisi DESC"
    );
    $st->execute([$oturum_id]);

    apiJson($st->fetchAll(PDO::FETCH_ASSOC));

} catch (Throwable $e) {
    apiJson([]);
}
