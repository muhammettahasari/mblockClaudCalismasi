<?php
/* ====================================================================
 * MODÜL: Süper Admin Çıkış
 * DOSYA YOLU: admin/super_cikis.php
 * NE İŞE YARAR: Sadece Süper Admin kimliğini oturumdan siler. Aynı
 * tarayıcıda açık bir öğretmen/öğrenci oturumu varsa ona dokunmaz.
 * YENİ EKLENEN ÖZELLİKLER: - [2026-08] Dosya oluşturuldu.
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';

guvenliOturumBaslat();

unset($_SESSION['super_admin_id'], $_SESSION['super_admin_ad'], $_SESSION['super_kullanici']);
oturumKimligiYenile();

header('Location: super_giris.php');
exit;
