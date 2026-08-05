/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/assets/js/admin/blueprint_motoru.js
 * KULLANICI / SAYFA: Süper Admin -> Görev Tasarlayıcı
 * * * NE İŞE YARAR? (HALK DİLİNDE):
 * Bu dosya platformun "Cevap Anahtarı" üreticisidir. Ana çözüm alanını ve 
 * butona basıldıkça çoğalan "Alternatif Çözüm" alanlarını oluşturur. 
 * Kaydetme aşamasında bu alanlardaki tüm blok haritalarını (XML) toplayıp 
 * paketlenmek üzere API Yöneticisine teslim eder.
 * * * TEMEL İŞLEMLER:
 * 1. blocklyAnaCozum alanına Zelos (mBlock) motorunu enjekte eder.
 * 2. "Alternatif Çözüm Ekle" butonunu dinleyip dinamik olarak yeni Blockly alanları açar.
 * 3. window.getBlueprintData() fonksiyonu ile tüm çözümleri tek bir objede toplar.
 * * * YENİ EKLENEN ÖZELLİKLER:
 * - [Bugün] Sınırsız Alternatif Çözüm ekleme ve silme altyapısı kuruldu.
 * - [Bugün] Tüm çözümlerin XML çıktıları tek bir Blueprint objesinde birleştirildi.
 * - [2026-08] DÜZENLEME MODU DÜZELTİLDİ:
 *      Kayıtlı cevap anahtarı (XML) çalışma alanına HİÇ geri yüklenmiyordu.
 *      Mevcut bir görevi düzenlemeye açınca Blockly alanı boş geliyor,
 *      öyle kaydedince de görevin cevap anahtarı siliniyordu.
 *      Artık ana çözüm ve tüm alternatifler geri yükleniyor.
 * ============================================================================
 */

// --- YENİ: MERKEZİ MOTORLARI KULLANARAK MENÜYÜ DOM'A EKLE ---
document.body.insertAdjacentHTML('beforeend', window.toolboxOlustur('HEPSI'));

// 1. ANA ÇÖZÜM ALANINI BAŞLAT (Master Blueprint)
const anaWorkspace = Blockly.inject('blocklyAnaCozum', {
    toolbox: document.getElementById('toolbox'),
    renderer: 'zelos',
    theme: Blockly.Themes.Zelos,
    trashcan: true,
    scrollbars: true,
    zoom: { controls: true, wheel: false, startScale: 0.9 }
});

// 2. ALTERNATİF ÇÖZÜMLER YÖNETİMİ
let alternatifWorkspaces = []; // Alternatif alanları hafızada tutacağımız dizi
let alternatifSayaci = 0;

/**
 * Yeni bir alternatif çözüm alanı açar.
 * @param {string|null} yuklenecekXml  Varsa bu XML yüklenir (düzenleme modu),
 *                                     yoksa ana çözüm kopyalanır (kolaylık).
 */
function alternatifCozumEkle(yuklenecekXml) {
    alternatifSayaci++;
    const containerId = 'alt_workspace_' + alternatifSayaci;

    // A. Yeni Çözüm İçin Çerçeve (Konteyner) Oluştur
    const cerceve = document.createElement('div');
    cerceve.style.marginTop = '20px';
    cerceve.style.border = '2px solid #10b981'; // Yeşil çerçeve
    cerceve.style.borderRadius = '8px';
    cerceve.style.padding = '15px';
    cerceve.style.position = 'relative';
    cerceve.style.background = '#f8fafc';

    // B. Başlık, Sil Butonu ve Blockly Hedef Div'i
    cerceve.innerHTML = `
        <h4 style="margin: 0 0 10px 0; color: #10b981;">Alternatif Çözüm Varyasyonu ${alternatifSayaci}</h4>
        <button type="button" onclick="silAlternatifCozum(this, '${containerId}')" style="position:absolute; top:15px; right:15px; background:#ef4444; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-weight:bold;">Tümünü Sil</button>
        <div id="${containerId}" style="height: 350px; width: 100%; border: 1px solid #cbd5e1; border-radius: 6px;"></div>
    `;
    
    document.getElementById('alternatifCozumlerKonteyneri').appendChild(cerceve);

    // C. Oluşan Yeni Div'in İçine Blockly (mBlock) Motorunu Enjekte Et
    const altWs = Blockly.inject(containerId, {
        toolbox: document.getElementById('toolbox'),
        renderer: 'zelos',
        theme: Blockly.Themes.Zelos,
        trashcan: true,
        scrollbars: true,
        zoom: { controls: true, wheel: false, startScale: 0.8 }
    });

    // Düzenleme modunda kayıtlı XML, yeni eklemede ana çözümün kopyası yüklenir
    try {
        if (yuklenecekXml && String(yuklenecekXml).trim() !== '') {
            Blockly.Xml.domToWorkspace(xmlMetnindenDom(yuklenecekXml), altWs);
        } else {
            Blockly.Xml.domToWorkspace(Blockly.Xml.workspaceToDom(anaWorkspace), altWs);
        }
    } catch (e) {
        console.error('Alternatif çözüm yüklenemedi:', e);
    }

    // Hafızaya Kaydet
    alternatifWorkspaces.push({ id: containerId, workspace: altWs });
    return altWs;
}

