/**
 * Dosya Yolu: v2_gorev_sistemi/assets/js/ogrenci/kod_tercumani.js
 * Kullanıcı Yetkisi ve Bağlı Olduğu Sayfa: Öğrenci -> Görev Oynatıcı Sayfası
 * Halk Dilinde İşlevi: Öğrencinin dizdiği yapboz bloklarını (Blockly), tarayıcının 
 * anlayabileceği gerçek JavaScript kodlarına çeviren "Tercüman" dosyasıdır.
 */

let jsMotoru = null;
if (typeof Blockly.JavaScript !== "undefined") {
  jsMotoru = Blockly.JavaScript;
} else if (typeof javascript !== "undefined") {
  jsMotoru = javascript.javascriptGenerator;
}

if (jsMotoru) {
  if (!jsMotoru.forBlock) jsMotoru.forBlock = jsMotoru;

  // ==========================================
  // 1. HAREKET BLOKLARI
  // ==========================================
  jsMotoru.forBlock["mblock_hareket_git"] = function (block) {
    var adim = jsMotoru.valueToCode(block, "ADIM", jsMotoru.ORDER_ATOMIC) || "0";
    return "window.kuklaHareketEt(" + adim + ");\n";
  };

  jsMotoru.forBlock["mblock_hareket_don_sag"] = function (block) {
    var derece = jsMotoru.valueToCode(block, "DERECE", jsMotoru.ORDER_ATOMIC) || "0";
    return "window.kuklaDon(" + derece + ");\n";
  };

  jsMotoru.forBlock["mblock_hareket_don_sol"] = function (block) {
    var derece = jsMotoru.valueToCode(block, "DERECE", jsMotoru.ORDER_ATOMIC) || "15";
    return "window.kuklaDon(-" + derece + ");\n";
  };

  jsMotoru.forBlock["mblock_hareket_rastgele_git"] = function (block) {
    return "window.kuklaRastgeleGit();\n";
  };

  jsMotoru.forBlock["mblock_hareket_xy_git"] = function (block) {
    var x = jsMotoru.valueToCode(block, "X", jsMotoru.ORDER_ATOMIC) || "0";
    var y = jsMotoru.valueToCode(block, "Y", jsMotoru.ORDER_ATOMIC) || "0";
    return "window.kuklaKoordinataGit(" + x + ", " + y + ");\n";
  };

  jsMotoru.forBlock["mblock_hareket_suzul_rastgele"] = function (block) {
    var sure = jsMotoru.valueToCode(block, "SURE", jsMotoru.ORDER_ATOMIC) || "1";
    return "window.kuklaSuzulRastgele(" + sure + ");\n";
  };
  // Görünüm Blokları****************************************************************************
  //==============================================================================================
jsMotoru.forBlock["mblock_gorunum_merhaba_de"] = function (block) {
    var mesaj = jsMotoru.valueToCode(block, "MESAJ", jsMotoru.ORDER_ATOMIC) || '"(Sessizlik)"';
    return "window.kuklaKonus(" + mesaj + ");\n";
  };

  jsMotoru.forBlock["mblock_gorunum_merhaba_de_sureli"] = function (block) {
    var mesaj = jsMotoru.valueToCode(block, "MESAJ", jsMotoru.ORDER_ATOMIC) || '""';
    var sure = jsMotoru.valueToCode(block, "SURE", jsMotoru.ORDER_ATOMIC) || "2";
    // SİHİRLİ DOKUNUŞ: Süreli olanlara await ekledik ki oyun o konuşma bitene kadar beklesin
    return "await window.kuklaKonusSureli(" + mesaj + ", " + sure + ");\n";
  };

  jsMotoru.forBlock["mblock_gorunum_dusun"] = function (block) {
    var mesaj = jsMotoru.valueToCode(block, "MESAJ", jsMotoru.ORDER_ATOMIC) || '"Hımm..."';
    return "window.kuklaDusun(" + mesaj + ");\n";
  };

  jsMotoru.forBlock["mblock_gorunum_dusun_sureli"] = function (block) {
    var mesaj = jsMotoru.valueToCode(block, "MESAJ", jsMotoru.ORDER_ATOMIC) || '"Hımm..."';
    var sure = jsMotoru.valueToCode(block, "SURE", jsMotoru.ORDER_ATOMIC) || "2";
    return "await window.kuklaDusunSureli(" + mesaj + ", " + sure + ");\n";
  };

  // ==========================================
  // 2. KONTROL & MANTIK BLOKLARI
  // ==========================================
  jsMotoru.forBlock["mblock_kontrol_tekrarla"] = function (block) {
    var tekrarSayisi = jsMotoru.valueToCode(block, "KEZ", jsMotoru.ORDER_ATOMIC) || "0";
    var icKodlar = jsMotoru.statementToCode(block, "YAP");
    var degisken = "i_" + block.id.replace(/[^a-zA-Z0-9]/g, "");
    return "for (let " + degisken + " = 0; " + degisken + " < " + tekrarSayisi + "; " + degisken + "++) {\n" + icKodlar + "}\n";
  };

jsMotoru.forBlock["mblock_kontrol_surekli"] = function (block) {
    var icKodlar = jsMotoru.statementToCode(block, "YAP");
    // Motor çalışıyorsa döngüye devam et, ancak tarayıcı çökmesin diye her turda 16 milisaniye (60 FPS) nefes al
    return "while (window.oyunMotoruCalisiyor) {\n" + icKodlar + "\n await window.oyunBekle(0.016);\n}\n";
  };

  jsMotoru.forBlock["mblock_kontrol_eger_ise"] = function (block) {
    var sarti = jsMotoru.valueToCode(block, "SART", jsMotoru.ORDER_ATOMIC) || "false";
    var icKodlar = jsMotoru.statementToCode(block, "YAP");
    return "if (" + sarti + ") {\n" + icKodlar + "}\n";
  };

  
  jsMotoru.forBlock["mblock_kontrol_bekle"] = function (block) {
    var sure = jsMotoru.valueToCode(block, "SURE", jsMotoru.ORDER_ATOMIC) || "1";
    // Sadece konsola yazmak yerine, gerçek zamanlı olarak motoru belirtilen saniye kadar bekletir
    return "await window.oyunBekle(" + sure + ");\n";
  };

  // ==========================================
  // 3. ALGILAMA BLOKLARI (SENSING)
  // ==========================================
  jsMotoru.forBlock["mblock_algilama_kenara_degiyor_mu"] = function (block) {
    return ["window.kenaraDegiyorMu()", jsMotoru.ORDER_ATOMIC];
  };

  jsMotoru.forBlock["mblock_algilama_fareye_degiyor_mu"] = function (block) {
    return ["window.fareyeDegiyorMu()", jsMotoru.ORDER_ATOMIC];
  };

  jsMotoru.forBlock["mblock_algilama_tusa_basili_mi"] = function (block) {
    var tus = block.getFieldValue("TUS") || "SPACE";
    return ["window.tusaBasiliMi('" + tus + "')", jsMotoru.ORDER_ATOMIC];
  };
// Başlangıç: 5. ALGILAMA BLOKLARI bölümünün içine, diğer algılama kodlarının hemen altına EKLE
  
  jsMotoru.forBlock["mblock_algilama_degiyor_mu"] = function (block) {
    // Açılır menüdeki seçimi almayı dener. Eğer menü adını bilmiyorsak varsayılan olarak fareyi algılar.
    var secilen = block.getFieldValue("HEDEF") || block.getFieldValue("NESNE") || block.getFieldValue("SECIM") || "fare";
    
    if (secilen.includes("kenar")) {
        return ["window.kenaraDegiyorMu()", jsMotoru.ORDER_ATOMIC];
    } else {
        return ["window.fareyeDegiyorMu()", jsMotoru.ORDER_ATOMIC];
    }
  };
  // Bitiş

  // ==========================================
  // 6. İŞLEMLER VE MATEMATİK BLOKLARI
  // ==========================================
jsMotoru.forBlock["mblock_islem_aritmetik"] = function (block) {
    var a = jsMotoru.valueToCode(block, "A", jsMotoru.ORDER_ATOMIC) || "0";
    var b = jsMotoru.valueToCode(block, "B", jsMotoru.ORDER_ATOMIC) || "0";
    var op = block.getFieldValue("OP");
    // GÜVENLİK AĞI: Eğer menü okuyamazsa çökmek yerine varsayılan olarak Toplama (+) yapsın
    var operator = {"ADD": "+", "MINUS": "-", "MULTIPLY": "*", "DIVIDE": "/"}[op] || "+"; 
    return [a + " " + operator + " " + b, jsMotoru.ORDER_ARITHMETIC];
  };

  // Başlangıç: assets/js/ogrenci/kod_tercumani.js içindeki eski `jsMotoru.forBlock["mblock_islem_karsilastirma"]` kodunun yerine yapıştır.
jsMotoru.forBlock["mblock_islem_karsilastirma"] = function (block) {
    var a = jsMotoru.valueToCode(block, "A", jsMotoru.ORDER_ATOMIC) || "0";
    var b = jsMotoru.valueToCode(block, "B", jsMotoru.ORDER_ATOMIC) || "0";
    var op = block.getFieldValue("OP");
    // GÜVENLİK AĞI: Eğer menü okuyamazsa çökmek yerine varsayılan olarak Eşittir (==) yapsın
    var operator = {"GT": ">", "LT": "<", "EQ": "==", "LTE": "<=", "GTE": ">="}[op] || "=="; 
    return [a + " " + operator + " " + b, jsMotoru.ORDER_RELATIONAL];
  };
// Bitiş

  jsMotoru.forBlock["mblock_islem_rastgele"] = function (block) {
    var min = jsMotoru.valueToCode(block, "MIN", jsMotoru.ORDER_ATOMIC) || "1";
    var max = jsMotoru.valueToCode(block, "MAX", jsMotoru.ORDER_ATOMIC) || "10";
    return ["Math.floor(Math.random() * (" + max + " - " + min + " + 1)) + Number(" + min + ")", jsMotoru.ORDER_FUNCTION_CALL];
  };

jsMotoru.forBlock["mblock_islem_ve_veya"] = function (block) {
    var a = jsMotoru.valueToCode(block, "A", jsMotoru.ORDER_LOGICAL_AND) || "false";
    var b = jsMotoru.valueToCode(block, "B", jsMotoru.ORDER_LOGICAL_AND) || "false";
    var op = block.getFieldValue("OP");
    // GÜVENLİK AĞI: Eğer menü okuyamazsa çökmek yerine varsayılan olarak VE (&&) yapsın
    var operator = {"AND": "&&", "OR": "||"}[op] || "&&"; 
    var order = op === "AND" ? jsMotoru.ORDER_LOGICAL_AND : jsMotoru.ORDER_LOGICAL_OR;
    return [a + " " + operator + " " + b, order];
  };
  jsMotoru.forBlock["mblock_islem_birlestir"] = function (block) {
    var a = jsMotoru.valueToCode(block, "A", jsMotoru.ORDER_ATOMIC) || '""';
    var b = jsMotoru.valueToCode(block, "B", jsMotoru.ORDER_ATOMIC) || '""';
    return ["String(" + a + ") + String(" + b + ")", jsMotoru.ORDER_ADDITION];
  };

  jsMotoru.forBlock["mblock_islem_mod"] = function (block) {
    var a = jsMotoru.valueToCode(block, "A", jsMotoru.ORDER_ATOMIC) || "0";
    var b = jsMotoru.valueToCode(block, "B", jsMotoru.ORDER_ATOMIC) || "0";
    return [a + " % " + b, jsMotoru.ORDER_MODULUS];
  };

  jsMotoru.forBlock["mblock_islem_yuvarla"] = function (block) {
    var a = jsMotoru.valueToCode(block, "NUM", jsMotoru.ORDER_ATOMIC) || "0";
    return ["Math.round(" + a + ")", jsMotoru.ORDER_FUNCTION_CALL];
  };

  
  // ========================================================================
  // 4. SİHİRLİ SARMALAYICI (SCRUB) - DİSİPLİN MERKEZİ
  // ========================================================================
  jsMotoru.scrub_ = function (block, code, opt_thisOnly) {
    let nextCode = "";
    if (!opt_thisOnly && block.nextConnection && block.nextConnection.targetBlock()) {
      nextCode = jsMotoru.blockToCode(block.nextConnection.targetBlock());
    }

 if (block.type === "mblock_olay_tus_basildiginda") {
      let tus = block.getFieldValue("TUS") || "SPACE";
      return `window.oyunaTusEkle("${tus}", async function() {\n${nextCode}\n});\n`;
    }
    if (block.type === "mblock_olay_bayrak_tiklandiginda") {
      return `window.oyunaBayrakEkle(async function() {\n${nextCode}\n});\n`;
    }
    if (block.type === "mblock_olay_kukla_tiklandiginda") {
      return `window.oyunaKuklaEkle(async function() {\n${nextCode}\n});\n`;
    }

    // Başıboş blokları engelle ama Test Tıklamasına (sinamaModuAktif) izin ver
    if (!block.getParent() && !window.sinamaModuAktif) {
      return ""; 
    }

    return code + nextCode;
  };

  // Şapka bloklarının gövdesini boş bırakıyoruz çünkü scrub_ her şeyi hallediyor.
  jsMotoru.forBlock["mblock_olay_bayrak_tiklandiginda"] = function () { return ""; };
  jsMotoru.forBlock["mblock_olay_tus_basildiginda"] = function () { return ""; };
  jsMotoru.forBlock["mblock_olay_kukla_tiklandiginda"] = function () { return ""; };

} else {
  console.error("KRİTİK HATA: JavaScript çevirmen motoru bulunamadı!");
}