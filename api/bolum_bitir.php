<?php
/**
 * @author Muhammet Taha Sarı
 * @description Bölüm bitirme (v1). Puanı kalıcı hesaba aktarır, seviye atlatır
 *              ve ogrenci_ilerleme tablosuna arşiv kaydı düşer.
 *
 * @updates
 *  - [2026-08] BOZUK SORGU DÜZELTİLDİ (Bilinen Sorunlar #3):
 *      Bu dosya `INSERT INTO ogrenci_ilerleme (ogrenci_id, bolum_id)` yazıyordu
 *      ama tabloda bolum_id sütunu YOKTU. PDO ERRMODE_EXCEPTION açık olduğu için
 *      sorgu istisna fırlatıyor ve JSON cevabı hiç dönmüyordu.
 *      Migration ile bolum_id + kaynak + tarih sütunları eklendi.
 *  - [2026-08] ÇİFT AKIŞ TEKLEŞTİRİLDİ:
 *      Seviye atlama hem burada hem update_score.php'de yapılıyordu.
 *      Artık ana akış update_score.php; bu dosya öğretmen onayı GEREKTİRMEYEN
 *      (ör. v2 otomatik doğrulamalı) bölüm bitirmeleri için sade bir yardımcıdır.
 *  - [2026-08] Öğretmen onay bileti kontrolü eklendi — bilet yoksa puan
 *      aktarılmaz, sadece seviye ilerlemesi yapılmaz ve hata döner.
 */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();

/** teacher_approval.php tarafından oluşturulan 5 dakikalık onay bileti */
$bilet = $_SESSION['bolum_onay_bileti'] ?? null;
$biletGecerli = is_array($bilet)
    && (int) ($bilet['ogrenci_id'] ?? 0) === $ogrenci_id
    && (time() - (int) ($bilet['zaman'] ?? 0)) <= 300;

if (!$biletGecerli) {
    apiHata('Öğretmen onayı bulunamadı ya da süresi doldu.', 403);
}

try {
    $pdo->beginTransaction();

    $st = $pdo->prepare("SELECT anlik_puan, mevcut_bolum FROM ogrenciler WHERE id = ? FOR UPDATE");
    $st->execute([$ogrenci_id]);
    $ogrenci = $st->fetch(PDO::FETCH_ASSOC);

    if (!$ogrenci) {
        $pdo->rollBack();
        apiHata('Öğrenci bulunamadı.', 404);
    }

    $kazanilan  = (int) $ogrenci['anlik_puan'];
    $bitenBolum = (int) $ogrenci['mevcut_bolum'];

    // 1. Puanı aktar ve seviye atlat
    $pdo->prepare(
        "UPDATE ogrenciler
            SET kalici_puan  = kalici_puan + anlik_puan,
                elmas        = elmas + anlik_puan,
                anlik_puan   = 0,
                can          = 100,
                enerji       = 100,
                mevcut_bolum = mevcut_bolum + 1,
                durum        = 'aktif'
          WHERE id = ?"
    )->execute([$ogrenci_id]);

    // 2. Arşiv kaydı (artık doğru sütunlarla)
    $pdo->prepare(
        "INSERT INTO ogrenci_ilerleme (ogrenci_id, bolum_id, kaynak, durum, bekleyen_puan)
         VALUES (?, ?, 'v1', 'onaylandi', ?)"
    )->execute([$ogrenci_id, $bitenBolum, $kazanilan]);

    $pdo->commit();

    unset($_SESSION['bolum_onay_bileti'], $_SESSION['hata_sayaci']);

    apiBasari(
        ['yeni_bolum' => $bitenBolum + 1, 'kazanilan_puan' => $kazanilan],
        'Bölüm tamamlandı! Puanlar aktarıldı, yeni seviyeye geçildi ve arşive işlendi.'
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiIstisna($e, 'Bölüm tamamlanamadı.');
}
