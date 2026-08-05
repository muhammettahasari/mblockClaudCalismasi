/**
 * Dosya Yolu: v2_gorev_sistemi/assets/js/ogrenci/kostum_atolyesi/transform_ve_secim.js
 * Kullanıcı Yetkisi ve Bağlı Olduğu Sayfa: Öğrenci / Kostüm Atölyesi
 * Halk Dilinde İşlevi: Sahnede seçilen tek veya çoklu nesnelerin etrafına mavi boyutlandırma kutusunu çizen, hizalama ve kesişim işlemlerini yürüten modüldür.
 * Temel İşlevler:
 * - Şekillerin etrafına mavi boyutlandırma kulplarını çizer.
 * - Seçilen nesneleri temizleme (deselect) işlemlerini yürütür.
 * - Birden fazla nesne birleştiğinde ortak sınırı (bounds) bulur ve zıt renk ayarlarını yapar.
 * YENİ EKLENEN ÖZELLİKLER: 
 */

window.TransformKutusuOlustur = function (hedefOge) {
  let secililer = paper && paper.project ? paper.project.selectedItems.filter((i) => i.parent !== window.uiKatmani && !i.locked) : [];
  if ((!hedefOge && secililer.length === 0) || (hedefOge && hedefOge.locked)) {
    if (window.transformKutusu) { window.transformKutusu.remove(); window.transformKutusu = null; }
    return null;
  }

  let bounds; let hedefListe = [];
  if (secililer.length > 1) {
    hedefListe = secililer;
    let ortakBounds = secililer[0].strokeBounds.clone();
    for (let i = 1; i < secililer.length; i++) ortakBounds = ortakBounds.unite(secililer[i].strokeBounds);
    bounds = ortakBounds.expand(10);
  } else {
    let hedef = hedefOge || secililer[0];
    if (!hedef || hedef.className === "Group") {
      if (window.transformKutusu) { window.transformKutusu.remove(); window.transformKutusu = null; }
      return null;
    }
    hedefListe = [hedef]; bounds = hedef.strokeBounds.expand(10);
  }

  if (!window.transformKutusu) {
    window.transformKutusu = new paper.Group();
    window.transformKutusu.name = "transKutu";

    let cerceve = new paper.Path.Rectangle(bounds);
    cerceve.strokeColor = "#3b82f6"; cerceve.strokeWidth = 1.5; cerceve.dashArray = [4, 4]; cerceve.name = "cerceve";
    window.transformKutusu.addChild(cerceve);

    let noktalar = ["tl", "tc", "tr", "cl", "cr", "bl", "bc", "br", "rot"];
    noktalar.forEach((isim) => {
      let handle = isim === "rot" ? new paper.Path.Circle({ radius: 4.5, fillColor: "#3b82f6", name: isim })
        : new paper.Path.Rectangle({ size: [6, 6], fillColor: "#ffffff", strokeColor: "#3b82f6", strokeWidth: 1.5, name: isim });
      let kursor = "crosshair";
      if (["tl", "br"].includes(isim)) kursor = "nwse-resize";
      if (["tr", "bl"].includes(isim)) kursor = "nesw-resize";
      if (["tc", "bc"].includes(isim)) kursor = "ns-resize";
      if (["cl", "cr"].includes(isim)) kursor = "ew-resize";
      handle.kursor = kursor;
      window.transformKutusu.addChild(handle);
    });

    let rotLine = new paper.Path.Line({ strokeColor: "#3b82f6", strokeWidth: 1.5, name: "rotLine" });
    window.transformKutusu.insertChild(1, rotLine);

    let pivotRenkleri = window.zitUIRengiBul(hedefListe.length === 1 ? hedefListe[0] : null);
    let pivotGrp = new paper.Group({ name: "pivotBtn" });
    pivotGrp.addChild(new paper.Path.Circle({ radius: 7, fillColor: pivotRenkleri.ters, strokeColor: pivotRenkleri.ana, strokeWidth: 1.5 }));
    pivotGrp.addChild(new paper.Path.Circle({ radius: 2, fillColor: pivotRenkleri.ana }));
    pivotGrp.addChild(new paper.Path.Line({ from: [-8, 0], to: [8, 0], strokeColor: pivotRenkleri.ana, strokeWidth: 1 }));
    pivotGrp.addChild(new paper.Path.Line({ from: [0, -8], to: [0, 8], strokeColor: pivotRenkleri.ana, strokeWidth: 1 }));
    pivotGrp.kursor = "move";
    window.transformKutusu.addChild(pivotGrp);

    window.transformKutusu.onMouseEnter = function (e) {
      if (window.aktifGradyanDuzenleme && window.aktifGradyanDuzenleme.mod === "pickcolor") { window.pickColorCursorGuncelle(false); return; }
      if (e.target.kursor && window.aktifArac === "secim") document.getElementById("cizimTuvali").style.cursor = e.target.kursor;
    };
    window.transformKutusu.onMouseLeave = function () {
      if (window.aktifGradyanDuzenleme && window.aktifGradyanDuzenleme.mod === "pickcolor") { window.pickColorCursorGuncelle(false); return; }
      if (window.aktifArac === "secim") document.getElementById("cizimTuvali").style.cursor = "default";
    };
    if (!window.uiKatmani) window.uiKatmani = new paper.Group();
    window.uiKatmani.addChild(window.transformKutusu);
  }

  window.transformKutusu.matrix = new paper.Matrix();
  window.transformKutusu.children["cerceve"].bounds = bounds;

  let p = {
    tl: bounds.topLeft, tc: bounds.topCenter, tr: bounds.topRight, cl: bounds.leftCenter,
    cr: bounds.rightCenter, bl: bounds.bottomLeft, bc: bounds.bottomCenter, br: bounds.bottomRight, rot: bounds.topCenter.add([0, -25])
  };
  ["tl", "tc", "tr", "cl", "cr", "bl", "bc", "br", "rot"].forEach((isim) => { window.transformKutusu.children[isim].position = p[isim]; });
  window.transformKutusu.children["rotLine"].firstSegment.point = bounds.topCenter;
  window.transformKutusu.children["rotLine"].lastSegment.point = p.rot;

  if (hedefListe.length === 1) {
    let hedef = hedefListe[0];
    if (!hedef.data) hedef.data = {};
    if (!hedef.data.customPivot) hedef.data.customPivot = bounds.center.clone();
    window.transformKutusu.children["pivotBtn"].position = hedef.data.customPivot;
    let angle = hedef.matrix.rotation || 0;
    window.transformKutusu.rotate(angle, bounds.center);
  } else {
    window.transformKutusu.children["pivotBtn"].position = bounds.center.clone();
  }

  window.transformKutusu.hedefOge = hedefListe.length === 1 ? hedefListe[0] : null;
  window.transformKutusu.hedefOgeler = hedefListe;
  window.transformKutusu.cokluSecim = hedefListe.length > 1;
  window.uiKatmani.bringToFront();
  return window.transformKutusu;
};

