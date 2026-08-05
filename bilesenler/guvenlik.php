<?php
/* ====================================================================
 * MODÜL: Merkezi Güvenlik Çekirdeği
 * DOSYA YOLU: bilesenler/guvenlik.php
 * BAĞLI OLDUĞU ANA DOSYA: Projedeki HER PHP dosyası (db.php'den hemen sonra)
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Sitenin kapısındaki güvenlik görevlisi. Kim hangi odaya girebilir onu
 * kontrol eder, oturum çerezlerini çalınmaya karşı sıkılaştırır, formların
 * başkası tarafından taklit edilmesini (CSRF) engeller ve şifre denemelerini
 * sayıp kaba kuvvet saldırısını durdurur.
 *
 * TEMEL İŞLEVLER:
 *  1. guvenliOturumBaslat()  — sıkılaştırılmış session_start
 *  2. superAdminZorunlu() / ogretmenZorunlu() / ogrenciZorunlu()  — sayfa kapıcıları
 *  3. *ZorunluApi()          — API kapıcıları (JSON döner, yönlendirmez)
 *  4. csrfAlani() / csrfDogrula()  — form sahteciliği koruması
 *  5. girisDenemesiKontrol() / girisDenemesiKaydet()  — kaba kuvvet kilidi
 *  6. temizle()              — XSS'e karşı çıktı temizleyici
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu; 3 katmanlı rol sistemi ve CSRF eklendi.
 * ==================================================================== */

require_once __DIR__ . '/../db.php';

/* --------------------------------------------------------------------
 * 1. GÜVENLİ OTURUM BAŞLATMA
 * Çerezi JavaScript'ten okunamaz (HttpOnly) ve başka siteden gönderilemez
 * (SameSite) hale getirir. Tarayıcı kapanınca oturum ölür.
 * ------------------------------------------------------------------ */
function guvenliOturumBaslat(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return; // Zaten başlamış, ikinci kez başlatma
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,          // Tarayıcı kapanınca oturum biter
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,     // HTTPS'e geçilince otomatik aktifleşir
        'httponly' => true,       // XSS ile çerez çalınmasını engeller
        'samesite' => 'Lax',      // CSRF'e karşı ilk savunma
    ]);

    session_start();

    // Oturum sabitleme (session fixation) saldırısına karşı periyodik kimlik yenileme
    if (!isset($_SESSION['_olusturulma'])) {
        $_SESSION['_olusturulma'] = time();
    } elseif (time() - $_SESSION['_olusturulma'] > 1800) { // 30 dakika
        session_regenerate_id(true);
        $_SESSION['_olusturulma'] = time();
    }
}

/* --------------------------------------------------------------------
 * 2. GİRİŞ SONRASI OTURUM TAZELEME
 * Her başarılı girişten sonra çağrılır — eski oturum kimliği geçersizleşir.
 * ------------------------------------------------------------------ */
function oturumKimligiYenile(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION['_olusturulma'] = time();
    }
}

/* --------------------------------------------------------------------
 * 3. SAYFA KAPICILARI (HTML sayfalar için — yönlendirir)
 * ------------------------------------------------------------------ */
function superAdminZorunlu(): void
{
    guvenliOturumBaslat();
    if (empty($_SESSION['super_admin_id'])) {
        $hedef = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: /mblock_platform/admin/super_giris.php?donus=' . $hedef);
        exit;
    }
}

function ogretmenZorunlu(): void
{
    guvenliOturumBaslat();
    if (empty($_SESSION['ogretmen_id'])) {
        header('Location: /mblock_platform/index.php');
        exit;
    }
}

function ogrenciZorunlu(): void
{
    guvenliOturumBaslat();
    if (empty($_SESSION['ogrenci_id'])) {
        header('Location: /mblock_platform/index.php');
        exit;
    }
}

/* --------------------------------------------------------------------
 * 4. API KAPICILARI (JSON dönen uç noktalar için — yönlendirmez)
 * Geriye dönük uyumluluk: hem success hem status hem durum anahtarı döner.
 * ------------------------------------------------------------------ */
