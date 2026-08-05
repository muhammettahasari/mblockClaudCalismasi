<?php
/* ====================================================================
 * MODÜL: RPG Stat Matrisi ve Güç Hesaplama Motoru
 * DOSYA YOLU: bilesenler/rpg_matris.php
 * BAĞLI OLDUĞU ANA DOSYALAR:
 *    profil.php, api/arena_savas_motoru.php,
 *    bilesenler/skorboard_modulleri/sinif_arenasi.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğrencinin üzerindeki kılıç, kalkan, miğfer, binek gibi eşyaların ona kaç
 * güç, çeviklik, karizma kattığını hesaplayan tek merkez. Eskiden bu tablo
 * üç ayrı dosyaya kopyalanmıştı; birini değiştirince diğerleri unutuluyordu.
 * Artık tek yer burası.
 *
 * Ayrıca eski sistemde bir savaşta sınıftaki HER öğrenci için ayrı ayrı
 * sorgu atılıyordu (30 kişilik sınıfta ~65 sorgu). Burada sınıfın tamamı
 * TEK sorguda hesaplanıyor ve sonuç ogrenci_avatar_durum tablosuna
 * önbelleklenerek tekrar tekrar hesaplanması engelleniyor.
 *
 * TEMEL İŞLEVLER:
 *  1. RPG_MATRIS                    — kategori × stat çarpan tablosu
 *  2. statlariHesapla()             — giyili eşya listesinden 6 statı üretir
 *  3. ogrencininGucu()              — tek öğrenci (önbellekli)
 *  4. sinifGucListesi()             — tüm sınıf TEK sorguda
 *  5. gucOnbelleginiTemizle()       — avatar/envanter değişince çağrılır
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Üç kopya tek dosyada birleştirildi (Bilinen Sorunlar #12).
 *  - [2026-08] N+1 sorgu problemi çözüldü (#10).
 *  - [2026-08] genel_guc / ceviklik sütunları önbellek olarak aktifleştirildi (#14).
 * ==================================================================== */

require_once __DIR__ . '/../db.php';

/* --------------------------------------------------------------------
 * 1. ANA MATRİS
 * Her kategori, eşyanın SEVİYESİ ile çarpılarak stat üretir.
 * Örn: 3. seviye sağ el eşyası => guc +15, savascilik +18, beceri +24
 * Negatif çeviklik = ağır zırh seni yavaşlatır.
 * ------------------------------------------------------------------ */
const RPG_MATRIS = [
    'evcil_hayvan' => ['guc' => 4, 'savascilik' => 3, 'beceri' => 5, 'liderlik' => 10, 'karizma' => 8,  'ceviklik' => 0],
    'binek'        => ['guc' => 0, 'savascilik' => 2, 'beceri' => 3, 'liderlik' => 10, 'karizma' => 7,  'ceviklik' => 12],
    'ayakkabi'     => ['guc' => 2, 'savascilik' => 2, 'beceri' => 4, 'liderlik' => 0,  'karizma' => 0,  'ceviklik' => 10],
    'sol_el'       => ['guc' => 4, 'savascilik' => 3, 'beceri' => 0, 'liderlik' => 0,  'karizma' => 2,  'ceviklik' => -1],
    'sag_el'       => ['guc' => 5, 'savascilik' => 6, 'beceri' => 8, 'liderlik' => 0,  'karizma' => 3,  'ceviklik' => -1],
    'kafa'         => ['guc' => 3, 'savascilik' => 2, 'beceri' => 0, 'liderlik' => 0,  'karizma' => 0,  'ceviklik' => -1],
    'zirh'         => ['guc' => 2, 'savascilik' => 2, 'beceri' => 0, 'liderlik' => 0,  'karizma' => 0,  'ceviklik' => -1],
    'yuz'          => ['guc' => 0, 'savascilik' => 0, 'beceri' => 0, 'liderlik' => 2,  'karizma' => 15, 'ceviklik' => 0],
];

