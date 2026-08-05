/**
 * ============================================================================
 * Dosya Yolu: v2_gorev_sistemi/assets/js/ogrenci/oyun_sahnesi.js
 * Kullanıcı Yetkisi ve Bağlı Olduğu Sayfa: Öğrenci -> Görev Oynatıcı Sayfası
 * Halk Dilinde İşlevi: Öğrencinin yazdığı kodların (Sola git, dön, sek) ekrandaki 
 * kuklaya fiziksel olarak yansımasını sağlayan 2D Fizik ve Oyun Motorudur.
 * Temel İşlevler:
 * 1. Kuklanın X, Y, Açı, Süzülme ve Sekme hareketlerini yönetir.
 * 2. Klavyeden veya fareden gelen tıklamaları dinler.
 * 3. Oyun her başladığında sahneyi sıfırlar.
 * YENİ EKLENEN ÖZELLİKLER:
 * - [Bugün] Süzülerek Rastgele Gitme ve X/Y Koordinat kontrolü eklendi.
 * ============================================================================
 */

// ==========================================
// 1. EVRENSEL OYUN MOTORU (HAREKET KASLARI)
// ==========================================

window.kuklaHareketEt = function (adimGirdi) {
  const kukla = document.getElementById("aktifKukla");
  const balon = document.getElementById("konusmaBalonu");
  if (!kukla) return;

  let mevcutX = parseFloat(kukla.style.left) || 20;
  let mevcutY = parseFloat(kukla.style.top) || 90;
  let aci = parseInt(kukla.getAttribute("data-aci")) || 0;

  let radyan = (aci * Math.PI) / 180;
  let yeniX = mevcutX + adimGirdi * Math.cos(radyan);
  let yeniY = mevcutY + adimGirdi * Math.sin(radyan);

  kukla.style.left = yeniX + "px";
  kukla.style.top = yeniY + "px";
  if (balon) {
    balon.style.left = yeniX + 50 + "px";
    balon.style.top = yeniY - 50 + "px";
  }
};

window.kuklaDon = function (dereceGirdi) {
  const kukla = document.getElementById("aktifKukla");
  if (!kukla) return;
  let mevcutAci = kukla.getAttribute("data-aci") ? parseInt(kukla.getAttribute("data-aci")) : 0;
  let yeniAci = mevcutAci + parseInt(dereceGirdi);
  kukla.style.transform = "rotate(" + yeniAci + "deg)";
  kukla.setAttribute("data-aci", yeniAci);
};

window.kuklaKonus = function (mesajGirdi, sureGirdi) {
  const balon = document.getElementById("konusmaBalonu");
  if (!balon) return;
  balon.innerHTML = mesajGirdi;
  balon.style.display = "block";
  if (sureGirdi < 9000) setTimeout(() => { balon.style.display = "none"; }, sureGirdi * 1000);
};

window.kuklaRastgeleGit = function() {
    const kukla = document.getElementById('aktifKukla');
    const sahne = document.querySelector('.oyun-sahnesi');
    if(!kukla || !sahne) return;
    
    let maxX = sahne.offsetWidth - (kukla.offsetWidth || 50);
    let maxY = sahne.offsetHeight - (kukla.offsetHeight || 50);
    let rndX = Math.floor(Math.random() * Math.max(0, maxX));
    let rndY = Math.floor(Math.random() * Math.max(0, maxY));
    
    kukla.style.left = rndX + 'px';
    kukla.style.top = rndY + 'px';
};

window.kuklaSuzulRastgele = function(sureGirdi) {
    const kukla = document.getElementById('aktifKukla');
    const sahne = document.querySelector('.oyun-sahnesi');
    if(!kukla || !sahne) return;
    
    let sure = parseFloat(sureGirdi) || 1;
    let maxX = sahne.offsetWidth - (kukla.offsetWidth || 50);
    let maxY = sahne.offsetHeight - (kukla.offsetHeight || 50);
    let rndX = Math.floor(Math.random() * Math.max(0, maxX));
    let rndY = Math.floor(Math.random() * Math.max(0, maxY));
    
    // Geçici olarak CSS animasyonunu aç, süre bitince kapat
    kukla.style.transition = 'left ' + sure + 's ease-in-out, top ' + sure + 's ease-in-out';
    kukla.style.left = rndX + 'px';
    kukla.style.top = rndY + 'px';

    setTimeout(() => { kukla.style.transition = 'none'; }, sure * 1000);
};

