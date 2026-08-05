/**
 * Dosya Yolu: v2_gorev_sistemi/assets/js/ogrenci/kostum_atolyesi.js
 * Kullanıcı Yetkisi ve Bağlı Olduğu Sayfa: Öğrenci / Kostüm Atölyesi
 * Halk Dilinde İşlevi: Paper.js çizim motorunu başlatan ve fare hareketlerini alt modüllere (Araçlar, Seçim) aktaran ana çekirdek santraldir.
 * Temel İşlevler:
 * - Tuval (Canvas) işlemlerini başlatır.
 * - Farenin Tıklama (MouseDown), Sürükleme (Drag) ve Bırakma (MouseUp) olaylarını algılar.
 * - Alt modüllerdeki özellikleri tetikler.
 * YENİ EKLENEN ÖZELLİKLER:
 */

window.aktifArac = "secim";
window.mevcutYol = null;
window.hayaletYol = null;
window.seciliOge = null;
window.ogeTipi = null;
window.atolyeGecmis = [];
window.gecmisAdim = -1;
window.aktifMetinKutusu = null;
window.aktifMetinNoktasi = null;
window.kopyalananOgeler = null;
window.transformKutusu = null;
window.secimKutusu = null;
window.altKopyaOrijinalleri = null;
window.altKopyaYapildi = false;
window.altKopyaKlonlari = null;
window.fircaImlecHalkasi = null;
window.fircaImlecAktif = false;
window.uiSenkronModu = false;
window.penEklemeBaslangicNoktasi = null;
window.penSuruklemeBasladi = false;
window.penAltCurveData = null;
window.penGeciciEgrilik = null;
window.shapeKalinlik = 3;
window.fircaKalinlik = 2;
window.silgiKalinlik = 35;
window.seciliPenModu = 'normal';
window.aktifKostumId = null;
 

