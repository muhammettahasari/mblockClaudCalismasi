<?php
/* ====================================================================
 * MODÜL: Hazine ve Avatar Odası (Öğrenci Profili)
 * DOSYA: profil.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğrencinin satın aldığı eşyaları karakterine giydirdiği, statlarını
 * (güç, çeviklik, karizma...) gördüğü oda.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Stat matrisi bilesenler/rpg_matris.php'ye taşındı (#12).
 *  - [2026-08] Merkezi güvenli oturum kapıcısına geçildi.
 * ==================================================================== */

require_once __DIR__ . '/bilesenler/guvenlik.php';
require_once __DIR__ . '/bilesenler/rpg_matris.php';
ogrenciZorunlu();

$ogrenci_id = (int) $_SESSION['ogrenci_id'];
$ogrenci_ad = $_SESSION['ogrenci_ad'] ?? 'Öğrenci';

// 1. Öğrencinin Avatar Durumunu Çek
$avatar_sorgu = $pdo->prepare("SELECT * FROM ogrenci_avatar_durum WHERE ogrenci_id = ?");
$avatar_sorgu->execute([$ogrenci_id]);
$avatar = $avatar_sorgu->fetch(PDO::FETCH_ASSOC);

if (!$avatar) {
    $pdo->prepare("INSERT INTO ogrenci_avatar_durum (ogrenci_id) VALUES (?)")->execute([$ogrenci_id]);
    $avatar = ['cinsiyet' => 'erkek', 'karakter_id' => null, 'sag_el_id' => null, 'sol_el_id' => null, 'kafa_id' => null, 'binek_id' => null, 'evcil_hayvan_id' => null, 'sac_id' => null, 'ayakkabi_id' => '', 'zirh_id' => '', 'yuz_id' => ''];
}
// YENİ: Öğrencinin hiç bedeni (karakteri) yoksa, marketteki en ucuz bedeni otomatik hediye et ve giydir
$beden_kontrol = $pdo->prepare("SELECT COUNT(*) FROM ogrenci_envanter oe JOIN esya_kutuphanesi e ON oe.esya_id = e.id WHERE oe.ogrenci_id = ? AND e.kategori = 'karakter'");
$beden_kontrol->execute([$ogrenci_id]);
if ($beden_kontrol->fetchColumn() == 0) {
    $base_karakter = $pdo->query("SELECT id FROM esya_kutuphanesi WHERE kategori = 'karakter' ORDER BY fiyat ASC LIMIT 1")->fetch();
    if ($base_karakter) {
        $pdo->prepare("INSERT INTO ogrenci_envanter (ogrenci_id, esya_id) VALUES (?, ?)")->execute([$ogrenci_id, $base_karakter['id']]);
        $pdo->prepare("UPDATE ogrenci_avatar_durum SET karakter_id = ? WHERE ogrenci_id = ?")->execute([$base_karakter['id'], $ogrenci_id]);
        $avatar['karakter_id'] = $base_karakter['id'];
    }
}
// 2. Öğrencinin Envanterini Çek
$envanter_sorgu = $pdo->prepare("
    SELECT e.* FROM ogrenci_envanter oe 
    JOIN esya_kutuphanesi e ON oe.esya_id = e.id 
    WHERE oe.ogrenci_id = ? AND e.kategori != 'saldiri_troll'
    ORDER BY e.fiyat ASC, e.esya_adi ASC
");
$envanter_sorgu->execute([$ogrenci_id]);
$sahip_olunan_esyalar = $envanter_sorgu->fetchAll(PDO::FETCH_ASSOC);

// Giyili eşyaların resim yollarını bul
$giyili_resimler = [
    'karakter' => '',
    'kafa' => '',
    'sag_el' => '',
    'sol_el' => '',
    'binek' => '',
    'evcil_hayvan' => '',
    'sac' => '',
    'ayakkabi' => '',
    'zirh' => '',
    'yuz' => ''
];

foreach ($sahip_olunan_esyalar as $esya) {
    $kat = $esya['kategori'];
    if (isset($avatar[$kat . '_id']) && $avatar[$kat . '_id'] == $esya['id']) {
        $giyili_resimler[$kat] = $esya['resim_yolu'];
    }
}
$ogrSorgu = $pdo->prepare("SELECT can, enerji FROM ogrenciler WHERE id = ?");
$ogrSorgu->execute([$ogrenci_id]);
$ogrVeri = $ogrSorgu->fetch();
$can = $ogrVeri['can'] ?? 100;
$enerji = $ogrVeri['enerji'] ?? 100;

/* ====================================================================
 * RPG STAT HESABI
 * [2026-08] Buradaki 50 satırlık kopya matris kaldırıldı (Bilinen Sorunlar #12).
 * Stat tablosu artık TEK yerde: bilesenler/rpg_matris.php
 * (Diğer iki kopya api/arena_savas_motoru.php ve sinif_arenasi.php'de idi.)
 * ==================================================================== */
$giyili_idler = array_filter([
    $avatar['sag_el_id']       ?? null,
    $avatar['sol_el_id']       ?? null,
    $avatar['kafa_id']         ?? null,
    $avatar['binek_id']        ?? null,
    $avatar['evcil_hayvan_id'] ?? null,
    $avatar['ayakkabi_id']     ?? null,
    $avatar['zirh_id']         ?? null,
    $avatar['yuz_id']          ?? null,
]);

$giyili_esyalar = [];
if (count($giyili_idler) > 0) {
    $in = implode(',', array_fill(0, count($giyili_idler), '?'));
    $esyaSorgu = $pdo->prepare("SELECT kategori, seviye FROM esya_kutuphanesi WHERE id IN ($in)");
    $esyaSorgu->execute(array_values($giyili_idler));
    $giyili_esyalar = $esyaSorgu->fetchAll(PDO::FETCH_ASSOC);
}

$statlar = statlariHesapla($giyili_esyalar);
$genel_guc_toplami = $statlar['genel_guc'];

// Şablonun beklediği 6 stat dışındaki yardımcı anahtarları ayıkla
unset($statlar['genel_guc']);

// Ekrandaki değer ile savaş motorunun kullandığı önbellek aynı kalsın
gucuYenidenHesapla($pdo, $ogrenci_id);
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hazine ve Avatar Odası</title>
    <style>
        body {
            background: #0f172a;
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            color: white;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        .header {
            background: #1e293b;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            z-index: 100;
        }

        .btn-geri {
            background: #3b82f6;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            transition: 0.3s;
        }

        .btn-geri:hover {
            background: #2563eb;
        }

        .room-container {
            display: flex;
            flex: 1;
            padding: 20px;
            gap: 20px;
            overflow: hidden;
        }

        /* Oyun Alanı ve CSS Ayarları (Avatar ve Envanter bileşenleri bu CSS'leri kullanır) */
        .game-area {
            flex: 2;
            background: radial-gradient(circle, #334155 0%, #0f172a 80%);
            border-radius: 16px;
            border: 2px solid #475569;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .avatar-canvas {
            width: 400px;
            height: 600px;
            position: relative;
        }

        .layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            pointer-events: none;
        }

        #layer_binek {
            z-index: 10;
        }

        #layer_base {
            z-index: 20;
        }

        #layer_evcil {
            z-index: 25;
        }

        #layer_sac {
            z-index: 30;
        }

        #layer_sol_el {
            z-index: 40;
        }

        #layer_sag_el {
            z-index: 50;
        }

        #layer_kafa {
            z-index: 60;
        }

        /* YENİ SİLÜET SİSTEMİ CSS */
        /* YENİ SİLÜET VE GÖRÜNMEZ KUTU CSS'LERİ */


        /* YENİ SİLÜET VE GÖRÜNMEZ KUTU CSS'LERİ */
        .slot {
            position: absolute;
            width: 40px;
            height: 40px;
            z-index: 100;
            display: flex;
            justify-content: center;
            align-items: center;
            pointer-events: none;
        }

        .btn-cikart {
            background: #ef4444;
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            pointer-events: auto;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.8);
            transition: 0.2s;
        }

        .btn-cikart:hover {
            transform: scale(1.2);
            background: #dc2626;
        }

        /* YENİ: Boşken Çalışan Standart Silüet Animasyonu */
        .siluet-katmani {
            opacity: 0.6;
            filter: drop-shadow(0 0 15px #0ea5e9);
            animation: pulse-glow 1.5s infinite;
            pointer-events: none;
        }

        /* EFSANE YENİLİK: Gerçek Eşyayı Silüete Çeviren Dinamik CSS */
        .mevcut-parlama {
            filter: brightness(0) invert(1) drop-shadow(0 0 20px #0ea5e9) !important;
            opacity: 0.8 !important;
            animation: pulse-glow 1.5s infinite;
            transition: 0.3s;
        }

        @keyframes pulse-glow {
            0% {
                filter: drop-shadow(0 0 5px #0ea5e9);
                opacity: 0.4;
                transform: scale(1);
            }

            50% {
                filter: drop-shadow(0 0 30px #7dd3fc);
                opacity: 0.95;
                transform: scale(1.035);
            }

            100% {
                filter: drop-shadow(0 0 5px #0ea5e9);
                opacity: 0.4;
                transform: scale(1);
            }
        }

        /* YENİ: Hazine Sandığında Giyili Olan Eşyayı Belli Eden CSS */
        .item-card.aktif-giyili {
            border-color: #10b981 !important;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.6) !important;
            background: rgba(16, 185, 129, 0.15);
        }

        .inventory-area {
            flex: 1;
            background: #1e293b;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            border: 2px solid #334155;
        }

        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 15px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .item-card {
            background: #0f172a;
            border: 2px solid #475569;
            border-radius: 12px;
            padding: 10px;
            text-align: center;
            cursor: grab;
            transition: 0.2s;
            position: relative;
        }

        .item-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
        }

        .item-card:active {
            cursor: grabbing;
            transform: scale(0.95);
        }

        .item-card img {
            width: 100%;
            height: 70px;
            object-fit: contain;
            pointer-events: none;
        }

        .item-card[data-kategori="zirh"] img {
            transform: scale(3.5);
            object-position: 50% 50%;
        }

        .item-card[data-kategori="yuz"] img {
            transform: scale(10.0);
            object-position: 50% 28%;
        }

        .item-card[data-kategori="kafa"] img {
            transform: scale(4.0);
            object-position: 50% 20%;
        }

        .item-card p {
            margin: 8px 0 0 0;
            font-size: 11px;
            font-weight: bold;
            color: #cbd5e1;
            pointer-events: none;
        }

        .btn-cikart {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #ef4444;
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            font-weight: bold;
            cursor: pointer;
            display: none;
            z-index: 110;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }

        .slot:hover .btn-cikart {
            display: block;
        }

        /* YENİ EKLENEN RPG BAR CSS BAŞLANGICI */
        /* YENİ: ANLIK GÜNCELLENEN ÜST PANEL VE BAR TASARIMLARI */
        .game-area {
            flex-direction: column !important;
            justify-content: flex-start !important;
            align-items: center;
        }

        .top-stat-panel {
            width: 100%;
            background: rgba(15, 23, 42, 0.95);
            border-bottom: 2px solid #334155;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            box-sizing: border-box;
            z-index: 200;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.6);
        }

        .rpg-bars-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 40px;
            flex: 2;
            padding-right: 20px;
        }

        .rpg-bar-row {
            display: flex;
            align-items: center;
        }

        .rpg-bar-label {
            width: 85px;
            font-size: 13px;
            font-weight: bold;
            color: #cbd5e1;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.8);
        }

        .rpg-bar-bg {
            flex: 1;
            background: #1e293b;
            border-radius: 6px;
            height: 18px;
            position: relative;
            border: 1px solid #0f172a;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.6);
            overflow: hidden;
        }

        .rpg-bar-fill {
            height: 100%;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 5px;
            font-size: 11px;
            font-weight: bold;
            color: white;
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            width: 0;
        }

        .vitals-box {
            flex: 0.8;
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-end;
            border-left: 2px dashed #475569;
            padding-left: 20px;
        }

        .vital-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 160px;
            background: #1e293b;
            padding: 8px 15px;
            border-radius: 8px;
            border: 1px solid #334155;
            font-weight: bold;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.4);
        }

        .vital-can {
            color: #f87171;
            border-color: #991b1b;
        }

        .vital-enerji {
            color: #facc15;
            border-color: #a16207;
        }

        .vital-guc {
            color: #60a5fa;
            border-color: #1e3a8a;
            background: #172554;
            font-size: 16px;
            text-shadow: 0 0 5px rgba(59, 130, 246, 0.5);
        }

        .fill-savascilik {
            background: linear-gradient(90deg, #b91c1c, #f87171);
        }

        .fill-guc {
            background: linear-gradient(90deg, #b45309, #f59e0b);
        }

        .fill-beceri {
            background: linear-gradient(90deg, #4338ca, #818cf8);
        }

        .fill-liderlik {
            background: linear-gradient(90deg, #6d28d9, #a78bfa);
        }

        .fill-karizma {
            background: linear-gradient(90deg, #be185d, #f472b6);
        }

        .fill-ceviklik {
            background: linear-gradient(90deg, #047857, #34d399);
        }

        /* YENİ EKLENEN RPG BAR CSS BİTİŞİ */
    </style>
</head>

<body>

    <div class="header">
        <a href="ogrenci_paneli.php" class="btn-geri">⬅️ Maceraya Dön</a>
        <h1 style="margin: 0; color: #3b82f6;">🛡️ <?= htmlspecialchars($ogrenci_ad) ?>'in Karakter Ekranı</h1>
        <a href="market.php" style="background: #f59e0b; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold;">🛒 Markete Git</a>
    </div>

    <div class="room-container">

        <div class="top-stat-panel" id="rpg_ust_panel">
            <div class="rpg-bars-grid">
                <div class="rpg-bar-row">
                    <div class="rpg-bar-label">🗡️ Savaşçılık</div>
                    <div class="rpg-bar-bg">
                        <div class="rpg-bar-fill fill-savascilik" data-width="<?= $statlar['savascilik'] ?>%">%<?= $statlar['savascilik'] ?></div>
                    </div>
                </div>
                <div class="rpg-bar-row">
                    <div class="rpg-bar-label">👑 Liderlik</div>
                    <div class="rpg-bar-bg">
                        <div class="rpg-bar-fill fill-liderlik" data-width="<?= $statlar['liderlik'] ?>%">%<?= $statlar['liderlik'] ?></div>
                    </div>
                </div>
                <div class="rpg-bar-row">
                    <div class="rpg-bar-label">💪 Güç</div>
                    <div class="rpg-bar-bg">
                        <div class="rpg-bar-fill fill-guc" data-width="<?= $statlar['guc'] ?>%">%<?= $statlar['guc'] ?></div>
                    </div>
                </div>
                <div class="rpg-bar-row">
                    <div class="rpg-bar-label">😎 Karizma</div>
                    <div class="rpg-bar-bg">
                        <div class="rpg-bar-fill fill-karizma" data-width="<?= $statlar['karizma'] ?>%">%<?= $statlar['karizma'] ?></div>
                    </div>
                </div>
                <div class="rpg-bar-row">
                    <div class="rpg-bar-label">🎯 Beceri</div>
                    <div class="rpg-bar-bg">
                        <div class="rpg-bar-fill fill-beceri" data-width="<?= $statlar['beceri'] ?>%">%<?= $statlar['beceri'] ?></div>
                    </div>
                </div>
                <div class="rpg-bar-row">
                    <div class="rpg-bar-label">💨 Çeviklik</div>
                    <div class="rpg-bar-bg">
                        <div class="rpg-bar-fill fill-ceviklik" data-width="<?= $statlar['ceviklik'] ?>%">%<?= $statlar['ceviklik'] ?></div>
                    </div>
                </div>
            </div>
            <div class="vitals-box">
                <div class="vital-item vital-can"><span>❤️ Can</span> <span><?= $can ?> / 100</span></div>
                <div class="vital-item vital-enerji"><span>⚡ Enerji</span> <span><?= $enerji ?> / 100</span></div>
                <div class="vital-item vital-guc"><span>🌟 Genel Güç</span> <span id="genel_guc_kutusu"><?= $genel_guc_toplami ?></span></div>
            </div>
        </div>

        <script>
            // 1. Paneli otomatik olarak Oyun Alanının en üstüne yerleştir
            document.addEventListener("DOMContentLoaded", () => {
                const gameArea = document.querySelector('.game-area');
                const ustPanel = document.getElementById('rpg_ust_panel');
                if (gameArea && ustPanel) {
                    gameArea.prepend(ustPanel);
                }
            });

            // 2. Veritabanındaki Eşyaları Hatasız Hafızaya Al
            <?php
            $esya_js_dizisi = [];
            foreach ($sahip_olunan_esyalar as $e) {
                $esya_js_dizisi[$e['resim_yolu']] = [
                    'kategori' => $e['kategori'],
                    'seviye' => $e['seviye'] > 0 ? (int)$e['seviye'] : 1
                ];
            }
            ?>
            const esyaVeritabani = <?= json_encode($esya_js_dizisi) ?>;

            /* [2026-08] AVATAR EKRANI SİYAH KALMA HATASI DÜZELTİLDİ:
               Burada `json_encode($matris)` yazıyordu. Stat matrisi
               bilesenler/rpg_matris.php'ye taşınınca $matris değişkeni
               kalmadı; PHP 8 "Undefined variable" uyarısını doğrudan
               <script> bloğunun içine basıyor, JavaScript sözdizimi
               bozuluyor ve sürükle-bırak dahil TÜM script ölüyordu.
               Artık matris paylaşılan sabitten geliyor. */
            const statMatrisi = <?= json_encode(RPG_MATRIS, JSON_UNESCAPED_UNICODE) ?>;

            // YENİ: KUSURSUZ EŞLEŞTİRME İÇİN DOSYA ADI AYIKLAYICI
            function dosyaAdiBul(yol) {
                if (!yol) return '';
                return yol.split('/').pop().split('\\').pop(); // Sadece en sondaki dosya adını (isim.png) alır
            }

            // 3. ANLIK GÜÇ HESAPLAMA MOTORU
            function anlikGucHesapla() {
                let hesaplananStatlar = {
                    guc: 0,
                    savascilik: 0,
                    beceri: 0,
                    liderlik: 0,
                    karizma: 0,
                    ceviklik: 0
                };

                // Avatarın üzerindeki katmanları tara
                document.querySelectorAll('.avatar-canvas img.layer').forEach(img => {
                    let currentSrc = img.getAttribute('src') || img.src;

                    // Resim gizli değilse, boş değilse ve siluet/base değilse
                    if (img.style.display !== 'none' && currentSrc && !currentSrc.includes('siluet') && !currentSrc.includes('base_karakter')) {

                        let esya = null;
                        let currentDosyaAdi = dosyaAdiBul(currentSrc);

                        // Tarayıcının uzun URL'sini boşver, sadece dosya adlarını (ornek.png) eşleştir!
                        for (let dbYolu in esyaVeritabani) {
                            if (dosyaAdiBul(dbYolu) === currentDosyaAdi) {
                                esya = esyaVeritabani[dbYolu];
                                break;
                            }
                        }

                        if (esya && statMatrisi[esya.kategori]) {
                            let sev = esya.seviye;
                            let m = statMatrisi[esya.kategori];
                            hesaplananStatlar.guc += m.guc * sev;
                            hesaplananStatlar.savascilik += m.savascilik * sev;
                            hesaplananStatlar.beceri += m.beceri * sev;
                            hesaplananStatlar.liderlik += m.liderlik * sev;
                            hesaplananStatlar.karizma += m.karizma * sev;
                            hesaplananStatlar.ceviklik += m.ceviklik * sev;
                        }
                    }
                });

                // Sınırları (0-100) ayarla ve Barları Animasyonla Doldur
                let yeniGenelGuc = 0;
                for (let k in hesaplananStatlar) {
                    if (hesaplananStatlar[k] < 0) hesaplananStatlar[k] = 0;
                    if (hesaplananStatlar[k] > 100) hesaplananStatlar[k] = 100;
                    yeniGenelGuc += hesaplananStatlar[k];

                    let bar = document.querySelector('.fill-' + k);
                    if (bar) {
                        bar.style.width = hesaplananStatlar[k] + '%';
                        bar.innerHTML = '%' + hesaplananStatlar[k];
                    }
                }

                // Sağdaki Sarı Kutudaki "Genel Güç" sayısını güncelle
                let gucKutusu = document.getElementById('genel_guc_kutusu');
                if (gucKutusu) gucKutusu.innerHTML = yeniGenelGuc;
            }

            // 4. CANLI GÖZLEMCİ (MUTATION OBSERVER) 
            const avatarGozlemci = new MutationObserver(() => {
                anlikGucHesapla();
            });

            // Artık tüm Canvas izleniyor. Sürükle-bırak yapıldığı ilk salise bar dolar!
            const canvas = document.querySelector('.avatar-canvas');
            if (canvas) {
                avatarGozlemci.observe(canvas, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['src', 'style']
                });
            }

            // Çıkart (X) butonlarına tıklandığında veya sürükleme bitince garanti olsun diye gecikmeli tetik
            document.addEventListener('mouseup', () => setTimeout(anlikGucHesapla, 100));
            document.addEventListener('click', () => setTimeout(anlikGucHesapla, 100));

            // YENİ: Sürükle-Bırak (Drag & Drop) işlemi bittiği AN çalışması için eklendi!
            document.addEventListener('drop', () => setTimeout(anlikGucHesapla, 150));
            document.addEventListener('dragend', () => setTimeout(anlikGucHesapla, 150));

            // Sayfa ilk yüklendiğinde barları doldurmak için tetikle
            setTimeout(anlikGucHesapla, 200);
        </script>
        <?php require_once 'bilesenler/profil_avatar.php'; ?>

        <?php require_once 'bilesenler/profil_envanter.php'; ?>

    </div>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const giyiliIdler = [
                <?= $avatar['karakter_id'] ?? 0 ?>, <?= $avatar['kafa_id'] ?? 0 ?>,
                <?= $avatar['sag_el_id'] ?? 0 ?>, <?= $avatar['sol_el_id'] ?? 0 ?>,
                <?= $avatar['ayakkabi_id'] ?? 0 ?>, <?= $avatar['binek_id'] ?? 0 ?>,
                <?= $avatar['evcil_hayvan_id'] ?? 0 ?>, <?= $avatar['sac_id'] ?? 0 ?>, <?= $avatar['zirh_id'] ?? 0 ?>, <?= $avatar['yuz_id'] ?? 0 ?>
            ];
            giyiliIdler.forEach(id => {
                if (id !== 0) {
                    let kart = document.querySelector(`.item-card[data-id="${id}"]`);
                    if (kart) kart.classList.add('aktif-giyili');
                }
            });

            // YENİ: Hazine sandığındaki giyili (yeşil) eşyaya tıklayınca çıkartma özelliği
            document.querySelectorAll('.item-card').forEach(kart => {
                kart.addEventListener('click', function() {
                    // Tıklanan eşya şu an karakterin üzerindeyse (aktif-giyili sınıfı varsa)
                    if (this.classList.contains('aktif-giyili')) {
                        let kategori = this.getAttribute('data-kategori');

                        // avatar_manager.js içindeki çıkartma motorunu tetikle
                        if (typeof esyaCikart === 'function') {
                            esyaCikart(kategori);
                        }
                    }
                });
            });
        });
    </script>

    <script src="<?= varlik('js/ogrenci_radar.js', '') ?>"></script>
    <script src="<?= varlik('js/avatar_manager.js', '') ?>"></script>
    <script>
        const profilMuzigi = new Audio('assets/sounds/profil_karakter_sound.mp3');
        profilMuzigi.loop = true;  // Müzik bitince başa sarar
        profilMuzigi.volume = 0.1; // Sakince, arkaplanda yormadan çalması için ses seviyesi (%30)

        // Sayfa açılır açılmaz çalmayı dene
        profilMuzigi.play().catch(() => {
            // Tarayıcının güvenlik duvarı otomatik sesi engellerse, öğrenci ekrana (herhangi bir yere) tıkladığı an müziği başlat
            document.body.addEventListener('click', () => {
                if (profilMuzigi.paused) profilMuzigi.play();
            }, { once: true });
        });
    </script>
<?php include 'api/quiz_baskin.php'; ?>
<?php include 'bilesenler/duello_radari.php'; ?>
</body>

</html>