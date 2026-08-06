<?php
/**
 * ============================================================================
 * DOSYA YOLU: api/sinif_ilerleme_durumu.php
 * KULLANICI / SAYFA: Öğretmen -> Öğrenci İlerleme Takibi (canlı tablo)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Öğretmenin ilerleme tablosunu F5'e basmadan canlı tutar. Öğrenci bir görevi
 * bitirdiği anda öğretmenin ekranındaki yüzde, bölüm adı ve ceza rozeti
 * kendiliğinden güncellenir.
 *
 * ⚡ PERFORMANS — EN ÖNEMLİ KISIM:
 * Bu dosya sınıf kaç kişilik olursa olsun HEP 5 SORGU çalıştırır:
 *   1. Sınıf sahiplik kontrolü             (LIMIT 1)
 *   2. Bölümler                            (bolumleriGetir, önbellekli)
 *   3. Bölümlerin yayındaki görevleri      (bolumleriGetir, önbellekli)
 *   4. Öğrencilerin temel bilgileri        (tek sorgu, tüm sınıf)
 *   5. Öğrenci x bölüm çözülen görev sayısı(tek GROUP BY, tüm sınıf)
 *
 * Öğrenci başına döngü içinde sorgu ATILMAZ. 40 kişilik bir sınıf için de
 * 5 sorgu, 400 kişilik için de 5 sorgu — yani maliyet öğrenci sayısıyla
 * BÜYÜMEZ. Bu yüzden 10 saniyede bir yoklamak sunucuyu zorlamaz.
 *
 * ÖLÇEKLENME NOTU (ileride binlerce eşzamanlı öğrenci olursa):
 * Yoklama aralığını 10 sn'den 20-30 sn'ye çıkarmak yükü doğrudan yarıya/üçte
 * bire indirir; tek değişiklik ogrenci_ilerleme.php içindeki ARALIK sabiti.
 * Ondan sonraki adım bu cevabı 5 saniyelik bir önbelleğe (APCu/Redis) almak
 * olur; sorgular zaten sınıf başına tek olduğu için önbellek anahtarı
 * doğrudan sinif_id olabilir.
 *
 * Ayrıca cevaba bir "damga" (özet imza) konur. Tarayıcı damga değişmediyse
 * tabloyu yeniden çizmez — boşuna DOM işi yapılmaz.
 *
 * GÜVENLİK:
 *   Öğretmen SADECE kendi sınıfını sorgulayabilir (sinif.ogretmen_id kontrolü).
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (öğretmen tablosu canlı hale getirildi).
 * ============================================================================
 */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';
require_once __DIR__ . '/../bilesenler/ilerleme_yardimcilari.php';

$ogretmen_id = ogretmenZorunluApi();

$gelen    = istekVerisi();
$sinif_id = (int) ($gelen['sinif_id'] ?? 0);

if ($sinif_id <= 0) {
    apiHata('Sınıf seçilmedi.');
}

/* ---- ÇOK KİRACILIK: bu sınıf gerçekten bu öğretmenin mi? ---- */
$st = $pdo->prepare("SELECT 1 FROM siniflar WHERE id = ? AND ogretmen_id = ? LIMIT 1");
$st->execute([$sinif_id, $ogretmen_id]);
if (!$st->fetchColumn()) {
    apiHata('Bu sınıf sana ait değil.', 403);
}

