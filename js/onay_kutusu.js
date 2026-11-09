/* ============================================================================
 * MODÜL: Ortak Onay / Metin Penceresi
 * DOSYA YOLU: js/onay_kutusu.js
 * KULLANICI: Bütün sayfalar (öğrenci, öğretmen, Süper Admin)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Tarayıcının "localhost web sitesinin mesajı" yazan confirm() / prompt()
 * kutularının yerine sayfanın kendi penceresini açar.
 * Neden? (bkz. CLAUDE.md): o kutular biçimlendirilemez, sınıfa yansıtılan
 * ekranda çirkin görünür ve tarayıcıda "bu sayfa daha fazla iletişim kutusu
 * göstermesin" işaretlenirse prompt() SORU SORMADAN null döner.
 *
 * KULLANIM:
 *   if (!(await onayKutusu('Silinsin mi?'))) return;
 *   onayKutusu({ baslik: 'Sil', mesaj: '...', onayYazi: 'Evet, sil', tehlikeli: true })
 *   const ad = await metinKutusu({ mesaj: 'Yeni ad:', varsayilan: 'Kuklam 1' });  // iptal → null
 *
 *   HTML tarafında JavaScript yazmadan:
 *     <form method="POST" data-onay="Silinsin mi?"> ... </form>
 *     <a href="..." data-onay="Emin misiniz?">...</a>
 *     <button data-onay="..." onclick="...">  (onay sonrası onclick çalışır)
 *
 * Metinler HER ZAMAN textContent ile basılır — XSS'e kapalıdır.
 * Klavye: Enter = onay, Esc = iptal. Pencere açıkken ikinci çağrı sıraya girer.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-11] Dosya oluşturuldu; projedeki tüm confirm()/prompt() buna taşındı.
 * ========================================================================== */
