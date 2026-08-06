<?php
/* ====================================================================
 * MODÜL: Quiz Puan Yazıcı
 * DOSYA: api/quiz_puan_guncelle.php
 * BAĞLI OLDUĞU ANA DOSYA: quiz_arena.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğrenci soru çözdükçe kazandığı puanı veritabanına yazar.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Oturum üyeliği doğrulanıyor (Bilinen Sorunlar #6).
 *  - [2026-08] HİLE FRENİ: Puan tarayıcıdan geldiği için artık üst sınıra
 *      kırpılıyor. Bir öğrenci konsoldan puan=999999 gönderse bile,
 *      çözdüğü soru sayısının izin verdiği tavanın üstüne çıkamaz.
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();

/* [2026-08] ÖĞRETMEN KİLİDİ — ders donduruldusa bu işlem yapılamaz.
   Ekrandaki kilit perdesi sadece görsel; gerçek engel burada. */
require_once __DIR__ . '/../bilesenler/ilerleme_yardimcilari.php';
kilitliyseReddet($pdo, $ogrenci_id);
$oturum_id    = isset($_POST['oturum_id']) ? (int) $_POST['oturum_id'] : 0;
$puan         = isset($_POST['puan']) ? (int) $_POST['puan'] : 0;
$cozulen_soru = isset($_POST['cozulen_soru']) ? (int) $_POST['cozulen_soru'] : 0;

if ($oturum_id <= 0) {
    apiHata('Oturum bilgisi eksik.');
}

try {
    // 1. Öğrenci bu oturumun oyuncusu mu?
    $st = $pdo->prepare("SELECT id FROM quiz_ogrenci WHERE oturum_id = ? AND ogrenci_id = ?");
    $st->execute([$oturum_id, $ogrenci_id]);
    $oyuncu = $st->fetch(PDO::FETCH_ASSOC);

    if (!$oyuncu) {
        apiHata('Bu arenanın oyuncusu değilsiniz.', 403);
    }

    // 2. Oturum hâlâ aktif mi? (Bitmiş oturuma puan yazılamaz)
    $st = $pdo->prepare("SELECT bolum_idler, zorluk_seviyesi FROM quiz_oturum WHERE id = ? AND durum = 'aktif'");
    $st->execute([$oturum_id]);
    $oturum = $st->fetch(PDO::FETCH_ASSOC);

    if (!$oturum) {
        apiHata('Savaş sona ermiş, puan kaydedilemez.', 409);
    }

    /* ---- 3. HİLE FRENİ ----
     * Havuzdaki soru sayısı ve en yüksek soru puanı üzerinden mantıklı bir
     * tavan hesaplanır. Güçlerin (kılıç/saat) etkisi için pay bırakılır. */
    $bolumler = array_filter(array_map('intval', explode(',', (string) $oturum['bolum_idler'])));
    $zorluklar = array_filter(array_map('trim', explode(',', (string) $oturum['zorluk_seviyesi'])));

    $havuzSoru = 0;
    $enYuksekPuan = 20;

    if ($bolumler && $zorluklar) {
        $bIn = implode(',', array_fill(0, count($bolumler), '?'));
        $zIn = implode(',', array_fill(0, count($zorluklar), '?'));

        $st = $pdo->prepare(
            "SELECT COUNT(*) AS adet, COALESCE(MAX(puan), 20) AS max_puan
               FROM quiz_sorulari
              WHERE bolum_id IN ($bIn) AND zorluk_seviyesi IN ($zIn)"
        );
        $st->execute(array_merge($bolumler, $zorluklar));
        $havuz = $st->fetch(PDO::FETCH_ASSOC);

        $havuzSoru    = (int) $havuz['adet'];
        $enYuksekPuan = max(1, (int) $havuz['max_puan']);
    }

    // Çözülen soru sayısı havuzu aşamaz
    $cozulen_soru = max(0, min($cozulen_soru, $havuzSoru > 0 ? $havuzSoru : 500));

    // Puan tavanı: (çözülen soru × soru puanı × 2 kat saat bonusu) + güç payı
    $puanTavani = ($cozulen_soru * $enYuksekPuan * 2) + 100;
    $puanTabani = -($cozulen_soru * $enYuksekPuan * 2) - 100;   // eksiye de düşebilir
    $puan = max($puanTabani, min($puan, $puanTavani));

    $pdo->prepare(
        "UPDATE quiz_ogrenci SET puan = ?, cozulen_soru_sayisi = ? WHERE id = ?"
    )->execute([$puan, $cozulen_soru, $oyuncu['id']]);

    apiBasari(['puan' => $puan, 'cozulen_soru' => $cozulen_soru]);

} catch (Throwable $e) {
    apiIstisna($e, 'Puan kaydedilemedi.');
}