/** Avatarda giyilebilen 10 yuva. Sorguya gömülecek sütun adları için beyaz liste. */
const AVATAR_YUVALARI = [
    'karakter', 'yuz', 'sac', 'kafa', 'zirh',
    'sag_el', 'sol_el', 'ayakkabi', 'binek', 'evcil_hayvan',
];

const STAT_ALT_SINIR = 0;
const STAT_UST_SINIR = 100;
const TABAN_GUC      = 10;   // Hiç eşyası olmayanın gücü
const TABAN_CEVIKLIK  = 10;

/* --------------------------------------------------------------------
 * 2. GİYİLİ EŞYA LİSTESİNDEN STAT ÜRETME
 * @param array $esyalar  [['kategori'=>'sag_el','seviye'=>3], ...]
 * @return array          ['guc'=>..,'savascilik'=>.., ... ,'genel_guc'=>..]
 * ------------------------------------------------------------------ */
function statlariHesapla(array $esyalar): array
{
    $statlar = ['guc' => 0, 'savascilik' => 0, 'beceri' => 0, 'liderlik' => 0, 'karizma' => 0, 'ceviklik' => 0];

    foreach ($esyalar as $esya) {
        $kategori = $esya['kategori'] ?? '';
        if (!isset(RPG_MATRIS[$kategori])) {
            continue; // karakter / sac / saldiri_troll stat vermez
        }
        $seviye = max(1, (int) ($esya['seviye'] ?? 1));

        foreach (RPG_MATRIS[$kategori] as $ad => $carpan) {
            $statlar[$ad] += $carpan * $seviye;
        }
    }

    // Sınırları uygula (alt 0, üst 100)
    foreach ($statlar as $ad => $deger) {
        $statlar[$ad] = max(STAT_ALT_SINIR, min(STAT_UST_SINIR, $deger));
    }

    $genel = array_sum($statlar);

    $statlar['genel_guc'] = $genel > 0 ? $genel : TABAN_GUC;
    $statlar['ceviklik']  = $statlar['ceviklik'] > 0 ? $statlar['ceviklik'] : TABAN_CEVIKLIK;

    return $statlar;
}

/* --------------------------------------------------------------------
 * 3. TEK ÖĞRENCİNİN GÜCÜ (önbellekli)
 * Önbellek taze ise veritabanından okur, değilse yeniden hesaplayıp yazar.
 * @param bool $zorla  true ise önbelleği yok sayıp yeniden hesaplar
 * ------------------------------------------------------------------ */
function ogrencininGucu(PDO $pdo, int $ogrenciId, bool $zorla = false): array
{
    if (!$zorla) {
        $st = $pdo->prepare(
            "SELECT genel_guc, ceviklik, guc_guncelleme
               FROM ogrenci_avatar_durum
              WHERE ogrenci_id = ?"
        );
        $st->execute([$ogrenciId]);
        $onbellek = $st->fetch(PDO::FETCH_ASSOC);

        if ($onbellek && !empty($onbellek['guc_guncelleme'])) {
            return [
                'genel_guc' => max(TABAN_GUC, (int) $onbellek['genel_guc']),
                'ceviklik'  => max(TABAN_CEVIKLIK, (int) $onbellek['ceviklik']),
            ];
        }
    }

    return gucuYenidenHesapla($pdo, $ogrenciId);
}

/* --------------------------------------------------------------------
 * 4. GÜCÜ YENİDEN HESAPLA VE ÖNBELLEĞE YAZ
 * ------------------------------------------------------------------ */
