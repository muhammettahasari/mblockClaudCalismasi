/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/assets/js/admin/ipucu_alani.js
 * KULLANICI / SAYFA: Süper Admin -> Görev Tasarlayıcı (4. Bölge: 💡 Bilgi Al)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Görevin "Bilgi Al" bölümünü çalıştırır. İki parça var:
 *   1. Yazı + resim editörü (Editor.js)
 *   2. Bilgi amaçlı blok alanı (Blockly)
 *
 * ⚠️ BURADAKİ BLOKLAR CEVAP ANAHTARI DEĞİLDİR.
 * Öğrenciye salt okunur gösterilir; hakem bunlara bakmaz, çalıştırılmaz.
 * Cevap anahtarı 2. Bölge (blueprint_motoru.js).
 *
 * ⚠️ YÜKLEME SIRASI ÖNEMLİ:
 * blueprint_motoru.js'ten SONRA yüklenmeli. Blockly araç kutusunu (#toolbox)
 * o dosya DOM'a ekliyor; burada aynısını ikinci kez eklersek iki tane
 * id="toolbox" olur ve getElementById hangisini döndüreceğini şansa bırakır.
 *
 * DIŞARIYA AÇILAN:
 *   window.v2IpucuEditor       Editor.js örneği (api_yoneticisi.js kaydederken çağırır)
 *   window.getIpucuBloklari()  Blok alanının XML'i (boşsa '')
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu.
 * ============================================================================
 */

(function () {
    'use strict';

    /* ------------------------------------------------------------------
     * 1. YAZI + RESİM EDİTÖRÜ
     * ---------------------------------------------------------------- */
    var araclar = {};
    if (typeof Header !== 'undefined') { araclar.header = { class: Header, inlineToolbar: true }; }
    if (typeof List   !== 'undefined') { araclar.list   = { class: List,   inlineToolbar: true }; }

    /* Resim aracı — dosya sunucuya yüklenir, adresi kaydedilir.
       ImageTool CDN'den gelmezse (ağ yok / engellendi) editör yine açılsın;
       sadece resim eklenemez. Tüm alanın çökmesindense o daha iyi. */
    if (typeof ImageTool !== 'undefined') {
        araclar.image = {
            class: ImageTool,
            config: {
                endpoints: { byFile: window.V2_RESIM_YUKLE || '../api/resim_yukle.php' },
                field: 'image',
                types: 'image/*',
                captionPlaceholder: 'Resim açıklaması (isteğe bağlı)',
                additionalRequestHeaders: {},
                /* Editor.js varsayılan olarak fetch'e kimlik bilgisi koymaz;
                   koymazsak oturum çerezi gitmez ve resim_yukle.php
                   "yetkisiz" der. */
                uploader: {
                    uploadByFile: function (dosya) {
                        var form = new FormData();
                        form.append('image', dosya);
                        return fetch(window.V2_RESIM_YUKLE || '../api/resim_yukle.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: form
                        }).then(function (r) { return r.json(); });
                    }
                }
            }
        };
    } else {
        console.warn('[Bilgi Al] Editor.js resim eklentisi yüklenemedi; yazı ve bloklar çalışıyor.');
    }

    /* Düzenleme modunda kayıtlı bilgiyi geri yükle.
       (Bu adım unutulursa görev düzenlenip kaydedildiğinde bilgi SİLİNİR —
        hikâye editöründe tam olarak bu hata yaşanmıştı.) */
    var baslangic;
    try {
        var kayit = window.v2DuzenlenenGorev;
        var m = kayit && kayit.icerik && kayit.icerik.ipucu && kayit.icerik.ipucu.metin;
        if (m && Array.isArray(m.blocks) && m.blocks.length > 0) { baslangic = m; }
    } catch (e) {
        console.warn('[Bilgi Al] Kayıtlı bilgi metni okunamadı:', e);
    }

    window.v2IpucuEditor = new EditorJS({
        holder: 'v2-ipucu-editorjs',
        placeholder: 'Öğrenciye ne anlatmak istiyorsun? Yazı yaz, resim ekle…',
        tools: araclar,
        data: baslangic
    });

    /* ------------------------------------------------------------------
     * 2. BİLGİ AMAÇLI BLOK ALANI
     * ---------------------------------------------------------------- */
    var ipucuWorkspace = null;

    function blokAlaniniKur() {
        var kutu = document.getElementById('blocklyIpucu');
        var toolbox = document.getElementById('toolbox');
        if (!kutu || typeof Blockly === 'undefined' || !toolbox) {
            console.warn('[Bilgi Al] Blok alanı kurulamadı (Blockly ya da araç kutusu yok).');
            return;
        }

        ipucuWorkspace = Blockly.inject('blocklyIpucu', {
            toolbox: toolbox,
            renderer: 'zelos',
            theme: Blockly.Themes.Zelos,
            trashcan: true,
            scrollbars: true,
            zoom: { controls: true, wheel: false, startScale: 0.85 }
        });

        /* Tam ekran düğmesi kutu id'sinden workspace'i bu eşlemeyle buluyor */
        window.blueprintAlanlari = window.blueprintAlanlari || {};
        window.blueprintAlanlari['blocklyIpucu'] = ipucuWorkspace;

        /* Kayıtlı blokları geri yükle */
        try {
            var kayit = window.v2DuzenlenenGorev;
            var xml = kayit && kayit.icerik && kayit.icerik.ipucu && kayit.icerik.ipucu.bloklar;
            if (xml && xml.trim() !== '') {
                /* Blockly sürümüne göre XML çözümleyici yer değiştirdi;
                   blueprint_motoru.js'teki savunmanın aynısı. */
                var dom = (Blockly.utils && Blockly.utils.xml && Blockly.utils.xml.textToDom)
                        ? Blockly.utils.xml.textToDom(xml)
                        : Blockly.Xml.textToDom(xml);
                Blockly.Xml.domToWorkspace(dom, ipucuWorkspace);
            }
        } catch (e) {
            console.warn('[Bilgi Al] Kayıtlı bilgi blokları yüklenemedi:', e);
        }
    }

    blokAlaniniKur();

    /**
     * Blok alanının XML'i. Alan boşsa BOŞ METİN döner — "içinde bir şey var mı?"
     * kontrolü sunucuda da tarayıcıda da bu boşluğa bakıyor.
     */
    window.getIpucuBloklari = function () {
        if (!ipucuWorkspace) { return ''; }
        try {
            if (ipucuWorkspace.getAllBlocks(false).length === 0) { return ''; }
            var dom = Blockly.Xml.workspaceToDom(ipucuWorkspace);
            return Blockly.Xml.domToText(dom);
        } catch (e) {
            console.warn('[Bilgi Al] Bloklar okunamadı:', e);
            return '';
        }
    };
})();
