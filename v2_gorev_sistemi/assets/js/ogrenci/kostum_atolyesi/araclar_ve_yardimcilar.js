/**
 * Dosya Yolu: v2_gorev_sistemi/assets/js/ogrenci/kostum_atolyesi/araclar_ve_yardimcilar.js
 * Kullanıcı Yetkisi ve Bağlı Olduğu Sayfa: Öğrenci / Kostüm Atölyesi
 * Halk Dilinde İşlevi: Kalem, fırça, silgi, ölçüm, yazı aracı ile klavye kısayollarını ve history (Geri/İleri al) işlevlerini yönetir.
 * Temel İşlevler:
 * - Çizgi kalınlıkları ve stillerini HTML'den okuyup senkronize eder.
 * - Yazı aracı şeffaf HTML nesnelerini Canvas'a oturtur.
 * - Silginin oluşturduğu kesme (intersect) matematiğini çalıştırır.
 * YENİ EKLENEN ÖZELLİKLER: 
 */
window.seciliPenModu = 'normal'; // Varsayılan mod

window.penModuDegistir = function(mod) {
    window.seciliPenModu = mod;
    
    let btnGrup = document.getElementById('grupPenModlari');
    if (btnGrup) {
        btnGrup.querySelectorAll('.arac-btn').forEach(btn => btn.classList.remove('aktif'));
        let aktifBtn = document.getElementById('btnPen' + mod.charAt(0).toUpperCase() + mod.slice(1));
        if (aktifBtn) aktifBtn.classList.add('aktif');
    }
};
window.getStrokeWidth = () => {
  if (window.aktifArac === "firca") return window.fircaKalinlik || 2;
  if (window.aktifArac === "silgi") return window.silgiKalinlik || 35;
  return window.shapeKalinlik || 3;
};

window.kalinlikKontrolleriniEsitle = function () {
  let input = document.getElementById("ayarKalinlik");
  let slider = document.getElementById("ayarKalinlikSlider");
  if (!input || !slider) return;
  let deger = parseInt(input.value, 10);
  if (isNaN(deger)) deger = 3;
  slider.value = deger;
};

window.kalinlikDegeriniUygula = function (deger) {
  let input = document.getElementById("ayarKalinlik");
  let slider = document.getElementById("ayarKalinlikSlider");
  let sayi = parseInt(deger, 10);
  if (isNaN(sayi)) sayi = 3;
  if (input) input.value = sayi;
  if (slider) slider.value = sayi;
  if (typeof window.fircaImlecBoyutunuGuncelle === "function") window.fircaImlecBoyutunuGuncelle();
  if (typeof window.seciliNesnelereCizgiStiliUygula === "function" && window.aktifArac !== "firca" && window.aktifArac !== "silgi") {
    window.seciliNesnelereCizgiStiliUygula();
  }
};

window.aktifAracaGoreKalinlikUIYukle = function () {
  let input = document.getElementById("ayarKalinlik");
  let slider = document.getElementById("ayarKalinlikSlider");
  let deger = window.shapeKalinlik || 3;
  if (window.aktifArac === "firca") deger = window.fircaKalinlik || 2;
  if (window.aktifArac === "silgi") deger = window.silgiKalinlik || 35;
  if (input) input.value = deger;
  if (slider) slider.value = deger;
};

window.getDashArray = () => {
  let ipt = document.getElementById("ayarCizgiTipi");
  return ipt && ipt.value === "dashed" ? [window.getStrokeWidth() * 2, window.getStrokeWidth() * 2] : [];
};

window.fircaImleciniOlustur = function () {
  if (window.fircaImlecHalkasi) return window.fircaImlecHalkasi;
  let wrapper = document.getElementById("atolyeCanvasWrapper");
  if (!wrapper) return null;
  let halka = document.createElement("div");
  halka.id = "fircaImlecHalkasi";
  halka.style.position = "absolute"; halka.style.left = "0px"; halka.style.top = "0px";
  halka.style.border = "1.5px solid #111"; halka.style.borderRadius = "50%";
  halka.style.background = "transparent"; halka.style.pointerEvents = "none";
  halka.style.transform = "translate(-50%, -50%)"; halka.style.zIndex = "9999";
  halka.style.display = "none"; halka.style.boxSizing = "border-box";
  halka.style.boxShadow = "0 0 0 1px #fff";
  wrapper.appendChild(halka);
  window.fircaImlecHalkasi = halka;
  return halka;
};

window.fircaImlecBoyutunuGuncelle = function () {
  let halka = window.fircaImleciniOlustur();
  if (!halka) return;
  let boyut = Math.max(2, window.getStrokeWidth());
  halka.style.width = boyut + "px"; halka.style.height = boyut + "px";
};

window.fircaImlecKonumunuGuncelle = function (event) {
  let halka = window.fircaImleciniOlustur();
  let wrapper = document.getElementById("atolyeCanvasWrapper");
  if (!halka || !wrapper || !paper || !paper.view) return;
  let viewPt = paper.view.projectToView(event.point);
  halka.style.left = viewPt.x + "px"; halka.style.top = viewPt.y + "px";
};

window.fircaImleciniGoster = function () {
  let canvasEl = document.getElementById("cizimTuvali");
  let halka = window.fircaImleciniOlustur();
  if (!canvasEl || !halka) return;
  window.fircaImlecAktif = true; canvasEl.style.cursor = "none";
  window.fircaImlecBoyutunuGuncelle(); halka.style.display = "block";
};

window.fircaImleciniGizle = function () {
  let canvasEl = document.getElementById("cizimTuvali");
  if (canvasEl) window.tuvalCursorunuGuncelle();
  if (window.fircaImlecHalkasi) window.fircaImlecHalkasi.style.display = "none";
  window.fircaImlecAktif = false;
};

window.kalemCursorTipiniGuncelle = function (tip = "normal") {
  let canvasEl = document.getElementById("cizimTuvali");
  if (!canvasEl || window.aktifArac !== "kalem") return;
  if (tip === "plus") canvasEl.style.cursor = "url('/mblock_platform/v2_gorev_sistemi/assets/images/imlecler/pentool_plus.png') 2 2, crosshair";
  else if (tip === "minus") canvasEl.style.cursor = "url('/mblock_platform/v2_gorev_sistemi/assets/images/imlecler/pentool_minus.png') 2 2, crosshair";
  else canvasEl.style.cursor = "url('/mblock_platform/v2_gorev_sistemi/assets/images/imlecler/pentool.png') 2 2, crosshair";
};

window.tuvalCursorunuGuncelle = function () {
  let canvasEl = document.getElementById("cizimTuvali");
  if (!canvasEl) return;
  if (window.aktifArac === "firca" || window.aktifArac === "silgi") { canvasEl.style.cursor = "none"; return; }
  if (window.aktifArac === "kalem") { canvasEl.style.cursor = "url('/mblock_platform/v2_gorev_sistemi/assets/images/imlecler/pentool.png') 2 2, crosshair"; return; }
  if (window.aktifArac === "el") { canvasEl.style.cursor = "grab"; return; }
  if (window.aktifArac === "metin") { canvasEl.style.cursor = "text"; return; }
  canvasEl.style.cursor = "default";
};

window.fircaAracUIBasliginiGuncelle = function () {
  let etiket = document.querySelector('[for="ayarKalinlik"]') || document.getElementById("lblAyarKalinlik") || document.getElementById("etiketAyarKalinlik");
  if (!etiket) return;
  if (window.aktifArac === "firca") etiket.textContent = "Fırça Kalınlığı:";
  else if (window.aktifArac === "silgi") etiket.textContent = "Silgi Kalınlığı:";
  else etiket.textContent = "Çizgi Kalınlığı:";
};

