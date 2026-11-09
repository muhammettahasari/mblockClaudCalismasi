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
 *  - [2026-11] ROL AYRIMI: öğrenci girişi oturuma 'ogretmen_id' yazıyordu ve
 *    öğretmen kapıcıları SADECE o anahtara baktığı için her öğrenci öğretmen
 *    API'lerini çağırabiliyordu. Artık öğretmen yetkisi '_rol' = 'ogretmen'
 *    damgası ister; damgasız 'ogretmen_id' oturum açılırken silinir.
 *    Girişler girisRoluAyarla() ile yapılır (kimlik yenilenir, öbür rol silinir).
 *  - [2026-11] KÖKEN KONTROLÜ: kapıcılar GET dışındaki isteklerde Origin /
 *    Referer başka bir siteyi gösteriyorsa isteği reddeder (CSRF kalkanı).
 *  - [2026-11] Kaba kuvvet sayacı oturumdan VERİTABANINA taşındı
 *    (giris_denemeleri tablosu). Oturumda tutulan sayaç, çerezi silen
 *    saldırganı hiç durdurmuyordu.
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

    // [2026-11] Rol damgası olmayan öğretmen kimliği geçersizdir.
    // Eski student_login.php öğrencinin oturumuna da 'ogretmen_id' yazıyordu;
    // o oturumlar hâlâ açıksa burada temizlenir.
    if (!empty($_SESSION['ogretmen_id']) && ($_SESSION['_rol_ogretmen'] ?? false) !== true) {
        unset($_SESSION['ogretmen_id'], $_SESSION['ogretmen_ad'], $_SESSION['ogretmen_pin']);
    }

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

/**
 * [2026-11] Başarılı bir girişten sonra çağrılır.
 * Oturum kimliğini yeniler (session fixation) ve ÖBÜR rolün anahtarlarını
 * siler — aynı tarayıcıda önce öğrenci sonra öğretmen girişi yapılırsa
 * iki kimlik üst üste binmesin.
 *
 * @param string $rol 'ogrenci' | 'ogretmen'
 */
function girisRoluAyarla(string $rol): void
{
    guvenliOturumBaslat();
    oturumKimligiYenile();

    $ogrenciAnahtarlari  = ['ogrenci_id', 'ogrenci_ad', 'sinif_id', 'ogrencinin_ogretmen_id',
                            'hata_sayaci', 'bolum_onay_bileti'];
    $ogretmenAnahtarlari = ['ogretmen_id', 'ogretmen_ad', 'ogretmen_pin', 'okul_adi', '_rol_ogretmen'];

    $silinecek = $rol === 'ogretmen' ? $ogrenciAnahtarlari : $ogretmenAnahtarlari;
    foreach ($silinecek as $k) {
        unset($_SESSION[$k]);
    }
    if ($rol === 'ogretmen') {
        $_SESSION['_rol_ogretmen'] = true;
    }
}

/**
 * [2026-11] KÖKEN (ORIGIN) KONTROLÜ — CSRF'e karşı ikinci kalkan.
 * GET/HEAD dışındaki isteklerde tarayıcının gönderdiği Origin (yoksa Referer)
 * bizim sunucumuzu göstermiyorsa isteği durdurur. Başlık hiç yoksa
 * (eski tarayıcı / gizlilik eklentisi) geçirir — SameSite çerez zaten var.
 */
function istekKokeniDogrula(bool $json = true): void
{
    $yontem = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($yontem === 'GET' || $yontem === 'HEAD' || $yontem === 'OPTIONS') {
        return;
    }

    $kaynak = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($kaynak === '' || $kaynak === 'null') {
        return;
    }

    $p = parse_url($kaynak);
    $kaynakHost = strtolower(($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : ''));
    $bizimHost  = strtolower($_SERVER['HTTP_HOST'] ?? '');

    // Standart port yazılmış/yazılmamış farkını yok say
    $sade = static fn(string $h): string => preg_replace('/:(80|443)$/', '', $h);

    if ($kaynakHost === '' || $sade($kaynakHost) !== $sade($bizimHost)) {
        if ($json) {
            apiYetkisiz('İstek başka bir siteden geldiği için reddedildi.');
        }
        http_response_code(403);
        exit('İstek başka bir siteden geldiği için reddedildi.');
    }
}

/* --------------------------------------------------------------------
 * 3. SAYFA KAPICILARI (HTML sayfalar için — yönlendirir)
 * ------------------------------------------------------------------ */
