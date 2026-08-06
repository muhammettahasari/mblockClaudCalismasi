<?php
/* ============================================================================
 * MODÜL: Düello Cevap Değerlendirici
 * DOSYA: api/duello/cevap.php
 * BAĞLI OLDUĞU ANA DOSYA: duello_arena.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Düelloda iki oyuncunun cevabını toplar, KİM DAHA HIZLI DÜŞÜNDÜYSE onu
 * kazanan ilan eder ve puanları dağıtır.
 *
 * ⚖️ [2026-08] ADALET DÜZELTMESİ — EN ÖNEMLİ KISIM
 *
 * ESKİ SİSTEM: Bu dosyaya İLK ULAŞAN kazanıyordu ("ilk POST kazanır").
 * Bu haksızdı, çünkü iki oyuncunun ekranı aynı anda hazır olmuyor:
 *   - Biri sayfayı 1 saniye geç açar
 *   - Birinin interneti yavaştır, isteği 300 ms geç varır
 *   - Telefon/tarayıcı farkı geri sayımı kaydırır
 * Sonuçta daha GEÇ tıklayan biri, sırf ağı hızlı diye kazanabiliyordu.
 *
 * YENİ SİSTEM: Her oyuncunun tarayıcısı, SORU EKRANDA GÖRÜNDÜĞÜ AN kendi
 * kronometresini sıfırdan başlatır (performance.now()) ve cevabıyla birlikte
 * kaç MİLİSANİYEDE bastığını gönderir. Sunucu:
 *   1. Gelen cevabı kendi tarafına yazar
 *   2. Rakip henüz cevaplamadıysa BEKLER (status: 'bekleniyor')
 *   3. İki cevap da geldiğinde en küçük süreli DOĞRU cevap kazanır
 * Ağ gecikmesi ve ekranın geç açılması artık sonucu etkilemiyor.
 *
 * PUANLAMA:
 *  - Meydan okuyan kazanırsa : okuyan +5 / okunan -3
 *  - Meydan okunan kazanırsa : okunan +6 / okuyan -6
 *  - İkisi de bilemezse      : okuyan -6 / okunan -3  (kazanan_id = -1)
 *
 * GÜVENLİK:
 *  - [2026-08] Sadece düellonun iki tarafından biri cevap verebilir (#7)
 *  - Süre değeri istemciden geliyor; makul aralığa KIRPILIR (aşağıya bak)
 *  - Bir oyuncu cevabını yalnızca BİR KEZ gönderebilir
 *  - Puanlar transaction içinde ve 0'ın altına düşmeyecek şekilde (GREATEST)
 * ========================================================================== */

require_once __DIR__ . '/../../bilesenler/guvenlik.php';
require_once __DIR__ . '/../../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();

$duello_id     = isset($_POST['duello_id']) ? (int) $_POST['duello_id'] : 0;
$verilen_cevap = strtoupper(trim($_POST['cevap'] ?? ''));
$sure_ms_ham   = isset($_POST['sure_ms']) ? (int) $_POST['sure_ms'] : 0;

if ($duello_id <= 0 || !in_array($verilen_cevap, ['A', 'B', 'C', 'D'], true)) {
    apiJson(['status' => 'gecersiz']);
}

