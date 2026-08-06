/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/assets/js/ogrenci/bilgi_al.js
 * KULLANICI / SAYFA: Öğrenci -> v2_gorev_sistemi/ogrenci/oynatici.php
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * "💡 Bilgi Al" düğmesinin tamamı. Öğrenciye önce uyarıyı gösterir
 * ("bilgi alırsan puanın yarıya iner"), kabul ederse sunucudan bilgiyi
 * çeker ve bir pencerede yazı + resim + blok olarak gösterir.
 *
 * [2026-08] ESKİDEN NE VARDI?
 * hakem_motoru.js içinde görev metninde "ipucu" kelimesi arayan bir tahmin
 * motoru vardı. Neredeyse her görevde "bu görev için ayrı bir ipucu
 * tanımlanmamış" diyordu, çünkü tasarlayıcıda ipucu yazılacak bir alan hiç
 * yoktu. Düğme vardı, arkasında hiçbir şey yoktu.
 *
 * ⚠️ CEZA TARAYICIDA DEĞİL SUNUCUDA.
 * Bu dosya sadece soruyu sorar. "Aldı" kaydını api/v2_ipucu.php düşer,
 * ödülü yarıya indiren de v2_gorev_tamamla.php'dir. Öğrenci bu dosyayı
 * konsoldan değiştirse bile puanı tam alamaz.
 *
 * ⚠️ BİLGİ, UYARI EKRANIYLA BİRLİKTE GELMEZ.
 * Sunucu 'durum' işleminde içerik göndermez; yoksa öğrenci F12 ile ağ
 * sekmesinden okur, kabul etmeden bilgiyi görürdü.
 *
 * BLOKLAR NEDEN SALT OKUNUR?
 * Bilgi amaçlı bloklar cevap anahtarı değil, açıklama. Sürüklenebilseydi
 * öğrenci onları kendi alanına taşımaya çalışır, çalışmayınca "bozuk"
 * sanırdı. readOnly:true bunu baştan keser.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu.
 * ============================================================================
 */

