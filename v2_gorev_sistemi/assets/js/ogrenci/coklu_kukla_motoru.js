/**
 * Dosya Yolu: v2_gorev_sistemi/assets/js/ogrenci/coklu_kukla_motoru.js
 * Kullanıcı Yetkisi ve Bağlı Olduğu Sayfa: Öğrenci -> Görev Oynatıcı Sayfası
 * Halk Dilinde İşlevi: Sahnede birden fazla kuklanın var olmasını, kütüphaneden çekilmesini ve blok kodlarının birbirine karışmadan çalışmasını sağlayan ana beyindir.
 * Temel İşlevler:
 * 1. Kuklaları dizi halinde hafızada tutar ve kütüphaneden API ile çeker.
 * 2. Sahnedeki kuklaların sürüklenip bırakılmasını yönetir.
 * 3. Sağ tık menüsü ile kuklaları kopyalama ve silme işlemlerini yapar.
 * YENİ EKLENEN ÖZELLİKLER:
 * - [Bugün] oynatici.php içindeki API (Fetch) istekleri ve motor kodları buraya taşındı.
 */

window.tumKuklalarVerisi = []; 
window.projeKuklalari = []; 
window.aktifKuklaId = null; 
window.sagTikKuklaId = null;

// ==========================================
// --- KUKLA KÜTÜPHANESİ API VE MODAL ---
// ==========================================
window.kuklaPenceresiniAc = function() {
    document.getElementById('kuklaSeciciModal').style.display = 'flex';
    if (window.tumKuklalarVerisi.length === 0) {
        fetch('../api/kukla_api.php')
            .then(response => response.json())
            .then(data => {
                if (data.durum === 'basarili') {
                    window.tumKuklalarVerisi = data.veri;
                    modalArayuzunuOlustur('hepsi');
                }
            })
            .catch(err => console.error("API Hatası:", err));
    }
};

window.kuklaPenceresiniKapat = function() {
    document.getElementById('kuklaSeciciModal').style.display = 'none';
};

window.modalArayuzunuOlustur = function(seciliGrup) {
    let filtreAlani = document.getElementById('modalFiltreAlani');
    let izgara = document.getElementById('modalKuklaIzgarasi');
    let gruplar = [...new Set(window.tumKuklalarVerisi.map(k => k.grup_adi))];
    
    if (filtreAlani.innerHTML.trim() === '') {
        let html = `<button class="m-filtre-btn aktif" onclick="modalArayuzunuOlustur('hepsi')">Tümü</button>`;
        gruplar.forEach(g => {
            html += `<button class="m-filtre-btn" onclick="modalArayuzunuOlustur('${g}')">${g}</button>`;
        });
        filtreAlani.innerHTML = html;
    }

    let butonlar = filtreAlani.querySelectorAll('.m-filtre-btn');
    butonlar.forEach(b => {
        if (b.innerText === (seciliGrup === 'hepsi' ? 'Tümü' : seciliGrup)) {
            b.classList.add('aktif');
        } else {
            b.classList.remove('aktif');
        }
    });

    let kuklaHtml = '';
    window.tumKuklalarVerisi.forEach(kukla => {
        if (seciliGrup === 'hepsi' || kukla.grup_adi === seciliGrup) {
            let kostumler = JSON.parse(kukla.kostumler_json);
            let kapakResmi = "../" + kostumler[0].yol; 
            let kuklaDataStr = encodeURIComponent(JSON.stringify(kukla));
            kuklaHtml += `
                <div class="secilebilir-kukla" onclick="sahnedeKuklayiDegistir('${kuklaDataStr}')">
                    <img src="${kapakResmi}" alt="${kukla.kukla_adi}">
                    <span>${kukla.kukla_adi}</span>
                </div>
            `;
        }
    });
    izgara.innerHTML = kuklaHtml;
};

