<?php
/* ====================================================================
 * MODÜL: Düello Şalteri (Öğretmen Kontrolü)
 * DOSYA: api/duello/duello_kontrol.php
 * BAĞLI OLDUĞU ANA DOSYA: admin/ogretmen_paneli.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğretmenin "düello serbest" düğmesi. Belirlediği dakika kadar kendi
 * öğrencilerine 1v1 meydan okuma hakkı açar, süre bitince otomatik kapanır.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] ÖĞRETMEN BAZLI ŞALTER (Bilinen Sorunlar #7):
 *      Eskiden duello_ayarlari tablosunda TEK bir satır (id=1) vardı ve bu
 *      global şalterdi — bir öğretmen düelloyu açınca sistemdeki BÜTÜN
 *      okullara açılıyordu. Artık her öğretmenin kendi penceresi var:
 *      ogretmenler.duello_bitis (savaş arenasıyla aynı desen).
 * ==================================================================== */

require_once __DIR__ . '/../../bilesenler/guvenlik.php';
require_once __DIR__ . '/../../bilesenler/api_yanit.php';

$ogretmen_id = ogretmenZorunluApi();

$islem = $_POST['islem'] ?? '';
$sure  = (int) ($_POST['sure'] ?? 5);

try {
    if ($islem === 'ac') {
        // 1 dakika ile 8 saat arası makul bir aralığa kırp
        $sure  = max(1, min($sure, 480));
        $bitis = date('Y-m-d H:i:s', time() + ($sure * 60));

        $pdo->prepare("UPDATE ogretmenler SET duello_bitis = ? WHERE id = ?")
            ->execute([$bitis, $ogretmen_id]);

        apiBasari(
            ['bitis' => $bitis],
            "⚔️ Düello {$sure} dakikalığına açıldı! Süre bitince otomatik kapanacak."
        );
    }

    if ($islem === 'kapat') {
        $pdo->prepare("UPDATE ogretmenler SET duello_bitis = NULL WHERE id = ?")
            ->execute([$ogretmen_id]);

        // Bekleyen (henüz kabul edilmemiş) meydan okumaları da iptal et
        $pdo->prepare(
            "UPDATE quiz_duellolar d
               JOIN ogrenciler o ON o.id = d.meydan_okuyan_id
               JOIN siniflar   s ON s.id = o.sinif_id      -- [2026-11] sahiplik zinciri
                SET d.durum = 'reddedildi'
              WHERE s.ogretmen_id = ? AND d.durum = 'bekliyor'"
        )->execute([$ogretmen_id]);

        apiBasari([], '🔒 Düello anında kapatıldı! Öğrencilerin erişimi kesildi.');
    }

    apiHata('Geçersiz bir işlem gönderildi.');

} catch (Throwable $e) {
    apiIstisna($e, 'Düello şalteri değiştirilemedi.');
}
