<?php
/* ====================================================================
 * MODÜL: Quiz Güç Kullanımı (Kılıç / Kalkan / Glitch / Saat)
 * DOSYA: api/quiz_guc_kullan.php
 * BAĞLI OLDUĞU ANA DOSYA: quiz_arena.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğrenci arenada bir güç kullandığında (kılıç fırlatma, kalkan açma vb.)
 * hakkını düşer, hedefe saldırı kaydı bırakır ve sonucu döner.
 *
 * GÜÇ KURALLARI:
 *  - kilic  : Hedef yanlış bilirse -20 / saldıran +20.
 *             Hedefin kalkanı varsa kalkan kırılır, hedef +20, saldıran -20.
 *  - kalkan : Kendini korur, hedef seçmez.
 *  - glitch : Kılıçla aynı puanlama, farklı görsel efekt.
 *  - saat   : Hedef doğru bilirse 2 kat geri teper.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Güç türü beyaz listeye alındı (SQL'e doğrudan gömülüyordu).
 *  - [2026-08] Hedefin AYNI oturumda olması zorunlu (Bilinen Sorunlar #6).
 *  - [2026-08] Kendine saldırma ve bitmiş oturumda saldırma engellendi.
 *  - [2026-08] Hak düşürme + log yazma tek transaction'a alındı.
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$saldiran_id = ogrenciZorunluApi();

$oturum_id = isset($_POST['oturum_id']) ? (int) $_POST['oturum_id'] : 0;
$hedef_id  = isset($_POST['hedef_id']) ? (int) $_POST['hedef_id'] : 0;

/* Güç türü doğrudan sütun adına dönüştüğü için BEYAZ LİSTE zorunlu */
$guc_turu = beyazListe($_POST['guc_turu'] ?? '', ['kilic', 'kalkan', 'glitch', 'saat']);

if (!$guc_turu || $oturum_id <= 0) {
    apiHata('Geçersiz güç isteği.');
}

try {
    // 1. Oturum aktif mi?
    $st = $pdo->prepare("SELECT id FROM quiz_oturum WHERE id = ? AND durum = 'aktif'");
    $st->execute([$oturum_id]);
    if (!$st->fetch()) {
        apiHata('Savaş sona ermiş.', 409);
    }

    // 2. Saldıran bu oturumun oyuncusu mu ve hakkı var mı?
    $st = $pdo->prepare("SELECT * FROM quiz_ogrenci WHERE oturum_id = ? AND ogrenci_id = ?");
    $st->execute([$oturum_id, $saldiran_id]);
    $saldiran = $st->fetch(PDO::FETCH_ASSOC);

    if (!$saldiran) {
        apiHata('Bu arenanın oyuncusu değilsiniz.', 403);
    }

    $guc_kolonu = $guc_turu . '_hakki';
    if ((int) $saldiran[$guc_kolonu] <= 0) {
        apiHata('Bu gücü zaten kullandın!');
    }

    /* ---- KALKAN: kendini korur ---- */
    if ($guc_turu === 'kalkan') {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE quiz_ogrenci SET {$guc_kolonu} = 0, kalkan_aktif = 1 WHERE id = ?")
            ->execute([$saldiran['id']]);
        $pdo->commit();

        apiBasari(['durum' => 'kalkan_acildi'], 'Kalkan Aktifleştirildi! 🛡️');
    }

    /* ---- SALDIRI GÜÇLERİ ---- */
    if ($hedef_id <= 0) {
        apiHata('Hedef seçilmedi.');
    }
    if ($hedef_id === $saldiran_id) {
        apiHata('Kendine saldıramazsın!');
    }

    // Hedef AYNI oturumda mı? (başka sınıfın oyuncusuna vurulamaz)
    $st = $pdo->prepare("SELECT * FROM quiz_ogrenci WHERE oturum_id = ? AND ogrenci_id = ?");
    $st->execute([$oturum_id, $hedef_id]);
    $hedef = $st->fetch(PDO::FETCH_ASSOC);

    if (!$hedef) {
        apiHata('Hedef bu arenada değil.');
    }

    $durum                = 'bekliyor';
    $saldiran_puan_etkisi = 0;
    $savunan_puan_etkisi  = 0;
    $mesaj                = 'Saldırı yola çıktı! 🚀';

    $pdo->beginTransaction();

    // Hakkı düş
    $pdo->prepare("UPDATE quiz_ogrenci SET {$guc_kolonu} = 0 WHERE id = ?")->execute([$saldiran['id']]);

    // ÖZEL DURUM: Kılıç atıldı ama hedefin kalkanı var → geri teper
    if ($guc_turu === 'kilic' && (int) $hedef['kalkan_aktif'] === 1) {
        $pdo->prepare("UPDATE quiz_ogrenci SET kalkan_aktif = 0, puan = puan + 20 WHERE id = ?")
            ->execute([$hedef['id']]);
        $pdo->prepare("UPDATE quiz_ogrenci SET puan = puan - 20 WHERE id = ?")
            ->execute([$saldiran['id']]);

        $durum                = 'kalkan_kirdi';
        $saldiran_puan_etkisi = -20;
        $savunan_puan_etkisi  = 20;
        $mesaj = 'Eyvah! Hedefin kalkanı vardı. Kalkanı kırdın ama kılıcın geri tepti ve 20 Puan kaybettin! 💔';
    }

    // Savaş logunu yaz (savunan tarafın ajanı buradan okuyacak)
    $pdo->prepare(
        "INSERT INTO quiz_savas_log
            (oturum_id, saldiran_id, savunan_id, yetenek_turu, durum, saldiran_puan_etkisi, savunan_puan_etkisi)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    )->execute([$oturum_id, $saldiran_id, $hedef_id, $guc_turu, $durum, $saldiran_puan_etkisi, $savunan_puan_etkisi]);

    $pdo->commit();

    apiBasari(['durum' => $durum], $mesaj);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiIstisna($e, 'Güç kullanılamadı.');
}
