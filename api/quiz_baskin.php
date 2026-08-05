<?php
/* ====================================================================
 * MODÜL: Quiz Baskın Perdesi (HTML PARÇASI — JSON API DEĞİL)
 * DOSYA: api/quiz_baskin.php
 * BAĞLI OLDUĞU ANA DOSYALAR: ogrenci_paneli.php, profil.php, market.php
 *   (PHP include ile sayfaya gömülür)
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğretmen quiz başlattığında öğrencinin ekranını karartıp "SAVAŞ BAŞLADI"
 * yazısını gösteren ve 2.5 saniye sonra arenaya fırlatan perde.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Kendi setInterval'ı kaldırıldı; artık js/ogrenci_radar.js
 *    üzerindeki TEK radara abone oluyor (Bilinen Sorunlar #11).
 *  - [2026-08] Quiz artık yalnızca öğrencinin KENDİ sınıfına açıldığında
 *    tetikleniyor (#6 — sunucu tarafında filtreleniyor).
 * ==================================================================== */
?>
<div id="baskinOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:radial-gradient(circle, #450a0a, #000000); z-index:2147483647; justify-content:center; align-items:center; flex-direction:column; text-align:center;">
    <h1 style="font-size: 60px; color: #ef4444; font-family: 'Impact', sans-serif; text-transform: uppercase; letter-spacing: 5px; text-shadow: 0 0 30px #dc2626; margin: 0; animation: pulseSavas 1s infinite;">
        ⚔️ SAVAŞ BAŞLADI! ⚔️
    </h1>
    <p style="color: #cbd5e1; font-size: 24px; font-weight: bold; margin-top: 20px;">
        Öğretmen zindan kapılarını açtı. Arenaya çekiliyorsunuz...
    </p>
</div>

<style>
    @keyframes pulseSavas {
        0%   { transform: scale(1); }
        50%  { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
</style>

<script src="js/ogrenci_radar.js"></script>
<script>
    (function () {
        let baskinBasladi = false;

        // Birleşik radara abone ol — artık ayrı bir setInterval yok
        mBlockRadar.abone(function (veri) {
            if (baskinBasladi || !veri.quiz || !veri.quiz.aktif) return;

            baskinBasladi = true;
            mBlockRadar.durdur();

            new Audio('assets/sounds/ogretmenuyari.mp3')
                .play()
                .catch(function () { /* tarayıcı sesi engelledi */ });

            document.getElementById('baskinOverlay').style.display = 'flex';

            setTimeout(function () {
                window.location.href = 'quiz_arena.php?oturum=' + veri.quiz.oturum_id;
            }, 2500);
        });
    })();
</script>