window.kuklaKoordinataGit = function(xGirdi, yGirdi) {
    const kukla = document.getElementById('aktifKukla');
    if(!kukla) return;
    kukla.style.left = xGirdi + 'px';
    kukla.style.top = yGirdi + 'px';
};

window.kuklaXDegistir = function(miktar) {
    const kukla = document.getElementById('aktifKukla');
    if(!kukla) return;
    let yeniX = (parseFloat(kukla.style.left) || 20) + parseFloat(miktar);
    kukla.style.left = yeniX + 'px';
};

window.kuklaYDegistir = function(miktar) {
    const kukla = document.getElementById('aktifKukla');
    if(!kukla) return;
    let yeniY = (parseFloat(kukla.style.top) || 90) + parseFloat(miktar);
    kukla.style.top = yeniY + 'px';
};

// ==========================================
// 2. GÖRÜNÜM KASLARI
// ==========================================

window.kuklaGizle = function() {
    const kukla = document.getElementById('aktifKukla');
    if(kukla) kukla.style.display = 'none';
};

window.kuklaGoster = function() {
    const kukla = document.getElementById('aktifKukla');
    if(kukla) kukla.style.display = 'block';
};

window.kuklaBoyutYap = function(yuzde) {
    const kukla = document.getElementById('aktifKukla');
    if(kukla) kukla.style.fontSize = (50 * (parseFloat(yuzde) / 100)) + 'px';
};

window.sahneDekorGec = function(dekorKodu) {
    const sahne = document.querySelector('.oyun-sahnesi');
    if(!sahne) return;
    if (dekorKodu === "bg_uzay") sahne.style.background = '#000033';
    else if (dekorKodu === "bg_col") sahne.style.background = '#d2b48c';
    else sahne.style.background = '#0f172a';
};

window.kuklaKatmanDegistir = function(katman) {
    const kukla = document.getElementById('aktifKukla');
    if(kukla) kukla.style.zIndex = (katman === "FRONT") ? "999" : "1";
};

// ==========================================
// 3. FİZİK ÇARPIŞMA (SEKME) KASI
// ==========================================

window.kuklaKenardanSek = function () {
  const kukla = document.getElementById("aktifKukla");
  const sahne = document.querySelector(".oyun-sahnesi");
  if (!kukla || !sahne) return;

  let x = parseFloat(kukla.style.left) || 20;
  let y = parseFloat(kukla.style.top) || 90;
  let w = kukla.offsetWidth || 50;
  let h = kukla.offsetHeight || 50;
  let sahneW = sahne.offsetWidth;
  let sahneH = sahne.offsetHeight;
  let aci = parseInt(kukla.getAttribute("data-aci")) || 0;
  let sektiMi = false;

  if (x <= 0) { x = 0; aci = 180 - aci; sektiMi = true; } 
  else if (x + w >= sahneW) { x = sahneW - w; aci = 180 - aci; sektiMi = true; }

  if (y <= 0) { y = 0; aci = 360 - aci; sektiMi = true; } 
  else if (y + h >= sahneH) { y = sahneH - h; aci = 360 - aci; sektiMi = true; }

  if (sektiMi) {
    aci = aci % 360;
    if (aci < 0) aci += 360;
    kukla.style.left = x + "px";
    kukla.style.top = y + "px";
    kukla.style.transform = "rotate(" + aci + "deg)";
    kukla.setAttribute("data-aci", aci);
  }
};

window.sahneyiSifirla = function () {
  const kukla = document.getElementById("aktifKukla");
  if (kukla) {
    kukla.style.left = "20px"; kukla.style.top = "90px";
    kukla.style.transform = "rotate(0deg)";
    kukla.setAttribute("data-aci", 0);
    kukla.style.display = "block";
    kukla.style.fontSize = "50px";
    kukla.style.transition = 'none'; // Animasyonları sıfırla
  }
  const balon = document.getElementById("konusmaBalonu");
  if (balon) balon.style.display = "none";
};

// ==========================================
// KUKLA KONTROL PANELİ (ÇİFT YÖNLÜ SENKRONİZASYON)
// ==========================================

