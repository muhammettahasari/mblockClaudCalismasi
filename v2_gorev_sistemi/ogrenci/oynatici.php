<?php
/* [2026-08] Merkezi güvenlik çekirdeğine geçildi.
   guvenlik.php hem db.php'yi yükler hem de varlik() önbellek kırıcısını
   ve güvenli oturum başlatıcıyı sağlar. Bu sayfa ogrenci_paneli.php
   içinde iframe olarak açıldığı için oturum zaten açıktır; Süper Admin
   önizlemesinde de çalışabilmesi için burada zorunlu kapı YOK. */
require_once __DIR__ . '/../../bilesenler/guvenlik.php';
require_once __DIR__ . '/../../bilesenler/ilerleme_yardimcilari.php';
guvenliOturumBaslat();

// Öğrenci Adı ve Puanlarını Çekme
$ogrenci_adi =$_SESSION['ogrenci_ad'] ?? 'Öğrenci';
$kalici_puan = 0;
$bekleyen_puan = 0;
$vesikalik = null;

if (isset($_SESSION['ogrenci_id'])) {
    $aktif_ogrenci_id =$_SESSION['ogrenci_id'];
    
    // 1. Temel Öğrenci Bilgileri
    $st_sorgu =$pdo->prepare("SELECT ad_soyad, kalici_puan, anlik_puan FROM ogrenciler WHERE id = ?");
    $st_sorgu->execute([$aktif_ogrenci_id]);
    $st_data =$st_sorgu->fetch(PDO::FETCH_ASSOC);
    if ($st_data) {
        if (!empty($st_data['ad_soyad'])) { $ogrenci_adi =$st_data['ad_soyad']; }
        $kalici_puan =$st_data['kalici_puan'] ?? 0;
        $bekleyen_puan =$st_data['anlik_puan'] ?? 0;
    }

    // 2. SKORBOARD KATMANLI AVATAR SORGUSU (profil_ve_puanlar.php'den)
    $vesikalik_sorgu =$pdo->prepare("
    SELECT 
            k.resim_yolu as karakter_resim,
            y.resim_yolu as yuz_resim,
            s.resim_yolu as sac_resim,
            kf.resim_yolu as kafa_resim,
            z.resim_yolu as zirh_resim
        FROM ogrenci_avatar_durum oad
        LEFT JOIN esya_kutuphanesi k ON oad.karakter_id = k.id
        LEFT JOIN esya_kutuphanesi y ON oad.yuz_id = y.id
        LEFT JOIN esya_kutuphanesi s ON oad.sac_id = s.id
        LEFT JOIN esya_kutuphanesi kf ON oad.kafa_id = kf.id
        LEFT JOIN esya_kutuphanesi z ON oad.zirh_id = z.id
        WHERE oad.ogrenci_id = ?
    ");
    $vesikalik_sorgu->execute([$aktif_ogrenci_id]);
    $vesikalik =$vesikalik_sorgu->fetch(PDO::FETCH_ASSOC);
}

/* ============================================================================
 * GÖREV SEÇİMİ
 * [2026-08] Eskiden burada "SELECT * FROM v2_gorevler ORDER BY id DESC LIMIT 1"
 * vardı; yani sayfa HER ZAMAN en son eklenen görevi açıyordu ve birden fazla
 * görevle çalışmak imkânsızdı (Bilinen Sorunlar #17).
 * Artık ?gorev_id=12 ile istenen görev açılır; parametre yoksa öğrencinin
 * kaldığı yerden devam edilir.
 *
 * [2026-08 GÜNCELLEME — İLERLEME ÇUBUĞU]
 * Görev seçimi artık bilesenler/ilerleme_yardimcilari.php üzerinden yapılır.
 * Kazanımlar:
 *   - Kilit kontrolü "bolum_id > mevcut_bolum" gibi kırılgan bir karşılaştırma
 *     yerine gerçek erişim listesiyle yapılır (bölüm id'si ile sıra no'su
 *     birbirinden farklı olabiliyor).
 *   - Öğretmen tarafından sıfırlanan görevler tekrar "çözülmemiş" sayıldığı
 *     için öğrenci doğal olarak oraya yönlendirilir.
 *   - Öğrenci son açtığı görevden devam eder (aktif_v2_gorev_id).
 *   - OTOMATİK GEÇİŞ YOK: görev bitince sıradakine kendisi tıklar.
 * ========================================================================== */
$istenenGorevId = isset($_GET['gorev_id']) ? (int) $_GET['gorev_id'] : 0;
$superAdminMi   = !empty($_SESSION['super_admin_id']);   // önizleme yetkisi
$ogrenciId      = isset($_SESSION['ogrenci_id']) ? (int) $_SESSION['ogrenci_id'] : 0;
$gorev          = null;

/* Öğrencinin erişebildiği bölümler (admin önizlemesinde kontrol yok) */
$acikBolumler = ($ogrenciId > 0) ? erisilebilirBolumler($pdo, $ogrenciId) : [];

if ($istenenGorevId > 0) {
    $st = $pdo->prepare("SELECT * FROM v2_gorevler WHERE id = ?");
    $st->execute([$istenenGorevId]);
    $gorev = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    // Taslak görevleri sadece admin önizleyebilir
    if ($gorev && (int) $gorev['yayinda'] !== 1 && !$superAdminMi) {
        $gorev = null;
    }

    // Öğrenci ulaşmadığı bölümün görevini açamaz (anti-hile)
    if ($gorev && !$superAdminMi && $ogrenciId > 0
        && !in_array((int) $gorev['bolum_id'], $acikBolumler, true)) {
        $gorev = null;
    }
}

/* Parametre yoksa: öğrencinin en son açtığı görev (kaldığın yerden devam)
 *
 * [2026-08 ÖNEMLİ] Son açılan görev ZATEN ÇÖZÜLMÜŞSE onu tekrar açmayız.
 * Sebebi: kutlama kartı kapatıldığında sayfa yenileniyor. Çözülmüş göreve
 * geri dönseydik öğrenci aynı görevde kilitli kalır, "sıradaki göreve
 * geçmiyor" derdi. Çözülmüşse aşağıdaki adım sıradaki görevi bulur. */
if (!$gorev && $ogrenciId > 0) {
    $st = $pdo->prepare("SELECT aktif_v2_gorev_id FROM ogrenciler WHERE id = ?");
    $st->execute([$ogrenciId]);
    $sonAcilan = (int) ($st->fetchColumn() ?: 0);

    if ($sonAcilan > 0) {
        $ilerlemeKayitlari = ogrencininIlerlemesi($pdo, $ogrenciId);
        $kayit   = $ilerlemeKayitlari[$sonAcilan] ?? null;
        $cozuldu = $kayit && (int) $kayit['kazanilan_puan'] > 0;

        if (!$cozuldu) {
            $st = $pdo->prepare("SELECT * FROM v2_gorevler WHERE id = ? AND yayinda = 1");
            $st->execute([$sonAcilan]);
            $aday = $st->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($aday && in_array((int) $aday['bolum_id'], $acikBolumler, true)) {
                $gorev = $aday;
            }
        }
    }
}

/* Hâlâ yoksa: sıradaki çözülmemiş görev (sıfırlananlar dahil) */
if (!$gorev && $ogrenciId > 0) {
    $sonraki = sonrakiGoreviBul($pdo, $ogrenciId);
    if ($sonraki) {
        $st = $pdo->prepare("SELECT * FROM v2_gorevler WHERE id = ?");
        $st->execute([$sonraki]);
        $gorev = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

// Hâlâ yoksa: yayındaki ilk görev (admin önizlemesi / boş kurulum / hepsi bitmiş)
if (!$gorev) {
    $gorev = $pdo->query(
        "SELECT * FROM v2_gorevler WHERE yayinda = 1 ORDER BY bolum_id ASC, sira_no ASC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC) ?: null;
}

$gorevPaketi = $gorev
    ? $gorev['icerik_json']
    : '{"hikaye_metni": {}, "blueprint": {"ana_cozum": "", "alternatif_cozumler": []}}';

$baslik      = $gorev ? $gorev['gorev_adi'] : 'Görev Bulunamadı';
$aktifGorevId = $gorev ? (int) $gorev['id'] : 0;
$gorevPuani   = $gorev ? (int) $gorev['puan'] : 0;

/* Açılan görevi "kaldığın yer" olarak kaydet */
if ($ogrenciId > 0 && $aktifGorevId > 0) {
    $pdo->prepare("UPDATE ogrenciler SET aktif_v2_gorev_id = ? WHERE id = ?")
        ->execute([$aktifGorevId, $ogrenciId]);
}

/* İlerleme çubuğu verisi + ceza durumu + öğretmen bildirimi */
$ilerlemeVerisi = $ogrenciId > 0
    ? ilerlemeHaritasi($pdo, $ogrenciId, $aktifGorevId > 0 ? $aktifGorevId : null)
    : ['bolumler' => [], 'aktif_gorev_id' => $aktifGorevId, 'toplam_gorev' => 0,
       'cozulen_gorev' => 0, 'genel_yuzde' => 0, 'bekleyen_ceza' => 0];

$cezaDurumu    = $ogrenciId > 0 ? ogrencininCezasi($pdo, $ogrenciId) : ['cezali' => false, 'gorev_sayisi' => 0, 'bolumler' => '', 'sebep' => null];
$cezaBildirimi = $ogrenciId > 0 ? okunmamisSifirlamaBildirimi($pdo, $ogrenciId) : null;

/* Aktif görevin ait olduğu bölüm — çubuk başlığında gösterilir */
$aktifBolumAdi = '';
foreach ($ilerlemeVerisi['bolumler'] as $b) {
    foreach ($b['gorevler'] as $g) {
        if ($g['id'] === $aktifGorevId) { $aktifBolumAdi = $b['ad']; break 2; }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Görev: <?= htmlspecialchars($baslik) ?></title>
    <link href="../assets/css/tasarlayici_style.css" rel="stylesheet">
    <link href="../assets/css/ogrenci_oynatici.css?v=<?= time() ?>" rel="stylesheet">
    <link href="<?= varlik('v2_gorev_sistemi/assets/css/ilerleme_cubugu.css', '../../') ?>" rel="stylesheet">
</head>

 <body<?= $cezaDurumu['cezali'] ? ' class="ceza-modu"' : '' ?>>

    <!-- ====================================================================
         [2026-08] İLERLEME ŞERİDİ — sayfanın en üstünde, boydan boya
         Sol: bölüm › görev başlığı.  Sağ: yüzde çubuğu.  Orta: görev daireleri.
           yeşil = bitti | turuncu = şu an buradasın | gri = sırası gelmedi
           kırmızı = öğretmen tekrar istedi
         SADECE ÖĞRENCİNİN İÇİNDE BULUNDUĞU BÖLÜM gösterilir; bitirdiği
         bölümler ekranı kalabalık etmesin diye gizlenir.
         Şerit sol/sağ panelin DIŞINDA olduğu için sahne küçülmez.
         ==================================================================== -->
    <div id="ilerlemeCubugu" class="ilerleme-serit">
        <div class="ic-sol">
            <span class="ic-bolum" id="icBolumAdi"><?= temizle($aktifBolumAdi ?: 'Bölüm') ?></span>
            <span class="ic-ayrac">›</span>
            <span class="ic-gorev" id="icGorevAdi"><?= temizle($baslik) ?></span>
        </div>

        <div class="ic-orta" id="icGovde"><!-- görev daireleri: JS doldurur --></div>

        <div class="ic-sag">
            <div class="ic-bar"><span id="icBarDolgu" style="width:<?= (int) $ilerlemeVerisi['genel_yuzde'] ?>%"></span></div>
            <small id="icOran"><?= (int) $ilerlemeVerisi['cozulen_gorev'] ?>/<?= (int) $ilerlemeVerisi['toplam_gorev'] ?></small>
        </div>
    </div>

    <!-- Şeridin altındaki asıl çalışma alanı: solda görev+sahne, sağda kod -->
    <div class="calisma-alani" id="calismaAlani">

    <div class="sol-panel">

        <div id="hikayeAlani" class="hikaye-icerik">Yükleniyor...</div>

        <div class="buton-grup">
            <button class="btn-ipucu" onclick="ipucuGoster()">💡 Bilgi Al</button>
            <button class="btn-test" id="btnHakem" onclick="hakemiCagir()" style="background: #3b82f6;">
                ✅ Görevimi Kontrol Et<?= $gorevPuani > 0 ? ' (' . $gorevPuani . ' Puan)' : '' ?>
            </button>
        </div>

        <!-- [2026-08] Hakem sonuç kutusu — sunucudan gelen karar buraya yazılır (#17) -->
        <div id="hakemSonucKutusu" style="display:none; margin-top:12px; padding:14px; border-radius:10px; font-weight:bold; font-size:14px; line-height:1.5;"></div>

        <script>
            // Aktif görevin kimliği — hakem_motoru.js bunu sunucuya gönderir
            window.v2AktifGorevId = <?= (int) $aktifGorevId ?>;
            window.v2GorevPuani   = <?= (int) $gorevPuani ?>;
        </script>

        <div class="sahne-alt-bar">
            <div class="gorunum-kontrolleri">
                <button class="gorunum-btn aktif" id="btnKucukSahne" title="Normal Sahne" onclick="sahneModuDegistir('kucuk')"><svg viewBox="0 0 24 24">
                        <path d="M3 5h8v14H3V5zm10 0h8v14h-8V5z" />
                    </svg></button>
                <button class="gorunum-btn" id="btnBuyukSahne" title="Geniş Sahne" onclick="sahneModuDegistir('buyuk')"><svg viewBox="0 0 24 24">
                        <path d="M3 5h12v14H3V5zm14 0h4v14h-4V5z" />
                    </svg></button>
                <button class="gorunum-btn" id="btnTamEkran" title="Tam Ekran" onclick="tamEkranYap()"><svg viewBox="0 0 24 24">
                        <path d="M5 5h5v2H7v3H5V5zm9 0h5v5h-2V7h-3V5zm5 14h-5v-2h3v-3h2v5zm-14 0v-5h2v3h3v2H5z" />
                    </svg></button>
            </div>

            <div class="sahne-kontrolleri" style="margin-top: 0;">
                <button id="btnDurdur" class="kontrol-btn durdur-btn" title="Durdur" onclick="window.oyunuDurdur()"><svg width="14" height="14" viewBox="0 0 24 24" fill="white">
                        <rect x="5" y="5" width="14" height="14" rx="2"></rect>
                    </svg></button>
                <button id="btnYesilBayrak" class="kontrol-btn baslat-btn" title="Başlat" onclick="window.yesilBayraklariTetikle()"><svg width="18" height="18" viewBox="0 0 24 24" fill="white">
                        <path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z"></path>
                    </svg></button>
            </div>
        </div>

        <div class="oyun-sahnesi">
            <div class="tam-ekran-kontroller">
                <button class="tam-ekran-kapat-btn" title="Tam Ekrandan Çık" onclick="tamEkranCik()"><svg viewBox="0 0 24 24">
                        <path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z" />
                    </svg></button>
                <div class="sahne-kontrolleri" style="margin-top: 0;">
                    <button class="kontrol-btn durdur-btn" onclick="window.oyunuDurdur()"><svg width="14" height="14" viewBox="0 0 24 24" fill="white">
                            <rect x="5" y="5" width="14" height="14" rx="2"></rect>
                        </svg></button>
                    <button class="kontrol-btn baslat-btn" onclick="window.yesilBayraklariTetikle()"><svg width="18" height="18" viewBox="0 0 24 24" fill="white">
                            <path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z"></path>
                        </svg></button>
                </div>
            </div>
            <div id="konusmaBalonu"></div>
        </div>

        <div class="kukla-paneli">
            <div class="panel-grup"><label>Kukla</label>
                <div style="display: flex; gap: 5px;">
                    <input type="text" id="panelKuklaAdi" value="" oninput="arayuzdenAktifKuklayiGuncelle()" maxlength="15" style="width: 70px;">
                    <button class="btn-test" style="padding: 6px 10px; font-size: 12px; border-radius: 6px;" onclick="kuklaPenceresiniAc()">+ Seç</button>
                    <button class="btn-ipucu" style="padding: 6px 10px; font-size: 12px; border-radius: 6px; background: #8b5cf6;" onclick="kostumAtolyesiniAc()">🎨 Kostümler</button>
                </div>
            </div>
            <div class="panel-grup"><label>X</label><input type="number" id="panelX" value="0" oninput="arayuzdenAktifKuklayiGuncelle()"></div>
            <div class="panel-grup"><label>Y</label><input type="number" id="panelY" value="0" oninput="arayuzdenAktifKuklayiGuncelle()"></div>
            <div class="panel-grup"><label>Boyut</label><input type="number" id="panelBoyut" value="100" oninput="arayuzdenAktifKuklayiGuncelle()"></div>
            <div class="panel-grup"><label>Yön</label><input type="number" id="panelYon" value="0" oninput="arayuzdenAktifKuklayiGuncelle()"></div>
            <div class="panel-grup"><label>Göster</label>
                <div class="panel-butonlar">
                    <button id="btnGoster" class="aktif" onclick="arayuzdenGosterGizle(true)">👁️</button>
                    <button id="btnGizle" onclick="arayuzdenGosterGizle(false)">🚫</button>
                </div>
            </div>
        </div>
        <div class="kukla-listesi-kapsayici" id="kuklaListesiKapsayici"></div>
        <div id="sagTikMenu">
            <div class="sag-tik-oge" onclick="kuklaKopyalaMenu()">📄 Kopyasını Çıkart</div>
            <div class="sag-tik-oge sil" onclick="kuklaSilMenu()">🗑️ Sil</div>
        </div>
    </div>

 <!-- [2026-08] height:100vh KALDIRILDI: artık .calisma-alani içindeyiz,
      yükseklik oradan geliyor. 100vh bırakılsaydı şeridin boyu kadar
      taşar, sayfa aşağı kayardı. -->
 <div class="sag-panel" style="display: flex; flex-direction: column; flex: 1 !important; min-width: 0; height: 100%; position: relative; padding-right: 15px; box-sizing: border-box;">
        
<div style="flex: 0 0 auto; display: flex; align-items: center; justify-content: space-between; background: linear-gradient(145deg, #1e293b, #0f172a); padding: 12px 20px; border-radius: 12px; margin-bottom: 10px; border: 1.5px solid #38bdf8; box-shadow: 0 4px 15px rgba(56, 189, 248, 0.2);">
           <div style="display: flex; align-items: center; gap: 15px;">
                <a href="../../profil.php" target="_parent" title="Profili Düzenle" style="display: flex; align-items: center; gap: 15px; text-decoration: none; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    
                    <!-- [2026-08] class="profile-circle": kırmızı ceza efektinin bağlandığı kanca -->
                    <div class="profile-circle<?= $cezaDurumu['cezali'] ? ' ceza-efekti' : '' ?>" style="width: 70px; height: 70px; border-radius: 50%; border: 3.5px solid <?= $cezaDurumu['cezali'] ? '#ef4444' : '#fbbf24' ?>; background-color: #0f172a; display: flex; justify-content: center; align-items: center; overflow: hidden; position: relative; box-shadow: 0 0 15px <?= $cezaDurumu['cezali'] ? 'rgba(239, 68, 68, 0.55)' : 'rgba(251, 191, 36, 0.4)' ?>; flex-shrink: 0;">
                        <?php if ($vesikalik && !empty($vesikalik['karakter_resim'])): ?>
                            <div style="position: absolute; width: 350px; height: 350px; left: 50%; top: 14%; transform: translate(-50%, -33%) scale(0.7); transform-origin: center center; pointer-events: none;">
                                <img src="../../<?= htmlspecialchars(ltrim($vesikalik['karakter_resim'], '/')) ?>" style="position: absolute; top:0; left:0; width: 100%; height: 100%; object-fit: contain; z-index: 1;">
                                <?php if (!empty($vesikalik['zirh_resim'])): ?>
                                    <img src="../../<?= htmlspecialchars(ltrim($vesikalik['zirh_resim'], '/')) ?>" style="position: absolute; top:0; left:0; width: 100%; height: 100%; object-fit: contain; z-index: 2;">
                                <?php endif; ?>
                                <?php if (!empty($vesikalik['yuz_resim'])): ?>
                                    <img src="../../<?= htmlspecialchars(ltrim($vesikalik['yuz_resim'], '/')) ?>" style="position: absolute; top:2px; left:-105px; width: 160%; height: 100%; object-fit: contain; z-index: 3;">
                                <?php endif; ?>
                                <?php if (!empty($vesikalik['sac_resim'])): ?>
                                    <img src="../../<?= htmlspecialchars(ltrim($vesikalik['sac_resim'], '/')) ?>" style="position: absolute; top:0; left:0; width: 100%; height: 100%; object-fit: contain; z-index: 4;">
                                <?php endif; ?>
                                <?php if (!empty($vesikalik['kafa_resim'])): ?>
                                    <img src="../../<?= htmlspecialchars(ltrim($vesikalik['kafa_resim'], '/')) ?>" style="position: absolute; top:8px; left:-140px; width: 180%; height: 100%; object-fit: contain; z-index: 5;">
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span style="font-size: 30px; font-weight: bold; color: white;"><?= mb_substr($ogrenci_adi, 0, 1) ?></span>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <span style="color: #ffffff; font-weight: 900; font-size: 16px; text-shadow: 0 2px 4px rgba(0,0,0,0.5);"><?= htmlspecialchars($ogrenci_adi) ?></span>
                        <span style="display: inline-block; background: rgba(56, 189, 248, 0.2); color: #38bdf8; font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 20px; border: 1px solid rgba(56, 189, 248, 0.4); text-align: center;">⚙️ Profili Düzenle</span>
                    </div>
                </a>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <span style="color: #fbbf24; font-size: 15px; font-weight: 900; background: rgba(251, 191, 36, 0.15); padding: 6px 14px; border-radius: 10px; border: 1px solid rgba(251, 191, 36, 0.4); box-shadow: 0 2px 6px rgba(0,0,0,0.3);">⭐ <?= $kalici_puan ?></span>
                <span style="color: #38bdf8; font-size: 15px; font-weight: 900; background: rgba(56, 189, 248, 0.15); padding: 6px 14px; border-radius: 10px; border: 1px solid rgba(56, 189, 248, 0.4); box-shadow: 0 2px 6px rgba(0,0,0,0.3);">⏳ <?= $bekleyen_puan ?></span>
            </div>
        </div>

        <div style="flex: 0 0 auto; display: flex; background: #0f172a; border-radius: 10px 10px 0 0; border: 1px solid #334155; border-bottom: none; overflow: hidden;">
            <button id="tab-btn-kod" onclick="sekmeDegistir('kod')" style="flex: 1; padding: 14px; background: #3b82f6; color: white; border: none; font-weight: 800; font-size: 14px; cursor: pointer; transition: 0.2s;">
                💻 Kod Blokları
            </button>
            <button id="tab-btn-savas" onclick="sekmeDegistir('savas')" style="flex: 1; padding: 14px; background: #1e293b; color: #94a3b8; border: none; font-weight: 800; font-size: 14px; cursor: pointer; transition: 0.2s;">
                ⚔️ Savaş Alanı
            </button>
        </div>

        <div style="flex: 1 1 auto; position: relative; border: 1px solid #334155; border-radius: 0 0 10px 10px; background: #ffffff; min-height: 400px; overflow: hidden;">
            
            <div id="sekme-kod-alani" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: block;">
                <div id="blocklyOgrenci" style="width: 100%; height: 100%;"></div>
            </div>

            <div id="sekme-savas-alani" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: none; overflow-y: auto; padding: 20px; background: #0f172a;">
                <div id="savas-alani-hedef">
                    <h3 style="color: #94a3b8; text-align: center; margin-top: 40px;">Savaş Alanı Yükleniyor...</h3>
                </div>
            </div>

        </div>
    </div>

    </div><!-- /.calisma-alani -->

     <script>
        function sekmeDegistir(hedef) {
            const btnKod = document.getElementById('tab-btn-kod');
            const btnSavas = document.getElementById('tab-btn-savas');
            const alanKod = document.getElementById('sekme-kod-alani');
            const alanSavas = document.getElementById('sekme-savas-alani');
            const hedefKutu = document.getElementById('savas-alani-hedef');

            if (hedef === 'kod') {
                btnKod.style.background = '#3b82f6';
                btnKod.style.color = 'white';
                btnSavas.style.background = '#1e293b';
                btnSavas.style.color = '#94a3b8';
                
                alanSavas.style.display = 'none';
                alanKod.style.display = 'block';

                if (window.Blockly && Blockly.mainWorkspace) {
                    Blockly.svgResize(Blockly.mainWorkspace);
                }
            } else if (hedef === 'savas') {
                btnSavas.style.background = '#ef4444';
                btnSavas.style.color = 'white';
                btnKod.style.background = '#1e293b';
                btnKod.style.color = '#94a3b8';

                alanKod.style.display = 'none';
                alanSavas.style.display = 'block';

                // Skorboard'u ebeveynden (parent) çek
                skorboardSenkronizeEt();
            }
        }

        // --- SKORBOARD DİNAMİK SENKRONİZASYON MOTORU ---
        function skorboardSenkronizeEt() {
            const hedefKutu = document.getElementById('savas-alani-hedef');
            try {
                const depo = window.parent.document.getElementById('gizli-skorboard-deposu');
                if (depo) {
                    hedefKutu.innerHTML = depo.innerHTML; // Ana sayfadan güncel HTML'i al
                    skorboardArayuzunuOnar(hedefKutu);    // Resimleri ve butonları onar
                } else {
                    hedefKutu.innerHTML = '<h3 style="color:white; text-align:center;">Skorboard deposu bulunamadı!</h3>';
                }
            } catch(e) {
                console.error("Skorboard aktarım hatası:", e);
                hedefKutu.innerHTML = '<h3 style="color:white; text-align:center;">Bağlantı Hatası!</h3>';
            }
        }

        // --- 404 RESİM HATALARINI VE BUTONLARI ONARMA ---
        function skorboardArayuzunuOnar(container) {
            if (!container) return;

            // 1. RESİMLERİ ONAR (Kırık Zırh, Karakter ve Yumurta resimleri için 404 çözüm)
            const resimler = container.querySelectorAll('img');
            resimler.forEach(img => {
                let src = img.getAttribute('src');
                if (src && !src.startsWith('http') && !src.startsWith('data:') && !src.startsWith('../../')) {
                    // Eğer resim yolu / ile başlamıyorsa başına ../../ ekleyerek ana dizine çıkar
                    if (!src.startsWith('/')) {
                        img.setAttribute('src', '../../' + src);
                    }
                }
            });

            // 2. LİNK YÖNLENDİRMELERİ (Profil, Market vb.)
            const linkler = container.querySelectorAll('a');
            linkler.forEach(a => {
                let href = a.getAttribute('href');
                if (href && !href.startsWith('http') && !href.startsWith('#') && !href.startsWith('javascript') && !href.startsWith('../../')) {
                    a.setAttribute('href', '../../' + href);
                    a.setAttribute('target', '_parent');
                }
            });

            // 3. SEKMELER VE BUTONLAR (Sınıf İçi, Okul, TR Geneli, Düello)
            //
            // [2026-08] "meydanOku is not defined" HATASI DÜZELTİLDİ:
            // Eskiden buradaki kod onclick komutunu SADECE window.parent
            // kapsamında çalıştırıyordu. Düello fonksiyonları yalnızca ana
            // sayfada tanımlı olduğu için, ana sayfa o an o fonksiyonu
            // yüklememişse "meydanOku is not defined" hatası düşüyordu.
            // Artık düello motoru (js/duello_motoru.js) bu iframe'e de
            // yükleniyor ve komut ÖNCE bu pencerede, olmazsa ana pencerede
            // çalıştırılıyor.
            const tumElemanlar = container.querySelectorAll('*');
            tumElemanlar.forEach(el => {
                let onclickAttr = el.getAttribute('onclick');
                if (onclickAttr && !onclickAttr.includes('sekmeDegistir')) {
                    // Orijinal onclick'i DOM'dan kaldır ki çakışma yapmasın
                    el.removeAttribute('onclick');

                    el.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();

                        const kod = onclickAttr.replace(/return\s+false;?/g, '');

                        // Denenecek kapsamlar: önce kendi penceremiz, sonra ana sayfa
                        const kapsamlar = [window];
                        try { if (window.parent && window.parent !== window) kapsamlar.push(window.parent); } catch (x) {}

                        let calisti = false;
                        let sonHata = null;

                        for (const kapsam of kapsamlar) {
                            try {
                                new Function('window', 'with(window) { ' + kod + ' }').call(el, kapsam);
                                calisti = true;
                                break;
                            } catch (err) {
                                sonHata = err;   // Bu kapsamda yoktu, diğerini dene
                            }
                        }

                        if (!calisti) {
                            console.warn('Buton çalıştırılamadı:', kod, sonHata);
                        }

                        // Ana sayfada sekme değiştiyse güncel HTML'i tekrar çek
                        setTimeout(() => { skorboardSenkronizeEt(); }, 50);
                    });
                }
            });
        }

        window.addEventListener('load', function() {
            setTimeout(function() {
                if (window.Blockly && Blockly.mainWorkspace) {
                    Blockly.svgResize(Blockly.mainWorkspace);
                }
            }, 500);
        });
    </script>
        
  <div class="kukla-modal-overlay" id="kuklaSeciciModal">
        <div class="kukla-modal">
            <div class="modal-baslik-alani" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px;">
                <h2 style="margin: 0;">🎭 Kütüphaneden Kukla Seç</h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    
                  <!-- GEÇİCİ SİLME KISAYOLU (Iframe Modal Açar) -->
                    <button onclick="adminPenceresiAc('/mblock_platform/v2_gorev_sistemi/admin/kukla_kutuphanesi/index.php')" style="background-color: #ef4444; color: white; padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); font-size: 14px;">
                        🗑️ Kukla Sil
                    </button>

                    <!-- YENİ KUKLA TANIMLA KISAYOLU (Iframe Modal Açar) -->
                    <button onclick="adminPenceresiAc('/mblock_platform/v2_gorev_sistemi/admin/kukla_kutuphanesi/kukla_ekle.php')" style="background-color: #10b981; color: white; padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); font-size: 14px;">
                        ➕ Yeni Kukla Tanımla
                    </button>
                    
                    <button class="btn-kapat" onclick="kuklaPenceresiniKapat()">✕</button>
                </div>
            </div>
            <div class="modal-filtreler" id="modalFiltreAlani"></div>
            <div class="modal-icerik" id="modalKuklaIzgarasi">
                <div style="grid-column: 1 / -1; text-align: center; color: #94a3b8; padding: 40px;">Kuklalar Yükleniyor...</div>
            </div>
        </div>
    </div>

    <div class="atolye-overlay" id="kostumAtolyesiModal">
        <div class="atolye-pencere">
            <div class="atolye-ust-bar">
                <h2 id="atolyeKuklaAdiBaslik">🎨 Kostüm Atölyesi</h2>
                <div class="atolye-butonlar">
                    <button class="btn-atolye btn-removebg" onclick="arkaplanKaldiracGit()" title="Yapay Zeka ile Arka Planı Sil">✨ Arka Planı Kaldır</button>
                    <button class="btn-atolye btn-indir" onclick="kuklayiIndir()" title="Kuklayı Bilgisayara İndir">📦 İndir (.zip)</button>
                    <button class="btn-atolye btn-kaydet" onclick="atolyeKaydet()">💾 Kaydet / Çık</button>
                <button type="button" onclick="window.atolyeKapatmayiDene()" class="atolye-kapat-btn">✖</button>
                </div>
            </div>

            <div class="atolye-govde">

                <div class="atolye-sol-panel" id="atolyeKostumListesi"></div>

                <div class="atolye-orta-panel">

                    <div class="ust-kontrol-paneli">
                        <div class="kontrol-satir">
                            <div class="kontrol-grup">
                                📝 <input type="text" id="ayarKostumAdi" value="Kostüm 1" onchange="kostumAdiniGuncelle(this.value)" title="Kostüm Adı">
                            </div>
                            <div class="kontrol-grup" style="position: relative;" id="grupDolgu">
                                <label>Dolgu:</label>
                                <div class="renk-btn-gorsel" id="btnGorselDolgu" onclick="renkPaneliAc('dolgu')"></div>
                                <div class="renk-popover" id="popoverDolgu">
                                    <div class="arac-grid dortlu">
                                        <button class="arac-btn aktif" onclick="renkTipiSec('dolgu', 'duz')" title="Düz Renk"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                                                <path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" />
                                            </svg></button>
                                        <button class="arac-btn" onclick="renkTipiSec('dolgu', 'yok')" title="Şeffaf (Yok)"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <line x1="3" y1="3" x2="21" y2="21" />
                                            </svg></button>
                                        <button class="arac-btn" onclick="renkTipiSec('dolgu', 'linear')" title="Doğrusal (Linear)"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <line x1="12" y1="3" x2="12" y2="21" />
                                            </svg></button>
                                        <button class="arac-btn" onclick="renkTipiSec('dolgu', 'radial')" title="Radyal (Radial)"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <circle cx="12" cy="12" r="5" />
                                            </svg></button>
                                    </div>
                                    <div id="icerikDolgu_duz"><input type="color" id="renkDolguDuz" value="#3b82f6" oninput="renkGuncelle('dolgu')" style="width:100%; height:30px; cursor:pointer;"></div>
                                    <div id="icerikDolgu_gradyan" style="display:none;">
                                        <div class="gradyan-kapsayici" onclick="gradyanDurakEkle(event, 'dolgu')">
                                            <div class="gradyan-cubuk" id="cubukGorselDolgu"></div>
                                            <div id="duraklarDolgu"></div>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <input type="color" id="renkDolguDurak" oninput="seciliDurakRenginiGuncelle('dolgu')" style="width:50px; height:25px;">
                                            <span style="font-size:10px; color:#64748b;">(Silmek için noktayı aşağı çek)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="kontrol-grup" style="position: relative;" id="grupCizgi">
                                <label>Çizgi:</label>
                                <div class="renk-btn-gorsel" id="btnGorselCizgi" style="background:#1e293b;" onclick="renkPaneliAc('cizgi')"></div>
                                <div class="renk-popover" id="popoverCizgi">
                                    <div class="arac-grid dortlu">
                                        <button class="arac-btn aktif" onclick="renkTipiSec('cizgi', 'duz')" title="Düz Renk"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                                                <path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" />
                                            </svg></button>
                                        <button class="arac-btn" onclick="renkTipiSec('cizgi', 'yok')" title="Şeffaf (Yok)"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <line x1="3" y1="3" x2="21" y2="21" />
                                            </svg></button>
                                        <button class="arac-btn" onclick="renkTipiSec('cizgi', 'linear')" title="Doğrusal (Linear)"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <line x1="12" y1="3" x2="12" y2="21" />
                                            </svg></button>
                                        <button class="arac-btn" onclick="renkTipiSec('cizgi', 'radial')" title="Radyal (Radial)"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <circle cx="12" cy="12" r="5" />
                                            </svg></button>
                                    </div>
                                    <div id="icerikCizgi_duz"><input type="color" id="renkCizgiDuz" value="#1e293b" oninput="renkGuncelle('cizgi')" style="width:100%; height:30px; cursor:pointer;"></div>
                                    <div id="icerikCizgi_gradyan" style="display:none;">
                                        <div class="gradyan-kapsayici" onclick="gradyanDurakEkle(event, 'cizgi')">
                                            <div class="gradyan-cubuk" id="cubukGorselCizgi"></div>
                                            <div id="duraklarCizgi"></div>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <input type="color" id="renkCizgiDurak" oninput="seciliDurakRenginiGuncelle('cizgi')" style="width:50px; height:25px;">
                                            <span style="font-size:10px; color:#64748b;">(Silmek için noktayı aşağı çek)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="kontrol-grup">
                                <button class="arac-btn" onclick="atolyeGeriAl()" title="Geri Al (Ctrl+Z)" style="padding: 4px 8px;"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                                        <path d="M3 3v5h5" />
                                    </svg></button>
                                <button class="arac-btn" onclick="atolyeIleriAl()" title="İleri Al (Ctrl+Y)" style="padding: 4px 8px;"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                                        <path d="M21 12a9 9 0 1 1-9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
                                        <path d="M21 3v5h-5" />
                                    </svg></button>
                            </div>
                            <div class="kontrol-grup">
                                <button class="arac-btn" onclick="katmanYukari()" title="Üste Al" style="padding: 4px 8px;"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                                        <polyline points="18 15 12 9 6 15" />
                                    </svg> Üste Al</button>
                                <button class="arac-btn" onclick="katmanAsagi()" title="Alta Gönder" style="padding: 4px 8px;"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                                        <polyline points="6 9 12 15 18 9" />
                                    </svg> Alta Gönder</button>
                            </div>
                        </div>
                        <div class="kontrol-satir baglamsal" id="context-shape">
                            <div class="kontrol-grup kalinlik-popover-grup" id="grupKalinlik">
                                <label id="lblAyarKalinlik">Çizgi Kalınlığı:</label>

                                <button type="button" class="kalinlik-ozet-btn" id="btnKalinlikPopover">
                                    <input type="number" id="ayarKalinlik" value="3" min="0" max="100">
                                    <span>px</span>
                                </button>

                                <div class="kalinlik-popover" id="popoverKalinlik">
                                    <input
                                        type="range"
                                        id="ayarKalinlikSlider"
                                        min="1"
                                        max="60"
                                        step="1"
                                        value="3">
                                </div>
                            </div>

                            <div class="kontrol-grup" id="grupCizgiTipi">
                                <label>Çizgi Türü:</label>
                                <select id="ayarCizgiTipi" onchange="cizgiTipiGuncelle()">
                                    <option value="solid">Düz Çizgi</option>
                                    <option value="dashed">Kesik Kesik</option>
                                </select>
                            </div>
                        </div>

                        <div class="kontrol-satir baglamsal" id="context-text">
                            <div class="kontrol-grup">
                                <label>Font Tipi:</label>
                                <select id="ayarFont">
                                    <option value="Arial">Arial</option>
                                    <option value="Verdana">Verdana</option>
                                    <option value="Courier New">Courier</option>
                                </select>
                            </div>
                            <div class="kontrol-grup"><label>Punto:</label><input type="number" id="ayarPunto" value="24" min="8" max="150"> px</div>
                        </div>
                        <div class="kontrol-satir baglamsal" id="context-olcum">
                            <div class="kontrol-grup">
                                <label>Ölçüm Birimi:</label>
                                <select id="ayarOlcumBirim">
                                    <option value="px">Piksel (px)</option>
                                    <option value="cm">Santimetre (cm)</option>
                                    <option value="mm">Milimetre (mm)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="canvas-container-div" id="atolyeCanvasWrapper">
                        <canvas id="cizimTuvali" resize></canvas>

                         <div id="atolyeZoomKontrol"
     class="atolye-zoom-kontrol"
     onmousedown="event.stopPropagation()"
     onclick="event.stopPropagation()">

    <!-- Seçim / Ok -->
    <button type="button"
            id="btnAtolyeSecimMini"
            class="zoom-sonrasi"
            onclick="window.atolyeSecimModuAc()"
            title="Seçim Aracı">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 4l7.5 16 2.5-7.5L21.5 10 4 4z" />
        </svg>
    </button>

    <!-- Pan / El -->
    <button type="button"
        id="btnAtolyePanMini"
        class="zoom-sonrasi"
        onclick="window.atolyePanModuAc()"
        title="Kaydır / Pan">
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M8.5 11V5.5a1.5 1.5 0 0 1 3 0V10" />
        <path d="M11.5 10V4.5a1.5 1.5 0 0 1 3 0V10" />
        <path d="M14.5 10V6.5a1.5 1.5 0 0 1 3 0V14" />
        <path d="M8.5 10V8.5a1.5 1.5 0 0 0-3 0V15a6.5 6.5 0 0 0 13 0v-3.5a1.5 1.5 0 0 0-3 0V14" />
    </svg>
</button>
    <!-- Sahneye sığdır -->
    <button type="button"
            onclick="window.atolyeEkranaSigdir()"
            title="Ekrana Sığdır">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="3.2"></circle>
            <line x1="12" y1="2.5" x2="12" y2="6"></line>
            <line x1="12" y1="18" x2="12" y2="21.5"></line>
            <line x1="2.5" y1="12" x2="6" y2="12"></line>
            <line x1="18" y1="12" x2="21.5" y2="12"></line>
        </svg>
    </button>

    <!-- Yakınlaştır -->
    <button type="button"
            onclick="window.atolyeZoomla(1.2)"
            title="Yakınlaştır">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="8.5"></circle>
            <line x1="12" y1="8" x2="12" y2="16"></line>
            <line x1="8" y1="12" x2="16" y2="12"></line>
        </svg>
    </button>

    <!-- Uzaklaştır -->
    <button type="button"
            onclick="window.atolyeZoomla(0.8)"
            title="Uzaklaştır">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="8.5"></circle>
            <line x1="8" y1="12" x2="16" y2="12"></line>
        </svg>
    </button>
</div>
                    </div>
                </div>

                <div class="atolye-sag-panel" style="width: 260px;">
                    <div class="arac-baslik">Araçlar (Tools)</div>
                    <div class="arac-grid uclu">
                        <button class="arac-btn aktif" id="btnAracSecim" onclick="aracSec('secim')" title="Seç (V)"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none">
                                <path d="M4 4l7.5 16 2.5-7.5L21.5 10 4 4z" />
                            </svg><span>Seç</span></button>
                        <button class="arac-btn" id="btnAracNokta" onclick="aracSec('nokta')" title="Düğüm (A)"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" fill="none">
                                <path d="M12 4v4m0 8v4M4 12h4m8 0h4" />
                                <rect x="9" y="9" width="6" height="6" />
                            </svg><span>Nokta</span></button>
                     <button class="arac-btn" id="btnAracEl" onclick="aracSec('el')" title="Kaydır (Space)">
    <svg viewBox="0 0 24 24" width="22" height="22"
         stroke="currentColor" stroke-width="1.5"
         stroke-linecap="round" stroke-linejoin="round" fill="none">
        <path d="M8.5 11V5.5a1.5 1.5 0 0 1 3 0V10" />
        <path d="M11.5 10V4.5a1.5 1.5 0 0 1 3 0V10" />
        <path d="M14.5 10V6.5a1.5 1.5 0 0 1 3 0V14" />
        <path d="M8.5 10V8.5a1.5 1.5 0 0 0-3 0V15a6.5 6.5 0 0 0 13 0v-3.5a1.5 1.5 0 0 0-3 0V14" />
    </svg>
    <span>Kaydır</span>
</button>
                        <button class="arac-btn" id="btnAracKalem" onclick="aracSec('kalem')" title="Pen Tool"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <path d="M12 19l7-7 3 3-7 7-3-3z" />
                                <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z" />
                                <path d="M2 2l7.586 7.586" />
                                <circle cx="11" cy="11" r="2" />
                            </svg><span>Kalem</span></button>
                        <button class="arac-btn" id="btnAracGradyan" onclick="aracSec('gradyan')" title="Degrade Yönü Aracı">
                            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" fill="none">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <line x1="3" y1="3" x2="21" y2="21" />
                                <circle cx="7" cy="7" r="2" fill="currentColor" />
                                <circle cx="17" cy="17" r="2" fill="currentColor" />
                            </svg><span>Degrade</span>
                        </button>
                        <button class="arac-btn" id="btnAracFirca" onclick="aracSec('firca')" title="Fırça"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <path d="M10 18h4v-4H10v4z" />
                                <path d="M10 14c-1.5-2.5-4-5-4-8a6 6 0 0 1 12 0c0 3-2.5 5.5-4 8" />
                            </svg><span>Fırça</span></button>
                        <button class="arac-btn" id="btnAracSilgi" onclick="aracSec('silgi')" title="Silgi"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <path d="M20 20H7L3 16C2 15 2 13 3 12L13 2C14 1 16 1 17 2L22 7C23 8 23 10 22 11L14 19Z" />
                                <path d="M8 7L17 16" />
                            </svg><span>Silgi</span></button>
                        <button class="arac-btn" id="btnAracDuz_cizgi" onclick="aracSec('duz_cizgi')" title="Çizgi"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none">
                                <line x1="4" y1="20" x2="20" y2="4" />
                            </svg><span>Çizgi</span></button>
                        <button class="arac-btn" id="btnAracMetin" onclick="aracSec('metin')" title="Yazı"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none">
                                <path d="M4 7V4h16v3M12 4v16M9 20h6" />
                            </svg><span>Yazı</span></button>
                        <button class="arac-btn" id="btnAracKutu" onclick="aracSec('kutu')" title="Kutu"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" fill="none">
                                <rect x="4" y="5" width="16" height="14" rx="2" />
                            </svg><span>Kutu</span></button>
                        <button class="arac-btn" id="btnAracDaire" onclick="aracSec('daire')" title="Daire"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" fill="none">
                                <circle cx="12" cy="12" r="9" />
                            </svg><span>Daire</span></button>
                        <button class="arac-btn" id="btnAracCokgen" onclick="aracSec('cokgen')" title="Çokgen"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none">
                                <polygon points="12 2 21 8 18 19 6 19 3 8" />
                            </svg><span>Çokgen</span></button>
                        <button class="arac-btn" id="btnAracOlcum" onclick="aracSec('olcum')" title="Ölçüm"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none">
                                <path d="M21 8L8 21M3 16L16 3" />
                                <path d="M12 7l3 3M8 11l3 3M4 15l3 3" />
                            </svg><span>Ölçüm</span></button>
                    </div>

                    <div id="grupPenModlari" style="display: none; flex-direction: column; gap: 5px; margin-top: 15px; background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <label style="font-size: 12px; font-weight: bold; color: #475569;">✒️ Kalem Çizim Modu:</label>
                        <div class="arac-btn-grup" style="display: flex; gap: 5px;">
                            <button type="button" class="arac-btn aktif" id="btnPenNormal" onclick="window.penModuDegistir('normal')" title="Normal Çizim (Kısayol Yok)" style="flex:1; padding:5px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 19l7-7 3 3-7 7-3-3z"></path>
                                    <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"></path>
                                    <path d="M2 2l7.586 7.586"></path>
                                </svg>
                            </button>
                            <button type="button" class="arac-btn" id="btnPenCorner" onclick="window.penModuDegistir('corner')" title="Sivri Köşe Bükme (Kısayol: Ctrl)" style="flex:1; padding:5px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="4 20 12 4 20 20"></polyline>
                                    <circle cx="12" cy="4" r="2" fill="currentColor"></circle>
                                </svg>
                            </button>
                            <button type="button" class="arac-btn" id="btnPenSmooth" onclick="window.penModuDegistir('smooth')" title="Yumuşak Eğri (Kısayol: Shift)" style="flex:1; padding:5px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 20 C 10 20, 14 4, 20 4"></path>
                                    <circle cx="12" cy="12" r="2" fill="currentColor"></circle>
                                </svg>
                            </button>
                            <button type="button" class="arac-btn" id="btnPenCurve" onclick="window.penModuDegistir('curve')" title="Şişkin/Oval Kavis (Kısayol: Alt)" style="flex:1; padding:5px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 20 Q 12 -4 20 20"></path>
                                    <circle cx="12" cy="8" r="2" fill="currentColor"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="arac-baslik" style="margin-top: 15px;">Boolean İşlemleri</div>
                    <div class="arac-grid uclu">
                        <button class="arac-btn" onclick="atolyeBoolean('unite')" title="Birleştir"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="1.5" fill="none">
                                <rect x="4" y="4" width="10" height="10" rx="2" />
                                <rect x="10" y="10" width="10" height="10" rx="2" />
                            </svg></button>
                        <button class="arac-btn" onclick="atolyeBoolean('subtract')" title="Üsttekini Çıkar"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="1.5" fill="none">
                                <rect x="4" y="4" width="10" height="10" rx="2" />
                                <circle cx="15" cy="15" r="5" />
                            </svg></button>
                        <button class="arac-btn" onclick="atolyeBoolean('intersect')" title="Kesişim"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="1.5" fill="none">
                                <circle cx="9" cy="12" r="6" />
                                <circle cx="15" cy="12" r="6" />
                            </svg></button>
                    </div>

                    <div class="arac-baslik" style="margin-top: 15px;">Hizalama</div>
                    <div class="arac-grid uclu">
                        <button class="arac-btn" onclick="atolyeHizala('left')">⇤<br><span>Sol</span></button>
                        <button class="arac-btn" onclick="atolyeHizala('center')">⇥⇤<br><span>Orta</span></button>
                        <button class="arac-btn" onclick="atolyeHizala('right')">⇥<br><span>Sağ</span></button>
                        <button class="arac-btn" onclick="atolyeHizala('top')">⇡<br><span>Üst</span></button>
                        <button class="arac-btn" onclick="atolyeHizala('middle')">⇡⇣<br><span>Merkez</span></button>
                        <button class="arac-btn" onclick="atolyeHizala('bottom')">⇣<br><span>Alt</span></button>
                    </div>

                    <div class="arac-baslik" style="margin-top: 15px;">Görünüm & Ayar</div>
                    <div class="ayar-kutu" style="margin-bottom: 10px; text-align:center;">
                        <label style="font-size: 11px; cursor: pointer; font-weight: bold; color:#3b82f6;"><input type="checkbox" id="cbSnapAktif"> 🧲 Izgaraya Yapış (Snap)</label>
                    </div>
                    <div class="ayar-kutu" style="display: flex; gap: 5px; margin-bottom: 5px;">
                        <button class="arac-btn" onclick="atolyeZoomla(1.2)" title="Yakınlaştır" style="flex:1; padding: 5px;"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                <line x1="11" y1="8" x2="11" y2="14" />
                                <line x1="8" y1="11" x2="14" y2="11" />
                            </svg></button>
                        <button class="arac-btn" onclick="atolyeZoomla(0.8)" title="Uzaklaştır" style="flex:1; padding: 5px;"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                <line x1="8" y1="11" x2="14" y2="11" />
                            </svg></button>
                        <button class="arac-btn" onclick="atolyeEkranaSigdir()" title="Ekrana Sığdır" style="flex: 1; padding: 5px;"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <path d="M8 8h8v8H8z" />
                            </svg></button>
                    </div>
                    <div class="arac-grid">
                        <button class="arac-btn" onclick="document.getElementById('kilavuzYukleyici').click()" style="padding: 8px; font-size: 11px;">📄 Resim/SVG Ekle</button>
                        <button class="arac-btn" onclick="atolyeSvgIndir()" style="padding: 8px; font-size: 11px; background: #fffbeb; border-color: #f59e0b; color: #d97706;">📦 SVG İndir</button>
                    </div>
                    <input type="file" id="kilavuzYukleyici" accept="image/*,.svg" style="display: none;" onchange="kilavuzResimYukle(event)">
                </div>

            </div>


        </div>
    </div>

    <script>
        const SUNUCUDAN_GELEN_GOREV = <?= $gorevPaketi ?>;
        // [2026-08] Hakem ve ipucu motorunun erişebilmesi için global kopya (#17)
        window.v2GorevPaketi = SUNUCUDAN_GELEN_GOREV;
        window.v2GorevBasligi = <?= json_encode($baslik, JSON_UNESCAPED_UNICODE) ?>;

        /* ====================================================================
         * GÖREV METNİNİ EKRANA ÇİZ
         * [2026-08] Bu kod HİÇ YAZILMAMIŞTI. #hikayeAlani kutusunda
         * "Yükleniyor..." yazısı duruyor ve hiçbir zaman değişmiyordu —
         * bu yüzden öğrenci sayfasında görev metni hiç görünmüyordu.
         *
         * Admin tarafı hikâyeyi Editor.js ile yazıyor ve şu yapıda saklıyor:
         *   { blocks: [ {type:'paragraph', data:{text:'...'}}, ... ] }
         * Burada o blokları güvenli HTML'e çeviriyoruz.
         * ================================================================== */
        (function goreviCiz() {
            const kutu = document.getElementById('hikayeAlani');
            if (!kutu) return;

            /** Metin içindeki [kukla:ripley] gibi kısa kodları rozete çevirir. */
            function kisaKodlariSusle(html) {
                return html.replace(/\[([a-zA-ZğüşıöçĞÜŞİÖÇ_]+):([^\]]+)\]/g, function (t, tur, deger) {
                    const ikonlar = { kukla: '🎭', dongu: '🔄', blok: '🧩', kod: '💻' };
                    const ikon = ikonlar[tur.toLowerCase()] || '🏷️';
                    return '<span style="display:inline-block; background:#eef2ff; color:#4338ca; '
                         + 'border:1px solid #c7d2fe; padding:2px 9px; border-radius:20px; '
                         + 'font-size:13px; font-weight:600; margin:0 2px;">'
                         + ikon + ' ' + deger.replace(/_/g, ' ') + '</span>';
                });
            }

            const paket = SUNUCUDAN_GELEN_GOREV || {};
            const hikaye = paket.hikaye_metni || {};
            const bloklar = Array.isArray(hikaye.blocks) ? hikaye.blocks : [];

            // Başlık her zaman en üstte
            let html = '<h2 style="margin:0 0 14px 0; color:#1e293b; font-size:21px;">📝 '
                     + (window.v2GorevBasligi || 'Görev') + '</h2>';

            if (bloklar.length === 0) {
                html += '<p style="color:#94a3b8; line-height:1.7;">'
                      + 'Bu görevin metni henüz yazılmamış.<br>'
                      + '<span style="font-size:13px;">Öğretmenine haber verebilirsin.</span></p>';
                kutu.innerHTML = html;
                return;
            }

            bloklar.forEach(function (blok) {
                const d = blok.data || {};
                switch (blok.type) {
                    case 'header': {
                        const seviye = Math.min(6, Math.max(2, parseInt(d.level, 10) || 3));
                        html += '<h' + seviye + ' style="color:#1e293b; margin:18px 0 8px 0;">'
                              + kisaKodlariSusle(d.text || '') + '</h' + seviye + '>';
                        break;
                    }
                    case 'list': {
                        const etiket = (d.style === 'ordered') ? 'ol' : 'ul';
                        html += '<' + etiket + ' style="color:#334155; line-height:1.9; padding-left:22px; margin:10px 0;">';
                        (d.items || []).forEach(function (oge) {
                            // Editor.js sürümüne göre öğe düz metin veya {content:...} olabilir
                            const icerik = (oge && typeof oge === 'object') ? (oge.content || '') : oge;
                            html += '<li>' + kisaKodlariSusle(icerik) + '</li>';
                        });
                        html += '</' + etiket + '>';
                        break;
                    }
                    case 'paragraph':
                    default: {
                        const metin = d.text || '';
                        if (metin.trim() !== '') {
                            html += '<p style="color:#334155; line-height:1.8; margin:10px 0;">'
                                  + kisaKodlariSusle(metin) + '</p>';
                        }
                        break;
                    }
                }
            });

            kutu.innerHTML = html;
        })();

        /* [2026-08] DÜELLO MOTORU AYARI
           Bu sayfa ogrenci_paneli.php içinde bir iframe olarak açılıyor ve
           skorboardu oradan kopyalıyor. Kopyalanan "⚔️ Düello" butonları
           meydanOku() fonksiyonunu arıyordu ama fonksiyon sadece ana sayfada
           tanımlıydı -> "meydanOku is not defined".
           Çözüm: motoru buraya da yüklüyoruz.
             TABAN  : API yolları iki klasör yukarıda
             DINLE  : false -> gelen istekleri ANA SAYFA dinlesin, çift modal çıkmasın */
        window.MBLOCK_TABAN = '../../';
        window.MBLOCK_DUELLO_DINLE = false;
    </script>
    <script src="<?= varlik('js/duello_motoru.js', '../../') ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/paper.js/0.12.17/paper-full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://unpkg.com/blockly/blockly_compressed.js"></script>
    <script src="https://unpkg.com/blockly/blocks_compressed.js"></script>
    <script src="https://unpkg.com/blockly/javascript_compressed.js"></script>
    <script src="https://unpkg.com/blockly/msg/tr.js"></script>

    <script src="<?= varlik('v2_gorev_sistemi/assets/js/admin/mblock_bloklari.js', '../../') ?>"></script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/kod_tercumani.js', '../../') ?>"></script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/oyun_sahnesi.js', '../../') ?>"></script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/hakem_motoru.js', '../../') ?>"></script>

    <!-- [2026-08] İlerleme çubuğu + konfetili kutlama ekranı -->
    <script>
        /* Sunucudan gelen ilk harita — sayfa açılırken beklemeden çizilsin */
        window.V2_ILERLEME     = <?= json_encode($ilerlemeVerisi, JSON_UNESCAPED_UNICODE) ?>;
        window.V2_CEZA         = <?= json_encode($cezaDurumu, JSON_UNESCAPED_UNICODE) ?>;
        window.V2_BILDIRIM     = <?= json_encode($cezaBildirimi, JSON_UNESCAPED_UNICODE) ?>;
        window.V2_ILERLEME_API = '<?= varlik('v2_gorev_sistemi/api/ilerleme_haritasi.php', '../../') ?>';
        window.V2_OYNATICI_URL = 'oynatici.php';

        /* [2026-08] Üst şerit eklendiğinde çalışma alanının yüksekliği değişti.
           Blockly kendi tuvalini pencere boyutuna göre kurduğu için bir kez
           yeniden ölçmesi gerekiyor; yoksa kod alanı taşar ya da eksik kalır. */
        window.addEventListener('load', function () {
            setTimeout(function () {
                try {
                    var w = window.ogrenciWorkspace
                         || (typeof Blockly !== 'undefined' ? Blockly.mainWorkspace : null);
                    if (w && typeof Blockly !== 'undefined' && Blockly.svgResize) {
                        Blockly.svgResize(w);
                    }
                    window.dispatchEvent(new Event('resize'));
                } catch (e) { /* Blockly henüz hazır değilse sorun değil */ }
            }, 250);
        });
    </script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/kutlama.js', '../../') ?>"></script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/ilerleme_cubugu.js', '../../') ?>"></script>

    <script>
        function sahneModuDegistir(mod) {
            let body = document.body;
            let btnKucuk = document.getElementById('btnKucukSahne');
            let btnBuyuk = document.getElementById('btnBuyukSahne');
            if (mod === 'buyuk') {
                body.classList.add('genis-mod');
                btnBuyuk.classList.add('aktif');
                btnKucuk.classList.remove('aktif');
            } else {
                body.classList.remove('genis-mod');
                btnKucuk.classList.add('aktif');
                btnBuyuk.classList.remove('aktif');
            }
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 300);
        }

        function tamEkranYap() {
            let s = document.querySelector('.oyun-sahnesi');
            if (s.requestFullscreen) s.requestFullscreen();
            else if (s.webkitRequestFullscreen) s.webkitRequestFullscreen();
        }

        function tamEkranCik() {
            if (document.exitFullscreen) document.exitFullscreen();
            else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
        }
        document.addEventListener('fullscreenchange', tamEkranDurumunuKontrolEt);

        function tamEkranDurumunuKontrolEt() {
            let s = document.querySelector('.oyun-sahnesi');
            if (document.fullscreenElement) s.classList.add('tam-ekran');
            else s.classList.remove('tam-ekran');
        }
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const btn = document.getElementById("btnKalinlikPopover");
            const pop = document.getElementById("popoverKalinlik");
            const grup = document.getElementById("grupKalinlik");

            if (!btn || !pop || !grup) return;

            btn.addEventListener("click", function(e) {
                e.stopPropagation();
                pop.classList.toggle("aktif");
            });

            document.addEventListener("click", function(e) {
                if (!grup.contains(e.target)) {
                    pop.classList.remove("aktif");
                }
            });
        });
    </script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/coklu_kukla_motoru.js', '../../') ?>"></script>

    <!-- ====================================================================
         BLOCKLY KOD ALANI KURULUMU
         [2026-08] Bilinen Sorunlar #17:
         Sayfada <div id="blocklyOgrenci"> vardı ama Blockly HİÇ enjekte
         edilmiyordu. Bu yüzden window.ogrenciWorkspace hiçbir zaman
         oluşmuyor, kod alanı boş görünüyor ve hakem çalışamıyordu.
         ==================================================================== -->
    <script>
    /* ====================================================================
     * [2026-08] BURADA İKİNCİ BİR BLOCKLY ENJEKSİYONU VARDI — KALDIRILDI.
     *
     * NE OLMUŞTU:
     * Öğrenci sayfasında araç kutusu boş göründüğü için "Blockly hiç
     * enjekte edilmiyor" sanıp buraya ikinci bir Blockly.inject eklemiştim.
     * Oysa oyun_sahnesi.js zaten her şeyi yapıyordu:
     *     satır 357 -> araç kutusunu kurar (toolboxOlustur, shadow'lu)
     *     satır 410 -> Blockly'yi #blocklyOgrenci içine enjekte eder
     *     satır 428 -> canlı derleyiciyi bağlar
     * Araç kutusunun boş görünmesinin gerçek sebebi bu değildi.
     *
     * SONUÇ: aynı div'e İKİ workspace enjekte oluyordu. Öğrenci üsttekine
     * blok bırakıyor, window.ogrenciWorkspace ise diğerini gösteriyordu.
     * Bu yüzden ekranda bloklar görünüyor ama hakem "hiç blok yok" diyordu.
     *
     * Artık tek enjeksiyon var: oyun_sahnesi.js.
     * Burada sadece pencere yeniden boyutlanınca çizimi tazeliyoruz.
     * ================================================================== */
    window.addEventListener('load', function () {
        function ciziminiTazele() {
            if (window.ogrenciWorkspace && typeof Blockly !== 'undefined') {
                Blockly.svgResize(window.ogrenciWorkspace);
            }
        }
        window.addEventListener('resize', ciziminiTazele);
        setTimeout(ciziminiTazele, 400);
    });
    </script>

    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/kostum_atolyesi/renk_ve_gradyan.js', '../../') ?>"></script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/kostum_atolyesi/transform_ve_secim.js', '../../') ?>"></script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/kostum_atolyesi/araclar_ve_yardimcilar.js', '../../') ?>"></script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/kostum_atolyesi.js', '../../') ?>"></script>

