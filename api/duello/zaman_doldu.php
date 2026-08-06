<?php
/* ============================================================================
 * MODÜL: Düello Zaman Aşımı
 * DOSYA: api/duello/zaman_doldu.php
 * BAĞLI OLDUĞU ANA DOSYA: duello_arena.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Sorunun süresi dolduğunda maçı kapatır. Ama artık körü körüne "ikiniz de
 * kaybettiniz" demiyor — o ana kadar gelen cevaplara bakıyor.
 *
 * ⚖️ [2026-08] SÜRE ÖLÇÜMÜ DEĞİŞTİ, BURASI DA UYARLANDI
 * Cevaplar artık anında maçı bitirmiyor; sunucu iki oyuncuyu da bekliyor
 * (bkz. api/duello/cevap.php). Dolayısıyla süre dolduğunda üç durum olabilir:
 *
 *   1. Biri doğru bildi, öteki hiç basmadı  -> BİLEN KAZANIR
 *      (Eskiden bu durumda ikisi de ceza yiyordu; doğru bilen haksızlığa
 *       uğruyordu çünkü rakibi sessiz kaldı diye maç berabere sayılıyordu.)
 *   2. Basanlar var ama hiçbiri doğru değil -> kazanan_id = -1
 *   3. Kimse basmadı                         -> kazanan_id = -2 (gerçek zaman aşımı)
 *
 * PUANLAR:
 *   Okuyan kazanır : okuyan +5 / okunan -3
 *   Okunan kazanır : okunan +6 / okuyan -6
 *   Kimse bilemedi : okuyan -6 / okunan -3
 *   Zaman aşımı    : okuyan -5 / okunan -3
 *
 * GÜVENLİK:
 *  - [2026-08] Sadece maçın tarafları çağırabilir (Bilinen Sorunlar #7).
 *      Eskiden HİÇ oturum kontrolü yoktu; düello numarasını bilen herkes
 *      istediği maçı zaman aşımına uğratıp iki öğrencinin puanını düşürebiliyordu.
 *  - Puanlar 0'ın altına düşmüyor (GREATEST), işlem transaction içinde.
 * ========================================================================== */

require_once __DIR__ . '/../../bilesenler/guvenlik.php';
require_once __DIR__ . '/../../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();
$duello_id  = isset($_POST['duello_id']) ? (int) $_POST['duello_id'] : 0;

if ($duello_id <= 0) {
    apiJson(['status' => 'zaman_doldu']);
}

try {
    $st = $pdo->prepare(
        "SELECT d.*, s.dogru_cevap
           FROM quiz_duellolar d
           LEFT JOIN quiz_sorulari s ON s.id = d.soru_id
          WHERE d.id = ? AND d.durum = 'kabul_edildi'"
    );
    $st->execute([$duello_id]);
    $duello = $st->fetch(PDO::FETCH_ASSOC);

    if (!$duello) {
        // Maç zaten bitmiş — istemci sonucu senkron.php'den öğrenir
        apiJson(['status' => 'bitmis']);
    }

    $meydan_okuyan = (int) $duello['meydan_okuyan_id'];
    $meydan_okunan = (int) $duello['meydan_okunan_id'];

    // GÜVENLİK: Sadece maçın tarafları zaman aşımı bildirebilir
    if ($ogrenci_id !== $meydan_okuyan && $ogrenci_id !== $meydan_okunan) {
        apiHata('Bu düellonun tarafı değilsiniz.', 403);
    }

    /* ------------------------------------------------------------------
     * O ANA KADAR NE GELDİ?
     * ---------------------------------------------------------------- */
    $dogru       = strtoupper(trim((string) $duello['dogru_cevap']));
    $okuyanBasti = !empty($duello['okuyan_cevap']);
    $okunanBasti = !empty($duello['okunan_cevap']);
    $okuyanDogru = $okuyanBasti && (strtoupper((string) $duello['okuyan_cevap']) === $dogru);
    $okunanDogru = $okunanBasti && (strtoupper((string) $duello['okunan_cevap']) === $dogru);

    if ($okuyanDogru && $okunanDogru) {
        // İkisi de bilmiş ama cevap.php bitirmeye yetişememiş — süreye bak
        $kazanan_id = ((int) $duello['okuyan_sure_ms'] < (int) $duello['okunan_sure_ms'])
                    ? $meydan_okuyan : $meydan_okunan;
        $okuyan_puan = ($kazanan_id === $meydan_okuyan) ?  5 : -6;
        $okunan_puan = ($kazanan_id === $meydan_okuyan) ? -3 :  6;

    } elseif ($okuyanDogru) {
        $kazanan_id  = $meydan_okuyan;
        $okuyan_puan = 5;  $okunan_puan = -3;

    } elseif ($okunanDogru) {
        $kazanan_id  = $meydan_okunan;
        $okunan_puan = 6;  $okuyan_puan = -6;

    } elseif ($okuyanBasti || $okunanBasti) {
        // Basan(lar) var ama doğru bilen yok
        $kazanan_id  = -1;
        $okuyan_puan = -6; $okunan_puan = -3;

    } else {
        // Gerçek zaman aşımı: ikisi de hiç basmadı
        $kazanan_id  = -2;
        $okuyan_puan = -5; $okunan_puan = -3;
    }

    $pdo->beginTransaction();

    // Yarış koruması
    $bitir = $pdo->prepare(
        "UPDATE quiz_duellolar SET durum = 'bitti', kazanan_id = ?
          WHERE id = ? AND durum = 'kabul_edildi'"
    );
    $bitir->execute([$kazanan_id, $duello_id]);

    if ($bitir->rowCount() === 0) {
        $pdo->rollBack();
        apiJson(['status' => 'bitmis']);
    }

    $puan = $pdo->prepare("UPDATE ogrenciler SET kalici_puan = GREATEST(0, kalici_puan + ?) WHERE id = ?");
    $puan->execute([$okuyan_puan, $meydan_okuyan]);
    $puan->execute([$okunan_puan, $meydan_okunan]);

    $pdo->commit();

    apiJson([
        'status'      => ($kazanan_id === -2) ? 'zaman_doldu' : 'bitti',
        'success'     => true,
        'kazandim_mi' => ($kazanan_id === $ogrenci_id),
        'berabere_mi' => ($kazanan_id === -1),
        'zaman_doldu' => ($kazanan_id === -2),
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiJson(['status' => 'zaman_doldu']);
}