window.seciliNesnelereCizgiStiliUygula = function () {
  if (!paper || !paper.project) return;
  let secililer = paper.project.selectedItems.filter((i) => i.parent !== window.uiKatmani && !i.locked && i.className !== "Group");
  if (secililer.length === 0) return;

  let yeniKalinlik = window.getStrokeWidth();
  let yeniDash = window.getDashArray();
  secililer.forEach((item) => {
    item.strokeWidth = yeniKalinlik; item.dashArray = yeniDash;
  });
  if (window.transformKutusu && window.seciliOge) window.TransformKutusuOlustur(window.seciliOge);
  if (paper && paper.view) paper.view.update();
  if (typeof window.durumuKaydet === "function") window.durumuKaydet();
};

window.snapHesapla = function (pt) {
  let snap = document.getElementById("cbSnapAktif");

  if (!snap || !snap.checked) {
    return pt;
  }

  // Serbest çizim ve düzenleme araçlarında snap uygulanmasın.
  // Yoksa kalem, fırça, silgi, çizgi ve nokta aracı farenin üstünden başlıyor gibi görünür.
  let snapUygulanmayacakAraclar = [
    "kalem",
    "firca",
    "silgi",
    "duz_cizgi",
    "nokta",
    "olcum",
    "metin",
    "gradyan"
  ];

  if (snapUygulanmayacakAraclar.includes(window.aktifArac)) {
    return pt;
  }

  // Snap sadece geometrik şekillerde kalsın.
  let grid = 15;

  return new paper.Point(
    Math.round(pt.x / grid) * grid,
    Math.round(pt.y / grid) * grid
  );
};

window.segmentiSmoothYap = function (seg) {
  if (!seg) return;
  let prev = seg.previous, next = seg.next;
  if (!prev || !next) { seg.smooth({ type: "continuous" }); return; }
  let v1 = prev.point.subtract(seg.point), v2 = next.point.subtract(seg.point);
  let dir = v1.normalize().add(v2.normalize());
  if (dir.length < 0.001) { seg.smooth({ type: "continuous" }); return; }
  dir = dir.normalize();
  let len1 = seg.point.getDistance(prev.point) / 3, len2 = seg.point.getDistance(next.point) / 3;
  seg.handleIn = dir.multiply(-len1); seg.handleOut = dir.multiply(len2);
};

window.segmentiCornerYap = function (seg) {
  if (!seg) return;
  seg.handleIn = new paper.Point(0, 0); seg.handleOut = new paper.Point(0, 0);
};
// =====================================
// NOKTA TOOLU - BEZIER KOLLARINI GÖRÜNÜR YAP
// Pen Tool'a dokunmaz.
// Sadece Düğüm/Nokta aracıyla düzenlenen path üzerinde çalışır.
// =====================================
window.noktaAraciBezierKollariniAc = function (path) {
  if (!path || path.className !== "Path" || !path.segments || path.segments.length === 0) return;

  const minKol = 40;
  const maxKol = 90;

  function sifirKolMu(kol) {
    return !kol || kol.length < 0.001;
  }

  function guvenliKolUzunlugu(mesafe) {
    if (!mesafe || mesafe < 0.001) return minKol;

    // Çok kısa iki düğüm arasında 40 px zorlamak şekli bozabilir.
    // Bu durumda mesafenin üçte biri kullanılır.
    if (mesafe < minKol * 2) {
      return Math.max(12, mesafe / 3);
    }

    return Math.min(maxKol, Math.max(minKol, mesafe / 3));
  }

  path.segments.forEach(function (seg) {
    let prev = seg.previous;
    let next = seg.next;

    // Önceki noktaya doğru giriş kolu
    if (prev) {
      let vIn = prev.point.subtract(seg.point);

      if (vIn.length > 0.001 && sifirKolMu(seg.handleIn)) {
        seg.handleIn = vIn.normalize().multiply(guvenliKolUzunlugu(vIn.length));
      }
    }

    // Sonraki noktaya doğru çıkış kolu
    if (next) {
      let vOut = next.point.subtract(seg.point);

      if (vOut.length > 0.001 && sifirKolMu(seg.handleOut)) {
        seg.handleOut = vOut.normalize().multiply(guvenliKolUzunlugu(vOut.length));
      }
    }

    // Açık path'in ilk ve son noktasında da görsel kol oluşsun.
    // Kullanılmayan kol şekli bozmaz.
    if (!prev && next && sifirKolMu(seg.handleIn) && !sifirKolMu(seg.handleOut)) {
      seg.handleIn = seg.handleOut.multiply(-1);
    }

    if (!next && prev && sifirKolMu(seg.handleOut) && !sifirKolMu(seg.handleIn)) {
      seg.handleOut = seg.handleIn.multiply(-1);
    }
  });

  path.selected = true;
  path.fullySelected = true;

  if (paper && paper.view) {
    paper.view.update();
  }
};

window.silgiIcinKesiciSekilOlustur = function (silgiYolu) {
  if (!silgiYolu || silgiYolu.segments.length < 2) return null;
  let kaynak = silgiYolu.clone({ insert: false });
  let yaricap = Math.max(1, (silgiYolu.strokeWidth || 1) / 2);
  if (kaynak.segments.length > 4) { try { kaynak.simplify(Math.max(1, yaricap * 0.35)); } catch (e) {} }

  let toplamUzunluk = kaynak.length;
  if (!toplamUzunluk || toplamUzunluk < 2) { kaynak.remove(); return null; }

  let adim = Math.max(1.2, yaricap * 0.35);
  let solNoktalar = [], sagNoktalar = [];

  function guvenliNormal(path, offset) {
    let n = path.getNormalAt(offset);
    if (n && n.length > 0.0001) return n.normalize();
    let t = path.getTangentAt(offset);
    if (t && t.length > 0.0001) return t.normalize().rotate(-90);
    return new paper.Point(0, -1);
  }

  function yayNoktalari(merkez, basAcisi, bitAcisi, yaricap, parcaSayisi) {
    let pts = [], fark = bitAcisi - basAcisi;
    while (fark <= -180) fark += 360; while (fark > 180) fark -= 360;
    for (let i = 0; i <= parcaSayisi; i++) {
      let oran = i / parcaSayisi, aci = basAcisi + fark * oran;
      let v = new paper.Point({ length: yaricap, angle: aci });
      pts.push(merkez.add(v));
    }
    return pts;
  }

  for (let offset = 0; offset <= toplamUzunluk; offset += adim) {
    let p = kaynak.getPointAt(offset); if (!p) continue;
    let n = guvenliNormal(kaynak, offset);
    solNoktalar.push(p.add(n.multiply(yaricap))); sagNoktalar.push(p.subtract(n.multiply(yaricap)));
  }

  let sonP = kaynak.getPointAt(toplamUzunluk);
  if (sonP) {
    let sonN = guvenliNormal(kaynak, toplamUzunluk);
    let solSon = sonP.add(sonN.multiply(yaricap)), sagSon = sonP.subtract(sonN.multiply(yaricap));
    if (solNoktalar.length === 0 || !solNoktalar[solNoktalar.length - 1].equals(solSon)) { solNoktalar.push(solSon); sagNoktalar.push(sagSon); }
  }

  if (solNoktalar.length < 2 || sagNoktalar.length < 2) { kaynak.remove(); return null; }

  let basMerkez = kaynak.firstSegment.point.clone(), sonMerkez = kaynak.lastSegment.point.clone();
  let basTanjant = kaynak.getTangentAt(0) || kaynak.lastSegment.point.subtract(basMerkez);
  let sonTanjant = kaynak.getTangentAt(toplamUzunluk) || sonMerkez.subtract(kaynak.lastSegment.previous.point);

  if (!basTanjant || basTanjant.length < 0.0001) basTanjant = new paper.Point(1, 0);
  if (!sonTanjant || sonTanjant.length < 0.0001) sonTanjant = new paper.Point(1, 0);

  let basNormal = basTanjant.normalize().rotate(-90), sonNormal = sonTanjant.normalize().rotate(-90);
  let basSol = basMerkez.add(basNormal.multiply(yaricap)), basSag = basMerkez.subtract(basNormal.multiply(yaricap));
  let sonSol = sonMerkez.add(sonNormal.multiply(yaricap)), sonSag = sonMerkez.subtract(sonNormal.multiply(yaricap));

  let basYayi = yayNoktalari(basMerkez, basSag.subtract(basMerkez).angle, basSol.subtract(basMerkez).angle, yaricap, 10);
  let sonYayi = yayNoktalari(sonMerkez, sonSol.subtract(sonMerkez).angle, sonSag.subtract(sonMerkez).angle, yaricap, 10);

  let kapaliNoktalar = [];
  kapaliNoktalar.push(...basYayi); kapaliNoktalar.push(...solNoktalar.slice(1));
  kapaliNoktalar.push(...sonYayi); kapaliNoktalar.push(...sagNoktalar.slice(0, -1).reverse());

  let kesici = new paper.Path({ segments: kapaliNoktalar, closed: true, insert: false });
  try { kesici.simplify(Math.max(0.6, yaricap * 0.15)); } catch (e) {}
  try { kesici.smooth({ type: "continuous" }); } catch (e) {}

  kaynak.remove(); return kesici;
};

