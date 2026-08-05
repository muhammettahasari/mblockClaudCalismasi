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

document.getElementById('btnAlternatifEkle').addEventListener('click', function() {
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

    // Ana çözümdeki blokları kolaylık olsun diye otomatik olarak alternatife kopyala
    const anaXmlDom = Blockly.Xml.workspaceToDom(anaWorkspace);
    Blockly.Xml.domToWorkspace(anaXmlDom, altWs);

    // Hafızaya Kaydet
    alternatifWorkspaces.push({ id: containerId, workspace: altWs });
});

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