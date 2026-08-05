/* ====================================================================
 * MODÜL: Öğrenci Birleşik Radar İstemcisi
 * DOSYA YOLU: js/ogrenci_radar.js
 * BAĞLI OLDUĞU ANA DOSYA: api/ogrenci_radar.php
 * KULLANAN SAYFALAR: ogrenci_paneli.php, profil.php, market.php
 *   (bilesenler/duello_radari.php, api/quiz_baskin.php,
 *    js/skorboard_motoru.js, bilesenler/ogrenci_modal.php buna abone olur)
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğrenci sayfasında arka planda dönen TEK kalp atışı. Eskiden her modül
 * kendi kendine sunucuya soruyordu (düello, quiz, arena, öğretmen onayı =
 * 4 ayrı istek, 3 saniyede bir). Artık tek istek atılıyor, gelen cevap
 * abone olan modüllere dağıtılıyor.
 *
 * KULLANIMI:
 *   mBlockRadar.abone(function (veri) {
 *       if (veri.quiz.aktif) { ... }
 *   });
 *   mBlockRadar.durdur();   // sayfadan ayrılırken / yönlendirme öncesi
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (Bilinen Sorunlar #11).
 * ==================================================================== */

// Bu dosya birden fazla bileşen tarafından include edilebilir
// (quiz_baskin.php, ogrenci_modal.php, sayfa başlıkları...).
// İkinci kez yüklenirse mevcut radarı EZMEMELİ — yoksa aboneler kaybolur.
window.mBlockRadar = window.mBlockRadar || (function () {
  "use strict";

  const ARALIK_MS = 3000;

  let aboneler = [];
  let zamanlayici = null;
  let calisiyor = false;
  let sonVeri = null;

  function tarama() {
    // Önceki istek hâlâ sürüyorsa üst üste bindirme
    if (calisiyor) return;
    calisiyor = true;

    fetch("api/ogrenci_radar.php?v=" + Date.now(), { credentials: "same-origin" })
      .then((res) => (res.ok ? res.json() : null))
      .then((veri) => {
        calisiyor = false;
        if (!veri) return;

        sonVeri = veri;
        aboneler.forEach(function (geriCagirma) {
          try {
            geriCagirma(veri);
          } catch (e) {
            console.log("Radar abonesi hata verdi:", e);
          }
        });
      })
      .catch(function () {
        calisiyor = false; // Ağ kesintisi — sessizce bir sonraki turu bekle
      });
  }

  function baslat() {
    if (zamanlayici) return;
    tarama(); // İlk taramayı hemen yap
    zamanlayici = setInterval(tarama, ARALIK_MS);
  }

  function durdur() {
    if (zamanlayici) {
      clearInterval(zamanlayici);
      zamanlayici = null;
    }
  }

  // Sekme arka plana alındığında boşuna sorgu atma (pil ve sunucu dostu)
  document.addEventListener("visibilitychange", function () {
    if (document.hidden) {
      durdur();
    } else {
      baslat();
    }
  });

  return {
    abone: function (geriCagirma) {
      if (typeof geriCagirma !== "function") return;
      aboneler.push(geriCagirma);

      // Geç abone olanlara elimizdeki son veriyi hemen ver
      if (sonVeri) {
        try {
          geriCagirma(sonVeri);
        } catch (e) {
          /* yoksay */
        }
      }
      baslat();
    },
    durdur: durdur,
    baslat: baslat,
    sonVeri: function () {
      return sonVeri;
    },
  };
})();