window.booleanSonucunuParcala = function (sonuc) {
  let parcalar = [];
  if (!sonuc) return parcalar;
  if (sonuc.className === "Path") { parcalar.push(sonuc); return parcalar; }
  if (sonuc.className === "CompoundPath") {
    let children = sonuc.children.slice();
    children.forEach((child) => {
      let parca = child.clone(); parca.closed = true; parca.selected = false; parca.fullySelected = false;
      window.cizimKatmani.addChild(parca); parcalar.push(parca);
    });
    sonuc.remove();
  }
  return parcalar;
};

window.kapaliPathleriSilgiyleParcala = function (silgiYolu) {
  if (!paper || !paper.project || !window.cizimKatmani || !silgiYolu) return;
  let kesici = window.silgiIcinKesiciSekilOlustur(silgiYolu);
  if (!kesici) return;

  let hedefler = window.cizimKatmani.children.filter((item) => item && item !== silgiYolu && item.className === "Path" && item.parent === window.cizimKatmani && item.closed && !item.locked);
  let degisti = false;

  function stilKopyala(hedefParca, kaynak) {
    hedefParca.closed = true; hedefParca.selected = false; hedefParca.fullySelected = false;
    hedefParca.fillColor = kaynak.fillColor ? (kaynak.fillColor.clone ? kaynak.fillColor.clone() : kaynak.fillColor) : null;
    hedefParca.strokeColor = kaynak.strokeColor ? (kaynak.strokeColor.clone ? kaynak.strokeColor.clone() : kaynak.strokeColor) : null;
    hedefParca.strokeWidth = kaynak.strokeWidth || 1;
    hedefParca.dashArray = Array.isArray(kaynak.dashArray) ? [...kaynak.dashArray] : [];
    hedefParca.strokeCap = kaynak.strokeCap; hedefParca.strokeJoin = kaynak.strokeJoin;
  }

  function sonucuParcalaraAyir(sonuc, kaynak) {
    let parcalar = [];
    if (!sonuc) return parcalar;
    if (sonuc.className === "Path") {
      if (Math.abs(sonuc.area || 0) > 0.5) { stilKopyala(sonuc, kaynak); window.cizimKatmani.addChild(sonuc); parcalar.push(sonuc); } else { sonuc.remove(); }
      return parcalar;
    }
    if (sonuc.className === "CompoundPath") {
      let children = sonuc.children.slice();
      children.forEach((child) => {
        let klon = child.clone();
        if (Math.abs(klon.area || 0) > 0.5) { stilKopyala(klon, kaynak); window.cizimKatmani.addChild(klon); parcalar.push(klon); } else { klon.remove(); }
      });
      sonuc.remove();
    }
    return parcalar;
  }

  hedefler.forEach((hedef) => {
    if (!hedef.bounds || !kesici.bounds || !hedef.bounds.intersects(kesici.bounds)) return;
    let oncekiAlan = Math.abs(hedef.area || 0);
    let kalanSonuc = hedef.subtract(kesici, { insert: false });
    if (!kalanSonuc) return;

    let yeniParcalar = sonucuParcalaraAyir(kalanSonuc, hedef);
    let yeniToplamAlan = yeniParcalar.reduce((toplam, parca) => toplam + Math.abs(parca.area || 0), 0);
    if (Math.abs(oncekiAlan - yeniToplamAlan) < 0.01) { yeniParcalar.forEach((p) => p.remove()); return; }
    hedef.remove(); degisti = true;
  });

  kesici.remove();
  if (silgiYolu && !silgiYolu.removed) silgiYolu.remove();
  if (degisti) { window.tumSecimleriTemizle(); if (paper && paper.view) paper.view.update(); if (typeof window.durumuKaydet === "function") window.durumuKaydet(); }
};

window.aracSec = function (arac) {
    let grupPenModlari = document.getElementById("grupPenModlari");
  if (grupPenModlari) {
    grupPenModlari.style.display = (arac === "kalem") ? "flex" : "none";
  }
  if (arac === "firca" || arac === "silgi") { window.fircaImleciniGoster(); window.fircaImlecBoyutunuGuncelle(); } 
  else { window.fircaImleciniGizle(); }
  if (window.aktifArac === "kalem" && window.mevcutYol) { window.mevcutYol.fullySelected = false; window.mevcutYol = null; }
  if (window.hayaletYol) window.hayaletYol.remove();
  if (window.aktifMetinKutusu) window.metinKutusunuKapatVeCiz();
  if (window.aktifGradyanDuzenleme && arac !== "gradyan") window.gradyanStopDuzenlemeKapat(false, false);

  window.aktifArac = arac;
  if (arac === "kalem") window.kalemCursorTipiniGuncelle("normal");
  window.tuvalCursorunuGuncelle();
  let grupCizgiTipi = document.getElementById("grupCizgiTipi");

  if (grupCizgiTipi) {
    if (["kutu", "daire", "cokgen", "duz_cizgi"].includes(arac)) grupCizgiTipi.style.display = "flex";
    else grupCizgiTipi.style.display = "none";
  }
  window.aktifAracaGoreKalinlikUIYukle();
  window.fircaAracUIBasliginiGuncelle();
  if (arac !== "gradyan" && arac !== "secim") window.tumSecimleriTemizle();
  else if (arac !== "secim" && window.transformKutusu) { window.transformKutusu.remove(); window.transformKutusu = null; }

  let sagPanel = document.querySelector(".atolye-sag-panel");
  if (sagPanel) sagPanel.querySelectorAll(".arac-btn").forEach((b) => b.classList.remove("aktif"));
  let btn = document.getElementById("btnArac" + arac.charAt(0).toUpperCase() + arac.slice(1));
  if (btn) btn.classList.add("aktif");

  document.querySelectorAll(".kontrol-satir.baglamsal").forEach((row) => row.classList.remove("aktif"));
  if (["kalem", "firca", "silgi", "kutu", "daire", "cokgen", "secim", "duz_cizgi"].includes(arac)) document.getElementById("context-shape")?.classList.add("aktif");
  else if (arac === "metin") document.getElementById("context-text")?.classList.add("aktif");
  else if (arac === "olcum") document.getElementById("context-olcum")?.classList.add("aktif");

  document.getElementById("cizimTuvali").style.cursor = arac === "el" ? "grab" : arac === "metin" ? "text" : "default";

  if (arac === "gradyan") { if (window.degradeUICiz) window.degradeUICiz(); } 
  else if (window.degradeUIGizle) window.degradeUIGizle();
  if (typeof window.atolyeGorunumKontrolGuncelle === "function") {
  window.atolyeGorunumKontrolGuncelle();
}
};

