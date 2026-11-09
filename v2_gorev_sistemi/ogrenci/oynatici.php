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

/* Hâlâ yoksa: sıradaki çözülmemiş görev — ÖNCE CEZALAR, sonra normal akış */
if (!$gorev && $ogrenciId > 0) {
    $sonraki = sonrakiGoreviBul($pdo, $ogrenciId);

    /* [2026-08 HATA DÜZELTMESİ] Öğrenci HER ŞEYİ bitirdiyse sonrakiGoreviBul
       null döner. Eskiden burada "yayındaki ilk görev"e düşülüyordu; yani
       bütün görevleri tamamlayan öğrenci 1. bölümün 1. görevine geri atılıyor
       ve orada takılı kalıyordu. Artık ulaştığı SON görevde bırakıyoruz. */
    if (!$sonraki) {
        $sonraki = sonErisilenGorev($pdo, $ogrenciId);
    }

    if ($sonraki) {
        $st = $pdo->prepare("SELECT * FROM v2_gorevler WHERE id = ?");
        $st->execute([$sonraki]);
        $gorev = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

/* Hâlâ yoksa: yayındaki ilk görev.
   Buraya SADECE öğrenci oturumu olmayan durumlarda düşülür
   (Süper Admin önizlemesi ya da hiç görev çözmemiş yeni öğrenci). */
if (!$gorev) {
    $gorev = $pdo->query(
        "SELECT * FROM v2_gorevler WHERE yayinda = 1 ORDER BY bolum_id ASC, sira_no ASC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC) ?: null;
}

/* [2026-11] CEVAP ANAHTARI TARAYICIYA GÖNDERİLMEZ.
   Eskiden icerik_json olduğu gibi sayfaya basılıyordu; F12'yi açan öğrenci
   blueprint.ana_cozum'dan cevabı, ipucu'ndan da bilgiyi CEZASIZ okuyabiliyordu.
   Hakem sunucuda (v2_hakem.php), bilgi kartı da v2_ipucu.php'den geliyor;
   tarayıcının bu iki alana ihtiyacı yok. */
$gorevIcerik = $gorev ? (json_decode((string) $gorev['icerik_json'], true) ?: []) : ['hikaye_metni' => new stdClass()];
unset($gorevIcerik['blueprint'], $gorevIcerik['ipucu']);
$gorevPaketi = json_encode($gorevIcerik, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

$baslik      = $gorev ? $gorev['gorev_adi'] : 'Görev Bulunamadı';
$aktifGorevId = $gorev ? (int) $gorev['id'] : 0;
$gorevPuani   = $gorev ? (int) $gorev['puan'] : 0;

/* ============================================================================
 * [2026-08] BİLGİ ALINDIYSA DÜĞMEDE YARIM PUAN YAZSIN
 *
 * Öğrenci "💡 Bilgi Al"ı kabul ettiyse ödül yarıya iner. Düğmede hâlâ
 * "20 Puan" yazsaydı öğrenci 10 puan alınca sistemi hatalı sanardı.
 * Kesilen bir ceza görünmüyorsa ceza değil, kafa karışıklığıdır.
 *
 * Yuvarlama v2_ipucu.php ve v2_gorev_tamamla.php ile AYNI olmalı:
 * en yakına (round), en az 1.
 *
 * ⚠️ try/catch ZORUNLU: migration çalıştırılmamış bir kurulumda
 * `v2_ipucu_alindi` tablosu yoktur ve bu sorgu ÖĞRENCİ SAYFASINI komple
 * çökertirdi. Süs bilgi, asıl işi deviremez — daha önce kilit sayacında
 * aynı hata yaşandı (bkz. docs/BILINEN_SORUNLAR.md). Tablo yoksa
 * "bilgi alınmamış" varsayıyoruz: şüpheden öğrenci yararlanır. */
$ipucuAlinmis = false;
if ($ogrenciId > 0 && $aktifGorevId > 0) {
    try {
        $st = $pdo->prepare("SELECT 1 FROM v2_ipucu_alindi WHERE ogrenci_id = ? AND v2_gorev_id = ? LIMIT 1");
        $st->execute([$ogrenciId, $aktifGorevId]);
        $ipucuAlinmis = (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        error_log('[mBlock] v2_ipucu_alindi okunamadı (migration çalıştırıldı mı?): ' . $e->getMessage());
    }
}
if ($ipucuAlinmis && $gorevPuani > 0) {
    $gorevPuani = max(1, (int) round($gorevPuani / 2));
}

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
<?php $KILIT_KOK = '../../'; require __DIR__ . '/../../bilesenler/ogrenci_kilit_perdesi.php'; ?>

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
        <!-- Bölüm adına tıklayınca aşağı açılan TAM HARİTA gelir -->
        <button type="button" class="ic-sol" id="icHaritaTetik"
                aria-haspopup="true" aria-expanded="false"
                title="Bölüm haritasını aç">
            <span class="ic-bolum" id="icBolumAdi"><?= temizle($aktifBolumAdi ?: 'Bölüm') ?></span>
            <span class="ic-ok">▾</span>
            <span class="ic-ayrac">›</span>
            <span class="ic-gorev" id="icGorevAdi"><?= temizle($baslik) ?></span>
        </button>

        <div class="ic-orta" id="icGovde"><!-- görev daireleri: JS doldurur --></div>

        <div class="ic-sag">
            <div class="ic-bar"><span id="icBarDolgu" style="width:<?= (int) $ilerlemeVerisi['genel_yuzde'] ?>%"></span></div>
            <small id="icOran"><?= (int) $ilerlemeVerisi['cozulen_gorev'] ?>/<?= (int) $ilerlemeVerisi['toplam_gorev'] ?></small>
        </div>

        <!-- ================================================================
             AÇILIR BÖLÜM HARİTASI
             Öğrenci tüm bölümleri, nerede olduğunu ve hangi görevleri
             bitirdiğini burada görür. Fare dışına çıkınca ya da dışarı
             tıklayınca kapanır, şerit tekrar sadece aktif bölümü gösterir.
             ================================================================ -->
        <div class="ic-harita" id="icHarita" role="menu">
            <div class="ic-harita-baslik">
                <span>🗺️ Bölüm Haritan</span>
                <span class="ipucu">kaydırarak gez</span>
            </div>
            <!-- [2026-08] Gövde 3 bölüm yüksekliğinde; açılınca bulunduğun
                 bölüm en üste gelir, yukarı/aşağı kaydırarak diğerlerine
                 ulaşırsın. Yükseklik JS ile ölçülür. -->
            <div class="ic-harita-govde" id="icHaritaGovde"><!-- JS doldurur --></div>
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

            <!-- [2026-11] Farenin sahnedeki x/y'si: çocuk "x: 100 y: 50'ye git" derken
                 hedefi kolayca bulsun -->
            <span class="fare-koordinat" id="fareKoordinat" title="Farenin sahnedeki konumu">🖱️ x: 0 &nbsp; y: 0</span>
            <div class="sahne-kontrolleri" style="margin-top: 0;">
                <button type="button" class="btn-basa-don" onclick="window.MBM && MBM.basaDon()" title="Kuklaları görevin başındaki yerine koy">↺ Başa Dön</button>
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
            <div class="panel-grup"><label title="Sola doğru eksi, sağa doğru artı (-240 … 240)">X</label><input type="number" id="panelX" value="0" oninput="arayuzdenAktifKuklayiGuncelle()"></div>
            <div class="panel-grup"><label title="Aşağı doğru eksi, yukarı doğru artı (-180 … 180)">Y</label><input type="number" id="panelY" value="0" oninput="arayuzdenAktifKuklayiGuncelle()"></div>
            <div class="panel-grup"><label>Boyut</label><input type="number" id="panelBoyut" value="100" oninput="arayuzdenAktifKuklayiGuncelle()"></div>
            <div class="panel-grup"><label title="90 = sağa, -90 = sola, 0 = yukarı, 180 = aşağı bakar">Yön <small>(90 = sağ)</small></label><input type="number" id="panelYon" value="0" oninput="arayuzdenAktifKuklayiGuncelle()"></div>
            <div class="panel-grup"><label>Göster</label>
                <div class="panel-butonlar">
                    <button id="btnGoster" class="aktif" onclick="arayuzdenGosterGizle(true)">👁️</button>
                    <button id="btnGizle" onclick="arayuzdenGosterGizle(false)">🚫</button>
                </div>
            </div>
        </div>
        <div class="kukla-listesi-kapsayici" id="kuklaListesiKapsayici"></div>
        <div id="sagTikMenu">
            <div class="sag-tik-oge" onclick="kuklaAdlandirMenu()">✏️ Yeniden Adlandır</div>
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
            <!-- [2026-08] Kod alanını tam ekran yapıp geri döndürme -->
            <button id="btnKodTamEkran" onclick="kodAlaniTamEkran()" title="Kod alanını tam ekran yap (ESC ile çık)"
                    style="flex: 0 0 auto; padding: 14px 18px; background: #1e293b; color: #94a3b8; border: none; border-left: 1px solid #334155; font-weight: 800; font-size: 14px; cursor: pointer; transition: 0.2s;">
                ⛶ Tam Ekran
            </button>
        </div>

        <div id="kodAlaniSarmal" style="flex: 1 1 auto; position: relative; border: 1px solid #334155; border-radius: 0 0 10px 10px; background: #ffffff; min-height: 400px; overflow: hidden;">
            
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

    <style>
    /* ====================================================================
       [2026-08] KOD ALANI TAM EKRAN
       Öğrenci uzun kod dizilimlerini kurarken dar kutuya sıkışmasın diye
       kod alanı ekranın tamamına yayılabilir. Aynı düğme geri döndürür.
       ================================================================== */
    #kodAlaniSarmal.tam-ekran{
        position: fixed !important; inset: 0 !important;
        z-index: 9400; border-radius: 0 !important;
        min-height: 0 !important; margin: 0 !important;
    }
    body.kod-tam-ekran #btnKodTamEkran{
        position: fixed; top: 12px; right: 16px; z-index: 9450;
        background: #dc2626 !important; color: #fff !important;
        border-radius: 9px; border: none !important; padding: 10px 18px;
        box-shadow: 0 6px 18px rgba(0,0,0,.35);
    }
    body.kod-tam-ekran{ overflow: hidden; }
    </style>

     <script>
        /* Kod alanını tam ekran aç/kapat.
           Blockly tuvalini kendi ölçüsüne göre çizdiği için her iki durumda da
           svgResize çağırmak ZORUNLU; yoksa bloklar kayık görünür. */
        function kodAlaniTamEkran() {
            const sarmal = document.getElementById('kodAlaniSarmal');
            const dugme  = document.getElementById('btnKodTamEkran');
            if (!sarmal) return;

            const acildi = sarmal.classList.toggle('tam-ekran');
            document.body.classList.toggle('kod-tam-ekran', acildi);
            if (dugme) dugme.textContent = acildi ? '✕ Küçült' : '⛶ Tam Ekran';

            // Tam ekrana geçince kod sekmesi görünsün
            if (acildi) sekmeDegistir('kod');

            setTimeout(function () {
                try {
                    const ws = window.ogrenciWorkspace
                            || (typeof Blockly !== 'undefined' ? Blockly.mainWorkspace : null);
                    if (ws && typeof Blockly !== 'undefined' && Blockly.svgResize) {
                        Blockly.svgResize(ws);
                    }
                } catch (e) { /* Blockly hazır değilse sorun değil */ }
                window.dispatchEvent(new Event('resize'));
            }, 60);
        }

        /* ESC ile tam ekrandan çık */
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            const sarmal = document.getElementById('kodAlaniSarmal');
            if (sarmal && sarmal.classList.contains('tam-ekran')) kodAlaniTamEkran();
        });

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

                    <!-- ================================================================
                         [2026-08] ADMIN KISAYOLLARI KALDIRILDI.
                         Burada "Kukla Sil" ve "Yeni Kukla Tanımla" düğmeleri vardı;
                         ikisi de SÜPER ADMİN sayfalarını iframe içinde açıyordu.
                         Yani her öğrenci kütüphaneden kukla silebiliyor, sunucuya
                         kalıcı kayıt yazabiliyordu — tüm okulları etkileyen bir yetki.

                         Yerine: öğrenci BOŞ KUKLA oluşturabilir. Bu kukla sadece
                         kendi tarayıcısında yaşar, sunucuya yazılmaz, çıkışta silinir.
                         Kütüphaneye kalıcı kukla eklemek artık sadece Süper Admin'in
                         işi (admin/kukla_kutuphanesi/index.php).
                         ================================================================ -->
                    <button onclick="bosKuklaOlustur()" title="Kendi kuklanı çiz — sadece sende kalır"
                            style="background-color: #10b981; color: white; padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); font-size: 14px;">
                        🎨 Kendi Kuklamı Çiz
                    </button>

                    <button class="btn-kapat" onclick="kuklaPenceresiniKapat()">✕</button>
                </div>
            </div>
            <div style="background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; padding:10px 13px; border-radius:8px; font-size:12.5px; line-height:1.55; margin-bottom:14px;">
                ℹ️ Buradan seçtiğin kuklalar <b>sadece senin ekranında</b> durur. İstediğin kadar ekle,
                adını değiştir, çiz, sil — kütüphaneyi bozmazsın. Çıkış yapınca sahnendeki kuklalar sıfırlanır.
            </div>

            <div class="modal-filtreler" id="modalFiltreAlani"></div>
            <div class="modal-icerik" id="modalKuklaIzgarasi">
                <div style="grid-column: 1 / -1; text-align: center; color: #94a3b8; padding: 40px;">Kuklalar Yükleniyor...</div>
            </div>
        </div>
    </div>

    <!-- [2026-08] Kostüm Atölyesi arayüzü ortak parçaya taşındı;
         aynı editörü Süper Admin de kukla kütüphanesinde kullanıyor. -->
    <?php require __DIR__ . '/../bilesenler/kostum_atolyesi_modal.php'; ?>

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
    <script src="<?= varlik('js/onay_kutusu.js', '../../') ?>"></script>
<script src="<?= varlik('js/duello_motoru.js', '../../') ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/paper.js/0.12.17/paper-full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://unpkg.com/blockly@13.4.0/blockly_compressed.js"></script>
    <script src="https://unpkg.com/blockly@13.4.0/blocks_compressed.js"></script>
    <script src="https://unpkg.com/blockly@13.4.0/javascript_compressed.js"></script>
    <script src="https://unpkg.com/blockly@13.4.0/msg/tr.js"></script>

    <script src="<?= varlik('v2_gorev_sistemi/assets/js/admin/mblock_bloklari.js', '../../') ?>"></script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/kod_tercumani.js', '../../') ?>"></script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/oyun_sahnesi.js', '../../') ?>"></script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/hakem_motoru.js', '../../') ?>"></script>
    <!-- [2026-11] mBlock sahne motoru: çoklu kukla, kalem, ses, mesajlar.
         hakem_motoru.js'ten SONRA gelmeli (yeşil bayrak / durdur köprüleri burada). -->
    <script>window.MBM_KOK = '../../';</script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/sahne_motoru.js', '../../') ?>"></script>
    <!-- [2026-08] "💡 Bilgi Al" penceresi. hakem_motoru.js'ten SONRA gelmeli:
         window.ipucuGoster'ın tek sahibi bu dosya. -->
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/bilgi_al.js', '../../') ?>"></script>

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

        /* ================================================================
           [2026-08] SOL PANEL UZAYINCA BLOCKLY DE UZASIN

           Sayfa artık aşağı büyüyor. Sol panelin boyu içerikle değişiyor
           (görev metni geldi, kukla eklendi, bilgi kartı açıldı...) ve
           iki sütun aynı boyda olduğu için sağdaki kod alanı da uzuyor.

           Ama Blockly tuvalini KENDİ ölçüsüne göre bir kez çiziyor: kutu
           büyüdüğünde SVG eski boyunda kalır, altta boş bir şerit oluşur ve
           blok sürüklerken imleçle blok arasında kayma olur. Bu yüzden
           kutunun boyu her değiştiğinde svgResize çağırmak ZORUNLU.

           `resize` olayı yetmez — o sadece PENCERE boyutu değişince tetiklenir,
           panel içerikle uzadığında değil.
           ================================================================ */
        if (typeof ResizeObserver === 'undefined') return;

        var kutu = document.getElementById('kodAlaniSarmal');
        if (!kutu) return;

        var bekleyen = null;
        new ResizeObserver(function () {
            /* Gözlemci saniyede onlarca kez tetiklenebilir; svgResize pahalı
               bir işlem. Son değişiklikten 80 ms sonra bir kez çalışsın. */
            clearTimeout(bekleyen);
            bekleyen = setTimeout(ciziminiTazele, 80);
        }).observe(kutu);
    });
    </script>

    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/kostum_atolyesi/renk_ve_gradyan.js', '../../') ?>"></script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/kostum_atolyesi/transform_ve_secim.js', '../../') ?>"></script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/kostum_atolyesi/araclar_ve_yardimcilar.js', '../../') ?>"></script>
    <script src="<?= varlik('v2_gorev_sistemi/assets/js/ogrenci/kostum_atolyesi.js', '../../') ?>"></script>

<!-- ============================================================================
     [2026-08] "SİSTEM YÖNETİMİ" IFRAME PENCERESİ KALDIRILDI.

     Burada öğrenci ekranından Süper Admin sayfalarını (kukla ekleme/silme)
     iframe içinde açan bir pencere vardı. Yani her öğrenci kütüphaneden
     kukla silebiliyor, sunucuya kalıcı kayıt yazabiliyordu — tüm okulları
     etkileyen bir yetki. Sayfalar zaten superAdminZorunlu() ile korunuyordu
     ama arayüzün öğrenciye bu düğmeleri göstermesi baştan yanlıştı.

     Yeni düzen:
       Öğrenci  -> kendi kuklaları, sadece tarayıcıda (window.projeKuklalari)
       S. Admin -> admin/kukla_kutuphanesi/index.php, kalıcı kütüphane
     ============================================================================ -->
</body>

</html>