<?php
/* ==========================================================
   Dosya Yolu: api/password_reset.php
   İşlev: Öğretmen şifre sıfırlama linki üretir ve mail atar
   ========================================================== */
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

$veri = json_decode(file_get_contents("php://input"), true);
$email = trim($veri['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Lütfen geçerli bir e-posta adresi girin."]);
    exit;
}

try {
    // E-posta adresine göre arama yap ve kullanıcı adını da çek
    $sorgu = $pdo->prepare("SELECT id, kullanici_adi, email, ad_soyad FROM ogretmenler WHERE email = ?");
    $sorgu->execute([$email]);
    $ogretmen = $sorgu->fetch();

    if ($ogretmen) {
        // 1 Saat süreli rastgele bir güvenlik anahtarı oluşturuyoruz
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", time() + 3600);

        $update = $pdo->prepare("UPDATE ogretmenler SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $update->execute([$token, $expires, $ogretmen['id']]);

        // GÖNDERİLECEK MAİL AYARLARI
        $alici = $ogretmen['email'];
        $konu = "Sistem Sifre Sifirlama Talebi";

        // DİKKAT: Site yayına girdiğinde localhost yazan yeri site adresinle değiştirmelisin
        $site_url = "http://localhost/mblock_egitim";
        $sifirlama_linki = $site_url . "/sifre_sifirla.php?token=" . $token;

        $mesaj = "Merhaba " . $ogretmen['ad_soyad'] . ",\n\n";
        $mesaj .= "Kullanici adiniz: " . $ogretmen['kullanici_adi'] . "\n\n";
        $mesaj .= "Lutfen sifrenizi sifirlamak icin asagidaki linke tiklayiniz:\n\n";
        $mesaj .= $sifirlama_linki . "\n\n";
        $mesaj .= "Bu link 1 saat boyunca gecerlidir.";


        // PHP'nin varsayılan Mail gönderme motoru
        $headers = "From: noreply@seninsiten.com";

        // Mail gönderme işlemini kontrol ediyoruz
        $mail_gitti_mi = @mail($alici, $konu, $mesaj, $headers);

        if ($mail_gitti_mi) {
            echo json_encode(["success" => true, "message" => "Kullanıcı adınız ve şifre sıfırlama linkiniz e-posta adresinize başarıyla gönderildi!"]);
        } else {
            // PHP sunucu yüzünden maili atamazsa bu hatayı fırlatır
            echo json_encode(["success" => false, "message" => "Sistem maili hazırladı ancak sunucunun posta (SMTP) ayarları kapalı veya eksik olduğu için gönderilemedi."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Sistemde bu e-posta adresiyle kayıtlı bir hesap bulunamadı!"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Sistemsel bir hata oluştu."]);
}
