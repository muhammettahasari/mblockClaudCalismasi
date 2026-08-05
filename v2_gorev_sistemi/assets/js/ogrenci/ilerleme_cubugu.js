/* ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/assets/js/ogrenci/ilerleme_cubugu.js
 * KULLANICI / SAYFA: Öğrenci -> Görev Oynatıcı (sayfanın en üstündeki şerit)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Üst şeritteki "Bölüm 1 › 10 Adım Git" başlığını ve yanındaki renkli görev
 * dairelerini çizer. Öğrenci bir daireye tıklayınca o göreve gider.
 *
 * SADECE BULUNULAN BÖLÜM GÖSTERİLİR:
 * Öğrenci 2. bölümdeyse 1. bölümün daireleri ekrana basılmaz — şerit dar bir
 * satır olduğu için kalabalık etmesin. Bitirilen bölümlerin toplamı sağdaki
 * yüzde çubuğunda zaten görünüyor.
 *
 * RENKLER:
 *   yeşil   tamamladı (puanı aldı)
 *   turuncu şu an burada
 *   gri     sırası gelmedi
 *   kırmızı öğretmen tekrar yapmasını istedi (nabız gibi atar)
 *
 * PUAN KURALI: Tamamlanmış bir göreve dönüp tekrar çözmek serbesttir ama
 * puan/elmas İKİNCİ KEZ VERİLMEZ (sunucu tarafında engelli).
 *
 * CANLI BİLDİRİM:
 * Öğretmen bir bölümü tekrar ettirdiğinde öğrencinin sayfayı yenilemesi
 * gerekmez; şerit 15 saniyede bir sunucuyu yoklar ve kırmızı uyarı kartını
 * anında açar.
 *
 * DIŞARI AÇILAN FONKSİYONLAR:
 *   window.ilerlemeCubuguCiz(harita) — şeridi yeniden çizer
 *   window.ilerlemeyiTazele()        — sunucudan güncel haritayı çeker
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu.
 *  - [2026-08] Tam genişlik şerit, sadece aktif bölüm, canlı bildirim.
 * ========================================================================== */

