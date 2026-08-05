<?php

/**
 * @author Muhammet Taha Sarı
 * @description 1v1 Meydan okuma (Düello) savaşlarının yapıldığı ana görsel arenadır.
 * @updates [2026-08] Katılımcı kontrolü eklendi (Bilinen Sorunlar #7):
 *          Eskiden düello numarasını adres çubuğuna yazan herkes başkasının
 *          maçını izleyip soruyu görebiliyordu. Artık sadece maçın iki
 *          tarafı bu sayfayı açabilir.
 */

require_once __DIR__ . '/bilesenler/guvenlik.php';
ogrenciZorunlu();

if (!isset($_GET['id'])) {
    header("Location: ogrenci_paneli.php");
    exit;
}

$ogrenci_id = (int) $_SESSION['ogrenci_id'];
$duello_id  = (int) $_GET['id'];

// Veritabanındaki gerçek sütun isimleri (cevap_a_metni vb.) sisteme entegre edildi.
$sorgu = $pdo->prepare("
    SELECT d.*, s.soru_metni, s.cevap_a_metni as secenek_a, s.cevap_b_metni as secenek_b, s.cevap_c_metni as secenek_c, s.cevap_d_metni as secenek_d, s.sure as soru_suresi,
           o1.ad_soyad as saldiran_ad, o2.ad_soyad as savunan_ad
    FROM quiz_duellolar d
    JOIN quiz_sorulari s ON d.soru_id = s.id
    JOIN ogrenciler o1 ON d.meydan_okuyan_id = o1.id
    JOIN ogrenciler o2 ON d.meydan_okunan_id = o2.id
    WHERE d.id = ?
");
$sorgu->execute([$duello_id]);
$arena = $sorgu->fetch();

// Güvenlik veya maç bittiyse geri at
// 'bitti' durumunda KİMSE ATILMAZ! Girip soruyu görürler, JavaScript onlara sonucu söyler.
if (!$arena || $arena['durum'] === 'reddedildi' || $arena['durum'] === 'bekliyor') {
    echo "<script>alert('Savaş arenası şu an kapalı!'); window.location.href='ogrenci_paneli.php';</script>";
    exit;
}

// KATILIMCI KONTROLÜ: Bu maçın tarafı değilsen soruyu göremezsin.
if ((int) $arena['meydan_okuyan_id'] !== $ogrenci_id && (int) $arena['meydan_okunan_id'] !== $ogrenci_id) {
    echo "<script>alert('Bu düello sana ait değil!'); window.location.href='ogrenci_paneli.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>⚔️ Savaş Arenası</title>
    <style>
        body {
            margin: 0;
            background: #0f172a;
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            overflow: hidden;
        }

        .vs-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 40px;
            font-size: 24px;
            font-weight: bold;
        }

        .player {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 30px;
            border-radius: 12px;
            border: 2px solid #3b82f6;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);
        }

        .player.defender {
            border-color: #ef4444;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.5);
        }

        .vs-text {
            font-size: 40px;
            color: #f59e0b;
            text-shadow: 0 0 20px #f59e0b;
            animation: pulse 1.5s infinite;
        }

        .soru-kutusu {
            background: #1e293b;
            padding: 30px;
            border-radius: 15px;
            width: 80%;
            max-width: 800px;
            text-align: center;
            font-size: 22px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            margin-bottom: 30px;
        }

        .secenekler {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            width: 80%;
            max-width: 800px;
        }

        .secenek-btn {
            background: #334155;
            color: white;
            border: 2px solid #475569;
            padding: 20px;
            font-size: 18px;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.2s;
        }

        .secenek-btn:hover {
            background: #3b82f6;
            border-color: #60a5fa;
            transform: scale(1.02);
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
            }
        }
    </style>
</head>