// ==========================================
// --- ÇOKLU KUKLA YÖNETİM MOTORU ---
// ==========================================
window.addEventListener('load', () => {
    if(window.projeKuklalari.length === 0) {
        let varsayilanResim = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='50' height='50'><text y='40' font-size='40'>👩‍🚀</text></svg>";
        
        window.projeKuklalari.push({
            id: "kukla_varsayilan",
            adi: "Astronot",
            x: 50, y: 90, boyut: 100, yon: 0, goster: true,
            resimYolu: varsayilanResim,
            kostumler: [
                { id: "k_varsayilan_1", adi: "kostüm 1", resimYolu: varsayilanResim }
            ], // Varsayılan kuklaya da kostüm dizisini verdik
            kodXml: '<xml xmlns="https://developers.google.com/blockly/xml"></xml>'
        });
        setTimeout(() => { kuklaSec('kukla_varsayilan'); }, 800); 
    }
});

window.sahnedeKuklayiDegistir = function(kuklaDataStr) {
    let kuklaDB = JSON.parse(decodeURIComponent(kuklaDataStr));
    let kostumler = JSON.parse(kuklaDB.kostumler_json);
    
    // ÇÖZÜM: Veritabanındaki TÜM kostümleri döngüye sokup öğrenci paneline uygun (../) yollarla hazırlıyoruz
    let atolyeKostumleri = kostumler.map(k => {
        return {
            id: k.id,
            adi: k.adi,
            resimYolu: "../" + k.yol, 
            cizimData: k.cizimData || null
        };
    });
    
    let yeniKukla = {
        id: "kukla_" + Date.now(),
        adi: kuklaDB.kukla_adi,
        x: 100, y: 100, boyut: 100, yon: 0, goster: true,
        resimYolu: atolyeKostumleri[0].resimYolu, // Sahnede ilk kostüm görünür
        kostumler: atolyeKostumleri, // İŞTE EKSİK OLAN SATIR! Artık tüm kostümler hafızada.
        kodXml: '<xml xmlns="https://developers.google.com/blockly/xml"></xml>'
    };

    window.projeKuklalari.push(yeniKukla);
    kuklaPenceresiniKapat();
    kuklaSec(yeniKukla.id); 
};

window.kuklaSec = function(hedefId) {
    // [2026-08] Aynı kuklaya tekrar tıklamak kodu boşuna temizlemesin
    if (window.aktifKuklaId === hedefId) return;

    if(window.ogrenciWorkspace && window.aktifKuklaId) {
        let eskiKukla = window.projeKuklalari.find(k => k.id === window.aktifKuklaId);
        if(eskiKukla) {
            let dom = Blockly.Xml.workspaceToDom(window.ogrenciWorkspace);
            eskiKukla.kodXml = Blockly.Xml.domToText(dom);
        }
    }

    let seciliKukla = window.projeKuklalari.find(k => k.id === hedefId);

    /* [2026-08] KOD KAYBI ÖNLENDİ:
       Sayfa açılışında 800 ms sonra kuklaSec('kukla_varsayilan') çalışıyor ve
       workspace'i temizliyordu. Öğrenci o ilk saniyede blok eklediyse kodu
       siliniyordu. Ayrıca aktifKuklaId henüz null olduğu için kod hiçbir
       kuklaya da kaydedilmiyordu — tamamen kayboluyordu.
       Artık: hedef kuklanın kodu boşsa ve ekranda blok varsa, o bloklar
       silinmek yerine hedef kuklaya devredilir. */
    if (window.ogrenciWorkspace && seciliKukla && !window.aktifKuklaId) {
        const ekrandakiBlok = window.ogrenciWorkspace.getAllBlocks(false).length;
        const hedefKoduBos  = !seciliKukla.kodXml
                              || seciliKukla.kodXml.indexOf('<block') === -1;

        if (ekrandakiBlok > 0 && hedefKoduBos) {
            seciliKukla.kodXml = Blockly.Xml.domToText(
                Blockly.Xml.workspaceToDom(window.ogrenciWorkspace)
            );
        }
    }

    window.aktifKuklaId = hedefId;

    if(window.ogrenciWorkspace && seciliKukla) {
        window.ogrenciWorkspace.clear();
        if (seciliKukla.kodXml && seciliKukla.kodXml !== "") {
            try {
                let parser = new DOMParser();
                let xmlDoc = parser.parseFromString(seciliKukla.kodXml, "text/xml");
                Blockly.Xml.domToWorkspace(xmlDoc.documentElement, window.ogrenciWorkspace);
            } catch(e) { console.error("Kod yükleme hatası:", e); }
        }
    }

    document.getElementById('panelKuklaAdi').value = seciliKukla.adi;
    document.getElementById('panelX').value = seciliKukla.x;
    document.getElementById('panelY').value = seciliKukla.y;
    document.getElementById('panelBoyut').value = seciliKukla.boyut;
    document.getElementById('panelYon').value = seciliKukla.yon;
    arayuzdenGosterGizle(seciliKukla.goster, false);

    ekraniYenile();
};

