<?php
/* ============================================================================
 * MODÜL: Quiz Skor Tablosu (öğrenci + öğretmen ORTAK)
 * DOSYA: api/quiz_skor_tablosu.php
 * BAĞLI OLDUĞU ANA DOSYALAR: quiz_arena.php, admin/quiz_kontrol_merkezi.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Savaş bitince herkesin gördüğü sıralama tablosu. Öğretmenin ekranındaki
 * tablonun AYNISI öğrencilere de gider — kimse "ben kaçıncı oldum" diye
 * öğretmene sormak zorunda kalmasın.
 *
 * NEDEN TEK UÇ NOKTA?
 * İki ayrı dosya yazılsaydı sıralama mantığı iki yerde durur, biri
 * değişince öğretmenle öğrenci farklı tablo görürdü. Tek kaynak var;
 * çağıranın kim olduğuna göre yetki kontrolü değişiyor, veri değil.
 *
 * YETKİ:
 *   Öğretmen -> yalnızca KENDİ açtığı oturumu görebilir
 *   Öğrenci  -> yalnızca KENDİ SINIFINA açılmış oturumu görebilir
 *               (sahiplik zinciri: ogretmenler -> siniflar -> ogrenciler)
 *
 * ⚡ PERFORMANS: Katılımcı sayısından bağımsız 2 sorgu.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (savaş sonu skor tablosu).
 * ========================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

guvenliOturumBaslat();

$gelen     = istekVerisi();
$oturum_id = (int) ($gelen['oturum_id'] ?? ($_GET['oturum_id'] ?? 0));

if ($oturum_id <= 0) {
    apiHata('Oturum seçilmedi.');
}

$ogrenci_id  = (int) ($_SESSION['ogrenci_id']  ?? 0);
$ogretmen_id = (int) ($_SESSION['ogretmen_id'] ?? 0);

if ($ogrenci_id <= 0 && $ogretmen_id <= 0) {
    apiHata('Bu tabloyu görmek için giriş yapmalısınız.', 401);
}

try {
    /* ------------------------------------------------------------------
     * 1. OTURUM + YETKİ (tek sorgu)
     * ---------------------------------------------------------------- */
    if ($ogretmen_id > 0) {
        // Öğretmen: oturum benim mi?
        $st = $pdo->prepare(
            "SELECT qo.id, qo.durum, qo.baslangic_zaman, qo.bitis_zaman, s.sinif_adi
               FROM quiz_oturum qo
          LEFT JOIN siniflar s ON s.id = qo.sinif_id
              WHERE qo.id = ? AND qo.ogretmen_id = ?"
        );
        $st->execute([$oturum_id, $ogretmen_id]);
    } else {
        /* Öğrenci: oturum benim sınıfıma mı açıldı?
           ⚠️ ogrenciler.ogretmen_id KULLANILMIYOR — o denormalize kopya
           sınıf devredilince bayatlıyor. Zincir siniflar üzerinden. */
        $st = $pdo->prepare(
            "SELECT qo.id, qo.durum, qo.baslangic_zaman, qo.bitis_zaman, s.sinif_adi
               FROM quiz_oturum qo
               JOIN ogrenciler o ON o.sinif_id = qo.sinif_id
               JOIN siniflar   s ON s.id       = o.sinif_id
              WHERE qo.id = ? AND o.id = ? AND s.ogretmen_id = qo.ogretmen_id"
        );
        $st->execute([$oturum_id, $ogrenci_id]);
    }

    $oturum = $st->fetch(PDO::FETCH_ASSOC);

    if (!$oturum) {
        apiHata('Bu savaşın tablosunu görme yetkiniz yok.', 403);
    }

    /* ------------------------------------------------------------------
     * 2. SIRALAMA (tek sorgu, katılımcı sayısından bağımsız)
     * ---------------------------------------------------------------- */
    $st = $pdo->prepare(
        "SELECT qog.ogrenci_id,
                o.ad_soyad,
                qog.puan,
                qog.cozulen_soru_sayisi
           FROM quiz_ogrenci qog
           JOIN ogrenciler o ON o.id = qog.ogrenci_id
          WHERE qog.oturum_id = ?
          ORDER BY qog.puan DESC, qog.cozulen_soru_sayisi DESC, o.ad_soyad ASC"
    );
    $st->execute([$oturum_id]);
    $satirlar = $st->fetchAll(PDO::FETCH_ASSOC);

    /* Sıra numarası ver + "ben hangisiyim" işaretle */
    $tablo   = [];
    $benimSira = 0;
    $sira    = 0;

    foreach ($satirlar as $r) {
        $sira++;
        $bu = ((int) $r['ogrenci_id'] === $ogrenci_id);
        if ($bu) {
            $benimSira = $sira;
        }

        $tablo[] = [
            'sira'    => $sira,
            'ad'      => $r['ad_soyad'],
            'puan'    => (int) $r['puan'],
            'cozulen' => (int) $r['cozulen_soru_sayisi'],
            'ben'     => $bu,
        ];
    }

    apiBasari([
        'oturum_id'  => (int) $oturum['id'],
        'durum'      => $oturum['durum'],
        'sinif_adi'  => $oturum['sinif_adi'] ?? '',
        'katilimci'  => count($tablo),
        'benim_sira' => $benimSira,
        'tablo'      => $tablo,
    ]);

} catch (Throwable $e) {
    apiIstisna($e, 'Skor tablosu alınamadı.');
}
