/* ====================================================================
 * MODÜL: Düello Motoru (bağımsız, yol-duyarlı)
 * DOSYA YOLU: js/duello_motoru.js
 * KULLANAN SAYFALAR:
 *    ogrenci_paneli.php, profil.php, market.php  (bilesenler/duello_radari.php üzerinden)
 *    v2_gorev_sistemi/ogrenci/oynatici.php       (iframe içinde)
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * "⚔️ Düello" butonuna basınca çalışan ve gelen meydan okumaları gösteren
 * motor. Eskiden bu kod doğrudan ogrenci_paneli.php'nin içine gömülüydü;
 * oynatici.php iframe'i skorboardu kopyaladığında butonlar geliyor ama
 * fonksiyonlar gelmiyordu. Buton `window.parent` üzerinden fonksiyonu
 * bulmaya çalışıyor, bulamayınca "meydanOku is not defined" hatası veriyordu.
 * Artık motor ayrı bir dosya; hem ana sayfaya hem iframe'e yükleniyor.
 *
 * AYARLAR (script yüklenmeden ÖNCE tanımlanmalı):
 *   window.MBLOCK_TABAN        — API'lere giden yolun ön eki.
 *                                Kök sayfalarda ''  |  oynatici.php'de '../../'
 *   window.MBLOCK_DUELLO_DINLE — false ise gelen istekleri dinlemez.
 *                                (iframe'de false; ana sayfa zaten dinliyor)
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu — "meydanOku is not defined" hatası çözüldü.
 * ==================================================================== */

