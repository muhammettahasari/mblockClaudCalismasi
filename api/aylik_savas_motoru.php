<?php
/* ====================================================================
 * MODÜL: Aylık Dünya Canavarı Savaş Motoru
 * DOSYA YOLU: api/aylik_savas_motoru.php
 * ÇALIŞTIRAN: Süper Admin (panelden) veya sunucu görev zamanlayıcısı (cron/CLI)
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Her ayın belirlenen gününde (varsayılan 18) tüm okulların ortalama gücünü
 * toplayıp canavara tek seferde vurur.
 *
 * CANAVAR YAŞAM DÖNGÜSÜ (assets/videos/metin.txt isteklerine göre):
 *   1. Canavar ÖLÜRSE  → yumurtadan yeni canavar doğar.
 *      Yeni canavarın canı = onu öldüren Türkiye ortalaması × 1.15
 *      (Yani yumurta güçlü doğar, her ay zorlaşır.)
 *   2. Canavar ÖLMEZSE → yumurtaya DÖNMEZ. "Dev" olarak kalır, yaralarını
 *      sarar ve büyür. İyileşme Türkiye ordularının toplam gücüyle orantılıdır:
 *      yeni_max = eski_max + türkiye_gücü   ve canı tam dolar.
 *      Böylece öğrenciler güçlendikçe canavar da güçlenir; belli bir aydan
 *      sonra "hep öğrenciler kazanır" durumu oluşmaz.
 *   3. VİDEO: Canavarın canı %50'nin ALTINA inerse 'zafer', inmezse
 *      'canavar_kazandi' videosu oynatılır.
 *
 * GÜVENLİK: [2026-08] Tarayıcıdan çağrılabilmesi için Süper Admin oturumu
 *           zorunlu. Komut satırından (cron) çalıştırma serbest.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Süper Admin koruması eklendi (Bilinen Sorunlar #1).
 *  - [2026-08] Ölmeyen canavarın iyileşme/büyüme formülü metin.txt'ye göre
 *      yeniden yazıldı (#18).
 *  - [2026-08] Savaş günü tek bir sabitte toplandı (CANAVAR_SAVAS_GUNU);
 *      arayüzdeki "ayın 1'i" yazısıyla oluşan tutarsızlık giderildi.
 *  - [2026-08] ?zorla=1 ile admin savaşı gün beklemeden başlatabilir (test).
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/canavar_ayar.php';

// Komut satırından (cron) çalışıyorsa oturum aranmaz.
if (PHP_SAPI !== 'cli') {
    superAdminZorunluApi();
}

header('Content-Type: application/json; charset=utf-8');

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$bugunGun = (int) date('d');
$zorla    = (PHP_SAPI !== 'cli') && !empty($_GET['zorla']);

if ($bugunGun !== CANAVAR_SAVAS_GUNU && !$zorla) {
    exit(json_encode([
        'status' => 'beklemede',
        'mesaj'  => 'Savaş günü henüz gelmedi. (Savaş her ayın ' . CANAVAR_SAVAS_GUNU . '. günü yapılır.)',
    ], JSON_UNESCAPED_UNICODE));
}

try {
    /* ---- 1. Vurulacak canavarı bul ---- */
    $canavar = $pdo->query(
        "SELECT * FROM dunya_canavari
          WHERE durum = 'aktif' AND savas_yapildi_mi = 0
          ORDER BY id DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    if (!$canavar) {
        exit(json_encode([
            'status' => 'tamamlandi',
            'mesaj'  => 'Vurulacak aktif canavar bulunamadı. (Bu ayın savaşı zaten yapılmış olabilir.)',
        ], JSON_UNESCAPED_UNICODE));
    }

    /* ---- 2. Türkiye toplam ordu gücü = her okulun ortalamasının toplamı ---- */
    $toplamHasar = (int) round($pdo->query(
        "SELECT COALESCE(SUM(okul_ort), 0) FROM (
            SELECT AVG(o.kalici_puan) AS okul_ort
              FROM ogrenciler o
              JOIN ogretmenler ogr ON o.ogretmen_id = ogr.id
             GROUP BY ogr.okul_adi
         ) AS okul_hasarlari"
    )->fetchColumn());

    if ($toplamHasar <= 0) {
        $toplamHasar = 10;   // Hiç öğrenci yoksa sembolik hasar
    }

    /* ---- 3. Hasarı uygula ---- */
    $maksCan = max(1, (int) $canavar['max_can']);
    $yeniCan = (int) $canavar['mevcut_can'] - $toplamHasar;

    $olduMu    = ($yeniCan <= 0);
    $yeniCan   = max(0, $yeniCan);
    $yeniDurum = $olduMu ? 'olu' : 'aktif';

    // VİDEO KARARI: can %50'nin ALTINA indiyse zafer
    $kalanYuzde = ($yeniCan / $maksCan) * 100;
    $videoTuru  = ($kalanYuzde < CANAVAR_ZAFER_ESIGI) ? 'zafer' : 'canavar_kazandi';

    $pdo->beginTransaction();

    $pdo->prepare(
        "UPDATE dunya_canavari SET mevcut_can = ?, durum = ?, savas_yapildi_mi = 1 WHERE id = ?"
    )->execute([$yeniCan, $yeniDurum, $canavar['id']]);

    /* ---- 4. Gelecek ayın canavarını hazırla ---- */
    $gelecekAy = date('Y-m', strtotime('+1 month'));

    $kontrol = $pdo->prepare("SELECT id FROM dunya_canavari WHERE savas_tarihi = ?");
    $kontrol->execute([$gelecekAy]);

    $yeniCanavarBilgi = null;

    if (!$kontrol->fetch()) {

        if ($olduMu) {
            /* ÖLDÜ → yumurtadan yeniden doğar, öldüren güçten %15 fazlasıyla */
            $yeniMax = max(CANAVAR_TABAN_CAN, (int) round($toplamHasar * CANAVAR_YUMURTA_CARPANI));
            $tur     = 'yeni';
            $adi     = 'Gizemli Ejderha Yumurtası';
            $baslangicCan = $yeniMax;

        } else {
            /* ÖLMEDİ → yumurtaya DÖNMEZ. Dev olarak kalır, iyileşir ve büyür.
               İyileşme Türkiye ordusunun gücüyle orantılıdır. */
            $yeniMax = (int) round($maksCan + ($toplamHasar * CANAVAR_BUYUME_CARPANI));
            $tur     = 'dev';
            $adi     = 'Kadim Dev Ejderha';
            $baslangicCan = $yeniMax;   // Yaralarını tamamen sarar
        }

        $ekle = $pdo->prepare(
            "INSERT INTO dunya_canavari (adi, mevcut_can, max_can, durum, savas_tarihi, savas_yapildi_mi, tur)
             VALUES (?, ?, ?, 'aktif', ?, 0, ?)"
        );
        $ekle->execute([$adi, $baslangicCan, $yeniMax, $gelecekAy, $tur]);

        $yeniCanavarBilgi = ['tur' => $tur, 'max_can' => $yeniMax, 'adi' => $adi];
    }

    $pdo->commit();

    echo json_encode([
        'status'        => 'basarili',
        'success'       => true,
        'verilen_hasar' => $toplamHasar,
        'kalan_can'     => $yeniCan,
        'kalan_yuzde'   => round($kalanYuzde, 1),
        'oldu_mu'       => $olduMu,
        'video'         => $videoTuru,
        'gelecek_ay'    => $yeniCanavarBilgi,
        'mesaj'         => $olduMu
            ? "Canavar öldürüldü! Gelecek ay yumurtadan daha güçlüsü doğacak."
            : "Canavar hayatta kaldı ({$yeniCan} HP). Yaralarını sarıp daha da büyüyecek.",
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'hata',
        'mesaj'  => 'SQL HATASI: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
