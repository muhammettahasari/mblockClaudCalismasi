<?php
/* ====================================================================
 * MODÜL: Öğretmen Canlı Radar API'si
 * DOSYA YOLU: api/ogretmen_radar.php
 * BAĞLI OLDUĞU ANA DOSYA: admin/ogretmen_paneli.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Savaş Arenası ve Düello sürelerini arka planda kontrol eder. Süresi dolan
 * etkinlikleri veritabanında otomatik kapatır ve öğretmen panelindeki
 * butonların sayfa yenilemeden güncellenmesi için durum bilgisi gönderir.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Düello artık global duello_ayarlari yerine öğretmenin kendi
 *    ogretmenler.duello_bitis alanından okunuyor (Bilinen Sorunlar #7).
 *  - [2026-08] Süre bitince bekleyen meydan okumalar da temizleniyor.
 *  - [2026-08] Aktif quiz oturumu bilgisi radara eklendi.
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogretmen_id = ogretmenZorunluApi();

try {
    $st = $pdo->prepare("SELECT arena_bitis, duello_bitis FROM ogretmenler WHERE id = ?");
    $st->execute([$ogretmen_id]);
    $ogr = $st->fetch(PDO::FETCH_ASSOC) ?: ['arena_bitis' => null, 'duello_bitis' => null];

    /* --- 1. SAVAŞ ARENASI --- */
    $arena_aktif = ($ogr['arena_bitis'] && strtotime($ogr['arena_bitis']) > time());
    if (!$arena_aktif && $ogr['arena_bitis']) {
        $pdo->prepare("UPDATE ogretmenler SET arena_bitis = NULL WHERE id = ?")->execute([$ogretmen_id]);
    }

    /* --- 2. DÜELLO ARENASI --- */
    $duello_aktif = ($ogr['duello_bitis'] && strtotime($ogr['duello_bitis']) > time());
    if (!$duello_aktif && $ogr['duello_bitis']) {
        $pdo->prepare("UPDATE ogretmenler SET duello_bitis = NULL WHERE id = ?")->execute([$ogretmen_id]);

        // Süre bittiyse bekleyen meydan okumaları da temizle
        $pdo->prepare(
            "UPDATE quiz_duellolar d
               JOIN ogrenciler o ON o.id = d.meydan_okuyan_id
                SET d.durum = 'reddedildi'
              WHERE o.ogretmen_id = ? AND d.durum = 'bekliyor'"
        )->execute([$ogretmen_id]);
    }

    /* --- 3. AKTİF QUIZ OTURUMU --- */
    $st = $pdo->prepare(
        "SELECT qo.id, s.sinif_adi
           FROM quiz_oturum qo
      LEFT JOIN siniflar s ON s.id = qo.sinif_id
          WHERE qo.ogretmen_id = ? AND qo.durum = 'aktif'
          ORDER BY qo.id DESC LIMIT 1"
    );
    $st->execute([$ogretmen_id]);
    $quiz = $st->fetch(PDO::FETCH_ASSOC);

    apiJson([
        'arena_aktif'    => $arena_aktif,
        'duello_aktif'   => $duello_aktif,
        'arena_bitis'    => $arena_aktif ? $ogr['arena_bitis'] : null,
        'duello_bitis'   => $duello_aktif ? $ogr['duello_bitis'] : null,
        'quiz_aktif'     => (bool) $quiz,
        'quiz_oturum_id' => $quiz ? (int) $quiz['id'] : null,
        'quiz_sinif'     => $quiz['sinif_adi'] ?? null,
    ]);

} catch (Throwable $e) {
    apiJson(['arena_aktif' => false, 'duello_aktif' => false, 'quiz_aktif' => false]);
}
