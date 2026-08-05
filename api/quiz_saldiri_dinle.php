<?php
/* ====================================================================
 * MODÜL: Quiz Saldırı Dinleyicisi
 * DOSYA: api/quiz_saldiri_dinle.php
 * BAĞLI OLDUĞU ANA DOSYA: quiz_arena.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğrenciye yeni bir saldırı gelip gelmediğini kontrol eder. Gelmişse
 * ekranda animasyonu bir kez oynatmak için "görüldü" işareti koyar.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Oturum üyeliği doğrulanıyor; kayıt yalnızca hedefine döner.
 *  - [2026-08] Standart API cevabına geçildi.
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();
$oturum_id  = isset($_GET['oturum_id']) ? (int) $_GET['oturum_id'] : 0;

if ($oturum_id <= 0) {
    apiJson(['var' => false]);
}

try {
    $st = $pdo->prepare(
        "SELECT l.id, l.oturum_id, l.saldiran_id, l.savunan_id, l.yetenek_turu,
                l.durum, l.saldiran_puan_etkisi, l.savunan_puan_etkisi,
                o.ad_soyad AS saldiran_ad
           FROM quiz_savas_log l
           JOIN ogrenciler o ON l.saldiran_id = o.id
          WHERE l.savunan_id = ?
            AND l.oturum_id  = ?
            AND l.savunan_gordu_mu = 0
            AND l.durum IN ('bekliyor', 'kalkan_kirdi')
          ORDER BY l.id ASC
          LIMIT 1"
    );
    $st->execute([$ogrenci_id, $oturum_id]);
    $saldiri = $st->fetch(PDO::FETCH_ASSOC);

    if (!$saldiri) {
        apiJson(['var' => false]);
    }

    // Aynı animasyon tekrar tekrar oynamasın
    $pdo->prepare("UPDATE quiz_savas_log SET savunan_gordu_mu = 1 WHERE id = ?")
        ->execute([$saldiri['id']]);

    apiJson(['var' => true, 'saldiri' => $saldiri]);

} catch (Throwable $e) {
    apiJson(['var' => false]);
}