try {
    /* ====================================================================
     * SORGU 1 — Bölümler ve her birindeki yayınlanmış görev sayısı
     * (bolumleriGetir zaten önbellekli; iki hafif sorgu çalıştırır)
     * ================================================================== */
    $bolumler = bolumleriGetir($pdo);

    $bolumBilgi   = [];   // bolum_id => ['ad'=>..,'sira'=>..,'toplam'=>..]
    $toplamGorev  = 0;
    foreach ($bolumler as $b) {
        $adet = count($b['gorevler']);
        if ($adet === 0) {
            continue;   // boş bölüm ilerleme hesabına girmez
        }
        $bolumBilgi[(int) $b['id']] = [
            'ad'     => $b['bolum_adi'],
            'sira'   => (int) $b['sira_no'],
            'toplam' => $adet,
        ];
        $toplamGorev += $adet;
    }

    /* ====================================================================
     * SORGU 2 — Sınıftaki öğrenciler (tek sorgu)
     * ================================================================== */
    $st = $pdo->prepare(
        "SELECT id, ad_soyad, elmas, kalici_puan
           FROM ogrenciler
          WHERE sinif_id = ? AND onay_durumu = 1
          ORDER BY ad_soyad ASC"
    );
    $st->execute([$sinif_id]);
    $ogrenciler = $st->fetchAll(PDO::FETCH_ASSOC);

    if (!$ogrenciler) {
        apiBasari(['damga' => 'bos', 'ogrenciler' => []]);
    }

    /* ====================================================================
     * SORGU 3 — Öğrenci x Bölüm kırılımında çözülen ve cezalı görev sayısı
     * TEK GROUP BY ile tüm sınıf. Öğrenci başına sorgu YOK.
     * ================================================================== */
    $st = $pdo->prepare(
        "SELECT i.ogrenci_id,
                g.bolum_id,
                SUM(CASE WHEN i.kazanilan_puan > 0 THEN 1 ELSE 0 END) AS cozulen,
                SUM(CASE WHEN i.kazanilan_puan = 0
                          AND i.sifirlanma_tarihi IS NOT NULL THEN 1 ELSE 0 END) AS cezali
           FROM v2_ogrenci_ilerleme i
           JOIN v2_gorevler g ON g.id = i.v2_gorev_id AND g.yayinda = 1
           JOIN ogrenciler  o ON o.id = i.ogrenci_id
          WHERE o.sinif_id = ?
          GROUP BY i.ogrenci_id, g.bolum_id"
    );
    $st->execute([$sinif_id]);

    $sayaclar = [];   // ogrenci_id => bolum_id => ['cozulen'=>..,'cezali'=>..]
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $sayaclar[(int) $r['ogrenci_id']][(int) $r['bolum_id']] = [
            'cozulen' => (int) $r['cozulen'],
            'cezali'  => (int) $r['cezali'],
        ];
    }

    /* ====================================================================
     * PHP tarafında birleştir (sorgu yok, sadece dizi işlemi)
     * ================================================================== */
    $cikti = [];
    foreach ($ogrenciler as $o) {
        $oid    = (int) $o['id'];
        $kendi  = $sayaclar[$oid] ?? [];

        $cozulenToplam = 0;
        $cezaliToplam  = 0;
        $suAnkiBolum   = '—';
        $bulundu       = false;

        foreach ($bolumBilgi as $bid => $bilgi) {
            $c = $kendi[$bid]['cozulen'] ?? 0;
            $z = $kendi[$bid]['cezali']  ?? 0;

            $cozulenToplam += $c;
            $cezaliToplam  += $z;

            // "Bulunduğu bölüm" = tamamlanmamış ilk bölüm
            if (!$bulundu && $c < $bilgi['toplam']) {
                $suAnkiBolum = $bilgi['ad'];
                $bulundu     = true;
            }
        }

        if (!$bulundu && $cozulenToplam > 0) {
            $suAnkiBolum = '🏁 Tümü tamam';
        }

        $cikti[] = [
            'id'      => $oid,
            'ad'      => $o['ad_soyad'],
            'bolum'   => $suAnkiBolum,
            'cozulen' => $cozulenToplam,
            'toplam'  => $toplamGorev,
            'yuzde'   => $toplamGorev > 0 ? (int) round($cozulenToplam / $toplamGorev * 100) : 0,
            'elmas'   => (int) $o['elmas'],
            'puan'    => (int) $o['kalici_puan'],
            'cezali'  => $cezaliToplam,
        ];
    }

    /* Damga: içerik değişmediyse tarayıcı tabloyu yeniden çizmesin */
    $damga = md5(json_encode($cikti));

    apiBasari([
        'damga'      => $damga,
        'ogrenciler' => $cikti,
    ]);

} catch (Throwable $e) {
    apiIstisna($e, 'Sınıf durumu alınamadı.');
}
