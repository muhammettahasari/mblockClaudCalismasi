<?php
/**
 * ============================================================================
 * DOSYA YOLU: api/ogrenci_kilit.php
 * KULLANICI / SAYFA: Öğretmen -> admin/ogretmen_paneli.php (Canlı Takip)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Öğretmenin ders sırasında öğrenci ekranlarını dondurup çözdüğü yer.
 * "Herkes başını kaldırsın, tahtaya bakın" anı. Kilit açılana kadar öğrenci
 * hiçbir görevi tamamlayamaz, market alışverişi yapamaz.
 *
 * DESTEKLENEN İŞLEMLER:
 *   kilitle       Tek öğrenciyi kilitle
 *   ac            Tek öğrencinin kilidini aç
 *   sinif_kilitle Bir sınıfın TAMAMINI kilitle
 *   sinif_ac      Bir sınıfın TAMAMININ kilidini aç
 *   durum         Öğretmenin sınıflarındaki kilit sayıları
 *
 * ⚠️ BU KİLİT, ŞİFRE KİLİDİYLE AYNI ŞEY DEĞİL
 * `ogrenciler.durum = 'kilitli'` -> öğrenci bölüm şifresini 3 kez yanlış girdi
 * `ogrenciler.ogretmen_kilidi`   -> öğretmen dersi dondurdu (BU DOSYA)
 * İkisi ayrı sütunda; biri diğerini ezmez. Öğretmen sınıfı dondurup açtığında
 * şifre yüzünden kilitlenmiş öğrencinin cezası kalkmaz.
 *
 * 🔒 GÜVENLİK — ÇOK KİRACILIK:
 * Öğretmen SADECE kendi sınıflarındaki öğrencileri kilitleyebilir. Sahiplik
 * `ogretmenler -> siniflar -> ogrenciler` zincirinden doğrulanır.
 * `ogrenciler.ogretmen_id` sütunu KULLANILMAZ — o denormalize bir kopya,
 * öğrenci başka sınıfa taşınınca ya da sınıf devredilince bayatlıyor.
 * Başka okulun öğrencisine istek atılırsa 403 döner.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu.
 * ============================================================================
 */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogretmen_id = ogretmenZorunluApi();

$gelen = istekVerisi();
$islem = beyazListe(
    $gelen['islem'] ?? '',
    ['kilitle', 'ac', 'sinif_kilitle', 'sinif_ac', 'durum'],
    ''
);

if ($islem === '') {
    apiHata('Tanımsız işlem.');
}

$mesaj = mb_substr(trim((string) ($gelen['mesaj'] ?? '')), 0, 255);

/**
 * Bu öğrenci gerçekten bu öğretmenin sınıfında mı?
 * Zincir: ogretmenler -> siniflar -> ogrenciler
 */
function ogrencimMi(PDO $pdo, int $ogretmenId, int $ogrenciId): bool
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

/** Bu sınıf gerçekten bu öğretmenin mi? */
function sinifimMi(PDO $pdo, int $ogretmenId, int $sinifId): bool
{
    $st = $pdo->prepare("SELECT 1 FROM siniflar WHERE id = ? AND ogretmen_id = ? LIMIT 1");
    $st->execute([$sinifId, $ogretmenId]);
    return (bool) $st->fetchColumn();
}

/**
 * Kullanılan not öğretmenin KAYITLI mesajlarından biriyse sayacını artırır.
 * Böylece kilit penceresinde en çok kullandığı not en üstte çıkar.
 *
 * ⚠️ BURASI ASLA HATA FIRLATMAMALI — [2026-08 HATA DÜZELTMESİ]
 * Bu fonksiyon eklendiğinde try/catch'i yoktu. `kilit_mesajlari` tablosu
 * henüz oluşturulmamış bir kurulumda (migration çalıştırılmamış) sınıf
 * gerçekten kilitleniyor, hemen ardından bu UPDATE "table doesn't exist"
 * fırlatıyor ve dıştaki catch bloğu öğretmene KIRMIZI HATA gösteriyordu.
 * Yani iş oldu ama ekranda olmadı yazıyordu — en kötü türden yanlış bilgi.
 *
 * Kural: kilidin kendisi ASIL İŞ, sayaç sadece süs. Süs, işi devirmez.
 * Hata sessizce yutulmuyor, sunucu günlüğüne yazılıyor.
 */
function mesajSayaciniArtir(PDO $pdo, int $ogretmenId, string $mesaj): void
{
    if ($mesaj === '') {
        return;
    }
    try {
        $pdo->prepare(
            "UPDATE kilit_mesajlari
                SET kullanim_sayisi = kullanim_sayisi + 1, son_kullanim = NOW()
              WHERE ogretmen_id = ? AND mesaj = ?"
        )->execute([$ogretmenId, $mesaj]);
    } catch (Throwable $e) {
        error_log('[mBlock] kilit mesaj sayacı güncellenemedi: ' . $e->getMessage());
    }
}

