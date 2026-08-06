<?php
/* ====================================================================
 * MODÜL: Öğrenci Birleşik Radarı
 * DOSYA: api/ogrenci_radar.php
 * BAĞLI OLDUĞU ANA DOSYALAR: bilesenler/duello_radari.php,
 *   api/quiz_baskin.php, js/skorboard_motoru.js, bilesenler/ogrenci_modal.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğrencinin sayfası arka planda sürekli "yeni bir şey var mı?" diye sorar.
 * Eskiden bunu DÖRT ayrı dosyaya ayrı ayrı soruyordu:
 *   - api/duello/dinle.php          (düello isteği geldi mi?)
 *   - api/quiz_durum_kontrol.php    (quiz başladı mı?) [2026-08: SİLİNDİ]
 *   - api/arena_durum_kontrol.php   (savaş arenası açıldı mı?)
 *   - api/student_sync.php?dinle    (öğretmen uzaktan onay verdi mi?)
 *
 * 30 kişilik bir sınıfta bu, saniyede onlarca gereksiz istek demekti.
 * Bu dosya dördünü TEK cevapta toplar (Bilinen Sorunlar #11).
 *
 * CEVAP YAPISI:
 * {
 *   durum: "aktif|kilitli|onay_bekliyor|uzaktan_onaylandi|uzaktan_reddedildi",
 *   arena: { acik: bool },
 *   quiz:  { aktif: bool, oturum_id: int|null },
 *   duello:{ arena_aktif: bool, gelen_istek: bool, arenaya_git: bool,
 *            duello_id: int|null, meydan_okuyan_ad: string|null }
 * }
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu; dört polling tek istekte birleştirildi.
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();

$cevap = [
    'durum'  => 'aktif',
    // [2026-08] Öğretmen kilidi — bilesenler/ogrenci_kilit_perdesi.php dinler
    'kilit'  => ['var' => false, 'mesaj' => null, 'saniye' => 0],
    'arena'  => ['acik' => false],
    'quiz'   => ['aktif' => false, 'oturum_id' => null],
    'duello' => [
        'arena_aktif'      => false,
        'gelen_istek'      => false,
        'arenaya_git'      => false,
        'duello_id'        => null,
        'meydan_okuyan_ad' => null,
    ],
];

try {
    /* ---- 1. Öğrencinin kimlik ve durum bilgisi + öğretmen şalterleri (TEK sorgu) ----
     *
     * [2026-08 HATA DÜZELTMESİ] Öğretmen eskiden `o.ogretmen_id` üzerinden
     * bulunuyordu. O sütun denormalize bir kopya; öğrenci başka sınıfa
     * taşınınca ya da sınıf devredilince güncellenmiyor. Sonuç: quiz açılıyor
     * ama öğrenciye davet gitmiyor, arena/düello şalterleri de yanlış öğretmenden
     * okunuyordu.
     *
     * Gerçek zincir: ogretmenler -> siniflar -> ogrenciler
     * Öğretmeni SINIFTAN türetiyoruz; ogrenciler.ogretmen_id yalnızca sınıfı
     * olmayan eski kayıtlar için yedek olarak kalıyor (COALESCE).
     */
    $st = $pdo->prepare(
        "SELECT o.durum,
                o.sinif_id,
                COALESCE(sn.ogretmen_id, o.ogretmen_id) AS ogretmen_id,
                o.ogretmen_kilidi,
                o.kilit_mesaji,
                TIMESTAMPDIFF(SECOND, o.kilit_zamani, NOW()) AS kilit_gecen,
                ogr.arena_bitis, ogr.duello_bitis
           FROM ogrenciler o
      LEFT JOIN siniflar    sn  ON sn.id  = o.sinif_id
      LEFT JOIN ogretmenler ogr ON ogr.id = COALESCE(sn.ogretmen_id, o.ogretmen_id)
          WHERE o.id = ?"
    );
    $st->execute([$ogrenci_id]);
    $ogr = $st->fetch(PDO::FETCH_ASSOC);

    if (!$ogr) {
        apiJson($cevap);
    }

    $cevap['durum'] = $ogr['durum'] ?: 'aktif';

    /* ---- Öğretmen kilidi ----
       Kilitliyse öğrencinin ekranı donar. Diğer bildirimleri (düello daveti,
       quiz baskını) yine de gönderiyoruz; perde zaten üstlerini kapatıyor ve
       kilit açıldığı an öğrenci güncel durumu görüyor. */
    if ((int) ($ogr['ogretmen_kilidi'] ?? 0) === 1) {
        $cevap['kilit'] = [
            'var'    => true,
            'mesaj'  => $ogr['kilit_mesaji'] ?: null,
            'saniye' => max(0, (int) ($ogr['kilit_gecen'] ?? 0)),
        ];
    }

    // Kilit açıldıysa hata sayacını sıfırla
    if ($cevap['durum'] !== 'kilitli') {
        unset($_SESSION['hata_sayaci']);
    }

    $cevap['arena']['acik']          = ($ogr['arena_bitis'] && strtotime($ogr['arena_bitis']) > time());
    $cevap['duello']['arena_aktif']  = ($ogr['duello_bitis'] && strtotime($ogr['duello_bitis']) > time());

    /* ---- 2. Sınıfa açılmış aktif quiz oturumu ---- */
    if (!empty($ogr['sinif_id'])) {
        $st = $pdo->prepare(
            "SELECT id FROM quiz_oturum
              WHERE durum = 'aktif' AND sinif_id = ? AND ogretmen_id = ?
              ORDER BY id DESC LIMIT 1"
        );
        $st->execute([$ogr['sinif_id'], $ogr['ogretmen_id']]);
        $oturum = $st->fetch(PDO::FETCH_ASSOC);

        if ($oturum) {
            $cevap['quiz'] = ['aktif' => true, 'oturum_id' => (int) $oturum['id']];
        }
    }

    /* ---- 3. Düello: kabul edilen meydan okumam var mı? ---- */
    $st = $pdo->prepare(
        "SELECT id FROM quiz_duellolar
          WHERE meydan_okuyan_id = ? AND durum = 'kabul_edildi'
          ORDER BY id DESC LIMIT 1"
    );
    $st->execute([$ogrenci_id]);
    $kabulEdilen = $st->fetch(PDO::FETCH_ASSOC);

    if ($kabulEdilen && ($_SESSION['son_gidilen_saldiri'] ?? null) != $kabulEdilen['id']) {
        $_SESSION['son_gidilen_saldiri']  = $kabulEdilen['id'];
        $cevap['duello']['arenaya_git']   = true;
        $cevap['duello']['duello_id']     = (int) $kabulEdilen['id'];

        apiJson($cevap);
    }

    /* ---- 4. Düello: bana gelen meydan okuma (sadece aynı sınıftan) ---- */
    $st = $pdo->prepare(
        "SELECT d.id, o.ad_soyad AS meydan_okuyan_ad
           FROM quiz_duellolar d
           JOIN ogrenciler o   ON d.meydan_okuyan_id = o.id
           JOIN ogrenciler ben ON ben.id = d.meydan_okunan_id
          WHERE d.meydan_okunan_id = ?
            AND d.durum = 'bekliyor'
            AND o.sinif_id = ben.sinif_id
          ORDER BY d.id ASC LIMIT 1"
    );
    $st->execute([$ogrenci_id]);
    $gelen = $st->fetch(PDO::FETCH_ASSOC);

    if ($gelen) {
        $_SESSION['son_gidilen_savunma']       = $gelen['id'];
        $cevap['duello']['gelen_istek']        = true;
        $cevap['duello']['duello_id']          = (int) $gelen['id'];
        $cevap['duello']['meydan_okuyan_ad']   = $gelen['meydan_okuyan_ad'];
    }

    apiJson($cevap);

} catch (Throwable $e) {
    apiJson($cevap);
}
