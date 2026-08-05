/**
 * Dosya Yolu: v2_gorev_sistemi/assets/js/ogrenci/kostum_atolyesi/renk_ve_gradyan.js
 * Kullanıcı Yetkisi ve Bağlı Olduğu Sayfa: Öğrenci / Kostüm Atölyesi
 * Halk Dilinde İşlevi: Atölyedeki nesnelerin renklerini, renk geçişlerini (gradyan) ve damlalık (renk seçici) aracını kontrol eden arka plan motorudur.
 * Temel İşlevler:
 * - Düz ve gradyan renk verilerini tutar ve sahnedeki nesnelere uygular.
 * - Renk paneli arayüzündeki çubukları, durakları (stop) ve renk seçim işlemlerini çizer.
 * - Damlalık aracı ile sahneden renk kopyalama işlemlerini yönetir.
 * YENİ EKLENEN ÖZELLİKLER:
 */

window.renkVerisi = {
  dolgu: {
    tip: "duz",
    renk: "#3b82f6",
    gradyan: [
      { c: "#3b82f6", p: 0 },
      { c: "#1e293b", p: 1 },
    ],
    seciliDurak: 0,
  },
  cizgi: {
    tip: "duz",
    renk: "#1e293b",
    gradyan: [
      { c: "#1e293b", p: 0 },
      { c: "#ef4444", p: 1 },
    ],
    seciliDurak: 0,
  },
};

window.renkPaneliAc = function (hedef) {
  document.getElementById("popoverDolgu")?.classList.remove("aktif");
  document.getElementById("popoverCizgi")?.classList.remove("aktif");
  document
    .getElementById("popover" + (hedef === "dolgu" ? "Dolgu" : "Cizgi"))
    ?.classList.add("aktif");
  window.gradyanArayuzunuCiz(hedef);
};

document.addEventListener("mousedown", function (e) {
  if (!e.target.closest("#grupDolgu") && !e.target.closest("#grupCizgi")) {
    document.getElementById("popoverDolgu")?.classList.remove("aktif");
    document.getElementById("popoverCizgi")?.classList.remove("aktif");
  }
});

window.renkTipiSec = function (hedef, tip) {
  window.renkVerisi[hedef].tip = tip;
  let popover = document.getElementById(
    "popover" + (hedef === "dolgu" ? "Dolgu" : "Cizgi"),
  );
  if (!popover) return;

  popover
    .querySelectorAll(".arac-btn")
    .forEach((b) => b.classList.remove("aktif"));
  popover
    .querySelector(`.arac-btn[onclick*="'${tip}'"]`)
    ?.classList.add("aktif");

  document.getElementById(
    `icerik${hedef === "dolgu" ? "Dolgu" : "Cizgi"}_duz`,
  ).style.display = tip === "duz" ? "block" : "none";
  document.getElementById(
    `icerik${hedef === "dolgu" ? "Dolgu" : "Cizgi"}_gradyan`,
  ).style.display = tip === "linear" || tip === "radial" ? "block" : "none";

  let anaBtn = document.getElementById(
    `btnGorsel${hedef === "dolgu" ? "Dolgu" : "Cizgi"}`,
  );
  if (tip === "yok") {
    anaBtn.className = "renk-btn-gorsel seffaf";
    anaBtn.style.background = "";
  } else if (tip === "duz") {
    anaBtn.className = "renk-btn-gorsel";
    anaBtn.style.background = window.renkVerisi[hedef].renk;
  } else {
    anaBtn.className = "renk-btn-gorsel";
    anaBtn.style.background = `linear-gradient(to right, ${window.renkVerisi[hedef].gradyan[0].c}, ${window.renkVerisi[hedef].gradyan[window.renkVerisi[hedef].gradyan.length - 1].c})`;
  }
  if (!window.uiSenkronModu) {
    window.seciliObjelereUygula();
  }
};

