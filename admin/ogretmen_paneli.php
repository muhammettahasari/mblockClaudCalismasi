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
    <title>Canlı Görev Takip Paneli</title>
    <link rel="stylesheet" href="../css/teacher_panel.css">
    <style>
        .pulse {
            animation: pulse-animation 1.5s infinite;
        }

        @keyframes pulse-animation {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        .btn-action {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            color: white;
            transition: 0.2s;
        }

        .btn-action:hover {
            opacity: 0.8;
        }
    </style>
</head>

<body>

    <div class="teacher-container" style="max-width: 1200px;">
        <div class="header-area">
            <div>
                <h1 style="margin-bottom: 5px;">📡 Canlı Görev Takibi <span style="font-size: 16px; color: #64748b; font-weight: normal;">(<?= htmlspecialchars($okul_adi) ?>)</span></h1>
                <p style="margin: 0; font-size: 18px; color: #4f46e5; font-weight: bold;">SINIF PIN KODUNUZ: <span style="background: #e0e7ff; padding: 5px 10px; border-radius: 6px; letter-spacing: 2px;"><?= htmlspecialchars($ogretmen_pin) ?></span></p>
            </div>

            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 10px;">
                <div style="display: flex; gap: 15px; align-items: stretch;">

                    <?php
                    /* [2026-08] Bilinen Sorunlar #7:
                       Bu blok iki kez kopyalanmıştı ve düello durumunu GLOBAL
                       duello_ayarlari (id=1) tablosundan okuyordu — yani bir
                       öğretmenin açtığı düello bütün okullarda açık görünüyordu.
                       Artık her öğretmenin kendi ogretmenler.duello_bitis alanı
                       kullanılıyor (savaş arenasıyla aynı desen). */
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
                    <button id="btnArenaToggle" onclick="<?= $arena_acik_mi ? 'arenaKapat()' : 'arenaModalAc()' ?>" style="background: <?= $arena_acik_mi ? '#ef4444' : '#10b981' ?>; color: white; padding: 10px 15px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.2);" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                        <?= $arena_acik_mi ? '🔒 Arenayı Kapat' : '⚔️ Savaş Arenasını Aç' ?>
                    </button>

                    <button id="btnDuelloToggle" onclick="duelloToggle()" style="background: <?= $duello_aktif_mi ? '#ef4444' : '#10b981' ?>; color: white; padding: 10px 15px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.2);" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                        <?= $duello_aktif_mi ? '🔒 Düello Kapat' : '⚔️ Düello Aç' ?>
                    </button>

                    <a href="quiz_kontrol_merkezi.php" style="background: linear-gradient(135deg, #8b5cf6, #6366f1); color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: bold; box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);">
                        🎮 Quiz Başlat
                    </a>

                    <a href="ogrenci_yonetimi.php" style="background: #3b82f6; color: white; text-decoration: none; padding: 10px 35px; border-radius: 8px; font-weight: bold; display: flex; flex-direction: column; justify-content: center; align-items: center; box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3); transition: 0.2s;" onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">
                        <span style="font-size: 16px; margin-bottom: 3px;">Öğrenci</span>
                        <span style="font-size: 16px;">Yönetimi</span>
                    </a>

                    <!-- [2026-08] Öğrencinin hangi bölümde olduğunu görme + geri gönderme -->
                    <a href="ogrenci_ilerleme.php" style="background: #7c3aed; color: white; text-decoration: none; padding: 10px 30px; border-radius: 8px; font-weight: bold; display: flex; flex-direction: column; justify-content: center; align-items: center; box-shadow: 0 4px 6px rgba(124, 58, 237, 0.3); transition: 0.2s;" onmouseover="this.style.background='#5b21b6'" onmouseout="this.style.background='#7c3aed'">
                        <span style="font-size: 16px; margin-bottom: 3px;">📊 İlerleme</span>
                        <span style="font-size: 16px;">Takibi</span>
                    </a>

                    <div style="display: flex; flex-direction: column; justify-content: space-between; gap: 6px; width: 150px;">
                        <a href="../api/logout.php" style="background: #ef4444; color: white; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 15px; transition: 0.2s; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.4); display: flex; align-items: center; justify-content: center; width: 100%; box-sizing: border-box; flex: 1;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'">
                            🚪 Çıkış
                        </a>

                        <button onclick="sureTalepFormuAc()" style="background: <?= $rozet_renk ?>; color: white; border: none; border-radius: 6px; font-weight: bold; font-size: 11px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); text-align: center; width: 100%; box-sizing: border-box; cursor: pointer; transition: 0.2s; flex: 1; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'" title="Bitiş Tarihi: <?= htmlspecialchars($uyelik_bitis ?: 'Belirtilmemiş') ?>">
                            ⏳ <?= $kalan_mesaj ?>
                        </button>
                    </div>
                </div>

                <a href="ogrenci_yonetimi.php" id="onayUyarisi" class="pulse" style="display: none; color: #ef4444; font-weight: bold; font-size: 14px; background: #fee2e2; padding: 6px 15px; border-radius: 6px; border: 1px solid #ef4444; text-decoration: none; cursor: pointer; text-align: center;">🚨 YENİ KAYIT VAR! ONAYLAMAK İÇİN TIKLA ➡️</a>
            </div>
        </div>


        <div class="controls-area" style="background: #f1f5f9; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
            <label style="font-weight: bold; color: #475569; display: block; margin-bottom: 8px;">Filtrele (Görüntülenecek Sınıf):</label>
            <select id="sinifSelect" style="width: 100%; max-width: 400px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 16px;" onchange="ogrencileriCek()">
                <option value="0">Tüm Sınıfları Göster</option>
                <?php foreach ($siniflar as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['sinif_adi']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <table class="student-table">
            <thead>
                <tr>
                    <th>Öğrenci Adı</th>
                    <th>Sınıfı</th>
                    <th>Kalıcı Puan</th>
                    <th>Bekleyen Puan</th>
                    <th>Durum</th>
                    <th>Uzaktan İşlem</th>
                </tr>
            </thead>
            <tbody id="canliOgrenciListesi">
                <tr>
                    <td colspan="6" style="text-align:center; padding: 20px;">Öğrenciler yükleniyor...</td>
                </tr>
            </tbody>
        </table>
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