window.arayuzdenAktifKuklayiGuncelle = function() {
    if(!window.aktifKuklaId) return;
    let seciliKukla = window.projeKuklalari.find(k => k.id === window.aktifKuklaId);
    
    seciliKukla.adi = document.getElementById('panelKuklaAdi').value;
    seciliKukla.x = parseInt(document.getElementById('panelX').value) || 0;
    seciliKukla.y = parseInt(document.getElementById('panelY').value) || 0;
    seciliKukla.boyut = parseInt(document.getElementById('panelBoyut').value) || 100;
    seciliKukla.yon = parseInt(document.getElementById('panelYon').value) || 0;

    ekraniYenile();
};

window.arayuzdenGosterGizle = function(durum, tetikle = true) {
    let btnGoster = document.getElementById('btnGoster');
    let btnGizle = document.getElementById('btnGizle');
    if(durum) { btnGoster.classList.add('aktif'); btnGizle.classList.remove('aktif'); } 
    else { btnGizle.classList.add('aktif'); btnGoster.classList.remove('aktif'); }

    if(tetikle && window.aktifKuklaId) {
        let secili = window.projeKuklalari.find(k => k.id === window.aktifKuklaId);
        secili.goster = durum;
        ekraniYenile();
    }
};

/**
 * Fonksiyon: ekraniYenile
 * Halk Dilinde İşlevi: Sahnedeki kuklaları mevcut ekran boyutuna göre oranlayarak çizer.
 * YENİ ÖZELLİK: Oransal ölçekleme (Responsive Scaling) eklendi.
 */