(function () {
    'use strict';

    var API = window.V2_ILERLEME_API || '../api/ilerleme_haritasi.php';

    var sonHarita       = window.V2_ILERLEME || null;
    var gosterilenUyari = 0;      // en son gösterilen bildirim id'si
    var yoklamaSuruyor  = false;  // aynı anda iki istek gitmesin

    /* Sayfa ilk açıldığında sunucudan gelen bildirim (varsa) */
    var ilkBildirim = window.V2_BILDIRIM || null;

    function el(id) { return document.getElementById(id); }


    /* ====================================================================
     * BULUNULAN BÖLÜMÜ SEÇ
     * Sıra: (1) aktif görevin bölümü, yoksa (2) tamamlanmamış ilk açık
     * bölüm, yoksa (3) son açık bölüm.
     * ================================================================== */
    function aktifBolumuBul(harita) {
        if (!harita || !harita.bolumler || !harita.bolumler.length) return null;

        var aktifId = harita.aktif_gorev_id;
        var acikOlanlar = harita.bolumler.filter(function (b) {
            return b.acik && b.gorevler && b.gorevler.length > 0;
        });

        // 1) Açık olan görevin bölümü
        if (aktifId) {
            for (var i = 0; i < acikOlanlar.length; i++) {
                var bulundu = acikOlanlar[i].gorevler.some(function (g) { return g.id === aktifId; });
                if (bulundu) return acikOlanlar[i];
            }
        }

        // 2) Tamamlanmamış ilk bölüm
        for (var j = 0; j < acikOlanlar.length; j++) {
            if (!acikOlanlar[j].bitti) return acikOlanlar[j];
        }

        // 3) Her şey bitmişse son bölüm
        return acikOlanlar.length ? acikOlanlar[acikOlanlar.length - 1] : null;
    }


    /* ====================================================================
     * ÇİZİM
     * ================================================================== */
    window.ilerlemeCubuguCiz = function (harita) {
        if (!harita) return;
        sonHarita = harita;

        var govde = el('icGovde');
        if (!govde) return;

        govde.innerHTML = '';

        var bolum = aktifBolumuBul(harita);

        if (!bolum) {
            govde.innerHTML = '<span style="font-size:12.5px;color:#94a3b8;">Henüz görev yok.</span>';
        } else {
            /* Bölüm adı + oran başlıkta */
            var adKutu = el('icBolumAdi');
            if (adKutu) {
                adKutu.textContent = bolum.ad + ' (' + bolum.cozulen + '/' + bolum.toplam + ')';
            }

            bolum.gorevler.forEach(function (gorev, sira) {
                var etiketler = {
                    tamam:      ' ✅ tamamladın',
                    aktif:      ' ⏳ şu an buradasın',
                    sifirlandi: ' 🔴 öğretmenin tekrar yapmanı istedi',
                    bekliyor:   '',
                    kilitli:    ' 🔒 henüz ulaşmadın'
                };

                var d = document.createElement('button');
                d.type = 'button';
                d.className = 'ic-daire ' + gorev.durum + (gorev.burada ? ' burada' : '');
                d.title = (sira + 1) + '. ' + gorev.ad + (etiketler[gorev.durum] || '');
                d.textContent = gorev.durum === 'kilitli' ? '🔒' : (sira + 1);

                if (gorev.durum === 'kilitli') {
                    d.disabled = true;
                } else {
                    d.addEventListener('click', function () { goreveGit(gorev.id); });
                }

                govde.appendChild(d);
            });

            /* Aktif görevin adı başlıkta */
            var gorevKutu = el('icGorevAdi');
            if (gorevKutu) {
                var aktif = bolum.gorevler.filter(function (g) { return g.burada; })[0];
                if (aktif) gorevKutu.textContent = aktif.ad;
            }
        }

        /* Sağdaki genel yüzde — TÜM bölümlerin toplamı */
        var dolgu = el('icBarDolgu');
        if (dolgu) dolgu.style.width = (harita.genel_yuzde | 0) + '%';

        var oran = el('icOran');
        if (oran) oran.textContent = harita.cozulen_gorev + '/' + harita.toplam_gorev;
    };


    /* ====================================================================
     * GÖREVE GİT — öğrenci daireye tıkladı
     * ================================================================== */
    function goreveGit(gorevId) {
        if (!gorevId) return;
        if (sonHarita && gorevId === sonHarita.aktif_gorev_id) return; // zaten buradayız

        var git = function () {
            window.location.href = (window.V2_OYNATICI_URL || 'oynatici.php') + '?gorev_id=' + gorevId;
        };

        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ islem: 'gorev_ac', gorev_id: gorevId })
        })
        .then(function (r) { return r.json(); })
        .then(function (s) {
            if (!s.success) { alert(s.mesaj || s.message || 'Bu göreve şu an geçemezsin.'); return; }
            git();
        })
        .catch(git);   // sunucuya ulaşılamadıysa da dene; sayfada da kapıcı var
    }


    /* ====================================================================
     * TAZELE — sunucudan güncel harita
     * @returns {Promise<object|null>}
     * ================================================================== */
    window.ilerlemeyiTazele = function () {
        return fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
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


    /* ====================================================================
     * KIRMIZI CEZA EFEKTİ
     * Bekleyen sıfırlanmış görev varsa gövdeye .ceza-modu eklenir ve avatara
     * kırmızı sis basılır. Görevler bitince kendiliğinden kalkar.
     * ================================================================== */
    function cezaEfektiniUygula(ceza) {
        var cezaliMi = !!(ceza && ceza.cezali);
        document.body.classList.toggle('ceza-modu', cezaliMi);

        var avatar = document.querySelector('.profile-circle');
        if (avatar) {
            avatar.classList.toggle('ceza-efekti', cezaliMi);

            var rozet = avatar.querySelector('.ceza-rozet');
            if (cezaliMi) {
                if (!rozet) {
                    rozet = document.createElement('span');
                    rozet.className = 'ceza-rozet';
                    rozet.title = 'Tekrar yapman gereken görev sayısı';
                    avatar.appendChild(rozet);
                }
                rozet.textContent = ceza.gorev_sayisi;
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


    /* ====================================================================
     * ÖĞRETMEN UYARISI — kırmızı kartı aç
     * ================================================================== */
    function uyariyiGoster(bildirim) {
        if (!bildirim || !bildirim.id) return;
        if (Number(bildirim.id) === gosterilenUyari) return;   // aynısını iki kez açma
        gosterilenUyari = Number(bildirim.id);

        // Bildirimi okundu işaretle (bir daha açılmasın)
        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ islem: 'bildirim_oku' })
        }).catch(function () {});

        if (typeof window.uyariKartiGoster !== 'function') {
            alert('Öğretmenin ' + bildirim.gorev_sayisi + ' görevi tekrar yapmanı istedi.');
            window.location.reload();
            return;
        }

        var elmas = bildirim.elmas_cezasi | 0;

        window.uyariKartiGoster({
            baslik: 'Öğretmeninden Mesaj Var',
            altMetin: 'Öğretmenin bazı görevleri tekrar yapmanı istedi. '
                    + (elmas > 0
                        ? 'Bu görevlerden aldığın elmaslar geri alındı — tekrar çözünce geri kazanacaksın.'
                        : 'Görevleri tekrar çözmen yeterli.'),
            gorevSayisi: bildirim.gorev_sayisi | 0,
            elmasCezasi: elmas,
            sebep: bildirim.sebep || '',
            yenile: true          // kapatınca sayfa yenilenir -> ilgili göreve geçer
        });
    }


    /* ====================================================================
     * CANLI YOKLAMA
     * 15 saniyede bir sunucuyu kontrol eder. Sekme arka plandaysa
     * (görünmüyorsa) boşuna istek atmaz.
     * ================================================================== */
    function yokla() {
        if (yoklamaSuruyor) return;
        if (document.hidden) return;                       // sekme arkada
        if (document.body.classList.contains('kart-acik')) return;  // kart zaten açık

        yoklamaSuruyor = true;

        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                islem: 'harita',
                aktif_gorev_id: window.v2AktifGorevId || 0
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (s) {
            var v = s.veri || s.data || s;
            if (!v || !v.harita) return;

            window.ilerlemeCubuguCiz(v.harita);
            cezaEfektiniUygula(v.ceza);

            if (v.bildirim) uyariyiGoster(v.bildirim);
        })
        .catch(function () { /* ağ koptu, bir sonraki turda tekrar dener */ })
        .finally(function () { yoklamaSuruyor = false; });
    }


    /* ====================================================================
     * BAŞLANGIÇ
     * ================================================================== */
    function baslat() {
        window.ilerlemeCubuguCiz(sonHarita);
        cezaEfektiniUygula(window.V2_CEZA);

        // Sayfa açılırken okunmamış bir uyarı varsa hemen göster
        if (ilkBildirim) {
            setTimeout(function () { uyariyiGoster(ilkBildirim); }, 400);
        }

        setInterval(yokla, 15000);

        // Sekmeye geri dönünce hemen kontrol et
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) yokla();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', baslat);
    } else {
        baslat();
    }

})();
