<?php
/* ====================================================================
 * MODÜL: Quiz Oturum Durum Sorgusu
 * DOSYA: api/quiz_arena_kontrol.php
 * BAĞLI OLDUĞU ANA DOSYA: quiz_arena.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Arenadaki öğrencinin "savaş hâlâ sürüyor mu?" diye öğretmeni dinlediği yer.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Oturum sahipliği kontrolü: öğrenci yalnızca KENDİ sınıfına
 *    ait oturumun durumunu sorabilir (Bilinen Sorunlar #6).
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();
$oturum_id  = isset($_GET['oturum_id']) ? (int) $_GET['oturum_id'] : 0;

if ($oturum_id <= 0) {
    apiJson(['durum' => 'bitti']);
}

try {
    /* Oturum bu öğrencinin sınıfına mı ait?
       [2026-08] Sahiplik zinciri quiz_arena.php ile aynı olmalı:
       ogretmenler -> siniflar -> ogrenciler. Aksi hâlde arenaya girebilen
       öğrenci burada "bitti" cevabı alıp saniyeler içinde dışarı atılırdı. */
    $st = $pdo->prepare(
        "SELECT qo.durum
           FROM quiz_oturum qo
           JOIN ogrenciler o ON o.sinif_id = qo.sinif_id
           JOIN siniflar   s ON s.id       = o.sinif_id
          WHERE qo.id = ? AND o.id = ? AND s.ogretmen_id = qo.ogretmen_id"
    );
    $st->execute([$oturum_id, $ogrenci_id]);
    $durum = $st->fetchColumn();

    apiJson(['durum' => $durum ?: 'bitti']);

} catch (Throwable $e) {
    apiJson(['durum' => 'bitti']);
}
