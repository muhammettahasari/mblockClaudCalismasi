<?php
/* ====================================================================
 * MODÜL: Düello Dinleyicisi (Öğrenci Radarı)
 * DOSYA: api/duello/dinle.php
 * BAĞLI OLDUĞU ANA DOSYA: bilesenler/duello_radari.php
 *   (api/ogrenci_radar.php üzerinden de çağrılır)
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğrencinin sayfası arka planda üç şeyi sorar:
 *   1. Bana meydan okuyan var mı?
 *   2. Benim attığım meydan okuma kabul edildi mi (arenaya gideyim mi)?
 *   3. Düello şu an açık mı?
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] ÖĞRETMEN BAZLI ŞALTER (Bilinen Sorunlar #7):
 *      Global duello_ayarlari yerine öğrencinin KENDİ öğretmeninin
 *      duello_bitis saatine bakılıyor.
 *  - [2026-08] Gelen meydan okumalar sınıf içiyle sınırlandı.
 * ==================================================================== */

require_once __DIR__ . '/../../bilesenler/guvenlik.php';
require_once __DIR__ . '/../../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();

try {
    /* --- 1. Öğrencinin kendi öğretmeninin düello penceresi açık mı? --- */
    $st = $pdo->prepare(
        "SELECT ogr.duello_bitis
           FROM ogrenciler o
           JOIN ogretmenler ogr ON ogr.id = o.ogretmen_id
          WHERE o.id = ?"
    );
    $st->execute([$ogrenci_id]);
    $duelloBitis = $st->fetchColumn();

    $arena_aktif = ($duelloBitis && strtotime($duelloBitis) > time());

    /* --- 2. Attığım meydan okuma kabul edildi mi? --- */
    $st = $pdo->prepare(
        "SELECT id FROM quiz_duellolar
          WHERE meydan_okuyan_id = ? AND durum = 'kabul_edildi'
          ORDER BY id DESC LIMIT 1"
    );
    $st->execute([$ogrenci_id]);
    $kabulEdilen = $st->fetch(PDO::FETCH_ASSOC);

    if ($kabulEdilen && ($_SESSION['son_gidilen_saldiri'] ?? null) != $kabulEdilen['id']) {
        $_SESSION['son_gidilen_saldiri'] = $kabulEdilen['id'];
        apiJson([
            'gelen_istek' => false,
            'arenaya_git' => true,
            'duello_id'   => (int) $kabulEdilen['id'],
            'arena_aktif' => $arena_aktif,
        ]);
    }

    /* --- 3. Bana gelen meydan okuma var mı? (sadece aynı sınıftan) --- */
    $st = $pdo->prepare(
        "SELECT d.id, o.ad_soyad AS meydan_okuyan_ad
           FROM quiz_duellolar d
           JOIN ogrenciler o   ON d.meydan_okuyan_id = o.id
           JOIN ogrenciler ben ON ben.id = d.meydan_okunan_id
          WHERE d.meydan_okunan_id = ?
            AND d.durum = 'bekliyor'
            AND o.sinif_id = ben.sinif_id
          ORDER BY d.id ASC LIMIT 1"
    );
    $st->execute([$ogrenci_id]);
    $duello = $st->fetch(PDO::FETCH_ASSOC);

    if ($duello) {
        $_SESSION['son_gidilen_savunma'] = $duello['id'];
        apiJson([
            'gelen_istek'      => true,
            'arenaya_git'      => false,
            'duello_id'        => (int) $duello['id'],
            'meydan_okuyan_ad' => $duello['meydan_okuyan_ad'],
            'arena_aktif'      => $arena_aktif,
        ]);
    }

    apiJson(['gelen_istek' => false, 'arenaya_git' => false, 'arena_aktif' => $arena_aktif]);

} catch (Throwable $e) {
    apiJson(['gelen_istek' => false, 'arenaya_git' => false, 'arena_aktif' => false]);
}