// Butona basınca boş (ana çözümün kopyası) bir alternatif açılır
document.getElementById('btnAlternatifEkle').addEventListener('click', function () {
    alternatifCozumEkle(null);
});

/** Blockly sürüm farklarını gözeten XML metin -> DOM çevirici */
function xmlMetnindenDom(xmlMetni) {
    if (Blockly.utils && Blockly.utils.xml && typeof Blockly.utils.xml.textToDom === 'function') {
        return Blockly.utils.xml.textToDom(xmlMetni);
    }
    return Blockly.Xml.textToDom(xmlMetni);
}

/* ============================================================================
 * DÜZENLEME MODU: kayıtlı cevap anahtarını geri yükle
 * [2026-08] Bu blok yoktu — düzenlemeye açılan görev boş görünüyordu.
 * ========================================================================== */
(function kayitliCozumuYukle() {
    const kayit = window.v2DuzenlenenGorev;
    if (!kayit || !kayit.icerik || !kayit.icerik.blueprint) return;

    const bp = kayit.icerik.blueprint;

    // 1. Ana çözüm
    if (bp.ana_cozum && String(bp.ana_cozum).trim() !== '') {
        try {
            anaWorkspace.clear();
            Blockly.Xml.domToWorkspace(xmlMetnindenDom(bp.ana_cozum), anaWorkspace);
        } catch (e) {
            console.error('Ana çözüm yüklenemedi:', e);
            alert('⚠️ Bu görevin kayıtlı cevap anahtarı okunamadı. Kaydetmeden önce yeniden kurmanız gerekebilir.');
        }
    }

    // 2. Alternatif çözümler
    const alternatifler = bp.alternatif_cozumler || [];
    if (Array.isArray(alternatifler)) {
        alternatifler.forEach(function (xml) {
            const metin = (xml && typeof xml === 'object') ? (xml.xml || '') : xml;
            if (metin && String(metin).trim() !== '') {
                alternatifCozumEkle(metin);
            }
        });
    }
})();

// Alternatif Çözüm Silme Fonksiyonu
window.silAlternatifCozum = function(butonElementi, id) {
    // 1. Ekrandan (HTML) sil
    butonElementi.parentElement.remove();
    // 2. Hafızadan (Diziden) sil
    alternatifWorkspaces = alternatifWorkspaces.filter(item => item.id !== id);
};

// 3. API YÖNETİCİSİ İÇİN VERİ TOPLAMA KÖPRÜSÜ
window.getBlueprintData = function() {
    // Ana çözümü XML'e çevir
    const anaXml = Blockly.Xml.domToText(Blockly.Xml.workspaceToDom(anaWorkspace));
    
    // Alternatif çözümlerin hepsini dolaş ve XML'e çevirip bir dizi (Array) yap
    const alternatiflerXml = alternatifWorkspaces.map(item => {
        return Blockly.Xml.domToText(Blockly.Xml.workspaceToDom(item.workspace));
    });

    // Hepsini paketleyip gönder
    return {
        ana_cozum: anaXml,
        alternatif_cozumler: alternatiflerXml
    };
};
// 4. KULLANILAN BLOKLARI TESPİT ET (Dinamik Menü İçin)
window.getKullanilanBlokTipleri = function() {
    let tipler = new Set();
    
    // A. Ana çözümdeki blokları tara
    anaWorkspace.getAllBlocks(false).forEach(b => tipler.add(b.type));
    
    // B. Alternatif çözümlerdeki blokları tara
    alternatifWorkspaces.forEach(wsObj => {
        wsObj.workspace.getAllBlocks(false).forEach(b => tipler.add(b.type));
    });
    
    // C. Yeşil bayrak bloğu her zaman menüde olmak zorunda
    tipler.add('mblock_olay_bayrak_tiklandiginda');
    
    return Array.from(tipler); // Eşsiz blok listesini dizi olarak gönder
};