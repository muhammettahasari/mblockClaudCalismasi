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


    <!-- ==================== CANLI ÖĞRENCİ LİSTESİ ==================== -->
    <div class="ot-kart">
        <div class="ot-kart-baslik">
            <h2>📡 Canlı Görev Takibi</h2>
            <select id="sinifSelect" class="ot-girdi" style="width:auto; min-width:220px;" onchange="ogrencileriCek()">
                <option value="0">Tüm sınıflar</option>
                <?php foreach ($siniflar as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= temizle($s['sinif_adi']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <p class="aciklama">
            Öğrencilerin puanı ve durumu 3 saniyede bir kendiliğinden güncellenir.
            Kilitlenen öğrenciyi buradan uzaktan açabilirsin.
        </p>

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
    </div>
</div>

    <script>
        let canliYayinInterval;

        function ogrencileriCek() {
            let secilenSinifId = document.getElementById('sinifSelect').value;

            fetch('../api/teacher_remote.php?islem=liste&sinif_id=' + secilenSinifId)
                .then(res => res.json())
                .then(veri => {
                    if (veri.success) {
                        let html = '';
                        veri.data.forEach(ogr => {
                            let durumBadge = `<span class="badge" style="background:#cbd5e1; color:#334155;">Aktif (Oyun İçi)</span>`;
                            let actionButtons = '-';
                            let sinifAdi = ogr.sinif_adi || 'Sınıfsız';

                            if (ogr.durum === 'kilitli') {
                                durumBadge = `<span class="badge pulse" style="background:#ef4444; color:white;">🔒 Sistem Kilitli</span>`;
                                actionButtons = `<button class="btn-action" style="background:#10b981;" onclick="uzaktanIslem('kilit_ac', ${ogr.id})">🔓 Kilidi Aç</button>`;
                            } else if (ogr.durum === 'onay_bekliyor') {
                                durumBadge = `<span class="badge pulse" style="background:#f59e0b; color:white;">⏳ Onay Bekliyor</span>`;
                                actionButtons = `<button class="btn-action" style="background:#10b981;" onclick="uzaktanIslem('onayla', ${ogr.id})">✅ Puanı Ver</button> <button class="btn-action" style="background:#ef4444;" onclick="uzaktanIslem('reddet_ac', ${ogr.id})">❌ Başa Sar</button>`;
                            }

                            html += `<tr>
                    <td><strong>${ogr.ad_soyad}</strong></td>
                    <td>${sinifAdi}</td>
                    <td style="color:#10b981; font-weight:bold; font-size:16px;">${ogr.kalici_puan}</td>
                    <td style="color:#f59e0b; font-weight:bold; font-size:16px;">${ogr.anlik_puan}</td>
                    <td>${durumBadge}</td>
                    <td>${actionButtons}</td>
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

        function uzaktanIslem(islemTuru, ogrenciId) {
            fetch(`../api/teacher_remote.php?islem=${islemTuru}&id=${ogrenciId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) ogrencileriCek();
                });
        }

        ogrencileriCek();
        canliYayinInterval = setInterval(ogrencileriCek, 3000); // 3 saniyede bir tabloyu yenile
        // canliYayinInterval = setInterval(ogrencileriCek, 3000); satırının altına yapıştırılacak
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
 
</body>

</html>