function gucuYenidenHesapla(PDO $pdo, int $ogrenciId): array
{
    // Avatar satırı yoksa oluştur (yeni öğrenci)
    $pdo->prepare("INSERT IGNORE INTO ogrenci_avatar_durum (ogrenci_id) VALUES (?)")->execute([$ogrenciId]);

    $sutunlar = [];
    foreach (AVATAR_YUVALARI as $yuva) {
        $sutunlar[] = "oad.{$yuva}_id";
    }
    $yuvaListesi = implode(', ', $sutunlar);

    $st = $pdo->prepare(
        "SELECT e.kategori, e.seviye
           FROM ogrenci_avatar_durum oad
           JOIN esya_kutuphanesi e ON e.id IN ($yuvaListesi)
          WHERE oad.ogrenci_id = ?"
    );
    $st->execute([$ogrenciId]);
    $statlar = statlariHesapla($st->fetchAll(PDO::FETCH_ASSOC));

    $pdo->prepare(
        "UPDATE ogrenci_avatar_durum
            SET genel_guc = ?, ceviklik = ?, guc_guncelleme = NOW()
          WHERE ogrenci_id = ?"
    )->execute([$statlar['genel_guc'], $statlar['ceviklik'], $ogrenciId]);

    return ['genel_guc' => $statlar['genel_guc'], 'ceviklik' => $statlar['ceviklik']];
}

/* --------------------------------------------------------------------
 * 5. SINIFIN TAMAMININ GÜCÜ — TEK SORGU
 * Eski kodda öğrenci başına 2 sorgu atılıyordu (N+1). Artık sınıfın bütün
 * giyili eşyaları tek seferde çekilip PHP tarafında gruplanıyor.
 *
 * @return array  Güce göre BÜYÜKTEN KÜÇÜĞE sıralı:
 *                [['id'=>5,'ad_soyad'=>'..','genel_guc'=>240,'ceviklik'=>30,'sira'=>1], ...]
 * ------------------------------------------------------------------ */
function sinifGucListesi(PDO $pdo, int $sinifId): array
{
    $sutunlar = [];
    foreach (AVATAR_YUVALARI as $yuva) {
        $sutunlar[] = "oad.{$yuva}_id";
    }
    $yuvaListesi = implode(', ', $sutunlar);

    // 1) Sınıftaki öğrenciler
    $st = $pdo->prepare(
        "SELECT id, ad_soyad, kalici_puan, can, enerji
           FROM ogrenciler
          WHERE sinif_id = ? AND (durum = 'aktif' OR durum IS NULL OR durum = '')
          ORDER BY id"
    );
    $st->execute([$sinifId]);
    $ogrenciler = $st->fetchAll(PDO::FETCH_ASSOC);

    if (!$ogrenciler) {
        return [];
    }

    // 2) Sınıfın TÜM giyili eşyaları — tek sorgu
    $st = $pdo->prepare(
        "SELECT oad.ogrenci_id, e.kategori, e.seviye
           FROM ogrenci_avatar_durum oad
           JOIN ogrenciler o        ON o.id = oad.ogrenci_id
           JOIN esya_kutuphanesi e  ON e.id IN ($yuvaListesi)
          WHERE o.sinif_id = ?"
    );
    $st->execute([$sinifId]);

    $esyaHaritasi = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $satir) {
        $esyaHaritasi[(int) $satir['ogrenci_id']][] = $satir;
    }

    // 3) PHP tarafında hesapla
    $liste = [];
    foreach ($ogrenciler as $ogr) {
        $id      = (int) $ogr['id'];
        $statlar = statlariHesapla($esyaHaritasi[$id] ?? []);

        $liste[] = [
            'id'          => $id,
            'ad_soyad'    => $ogr['ad_soyad'],
            'kalici_puan' => (int) $ogr['kalici_puan'],
            'can'         => (int) $ogr['can'],
            'enerji'      => (int) $ogr['enerji'],
            'genel_guc'   => $statlar['genel_guc'],
            'ceviklik'    => $statlar['ceviklik'],
        ];
    }

    // 4) Güce göre sırala ve sıra numarası ver
    usort($liste, fn($a, $b) => $b['genel_guc'] <=> $a['genel_guc']);
    foreach ($liste as $i => &$satir) {
        $satir['sira'] = $i + 1;
    }
    unset($satir);

    return $liste;
}

