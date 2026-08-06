<?php
/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/api/v2_ipucu.php
 * KULLANICI / SAYFA: Öğrenci -> v2_gorev_sistemi/ogrenci/oynatici.php
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * "💡 Bilgi Al" düğmesinin arkasındaki dosya. İki soruya cevap verir:
 *   durum : Bu görevde bilgi var mı? Öğrenci daha önce aldı mı? Alırsa kaç
 *           puan ve elmas kaybeder?
 *   al    : Öğrenci "kabul ediyorum" dedi — kaydet ve bilgiyi gönder.
 *
 * ⚠️ BİLGİ, "durum" ADIMINDA GÖNDERİLMEZ.
 * Uyarı ekranı çıkarken bilgiyi de yollasaydık öğrenci ağ sekmesinden (F12)
 * cevabı okuyup ceza almadan görebilirdi. İçerik SADECE 'al' işleminde,
 * kayıt düşüldükten sonra gönderilir.
 *
 * ⚠️ CEZA SUNUCUDA DURUR.
 * "Bilgi alındı" bilgisi `v2_ipucu_alindi` tablosunda. Tarayıcıda tutulsaydı
 * öğrenci bilgiyi alır, F5'e basar, ceza uçardı. Puanı yazan yer
 * (v2_gorev_tamamla.php) bu tabloya bakıp ödülü yarıya indiriyor.
 *
 * YUVARLAMA: Yarım puan olmaz. round() ile EN YAKIN tam sayıya yuvarlanır
 * (25 -> 13). En az 1 verilir; "doğru çözdüm ama sıfır aldım" olmasın.
 *
 * BAĞLI OLDUĞU TABLOLAR:
 *   v2_gorevler        icerik_json içindeki `ipucu` alanı
 *   v2_ipucu_alindi    öğrenci x görev, bilgi alındı kaydı
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu. Önceden "Bilgi Al" düğmesi görev metninde
 *    "ipucu" kelimesi arayan bir tahmin motoruydu ve neredeyse her zaman
 *    "bu görev için ayrı bir ipucu tanımlanmamış" diyordu.
 * ============================================================================
 */

require_once __DIR__ . '/../../bilesenler/guvenlik.php';
require_once __DIR__ . '/../../bilesenler/api_yanit.php';
require_once __DIR__ . '/../../bilesenler/ilerleme_yardimcilari.php';

$ogrenci_id = ogrenciZorunluApi();

$gelen    = istekVerisi();
$islem    = beyazListe($gelen['islem'] ?? 'durum', ['durum', 'al'], '');
$gorev_id = (int) ($gelen['gorev_id'] ?? 0);

if ($islem === '')   { apiHata('Tanımsız işlem.'); }
if ($gorev_id <= 0)  { apiHata('Görev seçilmedi.'); }

/**
 * Bilgi alındığında verilecek ödül. Yarısı, en yakına yuvarlanmış, en az 1.
 * Bu fonksiyonun ikizi v2_gorev_tamamla.php'de DEĞİL — orası bunu require edip
 * çağırıyor ki iki yerde farklı yuvarlama olmasın.
 */
function ipuculuOdul(int $tamPuan): int
{
    return max(1, (int) round($tamPuan / 2));
}

/** İçerikte gerçekten gösterilecek bir şey var mı? */
function ipucuDoluMu(?array $ipucu): bool
{
    if (!$ipucu) { return false; }

    $metinVar = !empty($ipucu['metin']['blocks']) && is_array($ipucu['metin']['blocks']);
    $blokVar  = !empty(trim((string) ($ipucu['bloklar'] ?? '')));

    return $metinVar || $blokVar;
}

try {
    /* ---- Görev gerçekten var ve öğrenci ona erişebiliyor mu? ---- */
    $st = $pdo->prepare("SELECT id, bolum_id, puan, icerik_json, yayinda FROM v2_gorevler WHERE id = ?");
    $st->execute([$gorev_id]);
    $gorev = $st->fetch(PDO::FETCH_ASSOC);

    if (!$gorev || (int) $gorev['yayinda'] !== 1) {
        apiHata('Görev bulunamadı.', 404);
    }

    /* Anti-hile: ulaşmadığı bölümün ipucunu okuyamasın */
    $acik = erisilebilirBolumler($pdo, $ogrenci_id);
    if (!in_array((int) $gorev['bolum_id'], $acik, true)) {
        apiHata('Bu görev sana henüz açılmadı.', 403);
    }

    $icerik = json_decode($gorev['icerik_json'] ?? '{}', true) ?: [];
    $ipucu  = is_array($icerik['ipucu'] ?? null) ? $icerik['ipucu'] : null;
    $doluMu = ipucuDoluMu($ipucu);

    $tamPuan   = max(1, (int) $gorev['puan']);
    $yariPuan  = ipuculuOdul($tamPuan);

    /* Daha önce almış mı? */
    $st = $pdo->prepare("SELECT 1 FROM v2_ipucu_alindi WHERE ogrenci_id = ? AND v2_gorev_id = ? LIMIT 1");
    $st->execute([$ogrenci_id, $gorev_id]);
    $alinmis = (bool) $st->fetchColumn();

    /* ====================================================================
     * DURUM — uyarı ekranı için. İÇERİK GÖNDERİLMEZ.
     * ================================================================== */
    if ($islem === 'durum') {
        apiBasari([
            'var'        => $doluMu,
            'alinmis'    => $alinmis,
            'tam_puan'   => $tamPuan,
            'yari_puan'  => $yariPuan,
            /* Zaten almışsa içeriği tekrar göstermek ek ceza değil —
               bir kez ödedi, istediği kadar bakabilir. */
            'icerik'     => ($alinmis && $doluMu) ? $ipucu : null,
        ]);
    }

    /* ====================================================================
     * AL — öğrenci kabul etti
     * ================================================================== */
    if (!$doluMu) {
        apiHata('Bu görev için bilgi tanımlanmamış.', 404);
    }

    /* Ders kilidi açıkken hiçbir şey yapılamaz (perde konsoldan silinebilir) */
    kilitliyseReddet($pdo, $ogrenci_id);

    /* INSERT IGNORE: ikinci kez basılırsa yeni satır açılmaz, ceza da
       ikinci kez uygulanmaz. UNIQUE anahtar bunu garantiliyor. */
    $pdo->prepare(
        "INSERT IGNORE INTO v2_ipucu_alindi (ogrenci_id, v2_gorev_id) VALUES (?, ?)"
    )->execute([$ogrenci_id, $gorev_id]);

    apiBasari([
        'icerik'    => $ipucu,
        'yari_puan' => $yariPuan,
        'tam_puan'  => $tamPuan,
    ], 'Bilgi açıldı. Bu görevden ' . $yariPuan . ' puan ve ' . $yariPuan . ' 💎 kazanabilirsin.');

} catch (Throwable $e) {
    apiIstisna($e, 'Bilgi alınamadı.');
}
