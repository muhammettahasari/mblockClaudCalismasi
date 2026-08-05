<?php
/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/api/v2_gorev_tamamla.php
 * KULLANICI / SAYFA: Öğrenci -> Görev Oynatıcı (oynatici.php)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Öğrenci "Görevi Teslim Et" dediğinde dizdiği blokları buraya gönderir.
 * Bu dosya cevabı SUNUCUDA kontrol eder; doğruysa puanı ve elması ANINDA
 * öğrencinin hesabına yazar. v1'deki "bekleyen puan + öğretmen şifresi"
 * adımı v2'de yoktur — v2'nin yazılma amacı zaten buydu.
 *
 * NEDEN SUNUCUDA KONTROL EDİLİYOR?
 * Kontrol tarayıcıda yapılsaydı öğrenci konsoldan "doğru bildim" diyerek
 * bedava puan alabilirdi. Karşılaştırma v2_hakem.php içinde, sunucuda yapılır.
 *
 * TEMEL İŞLEMLER:
 *  1. Öğrencinin oturumunu ve görevin yayında olup olmadığını doğrular
 *  2. Öğrencinin blok haritasını cevap anahtarıyla karşılaştırır
 *  3. Doğruysa puan + elmas ekler, v2_ogrenci_ilerleme'ye kaydeder
 *  4. Aynı görevden ikinci kez puan verilmez (tekrar çözülebilir ama puansız)
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu — v2 artık v1 puan sistemine bağlı (#17).
 * ============================================================================
 */

require_once __DIR__ . '/../../bilesenler/guvenlik.php';
require_once __DIR__ . '/../../bilesenler/api_yanit.php';
require_once __DIR__ . '/v2_hakem.php';

$ogrenci_id = ogrenciZorunluApi();

$gelen       = istekVerisi();
$gorev_id    = (int) ($gelen['gorev_id'] ?? 0);
$ogrenciXml  = (string) ($gelen['cozum_xml'] ?? '');

if ($gorev_id <= 0) {
    apiHata('Görev bilgisi eksik.');
}
if (trim($ogrenciXml) === '') {
    apiHata('Henüz hiç blok eklememişsin! Önce kodunu kur.');
}

// Aşırı büyük gönderimlere karşı basit fren (1 MB)
if (strlen($ogrenciXml) > 1048576) {
    apiHata('Çözüm çok büyük, lütfen gereksiz blokları temizle.');
}

try {
    /* ---- 1. Görev var mı, yayında mı? ---- */
    $st = $pdo->prepare("SELECT id, bolum_id, gorev_adi, puan, icerik_json, yayinda FROM v2_gorevler WHERE id = ?");
    $st->execute([$gorev_id]);
    $gorev = $st->fetch(PDO::FETCH_ASSOC);

    if (!$gorev) {
        apiHata('Görev bulunamadı.', 404);
    }
    if ((int) $gorev['yayinda'] !== 1) {
        apiHata('Bu görev şu anda yayında değil.', 403);
    }

    /* ---- 2. Öğrenci bu bölüme ulaşmış mı? (anti-hile) ---- */
    $st = $pdo->prepare("SELECT mevcut_bolum FROM ogrenciler WHERE id = ?");
    $st->execute([$ogrenci_id]);
    $seviye = max(1, (int) ($st->fetchColumn() ?: 1));

    if ((int) $gorev['bolum_id'] > $seviye) {
        apiHata('Bu bölüme henüz ulaşmadınız.', 403);
    }

    /* ---- 3. HAKEM: çözüm doğru mu? ---- */
    $icerik    = json_decode($gorev['icerik_json'] ?? '{}', true) ?: [];
    $blueprint = $icerik['blueprint'] ?? [];

    if (empty($blueprint['ana_cozum'])) {
        apiHata('Bu görevin cevap anahtarı henüz tanımlanmamış. Öğretmenine haber ver.', 409);
    }

    $karar = cozumDogruMu($ogrenciXml, $blueprint);

    /* ---- 4. Denemeyi kaydet (doğru/yanlış fark etmez) ---- */
    $st = $pdo->prepare("SELECT id, kazanilan_puan FROM v2_ogrenci_ilerleme WHERE ogrenci_id = ? AND v2_gorev_id = ?");
    $st->execute([$ogrenci_id, $gorev_id]);
    $oncekiKayit = $st->fetch(PDO::FETCH_ASSOC);

    if (!$karar['dogru']) {
        if ($oncekiKayit) {
            $pdo->prepare("UPDATE v2_ogrenci_ilerleme SET deneme_sayisi = deneme_sayisi + 1 WHERE id = ?")
                ->execute([$oncekiKayit['id']]);
        }

        $cevap = [
            'success' => false,
            'status'  => 'error',
            'durum'   => 'yanlis',
            'dogru'   => false,
            'mesaj'   => $karar['sebep'],
            'message' => $karar['sebep'],
        ];

        /* [2026-08] SORUN AYIKLAMA BİLGİSİ (sadece localhost)
           "Hiç blok eklememişsin" gibi hataların kaynağını görebilmek için
           kaç blok okunduğunu tarayıcı konsoluna gönderiyoruz. Canlı
           sunucuda bu blok çalışmaz. */
        if (in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true)) {
            $cevap['teshis'] = $karar['teshis'] ?? [];
            $cevap['teshis']['gelen_xml_onizleme'] = mb_substr($ogrenciXml, 0, 400);
        }

        apiJson($cevap);
    }

    /* ---- 5. DOĞRU: daha önce puan alınmış mı? ---- */
    if ($oncekiKayit && (int) $oncekiKayit['kazanilan_puan'] > 0) {
        $pdo->prepare("UPDATE v2_ogrenci_ilerleme SET deneme_sayisi = deneme_sayisi + 1 WHERE id = ?")
            ->execute([$oncekiKayit['id']]);

        apiJson([
            'success'       => true,
            'status'        => 'success',
            'durum'         => 'basarili',
            'dogru'         => true,
            'puan_verildi'  => false,
            'kazanilan'     => 0,
            'mesaj'         => '🎉 Doğru! Ama bu görevden puanı daha önce almıştın.',
            'message'       => '🎉 Doğru! Ama bu görevden puanı daha önce almıştın.',
        ]);
    }

    /* ---- 6. Puanı ANINDA hesaba yaz ---- */
    $kazanilan = max(1, (int) $gorev['puan']);

    $pdo->beginTransaction();

    // v1 ile aynı ekonomi: puan sıralama için, elmas harcama için
    $pdo->prepare(
        "UPDATE ogrenciler
            SET kalici_puan = kalici_puan + ?, elmas = elmas + ?
          WHERE id = ?"
    )->execute([$kazanilan, $kazanilan, $ogrenci_id]);

    if ($oncekiKayit) {
        $pdo->prepare(
            "UPDATE v2_ogrenci_ilerleme
                SET kazanilan_puan = ?, deneme_sayisi = deneme_sayisi + 1, tamamlanma_tarihi = NOW()
              WHERE id = ?"
        )->execute([$kazanilan, $oncekiKayit['id']]);
    } else {
        $pdo->prepare(
            "INSERT INTO v2_ogrenci_ilerleme (ogrenci_id, v2_gorev_id, kazanilan_puan, deneme_sayisi)
             VALUES (?, ?, ?, 1)"
        )->execute([$ogrenci_id, $gorev_id, $kazanilan]);
    }

    // Genel arşive de düş (v1 ile ortak tablo)
    $pdo->prepare(
        "INSERT INTO ogrenci_ilerleme (ogrenci_id, bolum_id, kaynak, durum, bekleyen_puan)
         VALUES (?, ?, 'v2', 'onaylandi', ?)"
    )->execute([$ogrenci_id, $gorev['bolum_id'], $kazanilan]);

    $pdo->commit();

    /* ---- 7. Bölümün tamamı bitti mi? Bittiyse seviye atlat ---- */
    $st = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM v2_gorevler WHERE bolum_id = ? AND yayinda = 1) AS toplam,
            (SELECT COUNT(*) FROM v2_ogrenci_ilerleme i
                JOIN v2_gorevler g ON g.id = i.v2_gorev_id
               WHERE g.bolum_id = ? AND g.yayinda = 1
                 AND i.ogrenci_id = ? AND i.kazanilan_puan > 0) AS cozulen"
    );
    $st->execute([$gorev['bolum_id'], $gorev['bolum_id'], $ogrenci_id]);
    $ilerleme = $st->fetch(PDO::FETCH_ASSOC);

    $bolumBitti = false;
    if ($ilerleme && (int) $ilerleme['toplam'] > 0
        && (int) $ilerleme['cozulen'] >= (int) $ilerleme['toplam']
        && (int) $gorev['bolum_id'] >= $seviye) {

        $pdo->prepare(
            "UPDATE ogrenciler SET mevcut_bolum = mevcut_bolum + 1, can = 100, enerji = 100 WHERE id = ?"
        )->execute([$ogrenci_id]);
        $bolumBitti = true;
    }

    // Güncel bakiye
    $st = $pdo->prepare("SELECT kalici_puan, elmas FROM ogrenciler WHERE id = ?");
    $st->execute([$ogrenci_id]);
    $bakiye = $st->fetch(PDO::FETCH_ASSOC) ?: ['kalici_puan' => 0, 'elmas' => 0];

    apiJson([
        'success'      => true,
        'status'       => 'success',
        'durum'        => 'basarili',
        'dogru'        => true,
        'puan_verildi' => true,
        'kazanilan'    => $kazanilan,
        'kalici_puan'  => (int) $bakiye['kalici_puan'],
        'elmas'        => (int) $bakiye['elmas'],
        'bolum_bitti'  => $bolumBitti,
        'eslesen'      => $karar['eslesen'],
        'mesaj'        => "🎉 Tebrikler! Görevi doğru çözdün. +{$kazanilan} Puan ve +{$kazanilan} 💎 kazandın!",
        'message'      => "🎉 Tebrikler! Görevi doğru çözdün. +{$kazanilan} Puan ve +{$kazanilan} 💎 kazandın!",
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiIstisna($e, 'Görev teslim edilemedi.');
}
