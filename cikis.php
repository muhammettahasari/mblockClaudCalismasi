<?php
/* ====================================================================
 * MODÜL: Çıkış (Öğrenci / Öğretmen)
 * DOSYA YOLU: cikis.php
 * BAĞLI OLDUĞU ANA DOSYALAR: ogrenci_paneli.php, profil.php, market.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * "🚪 Çıkış Yap" bağlantısının gittiği yer. Oturumu tamamen kapatır ve
 * giriş ekranına döndürür.
 *
 * NEDEN OLUŞTURULDU:
 * [2026-08] ogrenci_paneli.php içindeki çıkış bağlantısı `cikis.php`
 * adresine gidiyordu ama böyle bir dosya HİÇ YOKTU — bu yüzden öğrenci
 * çıkış yapmaya çalışınca 404 hatası alıyordu.
 *
 * TEMEL İŞLEVLER:
 *  1. Oturum verilerini temizler
 *  2. Oturum çerezini tarayıcıdan da siler (sadece session_destroy yetmez)
 *  3. index.php'ye yönlendirir
 * ==================================================================== */

require_once __DIR__ . '/bilesenler/guvenlik.php';

guvenliOturumBaslat();

// 1. Oturum içeriğini boşalt
$_SESSION = [];

// 2. Oturum çerezini tarayıcıdan sil
//    (session_destroy() sadece sunucudaki dosyayı siler, çerez kalır)
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]
    );
}

// 3. Oturumu kapat
session_destroy();

header('Location: index.php');
exit;
