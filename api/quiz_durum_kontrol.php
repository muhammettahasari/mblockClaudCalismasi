<?php
/* ====================================================================
 * MODÜL: Quiz Savaş Sensörü (Öğrenci Tarafı)
 * DOSYA: api/quiz_durum_kontrol.php
 * BAĞLI OLDUĞU ANA DOSYALAR: ogrenci_paneli.php, profil.php, market.php
 *   (api/ogrenci_radar.php üzerinden de çağrılır)
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğrencinin sayfası arka planda "öğretmenim quiz başlattı mı?" diye sorar.
 * Bu dosya cevabı verir.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] SINIF FİLTRESİ (Bilinen Sorunlar #6):
 *      Eskiden sistemdeki HERHANGİ bir aktif oturumu döndürüyordu; bir
 *      öğretmen quiz açınca tüm okulların öğrencileri arenaya çekiliyordu.
 *      Artık yalnızca öğrencinin KENDİ sınıfına açılmış oturum döner.
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();

try {
    $st = $pdo->prepare("SELECT sinif_id, ogretmen_id FROM ogrenciler WHERE id = ?");
    $st->execute([$ogrenci_id]);
    $ogr = $st->fetch(PDO::FETCH_ASSOC);

    if (!$ogr || empty($ogr['sinif_id'])) {
        apiJson(['aktif' => false]);
    }

    // SADECE bu sınıfa açılmış aktif oturum
    $st = $pdo->prepare(
        "SELECT id FROM quiz_oturum
          WHERE durum = 'aktif' AND sinif_id = ? AND ogretmen_id = ?
          ORDER BY id DESC LIMIT 1"
    );
    $st->execute([$ogr['sinif_id'], $ogr['ogretmen_id']]);
    $oturum = $st->fetch(PDO::FETCH_ASSOC);

    apiJson($oturum
        ? ['aktif' => true, 'oturum_id' => (int) $oturum['id']]
        : ['aktif' => false]);

} catch (Throwable $e) {
    apiJson(['aktif' => false]);
}
