<?php
/* ====================================================================
 * MODÜL: Ayın Canavarı ve Ordu Gücü
 * DOSYA YOLU: bilesenler/skorboard_modulleri/ayin_canavari.php
 * BAĞLI OLDUĞU ANA DOSYA: bilesenler/ogrenci_skorboard.php
 * AÇIKLAMA: Canavarın büyüme evreleri, Ordu Gücü modalı, Savaş Raporu
 * ve Video İntro ekranlarını barındırır.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] SAVAŞ GÜNÜ TUTARSIZLIĞI GİDERİLDİ (Bilinen Sorunlar #18):
 *      Motor ayın 18'inde çalışıyordu ama ekranda "Ayın 1'inde saldırı"
 *      yazıyor, geri sayım ise ay sonuna göre hesaplanıyordu. Artık her yer
 *      bilesenler/canavar_ayar.php içindeki CANAVAR_SAVAS_GUNU'nü kullanıyor.
 *  - [2026-08] Canavar ölmediği sürece yumurtaya dönmüyor; "dev" evresinde
 *      gerçek canı gösteriliyor (metin.txt isteği).
 * ==================================================================== */

require_once __DIR__ . '/../canavar_ayar.php';

// Savaş takvimi — tek kaynaktan (canavar_ayar.php)
$gecen_gun      = (int) date('j');
$kalan_gun      = canavaraKalanGun();
$buyume_yuzdesi = canavarBuyumeYuzdesi();

