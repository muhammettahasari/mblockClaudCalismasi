/* ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/assets/js/ogrenci/kutlama.js
 * KULLANICI / SAYFA: Öğrenci -> Görev Oynatıcı
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Öğrenci görevi doğru çözdüğünde ekranın ortasında konfeti patlatan
 * kutlama penceresini açar. Bölüm sonunda daha büyük bir kutlama gelir.
 *
 * ÖNEMLİ: OTOMATİK GEÇİŞ YOK.
 * Kutlama penceresi kapanınca sıradaki göreve KENDİLİĞİNDEN geçilmez.
 * Öğrenci "Sıradaki Göreve Geç" düğmesine basmalı ya da penceredeki
 * "Burada Kal" ile aynı görevde kalıp kodunu incelemeye devam etmeli.
 *
 * DIŞ BAĞIMLILIK YOK: konfeti saf canvas ile çizilir, CDN kütüphanesi
 * kullanılmaz (okul internetleri CDN'leri sık sık engelliyor).
 *
 * DIŞARI AÇILAN FONKSİYONLAR:
 *   window.kutlamaGoster(ayar)  — kutlama penceresini açar
 *   window.konfetiPatlat(sure)  — sadece konfeti
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu.
 * ========================================================================== */

(function () {
    'use strict';

    /* --------------------------------------------------------------------
     * 1. KONFETİ MOTORU (saf canvas)
     * ------------------------------------------------------------------ */
    var tuval = null;
    var ctx = null;
    var parcaciklar = [];
    var animasyonId = null;

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

    /** Belirtilen sayıda konfeti parçacığı üretir */
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

            p.vy += 0.35;          // yerçekimi
            p.vx *= 0.995;         // hava sürtünmesi
            p.x += p.vx;
            p.y += p.vy;
            p.aci += p.donme;
            p.omur -= 0.006;

            if (p.omur <= 0 || p.y > tuval.height + 60) {
                parcaciklar.splice(i, 1);
                continue;
            }

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

    /**
     * Konfeti patlat.
     * @param {number} guc  1 = normal görev, 2 = bölüm sonu (iki kat)
     */
    window.konfetiPatlat = function (guc) {
        guc = guc || 1;
        tuvaliHazirla();
        tuval.classList.add('acik');

        var merkezX = window.innerWidth / 2;
        var merkezY = window.innerHeight * 0.45;

        parcacikUret(70 * guc, merkezX, merkezY);

        // Yanlardan da birer salkım — bölüm sonunda daha şenlikli olsun
        if (guc >= 2) {
            setTimeout(function () { parcacikUret(50, window.innerWidth * 0.15, merkezY); }, 180);
            setTimeout(function () { parcacikUret(50, window.innerWidth * 0.85, merkezY); }, 340);
            setTimeout(function () { parcacikUret(60, merkezX, merkezY); }, 520);
        }

        if (!animasyonId) {
            animasyonId = requestAnimationFrame(cizimDongusu);
        }
    };


    /* --------------------------------------------------------------------
     * 2. KUTLAMA PENCERESİ
     * ------------------------------------------------------------------ */
    function perdeyiHazirla() {
        var perde = document.getElementById('kutlamaPerde');
        if (perde) return perde;

        perde = document.createElement('div');
        perde.id = 'kutlamaPerde';
        perde.className = 'kutlama-perde';
        perde.innerHTML = '<div class="kutlama-kart" id="kutlamaKart"></div>';
        document.body.appendChild(perde);

        // Perdeye tıklayınca kapansın (kart hariç)
        perde.addEventListener('click', function (e) {
            if (e.target === perde) window.kutlamaKapat();
        });

        return perde;
    }

    function kacir(metin) {
        var d = document.createElement('div');
        d.textContent = metin == null ? '' : String(metin);
        return d.innerHTML;
    }

    window.kutlamaKapat = function () {
        var perde = document.getElementById('kutlamaPerde');
        if (perde) perde.classList.remove('acik');
    };

    /**
     * Kutlama penceresini açar.
     *
     * @param {object} ayar
     *   tur          'gorev' | 'bolum' | 'tumu'
     *   baslik       Ana başlık
     *   altMetin     Açıklama satırı
     *   puan         Kazanılan puan (0 ise gösterilmez)
     *   elmas        Kazanılan elmas
     *   sonrakiId    Sıradaki görev id'si (yoksa buton çıkmaz)
     *   sonrakiAd    Sıradaki görevin adı
     *   ilkGorevMi   true ise "ilk görevini geçtin" vurgusu
     */
    window.kutlamaGoster = function (ayar) {
        ayar = ayar || {};
        var tur = ayar.tur || 'gorev';

        var perde = perdeyiHazirla();
        var kart = document.getElementById('kutlamaKart');

        var ikonlar = { gorev: '🎉', bolum: '🏆', tumu: '👑' };
        var ikon = ayar.ikon || ikonlar[tur] || '🎉';

        kart.className = 'kutlama-kart' + (tur !== 'gorev' ? ' bolum-sonu' : '');

        var odulHtml = '';
        if (ayar.puan > 0 || ayar.elmas > 0) {
            odulHtml = '<div class="kutlama-odul">';
            if (ayar.puan > 0) odulHtml += '<div>+' + (ayar.puan | 0) + ' Puan</div>';
            if (ayar.elmas > 0) odulHtml += '<div class="elmas">+' + (ayar.elmas | 0) + ' 💎</div>';
            odulHtml += '</div>';
        }

        var dugmeHtml = '<div class="kutlama-dugmeler">';
        if (ayar.sonrakiId) {
            dugmeHtml += '<button class="ku-kal" onclick="kutlamaKapat()">Burada Kal</button>';
            dugmeHtml += '<button class="ku-devam" onclick="kutlamaSonrakiGorev(' +
                (ayar.sonrakiId | 0) + ')">Sıradaki Görev →</button>';
        } else {
            dugmeHtml += '<button class="ku-devam" onclick="kutlamaKapat()">Harika!</button>';
        }
        dugmeHtml += '</div>';

        kart.innerHTML =
            '<div class="ku-ikon">' + ikon + '</div>' +
            '<h2>' + kacir(ayar.baslik || 'Tebrikler!') + '</h2>' +
            '<div class="ku-alt">' + kacir(ayar.altMetin || '') + '</div>' +
            odulHtml +
            (ayar.sonrakiAd
                ? '<div class="ku-alt" style="margin-bottom:14px;font-size:13px;">Sırada: <b>' +
                  kacir(ayar.sonrakiAd) + '</b></div>'
                : '') +
            dugmeHtml;

        perde.classList.add('acik');
        window.konfetiPatlat(tur === 'gorev' ? 1 : 2);
    };

    /* Sıradaki göreve MANUEL geçiş — öğrenci düğmeye bastığında çalışır */
    window.kutlamaSonrakiGorev = function (gorevId) {
        if (!gorevId) { window.kutlamaKapat(); return; }
        var taban = window.V2_OYNATICI_URL || 'oynatici.php';
        window.location.href = taban + '?gorev_id=' + (gorevId | 0);
    };

    /* ESC ile kapat */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') window.kutlamaKapat();
    });

})();
