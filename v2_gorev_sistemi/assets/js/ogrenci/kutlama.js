/* ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/assets/js/ogrenci/kutlama.js
 * KULLANICI / SAYFA: Öğrenci -> Görev Oynatıcı
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Ekranın ortasında açılan iki küçük pencereyi yönetir:
 *   1. KUTLAMA KARTI  — renkli, süslü, konfeti patlar. Görev/bölüm bitince.
 *   2. UYARI KARTI    — kırmızı. Öğretmen bir bölümü tekrar ettirdiğinde.
 *
 * ORTAK DAVRANIŞ:
 *   - Kart açıkken arka plan (şerit + çalışma alanı) BULANIKLAŞIR.
 *   - Kartta tek düğme vardır: "Kapat".
 *   - Kapatınca sayfa YENİLENİR. Böylece öğrenci otomatik olarak sıradaki
 *     göreve (ceza aldıysa tekrar etmesi gereken göreve) geçer.
 *
 * NEDEN "SAYFAYI YENİLE"?
 * Görev değişince Blockly çalışma alanı, kuklalar, sahne ve cevap anahtarı
 * baştan kurulmalı. Bunları tek tek JS ile değiştirmek yerine sayfayı
 * yenilemek hem çok daha güvenli hem de kod olarak sade.
 *
 * DIŞ BAĞIMLILIK YOK: konfeti saf canvas ile çizilir (okul ağları CDN'leri
 * sık sık engelliyor).
 *
 * DIŞARI AÇILAN FONKSİYONLAR:
 *   window.kutlamaGoster(ayar)   — renkli kutlama kartı
 *   window.uyariKartiGoster(ayar)— kırmızı uyarı kartı
 *   window.kartiKapat()          — kartı kapatır (ve gerekirse yeniler)
 *   window.konfetiPatlat(guc)    — sadece konfeti
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu.
 *  - [2026-08] "Sıradaki Görev" düğmesi kaldırıldı; tek "Kapat" + yenileme.
 *  - [2026-08] Kırmızı uyarı kartı ve arka plan bulanıklığı eklendi.
 * ========================================================================== */

