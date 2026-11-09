<?php
/* ==========================================================
   Dosya Yolu: api/teacher_add_student.php
   Kullanıcı: Öğretmen (ogrenci_yonetimi.php)
   Halk dilinde: Öğretmenin sınıfına tek öğrenci eklemesi (otomatik onaylı).
   [2026-11] Kapıcı ogretmenZorunluApi(); sınıf GERÇEKTEN bu öğretmenin mi
   kontrol edilir (eskiden başka öğretmenin sınıfına öğrenci eklenebiliyordu);
   ad/kullanıcı adı metinSadelestir(); kullanıcı adı benzersizliği siniflar
   zinciriyle.
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';
$ogretmen_id = ogretmenZorunluApi();
header('Content-Type: application/json; charset=utf-8');

$veri = istekVerisi();
$sinif_id = (int)($veri['sinif_id'] ?? 0);
$orj_ad = metinSadelestir($veri['ad_soyad'] ?? '', 60);
$orj_kadi = metinSadelestir($veri['kullanici_adi'] ?? '', 40);
$sifre = trim($veri['sifre'] ?? '');

if (!$sinif_id || empty($orj_ad) || empty($orj_kadi) || empty($sifre)) {
    echo json_encode(['success' => false, 'message' => 'Tüm alanları doldurunuz.']); exit;
}

$sahip = $pdo->prepare("SELECT id FROM siniflar WHERE id = ? AND ogretmen_id = ?");
$sahip->execute([$sinif_id, $ogretmen_id]);
if (!$sahip->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Bu sınıf size ait değil.'], JSON_UNESCAPED_UNICODE); exit;
}

$ad = $orj_ad;
$kadi = $orj_kadi;
$modifiye_edildi = false;

// İsim Çakışması Kontrolü
$sayac = 2;
$isim_kontrol = $pdo->prepare("SELECT id FROM ogrenciler WHERE ad_soyad = ? AND sinif_id = ?");
while(true) {
    $isim_kontrol->execute([$ad, $sinif_id]);
    if ($isim_kontrol->rowCount() == 0) break;
    $ad = $orj_ad . " " . $sayac;
    $sayac++;
    $modifiye_edildi = true;
}


// Kullanıcı Adı Çakışması Kontrolü
$sayac = 2;
// YENİ: Sadece bu öğretmenin okulunda ara
$kadi_kontrol = $pdo->prepare("SELECT o.id FROM ogrenciler o JOIN siniflar s ON s.id = o.sinif_id WHERE o.kullanici_adi = ? AND s.ogretmen_id = ?");
while(true) {
    $kadi_kontrol->execute([$kadi, $ogretmen_id]);
    if ($kadi_kontrol->rowCount() == 0) break;
    $kadi = $orj_kadi . $sayac;
    $sayac++;
    $modifiye_edildi = true;
}
try {
    // YENİ: Öğretmen eklediği için onay_durumu direkt 1 (Onaylı) olarak kaydediliyor.
    $sorgu = $pdo->prepare("INSERT INTO ogrenciler (ad_soyad, kullanici_adi, sifre, ogretmen_id, sinif_id, durum, onay_durumu) VALUES (?, ?, ?, ?, ?, 'aktif', 1)");
    $sorgu->execute([$ad, $kadi, $sifre, $ogretmen_id, $sinif_id]);

    /* [2026-08] Avatar satırını KAYIT ANINDA aç.
       Eskiden bu satır sadece öğrenci profil sayfasını açtığında oluşuyordu;
       hiç profiline girmemiş öğrencide arena günlük savunma kalkanı çalışmıyordu. */
    $pdo->prepare("INSERT IGNORE INTO ogrenci_avatar_durum (ogrenci_id) VALUES (?)")
        ->execute([(int) $pdo->lastInsertId()]);

    $mesaj = "Öğrenci başarıyla eklendi ve otomatik onaylandı!";
    if($modifiye_edildi) $mesaj .= " (İsim veya kullanıcı adı çakıştığı için sonuna rakam eklendi).";
    
    echo json_encode(['success' => true, 'message' => $mesaj]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası.']);
}
?>