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
// [2026-11] Koordinatlar artık mBlock'taki gibi: orta (0,0), y yukarı,
// yön 90 = sağ. Çizim, sürükleme ve kod çalıştırma sahne_motoru.js'te (MBM).
// ==========================================
window.addEventListener('load', () => {
    const paket = (typeof SUNUCUDAN_GELEN_GOREV !== 'undefined' && SUNUCUDAN_GELEN_GOREV) || {};
    if (window.MBM) window.MBM.gorevSahnesiniKur(paket.sahne || null);

    if (window.projeKuklalari.length === 0) {
        let varsayilanResim = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='60' height='60'><text y='48' font-size='46'>👩‍🚀</text></svg>";
        window.projeKuklalari.push({
            id: "kukla_varsayilan", adi: "Astronot",
            x: 0, y: 0, boyut: 100, yon: 90, goster: true, kostumIndex: 0, donusStili: 'HER',
            resimYolu: varsayilanResim,
            kostumler: [{ id: "k_varsayilan_1", adi: "kostüm1", resimYolu: varsayilanResim }],
            kodXml: '<xml xmlns="https://developers.google.com/blockly/xml"></xml>'
        });
        if (window.MBM) { window.MBM.anlikGoruntuAl(); window.MBM.gorevBaslangic = window.MBM.anlik; }
    }
    setTimeout(() => { kuklaSec(window.projeKuklalari[0].id); }, 50);
});

window.sahnedeKuklayiDegistir = function(kuklaDataStr) {
    let kuklaDB = JSON.parse(decodeURIComponent(kuklaDataStr));
    let kostumler = JSON.parse(kuklaDB.kostumler_json);
    let atolyeKostumleri = kostumler.map(k => ({ id: k.id, adi: k.adi, resimYolu: "../" + k.yol, cizimData: k.cizimData || null }));
    let yeniKukla = {
        id: "kukla_" + Date.now(), adi: kuklaDB.kukla_adi,
        x: 0, y: 0, boyut: 100, yon: 90, goster: true, kostumIndex: 0, donusStili: 'HER',
        resimYolu: atolyeKostumleri[0].resimYolu,
        kostumler: atolyeKostumleri,
        kodXml: '<xml xmlns="https://developers.google.com/blockly/xml"></xml>'
    };
    window.projeKuklalari.push(yeniKukla);
    if (window.MBM) window.MBM.anlikGoruntuAl();
    kuklaPenceresiniKapat();
    kuklaSec(yeniKukla.id);
};

window.kuklaSec = function(hedefId) {
    if (window.aktifKuklaId === hedefId) return;

    if (window.ogrenciWorkspace && window.aktifKuklaId) {
        let eskiKukla = window.projeKuklalari.find(k => k.id === window.aktifKuklaId);
        if (eskiKukla) eskiKukla.kodXml = Blockly.Xml.domToText(Blockly.Xml.workspaceToDom(window.ogrenciWorkspace));
    }

    let seciliKukla = window.projeKuklalari.find(k => k.id === hedefId);
    if (!seciliKukla) return;

    /* [2026-08] KOD KAYBI ÖNLENDİ: ilk seçimde ekranda blok varsa hedef kuklaya devret */
    if (window.ogrenciWorkspace && !window.aktifKuklaId) {
        const ekrandakiBlok = window.ogrenciWorkspace.getAllBlocks(false).length;
        const hedefKoduBos = !seciliKukla.kodXml || seciliKukla.kodXml.indexOf('<block') === -1;
        if (ekrandakiBlok > 0 && hedefKoduBos) {
            seciliKukla.kodXml = Blockly.Xml.domToText(Blockly.Xml.workspaceToDom(window.ogrenciWorkspace));
        }
    }

    window.aktifKuklaId = hedefId;

    if (window.ogrenciWorkspace) {
        window.ogrenciWorkspace.clear();
        if (seciliKukla.kodXml) {
            try {
                Blockly.Xml.domToWorkspace(Blockly.utils.xml.textToDom(seciliKukla.kodXml), window.ogrenciWorkspace);
            } catch (e) { console.error("Kod yükleme hatası:", e); }
        }
        // Görevin hazır değişkenleri (clear() onları da siliyor)
        if (typeof window.gorevDegiskenleriniKur === 'function') window.gorevDegiskenleriniKur(window.ogrenciWorkspace);
        // Kostüm menüsü yeni kuklanın kostümlerini göstersin
        try { window.ogrenciWorkspace.getToolbox() && window.ogrenciWorkspace.getToolbox().refreshSelection(); } catch (e) {}
    }
    kuklaPaneliniGuncelle();
    ekraniYenile();
};