function superAdminZorunlu(): void
{
    guvenliOturumBaslat();
    istekKokeniDogrula(false);
    if (empty($_SESSION['super_admin_id'])) {
        $hedef = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: /mblock_platform/admin/super_giris.php?donus=' . $hedef);
        exit;
    }
    // [2026-11] Varsayılan şifreyle girildiyse şifre değiştirme sayfası dışında hiçbir yer açılmaz
    if (!empty($_SESSION['super_sifre_degismeli'])) {
        $sayfa = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (!in_array($sayfa, ['super_sifre_degistir.php', 'super_cikis.php'], true)) {
            header('Location: /mblock_platform/admin/super_sifre_degistir.php?zorunlu=1');
            exit;
        }
    }
}

function ogretmenZorunlu(): void
{
    guvenliOturumBaslat();
    istekKokeniDogrula(false);
    if (empty($_SESSION['ogretmen_id'])) {
        header('Location: /mblock_platform/index.php');
        exit;
    }
}

function ogrenciZorunlu(): void
{
    guvenliOturumBaslat();
    istekKokeniDogrula(false);
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
    istekKokeniDogrula();
    if (empty($_SESSION['super_admin_id'])) {
        apiYetkisiz('Süper Admin girişi gerekli.');
    }
    if (!empty($_SESSION['super_sifre_degismeli'])) {
        apiYetkisiz('Önce varsayılan Süper Admin şifresini değiştirin.');
    }
    return (int) $_SESSION['super_admin_id'];
}

function ogretmenZorunluApi(): int
{
    guvenliOturumBaslat();
    istekKokeniDogrula();
    if (empty($_SESSION['ogretmen_id'])) {
        apiYetkisiz('Öğretmen girişi gerekli.');
    }
    return (int) $_SESSION['ogretmen_id'];
}

function ogrenciZorunluApi(): int
{
    guvenliOturumBaslat();
    istekKokeniDogrula();
    if (empty($_SESSION['ogrenci_id'])) {
        apiYetkisiz('Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.');
    }
    return (int) $_SESSION['ogrenci_id'];
}

