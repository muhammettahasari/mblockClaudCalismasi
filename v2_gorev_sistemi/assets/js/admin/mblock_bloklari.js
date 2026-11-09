/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/assets/js/admin/mblock_bloklari.js
 * Kullanıcı: Süper Admin (Görev Tasarlayıcı) + Öğrenci (Oynatıcı) — ORTAK
 *
 * HALK DİLİNDE İŞLEVİ:
 * mBlock'taki yapboz bloklarının şeklini, rengini ve menüsünü tanımlar.
 * Hem admin hem öğrenci araç kutusunu (sol menü) buradan üretir.
 *
 * TEMEL İŞLEVLER:
 *  1. Blockly.Blocks[...] — her bloğun görünüşü
 *  2. MBLOCK_SABITLERI.KATEGORILER — menüdeki kategori sırası
 *  3. MBLOCK_SABITLERI.SOZLUK — menüde görünen hazır (gölgeli) hâli
 *  4. toolboxOlustur(izinliler) — görevin izin verdiği blokları menüye dizer
 *
 * ⚠️ UYUMLULUK KURALI: Blok tip adları ve girdi/alan adları (ADIM, DERECE,
 *   KILIK ...) cevap anahtarlarında saklanır. Var olanları DEĞİŞTİRME,
 *   sadece yenisini ekle. Sunucudaki hakem (v2_hakem.php) bu adlara bakar.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-11] Kalem (çizim) kategorisi, mesaj yayınla/alındığında, dönüş
 *    stili, durdur, cevap, zamanlayıcı, nota çal, ses çal.
 *  - [2026-11] Kostüm / dekor / kukla açılır menüleri artık SAHNEDEKİ gerçek
 *    adları gösteriyor (görevin kendi kuklaları). Bilinmeyen bir değer
 *    yüklenince sessizce ilk seçeneğe dönmüyor — cevap anahtarı bozulmasın.
 *  - [2026-11] "Değişkenler" kategorisi sadece görevin izin listesinde
 *    'DEGISKENLER' varsa (veya admin'de) görünür; küçük öğrencinin menüsü sade kalsın.
 * ============================================================================
 */

// ----------------------------------------------------------------------------
// 0. DİNAMİK AÇILIR MENÜ ALTYAPISI
// ----------------------------------------------------------------------------
/* Oynatıcıdaki sahne motoru window.MBM_SECENEKLER'i doldurur. Admin
   tasarlayıcıda sahne olmadığı için yedek listeler kullanılır. */
window.MBM_SECENEKLER = window.MBM_SECENEKLER || {
    kostumler: function () { return ['kostüm1', 'kostüm2', 'kostüm3']; },
    dekorlar:  function () { return ['dekor1', 'dekor2', 'dekor3']; },
    kuklalar:  function () { return []; }
};

/**
 * Her değeri kabul eden açılır menü.
 * NEDEN? Blockly normalde listede olmayan bir değer yüklenince onu reddeder ve
 * menü ilk seçeneğe döner. Öğrencinin kodu "kostüm2" diyorken başka bir
 * sayfada liste farklıysa (ör. admin tasarlayıcı) değer kaybolur ve hakem
 * doğru cevabı yanlış sanar. Bu sınıf değeri korur, listede yoksa ekler.
 */
function mbmDinamikMenu(listeFn, sabitler) {
    const menuFn = function () {
        let liste = [];
        (sabitler || []).forEach(s => liste.push(s));
        try { (listeFn() || []).forEach(ad => liste.push([String(ad), String(ad)])); } catch (e) {}
        if (liste.length === 0) liste.push(['?', '?']);
        // Şu anki değer listede yoksa ekle (yüklenen kod kaybolmasın)
        let mevcut = null;
        try { mevcut = this && typeof this.getValue === 'function' ? this.getValue() : null; } catch (e) {}
        if (mevcut && !liste.some(o => o[1] === mevcut)) liste.push([String(mevcut), String(mevcut)]);
        // Aynı değeri iki kez gösterme
        const goruldu = {};
        return liste.filter(o => (goruldu[o[1]] ? false : (goruldu[o[1]] = true)));
    };
    const alan = new Blockly.FieldDropdown(menuFn);
    // Doğrulayıcıyı gevşet: her metin değeri geçerli
    alan.doClassValidation_ = function (v) { return (v === null || v === undefined) ? null : String(v); };
    return alan;
}

const MBM_RENKLER = [['🔴 Kırmızı', 'RED'], ['🔵 Mavi', 'BLUE'], ['🟢 Yeşil', 'GREEN'], ['🟡 Sarı', 'YELLOW'],
                     ['🟠 Turuncu', 'ORANGE'], ['🟣 Mor', 'PURPLE'], ['🩷 Pembe', 'PINK'], ['⚫ Siyah', 'BLACK'], ['⚪ Beyaz', 'WHITE']];

const MBM_SESLER = [['Miyav', 'MEOW'], ['Lazer', 'LASER'], ['Zıplama', 'JUMP'], ['Bip', 'BEEP'], ['Alkış', 'CLAP'],
                    ['Davul', 'DRUM'], ['Sihir', 'MAGIC'], ['Patlama', 'POP'], ['Zafer', 'WIN']];

const MBM_NOTALAR = [['Do', '60'], ['Re', '62'], ['Mi', '64'], ['Fa', '65'], ['Sol', '67'], ['La', '69'], ['Si', '71'], ['İnce Do', '72']];

function mbmTuslar(hepsiAdi) {
    const t = [['boşluk', 'SPACE'], ['yukarı ok', 'ARROWUP'], ['aşağı ok', 'ARROWDOWN'], ['sağ ok', 'ARROWRIGHT'], ['sol ok', 'ARROWLEFT'], [hepsiAdi, 'ANY']];
    'abcdefghijklmnopqrstuvwxyz'.split('').forEach(h => t.push([h, h.toUpperCase()]));
    '0123456789'.split('').forEach(r => t.push([r, r]));
    return t;
}

// Kısa yardımcılar
function mbmKomut(b, renk) { b.setPreviousStatement(true, null); b.setNextStatement(true, null); b.setColour(renk); }
const R_HAREKET = '#4C97FF', R_GORUNUM = '#9966FF', R_SES = '#CF63CF', R_OLAY = '#FFBF00',
      R_KONTROL = '#FFAB19', R_ALGI = '#5CB1D6', R_ISLEM = '#59C059', R_KALEM = '#0fBD8C';

// ============================================================================
// --- 1. HAREKET ---
// ============================================================================
Blockly.Blocks['mblock_hareket_git'] = { init: function () { this.appendValueInput("ADIM").setCheck("Number"); this.appendDummyInput().appendField("adım git"); this.setInputsInline(true); mbmKomut(this, R_HAREKET); } };
Blockly.Blocks['mblock_hareket_don_sag'] = { init: function () { this.appendValueInput("DERECE").setCheck("Number").appendField("sağa ↻"); this.appendDummyInput().appendField("derece dön"); this.setInputsInline(true); mbmKomut(this, R_HAREKET); } };
Blockly.Blocks['mblock_hareket_don_sol'] = { init: function () { this.appendValueInput("DERECE").setCheck("Number").appendField("sola ↺"); this.appendDummyInput().appendField("derece dön"); this.setInputsInline(true); mbmKomut(this, R_HAREKET); } };
Blockly.Blocks['mblock_hareket_rastgele_git'] = { init: function () { this.appendDummyInput().appendField(mbmDinamikMenu(() => window.MBM_SECENEKLER.kuklalar(), [["rastgele konum", "RANDOM"], ["fare oku", "MOUSE"]]), "HEDEF").appendField("'a git"); mbmKomut(this, R_HAREKET); } };
Blockly.Blocks['mblock_hareket_xy_git'] = { init: function () { this.appendDummyInput().appendField("x:"); this.appendValueInput("X").setCheck("Number"); this.appendDummyInput().appendField("y:"); this.appendValueInput("Y").setCheck("Number"); this.appendDummyInput().appendField("konumuna git"); this.setInputsInline(true); mbmKomut(this, R_HAREKET); } };
Blockly.Blocks['mblock_hareket_suzul_rastgele'] = { init: function () { this.appendValueInput("SURE").setCheck("Number"); this.appendDummyInput().appendField("sn. de").appendField(mbmDinamikMenu(() => window.MBM_SECENEKLER.kuklalar(), [["rastgele konum", "RANDOM"], ["fare oku", "MOUSE"]]), "HEDEF").appendField("'a süzül"); this.setInputsInline(true); mbmKomut(this, R_HAREKET); } };
Blockly.Blocks['mblock_hareket_suzul_xy'] = { init: function () { this.appendValueInput("SURE").setCheck("Number"); this.appendDummyInput().appendField("sn. de x:"); this.appendValueInput("X").setCheck("Number"); this.appendDummyInput().appendField("y:"); this.appendValueInput("Y").setCheck("Number"); this.appendDummyInput().appendField("'a süzül"); this.setInputsInline(true); mbmKomut(this, R_HAREKET); } };
Blockly.Blocks['mblock_hareket_yone_don'] = { init: function () { this.appendValueInput("YON").setCheck("Number"); this.appendDummyInput().appendField("yönüne dön"); this.setInputsInline(true); mbmKomut(this, R_HAREKET); } };
Blockly.Blocks['mblock_hareket_hedefe_don'] = { init: function () { this.appendDummyInput().appendField(mbmDinamikMenu(() => window.MBM_SECENEKLER.kuklalar(), [["fare oku", "MOUSE"]]), "HEDEF").appendField("'na doğru dön"); mbmKomut(this, R_HAREKET); } };
Blockly.Blocks['mblock_hareket_x_degistir'] = { init: function () { this.appendDummyInput().appendField("x'i"); this.appendValueInput("X").setCheck("Number"); this.appendDummyInput().appendField("değiştir"); this.setInputsInline(true); mbmKomut(this, R_HAREKET); } };
Blockly.Blocks['mblock_hareket_x_yap'] = { init: function () { this.appendDummyInput().appendField("x"); this.appendValueInput("X").setCheck("Number"); this.appendDummyInput().appendField("olsun"); this.setInputsInline(true); mbmKomut(this, R_HAREKET); } };
Blockly.Blocks['mblock_hareket_y_degistir'] = { init: function () { this.appendDummyInput().appendField("y'yi"); this.appendValueInput("Y").setCheck("Number"); this.appendDummyInput().appendField("değiştir"); this.setInputsInline(true); mbmKomut(this, R_HAREKET); } };
Blockly.Blocks['mblock_hareket_y_yap'] = { init: function () { this.appendDummyInput().appendField("y"); this.appendValueInput("Y").setCheck("Number"); this.appendDummyInput().appendField("olsun"); this.setInputsInline(true); mbmKomut(this, R_HAREKET); } };
Blockly.Blocks['mblock_hareket_kenardaysa_sek'] = { init: function () { this.appendDummyInput().appendField("kenara geldiysen sek"); mbmKomut(this, R_HAREKET); } };
Blockly.Blocks['mblock_hareket_donus_stili'] = { init: function () { this.appendDummyInput().appendField("dönüş stilini").appendField(new Blockly.FieldDropdown([["sol-sağ", "SOLSAG"], ["her yöne", "HER"], ["döndürme", "YOK"]]), "STIL").appendField("yap"); mbmKomut(this, R_HAREKET); } };
Blockly.Blocks['mblock_hareket_x_konumu'] = { init: function () { this.appendDummyInput().appendField("x konumu"); this.setOutput(true, "Number"); this.setColour(R_HAREKET); } };
Blockly.Blocks['mblock_hareket_y_konumu'] = { init: function () { this.appendDummyInput().appendField("y konumu"); this.setOutput(true, "Number"); this.setColour(R_HAREKET); } };
Blockly.Blocks['mblock_hareket_yonu'] = { init: function () { this.appendDummyInput().appendField("yönü"); this.setOutput(true, "Number"); this.setColour(R_HAREKET); } };

// ============================================================================
// --- 2. GÖRÜNÜM ---
// ============================================================================
Blockly.Blocks['mblock_gorunum_merhaba_de_sureli'] = { init: function () { this.appendValueInput("MESAJ").setCheck(null); this.appendDummyInput().appendField("de"); this.appendValueInput("SURE").setCheck("Number"); this.appendDummyInput().appendField("saniye"); this.setInputsInline(true); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_merhaba_de'] = { init: function () { this.appendValueInput("MESAJ").setCheck(null); this.appendDummyInput().appendField("de"); this.setInputsInline(true); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_dusun_sureli'] = { init: function () { this.appendValueInput("MESAJ").setCheck(null); this.appendDummyInput().appendField("diye düşün"); this.appendValueInput("SURE").setCheck("Number"); this.appendDummyInput().appendField("saniye"); this.setInputsInline(true); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_dusun'] = { init: function () { this.appendValueInput("MESAJ").setCheck(null); this.appendDummyInput().appendField("diye düşün"); this.setInputsInline(true); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_kiligina_gec'] = { init: function () { this.appendDummyInput().appendField(mbmDinamikMenu(() => window.MBM_SECENEKLER.kostumler()), "KILIK").appendField("kostümüne geç"); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_sonraki_kilik'] = { init: function () { this.appendDummyInput().appendField("sonraki kostüm"); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_dekora_gec'] = { init: function () { this.appendDummyInput().appendField(mbmDinamikMenu(() => window.MBM_SECENEKLER.dekorlar()), "DEKOR").appendField("dekoruna geç"); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_sonraki_dekor'] = { init: function () { this.appendDummyInput().appendField("sonraki dekor"); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_boyut_degistir'] = { init: function () { this.appendDummyInput().appendField("boyutu"); this.appendValueInput("BOYUT").setCheck("Number"); this.appendDummyInput().appendField("birim değiştir"); this.setInputsInline(true); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_boyut_yap'] = { init: function () { this.appendDummyInput().appendField("boyu %"); this.appendValueInput("BOYUT").setCheck("Number"); this.appendDummyInput().appendField("yap"); this.setInputsInline(true); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_renk_etkisi_degistir'] = { init: function () { this.appendDummyInput().appendField(new Blockly.FieldDropdown([["renk", "COLOR"], ["hayalet", "GHOST"]]), "ETKI").appendField("etkisini"); this.appendValueInput("DEGER").setCheck("Number"); this.appendDummyInput().appendField("arttır"); this.setInputsInline(true); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_renk_etkisi_yap'] = { init: function () { this.appendDummyInput().appendField(new Blockly.FieldDropdown([["renk", "COLOR"], ["hayalet", "GHOST"]]), "ETKI").appendField("etkisi"); this.appendValueInput("DEGER").setCheck("Number"); this.appendDummyInput().appendField("olsun"); this.setInputsInline(true); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_gorsel_temizle'] = { init: function () { this.appendDummyInput().appendField("görsel etkileri temizle"); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_goster'] = { init: function () { this.appendDummyInput().appendField("görün"); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_gizle'] = { init: function () { this.appendDummyInput().appendField("gizlen"); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_katmana_git'] = { init: function () { this.appendDummyInput().appendField(new Blockly.FieldDropdown([["en ön", "FRONT"], ["en arka", "BACK"]]), "KATMAN").appendField("katmana git"); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_katman_degistir'] = { init: function () { this.appendDummyInput().appendField(new Blockly.FieldDropdown([["ileri", "FORWARD"], ["geri", "BACKWARD"]]), "YON"); this.appendValueInput("DEGER").setCheck("Number"); this.appendDummyInput().appendField("katman git"); this.setInputsInline(true); mbmKomut(this, R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_kostum_no'] = { init: function () { this.appendDummyInput().appendField("kostüm").appendField(new Blockly.FieldDropdown([["sayı", "NUMBER"], ["isim", "NAME"]]), "TIP"); this.setOutput(true, null); this.setColour(R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_dekor_no'] = { init: function () { this.appendDummyInput().appendField("dekor").appendField(new Blockly.FieldDropdown([["sayı", "NUMBER"], ["isim", "NAME"]]), "TIP"); this.setOutput(true, null); this.setColour(R_GORUNUM); } };
Blockly.Blocks['mblock_gorunum_buyukluk'] = { init: function () { this.appendDummyInput().appendField("büyüklük"); this.setOutput(true, "Number"); this.setColour(R_GORUNUM); } };

// ============================================================================
// --- 3. SES ---
// ============================================================================
Blockly.Blocks['mblock_ses_cal_bitene_kadar'] = { init: function () { this.appendDummyInput().appendField("sesini bitene kadar çal:").appendField(new Blockly.FieldDropdown(MBM_SESLER), "SES"); mbmKomut(this, R_SES); } };
Blockly.Blocks['mblock_ses_cal'] = { init: function () { this.appendDummyInput().appendField("sesini çal:").appendField(new Blockly.FieldDropdown(MBM_SESLER), "SES"); mbmKomut(this, R_SES); } };
Blockly.Blocks['mblock_ses_nota_cal'] = { init: function () { this.appendDummyInput().appendField("🎵 nota çal:").appendField(new Blockly.FieldDropdown(MBM_NOTALAR), "NOTA"); this.appendValueInput("VURUS").setCheck("Number"); this.appendDummyInput().appendField("vuruş"); this.setInputsInline(true); mbmKomut(this, R_SES); } };
Blockly.Blocks['mblock_ses_durdur'] = { init: function () { this.appendDummyInput().appendField("tüm sesleri durdur"); mbmKomut(this, R_SES); } };

// ============================================================================
// --- 4. OLAYLAR ---
// ============================================================================
Blockly.Blocks['mblock_olay_bayrak_tiklandiginda'] = {
    init: function () {
        var bayrakSVG = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTYgMkw2IDIyIiBzdHJva2U9IiM0QjU1NjMiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIi8+CjxwYXRoIGQ9Ik02IDRDNiA0IDEwLjUgLTEgMTUgNEMxOS41IDkgMjIgNiAyMiA2VjE0QzIyIDE0IDE5LjUgMTcgMTUgMTJDMTAuNSA3IDYgMTIgNiAxMlY0WiIgZmlsbD0iIzIyQzU1RSIvPgo8L3N2Zz4=';
        this.appendDummyInput().appendField(new Blockly.FieldImage(bayrakSVG, 24, 24, "Bayrak")).appendField("Yeşil Bayrak Tıklandığında");
        this.setNextStatement(true, null); this.setColour(R_OLAY); this.hat = 'cap';
    }
};
Blockly.Blocks['mblock_olay_tus_basildiginda'] = { init: function () { this.appendDummyInput().appendField(new Blockly.FieldDropdown(mbmTuslar("herhangi bir")), "TUS").appendField("tuşuna basıldığında"); this.setNextStatement(true, null); this.setColour(R_OLAY); this.hat = 'cap'; } };
Blockly.Blocks['mblock_olay_kukla_tiklandiginda'] = { init: function () { this.appendDummyInput().appendField("👆 Bu kukla tıklandığında"); this.setNextStatement(true, null); this.setColour(R_OLAY); this.hat = 'cap'; } };
Blockly.Blocks['mblock_olay_mesaj_alindiginda'] = { init: function () { this.appendDummyInput().appendField("📩").appendField(new Blockly.FieldTextInput("mesaj1"), "MESAJ").appendField("mesajını aldığımda"); this.setNextStatement(true, null); this.setColour(R_OLAY); this.hat = 'cap'; } };
Blockly.Blocks['mblock_olay_mesaj_yayinla'] = { init: function () { this.appendDummyInput().appendField("📢").appendField(new Blockly.FieldTextInput("mesaj1"), "MESAJ").appendField("mesajını yayınla"); mbmKomut(this, R_OLAY); } };

// ============================================================================
// --- 5. KONTROL ---
// ============================================================================
Blockly.Blocks['mblock_kontrol_bekle'] = { init: function () { this.appendValueInput("SURE").setCheck("Number"); this.appendDummyInput().appendField("saniye bekle"); this.setInputsInline(true); mbmKomut(this, R_KONTROL); } };
Blockly.Blocks['mblock_kontrol_tekrarla'] = { init: function () { this.appendValueInput("KEZ").setCheck("Number").appendField("tekrarla"); this.appendStatementInput("YAP").setCheck(null); mbmKomut(this, R_KONTROL); } };
Blockly.Blocks['mblock_kontrol_surekli'] = { init: function () { this.appendDummyInput().appendField("sürekli tekrarla"); this.appendStatementInput("YAP").setCheck(null); this.setPreviousStatement(true, null); this.setNextStatement(false); this.setColour(R_KONTROL); } };
Blockly.Blocks['mblock_kontrol_eger_ise'] = { init: function () { this.appendValueInput("SART").setCheck("Boolean").appendField("eğer"); this.appendDummyInput().appendField("ise"); this.appendStatementInput("YAP").setCheck(null); mbmKomut(this, R_KONTROL); } };
Blockly.Blocks['mblock_kontrol_eger_degilse'] = { init: function () { this.appendValueInput("SART").setCheck("Boolean").appendField("eğer"); this.appendDummyInput().appendField("ise"); this.appendStatementInput("YAP").setCheck(null); this.appendDummyInput().appendField("değilse"); this.appendStatementInput("DEGILSEYAP").setCheck(null); mbmKomut(this, R_KONTROL); } };
Blockly.Blocks['mblock_kontrol_olana_kadar_tekrarla'] = { init: function () { this.appendValueInput("SART").setCheck("Boolean").appendField("olana kadar tekrarla"); this.appendStatementInput("YAP").setCheck(null); mbmKomut(this, R_KONTROL); } };
Blockly.Blocks['mblock_kontrol_olana_kadar_bekle'] = { init: function () { this.appendValueInput("SART").setCheck("Boolean").appendField("olana kadar bekle"); this.setInputsInline(true); mbmKomut(this, R_KONTROL); } };
Blockly.Blocks['mblock_kontrol_durdur'] = { init: function () { this.appendDummyInput().appendField("⛔ durdur:").appendField(new Blockly.FieldDropdown([["tümü", "TUMU"], ["bu betik", "BU"]]), "TUR"); this.setPreviousStatement(true, null); this.setNextStatement(false); this.setColour(R_KONTROL); } };

// ============================================================================
// --- 6. ALGILAMA ---
// ============================================================================
Blockly.Blocks['mblock_algilama_kenara_degiyor_mu'] = { init: function () { this.appendDummyInput().appendField("kenara değiyor mu?"); this.setOutput(true, "Boolean"); this.setColour(R_ALGI); } };
Blockly.Blocks['mblock_algilama_fareye_degiyor_mu'] = { init: function () { this.appendDummyInput().appendField("fare imlecine değiyor mu?"); this.setOutput(true, "Boolean"); this.setColour(R_ALGI); } };
Blockly.Blocks['mblock_algilama_tusa_basili_mi'] = { init: function () { this.appendDummyInput().appendField(new Blockly.FieldDropdown(mbmTuslar("herhangi bir")), "TUS").appendField("tuşuna basılı mı?"); this.setOutput(true, "Boolean"); this.setColour(R_ALGI); } };
Blockly.Blocks['mblock_algilama_degiyor_mu'] = { init: function () { this.appendDummyInput().appendField(mbmDinamikMenu(() => window.MBM_SECENEKLER.kuklalar(), [["fare okuna", "MOUSE"], ["kenara", "EDGE"]]), "HEDEF").appendField("değiyor mu?"); this.setOutput(true, "Boolean"); this.setColour(R_ALGI); } };
Blockly.Blocks['mblock_algilama_renk_degiyor_mu'] = { init: function () { this.appendDummyInput().appendField(new Blockly.FieldDropdown(MBM_RENKLER), "RENK").appendField("rengine değiyor mu?"); this.setOutput(true, "Boolean"); this.setColour(R_ALGI); } };
Blockly.Blocks['mblock_algilama_sor_bekle'] = { init: function () { this.appendValueInput("SORU").setCheck(null).appendField("sor:"); this.appendDummyInput().appendField("ve bekle"); this.setInputsInline(true); mbmKomut(this, R_ALGI); } };
Blockly.Blocks['mblock_algilama_cevap'] = { init: function () { this.appendDummyInput().appendField("cevap"); this.setOutput(true, null); this.setColour(R_ALGI); } };
Blockly.Blocks['mblock_algilama_tus_basili_mi'] = { init: function () { this.appendDummyInput().appendField(new Blockly.FieldDropdown([["boşluk", "SPACE"], ["sağ ok", "RIGHT"], ["sol ok", "LEFT"], ["yukarı ok", "UP"], ["aşağı ok", "DOWN"]]), "TUS").appendField("tuşu basılı mı?"); this.setOutput(true, "Boolean"); this.setColour(R_ALGI); } };
Blockly.Blocks['mblock_algilama_fare_basili_mi'] = { init: function () { this.appendDummyInput().appendField("fareye basılı mı?"); this.setOutput(true, "Boolean"); this.setColour(R_ALGI); } };
Blockly.Blocks['mblock_algilama_fare_x'] = { init: function () { this.appendDummyInput().appendField("farenin x'i"); this.setOutput(true, "Number"); this.setColour(R_ALGI); } };
Blockly.Blocks['mblock_algilama_fare_y'] = { init: function () { this.appendDummyInput().appendField("farenin y'si"); this.setOutput(true, "Number"); this.setColour(R_ALGI); } };
Blockly.Blocks['mblock_algilama_zamanlayici'] = { init: function () { this.appendDummyInput().appendField("⏱️ zamanlayıcı"); this.setOutput(true, "Number"); this.setColour(R_ALGI); } };
Blockly.Blocks['mblock_algilama_zamanlayici_sifirla'] = { init: function () { this.appendDummyInput().appendField("zamanlayıcıyı sıfırla"); mbmKomut(this, R_ALGI); } };

// ============================================================================
// --- 7. İŞLEMLER ---
// ============================================================================
Blockly.Blocks['mblock_islem_aritmetik'] = { init: function () { this.appendValueInput("A").setCheck("Number"); this.appendDummyInput().appendField(new Blockly.FieldDropdown([["+", "ADD"], ["-", "MINUS"], ["*", "MULTIPLY"], ["/", "DIVIDE"]]), "OP"); this.appendValueInput("B").setCheck("Number"); this.setInputsInline(true); this.setOutput(true, "Number"); this.setColour(R_ISLEM); } };
Blockly.Blocks['mblock_islem_karsilastirma'] = { init: function () { this.appendValueInput("A").setCheck(null); this.appendDummyInput().appendField(new Blockly.FieldDropdown([["=", "EQ"], ["≤", "LTE"], ["≥", "GTE"], ["<", "LT"], [">", "GT"]]), "OP"); this.appendValueInput("B").setCheck(null); this.setInputsInline(true); this.setOutput(true, "Boolean"); this.setColour(R_ISLEM); } };
Blockly.Blocks['mblock_islem_rastgele'] = { init: function () { this.appendValueInput("MIN").setCheck("Number"); this.appendDummyInput().appendField("ile"); this.appendValueInput("MAX").setCheck("Number"); this.appendDummyInput().appendField("arasında rastgele sayı"); this.setInputsInline(true); this.setOutput(true, "Number"); this.setColour(R_ISLEM); } };
Blockly.Blocks['mblock_islem_ve_veya'] = { init: function () { this.appendValueInput("A").setCheck("Boolean"); this.appendDummyInput().appendField(new Blockly.FieldDropdown([["ve", "AND"], ["veya", "OR"]]), "OP"); this.appendValueInput("B").setCheck("Boolean"); this.setInputsInline(true); this.setOutput(true, "Boolean"); this.setColour(R_ISLEM); } };
Blockly.Blocks['mblock_islem_degil'] = { init: function () { this.appendDummyInput().appendField("değil"); this.appendValueInput("A").setCheck("Boolean"); this.setInputsInline(true); this.setOutput(true, "Boolean"); this.setColour(R_ISLEM); } };
Blockly.Blocks['mblock_islem_birlestir'] = { init: function () { this.appendValueInput("A").setCheck(null).appendField("birleştir:"); this.appendValueInput("B").setCheck(null); this.setInputsInline(true); this.setOutput(true, "String"); this.setColour(R_ISLEM); } };
Blockly.Blocks['mblock_islem_mod'] = { init: function () { this.appendValueInput("A").setCheck("Number"); this.appendDummyInput().appendField("mod"); this.appendValueInput("B").setCheck("Number"); this.setInputsInline(true); this.setOutput(true, "Number"); this.setColour(R_ISLEM); } };
Blockly.Blocks['mblock_islem_yuvarla'] = { init: function () { this.appendDummyInput().appendField("yuvarla"); this.appendValueInput("NUM").setCheck("Number"); this.setInputsInline(true); this.setOutput(true, "Number"); this.setColour(R_ISLEM); } };

// ============================================================================
// --- 8. KALEM (ÇİZİM) ---
// ============================================================================
Blockly.Blocks['mblock_kalem_temizle'] = { init: function () { this.appendDummyInput().appendField("✏️ tümünü temizle"); mbmKomut(this, R_KALEM); } };
Blockly.Blocks['mblock_kalem_damga'] = { init: function () { this.appendDummyInput().appendField("✏️ damga bas"); mbmKomut(this, R_KALEM); } };
Blockly.Blocks['mblock_kalem_bastir'] = { init: function () { this.appendDummyInput().appendField("✏️ kalemi bastır"); mbmKomut(this, R_KALEM); } };
Blockly.Blocks['mblock_kalem_kaldir'] = { init: function () { this.appendDummyInput().appendField("✏️ kalemi kaldır"); mbmKomut(this, R_KALEM); } };
Blockly.Blocks['mblock_kalem_renk_yap'] = { init: function () { this.appendDummyInput().appendField("✏️ kalem rengini").appendField(new Blockly.FieldDropdown(MBM_RENKLER), "RENK").appendField("yap"); mbmKomut(this, R_KALEM); } };
Blockly.Blocks['mblock_kalem_kalinlik_yap'] = { init: function () { this.appendDummyInput().appendField("✏️ kalem kalınlığını"); this.appendValueInput("KALINLIK").setCheck("Number"); this.appendDummyInput().appendField("yap"); this.setInputsInline(true); mbmKomut(this, R_KALEM); } };
Blockly.Blocks['mblock_kalem_kalinlik_degistir'] = { init: function () { this.appendDummyInput().appendField("✏️ kalem kalınlığını"); this.appendValueInput("KALINLIK").setCheck("Number"); this.appendDummyInput().appendField("değiştir"); this.setInputsInline(true); mbmKomut(this, R_KALEM); } };

// ============================================================================
// --- MERKEZİ MENÜ MOTORU (SINGLE SOURCE OF TRUTH) ---
// ============================================================================
const mbmGS = (ad, deger) => '<value name="' + ad + '"><shadow type="math_number"><field name="NUM">' + deger + '</field></shadow></value>';
const mbmGT = (ad, deger) => '<value name="' + ad + '"><shadow type="text"><field name="TEXT">' + deger + '</field></shadow></value>';
const mbmGB = (tip, ic) => '<block type="' + tip + '">' + (ic || '') + '</block>';

window.MBLOCK_SABITLERI = {
    KATEGORILER: [
        { id: 'Olaylar', renk: R_OLAY, bloklar: ['mblock_olay_bayrak_tiklandiginda', 'mblock_olay_tus_basildiginda', 'mblock_olay_kukla_tiklandiginda', 'mblock_olay_mesaj_alindiginda', 'mblock_olay_mesaj_yayinla'] },
        { id: 'Hareket', renk: R_HAREKET, bloklar: ['mblock_hareket_git', 'mblock_hareket_don_sag', 'mblock_hareket_don_sol', 'mblock_hareket_rastgele_git', 'mblock_hareket_xy_git', 'mblock_hareket_suzul_rastgele', 'mblock_hareket_suzul_xy', 'mblock_hareket_yone_don', 'mblock_hareket_hedefe_don', 'mblock_hareket_x_degistir', 'mblock_hareket_x_yap', 'mblock_hareket_y_degistir', 'mblock_hareket_y_yap', 'mblock_hareket_kenardaysa_sek', 'mblock_hareket_donus_stili', 'mblock_hareket_x_konumu', 'mblock_hareket_y_konumu', 'mblock_hareket_yonu'] },
        { id: 'Görünüm', renk: R_GORUNUM, bloklar: ['mblock_gorunum_merhaba_de_sureli', 'mblock_gorunum_merhaba_de', 'mblock_gorunum_dusun_sureli', 'mblock_gorunum_dusun', 'mblock_gorunum_kiligina_gec', 'mblock_gorunum_sonraki_kilik', 'mblock_gorunum_dekora_gec', 'mblock_gorunum_sonraki_dekor', 'mblock_gorunum_boyut_degistir', 'mblock_gorunum_boyut_yap', 'mblock_gorunum_renk_etkisi_degistir', 'mblock_gorunum_renk_etkisi_yap', 'mblock_gorunum_gorsel_temizle', 'mblock_gorunum_goster', 'mblock_gorunum_gizle', 'mblock_gorunum_katmana_git', 'mblock_gorunum_katman_degistir', 'mblock_gorunum_kostum_no', 'mblock_gorunum_dekor_no', 'mblock_gorunum_buyukluk'] },
        { id: 'Ses', renk: R_SES, bloklar: ['mblock_ses_cal_bitene_kadar', 'mblock_ses_cal', 'mblock_ses_nota_cal', 'mblock_ses_durdur'] },
        { id: 'Kontrol', renk: R_KONTROL, bloklar: ['mblock_kontrol_bekle', 'mblock_kontrol_tekrarla', 'mblock_kontrol_surekli', 'mblock_kontrol_eger_ise', 'mblock_kontrol_eger_degilse', 'mblock_kontrol_olana_kadar_bekle', 'mblock_kontrol_olana_kadar_tekrarla', 'mblock_kontrol_durdur'] },
        { id: 'Algılama', renk: R_ALGI, bloklar: ['mblock_algilama_degiyor_mu', 'mblock_algilama_renk_degiyor_mu', 'mblock_algilama_sor_bekle', 'mblock_algilama_cevap', 'mblock_algilama_tus_basili_mi', 'mblock_algilama_fare_basili_mi', 'mblock_algilama_fare_x', 'mblock_algilama_fare_y', 'mblock_algilama_zamanlayici', 'mblock_algilama_zamanlayici_sifirla'] },
        { id: 'İşlemler', renk: R_ISLEM, bloklar: ['math_number', 'text', 'mblock_islem_aritmetik', 'mblock_islem_karsilastirma', 'mblock_islem_rastgele', 'mblock_islem_ve_veya', 'mblock_islem_degil', 'mblock_islem_birlestir', 'mblock_islem_mod', 'mblock_islem_yuvarla'] },
        { id: 'Kalem', renk: R_KALEM, bloklar: ['mblock_kalem_temizle', 'mblock_kalem_damga', 'mblock_kalem_bastir', 'mblock_kalem_kaldir', 'mblock_kalem_renk_yap', 'mblock_kalem_kalinlik_yap', 'mblock_kalem_kalinlik_degistir'] }
    ],

    SOZLUK: {
        'mblock_olay_bayrak_tiklandiginda': mbmGB('mblock_olay_bayrak_tiklandiginda'),
        'mblock_olay_kukla_tiklandiginda': mbmGB('mblock_olay_kukla_tiklandiginda'),
        'mblock_olay_tus_basildiginda': mbmGB('mblock_olay_tus_basildiginda'),
        'mblock_olay_mesaj_alindiginda': mbmGB('mblock_olay_mesaj_alindiginda'),
        'mblock_olay_mesaj_yayinla': mbmGB('mblock_olay_mesaj_yayinla'),

        'mblock_hareket_git': mbmGB('mblock_hareket_git', mbmGS('ADIM', 10)),
        'mblock_hareket_don_sag': mbmGB('mblock_hareket_don_sag', mbmGS('DERECE', 15)),
        'mblock_hareket_don_sol': mbmGB('mblock_hareket_don_sol', mbmGS('DERECE', 15)),
        'mblock_hareket_rastgele_git': mbmGB('mblock_hareket_rastgele_git'),
        'mblock_hareket_xy_git': mbmGB('mblock_hareket_xy_git', mbmGS('X', 0) + mbmGS('Y', 0)),
        'mblock_hareket_suzul_rastgele': mbmGB('mblock_hareket_suzul_rastgele', mbmGS('SURE', 1)),
        'mblock_hareket_suzul_xy': mbmGB('mblock_hareket_suzul_xy', mbmGS('SURE', 1) + mbmGS('X', 0) + mbmGS('Y', 0)),
        'mblock_hareket_yone_don': mbmGB('mblock_hareket_yone_don', mbmGS('YON', 90)),
        'mblock_hareket_hedefe_don': mbmGB('mblock_hareket_hedefe_don'),
        'mblock_hareket_x_degistir': mbmGB('mblock_hareket_x_degistir', mbmGS('X', 10)),
        'mblock_hareket_y_degistir': mbmGB('mblock_hareket_y_degistir', mbmGS('Y', 10)),
        'mblock_hareket_x_yap': mbmGB('mblock_hareket_x_yap', mbmGS('X', 0)),
        'mblock_hareket_y_yap': mbmGB('mblock_hareket_y_yap', mbmGS('Y', 0)),
        'mblock_hareket_kenardaysa_sek': mbmGB('mblock_hareket_kenardaysa_sek'),
        'mblock_hareket_donus_stili': mbmGB('mblock_hareket_donus_stili'),
        'mblock_hareket_x_konumu': mbmGB('mblock_hareket_x_konumu'),
        'mblock_hareket_y_konumu': mbmGB('mblock_hareket_y_konumu'),
        'mblock_hareket_yonu': mbmGB('mblock_hareket_yonu'),

        'mblock_gorunum_merhaba_de_sureli': mbmGB('mblock_gorunum_merhaba_de_sureli', mbmGT('MESAJ', 'Merhaba!') + mbmGS('SURE', 2)),
        'mblock_gorunum_merhaba_de': mbmGB('mblock_gorunum_merhaba_de', mbmGT('MESAJ', 'Merhaba!')),
        'mblock_gorunum_dusun_sureli': mbmGB('mblock_gorunum_dusun_sureli', mbmGT('MESAJ', 'Hımm...') + mbmGS('SURE', 2)),
        'mblock_gorunum_dusun': mbmGB('mblock_gorunum_dusun', mbmGT('MESAJ', 'Hımm...')),
        'mblock_gorunum_kiligina_gec': mbmGB('mblock_gorunum_kiligina_gec'),
        'mblock_gorunum_sonraki_kilik': mbmGB('mblock_gorunum_sonraki_kilik'),
        'mblock_gorunum_dekora_gec': mbmGB('mblock_gorunum_dekora_gec'),
        'mblock_gorunum_sonraki_dekor': mbmGB('mblock_gorunum_sonraki_dekor'),
        'mblock_gorunum_boyut_degistir': mbmGB('mblock_gorunum_boyut_degistir', mbmGS('BOYUT', 10)),
        'mblock_gorunum_boyut_yap': mbmGB('mblock_gorunum_boyut_yap', mbmGS('BOYUT', 100)),
        'mblock_gorunum_renk_etkisi_degistir': mbmGB('mblock_gorunum_renk_etkisi_degistir', mbmGS('DEGER', 25)),
        'mblock_gorunum_renk_etkisi_yap': mbmGB('mblock_gorunum_renk_etkisi_yap', mbmGS('DEGER', 0)),
        'mblock_gorunum_gorsel_temizle': mbmGB('mblock_gorunum_gorsel_temizle'),
        'mblock_gorunum_goster': mbmGB('mblock_gorunum_goster'),
        'mblock_gorunum_gizle': mbmGB('mblock_gorunum_gizle'),
        'mblock_gorunum_katmana_git': mbmGB('mblock_gorunum_katmana_git'),
        'mblock_gorunum_katman_degistir': mbmGB('mblock_gorunum_katman_degistir', mbmGS('DEGER', 1)),
        'mblock_gorunum_kostum_no': mbmGB('mblock_gorunum_kostum_no'),
        'mblock_gorunum_dekor_no': mbmGB('mblock_gorunum_dekor_no'),
        'mblock_gorunum_buyukluk': mbmGB('mblock_gorunum_buyukluk'),

        'mblock_ses_cal_bitene_kadar': mbmGB('mblock_ses_cal_bitene_kadar'),
        'mblock_ses_cal': mbmGB('mblock_ses_cal'),
        'mblock_ses_nota_cal': mbmGB('mblock_ses_nota_cal', mbmGS('VURUS', 1)),
        'mblock_ses_durdur': mbmGB('mblock_ses_durdur'),

        'mblock_kontrol_bekle': mbmGB('mblock_kontrol_bekle', mbmGS('SURE', 1)),
        'mblock_kontrol_tekrarla': mbmGB('mblock_kontrol_tekrarla', mbmGS('KEZ', 10)),
        'mblock_kontrol_surekli': mbmGB('mblock_kontrol_surekli'),
        'mblock_kontrol_eger_ise': mbmGB('mblock_kontrol_eger_ise'),
        'mblock_kontrol_eger_degilse': mbmGB('mblock_kontrol_eger_degilse'),
        'mblock_kontrol_olana_kadar_bekle': mbmGB('mblock_kontrol_olana_kadar_bekle'),
        'mblock_kontrol_olana_kadar_tekrarla': mbmGB('mblock_kontrol_olana_kadar_tekrarla'),
        'mblock_kontrol_durdur': mbmGB('mblock_kontrol_durdur'),

        'mblock_algilama_degiyor_mu': mbmGB('mblock_algilama_degiyor_mu'),
        'mblock_algilama_renk_degiyor_mu': mbmGB('mblock_algilama_renk_degiyor_mu'),
        'mblock_algilama_sor_bekle': mbmGB('mblock_algilama_sor_bekle', mbmGT('SORU', 'Adın ne?')),
        'mblock_algilama_cevap': mbmGB('mblock_algilama_cevap'),
        'mblock_algilama_tus_basili_mi': mbmGB('mblock_algilama_tus_basili_mi'),
        'mblock_algilama_fare_basili_mi': mbmGB('mblock_algilama_fare_basili_mi'),
        'mblock_algilama_fare_x': mbmGB('mblock_algilama_fare_x'),
        'mblock_algilama_fare_y': mbmGB('mblock_algilama_fare_y'),
        'mblock_algilama_zamanlayici': mbmGB('mblock_algilama_zamanlayici'),
        'mblock_algilama_zamanlayici_sifirla': mbmGB('mblock_algilama_zamanlayici_sifirla'),

        'math_number': '<block type="math_number"><field name="NUM">0</field></block>',
        'text': '<block type="text"><field name="TEXT"></field></block>',
        'mblock_islem_aritmetik': mbmGB('mblock_islem_aritmetik', mbmGS('A', '') + mbmGS('B', '')),
        // [2026-11] Metin gölgesi: "cevap = mavi" gibi kelime karşılaştırması da yazılabilsin (mBlock'taki gibi)
        'mblock_islem_karsilastirma': mbmGB('mblock_islem_karsilastirma', mbmGT('A', '') + mbmGT('B', '50')),
        'mblock_islem_rastgele': mbmGB('mblock_islem_rastgele', mbmGS('MIN', 1) + mbmGS('MAX', 10)),
        'mblock_islem_ve_veya': mbmGB('mblock_islem_ve_veya'),
        'mblock_islem_degil': mbmGB('mblock_islem_degil'),
        'mblock_islem_birlestir': mbmGB('mblock_islem_birlestir', mbmGT('A', 'merhaba ') + mbmGT('B', 'dünya')),
        'mblock_islem_mod': mbmGB('mblock_islem_mod', mbmGS('A', '') + mbmGS('B', '')),
        'mblock_islem_yuvarla': mbmGB('mblock_islem_yuvarla', mbmGS('NUM', '')),

        'mblock_kalem_temizle': mbmGB('mblock_kalem_temizle'),
        'mblock_kalem_damga': mbmGB('mblock_kalem_damga'),
        'mblock_kalem_bastir': mbmGB('mblock_kalem_bastir'),
        'mblock_kalem_kaldir': mbmGB('mblock_kalem_kaldir'),
        'mblock_kalem_renk_yap': mbmGB('mblock_kalem_renk_yap'),
        'mblock_kalem_kalinlik_yap': mbmGB('mblock_kalem_kalinlik_yap', mbmGS('KALINLIK', 3)),
        'mblock_kalem_kalinlik_degistir': mbmGB('mblock_kalem_kalinlik_degistir', mbmGS('KALINLIK', 1))
    }
};

/**
 * Menü üretici. Admin 'HEPSI' ister, öğrenci görevin izin listesini verir.
 * Listede 'DEGISKENLER' varsa (veya HEPSI ise) Değişkenler kategorisi eklenir.
 */
window.toolboxOlustur = function (izinVerilenler = 'HEPSI') {
    const hepsi = (izinVerilenler === 'HEPSI');
    let xml = '<xml id="toolbox" style="display: none">';

    window.MBLOCK_SABITLERI.KATEGORILER.forEach(kat => {
        let icerik = '';
        kat.bloklar.forEach(b => {
            if (hepsi || izinVerilenler.includes(b)) {
                if (window.MBLOCK_SABITLERI.SOZLUK[b]) icerik += window.MBLOCK_SABITLERI.SOZLUK[b];
            }
        });
        if (icerik !== '') xml += `<category name="${kat.id}" colour="${kat.renk}">${icerik}</category>`;
    });

    if (hepsi || izinVerilenler.includes('DEGISKENLER')) {
        xml += '<category name="Değişkenler" custom="VARIABLE" colour="#FF8C1A"></category>';
    }
    xml += '</xml>';
    return xml;
};