window.gradyanArayuzunuCiz = function (hedef) {
  let data = window.renkVerisi[hedef];
  let duraklarAlani = document.getElementById(
    `duraklar${hedef === "dolgu" ? "Dolgu" : "Cizgi"}`,
  );
  let cubuk = document.getElementById(
    `cubukGorsel${hedef === "dolgu" ? "Dolgu" : "Cizgi"}`,
  );
  if (!duraklarAlani || !cubuk) return;

  let siraliGrad = data.gradyan
    .map((g) => ({ ...g, p: window.gradyanPozisyonSinirla(g.p) }))
    .sort((a, b) => a.p - b.p);
  let cssGrad = siraliGrad.map((g) => `${g.c} ${g.p * 100}%`).join(", ");
  cubuk.style.background = `linear-gradient(to right, ${cssGrad})`;

  duraklarAlani.innerHTML = "";
  data.gradyan.forEach((g, originalIndex) => {
    let btn = document.createElement("div");
    btn.className =
      "gradyan-durak" + (data.seciliDurak === originalIndex ? " secili" : "");
    let guvenliP = window.gradyanPozisyonSinirla(g.p);
    btn.style.left = guvenliP * 100 + "%";
    btn.style.backgroundColor = g.c;

    function zıtRenk(hex) {
      if (!hex) return "#000";
      hex = hex.replace("#", "");
      let r = parseInt(hex.substr(0, 2), 16);
      let g = parseInt(hex.substr(2, 2), 16);
      let b = parseInt(hex.substr(4, 2), 16);
      let luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
      return luminance > 0.6 ? "#000000" : "#ffffff";
    }

    let borderColor = zıtRenk(g.c);
    btn.style.border = "2px solid " + borderColor;
    btn.style.boxShadow = "0 0 0 1px " + borderColor;
    btn.style.borderRadius = "50%";
    btn.style.width = "10px";
    btn.style.height = "10px";
    btn.onmousedown = function (e) {
      e.stopPropagation();
      data.seciliDurak = originalIndex;
      window.aktifGradyanDuzenleme = {
        hedef: hedef,
        stopIndex: originalIndex,
        mod: "beklemede",
      };
      document.getElementById(
        `renk${hedef === "dolgu" ? "Dolgu" : "Cizgi"}Durak`,
      ).value = g.c;
      Array.from(duraklarAlani.children).forEach((c) =>
        c.classList.remove("secili"),
      );
      btn.classList.add("secili");

      let kapsayici = btn.parentElement;
      let startX = e.clientX,
        startY = e.clientY,
        startP = g.p,
        suruklendi = false;

      document.onmousemove = function (me) {
        let deltaX = Math.abs(me.clientX - startX);
        let deltaY = Math.abs(me.clientY - startY);
        if (deltaX > 8 || deltaY > 8) suruklendi = true;

        if (me.clientY - startY > 40 && data.gradyan.length > 2) {
          data.gradyan.splice(originalIndex, 1);
          data.seciliDurak = 0;
          window.aktifGradyanDuzenleme = null;
          document.onmousemove = null;
          document.onmouseup = null;
          window.gradyanArayuzunuCiz(hedef);
          window.seciliObjelereUygula();
          return;
        }
        if (!suruklendi) return;
        let rect = kapsayici.getBoundingClientRect();
        let p = startP + (me.clientX - startX) / rect.width;
        p = window.gradyanPozisyonSinirla(p);
        data.gradyan[originalIndex].p = p;
        btn.style.left = p * 100 + "%";
        let guncelCssGrad = data.gradyan
          .map((gx) => ({ ...gx, p: window.gradyanPozisyonSinirla(gx.p) }))
          .sort((a, b) => a.p - b.p)
          .map((gx) => `${gx.c} ${gx.p * 100}%`)
          .join(", ");
        cubuk.style.background = `linear-gradient(to right, ${guncelCssGrad})`;
        window.seciliObjelereUygula();
      };
      document.onmouseup = function () {
        document.onmousemove = null;
        document.onmouseup = null;
        if (suruklendi) window.gradyanArayuzunuCiz(hedef);
      };
    };

    btn.ondblclick = function (e) {
      e.preventDefault();
      e.stopPropagation();
      window.gradyanStopDuzenlemeBaslat(hedef, originalIndex, "pickcolor");
      let renkInput = document.getElementById(
        `renk${hedef === "dolgu" ? "Dolgu" : "Cizgi"}Durak`,
      );
      if (renkInput) {
        renkInput.focus();
        renkInput.click();
      }
    };
    btn.onclick = function (e) {
      e.stopPropagation();
    };
    duraklarAlani.appendChild(btn);
  });
  if (data.gradyan[data.seciliDurak]) {
    let ipt = document.getElementById(
      `renk${hedef === "dolgu" ? "Dolgu" : "Cizgi"}Durak`,
    );
    if (ipt) ipt.value = data.gradyan[data.seciliDurak].c;
  }
};