try {
    $st = $pdo->prepare(
        "SELECT d.*, s.dogru_cevap, s.sure AS soru_suresi
           FROM quiz_duellolar d
           JOIN quiz_sorulari s ON d.soru_id = s.id
          WHERE d.id = ? AND d.durum = 'kabul_edildi'"
    );
    $st->execute([$duello_id]);
    $duello = $st->fetch(PDO::FETCH_ASSOC);

    if (!$duello) {
        // Maç zaten bitmiş olabilir — istemci senkron.php'den sonucu öğrenir
        apiJson(['status' => 'bitmis']);
    }

    $meydan_okuyan = (int) $duello['meydan_okuyan_id'];
    $meydan_okunan = (int) $duello['meydan_okunan_id'];

    // GÜVENLİK: Cevap veren gerçekten bu düellonun tarafı mı?
    if ($ogrenci_id !== $meydan_okuyan && $ogrenci_id !== $meydan_okunan) {
        apiHata('Bu düellonun tarafı değilsiniz.', 403);
    }

    /* ------------------------------------------------------------------
     * SÜREYİ KIRP — istemciden gelen değere körü körüne güvenmiyoruz
     *
     * Kötü niyetli bir öğrenci "0 ms'de bildim" diye gönderebilir. Bu yüzden
     * değeri makul bir aralığa sıkıştırıyoruz:
     *   Alt sınır : 150 ms — insan görsel refleksinin altı. Daha küçük bir
     *               değer elle girilmiş demektir, 150'ye çekilir.
     *   Üst sınır : sorunun süresi + 2 sn tolerans
     * Taban iki oyuncu için de aynı olduğundan hile belirleyici olamıyor.
     * ---------------------------------------------------------------- */
    $tavanMs = (max(5, (int) $duello['soru_suresi']) + 2) * 1000;
    $sure_ms = max(150, min($sure_ms_ham > 0 ? $sure_ms_ham : $tavanMs, $tavanMs));

    $benOkuyanMiyim = ($ogrenci_id === $meydan_okuyan);

    /* Sütun adları sabit iki değerden biri — kullanıcı girdisi DEĞİL,
       $benOkuyanMiyim karşılaştırmasından türüyor. SQL enjeksiyonu yok. */
    $benimSutun = $benOkuyanMiyim ? 'okuyan' : 'okunan';
    $rakipSutun = $benOkuyanMiyim ? 'okunan' : 'okuyan';

    /* ------------------------------------------------------------------
     * 1. CEVABIMI KAYDET (yalnızca bir kez)
     * "... IS NULL" koşulu ikinci gönderimi sessizce yok sayar.
     * ---------------------------------------------------------------- */
    $kaydet = $pdo->prepare(
        "UPDATE quiz_duellolar
            SET {$benimSutun}_cevap = ?, {$benimSutun}_sure_ms = ?
          WHERE id = ? AND durum = 'kabul_edildi' AND {$benimSutun}_cevap IS NULL"
    );
    $kaydet->execute([$verilen_cevap, $sure_ms, $duello_id]);

    /* ------------------------------------------------------------------
     * 2. RAKİP CEVAPLADI MI?
     * ---------------------------------------------------------------- */
    $st = $pdo->prepare(
        "SELECT durum, okuyan_cevap, okuyan_sure_ms, okunan_cevap, okunan_sure_ms
           FROM quiz_duellolar WHERE id = ?"
    );
    $st->execute([$duello_id]);
    $son = $st->fetch(PDO::FETCH_ASSOC);

    if (!$son || $son['durum'] === 'bitti') {
        apiJson(['status' => 'bitmis']);
    }

    if (empty($son[$rakipSutun . '_cevap'])) {
        // Rakip henüz basmadı — maçı BİTİRMİYORUZ, onu bekliyoruz.
        // İstemci bu cevabı alınca "rakip bekleniyor" perdesi gösterir ve
        // senkron.php'yi yoklamaya devam eder.
        apiJson([
            'status'  => 'bekleniyor',
            'success' => true,
            'sure_ms' => $sure_ms,
            'mesaj'   => 'Cevabın kaydedildi. Rakibin bekleniyor…',
        ]);
    }

    /* ------------------------------------------------------------------
     * 3. İKİ CEVAP DA GELDİ — KAZANANI SÜREYE GÖRE BELİRLE
     * ---------------------------------------------------------------- */
    $dogru = strtoupper(trim((string) $duello['dogru_cevap']));

    $okuyanDogru = (strtoupper((string) $son['okuyan_cevap']) === $dogru);
    $okunanDogru = (strtoupper((string) $son['okunan_cevap']) === $dogru);
    $okuyanMs    = (int) $son['okuyan_sure_ms'];
    $okunanMs    = (int) $son['okunan_sure_ms'];

    if ($okuyanDogru && $okunanDogru) {
        /* İkisi de bildi -> DAHA HIZLI DÜŞÜNEN kazanır.
           Tam beraberlik (aynı milisaniye) neredeyse imkânsız; olursa
           savunan taraf (meydan okunan) lehine sayılır. */
        $kazanan_id = ($okuyanMs < $okunanMs) ? $meydan_okuyan : $meydan_okunan;
    } elseif ($okuyanDogru) {
        $kazanan_id = $meydan_okuyan;
    } elseif ($okunanDogru) {
        $kazanan_id = $meydan_okunan;
    } else {
        $kazanan_id = -1;   // ikisi de bilemedi
    }

    /* Puan dağılımı */
    if ($kazanan_id === $meydan_okuyan) {
        $okuyan_puan = 5;  $okunan_puan = -3;
    } elseif ($kazanan_id === $meydan_okunan) {
        $okunan_puan = 6;  $okuyan_puan = -6;
    } else {
        $okuyan_puan = -6; $okunan_puan = -3;
    }

    $pdo->beginTransaction();

    /* Yarış koruması: iki istemci aynı anda buraya girerse sadece biri bitirir */
    $bitir = $pdo->prepare(
        "UPDATE quiz_duellolar SET durum = 'bitti', kazanan_id = ?
          WHERE id = ? AND durum = 'kabul_edildi'"
    );
    $bitir->execute([$kazanan_id, $duello_id]);

    if ($bitir->rowCount() === 0) {
        $pdo->rollBack();
        apiJson(['status' => 'bitmis']);
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
        'benim_ms'    => $benOkuyanMiyim ? $okuyanMs : $okunanMs,
        'rakip_ms'    => $benOkuyanMiyim ? $okunanMs : $okuyanMs,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiIstisna($e, 'Cevap işlenemedi.');
}