window.cizgiTipiGuncelle = function () { let dash = window.getDashArray(); if (paper.project.selectedItems) paper.project.selectedItems.forEach((i) => { if (i.strokeColor) i.dashArray = dash; }); };
window.kostumAdiniGuncelle = function(y) { 
    let s = window.projeKuklalari.find((k) => k.id === window.aktifKuklaId); 
    if (!s) return;
    let a = s.kostumler.find((k) => k.id === window.aktifKostumId); 
    if (a) {
        a.adi = y; 
        // Sol paneldeki kartın ismini ID'sinden bulup anında değiştirir
        let kartIsim = document.getElementById("isimLabel_" + a.id);
        if (kartIsim) {
            kartIsim.innerText = y;
        }
    } 
};
 // =====================================
// SAHNE ZOOM / PAN KONTROLÜ
// =====================================

window.atolyeVarsayilanZoom = null;

window.atolyeGorunumKontrolGuncelle = function () {
  let panel = document.getElementById("atolyeZoomKontrol");
  if (!panel || !paper || !paper.view) return;

  let varsayilan = window.atolyeVarsayilanZoom || paper.view.zoom || 1;
  let zoomluMu = paper.view.zoom > varsayilan * 1.01;

  panel.classList.toggle("zoomlu", zoomluMu);

  let btnPan = document.getElementById("btnAtolyePanMini");
  let btnSecim = document.getElementById("btnAtolyeSecimMini");

  if (btnPan) {
    btnPan.classList.toggle("aktif", window.aktifArac === "el");
  }

  if (btnSecim) {
    btnSecim.classList.toggle("aktif", window.aktifArac === "secim");
  }
};

window.atolyeZoomla = function (f) {
  if (!paper || !paper.view) return;

  let varsayilan = window.atolyeVarsayilanZoom || paper.view.zoom || 1;
  let maxZoom = varsayilan * 6;

  let yeniZoom = paper.view.zoom * f;

  if (yeniZoom < varsayilan) {
    yeniZoom = varsayilan;
  }

  if (yeniZoom > maxZoom) {
    yeniZoom = maxZoom;
  }

  paper.view.zoom = yeniZoom;

  if (paper.view.zoom <= varsayilan * 1.01) {
    paper.view.zoom = varsayilan;
    paper.view.center = new paper.Point(240, 180);

    if (window.aktifArac === "el" && typeof window.aracSec === "function") {
      window.aracSec("secim");
    }
  }

  window.atolyeGorunumKontrolGuncelle();
};

window.atolyeEkranaSigdir = function () {
  let w = document.getElementById("atolyeCanvasWrapper");
  if (!w || !paper || !paper.view) return;

  paper.view.viewSize = new paper.Size(w.clientWidth, w.clientHeight);

  let yeniZoom = Math.min(w.clientWidth / 550, w.clientHeight / 450);

  paper.view.zoom = yeniZoom;
  paper.view.center = new paper.Point(240, 180);

  window.atolyeVarsayilanZoom = yeniZoom;

  if (window.aktifArac === "el" && typeof window.aracSec === "function") {
    window.aracSec("secim");
  }

  window.atolyeGorunumKontrolGuncelle();
};

window.atolyePanModuAc = function () {
  if (typeof window.aracSec === "function") {
    window.aracSec("el");
  } else {
    window.aktifArac = "el";
  }

  let canvas = document.getElementById("cizimTuvali");
  if (canvas) canvas.style.cursor = "grab";

  window.atolyeGorunumKontrolGuncelle();
};

window.atolyeSecimModuAc = function () {
  if (typeof window.aracSec === "function") {
    window.aracSec("secim");
  } else {
    window.aktifArac = "secim";
  }

  let canvas = document.getElementById("cizimTuvali");
  if (canvas) canvas.style.cursor = "default";

  window.atolyeGorunumKontrolGuncelle();
};
 
window.durumuKaydet = function() {
  try { window.atolyeGecmis = window.atolyeGecmis.slice(0, window.gecmisAdim + 1); window.atolyeGecmis.push(window.cizimKatmani.exportJSON()); window.gecmisAdim++; } 
  catch (e) { console.warn("Güvenlik engeli:", e); }
};

window.atolyeGeriAl = function () {
  if (window.gecmisAdim > 0) { window.gecmisAdim--; window.cizimKatmani.clear(); window.cizimKatmani.importJSON(window.atolyeGecmis[window.gecmisAdim]); window.tumSecimleriTemizle(); window.mevcutYol = null; if (window.hayaletYol) window.hayaletYol.remove(); } 
  else if (window.gecmisAdim === 0) { window.gecmisAdim = -1; window.cizimKatmani.clear(); window.tumSecimleriTemizle(); window.mevcutYol = null; }
};

window.atolyeIleriAl = function () {
  if (window.gecmisAdim < window.atolyeGecmis.length - 1) { window.gecmisAdim++; window.cizimKatmani.clear(); window.cizimKatmani.importJSON(window.atolyeGecmis[window.gecmisAdim]); window.tumSecimleriTemizle(); window.mevcutYol = null; }
};

window.kilavuzResimYukle = function (event) {
  let file = event.target.files[0]; if (!file) return; let reader = new FileReader();
  reader.onload = function (f) {
    if (file.type === "image/svg+xml") { paper.project.importSVG(f.target.result, function (item) { item.position = new paper.Point(240, 180); window.cizimKatmani.addChild(item); window.durumuKaydet(); }); } 
    else { let r = new paper.Raster(f.target.result); r.position = new paper.Point(240, 180); r.onLoad = function () { if (r.width > 400) r.scale(400 / r.width); window.cizimKatmani.addChild(r); window.durumuKaydet(); }; }
  }; reader.readAsDataURL(file); document.getElementById("kilavuzYukleyici").value = "";
};
  
window.resimYukle = function(url) {
  if (window.cizimKatmani) window.cizimKatmani.removeChildren(); 
  window.atolyeGecmis = []; 
  window.gecmisAdim = -1; 
  if (!url) return;

  function clipleriKaldir(item) {
      if (!item) return;
      if (item.clipped) { item.clipped = false; }
      if (item.children) {
          item.children.forEach(function(child) { clipleriKaldir(child); });
      }
  }

  function olcegiUygula(item) {
      if (!item) return;
      item.position = new paper.Point(240, 180); 
      
      if (window.aktifKuklaOlcegi == null) {
          let idealBoyut = 300; 
          let w = (item.bounds && item.bounds.width) ? item.bounds.width : (item.width || 1);
          let h = (item.bounds && item.bounds.height) ? item.bounds.height : (item.height || 1);
          
          if (w > idealBoyut || h > idealBoyut) {
              window.aktifKuklaOlcegi = Math.min(idealBoyut / w, idealBoyut / h);
          } else {
              window.aktifKuklaOlcegi = 1;
          }
      }
      item.scale(window.aktifKuklaOlcegi); 
  }

  if (url.toLowerCase().endsWith(".svg") || url.includes("image/svg+xml")) {
      paper.project.importSVG(url, function(item) {
          clipleriKaldir(item); 
          olcegiUygula(item); 
          
          if (item.className === "Group") {
              item.children.forEach(function(child) { child.locked = false; });
          }
          
          window.cizimKatmani.addChild(item);
          if (typeof window.tumSecimleriTemizle === "function") window.tumSecimleriTemizle();
          if (typeof window.durumuKaydet === "function") window.durumuKaydet();
          if (paper.view) paper.view.update();
      });
      return;
  }

  let img = new Image(); 
  img.crossOrigin = "Anonymous";
  img.onload = function () { 
      let r = new paper.Raster(img); 
      olcegiUygula(r); 
      window.cizimKatmani.addChild(r); 
      if (typeof window.durumuKaydet === "function") window.durumuKaydet(); 
  };
  img.onerror = function () { 
      let r = new paper.Raster(url); 
      r.onLoad = function () { 
          olcegiUygula(r); 
          window.cizimKatmani.addChild(r); 
      }; 
  };
  img.src = url;
};

