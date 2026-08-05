<?php
/* ====================================================================
 * MODÜL: Süper Admin Ana Paneli (Kontrol Kulesi)
 * DOSYA YOLU: admin/super_panel.php
 * KULLANICI: Süper Admin
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Patronun giriş yaptıktan sonra karşılaştığı ana ekran. Sistemdeki bütün
 * yönetim sayfalarına buradan tek tıkla gidilir; ayrıca sistemin nabzını
 * (kaç öğretmen onay bekliyor, kaç öğrenci var, canavarın canı ne durumda)
 * tek bakışta gösterir.
 *
 * TEMEL İŞLEVLER:
 *  1. Özet istatistik kartları
 *  2. Tüm yönetim sayfalarına kısayol
 *  3. Bakım işlemleri (eşya senkronizasyonu, güç önbelleği yenileme)
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (Bilinen Sorunlar #1).
 * ==================================================================== */

require_once __DIR__ . '/super_auth.php';

superAdminZorunlu();

$adminId = (int) $_SESSION['super_admin_id'];

/* --- Özet istatistikler --- */
$sayac = static function (PDO $pdo, string $sql, array $p = []): int {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return (int) $st->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
};

$bekleyenOgretmen = $sayac($pdo, "SELECT COUNT(*) FROM ogretmenler WHERE durum = 'onay_bekliyor'");
$aktifOgretmen    = $sayac($pdo, "SELECT COUNT(*) FROM ogretmenler WHERE durum = 'onaylandi'");
$toplamOgrenci    = $sayac($pdo, "SELECT COUNT(*) FROM ogrenciler");
$bekleyenOgrenci  = $sayac($pdo, "SELECT COUNT(*) FROM ogrenciler WHERE onay_durumu = 0");
$toplamBolum      = $sayac($pdo, "SELECT COUNT(*) FROM bolumler");
$toplamGorev      = $sayac($pdo, "SELECT COUNT(*) FROM gorevler");
$toplamV2Gorev    = $sayac($pdo, "SELECT COUNT(*) FROM v2_gorevler");
$toplamSoru       = $sayac($pdo, "SELECT COUNT(*) FROM quiz_sorulari");
$toplamEsya       = $sayac($pdo, "SELECT COUNT(*) FROM esya_kutuphanesi");

$canavar = null;
try {
    $canavar = $pdo->query("SELECT adi, mevcut_can, max_can, tur, durum FROM dunya_canavari ORDER BY id DESC LIMIT 1")
                   ->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* yoksay */ }

$varsayilanSifreUyarisi = superAdminVarsayilanSifreMi($pdo, $adminId);

