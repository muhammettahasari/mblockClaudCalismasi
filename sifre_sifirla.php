<?php
/* ==========================================================
   Dosya Yolu: sifre_sifirla.php
   İşlev: E-postadan gelen linki doğrular ve şifreyi günceller
   ========================================================== */
require_once 'db.php';

$token = $_GET['token'] ?? '';
$hata = '';
$basarili = false;
$ogretmen_id = 0;

// 1. Gelen anahtarı (token) kontrol et
if (empty($token)) {
    $hata = "Güvenlik anahtarı bulunamadı. Lütfen mailinizdeki linke tekrar tıklayın.";
} else {
    // Anahtar veritabanında var mı ve süresi (1 saat) dolmamış mı kontrol et
    $sorgu = $pdo->prepare("SELECT id FROM ogretmenler WHERE reset_token = ? AND reset_expires > NOW()");
    $sorgu->execute([$token]);
    $ogretmen = $sorgu->fetch();

    if ($ogretmen) {
        $ogretmen_id = $ogretmen['id'];
    } else {
        $hata = "Bu şifre sıfırlama linki geçersiz veya süresi dolmuş. Lütfen yeni bir şifre sıfırlama talebinde bulunun.";
    }
}

// 2. Form gönderildiyse yeni şifreyi kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ogretmen_id > 0) {
    $yeni_sifre = $_POST['yeni_sifre'] ?? '';
    $sifre_tekrar = $_POST['sifre_tekrar'] ?? '';

    if (empty($yeni_sifre) || empty($sifre_tekrar)) {
        $hata = "Lütfen her iki alanı da doldurun.";
    } elseif ($yeni_sifre !== $sifre_tekrar) {
        $hata = "Şifreler birbiriyle eşleşmiyor! Lütfen kontrol edip tekrar yazın.";
    } else {
        // Şifreyi güncelle ve güvenlik anahtarını imha et (Aynı link bir daha kullanılamasın)
        $update = $pdo->prepare("UPDATE ogretmenler SET sifre = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $update->execute([$yeni_sifre, $ogretmen_id]);
        $basarili = true;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Sıfırlama | mBlock Akademi</title>
    <style>
        body { 
            display: flex; justify-content: center; align-items: center; 
            height: 100vh; background-color: #f8fafc; margin: 0; 
            font-family: 'Segoe UI', sans-serif; 
        }
        .reset-box { 
            background: white; padding: 40px; border-radius: 12px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
            width: 100%; max-width: 400px; text-align: center; 
            border: 1px solid #e2e8f0;
        }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; font-weight: bold; color: #475569; margin-bottom: 8px; font-size: 14px; }
        .form-group input { 
            width: 100%; padding: 12px; border: 2px solid #cbd5e1; 
            border-radius: 8px; font-size: 15px; box-sizing: border-box; 
            outline: none; transition: 0.2s; 
        }
        .form-group input:focus { border-color: #4f46e5; }
        .btn-submit { 
            background: #4f46e5; color: white; border: none; padding: 12px 20px; 
            width: 100%; border-radius: 8px; font-size: 16px; font-weight: bold; 
            cursor: pointer; transition: 0.2s; box-shadow: 0 4px 6px rgba(79, 70, 229, 0.3);
        }
        .btn-submit:hover { background: #4338ca; transform: translateY(-1px); }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; font-size: 14px; }
        .alert-danger { background: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; }
        .alert-success { background: #d1fae5; color: #10b981; border: 1px solid #6ee7b7; }
        .back-link { display: inline-block; margin-top: 20px; color: #64748b; text-decoration: none; font-weight: bold; font-size: 14px; transition: 0.2s; }
        .back-link:hover { color: #4f46e5; }
    </style>
</head>
<body>

    <div class="reset-box">
        <h2 style="color: #1e293b; margin-top: 0;">🔒 Güvenli Şifre Sıfırlama</h2>
        
        <?php if ($basarili): ?>
            <div class="alert alert-success">✅ Şifreniz başarıyla değiştirildi!</div>
            <p style="color: #64748b; font-weight: bold; font-size: 14px;">Giriş sayfasına yönlendiriliyorsunuz...</p>
            <script>
                // 2.5 Saniye sonra ana sayfaya otomatik yönlendir
                setTimeout(() => { window.location.href = 'index.php'; }, 2500);
            </script>
        <?php else: ?>
            
            <?php if (!empty($hata)): ?>
                <div class="alert alert-danger">❌ <?= $hata ?></div>
            <?php endif; ?>

            <?php if ($ogretmen_id > 0): ?>
                <p style="color: #64748b; margin-bottom: 25px; font-size: 14px;">Lütfen unutmayacağınız yeni bir şifre belirleyin.</p>
                <form method="POST">
                    <div class="form-group">
                        <label>Yeni Şifre</label>
                        <input type="password" name="yeni_sifre" required placeholder="Yeni şifrenizi girin">
                    </div>
                    <div class="form-group">
                        <label>Yeni Şifre (Tekrar)</label>
                        <input type="password" name="sifre_tekrar" required placeholder="Şifrenizi tekrar girin">
                    </div>
                    <button type="submit" class="btn-submit">Şifremi Güncelle</button>
                </form>
            <?php else: ?>
                <a href="index.php" class="back-link">⬅️ Ana Sayfaya Dön</a>
            <?php endif; ?>

        <?php endif; ?>
    </div>

</body>
</html>