/* [2026-11] Kukla adı / resim yolu HTML'e basılmadan önce kaçırılır. */
function kuklaHtmlKacir(x) {
    return String(x == null ? '' : x)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

window.ekraniYenile = function() {
    let kapsayici = document.getElementById('kuklaListesiKapsayici');
    let html = '';
    
    // Alt Listeyi Çiz (Burası sabit kalabilir)
    window.projeKuklalari.forEach(k => {
        let aktifSinifi = k.id === window.aktifKuklaId ? 'aktif' : '';
        html += `
            <div class="kukla-oge ${aktifSinifi}" data-id="${k.id}" onclick="kuklaSec('${k.id}')" oncontextmenu="sagTikMenuAc(event, '${k.id}')">
                <div class="kukla-sil-ikon" onclick="event.stopPropagation(); kuklaSilIstegi('${k.id}')">✕</div>
                <img src="${kuklaHtmlKacir(k.resimYolu)}">
                <div class="k-isim" title="${kuklaHtmlKacir(k.adi)}">${kuklaHtmlKacir(k.adi)}</div>
            </div>
        `;
    });
    html += `<button class="btn-yeni-kukla" onclick="kuklaPenceresiniAc()" title="Kütüphaneden Yeni Kukla Ekle"><span>+</span> Ekle</button>`;
    kapsayici.innerHTML = html;

    // --- ORANSAL HESAPLAMA ---
    let sahne = document.querySelector('.oyun-sahnesi');
    let referansGenislik = 480; // Standart mBlock/Scratch genişliği
    let oran = sahne.clientWidth / referansGenislik; 

    window.projeKuklalari.forEach(k => {
        let img = document.querySelector(`.dinamik-kukla[data-id="${k.id}"]`);
        
        if(!img) {
            img = document.createElement('img');
            img.className = 'dinamik-kukla';
            img.setAttribute('data-id', k.id);
            img.setAttribute('draggable', 'false');
            img.style.position = 'absolute';
            sahne.appendChild(img);
        }
        
        img.src = k.resimYolu;
        img.style.display = k.goster ? 'block' : 'none';
        
        // ÇÖZÜM: Konum ve boyutu oranla çarpıyoruz
        img.style.left = (k.x * oran) + 'px';
        img.style.top = (k.y * oran) + 'px';
        img.style.width = (k.boyut * oran) + 'px';
        
        img.style.transform = `rotate(${k.yon}deg)`;
        img.style.cursor = window.suruklenenKuklaId === k.id ? 'grabbing' : 'grab';
        
        if(k.id === window.aktifKuklaId) {
            img.id = 'aktifKukla';
            img.style.filter = "drop-shadow(0px 0px 8px rgba(59, 130, 246, 0.8))";
            img.style.zIndex = "100"; 
        } else {
            if(img.id === 'aktifKukla') img.removeAttribute('id'); 
            img.style.filter = "none";
            img.style.zIndex = "10";
        }
    });

    document.querySelectorAll('.dinamik-kukla').forEach(el => {
        let id = el.getAttribute('data-id');
        if(!window.projeKuklalari.find(k => k.id === id)) { el.remove(); }
    });
};
// ==========================================
// --- SÜRÜKLE BIRAK MOTORU ---
// ==========================================
window.suruklenenKuklaId = null;
window.baslangicX = 0; window.baslangicY = 0;
let oyunSahnesiAlani = document.querySelector('.oyun-sahnesi');

oyunSahnesiAlani.addEventListener('mousedown', function(e) {
    if(e.target.classList.contains('dinamik-kukla')) {
        e.preventDefault(); 
        window.suruklenenKuklaId = e.target.getAttribute('data-id');
        if(window.aktifKuklaId !== window.suruklenenKuklaId) {
            kuklaSec(window.suruklenenKuklaId);
        }
        let rect = e.target.getBoundingClientRect();
        window.baslangicX = e.clientX - rect.left;
        window.baslangicY = e.clientY - rect.top;
        e.target.style.cursor = 'grabbing';
    }
});

/**
 * YENİ ÖZELLİK: Sürükleme sırasında ekran oranını hesaba katarak koordinat kaydeder.
 * YENİ EKLENEN ÖZELLİKLER: Oransal ters hesaplama.
 */
window.addEventListener('mousemove', function(e) {
    if(window.suruklenenKuklaId) {
        let sahne = document.querySelector('.oyun-sahnesi');
        let sahneRect = sahne.getBoundingClientRect();
        let oran = sahne.clientWidth / 480; // Mevcut büyüme oranı

        // Gerçek piksel farkını hesapla
        let fizikselX = e.clientX - sahneRect.left - window.baslangicX;
        let fizikselY = e.clientY - sahneRect.top - window.baslangicY;
        
        let kukla = window.projeKuklalari.find(k => k.id === window.suruklenenKuklaId);
        if(kukla) {
            // ÇÖZÜM: Fiziksel konumu orana bölerek mantıksal (sabit) koordinata çevir
            kukla.x = Math.round(fizikselX / oran);
            kukla.y = Math.round(fizikselY / oran);
            
            let img = document.querySelector(`.dinamik-kukla[data-id="${window.suruklenenKuklaId}"]`);
            if(img) {
                img.style.left = (kukla.x * oran) + 'px';
                img.style.top = (kukla.y * oran) + 'px';
            }
            
            if(window.aktifKuklaId === window.suruklenenKuklaId) {
                document.getElementById('panelX').value = kukla.x;
                document.getElementById('panelY').value = kukla.y;
            }
        }
    }
});
window.addEventListener('mouseup', function(e) {
    if(window.suruklenenKuklaId) {
        let img = document.querySelector(`.dinamik-kukla[data-id="${window.suruklenenKuklaId}"]`);
        if(img) img.style.cursor = 'grab';
        window.suruklenenKuklaId = null; 
    }
});

// KOD ÇALIŞTIRMA SENKRONİZASYONU (Sihirli Köprü - Oransal Düzeltme)
setInterval(() => {
    let aktifImg = document.getElementById('aktifKukla');
    let sahne = document.querySelector('.oyun-sahnesi');
    if(aktifImg && window.aktifKuklaId && sahne) {
        let k = window.projeKuklalari.find(x => x.id === window.aktifKuklaId);
        if(k) {
            let oran = sahne.clientWidth / 480; // Büyüme oranını hesapla
            let domX = parseInt(aktifImg.style.left);
            let domY = parseInt(aktifImg.style.top);
            
            // Fiziksel pikseli orana bölerek "Mantıksal Koordinata" geri çevir (Roketleme bug'ı çözüldü)
            let gercekX = Math.round(domX / oran);
            let gercekY = Math.round(domY / oran);

            if(!isNaN(gercekX) && gercekX !== k.x) { 
                k.x = gercekX; 
                document.getElementById('panelX').value = k.x; 
            }
            if(!isNaN(gercekY) && gercekY !== k.y) { 
                k.y = gercekY; 
                document.getElementById('panelY').value = k.y; 
            }
        }
    }
}, 50);
// ==========================================
// --- SAĞ TIK MENÜSÜ İŞLEMLERİ ---
// ==========================================
window.sagTikMenuAc = function(e, kuklaId) {
    e.preventDefault();
    window.sagTikKuklaId = kuklaId;
    let menu = document.getElementById('sagTikMenu');
    menu.style.display = 'block';
    menu.style.left = e.pageX + 'px';
    menu.style.top = e.pageY + 'px';
};

document.addEventListener('click', () => { 
    let menu = document.getElementById('sagTikMenu');
    if(menu) menu.style.display = 'none'; 
});

window.kuklaKopyalaMenu = function() {
    if(!window.sagTikKuklaId) return;
    let kaynak = window.projeKuklalari.find(k => k.id === window.sagTikKuklaId);

    if(kaynak.id === window.aktifKuklaId) {
         let dom = Blockly.Xml.workspaceToDom(window.ogrenciWorkspace);
         kaynak.kodXml = Blockly.Xml.domToText(dom);
    }

    let yeni = JSON.parse(JSON.stringify(kaynak));
    yeni.id = "kukla_" + Date.now();
    yeni.adi = kaynak.adi + " (Kopya)";
    yeni.x += 30; 
    yeni.y += 30;

    window.projeKuklalari.push(yeni);
    kuklaSec(yeni.id);
};

window.kuklaSilIstegi = async function(id) {
    let kukla = window.projeKuklalari.find(k => k.id === id);
    if(await onayKutusu(`"${kukla.adi}" adlı kuklayı ve içindeki TÜM KODLARI silmek istediğinize emin misiniz?`)) {
        window.projeKuklalari = window.projeKuklalari.filter(k => k.id !== id);
        if(window.aktifKuklaId === id) {
            if(window.projeKuklalari.length > 0) {
                kuklaSec(window.projeKuklalari[0].id); 
            } else {
                window.ogrenciWorkspace.clear(); 
                window.aktifKuklaId = null;
                ekraniYenile();
            }
        } else { ekraniYenile(); }
    }
};

window.kuklaSilMenu = function() { if(window.sagTikKuklaId) kuklaSilIstegi(window.sagTikKuklaId); };


/* ============================================================================
 * [2026-08] ÖĞRENCİNİN KENDİ KUKLALARI — SADECE TARAYICIDA
 *
 * YETKİ AYRIMI:
 * Kukla KÜTÜPHANESİ (v2_kukla_kutuphanesi tablosu) Süper Admin'e aittir;
 * oraya yazmak tüm okulları etkiler. Öğrenci ise kendi sahnesinde istediği
 * kadar kukla oluşturur, adını değiştirir, kopyalar, siler — ama bunların
 * hepsi window.projeKuklalari dizisinde, yani SADECE KENDİ TARAYICISINDA
 * yaşar. Sunucuya hiçbir şey yazılmaz; sayfa kapanınca / çıkış yapılınca
 * kaybolur.
 *
 * Eskiden öğrenci ekranında admin sayfalarını iframe'de açan kısayollar
 * vardı; her öğrenci kütüphaneden kukla silebiliyordu. O kısayollar kaldırıldı.
 * ========================================================================== */

/** Boş (çizilmeye hazır) kukla oluşturur ve Kostüm Atölyesi'ni açar */
window.bosKuklaOlustur = async function () {
    const ad = (await metinKutusu('Yeni kuklanın adı:', 'Kuklam ' + (window.projeKuklalari.length + 1)) || '').trim();
    if (!ad) return;

    // Şeffaf 1x1 PNG — öğrenci atölyede üstüne çizecek
    const bosResim = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    const yeni = {
        id: 'kukla_' + Date.now(),
        adi: ad,
        x: 100, y: 100, boyut: 100, yon: 0, goster: true,
        resimYolu: bosResim,
        kostumler: [{ id: 'k_bos_' + Date.now(), adi: 'costume1', resimYolu: bosResim, cizimData: null }],
        kodXml: '<xml xmlns="https://developers.google.com/blockly/xml"></xml>',
        yerel: true          // sunucuya ait değil, öğrencinin kendi kuklası
    };

    window.projeKuklalari.push(yeni);

    if (typeof kuklaPenceresiniKapat === 'function') kuklaPenceresiniKapat();
    kuklaSec(yeni.id);

    // Çizim yapabilsin diye atölyeyi aç
    if (typeof kostumAtolyesiniAc === 'function') {
        setTimeout(kostumAtolyesiniAc, 250);
    }
};

/** Kuklanın adını değiştirir (yalnızca tarayıcıda) */
window.kuklaYenidenAdlandir = async function (id) {
    const kukla = window.projeKuklalari.find(k => k.id === id);
    if (!kukla) return;

    const ad = (await metinKutusu('Kuklanın yeni adı:', kukla.adi) || '').trim();
    if (!ad || ad === kukla.adi) return;

    kukla.adi = ad;
    ekraniYenile();
};

window.kuklaAdlandirMenu = function () {
    if (window.sagTikKuklaId) kuklaYenidenAdlandir(window.sagTikKuklaId);
};

/* ============================================================================
 * [2026-08] TÜM KUKLALARIN KODUNU TEK XML'DE TOPLA
 *
 * NEDEN GEREKLİ:
 * Bu sistemde HER KUKLANIN kendi kodu var (kodXml). Blockly çalışma alanı
 * yalnızca AKTİF kuklanın kodunu gösterir. Hakem motoru ise ham çalışma
 * alanını okuyordu; öğrencinin kodu başka bir kuklada kaldıysa hakem onu
 * göremiyor ve "Kod alanında hiç blok görünmüyor" diyordu.
 *
 * Bu fonksiyon:
 *   1. Aktif kuklanın EKRANDAKİ güncel kodunu kendi kaydına yazar
 *   2. Bütün kuklaların bloklarını tek bir <xml> içinde birleştirir
 * Hakem üst seviye blokları sıralayarak karşılaştırdığı için birleştirme
 * karşılaştırmayı bozmaz.
 * ========================================================================== */
window.tumKuklaKodlariniTopla = function () {
    // 1. Ekrandaki canlı kodu aktif kuklaya kaydet (kaybolmasın)
    if (window.ogrenciWorkspace && window.aktifKuklaId && Array.isArray(window.projeKuklalari)) {
        const aktif = window.projeKuklalari.find(k => k.id === window.aktifKuklaId);
        if (aktif) {
            try {
                aktif.kodXml = Blockly.Xml.domToText(
                    Blockly.Xml.workspaceToDom(window.ogrenciWorkspace)
                );
            } catch (e) {
                console.warn('Aktif kukla kodu kaydedilemedi:', e);
            }
        }
    }

    // 2. Bütün kuklaların <block> düğümlerini topla
    let bloklar = '';

    if (Array.isArray(window.projeKuklalari) && window.projeKuklalari.length > 0) {
        window.projeKuklalari.forEach(function (k) {
            const xml = k.kodXml || '';
            if (xml.indexOf('<block') === -1) return;   // bu kuklada kod yok

            // Kök <xml> etiketini soyup sadece içindeki blokları al
            const ic = xml.replace(/^[\s\S]*?<xml[^>]*>/i, '').replace(/<\/xml>\s*$/i, '');
            bloklar += ic;
        });
    }

    // 3. Hiç kukla yoksa (veya hepsi boşsa) doğrudan çalışma alanını kullan
    if (bloklar.trim() === '' && window.ogrenciWorkspace) {
        try {
            return Blockly.Xml.domToText(Blockly.Xml.workspaceToDom(window.ogrenciWorkspace));
        } catch (e) {
            return '<xml></xml>';
        }
    }

    return '<xml xmlns="https://developers.google.com/blockly/xml">' + bloklar + '</xml>';
};