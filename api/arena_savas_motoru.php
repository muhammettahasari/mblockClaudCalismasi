<?php
/* ====================================================================
 * MODÜL: Savaş Arenası Motoru (Sınıf İçi PvP)
 * DOSYA: api/arena_savas_motoru.php
 * BAĞLI OLDUĞU ANA DOSYA: bilesenler/skorboard_modulleri/sinif_arenasi.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Bir öğrenci sınıf arkadaşına saldırdığında; kuralları kontrol eden, zarları
 * atan, hasarı ve puanı hesaplayıp savaşı kaydeden ana motor.
 *
 * SAVAŞ KURALLARI:
 *   1. Öğretmen arenayı açmış olmalı (ogretmenler.arena_bitis)
 *   2. Hedef AYNI SINIFTAN olmalı
 *   3. Aynı rakibe 5 gün ara verilmeli
 *   4. Saldırı 20 enerji ister, canın 0'dan büyük olmalı
 *   5. Savunanın günlük savunma kalkanı: günde en fazla 3 savunma
 *   6. Güç sıralamasında ±5 sıra farkı (sıklet kuralı)
 *   7. Yenilen oyuncuya 2 saat koruma kalkanı (ogrenciler.kalkan_bitis)
 *
 * HASAR: genel_guc × rand(0.8–1.2) × (çeviklik şansıyla 1.5 kritik)
 * PUAN : güç farkına göre 5–20 arası. HP kaybı = hasar/10 (min 5, max 40)
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] N+1 SORGU ÇÖZÜLDÜ (Bilinen Sorunlar #10):
 *      Her saldırıda sınıftaki HER öğrenci için 2 sorgu atılıyordu
 *      (30 kişilik sınıfta ~65 sorgu). Artık bilesenler/rpg_matris.php
 *      içindeki sinifGucListesi() sınıfın tamamını TEK sorguda hesaplıyor.
 *  - [2026-08] KOPYA MATRİS KALDIRILDI (#12): stat tablosu artık tek yerde.
 *  - [2026-08] SINIF KONTROLÜ: başka sınıftan öğrenciye saldırı engellendi.
 *  - [2026-08] kalkan_bitis sütunu aktifleştirildi (#14): yenilen oyuncu
 *      2 saat koruma altına alınır, üst üste ezilmez.
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';
require_once __DIR__ . '/../bilesenler/rpg_matris.php';

$saldiran_id = ogrenciZorunluApi();
$savunan_id  = isset($_POST['hedef_id']) ? (int) $_POST['hedef_id'] : 0;

if ($savunan_id <= 0 || $savunan_id === $saldiran_id) {
    apiHata('Geçersiz savaş isteği!');
}

const ARENA_ENERJI_MALIYETI   = 20;
const ARENA_GUNLUK_SAVUNMA    = 3;
const ARENA_SIRA_FARKI        = 5;
const ARENA_BEKLEME_GUN       = 5;
const ARENA_KALKAN_SAAT       = 2;

try {
    /* ---- 1. SALDIRAN VE SAVUNANIN TEMEL BİLGİLERİ ---- */
    $st = $pdo->prepare(
        "SELECT id, ad_soyad, can, enerji, sinif_id, ogretmen_id, kalkan_bitis
           FROM ogrenciler WHERE id IN (?, ?)"
    );
    $st->execute([$saldiran_id, $savunan_id]);

    $kayitlar = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $satir) {
        $kayitlar[(int) $satir['id']] = $satir;
    }

    $saldiran = $kayitlar[$saldiran_id] ?? null;
    $savunan  = $kayitlar[$savunan_id] ?? null;

    if (!$saldiran || !$savunan) {
        apiHata('Karakter bilgileri bulunamadı!');
    }

    /* ---- 2. SINIF KONTROLÜ: sadece kendi sınıfına saldırabilir ---- */
    if ((int) $saldiran['sinif_id'] !== (int) $savunan['sinif_id']
        || (int) $saldiran['ogretmen_id'] !== (int) $savunan['ogretmen_id']) {
        apiHata('Sadece kendi sınıfındaki arkadaşlarına saldırabilirsin!');
    }

    /* ---- 3. ÖĞRETMEN ARENAYI AÇMIŞ MI? ---- */
    $st = $pdo->prepare("SELECT arena_bitis FROM ogretmenler WHERE id = ?");
    $st->execute([$saldiran['ogretmen_id']]);
    $arena_bitis = $st->fetchColumn();

    if (!$arena_bitis || strtotime($arena_bitis) < time()) {
        apiHata('Savaş Arenası kapalı! Lütfen öğretmeninizin arenayı açmasını bekleyin.');
    }

    /* ---- 4. TEMEL DURUM KONTROLLERİ ---- */
    if ((int) $saldiran['enerji'] < ARENA_ENERJI_MALIYETI) {
        apiHata('Yeterli enerjin yok! (Gerekli: ' . ARENA_ENERJI_MALIYETI . ' ⚡)');
    }
    if ((int) $saldiran['can'] <= 0) {
        apiHata('Ağır yaralısın, saldıramazsın! Önce görev yapıp canını doldur. (HP: 0 ❤️)');
    }
    if ((int) $savunan['can'] <= 0) {
        apiHata('Rakip zaten ağır yaralı! Yerdekine vurmak bir savaşçıya yakışmaz.');
    }

    // Yenilgi sonrası koruma kalkanı (kalkan_bitis sütunu artık aktif)
    if (!empty($savunan['kalkan_bitis']) && strtotime($savunan['kalkan_bitis']) > time()) {
        $kalanDk = (int) ceil((strtotime($savunan['kalkan_bitis']) - time()) / 60);
        apiHata("Rakibin son yenilgisinden sonra koruma kalkanı altında! ({$kalanDk} dk kaldı) 🛡️");
    }

    /* ---- 5. AYNI RAKİBE 5 GÜN KURALI ---- */
    $st = $pdo->prepare("SELECT MAX(tarih) FROM arena_savaslari WHERE saldiran_id = ? AND savunan_id = ?");
    $st->execute([$saldiran_id, $savunan_id]);
    $sonSavas = $st->fetchColumn();

    if ($sonSavas && (time() - strtotime($sonSavas)) < (ARENA_BEKLEME_GUN * 86400)) {
        apiHata('Aynı rakibe ' . ARENA_BEKLEME_GUN . ' gün içinde tekrar saldıramazsın! Başka hedefler bul.');
    }

    /* ---- 6. GÜNLÜK SAVUNMA KALKANI ---- */
    /* [2026-08] Avatar satırı olmayan öğrenciler için güvenlik ağı:
       ogrenci_avatar_durum satırı eskiden yalnızca öğrenci profil sayfasını
       açtığında oluşuyordu. Hiç profiline girmemiş bir öğrenciye saldırıldığında
       günlük savunma sayacı yazılamıyor ve "günde en fazla 3 savunma" kuralı
       sessizce devre dışı kalıyordu. Satır artık kayıt anında açılıyor
       (bkz. api/student_register.php) ama eski kayıtlar için burada da garanti. */
    $avatarSatiriAc = $pdo->prepare("INSERT IGNORE INTO ogrenci_avatar_durum (ogrenci_id) VALUES (?)");
    $avatarSatiriAc->execute([$saldiran_id]);
    $avatarSatiriAc->execute([$savunan_id]);

    $st = $pdo->prepare(
        "SELECT gunluk_savunma_sayisi, son_savunma_tarihi
           FROM ogrenci_avatar_durum WHERE ogrenci_id = ?"
    );
    $st->execute([$savunan_id]);
    $savunanKalkan = $st->fetch(PDO::FETCH_ASSOC);

    $bugun = date('Y-m-d');
    $savunmaSayisi = ($savunanKalkan && $savunanKalkan['son_savunma_tarihi'] === $bugun)
        ? (int) $savunanKalkan['gunluk_savunma_sayisi']
        : 0;

    if ($savunmaSayisi >= ARENA_GUNLUK_SAVUNMA) {
        apiHata('Rakibin Günlük Koruma Kalkanı aktif! (Bugün çok hırpalandı 🛡️)');
    }

    /* ---- 7. SIKLET (±5 SIRA) KURALI — sınıf TEK sorguda hesaplanır ---- */
    $sinifListesi = sinifGucListesi($pdo, (int) $saldiran['sinif_id']);

    $saldiranSira = 0;
    $savunanSira  = 0;
    $saldiranStat = ['genel_guc' => TABAN_GUC, 'ceviklik' => TABAN_CEVIKLIK];
    $savunanStat  = ['genel_guc' => TABAN_GUC, 'ceviklik' => TABAN_CEVIKLIK];

    foreach ($sinifListesi as $satir) {
        if ($satir['id'] === $saldiran_id) {
            $saldiranSira = $satir['sira'];
            $saldiranStat = $satir;
        }
        if ($satir['id'] === $savunan_id) {
            $savunanSira = $satir['sira'];
            $savunanStat = $satir;
        }
    }

    if ($saldiranSira && $savunanSira && abs($saldiranSira - $savunanSira) > ARENA_SIRA_FARKI) {
        apiHata("Sıklet uygun değil! Sadece " . ARENA_SIRA_FARKI . " sıra altındaki veya üstündeki "
              . "rakiplere saldırabilirsin. (Senin sıran: {$saldiranSira}, Rakip: {$savunanSira})");
    }

    $saldiranGucu = (int) $saldiranStat['genel_guc'];
    $savunanGucu  = (int) $savunanStat['genel_guc'];

    /* ---- 8. ZARLAR ATILIYOR ---- */
    $saldiranSans   = rand(80, 120) / 100;
    $savunanSans    = rand(80, 120) / 100;
    $saldiranKritik = (rand(1, 100) <= (int) $saldiranStat['ceviklik']) ? 1.5 : 1.0;
    $savunanKritik  = (rand(1, 100) <= (int) $savunanStat['ceviklik']) ? 1.5 : 1.0;

    $saldiranHasar = (int) round($saldiranGucu * $saldiranSans * $saldiranKritik);
    $savunanHasar  = (int) round($savunanGucu * $savunanSans * $savunanKritik);

    $kazananId = ($saldiranHasar >= $savunanHasar) ? $saldiran_id : $savunan_id;

    /* ---- 9. RİSK VE ÖDÜL MATEMATİĞİ ---- */
    $gucFarki = $savunanGucu - $saldiranGucu;

    if ($gucFarki >= 10)       { $kazanilan = 20; $kaybedilen = 5;  }  // Rakip çok güçlü
    elseif ($gucFarki >= 3)    { $kazanilan = 15; $kaybedilen = 8;  }
    elseif ($gucFarki >= -3)   { $kazanilan = 10; $kaybedilen = 10; }  // Denk güçler
    elseif ($gucFarki >= -10)  { $kazanilan = 8;  $kaybedilen = 15; }
    else                       { $kazanilan = 5;  $kaybedilen = 20; }  // Kolay lokma, büyük risk

    $saldiranHpKaybi = min(40, max(5, (int) round($savunanHasar / 10)));
    $savunanHpKaybi  = min(40, max(5, (int) round($saldiranHasar / 10)));

    /* ---- 10. SONUÇLARI İŞLE ---- */
    $pdo->beginTransaction();

    $saldiranYeniEnerji = max(0, (int) $saldiran['enerji'] - ARENA_ENERJI_MALIYETI);
    $savunmaSayisi++;
    $kalkanBitis = date('Y-m-d H:i:s', time() + (ARENA_KALKAN_SAAT * 3600));

    if ($kazananId === $saldiran_id) {
        // ZAFER
        $savunanYeniCan = max(0, (int) $savunan['can'] - $savunanHpKaybi);

        $pdo->prepare(
            "UPDATE ogrenciler SET kalici_puan = kalici_puan + ?, enerji = ? WHERE id = ?"
        )->execute([$kazanilan, $saldiranYeniEnerji, $saldiran_id]);

        // Yenilen oyuncuya koruma kalkanı
        $pdo->prepare(
            "UPDATE ogrenciler
                SET kalici_puan = GREATEST(0, kalici_puan - ?), can = ?, kalkan_bitis = ?
              WHERE id = ?"
        )->execute([$kaybedilen, $savunanYeniCan, $kalkanBitis, $savunan_id]);

        $sonucMesaji = "ZAFER! Rakibi alt ettin ve +{$kazanilan} Puan kazandın. "
                     . "Rakip aldığı {$saldiranHasar} hasar sonucu {$savunanHpKaybi} Can ve {$kaybedilen} Puan kaybetti.";
    } else {
        // BOZGUN
        $saldiranYeniCan = max(0, (int) $saldiran['can'] - $saldiranHpKaybi);

        $pdo->prepare(
            "UPDATE ogrenciler
                SET kalici_puan = GREATEST(0, kalici_puan - ?), enerji = ?, can = ?, kalkan_bitis = ?
              WHERE id = ?"
        )->execute([$kaybedilen, $saldiranYeniEnerji, $saldiranYeniCan, $kalkanBitis, $saldiran_id]);

        $pdo->prepare("UPDATE ogrenciler SET kalici_puan = kalici_puan + ? WHERE id = ?")
            ->execute([$kazanilan, $savunan_id]);

        $sonucMesaji = "BOZGUN! Rakip çok güçlü çıktı. Savuşturma sonucu {$saldiranHpKaybi} Can ve "
                     . "{$kaybedilen} Puan kaybettin. (Rakip +{$kazanilan} Puan kazandı)";
    }

    // Savunanın günlük savunma sayacı
    $pdo->prepare(
        "UPDATE ogrenci_avatar_durum
            SET son_savunma_tarihi = ?, gunluk_savunma_sayisi = ?
          WHERE ogrenci_id = ?"
    )->execute([$bugun, $savunmaSayisi, $savunan_id]);

    // Savaş kaydı
    $pdo->prepare(
        "INSERT INTO arena_savaslari (saldiran_id, savunan_id, kazanan_id, saldiran_hasar, savunan_hasar)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([$saldiran_id, $savunan_id, $kazananId, $saldiranHasar, $savunanHasar]);

    $pdo->commit();

    /* ---- 11. OTOMATİK TEMİZLİK (30 günden eski savaşlar) ---- */
    try {
        $pdo->exec("DELETE FROM arena_savaslari WHERE tarih < NOW() - INTERVAL 30 DAY");
    } catch (Throwable $e) {
        // Temizlik hatası ana savaşı bozmasın
    }

    apiJson([
        'status'          => 'success',
        'success'         => true,
        'mesaj'           => $sonucMesaji,
        'message'         => $sonucMesaji,
        'kazanan_id'      => $kazananId,
        'saldiran_hasar'  => $saldiranHasar,
        'savunan_hasar'   => $savunanHasar,
        'saldiran_kritik' => $saldiranKritik > 1.0,
        'savunan_kritik'  => $savunanKritik > 1.0,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiIstisna($e, 'Savaş sırasında bir hata oluştu.');
}