// Canavar bilgisini çek
$c_bilgi = $pdo->query(
    "SELECT adi, mevcut_can, max_can, tur, durum FROM dunya_canavari ORDER BY id DESC LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$canavar_turu = $c_bilgi['tur'] ?? 'yeni';
$mevcut_hp    = (int) ($c_bilgi['mevcut_can'] ?? 0);
$max_hp       = max(1, (int) ($c_bilgi['max_can'] ?? CANAVAR_TABAN_CAN));

/* Yumurta evresinde canavar gözle görülür şekilde "büyür": can çubuğu savaş
   gününe kadar dolar. Dev evresinde ise gerçek canı gösterilir — çünkü dev
   canavar ölmediği sürece yumurtaya dönmez, yaralarını sarıp büyür. */
if ($canavar_turu === 'yeni') {
    $gorunen_hp = min($max_hp, (int) round(($max_hp / max(1, CANAVAR_BUYUME_SURESI)) * $gecen_gun));
} else {
    $gorunen_hp = $mevcut_hp;
}

// Can yüzdesi — zafer eşiğini ekranda göstermek için
$can_yuzdesi = (int) round(($gorunen_hp / $max_hp) * 100);

/* Görsel ve unvan
   [2026-08] DEV canavar ölene kadar yumurtaya DÖNMEZ (metin.txt isteği):
   'dev' türündeyse gün kaç olursa olsun dev ejderha görseli kalır.
   Yumurta evresinde ise savaş gününe kadar 4 aşamada büyür. */
if ($canavar_turu === 'dev') {
    $canavar_resim = "img/canavarlar/dev_ejderha.png";
    $canavar_isim  = $c_bilgi['adi'] ?: "Kadim Dev Ejderha";
    $asama_metni   = "Tehdit Seviyesi: KRİTİK 🔥 (Ölmedi, güçlenerek geri döndü)";
} else {
    // Yumurta evresi: 4 aşama, savaş gününe orantılı
    $evre_dilimi = max(1, (int) ceil(CANAVAR_BUYUME_SURESI / 4));

    if ($gecen_gun <= $evre_dilimi) {
        $canavar_resim = "img/canavarlar/yumurta.png";
        $canavar_isim  = "Gizemli Ejderha Yumurtası";
        $asama_metni   = "1. Aşama: Kuluçka Dönemi";
    } elseif ($gecen_gun <= $evre_dilimi * 2) {
        $canavar_resim = "img/canavarlar/bebek_ejderha.png";
        $canavar_isim  = "Yavru Bug Ejderhası";
        $asama_metni   = "2. Aşama: Uyanış";
    } elseif ($gecen_gun <= $evre_dilimi * 3) {
        $canavar_resim = "img/canavarlar/cocuk_ejderha.png";
        $canavar_isim  = "Genç Bug Ejderhası";
        $asama_metni   = "3. Aşama: Büyüme";
    } else {
        $canavar_resim = "img/canavarlar/yetiskin_ejderha.png";
        $canavar_isim  = "Alev Püskürten Bug Ejderhası";
        $asama_metni   = "Son Aşama: Dehşet Saçan Patron!";
    }
}

// --- ORDU GÜCÜ HESAPLAMALARI ---
$ogr_id_sorgu = $pdo->prepare("SELECT o.ogretmen_id, s.sinif_adi FROM ogrenciler o LEFT JOIN siniflar s ON o.sinif_id = s.id WHERE o.id = ?");
$ogr_id_sorgu->execute([$aktif_ogrenci_id]);
$ogr_bilgi = $ogr_id_sorgu->fetch(PDO::FETCH_ASSOC);
$bulunan_ogretmen_id = $ogr_bilgi['ogretmen_id'] ?? null;
$aktif_sinif = trim($ogr_bilgi['sinif_adi'] ?? '');

$aktif_okul = '';
$aktif_il = '';
if ($bulunan_ogretmen_id) {
    $il_sorgu = $pdo->prepare("SELECT okul_adi, il FROM ogretmenler WHERE id = ?");
    $il_sorgu->execute([$bulunan_ogretmen_id]);
    $ogretmen_bilgi = $il_sorgu->fetch(PDO::FETCH_ASSOC);
    $aktif_okul = trim($ogretmen_bilgi['okul_adi'] ?? '');
    $aktif_il = trim($ogretmen_bilgi['il'] ?? '');
}
if (empty($aktif_il)) $aktif_il = 'Aksaray';

// Bölük, Birlik ve Ordu Sorguları
$sinif_gucu_sorgu = $pdo->prepare("SELECT s.sinif_adi, ogr.okul_adi, AVG(o.kalici_puan) as ortalama_hasar FROM ogrenciler o JOIN siniflar s ON o.sinif_id = s.id JOIN ogretmenler ogr ON o.ogretmen_id = ogr.id WHERE ogr.okul_adi LIKE ? GROUP BY o.sinif_id ORDER BY ortalama_hasar DESC LIMIT 15");
$sinif_gucu_sorgu->execute(['%' . $aktif_okul . '%']);
$liste_sinif_boluk = $sinif_gucu_sorgu->fetchAll();

$okul_gucu_sorgu = $pdo->prepare("SELECT ogr.okul_adi, AVG(o.kalici_puan) as ortalama_hasar FROM ogrenciler o JOIN ogretmenler ogr ON o.ogretmen_id = ogr.id WHERE ogr.il LIKE ? GROUP BY ogr.okul_adi ORDER BY ortalama_hasar DESC LIMIT 20");
$okul_gucu_sorgu->execute(['%' . $aktif_il . '%']);
$liste_okul_birlik = $okul_gucu_sorgu->fetchAll();

$sehir_gucu_sorgu = $pdo->query("SELECT ogr.il as sehir_adi, AVG(o.kalici_puan) as ortalama_hasar FROM ogrenciler o JOIN ogretmenler ogr ON o.ogretmen_id = ogr.id WHERE ogr.il IS NOT NULL AND TRIM(ogr.il) != '' GROUP BY TRIM(ogr.il) ORDER BY ortalama_hasar DESC LIMIT 15")->fetchAll();

$toplam_sinif_gucu = array_sum(array_column($liste_sinif_boluk, 'ortalama_hasar'));
$toplam_okul_gucu = array_sum(array_column($liste_okul_birlik, 'ortalama_hasar'));
$toplam_ordu_gucu = array_sum(array_column($sehir_gucu_sorgu, 'ortalama_hasar'));
?>

<div id="menuCanavar" style="display: none;">
    <div style="padding: 15px 15px 0 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px;">
        <h3 style="margin: 0 0 5px 0; color: #f43f5e;"><?= $canavar_isim ?></h3>
        <p style="font-size: 11px; color: #94a3b8; margin: 0 0 5px 0;">
            Her ayın <b><?= CANAVAR_SAVAS_GUNU ?>.</b> günü tüm okullar otomatik olarak saldırıya geçer!
        </p>
        <div style="display: inline-block; background: rgba(244, 63, 94, 0.2); border: 1px solid #f43f5e; color: #fda4af; font-size: 10px; font-weight: bold; padding: 2px 10px; border-radius: 10px; margin-bottom: 10px;"><?= $asama_metni ?></div>

        <style>
            @keyframes canavarNefes {
                0% { transform: scale(1) translateY(0); filter: drop-shadow(0 0 10px rgba(225, 29, 72, 0.4)); }
                50% { transform: scale(1.05) translateY(-5px); filter: drop-shadow(0 0 25px rgba(225, 29, 72, 0.8)); }
                100% { transform: scale(1) translateY(0); filter: drop-shadow(0 0 10px rgba(225, 29, 72, 0.4)); }
            }
        </style>
        <div style="margin: 5px auto 15px auto; width: 200px; height: 200px; background: radial-gradient(circle, rgba(0,0,0,0.4) 0%, transparent 70%); border-radius: 50%; display: flex; justify-content: center; align-items: center;">
          <img src="<?= $canavar_resim ?>" alt="Dünya Canavarı" onclick="showDragonStory()" style="width: 100%; height: 100%; object-fit: contain; animation: canavarNefes 3s ease-in-out infinite; cursor: pointer;" title="Hikayemi Oku 📜">
        </div>

        <div style="background: #1e293b; border-radius: 8px; height: 16px; border: 2px solid #0f172a; position: relative; overflow: hidden;">
            <div style="height: 100%; background: linear-gradient(90deg, #6d28d9, #9333ea); width: <?= $buyume_yuzdesi ?>%; display: flex; align-items: center; justify-content: flex-end; padding-right: 5px; font-size: 10px; font-weight: bold; color: white;">Büyüme: %<?= $buyume_yuzdesi ?></div>
        </div>

        <!-- [2026-08] Zafer eşiği göstergesi: öğrenciler ne kadar indirmeleri
             gerektiğini görsün (metin.txt isteği) -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; font-size:11px;">
            <span style="color:#94a3b8;">
                Can: <b style="color:#f43f5e;"><?= number_format($gorunen_hp, 0, ',', '.') ?></b>
                / <?= number_format($max_hp, 0, ',', '.') ?>
            </span>
            <span style="color: <?= $can_yuzdesi < CANAVAR_ZAFER_ESIGI ? '#10b981' : '#facc15' ?>; font-weight:bold;">
                %<?= $can_yuzdesi ?>
                <?= $can_yuzdesi < CANAVAR_ZAFER_ESIGI ? '🏆 Zafer bölgesi!' : '(Zafer için %' . CANAVAR_ZAFER_ESIGI . ' altı)' ?>
            </span>
        </div>

        <?php if ($kalan_gun > 0): ?>
            <div style="font-size: 11px; color: #facc15; margin-top: 8px; font-weight: bold; animation: pulse 2s infinite;">
                ⚠️ Canavar Büyüyor... (Saldırıya <?= (int) $kalan_gun ?> gün kaldı)
            </div>
            <div style="margin-top: 15px;">
                <button onclick="document.getElementById('orduGucuModal').style.display='flex'" style="width: 100%; padding: 10px; background: linear-gradient(135deg, #4338ca, #6d28d9); color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); transition: 0.3s;">
                    ⚔️ Ordu Gücünü Gör
                </button>
            </div>
        <?php else: ?>
            <div style="font-size: 11px; color: #ef4444; margin-top: 8px; font-weight: bold; animation: pulse 1s infinite;">🔥 CANAVAR TAM GÜCÜNDE! SALDIRI BAŞLIYOR! 🔥</div>
        <?php endif; ?>
    </div>
</div>

<div id="orduGucuModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 10000; justify-content: center; align-items: center; backdrop-filter: blur(10px);">
    <div style="width: 90%; max-width: 600px; background: #0f172a; border: 2px solid #334155; border-radius: 20px; max-height: 85vh; display: flex; flex-direction: column; position: relative; overflow: hidden; box-shadow: 0 0 50px rgba(79, 70, 229, 0.3);">
        <button onclick="document.getElementById('orduGucuModal').style.display='none'" style="position: absolute; top: 15px; right: 15px; background: #ef4444; border: none; color: white; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-weight: bold; z-index: 11;">X</button>

        <div style="padding: 20px; text-align: center; background: linear-gradient(to bottom, #1e1b4b, #0f172a); border-bottom: 1px solid #334155;">
            <h2 style="color: #facc15; margin: 0 0 15px 0; letter-spacing: 2px;">🛡️ KRALLIK SAVUNMA MERKEZİ</h2>
            <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.5); padding: 10px 15px; border-radius: 12px; border: 1px solid #f43f5e; box-shadow: 0 0 15px rgba(244, 63, 94, 0.2);">
                <img src="<?= $canavar_resim ?>" style="height: 50px; width: 50px; object-fit: contain; filter: drop-shadow(0 0 5px #f43f5e);">
                <div style="flex: 1; margin-left: 15px; text-align: left;">
                    <div style="color: white; font-weight: bold; font-size: 14px;"><?= $canavar_isim ?></div>
                    <div style="color: #facc15; font-size: 12px; font-weight: bold;">Mevcut Hedef HP: <span style="font-size: 16px; color: #f43f5e;"><?= number_format($gorunen_hp, 0, ',', '.') ?></span></div>
                </div>
            </div>
        </div>

        <div style="display: flex; background: #1e293b;">
            <button onclick="orduSekmeDegistir('tabBölük', event)" class="ordu-tab-btn ordu-aktif" style="flex:1; padding: 15px; background: none; border:none; color:white; font-weight:bold; cursor:pointer;">Bölükler</button>
            <button onclick="orduSekmeDegistir('tabBirlik', event)" class="ordu-tab-btn" style="flex:1; padding: 15px; background: none; border:none; color:white; font-weight:bold; cursor:pointer;">Taburlar</button>
            <button onclick="orduSekmeDegistir('tabOrdu', event)" class="ordu-tab-btn" style="flex:1; padding: 15px; background: none; border:none; color:white; font-weight:bold; cursor:pointer;">Ordular</button>
        </div>

        <div style="flex: 1; overflow-y: auto; padding: 20px; background: #0f172a;">
            <div id="tabBölük" class="ordu-liste" style="display: block;">
                <div style="background: rgba(250, 204, 21, 0.15); border: 1px dashed #facc15; color: #facc15; padding: 10px; border-radius: 8px; text-align: center; font-weight: bold; margin-bottom: 15px;">
                    ⚔️ Bu Okuldaki Bölüklerin Toplam Gücü: <span style="font-size: 16px; color: white;"><?= number_format($toplam_sinif_gucu, 0, ',', '.') ?> Hasar</span>
                </div>
                <?php $sira = 1; foreach ($liste_sinif_boluk as $veri): 
                    $aktif_vurgu = (trim($veri['sinif_adi']) === $aktif_sinif) ? 'aktif-satir-vurgu' : '';
                ?>
                    <div class="<?= $aktif_vurgu ?>" style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: rgba(255,255,255,0.03); border-radius: 10px; margin-bottom: 8px; border-left: 4px solid #64748b;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span style="font-weight: bold; color: #64748b; width: 25px; text-align: center;"><?= $sira ?>.</span>
                            <div>
                                <div style="font-weight: bold; color: white;"><?= htmlspecialchars($veri['sinif_adi']) ?></div>
                            </div>
                        </div>
                        <div style="font-size: 14px; font-weight: bold; color: #f43f5e;"><?= number_format($veri['ortalama_hasar'], 0) ?> Güç</div>
                    </div>
                <?php $sira++; endforeach; ?>
            </div>

            <div id="tabBirlik" class="ordu-liste" style="display: none;">
                <div style="background: rgba(59, 130, 246, 0.15); border: 1px dashed #3b82f6; color: #3b82f6; padding: 10px; border-radius: 8px; text-align: center; font-weight: bold; margin-bottom: 15px;">
                    ⚔️ İlindeki Taburların Toplam Gücü: <span style="font-size: 16px; color: white;"><?= number_format($toplam_okul_gucu, 0, ',', '.') ?> Hasar</span>
                </div>
                <?php $sira = 1; foreach ($liste_okul_birlik as $veri): 
                    $aktif_vurgu = (trim($veri['okul_adi']) === $aktif_okul) ? 'aktif-satir-vurgu' : '';
                ?>
                    <div class="<?= $aktif_vurgu ?>" style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: rgba(255,255,255,0.03); border-radius: 10px; margin-bottom: 8px; border-left: 4px solid #3b82f6;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span style="font-weight: bold; color: #3b82f6; width: 25px; text-align: center;"><?= $sira ?>.</span>
                            <div style="font-weight: bold; color: white;"><?= htmlspecialchars($veri['okul_adi']) ?></div>
                        </div>
                        <div style="font-size: 14px; font-weight: bold; color: #f43f5e;"><?= number_format($veri['ortalama_hasar'], 0) ?> Güç</div>
                    </div>
                <?php $sira++; endforeach; ?>
            </div>

            <div id="tabOrdu" class="ordu-liste" style="display: none;">
                <div style="background: rgba(239, 68, 68, 0.15); border: 1px dashed #ef4444; color: #ef4444; padding: 10px; border-radius: 8px; text-align: center; font-weight: bold; margin-bottom: 15px;">
                    💥 TÜRKİYE TOPLAM ORDU GÜCÜ: <span style="font-size: 18px; color: white; text-shadow: 0 0 10px #ef4444;"><?= number_format($toplam_ordu_gucu, 0, ',', '.') ?> Hasar</span>
                </div>
                <?php $sira = 1; foreach ($sehir_gucu_sorgu as $veri): 
                    $aktif_vurgu = (trim($veri['sehir_adi']) === $aktif_il) ? 'aktif-satir-vurgu' : '';
                ?>
                    <div class="<?= $aktif_vurgu ?>" style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: rgba(255,255,255,0.03); border-radius: 10px; margin-bottom: 8px; border-left: 4px solid #ef4444;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span style="font-weight: bold; color: #ef4444; width: 25px; text-align: center;"><?= $sira ?>.</span>
                            <div style="font-weight: bold; color: white;"><?= mb_strtoupper(htmlspecialchars($veri['sehir_adi'])) ?> ORDUSU</div>
                        </div>
                        <div style="font-size: 14px; font-weight: bold; color: #f43f5e;"><?= number_format($veri['ortalama_hasar'], 0) ?> Güç</div>
                    </div>
                <?php $sira++; endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .aktif-satir-vurgu { background-color: #3f3f46 !important; border-left: 5px solid #facc15 !important; }
    .aktif-satir-vurgu * { color: #facc15 !important; }
    .ordu-tab-btn { border-bottom: 3px solid transparent !important; transition: 0.3s; opacity: 0.6; }
    .ordu-aktif { border-bottom: 3px solid #facc15 !important; opacity: 1; background: rgba(250, 204, 21, 0.1) !important; }
    .ordu-liste { display: none; }
    #tabBölük { display: block; }
</style>

<div id="videoIntroContainer" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: black; z-index: 30000; justify-content: center; align-items: center; overflow: hidden;">
    <video id="aylikSavasVideosu" muted preload="auto" style="width: 100%; height: 100%; object-fit: cover;">Tarayıcınız video oynatmayı desteklemiyor.</video>
    <button onclick="document.getElementById('aylikSavasVideosu').muted = false; this.style.display='none'" style="position: absolute; bottom: 30px; right: 30px; background: rgba(0,0,0,0.6); color: white; border: 1px solid white; padding: 10px 20px; border-radius: 20px; cursor: pointer; z-index: 31;">🔊 Sesi Aç</button>
</div>

<div id="savasRaporModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 20000; justify-content: center; align-items: center; backdrop-filter: blur(15px);">
    <div style="background: linear-gradient(180deg, #1e1b4b 0%, #0f172a 100%); width: 90%; max-width: 500px; border-radius: 20px; border: 2px solid #facc15; box-shadow: 0 0 60px rgba(250, 204, 21, 0.4); text-align: center; padding: 40px 20px; position: relative; overflow: hidden;">
        <h1 style="color: #facc15; font-size: 32px; margin: 0 0 10px 0; text-shadow: 0 4px 10px rgba(0,0,0,0.5); letter-spacing: 2px;">⚔️ SAVAŞ RAPORU</h1>
        <p style="color: #cbd5e1; font-size: 14px; margin-bottom: 30px;">Tüm Türkiye Birlikleri bu ayki devasa saldırısını tamamladı!</p>
        <button onclick="document.getElementById('savasRaporModal').style.display='none'" style="background: linear-gradient(90deg, #f59e0b, #d97706); color: white; border: none; padding: 15px 40px; font-size: 16px; font-weight: bold; border-radius: 30px; cursor: pointer; box-shadow: 0 5px 15px rgba(245, 158, 11, 0.4); transition: 0.3s; width: 100%;">
            SAVAŞ MEYDANINA DÖN
        </button>
    </div>
</div>
<div id="dragonStoryOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(8px); opacity: 0; transition: opacity 0.3s ease;">
    
    <div style="position: relative; width: 90%; max-width: 500px; background: #dfb81c; border: 2px solid #8b4513; border-radius: 10px; padding: 40px; box-shadow: 0 0 50px rgba(0,0,0,0.8), inset 0 0 100px rgba(139,69,19,0.2); transform: scale(0.8); transition: transform 0.3s ease;">
        
        <button onclick="closeDragonStory()" style="position: absolute; top: 10px; right: 15px; background: none; border: none; color: #8b4513; font-size: 24px; cursor: pointer; font-weight: bold;">×</button>
        
        <div id="storyContentArea" style="text-align: center; color: #4b2e1a; font-family: 'Papyrus', 'Palatino Linotype', serif;">
            <h2 id="storyTitle" style="margin-top: 0; color: #8b0000; border-bottom: 1px dashed #8b4513; padding-bottom: 10px;">Aşama 1</h2>
            
            <div style="margin: 20px auto; width: 150px; height: 150px; display: flex; justify-content: center; align-items: center; background: rgba(0,0,0,0.05); border-radius: 50%; padding: 10px;">
                <img id="storyVisual" src="img/canavarlar/yumurta.png" style="max-width: 100%; max-height: 100%; object-fit: contain;">
            </div>
            
            <p id="storyText" style="font-size: 15px; line-height: 1.6; font-weight: 500; height: 180px; overflow-y: auto; text-align: left; padding: 0 10px;">Yükleniyor...</p>
        </div>
        
        <div style="display: flex; justify-content: space-between; margin-top: 25px;">
            <button id="prevStoryBtn" onclick="changeStoryPage(-1)" style="background: #8b4513; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; transition: 0.2s;">&larr; Geri</button>
            <span id="storyPageCounter" style="color: #8b4513; font-weight: bold; display: flex; align-items: center;">1/4</span>
            <button id="nextStoryBtn" onclick="changeStoryPage(1)" style="background: #8b4513; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; transition: 0.2s;">İleri &rarr;</button>
        </div>
    </div>
</div>

<style>
    /* Parşömen dokusu efekti */
    #dragonStoryOverlay > div {
        background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="%23f3e5ab"/><circle cx="50" cy="50" r="40" fill="%23e8d79a" opacity="0.5"/><circle cx="10" cy="10" r="20" fill="%23e8d79a" opacity="0.3"/></svg>');
    }
    #dragonStoryOverlay.aktif { opacity: 1 !important; display: flex !important; }
    #dragonStoryOverlay.aktif > div { transform: scale(1) !important; }
    
    #storyText::-webkit-scrollbar { width: 5px; }
    #storyText::-webkit-scrollbar-thumb { background: #8b4513; border-radius: 5px; }
</style>
<script>
    // --- SES MOTORU ---
    const hikayeMuzigi = new Audio('assets/sounds/hikaye_background1.mp3');
    hikayeMuzigi.loop = true; 
    hikayeMuzigi.volume = 0.6; 

    const sayfaGecisSesi = new Audio('assets/sounds/sayfa_gecis.mp3');
    sayfaGecisSesi.volume = 1.0;

    let muzikGecikmesi; // Öğrenci çok hızlı tıklarsa müziğin yankı yapmasını önleyecek hafıza değişkeni

    // --- HİKAYE VERİ TABANI ---
    const dragonPages = [
        {
            title: "Aşama 1: Tehdit Doğuyor (Kuluçka Evresi)",
            visual: "img/canavarlar/yumurta.png", 
            text: "Dijital evrenimiz yıllardır güvendeydi. Ta ki sistemin derinliklerinde karanlık bir kod satırı, bir 'Bug' (Hata) Yumurtası belirene kadar... Bu sadece sıradan bir ejderha değil. O, bilgisayarların, oyunların ve sistemlerin çökmesine neden olan Kaos'un ta kendisi. Şu an sadece bir yumurta olabilir ama sistemdeki tembelliklerden ve mantık hatalarından beslenerek her saniye büyüyor. Eğer onu durdurmazsak, inşa ettiğimiz tüm bu dijital dünya alevler içinde kalacak."
        },
        {
            title: "Aşama 2: Neden Kodlama Öğrenmelisin? (Silahlanma)",
            visual: "img/canavarlar/bebek_ejderha.png", 
            text: "Bu canavara kılıç veya ok işlemez; çünkü o verilerden ve hatalı algoritmalardan oluşuyor. Onu yenebilecek tek güç Saf Mantık ve Kodlamadır. mBlock'ta birleştirdiğin her bir kod bloğu, aslında senin dijital zırhını dövüyor ve silahını keskinleştiriyor. Yazdığın doğru kodlar (Debug işlemleri), onun hata alevlerini söndüren birer kalkan gibidir. Ne kadar çok kod yazarsan, bu sisteme o kadar çok koruma kalkanı eklersin."
        },
        {
            title: "Aşama 3: Zaman Daralıyor (4 Haftalık Döngü)",
            visual: "img/canavarlar/cocuk_ejderha.png", 
            text: "Ejderha her hafta kabuğunu biraz daha kıracak.\n\n" +
                  "1. Hafta: Zararsız bir yumurta.\n" +
                  "2. Hafta: Kodları kemiren bir larva.\n" +
                  "3. Hafta: Kanatları çıkmış, öfkeli bir canavar.\n" +
                  "4. Hafta: Tam gücüne ulaşmış, dehşet saçan bir Patron (Boss)!\n\n" +
                  "Savaş günü gelene kadar marketten en güçlü eşyaları almak ve algoritmalarını hazırlamak için tam 4 haftan var. Hazırlıksız yakalanırsan, sistemin çöküşünü izlemek zorunda kalırsın."
        },
        {
            title: "Aşama 4: Savaşın Sonucu (Döngü veya Mutasyon)",
            visual: "img/canavarlar/yetiskin_ejderha.png", 
            text: "4. haftanın sonunda büyük savaş başlar! Eğer yazdığın kodlarla ona yeterince hasar verirsen, ejderhanın karmaşık kod yapısı çöker, parçalanır ve sistem onu zararsız bir yumurta olarak yeniden başlatır (Reset). Dünya bir aylığına daha kurtulur.\n\n" +
                  "Ancak... Eğer yeterince çalışmaz ve onu yenemezsen, ejderha yazdığın eksik kodları yutarak Mutasyona uğrar! Hayatta kalır ve bir sonraki döngüde çok daha güçlü, çok daha öfkeli bir şekilde karşına çıkar. Seçim senin Geliştirici: Sistemi onaracak mısın, yoksa ejderhanın yemi mi olacaksın?"
        }
    ];

    let currentStoryPage = 0;

    // --- MODALI VE MÜZİĞİ AÇ ---
    function showDragonStory() {
        currentStoryPage = 0; 
        updateStoryPage();
        document.getElementById('dragonStoryOverlay').classList.add('aktif');

        // İlk açılışta gecikmesiz başlasın
        hikayeMuzigi.currentTime = 0;
        hikayeMuzigi.play().catch(e => console.log("Otomatik oynatma engellendi."));
    }

    // --- MODALI VE SESLERİ KAPAT ---
    function closeDragonStory() {
        document.getElementById('dragonStoryOverlay').classList.remove('aktif');

        // Çarpıya basılınca arka planda bekleyen 1 saniyelik emri de iptal et!
        clearTimeout(muzikGecikmesi);
        
        hikayeMuzigi.pause();
        hikayeMuzigi.currentTime = 0;
        
        sayfaGecisSesi.pause();
        sayfaGecisSesi.currentTime = 0;
    }

    // --- SAYFA DEĞİŞTİRME VE SES EFEKTLERİ ---
    function changeStoryPage(delta) {
        let yeniSayfa = currentStoryPage + delta;
        
        if (yeniSayfa < 0) yeniSayfa = 0;
        if (yeniSayfa >= dragonPages.length) yeniSayfa = dragonPages.length - 1;
        
        if (yeniSayfa !== currentStoryPage) {
            currentStoryPage = yeniSayfa;
            
            // 1. Arka plan müziğini anında durdur ve eski zamanlayıcıyı çöpe at
            hikayeMuzigi.pause();
            clearTimeout(muzikGecikmesi);
            
            // 2. Sayfa çevirme sesini hemen çal
            sayfaGecisSesi.currentTime = 0;
            sayfaGecisSesi.play();
            
            // 3. Arka plan müziğini tam 1 saniye (1000 milisaniye) sonra başlat
            muzikGecikmesi = setTimeout(() => {
                hikayeMuzigi.currentTime = 0;
                hikayeMuzigi.play().catch(e => console.log("Müzik oynatılamadı."));
            }, 1000);
            
            updateStoryPage();
        }
    }

    // --- İÇERİK GÜNCELLEME ---
    function updateStoryPage() {
        const page = dragonPages[currentStoryPage];
        
        document.getElementById('storyTitle').innerText = page.title;
        document.getElementById('storyVisual').src = page.visual;
        document.getElementById('storyText').innerHTML = page.text.replace(/\n/g, '<br>');
        document.getElementById('storyPageCounter').innerText = `${currentStoryPage + 1} / ${dragonPages.length}`;
        
        document.getElementById('prevStoryBtn').style.opacity = (currentStoryPage === 0) ? '0.5' : '1';
        document.getElementById('prevStoryBtn').style.cursor = (currentStoryPage === 0) ? 'not-allowed' : 'pointer';
        
        document.getElementById('nextStoryBtn').style.opacity = (currentStoryPage === dragonPages.length - 1) ? '0.5' : '1';
        document.getElementById('nextStoryBtn').style.cursor = (currentStoryPage === dragonPages.length - 1) ? 'not-allowed' : 'pointer';
    }

    document.getElementById('dragonStoryOverlay').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDragonStory();
        }
    });
</script>