<?php
/* ==========================================================
   Dosya Yolu: admin/ogretmen_paneli.php
   İşlev: Öğretmenin canlı takip, arena ve düello kontrol paneli
   GÜVENLİK: [2026-08] Merkezi oturum kapıcısına geçildi (bilesenler/guvenlik.php).
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';
ogretmenZorunlu();

$ogretmen_id = $_SESSION['ogretmen_id'];
$ogretmen_pin = $_SESSION['ogretmen_pin'] ?? 'Bilinmiyor';
$ogretmen_ad = $_SESSION['ogretmen_ad'] ?? 'Öğretmen';
$okul_adi = $_SESSION['okul_adi'] ?? 'Okul Bilinmiyor';

require_once '../db.php';
$sinifSorgu = $pdo->prepare("SELECT id, sinif_adi FROM siniflar WHERE ogretmen_id = ? ORDER BY sinif_adi ASC");
$sinifSorgu->execute([$ogretmen_id]);
$siniflar = $sinifSorgu->fetchAll();
// YENİ: Üyelik Süresi Hesaplama Motoru
// YENİ: Üyelik Süresi Hesaplama Motoru (Düzeltilmiş)
$uyelikSorgu = $pdo->prepare("SELECT bitis_tarihi FROM ogretmenler WHERE id = ?");
$uyelikSorgu->execute([$ogretmen_id]);
$uyelikBilgi = $uyelikSorgu->fetch();

$kalan_mesaj = "Süresiz / Belirsiz";
$rozet_renk = "#10b981"; // Varsayılan Yeşil
$uyelik_bitis = $uyelikBilgi['bitis_tarihi'] ?? null;
$sure_doldu_mu = false;

if ($uyelik_bitis) {
    $bitis_tarihi = new DateTime($uyelik_bitis);
    $bugun = new DateTime();

    $bitis_tarihi->setTime(0, 0, 0);
    $bugun->setTime(0, 0, 0);

    if ($bitis_tarihi > $bugun) {
        $fark = $bugun->diff($bitis_tarihi);
        $toplam_ay = ($fark->y * 12) + $fark->m;

        $kalan_mesaj = $toplam_ay . " Ay " . $fark->d . " Gün Kaldı";

        if ($toplam_ay < 1) {
            $rozet_renk = "#ef4444"; // Kırmızı
        } else {
            $rozet_renk = "#10b981"; // Yeşil
        }
    } else {
        $sure_doldu_mu = true; // Süre dolmuş!
    }
}

// EĞER SÜRE DOLDUYSA: Öğretmen sayfayı yenilediği an sistem onu kapı dışarı eder!
if ($sure_doldu_mu) {
    header("Location: ../api/logout.php");
    exit;
}
//bitiş motor
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canlı Takip | mBlock Akademi</title>
    <link rel="stylesheet" href="../css/ogretmen_tema.css">
    <style>
        /* Sayfaya özel — sınıf kontrol anahtarları */
        .salter-izgara { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px; }
        .salter {
            border: 2px solid var(--ot-cizgi); border-radius: var(--ot-radius);
            padding: 16px; transition: .18s; background: #fff;
        }
        .salter.acik { border-color: var(--ot-yesil); background: #f0fdf4; }
        .salter .ust { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .salter .ad { font-weight: 800; font-size: 14.5px; }
        .salter .aciklama { color: var(--ot-soluk); font-size: 12.5px; line-height: 1.5; margin-bottom: 12px; min-height: 34px; }
        .salter .ot-btn { width: 100%; }

        /* Tablo satırlarındaki uzaktan işlem düğmeleri — JS ile üretiliyor,
           bu yüzden sınıf adı (.btn-action) korundu. */
        .btn-action {
            padding: 6px 12px; border: none; border-radius: 7px;
            font-weight: 700; font-size: 12.5px; cursor: pointer;
            color: #fff; transition: .15s; margin-left: 4px;
        }
        .btn-action:hover { filter: brightness(1.1); }

        /* ================================================================
           ALT SEKMELER — [2026-08] Canlı Takip / İlerleme
           Üstteki ana sekmelerden görsel olarak AYRIŞMALI, yoksa öğretmen
           hangi seviyede gezindiğini karıştırır. Bu yüzden ana sekmeler
           koyu barda dolgulu, bunlar kart içinde alttan çizgili.
           ================================================================ */
        /* Sekmelerin oturduğu çizgi — sekme hissi bundan geliyor.
           :has() yerine sınıf kullanıldı; okul bilgisayarlarındaki eski
           tarayıcılarda :has() desteklenmiyor olabilir. */
        .ot-kart-baslik.sekmeli { border-bottom: 2px solid var(--ot-cizgi); margin-bottom: 0; padding-bottom: 0; }
        .ot-altsekmeler { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: -2px; }
        .ot-altsekme {
            background: none; border: none; border-bottom: 3px solid transparent;
            padding: 9px 14px 8px; cursor: pointer; font-family: inherit;
            font-size: 15px; font-weight: 800; color: var(--ot-soluk);
            transition: .15s; border-radius: 8px 8px 0 0;
        }
        .ot-altsekme:hover { color: var(--ot-ana); background: #faf5ff; }
        .ot-altsekme.aktif { color: var(--ot-ana-koyu); border-bottom-color: var(--ot-ana); }

        /* İlerleme tablosu */
        .il-cubuk { background: var(--ot-cizgi); border-radius: 99px; height: 9px; width: 140px; overflow: hidden; }
        .il-cubuk span { display: block; height: 100%; background: linear-gradient(90deg, var(--ot-yesil), #34d399); }
        .il-eksi { color: #b91c1c; font-weight: 800; }

        /* Sıfırlama panelindeki bölüm satırları */
        .bolum-satir {
            display: flex; align-items: center; gap: 12px; padding: 12px 14px;
            border: 2px solid var(--ot-cizgi); border-radius: 10px; margin-bottom: 10px; cursor: pointer;
        }
        .bolum-satir.secili { border-color: var(--ot-kirmizi); background: #fef2f2; }
        .bolum-satir.kilitli { opacity: .45; background: var(--ot-zemin); cursor: not-allowed; }
        .bolum-satir input { width: 19px; height: 19px; cursor: pointer; }
        .bolum-bilgi { flex: 1; }
        .bolum-bilgi b { display: block; }
        .bolum-bilgi small { color: var(--ot-soluk); }
        .noktalar { display: flex; gap: 5px; flex-wrap: wrap; margin-top: 6px; }
        .nokta { width: 13px; height: 13px; border-radius: 50%; background: var(--ot-cizgi); }
        .nokta.tamam { background: var(--ot-yesil); }
        .nokta.sifirlandi { background: var(--ot-kirmizi); }
        .nokta.aktif { background: var(--ot-turuncu); }

        /* Canlı güncellenen satır kısa bir an vurgulanır */
        tr.yenilendi td { animation: satirParla 1.6s ease-out; }
        @keyframes satirParla {
            0%   { background: #dcfce7; }
            100% { background: transparent; }
        }

        .pulse { animation: pulse-animation 1.5s infinite; }
        @keyframes pulse-animation {
            0%   { transform: scale(1); }
            50%  { transform: scale(1.04); }
            100% { transform: scale(1); }
        }
    </style>
</head>

<body class="ot">
<?php $OT_AKTIF = 'panel'; require __DIR__ . '/../bilesenler/ogretmen_ust_bar.php'; ?>

<div class="ot-sarmal">

    <?php
    /* [2026-08] Bilinen Sorunlar #7:
       Bu blok iki kez kopyalanmıştı ve düello durumunu GLOBAL duello_ayarlari
       (id=1) tablosundan okuyordu — yani bir öğretmenin açtığı düello bütün
       okullarda açık görünüyordu. Artık her öğretmenin kendi
       ogretmenler.duello_bitis alanı kullanılıyor (arenayla aynı desen). */
    $durumSorgu = $pdo->prepare("SELECT arena_bitis, duello_bitis FROM ogretmenler WHERE id = ?");
    $durumSorgu->execute([$ogretmen_id]);
    $salterler = $durumSorgu->fetch(PDO::FETCH_ASSOC) ?: ['arena_bitis' => null, 'duello_bitis' => null];

    $arena_acik_mi   = ($salterler['arena_bitis']  && strtotime($salterler['arena_bitis'])  > time());
    $duello_aktif_mi = ($salterler['duello_bitis'] && strtotime($salterler['duello_bitis']) > time());

    // Süresi dolmuşsa veritabanını temizle
    if (!$arena_acik_mi && $salterler['arena_bitis']) {
        $pdo->prepare("UPDATE ogretmenler SET arena_bitis = NULL WHERE id = ?")->execute([$ogretmen_id]);
    }
    if (!$duello_aktif_mi && $salterler['duello_bitis']) {
        $pdo->prepare("UPDATE ogretmenler SET duello_bitis = NULL WHERE id = ?")->execute([$ogretmen_id]);
    }
    ?>

    <!-- Onay bekleyen kayıt uyarısı -->
    <a href="ogrenci_yonetimi.php" id="onayUyarisi" class="ot-hata-kutu pulse"
       style="display:none; text-decoration:none; font-weight:800;">
        🚨 Yeni öğrenci kaydı var! Onaylamak için tıkla →
    </a>


    <!-- ==================== SINIF KONTROLLERİ ====================
         [2026-08] Bu üç anahtar eskiden sayfanın tepesinde çıkış ve menü
         düğmeleriyle yan yana duruyordu; hangisinin ne yaptığı ve hangisinin
         AÇIK olduğu anlaşılmıyordu. Artık kendi kartında, durumu görünür. -->
    <div class="ot-kart">
        <div class="ot-kart-baslik">
            <h2>🎛️ Sınıf Kontrolleri</h2>
            <span class="ot-canli"><span class="ot-canli-nokta" id="canliNokta"></span> Canlı</span>
        </div>
        <p class="aciklama">
            Bu anahtarlar <b>senin sınıflarını</b> etkiler; başka öğretmenlerin öğrencileri görmez.
            Arena ve düello süreli açılır, süre bitince kendiliğinden kapanır.
        </p>

        <div class="salter-izgara">
            <!-- Savaş Arenası -->
            <div class="salter <?= $arena_acik_mi ? 'acik' : '' ?>">
                <div class="ust">
                    <span class="ad">⚔️ Savaş Arenası</span>
                    <span class="ot-rozet <?= $arena_acik_mi ? 'ot-rozet-yesil' : 'ot-rozet-gri' ?>">
                        <?= $arena_acik_mi ? '● Açık' : '○ Kapalı' ?>
                    </span>
                </div>
                <p class="aciklama">Öğrenciler birbirine meydan okuyup puan kapabilir. Sınıf içi PvP.</p>
                <button id="btnArenaToggle"
                        class="ot-btn <?= $arena_acik_mi ? 'ot-btn-kirmizi' : 'ot-btn-yesil' ?>"
                        onclick="<?= $arena_acik_mi ? 'arenaKapat()' : 'arenaModalAc()' ?>">
                    <?= $arena_acik_mi ? '🔒 Kapat' : '▶ Süreli Aç' ?>
                </button>
            </div>

            <!-- Düello -->
            <div class="salter <?= $duello_aktif_mi ? 'acik' : '' ?>">
                <div class="ust">
                    <span class="ad">🤺 Düello</span>
                    <span class="ot-rozet <?= $duello_aktif_mi ? 'ot-rozet-yesil' : 'ot-rozet-gri' ?>">
                        <?= $duello_aktif_mi ? '● Açık' : '○ Kapalı' ?>
                    </span>
                </div>
                <p class="aciklama">Birebir soru yarışı. Ekran açıldıktan sonra en hızlı doğru cevap kazanır.</p>
                <button id="btnDuelloToggle"
                        class="ot-btn <?= $duello_aktif_mi ? 'ot-btn-kirmizi' : 'ot-btn-yesil' ?>"
                        onclick="duelloToggle()">
                    <?= $duello_aktif_mi ? '🔒 Kapat' : '▶ Aç' ?>
                </button>
            </div>

            <!-- Quiz -->
            <div class="salter">
                <div class="ust">
                    <span class="ad">🎮 Quiz Savaşı</span>
                    <span class="ot-rozet ot-rozet-mor">Sınıf seç</span>
                </div>
                <p class="aciklama">Tüm sınıf aynı anda yarışır. Bölüm ve zorluk seçip başlatırsın.</p>
                <a href="quiz_kontrol_merkezi.php" class="ot-btn ot-btn-ana">🎮 Quiz Merkezine Git</a>
            </div>
        </div>
    </div>


    <!-- ==================== CANLI TAKİP + İLERLEME ====================
         [2026-08] İki ayrı sayfaydı, tek karta alındı.

         NEDEN BİRLEŞTİRİLDİ? İkisi de "sınıfım ne durumda?" sorusunu
         cevaplıyordu ama ayrı sayfalardaydı. Öğretmen panelde 5A'yı seçiyor,
         ilerlemeye geçince sınıf seçimi kayboluyor ve 5A'yı BİR DAHA seçmesi
         gerekiyordu. Artık sınıf seçimi tek ve ortak; sekme değişince seçili
         sınıf korunur.

         Görünüm URL'de tutulur (?gorunum=ilerleme): öğretmen F5'e bastığında
         ya da bağlantıyı paylaştığında aynı sekme açılır. -->
    <div class="ot-kart">
        <div class="ot-kart-baslik sekmeli" style="align-items:flex-end; gap:14px;">
            <div class="ot-altsekmeler" role="tablist">
                <button type="button" class="ot-altsekme aktif" id="sekmeCanli"
                        role="tab" onclick="gorunumDegistir('canli')">📡 Canlı Görev Takibi</button>
                <button type="button" class="ot-altsekme" id="sekmeIlerleme"
                        role="tab" onclick="gorunumDegistir('ilerleme')">📊 İlerleme</button>
            </div>

            <!-- Sınıf seçimi İKİ SEKME İÇİN ORTAK — bilerek kartın başlığında,
                 sekmelerin dışında duruyor ki "bu seçim ikisini de etkiler"
                 görsel olarak da anlaşılsın. -->
            <select id="sinifSelect" class="ot-girdi" style="width:auto; min-width:200px; margin:0 0 10px auto;"
                    onchange="sinifDegisti()">
                <option value="0">Tüm sınıflar</option>
                <?php foreach ($siniflar as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= temizle($s['sinif_adi']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

    <!-- ============ GÖRÜNÜM 1: CANLI TAKİP ============ -->
    <div id="gorunumCanli">
        <p class="aciklama">
            Öğrencilerin puanı ve durumu 3 saniyede bir kendiliğinden güncellenir.
        </p>

        <!-- ==================== DERS KİLİDİ ====================
             [2026-08] "Herkes başını kaldırsın, tahtaya baksın" anı için.
             Kilitli öğrencinin ekranında büyük bir kilit çıkar, arka plan
             bulanıklaşır ve hiçbir ilerleme kaydedilemez. -->
        <div class="ot-bilgi" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
            <div>
                <b>⏸️ Ders Kilidi</b> — Seçili sınıfın ekranlarını dondurup çözersin.
                Öğrenci sayfayı yenilese, çıkıp girse bile kilit durur; sadece sen açabilirsin.
                <br><span style="opacity:.85;">Tek tek kilitlemek için satırdaki ⏸️ düğmesini kullan.</span>
                <!-- Sınıf seçilmeden toplu kilide basılırsa burada uyarı çıkar.
                     Eskiden alert() kutusuydu; ekranı kaplamasın diye satır içine alındı. -->
                <div id="kilitUyari" style="display:none; margin-top:8px; font-weight:800; color:#b91c1c;"></div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button class="ot-btn ot-btn-turuncu ot-btn-kucuk" onclick="sinifKilidi('sinif_kilitle')">⏸️ Sınıfı Kilitle</button>
                <button class="ot-btn ot-btn-yesil ot-btn-kucuk"   onclick="sinifKilidi('sinif_ac')">▶️ Sınıfı Çöz</button>
            </div>
        </div>

        <div class="ot-tablo-sarmal">
            <table class="ot-tablo student-table">
                <thead>
                    <tr>
                        <th>Öğrenci</th>
                        <th>Sınıf</th>
                        <th style="text-align:center;">Kalıcı Puan</th>
                        <th style="text-align:center;">Bekleyen</th>
                        <th>Durum</th>
                        <th style="text-align:right;">Uzaktan İşlem</th>
                    </tr>
                </thead>
                <tbody id="canliOgrenciListesi">
                    <tr><td colspan="6" class="ot-bos">Öğrenciler yükleniyor…</td></tr>
                </tbody>
            </table>
        </div>
    </div><!-- /gorunumCanli -->

    <!-- ============ GÖRÜNÜM 2: İLERLEME ============
         Tablo tamamen api/sinif_ilerleme_durumu.php'den doluyor — aynı uç
         nokta canlı yoklamayı da besliyor, yani PHP tarafında ikinci bir
         hesap kopyası YOK. Sınıf kaç kişilik olursa olsun 5 sorgu. -->
    <div id="gorunumIlerleme" style="display:none;">
        <p class="aciklama">
            Her öğrencinin hangi bölümde olduğunu ve ne kadar ilerlediğini gösterir.
            ✏️ ile bir öğrencinin bitirdiği bölümleri <b>tekrar yaptırabilirsin</b>.
        </p>

        <div class="ot-bilgi" id="ilerlemeSinifUyari" style="display:none;">
            📊 İlerleme tablosu <b>tek sınıf</b> için gösterilir — yukarıdan bir sınıf seç.
            ("Tüm sınıflar" seçiliyken bölüm yüzdeleri anlamlı olmaz.)
        </div>

        <div class="ot-tablo-sarmal" id="ilerlemeTabloSarmal">
            <table class="ot-tablo">
                <thead>
                    <tr>
                        <th>Öğrenci</th>
                        <th>Bulunduğu Bölüm</th>
                        <th>İlerleme</th>
                        <th style="text-align:center;">💎 Elmas</th>
                        <th>Durum</th>
                        <th style="text-align:right;">İşlem</th>
                    </tr>
                </thead>
                <tbody id="ilerlemeGovde">
                    <tr><td colspan="6" class="ot-bos">Yükleniyor…</td></tr>
                </tbody>
            </table>
        </div>
    </div><!-- /gorunumIlerleme -->

    </div><!-- /ot-kart -->
</div>


<!-- ==================== SIFIRLAMA PANELİ ====================
     [2026-08] admin/ogrenci_ilerleme.php'den buraya taşındı.
     İki adımlı: önce ÖNİZLEME (hiçbir şey yazılmaz), sonra onay.
     Böylece öğretmen kaç elmas cezası keseceğini görmeden onaylamaz. -->
<div class="ot-perde" id="sifirlaPerde">
    <div class="ot-panel">
        <h2 id="sifirlaBaslik">Öğrenci</h2>
        <div class="alt">Tekrar yapmasını istediğin bölümleri işaretle.</div>

        <div id="sifirlaBolumListesi"></div>

        <div class="ot-uyari-kutu">
            <b>Ulaşılmamış bölümler seçilemez.</b> Öğrencinin henüz gelmediği bölümler
            soluk ve kilitli görünür — geri alınacak bir işi yok.
        </div>

        <div class="ot-alan" style="margin-top:6px;">
            <label>Gerekçe <small style="font-weight:400; color:#64748b;">(isteğe bağlı, öğrenciye gösterilir)</small></label>
            <input type="text" id="sifirlaSebep" class="ot-girdi" maxlength="255"
                   placeholder="Örn: Konuyu pekiştirmen için 1. bölümü tekrar edelim.">
        </div>

        <div id="sifirlaOnizleme"></div>
        <div id="sifirlaDurum" style="display:none; margin-top:12px; border-radius:9px; padding:11px 14px; font-weight:700;"></div>

        <div class="ot-panel-dugmeler">
            <button type="button" class="ot-btn ot-btn-sade" onclick="sifirlaKapat()">Vazgeç</button>
            <button type="button" class="ot-btn ot-btn-kirmizi" id="sifirlaOnayBtn" disabled onclick="sifirlaAdimIlerle()">Devam</button>
        </div>
    </div>
</div>

    <script>
        let canliYayinInterval;

        /* [2026-08] Kilit penceresi başlıkta öğrencinin adını gösteriyor.
           Adı onclick içine gömmek yerine burada tutuyoruz: isimde tırnak
           ya da apostrof olursa (O'Brien gibi) üretilen HTML bozulmasın. */
        let ogrenciAdlari = {};

        /* ====================================================================
           ALT SEKME YÖNETİMİ — Canlı Takip / İlerleme

           ⚡ TEK KURAL: SADECE AÇIK OLAN SEKME YOKLAMA YAPAR.
           İkisi birden dönseydi öğretmen başına saniyede ~0.43 istek olurdu
           (3 sn + 10 sn). Görünmeyen tabloyu tazelemenin kimseye faydası yok;
           sekmeye geçildiğinde zaten hemen bir kez çekiliyor.
           ================================================================== */
        let aktifGorunum = 'canli';

        function gorunumDegistir(ad) {
            aktifGorunum = (ad === 'ilerleme') ? 'ilerleme' : 'canli';
            const ilerlemeMi = (aktifGorunum === 'ilerleme');

            document.getElementById('gorunumCanli').style.display    = ilerlemeMi ? 'none' : '';
            document.getElementById('gorunumIlerleme').style.display = ilerlemeMi ? '' : 'none';
            document.getElementById('sekmeCanli').classList.toggle('aktif', !ilerlemeMi);
            document.getElementById('sekmeIlerleme').classList.toggle('aktif', ilerlemeMi);

            /* Görünüm adreste tutulsun: F5 aynı sekmeyi açsın, bağlantı
               paylaşıldığında karşı taraf aynı ekranı görsün. */
            const adres = new URL(window.location);
            if (ilerlemeMi) { adres.searchParams.set('gorunum', 'ilerleme'); }
            else            { adres.searchParams.delete('gorunum'); }
            history.replaceState(null, '', adres);

            if (ilerlemeMi) { ilerlemeCek(); } else { ogrencileriCek(); }
        }

        /* Sınıf seçimi İKİ SEKME İÇİN ORTAK.
           Öğretmen canlı takipte 5A'yı seçip ilerlemeye geçtiğinde 5A'yı
           tekrar seçmek zorunda kalmasın diye tek kutu kullanılıyor. */
        function sinifDegisti() {
            if (aktifGorunum === 'ilerleme') { ilerlemeCek(); } else { ogrencileriCek(); }
        }

        function ogrencileriCek() {
            if (aktifGorunum !== 'canli') return;   // görünmeyen tabloyu tazeleme
            let secilenSinifId = document.getElementById('sinifSelect').value;

            fetch('../api/teacher_remote.php?islem=liste&sinif_id=' + secilenSinifId)
                .then(res => res.json())
                .then(veri => {
                    if (veri.success) {
                        let html = '';
                        ogrenciAdlari = {};
                        veri.data.forEach(ogr => {
                            ogrenciAdlari[ogr.id] = ogr.ad_soyad;
                            let durumBadge = `<span class="badge" style="background:#cbd5e1; color:#334155;">Aktif (Oyun İçi)</span>`;
                            let actionButtons = '-';
                            let sinifAdi = ogr.sinif_adi || 'Sınıfsız';

                            if (ogr.durum === 'kilitli') {
                                durumBadge = `<span class="badge pulse" style="background:#ef4444; color:white;">🔒 Şifre Kilidi</span>`;
                                actionButtons = `<button class="btn-action" style="background:#10b981;" onclick="uzaktanIslem('kilit_ac', ${ogr.id})">🔓 Kilidi Aç</button>`;
                            } else if (ogr.durum === 'onay_bekliyor') {
                                durumBadge = `<span class="badge pulse" style="background:#f59e0b; color:white;">⏳ Onay Bekliyor</span>`;
                                actionButtons = `<button class="btn-action" style="background:#10b981;" onclick="uzaktanIslem('onayla', ${ogr.id})">✅ Puanı Ver</button> <button class="btn-action" style="background:#ef4444;" onclick="uzaktanIslem('reddet_ac', ${ogr.id})">❌ Başa Sar</button>`;
                            }

                            /* ================================================
                               [2026-08] DERS KİLİDİ — şifre kilidinden AYRI

                               ogr.durum === 'kilitli'   -> öğrenci bölüm
                                   şifresini 3 kez yanlış girdi (yukarıdaki dal)
                               ogr.ogretmen_kilidi == 1  -> öğretmen dersi
                                   dondurdu (buradaki düğme)

                               İkisi ayrı sütunda tutuluyor; biri diğerini ezmez.
                               Bu yüzden ders kilidi düğmesi HER SATIRDA, diğer
                               durumdan bağımsız olarak görünür.
                               ================================================ */
                            const dersKilitli = Number(ogr.ogretmen_kilidi) === 1;

                            if (dersKilitli) {
                                durumBadge += ` <span class="badge pulse" style="background:#7c3aed; color:white;">⏸️ Ders Kilidi</span>`;
                            }

                            const kilitBtn = dersKilitli
                                ? `<button class="btn-action" style="background:#059669;" title="Bu öğrencinin ekranını çöz" onclick="dersKilidi('ac', ${ogr.id})">▶️ Çöz</button>`
                                : `<button class="btn-action" style="background:#7c3aed;" title="Bu öğrencinin ekranını dondur" onclick="dersKilidi('kilitle', ${ogr.id})">⏸️ Kilitle</button>`;

                            html += `<tr>
                    <td><strong>${ogr.ad_soyad}</strong></td>
                    <td>${sinifAdi}</td>
                    <td style="text-align:center; color:#059669; font-weight:900;">${ogr.kalici_puan}</td>
                    <td style="text-align:center; color:#d97706; font-weight:900;">${ogr.anlik_puan}</td>
                    <td>${durumBadge}</td>
                    <td style="text-align:right; white-space:nowrap;">${kilitBtn} ${actionButtons === '-' ? '' : actionButtons}</td>
                </tr>`;
                        });

                        if (veri.data.length === 0) html = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#64748b;">Bu sınıfta henüz öğrenci bulunmuyor.</td></tr>';
                        document.getElementById('canliOgrenciListesi').innerHTML = html;

                        // YENİ: Onay uyarısını göster/gizle
                        if (veri.bekleyen_onay_var) {
                            document.getElementById('onayUyarisi').style.display = 'block';
                        } else {
                            document.getElementById('onayUyarisi').style.display = 'none';
                        }
                    }
                });
        }

        /* ====================================================================
           [2026-08] DERS KİLİDİ — tek öğrenci

           Öğretmen "herkes tahtaya baksın" dediğinde öğrencinin ekranı donar:
           ortada büyük bir kilit, arka plan bulanık ve tıklanamaz. Öğrenci
           sayfayı yenilese, çıkıp girse, hatta başka tarayıcı açsa bile kilit
           durur — çünkü tarayıcıda değil VERİTABANINDA tutuluyor.
           ================================================================== */
        /* [2026-08] Onay ve not sorma işi artık bilesenler/kilit_modali.php'de.
           Eskiden burada üst üste iki tarayıcı kutusu vardı — confirm() sonra
           prompt(). İkincisini iptal eden öğretmen kilidin kurulup kurulmadığını
           anlayamıyordu; üstelik o kutular sınıfa yansıtılan ekranda
           "localhost web sitesinin mesajı" yazısıyla çıkıyordu.
           Pencere isteği kendi atıyor, biz sadece tabloyu tazeliyoruz. */
        function dersKilidi(islem, ogrenciId) {
            kilitModaliAc({
                islem:   islem,                                       // 'kilitle' | 'ac'
                hedefId: ogrenciId,
                hedefAd: ogrenciAdlari[ogrenciId] || 'Öğrenci',
                onTamam: ogrencileriCek
            });
        }

        /* Tüm sınıfı kilitle / çöz.
           Sınıf seçilmemişse (Tüm sınıflar) hangi sınıf olduğu belirsiz olur;
           bu yüzden önce seçim isteniyor. */
        function sinifKilidi(islem) {
            const secim = document.getElementById('sinifSelect');
            const sinifId = parseInt(secim.value, 10);

            if (!sinifId) {
                const uyari = document.getElementById('kilitUyari');
                uyari.textContent = '⚠️ Önce yukarıdan bir sınıf seç. "Tüm sınıflar" seçiliyken toplu kilit ' +
                                    'uygulanmaz — yanlışlıkla başka sınıfları dondurmamak için.';
                uyari.style.display = '';
                secim.focus();
                setTimeout(() => { uyari.style.display = 'none'; }, 6000);
                return;
            }
            document.getElementById('kilitUyari').style.display = 'none';

            kilitModaliAc({
                islem:   islem,                                       // 'sinif_kilitle' | 'sinif_ac'
                hedefId: sinifId,
                hedefAd: secim.options[secim.selectedIndex].text,
                onTamam: ogrencileriCek
            });
        }

        function uzaktanIslem(islemTuru, ogrenciId) {
            fetch(`../api/teacher_remote.php?islem=${islemTuru}&id=${ogrenciId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) ogrencileriCek();
                });
        }

        /* ====================================================================
           İLERLEME SEKMESİ — [2026-08] admin/ogrenci_ilerleme.php'den taşındı

           Tablo tamamen api/sinif_ilerleme_durumu.php'den doluyor. O uç nokta
           sınıf kaç kişilik olursa olsun 5 SORGU çalıştırır (öğrenci başına
           döngü içinde sorgu YOK), bu yüzden 10 saniyede bir yoklamak
           sunucuyu zorlamaz.

           Frenler: sekme arkadaysa dur, sıfırlama paneli açıkken dur, üst üste
           istek atma, `damga` değişmediyse DOM'a hiç dokunma.
           ================================================================== */
        const ILERLEME_API = '../api/sinif_ilerleme_durumu.php';
        const SIFIRLA_API  = '../api/ogrenci_ilerleme_sifirla.php';
        const ILERLEME_ARALIK = 10000;

        let ilerlemeDamga   = '';
        let ilerlemeMesgul  = false;
        let ilerlemeSonSinif = 0;   // hangi sınıf çizili — sınıf değişince vurgu yapma

        function kacir(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

        function ilerlemeCek() {
            if (aktifGorunum !== 'ilerleme') return;

            /* ⚠️ SIRALAMA ÖNEMLİ — [2026-08 HATA DÜZELTMESİ]
               Sınıf kontrolü, "istek uçuşta mı" (ilerlemeMesgul) kontrolünden
               ÖNCE gelmeli. Ters sıradayken şu oluyordu: bir istek uçarken
               öğretmen "Tüm sınıflar"a geçince fonksiyon erkenden dönüyor,
               uyarı hiç görünmüyor ve saniyeler sonra dönen cevap ESKİ sınıfın
               tablosunu çiziyordu. Ekranda "Tüm sınıflar" yazarken 5A'nın
               verisi duruyordu. Arayüz her zaman ŞU ANKİ seçimi göstermeli. */
            const sinifId = parseInt(document.getElementById('sinifSelect').value, 10);
            const uyari   = document.getElementById('ilerlemeSinifUyari');
            const sarmal  = document.getElementById('ilerlemeTabloSarmal');

            /* "Tüm sınıflar" seçiliyken ilerleme gösterilmez: bölüm yüzdeleri
               farklı sınıfların müfredat noktalarını karıştırırdı. */
            if (!sinifId) {
                uyari.style.display  = '';
                sarmal.style.display = 'none';
                ilerlemeDamga = '';          // sonraki sınıf mutlaka yeniden çizilsin
                return;
            }
            uyari.style.display  = 'none';
            sarmal.style.display = '';

            if (ilerlemeMesgul) return;
            if (document.hidden) return;
            if (document.getElementById('sifirlaPerde').classList.contains('acik')) return;

            ilerlemeMesgul = true;
            fetch(ILERLEME_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ sinif_id: sinifId })
            })
            .then(r => r.json())
            .then(d => {
                if (!d.success) throw new Error(d.mesaj || 'hata');

                /* Cevap geç geldiyse ve öğretmen bu arada sınıfı değiştirdiyse
                   ÇÖPE AT. Yoksa 5A'nın gecikmiş cevabı 5B'nin tablosunu ezer. */
                if (parseInt(document.getElementById('sinifSelect').value, 10) !== sinifId) return;

                /* Damga aynıysa hiçbir şey değişmemiş — DOM'a dokunma */
                const yeniDamga = d.damga + ':' + sinifId;
                if (yeniDamga === ilerlemeDamga) return;

                /* Yeşil vurgu SADECE "bir şey değişti" demek için var.
                   İlk çizimde ya da sınıf değiştiğinde tüm tablo yanıp sönerse
                   anlamını yitirir — öğretmen neyin değiştiğini ayırt edemez. */
                const vurgula = (ilerlemeDamga !== '') && (ilerlemeSonSinif === sinifId);

                ilerlemeDamga    = yeniDamga;
                ilerlemeSonSinif = sinifId;

                ilerlemeCiz(d.ogrenciler || [], !vurgula);
            })
            .catch(() => {
                document.getElementById('ilerlemeGovde').innerHTML =
                    '<tr><td colspan="6" class="ot-bos">Bağlantı kurulamadı, tekrar denenecek.</td></tr>';
            })
            .finally(() => { ilerlemeMesgul = false; });
        }

        function ilerlemeCiz(liste, ilkCizim) {
            const govde = document.getElementById('ilerlemeGovde');

            if (!liste.length) {
                govde.innerHTML = '<tr><td colspan="6" class="ot-bos">Bu sınıfta onaylı öğrenci yok.</td></tr>';
                return;
            }

            let html = '';
            liste.forEach(o => {
                ogrenciAdlari[o.id] = o.ad;   // sıfırlama paneli başlığı için

                const durum = o.cezali > 0
                    ? `<span class="ot-rozet ot-rozet-kirmizi">⚠️ ${o.cezali} görev tekrar</span>`
                    : `<span class="ot-rozet ot-rozet-yesil">✓ Temiz</span>`;

                html += `<tr data-ogrenci="${o.id}">
                    <td><strong>${kacir(o.ad)}</strong>${o.kullanici_adi ? `<br><small style="color:#64748b;">@${kacir(o.kullanici_adi)}</small>` : ''}</td>
                    <td>${kacir(o.bolum)}</td>
                    <td>
                        <div class="il-cubuk"><span style="width:${o.yuzde}%"></span></div>
                        <small style="color:#64748b;">${o.cozulen} / ${o.toplam} görev (%${o.yuzde})</small>
                    </td>
                    <td style="text-align:center;" class="${o.elmas < 0 ? 'il-eksi' : ''}">${o.elmas}</td>
                    <td>${durum}</td>
                    <td style="text-align:right;">
                        <button class="ot-btn ot-btn-ana ot-btn-kucuk" onclick="sifirlaAc(${o.id})">✏️ Düzenle</button>
                    </td>
                </tr>`;
            });
            govde.innerHTML = html;

            /* İlk çizimde her satır yanıp sönmesin — sadece sonraki
               güncellemelerde öğretmenin gözü değişikliğe takılsın. */
            if (!ilkCizim) {
                govde.querySelectorAll('tr').forEach(tr => tr.classList.add('yenilendi'));
            }
        }

        /* ====================================================================
           SIFIRLAMA PANELİ — iki adımlı
           Adım 1: bölüm seç -> "Devam" -> sunucudan ÖNİZLEME (hiçbir şey yazılmaz)
           Adım 2: önizlemeyi gör -> "Evet, Sıfırla" -> gerçek işlem

           ⚠️ CEZA KURALI: kalici_puan'a dokunulmaz (lig/arena/canavar ona bağlı).
           Ceza sadece elmastan kesilir ve eksiye düşebilir.
           ================================================================== */
        let sifirlaOgrenci = 0;
        let sifirlaAdim    = 1;

        function sifirlaDurumGoster(tur, metin) {
            const k = document.getElementById('sifirlaDurum');
            k.textContent = metin;
            k.style.display = '';
            k.style.background = tur === 'hata' ? '#fef2f2' : '#ecfdf5';
            k.style.color      = tur === 'hata' ? '#991b1b' : '#065f46';
            k.style.border     = '1px solid ' + (tur === 'hata' ? '#fecaca' : '#a7f3d0');
        }
        function sifirlaDurumGizle() { document.getElementById('sifirlaDurum').style.display = 'none'; }

        async function sifirlaAc(ogrenciId) {
            sifirlaOgrenci = ogrenciId;
            sifirlaAdim    = 1;

            document.getElementById('sifirlaBaslik').textContent = ogrenciAdlari[ogrenciId] || 'Öğrenci';
            document.getElementById('sifirlaSebep').value = '';
            document.getElementById('sifirlaOnizleme').innerHTML = '';
            document.getElementById('sifirlaOnayBtn').textContent = 'Devam';
            document.getElementById('sifirlaOnayBtn').disabled = true;
            sifirlaDurumGizle();

            const kutu = document.getElementById('sifirlaBolumListesi');
            kutu.innerHTML = '<div class="ot-bos">Yükleniyor…</div>';
            document.getElementById('sifirlaPerde').classList.add('acik');

            /* [PERFORMANS] Bölüm haritaları sayfaya GÖMÜLMÜYOR. 40 kişilik
               sınıfta 40 ekstra sorgu demekti, üstelik öğretmen belki hiçbirine
               tıklamayacaktı. Kaleme basılınca tek öğrenci için çekiliyor. */
            let harita = null;
            try {
                const cevap = await fetch(SIFIRLA_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ islem: 'harita', ogrenci_id: ogrenciId })
                });
                const s = await cevap.json();
                if (s.success) { harita = s.harita; }
                else { kutu.innerHTML = `<div class="ot-bos">${kacir(s.mesaj || 'Harita alınamadı.')}</div>`; return; }
            } catch (e) {
                kutu.innerHTML = '<div class="ot-bos">Bağlantı kurulamadı.</div>';
                return;
            }

            if (!harita || !harita.bolumler || !harita.bolumler.length) {
                kutu.innerHTML = '<div class="ot-bos">Henüz görev tanımlanmamış.</div>';
                return;
            }

            kutu.innerHTML = '';
            harita.bolumler.forEach(b => {
                const dokundu = b.cozulen > 0;   // hiç dokunmadığı bölüm seçilemez
                const satir = document.createElement('label');
                satir.className = 'bolum-satir' + (dokundu ? '' : ' kilitli');

                const noktalar = b.gorevler.map(g =>
                    `<span class="nokta ${g.durum}" title="${kacir(g.ad)}"></span>`).join('');

                satir.innerHTML = `
                    <input type="checkbox" value="${b.id}" ${dokundu ? '' : 'disabled'}>
                    <div class="bolum-bilgi">
                        <b>${dokundu ? '' : '🔒 '}${kacir(b.ad)}</b>
                        <small>${b.cozulen} / ${b.toplam} görev tamamlandı</small>
                        <div class="noktalar">${noktalar}</div>
                    </div>`;

                const kutucuk = satir.querySelector('input');
                kutucuk.addEventListener('change', () => {
                    satir.classList.toggle('secili', kutucuk.checked);
                    sifirlaSecimKontrol();
                });
                kutu.appendChild(satir);
            });
        }

        function sifirlaKapat() {
            document.getElementById('sifirlaPerde').classList.remove('acik');
            sifirlaOgrenci = 0;
        }

        function sifirlaSecilenler() {
            return [...document.querySelectorAll('#sifirlaBolumListesi input:checked')]
                   .map(i => parseInt(i.value, 10));
        }

        function sifirlaSecimKontrol() {
            /* Seçim değişince önizleme geçersiz olur — adım 1'e dön.
               Yoksa öğretmen A bölümünün önizlemesini görüp B'yi sıfırlardı. */
            if (sifirlaAdim === 2) {
                sifirlaAdim = 1;
                document.getElementById('sifirlaOnizleme').innerHTML = '';
                document.getElementById('sifirlaOnayBtn').textContent = 'Devam';
            }
            document.getElementById('sifirlaOnayBtn').disabled = sifirlaSecilenler().length === 0;
        }

        async function sifirlaIstek(islem) {
            const cevap = await fetch(SIFIRLA_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    islem,
                    ogrenci_id: sifirlaOgrenci,
                    bolum_idler: sifirlaSecilenler(),
                    sebep: document.getElementById('sifirlaSebep').value
                })
            });
            return cevap.json();
        }

        async function sifirlaAdimIlerle() {
            const btn = document.getElementById('sifirlaOnayBtn');
            btn.disabled = true;
            sifirlaDurumGizle();

            try {
                if (sifirlaAdim === 1) {
                    const s = await sifirlaIstek('onizleme');
                    if (!s.success) { sifirlaDurumGoster('hata', '❌ ' + (s.mesaj || s.message || 'Hata')); btn.disabled = false; return; }

                    const v = s.veri || s.data || s;
                    document.getElementById('sifirlaOnizleme').innerHTML = `
                      <div class="ot-hata-kutu" style="margin-top:14px;">
                        <b>Onaylarsan ne olacak?</b>
                        <ul style="margin:10px 0 0; padding-left:20px; font-weight:400;">
                          <li><b>${v.gorev_sayisi}</b> görev sıfırlanacak, öğrenci bunları tekrar çözecek.</li>
                          <li>Elmas cezası: <b>-${v.elmas_cezasi} 💎</b>
                              (${v.mevcut_elmas} → <b style="color:${v.yeni_elmas < 0 ? '#b91c1c' : 'inherit'}">${v.yeni_elmas}</b>)</li>
                          <li>Puanına <b>dokunulmayacak</b> — lig sıralaması ve arena gücü bozulmaz.</li>
                          <li>Avatarında kırmızı ceza efekti çıkacak; görevleri bitirince kalkacak.</li>
                        </ul>
                      </div>`;

                    sifirlaAdim = 2;
                    btn.textContent = '🔴 Evet, Sıfırla';
                    btn.disabled = false;
                    return;
                }

                const s = await sifirlaIstek('sifirla');
                if (!s.success) { sifirlaDurumGoster('hata', '❌ ' + (s.mesaj || s.message || 'Hata')); btn.disabled = false; return; }

                sifirlaDurumGoster('basari', '✅ ' + (s.mesaj || s.message || 'Sıfırlandı.'));
                ilerlemeDamga = '';                 // tablo mutlaka yeniden çizilsin
                setTimeout(() => { sifirlaKapat(); ilerlemeCek(); }, 900);

            } catch (e) {
                sifirlaDurumGoster('hata', '❌ Bağlantı hatası: ' + e.message);
                btn.disabled = false;
            }
        }

        document.getElementById('sifirlaPerde').addEventListener('mousedown', e => {
            if (e.target.id === 'sifirlaPerde') sifirlaKapat();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') sifirlaKapat();
        });

        /* ====================================================================
           BAŞLANGIÇ
           ================================================================== */
        canliYayinInterval = setInterval(ogrencileriCek, 3000);   // canlı takip
        setInterval(ilerlemeCek, ILERLEME_ARALIK);                // ilerleme
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && aktifGorunum === 'ilerleme') ilerlemeCek();
        });

        /* Adreste ?gorunum=ilerleme varsa o sekmeyle aç (F5 / paylaşılan bağlantı).
           Sınıf da adresten gelebilir: eski ogrenci_ilerleme.php buraya
           ?gorunum=ilerleme&sinif_id=N ile yönlendiriyor. */
        (function baslangic() {
            const p = new URLSearchParams(window.location.search);
            const sinifId = parseInt(p.get('sinif_id') || '0', 10);
            if (sinifId) {
                const secim = document.getElementById('sinifSelect');
                if ([...secim.options].some(o => parseInt(o.value, 10) === sinifId)) {
                    secim.value = String(sinifId);
                }
            }
            gorunumDegistir(p.get('gorunum') === 'ilerleme' ? 'ilerleme' : 'canli');
        })();
    </script>

    <?php if ($uyelik_bitis): ?>
        <script>
            // OTOMATİK ATMA SİSTEMİ: Her 1 dakikada bir saati kontrol eder
            setInterval(function() {
                // Bitiş tarihini gece 00:00 olarak ayarlar
                let bitis = new Date("<?= $uyelik_bitis ?>T00:00:00").getTime();
                let simdi = new Date().getTime();

                // Eğer şu anki saat, bitiş saatini geçmişse acımadan at!
                if (simdi >= bitis) {
                    alert("Üyelik süreniz an itibarıyla dolmuştur! Sistemden çıkarılıyorsunuz.");
                    window.location.href = '../api/logout.php';
                }
            }, 60000); // 60000 milisaniye = 1 Dakika
        </script>
    <?php endif; ?>

    <script>
        /* [2026-08] Süre talebi artık üst bardaki üyelik rozetinden yapılıyor
           (bilesenler/ogretmen_ust_bar.php -> otSureTalepEt).
           Bu fonksiyon geriye dönük uyumluluk için duruyor; eski bir yerden
           çağrılırsa yine çalışsın. */
        function sureTalepFormuAc() {
            let ay = prompt("Kaç aylık ek süre talep ediyorsunuz? (Örn: 1, 3, 6)", "1");
            if (ay !== null && ay.trim() !== "") {
                fetch('../api/teacher_time_request.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            ay: parseInt(ay)
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert("✅ " + ay + " aylık süre talebiniz Süper Admine iletildi! Onaylandığında süreniz uzayacaktır.");
                        } else {
                            alert("Hata: " + data.message);
                        }
                    });
            }
        }

        // --- ARENA KONTROL FONKSİYONLARI ---
        function arenaModalAc() {
            document.getElementById('arenaSureModal').style.display = 'flex';
        }

        function arenaKapat() {
            if (confirm("Arenayı kapatmak istediğinize emin misiniz?")) {
                arenaIstekGonder('kapat', null);
            }
        }

        function arenaKaydet() {
            let secilenSure = document.getElementById('arenaSureSecim').value;
            event.target.innerText = "⏳ Açılıyor...";
            arenaIstekGonder('ac', secilenSure);
        }

        function arenaIstekGonder(islemTuru, sureDegeri) {
            let formData = new FormData();
            formData.append('islem', islemTuru);
            if (sureDegeri) formData.append('sure', sureDegeri);

            fetch('../api/arena_durum_degistir.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert("HATA: " + (data.mesaj || "Bilinmeyen bir sorun oluştu."));
                        location.reload();
                    }
                })
                .catch(err => {
                    alert("Sisteme bağlanılamadı!");
                    console.error(err);
                    location.reload();
                });
        }
    </script>
    <div id="arenaSureModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(5px);">
        <div style="background: #1e293b; padding: 30px; border-radius: 16px; border: 2px solid #3b82f6; width: 350px; text-align: center; box-shadow: 0 0 30px rgba(59, 130, 246, 0.4);">
            <div style="font-size: 40px; margin-bottom: 10px;">⏳</div>
            <h2 style="color: white; margin-top: 0; margin-bottom: 5px;">Savaş Arenası</h2>
            <p style="color: #94a3b8; font-size: 14px; margin-bottom: 25px;">Arenayı kaç dakika boyunca açık tutmak istiyorsunuz?</p>

            <select id="arenaSureSecim" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #475569; background: #0f172a; color: white; margin-bottom: 25px; outline: none; font-size: 16px; cursor: pointer;">
                <option value="suresiz" selected>Süresiz (Açık Kalır)</option>
                <option value="15">15 Dakika</option>
                <option value="30">30 Dakika</option>
                <option value="45">45 Dakika</option>
                <option value="60">1 Saat</option>
                <option value="120">2 Saat</option>
            </select>

            <div style="display: flex; gap: 10px;">
                <button onclick="document.getElementById('arenaSureModal').style.display='none'" style="flex: 1; padding: 12px; background: #475569; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold;">İptal</button>
                <button onclick="arenaKaydet()" style="flex: 1; padding: 12px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);">🚀 Başlat</button>
            </div>
        </div>
    </div>


    <div id="duelloModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(5px);">
        <div style="background: #1e293b; padding: 30px; border-radius: 16px; border: 2px solid #3b82f6; width: 350px; text-align: center; box-shadow: 0 0 30px rgba(59, 130, 246, 0.4);">
            <div style="font-size: 40px; margin-bottom: 10px;">⚔️</div>
            <h2 style="color: white; margin-top: 0; margin-bottom: 5px;">Düello Arenası</h2>
            <p style="color: #94a3b8; font-size: 14px; margin-bottom: 25px;">Öğrencilerin birbirine meydan okumasına ne kadar süre izin vermek istiyorsunuz?</p>

            <select id="duello_sure_secim" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #475569; background: #0f172a; color: white; margin-bottom: 25px; outline: none; font-size: 16px; cursor: pointer;">
                <option value="5" selected>5 Dakika</option>
                <option value="10">10 Dakika</option>
                <option value="15">15 Dakika</option>
                <option value="30">30 Dakika</option>
            </select>

            <div style="display: flex; gap: 10px;">
                <button onclick="document.getElementById('duelloModal').style.display='none'" style="flex: 1; padding: 12px; background: #475569; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold;">İptal</button>
                <button onclick="duelloIslemYap('ac')" style="flex: 1; padding: 12px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);">🚀 Başlat</button>
            </div>
        </div>
    </div>

    <script>
        let duelloAktif = <?= $duello_aktif_mi ? 'true' : 'false' ?>;
        let btnDuelloToggle = document.getElementById('btnDuelloToggle');
        let duelloModal = document.getElementById('duelloModal');

        function duelloToggle() {
            if (duelloAktif) {
                if (confirm("Arenayı hemen kapatmak istediğinize emin misiniz? Öğrencilerin erişimi anında kesilecek.")) {
                    duelloIslemYap('kapat');
                }
            } else {
                duelloModal.style.display = 'flex';
            }
        }

        function duelloIslemYap(islem) {
            let sure = document.getElementById('duello_sure_secim').value;
            let formData = new FormData();
            formData.append('islem', islem);
            if (islem === 'ac') formData.append('sure', sure);

            // Öğretmen paneli bir alt klasörde olduğu için API yolu ../api/duello/arena_kontrol.php olarak ayarlandı
            fetch('../api/duello/duello_kontrol.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    alert(data.mesaj);
                    duelloModal.style.display = 'none';

                    if (islem === 'ac') {
                        duelloAktif = true;
                        btnDuelloToggle.style.background = '#ef4444';
                        btnDuelloToggle.innerHTML = '🔒 Düello Kapat';
                    } else {
                        duelloAktif = false;
                        btnDuelloToggle.style.background = '#10b981';
                        btnDuelloToggle.innerHTML = '⚔️ Düello Aç';
                    }
                }).catch(e => {
                    console.log(e);
                    alert("İşlem sırasında bir hata oluştu.");
                });
        }
        // --- ÖĞRETMEN RADARI: BUTONLARI F5'SİZ OTOMATİK GÜNCELLER ---
        setInterval(() => {
            fetch('../api/ogretmen_radar.php?v=' + Date.now())
                .then(res => res.json())
                .then(data => {
                    // 1. Savaş Arenası Butonu Güncelleme
                    let btnArena = document.getElementById('btnArenaToggle');
                    if (btnArena) {
                        if (data.arena_aktif) {
                            btnArena.style.background = '#ef4444'; // Kırmızı yap
                            btnArena.innerHTML = '🔒 Arenayı Kapat';
                            btnArena.setAttribute('onclick', 'arenaKapat()');
                        } else {
                            btnArena.style.background = '#10b981'; // Yeşil yap
                            btnArena.innerHTML = '⚔️ Savaş Arenasını Aç';
                            btnArena.setAttribute('onclick', 'arenaModalAc()');
                        }
                    }

                    // 2. Düello Arenası Butonu Güncelleme
                    let btnDuello = document.getElementById('btnDuelloToggle');
                    if (btnDuello) {
                        if (data.duello_aktif) {
                            duelloAktif = true;
                            btnDuello.style.background = '#ef4444'; // Kırmızı yap
                            btnDuello.innerHTML = '🔒 Düello Kapat';
                        } else {
                            duelloAktif = false;
                            btnDuello.style.background = '#10b981'; // Yeşil yap
                            btnDuello.innerHTML = '⚔️ Düello Aç';
                        }
                    }
                }).catch(e => console.log("Radar devrede..."));
        }, 2000); // 2 saniyede bir süreleri kontrol eder
    </script>

<?php
/* [2026-08] Ders kilidi penceresi — confirm() + prompt() ikilisinin yerine.
   Hazır notlar, öğretmenin kendi kayıtlı notları ve serbest metin burada. */
$KILIT_MODAL_KOK = '../';
require __DIR__ . '/../bilesenler/kilit_modali.php';
?>
</body>

</html>