window.atolyeKaydet = function () {
  if (window.aktifMetinKutusu) window.metinKutusunuKapatVeCiz(); window.tumSecimleriTemizle();
  try {
    // Tüm çizimi o anki aktif kostüme kaydet (Oyun motoru buradan çekecek)
    window.mevcutKostumuHafizayaAl(); 
    
    let secili = window.projeKuklalari.find((k) => k.id === window.aktifKuklaId); 
    // Kostüm listesindeki ilk resmi, kuklanın genel resmi olarak ekrana yansıt
    if (secili.kostumler && secili.kostumler.length > 0) {
         secili.resimYolu = secili.kostumler[0].resimYolu;
    }
    
    window.ekraniYenile(); 
    document.getElementById("kostumAtolyesiModal").style.display = "none";
  } catch (e) { alert("Güvenlik Engeli!"); console.error(e); }
};

window.atolyeSvgIndir = function () { window.tumSecimleriTemizle(); let svg = paper.project.exportSVG({ asString: true, bounds: new paper.Rectangle(0, 0, 480, 360) }); let blob = new Blob([svg], { type: "image/svg+xml;charset=utf-8" }); let link = document.createElement("a"); link.href = URL.createObjectURL(blob); link.download = "cizim.svg"; link.click(); };
window.kostumAtolyesiniKapat = () => (document.getElementById("kostumAtolyesiModal").style.display = "none");

window.katmanYukari = function () { if (paper.project.selectedItems.length > 0) { paper.project.selectedItems.forEach((i) => window.cizimKatmani.addChild(i)); window.durumuKaydet(); } };
window.katmanAsagi = function () { if (paper.project.selectedItems.length > 0) { paper.project.selectedItems.forEach((i) => window.cizimKatmani.insertChild(0, i)); window.durumuKaydet(); } };

window.metniDuzenle = function (textItem) {
  if (!textItem) return;
  let pt = textItem.point.clone(); let icerik = textItem.content;
  let renk = textItem.fillColor && !textItem.fillColor.gradient ? textItem.fillColor.toCSS(true) : "#000000";
  let fnt = textItem.fontFamily; let pnt = textItem.fontSize;
  window.tumSecimleriTemizle(); textItem.remove(); window.olusturMetinKutusu(pt, icerik, renk, fnt, pnt);
};

window.olusturMetinKutusu = function(pt, varsayilanMetin = "", mevcutRenk = null, mevcutFont = null, mevcutPunto = null) {
  let wrapper = document.getElementById("atolyeCanvasWrapper"); let viewPt = paper.view.projectToView(pt);
  let textarea = document.createElement("textarea"); textarea.className = "canvas-text-input";
  textarea.style.left = viewPt.x + "px"; textarea.style.top = viewPt.y + "px";

  let fontAyari = document.getElementById("ayarFont"); let puntoAyari = document.getElementById("ayarPunto");
  textarea.style.color = mevcutRenk || window.getPaperColor("dolgu", null) || window.getPaperColor("cizgi", null) || "#000000";
  textarea.style.fontFamily = mevcutFont || (fontAyari ? fontAyari.value : "Arial");

  let numPunto = parseInt(mevcutPunto);
  let pointSize = !isNaN(numPunto) && numPunto > 0 ? numPunto : parseInt(puntoAyari ? puntoAyari.value : 24);
  textarea.style.fontSize = pointSize * paper.view.zoom + "px";

  textarea.value = varsayilanMetin; textarea.placeholder = "Yazın..."; wrapper.appendChild(textarea);
  setTimeout(() => { textarea.focus(); }, 10);

  textarea.style.width = "auto"; textarea.style.width = textarea.scrollWidth + 15 + "px";
  textarea.style.height = "auto"; textarea.style.height = textarea.scrollHeight + 5 + "px";

  textarea.addEventListener("input", function () { this.style.width = "auto"; this.style.width = this.scrollWidth + 10 + "px"; this.style.height = "auto"; this.style.height = this.scrollHeight + "px"; });
  window.aktifMetinKutusu = textarea; window.aktifMetinNoktasi = pt; window.aktifMetinAyar = { fnt: textarea.style.fontFamily, pnt: pointSize };
};

window.metinKutusunuKapatVeCiz = function() {
  if (!window.aktifMetinKutusu) return false;
  let yazi = window.aktifMetinKutusu.value.trim(); let pt = window.aktifMetinNoktasi; let renk = window.aktifMetinKutusu.style.color;
  let fnt = window.aktifMetinAyar ? window.aktifMetinAyar.fnt : "Arial"; let pnt = window.aktifMetinAyar ? window.aktifMetinAyar.pnt : 24;

  window.aktifMetinKutusu.remove(); window.aktifMetinKutusu = null; window.aktifMetinNoktasi = null; window.aktifMetinAyar = null;

  if (yazi !== "" && pt) {
    try {
      let text = new paper.PointText({ point: pt, content: yazi, fillColor: renk, fontFamily: fnt, fontWeight: "bold", fontSize: pnt });
      window.cizimKatmani.addChild(text); window.tumSecimleriTemizle();
      text.selected = true; window.seciliOge = text; window.ogeTipi = "item";
      window.TransformKutusuOlustur(window.seciliOge); window.durumuKaydet(); return true;
    } catch (e) { console.error("Yazı hatası:", e); return false; }
  }
  return false;
};

