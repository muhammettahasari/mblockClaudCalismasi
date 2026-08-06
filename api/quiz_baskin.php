<?php
/* ====================================================================
 * MODÜL: Quiz Baskın Perdesi (HTML PARÇASI — JSON API DEĞİL)
 * DOSYA: api/quiz_baskin.php
 * BAĞLI OLDUĞU ANA DOSYALAR: ogrenci_paneli.php, profil.php, market.php
 *   (PHP include ile sayfaya gömülür)
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğretmen quiz başlattığında öğrencinin ekranını karartıp "SAVAŞ BAŞLADI"
 * yazısını gösteren, 3-2-1 geri sayım yapan ve sonunda arenaya fırlatan perde.
 *
 * AKIŞ:
 *   1. Radar "quiz.aktif = true" dediği an perde açılır, uyarı sesi çalar
 *   2. Halka daralarak 3 → 2 → 1 geri sayar
 *   3. "⚔️" işareti belirir ve quiz_arena.php'ye yönlendirilir
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Kendi setInterval'ı kaldırıldı; artık js/ogrenci_radar.js
 *    üzerindeki TEK radara abone oluyor (Bilinen Sorunlar #11).
 *  - [2026-08] Quiz artık yalnızca öğrencinin KENDİ sınıfına açıldığında
 *    tetikleniyor (#6 — sunucu tarafında filtreleniyor).
 *  - [2026-08] GERİ SAYIM EKLENDİ. Eskiden sabit 2.5 saniye bekleyip
 *    yönlendiriyordu; öğrenci ne olduğunu anlamadan kendini arenada buluyordu.
 *    Artık sayaç görünüyor ve ses eşlik ediyor (ucsaniyegerisayim.mp3 zaten
 *    projede duruyordu ama hiçbir yerde çalınmıyordu).
 *  - [2026-08] SONSUZ DÖNGÜ ÖNLENDİ: aynı oturum için perde iki kez açılmaz.
 * ==================================================================== */
?>
<div id="baskinOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:radial-gradient(circle, #450a0a, #000000); z-index:2147483647; justify-content:center; align-items:center; flex-direction:column; text-align:center;">
    <h1 style="font-size: 56px; color: #ef4444; font-family: 'Impact', 'Segoe UI', sans-serif; text-transform: uppercase; letter-spacing: 5px; text-shadow: 0 0 30px #dc2626; margin: 0; animation: pulseSavas 1s infinite;">
        ⚔️ SAVAŞ BAŞLADI! ⚔️
    </h1>

    <p id="baskinAltMetin" style="color: #cbd5e1; font-size: 22px; font-weight: bold; margin-top: 18px;">
        Öğretmen zindan kapılarını açtı. Arenaya hazırlan!
    </p>

    <!-- Geri sayım halkası -->
    <div style="margin-top: 32px; position: relative; width: 150px; height: 150px;">
        <svg width="150" height="150" style="transform: rotate(-90deg);">
            <circle cx="75" cy="75" r="66" stroke="rgba(239,68,68,.22)" stroke-width="9" fill="none"></circle>
            <circle id="baskinHalka" cx="75" cy="75" r="66" stroke="#ef4444" stroke-width="9" fill="none"
                    stroke-linecap="round" stroke-dasharray="414.7" stroke-dashoffset="0"
                    style="transition: stroke-dashoffset 1s linear;"></circle>
        </svg>
        <div id="baskinSayac" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:62px; font-weight:900; color:#fff; text-shadow:0 0 24px #ef4444;">3</div>
    </div>
</div>

<style>
    @keyframes pulseSavas {
        0%   { transform: scale(1); }
        50%  { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    @keyframes sayacZipla {
        0%   { transform: scale(1.7); opacity: 0; }
        45%  { transform: scale(1);   opacity: 1; }
        100% { transform: scale(1);   opacity: 1; }
    }
    #baskinSayac.tik { animation: sayacZipla .5s ease-out; }
</style>

<script src="<?= varlik('js/ogrenci_radar.js', '') ?>"></script>
<script>
(function () {
    'use strict';

    const GERI_SAYIM = 3;       // saniye
    const CEVRE      = 414.7;   // 2 * PI * 66 — halkanın çevresi

    let baskinBasladi = false;

    /** Sesi çal; tarayıcı engellerse sessizce geç (etkileşim izni yoksa normal) */
    function sesCal(dosya) {
        try {
            const s = new Audio('assets/sounds/' + dosya);
            s.volume = 0.75;
            s.play().catch(function () { /* tarayıcı sesi engelledi */ });
        } catch (e) { /* ses dosyası yoksa akışı bozma */ }
    }

    function perdeyiAcVeSay(oturumId) {
        const perde  = document.getElementById('baskinOverlay');
        const sayac  = document.getElementById('baskinSayac');
        const halka  = document.getElementById('baskinHalka');
        const altMet = document.getElementById('baskinAltMetin');

        perde.style.display = 'flex';
        sesCal('ogretmenuyari.mp3');

        let kalan = GERI_SAYIM;
        sayac.textContent = kalan;
        halka.style.strokeDashoffset = 0;

        // İlk tık sesi ve halkanın ilk dilimi
        setTimeout(function () {
            sesCal('ucsaniyegerisayim.mp3');
            halka.style.strokeDashoffset = (CEVRE / GERI_SAYIM).toFixed(1);
        }, 60);

        const zamanlayici = setInterval(function () {
            kalan--;

            if (kalan > 0) {
                sayac.textContent = kalan;
                sayac.classList.remove('tik');
                void sayac.offsetWidth;      // animasyonu baştan başlat
                sayac.classList.add('tik');
                halka.style.strokeDashoffset =
                    (CEVRE * (GERI_SAYIM - kalan + 1) / GERI_SAYIM).toFixed(1);
                return;
            }

            clearInterval(zamanlayici);

            sayac.textContent = '⚔️';
            altMet.textContent = 'Arenaya çekiliyorsun…';
            halka.style.strokeDashoffset = CEVRE;

            setTimeout(function () {
                window.location.href = 'quiz_arena.php?oturum=' + oturumId;
            }, 650);

        }, 1000);
    }

    /* Birleşik radara abone ol — ayrı bir setInterval yok */
    mBlockRadar.abone(function (veri) {
        if (baskinBasladi || !veri || !veri.quiz || !veri.quiz.aktif) return;

        const oturumId = veri.quiz.oturum_id;
        if (!oturumId) return;

        /* AYNI OTURUM İÇİN PERDEYİ İKİ KEZ AÇMA.
           Öğrenci arenadan panele geri dönerse (quiz hâlâ aktifken) radar
           yine "aktif" der. Bu kontrol olmasaydı öğrenci sonsuz döngüye
           girer, panele hiç bakamazdı. */
        let gorulen = null;
        try { gorulen = sessionStorage.getItem('quiz_baskin_gorulen'); } catch (e) {}

        if (String(gorulen) === String(oturumId)) return;

        try { sessionStorage.setItem('quiz_baskin_gorulen', String(oturumId)); } catch (e) {}

        baskinBasladi = true;
        mBlockRadar.durdur();
        perdeyiAcVeSay(oturumId);
    });
})();
</script>
