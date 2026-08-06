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
require_once __DIR__ . '/../../bilesenler/ilerleme_yardimcilari.php';
require_once __DIR__ . '/v2_hakem.php';

$ogrenci_id = ogrenciZorunluApi();

/* [2026-08] ÖĞRETMEN KİLİDİ
   Öğretmen dersi dondurduysa görev tamamlanamaz. Öğrencinin ekranındaki
   kilit perdesi sadece görsel bir engel — konsolu bilen bir öğrenci onu
   silebilir. Gerçek engel burada, sunucuda. */
kilitliyseReddet($pdo, $ogrenci_id);

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

    /* ---- 2. Öğrenci bu bölüme ulaşmış mı? (anti-hile) ----
       [2026-08] Eskiden "bolum_id > mevcut_bolum" karşılaştırması yapılıyordu.
       Bölümün id'si ile sıra numarası birbirinden farklı olabildiği için
       (silinen bölümler id'lerde boşluk bırakıyor) bu karşılaştırma yanlış
       sonuç veriyordu. Artık gerçek erişim listesine bakıyoruz. */
    $acikBolumler = erisilebilirBolumler($pdo, $ogrenci_id);

    if (!in_array((int) $gorev['bolum_id'], $acikBolumler, true)) {
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
    $st = $pdo->prepare("SELECT id, kazanilan_puan, sifirlanma_tarihi FROM v2_ogrenci_ilerleme WHERE ogrenci_id = ? AND v2_gorev_id = ?");
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

    /* ========================================================================
     * 6. ÖDÜLÜ ANINDA HESABA YAZ
     *
     * [2026-08 ÖNEMLİ KURAL — ÇİFT PUAN ÖNLEME]
     * Öğretmen bir görevi sıfırladığında CEZA SADECE ELMASTAN kesilir;
     * kalici_puan'a hiç dokunulmaz (puan lig sıralamasını, arena gücünü ve
     * canavar hasarını besliyor, geri alınması zincirleme bozuyor).
     *
     * Bu yüzden sıfırlanmış bir görev TEKRAR çözüldüğünde puanı ikinci kez
     * yazamayız — yoksa öğretmen her sıfırladığında öğrencinin puanı şişerdi
     * ve "ceza" ödüle dönerdi. Sadece kesilen elmas geri verilir.
     *
     *   İlk çözüm            -> +puan  +elmas
     *   Sıfırlama sonrası    -> +0     +elmas   (borcunu kapatıyor)
     * ====================================================================== */
    $kazanilan   = max(1, (int) $gorev['puan']);
    $sifirlanmis = $oncekiKayit && !empty($oncekiKayit['sifirlanma_tarihi']);

    /* ------------------------------------------------------------------
     * [2026-08] BİLGİ ALDIYSA ÖDÜL YARIYA İNER
     *
     * Öğrenci "💡 Bilgi Al" düğmesine basıp uyarıyı kabul ettiyse
     * v2_ipucu_alindi'ye satır düşmüştür. Ceza SUNUCUDA duruyor; tarayıcıda
     * tutulsaydı öğrenci bilgiyi alıp sayfayı yeniler, ceza uçardı.
     *
     * Yarım puan olmaz: round() ile en yakın tam sayıya (25 -> 13).
     * En az 1 verilir — "doğru çözdüm ama sıfır aldım" olmasın.
     * ---------------------------------------------------------------- */
    /* try/catch: migration çalıştırılmamışsa tablo yoktur. O durumda GÖREV
       TESLİMİ ÇÖKMEMELİ — öğrencinin emeği kaybolur. Tablo yoksa "bilgi
       alınmamış" sayılır, yani öğrenci tam puan alır. Şüpheden öğrenci
       yararlanır; tersi olsaydı hak edilen puan haksızca yarıya inerdi. */
    $ipucuAlindi = false;
    try {
        $st = $pdo->prepare("SELECT 1 FROM v2_ipucu_alindi WHERE ogrenci_id = ? AND v2_gorev_id = ? LIMIT 1");
        $st->execute([$ogrenci_id, $gorev_id]);
        $ipucuAlindi = (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        error_log('[mBlock] v2_ipucu_alindi okunamadı (migration çalıştırıldı mı?): ' . $e->getMessage());
    }

    if ($ipucuAlindi) {
        $kazanilan = max(1, (int) round($kazanilan / 2));
    }

    $puanOdulu  = $sifirlanmis ? 0 : $kazanilan;   // puan sadece ilk seferde
    $elmasOdulu = $kazanilan;                      // elmas her seferinde

    $pdo->beginTransaction();

    $pdo->prepare(
        "UPDATE ogrenciler
            SET kalici_puan = kalici_puan + ?, elmas = elmas + ?
          WHERE id = ?"
    )->execute([$puanOdulu, $elmasOdulu, $ogrenci_id]);

    if ($oncekiKayit) {
        /* kazanilan_puan sıfırdan büyük olunca görev tekrar "çözüldü" sayılır
           ve kırmızı ceza efekti bu görev için kalkar. sifirlanma_tarihi
           geçmiş kaydı olarak DURUR (öğretmen "bunu bir kez sıfırlamıştım"
           bilgisini kaybetmesin), ama artık cezalı sorguya takılmaz çünkü
           o sorgu kazanilan_puan = 0 şartını da arıyor. */
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
    )->execute([$ogrenci_id, $gorev['bolum_id'], $puanOdulu]);

    $pdo->commit();

    ilerlemeOnbelleginiTemizle($ogrenci_id);

    /* ========================================================================
     * 7. BÖLÜM BİTTİ Mİ? SEVİYE NEREYE GİTMELİ?
     *
     * [2026-08] "mevcut_bolum + 1" mantığı KALDIRILDI.
     * Öğretmen öğrenciyi 1. bölüme geri gönderebiliyor. Öğrenci 1'i tekrar
     * bitirdiğinde 2-3-4 zaten çözülüyse doğrudan 5'e dönmeli, 2'ye değil.
     * Bu yüzden seviye "tamamlanmamış ilk bölüm"den hesaplanır.
     * mevcutBolumuTazele() sütunu ASLA geri çekmez, sadece ileri taşır.
     * ====================================================================== */
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

    $bolumBitti = ($ilerleme && (int) $ilerleme['toplam'] > 0
                   && (int) $ilerleme['cozulen'] >= (int) $ilerleme['toplam']);

    if ($bolumBitti) {
        mevcutBolumuTazele($pdo, $ogrenci_id);   // ileri taşır, geri çekmez
        $pdo->prepare("UPDATE ogrenciler SET can = 100, enerji = 100 WHERE id = ?")
            ->execute([$ogrenci_id]);
    }

    /* ---- Ceza kapandı mı? (kırmızı efekt için) ---- */
    $ceza = ogrencininCezasi($pdo, $ogrenci_id);

    /* ========================================================================
     * SIRADAKİ GÖREVİ SUNUCU SÖYLER
     *
     * [2026-08 HATA DÜZELTMESİ] Kutlama kartı kapanınca tarayıcı sadece
     * "oynatici.php"ye gidiyordu ve sayfanın doğru görevi seçmesini umuyorduk.
     * Tarayıcı önbelleği ya da eski aktif_v2_gorev_id yüzünden öğrenci aynı
     * bölümde takılı kalabiliyordu.
     *
     * Artık sıradaki görevin kimliğini BURADA hesaplayıp cevaba koyuyoruz;
     * tarayıcı doğrudan "oynatici.php?gorev_id=N" adresine gidiyor.
     * Tahmin yok, önbellek sorunu yok.
     * ====================================================================== */
    $sonrakiGorevId = sonrakiGoreviBul($pdo, $ogrenci_id, $gorev_id);

    /* [2026-08 HATA DÜZELTMESİ — CEZADAN SONRA GERİ DÖNMEME]
     *
     * Burada eskiden "$sonrakiGorevId = $gorev_id" yazıyordu, yani çözülmemiş
     * görev kalmayınca öğrenci AZ ÖNCE ÇÖZDÜĞÜ görevde bırakılıyordu.
     *
     * Bu, tam olarak şu senaryoyu kırıyordu:
     *   Öğrenci 2. bölümü bitirmiş, öğretmen onu 1. bölüme geri gönderiyor.
     *   Öğrenci 1. bölümü tekrar bitiriyor -> ortada çözülmemiş görev yok
     *   (2. bölüm zaten çözülüydü) -> sonrakiGoreviBul null dönüyor ->
     *   öğrenci 1. bölümdeki ceza görevinde bırakılıyordu. Ekranda "cezan
     *   kalktı" yazıyor ama öğrenci hâlâ eski bölümde duruyordu.
     *
     * Doğrusu: çözülecek bir şey kalmadıysa öğrenciyi ULAŞTIĞI EN SON göreve
     * götür. Böylece 1. bölümü tekrar eden öğrenci, işi bitince kaldığı yere
     * (2. bölümün son görevine) döner.
     */
    if (!$sonrakiGorevId) {
        $sonrakiGorevId = sonErisilenGorev($pdo, $ogrenci_id) ?: $gorev_id;
    }

    $sonrakiAd = '';
    if ($sonrakiGorevId && $sonrakiGorevId !== $gorev_id) {
        $st = $pdo->prepare("SELECT gorev_adi FROM v2_gorevler WHERE id = ?");
        $st->execute([$sonrakiGorevId]);
        $sonrakiAd = (string) ($st->fetchColumn() ?: '');

        // Öğrenciyi oraya "kaldığın yer" olarak işaretle
        $pdo->prepare("UPDATE ogrenciler SET aktif_v2_gorev_id = ? WHERE id = ?")
            ->execute([$sonrakiGorevId, $ogrenci_id]);
    }

    // Güncel bakiye
    $st = $pdo->prepare("SELECT kalici_puan, elmas FROM ogrenciler WHERE id = ?");
    $st->execute([$ogrenci_id]);
    $bakiye = $st->fetch(PDO::FETCH_ASSOC) ?: ['kalici_puan' => 0, 'elmas' => 0];

    $mesaj = $sifirlanmis
        ? "🎉 Doğru! Öğretmenin tekrar istediği bu görevi çözdün, +{$elmasOdulu} 💎 geri kazandın."
          . ($ceza['cezali'] ? " (Tekrar edilecek {$ceza['gorev_sayisi']} görev kaldı.)" : ' Cezan tamamen kalktı!')
        : "🎉 Tebrikler! Görevi doğru çözdün. +{$puanOdulu} Puan ve +{$elmasOdulu} 💎 kazandın!";

    /* Bilgi aldıysa NEDEN yarım aldığını söyle — yoksa öğrenci sistemin
       yanlış saydığını düşünür. Sessiz ceza, ceza değil kafa karışıklığıdır. */
    if ($ipucuAlindi) {
        $mesaj .= ' (💡 Bilgi aldığın için ödül yarıya indi.)';
    }

    apiJson([
        'success'      => true,
        'status'       => 'success',
        'durum'        => 'basarili',
        'dogru'        => true,
        'puan_verildi' => true,
        'kazanilan'    => $kazanilan,
        'puan_odulu'   => $puanOdulu,
        'elmas_odulu'  => $elmasOdulu,
        'tekrar_cozum' => $sifirlanmis,
        'ipucu_alindi' => $ipucuAlindi,
        'kalici_puan'  => (int) $bakiye['kalici_puan'],
        'elmas'        => (int) $bakiye['elmas'],
        'bolum_bitti'  => $bolumBitti,
        'ceza'         => $ceza,
        'sonraki_gorev_id' => (int) $sonrakiGorevId,
        'sonraki_gorev_ad' => $sonrakiAd,
        'hepsi_bitti'      => ($sonrakiGorevId === $gorev_id),
        'eslesen'      => $karar['eslesen'],
        'mesaj'        => $mesaj,
        'message'      => $mesaj,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiIstisna($e, 'Görev teslim edilemedi.');
}