try {
    switch ($islem) {

        /* ------------------------------------------------------------------
         * TEK ÖĞRENCİ — KİLİTLE / AÇ
         * ---------------------------------------------------------------- */
        case 'kilitle':
        case 'ac': {
            $ogrenci_id = (int) ($gelen['ogrenci_id'] ?? 0);

            if ($ogrenci_id <= 0) {
                apiHata('Öğrenci seçilmedi.');
            }
            if (!ogrencimMi($pdo, $ogretmen_id, $ogrenci_id)) {
                apiHata('Bu öğrenci senin sınıfında değil.', 403);
            }

            if ($islem === 'kilitle') {
                $pdo->prepare(
                    "UPDATE ogrenciler
                        SET ogretmen_kilidi = 1, kilit_zamani = NOW(), kilit_mesaji = ?
                      WHERE id = ?"
                )->execute([$mesaj !== '' ? $mesaj : null, $ogrenci_id]);
                mesajSayaciniArtir($pdo, $ogretmen_id, $mesaj);
            } else {
                $pdo->prepare(
                    "UPDATE ogrenciler
                        SET ogretmen_kilidi = 0, kilit_zamani = NULL, kilit_mesaji = NULL
                      WHERE id = ?"
                )->execute([$ogrenci_id]);
            }

            $st = $pdo->prepare("SELECT ad_soyad FROM ogrenciler WHERE id = ?");
            $st->execute([$ogrenci_id]);
            $ad = (string) ($st->fetchColumn() ?: 'Öğrenci');

            apiBasari(
                ['ogrenci_id' => $ogrenci_id, 'kilitli' => ($islem === 'kilitle')],
                $ad . ($islem === 'kilitle' ? ' kilitlendi.' : ' için kilit açıldı.')
            );
        }

        /* ------------------------------------------------------------------
         * TÜM SINIF — KİLİTLE / AÇ
         * Onaylanmamış öğrencilere dokunulmaz (zaten giriş yapamıyorlar).
         * ---------------------------------------------------------------- */
        case 'sinif_kilitle':
        case 'sinif_ac': {
            $sinif_id = (int) ($gelen['sinif_id'] ?? 0);

            if ($sinif_id <= 0) {
                apiHata('Sınıf seçilmedi.');
            }
            if (!sinifimMi($pdo, $ogretmen_id, $sinif_id)) {
                apiHata('Bu sınıf sana ait değil.', 403);
            }

            if ($islem === 'sinif_kilitle') {
                $st = $pdo->prepare(
                    "UPDATE ogrenciler
                        SET ogretmen_kilidi = 1, kilit_zamani = NOW(), kilit_mesaji = ?
                      WHERE sinif_id = ? AND onay_durumu = 1"
                );
                $st->execute([$mesaj !== '' ? $mesaj : null, $sinif_id]);
                mesajSayaciniArtir($pdo, $ogretmen_id, $mesaj);
            } else {
                $st = $pdo->prepare(
                    "UPDATE ogrenciler
                        SET ogretmen_kilidi = 0, kilit_zamani = NULL, kilit_mesaji = NULL
                      WHERE sinif_id = ?"
                );
                $st->execute([$sinif_id]);
            }

            $etkilenen = $st->rowCount();

            apiBasari(
                ['sinif_id' => $sinif_id, 'etkilenen' => $etkilenen,
                 'kilitli'  => ($islem === 'sinif_kilitle')],
                $etkilenen . ' öğrenci ' . ($islem === 'sinif_kilitle' ? 'kilitlendi.' : 'için kilit açıldı.')
            );
        }

        /* ------------------------------------------------------------------
         * DURUM — öğretmenin sınıflarındaki kilit sayıları
         * Panelde "3 sınıfın 2'si kilitli" bilgisini göstermek için.
         * Tek sorgu; sınıf sayısıyla büyümez.
         * ---------------------------------------------------------------- */
        case 'durum': {
            $st = $pdo->prepare(
                "SELECT s.id, s.sinif_adi,
                        COUNT(o.id) AS toplam,
                        SUM(CASE WHEN o.ogretmen_kilidi = 1 THEN 1 ELSE 0 END) AS kilitli
                   FROM siniflar s
              LEFT JOIN ogrenciler o ON o.sinif_id = s.id AND o.onay_durumu = 1
                  WHERE s.ogretmen_id = ?
                  GROUP BY s.id, s.sinif_adi
                  ORDER BY s.sinif_adi ASC"
            );
            $st->execute([$ogretmen_id]);

            $siniflar = array_map(fn($r) => [
                'id'      => (int) $r['id'],
                'ad'      => $r['sinif_adi'],
                'toplam'  => (int) $r['toplam'],
                'kilitli' => (int) $r['kilitli'],
            ], $st->fetchAll(PDO::FETCH_ASSOC));

            apiBasari(['siniflar' => $siniflar]);
        }
    }

} catch (Throwable $e) {
    apiIstisna($e, 'Kilit işlemi tamamlanamadı.');
}
