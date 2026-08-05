/* ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/assets/js/ogrenci/ilerleme_cubugu.js
 * KULLANICI / SAYFA: Öğrenci -> Görev Oynatıcı (sol panelin tepesi)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * "Bölüm 1 › 10 Adım Git" başlığını ve altındaki renkli görev dairelerini
 * çizer. Öğrenci bir daireye tıklayınca o göreve gider — böylece öğretmen
 * "1. bölümü tekrar çöz" dediğinde eski görevlere geri dönebilir.
 *
 * RENKLER:
 *   yeşil   tamamladı (puanı aldı)
 *   turuncu şu an burada
 *   gri     sırası gelmedi
 *   kırmızı öğretmen tekrar yapmasını istedi (nabız gibi atar)
 *   kilit   bölüme henüz ulaşmadı, tıklanamaz
 *
 * PUAN KURALI: Tamamlanmış bir göreve geri dönüp tekrar çözmek serbesttir,
 * ama puan/elmas İKİNCİ KEZ VERİLMEZ (sunucu tarafında engelli).
 *
 * DIŞARI AÇILAN FONKSİYONLAR:
 *   window.ilerlemeCubuguCiz(harita)  — haritayı yeniden çizer
 *   window.ilerlemeyiTazele()         — sunucudan güncel haritayı çeker
 *   window.cezaBildiriminiKapat()     — öğretmen uyarısını kapatır
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu.
 * ========================================================================== */

