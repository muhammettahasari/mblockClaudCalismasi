/**
 * ============================================================================
 * Dosya Yolu: v2_gorev_sistemi/assets/js/ogrenci/oyun_sahnesi.js
 * Kullanıcı: Öğrenci -> Görev Oynatıcı
 *
 * HALK DİLİNDE İŞLEVİ:
 * Sağdaki yapboz (Blockly) kod alanını kurar: görevin izin verdiği blokları
 * menüye dizer, sağ tık "Çoğalt" menüsünü ekler ve canlı derleyiciyi bağlar.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-11] Kukla hareketi, tuş/fare dinleme ve oyun döngüsü buradan
 *    sahne_motoru.js'e taşındı (mBlock koordinatları, çoklu kukla). Bu dosya
 *    artık sadece kod alanıyla ilgilenir.
 *  - [2026-11] Çocuklar için: bloklar biraz daha büyük (startScale 1.0),
 *    ızgara çizgileri, fare tekerleğiyle kaydırma.
 * ============================================================================
 */

/** Görevin hazır değişkenlerini (icerik_json.sahne.degiskenler) çalışma alanına ekler */
window.gorevDegiskenleriniKur = function (ws) {
    ws = ws || window.ogrenciWorkspace; if (!ws) return;
    const paket = (typeof SUNUCUDAN_GELEN_GOREV !== "undefined" && SUNUCUDAN_GELEN_GOREV) || {};
    const liste = (paket.sahne && Array.isArray(paket.sahne.degiskenler)) ? paket.sahne.degiskenler : [];
    const harita = ws.getVariableMap();   // Blockly 12+: değişken işlemleri VariableMap'te
    liste.forEach(ad => {
        try { if (!harita.getVariable(ad, '')) harita.createVariable(ad, '', null); }
        catch (e) { console.warn('Değişken oluşturulamadı:', ad, e); }
    });
};

document.addEventListener("DOMContentLoaded", function () {
    // 1. Araç kutusu — görevin izin listesi yoksa hepsi
    if (typeof window.toolboxOlustur === "function") {
        const paket = (typeof SUNUCUDAN_GELEN_GOREV !== "undefined" && SUNUCUDAN_GELEN_GOREV) || {};
        const izinListesi = paket.izin_verilen_bloklar || [];
        const izinVerilenler = (Array.isArray(izinListesi) && izinListesi.length > 0) ? izinListesi : "HEPSI";
        const eski = document.getElementById("toolbox");
        if (eski) eski.remove();
        document.body.insertAdjacentHTML("beforeend", window.toolboxOlustur(izinVerilenler));
    }

    // 2. Sağ tık menüsü: gereksizleri kaldır, mBlock'taki gibi "Çoğalt"
    if (typeof Blockly !== "undefined" && Blockly.ContextMenuRegistry) {
        try {
            ['blockDuplicate', 'blockInline', 'blockCollapseExpand'].forEach(id => {
                try { Blockly.ContextMenuRegistry.registry.unregister(id); } catch (e) {}
            });
            Blockly.ContextMenuRegistry.registry.register({
                displayText: 'Çoğalt',
                preconditionFn: scope => (scope.block.isDeletable() && scope.block.isMovable()) ? 'enabled' : 'hidden',
                callback: function (scope) {
                    const block = scope.block, ws = block.workspace;
                    const dom = Blockly.Xml.blockToDom(block, true);
                    const yeni = Blockly.Xml.domToBlock(dom, ws);
                    const k = block.getRelativeToSurfaceXY();
                    yeni.moveBy(k.x + 40, k.y + 40);
                    yeni.select();
                },
                scopeType: Blockly.ContextMenuRegistry.ScopeType.BLOCK,
                id: 'ozel_mblock_cogalt',
                weight: 1
            });
        } catch (err) { console.warn("Menü ayarlama hatası:", err); }
    }

    // 3. Blockly'nin kendi soru kutuları (değişken oluştur / sil) tarayıcı
    //    prompt()/confirm() yerine sitenin penceresini kullansın (CLAUDE.md kuralı)
    try {
        if (Blockly.dialog && typeof window.metinKutusu === 'function') {
            Blockly.dialog.setPrompt(function (mesaj, varsayilan, geri) {
                window.metinKutusu(mesaj, varsayilan).then(v => geri(v === undefined ? null : v));
            });
            Blockly.dialog.setConfirm(function (mesaj, geri) { window.onayKutusu(mesaj).then(v => geri(!!v)); });
            Blockly.dialog.setAlert(function (mesaj, geri) { window.onayKutusu(mesaj).then(() => geri && geri()); });
        }
    } catch (e) { console.warn('Blockly pencere ayarı:', e); }

    // 4. Blockly'yi kur
    try {
        window.ogrenciWorkspace = Blockly.inject("blocklyOgrenci", {
            toolbox: document.getElementById("toolbox"),
            renderer: "zelos",
            theme: Blockly.Themes.Zelos,
            trashcan: true,
            scrollbars: true,
            sounds: true,
            grid: { spacing: 28, length: 3, colour: '#e2e8f0', snap: false },
            move: { scrollbars: true, drag: true, wheel: true },
            zoom: { controls: true, wheel: false, startScale: 1.0, maxScale: 2.5, minScale: 0.4, scaleSpeed: 1.2 }
        });
        /* [2026-11] Değişkenler menüsü: "değerini yap" bloğu hazır sayıyla gelsin
           ve görevin değişkenleri (ör. skor) önceden oluşturulmuş olsun. */
        window.ogrenciWorkspace.registerToolboxCategoryCallback('VARIABLE', function (ws) {
            window.gorevDegiskenleriniKur && window.gorevDegiskenleriniKur(ws);
            const liste = [];
            const dugme = Blockly.utils.xml.createElement('button');
            dugme.setAttribute('text', '➕ Değişken Oluştur');
            dugme.setAttribute('callbackKey', 'DEGISKEN_OLUSTUR');
            liste.push(dugme);
            const adi = v => (typeof v.getName === 'function' ? v.getName() : v.name);
            ws.getVariableMap().getVariablesOfType('').sort((a, b) => adi(a).localeCompare(adi(b), 'tr')).forEach(v => {
                const kacir = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;');
                ['<block type="variables_set"><field name="VAR" id="' + v.getId() + '">' + kacir(adi(v)) + '</field><value name="VALUE"><shadow type="math_number"><field name="NUM">0</field></shadow></value></block>',
                 '<block type="math_change"><field name="VAR" id="' + v.getId() + '">' + kacir(adi(v)) + '</field><value name="DELTA"><shadow type="math_number"><field name="NUM">1</field></shadow></value></block>',
                 '<block type="variables_get"><field name="VAR" id="' + v.getId() + '">' + kacir(adi(v)) + '</field></block>'
                ].forEach(x => liste.push(Blockly.utils.xml.textToDom(x)));
            });
            return liste;
        });
        window.ogrenciWorkspace.registerButtonCallback('DEGISKEN_OLUSTUR', function (b) {
            Blockly.Variables.createVariableButtonHandler(b.getTargetWorkspace(), null, '');
        });

        if (typeof window.canliDerleyiciyiBaslat === "function") {
            window.canliDerleyiciyiBaslat(window.ogrenciWorkspace);
        }
    } catch (err) {
        console.error("Blockly Hatası:", err);
    }
});