$kartlar = [
    ['baslik' => '👨‍🏫 Öğretmen Yönetimi', 'link' => 'ogretmen_yonetimi.php',
     'aciklama' => 'Başvuruları onayla, PIN üret, üyelik süresi ver.',
     'rozet' => $bekleyenOgretmen > 0 ? $bekleyenOgretmen . ' onay bekliyor' : '', 'renk' => '#ef4444'],

    ['baslik' => '📚 Görev Yönetimi (v1)', 'link' => 'gorev_yonetimi.php',
     'aciklama' => 'Bölümleri ve mBlock görevlerini listele, sırala, sil.',
     'rozet' => $toplamGorev . ' görev', 'renk' => '#3b82f6'],

    ['baslik' => '➕ Yeni Görev Ekle (v1)', 'link' => 'gorev_ekle.php',
     'aciklama' => 'Ekran görüntülü klasik görev oluştur.',
     'rozet' => '', 'renk' => '#3b82f6'],

    ['baslik' => '🚀 Görev Tasarlayıcı (v2)', 'link' => '../v2_gorev_sistemi/admin/gorev_listesi.php',
     'aciklama' => 'Tarayıcı içi Blockly görevleri — otomatik kontrol.',
     'rozet' => $toplamV2Gorev . ' görev', 'renk' => '#8b5cf6'],

    ['baslik' => '🎭 Kukla Kütüphanesi', 'link' => '../v2_gorev_sistemi/admin/kukla_kutuphanesi/index.php',
     'aciklama' => 'v2 sahnesindeki kuklaları ve kostümleri yönet.',
     'rozet' => '', 'renk' => '#8b5cf6'],

    ['baslik' => '🎯 Quiz ve Soru Havuzu', 'link' => 'super_quiz_yonetimi.php',
     'aciklama' => 'Quiz sorularını ekle, düzenle, resim yükle.',
     'rozet' => $toplamSoru . ' soru', 'renk' => '#f59e0b'],

    ['baslik' => '🛒 Gizemli Market', 'link' => 'admin_market.php',
     'aciklama' => 'Avatar eşyalarını yönet, fiyat ve seviye ayarla.',
     'rozet' => $toplamEsya . ' eşya', 'renk' => '#10b981'],

    ['baslik' => '🔧 Bakım İşlemleri', 'link' => 'super_bakim.php',
     'aciklama' => 'Eşya senkronizasyonu, güç önbelleği, kayıp dosya taraması.',
     'rozet' => '', 'renk' => '#64748b'],
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Süper Admin Paneli | mBlock Akademi</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f172a; color: #f8fafc; margin: 0; padding: 25px 20px 60px;
        }
        .sarmal { max-width: 1100px; margin: 0 auto; }

        .ust {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 15px; margin-bottom: 25px;
        }
        .ust h1 { margin: 0; font-size: 24px; }
        .ust .kim { color: #94a3b8; font-size: 13px; margin-top: 4px; }
        .ust-btnler { display: flex; gap: 10px; }
        .ust-btnler a {
            padding: 9px 16px; border-radius: 8px; text-decoration: none;
            font-size: 14px; font-weight: bold; transition: 0.2s;
        }
        .btn-ayar { background: #334155; color: #f8fafc; }
        .btn-ayar:hover { background: #475569; }
        .btn-cikis { background: #ef4444; color: #fff; }
        .btn-cikis:hover { background: #dc2626; }

        .uyari {
            background: #78350f; color: #fde68a; border: 1px solid #d97706;
            padding: 14px 18px; border-radius: 10px; margin-bottom: 22px;
            font-weight: bold; font-size: 14px;
        }
        .uyari a { color: #fde68a; }

        .istatistikler {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px; margin-bottom: 30px;
        }
        .ist {
            background: #1e293b; border: 1px solid #334155; border-radius: 10px;
            padding: 16px; text-align: center;
        }
        .ist .sayi { font-size: 26px; font-weight: bold; display: block; }
        .ist .etiket { color: #94a3b8; font-size: 12px; margin-top: 4px; display: block; }

        .kartlar {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;
        }
        .kart {
            background: #1e293b; border: 1px solid #334155; border-radius: 12px;
            padding: 20px; text-decoration: none; color: inherit;
            display: block; transition: 0.2s; position: relative;
        }
        .kart:hover { border-color: #3b82f6; transform: translateY(-2px); }
        .kart h3 { margin: 0 0 8px 0; font-size: 16px; }
        .kart p { margin: 0; color: #94a3b8; font-size: 13px; line-height: 1.5; }
        .rozet {
            position: absolute; top: 16px; right: 16px; padding: 4px 10px;
            border-radius: 20px; font-size: 11px; font-weight: bold; color: #fff;
        }
        .canavar-kutu {
            background: linear-gradient(135deg, #450a0a, #1e293b);
            border: 1px solid #7f1d1d; border-radius: 12px;
            padding: 20px; margin-bottom: 30px;
        }
        .can-bar-dis { background: #0f172a; border-radius: 20px; height: 22px; overflow: hidden; margin-top: 10px; }
        .can-bar-ic { background: linear-gradient(90deg, #ef4444, #f59e0b); height: 100%; transition: 0.4s; }
    </style>
</head>
<body>

<div class="sarmal">

    <div class="ust">
        <div>
            <h1>👑 Süper Admin Paneli</h1>
            <div class="kim">
                Hoş geldin, <b><?= temizle($_SESSION['super_admin_ad'] ?? 'Süper Admin') ?></b>
                (<?= temizle($_SESSION['super_kullanici'] ?? '') ?>)
            </div>
        </div>
        <div class="ust-btnler">
            <a href="super_sifre_degistir.php" class="btn-ayar">⚙️ Hesap Ayarları</a>
            <a href="super_cikis.php" class="btn-cikis">🚪 Çıkış</a>
        </div>
    </div>

    <?php if ($varsayilanSifreUyarisi): ?>
        <div class="uyari">
            ⚠️ Varsayılan şifre (<b>123</b>) hâlâ aktif.
            <a href="super_sifre_degistir.php">Hemen değiştir →</a>
        </div>
    <?php endif; ?>

    <div class="istatistikler">
        <div class="ist"><span class="sayi" style="color:#ef4444"><?= $bekleyenOgretmen ?></span><span class="etiket">Onay Bekleyen Öğretmen</span></div>
        <div class="ist"><span class="sayi" style="color:#10b981"><?= $aktifOgretmen ?></span><span class="etiket">Aktif Öğretmen</span></div>
        <div class="ist"><span class="sayi" style="color:#3b82f6"><?= $toplamOgrenci ?></span><span class="etiket">Toplam Öğrenci</span></div>
        <div class="ist"><span class="sayi" style="color:#f59e0b"><?= $bekleyenOgrenci ?></span><span class="etiket">Onay Bekleyen Öğrenci</span></div>
        <div class="ist"><span class="sayi" style="color:#8b5cf6"><?= $toplamBolum ?></span><span class="etiket">Bölüm</span></div>
        <div class="ist"><span class="sayi" style="color:#facc15"><?= $toplamGorev + $toplamV2Gorev ?></span><span class="etiket">Toplam Görev (v1+v2)</span></div>
    </div>

    <?php if ($canavar):
        $maxCan  = max(1, (int) $canavar['max_can']);
        $yuzde   = max(0, min(100, ((int) $canavar['mevcut_can'] / $maxCan) * 100));
    ?>
    <div class="canavar-kutu">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
            <div>
                <strong style="font-size:17px">
                    <?= $canavar['tur'] === 'dev' ? '🐉' : '🥚' ?>
                    Ayın Canavarı<?= $canavar['adi'] ? ' — ' . temizle($canavar['adi']) : '' ?>
                </strong>
                <div style="color:#fca5a5;font-size:13px;margin-top:4px">
                    Durum: <?= $canavar['durum'] === 'olu' ? 'Öldürüldü ☠️' : 'Yaşıyor' ?>
                    &nbsp;·&nbsp; Tür: <?= $canavar['tur'] === 'dev' ? 'Dev (büyümüş)' : 'Yeni doğan' ?>
                </div>
            </div>
            <div style="text-align:right;font-size:14px">
                <b><?= number_format((int) $canavar['mevcut_can'], 0, ',', '.') ?></b>
                / <?= number_format($maxCan, 0, ',', '.') ?> HP
                <div style="color:#94a3b8;font-size:12px"><?= round($yuzde) ?>% kaldı</div>
            </div>
        </div>
        <div class="can-bar-dis"><div class="can-bar-ic" style="width:<?= $yuzde ?>%"></div></div>
    </div>
    <?php endif; ?>

    <div class="kartlar">
        <?php foreach ($kartlar as $k): ?>
            <a href="<?= temizle($k['link']) ?>" class="kart">
                <?php if ($k['rozet'] !== ''): ?>
                    <span class="rozet" style="background:<?= $k['renk'] ?>"><?= temizle($k['rozet']) ?></span>
                <?php endif; ?>
                <h3><?= $k['baslik'] ?></h3>
                <p><?= temizle($k['aciklama']) ?></p>
            </a>
        <?php endforeach; ?>
    </div>

</div>

</body>
</html>