function apiYetkisiz(string $mesaj = 'Bu işlem için yetkiniz yok.'): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
    }
    echo json_encode([
        'success' => false,
        'status'  => 'error',
        'durum'   => 'hata',
        'message' => $mesaj,
        'mesaj'   => $mesaj,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function superAdminZorunluApi(): int
{
    guvenliOturumBaslat();
    if (empty($_SESSION['super_admin_id'])) {
        apiYetkisiz('Süper Admin girişi gerekli.');
    }
    return (int) $_SESSION['super_admin_id'];
}

function ogretmenZorunluApi(): int
{
    guvenliOturumBaslat();
    if (empty($_SESSION['ogretmen_id'])) {
        apiYetkisiz('Öğretmen girişi gerekli.');
    }
    return (int) $_SESSION['ogretmen_id'];
}

function ogrenciZorunluApi(): int
{
    guvenliOturumBaslat();
    if (empty($_SESSION['ogrenci_id'])) {
        apiYetkisiz('Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.');
    }
    return (int) $_SESSION['ogrenci_id'];
}

/* Öğretmen VEYA Süper Admin (ortak paneller için) */
function yoneticiZorunluApi(): void
{
    guvenliOturumBaslat();
    if (empty($_SESSION['ogretmen_id']) && empty($_SESSION['super_admin_id'])) {
        apiYetkisiz('Yönetici girişi gerekli.');
    }
}

/* --------------------------------------------------------------------
 * 5. CSRF KORUMASI
 * Formun gerçekten bizim sayfamızdan gönderildiğini kanıtlayan gizli anahtar.
 * Kullanımı:
 *    Form içinde:  <?= csrfAlani() ?>
 *    İşleyen tarafta:  csrfDogrula();
 * ------------------------------------------------------------------ */
function csrfToken(): string
{
    guvenliOturumBaslat();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrfAlani(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrfToken() . '">';
}

function csrfDogrula(?string $token = null): void
{
    guvenliOturumBaslat();

    if ($token === null) {
        // POST gövdesinden veya JSON gövdesinden oku
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($token === '') {
            $ham = json_decode(file_get_contents('php://input'), true);
            $token = $ham['_csrf'] ?? '';
        }
    }

    if (empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], (string) $token)) {
        apiYetkisiz('Güvenlik anahtarı geçersiz. Sayfayı yenileyip tekrar deneyin.');
    }
}

/**
 * HTML form sayfaları için CSRF doğrulaması.
 * csrfDogrula() JSON döndürür; bu ise insan okuyabilir bir hata sayfası basar.
 */
function csrfDogrulaSayfa(string $geriLink = ''): void
{
    guvenliOturumBaslat();
    $token = $_POST['_csrf'] ?? '';

    if (empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], (string) $token)) {
        http_response_code(403);
        $link = $geriLink !== '' ? $geriLink : ($_SERVER['PHP_SELF'] ?? '/');
        exit('<div style="font-family:Segoe UI,sans-serif;padding:40px;max-width:520px;margin:60px auto;'
           . 'background:#fee2e2;border:1px solid #fecaca;border-radius:12px;color:#991b1b">'
           . '<h2 style="margin-top:0">⚠️ Güvenlik anahtarı geçersiz</h2>'
           . '<p>Form çok uzun süre açık kaldı ya da başka bir siteden gönderildi. '
           . 'Lütfen sayfayı yenileyip tekrar deneyin.</p>'
           . '<a href="' . htmlspecialchars($link, ENT_QUOTES) . '" '
           . 'style="color:#991b1b;font-weight:bold">← Sayfayı yenile</a></div>');
    }
}

/* --------------------------------------------------------------------
 * 6. KABA KUVVET (BRUTE FORCE) KİLİDİ
 * Aynı oturumdan arka arkaya hatalı şifre denemelerini yavaşlatır.
 * ------------------------------------------------------------------ */