(function () {
    'use strict';

    var API = '../api/v2_ipucu.php';
    var perde = null;
    var bilgiWorkspace = null;

    /* Blockly sürümüne göre XML çözümleyici yer değiştirdi.
       blueprint_motoru.js'te de aynı savunma var; ikisi ayrı dosyada
       çalıştığı için kopyalanıyor (paylaşılan bir yardımcı dosya kurmak
       tek fonksiyon için fazla olurdu). */
    function xmlDomu(metin) {
        if (Blockly.utils && Blockly.utils.xml && typeof Blockly.utils.xml.textToDom === 'function') {
            return Blockly.utils.xml.textToDom(metin);
        }
        return Blockly.Xml.textToDom(metin);
    }

    /* ----------------------------------------------------------------------
       PENCERE İSKELETİ — sayfaya bir kez basılır
       -------------------------------------------------------------------- */
    function pencereyiKur() {
        if (perde) { return; }

        var stil = document.createElement('style');
        stil.textContent = [
            '#bilgiPerde{position:fixed;inset:0;z-index:9600;display:none;',
            '  align-items:center;justify-content:center;padding:18px;',
            '  background:rgba(15,23,42,.6);backdrop-filter:blur(3px);',
            "  font-family:'Segoe UI',Tahoma,sans-serif;}",
            '#bilgiPerde.acik{display:flex;}',
            '.bilgi-panel{background:#fff;border-radius:18px;width:720px;max-width:100%;',
            '  max-height:90vh;display:flex;flex-direction:column;overflow:hidden;',
            '  box-shadow:0 26px 70px rgba(15,23,42,.4);animation:bilgiGir .22s ease-out;}',
            '@keyframes bilgiGir{from{transform:scale(.95) translateY(12px);opacity:0}to{transform:none;opacity:1}}',
            '.bilgi-bas{padding:20px 22px 16px;color:#fff;display:flex;align-items:flex-start;gap:14px;}',
            '.bilgi-bas.uyari{background:linear-gradient(135deg,#f59e0b,#d97706);}',
            '.bilgi-bas.acik{background:linear-gradient(135deg,#2563eb,#1d4ed8);}',
            '.bilgi-bas .sim{font-size:30px;line-height:1;}',
            '.bilgi-bas h3{margin:0 0 4px;font-size:18px;font-weight:800;}',
            '.bilgi-bas p{margin:0;font-size:13px;line-height:1.55;color:rgba(255,255,255,.92);}',
            '.bilgi-govde{padding:18px 22px;overflow-y:auto;color:#1e293b;font-size:15px;line-height:1.65;}',
            '.bilgi-govde h2,.bilgi-govde h3,.bilgi-govde h4{margin:16px 0 8px;color:#0f172a;}',
            '.bilgi-govde p{margin:0 0 10px;}',
            '.bilgi-govde ul,.bilgi-govde ol{margin:0 0 10px;padding-left:22px;}',
            '.bilgi-govde img{max-width:100%;border-radius:10px;border:1px solid #e2e8f0;display:block;}',
            /* Resim hizalama — yazı resmin yanına aksın diye float.
               `.bilgi-govde` içinde overflow/display:flow-root YOK; olsaydı
               yeni bir blok bağlamı açılır ve float sonraki paragrafları
               etkilemezdi (yani yazı resmin yanına akmazdı). */
            '.bilgi-resim{display:block;margin:8px 0;}',
            '.bilgi-resim.sol{float:left;margin:4px 18px 10px 0;max-width:70%;}',
            '.bilgi-resim.sag{float:right;margin:4px 0 10px 18px;max-width:70%;}',
            '.bilgi-resim.orta img{margin-left:auto;margin-right:auto;}',
            '.bilgi-resim.tam{width:100%;}',
            '.bilgi-resim-alt{display:block;font-size:12.5px;color:#64748b;margin-top:4px;}',
            /* Son float kapanmazsa pencerenin alt kenarı resmin üstüne biner */
            '.bilgi-govde::after{content:"";display:block;clear:both;}',
            '.bilgi-blok-etiket{clear:both;}',
            '.bilgi-bedel{background:#fffbeb;border:1px solid #fcd34d;color:#92400e;border-radius:12px;',
            '  padding:14px 16px;font-size:14.5px;line-height:1.6;}',
            '.bilgi-bedel b{color:#b45309;}',
            '.bilgi-bloklar{height:320px;border:2px solid #c7d2fe;border-radius:12px;margin-top:12px;background:#f8fafc;}',
            '.bilgi-blok-etiket{font-size:12px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;',
            '  color:#64748b;margin:18px 0 6px;}',
            '.bilgi-alt{display:flex;gap:10px;padding:16px 22px;border-top:1px solid #e2e8f0;background:#f8fafc;}',
            '.bilgi-btn{flex:1;padding:12px;border:none;border-radius:10px;font-size:15px;font-weight:800;',
            "  cursor:pointer;font-family:inherit;transition:.15s;}",
            '.bilgi-btn:disabled{opacity:.6;cursor:wait;}',
            '.bilgi-btn-vazgec{background:#fff;border:1.5px solid #cbd5e1;color:#475569;flex:0 0 150px;}',
            '.bilgi-btn-vazgec:hover:not(:disabled){background:#f1f5f9;}',
            '.bilgi-btn-kabul{background:#d97706;color:#fff;}',
            '.bilgi-btn-kabul:hover:not(:disabled){background:#b45309;}',
            '.bilgi-btn-kapat{background:#2563eb;color:#fff;}',
            '.bilgi-btn-kapat:hover{background:#1d4ed8;}',
            '@media(max-width:560px){.bilgi-alt{flex-direction:column-reverse;}.bilgi-btn-vazgec{flex:1 1 auto;}}'
        ].join('\n');
        document.head.appendChild(stil);

        perde = document.createElement('div');
        perde.id = 'bilgiPerde';
        perde.setAttribute('role', 'dialog');
        perde.setAttribute('aria-modal', 'true');
        perde.innerHTML =
            '<div class="bilgi-panel">' +
              '<div class="bilgi-bas uyari" id="bilgiBas">' +
                '<span class="sim" id="bilgiSim">💡</span>' +
                '<div><h3 id="bilgiBaslik">Bilgi Al</h3><p id="bilgiAltBaslik"></p></div>' +
              '</div>' +
              '<div class="bilgi-govde" id="bilgiGovde"></div>' +
              '<div class="bilgi-alt" id="bilgiAlt"></div>' +
            '</div>';
        document.body.appendChild(perde);

        perde.addEventListener('mousedown', function (e) {
            if (e.target === perde) { kapat(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && perde.classList.contains('acik')) { kapat(); }
        });
    }

    function kapat() {
        if (!perde) { return; }
        perde.classList.remove('acik');
        /* Blockly örneğini bırak; pencere tekrar açıldığında yenisi kurulur.
           Bırakmazsak her açılışta bir tane daha birikir ve sayfa yavaşlar. */
        if (bilgiWorkspace) {
            try { bilgiWorkspace.dispose(); } catch (e) { /* yoksay */ }
            bilgiWorkspace = null;
        }
    }

    function ac() { pencereyiKur(); perde.classList.add('acik'); }

    function kacir(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    /* ----------------------------------------------------------------------
       EDITOR.JS VERİSİNİ HTML'E ÇEVİR

       Neden hazır bir kütüphane değil? Kullanılan blok türü dört tane
       (paragraf, başlık, liste, resim). Bunun için CDN'den ikinci bir
       kütüphane çekmek, çevrimdışı bir okul bilgisayarında sayfayı
       bozma riskine değmez.

       ⚠️ Editor.js metni HTML İÇEREBİLİR (kalın, italik, link). Bu yüzden
       paragraf/başlık gövdesi olduğu gibi basılıyor. İçeriği SÜPER ADMİN
       yazıyor, öğrenci değil; öğrenci verisi buraya hiç girmiyor.
       -------------------------------------------------------------------- */
    /**
     * Resim bloğunu çizer.
     *
     * Süper admin tasarlayıcıda resmi sola/sağa yaslayıp genişliğini
     * ayarlayabiliyor (assets/js/admin/resim_blogu.js). Buradaki `float`
     * sayesinde ALTINDAKİ yazı blokları resmin yanındaki boşluğa akıyor —
     * gazete sayfası gibi.
     *
     * ⚠️ Alan adları admin tarafıyla ANLAŞMALI: url, aciklama, hizalama,
     * genislik. Biri değişirse ikisi birden değişmeli.
     *
     * Eski @editorjs/image ile kaydedilmiş bloklar `file.url` + `caption`
     * kullanıyordu; onlar da çizilsin diye ikisi de okunuyor.
     */
    function resmiCiz(d) {
        var url = (d.file && d.file.url) || d.url || '';
        if (!url) { return ''; }

        /* Adres proje köküne göreli saklanır ('img/gorev_ipuclari/x.png');
           oynatıcı iki klasör içeride olduğu için başa ../../ ekleniyor. */
        var tam = /^(https?:)?\/\//.test(url) || url.charAt(0) === '/'
                ? url
                : '../../' + url.replace(/^\.\.\/+/, '');

        var hizalama = d.hizalama || 'orta';
        var genislik = parseInt(d.genislik, 10) || 100;
        if (genislik < 10)  { genislik = 10; }
        if (genislik > 100) { genislik = 100; }

        var stil = (hizalama === 'tam') ? 'width:100%;' : 'width:' + genislik + '%;';
        var aciklama = d.aciklama || d.caption || '';

        return '<span class="bilgi-resim ' + hizalama + '">' +
                   '<img src="' + kacir(tam) + '" alt="" style="' + stil + '">' +
                   (aciklama ? '<span class="bilgi-resim-alt">' + aciklama + '</span>' : '') +
               '</span>';
    }

    function metniCiz(veri) {
        if (!veri || !Array.isArray(veri.blocks)) { return ''; }

        return veri.blocks.map(function (b) {
            var d = b.data || {};
            switch (b.type) {
                case 'header':
                    var seviye = Math.min(6, Math.max(2, parseInt(d.level, 10) || 3));
                    return '<h' + seviye + '>' + (d.text || '') + '</h' + seviye + '>';

                case 'list':
                    var etiket = (d.style === 'ordered') ? 'ol' : 'ul';
                    var maddeler = (d.items || []).map(function (m) {
                        /* Editor.js sürümüne göre madde ya düz metin ya {content} */
                        return '<li>' + (typeof m === 'string' ? m : (m && m.content) || '') + '</li>';
                    }).join('');
                    return '<' + etiket + '>' + maddeler + '</' + etiket + '>';

                /* 'resim'  -> projeye özel blok (admin/resim_blogu.js)
                   'image'   -> hazır @editorjs/image ile kaydedilmiş eski
                                içerikler. İkisi de çizilebilsin ki bir sürüm
                                geçişinde kimsenin resmi kaybolmasın. */
                case 'resim':
                case 'image':
                    return resmiCiz(d);

                case 'paragraph':
                default:
                    return '<p>' + (d.text || '') + '</p>';
            }
        }).join('');
    }

    /* ----------------------------------------------------------------------
       BİLGİYİ GÖSTER
       -------------------------------------------------------------------- */
    function icerigiGoster(icerik, yariPuan) {
        ac();

        document.getElementById('bilgiBas').className = 'bilgi-bas acik';
        document.getElementById('bilgiSim').textContent = '💡';
        document.getElementById('bilgiBaslik').textContent = 'Bilgi';
        document.getElementById('bilgiAltBaslik').textContent =
            yariPuan ? ('Bu görevden kazanabileceğin ödül: ' + yariPuan + ' puan + ' + yariPuan + ' 💎') : '';

        var govde = document.getElementById('bilgiGovde');
        govde.innerHTML = metniCiz(icerik && icerik.metin);

        var xml = (icerik && icerik.bloklar) || '';
        if (xml.trim() !== '') {
            var etiket = document.createElement('div');
            etiket.className = 'bilgi-blok-etiket';
            etiket.textContent = 'Örnek blok dizilimi (sadece göstermelik)';
            govde.appendChild(etiket);

            var kutu = document.createElement('div');
            kutu.className = 'bilgi-bloklar';
            kutu.id = 'bilgiBloklarKutusu';
            govde.appendChild(kutu);

            /* Pencere görünür olduktan sonra enjekte et: Blockly kutuyu
               ölçerek çiziyor, gizliyken ölçü 0 çıkar ve bloklar görünmez. */
            setTimeout(function () { bloklariCiz(xml); }, 40);
        }

        var alt = document.getElementById('bilgiAlt');
        alt.innerHTML = '';
        var kapatBtn = document.createElement('button');
        kapatBtn.type = 'button';
        kapatBtn.className = 'bilgi-btn bilgi-btn-kapat';
        kapatBtn.textContent = 'Anladım, Kapat';
        kapatBtn.addEventListener('click', kapat);
        alt.appendChild(kapatBtn);
    }

    function bloklariCiz(xml) {
        var kutu = document.getElementById('bilgiBloklarKutusu');
        if (!kutu || typeof Blockly === 'undefined') { return; }

        try {
            bilgiWorkspace = Blockly.inject(kutu, {
                readOnly: true,          // sürüklenemez, silinemez — bu bir açıklama
                renderer: 'zelos',
                theme: Blockly.Themes.Zelos,
                trashcan: false,
                scrollbars: true,
                zoom: { controls: true, wheel: false, startScale: 0.8 }
            });
            Blockly.Xml.domToWorkspace(xmlDomu(xml), bilgiWorkspace);
            Blockly.svgResize(bilgiWorkspace);
        } catch (e) {
            console.warn('[Bilgi Al] Bilgi blokları çizilemedi:', e);
            kutu.innerHTML = '<div style="padding:16px;color:#64748b;">Blok görseli yüklenemedi.</div>';
        }
    }

    /* ----------------------------------------------------------------------
       UYARI EKRANI
       -------------------------------------------------------------------- */
    function bedeliSor(durum) {
        ac();

        document.getElementById('bilgiBas').className = 'bilgi-bas uyari';
        document.getElementById('bilgiSim').textContent = '⚠️';
        document.getElementById('bilgiBaslik').textContent = 'Bilgi almak istiyor musun?';
        document.getElementById('bilgiAltBaslik').textContent = 'Bu görevde sana yardımcı olacak bir bilgi var.';

        document.getElementById('bilgiGovde').innerHTML =
            '<div class="bilgi-bedel">' +
              'Bilgiyi açarsan bu görevden kazanacağın ödül <b>yarıya iner</b>:' +
              '<div style="margin:12px 0 4px;font-size:16px;">' +
                '<b>' + durum.tam_puan + ' puan + ' + durum.tam_puan + ' 💎</b>' +
                '<span style="margin:0 10px;color:#b45309;">→</span>' +
                '<b>' + durum.yari_puan + ' puan + ' + durum.yari_puan + ' 💎</b>' +
              '</div>' +
              '<div style="margin-top:12px;font-size:13.5px;">' +
                'Önce kendin denemek istersen <b>Vazgeç</b>\'e bas — hiçbir şey kaybetmezsin. ' +
                'Bir kez açarsan bu görevde tekrar tekrar bakabilirsin, ceza yine bir kez uygulanır.' +
              '</div>' +
            '</div>';

        var alt = document.getElementById('bilgiAlt');
        alt.innerHTML = '';

        var vazgec = document.createElement('button');
        vazgec.type = 'button';
        vazgec.className = 'bilgi-btn bilgi-btn-vazgec';
        vazgec.textContent = 'Vazgeç';
        vazgec.addEventListener('click', kapat);

        var kabul = document.createElement('button');
        kabul.type = 'button';
        kabul.className = 'bilgi-btn bilgi-btn-kabul';
        kabul.textContent = '💡 Kabul ediyorum, göster';
        kabul.addEventListener('click', function () {
            kabul.disabled = true;
            vazgec.disabled = true;
            kabul.textContent = '⏳ Açılıyor…';
            bilgiyiAl();
        });

        alt.appendChild(vazgec);
        alt.appendChild(kabul);
    }

    function bilgiyiAl() {
        istek('al').then(function (d) {
            if (!d.success) { hataGoster(d.mesaj || d.message); return; }
            kontrolDugmesiniGuncelle(d.yari_puan);
            icerigiGoster(d.icerik, d.yari_puan);
        }).catch(function () { hataGoster('Sunucuya ulaşılamadı.'); });
    }

    /**
     * "✅ Görevimi Kontrol Et (20 Puan)" düğmesindeki sayıyı yarıya çeker.
     *
     * Sayfa yenilenmeden bilgi alındığında düğmede hâlâ eski puan yazardı;
     * öğrenci 10 puan alınca sistemin hatalı saydığını düşünürdü.
     * Görünmeyen ceza, ceza değil kafa karışıklığıdır.
     */
    function kontrolDugmesiniGuncelle(yariPuan) {
        var btn = document.getElementById('btnHakem');
        if (!btn || !yariPuan) { return; }
        window.v2GorevPuani = yariPuan;
        btn.textContent = '✅ Görevimi Kontrol Et (' + yariPuan + ' Puan)';
    }

    function hataGoster(mesaj) {
        ac();
        document.getElementById('bilgiBas').className = 'bilgi-bas uyari';
        document.getElementById('bilgiSim').textContent = 'ℹ️';
        document.getElementById('bilgiBaslik').textContent = 'Bilgi';
        document.getElementById('bilgiAltBaslik').textContent = '';
        document.getElementById('bilgiGovde').innerHTML = '<p>' + kacir(mesaj || 'Bir şeyler ters gitti.') + '</p>';

        var alt = document.getElementById('bilgiAlt');
        alt.innerHTML = '';
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'bilgi-btn bilgi-btn-kapat';
        b.textContent = 'Kapat';
        b.addEventListener('click', kapat);
        alt.appendChild(b);
    }

    function istek(islem) {
        return fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ islem: islem, gorev_id: window.v2AktifGorevId || 0 })
        }).then(function (r) { return r.json(); });
    }

    /* ----------------------------------------------------------------------
       DÜĞMENİN BAĞLANDIĞI TEK NOKTA
       (hakem_motoru.js'teki eski tahmin motorunun yerini alır)
       -------------------------------------------------------------------- */
    window.ipucuGoster = function () {
        if (!window.v2AktifGorevId) {
            hataGoster('Şu an açık bir görev yok.');
            return;
        }

        istek('durum').then(function (d) {
            if (!d.success) { hataGoster(d.mesaj || d.message); return; }

            if (!d.var) {
                hataGoster('Bu görev için ayrı bir bilgi hazırlanmamış. '
                         + 'Görev metnini baştan oku, blokları sırayla dizmeyi dene ve '
                         + 'her bloğa tıklayarak ne yaptığını gör.');
                return;
            }

            /* Bedelini bir kez ödedi — istediği kadar bakabilir */
            if (d.alinmis) { icerigiGoster(d.icerik, d.yari_puan); return; }

            bedeliSor(d);
        }).catch(function () { hataGoster('Sunucuya ulaşılamadı.'); });
    };
})();
