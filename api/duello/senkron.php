<?php
/* ====================================================================
 * MODÜL: Düello Senkronizasyonu
 * DOSYA: api/duello/senkron.php
 * BAĞLI OLDUĞU ANA DOSYA: duello_arena.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Arenadaki iki oyuncu da "maç bitti mi?" diye sorar. Rakip önce cevap
 * verdiyse diğerine sonucu bildirir.
 *
 * KAZANAN_ID ÖZEL DEĞERLERİ:
 *   -1 = kimse bilemedi (berabere)   |   -2 = süre doldu
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Sadece düellonun tarafları sonucu sorgulayabilir.
 * ==================================================================== */

require_once __DIR__ . '/../../bilesenler/guvenlik.php';
require_once __DIR__ . '/../../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();
$duello_id  = isset($_GET['duello_id']) ? (int) $_GET['duello_id'] : 0;

if ($duello_id <= 0) {
    apiJson(['oyun_bitti' => false]);
}

try {
    $st = $pdo->prepare(
        "SELECT durum, kazanan_id, meydan_okuyan_id, meydan_okunan_id,
                okuyan_cevap, okuyan_sure_ms, okunan_cevap, okunan_sure_ms
           FROM quiz_duellolar WHERE id = ?"
    );
    $st->execute([$duello_id]);
    $duello = $st->fetch(PDO::FETCH_ASSOC);

    // Taraf değilse bilgi sızdırma
    if (!$duello
        || ((int) $duello['meydan_okuyan_id'] !== $ogrenci_id
            && (int) $duello['meydan_okunan_id'] !== $ogrenci_id)) {
        apiJson(['oyun_bitti' => false]);
    }

    $benOkuyanMiyim = ((int) $duello['meydan_okuyan_id'] === $ogrenci_id);
    $rakipSutun     = $benOkuyanMiyim ? 'okunan' : 'okuyan';

    if ($duello['durum'] !== 'bitti') {
        /* [2026-08] Maç bitmedi ama rakip cevabını göndermiş olabilir.
           İstemci bunu bilirse "rakip cevapladı, sen de acele et" uyarısı
           gösterebiliyor. Hangi ŞIKKI işaretlediğini söylemiyoruz — sadece
           bastığı bilgisini veriyoruz, yoksa kopya çekilir. */
        apiJson([
            'oyun_bitti'     => false,
            'rakip_cevapladi'=> !empty($duello[$rakipSutun . '_cevap']),
        ]);
    }

    $kazanan = (int) $duello['kazanan_id'];

    apiJson([
        'oyun_bitti'  => true,
        'kazandim_mi' => ($kazanan === $ogrenci_id),
        'berabere_mi' => ($kazanan === -1),
        'zaman_doldu' => ($kazanan === -2),
        // Sonuç ekranında "sen 1.2 sn, rakibin 1.8 sn" diye göstermek için
        'benim_ms'    => (int) ($benOkuyanMiyim ? $duello['okuyan_sure_ms'] : $duello['okunan_sure_ms']),
        'rakip_ms'    => (int) ($benOkuyanMiyim ? $duello['okunan_sure_ms'] : $duello['okuyan_sure_ms']),
    ]);

} catch (Throwable $e) {
    apiJson(['oyun_bitti' => false]);
}
