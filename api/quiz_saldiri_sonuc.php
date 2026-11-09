<?php
/* ====================================================================
 * MODÜL: Quiz Saldırı Sonucu Değerlendirici
 * DOSYA: api/quiz_saldiri_sonuc.php
 * BAĞLI OLDUĞU ANA DOSYA: (eski) quiz_arena.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Bir öğrenciye kılıç/glitch/saat atıldığında, o öğrenci soruyu cevaplayınca
 * kimin kaç puan alacağını hesaplayan hakem.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Oturum kontrolü, savunan doğrulaması, tek kullanım.
 *  - [2026-11] ARTIK İSTEMCİYE HİÇ GÜVENMİYOR. 'dogru_mu' ve 'normal_puan'
 *    tarayıcıdan alınmaz; sonuç, öğrencinin api/quiz_cevap.php'ye verdiği
 *    ve sunucuda kaydedilen SON cevaptan okunur. quiz_arena.php saldırıyı
 *    artık doğrudan quiz_cevap.php içinde sonuçlandırıyor; bu dosya geriye
 *    dönük uyumluluk için duruyor. Puanlama kuralı: bilesenler/quiz_hakem.php
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';
require_once __DIR__ . '/../bilesenler/quiz_hakem.php';

$ogrenci_id = ogrenciZorunluApi();
$log_id     = (int) (istekVerisi()['log_id'] ?? 0);

if ($log_id <= 0) {
    apiHata('Saldırı kaydı bulunamadı.');
}

try {
    quizCevapTablosu($pdo);
    $pdo->beginTransaction();

    $st = $pdo->prepare("SELECT * FROM quiz_savas_log WHERE id = ? FOR UPDATE");
    $st->execute([$log_id]);
    $log = $st->fetch(PDO::FETCH_ASSOC);

    if (!$log || (int) $log['savunan_id'] !== $ogrenci_id) {
        $pdo->rollBack();
        apiHata('Bu saldırının sonucunu yalnızca hedefi bildirebilir.', 403);
    }
    if (!in_array($log['durum'], ['bekliyor', 'kalkan_kirdi'], true)) {
        $pdo->rollBack();
        apiHata('Bu saldırı zaten sonuçlanmış.', 409);
    }

    $st = $pdo->prepare("SELECT id FROM quiz_oturum WHERE id = ? AND durum = 'aktif'");
    $st->execute([(int) $log['oturum_id']]);
    if (!$st->fetch()) {
        $pdo->rollBack();
        apiHata('Savaş sona ermiş.', 409);
    }

    // Saldırıdan SONRA verilen son cevap (sunucu kaydı)
    $st = $pdo->prepare(
        "SELECT c.dogru, c.kazanilan
           FROM quiz_cevaplar c
           JOIN quiz_ogrenci qo ON qo.id = c.oyuncu_id
          WHERE qo.oturum_id = ? AND qo.ogrenci_id = ?
            AND c.cevap IS NOT NULL AND c.cevap_zamani >= ?
          ORDER BY c.cevap_zamani DESC LIMIT 1"
    );
    $st->execute([(int) $log['oturum_id'], $ogrenci_id, $log['islem_tarihi']]);
    $cevap = $st->fetch(PDO::FETCH_ASSOC);

    $dogru  = $cevap ? ((int) $cevap['dogru'] === 1) : false;
    $normal = $cevap ? (int) $cevap['kazanilan'] : 0;

    $sonuc = quizSaldiriSonuclandir($pdo, $log, $dogru, $normal);
    $pdo->commit();

    $st = $pdo->prepare("SELECT puan FROM quiz_ogrenci WHERE ogrenci_id = ? AND oturum_id = ?");
    $st->execute([$ogrenci_id, (int) $log['oturum_id']]);

    apiBasari(['guncel_puan' => (int) $st->fetchColumn(), 'durum' => $sonuc['durum']], $sonuc['mesaj']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiIstisna($e, 'Saldırı sonucu işlenemedi.');
}
