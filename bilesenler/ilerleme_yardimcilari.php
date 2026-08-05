<?php
/**
 * ============================================================================
 * DOSYA YOLU: bilesenler/ilerleme_yardimcilari.php
 * KULLANICI / SAYFA: Ortak çekirdek — öğrenci oynatıcı, öğretmen paneli, API'ler
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * "Öğrenci nerede?" sorusunun TEK cevap merciidir.
 * Hangi bölümleri bitirdi, hangi görevi çözdü, sıradaki görevi hangisi,
 * öğretmen tarafından geri gönderildiği için cezalı mı — hepsi buradan sorulur.
 *
 * NEDEN TEK DOSYA?
 * Daha önce stat matrisi 3 ayrı dosyaya kopyalanmıştı ve biri değişince
 * diğerleri bozuluyordu. Aynı hatayı ilerleme mantığında yapmıyoruz.
 *
 * ÖNEMLİ TASARIM KARARI — "SIRADAKİ BÖLÜM" ARTIK +1 DEĞİL:
 * Eskiden bölüm bitince mevcut_bolum = mevcut_bolum + 1 yapılıyordu.
 * Artık öğretmen öğrenciyi 1. bölüme geri gönderebiliyor. Öğrenci 1'i
 * tekrar bitirince 2-3-4 zaten çözülüyse doğrudan 5'e dönmeli, 2'ye değil.
 * Bu yüzden "sıradaki bölüm" = TAMAMLANMAMIŞ İLK BÖLÜM olarak hesaplanır.
 *
 * TEMEL İŞLEVLER:
 *   bolumleriGetir()          — yayındaki bölümler + görevleri (önbellekli)
 *   ogrencininIlerlemesi()    — öğrencinin görev bazlı durum haritası
 *   ilerlemeHaritasi()        — ekrana basılmaya hazır tam harita
 *   ogrencininCezasi()        — bekleyen sıfırlama var mı, kaç görev
 *   erisilebilirBolumler()    — öğrencinin ulaştığı (kilidi açık) bölümler
 *   sonrakiBolumuHesapla()    — tamamlanmamış ilk bölüm
 *   mevcutBolumuTazele()      — ogrenciler.mevcut_bolum sütununu düzeltir
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (ilerleme çubuğu + öğretmen sıfırlama).
 * ============================================================================
 */

require_once __DIR__ . '/guvenlik.php';

/* Aynı istek içinde tekrar tekrar sorgu atmamak için hafıza önbelleği */
$GLOBALS['__ilerleme_onbellek'] = $GLOBALS['__ilerleme_onbellek'] ?? [];


/**
 * Yayındaki tüm bölümleri ve içindeki v2 görevlerini sıralı döndürür.
 *
 * Dönen yapı:
 * [
 *   ['id'=>1,'bolum_adi'=>'...', 'sira_no'=>1, 'gorevler'=>[
 *        ['id'=>7,'gorev_adi'=>'...','sira_no'=>1,'puan'=>20,'gorev_tipi'=>'standart'], ...
 *   ]], ...
 * ]
 *
 * NOT: Görevi olmayan bölümler de listede kalır (öğretmen boş bölümü görsün),
 * ama "tamamlandı" hesabına katılmazlar.
 */