// Klavye ve Olay Dinleyicileri
document.addEventListener("keydown", function (e) {
  let modal = document.getElementById("kostumAtolyesiModal");
  if (!modal || modal.style.display !== "flex") return;
  let isInput = e.target.tagName.toLowerCase() === "input" || e.target.tagName.toLowerCase() === "textarea";

  if (e.key === "Control") {
    window.ctrlBasili = true;
    if (!isInput && !(window.aktifGradyanDuzenleme && window.aktifGradyanDuzenleme.mod === "pickcolor")) { document.getElementById("cizimTuvali").style.cursor = "crosshair"; }
  } else if (e.key === "Alt") { window.altBasili = true; } 
  else if (e.key === "Shift") { window.shiftBasili = true; } 
  else if (e.code === "Space" && !window.spaceBasili && !isInput) {
    window.spaceBasili = true; document.getElementById("cizimTuvali").style.cursor = "grab";
  } else if (!isInput) {
    let key = e.key.toLowerCase();
    if (key === "escape" && window.aktifGradyanDuzenleme) { e.preventDefault(); window.gradyanStopDuzenlemeKapat(true); return; }
   if (e.ctrlKey || e.metaKey) {
      if (key === "z") { e.preventDefault(); window.atolyeGeriAl(); } 
      else if (key === "y") { e.preventDefault(); window.atolyeIleriAl(); } 
     else if (key === "a") {
        e.preventDefault(); 
        window.aracSec("secim"); 
        window.tumSecimleriTemizle();
        
        let secilenler = [];
        window.tumOgeleriTopla(window.cizimKatmani, secilenler);

        secilenler.forEach(function(c) { 
            c.fullySelected = false; 
            c.selected = true; 
        });

        if (secilenler.length > 0) {
          window.seciliOge = secilenler[secilenler.length - 1]; 
          window.ogeTipi = "item";
          
          // GÜVENLİK KALKANI: Sınır hesaplama hatasını (Cannot read properties of null) engeller
          try {
              if (typeof window.TransformKutusuOlustur === "function" && window.seciliOge && !window.seciliOge.removed) {
                  window.TransformKutusuOlustur(window.seciliOge);
              }
          } catch(err) {
              console.warn("Transform kutusu oluşturulurken güvenli geçiş yapıldı:", err);
          }

          if (secilenler.length === 1 && typeof window.arayuzRenkleriniGuncelle === "function") {
              window.arayuzRenkleriniGuncelle(window.seciliOge);
          }
        }
      }
      else if (key === "c") {
        e.preventDefault(); if (window.seciliOge) window.kopyalananOgeler = [window.seciliOge.clone({ insert: false })];
      } 
      else if (key === "x") {
        e.preventDefault(); if (window.seciliOge) { window.kopyalananOgeler = [window.seciliOge.clone({ insert: false })]; window.seciliOge.remove(); window.tumSecimleriTemizle(); window.durumuKaydet(); }
      } 
      else if (key === "v") {
        e.preventDefault();
        if (window.kopyalananOgeler && window.kopyalananOgeler.length > 0) {
          window.tumSecimleriTemizle(); let yeniKopyalar = [];
          window.kopyalananOgeler.forEach((item) => {
            let y = item.clone(); y.position = y.position.add(new paper.Point(20, 20));
            window.cizimKatmani.addChild(y); y.selected = true; window.seciliOge = y; window.ogeTipi = "item";
            window.TransformKutusuOlustur(y); yeniKopyalar.push(y);
          });
          window.kopyalananOgeler = yeniKopyalar; window.aracSec("secim"); window.durumuKaydet();
        }
      }
    } else {
      if (key === "v") { e.preventDefault(); window.aracSec("secim"); } 
      else if (key === "p") { e.preventDefault(); window.aracSec("kalem"); } 
      else if (key === "b") { e.preventDefault(); window.aracSec("firca"); } 
      else if (key === "e") { e.preventDefault(); window.aracSec("silgi"); } 
      else if (key === "r" || key === "m") { e.preventDefault(); window.aracSec("kutu"); } 
      else if (key === "o" || key === "c") { e.preventDefault(); window.aracSec("daire"); } 
      else if (key === "t") { e.preventDefault(); window.aracSec("metin"); } 
      else if (key === "g") { e.preventDefault(); window.aracSec("gradyan"); } 
      else if (key === "\\" || key === "l") { e.preventDefault(); window.aracSec("duz_cizgi"); } 
      else if (key === "h") { e.preventDefault(); window.aracSec("el"); }

      if ((key === "enter" || key === "escape") && window.aktifArac === "kalem") {
        if (window.mevcutYol) {
          window.mevcutYol.fullySelected = false; window.mevcutYol.fillColor = window.getPaperColor("dolgu", window.mevcutYol.bounds);
          window.tumSecimleriTemizle(); window.mevcutYol.selected = true; window.seciliOge = window.mevcutYol; window.ogeTipi = "item";
          window.TransformKutusuOlustur(window.seciliOge); window.mevcutYol = null; window.durumuKaydet(); window.aracSec("secim");
        }
        if (window.hayaletYol) window.hayaletYol.remove();
      }
      if (key === "delete" || key === "backspace") {
        e.preventDefault();
        if (paper.project.selectedItems.length > 0) { paper.project.selectedItems.forEach((i) => i.remove()); window.tumSecimleriTemizle(); window.durumuKaydet(); }
      }
    }
  }
});

document.addEventListener("keyup", function (e) {
  if (e.key === "Control") {
    window.ctrlBasili = false;
    if (!(window.aktifGradyanDuzenleme && window.aktifGradyanDuzenleme.mod === "pickcolor")) { document.getElementById("cizimTuvali").style.cursor = "default"; } 
    else { window.pickColorCursorGuncelle(false); }
  } else if (e.key === "Alt") { window.altBasili = false; } 
  else if (e.key === "Shift") { window.shiftBasili = false; } 
  else if (e.code === "Space") {
    window.spaceBasili = false; document.getElementById("cizimTuvali").style.cursor = window.aktifArac === "el" ? "grab" : "default";
  }
});
// --- KOSTÜM YÖNETİM VE ANİMASYON ALTYAPISI ---
window.mevcutKostumuHafizayaAl = function() {
    if (!window.aktifKuklaId || !window.aktifKostumId) return;
    let seciliKukla = window.projeKuklalari.find(k => k.id === window.aktifKuklaId);
    if (!seciliKukla || !seciliKukla.kostumler) return;
    let aktifKostum = seciliKukla.kostumler.find(k => k.id === window.aktifKostumId);
    if (aktifKostum) {
        window.tumSecimleriTemizle();
        aktifKostum.cizimData = window.cizimKatmani.exportJSON();
        try {
            let raster = window.cizimKatmani.rasterize(72, false, new paper.Rectangle(0, 0, 480, 360));
            aktifKostum.resimYolu = raster.toDataURL();
            raster.remove();
        } catch(e) { console.warn("Önizleme resmi oluşturulamadı", e); }
    }
};

window.kostumSec = function(kostumId, ilkAcilis = false) {
    if (!ilkAcilis && typeof window.mevcutKostumuHafizayaAl === "function") window.mevcutKostumuHafizayaAl();
    window.aktifKostumId = kostumId;
    
    let seciliKukla = window.projeKuklalari.find(k => k.id === window.aktifKuklaId);
    if (!seciliKukla || !seciliKukla.kostumler) return;
    let aktifKostum = seciliKukla.kostumler.find(k => k.id === kostumId);
    if (!aktifKostum) return;
    
    if (window.cizimKatmani) window.cizimKatmani.removeChildren();
    window.atolyeGecmis = []; window.gecmisAdim = -1;

    if (aktifKostum.cizimData) {
        window.cizimKatmani.importJSON(aktifKostum.cizimData);
        if (typeof window.durumuKaydet === "function") window.durumuKaydet();
    } else if (aktifKostum.resimYolu) {
        window.resimYukle(aktifKostum.resimYolu);
    } else {
        if (typeof window.durumuKaydet === "function") window.durumuKaydet();
    }
    
    // --- ZEKİ KUTU BULUCU (HTML değiştirmeye gerek yok) ---
    let ustInput = null;
    let tumInputlar = document.querySelectorAll("input");
    
    // 1. Önce kodlarında "kostumAdiniGuncelle" geçen inputu arar
    for (let i = 0; i < tumInputlar.length; i++) {
        if ((tumInputlar[i].outerHTML || "").includes("kostumAdiniGuncelle")) {
            ustInput = tumInputlar[i];
            break;
        }
    }
    
    // 2. Bulamazsa, kalınlık, font veya gizli dosya ayarı olmayan ilk metin kutusunu alır
    if (!ustInput) {
        for (let i = 0; i < tumInputlar.length; i++) {
            let inp = tumInputlar[i];
            let tip = inp.getAttribute("type");
            let id = (inp.id || "").toLowerCase();
            let sinif = (inp.className || "").toLowerCase();
            
            if ((tip === "text" || !tip) && !id.includes("ayar") && !sinif.includes("canvas") && !id.includes("gizli")) {
                ustInput = inp;
                break;
            }
        }
    }

    // Doğru kutuyu bulduktan sonra aktif kostümün adını yazar
    if (ustInput) {
        ustInput.value = aktifKostum.adi;
    }

    if (typeof window.kostumListesiniCiz === "function") window.kostumListesiniCiz();
};

