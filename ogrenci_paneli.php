<?php
session_start();
if (!isset($_SESSION['ogrenci_id'])) {
    header("Location: index.php");
    exit;
}
require_once 'db.php';

$aktif_ogrenci_id = $_SESSION['ogrenci_id'];
$aktif_ogrenci_ad = $_SESSION['ogrenci_ad'] ?? 'Öğrenci';

// Okul, Sınıf ve Puan bilgileri
$ogrenci_sorgu = $pdo->prepare("
    SELECT o.kalici_puan, o.anlik_puan, o.ad_soyad, o.durum, o.sinif_id, o.ogretmen_id, o.mevcut_bolum,
           ogr.okul_adi, s.sinif_adi 
    FROM ogrenciler o
    LEFT JOIN ogretmenler ogr ON o.ogretmen_id = ogr.id
    LEFT JOIN siniflar s ON o.sinif_id = s.id
    WHERE o.id = ?
");
$ogrenci_sorgu->execute([$aktif_ogrenci_id]);
$ogrenci_bilgi = $ogrenci_sorgu->fetch();

$okul_adi = $ogrenci_bilgi['okul_adi'] ?? 'Okul Bilinmiyor';
$sinif_adi = $ogrenci_bilgi['sinif_adi'] ?? 'Sınıf Bilinmiyor';
$gercek_kalici_puan = $ogrenci_bilgi ? $ogrenci_bilgi['kalici_puan'] : 0;
$bekleyen_puan = $ogrenci_bilgi ? $ogrenci_bilgi['anlik_puan'] : 0;
$ogrenci_durum = $ogrenci_bilgi ? $ogrenci_bilgi['durum'] : 'aktif';
$kilitli_mi = ($ogrenci_durum === 'kilitli');

// --- ŞAMPİYONA SORGULARI ---
$sinif_id_hedef = $ogrenci_bilgi['sinif_id'] ?? 0;
$ogretmen_id_hedef = $ogrenci_bilgi['ogretmen_id'] ?? 0;

$sinif_liderlik = $pdo->prepare("SELECT id, ad_soyad, kalici_puan FROM ogrenciler WHERE sinif_id = ? AND (durum = 'aktif' OR durum IS NULL OR durum = '') ORDER BY kalici_puan DESC, id ASC");
$sinif_liderlik->execute([$sinif_id_hedef]);
$liste_sinif = $sinif_liderlik->fetchAll();

$okul_liderlik = $pdo->prepare("SELECT o.id, o.ad_soyad, o.kalici_puan, s.sinif_adi FROM ogrenciler o LEFT JOIN siniflar s ON o.sinif_id = s.id WHERE o.ogretmen_id = ? AND (o.durum = 'aktif' OR o.durum IS NULL OR o.durum = '') ORDER BY o.kalici_puan DESC, o.id ASC");
$okul_liderlik->execute([$ogretmen_id_hedef]);
$liste_okul = $okul_liderlik->fetchAll();

$tr_liderlik = $pdo->query("SELECT o.id, o.ad_soyad, o.kalici_puan, ogr.okul_adi FROM ogrenciler o LEFT JOIN ogretmenler ogr ON o.ogretmen_id = ogr.id WHERE (o.durum = 'aktif' OR o.durum IS NULL OR o.durum = '') ORDER BY o.kalici_puan DESC, o.id ASC");
$liste_tr = $tr_liderlik->fetchAll();

// --- BÖLÜM VE GÖREV ÇEKME ---
// --- BÖLÜM VE GÖREV ÇEKME (KALICI SİSTEM) ---
$secilen_gorev_id = isset($_GET['gorev']) ? (int)$_GET['gorev'] : 0;

// Öğrencinin veritabanındaki gerçek seviyesi
$ogrencinin_seviyesi = $ogrenci_bilgi['mevcut_bolum'] ?? 1;

// Öğrenci URL'den belli bir bölüm görmek istiyor mu?
$istenen_bolum = isset($_GET['bolum']) ? (int)$_GET['bolum'] : $ogrencinin_seviyesi;

// ANTİ-HİLE: Öğrenci henüz ulaşmadığı bir bölüme girmeye çalışırsa, onu kendi seviyesine geri postalıyoruz.
// (Ama geçmiş bölümlere dönüp konuları tekrar okumasına izin veriyoruz)
if ($istenen_bolum > $ogrencinin_seviyesi) {
    $aktif_bolum_id = $ogrencinin_seviyesi;
} else {
    $aktif_bolum_id = $istenen_bolum;
}

$gorev_sorgu = $pdo->prepare("SELECT * FROM gorevler WHERE bolum_id = ? ORDER BY sira_no ASC, id ASC");
$gorev_sorgu->execute([$aktif_bolum_id]);
$gorevler = $gorev_sorgu->fetchAll(PDO::FETCH_ASSOC);

$toplam_gorev = count($gorevler);
$aktif_gorev = null;
$aktif_index = 0;
$ilk_gorev_id = 0;

if ($toplam_gorev > 0) {
    $ilk_gorev_id = $gorevler[0]['id'];
    if ($secilen_gorev_id > 0) {
        foreach ($gorevler as $index => $g) {
            if ($g['id'] == $secilen_gorev_id) {
                $aktif_gorev = $g;
                $aktif_index = $index;
                break;
            }
        }
    }
    if (!$aktif_gorev) {
        $aktif_gorev = $gorevler[0];
        $aktif_index = 0;
    }

    $g_adi = $aktif_gorev['baslik'] ?? 'İsimsiz Görev';
    $g_nasil = $aktif_gorev['nasil_yaparim'] ?? 'Açıklama yok.';
    $g_metin = $aktif_gorev['gorev_metni'] ?? 'Görev metni bulunamadı.';
    $g_ekran = $aktif_gorev['istenen_ekran'] ?? '';
    $g_ipucu = $aktif_gorev['ipucu'] ?? 'İpucu bulunmuyor.';
    $g_puan = $aktif_gorev['puan'] ?? 0;
    $g_id = $aktif_gorev['id'];

    $son_gorev_mi = ($aktif_index == $toplam_gorev - 1);
    $sonraki_gorev_id = !$son_gorev_mi ? $gorevler[$aktif_index + 1]['id'] : null;
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>mBlock Kodlama Macerası</title>
    <link rel="stylesheet" href="css/student_style.css">
    <style>
        body {
            background: #f8fafc;
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            overflow-y: auto !important;
        }

       
        .sidebar {
            order: 2;
            flex: 0 0 430px;
            background: #1e293b !important;
            padding: 30px;
            border-radius: 16px;
            color: white !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            position: sticky;
            top: 20px;
            z-index: 10;
        }

        .sidebar h2,
        .sidebar h3,
        .sidebar span,
        .sidebar li {
            color: white !important;
        }

        .main-content {
            order: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .task-description img,
        #alanNasil img,
        #alanGorev img,
        #alanEkran img,
        #ipucuAcikArayuz img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: 0.3s;
            backdrop-filter: blur(4px);
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-box {
            background: white;
            padding: 40px;
            border-radius: 16px;
            width: 400px;
            text-align: center;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            transform: translateY(-20px);
            transition: 0.3s;
        }

        .modal-overlay.active .modal-box {
            transform: translateY(0);
        }

        .btn-close {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #ef4444;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-weight: bold;
        }

        .lock-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #ef4444;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .lock-screen h1 {
            font-size: 5rem;
            margin: 0 0 20px 0;
        }

        .lock-screen p {
            font-size: 1.5rem;
            font-weight: bold;
            max-width: 600px;
        }

        /* YENİ EKLENEN ANA EKRAN AVATAR DÜZELTMESİ BAŞLANGICI */
        .layer {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            object-fit: contain !important;
            pointer-events: none !important;
        }

        /* YENİ EKLENEN ANA EKRAN AVATAR DÜZELTMESİ BİTİŞİ */
    </style>
</head>

<body>

    <?php require_once 'bilesenler/ogrenci_modal.php'; ?>


    <div class="app-container" style="display: flex; flex-direction: column; width: 100%; height: 100vh; margin: 0; padding: 0; overflow: hidden; background: #0f172a;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; background: #1e293b; padding: 10px 25px; border-bottom: 2px solid #334155; height: 55px; box-sizing: border-box;">
            <div style="display: flex; gap: 15px; align-items: center;">
                <span style="color: white; font-weight: 800; font-size: 16px;">🏫 <?= htmlspecialchars($okul_adi) ?></span>
                <span style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.4); padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 700;"><?= htmlspecialchars($sinif_adi) ?></span>
            </div>
            <div>
                <a href="cikis.php" style="color: #ef4444; text-decoration: none; font-weight: 800; font-size: 13px; background: rgba(239, 68, 68, 0.1); padding: 5px 12px; border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.3);">🚪 Çıkış Yap</a>
            </div>
        </div>

        <div style="flex: 1; width: 100%; height: calc(100vh - 55px); overflow: hidden;">
            <iframe id="v2OynaticiIframe" src="v2_gorev_sistemi/ogrenci/oynatici.php" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>

    </div>

    <div id="gizli-skorboard-deposu" style="display: none;">
        <?php require_once 'bilesenler/ogrenci_skorboard.php'; ?>
    </div>
 
 

    <script>
        window.addEventListener('message', function(event) {
            if (event.data === 'showScoreboard') {
                document.getElementById('coder-view-container').style.display = 'none';
                document.getElementById('scoreboard-view-container').style.display = 'block';
            } else if (event.data === 'showCoder') {
                document.getElementById('scoreboard-view-container').style.display = 'none';
                document.getElementById('coder-view-container').style.display = 'block';
            }
        });
    </script>

    <?php if ($toplam_gorev > 0): ?>
        <script>
            let currentPendingScore = <?= $bekleyen_puan ?>;

            const currentBolumId = <?= $aktif_bolum_id ?>;
            const ilkGorevId = <?= $ilk_gorev_id ?>;
        </script>
        <!-- [2026-08] Birleşik radar: dört ayrı polling yerine tek kalp atışı (#11) -->
        <script src="js/ogrenci_radar.js"></script>
        <script src="js/progress_manager.js"></script>
    <?php endif; ?>
    <?php include 'api/quiz_baskin.php'; ?>
    <?php include 'bilesenler/duello_radari.php'; ?>
 
        
</body>

</html>