function girisDenemesiKontrol(string $anahtar, int $maxDeneme = 5, int $kilitSaniye = 300): void
{
    guvenliOturumBaslat();
    $kayit = $_SESSION['_giris_' . $anahtar] ?? ['sayi' => 0, 'kilit' => 0];

    if ($kayit['kilit'] > time()) {
        $kalan = (int) ceil(($kayit['kilit'] - time()) / 60);
        apiYetkisiz("Çok fazla hatalı deneme yaptınız. {$kalan} dakika sonra tekrar deneyin.");
    }
}

function girisDenemesiKaydet(string $anahtar, bool $basarili, int $maxDeneme = 5, int $kilitSaniye = 300): void
{
    guvenliOturumBaslat();
    $k = '_giris_' . $anahtar;

    if ($basarili) {
        unset($_SESSION[$k]);
        return;
    }

    $kayit = $_SESSION[$k] ?? ['sayi' => 0, 'kilit' => 0];
    $kayit['sayi']++;
    if ($kayit['sayi'] >= $maxDeneme) {
        $kayit['kilit'] = time() + $kilitSaniye;
        $kayit['sayi']  = 0;
    }
    $_SESSION[$k] = $kayit;
}

/* --------------------------------------------------------------------
 * 7. YARDIMCILAR
 * ------------------------------------------------------------------ */

/** XSS'e karşı çıktı temizleyici — ekrana basılan her kullanıcı verisinde kullan. */
function temizle($deger): string
{
    return htmlspecialchars((string) $deger, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** İstek gövdesini formatı ne olursa olsun diziye çevirir (JSON / POST / GET). */
function istekVerisi(): array
{
    $json = json_decode(file_get_contents('php://input'), true);
    if (is_array($json) && $json !== []) {
        return $json;
    }
    if (!empty($_POST)) {
        return $_POST;
    }
    return $_GET;
}

/** Tablo/sütun adı gibi sorguya doğrudan gömülecek değerleri beyaz listeye zorlar. */
function beyazListe($deger, array $izinliler, $varsayilan = null)
{
    return in_array($deger, $izinliler, true) ? $deger : $varsayilan;
}

/**
 * TARAYICI ÖNBELLEK KIRICI
 * [2026-08] JS/CSS dosyalarının sonuna dosyanın son değişiklik zamanını ekler.
 *
 * NEDEN GEREKLİ:
 * PHP sayfaları her istekte yeniden üretilir ama .js dosyaları tarayıcıda
 * önbelleğe alınır. Bir JS dosyasını güncellediğimizde kullanıcının tarayıcısı
 * ESKİ sürümü çalıştırmaya devam eder ve "kod düzeldi ama hâlâ hata veriyor"
 * durumu oluşur. (Örn: api_yoneticisi.js güncellendi ama tarayıcı eski
 * sürümü çalıştırdığı için bolum_id gönderilmiyordu.)
 *
 * Dosya değişmediği sürece adres aynı kalır — yani önbellek boşuna bozulmaz.
 *
 * KULLANIMI — yol HER ZAMAN proje kökünden yazılır, ön ek ise sayfanın
 * köke göre konumudur:
 *   Kökteki sayfa (profil.php):
 *      <script src="<?= varlik('js/duello_motoru.js') ?>"></script>
 *   admin/ içindeki sayfa:
 *      <script src="<?= varlik('js/duello_motoru.js', '../') ?>"></script>
 *   v2_gorev_sistemi/admin/ içindeki sayfa:
 *      <script src="<?= varlik('v2_gorev_sistemi/assets/js/admin/api_yoneticisi.js', '../../') ?>"></script>
 *
 * @param string $kokGoreliYol Proje KÖKÜNDEN itibaren dosya yolu
 * @param string $kokOnEki     Sayfadan proje köküne çıkan yol ('', '../', '../../')
 */
function varlik(string $kokGoreliYol, string $kokOnEki = ''): string
{
    $kokGoreliYol = ltrim($kokGoreliYol, '/');

    // Disk yolu: bu dosya bilesenler/ içinde olduğu için bir üst klasör proje köküdür
    $diskYolu = dirname(__DIR__) . '/' . $kokGoreliYol;
    $surum    = is_file($diskYolu) ? filemtime($diskYolu) : time();

    return $kokOnEki . $kokGoreliYol . '?v=' . $surum;
}
