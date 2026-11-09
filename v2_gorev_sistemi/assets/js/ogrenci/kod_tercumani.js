/**
 * ============================================================================
 * Dosya Yolu: v2_gorev_sistemi/assets/js/ogrenci/kod_tercumani.js
 * Kullanıcı: Öğrenci -> Görev Oynatıcı
 *
 * HALK DİLİNDE İŞLEVİ:
 * Öğrencinin dizdiği yapboz bloklarını, sahne motorunun (sahne_motoru.js)
 * anlayacağı JavaScript'e çeviren "tercüman".
 *
 * NASIL ÇALIŞIR:
 *  - Her kuklanın kodu AYRI derlenir ve o kuklanın bağlamıyla (S) çalışır.
 *    S.git(10) -> "bu kukla 10 adım gitsin".
 *  - Şapka blokları (yeşil bayrak, tuş, tıklama, mesaj) kayıt olur:
 *        S.olay('bayrak', null, async function (S) { ...gövde... });
 *    İçteki S, olay tetiklendiğinde oluşturulan TAZE bağlamdır; "durdur"a
 *    basılınca o bağlam geçersizleşir ve betik bir sonraki beklemede durur.
 *  - Döngüler her turda bir kare bekler (await S.kare()). Böylece hareket
 *    ekranda adım adım görünür ve "sürekli tekrarla" tarayıcıyı dondurmaz.
 *  - D: bütün kuklaların ortak verisi (değişkenler, cevap, zamanlayıcı).
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-11] Baştan yazıldı. Eskiden 65 bloğun 37'sinin tercümesi yoktu
 *    ("gizlen", "x'i değiştir", "eğer-değilse", "sonraki kostüm"... hiç
 *    çalışmıyordu) ve kod sadece seçili kuklada çalışıyordu. Artık bütün
 *    bloklar + değişkenler + kalem + mesajlar çalışıyor, her kukla kendi
 *    kodunu çalıştırıyor.
 * ============================================================================
 */

