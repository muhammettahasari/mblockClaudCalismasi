/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/assets/js/admin/resim_blogu.js
 * KULLANICI / SAYFA: Süper Admin -> Görev Tasarlayıcı (4. Bölge: 💡 Bilgi Al)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Editor.js için özel bir "Resim" bloğu. Hazır eklentiden farkı: resmi
 * SOLA / SAĞA yaslayabiliyor ve GENİŞLİĞİNİ ayarlayabiliyorsun. Sola ya da
 * sağa yaslanan resmin yanındaki boşluğa yazı akıyor — gazete sayfası gibi.
 *
 * NEDEN HAZIR @editorjs/image KULLANILMADI?
 * O eklenti sadece üç ayar veriyor: kenarlık, arka plan, tam genişlik.
 * "Resmi küçült ve yazının soluna al" diye bir seçeneği yok. İhtiyaç tam
 * olarak buydu, o yüzden blok elle yazıldı.
 *
 * KAYDEDİLEN VERİ:
 *   { type: 'resim', data: { url, aciklama, hizalama, genislik } }
 *     hizalama : 'sol' | 'orta' | 'sag' | 'tam'
 *     genislik : yüzde (10–100). 'orta' ve 'tam' için de geçerli.
 *
 * ⚠️ ÖĞRENCİ TARAFIYLA ANLAŞMA:
 * Bu veriyi ekrana basan yer `assets/js/ogrenci/bilgi_al.js` içindeki
 * metniCiz(). Buradaki alan adları değişirse ORASI DA değişmeli, yoksa
 * resimler sessizce kaybolur.
 *
 * ⚠️ URL PROJE KÖKÜNE GÖRELİ SAKLANIR ('img/gorev_ipuclari/x.png').
 * Oynatıcı iki klasör içeride olduğu için başına '../../' ekliyor. Buraya
 * mutlak yol yazma.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu.
 * ============================================================================
 */

