<?php
/**
 * ============================================================================
 * DOSYA YOLU: api/ogrenci_ilerleme_sifirla.php
 * KULLANICI / SAYFA: Öğretmen -> Öğrenci İlerleme Yönetimi (ogrenci_ilerleme.php)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Öğretmen "bu öğrenci 1. bölümü gerçekten öğrenmemiş / kopya çekmiş"
 * dediğinde, seçtiği bölümleri öğrenciye TEKRAR yaptırmak için sıfırlar.
 *
 * CEZA MODELİ (kullanıcı kararı, 2026-08):
 *   - kalici_puan'a DOKUNULMAZ. Puan lig sıralamasını, arena gücünü ve
 *     canavar hasarını etkilediği için geri alınması zincirleme bozuyor.
 *   - Ceza SADECE ELMAS üzerinden verilir: sıfırlanan görevlerden kazandığı
 *     elmas kadar elmas geri alınır.
 *   - Elmas EKSİYE DÜŞEBİLİR. Öğrenci elmasını harcamışsa bile ceza
 *     hissedilir; borcunu görevleri tekrar çözerek kapatır.
 *   - Öğrencinin avatarında kırmızı "ceza" efekti belirir. Bu efekt için
 *     ayrı bir sütun tutulmaz; sıfırlanmış görev kalmayınca kendiliğinden
 *     kalkar (bkz. bilesenler/ilerleme_yardimcilari.php -> ogrencininCezasi).
 *
 * SATIR SİLİNMEZ, kazanilan_puan = 0 YAPILIR:
 *   deneme_sayisi ve geçmiş korunur; öğretmen "kaç denemede çözmüştü"
 *   bilgisini kaybetmez.
 *
 * GÜVENLİK:
 *   - Öğretmen SADECE kendi sınıfındaki öğrenciyi sıfırlayabilir.
 *   - Kimlik oturumdan alınır, POST'tan değil.
 *   - Bölüm id listesi tamsayıya zorlanır (SQL enjeksiyonu imkansız).
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu.
 * ============================================================================
 */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';
require_once __DIR__ . '/../bilesenler/ilerleme_yardimcilari.php';

$ogretmen_id = ogretmenZorunluApi();

$gelen = istekVerisi();
$islem = beyazListe($gelen['islem'] ?? 'sifirla', ['sifirla', 'onizleme', 'harita'], 'sifirla');

$ogrenci_id = (int) ($gelen['ogrenci_id'] ?? 0);
$bolumIdler = array_values(array_unique(array_filter(
    array_map('intval', (array) ($gelen['bolum_idler'] ?? [])),
    fn($v) => $v > 0
)));
$sebep = mb_substr(trim((string) ($gelen['sebep'] ?? '')), 0, 255);

if ($ogrenci_id <= 0) {
    apiHata('Öğrenci seçilmedi.');
}

/* ---- ÇOK KİRACILIK: bu öğrenci gerçekten bu öğretmenin mi? ---- */
if (!ogretmeninOgrencisiMi($pdo, $ogretmen_id, $ogrenci_id)) {
    apiHata('Bu öğrenci senin sınıfında değil.', 403);
}

/* ========================================================================
 * HARİTA — öğretmen ✏️ kalemine bastığında SADECE O ÖĞRENCİNİN haritası
 *
 * [2026-08 PERFORMANS] Eskiden sayfa açılırken sınıftaki HER öğrenci için
 * ilerlemeHaritasi() çalıştırılıp hepsi JavaScript'e gömülüyordu; 40 kişilik
 * bir sınıfta 40 ekstra sorgu demekti ve öğretmen belki hiçbirine
 * tıklamayacaktı. Artık harita tıklandığı anda, tek öğrenci için çekiliyor.
 * ====================================================================== */
if ($islem === 'harita') {
    try {
        ilerlemeOnbelleginiTemizle($ogrenci_id);

        $st = $pdo->prepare("SELECT ad_soyad FROM ogrenciler WHERE id = ?");
        $st->execute([$ogrenci_id]);

        apiBasari([
            'ogrenci_adi' => (string) ($st->fetchColumn() ?: ''),
            'harita'      => ilerlemeHaritasi($pdo, $ogrenci_id),
        ]);
    } catch (Throwable $e) {
        apiIstisna($e, 'Öğrencinin haritası alınamadı.');
    }
}

if (!$bolumIdler) {
    apiHata('Tekrar ettirilecek en az bir bölüm seçmelisin.');
}

