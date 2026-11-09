/**
 * ============================================================================
 * Dosya Yolu: v2_gorev_sistemi/assets/js/ogrenci/sahne_motoru.js
 * Kullanıcı: Öğrenci -> Görev Oynatıcı (ve Süper Admin önizlemesi)
 *
 * HALK DİLİNDE İŞLEVİ:
 * mBlock / Scratch sahnesinin aynısı. Kuklaları çizer, blok kodlarını
 * çalıştırır, tuş / fare / tıklama olaylarını dinler, ses çalar, kalemle
 * çizer, değişkenleri gösterir.
 *
 * KOORDİNAT SİSTEMİ (mBlock ile birebir):
 *   Sahne 480 x 360. Orta nokta (0,0). x sağa doğru artar (-240 .. 240),
 *   y YUKARI doğru artar (-180 .. 180). Yön: 90 = sağ, 0 = yukarı,
 *   -90 = sol, 180 = aşağı.
 *
 * TEMEL İŞLEVLER:
 *  1. derle()       — her kuklanın bloklarını ayrı ayrı JavaScript'e çevirir
 *  2. bayrak()      — sahneyi başlangıç hâline getirir, yeşil bayrak betiklerini başlatır
 *  3. durdur()      — çalışan bütün betikleri bir sonraki beklemede keser
 *  4. ciz()         — saniyede 60 kez sahneyi DOM'a yansıtır
 *  5. Baglam (S)    — blokların çağırdığı komutlar: S.git(), S.de(), S.kalemBastir()...
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-11] Dosya oluşturuldu. Eski oyun_sahnesi.js motoru tek bir
 *    kuklayı sol üst köşe pikseliyle oynatıyordu; diğer kuklaların kodu hiç
 *    çalışmıyordu ve 37 bloğun karşılığı yoktu.
 *  - Görevin kendi dekoru ve kuklaları (icerik_json.sahne) yükleniyor.
 *  - Yeşil bayrak sahneyi her seferinde başlangıç hâline getirir; böylece
 *    çocuk kodunu defalarca deneyebilir, kukla kaybolup gitmez.
 * ============================================================================
 */