window.penModuDegistir = function (mod) {
  window.seciliPenModu = mod;
  let btnGrup = document.getElementById("grupPenModlari");
  if (btnGrup) {
    btnGrup
      .querySelectorAll(".arac-btn")
      .forEach((btn) => btn.classList.remove("aktif"));
    let aktifBtn = document.getElementById(
      "btnPen" + mod.charAt(0).toUpperCase() + mod.slice(1),
    );
    if (aktifBtn) aktifBtn.classList.add("aktif");
  }
};
// // ===================================== satırının üzerinde olacak
window.kostumAtolyesiniAc = function () {
  window.aktifKuklaOlcegi = null; // DÜZELTME: Tüm dosyalar görsün diye 'window' eklendi
  if (!window.aktifKuklaId) return alert("Lütfen bir kukla seçin!");
  
  document.getElementById("kostumAtolyesiModal").style.display = "flex";
  if (!paper.project) { paper.setup("cizimTuvali"); window.motoruBaslat(); }
  let seciliKukla = window.projeKuklalari.find((k) => k.id === window.aktifKuklaId);
  
  if (!seciliKukla.kostumler || seciliKukla.kostumler.length === 0) {
      seciliKukla.kostumler = [{ id: "k_" + Date.now(), adi: "costume1", resimYolu: seciliKukla.resimYolu, cizimData: null }];
  }
  
  // İlk kostümü aktif yap ve sistemi başlat
  window.aktifKostumId = seciliKukla.kostumler[0].id;
  window.kostumSec(window.aktifKostumId, true); 
};

 
window.motoruBaslat = function () {
  let wrapper = document.getElementById("atolyeCanvasWrapper");
  paper.view.viewSize = new paper.Size(
    wrapper.clientWidth,
    wrapper.clientHeight,
  );
  // Düğüm ve Bezier kontrol noktalarını daha görünür yap
  paper.settings.handleSize = 8;

  // Hit test varsayılan toleransını artır
  paper.settings.hitTolerance = 16;

  window.artboard = new paper.Path.Rectangle({
    point: [0, 0],
    size: [480, 360],
    fillColor: "#ffffff",
    shadowColor: new paper.Color(0, 0, 0, 0.15),
    shadowBlur: 15,
    shadowOffset: new paper.Point(0, 5),
  });
  window.artboard.locked = true;

  let kilavuz = new paper.Group([
    new paper.Path.Line([235, 180], [245, 180]),
    new paper.Path.Line([240, 175], [240, 185]),
  ]);
  kilavuz.strokeColor = "#cbd5e1";
  kilavuz.locked = true;

  window.cizimKatmani = new paper.Group();
  window.uiKatmani = new paper.Group();
  window.atolyeEkranaSigdir();
  setTimeout(function () {
    if (typeof window.atolyeZoomKontrolKonumla === "function") {
      window.atolyeZoomKontrolKonumla();
    }
  }, 0);

  let canvasEl = document.getElementById("cizimTuvali");
  if (canvasEl) {
    canvasEl.addEventListener("mouseenter", function () {
      window.pickColorSahneUstunde = true;
      window.pickColorCursorGuncelle(false);
      if (window.aktifArac === "firca" || window.aktifArac === "silgi")
        window.fircaImleciniGoster();
    });
    canvasEl.addEventListener("mouseleave", function () {
      window.pickColorSahneUstunde = false;
      window.pickColorCursorGuncelle(false);
      window.fircaImleciniGizle();
    });
    canvasEl.addEventListener("mousedown", function () {
      if (
        window.aktifGradyanDuzenleme &&
        window.aktifGradyanDuzenleme.mod === "pickcolor"
      )
        window.pickColorCursorGuncelle(true);
    });
    canvasEl.addEventListener("mouseup", function () {
      if (
        window.aktifGradyanDuzenleme &&
        window.aktifGradyanDuzenleme.mod === "pickcolor"
      )
        window.pickColorCursorGuncelle(false);
    });
  }

  let cizgiTipiInput = document.getElementById("ayarCizgiTipi");
  if (cizgiTipiInput && !cizgiTipiInput.dataset.boundStrokeSync) {
    let cizgiTipiHandler = function () {
      window.seciliNesnelereCizgiStiliUygula();
    };
    cizgiTipiInput.addEventListener("change", cizgiTipiHandler);
    cizgiTipiInput.addEventListener("input", cizgiTipiHandler);
    cizgiTipiInput.dataset.boundStrokeSync = "1";
  }

  let kalinlikInput = document.getElementById("ayarKalinlik");
  let kalinlikSlider = document.getElementById("ayarKalinlikSlider");
  function kalinlikHandler(e) {
    let deger = parseInt(e.target.value, 10);
    if (isNaN(deger)) deger = 3;
    if (window.aktifArac === "firca") window.fircaKalinlik = deger;
    else if (window.aktifArac === "silgi") window.silgiKalinlik = deger;
    else window.shapeKalinlik = deger;
    if (kalinlikInput) kalinlikInput.value = deger;
    if (kalinlikSlider) kalinlikSlider.value = deger;
    if (typeof window.fircaImlecBoyutunuGuncelle === "function")
      window.fircaImlecBoyutunuGuncelle();
    if (
      typeof window.seciliNesnelereCizgiStiliUygula === "function" &&
      window.aktifArac !== "firca" &&
      window.aktifArac !== "silgi"
    )
      window.seciliNesnelereCizgiStiliUygula();
  }

  if (kalinlikInput && !kalinlikInput.dataset.bound) {
    kalinlikInput.addEventListener("input", kalinlikHandler);
    kalinlikInput.addEventListener("change", kalinlikHandler);
    kalinlikInput.dataset.bound = "1";
  }
  if (kalinlikSlider && !kalinlikSlider.dataset.bound) {
    kalinlikSlider.addEventListener("input", kalinlikHandler);
    kalinlikSlider.addEventListener("change", kalinlikHandler);
    kalinlikSlider.dataset.bound = "1";
  }

  paper.view.onMouseDown = function (event) {
    if (window.spaceBasili || window.aktifArac === "el") return;
    if (window.aktifMetinKutusu) {
      let metinEklendiMi = window.metinKutusunuKapatVeCiz();
      if (metinEklendiMi) window.aracSec("secim");
      return;
    }

    let pt = window.snapHesapla(event.point);
    let hit = window.cizimKatmani.hitTest(pt, {
      segments: true,
      handles: true,
      stroke: true,
      fill: true,
      tolerance: window.aktifArac === "nokta" ? 18 : 8,
    });
    let hitKalem =
      window.aktifArac === "kalem"
        ? window.cizimKatmani.hitTest(pt, {
            segments: true,
            handles: true,
            stroke: true,
            fill: false,
            tolerance: 16,
          })
        : null;

    if (
      window.aktifGradyanDuzenleme &&
      window.aktifGradyanDuzenleme.mod === "pickcolor"
    ) {
      if (hit && hit.item && hit.item.parent !== window.uiKatmani) {
        window.sahnedenRenkAlVeStopaUygula(hit.item);
        return;
      } else return;
    }

    let hitUI = window.uiKatmani
      ? window.uiKatmani.hitTest(event.point, {
          fill: true,
          stroke: true,
          tolerance: 12,
        })
      : null;
    let isUIHandle = false,
      uiItemName = "";
    if (hitUI && hitUI.item) {
      uiItemName =
        hitUI.item.name || (hitUI.item.parent ? hitUI.item.parent.name : "");
      if (
        [
          "tl",
          "tc",
          "tr",
          "cl",
          "cr",
          "bl",
          "bc",
          "br",
          "rot",
          "pivotBtn",
        ].includes(uiItemName)
      )
        isUIHandle = true;
    }

    let gecerliAraclar = [
      "kalem",
      "firca",
      "silgi",
      "kutu",
      "daire",
      "cokgen",
      "duz_cizgi",
      "olcum",
      "gradyan",
    ];
    if (!hit && !isUIHandle && !gecerliAraclar.includes(window.aktifArac))
      window.tumSecimleriTemizle();

   // SADECE NOKTA ARACI İLE DÜĞÜM DÜZENLEME
// Pen Tool'a dokunmaz.
// Mevcut şekle tıklayınca düğümleri ve Bezier kollarını açar.
// Çizgi üzerine tıklayınca yeni düğüm ekler ve o düğümde de Bezier kolu oluşturur.
if (window.aktifArac === "nokta") {
  if (hit && hit.item && hit.item.className !== "PointText") {
    let hedefPath = hit.item;

    if (!hedefPath || hedefPath.className !== "Path") {
      return;
    }

    window.tumSecimleriTemizle();

    // Çizgi / curve üzerine tıklanınca yeni düğüm ekle
    if ((hit.type === "stroke" || hit.type === "curve") && hit.location) {
      hedefPath.selected = true;
      hedefPath.fullySelected = true;

      let yeniSeg = hedefPath.insert(hit.location.index + 1, pt);

      if (typeof window.noktaAraciBezierKollariniAc === "function") {
        window.noktaAraciBezierKollariniAc(hedefPath);
      }

      hedefPath.selected = true;
      hedefPath.fullySelected = true;

      window.ogeTipi = "segment";
      window.seciliOge = yeniSeg;

      if (paper && paper.view) {
        paper.view.update();
      }

      return;
    }

    // Şekle / düğüme tıklanınca tüm düğümler ve Bezier kolları görünür olsun
    if (typeof window.noktaAraciBezierKollariniAc === "function") {
      window.noktaAraciBezierKollariniAc(hedefPath);
    } else {
      hedefPath.selected = true;
      hedefPath.fullySelected = true;
    }

    if (hit.type === "segment") {
      window.ogeTipi = "segment";
      window.seciliOge = hit.segment;
      return;
    }

    if (hit.type === "handle-in") {
      window.seciliOge = hit.segment;
      window.ogeTipi = "handle-in";
      return;
    }

    if (hit.type === "handle-out") {
      window.seciliOge = hit.segment;
      window.ogeTipi = "handle-out";
      return;
    }

    window.seciliOge = hedefPath;
    window.ogeTipi = "item";
  }

  return;
}

    // ANA SEÇİM ARACI MOTORU (Çoklu Seçim Destekli)
    if (window.aktifArac === "secim") {
      if (
        isUIHandle &&
        window.transformKutusu &&
        (window.transformKutusu.hedefOge ||
          (window.transformKutusu.hedefOgeler &&
            window.transformKutusu.hedefOgeler.length > 0))
      ) {
        window.seciliOge =
          window.transformKutusu.hedefOge ||
          window.transformKutusu.hedefOgeler[0];
        window.ogeTipi = "transform_" + uiItemName;
        window.transformBaslangicNoktasi = event.point;
        let ilkB = window.transformKutusu.children["cerceve"].bounds.clone();
        if (window.transformKutusu.cokluSecim) {
          window.transformPivot =
            window.transformKutusu.children["pivotBtn"].position.clone();
        } else if (
          window.altBasili &&
          uiItemName !== "rot" &&
          uiItemName !== "pivotBtn"
        ) {
          window.transformPivot = ilkB.center.clone();
          if (uiItemName.includes("l")) window.transformPivot.x = ilkB.right;
          else if (uiItemName.includes("r"))
            window.transformPivot.x = ilkB.left;
          if (uiItemName.includes("t")) window.transformPivot.y = ilkB.bottom;
          else if (uiItemName.includes("b")) window.transformPivot.y = ilkB.top;
        } else {
          window.transformPivot =
            window.seciliOge.data && window.seciliOge.data.customPivot
              ? window.seciliOge.data.customPivot.clone()
              : ilkB.center.clone();
        }
        window.transformStartDistX = event.point.x - window.transformPivot.x;
        window.transformStartDistY = event.point.y - window.transformPivot.y;
        window.transformSonScaleX = 1;
        window.transformSonScaleY = 1;
        window.transformSonAci = event.point.subtract(
          window.transformPivot,
        ).angle;
        return;
      }

      if (
        hit &&
        hit.item &&
        hit.item.parent !== window.uiKatmani &&
        !hit.item.locked
      ) {
        // Ctrl veya Shift basılıysa tıklanan objeyi listeye ekle/çıkar (Toggle)
        if (window.shiftBasili || window.ctrlBasili) {
          hit.item.fullySelected = false;
          hit.item.selected = !hit.item.selected;
        } else {
          if (!hit.item.selected) {
            window.tumSecimleriTemizle();
            hit.item.fullySelected = false;
            hit.item.selected = true;
          }
        }

        window.seciliOge = hit.item;
        window.ogeTipi = "item";
        let secilenler = paper.project.selectedItems;

        if (secilenler.length >= 1) {
          // Çoklu şekilleri saran transform kutusunu çiz
          window.seciliOge = secilenler[secilenler.length - 1];
          window.TransformKutusuOlustur(window.seciliOge);
          if (
            secilenler.length === 1 &&
            typeof window.arayuzRenkleriniGuncelle === "function"
          )
            window.arayuzRenkleriniGuncelle(window.seciliOge);
        } else {
          // Eğer Ctrl ile son şekli de seçimden çıkardıysak kutuyu gizle
          window.tumSecimleriTemizle();
        }
      } else if (!isUIHandle) {
        if (!window.shiftBasili && !window.ctrlBasili)
          window.tumSecimleriTemizle();
        window.baslangicNoktasi = pt;
        window.ogeTipi = "marquee";
        if (window.secimKutusu) {
          window.secimKutusu.remove();
          window.secimKutusu = null;
        }
        window.secimKutusu = new paper.Path.Rectangle({
          from: pt,
          to: pt,
          strokeColor: "#3b82f6",
          strokeWidth: 1,
          dashArray: [4, 4],
          fillColor: new paper.Color(0.23, 0.51, 0.96, 0.08),
        });
        if (window.uiKatmani) window.uiKatmani.addChild(window.secimKutusu);
      }
    } else if (window.aktifArac === "gradyan") {
      let hitUI = window.uiKatmani
        ? window.uiKatmani.hitTest(pt, {
            fill: true,
            stroke: true,
            tolerance: 8,
          })
        : null;
      if (hitUI && hitUI.item && paper.project.selectedItems.length > 0) {
        let obj = paper.project.selectedItems[0];
        window.seciliOge = obj;
        if (window.degradeBas && hitUI.item === window.degradeBas) {
          window.ogeTipi = "gradyan_origin";
          return;
        }
        if (window.degradeSon && hitUI.item === window.degradeSon) {
          window.ogeTipi = "gradyan_dest";
          return;
        }
        if (window.degradeYolu && hitUI.item.parent === window.degradeYolu) {
          window.ogeTipi = "gradyan_dest";
          return;
        }
      }
      if (
        hit &&
        hit.item &&
        hit.item.fillColor &&
        hit.item.fillColor.gradient
      ) {
        window.tumSecimleriTemizle();
        hit.item.selected = true;
        window.seciliOge = hit.item;
        window.ogeTipi = "gradyan_yeni";
        window.baslangicNoktasi = pt;
        if (!hit.item.data) hit.item.data = {};
        hit.item.data.gradOrigin = pt;
        hit.item.data.gradDest = pt.add(new paper.Point(1, 1));
        hit.item.fillColor = window.getPaperColor(
          "dolgu",
          hit.item.bounds,
          hit.item.data,
        );
        if (window.degradeUICiz) window.degradeUICiz();
      } else {
        window.tumSecimleriTemizle();
      }
    } else if (window.aktifArac === "kalem") {
      if (
        !window.mevcutYol &&
        hitKalem &&
        hitKalem.item &&
        hitKalem.item.className === "Path" &&
        hitKalem.item.parent !== window.uiKatmani
      ) {
        let hedefPath = hitKalem.item;
        if (hitKalem.type === "segment") {
          let seg = hitKalem.segment;

          // YENİ: Hem klavyeyi hem UI butonunu kontrol et
          let kCorner = window.ctrlBasili || window.seciliPenModu === "corner";
          let kSmooth = window.shiftBasili || window.seciliPenModu === "smooth";
          let kCurve = window.altBasili || window.seciliPenModu === "curve";

          if (kCorner) {
            window.seciliOge = seg;
            window.ogeTipi = "pen_corner_drag";
            window.penEklemeBaslangicNoktasi = seg.point.clone();
            window.penSuruklemeBasladi = false;
            return;
          }

          if (kSmooth) {
            window.seciliOge = seg;
            window.ogeTipi = "pen_smooth_drag";
            window.penEklemeBaslangicNoktasi = seg.point.clone();
            window.penSuruklemeBasladi = false;
            return;
          }

          if (kCurve) {
            window.seciliOge = seg;
            window.ogeTipi = "pen_alt_curve_drag";
            window.penSuruklemeBasladi = false;
            window.penEklemeBaslangicNoktasi = seg.point.clone();
            window.penAltCurveData = {
              mode: "existing_segment",
              merkezSeg: seg,
              merkez0: seg.point.clone(),
              handleIn0: seg.handleIn.clone(),
              handleOut0: seg.handleOut.clone(),
            };
            return;
          }

          // Hiçbir mod aktif değilse (Normal) noktayı sil
          if (hedefPath.segments.length > 2) {
            hedefPath.removeSegment(seg.index);
            hedefPath.selected = true;
            hedefPath.fullySelected = true;
            window.seciliOge = hedefPath;
            window.ogeTipi = "item";
            TransformKutusuOlustur(window.seciliOge);
            durumuKaydet();
          }
          return;
        }
        if (
          (hitKalem.type === "stroke" || hitKalem.type === "curve") &&
          hitKalem.location
        ) {
          hedefPath.selected = true;
          hedefPath.fullySelected = true;

          // YENİ: Hem klavyeyi hem UI butonunu kontrol et
          let kCorner = window.ctrlBasili || window.seciliPenModu === "corner";
          let kSmooth = window.shiftBasili || window.seciliPenModu === "smooth";
          let kCurve = window.altBasili || window.seciliPenModu === "curve";

          if (kCurve) {
            let yeniSeg = hedefPath.insert(hitKalem.location.index + 1, pt);
            yeniSeg.handleIn = new paper.Point(0, 0);
            yeniSeg.handleOut = new paper.Point(0, 0);

            window.seciliOge = yeniSeg;
            window.ogeTipi = "pen_alt_curve_drag";
            window.penSuruklemeBasladi = false;
            window.penEklemeBaslangicNoktasi = pt.clone();

            window.penAltCurveData = {
              mode: "new_segment",
              merkezSeg: yeniSeg,
              merkez0: yeniSeg.point.clone(),
              handleIn0: yeniSeg.handleIn.clone(),
              handleOut0: yeniSeg.handleOut.clone(),
            };
            return;
          }

          let yeniSeg = hedefPath.insert(hitKalem.location.index + 1, pt);
          yeniSeg.handleIn = new paper.Point(0, 0);
          yeniSeg.handleOut = new paper.Point(0, 0);

          window.seciliOge = yeniSeg;
          window.ogeTipi = "pen_yeni_nokta";
          window.penEklemeBaslangicNoktasi = pt.clone();
          window.penSuruklemeBasladi = false;

          if (kCorner) {
            window.ogeTipi = "pen_corner_drag";
          } else if (kSmooth) {
            window.ogeTipi = "pen_smooth_drag";
          }

          durumuKaydet();
          return;
        }
      }
      if (
        window.mevcutYol &&
        window.mevcutYol.segments.length > 1 &&
        pt.getDistance(window.mevcutYol.firstSegment.point) < 10
      ) {
        window.mevcutYol.closed = true;
        window.mevcutYol.fullySelected = false;
        window.mevcutYol.fillColor = window.getPaperColor(
          "dolgu",
          window.mevcutYol.bounds,
        );
        window.tumSecimleriTemizle();
        window.mevcutYol.selected = true;
        window.seciliOge = window.mevcutYol;
        window.ogeTipi = "item";
        window.TransformKutusuOlustur(window.seciliOge);
        window.mevcutYol = null;
        if (window.hayaletYol) window.hayaletYol.remove();
        window.durumuKaydet();
        window.aracSec("secim");
        return;
      }
      if (
        window.altBasili &&
        hit &&
        (hit.type === "handle-out" || hit.type === "handle-in") &&
        hit.item === window.mevcutYol
      ) {
        window.seciliOge = hit.segment;
        window.ogeTipi = hit.type;
        return;
      }
      if (
        window.mevcutYol &&
        window.mevcutYol.segments.length > 0 &&
        pt.getDistance(window.mevcutYol.lastSegment.point) < 8
      ) {
        window.mevcutYol.lastSegment.handleOut = new paper.Point(0, 0);
        if (window.hayaletYol) window.hayaletYol.remove();
        return;
      }
      if (!window.mevcutYol) {
        window.tumSecimleriTemizle();
        window.mevcutYol = new paper.Path({
          strokeColor: window.getPaperColor("cizgi", null),
          strokeWidth: window.getStrokeWidth(),
          dashArray: window.getDashArray(),
          fullySelected: true,
        });
        window.cizimKatmani.addChild(window.mevcutYol);
      }
      let yeniSeg = window.mevcutYol.add(pt);
      yeniSeg.handleIn = new paper.Point(0, 0);
      yeniSeg.handleOut = new paper.Point(0, 0);

      window.seciliOge = yeniSeg;
      window.ogeTipi = "pen_yeni_nokta";
      window.penEklemeBaslangicNoktasi = pt.clone();
      window.penSuruklemeBasladi = false;

      let kCorner = window.ctrlBasili || window.seciliPenModu === "corner";
      let kSmooth = window.shiftBasili || window.seciliPenModu === "smooth";
      let kCurve = window.altBasili || window.seciliPenModu === "curve";

      if (kCorner) {
        window.ogeTipi = "pen_corner_drag";
      } else if (kSmooth || kCurve) {
        window.ogeTipi = "pen_smooth_drag";
      }
    } else if (window.aktifArac === "firca") {
      window.tumSecimleriTemizle();
      window.mevcutYol = new paper.Path({
        strokeColor: window.getPaperColor("cizgi", null),
        strokeWidth: window.getStrokeWidth(),
        dashArray: window.getDashArray(),
        strokeCap: "round",
        strokeJoin: "round",
      });
      window.mevcutYol.add(pt);
      window.cizimKatmani.addChild(window.mevcutYol);
    } else if (window.aktifArac === "silgi") {
      window.tumSecimleriTemizle();
      window.mevcutYol = new paper.Path({
        strokeColor: new paper.Color(0, 0, 0, 0.18),
        strokeWidth: window.getStrokeWidth(),
        strokeCap: "round",
        strokeJoin: "round",
        dashArray: [],
        guide: true,
        selected: false,
        fullySelected: false,
      });
      window.mevcutYol.add(pt);
      window.cizimKatmani.addChild(window.mevcutYol);
    } else if (
      ["kutu", "daire", "cokgen", "duz_cizgi", "olcum"].includes(
        window.aktifArac,
      )
    ) {
      window.tumSecimleriTemizle();
      window.baslangicNoktasi = pt;
    } else if (window.aktifArac === "metin") {
      let hitText = window.cizimKatmani.hitTest(pt, {
        fill: true,
        tolerance: 8,
      });
      if (hitText && hitText.item && hitText.item.className === "PointText") {
        window.metniDuzenle(hitText.item);
        return;
      }
      window.tumSecimleriTemizle();
      window.olusturMetinKutusu(pt);
    }
  };

  paper.view.onMouseDrag = function (event) {
    if (window.aktifArac === "firca" || window.aktifArac === "silgi") {
      window.fircaImlecKonumunuGuncelle(event);
      window.fircaImleciniGoster();
    }
    if (window.spaceBasili || window.aktifArac === "el") {
      document.getElementById("cizimTuvali").style.cursor = "grabbing";
      paper.view.center = paper.view.center.subtract(event.delta);
      return;
    }

    let pt = window.snapHesapla(event.point);
    if (
      window.aktifArac === "secim" &&
      window.ogeTipi === "marquee" &&
      window.secimKutusu
    ) {
      window.secimKutusu.remove();
      window.secimKutusu = new paper.Path.Rectangle({
        from: window.baslangicNoktasi,
        to: pt,
        strokeColor: "#3b82f6",
        strokeWidth: 1,
        dashArray: [4, 4],
        fillColor: new paper.Color(0.23, 0.51, 0.96, 0.08),
      });
      if (window.uiKatmani) window.uiKatmani.addChild(window.secimKutusu);
      return;
    }

    if (
      window.aktifArac === "gradyan" &&
      window.seciliOge &&
      window.ogeTipi &&
      window.ogeTipi.startsWith("gradyan")
    ) {
      let obj = window.seciliOge;
      if (!obj.data) obj.data = {};
      if (
        window.ogeTipi === "gradyan_yeni" ||
        window.ogeTipi === "gradyan_dest"
      ) {
        obj.data.gradDest = pt;
        if (window.ogeTipi === "gradyan_yeni")
          obj.data.gradOrigin = window.baslangicNoktasi;
      } else if (window.ogeTipi === "gradyan_origin") {
        obj.data.gradOrigin = pt;
      }
      obj.fillColor = window.getPaperColor("dolgu", obj.bounds, obj.data);
      window.degradeUICiz();
      return;
    }

    if (
      !window.seciliOge &&
      ![
        "firca",
        "silgi",
        "kutu",
        "daire",
        "cokgen",
        "duz_cizgi",
        "olcum",
      ].includes(window.aktifArac)
    )
      return;

    if (
      window.ogeTipi &&
      window.ogeTipi.startsWith("transform_") &&
      window.seciliOge
    ) {
      let handleName = window.ogeTipi.split("_")[1];
      let pivot = window.transformPivot;
      let hedefler =
        window.transformKutusu &&
        window.transformKutusu.hedefOgeler &&
        window.transformKutusu.hedefOgeler.length > 0
          ? window.transformKutusu.hedefOgeler
          : [window.seciliOge];

      if (handleName === "pivotBtn") {
        if (window.transformKutusu.cokluSecim) {
          window.transformKutusu.children["pivotBtn"].position = pt;
          window.transformPivot = pt.clone();
        } else {
          if (!window.seciliOge.data) window.seciliOge.data = {};
          window.seciliOge.data.customPivot = pt;
          window.TransformKutusuOlustur(window.seciliOge);
        }
        return;
      }
      if (handleName === "rot") {
        let currentAngle = event.point.subtract(pivot).angle;
        let stepAngle = currentAngle - window.transformSonAci;
        hedefler.forEach((item) => {
          item.rotate(stepAngle, pivot);
          if (item.data && item.data.customPivot)
            item.data.customPivot = item.data.customPivot.rotate(
              stepAngle,
              pivot,
            );
        });
        window.transformSonAci = currentAngle;
        window.TransformKutusuOlustur(window.seciliOge);
        return;
      }
      if (handleName !== "cerceve") {
        let currDistX = event.point.x - pivot.x,
          currDistY = event.point.y - pivot.y;
        let targetSx = 1,
          targetSy = 1;
        if (
          Math.abs(window.transformStartDistX) > 0.1 &&
          (handleName.includes("l") || handleName.includes("r"))
        )
          targetSx = currDistX / window.transformStartDistX;
        if (
          Math.abs(window.transformStartDistY) > 0.1 &&
          (handleName.includes("t") || handleName.includes("b"))
        )
          targetSy = currDistY / window.transformStartDistY;
        if (window.shiftBasili && handleName.length === 2) {
          let maxScale = Math.max(targetSx, targetSy);
          targetSx = maxScale;
          targetSy = maxScale;
        }
       let stepSx = targetSx / window.transformSonScaleX,
          stepSy = targetSy / window.transformSonScaleY;
        
        // Ortalama ölçek faktörünü hesapla (çizgilerin orantılı incelmesi için)
        let ortalamaScale = (Math.abs(stepSx) + Math.abs(stepSy)) / 2;

        hedefler.forEach((item) => {
          item.scale(stepSx, stepSy, pivot);
          
          // Çizgi kalınlıklarını ölçek oranına göre dinamik olarak güncelle
          if (item.strokeWidth) {
              item.strokeWidth = Math.max(0.5, item.strokeWidth * ortalamaScale);
          }
          // Eğer grup içindeki alt yollar taranacaksa onları da güncelle
          if (item.children && item.children.length > 0) {
              item.children.forEach(function(child) {
                  if (child.strokeWidth) {
                      child.strokeWidth = Math.max(0.5, child.strokeWidth * ortalamaScale);
                  }
              });
          }

          if (item.data && item.data.customPivot) {
            let v = item.data.customPivot.subtract(pivot);
            item.data.customPivot = new paper.Point(
              pivot.x + v.x * stepSx,
              pivot.y + v.y * stepSy,
            );
          }
        });
        window.transformSonScaleX = targetSx;
        window.transformSonScaleY = targetSy;
        window.TransformKutusuOlustur(window.seciliOge);
      }
      return;
    }

    if (window.ogeTipi === "pen_yeni_nokta") {
      let bas =
        window.penEklemeBaslangicNoktasi || window.seciliOge.point.clone();
      let v = pt.subtract(bas);
      window.seciliOge.point = bas;
      window.seciliOge.handleOut = v.multiply(0.5);
      window.seciliOge.handleIn = v.multiply(-0.5);
      if (window.seciliOge.path) {
        window.seciliOge.path.selected = true;
        window.seciliOge.path.fullySelected = true;
      }
      window.penSuruklemeBasladi = true;
    } else if (window.ogeTipi === "pen_corner_drag") {
      window.seciliOge.point = pt;
      window.seciliOge.handleIn = new paper.Point(0, 0);
      window.seciliOge.handleOut = new paper.Point(0, 0);
      window.penSuruklemeBasladi = true;
    } else if (window.ogeTipi === "pen_alt_curve_drag") {
      let d = window.penAltCurveData;
      if (!d || !d.merkezSeg) return;
      let seg = d.merkezSeg,
        merkez0 = d.merkez0.clone(),
        prev = seg.previous,
        next = seg.next;
      if (!prev || !next) {
        seg.point = merkez0.clone();
        seg.handleIn = d.handleIn0.clone();
        seg.handleOut = d.handleOut0.clone();
        window.penSuruklemeBasladi = true;
        return;
      }
      let kenarTanjant = next.point.subtract(prev.point);
      if (kenarTanjant.length < 0.001) return;
      kenarTanjant = kenarTanjant.normalize();
      let kenarNormali = new paper.Point(-kenarTanjant.y, kenarTanjant.x);
      let cekim = pt.subtract(merkez0);
      if (cekim.dot(kenarNormali) < 0) kenarNormali = kenarNormali.multiply(-1);
      let disariMesafe = Math.max(0, cekim.dot(kenarNormali));
      let ilerleme = Math.pow(disariMesafe, 0.9);
      seg.point = merkez0.add(kenarNormali.multiply(ilerleme));
      let taban = Math.min(
          seg.point.getDistance(prev.point),
          seg.point.getDistance(next.point),
        ),
        ease = Math.pow(disariMesafe, 0.82),
        kol = Math.max(8, taban * 0.1 + ease * 0.16);
      seg.handleOut = kenarTanjant.multiply(kol);
      seg.handleIn = kenarTanjant.multiply(-kol);
      window.penSuruklemeBasladi = true;
    } else if (window.ogeTipi === "pen_smooth_drag") {
      let seg = window.seciliOge,
        merkez = seg.point.clone(),
        prev = seg.previous,
        next = seg.next,
        cekim = pt.subtract(merkez),
        uzunluk = cekim.length;
      if (uzunluk < 2) {
        seg.handleIn = new paper.Point(0, 0);
        seg.handleOut = new paper.Point(0, 0);
        window.penSuruklemeBasladi = true;
      } else {
        let yon = null;
        if (prev && next) {
          let v1 = prev.point.subtract(merkez),
            v2 = next.point.subtract(merkez);
          if (v1.length > 0.001 && v2.length > 0.001) {
            let n1 = v1.normalize(),
              n2 = v2.normalize();
            yon = n1.add(n2);
            if (yon.length < 0.001) yon = n2.subtract(n1);
          }
        } else if (prev) {
          let v1 = merkez.subtract(prev.point);
          if (v1.length > 0.001) yon = v1.normalize();
        } else if (next) {
          let v2 = next.point.subtract(merkez);
          if (v2.length > 0.001) yon = v2.normalize();
        }
        if (!yon || yon.length < 0.001) yon = cekim.normalize();
        else yon = yon.normalize();
        if (cekim.dot(yon) < 0) yon = yon.multiply(-1);
        seg.handleOut = yon.multiply(uzunluk * 0.5);
        seg.handleIn = yon.multiply(-uzunluk * 0.5);
        window.penSuruklemeBasladi = true;
      }
    } else if (window.ogeTipi === "pen_single_handle_drag") {
      let bas =
        window.penEklemeBaslangicNoktasi || window.seciliOge.point.clone();
      let v = pt.subtract(bas);
      window.seciliOge.point = bas;
      window.seciliOge.handleOut = v;
      window.seciliOge.handleIn = new paper.Point(0, 0);
      window.penSuruklemeBasladi = true;
    } else if (window.ogeTipi === "segment")
      window.seciliOge.point = window.seciliOge.point.add(event.delta);
    else if (window.ogeTipi === "handle-in") {
      window.seciliOge.handleIn = window.seciliOge.handleIn.add(event.delta);
      if (!window.altBasili)
        window.seciliOge.handleOut = window.seciliOge.handleIn.multiply(-1);
    } else if (window.ogeTipi === "handle-out") {
      window.seciliOge.handleOut = window.seciliOge.handleOut.add(event.delta);
      if (!window.altBasili)
        window.seciliOge.handleIn = window.seciliOge.handleOut.multiply(-1);
    } else if (window.ogeTipi === "item" && window.aktifArac !== "kalem") {
      let itemsToMove =
        paper.project.selectedItems.length > 0
          ? paper.project.selectedItems
          : [window.seciliOge];
      if (
        window.aktifArac === "secim" &&
        window.altBasili &&
        !window.altKopyaYapildi
      ) {
        let pivottaMi = false;
        if (
          window.transformKutusu &&
          window.transformKutusu.children["pivotBtn"]
        )
          pivottaMi = window.transformKutusu.children[
            "pivotBtn"
          ].bounds.contains(event.point);
        if (!pivottaMi) {
          let klonlar = window.altKopyaHazirla();
          if (klonlar && klonlar.length > 0) itemsToMove = klonlar;
        }
      }
      itemsToMove =
        paper.project.selectedItems.length > 0
          ? paper.project.selectedItems
          : itemsToMove;
      itemsToMove = (itemsToMove || []).filter(
        (item) =>
          item &&
          !item.removed &&
          item.parent &&
          item.position &&
          typeof item.position.add === "function",
      );
      if (itemsToMove.length === 0) {
        window.seciliOge = null;
        window.ogeTipi = null;
        return;
      }
      itemsToMove.forEach((item) => {
        item.position = item.position.add(event.delta);
        if (item.data && item.data.customPivot)
          item.data.customPivot = item.data.customPivot.add(event.delta);
      });
      if (
        window.seciliOge &&
        !window.seciliOge.removed &&
        window.transformKutusu
      )
        window.TransformKutusuOlustur(window.seciliOge);
    }

    if (
      (window.aktifArac === "firca" || window.aktifArac === "silgi") &&
      window.mevcutYol
    )
      window.mevcutYol.add(pt);
    else if (window.aktifArac === "kutu") {
      if (window.geciciSekil) window.geciciSekil.remove();
      window.geciciSekil = new paper.Path.Rectangle(
        new paper.Rectangle(window.baslangicNoktasi, pt),
      );
      window.geciciSekil.strokeWidth = window.getStrokeWidth();
      window.geciciSekil.dashArray = window.getDashArray();
    } else if (window.aktifArac === "daire") {
      if (window.geciciSekil) window.geciciSekil.remove();
      window.geciciSekil = new paper.Path.Circle(
        window.baslangicNoktasi,
        window.baslangicNoktasi.getDistance(pt),
      );
      window.geciciSekil.strokeWidth = window.getStrokeWidth();
      window.geciciSekil.dashArray = window.getDashArray();
    } else if (window.aktifArac === "cokgen") {
      if (window.geciciSekil) window.geciciSekil.remove();
      window.geciciSekil = new paper.Path.RegularPolygon(
        window.baslangicNoktasi,
        6,
        window.baslangicNoktasi.getDistance(pt),
      );
      window.geciciSekil.strokeWidth = window.getStrokeWidth();
      window.geciciSekil.dashArray = window.getDashArray();
    } else if (window.aktifArac === "duz_cizgi") {
      if (window.geciciSekil) window.geciciSekil.remove();
      window.geciciSekil = new paper.Path.Line(window.baslangicNoktasi, pt);
      window.geciciSekil.strokeWidth = window.getStrokeWidth();
      window.geciciSekil.dashArray = window.getDashArray();
    } else if (window.aktifArac === "olcum") {
      if (window.geciciSekil) window.geciciSekil.remove();
      if (window.olcumMetni) window.olcumMetni.remove();
      let pxMesafe = window.baslangicNoktasi.getDistance(pt),
        birimSecici = document.getElementById("ayarOlcumBirim"),
        birim = birimSecici ? birimSecici.value : "px",
        degerText = "";
      if (birim === "cm") degerText = (pxMesafe / 37.795).toFixed(2) + " cm";
      else if (birim === "mm")
        degerText = (pxMesafe / 3.7795).toFixed(1) + " mm";
      else degerText = Math.round(pxMesafe) + " px";
      window.geciciSekil = new paper.Path.Line({
        from: window.baslangicNoktasi,
        to: pt,
        strokeColor: "#ef4444",
        strokeWidth: 2,
        dashArray: [4, 4],
      });
      window.olcumMetni = new paper.PointText({
        point: pt.add([10, -10]),
        content: degerText,
        fillColor: "#ef4444",
        fontSize: 12,
        fontWeight: "bold",
      });
    }
    if (
      window.geciciSekil &&
      ["kutu", "daire", "cokgen", "duz_cizgi"].includes(window.aktifArac)
    ) {
      window.geciciSekil.strokeColor = "#3b82f6";
      window.geciciSekil.fillColor = null;
    }
  };

  paper.view.onMouseMove = function (event) {
    if (window.aktifArac === "firca" || window.aktifArac === "silgi") {
      window.fircaImlecKonumunuGuncelle(event);
      window.fircaImleciniGoster();
    } else window.fircaImleciniGizle();

    if (window.aktifArac === "kalem") {
      let pt = window.snapHesapla(event.point);
      let hitPen = window.cizimKatmani.hitTest(pt, {
        segments: true,
        stroke: true,
        fill: false,
        tolerance: 8,
      });
      if (hitPen && hitPen.type === "segment")
        window.kalemCursorTipiniGuncelle("minus");
      else if (
        hitPen &&
        (hitPen.type === "stroke" || hitPen.type === "curve") &&
        hitPen.item &&
        hitPen.item.className === "Path"
      )
        window.kalemCursorTipiniGuncelle("plus");
      else window.kalemCursorTipiniGuncelle("normal");
    }

    if (
      window.aktifArac === "kalem" &&
      window.mevcutYol &&
      window.mevcutYol.segments.length > 0 &&
      !window.ctrlBasili
    ) {
      if (window.hayaletYol) window.hayaletYol.remove();
      window.hayaletYol = new paper.Path({
        strokeColor: window.getPaperColor("cizgi", null) || "#1e293b",
        strokeWidth: window.getStrokeWidth(),
        opacity: 0.5,
        dashArray: window.getDashArray(),
      });
      window.hayaletYol.add(
        new paper.Segment(
          window.mevcutYol.lastSegment.point,
          null,
          window.mevcutYol.lastSegment.handleOut,
        ),
      );
      window.hayaletYol.add(window.snapHesapla(event.point));
    } else if (window.aktifArac !== "gradyan") {
      if (window.hayaletYol) window.hayaletYol.remove();
    }
  };

paper.view.onMouseUp = function () {
    if (window.aktifArac === "secim" && window.ogeTipi === "marquee") {
      if (window.secimKutusu) {
        let rect = window.secimKutusu.bounds;
        let secilenlerListe = [];
        
        // GÜVENLİ TARAMA: Grupların içine kadar girer, fare kutusuna değen/içinde kalan tüm parçaları toplar
        if (typeof window.tumOgeleriTopla === "function") {
            window.tumOgeleriTopla(window.cizimKatmani, secilenlerListe);
        }
        
        secilenlerListe.forEach((item) => {
          // Bounding box veya merkez noktası fare kutusuyla kesişiyorsa seç
          if (item.bounds && (item.bounds.intersects(rect) || rect.contains(item.bounds) || rect.contains(item.position))) {
            item.fullySelected = false;
            item.selected = true;
          }
        });
        
        window.secimKutusu.remove();
        window.secimKutusu = null;

        if (paper.project.selectedItems.length >= 1) {
          window.seciliOge = paper.project.selectedItems[paper.project.selectedItems.length - 1];
          
          try {
              if (typeof window.TransformKutusuOlustur === "function" && window.seciliOge && !window.seciliOge.removed) {
                  window.TransformKutusuOlustur(window.seciliOge);
              }
          } catch(err) {}
          
          if (paper.project.selectedItems.length === 1 && typeof window.arayuzRenkleriniGuncelle === "function") {
            window.arayuzRenkleriniGuncelle(window.seciliOge);
          }
        }
      }
      window.ogeTipi = null;
      return;
    } else if (
      window.aktifArac === "gradyan" &&
      window.ogeTipi &&
      window.ogeTipi.startsWith("gradyan")
    ) {
      window.durumuKaydet();
      window.ogeTipi = null;
      window.aktifDurakIndex = null;
    } else if (window.aktifArac === "firca" && window.mevcutYol) {
      window.mevcutYol.simplify(5);
      window.tumSecimleriTemizle();
      window.mevcutYol.selected = true;
      window.seciliOge = window.mevcutYol;
      window.ogeTipi = "item";
      window.TransformKutusuOlustur(window.seciliOge);
      window.mevcutYol = null;
      window.durumuKaydet();
     // window.aracSec("secim");
      return;
    } else if (window.aktifArac === "silgi" && window.mevcutYol) {
      if (window.mevcutYol.segments.length > 1) {
        window.mevcutYol.simplify(3);
        window.kapaliPathleriSilgiyleParcala(window.mevcutYol);
      }
      if (window.mevcutYol) {
        window.mevcutYol.remove();
        window.mevcutYol = null;
      }
      window.seciliOge = null;
      window.ogeTipi = null;
      return;
    } else if (
      window.ogeTipi === "pen_yeni_nokta" ||
      window.ogeTipi === "pen_corner_drag" ||
      window.ogeTipi === "pen_smooth_drag" ||
      window.ogeTipi === "pen_single_handle_drag" ||
      window.ogeTipi === "pen_alt_curve_drag"
    ) {
      window.ogeTipi = null;
      window.penEklemeBaslangicNoktasi = null;
      window.penSuruklemeBasladi = false;
      window.penAltCurveData = null;
      if (paper && paper.view) paper.view.update();
      return;
    } else if (
      ["kutu", "daire", "cokgen", "duz_cizgi"].includes(window.aktifArac)
    ) {
      if (window.geciciSekil) {
        window.cizimKatmani.addChild(window.geciciSekil);
        window.geciciSekil.fillColor = window.getPaperColor(
          "dolgu",
          window.geciciSekil.bounds,
        );
        window.geciciSekil.strokeColor =
          window.getPaperColor("cizgi", window.geciciSekil.bounds) || "#1e293b";
        window.tumSecimleriTemizle();
        window.geciciSekil.selected = true;
        window.seciliOge = window.geciciSekil;
        window.ogeTipi = "item";
        window.TransformKutusuOlustur(window.seciliOge);
        window.geciciSekil = null;
        window.durumuKaydet();
        //window.aracSec("secim");
        return;
      }
    } else if (window.aktifArac === "olcum") {
      if (window.geciciSekil) window.geciciSekil.remove();
      if (window.olcumMetni) window.olcumMetni.remove();
    } else if (window.seciliOge && window.ogeTipi !== "yeni_nokta") {
      window.durumuKaydet();
    }

    window.altKopyaYapildi = false;
    window.altKopyaOrijinalleri = null;
    window.altKopyaKlonlari = null;
    if (
      window.aktifGradyanDuzenleme &&
      window.aktifGradyanDuzenleme.mod === "pickcolor"
    )
      window.pickColorCursorGuncelle(false);
    if (window.aktifArac !== "secim") {
      if (window.ogeTipi !== "yeni_nokta") window.seciliOge = null;
    }
    window.ogeTipi = null;
  };

  paper.view.onDoubleClick = function (event) {
    if (
      window.aktifGradyanDuzenleme &&
      window.aktifGradyanDuzenleme.mod === "pickcolor"
    )
      return;
    if (window.aktifArac === "secim") {
      let rawPt = event.point;
      let hit = window.cizimKatmani.hitTest(rawPt, {
        fill: true,
        tolerance: 10,
      });
      if (hit && hit.item && hit.item.className === "PointText") {
        window.metniDuzenle(hit.item);
        return;
      }
      window.tumSecimleriTemizle();
    }
  };
};
window.atolyeKapatmayiDene = function() {
    // Sadece güvenli bir şekilde seçimleri temizle
    try {
        if (typeof window.tumSecimleriTemizle === "function") window.tumSecimleriTemizle();
        if (window.transformKutusu) { window.transformKutusu.remove(); window.transformKutusu = null; }
    } catch(err) {}

    // Sadece atölye modalını kapat, sahneye ve kostümlere dokunma
    let modal = document.getElementById("kostumAtolyesiModal");
    if (modal) {
        modal.style.display = "none";
    }
};