(function () {
    "use strict";

    // Aynı sayfaya iki kez yüklenirse ikinci kez kurulum yapma
    if (window.mBlockDuello) return;

    const TABAN = (typeof window.MBLOCK_TABAN === 'string') ? window.MBLOCK_TABAN : '';
    const DINLE = (window.MBLOCK_DUELLO_DINLE !== false);

    /* --- Sesler (dosya yoksa sessizce geç) --- */
    let uyariSesi = null, ogretmenUyariSesi = null;
    try {
        uyariSesi = new Audio(TABAN + 'assets/sounds/uyari.mp3');
        ogretmenUyariSesi = new Audio(TABAN + 'assets/sounds/ogretmenuyari.mp3');
    } catch (e) { /* yoksay */ }

    function sesCal(ses) {
        try { if (ses) ses.play().catch(function () {}); } catch (e) {}
    }

    /* --- Bildirim kutusu --- */
    function bildirimGoster(mesaj, tur) {
        const kutu = document.createElement('div');
        kutu.style.cssText =
            'position:fixed; top:20px; left:50%; transform:translateX(-50%);' +
            'background:' + (tur === 'hata' ? '#ef4444' : '#f59e0b') + ';' +
            'color:white; padding:15px 30px; border-radius:12px;' +
            'box-shadow:0 10px 25px rgba(0,0,0,0.5); z-index:2147483000;' +
            'font-weight:bold; font-size:16px; text-align:center; max-width:90%;';
        kutu.textContent = mesaj;
        document.body.appendChild(kutu);

        setTimeout(function () {
            kutu.style.opacity = '0';
            kutu.style.transition = '0.5s';
            setTimeout(function () { kutu.remove(); }, 500);
        }, 3000);
    }

    /* --- İstek penceresi (modal) --- */
    let istekModal = document.getElementById('duelloIstekModal');
    if (!istekModal) {
        istekModal = document.createElement('div');
        istekModal.id = 'duelloIstekModal';
        istekModal.style.cssText =
            'display:none; position:fixed; top:0; left:0; width:100%; height:100%;' +
            'background:rgba(15,23,42,0.85); z-index:2147483001;' +
            'justify-content:center; align-items:center; backdrop-filter:blur(5px);';
        istekModal.innerHTML =
            '<div style="background:#1e293b; padding:30px; border-radius:16px; border:2px solid #ef4444;' +
            'width:350px; max-width:90%; text-align:center; box-shadow:0 0 40px rgba(239,68,68,0.4); color:white;">' +
            '<div id="istekModalIkon" style="font-size:50px; margin-bottom:10px;">🚨</div>' +
            '<h2 id="istekModalBaslik" style="margin-top:0; color:#ef4444;">DÜELLO İSTEĞİ!</h2>' +
            '<p id="istekModalMetin" style="font-size:15px; margin-bottom:15px; color:#cbd5e1;"></p>' +
            '<div style="font-size:26px; font-weight:bold; color:#facc15; margin-bottom:20px;">⏳ <span id="istekModalSayac">5</span></div>' +
            '<div id="istekModalButonlar" style="display:flex; gap:10px;"></div>' +
            '</div>';
        document.body.appendChild(istekModal);
    }

    let sayacInterval = null;

    function modalKapat() {
        clearInterval(sayacInterval);
        istekModal.style.display = 'none';
    }

    /** Modalın kenar rengini ve ikonunu ayarlar. */
    function modalBicimle(ikon, baslik, renk) {
        document.getElementById('istekModalIkon').textContent = ikon;
        const b = document.getElementById('istekModalBaslik');
        b.textContent = baslik;
        b.style.color = renk;
        const ic = istekModal.querySelector('div');
        if (ic) ic.style.borderColor = renk;
    }

    /* ================================================================
     * 1. MEYDAN OKUMA (saldıran taraf)
     * Skorboarddaki "⚔️ Düello" butonu bunu çağırır.
     * ============================================================== */
    function meydanOku(hedefId, hedefAd) {
        if (!confirm('⚔️ ' + hedefAd + ' adlı savaşçıya meydan okumak üzeresin! Emin misin?')) return;

        const veri = new FormData();
        veri.append('hedef_id', hedefId);

        fetch(TABAN + 'api/duello/istek_at.php', {
            method: 'POST',
            body: veri,
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!(data.success === true || data.status === 'success')) {
                    bildirimGoster(data.mesaj || data.message || 'Meydan okuma gönderilemedi.', 'hata');
                    return;
                }

                modalBicimle('⚔️', 'MEYDAN OKUNDU!', '#3b82f6');
                document.getElementById('istekModalMetin').textContent =
                    hedefAd + ' adlı savaşçının cevabı bekleniyor...';
                document.getElementById('istekModalButonlar').innerHTML = '';

                let sayac = 8;
                document.getElementById('istekModalSayac').textContent = sayac;
                istekModal.style.display = 'flex';

                clearInterval(sayacInterval);
                sayacInterval = setInterval(function () {
                    sayac--;
                    document.getElementById('istekModalSayac').textContent = sayac;
                    if (sayac <= 0) {
                        modalKapat();
                        bildirimGoster('Rakip cevap vermedi, istek zaman aşımına uğradı.', 'hata');
                    }
                }, 1000);
            })
            .catch(function (e) {
                console.error('Düello isteği hatası:', e);
                bildirimGoster('Sunucuya ulaşılamadı.', 'hata');
            });
    }

    /* ================================================================
     * 2. GELEN İSTEĞİ GÖSTER (savunan taraf)
     * ============================================================== */
    let yanitlaniyor = false;

    function gelenIstegiGoster(data) {
        if (yanitlaniyor) return;
        yanitlaniyor = true;

        sesCal(uyariSesi);
        modalBicimle('🚨', 'DÜELLO İSTEĞİ!', '#ef4444');

        // XSS koruması: ad HTML olarak yorumlanmasın
        const ad = document.createElement('b');
        ad.textContent = data.meydan_okuyan_ad || 'Bir arkadaşın';

        const metin = document.getElementById('istekModalMetin');
        metin.innerHTML = '';
        metin.appendChild(ad);
        metin.insertAdjacentHTML('beforeend',
            ' sana meydan okuyor!<br><br><span style="font-size:12px; color:#94a3b8;' +
            'background:#0f172a; padding:5px 10px; border-radius:5px;">' +
            'Kazanırsan +6 Puan, Kaybedersen -3 Puan</span>');

        document.getElementById('istekModalButonlar').innerHTML =
            '<button id="btnReddet" style="flex:1; padding:12px; background:#ef4444; color:white; border:none;' +
            'border-radius:8px; cursor:pointer; font-weight:bold;">❌ Reddet</button>' +
            '<button id="btnKabul" style="flex:1; padding:12px; background:#10b981; color:white; border:none;' +
            'border-radius:8px; cursor:pointer; font-weight:bold;">✔️ Kabul Et</button>';

        let sayac = 8;
        document.getElementById('istekModalSayac').textContent = sayac;
        istekModal.style.display = 'flex';

        let gonderildi = false;

        function yanitGonder(yanit) {
            if (gonderildi) return;
            gonderildi = true;
            modalKapat();

            const veri = new FormData();
            veri.append('duello_id', data.duello_id);
            veri.append('yanit', yanit);

            fetch(TABAN + 'api/duello/yanitla.php', {
                method: 'POST',
                body: veri,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (cevap) {
                    if (yanit === 'kabul' && cevap.status === 'basladi') {
                        bildirimGoster('⚔️ Savaş başlıyor! Arenaya ışınlanıyorsunuz...');
                        arenayaGit(data.duello_id);
                    } else {
                        if (yanit === 'red') bildirimGoster('Düelloyu reddettin.', 'hata');
                        yanitlaniyor = false;
                        if (window.mBlockRadar) window.mBlockRadar.baslat();
                    }
                })
                .catch(function () {
                    yanitlaniyor = false;
                    bildirimGoster('Sunucuya ulaşılamadı.', 'hata');
                });
        }

        document.getElementById('btnReddet').onclick = function () { yanitGonder('red'); };
        document.getElementById('btnKabul').onclick = function () { yanitGonder('kabul'); };

        clearInterval(sayacInterval);
        sayacInterval = setInterval(function () {
            sayac--;
            document.getElementById('istekModalSayac').textContent = sayac;
            if (sayac <= 0) yanitGonder('red');
        }, 1000);
    }

    /** Arenaya yönlendir. iframe içindeysek en üst pencereyi taşı. */
    function arenayaGit(duelloId) {
        const hedef = TABAN + 'duello_arena.php?id=' + duelloId;
        if (window.top && window.top !== window) {
            window.top.location.href = (TABAN ? TABAN : '') + 'duello_arena.php?id=' + duelloId;
        } else {
            window.location.href = hedef;
        }
    }

    /* ================================================================
     * 3. RADAR ABONELİĞİ (sadece ana sayfada)
     * ============================================================== */
    let sonArenaDurumu = null;

    if (DINLE && window.mBlockRadar) {
        window.mBlockRadar.abone(function (radarVerisi) {
            const data = (radarVerisi && radarVerisi.duello) || {};

            // Öğretmen düelloyu açtı/kapattı → sayfayı tazele ki butonlar güncellensin
            if (sonArenaDurumu !== null && sonArenaDurumu !== data.arena_aktif) {
                sonArenaDurumu = data.arena_aktif;
                sesCal(ogretmenUyariSesi);
                alert(data.arena_aktif
                    ? '⚔️ MÜJDE! Düello Arenası Açıldı! Artık rakiplerinize meydan okuyabilirsiniz.'
                    : '🔒 Düello Arenası Kapatıldı veya Süre Doldu! Kılıçlar kınına dönüyor...');
                window.location.reload();
                return;
            }
            sonArenaDurumu = data.arena_aktif;

            // Rakip kabul etti → arenaya git
            if (data.arenaya_git) {
                window.mBlockRadar.durdur();
                clearInterval(sayacInterval);
                arenayaGit(data.duello_id);
                return;
            }

            // Bana meydan okundu
            if (data.gelen_istek) {
                window.mBlockRadar.durdur();
                gelenIstegiGoster(data);
            }
        });
    }

    /* --- Dışarıya aç (skorboard butonları bunları çağırıyor) --- */
    window.meydanOku      = meydanOku;
    window.bildirimGoster = window.bildirimGoster || bildirimGoster;
    window.mBlockDuello   = {
        meydanOku: meydanOku,
        bildirimGoster: bildirimGoster,
        taban: TABAN,
        dinliyor: DINLE
    };
})();