window.tumSecimleriTemizle = function () {
  if (window.degradeUIGizle) window.degradeUIGizle();
  if (window.transformKutusu) { window.transformKutusu.remove(); window.transformKutusu = null; }
  if (window.secimKutusu) { window.secimKutusu.remove(); window.secimKutusu = null; }
  if (!paper || !paper.project) return;
  paper.project.deselectAll();
  let paths = paper.project.getItems({ className: "Path" });
  if (paths) paths.forEach((item) => { item.fullySelected = false; item.selected = false; });
  let texts = paper.project.getItems({ className: "PointText" });
  if (texts) texts.forEach((item) => { item.selected = false; });
  window.seciliOge = null;
};

window.seciliNesneleriGetir = function () {
  if (!paper || !paper.project) return [];
  return paper.project.selectedItems.filter((i) => i.parent !== window.uiKatmani && !i.locked);
};

window.seciliBoundsGetir = function () {
  let secililer = window.seciliNesneleriGetir();
  if (secililer.length === 0) return null;
  let b = secililer[0].strokeBounds.clone();
  for (let i = 1; i < secililer.length; i++) b = b.unite(secililer[i].strokeBounds);
  return b;
};

window.altKopyaHazirla = function () {
  let secililer = window.seciliNesneleriGetir();
  if (secililer.length === 0) return null;
  let klonlar = secililer.map((item) => {
    let k = item.clone(); window.cizimKatmani.addChild(k); k.selected = true; k.fullySelected = false; return k;
  });
  secililer.forEach((item) => { item.selected = false; item.fullySelected = false; });
  window.altKopyaYapildi = true; window.altKopyaOrijinalleri = secililer; window.altKopyaKlonlari = klonlar;
  window.seciliOge = klonlar[klonlar.length - 1];
  return klonlar;
};

window.zitUIRengiBul = function (item) {
  function luminanceFromHex(hex) {
    if (!hex) return 1;
    hex = String(hex).replace("#", "").trim();
    if (hex.length === 3) hex = hex.split("").map((c) => c + c).join("");
    if (hex.length !== 6) return 1;
    let r = parseInt(hex.substring(0, 2), 16), g = parseInt(hex.substring(2, 4), 16), b = parseInt(hex.substring(4, 6), 16);
    if ([r, g, b].some((n) => isNaN(n))) return 1;
    return (0.299 * r + 0.587 * g + 0.114 * b) / 255;
  }
  let cssRenk = null;
  if (item) {
    if (item.fillColor && !item.fillColor.gradient) cssRenk = item.fillColor.toCSS(true);
    else if (item.strokeColor && !item.strokeColor.gradient) cssRenk = item.strokeColor.toCSS(true);
  }
  let lum = luminanceFromHex(cssRenk);
  return { ana: lum > 0.6 ? "#111827" : "#ffffff", ters: lum > 0.6 ? "#ffffff" : "#111827" };
};

window.atolyeBoolean = function (islem) {
  let secililer = paper.project.selectedItems;
  if (secililer.length < 2) return alert("En az 2 şekil seçin (Shift ile)");
  let sonuc = secililer[0];
  for (let i = 1; i < secililer.length; i++) {
    let gecici = islem === "unite" ? sonuc.unite(secililer[i]) : islem === "subtract" ? secililer[i].subtract(sonuc) : sonuc.intersect(secililer[i]);
    sonuc.remove(); secililer[i].remove(); sonuc = gecici;
  }
  window.cizimKatmani.addChild(sonuc);
  window.tumSecimleriTemizle();
  sonuc.selected = true; window.seciliOge = sonuc;
  window.TransformKutusuOlustur(sonuc);
  window.durumuKaydet();
};

window.atolyeHizala = function (yon) {
  let secililer = paper.project.selectedItems;
  if (secililer.length < 2) return alert("En az 2 şekil seçin");
  let refBounds = secililer[0].bounds;
  for (let i = 1; i < secililer.length; i++) {
    let oge = secililer[i];
    if (yon === "left") oge.bounds.left = refBounds.left; else if (yon === "center") oge.bounds.center.x = refBounds.center.x; else if (yon === "right") oge.bounds.right = refBounds.right;
    else if (yon === "top") oge.bounds.top = refBounds.top; else if (yon === "middle") oge.bounds.center.y = refBounds.center.y; else if (yon === "bottom") oge.bounds.bottom = refBounds.bottom;
  }
  if (window.transformKutusu && secililer.length === 1) window.TransformKutusuOlustur(secililer[0]);
  window.durumuKaydet();
};