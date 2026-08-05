<?php
/* ====================================================================
 * MODÜL: Düello Yanıtı (Kabul / Red)
 * DOSYA: api/duello/yanitla.php
 * BAĞLI OLDUĞU ANA DOSYA: bilesenler/duello_radari.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Meydan okunan öğrencinin "kabul ediyorum" ya da "reddediyorum" cevabı.
 * Kabul edilirse seviyeye uygun rastgele bir soru seçilip masaya konur.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] GÜVENLİK AÇIĞI KAPATILDI (Bilinen Sorunlar #7):
 *      Eskiden düello numarasını bilen HERKES o düelloyu kabul veya
 *      reddedebiliyordu. Artık yalnızca meydan okunan kişi yanıtlayabilir.
 *  - [2026-08] Soru seçimi öğrencinin bölüm seviyesine göre yapılırken
 *      havuz boşsa anlamlı hata dönüyor.
 * ==================================================================== */

require_once __DIR__ . '/../../bilesenler/guvenlik.php';
require_once __DIR__ . '/../../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();

$duello_id = isset($_POST['duello_id']) ? (int) $_POST['duello_id'] : 0;
$yanit     = beyazListe($_POST['yanit'] ?? '', ['kabul', 'red']);

if ($duello_id <= 0 || !$yanit) {
    apiHata('Geçersiz istek.');
}

try {
    // GÜVENLİK: Bu düello gerçekten BANA mı okundu ve hâlâ bekliyor mu?
    $st = $pdo->prepare(
        "SELECT * FROM quiz_duellolar
          WHERE id = ? AND meydan_okunan_id = ? AND durum = 'bekliyor'"
    );
    $st->execute([$duello_id, $ogrenci_id]);
    $duello = $st->fetch(PDO::FETCH_ASSOC);

    if (!$duello) {
        apiHata('Bu meydan okumayı yanıtlama yetkiniz yok ya da süresi geçmiş.', 403);
    }

    /* ---- RED ---- */
    if ($yanit === 'red') {
        $pdo->prepare("UPDATE quiz_duellolar SET durum = 'reddedildi' WHERE id = ?")->execute([$duello_id]);
        apiJson(['status' => 'reddedildi', 'success' => true]);
    }

    /* ---- KABUL ---- */
    $st = $pdo->prepare("SELECT mevcut_bolum FROM ogrenciler WHERE id = ?");
    $st->execute([$ogrenci_id]);
    $max_bolum = max(1, (int) ($st->fetchColumn() ?: 1));

    // Seviyeye uygun (kendi bölümü ve altı) rastgele bir soru
    $st = $pdo->prepare(
        "SELECT s.id
           FROM quiz_sorulari s
           JOIN bolumler b ON b.id = s.bolum_id
          WHERE b.sira_no <= ?
          ORDER BY RAND() LIMIT 1"
    );
    $st->execute([$max_bolum]);
    $soru_id = $st->fetchColumn();

    // Bölüm sıra numarası eşleşmediyse ham bolum_id ile tekrar dene
    if (!$soru_id) {
        $st = $pdo->prepare("SELECT id FROM quiz_sorulari WHERE bolum_id <= ? ORDER BY RAND() LIMIT 1");
        $st->execute([$max_bolum]);
        $soru_id = $st->fetchColumn();
    }

    if (!$soru_id) {
        $pdo->prepare("UPDATE quiz_duellolar SET durum = 'reddedildi' WHERE id = ?")->execute([$duello_id]);
        apiHata('Havuzda seviyenize uygun soru bulunamadı, düello iptal edildi.');
    }

    $pdo->prepare("UPDATE quiz_duellolar SET durum = 'kabul_edildi', soru_id = ? WHERE id = ?")
        ->execute([$soru_id, $duello_id]);

    apiJson(['status' => 'basladi', 'success' => true, 'duello_id' => $duello_id]);

} catch (Throwable $e) {
    apiIstisna($e, 'Meydan okuma yanıtlanamadı.');
}