(function () {
    let G = null;
    if (typeof javascript !== 'undefined' && javascript.javascriptGenerator) G = javascript.javascriptGenerator;
    else if (typeof Blockly !== 'undefined' && Blockly.JavaScript) G = Blockly.JavaScript;
    if (!G) { console.error('KRİTİK HATA: JavaScript çevirmen motoru bulunamadı!'); return; }
    if (!G.forBlock) G.forBlock = G;
    window.jsMotoru = G;   // hakem_motoru.js ve bilgi_al.js eski adla erişiyor

    const A = 0;           // ORDER_ATOMIC  — her ifadeyi zaten parantezle sarıyoruz
    const YOK = 99;        // ORDER_NONE

    const v = (b, ad, yedek) => G.valueToCode(b, ad, YOK) || yedek;
    const n = (b, ad, yedek) => 'S.n(' + v(b, ad, String(yedek === undefined ? 0 : yedek)) + ')';
    const t = (b, ad, yedek) => v(b, ad, JSON.stringify(yedek === undefined ? '' : yedek));
    const f = (b, ad) => JSON.stringify(String(b.getFieldValue(ad) == null ? '' : b.getFieldValue(ad)));
    const ic = (b, ad) => G.statementToCode(b, ad);
    const K = (kod) => kod + '\n';
    const def = (tip, fn) => { G.forBlock[tip] = fn; };

    // ---------------- HAREKET ----------------
    def('mblock_hareket_git', b => K('S.git(' + n(b, 'ADIM', 10) + ');'));
    def('mblock_hareket_don_sag', b => K('S.don(' + n(b, 'DERECE', 15) + ');'));
    def('mblock_hareket_don_sol', b => K('S.don(-' + n(b, 'DERECE', 15) + ');'));
    def('mblock_hareket_rastgele_git', b => K('S.hedefeGit(' + f(b, 'HEDEF') + ');'));
    def('mblock_hareket_xy_git', b => K('S.xyGit(' + n(b, 'X') + ', ' + n(b, 'Y') + ');'));
    def('mblock_hareket_suzul_rastgele', b => K('await S.hedefeSuzul(' + n(b, 'SURE', 1) + ', ' + f(b, 'HEDEF') + ');'));
    def('mblock_hareket_suzul_xy', b => K('await S.suzul(' + n(b, 'SURE', 1) + ', ' + n(b, 'X') + ', ' + n(b, 'Y') + ');'));
    def('mblock_hareket_yone_don', b => K('S.yonYap(' + n(b, 'YON', 90) + ');'));
    def('mblock_hareket_hedefe_don', b => K('S.hedefeDon(' + f(b, 'HEDEF') + ');'));
    def('mblock_hareket_x_degistir', b => K('S.xDegistir(' + n(b, 'X', 10) + ');'));
    def('mblock_hareket_x_yap', b => K('S.xYap(' + n(b, 'X') + ');'));
    def('mblock_hareket_y_degistir', b => K('S.yDegistir(' + n(b, 'Y', 10) + ');'));
    def('mblock_hareket_y_yap', b => K('S.yYap(' + n(b, 'Y') + ');'));
    def('mblock_hareket_kenardaysa_sek', () => K('S.kenardaSek();'));
    def('mblock_hareket_donus_stili', b => K('S.donusStili(' + f(b, 'STIL') + ');'));
    def('mblock_hareket_x_konumu', () => ['S.xKonumu()', A]);
    def('mblock_hareket_y_konumu', () => ['S.yKonumu()', A]);
    def('mblock_hareket_yonu', () => ['S.yonu()', A]);

    // ---------------- GÖRÜNÜM ----------------
    def('mblock_gorunum_merhaba_de', b => K('S.de(' + t(b, 'MESAJ') + ');'));
    def('mblock_gorunum_merhaba_de_sureli', b => K('await S.deSureli(' + t(b, 'MESAJ') + ', ' + n(b, 'SURE', 2) + ');'));
    def('mblock_gorunum_dusun', b => K('S.dusun(' + t(b, 'MESAJ') + ');'));
    def('mblock_gorunum_dusun_sureli', b => K('await S.dusunSureli(' + t(b, 'MESAJ') + ', ' + n(b, 'SURE', 2) + ');'));
    def('mblock_gorunum_kiligina_gec', b => K('S.kostumYap(' + f(b, 'KILIK') + ');'));
    def('mblock_gorunum_sonraki_kilik', () => K('S.sonrakiKostum();'));
    def('mblock_gorunum_dekora_gec', b => K('S.dekorYap(' + f(b, 'DEKOR') + ');'));
    def('mblock_gorunum_sonraki_dekor', () => K('S.sonrakiDekor();'));
    def('mblock_gorunum_boyut_degistir', b => K('S.boyutDegistir(' + n(b, 'BOYUT', 10) + ');'));
    def('mblock_gorunum_boyut_yap', b => K('S.boyutYap(' + n(b, 'BOYUT', 100) + ');'));
    def('mblock_gorunum_renk_etkisi_degistir', b => K('S.efektDegistir(' + f(b, 'ETKI') + ', ' + n(b, 'DEGER', 25) + ');'));
    def('mblock_gorunum_renk_etkisi_yap', b => K('S.efektYap(' + f(b, 'ETKI') + ', ' + n(b, 'DEGER', 0) + ');'));
    def('mblock_gorunum_gorsel_temizle', () => K('S.efektTemizle();'));
    def('mblock_gorunum_goster', () => K('S.goster();'));
    def('mblock_gorunum_gizle', () => K('S.gizle();'));
    def('mblock_gorunum_katmana_git', b => K('S.katmanaGit(' + f(b, 'KATMAN') + ');'));
    def('mblock_gorunum_katman_degistir', b => K('S.katmanDegistir(' + f(b, 'YON') + ', ' + n(b, 'DEGER', 1) + ');'));
    def('mblock_gorunum_kostum_no', b => ['S.kostumBilgi(' + f(b, 'TIP') + ')', A]);
    def('mblock_gorunum_dekor_no', b => ['S.dekorBilgi(' + f(b, 'TIP') + ')', A]);
    def('mblock_gorunum_buyukluk', () => ['S.buyukluk()', A]);

    // ---------------- SES ----------------
    def('mblock_ses_cal_bitene_kadar', b => K('await S.sesCalBitene(' + f(b, 'SES') + ');'));
    def('mblock_ses_cal', b => K('S.sesCal(' + f(b, 'SES') + ');'));
    def('mblock_ses_nota_cal', b => K('await S.notaCal(' + f(b, 'NOTA') + ', ' + n(b, 'VURUS', 1) + ');'));
    def('mblock_ses_durdur', () => K('S.sesleriDurdur();'));

    // ---------------- OLAYLAR ----------------
    def('mblock_olay_mesaj_yayinla', b => K('S.yayinla(' + f(b, 'MESAJ') + ');'));
    // Şapkaların kendi kodu boş; gövdeyi scrub_ sarar.
    ['mblock_olay_bayrak_tiklandiginda', 'mblock_olay_tus_basildiginda', 'mblock_olay_kukla_tiklandiginda', 'mblock_olay_mesaj_alindiginda']
        .forEach(tip => def(tip, () => ''));

    // ---------------- KONTROL ----------------
    def('mblock_kontrol_bekle', b => K('await S.bekle(' + n(b, 'SURE', 1) + ');'));
    def('mblock_kontrol_tekrarla', b => {
        const d = 'i_' + b.id.replace(/[^a-zA-Z0-9]/g, '');
        return 'for (let ' + d + ' = 0, ' + d + 'n = ' + n(b, 'KEZ', 10) + '; ' + d + ' < ' + d + 'n; ' + d + '++) {\n'
             + ic(b, 'YAP') + '  await S.kare();\n}\n';
    });
    def('mblock_kontrol_surekli', b => 'while (true) {\n' + ic(b, 'YAP') + '  await S.kare();\n}\n');
    def('mblock_kontrol_eger_ise', b => 'if (' + v(b, 'SART', 'false') + ') {\n' + ic(b, 'YAP') + '}\n');
    def('mblock_kontrol_eger_degilse', b => 'if (' + v(b, 'SART', 'false') + ') {\n' + ic(b, 'YAP') + '} else {\n' + ic(b, 'DEGILSEYAP') + '}\n');
    def('mblock_kontrol_olana_kadar_tekrarla', b => 'while (!(' + v(b, 'SART', 'false') + ')) {\n' + ic(b, 'YAP') + '  await S.kare();\n}\n');
    def('mblock_kontrol_olana_kadar_bekle', b => 'while (!(' + v(b, 'SART', 'false') + ')) { await S.kare(); }\n');
    def('mblock_kontrol_durdur', b => (b.getFieldValue('TUR') === 'TUMU') ? 'S.hepsiniDurdur(); return;\n' : 'return;\n');

    // ---------------- ALGILAMA ----------------
    def('mblock_algilama_kenara_degiyor_mu', () => ['S.degiyorMu("EDGE")', A]);
    def('mblock_algilama_fareye_degiyor_mu', () => ['S.degiyorMu("MOUSE")', A]);
    def('mblock_algilama_degiyor_mu', b => ['S.degiyorMu(' + f(b, 'HEDEF') + ')', A]);
    def('mblock_algilama_renk_degiyor_mu', b => ['S.renkDegiyorMu(' + f(b, 'RENK') + ')', A]);
    def('mblock_algilama_tusa_basili_mi', b => ['S.tusBasiliMi(' + f(b, 'TUS') + ')', A]);
    def('mblock_algilama_tus_basili_mi', b => ['S.tusBasiliMi(' + f(b, 'TUS') + ')', A]);
    def('mblock_algilama_fare_basili_mi', () => ['S.fareBasiliMi()', A]);
    def('mblock_algilama_fare_x', () => ['S.fareX()', A]);
    def('mblock_algilama_fare_y', () => ['S.fareY()', A]);
    def('mblock_algilama_sor_bekle', b => K('await S.sor(' + t(b, 'SORU') + ');'));
    def('mblock_algilama_cevap', () => ['D.cevap', A]);
    def('mblock_algilama_zamanlayici', () => ['S.zamanlayici()', A]);
    def('mblock_algilama_zamanlayici_sifirla', () => K('S.zamanlayiciSifirla();'));

    // ---------------- İŞLEMLER ----------------
    def('mblock_islem_aritmetik', b => {
        const op = { ADD: '+', MINUS: '-', MULTIPLY: '*', DIVIDE: '/' }[b.getFieldValue('OP')] || '+';
        return ['(' + n(b, 'A') + ' ' + op + ' ' + n(b, 'B') + ')', A];
    });
    def('mblock_islem_karsilastirma', b => ['S.karsilastir(' + v(b, 'A', '0') + ', ' + f(b, 'OP') + ', ' + v(b, 'B', '0') + ')', A]);
    def('mblock_islem_rastgele', b => ['S.rastgele(' + n(b, 'MIN', 1) + ', ' + n(b, 'MAX', 10) + ')', A]);
    def('mblock_islem_ve_veya', b => ['(' + v(b, 'A', 'false') + (b.getFieldValue('OP') === 'OR' ? ' || ' : ' && ') + v(b, 'B', 'false') + ')', A]);
    def('mblock_islem_degil', b => ['(!(' + v(b, 'A', 'false') + '))', A]);
    def('mblock_islem_birlestir', b => ['(String(' + t(b, 'A') + ') + String(' + t(b, 'B') + '))', A]);
    def('mblock_islem_mod', b => ['S.mod(' + n(b, 'A') + ', ' + n(b, 'B') + ')', A]);
    def('mblock_islem_yuvarla', b => ['Math.round(' + n(b, 'NUM') + ')', A]);
    def('math_number', b => { const s = String(b.getFieldValue('NUM')); return [(s === '' || isNaN(Number(s))) ? '0' : String(Number(s)), A]; });
    def('text', b => [JSON.stringify(String(b.getFieldValue('TEXT') || '')), A]);

    // ---------------- KALEM ----------------
    def('mblock_kalem_temizle', () => K('S.kalemTemizle();'));
    def('mblock_kalem_damga', () => K('S.damga();'));
    def('mblock_kalem_bastir', () => K('S.kalemBastir();'));
    def('mblock_kalem_kaldir', () => K('S.kalemKaldir();'));
    def('mblock_kalem_renk_yap', b => K('S.kalemRenk(' + f(b, 'RENK') + ');'));
    def('mblock_kalem_kalinlik_yap', b => K('S.kalemKalinlik(' + n(b, 'KALINLIK', 3) + ');'));
    def('mblock_kalem_kalinlik_degistir', b => K('S.kalemKalinlikDegistir(' + n(b, 'KALINLIK', 1) + ');'));

    // ---------------- DEĞİŞKENLER (Blockly'nin hazır blokları) ----------------
    const degAdi = b => { const a = b.getField('VAR'); return JSON.stringify(a ? a.getText() : 'değişken'); };
    def('variables_get', b => ['D.al(' + degAdi(b) + ')', A]);
    def('variables_set', b => K('D.ata(' + degAdi(b) + ', ' + v(b, 'VALUE', '0') + ');'));
    def('math_change', b => K('D.ata(' + degAdi(b) + ', S.n(D.al(' + degAdi(b) + ')) + ' + n(b, 'DELTA', 1) + ');'));

    // ---------------- ŞAPKALAR VE BAŞIBOŞ BLOKLAR ----------------
    const SAPKA = {
        mblock_olay_bayrak_tiklandiginda: () => "'bayrak', null",
        mblock_olay_kukla_tiklandiginda: () => "'tikla', null",
        mblock_olay_tus_basildiginda: b => "'tus', " + f(b, 'TUS'),
        mblock_olay_mesaj_alindiginda: b => "'mesaj', " + f(b, 'MESAJ')
    };

    G.scrub_ = function (block, code, thisOnly) {
        let sonraki = '';
        if (!thisOnly && block.nextConnection && block.nextConnection.targetBlock()) {
            sonraki = G.blockToCode(block.nextConnection.targetBlock());
        }
        if (SAPKA[block.type]) {
            return 'S.olay(' + SAPKA[block.type](block) + ', async function (S) {\n' + sonraki + '});\n';
        }
        // Hiçbir yere bağlı olmayan bloklar çalışmaz (mBlock'ta da öyle).
        // Tek blok denemesi (bloğa tıklama) ise sinamaModuAktif ile izinli.
        if (!block.getParent() && !window.sinamaModuAktif) return '';
        return code + sonraki;
    };
    // Blockly'nin "var x;" bildirimlerini üretmesini engelle (değişkenler D'de yaşar)
    const eskiInit = G.init;
    G.init = function (ws) { eskiInit.call(this, ws); try { if (this.definitions_) delete this.definitions_['variables']; } catch (e) {} };
})();
