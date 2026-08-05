<?php
/* ====================================================================
 * MODÜL: Düello Zaman Aşımı
 * DOSYA: api/duello/zaman_doldu.php
 * BAĞLI OLDUĞU ANA DOSYA: duello_arena.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Süre dolduğunda kimse cevap verememişse maçı bitirir ve iki tarafa da
 * küçük bir ceza yazar (meydan okuyan -5, okunan -3). Kazanan_id = -2
 * yazılarak bunun bir zaman aşımı olduğu işaretlenir.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] GÜVENLİK AÇIĞI KAPATILDI (Bilinen Sorunlar #7):
 *      Eskiden bu dosyada HİÇ oturum kontrolü yoktu; düello numarasını
 *      bilen herkes istediği maçı zaman aşımına uğratıp iki öğrencinin
 *      puanını düşürebiliyordu. Artık sadece maçın tarafları çağırabilir.
 *  - [2026-08] Puanlar 0'ın altına düşmüyor (GREATEST) ve işlem
 *      transaction içinde yapılıyor.
 * ==================================================================== */

require_once __DIR__ . '/../../bilesenler/guvenlik.php';
require_once __DIR__ . '/../../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();
$duello_id  = isset($_POST['duello_id']) ? (int) $_POST['duello_id'] : 0;

if ($duello_id <= 0) {
    apiJson(['status' => 'zaman_doldu']);
}

try {
    $st = $pdo->prepare("SELECT * FROM quiz_duellolar WHERE id = ? AND durum = 'kabul_edildi'");
    $st->execute([$duello_id]);
    $duello = $st->fetch(PDO::FETCH_ASSOC);

    if (!$duello) {
        apiJson(['status' => 'zaman_doldu']);
    }

    $meydan_okuyan = (int) $duello['meydan_okuyan_id'];
    $meydan_okunan = (int) $duello['meydan_okunan_id'];

    // GÜVENLİK: Sadece maçın tarafları zaman aşımı bildirebilir
    if ($ogrenci_id !== $meydan_okuyan && $ogrenci_id !== $meydan_okunan) {
        apiHata('Bu düellonun tarafı değilsiniz.', 403);
    }

    $pdo->beginTransaction();

    // Yarış durumu koruması
    $bitir = $pdo->prepare(
        "UPDATE quiz_duellolar SET durum = 'bitti', kazanan_id = -2
          WHERE id = ? AND durum = 'kabul_edildi'"
    );
    $bitir->execute([$duello_id]);

    if ($bitir->rowCount() === 0) {
        $pdo->rollBack();
        apiJson(['status' => 'zaman_doldu']);
    }

    $ceza = $pdo->prepare("UPDATE ogrenciler SET kalici_puan = GREATEST(0, kalici_puan - ?) WHERE id = ?");
    $ceza->execute([5, $meydan_okuyan]);
    $ceza->execute([3, $meydan_okunan]);

    $pdo->commit();

    apiJson(['status' => 'zaman_doldu', 'success' => true]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiJson(['status' => 'zaman_doldu']);
}