/* Öğretmen VEYA Süper Admin (ortak paneller için) */
function yoneticiZorunluApi(): void
{
    guvenliOturumBaslat();
    istekKokeniDogrula();
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
 * 6. KABA KUVVET (BRUTE FORCE) KİLİDİ — [2026-11] veritabanında
 * Sayaç 'giris_denemeleri' tablosunda tutulur (tablo yoksa kendisi kurar).
 * Anahtar örnekleri: 'pin|ip:1.2.3.4', 'ogrenci|hesap:12|ali'
 *
 * Kullanımı:
 *    girisDenemesiKontrol('ogrenci|hesap:' . $kimlik, 5, 300);   // kilitliyse burada durur
 *    ... şifreyi kontrol et ...
 *    girisDenemesiKaydet('ogrenci|hesap:' . $kimlik, $basarili, 5, 300);
 *
 * ⚠️ Okulda bütün sınıf TEK IP'den çıkar. IP anahtarlı sınırları
 *    cömert tut, sıkı sınırı hesaba (kullanıcı adına) koy.
 * ------------------------------------------------------------------ */
function istemciIp(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function girisDenemeTablosu(): ?PDO
{
    global $pdo;
    static $hazir = false;
    if (!$pdo instanceof PDO) {
        return null;
    }
    if (!$hazir) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `giris_denemeleri` (
                `anahtar`     varchar(191) NOT NULL,
                `sayi`        int(11)      NOT NULL DEFAULT 0,
                `ilk_deneme`  datetime     NOT NULL,
                `kilit_bitis` datetime     DEFAULT NULL,
                PRIMARY KEY (`anahtar`),
                KEY `idx_ilk_deneme` (`ilk_deneme`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {
            error_log('[guvenlik] giris_denemeleri kurulamadı: ' . $e->getMessage());
            return null;
        }
        $hazir = true;
    }
    return $pdo;
}

/** @return int Kilit bitene kadar kalan saniye (0 = kilitli değil) */
function girisKilitKalan(string $anahtar): int
{
    $db = girisDenemeTablosu();
    if (!$db) {
        return 0;
    }
    $st = $db->prepare("SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), kilit_bitis))
                          FROM giris_denemeleri WHERE anahtar = ?");
    $st->execute([mb_substr($anahtar, 0, 191)]);
    return (int) $st->fetchColumn();
}

function girisDenemesiKontrol(string $anahtar, int $maxDeneme = 5, int $kilitSaniye = 300): void
{
    $kalan = girisKilitKalan($anahtar);
    if ($kalan > 0) {
        $dk = (int) ceil($kalan / 60);
        apiYetkisiz("Çok fazla hatalı deneme yapıldı. {$dk} dakika sonra tekrar deneyin.");
    }
}

/**
 * Hatalı denemeyi sayar. $maxDeneme hata, ilk hatadan itibaren $kilitSaniye
 * içinde birikirse anahtar $kilitSaniye boyunca kilitlenir.
 */
function girisDenemesiKaydet(string $anahtar, bool $basarili, int $maxDeneme = 5, int $kilitSaniye = 300): void
{
    $db = girisDenemeTablosu();
    if (!$db) {
        return;
    }
    $anahtar = mb_substr($anahtar, 0, 191);

    try {
        if ($basarili) {
            $db->prepare("DELETE FROM giris_denemeleri WHERE anahtar = ?")->execute([$anahtar]);
            return;
        }

        // Süresi geçmiş pencereyi sıfırla, sonra say
        $db->prepare(
            "INSERT INTO giris_denemeleri (anahtar, sayi, ilk_deneme) VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE
               sayi       = IF(ilk_deneme < NOW() - INTERVAL ? SECOND AND (kilit_bitis IS NULL OR kilit_bitis < NOW()), 1, sayi + 1),
               ilk_deneme = IF(ilk_deneme < NOW() - INTERVAL ? SECOND AND (kilit_bitis IS NULL OR kilit_bitis < NOW()), NOW(), ilk_deneme)"
        )->execute([$anahtar, $kilitSaniye, $kilitSaniye]);

        $db->prepare(
            "UPDATE giris_denemeleri
                SET kilit_bitis = NOW() + INTERVAL ? SECOND, sayi = 0, ilk_deneme = NOW()
              WHERE anahtar = ? AND sayi >= ?"
        )->execute([$kilitSaniye, $anahtar, $maxDeneme]);

        // Ara sıra eski kayıtları süpür
        if (random_int(1, 50) === 1) {
            $db->exec("DELETE FROM giris_denemeleri
                        WHERE ilk_deneme < NOW() - INTERVAL 1 DAY
                          AND (kilit_bitis IS NULL OR kilit_bitis < NOW())");
        }
    } catch (PDOException $e) {
        error_log('[guvenlik] girisDenemesiKaydet: ' . $e->getMessage());
    }
}

/**
 * [2026-11] YÜKLENEN DOSYA GERÇEKTEN RESİM Mİ?
 * Uzantı kolay değiştirilir; asıl kontrol içeriğe bakılarak yapılır.
 *  - jpg/png/gif/webp → getimagesize() bir resim boyutu döndürmeli
 *  - svg              → XML olmalı, betik / olay özniteliği / dış bağlantı İÇERMEMELİ
 *
 * @param string $geciciYol $_FILES[...]['tmp_name']
 * @param string $uzanti    küçük harf uzantı (beyaz listeden geçmiş olmalı)
 */
function yuklenenResimGecerliMi(string $geciciYol, string $uzanti, int $azamiBayt = 8388608): bool
{
    if (!is_uploaded_file($geciciYol) && !is_file($geciciYol)) {
        return false;
    }
    $boyut = @filesize($geciciYol);
    if ($boyut === false || $boyut <= 0 || $boyut > $azamiBayt) {
        return false;
    }

    if ($uzanti === 'svg') {
        $icerik = (string) @file_get_contents($geciciYol);
        if (stripos($icerik, '<svg') === false) {
            return false;
        }
        $yasak = '/<\s*script|<\s*foreignObject|<\s*iframe|<\s*embed|<\s*object|\son[a-z]+\s*=|javascript\s*:|<!ENTITY|xlink:href\s*=\s*["\']\s*(?!#|data:image\/)/i';
        return !preg_match($yasak, $icerik);
    }

    $olcu = @getimagesize($geciciYol);
    return $olcu !== false && ($olcu[0] ?? 0) > 0 && ($olcu[1] ?? 0) > 0;
}

/**
 * Öğrenci adı / kullanıcı adı gibi serbest metinleri kayıttan ÖNCE sadeleştirir:
 * HTML etiketlerini ve kontrol karakterlerini atar, boşlukları toplar, boyu keser.
 * (Asıl XSS kalkanı yine ÇIKTIDA temizle()/textContent'tir; bu ikinci kat.)
 */
function metinSadelestir($deger, int $maxUzunluk = 100): string
{
    $m = strip_tags((string) $deger);
    $m = preg_replace('/[\x00-\x1F\x7F<>"`]/u', '', $m) ?? '';
    $m = trim(preg_replace('/\s+/u', ' ', $m) ?? '');
    return mb_substr($m, 0, $maxUzunluk);
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