window.gradyanDurakEkle = function (e, hedef) {
  let kapsayici = e.currentTarget;
  let tiklananDurak = e.target.closest(".gradyan-durak");
  if (tiklananDurak) return;
  let rect = kapsayici.getBoundingClientRect();
  let p = window.gradyanPozisyonSinirla((e.clientX - rect.left) / rect.width);
  window.renkVerisi[hedef].gradyan.push({ c: "#ffffff", p: p });
  window.renkVerisi[hedef].seciliDurak =
    window.renkVerisi[hedef].gradyan.length - 1;
  window.gradyanArayuzunuCiz(hedef);
  window.seciliObjelereUygula();
};

window.seciliDurakRenginiGuncelle = function (hedef) {
  let data = window.renkVerisi[hedef];
  let yeniRenk = document.getElementById(
    `renk${hedef === "dolgu" ? "Dolgu" : "Cizgi"}Durak`,
  ).value;
  if (!data.gradyan[data.seciliDurak]) return;
  data.gradyan[data.seciliDurak].c = yeniRenk;
  window.gradyanArayuzunuCiz(hedef);
  window.renkTipiSec(hedef, data.tip);
  window.seciliObjelereUygula();
  if (
    window.aktifGradyanDuzenleme &&
    window.aktifGradyanDuzenleme.mod === "pickcolor"
  )
    window.pickColorCursorGuncelle(false);
};

window.getPaperColor = function (hedef, bounds, itemData) {
  let data = window.renkVerisi[hedef];
  if (data.tip === "yok") return null;
  if (data.tip === "duz" || !bounds) return data.renk;
  let pGrad = new paper.Gradient();
  pGrad.stops = data.gradyan
    .map((g) => ({ c: g.c, p: window.gradyanPozisyonSinirla(g.p) }))
    .sort((a, b) => a.p - b.p)
    .map((g) => new paper.GradientStop(g.c, g.p));
  pGrad.radial = data.tip === "radial";
  let origin = bounds.leftCenter,
    dest = bounds.rightCenter;
  if (data.tip === "radial") {
    origin = bounds.center;
    dest = bounds.rightCenter;
  }
  if (itemData && itemData.gradOrigin && itemData.gradDest) {
    origin = itemData.gradOrigin;
    dest = itemData.gradDest;
  }
  if (origin && dest && origin.equals(dest)) dest = dest.add([1, 0]);
  return new paper.Color(pGrad, origin, dest);
};

window.renkGuncelle = function (hedef) {
  let yeniRenk = document.getElementById(
    `renk${hedef === "dolgu" ? "Dolgu" : "Cizgi"}Duz`,
  ).value;
  window.renkVerisi[hedef].renk = yeniRenk;
  window.renkVerisi[hedef].tip = "duz";
  document.getElementById(
    `btnGorsel${hedef === "dolgu" ? "Dolgu" : "Cizgi"}`,
  ).style.background = yeniRenk;
  window.seciliObjelereUygula();
};