<body>
    <div id="geriSayimEkrani" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.95); z-index: 99999; display: flex; flex-direction: column; justify-content: center; align-items: center; backdrop-filter: blur(10px); transition: 0.3s;">
        <h1 style="color: #f59e0b; font-size: 40px; margin-bottom: 20px; text-shadow: 0 0 20px #f59e0b;">⚔️ SAVAŞA HAZIRLAN ⚔️</h1>
        <div id="sayacRakam" style="font-size: 150px; font-weight: bold; color: white; text-shadow: 0 0 30px #3b82f6;">3</div>
        <p style="color: #94a3b8; font-size: 18px; margin-top: 20px;">İlk doğru cevabı veren kazanır!</p>
    </div>

    <div class="vs-header">
        <div class="player"><?= htmlspecialchars($arena['saldiran_ad']) ?> <br><span style="font-size: 14px; color:#94a3b8;">(Meydan Okuyan)</span></div>
        <div class="vs-text">VS</div>
        <div class="player defender"><?= htmlspecialchars($arena['savunan_ad']) ?> <br><span style="font-size: 14px; color:#94a3b8;">(Savunan)</span></div>
    </div>

    <div class="soru-kutusu" style="text-align: left; padding: 20px 40px; font-size: 18px;">
        <?= $arena['soru_metni'] ?>
    </div>
    <div id="soruSuresiKutusu" style="display: none; margin-bottom: 25px; width: 100%; max-width: 600px; margin-left: auto; margin-right: auto;">
        <div style="width: 100%; background: #1e293b; border-radius: 20px; height: 18px; overflow: hidden; border: 2px solid #3b82f6; box-shadow: 0 0 10px rgba(59, 130, 246, 0.3);">
            <div id="zamanCubugu" style="width: 100%; height: 100%; background: linear-gradient(90deg, #10b981, #34d399); transition: width 1s linear, background-color 0.5s;"></div>
        </div>
    </div>
    <div id="soruSuresiKutusu" style="display: none; margin-bottom: 25px; width: 100%; max-width: 600px; margin-left: auto; margin-right: auto;">
        <div style="text-align: center; margin-bottom: 10px;">
            <span style="background: rgba(15, 23, 42, 0.8); padding: 8px 20px; border-radius: 8px; font-size: 18px; font-weight: bold; color: #facc15; border: 1px solid #facc15; box-shadow: 0 0 10px rgba(250, 204, 21, 0.2);">
                ⏳ Kalan Süre: <span id="soruSayaci"><?= $arena['soru_suresi'] > 0 ? $arena['soru_suresi'] : 30 ?></span>
            </span>
        </div>

        <div style="width: 100%; background: #334155; border-radius: 10px; height: 14px; overflow: hidden; border: 1px solid #475569; box-shadow: inset 0 2px 4px rgba(0,0,0,0.5);">
            <div id="zamanCubugu" style="width: 100%; height: 100%; background: linear-gradient(90deg, #10b981, #3b82f6); transition: width 1s linear, background-color 0.5s;"></div>
        </div>
    </div>
    <div class="secenekler">
        <button class="secenek-btn" onclick="cevapGonder('A')"><b>A)</b> <?= $arena['secenek_a'] ?></button>
        <button class="secenek-btn" onclick="cevapGonder('B')"><b>B)</b> <?= $arena['secenek_b'] ?></button>
        <button class="secenek-btn" onclick="cevapGonder('C')"><b>C)</b> <?= $arena['secenek_c'] ?></button>
        <button class="secenek-btn" onclick="cevapGonder('D')"><b>D)</b> <?= $arena['secenek_d'] ?></button>
    </div>

    <script>
        let duello_id = <?= $duello_id ?>;
        // DİKKAT: islemYapiliyor TRUE ile başlar, yani şıklar başlangıçta KİLİTLİDİR!
        let islemYapiliyor = true;
        // --- 🎵 SES MOTORU (AUDIO ENGINE) 🎵 ---
        const sayacSesi = new Audio('assets/sounds/ucsaniyegerisayim.mp3');
        const duelloSesi = new Audio('assets/sounds/duellomuzik.mp3');
        duelloSesi.loop = true; // Savaş müziği oyun bitene kadar döngüye girer
        duelloSesi.volume = 0.4; // Soru okunabilsin diye arka plan müziğinin sesini biraz kıstık

        // Sayfa yüklenir yüklenmez 3 saniye sayacının sesini başlat
        sayacSesi.play().catch(e => console.log("Tarayıcı otomatik sesi engelledi."));


        // --- GERİ SAYIM MOTORU ---
        let sayac = 3;
        let sayacKutusu = document.getElementById('sayacRakam');
        let kalkan = document.getElementById('geriSayimEkrani');

        let geriSayimBaslat = setInterval(() => {
            sayac--;
            if (sayac > 0) {
                sayacKutusu.innerText = sayac;
                sayacKutusu.style.color = sayac === 2 ? '#facc15' : '#f87171';
            } else if (sayac === 0) {
                sayacKutusu.innerText = "SAVAŞ!";
                sayacKutusu.style.color = '#10b981';
            } else {
                clearInterval(geriSayimBaslat);
                kalkan.style.opacity = '0';
                setTimeout(() => {
                    kalkan.style.display = 'none';
                    // SÜRE BİTTİ, KİLİT AÇILDI! ARTIK TIKLAYABİLİRLER!
                    islemYapiliyor = false;
                    duelloSesi.play().catch(e => console.log("Tarayıcı sesi engelledi."));

                    // --- SADECE KAYAN ÇUBUK MOTORU ---
                    document.getElementById('soruSuresiKutusu').style.display = 'block';

                    let toplamSure = <?= $arena['soru_suresi'] > 0 ? $arena['soru_suresi'] : 30 ?>;
                    let kalanSoruSuresi = toplamSure;
                    let zamanCubugu = document.getElementById('zamanCubugu');

                    let bomba = setInterval(() => {
                        if (islemYapiliyor) {
                            clearInterval(bomba);
                            return;
                        } // Biri tıklarsa durdur

                        kalanSoruSuresi--;

                        // Çubuğu Akıcı Bir Şekilde Kaydır (% Hesaplaması)
                        let yuzde = (kalanSoruSuresi / toplamSure) * 100;
                        zamanCubugu.style.width = yuzde + '%';

                        // Renk Değişimleri (Zaman azaldıkça tehlike hissi verir)
                        if (kalanSoruSuresi <= 5) {
                            zamanCubugu.style.background = '#ef4444'; // Son saniyelerde Kırmızı
                            zamanCubugu.style.boxShadow = '0 0 15px #ef4444';
                        } else if (kalanSoruSuresi <= (toplamSure / 2)) {
                            zamanCubugu.style.background = '#f59e0b'; // Yarı sürede Turuncu
                        }

                        if (kalanSoruSuresi <= 0) {
                            clearInterval(bomba);
                            islemYapiliyor = true; // Ekranı kilitle (Radarı durdurur)
                            duelloSesi.pause(); // Müziği sustur

                            // Süre bittiği için API'ye haber ver ve OYUNCUYU DİREKT AT!
                            let formData = new FormData();
                            formData.append('duello_id', duello_id);

                            fetch('api/duello/zaman_doldu.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(res => res.json())
                                .then(data => {
                                    alert("⏳ SÜRE DOLDU! İkiniz de yavaş kaldınız!\n\n💥 HASAR RAPORU:\nSaldıran: -5 Puan\nSavunan: -3 Puan ceza yedi!");
                                    window.location.href = 'ogrenci_paneli.php';
                                }).catch(e => {
                                    window.location.href = 'ogrenci_paneli.php';
                                });
                        }
                    }, 1000);

                    // ---------------------------------------------
                }, 300);
            }
        }, 1000);
        // --- GERİ SAYIM BİTİŞ ---

        function cevapGonder(secilen) {
            if (islemYapiliyor) return;
            islemYapiliyor = true;
            duelloSesi.pause(); // 🎵 CEVAP VERİLDİ, MÜZİĞİ SUSTUR!
            let formData = new FormData();
            formData.append('duello_id', duello_id);
            formData.append('cevap', secilen);

            fetch('api/duello/cevap.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'gec_kaldin') {
                        alert("Çok yavaşsın! Rakibin senden önce cevap verdi.");
                    } else if (data.berabere_mi) {
                        alert("💀 YANLIŞ CEVAP! Çok acele ettin ve yanlış bildiğin için ceza puanını yedin!");
                    } else if (data.kazandim_mi) {
                        alert("🎉 DOĞRU! MUHTEŞEM BİR ZAFER KAZANDIN!\nPuanlar hesabına eklendi.");
                    }
                    window.location.href = 'ogrenci_paneli.php';
                });
        }

        setInterval(() => {
            if (islemYapiliyor) return;
            fetch('api/duello/senkron.php?duello_id=' + duello_id)
                .then(res => res.json())
                .then(data => {
                    if (data.oyun_bitti) {
                        islemYapiliyor = true;
                        duelloSesi.pause();

                        if (data.zaman_doldu) {
                            alert("⏳ SÜRE DOLDU! İkiniz de uyudunuz!\n\n💥 HASAR RAPORU:\nSaldıran: -5 Puan\nSavunan: -3 Puan ceza yedi!");
                        } else if (data.berabere_mi) {
                            alert("😲 RAKİBİN YANLIŞ BİLDİ VE CEZAYI YEDİ!\nSen kıl payı kurtuldun, puan kaybetmedin.");
                        } else if (!data.kazandim_mi) {
                            alert("💀 RAKİBİN SENDEN HIZLI DAVRANDI VE DOĞRU BİLDİ!\nSavaşı kaybettin.");
                        } else {
                            alert("🎉 DOĞRU! MUHTEŞEM BİR ZAFER KAZANDIN!\nPuanlar hesabına eklendi.");
                        }
                        window.location.href = 'ogrenci_paneli.php';
                    }
                });
        }, 1000);
    </script>
</body>

</html>