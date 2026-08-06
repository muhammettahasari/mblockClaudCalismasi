<?php
/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/admin/kukla_kutuphanesi/kukla_islem.php
 * KULLANICI / SAYFA: Süper Admin -> Kukla Kütüphanesi (tüm yazma işlemleri)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Kukla kütüphanesinin tek arka plan motoru. Yeni kukla ekleme, adını/grubunu
 * değiştirme, kostüm ekleme/silme/yeniden adlandırma ve kukla silme — hepsi
 * buradan geçer.
 *
 * [2026-08] ÜÇ DOSYA BURADA BİRLEŞTİRİLDİ:
 *   kukla_ekle.php (form sayfası)  -> index.php içine taşındı
 *   kukla_sil.php  (toplu silme)   -> buraya taşındı
 *   kukla_islem.php (sadece ekleme)-> tüm işlemleri üstlendi
 *
 * DESTEKLENEN İŞLEMLER:
 *   kukla_ekle        multipart  — yeni kukla + kostüm dosyaları
 *   kostum_ekle       multipart  — mevcut kuklaya kostüm dosyası ekler
 *   kukla_guncelle    JSON       — ad / grup değiştir
 *   kostum_adlandir   JSON       — tek kostümün adını değiştir
 *   kostum_sil        JSON       — tek kostümü siler (dosyayı da)
 *   kukla_sil         JSON       — kuklayı ve tüm kostüm dosyalarını siler
 *
 * GÜVENLİK:
 *   - Süper Admin oturumu zorunlu
 *   - Uzantı beyaz listesi (jpg/jpeg/png/gif/svg)
 *   - Dosya adı sunucuda üretilir (uniqid), kullanıcı adı kullanılmaz
 *   - Silinecek dosya yolu kuklalar klasörünün DIŞINA çıkamaz (realpath kontrolü)
 * ============================================================================
 */

require_once __DIR__ . '/../../../admin/super_auth.php';
require_once __DIR__ . '/../../../bilesenler/api_yanit.php';
superAdminZorunlu();

/** Kostüm dosyalarının tutulduğu klasör */
const KUKLA_KLASORU = __DIR__ . '/../../assets/images/kuklalar/';
/** Veritabanına yazılan göreli yol öneki (öğrenci JS'i başına ../ ekler) */
const KUKLA_DB_ONEK = 'assets/images/kuklalar/';

const IZINLI_UZANTILAR = ['jpg', 'jpeg', 'png', 'gif', 'svg'];

/**
 * Yüklenen dosyayı güvenli şekilde kaydeder.
 * @return string|null Veritabanına yazılacak göreli yol, başarısızsa null
 */
function kostumDosyasiKaydet(string $geciciYol, string $orijinalAd): ?string
{
    $uzanti = strtolower(pathinfo($orijinalAd, PATHINFO_EXTENSION));
    if (!in_array($uzanti, IZINLI_UZANTILAR, true)) {
        return null;
    }

    if (!is_dir(KUKLA_KLASORU)) {
        mkdir(KUKLA_KLASORU, 0777, true);
    }

    // Dosya adını SUNUCU üretir; kullanıcının verdiği ad hiç kullanılmaz
    $yeniAd = uniqid('kostum_') . '.' . $uzanti;

    return move_uploaded_file($geciciYol, KUKLA_KLASORU . $yeniAd)
        ? KUKLA_DB_ONEK . $yeniAd
        : null;
}

/**
 * Kostüm dosyasını diskten siler.
 * GÜVENLİK: yol kuklalar klasörünün dışına çıkarsa hiçbir şey yapılmaz —
 * veritabanına elle "../../db.php" gibi bir değer yazılmış olsa bile.
 */
function kostumDosyasiSil(?string $goreliYol): void
{
    if (!$goreliYol) {
        return;
    }

    $tam = realpath(__DIR__ . '/../../' . ltrim($goreliYol, '/'));
    $kok = realpath(KUKLA_KLASORU);

    if ($tam && $kok && str_starts_with($tam, $kok) && is_file($tam)) {
        @unlink($tam);
    }
}

/** Kuklanın kostüm dizisini okur */
function kostumleriOku(PDO $pdo, int $kuklaId): ?array
{
    $st = $pdo->prepare("SELECT kostumler_json FROM v2_kukla_kutuphanesi WHERE id = ?");
    $st->execute([$kuklaId]);
    $satir = $st->fetch(PDO::FETCH_ASSOC);

    if (!$satir) {
        return null;
    }
    return json_decode($satir['kostumler_json'] ?? '[]', true) ?: [];
}