window.kostumKopyala = function(kostumId, event) {
    if (event) event.stopPropagation();
    window.mevcutKostumuHafizayaAl(); 
    
    let seciliKukla = window.projeKuklalari.find(k => k.id === window.aktifKuklaId);
    let kaynakKostum = seciliKukla.kostumler.find(k => k.id === kostumId);
    let index = seciliKukla.kostumler.findIndex(k => k.id === kostumId);
    
    let yeniId = "k_" + Date.now();
    let yeniKostum = {
        id: yeniId,
        adi: kaynakKostum.adi + "_kopya",
        resimYolu: kaynakKostum.resimYolu,
        cizimData: kaynakKostum.cizimData
    };
    
    seciliKukla.kostumler.splice(index + 1, 0, yeniKostum);
    window.kostumSec(yeniId);
};

window.kostumSil = function(kostumId, event) {
    if (event) event.stopPropagation();
    let seciliKukla = window.projeKuklalari.find(k => k.id === window.aktifKuklaId);
    if (seciliKukla.kostumler.length <= 1) return alert("Kuklanın en az 1 kostümü olmak zorundadır!");
    
    let index = seciliKukla.kostumler.findIndex(k => k.id === kostumId);
    seciliKukla.kostumler.splice(index, 1);
    
    if (window.aktifKostumId === kostumId) {
        let yeniSecilecekIndex = Math.max(0, index - 1);
        window.kostumSec(seciliKukla.kostumler[yeniSecilecekIndex].id);
    } else {
        window.kostumListesiniCiz();
    }
};

window.yeniKostumEkle = function() {
    window.mevcutKostumuHafizayaAl();
    let seciliKukla = window.projeKuklalari.find(k => k.id === window.aktifKuklaId);
    let yeniId = "k_" + Date.now();
    let sira = seciliKukla.kostumler.length + 1;
    seciliKukla.kostumler.push({ id: yeniId, adi: "costume" + sira, resimYolu: "", cizimData: null });
    window.kostumSec(yeniId);
};

