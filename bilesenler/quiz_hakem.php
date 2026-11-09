<?php
/* ====================================================================
 * MODÜL: Quiz Hakemi (sunucu tarafı puanlama)
 * DOSYA YOLU: bilesenler/quiz_hakem.php
 * BAĞLI OLDUĞU ANA DOSYALAR: api/quiz_cevap.php, api/quiz_saldiri_sonuc.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Quiz'de "doğru mu bildi, kaç puan aldı, saldırı ne oldu?" sorularının
 * TEK cevap mercii. Eskiden bunların hepsi tarayıcıda hesaplanıyordu ve
 * doğru cevaplar sayfanın içine gömülüydü — öğrenci F12 ile bütün
 * cevapları okuyup istediği puanı yazdırabiliyordu.
 *
 * TEMEL İŞLEVLER:
 *  quizCevapTablosu($pdo)         — quiz_cevaplar tablosunu (yoksa) kurar
 *  quizOturumHavuzu($pdo, $oturum) — oturumun bölüm/zorluk listeleri
 *  quizSaldiriSonuclandir(...)     — kılıç/glitch/saat sonucunu puanlar
 *
 * PUANLAMA (eski istemci kuralının aynısı, artık sunucuda):
 *  - Doğru: max(1, ceil(kalan_sure / sure × soru_puanı))
 *  - Yanlış / süre bitti: 0
 *  - Saldırı altında yanlış: savunan -20, saldıran +20
 *  - Saldırı altında doğru + saat: savunan +2×puan, saldıran -2×puan
 *  - Saldırı altında doğru + kılıç/glitch: savunan normal puanını alır
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-11] Dosya oluşturuldu.
 * ==================================================================== */

function quizCevapTablosu(PDO $pdo): void
{
    static $hazir = false;
    if ($hazir) {
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS `quiz_cevaplar` (
        `oyuncu_id`    int(11)     NOT NULL COMMENT 'quiz_ogrenci.id',
        `soru_id`      int(11)     NOT NULL,
        `baslangic`    datetime(3) NOT NULL COMMENT 'Soru ekrana geldiği an (sunucu saati)',
        `cevap`        varchar(12) DEFAULT NULL,
        `dogru`        tinyint(1)  DEFAULT NULL,
        `kazanilan`    int(11)     NOT NULL DEFAULT 0,
        `cevap_zamani` datetime(3) DEFAULT NULL,
        PRIMARY KEY (`oyuncu_id`, `soru_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $hazir = true;
}

/** @return array{0:int[],1:string[]} [bölüm id'leri, zorluklar] */
function quizOturumHavuzu(array $oturum): array
{
    $bolumler  = array_values(array_filter(array_map('intval', explode(',', (string) $oturum['bolum_idler']))));
    $zorluklar = array_values(array_filter(array_map('trim', explode(',', (string) $oturum['zorluk_seviyesi'])), 'strlen'));
    return [$bolumler, $zorluklar];
}

/**
 * Bekleyen bir saldırıyı (quiz_savas_log) sonuçlandırır. Çağıran taraf
 * transaction açmış olmalı ve log satırını FOR UPDATE ile okumuş olmalı.
 *
 * @return array{savunan:int, saldiran:int, durum:string, mesaj:string}
 */
function quizSaldiriSonuclandir(PDO $pdo, array $log, bool $dogru, int $normalPuan): array
{
    $oturum_id   = (int) $log['oturum_id'];
    $saldiran_id = (int) $log['saldiran_id'];
    $savunan_id  = (int) $log['savunan_id'];
    $yetenek     = (string) $log['yetenek_turu'];

    if (!$dogru) {
        $sav = -20; $sal = 20; $durum = 'basarili';
        $mesaj = 'Saldırı başarılı oldu! Yanlış bildiğin için 20 puanın çalındı.';
    } elseif ($yetenek === 'saat') {
        $sav = $normalPuan * 2; $sal = -$sav; $durum = 'geri_tepti';
        $mesaj = "⏳ Zaman Bükücüyü geri yansıttın! İki katı puan ({$sav}) kazandın ve bu puanı saldırandan çaldın!";
    } else {
        $sav = $normalPuan; $sal = 0; $durum = 'basarisiz';
        $mesaj = "Saldırıyı savuşturdun ve doğru bildin! Normal puanını ({$normalPuan}) kazandın.";
    }

    $g = $pdo->prepare("UPDATE quiz_ogrenci SET puan = puan + ? WHERE ogrenci_id = ? AND oturum_id = ?");
    $g->execute([$sav, $savunan_id, $oturum_id]);
    if ($sal !== 0) {
        $g->execute([$sal, $saldiran_id, $oturum_id]);
    }

    $pdo->prepare(
        "UPDATE quiz_savas_log SET durum = ?, saldiran_puan_etkisi = ?, savunan_puan_etkisi = ? WHERE id = ?"
    )->execute([$durum, $sal, $sav, (int) $log['id']]);

    return ['savunan' => $sav, 'saldiran' => $sal, 'durum' => $durum, 'mesaj' => $mesaj];
}