(function () {
    'use strict';

    var HIZALAMALAR = [
        { anahtar: 'sol',  etiket: '⬅️ Sola yasla',  ipucu: 'Resim solda, yazı sağına akar' },
        { anahtar: 'orta', etiket: '⏺️ Ortala',       ipucu: 'Resim ortada, yazı altında' },
        { anahtar: 'sag',  etiket: '➡️ Sağa yasla',   ipucu: 'Resim sağda, yazı soluna akar' },
        { anahtar: 'tam',  etiket: '↔️ Tam genişlik', ipucu: 'Satırın tamamını kaplar' }
    ];

    /* Editor'de blokların görünümü — öğrenci tarafındaki stilin eşi.
       Bir kez basılır. */
    function stilleriEkle() {
        if (document.getElementById('resimBloguStil')) { return; }
        var s = document.createElement('style');
        s.id = 'resimBloguStil';
        s.textContent = [
            '.rb-sarmal{margin:6px 0;}',
            '.rb-bos{border:2px dashed #c4b5fd;border-radius:12px;padding:26px;text-align:center;background:#faf5ff;}',
            '.rb-bos p{margin:0 0 14px;color:#6d28d9;font-size:14px;font-weight:600;}',
            '.rb-btn{background:#7c3aed;color:#fff;border:none;border-radius:8px;padding:9px 16px;',
            "  font-weight:700;font-size:13.5px;cursor:pointer;font-family:inherit;}",
            '.rb-btn:hover{background:#6d28d9;}',
            '.rb-btn.ikincil{background:#e2e8f0;color:#334155;}',
            '.rb-btn.ikincil:hover{background:#cbd5e1;}',
            '.rb-adres{width:100%;max-width:420px;padding:8px 10px;border:1.5px solid #cbd5e1;',
            '  border-radius:8px;font-size:13px;margin-top:10px;}',
            '.rb-gorsel{display:block;border-radius:10px;border:1px solid #e2e8f0;max-width:100%;}',
            '.rb-gorsel.sol{float:left;margin:4px 18px 10px 0;}',
            '.rb-gorsel.sag{float:right;margin:4px 0 10px 18px;}',
            '.rb-gorsel.orta{margin:8px auto;}',
            '.rb-gorsel.tam{width:100% !important;margin:8px 0;}',
            '.rb-arac{display:flex;flex-wrap:wrap;gap:8px;align-items:center;clear:both;',
            '  background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;margin-top:10px;}',
            '.rb-yas{border:1.5px solid #cbd5e1;background:#fff;color:#475569;border-radius:7px;',
            "  padding:6px 10px;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;}",
            '.rb-yas:hover{border-color:#a78bfa;color:#6d28d9;}',
            '.rb-yas.secili{background:#7c3aed;border-color:#7c3aed;color:#fff;}',
            '.rb-olcu{display:flex;align-items:center;gap:8px;margin-left:auto;font-size:12.5px;color:#475569;font-weight:700;}',
            '.rb-olcu input[type=range]{width:130px;}',
            '.rb-aciklama{width:100%;border:none;border-top:1px solid #e2e8f0;outline:none;',
            '  padding:8px 2px;font-size:13px;color:#64748b;font-family:inherit;margin-top:8px;}',
            '.rb-not{clear:both;font-size:12px;color:#94a3b8;margin-top:6px;}'
        ].join('\n');
        document.head.appendChild(s);
    }

    function ResimBlogu(ayar) {
        ayar = ayar || {};
        var d = ayar.data || {};

        this.readOnly = !!ayar.readOnly;
        this.veri = {
            url:       d.url || '',
            aciklama:  d.aciklama || d.caption || '',
            hizalama:  d.hizalama || 'orta',
            genislik:  parseInt(d.genislik, 10) || 100
        };
        this.sarmal = null;
        stilleriEkle();
    }

    ResimBlogu.toolbox = {
        title: 'Resim',
        icon: '<svg width="17" height="15" viewBox="0 0 24 22"><path d="M2 2h20v18H2z" fill="none" stroke="currentColor" stroke-width="2"/>'
            + '<circle cx="8" cy="8" r="2" fill="currentColor"/><path d="M3 18l6-7 4 4 3-3 5 6z" fill="currentColor"/></svg>'
    };
    ResimBlogu.isReadOnlySupported = true;

    ResimBlogu.prototype.render = function () {
        this.sarmal = document.createElement('div');
        this.sarmal.className = 'rb-sarmal';
        this.ciz();
        return this.sarmal;
    };

    ResimBlogu.prototype.save = function () {
        return this.veri;
    };

    /** İçeriği baştan çizer (resim yok / resim var iki durumu). */
    ResimBlogu.prototype.ciz = function () {
        var self = this;
        this.sarmal.innerHTML = '';

        if (!this.veri.url) { this.bosHaliCiz(); return; }

        /* --- Resim --- */
        var img = document.createElement('img');
        img.className = 'rb-gorsel ' + this.veri.hizalama;
        img.src = this.tamYol(this.veri.url);
        img.alt = '';
        if (this.veri.hizalama !== 'tam') {
            img.style.width = this.veri.genislik + '%';
        }
        this.sarmal.appendChild(img);

        if (this.readOnly) {
            if (this.veri.aciklama) {
                var alt = document.createElement('div');
                alt.className = 'rb-not';
                alt.textContent = this.veri.aciklama;
                this.sarmal.appendChild(alt);
            }
            return;
        }

        /* --- Araç çubuğu --- */
        var arac = document.createElement('div');
        arac.className = 'rb-arac';

        HIZALAMALAR.forEach(function (h) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'rb-yas' + (self.veri.hizalama === h.anahtar ? ' secili' : '');
            b.textContent = h.etiket;
            b.title = h.ipucu;
            b.addEventListener('click', function () {
                self.veri.hizalama = h.anahtar;
                self.ciz();
            });
            arac.appendChild(b);
        });

        var olcu = document.createElement('label');
        olcu.className = 'rb-olcu';
        var sayi = document.createElement('span');
        sayi.textContent = this.veri.genislik + '%';

        var kaydirac = document.createElement('input');
        kaydirac.type = 'range';
        kaydirac.min = 10;
        kaydirac.max = 100;
        kaydirac.step = 5;
        kaydirac.value = this.veri.genislik;
        kaydirac.disabled = (this.veri.hizalama === 'tam');
        kaydirac.addEventListener('input', function () {
            self.veri.genislik = parseInt(kaydirac.value, 10);
            sayi.textContent = self.veri.genislik + '%';
            img.style.width = self.veri.genislik + '%';   // anında önizleme
        });

        olcu.appendChild(document.createTextNode('Boyut'));
        olcu.appendChild(kaydirac);
        olcu.appendChild(sayi);
        arac.appendChild(olcu);

        var degistir = document.createElement('button');
        degistir.type = 'button';
        degistir.className = 'rb-btn ikincil';
        degistir.textContent = '🔁 Değiştir';
        degistir.addEventListener('click', function () { self.veri.url = ''; self.ciz(); });
        arac.appendChild(degistir);

        this.sarmal.appendChild(arac);

        /* --- Açıklama --- */
        var aciklama = document.createElement('input');
        aciklama.type = 'text';
        aciklama.className = 'rb-aciklama';
        aciklama.placeholder = 'Resim açıklaması (isteğe bağlı)';
        aciklama.value = this.veri.aciklama;
        aciklama.addEventListener('input', function () { self.veri.aciklama = aciklama.value; });
        this.sarmal.appendChild(aciklama);

        var not = document.createElement('div');
        not.className = 'rb-not';
        not.textContent = (this.veri.hizalama === 'sol' || this.veri.hizalama === 'sag')
            ? 'Bu resmin yanındaki boşluğa, ALTINDAKİ yazı blokları akar.'
            : 'Yazının resmin yanına akmasını istersen "Sola yasla" ya da "Sağa yasla" seç.';
        this.sarmal.appendChild(not);
    };

    /** Henüz resim seçilmemiş hali */
    ResimBlogu.prototype.bosHaliCiz = function () {
        var self = this;

        var kutu = document.createElement('div');
        kutu.className = 'rb-bos';
        kutu.innerHTML = '<p>🖼️ Bilgi alanına resim ekle</p>';

        var girdi = document.createElement('input');
        girdi.type = 'file';
        girdi.accept = 'image/*';
        girdi.style.display = 'none';
        girdi.addEventListener('change', function () {
            if (girdi.files && girdi.files[0]) { self.yukle(girdi.files[0], kutu); }
        });

        var sec = document.createElement('button');
        sec.type = 'button';
        sec.className = 'rb-btn';
        sec.textContent = '📁 Bilgisayardan Seç';
        sec.addEventListener('click', function () { girdi.click(); });

        var adres = document.createElement('input');
        adres.type = 'text';
        adres.className = 'rb-adres';
        adres.placeholder = 'ya da resim adresi yapıştır (img/… veya https://…)';
        adres.addEventListener('change', function () {
            var v = adres.value.trim();
            if (v) { self.veri.url = v; self.ciz(); }
        });

        kutu.appendChild(sec);
        kutu.appendChild(girdi);
        kutu.appendChild(adres);
        this.sarmal.appendChild(kutu);
    };

    /** Dosyayı sunucuya yükler. */
    ResimBlogu.prototype.yukle = function (dosya, kutu) {
        var self = this;
        kutu.innerHTML = '<p>⏳ Yükleniyor…</p>';

        var form = new FormData();
        form.append('image', dosya);

        fetch(window.V2_RESIM_YUKLE || '../api/resim_yukle.php', {
            method: 'POST',
            credentials: 'same-origin',   // oturum çerezi gitmezse "yetkisiz" döner
            body: form
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d && d.success === 1 && d.file && d.file.url) {
                self.veri.url = d.file.url;
                self.ciz();
                return;
            }
            kutu.innerHTML = '<p style="color:#b91c1c;">❌ ' +
                ((d && (d.message || d.mesaj)) || 'Yüklenemedi.') + '</p>';
            setTimeout(function () { self.ciz(); }, 2200);
        })
        .catch(function () {
            kutu.innerHTML = '<p style="color:#b91c1c;">❌ Sunucuya ulaşılamadı.</p>';
            setTimeout(function () { self.ciz(); }, 2200);
        });
    };

    /** Kayıtlı yol proje köküne göreli; tasarlayıcı da iki klasör içeride. */
    ResimBlogu.prototype.tamYol = function (url) {
        if (/^(https?:)?\/\//.test(url) || url.charAt(0) === '/') { return url; }
        return '../../' + url.replace(/^\.\.\/+/, '');
    };

    window.ResimBlogu = ResimBlogu;
})();
