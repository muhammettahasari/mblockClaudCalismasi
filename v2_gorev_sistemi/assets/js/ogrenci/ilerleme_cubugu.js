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
     * TEK BİR GÖREV DAİRESİ
     * Hem üst şeritte hem açılır haritada aynı görünüm kullanılsın diye
     * tek yerde üretiliyor.
     * ================================================================== */
    var DURUM_ETIKETI = {
        tamam:      ' ✅ tamamladın',
        aktif:      ' ⏳ şu an buradasın',
        sifirlandi: ' 🔴 öğretmenin tekrar yapmanı istedi',
        bekliyor:   ' ⚪ sırası gelmedi',
        kilitli:    ' 🔒 henüz ulaşmadın'
    };

    function daireYap(gorev, sira) {
        var d = document.createElement('button');
        d.type = 'button';
        d.className = 'ic-daire ' + gorev.durum + (gorev.burada ? ' burada' : '');
        d.title = (sira + 1) + '. ' + gorev.ad + (DURUM_ETIKETI[gorev.durum] || '');
        d.textContent = gorev.durum === 'kilitli' ? '🔒' : (sira + 1);

        if (gorev.durum === 'kilitli') {
            d.disabled = true;
        } else {
            d.addEventListener('click', function (e) {
                e.stopPropagation();
                goreveGit(gorev.id);
            });
        }
        return d;
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
                govde.appendChild(daireYap(gorev, sira));
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

        /* Açılır haritayı da tazele */
        haritayiCiz(harita, bolum ? bolum.id : 0);
    };


    /* ====================================================================
     * AÇILIR BÖLÜM HARİTASI
     * Şeritteki bölüm adına tıklanınca açılır; öğrenci TÜM bölümleri ve
     * nerede olduğunu görür. Dışarı tıklayınca ya da fare menüden çıkınca
     * kapanır.
     * ================================================================== */
    function haritayiCiz(harita, aktifBolumId) {
        var kutu = el('icHaritaGovde');
        if (!kutu || !harita) return;

        kutu.innerHTML = '';

        var bolumler = (harita.bolumler || []).filter(function (b) {
            return b.gorevler && b.gorevler.length > 0;
        });

        if (!bolumler.length) {
            kutu.innerHTML = '<div class="ic-h-bos">Henüz görev tanımlanmamış.</div>';
            return;
        }

        bolumler.forEach(function (b) {
            var satir = document.createElement('div');
            satir.className = 'ic-h-bolum'
                + (b.id === aktifBolumId ? ' burada' : '')
                + (b.bitti ? ' bitti' : '')
                + (b.acik ? '' : ' kilitli');

            var isaret = !b.acik ? '🔒 ' : (b.bitti ? '✅ ' : (b.id === aktifBolumId ? '📍 ' : ''));

            var ust = document.createElement('div');
            ust.className = 'ic-h-ust';

            var ad = document.createElement('span');
            ad.className = 'ic-h-ad';
            ad.textContent = isaret + b.ad;

            var oran = document.createElement('span');
            oran.className = 'ic-h-oran';
            oran.textContent = b.cozulen + '/' + b.toplam;

            ust.appendChild(ad);
            ust.appendChild(oran);
            satir.appendChild(ust);

            var daireler = document.createElement('div');
            daireler.className = 'ic-h-daireler';

            b.gorevler.forEach(function (g, sira) {
                daireler.appendChild(daireYap(g, sira));
            });

            satir.appendChild(daireler);
            kutu.appendChild(satir);
        });
    }

    function haritayiAc() {
        var menu = el('icHarita');
        var tetik = el('icHaritaTetik');
        if (!menu || !tetik) return;
        menu.classList.add('acik');
        tetik.setAttribute('aria-expanded', 'true');
    }

    function haritayiKapat() {
        var menu = el('icHarita');
        var tetik = el('icHaritaTetik');
        if (!menu || !tetik) return;
        menu.classList.remove('acik');
        tetik.setAttribute('aria-expanded', 'false');
    }

    function haritaKurulumu() {
        var menu  = el('icHarita');
        var tetik = el('icHaritaTetik');
        var serit = el('ilerlemeCubugu');
        if (!menu || !tetik || !serit) return;

        /* [2026-08] ŞERİDİN TAMAMI TIKLANABİLİR.
           Sadece bölüm yazısı değil; boş alan, yüzde çubuğu, oran yazısı —
           şeritte nereye tıklanırsa harita açılır/kapanır.
           TEK İSTİSNA: görev daireleri. Onlar öğrenciyi göreve götürür.
           (daireYap() içinde stopPropagation var, buraya hiç ulaşmazlar.) */
        serit.addEventListener('click', function (e) {
            // Açılır menünün İÇİNE tıklandıysa menüyü kapatma
            if (menu.contains(e.target)) return;

            e.stopPropagation();
            if (menu.classList.contains('acik')) haritayiKapat();
            else haritayiAc();
        });

        /* Fare şeridin + menünün dışına çıkınca kendiliğinden toparlansın.
           Küçük bir gecikme veriyoruz ki başlıktan menüye giderken
           aradaki 1-2 pikselde kapanmasın. */
        var kapatmaZamani = null;

        function gecikmeliKapat() {
            clearTimeout(kapatmaZamani);
            kapatmaZamani = setTimeout(haritayiKapat, 320);
        }
        function kapatmayiIptalEt() {
            clearTimeout(kapatmaZamani);
        }

        serit.addEventListener('mouseleave', gecikmeliKapat);
        serit.addEventListener('mouseenter', kapatmayiIptalEt);
        menu.addEventListener('mouseenter', kapatmayiIptalEt);
        menu.addEventListener('mouseleave', gecikmeliKapat);

        /* Sayfanın herhangi bir yerine tıklayınca kapansın */
        document.addEventListener('click', function (e) {
            if (!menu.classList.contains('acik')) return;
            if (menu.contains(e.target) || tetik.contains(e.target)) return;
            haritayiKapat();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') haritayiKapat();
        });
    }


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
        .catch(function () { return null; })
        .then(function (v) { return v || null; });   // her koşulda bir değer dön
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
    function uyariyiGoster(bildirim, sonrakiGorevId) {
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
            /* Kapatınca doğrudan tekrar edilecek göreve gidilir.
               Kimliği sunucu söylüyor; tarayıcı tahmin etmiyor. */
            gorevId: sonrakiGorevId | 0,
            yenile: true
        });
    }


    /* ====================================================================
     * CANLI YOKLAMA
     *
     * 10 saniyede bir sunucuyu kontrol eder. Frenler:
     *   - Sekme arka plandaysa hiç istek atılmaz
     *   - Kart açıkken durur (öğrencinin okuduğu şeyi bozmasın)
     *   - Üst üste istek gitmez
     *   - Arka arkaya 3 hata olursa aralık üç katına çıkar (sunucu rahatlasın)
     * ================================================================== */
    var hataSayisi  = 0;
    var zamanlayici = null;
    var ARALIK      = 10000;

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

            hataSayisi = 0;

            window.ilerlemeCubuguCiz(v.harita);
            cezaEfektiniUygula(v.ceza);

            if (v.bildirim) uyariyiGoster(v.bildirim, v.sonraki_gorev_id);
        })
        .catch(function () {
            // Ağ koptu. Arka arkaya hata varsa sunucuyu rahat bırak.
            hataSayisi++;
            if (hataSayisi === 3 && zamanlayici) {
                clearInterval(zamanlayici);
                zamanlayici = setInterval(yokla, ARALIK * 3);
            }
        })
        .finally(function () { yoklamaSuruyor = false; });
    }


    /* ====================================================================
     * BAŞLANGIÇ
     * ================================================================== */
    function baslat() {
        window.ilerlemeCubuguCiz(sonHarita);
        cezaEfektiniUygula(window.V2_CEZA);
        haritaKurulumu();

        // Sayfa açılırken okunmamış bir uyarı varsa hemen göster.
        // Hedef görevi sunucudan öğrenmek için önce haritayı tazeliyoruz.
        if (ilkBildirim) {
            window.ilerlemeyiTazele().then(function (v) {
                uyariyiGoster(ilkBildirim, v ? v.sonraki_gorev_id : 0);
            });
        }

        zamanlayici = setInterval(yokla, ARALIK);

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