(function () {
    'use strict';

    var API = window.V2_ILERLEME_API || '../api/ilerleme_haritasi.php';
    var sonHarita = window.V2_ILERLEME || null;

    function el(id) { return document.getElementById(id); }

    function kacir(metin) {
        var d = document.createElement('div');
        d.textContent = metin == null ? '' : String(metin);
        return d.innerHTML;
    }

    /* --------------------------------------------------------------------
     * ÇİZİM
     * ------------------------------------------------------------------ */
    window.ilerlemeCubuguCiz = function (harita) {
        if (!harita) return;
        sonHarita = harita;

        var govde = el('icGovde');
        if (!govde) return;

        govde.innerHTML = '';

        (harita.bolumler || []).forEach(function (bolum) {
            // Görevi olmayan bölümleri gösterme (öğrenciyi kalabalık etmesin)
            if (!bolum.gorevler || bolum.gorevler.length === 0) return;

            var satir = document.createElement('div');
            satir.className = 'ic-bolum-satir' +
                (bolum.acik ? '' : ' kilitli') +
                (bolum.bitti ? ' bitti' : '');

            var kilitIkon = bolum.acik ? (bolum.bitti ? '✅ ' : '') : '🔒 ';

            var baslik = document.createElement('div');
            baslik.className = 'ic-bolum-ad';
            baslik.innerHTML = kilitIkon + kacir(bolum.ad) +
                ' <span class="ic-oran">(' + bolum.cozulen + '/' + bolum.toplam + ')</span>';
            satir.appendChild(baslik);

            var daireler = document.createElement('div');
            daireler.className = 'ic-daireler';

            bolum.gorevler.forEach(function (gorev, sira) {
                var d = document.createElement('button');
                d.type = 'button';
                d.className = 'ic-daire ' + gorev.durum + (gorev.burada ? ' burada' : '');
                d.setAttribute('data-ad', gorev.ad + (gorev.durum === 'sifirlandi' ? ' — tekrar yapman gerekiyor' : ''));
                d.textContent = gorev.durum === 'kilitli' ? '🔒' : (sira + 1);

                if (gorev.durum === 'kilitli') {
                    d.disabled = true;
                    d.title = 'Bu bölüme henüz ulaşmadın.';
                } else {
                    d.addEventListener('click', function () { goreveGit(gorev.id); });
                }

                daireler.appendChild(d);
            });

            satir.appendChild(daireler);
            govde.appendChild(satir);
        });

        // Üstteki genel çubuk
        var bar = document.querySelector('#ilerlemeCubugu .ic-bar span');
        if (bar) bar.style.width = (harita.genel_yuzde | 0) + '%';

        var oran = document.querySelector('#ilerlemeCubugu .ic-genel small');
        if (oran) oran.textContent = harita.cozulen_gorev + '/' + harita.toplam_gorev;
    };


    /* --------------------------------------------------------------------
     * GÖREVE GİT — öğrenci daireye tıkladı
     * Sunucuya haber verip (kaldığın yer kaydı) sonra sayfayı açıyoruz.
     * ------------------------------------------------------------------ */
    function goreveGit(gorevId) {
        if (!gorevId) return;
        if (gorevId === (sonHarita && sonHarita.aktif_gorev_id)) return; // zaten buradayız

        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ islem: 'gorev_ac', gorev_id: gorevId })
        })
        .then(function (r) { return r.json(); })
        .then(function (s) {
            if (!s.success) {
                alert(s.mesaj || s.message || 'Bu göreve şu an geçemezsin.');
                return;
            }
            window.location.href = (window.V2_OYNATICI_URL || 'oynatici.php') + '?gorev_id=' + gorevId;
        })
        .catch(function () {
            // Sunucuya ulaşılamadıysa da denemeye değer; kapıcı sayfada da var
            window.location.href = (window.V2_OYNATICI_URL || 'oynatici.php') + '?gorev_id=' + gorevId;
        });
    }


    /* --------------------------------------------------------------------
     * TAZELE — görev çözüldükten sonra çubuğu güncelle
     * @returns {Promise<object|null>} güncel harita
     * ------------------------------------------------------------------ */
    window.ilerlemeyiTazele = function () {
        return fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                islem: 'harita',
                aktif_gorev_id: window.v2AktifGorevId || 0
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (s) {
            var v = s.veri || s.data || s;
            if (!v || !v.harita) return null;

            window.ilerlemeCubuguCiz(v.harita);
            cezaEfektiniUygula(v.ceza);
            return v;
        })
        .catch(function () { return null; });
    };


    /* --------------------------------------------------------------------
     * KIRMIZI CEZA EFEKTİ
     * Bekleyen sıfırlanmış görev varsa gövdeye .ceza-modu eklenir, avatara
     * kırmızı sis basılır. Görevler bitince kendiliğinden kalkar.
     * ------------------------------------------------------------------ */
    function cezaEfektiniUygula(ceza) {
        var cezaliMi = !!(ceza && ceza.cezali);
        document.body.classList.toggle('ceza-modu', cezaliMi);

        // Sayfadaki vesikalık avatar (üst bardaki yuvarlak)
        var avatar = document.querySelector('.profile-circle');
        if (avatar) {
            avatar.classList.toggle('ceza-efekti', cezaliMi);

            var rozet = avatar.querySelector('.ceza-rozet');
            if (cezaliMi) {
                if (!rozet) {
                    rozet = document.createElement('span');
                    rozet.className = 'ceza-rozet';
                    avatar.appendChild(rozet);
                }
                rozet.textContent = ceza.gorev_sayisi;
                rozet.title = 'Tekrar yapman gereken görev sayısı';
            } else if (rozet) {
                rozet.remove();
            }
        }

        // Üst pencereye (ogrenci_paneli.php) haber ver — avatar orada da var
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    tip: 'v2_ceza_durumu',
                    cezali: cezaliMi,
                    gorev_sayisi: ceza ? (ceza.gorev_sayisi | 0) : 0
                }, '*');
            }
        } catch (e) { /* farklı kaynak — sorun değil */ }
    }


    /* --------------------------------------------------------------------
     * ÖĞRETMEN BİLDİRİMİ
     * ------------------------------------------------------------------ */
    window.cezaBildiriminiKapat = function () {
        var kutu = el('cezaBildirimi');
        if (kutu) kutu.style.display = 'none';

        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ islem: 'bildirim_oku' })
        }).catch(function () {});
    };


    /* --------------------------------------------------------------------
     * BAŞLANGIÇ
     * ------------------------------------------------------------------ */
    function baslat() {
        window.ilerlemeCubuguCiz(sonHarita);
        cezaEfektiniUygula(window.V2_CEZA);

        var katla = el('icKatlaBtn');
        var cubuk = el('ilerlemeCubugu');
        if (katla && cubuk) {
            // Öğrencinin tercihi hatırlansın (sayfa yenilense de)
            try {
                if (sessionStorage.getItem('ic_katli') === '1') cubuk.classList.add('katli');
            } catch (e) {}

            katla.addEventListener('click', function () {
                cubuk.classList.toggle('katli');
                try {
                    sessionStorage.setItem('ic_katli', cubuk.classList.contains('katli') ? '1' : '0');
                } catch (e) {}
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', baslat);
    } else {
        baslat();
    }

})();