try {
    /* ------------------------------------------------------------------
     * 1. SIFIRLANACAK GÖREVLERİ VE ELMAS CEZASINI HESAPLA
     *    Sadece GERÇEKTEN ÇÖZÜLMÜŞ (kazanilan_puan > 0) görevler sayılır.
     *    Zaten sıfırlanmış bir görevi ikinci kez cezalandırmayız —
     *    yoksa öğretmen iki kez tıklayınca çift ceza yazardı.
     * ---------------------------------------------------------------- */
    $yerTutucu = implode(',', array_fill(0, count($bolumIdler), '?'));

    $st = $pdo->prepare(
        "SELECT i.id, i.v2_gorev_id, i.kazanilan_puan, g.gorev_adi, g.bolum_id, b.bolum_adi
           FROM v2_ogrenci_ilerleme i
           JOIN v2_gorevler g ON g.id = i.v2_gorev_id
      LEFT JOIN bolumler   b ON b.id = g.bolum_id
          WHERE i.ogrenci_id = ?
            AND g.bolum_id IN ({$yerTutucu})
            AND i.kazanilan_puan > 0"
    );
    $st->execute(array_merge([$ogrenci_id], $bolumIdler));
    $hedefler = $st->fetchAll(PDO::FETCH_ASSOC);

    if (!$hedefler) {
        apiHata('Seçtiğin bölümlerde öğrencinin tamamladığı görev yok. Sıfırlanacak bir şey bulunamadı.');
    }

    // v2'de puan = elmas (v2_gorev_tamamla.php ikisini de aynı miktarda yazar)
    $elmasCezasi = 0;
    foreach ($hedefler as $h) {
        $elmasCezasi += (int) $h['kazanilan_puan'];
    }

    /* ------------------------------------------------------------------
     * ÖNİZLEME — öğretmen onay ekranında "ne olacak" görsün, yazma yok
     * ---------------------------------------------------------------- */
    if ($islem === 'onizleme') {
        $st = $pdo->prepare("SELECT ad_soyad, elmas FROM ogrenciler WHERE id = ?");
        $st->execute([$ogrenci_id]);
        $o = $st->fetch(PDO::FETCH_ASSOC) ?: ['ad_soyad' => '', 'elmas' => 0];

        apiBasari([
            'ogrenci_adi'   => $o['ad_soyad'],
            'gorev_sayisi'  => count($hedefler),
            'gorevler'      => array_map(fn($h) => [
                'ad'    => $h['gorev_adi'],
                'bolum' => $h['bolum_adi'],
                'puan'  => (int) $h['kazanilan_puan'],
            ], $hedefler),
            'elmas_cezasi'  => $elmasCezasi,
            'mevcut_elmas'  => (int) $o['elmas'],
            'yeni_elmas'    => (int) $o['elmas'] - $elmasCezasi,
        ]);
    }

    /* ------------------------------------------------------------------
     * 2. SIFIRLA (tek işlemde — yarım kalırsa hepsi geri alınır)
     * ---------------------------------------------------------------- */
    $pdo->beginTransaction();

    $ilerlemeIdler = array_map(fn($h) => (int) $h['id'], $hedefler);
    $yerTutucu2    = implode(',', array_fill(0, count($ilerlemeIdler), '?'));

    $pdo->prepare(
        "UPDATE v2_ogrenci_ilerleme
            SET kazanilan_puan         = 0,
                sifirlanma_tarihi      = NOW(),
                sifirlayan_ogretmen_id = ?,
                sifirlama_sebebi       = ?,
                sifirlama_sayisi       = sifirlama_sayisi + 1
          WHERE id IN ({$yerTutucu2})"
    )->execute(array_merge([$ogretmen_id, ($sebep !== '' ? $sebep : null)], $ilerlemeIdler));

    /* ---- ELMAS CEZASI — eksiye düşebilir, taban yok (kullanıcı kararı) ---- */
    $pdo->prepare("UPDATE ogrenciler SET elmas = elmas - ? WHERE id = ?")
        ->execute([$elmasCezasi, $ogrenci_id]);

    /* ---- Öğrenciyi seçilen en küçük sıralı bölüme geri çek ---- */
    $st = $pdo->prepare(
        "SELECT MIN(sira_no) FROM bolumler WHERE id IN ({$yerTutucu})"
    );
    $st->execute($bolumIdler);
    $enKucukSira = max(1, (int) ($st->fetchColumn() ?: 1));

    // mevcut_bolum bir "kilit tavanı"dır; geri çekmiyoruz ki öğrenci daha
    // ileri bölümlere de bakabilsin. Ama sıradaki görev artık sıfırlanan
    // görev olacağı için öğrenci doğal olarak oraya yönlendirilir.
    $pdo->prepare("UPDATE ogrenciler SET aktif_v2_gorev_id = NULL WHERE id = ?")
        ->execute([$ogrenci_id]);

    /* ---- Günlüğe yaz (öğrenciye bildirim buradan gösterilir) ---- */
    $pdo->prepare(
        "INSERT INTO v2_sifirlama_gunlugu
            (ogrenci_id, ogretmen_id, bolum_idler, gorev_sayisi, elmas_cezasi, sebep)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([
        $ogrenci_id,
        $ogretmen_id,
        implode(',', $bolumIdler),
        count($hedefler),
        $elmasCezasi,
        ($sebep !== '' ? $sebep : null),
    ]);

    $pdo->commit();

    ilerlemeOnbelleginiTemizle($ogrenci_id);

    /* ---- Güncel bakiye ---- */
    $st = $pdo->prepare("SELECT ad_soyad, elmas FROM ogrenciler WHERE id = ?");
    $st->execute([$ogrenci_id]);
    $o = $st->fetch(PDO::FETCH_ASSOC) ?: ['ad_soyad' => '', 'elmas' => 0];

    apiBasari([
        'gorev_sayisi'  => count($hedefler),
        'elmas_cezasi'  => $elmasCezasi,
        'yeni_elmas'    => (int) $o['elmas'],
        'en_kucuk_sira' => $enKucukSira,
    ], sprintf(
        '%s adlı öğrencinin %d görevi sıfırlandı. %d 💎 ceza uygulandı (yeni bakiye: %d 💎).',
        $o['ad_soyad'],
        count($hedefler),
        $elmasCezasi,
        (int) $o['elmas']
    ));

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiIstisna($e, 'Sıfırlama işlemi tamamlanamadı.');
}
