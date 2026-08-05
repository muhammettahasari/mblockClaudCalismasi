<?php
/* ====================================================================
 * MODÜL: Düello Cevap Değerlendirici
 * DOSYA: api/duello/cevap.php
 * BAĞLI OLDUĞU ANA DOSYA: duello_arena.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Düelloda ilk cevap veren kişinin şıkkını değerlendirir, kazananı belirler
 * ve puanları dağıtır. İlk doğru bilen maçı bitirir.
 *
 * PUANLAMA:
 *  - Meydan okuyan bilirse : okuyan +5 / okunan -3
 *  - Meydan okunan bilirse : okunan +6 / okuyan -6
 *  - Yanlış bilirse        : sadece yanlış bilen ceza yer (kazanan_id = -1)
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] GÜVENLİK AÇIĞI KAPATILDI (Bilinen Sorunlar #7):
 *      Eskiden düello numarasını bilen HERKES cevap gönderip puan
 *      dağıtabiliyordu. Artık sadece düellonun iki tarafından biri
 *      cevap verebilir.
 *  - [2026-08] Puanlar transaction içinde ve 0'ın altına düşmeyecek
 *      şekilde (GREATEST) işleniyor.
 * ==================================================================== */

require_once __DIR__ . '/../../bilesenler/guvenlik.php';
require_once __DIR__ . '/../../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();

$duello_id     = isset($_POST['duello_id']) ? (int) $_POST['duello_id'] : 0;
$verilen_cevap = strtoupper(trim($_POST['cevap'] ?? ''));

if ($duello_id <= 0 || !in_array($verilen_cevap, ['A', 'B', 'C', 'D'], true)) {
    apiJson(['status' => 'gec_kaldin']);
}

try {
    $st = $pdo->prepare(
        "SELECT d.*, s.dogru_cevap
           FROM quiz_duellolar d
           JOIN quiz_sorulari s ON d.soru_id = s.id
          WHERE d.id = ? AND d.durum = 'kabul_edildi'"
    );
    $st->execute([$duello_id]);
    $duello = $st->fetch(PDO::FETCH_ASSOC);

    if (!$duello) {
        apiJson(['status' => 'gec_kaldin']);
    }

    $meydan_okuyan = (int) $duello['meydan_okuyan_id'];
    $meydan_okunan = (int) $duello['meydan_okunan_id'];

    // GÜVENLİK: Cevap veren gerçekten bu düellonun tarafı mı?
    if ($ogrenci_id !== $meydan_okuyan && $ogrenci_id !== $meydan_okunan) {
        apiHata('Bu düellonun tarafı değilsiniz.', 403);
    }

    $dogru_cevap   = strtoupper(trim((string) $duello['dogru_cevap']));
    $benOkuyanMiyim = ($ogrenci_id === $meydan_okuyan);
    $bildiMi        = ($verilen_cevap === $dogru_cevap);

    if ($bildiMi) {
        $kazanan_id = $ogrenci_id;
        if ($benOkuyanMiyim) {
            $okuyan_puan = 5;  $okunan_puan = -3;
        } else {
            $okunan_puan = 6;  $okuyan_puan = -6;
        }
    } else {
        // Yanlış bildi: kazanan yok, sadece yanlış bilen ceza yer
        $kazanan_id = -1;
        if ($benOkuyanMiyim) {
            $okuyan_puan = -6; $okunan_puan = 0;
        } else {
            $okunan_puan = -3; $okuyan_puan = 0;
        }
    }

    $pdo->beginTransaction();

    // Yarış durumu koruması: sadece hâlâ 'kabul_edildi' ise bitir
    $bitir = $pdo->prepare(
        "UPDATE quiz_duellolar SET durum = 'bitti', kazanan_id = ?
          WHERE id = ? AND durum = 'kabul_edildi'"
    );
    $bitir->execute([$kazanan_id, $duello_id]);

    if ($bitir->rowCount() === 0) {
        // Rakip milisaniyeler önce cevaplamış
        $pdo->rollBack();
        apiJson(['status' => 'gec_kaldin']);
    }

    $puanEkle = $pdo->prepare("UPDATE ogrenciler SET kalici_puan = GREATEST(0, kalici_puan + ?) WHERE id = ?");
    $puanEkle->execute([$okuyan_puan, $meydan_okuyan]);
    $puanEkle->execute([$okunan_puan, $meydan_okunan]);

    $pdo->commit();

    apiJson([
        'status'      => 'bitti',
        'success'     => true,
        'kazandim_mi' => ($kazanan_id === $ogrenci_id),
        'berabere_mi' => ($kazanan_id === -1),
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiIstisna($e, 'Cevap işlenemedi.');
}