/* --------------------------------------------------------------------
 * 6. ÖNBELLEĞİ TEMİZLE
 * Avatar değiştiğinde / eşya alındığında / eşya silindiğinde çağrılır.
 * Bir sonraki okumada güç yeniden hesaplanır.
 * ------------------------------------------------------------------ */
function gucOnbelleginiTemizle(PDO $pdo, int $ogrenciId): void
{
    $pdo->prepare("UPDATE ogrenci_avatar_durum SET guc_guncelleme = NULL WHERE ogrenci_id = ?")
        ->execute([$ogrenciId]);
}

/** Toplu temizlik — bir eşya silindiğinde onu giyen herkesin önbelleğini düşürür. */
function tumGucOnbelleginiTemizle(PDO $pdo): void
{
    $pdo->exec("UPDATE ogrenci_avatar_durum SET guc_guncelleme = NULL");
}

/* --------------------------------------------------------------------
 * 7. ORDU GÜCÜ TOPLAMLARI (Ayın Canavarı — Krallık Savunma Merkezi)
 * Sınıf / okul / il bazında toplam savaş gücünü döndürür.
 * ------------------------------------------------------------------ */
function orduGucuOzeti(PDO $pdo, int $ogrenciId): array
{
    $st = $pdo->prepare(
        "SELECT o.sinif_id, o.ogretmen_id, ogr.okul_adi, ogr.il
           FROM ogrenciler o
      LEFT JOIN ogretmenler ogr ON ogr.id = o.ogretmen_id
          WHERE o.id = ?"
    );
    $st->execute([$ogrenciId]);
    $kimlik = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    $ozet = ['sinif' => 0, 'okul' => 0, 'il' => 0, 'turkiye' => 0,
             'sinif_adi' => '', 'okul_adi' => $kimlik['okul_adi'] ?? '', 'il_adi' => $kimlik['il'] ?? ''];

    // Canavara verilecek hasar = öğrencilerin kalıcı puan ortalaması (aylık savaş motoruyla aynı mantık)
    if (!empty($kimlik['sinif_id'])) {
        $st = $pdo->prepare("SELECT COALESCE(AVG(kalici_puan),0) FROM ogrenciler WHERE sinif_id = ?");
        $st->execute([$kimlik['sinif_id']]);
        $ozet['sinif'] = (int) round($st->fetchColumn());

        $st = $pdo->prepare("SELECT sinif_adi FROM siniflar WHERE id = ?");
        $st->execute([$kimlik['sinif_id']]);
        $ozet['sinif_adi'] = (string) $st->fetchColumn();
    }

    if (!empty($kimlik['okul_adi'])) {
        $st = $pdo->prepare(
            "SELECT COALESCE(AVG(o.kalici_puan),0)
               FROM ogrenciler o JOIN ogretmenler ogr ON ogr.id = o.ogretmen_id
              WHERE ogr.okul_adi = ?"
        );
        $st->execute([$kimlik['okul_adi']]);
        $ozet['okul'] = (int) round($st->fetchColumn());
    }

    if (!empty($kimlik['il'])) {
        $st = $pdo->prepare(
            "SELECT COALESCE(SUM(okul_ort),0) FROM (
                SELECT AVG(o.kalici_puan) AS okul_ort
                  FROM ogrenciler o JOIN ogretmenler ogr ON ogr.id = o.ogretmen_id
                 WHERE ogr.il = ?
                 GROUP BY ogr.okul_adi
             ) t"
        );
        $st->execute([$kimlik['il']]);
        $ozet['il'] = (int) round($st->fetchColumn());
    }

    // Türkiye toplamı = her okulun ortalamasının toplamı (aylik_savas_motoru.php ile aynı formül)
    $ozet['turkiye'] = (int) round($pdo->query(
        "SELECT COALESCE(SUM(okul_ort),0) FROM (
            SELECT AVG(o.kalici_puan) AS okul_ort
              FROM ogrenciler o JOIN ogretmenler ogr ON ogr.id = o.ogretmen_id
             GROUP BY ogr.okul_adi
         ) t"
    )->fetchColumn());

    return $ozet;
}