(function () {
    'use strict';

    const GEN = 480, YUK = 360;
    const RENK_HEX = { RED: '#ef4444', BLUE: '#3b82f6', GREEN: '#22c55e', YELLOW: '#facc15', ORANGE: '#f97316',
                       PURPLE: '#a855f7', PINK: '#ec4899', BLACK: '#111827', WHITE: '#ffffff' };

    // ------------------------------------------------------------------
    // ORTAK DURUM
    // ------------------------------------------------------------------
    const D = {
        kosu: 1,                 // her "durdur"da artar; eski betikler kendini öldürür
        v: {},                   // değişkenler
        cevap: '',
        zamanBas: performance.now(),
        aktifBetik: 0,
        dekorlar: [],            // [{adi, yol}]
        dekorIndex: 0,
        sira: [],                // katman sırası (kukla id'leri, sondaki en önde)
        fare: { x: 0, y: 0, basili: false },
        tuslar: {},
        al: function (ad) { return Object.prototype.hasOwnProperty.call(D.v, ad) ? D.v[ad] : 0; },
        ata: function (ad, deger) { D.v[ad] = deger; MBM.monitorKirli = true; }
    };

    class DurdurSinyali extends Error { constructor() { super('durduruldu'); this.durdur = true; } }

    const MBM = {
        D: D,
        hazir: false,
        sahne: null, dekorEl: null, kalemCanvas: null, kalemCtx: null, katman: null, monitorEl: null, sorEl: null,
        isleyiciler: [],         // [{kuklaId, tip, param, fn, aktif: Baglam|null}]
        anlik: null,             // son elle düzenlenen durum (bayrakta geri yüklenir)
        gorevBaslangic: null,    // görevin orijinal durumu (↺ Başa Dön)
        resimler: {},            // yol -> {img, w, h, yuklendi}
        dekorPiksel: {},         // yol -> ImageData (renk algılama)
        monitorKirli: true,
        sonHata: null
    };
    window.MBM = MBM;

    // ------------------------------------------------------------------
    // YARDIMCILAR
    // ------------------------------------------------------------------
    const sayi = x => { const s = Number(x); return isFinite(s) ? s : 0; };
    const kuklalar = () => Array.isArray(window.projeKuklalari) ? window.projeKuklalari : [];
    const kuklaBul = id => kuklalar().find(k => k.id === id);
    const kuklaAdla = ad => kuklalar().find(k => k.adi === ad);
    const yonNormal = d => { d = sayi(d) % 360; if (d > 180) d -= 360; if (d <= -180) d += 360; return d; };
    const uyu = ms => new Promise(r => setTimeout(r, ms));

    function kostum(k) {
        const liste = Array.isArray(k.kostumler) && k.kostumler.length ? k.kostumler : [{ adi: 'kostüm1', resimYolu: k.resimYolu }];
        let i = Math.max(0, Math.min(liste.length - 1, k.kostumIndex | 0));
        return { liste, i, aktif: liste[i] };
    }
    function resim(yol) {
        if (!yol) return null;
        let r = MBM.resimler[yol];
        if (!r) {
            r = { img: new Image(), w: 60, h: 60, yuklendi: false };
            r.img.onload = () => {
                r.w = r.img.naturalWidth || 60; r.h = r.img.naturalHeight || 60;
                // SVG'de genişlik yazmıyorsa tarayıcı 150 / 300 verir; makul sınıra çek
                if (r.w > 480 || r.h > 360) { const o = Math.min(480 / r.w, 360 / r.h); r.w *= o; r.h *= o; }
                r.yuklendi = true;
            };
            r.img.src = yol;
            MBM.resimler[yol] = r;
        }
        return r;
    }
    function boyutlar(k) {
        const r = resim(kostum(k).aktif.resimYolu);
        const o = sayi(k.boyut || 100) / 100;
        return { w: (r ? r.w : 60) * o, h: (r ? r.h : 60) * o };
    }
    function sinirlar(k) {
        const b = boyutlar(k);
        return { sol: k.x - b.w / 2, sag: k.x + b.w / 2, ust: k.y + b.h / 2, alt: k.y - b.h / 2, w: b.w, h: b.h };
    }
    /** Kukla sahneden tamamen kaçmasın (mBlock'taki "çit") */
    function citeSok(k) {
        const b = boyutlar(k);
        const payX = Math.min(15, b.w / 2), payY = Math.min(15, b.h / 2);
        const minX = -GEN / 2 - b.w / 2 + payX, maxX = GEN / 2 + b.w / 2 - payX;
        const minY = -YUK / 2 - b.h / 2 + payY, maxY = YUK / 2 + b.h / 2 - payY;
        k.x = Math.max(minX, Math.min(maxX, k.x));
        k.y = Math.max(minY, Math.min(maxY, k.y));
    }
    function konumAyarla(k, x, y) {
        const eskiX = k.x, eskiY = k.y;
        // 4.9e-15 gibi kayan nokta kırıntıları panelde/karşılaştırmada çocuğu şaşırtmasın
        k.x = Math.round(sayi(x) * 1e6) / 1e6; k.y = Math.round(sayi(y) * 1e6) / 1e6;
        citeSok(k);
        if (k.kalemBasili) kalemCizgi(k, eskiX, eskiY, k.x, k.y);
    }

    // ------------------------------------------------------------------
    // KALEM KATMANI
    // ------------------------------------------------------------------
    const KALEM_OLCEK = 2; // keskin çizgi için 2 kat çözünürlük
    function kalemCizgi(k, x1, y1, x2, y2) {
        const c = MBM.kalemCtx; if (!c) return;
        c.strokeStyle = k.kalemRenk || RENK_HEX.BLUE;
        c.lineWidth = sayi(k.kalemKalinlik || 3) * KALEM_OLCEK;
        c.lineCap = 'round'; c.lineJoin = 'round';
        c.beginPath();
        c.moveTo((x1 + GEN / 2) * KALEM_OLCEK, (YUK / 2 - y1) * KALEM_OLCEK);
        c.lineTo((x2 + GEN / 2) * KALEM_OLCEK, (YUK / 2 - y2) * KALEM_OLCEK);
        c.stroke();
    }
    function kalemTemizle() {
        if (MBM.kalemCtx) MBM.kalemCtx.clearRect(0, 0, GEN * KALEM_OLCEK, YUK * KALEM_OLCEK);
    }

    // ------------------------------------------------------------------
    // SES (Web Audio ile üretilen sesler — dosya indirmeye gerek yok)
    // ------------------------------------------------------------------
    let ses = null; const calanlar = new Set();
    function sesBaglami() {
        if (!ses) { try { ses = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { ses = null; } }
        if (ses && ses.state === 'suspended') ses.resume();
        return ses;
    }
    function ton(frek, bas, sure, tip, ses1, frek2) {
        const a = sesBaglami(); if (!a) return;
        const o = a.createOscillator(), g = a.createGain();
        o.type = tip || 'sine';
        o.frequency.setValueAtTime(frek, a.currentTime + bas);
        if (frek2) o.frequency.exponentialRampToValueAtTime(frek2, a.currentTime + bas + sure);
        g.gain.setValueAtTime(0.0001, a.currentTime + bas);
        g.gain.exponentialRampToValueAtTime(ses1 || 0.25, a.currentTime + bas + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, a.currentTime + bas + sure);
        o.connect(g); g.connect(a.destination);
        o.start(a.currentTime + bas); o.stop(a.currentTime + bas + sure + 0.05);
        calanlar.add(o); o.onended = () => calanlar.delete(o);
    }
    function gurultu(bas, sure, ses1) {
        const a = sesBaglami(); if (!a) return;
        const tampon = a.createBuffer(1, Math.max(1, a.sampleRate * sure), a.sampleRate);
        const d = tampon.getChannelData(0);
        for (let i = 0; i < d.length; i++) d[i] = (Math.random() * 2 - 1) * (1 - i / d.length);
        const s = a.createBufferSource(), g = a.createGain();
        s.buffer = tampon; g.gain.value = ses1 || 0.3;
        s.connect(g); g.connect(a.destination); s.start(a.currentTime + bas);
        calanlar.add(s); s.onended = () => calanlar.delete(s);
    }
    /** Sesi çalar, saniye cinsinden süresini döndürür */
    function sesCal(ad) {
        switch (ad) {
            case 'MEOW': ton(700, 0, 0.18, 'sawtooth', 0.12, 900); ton(900, 0.18, 0.35, 'sawtooth', 0.12, 500); return 0.55;
            case 'LASER': ton(1800, 0, 0.35, 'square', 0.12, 200); return 0.35;
            case 'JUMP': ton(250, 0, 0.25, 'square', 0.12, 900); return 0.25;
            case 'BEEP': ton(880, 0, 0.2, 'sine', 0.3); return 0.2;
            case 'CLAP': gurultu(0, 0.12, 0.4); gurultu(0.15, 0.12, 0.35); return 0.3;
            case 'DRUM': ton(150, 0, 0.3, 'sine', 0.6, 50); return 0.3;
            case 'MAGIC': [523, 659, 784, 1046].forEach((f, i) => ton(f, i * 0.09, 0.25, 'triangle', 0.2)); return 0.55;
            case 'POP': ton(500, 0, 0.08, 'sine', 0.4, 120); gurultu(0, 0.05, 0.2); return 0.12;
            case 'WIN': [523, 659, 784, 1046, 784, 1046].forEach((f, i) => ton(f, i * 0.12, 0.2, 'square', 0.1)); return 0.85;
            default: ton(660, 0, 0.2, 'sine', 0.25); return 0.2;
        }
    }
    function sesleriDurdur() { calanlar.forEach(n => { try { n.stop(); } catch (e) {} }); calanlar.clear(); }

    // ------------------------------------------------------------------
    // RENK ALGILAMA
    // ------------------------------------------------------------------
    function renkSinifi(r, g, b) {
        const mx = Math.max(r, g, b), mn = Math.min(r, g, b), d = mx - mn;
        const v = mx / 255, s = mx === 0 ? 0 : d / mx;
        if (v < 0.22) return 'BLACK';
        if (s < 0.18 && v > 0.85) return 'WHITE';
        // Soluk/pastel renkler (açık gökyüzü, gri zemin) hiçbir renk sınıfına girmez
        if (s < 0.45 || v < 0.35) return 'SOLUK';
        let h = 0;
        if (d !== 0) {
            if (mx === r) h = ((g - b) / d) % 6; else if (mx === g) h = (b - r) / d + 2; else h = (r - g) / d + 4;
            h *= 60; if (h < 0) h += 360;
        }
        if (h < 14 || h >= 345) return 'RED';
        if (h < 40) return 'ORANGE';
        if (h < 70) return 'YELLOW';
        if (h < 170) return 'GREEN';
        if (h < 255) return 'BLUE';
        if (h < 290) return 'PURPLE';
        return 'PINK';
    }
    function dekorPikselleri() {
        const dk = D.dekorlar[D.dekorIndex];
        if (!dk) return null;
        if (MBM.dekorPiksel[dk.yol] !== undefined) return MBM.dekorPiksel[dk.yol];
        MBM.dekorPiksel[dk.yol] = null; // yükleniyor
        const img = new Image();
        img.onload = () => {
            try {
                const c = document.createElement('canvas'); c.width = GEN; c.height = YUK;
                const x = c.getContext('2d'); x.drawImage(img, 0, 0, GEN, YUK);
                MBM.dekorPiksel[dk.yol] = x.getImageData(0, 0, GEN, YUK);
            } catch (e) { MBM.dekorPiksel[dk.yol] = null; }
        };
        img.src = dk.yol;
        return null;
    }
    function renkDegiyorMu(k, renk) {
        if (!k.goster) return false;
        const s = sinirlar(k);
        const pd = dekorPikselleri();
        let kalemVeri = null;
        try { if (MBM.kalemCtx) kalemVeri = MBM.kalemCtx.getImageData(0, 0, GEN * KALEM_OLCEK, YUK * KALEM_OLCEK); } catch (e) {}
        // Kuklanın kutusunu %25 içeriden tara (köşeler genelde boş)
        const kx = s.w * 0.25, ky = s.h * 0.25;
        for (let i = 0; i <= 8; i++) {
            for (let j = 0; j <= 8; j++) {
                const x = s.sol + kx + (s.w - 2 * kx) * i / 8, y = s.alt + ky + (s.h - 2 * ky) * j / 8;
                const px = Math.round(x + GEN / 2), py = Math.round(YUK / 2 - y);
                if (px < 0 || py < 0 || px >= GEN || py >= YUK) continue;
                if (kalemVeri) {
                    const q = ((py * KALEM_OLCEK) * GEN * KALEM_OLCEK + px * KALEM_OLCEK) * 4;
                    if (kalemVeri.data[q + 3] > 100 && renkSinifi(kalemVeri.data[q], kalemVeri.data[q + 1], kalemVeri.data[q + 2]) === renk) return true;
                }
                if (pd) {
                    const q = (py * GEN + px) * 4;
                    if (renkSinifi(pd.data[q], pd.data[q + 1], pd.data[q + 2]) === renk) return true;
                }
            }
        }
        return false;
    }

    // ------------------------------------------------------------------
    // BAĞLAM (S) — blokların çağırdığı komutlar
    // ------------------------------------------------------------------
    class Baglam {
        constructor(k) { this.k = k; this.kosu = D.kosu; this.iptal = false; }
        canli() { if (this.iptal || this.kosu !== D.kosu) throw new DurdurSinyali(); }
        n(x) { return sayi(x); }
        olay() { /* yalnız derleme anında anlamlı */ }

        // --- zaman ---
        async kare() { this.canli(); await kareBekle(); this.canli(); }
        async bekle(sn) {
            const bitis = performance.now() + Math.max(0, sayi(sn)) * 1000;
            do { await kareBekle(); this.canli(); } while (performance.now() < bitis);
        }

        // --- hareket ---
        git(adim) { const r = (90 - sayi(this.k.yon)) * Math.PI / 180; konumAyarla(this.k, this.k.x + adim * Math.cos(r), this.k.y + adim * Math.sin(r)); }
        don(d) { this.k.yon = yonNormal(sayi(this.k.yon) + d); }
        yonYap(d) { this.k.yon = yonNormal(d); }
        xyGit(x, y) { konumAyarla(this.k, x, y); }
        xDegistir(d) { konumAyarla(this.k, this.k.x + d, this.k.y); }
        yDegistir(d) { konumAyarla(this.k, this.k.x, this.k.y + d); }
        xYap(x) { konumAyarla(this.k, x, this.k.y); }
        yYap(y) { konumAyarla(this.k, this.k.x, y); }
        hedefNokta(h) {
            if (h === 'MOUSE') return { x: D.fare.x, y: D.fare.y };
            if (h === 'RANDOM') return { x: Math.round(Math.random() * GEN - GEN / 2), y: Math.round(Math.random() * YUK - YUK / 2) };
            const t = kuklaAdla(h); return t ? { x: t.x, y: t.y } : { x: this.k.x, y: this.k.y };
        }
        hedefeGit(h) { const p = this.hedefNokta(h); konumAyarla(this.k, p.x, p.y); }
        async suzul(sn, x, y) {
            const bx = this.k.x, by = this.k.y, sure = Math.max(0, sayi(sn)) * 1000, bas = performance.now();
            if (sure <= 0) { konumAyarla(this.k, x, y); return; }
            while (true) {
                await kareBekle(); this.canli();
                const o = Math.min(1, (performance.now() - bas) / sure);
                konumAyarla(this.k, bx + (x - bx) * o, by + (y - by) * o);
                if (o >= 1) break;
            }
        }
        async hedefeSuzul(sn, h) { const p = this.hedefNokta(h); await this.suzul(sn, p.x, p.y); }
        hedefeDon(h) {
            const p = this.hedefNokta(h); const dx = p.x - this.k.x, dy = p.y - this.k.y;
            if (dx === 0 && dy === 0) return;
            this.k.yon = yonNormal(90 - Math.atan2(dy, dx) * 180 / Math.PI);
        }
        kenardaSek() {
            const k = this.k, s = sinirlar(k);
            const uz = { sol: s.sol + GEN / 2, sag: GEN / 2 - s.sag, ust: YUK / 2 - s.ust, alt: s.alt + YUK / 2 };
            let en = null, min = 0;
            for (const kenar in uz) if (uz[kenar] < min) { min = uz[kenar]; en = kenar; }
            if (!en) return;
            const r = (90 - sayi(k.yon)) * Math.PI / 180;
            let dx = Math.cos(r), dy = Math.sin(r);
            if (en === 'sol') dx = Math.max(0.2, Math.abs(dx));
            if (en === 'sag') dx = -Math.max(0.2, Math.abs(dx));
            if (en === 'ust') dy = -Math.max(0.2, Math.abs(dy));
            if (en === 'alt') dy = Math.max(0.2, Math.abs(dy));
            k.yon = yonNormal(90 - Math.atan2(dy, dx) * 180 / Math.PI);
            // İçeri al
            if (s.sol < -GEN / 2) k.x += -GEN / 2 - s.sol;
            if (s.sag > GEN / 2) k.x -= s.sag - GEN / 2;
            if (s.ust > YUK / 2) k.y -= s.ust - YUK / 2;
            if (s.alt < -YUK / 2) k.y += -YUK / 2 - s.alt;
        }
        donusStili(s) { this.k.donusStili = s; }
        xKonumu() { return Math.round(this.k.x); }
        yKonumu() { return Math.round(this.k.y); }
        yonu() { return Math.round(sayi(this.k.yon)); }

        // --- görünüm ---
        de(m) { this.k.balon = (m === '' || m === null || m === undefined) ? null : { metin: String(m), tur: 'de' }; }
        dusun(m) { this.k.balon = (m === '' || m === null || m === undefined) ? null : { metin: String(m), tur: 'dusun' }; }
        async deSureli(m, sn) { this.de(m); const b = this.k.balon; await this.bekle(sn); if (this.k.balon === b) this.k.balon = null; }
        async dusunSureli(m, sn) { this.dusun(m); const b = this.k.balon; await this.bekle(sn); if (this.k.balon === b) this.k.balon = null; }
        kostumYap(ad) {
            const c = kostum(this.k);
            let i = c.liste.findIndex(x => x.adi === ad);
            if (i < 0 && /^\d+$/.test(String(ad))) i = Number(ad) - 1;
            if (i >= 0 && i < c.liste.length) this.k.kostumIndex = i;
        }
        sonrakiKostum() { const c = kostum(this.k); this.k.kostumIndex = (c.i + 1) % c.liste.length; }
        dekorYap(ad) {
            let i = D.dekorlar.findIndex(x => x.adi === ad);
            if (i < 0 && /^\d+$/.test(String(ad))) i = Number(ad) - 1;
            if (i >= 0 && i < D.dekorlar.length) { D.dekorIndex = i; MBM.dekorTetikle(); }
        }
        sonrakiDekor() { if (D.dekorlar.length) { D.dekorIndex = (D.dekorIndex + 1) % D.dekorlar.length; MBM.dekorTetikle(); } }
        boyutDegistir(d) { this.k.boyut = Math.max(5, Math.min(500, sayi(this.k.boyut || 100) + d)); }
        boyutYap(d) { this.k.boyut = Math.max(5, Math.min(500, sayi(d))); }
        efektDegistir(e, d) { this.k.efekt = this.k.efekt || {}; this.k.efekt[e] = sayi(this.k.efekt[e]) + d; }
        efektYap(e, d) { this.k.efekt = this.k.efekt || {}; this.k.efekt[e] = sayi(d); }
        efektTemizle() { this.k.efekt = {}; }
        goster() { this.k.goster = true; }
        gizle() { this.k.goster = false; this.k.balon = null; }
        katmanaGit(nereye) {
            D.sira = D.sira.filter(id => id !== this.k.id);
            if (nereye === 'BACK') D.sira.unshift(this.k.id); else D.sira.push(this.k.id);
        }
        katmanDegistir(yon, adet) {
            const i = D.sira.indexOf(this.k.id); if (i < 0) return;
            D.sira.splice(i, 1);
            const yeni = Math.max(0, Math.min(D.sira.length, i + (yon === 'BACKWARD' ? -1 : 1) * Math.round(adet)));
            D.sira.splice(yeni, 0, this.k.id);
        }
        kostumBilgi(tip) { const c = kostum(this.k); return tip === 'NAME' ? c.aktif.adi : c.i + 1; }
        dekorBilgi(tip) { const d = D.dekorlar[D.dekorIndex]; return tip === 'NAME' ? (d ? d.adi : '') : D.dekorIndex + 1; }
        buyukluk() { return Math.round(sayi(this.k.boyut || 100)); }

        // --- ses ---
        sesCal(ad) { sesCal(ad); }
        async sesCalBitene(ad) { const s = sesCal(ad); await this.bekle(s); }
        async notaCal(nota, vurus) {
            const sure = Math.max(0.05, sayi(vurus) * 0.5);
            ton(440 * Math.pow(2, (sayi(nota) - 69) / 12), 0, sure, 'triangle', 0.3);
            await this.bekle(sure);
        }
        sesleriDurdur() { sesleriDurdur(); }

        // --- olaylar ---
        yayinla(mesaj) { MBM.tetikle('mesaj', mesaj); }

        // --- kontrol ---
        hepsiniDurdur() { MBM.durdur(); }

        // --- algılama ---
        degiyorMu(h) {
            const k = this.k; if (!k.goster) return false;
            const s = sinirlar(k);
            if (h === 'EDGE') return s.sol <= -GEN / 2 || s.sag >= GEN / 2 || s.ust >= YUK / 2 || s.alt <= -YUK / 2;
            if (h === 'MOUSE') return D.fare.x >= s.sol && D.fare.x <= s.sag && D.fare.y >= s.alt && D.fare.y <= s.ust;
            const t = kuklaAdla(h); if (!t || t === k || !t.goster) return false;
            const b = sinirlar(t);
            // Kutuları %15 içeriden karşılaştır: şeffaf köşeler "değdi" sayılmasın
            const ks = 0.15;
            return (s.sol + s.w * ks) < (b.sag - b.w * ks) && (s.sag - s.w * ks) > (b.sol + b.w * ks)
                && (s.alt + s.h * ks) < (b.ust - b.h * ks) && (s.ust - s.h * ks) > (b.alt + b.h * ks);
        }
        renkDegiyorMu(r) { return renkDegiyorMu(this.k, r); }
        tusBasiliMi(t) {
            const es = { RIGHT: 'ARROWRIGHT', LEFT: 'ARROWLEFT', UP: 'ARROWUP', DOWN: 'ARROWDOWN' };
            t = es[t] || t;
            if (t === 'ANY') return Object.values(D.tuslar).some(Boolean);
            return !!D.tuslar[t];
        }
        fareBasiliMi() { return D.fare.basili; }
        fareX() { return Math.round(D.fare.x); }
        fareY() { return Math.round(D.fare.y); }
        zamanlayici() { return Math.round((performance.now() - D.zamanBas) / 100) / 10; }
        zamanlayiciSifirla() { D.zamanBas = performance.now(); }
        async sor(soru) {
            this.de(soru);
            const cevap = await MBM.sorKutusu(soru, this);
            this.k.balon = null;
            D.cevap = cevap;
        }

        // --- işlemler ---
        karsilastir(a, op, b) {
            const na = Number(a), nb = Number(b);
            let fark;
            if (String(a).trim() !== '' && String(b).trim() !== '' && isFinite(na) && isFinite(nb)) fark = na - nb;
            else fark = String(a).toLocaleLowerCase('tr').localeCompare(String(b).toLocaleLowerCase('tr'), 'tr');
            switch (op) { case 'LT': return fark < 0; case 'GT': return fark > 0; case 'LTE': return fark <= 0; case 'GTE': return fark >= 0; default: return fark === 0; }
        }
        rastgele(a, b) {
            const min = Math.min(a, b), max = Math.max(a, b);
            if (Number.isInteger(a) && Number.isInteger(b)) return min + Math.floor(Math.random() * (max - min + 1));
            return min + Math.random() * (max - min);
        }
        mod(a, b) { if (b === 0) return 0; return ((a % b) + b) % b; }

        // --- kalem ---
        kalemBastir() { this.k.kalemBasili = true; kalemCizgi(this.k, this.k.x, this.k.y, this.k.x + 0.01, this.k.y); }
        kalemKaldir() { this.k.kalemBasili = false; }
        kalemRenk(r) { this.k.kalemRenk = RENK_HEX[r] || r; }
        kalemKalinlik(n) { this.k.kalemKalinlik = Math.max(1, Math.min(60, n)); }
        kalemKalinlikDegistir(n) { this.kalemKalinlik(sayi(this.k.kalemKalinlik || 3) + n); }
        kalemTemizle() { kalemTemizle(); }
        damga() {
            const c = MBM.kalemCtx, k = this.k; if (!c || !k.goster) return;
            const r = resim(kostum(k).aktif.resimYolu); if (!r || !r.yuklendi) return;
            const b = boyutlar(k);
            c.save();
            c.translate((k.x + GEN / 2) * KALEM_OLCEK, (YUK / 2 - k.y) * KALEM_OLCEK);
            const st = donusu(k);
            c.rotate(st.aci * Math.PI / 180); c.scale(st.ayna, 1);
            c.globalAlpha = 1 - Math.max(0, Math.min(100, sayi((k.efekt || {}).GHOST))) / 100;
            c.drawImage(r.img, -b.w / 2 * KALEM_OLCEK, -b.h / 2 * KALEM_OLCEK, b.w * KALEM_OLCEK, b.h * KALEM_OLCEK);
            c.restore();
        }
    }
    MBM.Baglam = Baglam;

    function donusu(k) {
        const stil = k.donusStili || 'HER', yon = sayi(k.yon === undefined ? 90 : k.yon);
        if (stil === 'YOK') return { aci: 0, ayna: 1 };
        if (stil === 'SOLSAG') return { aci: 0, ayna: yon < 0 ? -1 : 1 };
        return { aci: yon - 90, ayna: 1 };
    }

    // Tek bir kare bekleme sözü bütün betikler arasında paylaşılır
    let kareSozu = null;
    function kareBekle() {
        if (!kareSozu) {
            kareSozu = new Promise(r => {
                let bitti = false;
                const bitir = () => { if (bitti) return; bitti = true; kareSozu = null; r(); };
                /* Normalde ekran yenilemesiyle (60 FPS) ilerler. Ama pencere küçültülünce
                   ya da sekme gizlenince tarayıcı requestAnimationFrame'i HİÇ çağırmaz;
                   o zaman betikler sonsuza dek takılırdı. 50 ms'lik yedek saat bunu önler. */
                if (!document.hidden) requestAnimationFrame(bitir);
                setTimeout(bitir, document.hidden ? 16 : 50);
            });
        }
        return kareSozu;
    }

    // ------------------------------------------------------------------
    // DERLEME VE ÇALIŞTIRMA
    // ------------------------------------------------------------------
    MBM.kuklaKodu = function (k) {
        if (window.ogrenciWorkspace && k.id === window.aktifKuklaId) {
            try { return Blockly.Xml.domToText(Blockly.Xml.workspaceToDom(window.ogrenciWorkspace)); } catch (e) {}
        }
        return k.kodXml || '';
    };

    MBM.derle = function () {
        const G = window.jsMotoru;
        MBM.isleyiciler = [];
        MBM.sonHata = null;
        if (!G || typeof Blockly === 'undefined') return;
        kuklalar().forEach(k => {
            const xml = MBM.kuklaKodu(k);
            if (!xml || xml.indexOf('<block') === -1) return;
            let ws = null;
            try {
                ws = new Blockly.Workspace();
                Blockly.Xml.domToWorkspace(Blockly.utils.xml.textToDom(xml), ws);
                const kod = G.workspaceToCode(ws);
                const kayit = {
                    olay: (tip, param, fn) => MBM.isleyiciler.push({ kuklaId: k.id, tip, param, fn, aktif: null }),
                    n: sayi
                };
                new Function('S', 'D', kod)(kayit, D);
            } catch (e) {
                console.error('Derleme hatası (' + k.adi + '):', e);
                MBM.sonHata = k.adi + ': ' + e.message;
            } finally {
                if (ws) ws.dispose();
            }
        });
    };

    function betikBaslat(h) {
        const k = kuklaBul(h.kuklaId); if (!k) return;
        if (h.aktif) {
            if (h.tip === 'tus') return;           // mBlock: tuş betiği bitmeden yeniden başlamaz
            h.aktif.iptal = true;                  // diğerleri baştan başlar
        }
        const S = new Baglam(k);
        h.aktif = S;
        D.aktifBetik++;
        MBM.calismaGostergesi();
        Promise.resolve().then(() => h.fn.call(null, S)).catch(e => {
            if (e && e.durdur) return;
            console.error('Betik hatası:', e);
            MBM.hataGoster('Kodunda bir sorun var (' + k.adi + '): ' + (e && e.message ? e.message : e));
        }).finally(() => {
            if (h.aktif === S) h.aktif = null;
            D.aktifBetik = Math.max(0, D.aktifBetik - 1);
            MBM.calismaGostergesi();
        });
    }

    MBM.tetikle = function (tip, param, kuklaId) {
        let adet = 0;
        MBM.isleyiciler.forEach(h => {
            if (h.tip !== tip) return;
            if (kuklaId && h.kuklaId !== kuklaId) return;
            if (tip === 'tus' && h.param !== 'ANY' && h.param !== param) return;
            if (tip === 'mesaj' && String(h.param).trim().toLocaleLowerCase('tr') !== String(param).trim().toLocaleLowerCase('tr')) return;
            adet++;
            betikBaslat(h);
        });
        return adet;
    };

    MBM.durdur = function () {
        D.kosu++;
        MBM.isleyiciler.forEach(h => { h.aktif = null; });
        D.aktifBetik = 0;
        sesleriDurdur();
        MBM.sorKapat();
        D.tuslar = {};
        MBM.calismaGostergesi();
    };

    MBM.bayrak = function () {
        sesBaglami();   // tarayıcı sesi ancak kullanıcı tıklayınca açar
        MBM.durdur();
        MBM.geriYukle(MBM.anlik);
        MBM.derle();
        D.zamanBas = performance.now();
        if (MBM.sonHata) { MBM.hataGoster('Kodun derlenemedi → ' + MBM.sonHata); return; }
        const adet = MBM.tetikle('bayrak', null);
        if (adet === 0) {
            const blokVar = kuklalar().some(k => (MBM.kuklaKodu(k) || '').indexOf('<block') !== -1);
            MBM.bilgiGoster(blokVar
                ? '🏳️ Bloklarını "Yeşil Bayrak Tıklandığında" bloğunun ALTINA takmalısın. Bu bloğu "Olaylar" kategorisinde bulabilirsin.'
                : '👋 Henüz hiç blok eklemedin! Soldaki menüden blokları sürükleyip kod alanına bırak.');
        }
    };

    // ------------------------------------------------------------------
    // DURUM KAYDET / GERİ YÜKLE
    // ------------------------------------------------------------------
    const ALANLAR = ['x', 'y', 'yon', 'boyut', 'goster', 'kostumIndex', 'donusStili'];
    function durumAl() {
        return {
            kuklalar: kuklalar().map(k => {
                const o = { id: k.id }; ALANLAR.forEach(a => o[a] = k[a]); return o;
            }),
            dekorIndex: D.dekorIndex,
            sira: D.sira.slice()
        };
    }
    MBM.anlikGoruntuAl = function () { MBM.anlik = durumAl(); };
    MBM.geriYukle = function (d) {
        if (!d) return;
        d.kuklalar.forEach(o => {
            const k = kuklaBul(o.id); if (!k) return;
            ALANLAR.forEach(a => { if (o[a] !== undefined) k[a] = o[a]; });
        });
        kuklalar().forEach(k => { k.balon = null; k.efekt = {}; k.kalemBasili = false; k.kalemRenk = RENK_HEX.BLUE; k.kalemKalinlik = 3; });
        D.dekorIndex = d.dekorIndex || 0;
        if (Array.isArray(d.sira)) D.sira = d.sira.filter(id => kuklaBul(id));
        D.v = {}; D.cevap = '';
        MBM.monitorKirli = true;
        kalemTemizle();
        MBM.dekorTetikle();
    };
    /** "↺ Başa Dön": görevin ilk hâli */
    MBM.basaDon = function () {
        MBM.durdur();
        if (MBM.gorevBaslangic) { MBM.geriYukle(MBM.gorevBaslangic); MBM.anlik = MBM.gorevBaslangic; }
        if (typeof window.kuklaPaneliniGuncelle === 'function') window.kuklaPaneliniGuncelle();
    };

    // ------------------------------------------------------------------
    // GÖREVİN SAHNESİNİ YÜKLE
    // ------------------------------------------------------------------
    MBM.yol = function (y) {
        if (!y) return y;
        if (/^(data:|https?:|\/|\.\.\/)/.test(y)) return y;
        return (window.MBM_KOK || '../../') + y;   // veritabanında site köküne göre saklanır
    };

    MBM.gorevSahnesiniKur = function (sahne) {
        sahne = sahne || {};
        D.dekorlar = (Array.isArray(sahne.dekorlar) ? sahne.dekorlar : []).map((d, i) => ({ adi: d.adi || ('dekor' + (i + 1)), yol: MBM.yol(d.yol) }));
        D.dekorIndex = 0;
        const liste = Array.isArray(sahne.kuklalar) ? sahne.kuklalar : [];
        if (liste.length) {
            window.projeKuklalari = liste.map((s, i) => {
                const kostumler = (s.kostumler || []).map((c, j) => ({ id: 'gk_' + i + '_' + j, adi: c.adi || ('kostüm' + (j + 1)), resimYolu: MBM.yol(c.yol), cizimData: null }));
                return {
                    id: 'kukla_g' + i, adi: s.adi || ('Kukla' + (i + 1)),
                    x: sayi(s.x), y: sayi(s.y), boyut: sayi(s.boyut || 100), yon: s.yon === undefined ? 90 : sayi(s.yon),
                    goster: s.goster !== false, donusStili: s.donus_stili || 'HER', kostumIndex: 0,
                    kostumler: kostumler, resimYolu: kostumler[0] ? kostumler[0].resimYolu : '',
                    kodXml: '<xml xmlns="https://developers.google.com/blockly/xml"></xml>'
                };
            });
        }
        D.sira = kuklalar().map(k => k.id);
        kuklalar().forEach(k => (k.kostumler || []).forEach(c => resim(c.resimYolu)));
        D.dekorlar.forEach(d => { const im = new Image(); im.src = d.yol; });
        MBM.anlik = durumAl();
        MBM.gorevBaslangic = durumAl();
        MBM.dekorTetikle();
    };

    // Açılır menüler sahnedeki gerçek adları göstersin
    window.MBM_SECENEKLER = {
        kostumler: function () {
            const k = kuklaBul(window.aktifKuklaId) || kuklalar()[0];
            const ad = k ? kostum(k).liste.map(c => c.adi) : [];
            return ad.length ? ad : ['kostüm1', 'kostüm2'];
        },
        dekorlar: function () { return D.dekorlar.length ? D.dekorlar.map(d => d.adi) : ['dekor1', 'dekor2']; },
        kuklalar: function () { return kuklalar().filter(k => k.id !== window.aktifKuklaId).map(k => k.adi); }
    };

    // ------------------------------------------------------------------
    // ÇİZİM (DOM)
    // ------------------------------------------------------------------
    MBM.dekorTetikle = function () {
        if (!MBM.dekorEl) return;
        const d = D.dekorlar[D.dekorIndex];
        MBM.dekorEl.style.backgroundImage = d ? 'url("' + d.yol + '")' : 'none';
    };

    MBM.ciz = function () {
        const sahne = MBM.sahne; if (!sahne || !MBM.katman) return;
        const oran = sahne.clientWidth / GEN || 1;
        const mevcut = {};
        MBM.katman.querySelectorAll('.dinamik-kukla').forEach(el => { mevcut[el.getAttribute('data-id')] = el; });
        // Sırada olmayan kuklaları sona ekle
        kuklalar().forEach(k => { if (D.sira.indexOf(k.id) === -1) D.sira.push(k.id); });
        D.sira = D.sira.filter(id => kuklaBul(id));

        kuklalar().forEach(k => {
            let img = mevcut[k.id];
            if (!img) {
                img = document.createElement('img');
                img.className = 'dinamik-kukla';
                img.setAttribute('data-id', k.id);
                img.setAttribute('draggable', 'false');
                MBM.katman.appendChild(img);
            }
            delete mevcut[k.id];
            const c = kostum(k), yol = c.aktif.resimYolu || '';
            if (img.getAttribute('src') !== yol) img.setAttribute('src', yol);
            const b = boyutlar(k), st = donusu(k);
            img.style.width = (b.w * oran) + 'px';
            img.style.height = (b.h * oran) + 'px';
            img.style.left = ((k.x + GEN / 2) * oran) + 'px';
            img.style.top = ((YUK / 2 - k.y) * oran) + 'px';
            img.style.transform = 'translate(-50%,-50%) rotate(' + st.aci + 'deg) scaleX(' + st.ayna + ')';
            img.style.display = k.goster ? 'block' : 'none';
            img.style.zIndex = String(10 + D.sira.indexOf(k.id));
            const e = k.efekt || {};
            const filtre = [];
            if (sayi(e.COLOR)) filtre.push('hue-rotate(' + (sayi(e.COLOR) * 1.8) + 'deg)');
            if (k.id === window.aktifKuklaId && D.aktifBetik === 0) filtre.push('drop-shadow(0 0 5px rgba(59,130,246,.9))');
            img.style.filter = filtre.join(' ');
            img.style.opacity = String(1 - Math.max(0, Math.min(100, sayi(e.GHOST))) / 100);

            // Konuşma balonu
            let balon = MBM.katman.querySelector('.mbm-balon[data-id="' + k.id + '"]');
            if (k.balon && k.goster) {
                if (!balon) { balon = document.createElement('div'); balon.className = 'mbm-balon'; balon.setAttribute('data-id', k.id); MBM.katman.appendChild(balon); }
                if (balon.textContent !== k.balon.metin) balon.textContent = k.balon.metin;
                balon.classList.toggle('dusunce', k.balon.tur === 'dusun');
                const sag = (k.x + b.w * 0.3 + GEN / 2) * oran, ust = (YUK / 2 - k.y - b.h / 2) * oran;
                balon.style.left = Math.min(sag, sahne.clientWidth - 40) + 'px';
                balon.style.top = Math.max(ust, 46) + 'px';
                balon.style.zIndex = '500';
            } else if (balon) balon.remove();
        });
        Object.keys(mevcut).forEach(id => { mevcut[id].remove(); const b = MBM.katman.querySelector('.mbm-balon[data-id="' + id + '"]'); if (b) b.remove(); });

        if (MBM.monitorKirli) {
            MBM.monitorKirli = false;
            const ad = Object.keys(D.v);
            MBM.monitorEl.innerHTML = '';
            ad.forEach(a => {
                const d = document.createElement('div'); d.className = 'mbm-monitor';
                const s1 = document.createElement('span'); s1.textContent = a;
                const s2 = document.createElement('b'); s2.textContent = String(D.v[a]);
                d.appendChild(s1); d.appendChild(s2); MBM.monitorEl.appendChild(d);
            });
        }
        if (typeof window.kuklaPaneliCanli === 'function') window.kuklaPaneliCanli();
    };
    let sonCizim = 0;
    function dongu() { sonCizim = performance.now(); try { MBM.ciz(); } catch (e) { /* çizim hatası oyunu durdurmasın */ } requestAnimationFrame(dongu); }
    // rAF durursa (küçültülmüş / arkada kalan pencere) çizim yine de ara ara tazelensin
    setInterval(() => { if (performance.now() - sonCizim > 300) { try { MBM.ciz(); } catch (e) {} } }, 250);

    // ------------------------------------------------------------------
    // SOR / CEVAP KUTUSU
    // ------------------------------------------------------------------
    MBM.sorKutusu = function (soru, S) {
        return new Promise(coz => {
            MBM.sorKapat();
            const k = document.createElement('div'); k.className = 'mbm-sor';
            const inp = document.createElement('input'); inp.type = 'text'; inp.placeholder = 'Cevabını yaz, Enter\'a bas';
            const btn = document.createElement('button'); btn.type = 'button'; btn.textContent = '✔';
            k.appendChild(inp); k.appendChild(btn);
            MBM.sahne.appendChild(k);
            const bitir = () => { const v = inp.value; MBM.sorKapat(); coz(v); };
            btn.onclick = bitir;
            inp.onkeydown = e => { e.stopPropagation(); if (e.key === 'Enter') bitir(); };
            MBM._sorIptal = () => { coz(''); };
            setTimeout(() => inp.focus(), 30);
        });
    };
    MBM.sorKapat = function () {
        const k = MBM.sahne && MBM.sahne.querySelector('.mbm-sor');
        if (k) k.remove();
        MBM._sorIptal = null;
    };

    // ------------------------------------------------------------------
    // MESAJ KUTULARI
    // ------------------------------------------------------------------
    MBM.hataGoster = function (m) { if (typeof window.hakemSonucGoster === 'function') window.hakemSonucGoster('🐞 ' + m, 'yanlis'); else console.warn(m); };
    MBM.bilgiGoster = function (m) { if (typeof window.hakemSonucGoster === 'function') window.hakemSonucGoster(m, 'bilgi'); else console.info(m); };
    MBM.calismaGostergesi = function () {
        const b = document.getElementById('btnYesilBayrak');
        if (b) b.classList.toggle('calisiyor', D.aktifBetik > 0);
        if (MBM.sahne) MBM.sahne.classList.toggle('calisiyor', D.aktifBetik > 0);
    };

    // ------------------------------------------------------------------
    // FARE / KLAVYE / SÜRÜKLE
    // ------------------------------------------------------------------
    function sahneKoordinati(e) {
        const r = MBM.sahne.getBoundingClientRect();
        return { x: (e.clientX - r.left) / r.width * GEN - GEN / 2, y: YUK / 2 - (e.clientY - r.top) / r.height * YUK };
    }
    const TUS_ADI = e => {
        if (e.code === 'Space') return 'SPACE';
        if (e.key && e.key.startsWith('Arrow')) return e.key.toUpperCase();
        if (/^Key[A-Z]$/.test(e.code)) return e.code.slice(3);
        if (/^Digit\d$/.test(e.code)) return e.code.slice(5);
        return (e.key || '').toUpperCase();
    };
    function yaziAlaninda(e) {
        const t = e.target; if (!t) return false;
        const tag = (t.tagName || '').toUpperCase();
        return tag === 'INPUT' || tag === 'TEXTAREA' || t.isContentEditable || (t.closest && t.closest('.blocklyWidgetDiv, .blocklyHtmlInput'));
    }

    function olaylariBagla() {
        const sahne = MBM.sahne;
        let surukle = null;
        const fk = document.getElementById('fareKoordinat');
        sahne.addEventListener('mousemove', e => {
            const p = sahneKoordinati(e); D.fare.x = p.x; D.fare.y = p.y;
            if (fk) fk.textContent = '🖱️ x: ' + Math.round(p.x) + '   y: ' + Math.round(p.y);
        });
        sahne.addEventListener('mousedown', e => {
            D.fare.basili = true;
            const el = e.target.closest ? e.target.closest('.dinamik-kukla') : null;
            if (!el) return;
            e.preventDefault();
            const k = kuklaBul(el.getAttribute('data-id')); if (!k) return;
            const p = sahneKoordinati(e);
            surukle = { k, dx: k.x - p.x, dy: k.y - p.y, bx: e.clientX, by: e.clientY, oynadi: false };
            if (window.aktifKuklaId !== k.id && typeof window.kuklaSec === 'function') window.kuklaSec(k.id);
        });
        window.addEventListener('mousemove', e => {
            if (!surukle) return;
            if (Math.abs(e.clientX - surukle.bx) + Math.abs(e.clientY - surukle.by) > 4) surukle.oynadi = true;
            if (!surukle.oynadi) return;
            const p = sahneKoordinati(e);
            surukle.k.x = Math.round(p.x + surukle.dx); surukle.k.y = Math.round(p.y + surukle.dy);
            citeSok(surukle.k);
        });
        window.addEventListener('mouseup', () => {
            D.fare.basili = false;
            if (!surukle) return;
            const s = surukle; surukle = null;
            if (s.oynadi) { if (D.aktifBetik === 0) MBM.anlikGoruntuAl(); }
            else {
                // Tıklama: önce kodu güncelle, sonra "bu kukla tıklandığında"
                if (D.aktifBetik === 0) MBM.derle();
                MBM.tetikle('tikla', null, s.k.id);
            }
        });

        document.addEventListener('keydown', e => {
            if (yaziAlaninda(e)) return;
            const ad = TUS_ADI(e);
            D.tuslar[ad] = true;
            const ilgili = MBM.isleyiciler.some(h => h.tip === 'tus' && (h.param === ad || h.param === 'ANY')) || D.aktifBetik > 0;
            if (ilgili && (ad === 'SPACE' || ad.startsWith('ARROW'))) e.preventDefault();
            MBM.tetikle('tus', ad);
        });
        document.addEventListener('keyup', e => { D.tuslar[TUS_ADI(e)] = false; });
        window.addEventListener('blur', () => { D.tuslar = {}; D.fare.basili = false; });
    }

    // ------------------------------------------------------------------
    // KURULUM
    // ------------------------------------------------------------------
    MBM.kur = function () {
        const sahne = document.querySelector('.oyun-sahnesi');
        if (!sahne || MBM.hazir) return;
        MBM.sahne = sahne;
        MBM.dekorEl = document.createElement('div'); MBM.dekorEl.className = 'mbm-dekor';
        MBM.kalemCanvas = document.createElement('canvas'); MBM.kalemCanvas.className = 'mbm-kalem';
        MBM.kalemCanvas.width = GEN * KALEM_OLCEK; MBM.kalemCanvas.height = YUK * KALEM_OLCEK;
        MBM.kalemCtx = MBM.kalemCanvas.getContext('2d');
        MBM.katman = document.createElement('div'); MBM.katman.className = 'mbm-katman';
        MBM.monitorEl = document.createElement('div'); MBM.monitorEl.className = 'mbm-monitorler';
        sahne.insertBefore(MBM.monitorEl, sahne.firstChild);
        sahne.insertBefore(MBM.katman, sahne.firstChild);
        sahne.insertBefore(MBM.kalemCanvas, sahne.firstChild);
        sahne.insertBefore(MBM.dekorEl, sahne.firstChild);
        const eskiBalon = document.getElementById('konusmaBalonu'); if (eskiBalon) eskiBalon.style.display = 'none';
        olaylariBagla();
        MBM.hazir = true;
        requestAnimationFrame(dongu);
    };

    // Eski adlarla çağıranlar için köprüler (butonlar, hakem_motoru.js)
    window.yesilBayraklariTetikle = function () { MBM.bayrak(); };
    window.oyunuDurdur = function () { MBM.durdur(); };
    window.arkaPlandaDerle = function () { if (D.aktifBetik === 0) MBM.derle(); };
    window.oyunBekle = function (sn) { return uyu(sayi(sn) * 1000); };

    /** Canlı derleyici: kod değişince olay dinleyicilerini tazeler; bloğa tıklayınca o bloğu dener */
    window.canliDerleyiciyiBaslat = function (ws) {
        let zaman = null;
        ws.addChangeListener(function (ev) {
            const tikla = (ev.type === Blockly.Events.CLICK) || (ev.type === Blockly.Events.UI && ev.element === 'click');
            if (tikla && ev.blockId && (ev.targetType === undefined || ev.targetType === 'block')) {
                const blok = ws.getBlockById(ev.blockId); if (!blok) return;
                const kok = blok.getRootBlock();
                const k = kuklaBul(window.aktifKuklaId) || kuklalar()[0]; if (!k) return;
                clearTimeout(zaman);
                MBM.derle();
                if (/^mblock_olay_/.test(kok.type)) {
                    // Şapkalı yığına tıklandı: o yığını çalıştır
                    let kod = '';
                    window.sinamaModuAktif = true;
                    try { const alt = kok.getNextBlock(); kod = alt ? window.jsMotoru.blockToCode(alt) : ''; } finally { window.sinamaModuAktif = false; }
                    if (kod) tekKodCalistir(k, kod);
                } else {
                    let kod = '';
                    window.sinamaModuAktif = true;
                    try { kod = window.jsMotoru.blockToCode(kok); if (Array.isArray(kod)) kod = 'S.de(' + kod[0] + ');'; } finally { window.sinamaModuAktif = false; }
                    if (kod) tekKodCalistir(k, kod);
                }
                return;
            }
            if (ev.isUiEvent) return;
            clearTimeout(zaman);
            zaman = setTimeout(() => { if (D.aktifBetik === 0) MBM.derle(); }, 600);
        });
    };
    function tekKodCalistir(k, kod) {
        try {
            const fn = new Function('S', 'D', 'return (async function(){\n' + kod + '\n})();');
            const S = new Baglam(k);
            D.aktifBetik++; MBM.calismaGostergesi();
            fn(S, D).catch(e => { if (!(e && e.durdur)) MBM.hataGoster(e.message || String(e)); })
                .finally(() => { D.aktifBetik = Math.max(0, D.aktifBetik - 1); MBM.calismaGostergesi(); });
        } catch (e) { MBM.hataGoster(e.message || String(e)); }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', MBM.kur); else MBM.kur();
})();
