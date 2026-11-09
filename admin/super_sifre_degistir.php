<?php
/* ====================================================================
 * MODÜL: Süper Admin Hesap Ayarları
 * DOSYA YOLU: admin/super_sifre_degistir.php
 * KULLANICI: Süper Admin
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Patronun kendi kullanıcı adını ve şifresini değiştirdiği yer. Yeni şifre
 * password_hash() ile şifrelenerek saklanır, düz metin hiçbir yerde durmaz.
 *
 * TEMEL İŞLEVLER:
 *  1. Mevcut şifre doğrulamalı şifre değiştirme
 *  2. Şifre doğrulamalı kullanıcı adı değiştirme
 *  3. CSRF korumalı formlar
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (Bilinen Sorunlar #1).
 * ==================================================================== */

require_once __DIR__ . '/super_auth.php';

superAdminZorunlu();

$adminId = (int) $_SESSION['super_admin_id'];
$admin   = superAdminBilgi($pdo);
$mesaj   = '';
$mesajTuru = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfDogrulaSayfa('super_sifre_degistir.php');

    $islem = $_POST['islem'] ?? '';

    if ($islem === 'sifre') {
        $sonuc = superAdminSifreDegistir(
            $pdo,
            $adminId,
            $_POST['mevcut_sifre'] ?? '',
            $_POST['yeni_sifre'] ?? '',
            $_POST['yeni_sifre_tekrar'] ?? ''
        );
    } elseif ($islem === 'kullanici') {
        $sonuc = superAdminKullaniciDegistir(
            $pdo,
            $adminId,
            $_POST['yeni_kullanici'] ?? '',
            $_POST['dogrulama_sifre'] ?? ''
        );
    } else {
        $sonuc = ['basarili' => false, 'mesaj' => 'Geçersiz işlem.'];
    }

    $mesaj     = $sonuc['mesaj'];
    $mesajTuru = $sonuc['basarili'] ? 'basarili' : 'hata';
    $admin     = superAdminBilgi($pdo);
}

