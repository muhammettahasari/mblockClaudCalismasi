<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

$ogretmen_id = $_SESSION['ogretmen_id'] ?? 0;
if (!$ogretmen_id) { echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.']); exit; }

$veri = json_decode(file_get_contents("php://input"), true);
$sinif_id = (int)($veri['sinif_id'] ?? 0);
$ogrenciler = $veri['ogrenciler'] ?? [];

if (!$sinif_id || empty($ogrenciler)) {
    echo json_encode(['success' => false, 'message' => 'Sınıf seçilmedi veya liste boş.']);
    exit;
}

$eklenen = 0;
$degistirilen = 0;

// YENİ: Toplu eklenen öğrenciler de direkt onay_durumu = 1 (Onaylı) olarak kaydediliyor.
$sorgu = $pdo->prepare("INSERT INTO ogrenciler (ad_soyad, kullanici_adi, sifre, ogretmen_id, sinif_id, durum, onay_durumu) VALUES (?, ?, ?, ?, ?, 'aktif', 1)");
$isim_kontrol = $pdo->prepare("SELECT id FROM ogrenciler WHERE ad_soyad = ? AND sinif_id = ?");
$kadi_kontrol = $pdo->prepare("SELECT id FROM ogrenciler WHERE kullanici_adi = ? AND ogretmen_id = ?");

/* [2026-08] Avatar satırı sorgusu döngü DIŞINDA hazırlanır —
   yüzlerce öğrenci eklenirken her tur yeniden prepare edilmesin. */
$avatarAc = $pdo->prepare("INSERT IGNORE INTO ogrenci_avatar_durum (ogrenci_id) VALUES (?)");

foreach ($ogrenciler as $ogr) {
    $orj_ad = trim($ogr['Ad Soyad'] ?? '');
    $orj_kadi = trim($ogr['Kullanıcı Adı'] ?? '');
    $sifre = trim($ogr['Şifre'] ?? '');

    if (!empty($orj_ad) && !empty($orj_kadi) && !empty($sifre)) {
        $ad = $orj_ad;
        $kadi = $orj_kadi;
        $modifiye_edildi = false;

        // İsim Çakışması Kontrolü (Aynı sınıfta aynı isim varsa rakam ekle)
        $sayac = 2;
        while(true) {
            $isim_kontrol->execute([$ad, $sinif_id]);
            if ($isim_kontrol->rowCount() == 0) break;
            $ad = $orj_ad . " " . $sayac;
            $sayac++;
            $modifiye_edildi = true;
        }

        // Kullanıcı Adı Çakışması Kontrolü (Tüm sistemde varsa rakam ekle)
        $sayac = 2;
        while(true) {
           $kadi_kontrol->execute([$kadi, $ogretmen_id]);
            if ($kadi_kontrol->rowCount() == 0) break;
            $kadi = $orj_kadi . $sayac;
            $sayac++;
            $modifiye_edildi = true;
        }

        $sorgu->execute([$ad, $kadi, $sifre, $ogretmen_id, $sinif_id]);

        /* [2026-08] Avatar satırını kayıt anında aç (bkz. teacher_add_student.php) */
        $avatarAc->execute([(int) $pdo->lastInsertId()]);

        $eklenen++;
        if ($modifiye_edildi) $degistirilen++;
    }
}

$mesaj = "$eklenen öğrenci başarıyla eklendi ve otomatik onaylandı!";
if ($degistirilen > 0) {
    $mesaj .= "\n\nDİKKAT: Sistemde aynı isim/kullanıcı adına sahip $degistirilen öğrenci bulunduğu için sonlarına rakam eklendi (Kırmızı renkle gösterilecekler).";
}

echo json_encode(['success' => true, 'message' => $mesaj]);
?>