(function () {
    'use strict';

    /* ====================================================================
     * 1. KONFETİ MOTORU (saf canvas)
     * ================================================================== */
    var tuval = null, ctx = null, parcaciklar = [], animasyonId = null;

    var RENKLER = ['#10b981', '#f59e0b', '#7c3aed', '#3b82f6', '#ef4444', '#ec4899', '#facc15'];

    function tuvaliHazirla() {
        if (tuval) return;
        tuval = document.createElement('canvas');
        tuval.id = 'konfetiTuval';
        document.body.appendChild(tuval);
        ctx = tuval.getContext('2d');
        boyutlandir();
        window.addEventListener('resize', boyutlandir);
    }

    function boyutlandir() {
        if (!tuval) return;
        tuval.width = window.innerWidth;
        tuval.height = window.innerHeight;
    }

    function parcacikUret(adet, kaynakX, kaynakY) {
        for (var i = 0; i < adet; i++) {
            parcaciklar.push({
                x: kaynakX + (Math.random() - 0.5) * 220,
                y: kaynakY + (Math.random() - 0.5) * 60,
                vx: (Math.random() - 0.5) * 11,
                vy: Math.random() * -13 - 4,
                en: 6 + Math.random() * 7,
                boy: 9 + Math.random() * 9,
                renk: RENKLER[(Math.random() * RENKLER.length) | 0],
                aci: Math.random() * Math.PI * 2,
                donme: (Math.random() - 0.5) * 0.28,
                omur: 1
            });
        }
    }

    function cizimDongusu() {
        if (!ctx) return;
        ctx.clearRect(0, 0, tuval.width, tuval.height);

        for (var i = parcaciklar.length - 1; i >= 0; i--) {
            var p = parcaciklar[i];
            p.vy += 0.35;    // yerçekimi
            p.vx *= 0.995;   // hava sürtünmesi
            p.x += p.vx; p.y += p.vy;
            p.aci += p.donme;
            p.omur -= 0.006;

            if (p.omur <= 0 || p.y > tuval.height + 60) { parcaciklar.splice(i, 1); continue; }

            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.aci);
            ctx.globalAlpha = Math.max(0, Math.min(1, p.omur));
            ctx.fillStyle = p.renk;
            ctx.fillRect(-p.en / 2, -p.boy / 2, p.en, p.boy);
            ctx.restore();
        }

        if (parcaciklar.length > 0) {
            animasyonId = requestAnimationFrame(cizimDongusu);
        } else {
            animasyonId = null;
            tuval.classList.remove('acik');
        }
    }

    /** @param {number} guc 1 = normal görev, 2 = bölüm sonu (daha şenlikli) */
    window.konfetiPatlat = function (guc) {
        guc = guc || 1;
        tuvaliHazirla();
        tuval.classList.add('acik');

        var mx = window.innerWidth / 2, my = window.innerHeight * 0.42;
        parcacikUret(70 * guc, mx, my);

        if (guc >= 2) {
            setTimeout(function () { parcacikUret(50, window.innerWidth * 0.15, my); }, 180);
            setTimeout(function () { parcacikUret(50, window.innerWidth * 0.85, my); }, 340);
            setTimeout(function () { parcacikUret(60, mx, my); }, 520);
        }

        if (!animasyonId) animasyonId = requestAnimationFrame(cizimDongusu);
    };


    /* ====================================================================
     * 2. KART PENCERESİ (ortak iskelet)
     * ================================================================== */
    var kapatincaYenile = false;   // kart kapanınca sayfa yenilensin mi?

    function perdeyiHazirla() {
        var perde = document.getElementById('kartPerde');
        if (perde) return perde;

        perde = document.createElement('div');
        perde.id = 'kartPerde';
        perde.className = 'kart-perde';
        perde.innerHTML = '<div class="kart" id="kartGovde"></div>';
        document.body.appendChild(perde);
        return perde;
    }

    function kacir(metin) {
        var d = document.createElement('div');
        d.textContent = metin == null ? '' : String(metin);
        return d.innerHTML;
    }

    /**
     * Kartı kapatır.
     * kapatincaYenile true ise sayfa yenilenir → öğrenci sıradaki göreve geçer.
     */
    window.kartiKapat = function () {
        var perde = document.getElementById('kartPerde');
        if (perde) perde.classList.remove('acik');
        document.body.classList.remove('kart-acik');

        if (kapatincaYenile) {
            kapatincaYenile = false;
            // Adres çubuğundaki ?gorev_id=... temizlenir ki sunucu
            // "sıradaki çözülmemiş görev"i kendisi seçebilsin.
            window.location.href = window.V2_OYNATICI_URL || 'oynatici.php';
        }
    };

    /** Kart iskeletini ekrana basar */
    function kartiAc(sinif, ivHtml, yenile) {
        kapatincaYenile = !!yenile;

        var perde = perdeyiHazirla();
        var govde = document.getElementById('kartGovde');

        govde.className = 'kart ' + sinif;
        govde.innerHTML = ivHtml;

        perde.classList.add('acik');
        document.body.classList.add('kart-acik');
    }


    /* ====================================================================
     * 3. KUTLAMA KARTI (renkli, süslü)
     *
     * @param {object} ayar
     *   tur       'gorev' | 'bolum' | 'tumu'
     *   ikon      Emoji (verilmezse türe göre seçilir)
     *   baslik    Ana başlık
     *   altMetin  Açıklama
     *   puan      Kazanılan puan (0 ise gösterilmez)
     *   elmas     Kazanılan elmas
     *   yenile    Kapatınca sayfa yenilensin mi (varsayılan: evet)
     * ================================================================== */
    window.kutlamaGoster = function (ayar) {
        ayar = ayar || {};
        var tur = ayar.tur || 'gorev';
        var ikonlar = { gorev: '🎉', bolum: '🏆', tumu: '👑' };
        var ikon = ayar.ikon || ikonlar[tur] || '🎉';

        var odul = '';
        if (ayar.puan > 0 || ayar.elmas > 0) {
            odul = '<div class="k-odul">';
            if (ayar.puan > 0)  odul += '<div>+' + (ayar.puan | 0) + ' Puan</div>';
            if (ayar.elmas > 0) odul += '<div class="elmas">+' + (ayar.elmas | 0) + ' 💎</div>';
            odul += '</div>';
        }

        kartiAc(
            'kutlama' + (tur !== 'gorev' ? ' buyuk' : ''),
            '<div class="k-ikon">' + ikon + '</div>' +
            '<h2>' + kacir(ayar.baslik || 'Tebrikler!') + '</h2>' +
            '<div class="k-alt">' + kacir(ayar.altMetin || '') + '</div>' +
            odul +
            '<button class="k-kapat" onclick="kartiKapat()">Kapat</button>',
            ayar.yenile !== false
        );

        window.konfetiPatlat(tur === 'gorev' ? 1 : 2);
    };


    /* ====================================================================
     * 4. UYARI KARTI (kırmızı)
     *
     * @param {object} ayar
     *   baslik       Ana başlık
     *   altMetin     Açıklama
     *   gorevSayisi  Tekrar edilecek görev sayısı
     *   elmasCezasi  Geri alınan elmas
     *   sebep        Öğretmenin yazdığı gerekçe
     *   yenile       Kapatınca sayfa yenilensin mi (varsayılan: evet)
     * ================================================================== */
    window.uyariKartiGoster = function (ayar) {
        ayar = ayar || {};

        var satir = '';
        if (ayar.gorevSayisi > 0 || ayar.elmasCezasi > 0) {
            satir = '<div class="k-ceza-satir">';
            if (ayar.gorevSayisi > 0) satir += '<div>' + (ayar.gorevSayisi | 0) + ' görev tekrar</div>';
            if (ayar.elmasCezasi > 0) satir += '<div>-' + (ayar.elmasCezasi | 0) + ' 💎</div>';
            satir += '</div>';
        }

        var sebep = ayar.sebep
            ? '<div class="k-sebep">“' + kacir(ayar.sebep) + '”</div>'
            : '';

        kartiAc(
            'uyari',
            '<div class="k-ikon">' + (ayar.ikon || '⚠️') + '</div>' +
            '<h2>' + kacir(ayar.baslik || 'Öğretmeninden Mesaj Var') + '</h2>' +
            '<div class="k-alt">' + kacir(ayar.altMetin || '') + '</div>' +
            satir + sebep +
            '<button class="k-kapat" onclick="kartiKapat()">Kapat</button>',
            ayar.yenile !== false
        );
    };


    /* ESC ile kapat (kart açıksa) */
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var perde = document.getElementById('kartPerde');
        if (perde && perde.classList.contains('acik')) window.kartiKapat();
    });

})();