window.seciliObjelereUygula = function () {
  let uygulanacaklar = [];
  if (window.seciliOge && window.seciliOge.parent !== window.uiKatmani)
    uygulanacaklar.push(window.seciliOge);
  if (paper && paper.project && paper.project.selectedItems) {
    paper.project.selectedItems.forEach((item) => {
      if (
        !uygulanacaklar.includes(item) &&
        item.parent !== window.uiKatmani &&
        item.className !== "Group"
      )
        uygulanacaklar.push(item);
    });
  }
  uygulanacaklar.forEach((item) => {
    if (item.className !== "Group") {
      let newFill = window.getPaperColor("dolgu", item.bounds, item.data);
      if (
        newFill &&
        newFill.gradient &&
        item.fillColor &&
        item.fillColor.gradient
      ) {
        newFill.origin = item.fillColor.origin;
        newFill.destination = item.fillColor.destination;
      }
      item.fillColor = newFill;
      item.strokeColor =
        window.getPaperColor("cizgi", item.bounds, item.data) || "#1e293b";
    }
  });
  if (paper && paper.view) paper.view.update();
};

window.arayuzRenkleriniGuncelle = function (item) {
  if (!item) return;
  window.uiSenkronModu = true;
  try {
    function cssGradientFromStops(stops) {
      return `linear-gradient(to right, ${stops.map((s) => `${s.c} ${Math.round(s.p * 100)}%`).join(", ")})`;
    }
    function setRenkButonu(hedef, mod) {
      let btn = document.getElementById(
        hedef === "dolgu" ? "btnGorselDolgu" : "btnGorselCizgi",
      );
      if (!btn) return;
      if (mod === "yok") {
        btn.style.background = "";
        btn.classList.add("seffaf");
        return;
      }
      btn.classList.remove("seffaf");
      if (window.renkVerisi[hedef].tip === "duz") {
        btn.style.background = window.renkVerisi[hedef].renk || "";
      } else {
        let stops = window.renkVerisi[hedef].gradyan || [];
        if (stops.length > 0)
          btn.style.background = cssGradientFromStops(stops);
      }
    }
    function fillOrStrokeToUI(hedef, colorObj) {
      let duzInput = document.getElementById(
        hedef === "dolgu" ? "renkDolguDuz" : "renkCizgiDuz",
      );
      let durakInput = document.getElementById(
        hedef === "dolgu" ? "renkDolguDurak" : "renkCizgiDurak",
      );
      if (!colorObj) {
        window.renkVerisi[hedef].tip = "yok";
        window.renkVerisi[hedef].seciliDurak = 0;
        setRenkButonu(hedef, "yok");
        if (typeof window.renkTipiSec === "function")
          window.renkTipiSec(hedef, "yok");
        return;
      }
      if (colorObj.gradient) {
        let stops = colorObj.gradient.stops.map((s) => ({
          c: s.color.toCSS(true),
          p: typeof s.offset === "number" ? s.offset : 0,
        }));
        window.renkVerisi[hedef].gradyan = stops;
        window.renkVerisi[hedef].seciliDurak = 0;
        window.renkVerisi[hedef].tip = colorObj.gradient.radial
          ? "radial"
          : "linear";
        if (durakInput && stops[0]) durakInput.value = stops[0].c;
        if (typeof window.renkTipiSec === "function")
          window.renkTipiSec(hedef, window.renkVerisi[hedef].tip);
        setRenkButonu(hedef, "gradient");
        if (typeof window.gradyanArayuzunuCiz === "function")
          window.gradyanArayuzunuCiz(hedef);
        return;
      }
      let hex = colorObj.toCSS(true);
      window.renkVerisi[hedef].renk = hex;
      window.renkVerisi[hedef].tip = "duz";
      window.renkVerisi[hedef].seciliDurak = 0;
      if (duzInput) duzInput.value = hex;
      if (typeof window.renkTipiSec === "function")
        window.renkTipiSec(hedef, "duz");
      setRenkButonu(hedef, "duz");
      if (typeof window.gradyanArayuzunuCiz === "function")
        window.gradyanArayuzunuCiz(hedef);
    }
    fillOrStrokeToUI("dolgu", item.fillColor);
    fillOrStrokeToUI("cizgi", item.strokeColor);
    let kalinlikInput = document.getElementById("ayarKalinlik");
    let kalinlikSlider = document.getElementById("ayarKalinlikSlider");
    let kalinlikDegeri =
      typeof item.strokeWidth === "number" && !isNaN(item.strokeWidth)
        ? item.strokeWidth
        : 1;
    window.shapeKalinlik = kalinlikDegeri;
    if (kalinlikInput) kalinlikInput.value = kalinlikDegeri;
    if (kalinlikSlider) kalinlikSlider.value = kalinlikDegeri;
    let cizgiTipiInput = document.getElementById("ayarCizgiTipi");
    if (cizgiTipiInput) {
      let dashed =
        Array.isArray(item.dashArray) &&
        item.dashArray.length > 0 &&
        item.dashArray.some((n) => Number(n) > 0);
      cizgiTipiInput.value = dashed ? "dashed" : "solid";
    }
    if (typeof window.gradyanArayuzunuCiz === "function") {
      window.gradyanArayuzunuCiz("dolgu");
      window.gradyanArayuzunuCiz("cizgi");
    }
  } finally {
    window.uiSenkronModu = false;
  }
};

