<?php
/* ==========================================================
   Dosya Yolu: api/password_reset.php
   Kullanıcı: Giriş ekranındaki öğretmen ("Şifremi Unuttum")
   Halk dilinde: Öğretmene 1 saat geçerli şifre sıfırlama bağlantısı e-postalar.

   YENİ EKLENEN ÖZELLİKLER:
   - [2026-11] Bağlantı adresi düzeltildi: eskiden sabit
     "http://localhost/mblock_egitim" yazıyordu (klasör adı yanlış → 404).
     Artık proje klasörü otomatik bulunur. Sunucu adı yalnızca güvenilir
     listeden (SITE_ADRESI sabiti / localhost / yerel ağ IP'leri) alınır;
     aksi halde saldırgan sahte "Host" başlığıyla bağlantıyı kendi sitesine
     yönlendirip sıfırlama anahtarını çalabilirdi.
     Site internete açılınca aşağıdaki SITE_ADRESI'ni doldurun.
   - [2026-11] Aynı IP'den 10 dakikada en fazla 5 istek.
   - [2026-11] "Bu e-posta kayıtlı değil" denmez (hangi e-postaların kayıtlı
     olduğu dışarıdan öğrenilemesin); cevap her durumda aynıdır.
   ⚠️ XAMPP'te mail() varsayılan olarak ÇALIŞMAZ; php.ini / sendmail.ini'de
     SMTP ayarı gerekir. Gönderilemeyen mail error_log'a yazılır.
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';

guvenliOturumBaslat();
istekKokeniDogrula();
header('Content-Type: application/json; charset=utf-8');

/** Site internete açıldığında buraya tam adresi yazın, örn. 'https://akademi.okulum.k12.tr/mblock_platform' */
const SITE_ADRESI = '';

function sifirlamaSiteAdresi(): string
{
    if (SITE_ADRESI !== '') {
        return rtrim(SITE_ADRESI, '/');
    }
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $sadeHost = preg_replace('/:\d+$/', '', $host);
    $yerel = $sadeHost === 'localhost'
          || preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)\d{1,3}\.\d{1,3}(\.\d{1,3})?$/', $sadeHost);
    if (!$yerel || !preg_match('/^[a-z0-9.\-]+(:\d{1,5})?$/', $host)) {
        $host = 'localhost';
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    // Bu dosya <proje>/api/ içinde → proje kökü bir üst klasör
    $kok = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/mblock_platform/api/x.php'))), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $kok;
}

$GENEL_CEVAP = "Bu e-posta adresi sistemde kayıtlıysa kullanıcı adınız ve şifre sıfırlama bağlantınız gönderildi. "
             . "Birkaç dakika içinde gelmezse Süper Admin ile iletişime geçin.";

$veri  = istekVerisi();
$email = trim((string) ($veri['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Lütfen geçerli bir e-posta adresi girin."], JSON_UNESCAPED_UNICODE);
    exit;
}

$ipAnahtari = 'sifre_sifirla|ip:' . istemciIp();
girisDenemesiKontrol($ipAnahtari, 5, 600);
girisDenemesiKaydet($ipAnahtari, false, 5, 600);   // her istek sayılır

try {
    $sorgu = $pdo->prepare("SELECT id, kullanici_adi, email, ad_soyad FROM ogretmenler WHERE email = ?");
    $sorgu->execute([$email]);
    $ogretmen = $sorgu->fetch();

    if ($ogretmen) {
        $token   = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", time() + 3600);

        $pdo->prepare("UPDATE ogretmenler SET reset_token = ?, reset_expires = ? WHERE id = ?")
            ->execute([$token, $expires, $ogretmen['id']]);

        $sifirlama_linki = sifirlamaSiteAdresi() . "/sifre_sifirla.php?token=" . $token;

        $konu  = "Sistem Sifre Sifirlama Talebi";
        $mesaj = "Merhaba " . $ogretmen['ad_soyad'] . ",\n\n"
               . "Kullanici adiniz: " . $ogretmen['kullanici_adi'] . "\n\n"
               . "Lutfen sifrenizi sifirlamak icin asagidaki linke tiklayiniz:\n\n"
               . $sifirlama_linki . "\n\n"
               . "Bu link 1 saat boyunca gecerlidir. Bu istegi siz yapmadiysaniz bu e-postayi yok sayin.";
        $headers = "From: noreply@" . preg_replace('/:\d+$/', '', parse_url(sifirlamaSiteAdresi(), PHP_URL_HOST) ?: 'localhost')
                 . "\r\nContent-Type: text/plain; charset=UTF-8";

        if (!@mail($ogretmen['email'], $konu, $mesaj, $headers)) {
            error_log('[password_reset] mail() gönderemedi (SMTP ayarı yok?) — öğretmen id ' . (int) $ogretmen['id']);
        }
    }

    echo json_encode(["success" => true, "message" => $GENEL_CEVAP], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('[password_reset] ' . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Sistemsel bir hata oluştu."], JSON_UNESCAPED_UNICODE);
}