<!-- YENİ ADMİN İŞLEMLERİ PENCERESİ (IFRAME MODAL) -->
    <div class="kukla-modal-overlay" id="adminIslemModal" style="display: none; z-index: 99999;">
        <div class="kukla-modal" style="width: 90%; max-width: 1100px; height: 85vh; padding: 0; display: flex; flex-direction: column; overflow: hidden; border-radius: 12px;">
            
            <div class="modal-baslik-alani" style="padding: 15px 20px; background: #fff; border-bottom: 2px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; color: #1e293b; font-size: 20px;">⚙️ Sistem Yönetimi</h2>
                <button class="btn-kapat" onclick="adminPenceresiKapat()" style="background: #ef4444; color: white; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-weight: bold; font-size: 16px; display: flex; align-items: center; justify-content: center;">✕</button>
            </div>
            
            <iframe id="adminIframe" src="" style="width: 100%; height: 100%; border: none; flex: 1; background: #f8fafc;"></iframe>
            
        </div>
    </div>

    <script>
        // İlgili sayfayı iframe içine yükler ve modalı gösterir
        function adminPenceresiAc(url) {
            // 1. Önce açık olan "Kütüphaneden Kukla Seç" penceresini gizle
            document.getElementById('kuklaSeciciModal').style.display = 'none';
            
            // 2. Sonra yeni Admin penceresini aç
            document.getElementById('adminIframe').src = url;
            document.getElementById('adminIslemModal').style.display = 'flex';
        }

        // Modalı kapatır, iframe içini temizler ve sayfayı yeniler (silinenleri/eklenenleri anında görmek için)
        function adminPenceresiKapat() {
            document.getElementById('adminIslemModal').style.display = 'none';
            document.getElementById('adminIframe').src = '';
            window.location.reload(); // Atölyeyi yenile
        }
    </script>
</body>

</html>