window.degradeYolu = null;
window.degradeBas = null;
window.degradeSon = null;
window.degradeNoktalari = [];

window.degradeUIGizle = function () {
  if (window.degradeYolu) {
    window.degradeYolu.remove();
    window.degradeYolu = null;
  }
  if (window.degradeBas) {
    window.degradeBas.remove();
    window.degradeBas = null;
  }
  if (window.degradeSon) {
    window.degradeSon.remove();
    window.degradeSon = null;
  }
  if (paper && paper.view) paper.view.update();
};

window.degradeUICiz = function () {
  window.degradeUIGizle();
  if (
    window.aktifArac !== "gradyan" ||
    !paper ||
    !paper.project ||
    paper.project.selectedItems.length === 0
  )
    return;
  let obj = paper.project.selectedItems[0];
  if (!obj.fillColor || !obj.fillColor.gradient) return;
  let origin = obj.fillColor.origin;
  let dest = obj.fillColor.destination;
  if (!origin || !dest) return;
  if (!window.uiKatmani) window.uiKatmani = new paper.Group();

  window.degradeYolu = new paper.Group({ parent: window.uiKatmani });
  window.degradeYolu.addChild(
    new paper.Path.Line({
      from: origin,
      to: dest,
      strokeColor: "black",
      strokeWidth: 1.5,
    }),
  );
  window.degradeYolu.addChild(
    new paper.Path.Line({
      from: origin,
      to: dest,
      strokeColor: "white",
      strokeWidth: 0.5,
    }),
  );
  window.degradeBas = new paper.Path.Circle({
    center: origin,
    radius: 5,
    fillColor: "white",
    strokeColor: "black",
    strokeWidth: 1.5,
    parent: window.uiKatmani,
  });
  window.degradeSon = new paper.Path.Rectangle({
    center: dest,
    size: [10, 10],
    fillColor: "black",
    parent: window.uiKatmani,
  });
  window.uiKatmani.bringToFront();
  paper.view.update();
};

window.gradyanPozisyonSinirla = function (p) {
  p = Number(p);
  if (!isFinite(p)) return 0;
  return Math.max(0, Math.min(1, p));
};