/** Panel kutularını aktif kuklanın değerleriyle doldurur */
window.kuklaPaneliniGuncelle = function() {
    let k = window.projeKuklalari.find(x => x.id === window.aktifKuklaId);
    if (!k) return;
    document.getElementById('panelKuklaAdi').value = k.adi;
    document.getElementById('panelX').value = Math.round(k.x);
    document.getElementById('panelY').value = Math.round(k.y);
    document.getElementById('panelBoyut').value = Math.round(k.boyut);
    document.getElementById('panelYon').value = Math.round(k.yon);
    arayuzdenGosterGizle(k.goster, false);
};

/** Kod çalışırken panel canlı güncellensin (kutuya yazılıyorsa dokunma) */
window.kuklaPaneliCanli = function() {
    const odak = document.activeElement;
    if (odak && odak.closest && odak.closest('.kukla-paneli')) return;
    let k = window.projeKuklalari.find(x => x.id === window.aktifKuklaId);
    if (!k) return;
    const yaz = (id, deger) => { const el = document.getElementById(id); if (el && String(el.value) !== String(deger)) el.value = deger; };
    yaz('panelX', Math.round(k.x)); yaz('panelY', Math.round(k.y));
    yaz('panelBoyut', Math.round(k.boyut)); yaz('panelYon', Math.round(k.yon));
    const bg = document.getElementById('btnGoster');
    if (bg && bg.classList.contains('aktif') !== !!k.goster) arayuzdenGosterGizle(k.goster, false);
};

window.arayuzdenAktifKuklayiGuncelle = function() {
    if (!window.aktifKuklaId) return;
    let k = window.projeKuklalari.find(x => x.id === window.aktifKuklaId);
    if (!k) return;
    k.adi = document.getElementById('panelKuklaAdi').value;
    k.x = parseInt(document.getElementById('panelX').value) || 0;
    k.y = parseInt(document.getElementById('panelY').value) || 0;
    k.boyut = parseInt(document.getElementById('panelBoyut').value) || 100;
    let yon = parseInt(document.getElementById('panelYon').value);
    k.yon = isNaN(yon) ? 90 : yon;
    if (window.MBM) window.MBM.anlikGoruntuAl();
    ekraniYenile();
};

window.arayuzdenGosterGizle = function(durum, tetikle = true) {
    let btnGoster = document.getElementById('btnGoster');
    let btnGizle = document.getElementById('btnGizle');
    if (durum) { btnGoster.classList.add('aktif'); btnGizle.classList.remove('aktif'); }
    else { btnGizle.classList.add('aktif'); btnGoster.classList.remove('aktif'); }
    if (tetikle && window.aktifKuklaId) {
        let secili = window.projeKuklalari.find(k => k.id === window.aktifKuklaId);
        secili.goster = durum;
        if (window.MBM) window.MBM.anlikGoruntuAl();
        ekraniYenile();
    }
};

/* [2026-11] Kukla adı / resim yolu HTML'e basılmadan önce kaçırılır. */
function kuklaHtmlKacir(x) {
    return String(x == null ? '' : x)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/** Alttaki kukla listesini çizer; sahnedeki çizimi MBM yapar */
window.ekraniYenile = function() {
    let kapsayici = document.getElementById('kuklaListesiKapsayici');
    let html = '';
    window.projeKuklalari.forEach(k => {
        let aktifSinifi = k.id === window.aktifKuklaId ? 'aktif' : '';
        const liste = (k.kostumler && k.kostumler.length) ? k.kostumler : [{ resimYolu: k.resimYolu }];
        const resim = (liste[Math.min(liste.length - 1, k.kostumIndex | 0)] || liste[0]).resimYolu;
        html += `
            <div class="kukla-oge ${aktifSinifi}" data-id="${kuklaHtmlKacir(k.id)}" onclick="kuklaSec('${kuklaHtmlKacir(k.id)}')" oncontextmenu="sagTikMenuAc(event, '${kuklaHtmlKacir(k.id)}')">
                <div class="kukla-sil-ikon" onclick="event.stopPropagation(); kuklaSilIstegi('${kuklaHtmlKacir(k.id)}')">✕</div>
                <img src="${kuklaHtmlKacir(resim)}">
                <div class="k-isim" title="${kuklaHtmlKacir(k.adi)}">${kuklaHtmlKacir(k.adi)}</div>
            </div>`;
    });
    html += `<button class="btn-yeni-kukla" onclick="kuklaPenceresiniAc()" title="Kütüphaneden Yeni Kukla Ekle"><span>+</span> Ekle</button>`;
    kapsayici.innerHTML = html;
    if (window.MBM) window.MBM.ciz();
};

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
    yeni.y -= 30;

    window.projeKuklalari.push(yeni);
    if (window.MBM) window.MBM.anlikGoruntuAl();
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
        x: 0, y: 0, boyut: 100, yon: 90, goster: true, kostumIndex: 0, donusStili: 'HER',
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