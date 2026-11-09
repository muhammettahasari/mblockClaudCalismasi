<?php
/* ==========================================================
   Dosya Yolu: api/student_register.php
   Kullanıcı: Giriş/kayıt ekranındaki öğrenci (index.php)
   Halk dilinde: Öğrencinin kendi kaydını alır; öğretmen onaylayınca
   öğrenci giriş yapabilir.

   YENİ EKLENEN ÖZELLİKLER:
   - [2026-11] Öğretmen kimliği istekten ALINMAZ. Önce verify_pin.php ile
     PIN doğrulanmış olmalı (30 dk geçerli); öğretmen oradan gelir ve seçilen
     sınıfın o öğretmene ait olduğu kontrol edilir. Eskiden konsoldan
     herhangi bir öğretmen/sınıf numarası yazılarak kayıt olunabiliyordu.
   - [2026-11] Ad ve kullanıcı adı metinSadelestir() ile temizlenir (HTML
     etiketi giremez) — öğretmen ekranında XSS'in ilk kalkanı.
   - [2026-11] Aynı IP'den 10 dakikada en fazla 60 kayıt.
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';

guvenliOturumBaslat();
header('Content-Type: application/json; charset=utf-8');

function kayitCevap(bool $ok, string $mesaj): void
{
    echo json_encode(["success" => $ok, "message" => $mesaj], JSON_UNESCAPED_UNICODE);
    exit;
}

$veri = istekVerisi();

$ad        = metinSadelestir($veri['ad_soyad'] ?? '', 60);
$kullanici = metinSadelestir($veri['kullanici_adi'] ?? '', 40);
$sifre     = trim((string) ($veri['sifre'] ?? ''));
$sinif_id  = (int) ($veri['sinif_id'] ?? 0);

if ($ad === '' || $kullanici === '' || $sifre === '' || $sinif_id === 0) {
    kayitCevap(false, "Lütfen tüm alanları doldurun ve sınıfınızı seçin.");
}
if (mb_strlen($sifre) > 64) {
    kayitCevap(false, "Şifre en fazla 64 karakter olabilir.");
}

// PIN adımı gerçekten yapıldı mı?
$ogretmen_id = (int) ($_SESSION['_pin_ogretmen_id'] ?? 0);
if ($ogretmen_id === 0 || (time() - (int) ($_SESSION['_pin_zamani'] ?? 0)) > 1800) {
    kayitCevap(false, "Lütfen önce öğretmeninin PIN kodunu girip sınıfını seç.");
}

$ipAnahtari = 'kayit|ip:' . istemciIp();
girisDenemesiKontrol($ipAnahtari, 60, 600);

try {
    // Sınıf bu öğretmenin mi?
    $st = $pdo->prepare("SELECT id FROM siniflar WHERE id = ? AND ogretmen_id = ?");
    $st->execute([$sinif_id, $ogretmen_id]);
    if (!$st->fetch()) {
        kayitCevap(false, "Seçilen sınıf bu öğretmene ait değil. Sayfayı yenileyip tekrar dene.");
    }

    // Kullanıcı adı öğretmenin sınıflarında benzersiz olmalı (siniflar zinciri)
    $kontrol = $pdo->prepare(
        "SELECT o.id FROM ogrenciler o
           JOIN siniflar s ON s.id = o.sinif_id
          WHERE o.kullanici_adi = ? AND s.ogretmen_id = ?"
    );
    $kontrol->execute([$kullanici, $ogretmen_id]);
    if ($kontrol->fetch()) {
        kayitCevap(false, "Bu kullanıcı adı zaten alınmış. Lütfen başka bir ad deneyin.");
    }

    $sorgu = $pdo->prepare("INSERT INTO ogrenciler (ad_soyad, kullanici_adi, sifre, ogretmen_id, sinif_id, kalici_puan, anlik_puan, durum, onay_durumu, elmas, can, enerji, son_dolum_tarihi) VALUES (?, ?, ?, ?, ?, 0, 0, 'aktif', 0, 0, 100, 100, NOW())");
    $sorgu->execute([$ad, $kullanici, $sifre, $ogretmen_id, $sinif_id]);

    /* [2026-08] Avatar satırını KAYIT ANINDA aç (arena savunma kalkanı için). */
    $pdo->prepare("INSERT IGNORE INTO ogrenci_avatar_durum (ogrenci_id) VALUES (?)")
        ->execute([(int) $pdo->lastInsertId()]);

    // Kayıt sayacı (başarılı kayıtlar da sayılır — toplu sahte kayda fren)
    girisDenemesiKaydet($ipAnahtari, false, 60, 600);

    kayitCevap(true, "Kaydın alındı! Öğretmenin onayladıktan sonra sisteme giriş yapabilirsin.");
} catch (PDOException $e) {
    error_log('[student_register] ' . $e->getMessage());
    kayitCevap(false, "Kayıt sırasında bir hata oluştu.");
}
