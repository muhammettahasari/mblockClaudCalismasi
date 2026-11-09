<?php
/* ====================================================================
 * DOSYA: api/quiz_cevap.php
 * KULLANICI: Öğrenci (quiz_arena.php)
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Quiz'in hakemi. İki iş yapar:
 *   islem=basla  soru_id=N          → "Bu soru şimdi ekrana geldi" (saat sunucuda başlar)
 *   islem=cevap  soru_id=N secim=A  → Cevabı kontrol eder, puanı SUNUCU hesaplar.
 *                [log_id=M]            Bekleyen bir saldırı varsa onu da burada sonuçlandırır.
 * Cevap: { dogru, dogru_cevap, kazanilan, guncel_puan, saldiri:{mesaj,durum}|null }
 *
 * NEDEN?
 * Eskiden doğru cevaplar sayfaya gömülüydü, puanı tarayıcı hesaplayıp
 * quiz_puan_guncelle.php'ye MUTLAK değer olarak yazıyordu. F12 ile hem
 * cevaplar okunabiliyor hem istenen puan yazdırılabiliyordu.
 *
 * KURALLAR:
 *  - Aynı soru bir oyuncu için yalnızca BİR kez cevaplanır (PK).
 *  - "basla" çağrılmadan cevaplanan soru, doğruysa en fazla 1 puan alır.
 *  - Süre sunucu saatiyle ölçülür; ağ gecikmesi için 1 sn pay vardır.
 *  - Soru, oturumun bölüm + zorluk havuzunda olmalı.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-11] Dosya oluşturuldu.
 * ==================================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';
require_once __DIR__ . '/../bilesenler/quiz_hakem.php';
require_once __DIR__ . '/../bilesenler/ilerleme_yardimcilari.php';

$ogrenci_id = ogrenciZorunluApi();
kilitliyseReddet($pdo, $ogrenci_id);

$veri      = istekVerisi();
$islem     = beyazListe((string) ($veri['islem'] ?? ''), ['basla', 'cevap']);
$oturum_id = (int) ($veri['oturum_id'] ?? 0);
$soru_id   = (int) ($veri['soru_id'] ?? 0);

if (!$islem || $oturum_id <= 0 || $soru_id <= 0) {
    apiHata('Eksik bilgi.');
}

const QUIZ_GECIKME_PAYI = 1.0;   // saniye

try {
    quizCevapTablosu($pdo);

    // Oyuncu + aktif oturum
    $st = $pdo->prepare(
        "SELECT qo.id AS oyuncu_id, o.bolum_idler, o.zorluk_seviyesi
           FROM quiz_ogrenci qo
           JOIN quiz_oturum o ON o.id = qo.oturum_id
          WHERE qo.oturum_id = ? AND qo.ogrenci_id = ? AND o.durum = 'aktif'"
    );
    $st->execute([$oturum_id, $ogrenci_id]);
    $oyuncu = $st->fetch(PDO::FETCH_ASSOC);
    if (!$oyuncu) {
        apiHata('Savaş sona ermiş ya da bu arenanın oyuncusu değilsin.', 409);
    }
    $oyuncu_id = (int) $oyuncu['oyuncu_id'];

    // Soru oturumun havuzunda mı?
    [$bolumler, $zorluklar] = quizOturumHavuzu($oyuncu);
    if (!$bolumler || !$zorluklar) {
        apiHata('Oturumun soru havuzu tanımsız.');
    }
    $bIn = implode(',', array_fill(0, count($bolumler), '?'));
    $zIn = implode(',', array_fill(0, count($zorluklar), '?'));
    $st = $pdo->prepare(
        "SELECT id, sure, puan, dogru_cevap FROM quiz_sorulari
          WHERE id = ? AND bolum_id IN ($bIn) AND zorluk_seviyesi IN ($zIn)"
    );
    $st->execute(array_merge([$soru_id], $bolumler, $zorluklar));
    $soru = $st->fetch(PDO::FETCH_ASSOC);
    if (!$soru) {
        apiHata('Bu soru bu savaşa ait değil.', 403);
    }

    /* ---------------- BAŞLA ---------------- */
    if ($islem === 'basla') {
        $pdo->prepare("INSERT IGNORE INTO quiz_cevaplar (oyuncu_id, soru_id, baslangic) VALUES (?, ?, NOW(3))")
            ->execute([$oyuncu_id, $soru_id]);
        apiBasari(['sure' => (int) $soru['sure']]);
    }

    /* ---------------- CEVAP ---------------- */
    $secim = strtoupper(trim((string) ($veri['secim'] ?? '')));
    $log_id = (int) ($veri['log_id'] ?? 0);

    $sure      = max(1, (int) ($soru['sure'] ?: 20));
    $soruPuani = max(1, (int) ($soru['puan'] ?: 20));
    $dogruSik  = strtoupper(trim((string) $soru['dogru_cevap']));

    $pdo->beginTransaction();

    $st = $pdo->prepare(
        "SELECT cevap, TIMESTAMPDIFF(MICROSECOND, baslangic, NOW(3)) / 1000000 AS gecen
           FROM quiz_cevaplar WHERE oyuncu_id = ? AND soru_id = ? FOR UPDATE"
    );
    $st->execute([$oyuncu_id, $soru_id]);
    $kayit = $st->fetch(PDO::FETCH_ASSOC);

    if ($kayit && $kayit['cevap'] !== null) {
        $pdo->rollBack();
        apiHata('Bu soruyu zaten cevapladın.', 409);
    }

    $dogru = in_array($secim, ['A', 'B', 'C', 'D'], true) && $secim === $dogruSik;

    // Bekleyen saldırı (bu oyuncu savunan olmalı)
    $log = null;
    if ($log_id > 0) {
        $st = $pdo->prepare(
            "SELECT * FROM quiz_savas_log
              WHERE id = ? AND oturum_id = ? AND savunan_id = ? AND durum IN ('bekliyor', 'kalkan_kirdi')
              FOR UPDATE"
        );
        $st->execute([$log_id, $oturum_id, $ogrenci_id]);
        $log = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // Kalan süre (sunucu saatiyle)
    if ($kayit) {
        $gecen = max(0.0, (float) $kayit['gecen'] - QUIZ_GECIKME_PAYI);
        $kalan = max(0.0, $sure - $gecen);
        if ($log && $log['yetenek_turu'] === 'saat') {
            $kalan = min($kalan, 10.0);   // Zaman Bükücü süreyi 10 sn'ye indirir
        }
        $kazanilan = $dogru ? max(1, (int) ceil(($kalan / $sure) * $soruPuani)) : 0;
        if ($dogru && $kalan <= 0) {
            $dogru = false;               // süre çoktan bitmiş
            $kazanilan = 0;
        }
    } else {
        $kazanilan = $dogru ? 1 : 0;      // "basla" hiç çağrılmadı
    }

    $pdo->prepare(
        "INSERT INTO quiz_cevaplar (oyuncu_id, soru_id, baslangic, cevap, dogru, kazanilan, cevap_zamani)
         VALUES (?, ?, NOW(3), ?, ?, ?, NOW(3))
         ON DUPLICATE KEY UPDATE cevap = VALUES(cevap), dogru = VALUES(dogru),
                                 kazanilan = VALUES(kazanilan), cevap_zamani = VALUES(cevap_zamani)"
    )->execute([$oyuncu_id, $soru_id, $secim === '' ? 'BOS' : mb_substr($secim, 0, 12), $dogru ? 1 : 0, $kazanilan]);

    $saldiri = null;
    if ($log) {
        $saldiri = quizSaldiriSonuclandir($pdo, $log, $dogru, $kazanilan);
    } elseif ($kazanilan > 0) {
        $pdo->prepare("UPDATE quiz_ogrenci SET puan = puan + ? WHERE id = ?")->execute([$kazanilan, $oyuncu_id]);
    }

    $pdo->prepare(
        "UPDATE quiz_ogrenci
            SET cozulen_soru_sayisi = (SELECT COUNT(*) FROM quiz_cevaplar WHERE oyuncu_id = ? AND cevap IS NOT NULL)
          WHERE id = ?"
    )->execute([$oyuncu_id, $oyuncu_id]);

    $pdo->commit();

    $st = $pdo->prepare("SELECT puan FROM quiz_ogrenci WHERE id = ?");
    $st->execute([$oyuncu_id]);

    apiBasari([
        'dogru'       => $dogru,
        'dogru_cevap' => $dogruSik,
        'kazanilan'   => $saldiri ? $saldiri['savunan'] : $kazanilan,
        'guncel_puan' => (int) $st->fetchColumn(),
        'saldiri'     => $saldiri ? ['mesaj' => $saldiri['mesaj'], 'durum' => $saldiri['durum']] : null,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiIstisna($e, 'Cevap kaydedilemedi.');
}