window.gradyanStopDuzenlemeBaslat = function (
  hedef,
  stopIndex,
  mod = "beklemede",
) {
  window.renkVerisi[hedef].seciliDurak = stopIndex;
  window.aktifGradyanDuzenleme = {
    hedef: hedef,
    stopIndex: stopIndex,
    mod: mod,
  };
  window.gradyanArayuzunuCiz(hedef);
  let renkInput = document.getElementById(
    `renk${hedef === "dolgu" ? "Dolgu" : "Cizgi"}Durak`,
  );
  if (renkInput && window.renkVerisi[hedef].gradyan[stopIndex]) {
    renkInput.value = window.renkVerisi[hedef].gradyan[stopIndex].c;
  }
  window.pickColorCursorGuncelle(false);
};

window.gradyanStopDuzenlemeKapat = function (
  vAracinaDon = false,
  paneliKapat = false,
) {
  window.aktifGradyanDuzenleme = null;
  if (vAracinaDon) window.aracSec("secim");
  else if (paper && paper.view) paper.view.update();
  if (paneliKapat) {
    document.getElementById("popoverDolgu")?.classList.remove("aktif");
    document.getElementById("popoverCizgi")?.classList.remove("aktif");
  }
  window.pickColorCursorGuncelle(false);
};

window.gradyanStopRengiUygula = function (hedef, stopIndex, renk) {
  if (!window.renkVerisi[hedef] || !window.renkVerisi[hedef].gradyan[stopIndex])
    return;
  window.renkVerisi[hedef].gradyan[stopIndex].c = renk;
  window.renkVerisi[hedef].seciliDurak = stopIndex;
  let inputId = `renk${hedef === "dolgu" ? "Dolgu" : "Cizgi"}Durak`;
  let renkInput = document.getElementById(inputId);
  if (renkInput) renkInput.value = renk;
  window.gradyanArayuzunuCiz(hedef);
  window.renkTipiSec(hedef, window.renkVerisi[hedef].tip);
  window.seciliObjelereUygula();
};

window.sahnedenRenkAlVeStopaUygula = function (item) {
  if (!window.aktifGradyanDuzenleme) return { durum: "yok", uygulandi: false };
  if (!item || !item.fillColor) return { durum: "gecersiz", uygulandi: false };
  if (item.fillColor.gradient) return { durum: "gradient", uygulandi: false };
  let renk = item.fillColor.toCSS(true);
  window.gradyanStopRengiUygula(
    window.aktifGradyanDuzenleme.hedef,
    window.aktifGradyanDuzenleme.stopIndex,
    renk,
  );
  return { durum: "tamam", uygulandi: true };
};

window.pickColorSahneUstunde = false;

window.pickColorCursorAc = function () {
  let canvasEl = document.getElementById("cizimTuvali");
  if (!canvasEl) return;
  canvasEl.style.cursor =
    'url("/mblock_platform/v2_gorev_sistemi/assets/images/imlecler/eyedropper.svg") 2 28, default';
};

window.pickColorCursorDoluAc = function () {
  let canvasEl = document.getElementById("cizimTuvali");
  if (!canvasEl) return;
  canvasEl.style.cursor =
    'url("/mblock_platform/v2_gorev_sistemi/assets/images/imlecler/eyedropperfill.svg") 2 28, default';
};

window.pickColorCursorNormalAracaDon = function () {
  let canvasEl = document.getElementById("cizimTuvali");
  if (!canvasEl) return;
  if (window.aktifArac === "el") canvasEl.style.cursor = "grab";
  else if (window.aktifArac === "metin") canvasEl.style.cursor = "text";
  else canvasEl.style.cursor = "default";
};

window.pickColorCursorGuncelle = function (mouseDown = false) {
  let canvasEl = document.getElementById("cizimTuvali");
  if (!canvasEl) return;
  if (
    window.aktifGradyanDuzenleme &&
    window.aktifGradyanDuzenleme.mod === "pickcolor"
  ) {
    if (window.pickColorSahneUstunde) {
      if (mouseDown) window.pickColorCursorDoluAc();
      else window.pickColorCursorAc();
    } else window.pickColorCursorNormalAracaDon();
  } else {
    window.pickColorCursorNormalAracaDon();
  }
};