(function () {
    'use strict';
    if (window.onayKutusu) { return; }   // iki kez yüklenirse tek kopya kalsın

    var STIL = '' +
        '#okPerde{position:fixed;inset:0;z-index:100000;display:none;align-items:center;justify-content:center;' +
        'padding:16px;background:rgba(15,23,42,.6);font-family:"Segoe UI",Tahoma,sans-serif}' +
        '#okPerde.acik{display:flex}' +
        '#okKutu{background:#fff;color:#1e293b;width:100%;max-width:440px;border-radius:14px;' +
        'box-shadow:0 20px 50px rgba(0,0,0,.35);overflow:hidden;animation:okGir .15s ease-out}' +
        '@keyframes okGir{from{transform:translateY(8px) scale(.98);opacity:0}to{transform:none;opacity:1}}' +
        '#okBaslik{margin:0;padding:18px 22px 0;font-size:18px;font-weight:700}' +
        '#okMesaj{padding:10px 22px 4px;font-size:15px;line-height:1.5;color:#475569;white-space:pre-line}' +
        '#okGirdi{display:none;margin:8px 22px 0;width:calc(100% - 44px);box-sizing:border-box;padding:10px 12px;' +
        'border:2px solid #cbd5e1;border-radius:8px;font-size:15px;outline:none}' +
        '#okGirdi:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15)}' +
        '#okDugmeler{display:flex;gap:10px;justify-content:flex-end;padding:18px 22px 20px}' +
        '#okDugmeler button{border:none;border-radius:8px;padding:10px 18px;font-size:15px;font-weight:700;cursor:pointer}' +
        '#okIptal{background:#e2e8f0;color:#334155}#okIptal:hover{background:#cbd5e1}' +
        '#okTamam{background:#3b82f6;color:#fff}#okTamam:hover{background:#2563eb}' +
        '#okTamam.tehlike{background:#ef4444}#okTamam.tehlike:hover{background:#dc2626}';

    var perde, baslikEl, mesajEl, girdiEl, tamamEl, iptalEl;
    var sira = [], aktif = null;

    function kur() {
        if (perde) { return; }
        var st = document.createElement('style');
        st.textContent = STIL;
        document.head.appendChild(st);

        perde = document.createElement('div');
        perde.id = 'okPerde';
        perde.setAttribute('role', 'dialog');
        perde.setAttribute('aria-modal', 'true');
        perde.innerHTML = '<div id="okKutu"><h3 id="okBaslik"></h3><div id="okMesaj"></div>' +
            '<input id="okGirdi" type="text" autocomplete="off">' +
            '<div id="okDugmeler"><button type="button" id="okIptal"></button>' +
            '<button type="button" id="okTamam"></button></div></div>';
        document.body.appendChild(perde);

        baslikEl = perde.querySelector('#okBaslik');
        mesajEl  = perde.querySelector('#okMesaj');
        girdiEl  = perde.querySelector('#okGirdi');
        tamamEl  = perde.querySelector('#okTamam');
        iptalEl  = perde.querySelector('#okIptal');

        tamamEl.addEventListener('click', function () { bitir(true); });
        iptalEl.addEventListener('click', function () { bitir(false); });
        perde.addEventListener('mousedown', function (e) { if (e.target === perde) { bitir(false); } });
        document.addEventListener('keydown', function (e) {
            if (!aktif) { return; }
            if (e.key === 'Escape') { e.preventDefault(); bitir(false); }
            else if (e.key === 'Enter' && (document.activeElement !== iptalEl)) { e.preventDefault(); bitir(true); }
        }, true);
    }

    function goster(is) {
        kur();
        aktif = is;
        var tehlikeli = is.tehlikeli;
        if (tehlikeli === undefined) {
            tehlikeli = /sil|kalıcı|kapat|bitir|düş/i.test(is.mesaj + ' ' + (is.baslik || ''));
        }
        baslikEl.textContent = is.baslik || (is.tur === 'metin' ? '✏️ Bilgi girin' : '⚠️ Emin misiniz?');
        mesajEl.textContent  = is.mesaj || '';
        tamamEl.textContent  = is.onayYazi || (is.tur === 'metin' ? 'Tamam' : 'Evet');
        iptalEl.textContent  = is.iptalYazi || 'Vazgeç';
        tamamEl.classList.toggle('tehlike', is.tur !== 'metin' && !!tehlikeli);

        girdiEl.style.display = is.tur === 'metin' ? 'block' : 'none';
        girdiEl.value = is.tur === 'metin' ? String(is.varsayilan == null ? '' : is.varsayilan) : '';
        girdiEl.placeholder = is.yerTutucu || '';
        girdiEl.type = is.girdiTuru || 'text';

        perde.classList.add('acik');
        setTimeout(function () {
            if (is.tur === 'metin') { girdiEl.focus(); girdiEl.select(); } else { tamamEl.focus(); }
        }, 0);
    }

    function bitir(onay) {
        if (!aktif) { return; }
        var is = aktif;
        aktif = null;
        perde.classList.remove('acik');
        is.coz(is.tur === 'metin' ? (onay ? girdiEl.value : null) : !!onay);
        if (sira.length) { goster(sira.shift()); }
    }

    function siraya(is) {
        return new Promise(function (coz) {
            is.coz = coz;
            var baslat = function () { if (aktif) { sira.push(is); } else { goster(is); } };
            if (document.body) { baslat(); } else { document.addEventListener('DOMContentLoaded', baslat); }
        });
    }

    function secenek(a) { return (typeof a === 'object' && a !== null) ? a : { mesaj: String(a == null ? '' : a) }; }

    /** @returns {Promise<boolean>} */
    window.onayKutusu = function (a) {
        var is = secenek(a); is.tur = 'onay';
        return siraya(is);
    };

    /** @returns {Promise<string|null>} iptal edilirse null */
    window.metinKutusu = function (a, varsayilan) {
        var is = secenek(a); is.tur = 'metin';
        if (varsayilan !== undefined && is.varsayilan === undefined) { is.varsayilan = varsayilan; }
        return siraya(is);
    };

    /* ---- data-onay: form, bağlantı ve düğmeler için JavaScript'siz kullanım ---- */
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f || !f.hasAttribute || !f.hasAttribute('data-onay') || f.__okOnaylandi) { return; }
        e.preventDefault();
        var gonderen = e.submitter || null;
        window.onayKutusu(f.getAttribute('data-onay')).then(function (ok) {
            if (!ok) { return; }
            f.__okOnaylandi = true;
            if (f.requestSubmit) { f.requestSubmit(gonderen || undefined); } else { f.submit(); }
            f.__okOnaylandi = false;
        });
    }, true);

    document.addEventListener('click', function (e) {
        var el = e.target && e.target.closest ? e.target.closest('a[data-onay], button[data-onay]:not([type=submit])') : null;
        if (!el || el.__okOnaylandi) { return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        window.onayKutusu(el.getAttribute('data-onay')).then(function (ok) {
            if (!ok) { return; }
            if (el.tagName === 'A' && el.href) { window.location.href = el.href; return; }
            el.__okOnaylandi = true;
            el.click();
            el.__okOnaylandi = false;
        });
    }, true);
})();