// 1. Panelden girilen sayıları Kuklaya aktar
window.arayuzdenKuklaGuncelle = function() {
    const kukla = document.getElementById('aktifKukla');
    if(!kukla) return;
    
    kukla.style.left = document.getElementById('panelX').value + 'px';
    kukla.style.top = document.getElementById('panelY').value + 'px';
    
    // Boyut yüzdesini font büyüklüğüne (50px referans) çevir
    let boyut = document.getElementById('panelBoyut').value;
    kukla.style.fontSize = (50 * (boyut / 100)) + 'px';
    
    let aci = document.getElementById('panelYon').value;
    kukla.style.transform = 'rotate(' + aci + 'deg)';
    kukla.setAttribute('data-aci', aci);
};

// 2. Göster/Gizle Butonları
window.kuklaGosterGizle = function(durum) {
    const kukla = document.getElementById('aktifKukla');
    if(!kukla) return;
    
    kukla.style.display = durum ? 'block' : 'none';
    document.getElementById('btnGoster').classList.toggle('aktif', durum);
    document.getElementById('btnGizle').classList.toggle('aktif', !durum);
};

// 3. Kukla hareket ettiğinde Paneli otomatik güncelle (Live Tracker)
setInterval(() => {
    const kukla = document.getElementById('aktifKukla');
    // Eğer öğrenci o an kutunun içine sayı yazıyorsa, yazdığını silmemek için beklet
    if(!kukla || document.activeElement.tagName === 'INPUT') return; 

    let mevcutX = parseInt(kukla.style.left) || 0;
    let mevcutY = parseInt(kukla.style.top) || 0;
    let mevcutAci = parseInt(kukla.getAttribute('data-aci')) || 0;
    
    // Açıyı 0-360 derecesinde tutarak panele yaz
    mevcutAci = mevcutAci % 360;
    if (mevcutAci < 0) mevcutAci += 360;

    let fontBuyuklugu = parseInt(window.getComputedStyle(kukla).fontSize) || 50;
    let mevcutBoyut = Math.round((fontBuyuklugu / 50) * 100);

    document.getElementById('panelX').value = mevcutX;
    document.getElementById('panelY').value = mevcutY;
    document.getElementById('panelYon').value = mevcutAci;
    document.getElementById('panelBoyut').value = mevcutBoyut;
}, 50); // Saniyede 20 kez kuklanın konumunu tarar
// 4. Kukla İsimlendirme Motoru
window.kuklaIsminiGuncelle = function() {
    const kukla = document.getElementById('aktifKukla');
    const yeniIsim = document.getElementById('panelKuklaAdi').value;
    
    if(kukla) {
        // 1. Fareyle kuklanın üzerine gelince ismini gösterir (Tooltip efekti)
        kukla.setAttribute('title', yeniIsim);
        
        // 2. İleride birden fazla kukla eklersek onları ayırt edebilmek için kimliğine yazar
        kukla.setAttribute('data-isim', yeniIsim);
    }
};

// Sayfa ilk yüklendiğinde varsayılan ismi (Astronot) kuklaya ata
document.addEventListener("DOMContentLoaded", function () {
    setTimeout(window.kuklaIsminiGuncelle, 500); 
});

// ==========================================
// 4. MERKEZİ OLAY YÖNETİCİSİ VE DİNLEYİCİLER
// ==========================================

window.oyunTetikleyicileri = { tuslar: {}, bayrak: [], kukla: [] };

window.oyunaTusEkle = function (tus, callback) {
  if (!window.oyunTetikleyicileri.tuslar[tus]) window.oyunTetikleyicileri.tuslar[tus] = [];
  window.oyunTetikleyicileri.tuslar[tus].push(callback);
};
window.oyunaBayrakEkle = function (callback) { window.oyunTetikleyicileri.bayrak.push(callback); };
window.oyunaKuklaEkle = function (callback) { window.oyunTetikleyicileri.kukla.push(callback); };

window.motoruTemizle = function () {
  window.oyunTetikleyicileri = { tuslar: {}, bayrak: [], kukla: [] };
  window.sahneyiSifirla();
};
// --- ODAK (FOCUS) YÖNETİMİ ---
window.sahneOdaktaMi = true; // Varsayılan olarak aktif

// Fare tarayıcı dışına (örn: adres çubuğuna) çıkarsa dinlemeyi kes
document.addEventListener('mouseenter', () => window.sahneOdaktaMi = true);
document.addEventListener('mouseleave', () => window.sahneOdaktaMi = false);

