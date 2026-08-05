<?php
/* ====================================================================
 * MODÜL: Çıkış (API yolu — geriye dönük uyumluluk)
 * DOSYA YOLU: api/logout.php
 * BAĞLI OLDUĞU ANA DOSYALAR: bilesenler/ogrenci_gorev.php, admin/ogretmen_paneli.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Eski çıkış bağlantılarının kırılmaması için duran dosya. Asıl iş
 * kökteki cikis.php'de yapılır.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Oturum çerezi de siliniyor (eskiden sadece session_destroy
 *    çağrılıyordu; çerez tarayıcıda kalıyordu).
 *  - [2026-08] Yönlendirme göreli "../index.php" yerine hesaplanmış mutlak
 *    yola çevrildi — alt klasörlerden çağrıldığında 404 vermesin diye.
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';

guvenliOturumBaslat();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'] ?? 'Lax',
    ]);
}

session_destroy();

/* Projenin kök adresini isteğin kendi yolundan hesapla.
   Örn: /mblock_platform/api/logout.php  ->  /mblock_platform/index.php
   Böylece proje klasörünün adı değişse bile çalışır. */
$kok = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
$kok = rtrim($kok, '/');

header('Location: ' . $kok . '/index.php');
exit;