function bolumleriGetir(PDO $pdo): array
{
    if (isset($GLOBALS['__ilerleme_onbellek']['bolumler'])) {
        return $GLOBALS['__ilerleme_onbellek']['bolumler'];
    }

    $bolumler = $pdo->query(
        "SELECT id, bolum_adi, sira_no FROM bolumler ORDER BY sira_no ASC, id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $harita = [];
    foreach ($bolumler as $b) {
        $b['id']       = (int) $b['id'];
        $b['sira_no']  = (int) $b['sira_no'];
        $b['gorevler'] = [];
        $harita[$b['id']] = $b;
    }

    if ($harita) {
        $gorevler = $pdo->query(
            "SELECT id, bolum_id, gorev_adi, sira_no, puan, gorev_tipi
               FROM v2_gorevler
              WHERE yayinda = 1
              ORDER BY bolum_id ASC, sira_no ASC, id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($gorevler as $g) {
            $bid = (int) $g['bolum_id'];
            if (!isset($harita[$bid])) {
                continue; // silinmiş bölüme bağlı yetim görev
            }
            $harita[$bid]['gorevler'][] = [
                'id'         => (int) $g['id'],
                'gorev_adi'  => $g['gorev_adi'],
                'sira_no'    => (int) $g['sira_no'],
                'puan'       => (int) $g['puan'],
                'gorev_tipi' => $g['gorev_tipi'],
            ];
        }
    }

    $sonuc = array_values($harita);
    $GLOBALS['__ilerleme_onbellek']['bolumler'] = $sonuc;
    return $sonuc;
}


/**
 * Öğrencinin v2 görev kayıtlarını gorev_id => satır şeklinde döndürür.
 * Sıfırlanmış görevler de gelir (kazanilan_puan = 0 olur).
 */
function ogrencininIlerlemesi(PDO $pdo, int $ogrenciId): array
{
    $anahtar = 'ilerleme_' . $ogrenciId;
    if (isset($GLOBALS['__ilerleme_onbellek'][$anahtar])) {
        return $GLOBALS['__ilerleme_onbellek'][$anahtar];
    }

    $st = $pdo->prepare(
        "SELECT v2_gorev_id, kazanilan_puan, deneme_sayisi,
                tamamlanma_tarihi, sifirlanma_tarihi, sifirlama_sebebi, sifirlama_sayisi
           FROM v2_ogrenci_ilerleme
          WHERE ogrenci_id = ?"
    );
    $st->execute([$ogrenciId]);

    $harita = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $satir) {
        $harita[(int) $satir['v2_gorev_id']] = $satir;
    }

    $GLOBALS['__ilerleme_onbellek'][$anahtar] = $harita;
    return $harita;
}


/** Öğrenciyle ilgili önbelleği temizler (puan yazıldıktan / sıfırlama sonrası çağır) */
function ilerlemeOnbelleginiTemizle(?int $ogrenciId = null): void
{
    if ($ogrenciId === null) {
        $GLOBALS['__ilerleme_onbellek'] = [];
        return;
    }
    unset($GLOBALS['__ilerleme_onbellek']['ilerleme_' . $ogrenciId]);
}


/**
 * ÖĞRENCİNİN TAM İLERLEME HARİTASI — ekrana basılmaya hazır.
 *
 * Her göreve bir "durum" etiketi verilir:
 *   'tamam'     yeşil  — çözüldü, puanı alındı
 *   'aktif'     turuncu— şu an oynanan / sıradaki görev
 *   'sifirlandi'kırmızı— öğretmen geri gönderdi, tekrar çözülmeli
 *   'bekliyor'  gri    — henüz sırası gelmedi
 *   'kilitli'   kilit  — bölümüne hiç ulaşılmadı
 *
 * @param int|null $aktifGorevId Öğrencinin şu an açık olan görevi (varsa)
 */
function ilerlemeHaritasi(PDO $pdo, int $ogrenciId, ?int $aktifGorevId = null): array
{
    $bolumler  = bolumleriGetir($pdo);
    $ilerleme  = ogrencininIlerlemesi($pdo, $ogrenciId);
    $erisilen  = erisilebilirBolumler($pdo, $ogrenciId);

    /* Aktif görev verilmediyse: ilk çözülmemiş erişilebilir görev aktiftir */
    if ($aktifGorevId === null) {
        $aktifGorevId = sonrakiGoreviBul($pdo, $ogrenciId);
    }

    $cikti           = [];
    $toplamGorev     = 0;
    $toplamCozulen   = 0;
    $bekleyenCeza    = 0;

    foreach ($bolumler as $bolum) {
        $bolumAcik   = in_array((int) $bolum['id'], $erisilen, true);
        $bolumGorev  = [];
        $bolumCozulen = 0;

        foreach ($bolum['gorevler'] as $gorev) {
            $kayit    = $ilerleme[$gorev['id']] ?? null;
            $cozuldu  = $kayit && (int) $kayit['kazanilan_puan'] > 0;
            $sifirli  = $kayit && !$cozuldu && !empty($kayit['sifirlanma_tarihi']);

            if (!$bolumAcik) {
                $durum = 'kilitli';
            } elseif ($cozuldu) {
                $durum = 'tamam';
            } elseif ($sifirli) {
                $durum = 'sifirlandi';
            } elseif ($gorev['id'] === $aktifGorevId) {
                $durum = 'aktif';
            } else {
                $durum = 'bekliyor';
            }

            // Aktif görev aynı zamanda sıfırlanmış olabilir; kırmızı öncelikli
            // ama "şu an buradasın" bilgisi de lazım:
            $buradaMi = ($gorev['id'] === $aktifGorevId);

            $bolumGorev[] = [
                'id'            => $gorev['id'],
                'ad'            => $gorev['gorev_adi'],
                'sira'          => $gorev['sira_no'],
                'puan'          => $gorev['puan'],
                'tip'           => $gorev['gorev_tipi'],
                'durum'         => $durum,
                'burada'        => $buradaMi,
                'deneme'        => $kayit ? (int) $kayit['deneme_sayisi'] : 0,
                'sifirlama_say' => $kayit ? (int) ($kayit['sifirlama_sayisi'] ?? 0) : 0,
                'sebep'         => $sifirli ? ($kayit['sifirlama_sebebi'] ?? null) : null,
            ];

            $toplamGorev++;
            if ($cozuldu)  { $bolumCozulen++; $toplamCozulen++; }
            if ($sifirli)  { $bekleyenCeza++; }
        }

        $adet = count($bolumGorev);
        $cikti[] = [
            'id'      => (int) $bolum['id'],
            'ad'      => $bolum['bolum_adi'],
            'sira'    => (int) $bolum['sira_no'],
            'acik'    => $bolumAcik,
            'gorevler'=> $bolumGorev,
            'toplam'  => $adet,
            'cozulen' => $bolumCozulen,
            'bitti'   => ($adet > 0 && $bolumCozulen >= $adet),
            'yuzde'   => $adet > 0 ? (int) round($bolumCozulen / $adet * 100) : 0,
        ];
    }

    return [
        'bolumler'      => $cikti,
        'aktif_gorev_id'=> $aktifGorevId,
        'toplam_gorev'  => $toplamGorev,
        'cozulen_gorev' => $toplamCozulen,
        'genel_yuzde'   => $toplamGorev > 0 ? (int) round($toplamCozulen / $toplamGorev * 100) : 0,
        'bekleyen_ceza' => $bekleyenCeza,
    ];
}


/**
 * Öğrencinin kilidi AÇIK bölüm id'leri.
 *
 * Kural: mevcut_bolum sütunu bir "tavan"dır — o sıradaki bölüme kadar
 * (dahil) her şey açıktır. Ayrıca öğrencinin daha önce çözdüğü herhangi bir
 * bölüm, öğretmen onu geri gönderse bile açık kalır (tekrar oynayabilsin).
 */
function erisilebilirBolumler(PDO $pdo, int $ogrenciId): array
{
    $st = $pdo->prepare("SELECT mevcut_bolum FROM ogrenciler WHERE id = ?");
    $st->execute([$ogrenciId]);
    $tavanSira = max(1, (int) ($st->fetchColumn() ?: 1));

    $bolumler = bolumleriGetir($pdo);
    $ilerleme = ogrencininIlerlemesi($pdo, $ogrenciId);

    /* mevcut_bolum tarihsel olarak hem "sıra no" hem "bolum id" gibi
       kullanılmış. İkisini de kabul ediyoruz: sıra_no <= tavan VEYA id <= tavan */
    $acik = [];
    foreach ($bolumler as $b) {
        $id   = (int) $b['id'];
        $sira = (int) $b['sira_no'];

        if ($sira <= $tavanSira || $id <= $tavanSira) {
            $acik[] = $id;
            continue;
        }

        // Daha önce bu bölümden en az bir görev çözmüşse kilit açık kalsın
        foreach ($b['gorevler'] as $g) {
            $k = $ilerleme[$g['id']] ?? null;
            if ($k && ((int) $k['kazanilan_puan'] > 0 || !empty($k['sifirlanma_tarihi']))) {
                $acik[] = $id;
                break;
            }
        }
    }

    return $acik;
}


/**
 * Öğrencinin erişebildiği TÜM görevleri sıralı düz liste hâlinde verir.
 * Her eleman: ['id','bolum_id','ad','cozuldu','sifirli']
 *
 * Sıralama: bölüm sıra_no -> görev sıra_no. "Sıradaki görev" hesapları
 * hep bu liste üzerinden yapılır, böylece tek bir sıralama tanımı olur.
 */
function erisilebilirGorevAkisi(PDO $pdo, int $ogrenciId): array
{
    $bolumler = bolumleriGetir($pdo);
    $ilerleme = ogrencininIlerlemesi($pdo, $ogrenciId);
    $erisilen = erisilebilirBolumler($pdo, $ogrenciId);

    $akis = [];
    foreach ($bolumler as $b) {
        if (!in_array((int) $b['id'], $erisilen, true)) {
            continue;
        }
        foreach ($b['gorevler'] as $g) {
            $k = $ilerleme[$g['id']] ?? null;
            $akis[] = [
                'id'       => (int) $g['id'],
                'bolum_id' => (int) $b['id'],
                'ad'       => $g['gorev_adi'],
                'cozuldu'  => (bool) ($k && (int) $k['kazanilan_puan'] > 0),
                'sifirli'  => (bool) ($k && (int) $k['kazanilan_puan'] <= 0 && !empty($k['sifirlanma_tarihi'])),
            ];
        }
    }
    return $akis;
}


/**
 * Öğrencinin oynaması gereken SIRADAKİ görevi bulur.
 *
 * ÖNCELİK SIRASI:
 *   1. ÖĞRETMENİN SIFIRLADIĞI görevler — önce ceza kapanır.
 *   2. Kalan çözülmemiş görevlerin ilki (bölüm sırası -> görev sırası).
 *   3. Hiçbiri yoksa null (öğrenci her şeyi bitirmiş).
 *
 * NEDEN CEZA AYRI BİR ADIM?
 * [2026-08 HATA DÜZELTMESİ] Eskiden "ilk çözülmemiş görev" yeterli sanılmıştı;
 * sıfırlanan görev zaten önde olur diye düşünülmüştü. Ama öğretmen görev
 * ekleyince sıralama değişebiliyor ve öğrenci ceza görevi ile yeni görev
 * arasında gidip geliyordu. Artık ceza görevleri açıkça öne alınıyor.
 *
 * @param int $haricGorevId Bu görevi atla (az önce çözülen görevi tekrar verme)
 */
function sonrakiGoreviBul(PDO $pdo, int $ogrenciId, int $haricGorevId = 0): ?int
{
    $akis = erisilebilirGorevAkisi($pdo, $ogrenciId);

    // 1) Önce cezalar
    foreach ($akis as $g) {
        if ($g['sifirli'] && $g['id'] !== $haricGorevId) {
            return $g['id'];
        }
    }

    // 2) Sonra normal çözülmemiş görevler
    foreach ($akis as $g) {
        if (!$g['cozuldu'] && $g['id'] !== $haricGorevId) {
            return $g['id'];
        }
    }

    return null;
}


/**
 * Her şey bitmişse öğrenciyi nereye koyacağız?
 *
 * [2026-08 HATA DÜZELTMESİ] Eskiden oynatici.php "yayındaki ilk görev"e
 * düşüyordu; yani bütün görevleri bitiren öğrenci 1. bölümün 1. görevine
 * geri atılıyor ve orada takılı kalıyordu. Artık ulaştığı EN SON görevde
 * kalır.
 */
function sonErisilenGorev(PDO $pdo, int $ogrenciId): ?int
{
    $akis = erisilebilirGorevAkisi($pdo, $ogrenciId);
    if (!$akis) {
        return null;
    }
    $son = end($akis);
    return (int) $son['id'];
}


/**
 * TAMAMLANMAMIŞ İLK BÖLÜMÜN sıra numarasını döndürür.
 * Hepsi bittiyse son bölümün sıra numarasını döndürür (ileri kaçmasın).
 */
function sonrakiBolumuHesapla(PDO $pdo, int $ogrenciId): int
{
    $bolumler = bolumleriGetir($pdo);
    $ilerleme = ogrencininIlerlemesi($pdo, $ogrenciId);

    $sonSira = 1;
    foreach ($bolumler as $b) {
        $sonSira = max($sonSira, (int) $b['sira_no']);

        if (count($b['gorevler']) === 0) {
            continue; // boş bölüm ilerlemeyi tıkamasın
        }

        foreach ($b['gorevler'] as $g) {
            $k = $ilerleme[$g['id']] ?? null;
            if (!$k || (int) $k['kazanilan_puan'] <= 0) {
                return (int) $b['sira_no']; // burada eksik var
            }
        }
    }

    return $sonSira;
}


/**
 * ogrenciler.mevcut_bolum sütununu gerçek ilerlemeye göre tazeler.
 *
 * ÖNEMLİ: Sütunu ASLA GERİ ÇEKMEZ (öğretmen sıfırlaması hariç, o ayrı
 * fonksiyonla yapılır). Sadece ileri taşır. Böylece öğrenci daha önce
 * açtığı bir bölümün kilidini kazara kaybetmez.
 *
 * @return int Güncel mevcut_bolum değeri
 */
function mevcutBolumuTazele(PDO $pdo, int $ogrenciId, bool $geriCekilebilir = false): int
{
    $st = $pdo->prepare("SELECT mevcut_bolum FROM ogrenciler WHERE id = ?");
    $st->execute([$ogrenciId]);
    $simdiki = max(1, (int) ($st->fetchColumn() ?: 1));

    $hesaplanan = sonrakiBolumuHesapla($pdo, $ogrenciId);
    $yeni = $geriCekilebilir ? $hesaplanan : max($simdiki, $hesaplanan);

    if ($yeni !== $simdiki) {
        $pdo->prepare("UPDATE ogrenciler SET mevcut_bolum = ? WHERE id = ?")
            ->execute([$yeni, $ogrenciId]);
    }

    return $yeni;
}


/**
 * ÖĞRENCİNİN CEZA DURUMU (kırmızı efekt için).
 *
 * Ayrı bir "cezali" sütunu YOK — bilgi doğrudan ilerleme tablosundan türetilir.
 * Öğrenci sıfırlanan görevlerin hepsini tekrar çözünce sonuç otomatik boşalır
 * ve kırmızı efekt kendiliğinden kalkar.
 *
 * @return array ['cezali'=>bool, 'gorev_sayisi'=>int, 'bolumler'=>[..adlar..], 'sebep'=>?string]
 */
function ogrencininCezasi(PDO $pdo, int $ogrenciId): array
{
    $st = $pdo->prepare(
        "SELECT COUNT(*) AS adet,
                MAX(i.sifirlama_sebebi) AS sebep,
                GROUP_CONCAT(DISTINCT b.bolum_adi ORDER BY b.sira_no SEPARATOR ', ') AS bolum_adlari
           FROM v2_ogrenci_ilerleme i
           JOIN v2_gorevler g ON g.id = i.v2_gorev_id
           LEFT JOIN bolumler b ON b.id = g.bolum_id
          WHERE i.ogrenci_id = ?
            AND i.kazanilan_puan = 0
            AND i.sifirlanma_tarihi IS NOT NULL
            AND g.yayinda = 1"
    );
    $st->execute([$ogrenciId]);
    $satir = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    $adet = (int) ($satir['adet'] ?? 0);

    return [
        'cezali'       => $adet > 0,
        'gorev_sayisi' => $adet,
        'bolumler'     => $satir['bolum_adlari'] ?? '',
        'sebep'        => $satir['sebep'] ?? null,
    ];
}


/**
 * Öğrencinin okumadığı sıfırlama bildirimlerini getirir (öğrenci paneli uyarısı).
 */
function okunmamisSifirlamaBildirimi(PDO $pdo, int $ogrenciId): ?array
{
    $st = $pdo->prepare(
        "SELECT id, gorev_sayisi, elmas_cezasi, sebep, tarih
           FROM v2_sifirlama_gunlugu
          WHERE ogrenci_id = ? AND okundu = 0
          ORDER BY tarih DESC LIMIT 1"
    );
    $st->execute([$ogrenciId]);
    $satir = $st->fetch(PDO::FETCH_ASSOC);
    return $satir ?: null;
}


/**
 * Öğretmenin bu öğrenciye müdahale yetkisi var mı?
 * Çok kiracılık kuralı: öğretmen SADECE kendi öğrencisini sıfırlayabilir.
 */
function ogretmeninOgrencisiMi(PDO $pdo, int $ogretmenId, int $ogrenciId): bool
{
    $st = $pdo->prepare(
        "SELECT 1 FROM ogrenciler o
           JOIN siniflar s ON s.id = o.sinif_id
          WHERE o.id = ? AND s.ogretmen_id = ?
          LIMIT 1"
    );
    $st->execute([$ogrenciId, $ogretmenId]);
    return (bool) $st->fetchColumn();
}
