<?php
/* ====================================================================
 * MODÜL: Süper Admin Giriş Ekranı
 * DOSYA YOLU: admin/super_giris.php
 * KULLANICI: Süper Admin (patron)
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Eskiden Süper Admin panellerinin hiç giriş ekranı yoktu — adresi bilen
 * herkes öğretmen onaylayabiliyor, PIN görebiliyor, müfredat silebiliyordu.
 * Bu sayfa o kapıyı kapatır.
 *
 * VARSAYILAN GİRİŞ: super / 123  (ilk girişten sonra mutlaka değiştirin)
 *
 * TEMEL İŞLEVLER:
 *  1. Tablo boşsa varsayılan hesabı otomatik kurar.
 *  2. CSRF korumalı giriş formu sunar.
 *  3. 5 hatalı denemede hesabı 10 dakika kilitler.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (Bilinen Sorunlar #1).
 * ==================================================================== */

require_once __DIR__ . '/super_auth.php';

guvenliOturumBaslat();
superAdminHazirla($pdo);

// Zaten girişliyse panele gönder
if (!empty($_SESSION['super_admin_id'])) {
    header('Location: super_panel.php');
    exit;
}

$hata  = '';
$donus = $_GET['donus'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfDogrulaSayfa('super_giris.php');

    $sonuc = superAdminGirisDene($pdo, $_POST['kullanici_adi'] ?? '', $_POST['sifre'] ?? '');

    if ($sonuc['basarili']) {
        $hedef = 'super_panel.php';
        // Açık yönlendirme (open redirect) koruması: sadece kendi sitemize dön
        if ($donus !== '' && str_starts_with($donus, '/mblock_platform/') && !str_contains($donus, '//')) {
            $hedef = $donus;
        }
        header('Location: ' . $hedef);
        exit;
    }
    $hata = $sonuc['mesaj'];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Süper Admin Girişi | mBlock Akademi</title>
    <style>
        * { box-sizing: border-box; }
        body {
            display: flex; justify-content: center; align-items: center;
            min-height: 100vh; margin: 0; background: #0f172a;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .kutu {
            background: #1e293b; padding: 40px; border-radius: 12px;
            width: 100%; max-width: 400px; border: 1px solid #334155;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
        }
        .kutu h1 {
            margin: 0 0 6px 0; color: #f8fafc; font-size: 22px; text-align: center;
        }
        .kutu p.alt {
            margin: 0 0 25px 0; color: #94a3b8; font-size: 13px; text-align: center;
        }
        .form-grup { margin-bottom: 18px; }
        .form-grup label {
            display: block; font-weight: bold; color: #cbd5e1;
            margin-bottom: 8px; font-size: 13px;
        }
        .form-grup input {
            width: 100%; padding: 12px; border: 2px solid #334155;
            border-radius: 8px; font-size: 15px; outline: none;
            background: #0f172a; color: #f8fafc; transition: 0.2s;
        }
        .form-grup input:focus {
            border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        .btn {
            width: 100%; padding: 13px; background: #ef4444; color: #fff;
            border: none; border-radius: 8px; font-size: 16px; font-weight: bold;
            cursor: pointer; transition: 0.2s;
        }
        .btn:hover { background: #dc2626; }
        .hata {
            background: #7f1d1d; color: #fecaca; border: 1px solid #991b1b;
            padding: 12px; border-radius: 8px; margin-bottom: 18px;
            font-size: 14px; font-weight: bold; text-align: center;
        }
        .ipucu {
            margin-top: 22px; padding-top: 18px; border-top: 1px solid #334155;
            color: #64748b; font-size: 12px; text-align: center; line-height: 1.6;
        }
        .geri { display: block; text-align: center; margin-top: 15px; color: #3b82f6; font-size: 13px; text-decoration: none; }
    </style>
</head>
<body>

<div class="kutu">
    <h1>🔐 Süper Admin</h1>
    <p class="alt">Yönetim paneline erişim</p>

    <?php if ($hata !== ''): ?>
        <div class="hata">❌ <?= temizle($hata) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <?= csrfAlani() ?>

        <div class="form-grup">
            <label for="kullanici_adi">Kullanıcı Adı</label>
            <input type="text" id="kullanici_adi" name="kullanici_adi" required autofocus>
        </div>

        <div class="form-grup">
            <label for="sifre">Şifre</label>
            <input type="password" id="sifre" name="sifre" required>
        </div>

        <button type="submit" class="btn">Giriş Yap</button>
    </form>

    <div class="ipucu">
        İlk kurulumda varsayılan giriş: <b>super / 123</b><br>
        Giriş yaptıktan sonra şifrenizi mutlaka değiştirin.
    </div>

    <a href="../index.php" class="geri">← Öğrenci / Öğretmen girişine dön</a>
</div>

</body>
</html>