/** Kostüm dizisini geri yazar */
function kostumleriYaz(PDO $pdo, int $kuklaId, array $kostumler): void
{
    $pdo->prepare("UPDATE v2_kukla_kutuphanesi SET kostumler_json = ? WHERE id = ?")
        ->execute([json_encode(array_values($kostumler), JSON_UNESCAPED_UNICODE), $kuklaId]);
}


/* ==========================================================================
 * 1. DOSYA YÜKLEMELİ İŞLEMLER (multipart form)
 * ========================================================================== */

/* ---- YENİ KUKLA ---- */
if (isset($_POST['islem']) && $_POST['islem'] === 'kukla_ekle') {

    $kuklaAdi = trim((string) ($_POST['kukla_adi'] ?? ''));
    $grupAdi  = trim((string) ($_POST['grup_adi'] ?? '')) ?: 'Genel';

    if ($kuklaAdi === '') {
        header('Location: index.php?durum=hata&mesaj=' . urlencode('Kukla adı boş olamaz.'));
        exit;
    }

    $kostumler = [];
    $sayac     = 0;
    $adet      = isset($_FILES['kukla_resimleri']['name']) ? count($_FILES['kukla_resimleri']['name']) : 0;

    for ($i = 0; $i < $adet; $i++) {
        if (($_FILES['kukla_resimleri']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $yol = kostumDosyasiKaydet(
            $_FILES['kukla_resimleri']['tmp_name'][$i],
            $_FILES['kukla_resimleri']['name'][$i]
        );

        if ($yol) {
            $sayac++;
            $kostumler[] = [
                'id'        => 'k_' . time() . '_' . $sayac,
                'adi'       => 'costume' . $sayac,
                // İKİ ANAHTAR BİRDEN: 'resimYolu' Kostüm Atölyesi'nin,
                // 'yol' öğrenci alt panelinin okuduğu alan. Biri eksik olursa
                // o tarafta kırık resim çıkıyor.
                'resimYolu' => $yol,
                'yol'       => $yol,
                'cizimData' => null,
            ];
        }
    }

    if (!$kostumler) {
        header('Location: index.php?durum=hata&mesaj=' . urlencode('Geçerli bir görsel yüklenemedi.'));
        exit;
    }

    $pdo->prepare("INSERT INTO v2_kukla_kutuphanesi (kukla_adi, grup_adi, kostumler_json) VALUES (?, ?, ?)")
        ->execute([$kuklaAdi, $grupAdi, json_encode($kostumler, JSON_UNESCAPED_UNICODE)]);

    header('Location: index.php?durum=eklendi&ad=' . urlencode($kuklaAdi));
    exit;
}

/* ---- MEVCUT KUKLAYA KOSTÜM EKLE ---- */
if (isset($_POST['islem']) && $_POST['islem'] === 'kostum_ekle') {

    $kuklaId = (int) ($_POST['kukla_id'] ?? 0);
    $mevcut  = kostumleriOku($pdo, $kuklaId);

    if ($mevcut === null) {
        header('Location: index.php?durum=hata&mesaj=' . urlencode('Kukla bulunamadı.'));
        exit;
    }

    $eklenen = 0;
    $adet    = isset($_FILES['kostum_dosyalari']['name']) ? count($_FILES['kostum_dosyalari']['name']) : 0;

    for ($i = 0; $i < $adet; $i++) {
        if (($_FILES['kostum_dosyalari']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $yol = kostumDosyasiKaydet(
            $_FILES['kostum_dosyalari']['tmp_name'][$i],
            $_FILES['kostum_dosyalari']['name'][$i]
        );

        if ($yol) {
            $eklenen++;
            $sira = count($mevcut) + $eklenen;
            $mevcut[] = [
                'id'        => 'k_' . time() . '_' . $sira,
                'adi'       => 'costume' . $sira,
                'resimYolu' => $yol,
                'yol'       => $yol,
                'cizimData' => null,
            ];
        }
    }

    if ($eklenen > 0) {
        kostumleriYaz($pdo, $kuklaId, $mevcut);
    }

    header('Location: index.php?durum=kostum_eklendi&sayi=' . $eklenen . '&ac=' . $kuklaId);
    exit;
}


/* ==========================================================================
 * 2. JSON İŞLEMLERİ (dosya yüklemesi olmayanlar)
 * ========================================================================== */
$gelen = istekVerisi();
$islem = beyazListe(
    $gelen['islem'] ?? '',
    ['kukla_guncelle', 'kostum_adlandir', 'kostum_sil', 'kukla_sil'],
    ''
);

if ($islem === '') {
    // Ne form ne JSON — doğrudan adrese girilmiş
    header('Location: index.php');
    exit;
}

try {
    $kuklaId = (int) ($gelen['kukla_id'] ?? 0);
    if ($kuklaId <= 0) {
        apiHata('Kukla seçilmedi.');
    }

    switch ($islem) {

        /* ---- AD / GRUP DEĞİŞTİR ---- */
        case 'kukla_guncelle': {
            $ad   = mb_substr(trim((string) ($gelen['kukla_adi'] ?? '')), 0, 100);
            $grup = mb_substr(trim((string) ($gelen['grup_adi'] ?? '')), 0, 100) ?: 'Genel';

            if ($ad === '') {
                apiHata('Kukla adı boş olamaz.');
            }

            $pdo->prepare("UPDATE v2_kukla_kutuphanesi SET kukla_adi = ?, grup_adi = ? WHERE id = ?")
                ->execute([$ad, $grup, $kuklaId]);

            apiBasari(['kukla_adi' => $ad, 'grup_adi' => $grup], 'Kukla güncellendi.');
        }

        /* ---- KOSTÜM ADI ---- */
        case 'kostum_adlandir': {
            $kostumId = (string) ($gelen['kostum_id'] ?? '');
            $yeniAd   = mb_substr(trim((string) ($gelen['yeni_ad'] ?? '')), 0, 60);

            if ($yeniAd === '') {
                apiHata('Kostüm adı boş olamaz.');
            }

            $kostumler = kostumleriOku($pdo, $kuklaId);
            if ($kostumler === null) {
                apiHata('Kukla bulunamadı.', 404);
            }

            $bulundu = false;
            foreach ($kostumler as &$k) {
                if (($k['id'] ?? '') === $kostumId) {
                    $k['adi'] = $yeniAd;
                    $bulundu  = true;
                    break;
                }
            }
            unset($k);

            if (!$bulundu) {
                apiHata('Kostüm bulunamadı.', 404);
            }

            kostumleriYaz($pdo, $kuklaId, $kostumler);
            apiBasari([], 'Kostüm adı güncellendi.');
        }

        /* ---- KOSTÜM SİL ---- */
        case 'kostum_sil': {
            $kostumId  = (string) ($gelen['kostum_id'] ?? '');
            $kostumler = kostumleriOku($pdo, $kuklaId);

            if ($kostumler === null) {
                apiHata('Kukla bulunamadı.', 404);
            }
            if (count($kostumler) <= 1) {
                apiHata('Son kostüm silinemez. Kuklanın en az bir görseli olmalı.');
            }

            $kalan = [];
            $silinen = null;
            foreach ($kostumler as $k) {
                if (($k['id'] ?? '') === $kostumId) { $silinen = $k; continue; }
                $kalan[] = $k;
            }

            if (!$silinen) {
                apiHata('Kostüm bulunamadı.', 404);
            }

            kostumDosyasiSil($silinen['yol'] ?? ($silinen['resimYolu'] ?? null));
            kostumleriYaz($pdo, $kuklaId, $kalan);

            apiBasari(['kalan' => count($kalan)], 'Kostüm silindi.');
        }

        /* ---- KUKLA SİL (kostüm dosyalarıyla birlikte) ---- */
        case 'kukla_sil': {
            $kostumler = kostumleriOku($pdo, $kuklaId);
            if ($kostumler === null) {
                apiHata('Kukla bulunamadı.', 404);
            }

            foreach ($kostumler as $k) {
                kostumDosyasiSil($k['yol'] ?? ($k['resimYolu'] ?? null));
            }

            $pdo->prepare("DELETE FROM v2_kukla_kutuphanesi WHERE id = ?")->execute([$kuklaId]);
            apiBasari([], 'Kukla ve tüm kostümleri silindi.');
        }
    }

} catch (Throwable $e) {
    apiIstisna($e, 'İşlem tamamlanamadı.');
}