$varsayilanSifreUyarisi = superAdminVarsayilanSifreMi($pdo, $adminId);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Hesap Ayarları | Süper Admin</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f172a; color: #f8fafc; margin: 0; padding: 30px 20px;
        }
        .sarmal { max-width: 560px; margin: 0 auto; }
        .ust {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 25px;
        }
        .ust h1 { margin: 0; font-size: 22px; }
        .geri {
            background: #334155; color: #f8fafc; padding: 8px 16px;
            border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: bold;
        }
        .kart {
            background: #1e293b; border: 1px solid #334155; border-radius: 12px;
            padding: 25px; margin-bottom: 20px;
        }
        .kart h2 {
            margin: 0 0 18px 0; font-size: 17px; color: #f8fafc;
            padding-bottom: 12px; border-bottom: 1px solid #334155;
        }
        .form-grup { margin-bottom: 16px; }
        .form-grup label {
            display: block; font-weight: bold; color: #cbd5e1;
            margin-bottom: 7px; font-size: 13px;
        }
        .form-grup input {
            width: 100%; padding: 11px; border: 2px solid #334155; border-radius: 8px;
            background: #0f172a; color: #f8fafc; font-size: 15px; outline: none; transition: 0.2s;
        }
        .form-grup input:focus { border-color: #3b82f6; }
        .btn {
            padding: 11px 20px; border: none; border-radius: 8px; color: #fff;
            font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.2s;
        }
        .btn-mavi { background: #3b82f6; } .btn-mavi:hover { background: #2563eb; }
        .btn-yesil { background: #10b981; } .btn-yesil:hover { background: #059669; }
        .uyari {
            padding: 13px; border-radius: 8px; margin-bottom: 20px;
            font-weight: bold; font-size: 14px;
        }
        .uyari.basarili { background: #064e3b; color: #a7f3d0; border: 1px solid #059669; }
        .uyari.hata     { background: #7f1d1d; color: #fecaca; border: 1px solid #991b1b; }
        .uyari.dikkat   { background: #78350f; color: #fde68a; border: 1px solid #d97706; }
        .bilgi { color: #94a3b8; font-size: 13px; line-height: 1.7; }
    </style>
</head>
<body>
<?php require __DIR__ . '/../bilesenler/super_gezinme.php'; ?>


<div class="sarmal">
    <div class="ust">
        <h1>⚙️ Hesap Ayarları</h1>
        <a href="super_panel.php" class="geri">← Panele Dön</a>
    </div>

    <?php if (!empty($_SESSION['super_sifre_degismeli'])): ?>
        <div class="uyari hata">
            🔐 Varsayılan şifreyle (<b>123</b>) giriş yaptınız. Güvenlik için devam etmeden önce
            en az 8 karakterlik yeni bir şifre belirleyin. Şifre değişene kadar diğer paneller kapalı.
        </div>
    <?php endif; ?>
    <?php if ($mesaj !== ''): ?>
        <div class="uyari <?= $mesajTuru ?>">
            <?= $mesajTuru === 'basarili' ? '✅' : '❌' ?> <?= temizle($mesaj) ?>
        </div>
    <?php endif; ?>

    <?php if ($varsayilanSifreUyarisi): ?>
        <div class="uyari dikkat">
            ⚠️ Hâlâ varsayılan şifreyi (123) kullanıyorsunuz. Lütfen aşağıdan değiştirin.
        </div>
    <?php endif; ?>

    <div class="kart">
        <h2>🔑 Şifre Değiştir</h2>
        <form method="POST">
            <?= csrfAlani() ?>
            <input type="hidden" name="islem" value="sifre">

            <div class="form-grup">
                <label for="mevcut_sifre">Mevcut Şifre</label>
                <input type="password" id="mevcut_sifre" name="mevcut_sifre" required autocomplete="current-password">
            </div>
            <div class="form-grup">
                <label for="yeni_sifre">Yeni Şifre</label>
                <input type="password" id="yeni_sifre" name="yeni_sifre" required minlength="8" autocomplete="new-password">
            </div>
            <div class="form-grup">
                <label for="yeni_sifre_tekrar">Yeni Şifre (Tekrar)</label>
                <input type="password" id="yeni_sifre_tekrar" name="yeni_sifre_tekrar" required minlength="8" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-yesil">Şifreyi Güncelle</button>
        </form>
    </div>

    <div class="kart">
        <h2>👤 Kullanıcı Adı Değiştir</h2>
        <p class="bilgi" style="margin-top:0">
            Mevcut kullanıcı adınız: <b><?= temizle($admin['kullanici_adi'] ?? '-') ?></b>
        </p>
        <form method="POST">
            <?= csrfAlani() ?>
            <input type="hidden" name="islem" value="kullanici">

            <div class="form-grup">
                <label for="yeni_kullanici">Yeni Kullanıcı Adı</label>
                <input type="text" id="yeni_kullanici" name="yeni_kullanici" required minlength="3">
            </div>
            <div class="form-grup">
                <label for="dogrulama_sifre">Şifreniz (doğrulama için)</label>
                <input type="password" id="dogrulama_sifre" name="dogrulama_sifre" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-mavi">Kullanıcı Adını Güncelle</button>
        </form>
    </div>

    <div class="kart">
        <h2>ℹ️ Hesap Bilgisi</h2>
        <p class="bilgi" style="margin:0">
            Son giriş: <b><?= $admin['son_giris'] ? temizle(date('d.m.Y H:i', strtotime($admin['son_giris']))) : 'İlk giriş' ?></b><br>
            Şifreniz veritabanında <b>password_hash()</b> ile şifrelenmiş olarak saklanır;
            düz metin hâli hiçbir yerde tutulmaz.<br>
            5 hatalı denemede hesap 10 dakika kilitlenir.
        </p>
    </div>
</div>

</body>
</html>