document.addEventListener("DOMContentLoaded", function () {
  // Görev Metnini Yükle
  const hikayeAlani = document.getElementById("hikayeAlani");
  if (hikayeAlani && typeof SUNUCUDAN_GELEN_GOREV !== "undefined" && typeof edjsHTML !== "undefined") {
    try {
      const edjsParser = edjsHTML();
      let htmlIcerik = edjsParser.parse(SUNUCUDAN_GELEN_GOREV.hikaye_metni);
      hikayeAlani.innerHTML = htmlIcerik.join("");
    } catch (e) {
      hikayeAlani.innerHTML = SUNUCUDAN_GELEN_GOREV.hikaye_metni || "Görev metni yüklenemedi.";
    }
  }

// Klavye Dinleyicisi (Gelişmiş, Toleranslı ve Odak Kontrollü)
  document.addEventListener("keydown", function (e) {
    // Eğer fare tarayıcı dışındaysa veya input/textarea içine yazı yazılıyorsa dinleme!
    if (!window.sahneOdaktaMi || e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

    let basilan = e.code.toUpperCase(); 
    let basilanKey = e.key.toUpperCase(); 

    if (["SPACE", "ARROWUP", "ARROWDOWN", "ARROWLEFT", "ARROWRIGHT"].includes(basilan)) {
      e.preventDefault();
    }

    // Başlangıç: for (let tus in window.oyunTetikleyicileri.tuslar) { satırının yerine yapışacak
for (let tus in window.oyunTetikleyicileri.tuslar) {
    let beklenen = tus.toUpperCase();
    
    // ANY (hiç) tuşu için güvenlik eklendi
    if (beklenen === "ANY" || basilan.includes(beklenen) || basilanKey === beklenen || ((beklenen === "BOŞLUK" || beklenen === "BOSLUK") && basilan === "SPACE")) {
        window.oyunTetikleyicileri.tuslar[tus].forEach((cb) => cb());
    }
}
// Bitiş: for döngüsünün kapandığı } işaretinin üzerinde kalacak
  });
  // Kukla Tıklama ve Sürükleme
  const kukla = document.getElementById("aktifKukla");
  if (kukla) {
    kukla.addEventListener("click", function () {
      window.oyunTetikleyicileri.kukla.forEach((cb) => cb());
    });

    let surukleniyorMu = false;
    let basY, basX, basSol, basUst;

    kukla.addEventListener("mousedown", function (e) {
      surukleniyorMu = true;
      basX = e.clientX; basY = e.clientY;
      basSol = parseInt(window.getComputedStyle(kukla).left) || 0;
      basUst = parseInt(window.getComputedStyle(kukla).top) || 0;
      kukla.style.cursor = "grabbing";
    });

    document.addEventListener("mousemove", function (e) {
      if (!surukleniyorMu) return;
      kukla.style.left = basSol + (e.clientX - basX) + "px";
      kukla.style.top = basUst + (e.clientY - basY) + "px";
    });

    document.addEventListener("mouseup", function () {
      surukleniyorMu = false;
      kukla.style.cursor = "grab";
    });
  }

  // Blockly Yükleme
  if (typeof window.toolboxOlustur === "function") {
    /* [2026-08] ARAÇ KUTUSU BOŞ GELİYORDU — GERÇEK SEBEP BURASIYDI:
       toolboxOlustur(liste) yalnızca listedeki blokları gösterir; 'HEPSI'
       yazılırsa hepsini gösterir. Burada boş dizi ([]) geçiliyordu ve
       [].includes(blok) her zaman false döndüğü için araç kutusu TAMAMEN
       BOŞ üretiliyordu.
       Görevin izin_verilen_bloklar listesi yoksa artık 'HEPSI' geçiyoruz. */
    const izinListesi = (SUNUCUDAN_GELEN_GOREV && SUNUCUDAN_GELEN_GOREV.izin_verilen_bloklar) || [];
    const izinVerilenler = (Array.isArray(izinListesi) && izinListesi.length > 0) ? izinListesi : "HEPSI";

    // Aynı sayfada iki araç kutusu kalmasın (tekrar yüklenme ihtimaline karşı)
    const eski = document.getElementById("toolbox");
    if (eski) eski.remove();

    document.body.insertAdjacentHTML("beforeend", window.toolboxOlustur(izinVerilenler));
  }

// ========================================================================
  // --- SAĞ TIK MENÜSÜ (CONTEXT MENU) VE ÖZEL ÇOĞALTMA MOTORU ---
  // ========================================================================
  if (typeof Blockly !== "undefined" && Blockly.ContextMenuRegistry) {
    try {
      // 1. Orijinal ve gereksiz menüleri sistemden tamamen sil
      Blockly.ContextMenuRegistry.registry.unregister('blockDuplicate'); // Yarım akıllı varsayılan kopyalamayı sil
      Blockly.ContextMenuRegistry.registry.unregister('blockInline'); // Harici/Satır içi yap seçeneğini sil
      Blockly.ContextMenuRegistry.registry.unregister('blockCollapseExpand'); // Daralt/Genişlet seçeneğini sil
      
      // 2. Tam mBlock mantığında (Alt bloklarla ve içindekilerle birlikte) Çoğaltma Motoru
      let ozelCogaltMenu = {
        displayText: 'Çoğalt',
        preconditionFn: function(scope) {
          // Eğer blok silinebilir ve hareket ettirilebilir durumdaysa menüde göster
          return (scope.block.isDeletable() && scope.block.isMovable()) ? 'enabled' : 'hidden';
        },
        callback: function(scope) {
          let block = scope.block;
          let workspace = block.workspace;
          
          // SİHİRLİ NOKTA: Bloğu, içindeki döngüleri ve altındaki tüm bağlı blokları XML olarak kopyala. 
          // (İkinci parametre olan 'true', blok ID'lerinin çakışmasını engeller)
          let xmlDom = Blockly.Xml.blockToDom(block, true); 
          
          // Klonlanan bu devasa yapıyı sahneye yapıştır
          let yeniBlok = Blockly.Xml.domToBlock(xmlDom, workspace);
          
          // Yeni bloğu, eskisinin tam üstüne binmesin diye biraz sağına ve aşağısına kaydır
          let mevcutKonum = block.getRelativeToSurfaceXY();
          yeniBlok.moveBy(mevcutKonum.x + 40, mevcutKonum.y + 40);
          
          // Çoğaltılan yeni grubu seçili (sarı çerçeveli) hale getir
          yeniBlok.select();
        },
        scopeType: Blockly.ContextMenuRegistry.ScopeType.BLOCK, // Sadece bloklara tıklandığında çıksın
        id: 'ozel_mblock_cogalt', // Bizim motorumuzun ID'si
        weight: 1 // Menüde en üstte çıksın
      };
      
      // Kendi yazdığımız bu özel menüyü sisteme kaydet
      Blockly.ContextMenuRegistry.registry.register(ozelCogaltMenu);

    } catch (err) {
      console.warn("Menü ayarlama hatası:", err);
    }
  }

  // Blockly Başlat ve Canlı Derleyiciyi Bağla
  try {
    window.ogrenciWorkspace = Blockly.inject("blocklyOgrenci", {
      toolbox: document.getElementById("toolbox"),
      renderer: "zelos",
      theme: Blockly.Themes.Zelos,
      trashcan: true,
      scrollbars: true,
      // --- SİHİRLİ DOKUNUŞ: ZOOM (MERCEK) KONTROLLERİ ---
    zoom: {
        controls: true,  // Sağ alt köşeye (+), (-) ve (Merkezle) butonlarını koyar
        wheel: false,    // Mouse tekerleği (scroll) ile kazara büyütüp küçültmeyi tamamen yasaklar
        startScale: 0.9, // İlk açılış büyüklüğü (biraz küçültülmüş ferah görünüm)
        maxScale: 2.5,   // En fazla ne kadar büyütülebileceği
        minScale: 0.4,   // En fazla ne kadar küçültülebileceği
        scaleSpeed: 1.2  // Merceklere tıklanınca büyüme/küçülme hızı
    }
    });

    // Sihirli Dokunuş: Çalışma alanını Canlı Derleyiciye emanet et
    if (typeof window.canliDerleyiciyiBaslat === "function") {
      window.canliDerleyiciyiBaslat(window.ogrenciWorkspace);
    }
  } catch (err) { 
    console.error("Blockly Hatası:", err); 
  }
});
// ========================================================================
// --- 5. OYUN MOTORU: ALGILAMA VE ÇARPIŞMA RADARI (SENSING & COLLISION) ---
// ========================================================================

// --- A. TUŞ TAKİP SİSTEMİ (KEY STATE POLLING) ---
window.basiliTuslar = {}; // O an basılı olan tüm tuşların listesini tutar

// Tuşa basıldığında listeye "true" olarak kaydet
document.addEventListener("keydown", function (e) {
    if (!window.sahneOdaktaMi || e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    window.basiliTuslar[e.code.toUpperCase()] = true;
    window.basiliTuslar[e.key.toUpperCase()] = true;
    if (e.code === "Space") window.basiliTuslar["SPACE"] = true; // Boşluk tuşu garantisi
});

// Tuştan el çekildiğinde listeden "false" yaparak sil
document.addEventListener("keyup", function (e) {
    window.basiliTuslar[e.code.toUpperCase()] = false;
    window.basiliTuslar[e.key.toUpperCase()] = false;
    if (e.code === "Space") window.basiliTuslar["SPACE"] = false;
});

// Bloktan gelen sorgu: "Şu an bu tuşa basılı mı?"
window.tusaBasiliMi = function(beklenenTus) {
    if (beklenenTus === "ANY") {
        // Herhangi bir tuşa basılıp basılmadığını kontrol et
        return Object.values(window.basiliTuslar).some(durum => durum === true);
    }
    // İstenen özel tuşun durumunu döndür
    return !!window.basiliTuslar[beklenenTus.toUpperCase()];
};

// --- B. FARE TAKİP SİSTEMİ (MOUSE TRACKER) ---
window.fareX = 0;
window.fareY = 0;

// Fare hareket ettikçe lazer radarı güncellenir
document.addEventListener("mousemove", function(e) {
    window.fareX = e.clientX;
    window.fareY = e.clientY;
});

// Bloktan gelen sorgu: "Fare Kuklaya Değiyor mu?"
window.fareyeDegiyorMu = function() {
    const kukla = document.getElementById('aktifKukla');
    if (!kukla || kukla.style.display === 'none') return false;

    // Kuklanın ekranda kapladığı gerçek dikdörtgen alan (AABB)
    const kutu = kukla.getBoundingClientRect(); 

    // Farenin koordinatları bu dikdörtgenin içine girmiş mi?
    return (window.fareX >= kutu.left && window.fareX <= kutu.right &&
            window.fareY >= kutu.top && window.fareY <= kutu.bottom);
};

// --- C. KENAR ÇARPIŞMA SİSTEMİ (EDGE COLLISION) ---
// Bloktan gelen sorgu: "Kukla sahnenin kenarına çarptı mı?"
window.kenaraDegiyorMu = function() {
    const kukla = document.getElementById('aktifKukla');
    const sahne = document.querySelector('.oyun-sahnesi') || document.getElementById('oyunAlan'); 
    
    if (!kukla || !sahne || kukla.style.display === 'none') return false;

    const kuklaKutu = kukla.getBoundingClientRect();
    const sahneKutu = sahne.getBoundingClientRect();

    // Tolerans: Tam 0'a 0 değil, birkaç piksel taşmayı da değme sayıyoruz ki oyun takılmasın
    return (
        kuklaKutu.left <= sahneKutu.left + 5 ||   // Sol duvara çarptı
        kuklaKutu.right >= sahneKutu.right - 5 || // Sağ duvara çarptı
        kuklaKutu.top <= sahneKutu.top + 5 ||     // Tavana çarptı
        kuklaKutu.bottom >= sahneKutu.bottom - 5  // Yere çarptı
    );
};
// ========================================================================
// --- 6. OYUN MOTORU: SÜREKLİ TEKRARLA & DURDURMA MANTIGI (60 FPS) ---
// ========================================================================
window.aktifOyunDonguleri = [];
window.oyunMotoruCalisiyor = false; // Güvenlik kilidi

// Sürekli tekrarla bloğundan gelen kodları hafızaya alır
window.oyunDongusuneEkle = function(fonk) {
    window.aktifOyunDonguleri.push(fonk);
};

// Kırmızı Durdur Butonuna basıldığında çalışan Acil Fren Sistemi
window.oyunuDurdur = function() {
    window.oyunMotoruCalisiyor = false; // Motor ateşlemesini kes
    window.aktifOyunDonguleri = [];     // Hafızadaki tüm döngüleri çöpe at
    window.basiliTuslar = {};           // Takılı kalan tuşları sıfırla
    console.log("🛑 Oyun Durduruldu!");
};
// Başlangıç: if (window.anaDonguID) cancelAnimationFrame(window.anaDonguID); satırının ÜZERİNE yapıştırılacak
// --- OLAY DİNLEYİCİ KAYIT MERKEZİ (ŞAPKA BLOKLARI İÇİN) ---
window.oyunTetikleyicileri = { tuslar: {}, bayrak: [], kukla: [] };

window.oyunaBayrakEkle = function(fonk) {
    window.oyunTetikleyicileri.bayrak.push(fonk);
};
window.oyunaKuklaEkle = function(fonk) {
    window.oyunTetikleyicileri.kukla.push(fonk);
};
window.oyunaTusEkle = function(tus, fonk) {
    if(!window.oyunTetikleyicileri.tuslar[tus]) window.oyunTetikleyicileri.tuslar[tus] = [];
    window.oyunTetikleyicileri.tuslar[tus].push(fonk);
};
// Bitiş: if (window.anaDonguID) satırının üzerinde kalacak
// Çakışmaları engellemek için varsa eski motor kimliğini iptal et
if (window.anaDonguID) cancelAnimationFrame(window.anaDonguID);

// Tarayıcının ekran yenileme hızına (60 FPS) senkronize çalışan ana kalp
function anaOyunDongusu() {
    // Motor sadece "Çalışıyor" durumundaysa ve içinde kod varsa döner
    if (window.oyunMotoruCalisiyor && window.aktifOyunDonguleri.length > 0) {
        window.aktifOyunDonguleri.forEach(fonk => {
            try { fonk(); } catch(e) {}
        });
    }
    // Kalbi saniyede 60 kez atmaya devam ettir
    window.anaDonguID = requestAnimationFrame(anaOyunDongusu); 
}
anaOyunDongusu(); // Sistemi bekleme modunda başlat

// --- BUTONLARI SİSTEME BAĞLAMA ---
document.addEventListener("DOMContentLoaded", function() {
    let btnYesil = document.getElementById("btnYesilBayrak");
    let btnDur = document.getElementById("btnDurdur");
    
    if (btnYesil) btnYesil.addEventListener("click", window.yesilBayraklariTetikle);
    if (btnDur) btnDur.addEventListener("click", window.oyunuDurdur);
});
// ========================================================================
// --- 7. ZAMAN MAKİNESİ (ASENKRON BEKLEME MOTORU) ---
// ========================================================================
window.oyunBekle = function(saniye) {
    // Motoru dondurmadan, sadece o anki bloğun belirtilen süre kadar beklemesini sağlar
    return new Promise(resolve => setTimeout(resolve, saniye * 1000));
};

// ========================================================================
// --- 8. GÖRÜNÜM VE ANİMASYON MOTORU ---
// ========================================================================

// Süresiz Konuşma (Sadece balonu açar ve bırakır)
window.kuklaKonus = function(mesaj) {
    let balon = document.getElementById("konusmaBalonu");
    if(balon) {
        balon.innerText = mesaj.replace(/^'|'$/g, '').replace(/^"|"$/g, ''); // Tırnakları temizle
        balon.style.display = "block";
        balon.style.borderRadius = "15px"; // Normal konuşma balonu köşesi
    }
};

// Süreli Konuşma (Balonu açar, bekler ve kapatır)
window.kuklaKonusSureli = async function(mesaj, sure) {
    window.kuklaKonus(mesaj);
    await window.oyunBekle(sure);
    document.getElementById("konusmaBalonu").style.display = "none";
};

// Süresiz Düşünme (Balonu açar, bulut şekli verir)
window.kuklaDusun = function(mesaj) {
    let balon = document.getElementById("konusmaBalonu");
    if(balon) {
        balon.innerText = mesaj.replace(/^'|'$/g, '').replace(/^"|"$/g, '');
        balon.style.display = "block";
        balon.style.borderRadius = "50%"; // Düşünme balonu efekti (yuvarlak bulut)
    }
};

// Süreli Düşünme (Balonu açar, bekler ve kapatır)
window.kuklaDusunSureli = async function(mesaj, sure) {
    window.kuklaDusun(mesaj);
    await window.oyunBekle(sure);
    document.getElementById("konusmaBalonu").style.display = "none";
};

// Kuklayı tamamen gizleme/gösterme
window.kuklaGizle = function() { 
    document.getElementById("aktifKukla").style.display = "none"; 
    document.getElementById("konusmaBalonu").style.display = "none"; 
};
window.kuklaGoster = function() { 
    document.getElementById("aktifKukla").style.display = "block"; 
};