window.kostumListesiniCiz = function() {
    let solPanel = document.querySelector(".atolye-sol-panel");
    if (!solPanel) return;

    let seciliKukla = window.projeKuklalari.find(k => k.id === window.aktifKuklaId);
    if (!seciliKukla || !seciliKukla.kostumler) return;

    solPanel.innerHTML = "";
    solPanel.style.display = "flex"; solPanel.style.flexDirection = "column"; solPanel.style.gap = "10px";
    solPanel.style.padding = "10px";

    let modal = document.getElementById("kostumAtolyesiModal");
    let contextMenu = document.getElementById("sagTikMenuKostum");

    if (!contextMenu) {
        contextMenu = document.createElement("div");
        contextMenu.id = "sagTikMenuKostum";
        contextMenu.style.position = "fixed";
        contextMenu.style.display = "none";
        contextMenu.style.backgroundColor = "#ffffff";
        contextMenu.style.border = "1px solid #cbd5e1";
        contextMenu.style.boxShadow = "0 10px 25px rgba(0,0,0,0.2)";
        contextMenu.style.zIndex = "999999999"; 
        contextMenu.style.borderRadius = "8px";
        contextMenu.style.padding = "4px 0";
        contextMenu.style.minWidth = "140px";
        contextMenu.style.flexDirection = "column";

        let btnKopya = document.createElement("div");
        btnKopya.id = "cmenu-kopya";
        btnKopya.innerHTML = "📄 Kopya Oluştur";
        btnKopya.style.padding = "10px 15px";
        btnKopya.style.cursor = "pointer";
        btnKopya.style.fontSize = "12px";
        btnKopya.style.color = "#334155";
        btnKopya.onmouseover = () => btnKopya.style.backgroundColor = "#f1f5f9";
        btnKopya.onmouseout = () => btnKopya.style.backgroundColor = "#ffffff";

        let btnSil = document.createElement("div");
        btnSil.id = "cmenu-sil";
        btnSil.innerHTML = "✖ Sil";
        btnSil.style.padding = "10px 15px";
        btnSil.style.cursor = "pointer";
        btnSil.style.fontSize = "12px";
        btnSil.style.color = "#ef4444";
        btnSil.onmouseover = () => btnSil.style.backgroundColor = "#fef2f2";
        btnSil.onmouseout = () => btnSil.style.backgroundColor = "#ffffff";

        contextMenu.appendChild(btnKopya);
        contextMenu.appendChild(btnSil);

        if (modal) modal.appendChild(contextMenu);
        else document.body.appendChild(contextMenu);

        document.addEventListener("click", function(e) {
            if (contextMenu.style.display === "flex") contextMenu.style.display = "none";
        });
    }

    seciliKukla.kostumler.forEach((kostum, index) => {
        let isAktif = kostum.id === window.aktifKostumId;
        let div = document.createElement("div");
        div.className = "kostum-kart";
        div.style.position = "relative"; 
        div.style.width = "90px"; 
        div.style.borderRadius = "8px";
        div.style.border = isAktif ? "2px solid #0ea5e9" : "2px solid #cbd5e1";
        div.style.backgroundColor = isAktif ? "#e0f2fe" : "#ffffff";
        div.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)"; 
        div.style.cursor = "pointer";
        div.style.display = "flex"; 
        div.style.flexDirection = "column"; 
        div.style.alignItems = "center";
        
        div.onclick = () => {
            if (contextMenu) contextMenu.style.display = "none";
            if (!isAktif) window.kostumSec(kostum.id);
        };

        div.oncontextmenu = (e) => {
            e.preventDefault();
            e.stopPropagation();

            let kopyaBtn = document.getElementById("cmenu-kopya");
            let silBtn = document.getElementById("cmenu-sil");

            kopyaBtn.onclick = (ev) => {
                ev.stopPropagation();
                contextMenu.style.display = "none";
                window.kostumKopyala(kostum.id);
            };

            silBtn.onclick = (ev) => {
                ev.stopPropagation();
                contextMenu.style.display = "none";
                window.kostumSil(kostum.id);
            };

            contextMenu.style.left = e.clientX + "px";
            contextMenu.style.top = e.clientY + "px";
            contextMenu.style.display = "flex";
        };

        let imgContainer = document.createElement("div");
        imgContainer.style.width = "100%"; imgContainer.style.height = "70px"; imgContainer.style.display = "flex";
        imgContainer.style.justifyContent = "center"; imgContainer.style.alignItems = "center";

        let img = document.createElement("img");
        img.src = kostum.resimYolu || "data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=";
        img.style.maxWidth = "80%"; img.style.maxHeight = "80%"; img.style.objectFit = "contain";
        imgContainer.appendChild(img);

       let altBilgi = document.createElement("div");
        altBilgi.id = "isimLabel_" + kostum.id; // İsim kısımlarının ID'si burada belirleniyor
        altBilgi.style.width = "100%"; 
        altBilgi.style.backgroundColor = isAktif ? "#0ea5e9" : "transparent";
        altBilgi.style.color = isAktif ? "white" : "#475569"; 
        altBilgi.style.fontSize = "11px";
        altBilgi.style.textAlign = "center"; 
        altBilgi.style.padding = "4px 0"; 
        altBilgi.style.borderBottomLeftRadius = "5px"; 
        altBilgi.style.borderBottomRightRadius = "5px";
        altBilgi.innerText = kostum.adi;

        let no = document.createElement("div");
        no.innerText = index + 1; no.style.position = "absolute"; no.style.top = "4px"; no.style.left = "6px";
        no.style.fontSize = "10px"; no.style.fontWeight = "bold"; no.style.color = "#0ea5e9";

        div.appendChild(imgContainer); div.appendChild(altBilgi); div.appendChild(no);
        solPanel.appendChild(div);
    });

    let ekleKapsayici = document.createElement("div");
    ekleKapsayici.style.position = "relative"; ekleKapsayici.style.marginTop = "10px";

    let btnYeni = document.createElement("button");
    btnYeni.innerHTML = `<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 4px;"><rect x="3" y="11" width="18" height="10" rx="2"></rect><circle cx="12" cy="5" r="2"></circle><path d="M12 7v4"></path><line x1="8" y1="16" x2="8" y2="16"></line><line x1="16" y1="16" x2="16" y2="16"></line></svg><br>Kostüm Ekle`;
    btnYeni.style.width = "100%"; btnYeni.style.padding = "10px"; btnYeni.style.border = "2px dashed #94a3b8";
    btnYeni.style.backgroundColor = "transparent"; btnYeni.style.color = "#475569"; btnYeni.style.borderRadius = "8px";
    btnYeni.style.cursor = "pointer"; btnYeni.style.fontWeight = "bold"; btnYeni.style.fontSize = "11px";
    btnYeni.style.display = "flex"; btnYeni.style.flexDirection = "column"; btnYeni.style.alignItems = "center";
    btnYeni.onmouseover = () => btnYeni.style.backgroundColor = "#e2e8f0";
    btnYeni.onmouseout = () => btnYeni.style.backgroundColor = "transparent";

    let popoverMenu = document.createElement("div");
    popoverMenu.style.position = "absolute"; popoverMenu.style.bottom = "100%"; popoverMenu.style.left = "0";
    popoverMenu.style.width = "100%"; popoverMenu.style.backgroundColor = "white"; popoverMenu.style.border = "1px solid #cbd5e1";
    popoverMenu.style.borderRadius = "8px"; popoverMenu.style.boxShadow = "0 -4px 6px -1px rgba(0, 0, 0, 0.1)";
    popoverMenu.style.display = "none"; popoverMenu.style.flexDirection = "column"; popoverMenu.style.overflow = "hidden";
    popoverMenu.style.zIndex = "100"; popoverMenu.style.marginBottom = "8px";

    btnYeni.onclick = (e) => {
        e.stopPropagation();
        popoverMenu.style.display = popoverMenu.style.display === "none" ? "flex" : "none";
    };

    let secenekStil = "padding: 10px 8px; font-size: 11px; cursor: pointer; color: #334155; border-bottom: 1px solid #f1f5f9; text-align: left; display:flex; align-items:center; gap:6px;";
    
    let optCiz = document.createElement("div");
    optCiz.innerHTML = "🖌️ Yeni Kostüm Çiz";
    optCiz.style.cssText = secenekStil;
    optCiz.onclick = () => { popoverMenu.style.display = "none"; window.yeniKostumEkle(); };

    let optYukle = document.createElement("div");
    optYukle.innerHTML = "📂 Kostüm Yükle";
    optYukle.style.cssText = secenekStil;
    optYukle.onclick = () => { popoverMenu.style.display = "none"; document.getElementById("gizliKostumYukleyici").click(); };

    popoverMenu.appendChild(optCiz);
    popoverMenu.appendChild(optYukle);

    ekleKapsayici.appendChild(btnYeni);
    ekleKapsayici.appendChild(popoverMenu);
    solPanel.appendChild(ekleKapsayici);

    if (!document.getElementById("gizliKostumYukleyici")) {
        let fileInput = document.createElement("input");
        fileInput.type = "file"; fileInput.id = "gizliKostumYukleyici";
        fileInput.accept = ".png, .jpg, .jpeg, .svg"; fileInput.style.display = "none";
        
        fileInput.onchange = function(event) {
            let file = event.target.files[0];
            if (!file) return;
            
            let reader = new FileReader();
            reader.onload = function(f) {
                window.mevcutKostumuHafizayaAl();
                let seciliKukla = window.projeKuklalari.find(k => k.id === window.aktifKuklaId);
                let yeniId = "k_" + Date.now();
                let sira = seciliKukla.kostumler.length + 1;
                
                // Başlangıçta boş bir kostüm iskeleti oluşturuyoruz
                let yeniKostum = { id: yeniId, adi: "costume" + sira, resimYolu: "", cizimData: null };
                seciliKukla.kostumler.push(yeniKostum);
                
                // 1. EĞER YÜKLENEN DOSYA SVG İSE (Vektörel Çeviri)
                if (file.type === "image/svg+xml" || file.name.toLowerCase().endsWith(".svg")) {
                    window.kostumSec(yeniId); // Önce boş sahneyi aç
                   paper.project.importSVG(f.target.result, function(item) {
                            item.position = new paper.Point(240, 180);
                            
                            // DÜZELTME: window. ve boyut okuma koruması eklendi
                            if (window.aktifKuklaOlcegi == null) {
                                let idealBoyut = 300;
                                let w = (item.bounds && item.bounds.width) ? item.bounds.width : 1;
                                let h = (item.bounds && item.bounds.height) ? item.bounds.height : 1;
                                
                                if (w > idealBoyut || h > idealBoyut) {
                                    window.aktifKuklaOlcegi = Math.min(idealBoyut / w, idealBoyut / h);
                                } else {
                                    window.aktifKuklaOlcegi = 1;
                                }
                            }
                            item.scale(window.aktifKuklaOlcegi);
                            
                            if (item.className === "Group") {
                                item.children.forEach(child => child.locked = false);
                            }
                            
                            window.cizimKatmani.addChild(item);
                        if (typeof window.tumSecimleriTemizle === "function") window.tumSecimleriTemizle();
                        if (typeof window.durumuKaydet === "function") window.durumuKaydet();
                        
                        // SVG'nin küçük önizleme resmini (thumbnail) oluştur ve sol paneli yenile
                        if (typeof window.mevcutKostumuHafizayaAl === "function") window.mevcutKostumuHafizayaAl();
                        if (typeof window.kostumListesiniCiz === "function") window.kostumListesiniCiz();
                    });
                } 
                // 2. EĞER PNG VEYA JPG İSE (Düz Resim)
                else {
                    yeniKostum.resimYolu = f.target.result;
                    window.kostumSec(yeniId);
                }
                
                fileInput.value = ""; 
            };
            
            // Okuma Formatını Ayarla (SVG'ler metin olarak okunursa hatasız şekillere ayrışır)
            if (file.type === "image/svg+xml" || file.name.toLowerCase().endsWith(".svg")) {
                reader.readAsText(file); 
            } else {
                reader.readAsDataURL(file); 
            }
        };
        document.body.appendChild(fileInput);
    }
}; 
// --- KOSTÜM YÖNETİMİ BİTİŞİ --- taha sarı
// GÜVENLİ SEÇİM YARDIMCISI: Grupların içine kadar girer, çökmeleri engeller
// GÜVENLİ SEÇİM YARDIMCISI (Hata almayacak şekilde optimize edildi)
window.tumOgeleriTopla = function(parentItem, hedefDizi) {
    if (!parentItem) return;
    if (parentItem !== window.cizimKatmani && parentItem.className !== "Group" && !parentItem.locked) {
        hedefDizi.push(parentItem);
    }
    if (parentItem.children && parentItem.children.length > 0) {
        parentItem.children.forEach(function(child) {
            window.tumOgeleriTopla(child, hedefDizi);
        });
    }
};
 
