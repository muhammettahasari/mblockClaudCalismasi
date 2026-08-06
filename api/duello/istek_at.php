<?php
/* ====================================================================
 * MODÜL: Düello Meydan Okuma
 * DOSYA: api/duello/istek_at.php
 * BAĞLI OLDUĞU ANA DOSYA: bilesenler/skorboard_modulleri/bireysel_lig.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğrencinin sınıf arkadaşına "seninle düello yapalım" isteği göndermesi.
 *
 * KURALLAR:
 *  - Öğretmenin düello penceresi açık olmalı
 *  - Hedef AYNI SINIFTAN olmalı
 *  - Aynı anda tek bekleyen meydan okuma (spam koruması)
 *  - Kendine meydan okuyamaz
 *  - Rakip başka bir düellonun içindeyse beklemeli
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] SINIF KİLİDİ (Bilinen Sorunlar #7):
 *      Eskiden hedefin sınıfı/okulu hiç kontrol edilmiyordu; başka
 *      şehirdeki bir öğrenciye meydan okunabiliyordu.
 *  - [2026-08] Global şalter yerine öğretmenin kendi duello_bitis saati.
 * ==================================================================== */

require_once __DIR__ . '/../../bilesenler/guvenlik.php';
require_once __DIR__ . '/../../bilesenler/api_yanit.php';

$meydan_okuyan = ogrenciZorunluApi();

/* [2026-08] ÖĞRETMEN KİLİDİ — ders donduruldusa bu işlem yapılamaz.
   Ekrandaki kilit perdesi sadece görsel; gerçek engel burada. */
require_once __DIR__ . '/../../bilesenler/ilerleme_yardimcilari.php';
kilitliyseReddet($pdo, $meydan_okuyan);
$meydan_okunan = isset($_POST['hedef_id']) ? (int) $_POST['hedef_id'] : 0;

if ($meydan_okunan <= 0) {
    apiHata('Hedef seçilmedi.');
}
if ($meydan_okunan === $meydan_okuyan) {
    apiHata('Kendine meydan okuyamazsın! 🙂');
}

try {
    // 1. Meydan okuyanın sınıfı + öğretmeninin düello penceresi
    $st = $pdo->prepare(
        "SELECT o.sinif_id, o.ogretmen_id, ogr.duello_bitis
           FROM ogrenciler o
           JOIN ogretmenler ogr ON ogr.id = o.ogretmen_id
          WHERE o.id = ?"
    );
    $st->execute([$meydan_okuyan]);
    $ben = $st->fetch(PDO::FETCH_ASSOC);

    if (!$ben) {
        apiHata('Öğrenci kaydınız bulunamadı.');
    }

    if (empty($ben['duello_bitis']) || strtotime($ben['duello_bitis']) <= time()) {
        apiHata('Düello arenası kapalı! Öğretmeninizin açmasını bekleyin.');
    }

    // 2. Hedef AYNI sınıfta mı?
    $st = $pdo->prepare("SELECT id FROM ogrenciler WHERE id = ? AND sinif_id = ? AND ogretmen_id = ?");
    $st->execute([$meydan_okunan, $ben['sinif_id'], $ben['ogretmen_id']]);

    if (!$st->fetch()) {
        apiHata('Sadece kendi sınıfındaki arkadaşlarına meydan okuyabilirsin.');
    }

    // 3. Spam koruması — zaten bekleyen bir meydan okuman var mı?
    $st = $pdo->prepare("SELECT id FROM quiz_duellolar WHERE meydan_okuyan_id = ? AND durum = 'bekliyor'");
    $st->execute([$meydan_okuyan]);
    if ($st->fetch()) {
        apiHata('Zaten bekleyen bir meydan okuman var!');
    }

    // 4. Rakip şu an başka bir düelloda mı?
    $st = $pdo->prepare(
        "SELECT id FROM quiz_duellolar
          WHERE (meydan_okunan_id = ? OR meydan_okuyan_id = ?)
            AND durum IN ('bekliyor', 'kabul_edildi')"
    );
    $st->execute([$meydan_okunan, $meydan_okunan]);
    if ($st->fetch()) {
        apiHata('Rakibin şu an başka bir düelloda. Biraz sonra tekrar dene.');
    }

    $pdo->prepare("INSERT INTO quiz_duellolar (meydan_okuyan_id, meydan_okunan_id) VALUES (?, ?)")
        ->execute([$meydan_okuyan, $meydan_okunan]);

    apiBasari([], 'Meydan okuma gönderildi! ⚔️');

} catch (Throwable $e) {
    apiIstisna($e, 'Meydan okuma gönderilemedi.');
}
