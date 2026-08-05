<?php
/* ====================================================================
 * MODÜL: Quiz Saldırı Sonucu Değerlendirici
 * DOSYA: api/quiz_saldiri_sonuc.php
 * BAĞLI OLDUĞU ANA DOSYA: quiz_arena.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Bir öğrenciye kılıç/glitch/saat atıldığında, o öğrenci soruyu cevaplayınca
 * kimin kaç puan alacağını hesaplayan hakem.
 *
 * PUANLAMA:
 *  - Yanlış bildi     → savunan -20, saldıran +20
 *  - Doğru + kılıç/glitch → savunan normal puanını alır, saldıran bir şey alamaz
 *  - Doğru + saat     → geri teper: savunan +2 kat, saldıran -2 kat
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] KRİTİK GÜVENLİK AÇIĞI KAPATILDI:
 *      Eskiden bu dosyada HİÇ oturum kontrolü yoktu ve log_id ile
 *      normal_puan doğrudan tarayıcıdan alınıyordu. Herhangi biri
 *      istediği log için istediği puanı yazdırabiliyordu.
 *      Artık: (a) giriş zorunlu, (b) isteği yapan kişi o saldırının
 *      SAVUNANI olmak zorunda, (c) aynı log iki kez işlenemez,
 *      (d) normal_puan sorunun gerçek puanına kırpılır.
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();

$log_id      = isset($_POST['log_id']) ? (int) $_POST['log_id'] : 0;
$dogru_mu    = isset($_POST['dogru_mu']) ? (int) $_POST['dogru_mu'] : 0;
$normal_puan = isset($_POST['normal_puan']) ? (int) $_POST['normal_puan'] : 0;

if ($log_id <= 0) {
    apiHata('Saldırı kaydı bulunamadı.');
}

try {
    $st = $pdo->prepare("SELECT * FROM quiz_savas_log WHERE id = ?");
    $st->execute([$log_id]);
    $log = $st->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        apiHata('Saldırı kaydı bulunamadı.');
    }

    // (b) İsteği yapan kişi gerçekten bu saldırının hedefi mi?
    if ((int) $log['savunan_id'] !== $ogrenci_id) {
        apiHata('Bu saldırının sonucunu yalnızca hedefi bildirebilir.', 403);
    }

    // (c) Aynı log iki kez puanlanmasın
    if (!in_array($log['durum'], ['bekliyor', 'kalkan_kirdi'], true)) {
        apiHata('Bu saldırı zaten sonuçlanmış.', 409);
    }

    $oturum_id   = (int) $log['oturum_id'];
    $saldiran_id = (int) $log['saldiran_id'];
    $savunan_id  = (int) $log['savunan_id'];
    $yetenek     = $log['yetenek_turu'];

    // Oturum hâlâ açık mı?
    $st = $pdo->prepare("SELECT bolum_idler, zorluk_seviyesi FROM quiz_oturum WHERE id = ? AND durum = 'aktif'");
    $st->execute([$oturum_id]);
    $oturum = $st->fetch(PDO::FETCH_ASSOC);

    if (!$oturum) {
        apiHata('Savaş sona ermiş.', 409);
    }

    /* (d) normal_puan hile freni — havuzdaki en yüksek soru puanına kırp */
    $enYuksekPuan = 20;
    $bolumler  = array_filter(array_map('intval', explode(',', (string) $oturum['bolum_idler'])));
    $zorluklar = array_filter(array_map('trim', explode(',', (string) $oturum['zorluk_seviyesi'])));

    if ($bolumler && $zorluklar) {
        $bIn = implode(',', array_fill(0, count($bolumler), '?'));
        $zIn = implode(',', array_fill(0, count($zorluklar), '?'));
        $st = $pdo->prepare(
            "SELECT COALESCE(MAX(puan), 20) FROM quiz_sorulari
              WHERE bolum_id IN ($bIn) AND zorluk_seviyesi IN ($zIn)"
        );
        $st->execute(array_merge($bolumler, $zorluklar));
        $enYuksekPuan = max(1, (int) $st->fetchColumn());
    }
    $normal_puan = max(0, min($normal_puan, $enYuksekPuan));

    /* ---- PUANLAMA ---- */
    $savunan_puan_etkisi  = 0;
    $saldiran_puan_etkisi = 0;

    if ($dogru_mu === 0) {
        // Yanlış bildi
        $savunan_puan_etkisi  = -20;
        $saldiran_puan_etkisi = 20;
        $durum = 'basarili';
        $mesaj = 'Saldırı başarılı oldu! Yanlış bildiğin için 20 puanın çalındı.';
    } elseif ($yetenek === 'saat') {
        // Saat atıldı ve bildi → geri teper
        $savunan_puan_etkisi  = $normal_puan * 2;
        $saldiran_puan_etkisi = -($normal_puan * 2);
        $durum = 'geri_tepti';
        $mesaj = "⏳ Zaman Bükücüyü geri yansıttın! İki katı puan ({$savunan_puan_etkisi}) kazandın ve bu puanı saldırandan çaldın!";
    } else {
        // Kılıç / glitch atıldı ama bildi
        $savunan_puan_etkisi  = $normal_puan;
        $saldiran_puan_etkisi = 0;
        $durum = 'basarisiz';
        $mesaj = "Saldırıyı savuşturdun ve doğru bildin! Normal puanını ({$normal_puan}) kazandın.";
    }

    $pdo->beginTransaction();

    $guncelle = $pdo->prepare("UPDATE quiz_ogrenci SET puan = puan + ? WHERE ogrenci_id = ? AND oturum_id = ?");
    $guncelle->execute([$savunan_puan_etkisi, $savunan_id, $oturum_id]);
    $guncelle->execute([$saldiran_puan_etkisi, $saldiran_id, $oturum_id]);

    $pdo->prepare(
        "UPDATE quiz_savas_log
            SET durum = ?, saldiran_puan_etkisi = ?, savunan_puan_etkisi = ?
          WHERE id = ?"
    )->execute([$durum, $saldiran_puan_etkisi, $savunan_puan_etkisi, $log_id]);

    $pdo->commit();

    $st = $pdo->prepare("SELECT puan FROM quiz_ogrenci WHERE ogrenci_id = ? AND oturum_id = ?");
    $st->execute([$savunan_id, $oturum_id]);
    $guncelPuan = (int) $st->fetchColumn();

    apiBasari(['guncel_puan' => $guncelPuan, 'durum' => $durum], $mesaj);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiIstisna($e, 'Saldırı sonucu işlenemedi.');
}
