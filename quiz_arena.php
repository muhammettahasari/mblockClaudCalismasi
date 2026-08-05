<?php
/* ====================================================================
 * MODÜL: Öğrenci Savaş Arenası (Nihai RPG Sürümü)
 * DOSYA: quiz_arena.php
 * AÇIKLAMA: Soruların çözüldüğü, güçlerin havada uçuştuğu tam savaş ekranı.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] SINIF KİLİDİ (Bilinen Sorunlar #6): Öğrenci yalnızca KENDİ
 *    sınıfına açılmış oturuma girebilir. Başka bir sınıfın oturum numarasını
 *    adres çubuğuna yazarak arenaya sızmak artık mümkün değil.
 * ==================================================================== */

require_once __DIR__ . '/bilesenler/guvenlik.php';
ogrenciZorunlu();

if (!isset($_GET['oturum'])) {
    header('Location: ogrenci_paneli.php');
    exit;
}

$ogrenci_id = (int) $_SESSION['ogrenci_id'];
$oturum_id  = (int) $_GET['oturum'];

$savasBittiEkrani = static function (string $baslik = 'Savaş Sona Erdi!'): void {
    die("<div style='background:black; color:white; height:100vh; display:flex; justify-content:center; align-items:center; flex-direction:column; font-family:sans-serif;'>
            <h1>" . htmlspecialchars($baslik, ENT_QUOTES) . "</h1>
            <a href='ogrenci_paneli.php' style='color:#3b82f6; font-size:20px;'>Köye Dön</a>
         </div>");
};

// 1. Oturum aktif mi VE bu öğrencinin sınıfına mı ait?
$oturum_sorgu = $pdo->prepare(
    "SELECT qo.*
       FROM quiz_oturum qo
       JOIN ogrenciler o ON o.sinif_id = qo.sinif_id AND o.ogretmen_id = qo.ogretmen_id
      WHERE qo.id = ? AND qo.durum = 'aktif' AND o.id = ?"
);
$oturum_sorgu->execute([$oturum_id, $ogrenci_id]);
$oturum = $oturum_sorgu->fetch(PDO::FETCH_ASSOC);

if (!$oturum) {
    $savasBittiEkrani('Bu savaş sizin sınıfınıza ait değil ya da sona erdi!');
}

// 2. Öğrenci arenaya ilk kez mi giriyor? (Güçleri ver)
$oyuncu_kontrol = $pdo->prepare("SELECT * FROM quiz_ogrenci WHERE oturum_id = ? AND ogrenci_id = ?");
$oyuncu_kontrol->execute([$oturum_id, $ogrenci_id]);
$oyuncu = $oyuncu_kontrol->fetch(PDO::FETCH_ASSOC);

if (!$oyuncu) {
    $ekle = $pdo->prepare("INSERT INTO quiz_ogrenci (oturum_id, ogrenci_id, puan, cozulen_soru_sayisi, kalkan_aktif, kilic_hakki, kalkan_hakki, glitch_hakki, saat_hakki) VALUES (?, ?, 0, 0, 0, 1, 1, 1, 1)");
    $ekle->execute([$oturum_id, $ogrenci_id]);

    $oyuncu_kontrol->execute([$oturum_id, $ogrenci_id]);
    $oyuncu = $oyuncu_kontrol->fetch(PDO::FETCH_ASSOC);
}

// 3. Soruları Havuzdan Çek (Öğretmenin seçtiği bölümlere VE zorluklara göre)
$bolumler_array = explode(',', $oturum['bolum_idler']);
$bolumInQuery = implode(',', array_fill(0, count($bolumler_array), '?'));

$zorluk_array = explode(',', $oturum['zorluk_seviyesi']);
$zorlukInQuery = implode(',', array_fill(0, count($zorluk_array), '?'));

$soru_sql = "SELECT * FROM quiz_sorulari WHERE zorluk_seviyesi IN ($zorlukInQuery) AND bolum_id IN ($bolumInQuery) ORDER BY RAND()";

$soru_params = array_merge($zorluk_array, $bolumler_array);
$soru_stmt = $pdo->prepare($soru_sql);
$soru_stmt->execute($soru_params);
$sorular = $soru_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Savaş Arenası ⚔️</title>
    <style>
        body {
            background: #0f172a;
            color: white;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        /* Üst Panel */
        .arena-header {
            background: #1e293b;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #3b82f6;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
        }

        .stat-box {
            background: rgba(0, 0, 0, 0.3);
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 20px;
            font-weight: bold;
            border: 1px solid #475569;
        }

        /* Zamanlayıcı Barı (Kalınlaştırılmış ve Gölgeli) */
        .timer-container {
            width: 100%;
            height: 25px;
            background: #1e293b;
            position: relative;
            border-bottom: 2px solid #0f172a;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.8);
        }

     .timer-bar { height: 100%; width: 100%; background: linear-gradient(90deg, #3b82f6, #06b6d4); transition: background-color 0.5s; box-shadow: 0 0 15px rgba(6, 182, 212, 0.5); }

        /* Orta Alan: Soru */
        .question-area {
            flex: 1;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
        }

        .question-box {
            background: rgba(30, 41, 59, 0.9);
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            border: 1px solid #475569;
            transition: 0.3s;
        }

        .question-image {
            max-height: 200px;
            max-width: 100%;
            border-radius: 8px;
            margin-bottom: 15px;
            display: none;
        }

        /* Alt Alan: Şıklar ve Güçler */
        .bottom-area {
            padding: 20px;
            background: #1e293b;
            border-top: 2px solid #475569;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            width: 100%;
            max-width: 800px;
            transition: 0.3s;
        }

        .option-btn {
            background: #334155;
            color: white;
            padding: 20px;
            font-size: 18px;
            font-weight: bold;
            border: 2px solid #64748b;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .option-btn:hover {
            background: #475569;
            transform: translateY(-2px);
        }

        /* RPG Güç Çubuğu */
        .power-bar {
            display: flex;
            gap: 15px;
        }

        .power-btn {
            background: #0f172a;
            border: 2px solid #64748b;
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }

        .power-btn:hover {
            background: #1e293b;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.2);
        }

        .power-btn.used {
            opacity: 0.4;
            cursor: not-allowed;
            border-color: #ef4444;
        }

        .p-kalkan {
            border-color: #3b82f6;
        }

        .p-kalkan:hover {
            box-shadow: 0 0 15px #3b82f6;
        }

        .p-kilic {
            border-color: #ef4444;
        }

        .p-kilic:hover {
            box-shadow: 0 0 15px #ef4444;
        }

        .p-glitch {
            border-color: #a855f7;
        }

        .p-glitch:hover {
            box-shadow: 0 0 15px #a855f7;
        }

        .p-saat {
            border-color: #f59e0b;
        }

        .p-saat:hover {
            box-shadow: 0 0 15px #f59e0b;
        }

        /* GLITCH VE RPG GÖRSEL EFEKTLERİ */
        @keyframes glitchAnim {
            0% {
                transform: translate(0);
                filter: hue-rotate(0deg);
            }

            20% {
                transform: translate(-5px, 5px);
                filter: hue-rotate(90deg) invert(10%);
            }

            40% {
                transform: translate(-5px, -5px);
                filter: blur(2px);
            }

            60% {
                transform: translate(5px, 5px);
                filter: hue-rotate(-90deg) invert(20%);
            }

            80% {
                transform: translate(5px, -5px);
                filter: blur(1px);
            }

            100% {
                transform: translate(0);
            }
        }

        @keyframes pulseAlert {
            0% {
                transform: translateX(-50%) scale(1);
            }

            50% {
                transform: translateX(-50%) scale(1.05);
            }

            100% {
                transform: translateX(-50%) scale(1);
            }
        }

        .efekt-glitch {
            animation: glitchAnim 0.3s infinite;
            border: 3px solid #a855f7 !important;
            box-shadow: 0 0 30px #a855f7 !important;
        }

        .efekt-kilic {
            border: 3px solid #ef4444 !important;
            box-shadow: 0 0 30px #ef4444 !important;
            animation: pulseAlert 1s infinite;
        }

        .efekt-saat {
            border: 3px solid #f59e0b !important;
            box-shadow: 0 0 30px #f59e0b !important;
        }

        #saldiriUyari {
            display: none;
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: bold;
            z-index: 1000;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            font-size: 18px;
            letter-spacing: 1px;
            animation: pulseAlert 1s infinite;
        }

        /* Modallar */
        #hedefModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .hedef-btn {
            background: #0f172a;
            border: 1px solid #475569;
            color: white;
            padding: 15px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: bold;
            transition: 0.2s;
        }

        .hedef-btn:hover {
            background: #3b82f6;
            border-color: #60a5fa;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        #raporModal {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            background: #1e293b;
            border-left: 5px solid #3b82f6;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            z-index: 99999;
            color: white;
            max-width: 300px;
            animation: slideIn 0.5s ease-out;
        }
    </style>
</head>

<body>
    <audio id="saatSesi" src="assets/sounds/saatsesi.mp3" loop></audio>
    <div class="arena-header">
        <div class="stat-box" style="color: #3b82f6;">👤 <?= $_SESSION['ogrenci_ad'] ?></div>
        <div class="stat-box" style="color: #22c55e;">⭐ Puan: <span id="anlikPuan"><?= $oyuncu['puan'] ?></span></div>
    </div>

    <div class="timer-container">
        <div class="timer-bar" id="timerBar"></div>
    </div>

    <div class="question-area">
        <div id="saldiriUyari">⚠️ UYARI: SALDIRI ALTINDASIN! ⚠️</div>

        <?php if (count($sorular) == 0): ?>
            <h1 style="color:#ef4444;">Bu bölümde hiç canavar (soru) yok!</h1>
        <?php else: ?>
            <div class="question-box">
                <img id="qImg" src="" class="question-image">
                <div id="qText">Soru Yükleniyor... Arenaya Hazırlan!</div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (count($sorular) > 0): ?>
        <div class="bottom-area">
            <div class="options-grid" id="optionsGrid">
                <button class="option-btn" id="btn_A" onclick="cevapVer('A')">A</button>
                <button class="option-btn" id="btn_B" onclick="cevapVer('B')">B</button>
                <button class="option-btn" id="btn_C" onclick="cevapVer('C')">C</button>
                <button class="option-btn" id="btn_D" onclick="cevapVer('D')">D</button>
            </div>

            <div class="power-bar">
                <button class="power-btn p-kalkan <?= $oyuncu['kalkan_hakki'] == 0 ? 'used' : '' ?>" onclick="gucKullan('kalkan')">🛡️ Kalkan</button>
                <button class="power-btn p-kilic <?= $oyuncu['kilic_hakki'] == 0 ? 'used' : '' ?>" onclick="hedefSecAc('kilic')">🗡️ Kılıç</button>
                <button class="power-btn p-glitch <?= $oyuncu['glitch_hakki'] == 0 ? 'used' : '' ?>" onclick="hedefSecAc('glitch')">👾 Glitch</button>
                <button class="power-btn p-saat <?= $oyuncu['saat_hakki'] == 0 ? 'used' : '' ?>" onclick="hedefSecAc('saat')">⏳ Zaman Bükücü</button>
            </div>
        </div>
    <?php endif; ?>

    <div id="hedefModal">
        <div style="background:#1e293b; border:2px solid #3b82f6; border-radius:15px; padding:30px; width:90%; max-width:500px; text-align:center; box-shadow: 0 0 30px rgba(59, 130, 246, 0.4);">
            <h2 id="hedefBaslik" style="color:#60a5fa; margin-top:0; text-transform:uppercase; letter-spacing:2px;">Hedefe Kilitlen</h2>
            <p style="color:#94a3b8; font-size:14px; margin-bottom:20px;">Saldırmak istediğin rakibini seç:</p>
            <div id="hedefListesi" style="max-height:300px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; margin-bottom:20px;"></div>
            <button onclick="document.getElementById('hedefModal').style.display='none'" style="background:#475569; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:bold;">Vazgeç</button>
        </div>
    </div>

    <div id="raporModal">
        <h3 id="raporBaslik" style="margin:0 0 10px 0; font-size:18px;">Bildirim</h3>
        <p id="raporMetin" style="margin:0; font-size:14px; color:#cbd5e1;"></p>
    </div>
  <script>
        const sorular = <?= json_encode($sorular) ?>;
        const maxSoru = sorular.length;
        let aktifSoruIndex = 0;
        let anlikPuan = <?= $oyuncu['puan'] ?>;
        
        // YENİ: DİNAMİK DEĞİŞKENLER (Her soruda değişecek)
        let soruSuresi = 20; 
        let maxSoruPuani = 20; 
        
        let kalanSure = soruSuresi;
        let soruBitisZamani = 0; 
        let zamanlayici;
        let cevapVerildi = false;

        // RPG VE SENSÖR DEĞİŞKENLERİ
        let secilenGuc = '';
        let aktifSaldiriLogId = null; 
        let saldiriDinleyici = null;

        // UI Elementleri
        const uiQText = document.getElementById('qText');
        const uiQImg = document.getElementById('qImg');
        const uiTimerBar = document.getElementById('timerBar');
        const uiPuan = document.getElementById('anlikPuan');

        // ----------------------------------------------------------------------
        // 1. SORU YÜKLEME SİSTEMİ
        // ----------------------------------------------------------------------
        function soruyuYukle() {
            if(aktifSoruIndex >= maxSoru) {
                savasiBitir();
                return;
            }

            // Saat sesini başlat
            let ses = document.getElementById('saatSesi');
            if(ses) {
                ses.currentTime = 0;
                ses.play().catch(e => console.log("İlk tıklama bekleniyor."));
            }

            let soru = sorular[aktifSoruIndex];
            
            // VERİTABANINDAN SÜRE VE PUANI ÇEK (Yoksa 20)
            soruSuresi = parseInt(soru.sure) || 20;
            maxSoruPuani = parseInt(soru.puan) || 20;

            cevapVerildi = false;
            kalanSure = soruSuresi;
            soruBitisZamani = Date.now() + (soruSuresi * 1000); 

            // Ekrana Bas
            uiQText.innerHTML = soru.soru_metni;
            if(soru.soru_medya) {
                uiQImg.src = soru.soru_medya;
                uiQImg.style.display = 'block';
            } else {
                uiQImg.style.display = 'none';
            }

            const siklar = ['A', 'B', 'C', 'D'];
            siklar.forEach(s => {
                let btn = document.getElementById('btn_' + s);
                btn.className = "option-btn"; 
                btn.style.background = '#334155';
                btn.style.borderColor = '#64748b';
                btn.style.display = 'flex'; 

                let metin = soru['cevap_' + s.toLowerCase() + '_metni'] || '';
                let resim = soru['cevap_' + s.toLowerCase() + '_medya'] || '';
                
                if(metin === '' && resim === '') {
                    btn.style.display = 'none'; 
                } else {
                    let icerik = '';
                    if(resim) icerik += `<img src="${resim}" style="max-height:100px; border-radius:5px; margin-bottom:10px;">`;
                    if(metin) icerik += `<span>${s}) ${metin}</span>`;
                    btn.innerHTML = icerik;
                }
            });

            baslatZamanlayici();
        }

        // ----------------------------------------------------------------------
        // 2. ANTİ-HİLE ZAMANLAYICI (Sekme değişse bile zaman akar)
        // ----------------------------------------------------------------------
     // ----------------------------------------------------------------------
        // 2. ANTİ-HİLE VE AKICI ZAMANLAYICI (60 FPS Milisaniye Motoru)
        // ----------------------------------------------------------------------
        function baslatZamanlayici() {
            clearInterval(zamanlayici);
            uiTimerBar.style.width = '100%';
            uiTimerBar.style.background = 'linear-gradient(90deg, #3b82f6, #06b6d4)';

            // 500ms yerine 30ms (Saniyede ~33 kare) hızında çalıştırıyoruz
            zamanlayici = setInterval(() => {
                let suAn = Date.now();
                let gercekKalanMs = soruBitisZamani - suAn;
                
                if (gercekKalanMs < 0) gercekKalanMs = 0;

                // 1. GÖRSEL İÇİN KÜSÜRATLI YÜZDE (Çubuğun su gibi akmasını sağlar)
                let yuzde = (gercekKalanMs / (soruSuresi * 1000)) * 100;
                uiTimerBar.style.width = yuzde + '%';

                // 2. OYUN MANTIĞI İÇİN TAM SANİYE (Puanlama ve Saat Gücü için)
                kalanSure = Math.ceil(gercekKalanMs / 1000);

                // Renk değişimleri (Tehlike anı)
                if(yuzde < 30) uiTimerBar.style.background = '#ef4444'; 
                else if(yuzde < 60) uiTimerBar.style.background = '#f59e0b'; 

                // Süre tamamen bittiyse
                if(gercekKalanMs <= 0) {
                    clearInterval(zamanlayici);
                    if(!cevapVerildi) cevapVer('SURE_BITTI'); 
                }
            }, 30); // 30 milisaniyede bir ekranı yenile
        }

        // ----------------------------------------------------------------------
        // 3. GELEN SALDIRILARI DİNLEYEN SENSÖR
        // ----------------------------------------------------------------------
        saldiriDinleyici = setInterval(() => {
            fetch('api/quiz_saldiri_dinle.php?oturum_id=<?= $oturum_id ?>')
            .then(res => res.json())
            .then(data => {
                if (data.var && !cevapVerildi) {
                    let saldiri = data.saldiri;
                    let yetenek = saldiri.yetenek_turu;
                    let saldiran = saldiri.saldiran_ad;
                    let durum = saldiri.durum;
                    let kazanilanKalkanPuani = saldiri.savunan_puan_etkisi;
                    
                    if (durum === 'kalkan_kirdi') {
                        bildirimGoster('🛡️ KALKANIN PARÇALANDI!', `<b>${saldiran}</b> sana kılıç attı ama kalkanın seni korudu! <b>+${kazanilanKalkanPuani} Puan</b> kazandın!`, '#10b981');
                        anlikPuan += parseInt(kazanilanKalkanPuani);
                        uiPuan.innerText = anlikPuan;
                        let kalkanBtn = document.querySelector('.p-kalkan');
                        if(kalkanBtn) { kalkanBtn.classList.add('used'); kalkanBtn.onclick = null; }
                        return; 
                    }

                    aktifSaldiriLogId = saldiri.id;
                    let uyari = document.getElementById('saldiriUyari');
                    let soruKutusu = document.querySelector('.question-box');

                    if (yetenek === 'kilic') {
                        uyari.innerHTML = `🗡️ DİKKAT! ${saldiran} sana Kılıç fırlattı!`;
                        uyari.style.background = '#ef4444';
                        soruKutusu.classList.add('efekt-kilic');
                    } 
                    else if (yetenek === 'glitch') {
                        uyari.innerHTML = `👾 HACKLENDİN! ${saldiran} ekranını bozuyor!`;
                        uyari.style.background = '#a855f7';
                        soruKutusu.classList.add('efekt-glitch');
                        
                        let grid = document.getElementById('optionsGrid');
                        for (let i = grid.children.length; i >= 0; i--) {
                            grid.appendChild(grid.children[Math.random() * i | 0]);
                        }
                    } 
                    else if (yetenek === 'saat') {
                        uyari.innerHTML = `⏳ ZAMAN BÜKÜCÜ! ${saldiran} süreni çaldı!`;
                        uyari.style.background = '#f59e0b';
                        soruKutusu.classList.add('efekt-saat');
                        
                        if (kalanSure > 10) {
                            kalanSure = 10; 
                            soruBitisZamani = Date.now() + (10 * 1000); 
                        }
                    }
                    uyari.style.display = 'block';
                }
            }).catch(e => console.log("Sensör hatası"));
        }, 1500);

        // ----------------------------------------------------------------------
        // 4. CEVAPLAMA MOTORU VE DİNAMİK PUANLAMA
        // ----------------------------------------------------------------------
        function cevapVer(secilenSik) {
            if(cevapVerildi) return;
            cevapVerildi = true;
            clearInterval(zamanlayici);
            
            let ses = document.getElementById('saatSesi');
            if(ses) ses.pause(); 

            let soru = sorular[aktifSoruIndex];
            let dogruSik = soru.dogru_cevap;

            let secilenBtn = document.getElementById('btn_' + secilenSik);
            let dogruBtn = document.getElementById('btn_' + dogruSik);

            let dogruBildi = (secilenSik === dogruSik);
            let kazanilanPuan = 0;
            
            if (dogruBildi) {
                if(secilenBtn) { secilenBtn.style.background = '#10b981'; secilenBtn.style.borderColor = '#059669'; }
                
                // DİNAMİK PUAN HESAPLAMASI (Max puana göre)
                kazanilanPuan = Math.ceil((kalanSure / soruSuresi) * maxSoruPuani);
                if(kazanilanPuan < 1) kazanilanPuan = 1;
            } else {
                if(secilenBtn) { secilenBtn.style.background = '#ef4444'; secilenBtn.style.borderColor = '#b91c1c'; }
                if(dogruBtn) { dogruBtn.style.background = '#10b981'; } 
            }

            if (aktifSaldiriLogId !== null) {
                let formData = new FormData();
                formData.append('log_id', aktifSaldiriLogId);
                formData.append('dogru_mu', dogruBildi ? 1 : 0);
                formData.append('normal_puan', kazanilanPuan);

                fetch('api/quiz_saldiri_sonuc.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    bildirimGoster(dogruBildi ? '🛡️ Savuşturuldu!' : '💥 Hasar Aldın!', data.mesaj, dogruBildi ? '#10b981' : '#ef4444');
                    anlikPuan = parseInt(data.guncel_puan);
                    uiPuan.innerText = anlikPuan;
                    saldiriEtkileriniTemizle();
                    sonrakiSoruyaGec();
                });
            } 
            else {
                if (dogruBildi) {
                    anlikPuan += kazanilanPuan;
                    uiPuan.innerText = anlikPuan;
                }
                veritabaninaPuanGonder();
                sonrakiSoruyaGec();
            }
        }

        function saldiriEtkileriniTemizle() {
            aktifSaldiriLogId = null;
            let uyari = document.getElementById('saldiriUyari');
            if(uyari) uyari.style.display = 'none';
            let soruKutusu = document.querySelector('.question-box');
            if(soruKutusu) soruKutusu.classList.remove('efekt-kilic', 'efekt-glitch', 'efekt-saat');
        }

        function sonrakiSoruyaGec() {
            setTimeout(() => {
                aktifSoruIndex++;
                saldiriEtkileriniTemizle();
                soruyuYukle();
            }, 2500);
        }

        function veritabaninaPuanGonder() {
            let formData = new FormData();
            formData.append('oturum_id', <?= $oturum_id ?>);
            formData.append('puan', anlikPuan);
            formData.append('cozulen_soru', aktifSoruIndex + 1);
            fetch('api/quiz_puan_guncelle.php', { method: 'POST', body: formData }).catch(e => console.log(e));
        }

        // ----------------------------------------------------------------------
        // 5. GÜÇ KULLANIMI VE HEDEF SEÇME MOTORU
        // ----------------------------------------------------------------------
        function hedefSecAc(gucTuru) {
            secilenGuc = gucTuru;
            let baslik = document.getElementById('hedefBaslik');
            if(gucTuru === 'kilic') baslik.innerHTML = '🗡️ Kılıç Saldırısı';
            else if(gucTuru === 'glitch') baslik.innerHTML = '👾 Glitch Gönder';
            else if(gucTuru === 'saat') baslik.innerHTML = '⏳ Zaman Bükücü';

            document.getElementById('hedefListesi').innerHTML = '<span style="color:#94a3b8;">Radarda rakipler aranıyor... 📡</span>';
            document.getElementById('hedefModal').style.display = 'flex';

            fetch('api/quiz_aktif_hedefler.php?oturum_id=<?= $oturum_id ?>')
            .then(res => res.json())
            .then(data => {
                let html = '';
                if(data.length === 0) {
                    html = '<span style="color:#ef4444;">Arenada saldırılacak kimse yok!</span>';
                } else {
                    data.forEach(rakip => {
                        html += `<button class="hedef-btn" onclick="saldiriYap(${rakip.id})">
                                    <span>${rakip.ad_soyad}</span>
                                    <span style="color:#facc15;">${rakip.puan} Puan</span>
                                 </button>`;
                    });
                }
                document.getElementById('hedefListesi').innerHTML = html;
            });
        }

        function saldiriYap(hedefId = 0) {
            let gucTuru = hedefId === 0 ? 'kalkan' : secilenGuc;
            document.getElementById('hedefModal').style.display = 'none';

            let formData = new FormData();
            formData.append('oturum_id', <?= $oturum_id ?>);
            formData.append('guc_turu', gucTuru);
            if(hedefId > 0) formData.append('hedef_id', hedefId);

            fetch('api/quiz_guc_kullan.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                document.querySelector(`.p-${gucTuru}`).classList.add('used');
                document.querySelector(`.p-${gucTuru}`).onclick = null;
                bildirimGoster(data.status === 'success' ? '⚡ Başarılı!' : '❌ Hata', data.mesaj, data.status === 'success' ? '#10b981' : '#ef4444');
                
                if(data.durum === 'kalkan_kirdi') {
                    anlikPuan -= 20;
                    document.getElementById('anlikPuan').innerText = anlikPuan;
                    veritabaninaPuanGonder();
                }
            });
        }

        function gucKullan(gucTuru) {
            if(gucTuru === 'kalkan') {
                if(!confirm('Kalkanı şimdi açmak istediğine emin misin? (Gelecek ilk saldırıyı engeller)')) return;
                saldiriYap(0);
            }
        }

        function bildirimGoster(baslik, metin, renk) {
            let modal = document.getElementById('raporModal');
            document.getElementById('raporBaslik').innerHTML = baslik;
            document.getElementById('raporBaslik').style.color = renk;
            document.getElementById('raporModal').style.borderLeftColor = renk;
            document.getElementById('raporMetin').innerHTML = metin;
            modal.style.display = 'block';
            setTimeout(() => { modal.style.display = 'none'; }, 5000);
        }

        // ----------------------------------------------------------------------
        // 6. BİTİŞ KONTROLLERİ VE ÖĞRETMEN RADARI
        // ----------------------------------------------------------------------
        function savasiBitir() {
            clearInterval(zamanlayici);
            clearInterval(saldiriDinleyici);
            let ses = document.getElementById('saatSesi');
            if(ses) ses.pause();

            document.querySelector('.question-area').innerHTML = `
                <h1 style="color:#10b981; font-size:40px;">Soru Havuzu Tükendi!</h1>
                <div style="background:#1e293b; padding:20px; border-radius:10px; margin:20px 0; border:2px solid #facc15;">
                    <h2 style="margin:0; color:#facc15;">Topladığın Puan: ${anlikPuan}</h2>
                </div>
                <p style="color:#94a3b8; font-size:20px; max-width:600px;">
                    Bekle! Öğretmen savaşı bitirene kadar diğer savaşçılar sıralamanı değiştirebilir.
                </p>
            `;
            document.querySelector('.bottom-area').style.display = 'none';
            document.querySelector('.timer-container').style.display = 'none';
        }

        let ogretmenRadari = setInterval(() => {
            fetch('api/quiz_arena_kontrol.php?oturum_id=<?= $oturum_id ?>')
            .then(res => res.json())
            .then(data => {
                if (data.durum === 'bitti') {
                    clearInterval(ogretmenRadari); 
                    clearInterval(zamanlayici); 
                    clearInterval(saldiriDinleyici);
                    let ses = document.getElementById('saatSesi');
                    if(ses) ses.pause();
                    
                    document.querySelector('.question-area').innerHTML = `
                        <h1 style="color:#ef4444; font-size:45px; text-shadow: 0 0 20px #ef4444;">🛑 SAVAŞ SONA ERDİ!</h1>
                        <div style="background:#1e293b; padding:20px; border-radius:10px; margin:20px 0; border:2px solid #facc15;">
                            <h2 style="margin:0; color:#facc15;">Meydanda Topladığın Puan: ${anlikPuan}</h2>
                        </div>
                        <p style="color:#94a3b8; font-size:18px;">Şampiyonları öğretmenin yansıttığı ana ekrandan takip et!</p>
                        <a href="ogrenci_paneli.php" style="background:#3b82f6; padding:15px 30px; color:white; text-decoration:none; border-radius:10px; font-weight:bold; margin-top:20px; display:inline-block;">⚔️ Köye Geri Dön</a>
                    `;
                    document.querySelector('.bottom-area').style.display = 'none';
                    document.querySelector('.timer-container').style.display = 'none';
                }
            }).catch(e => console.log("Radar kesintisi..."));
        }, 2000); 

        // MOTORU ÇALIŞTIR!
        if(sorular.length > 0) {
            soruyuYukle();
        }
    </script>
</body>

</html>