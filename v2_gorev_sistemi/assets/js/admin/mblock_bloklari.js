/**
 * DOSYA YOLU: v2_gorev_sistemi/assets/js/admin/mblock_bloklari.js
 * GÜNCELLEME: mBlock'un hayati öneme sahip eksik blokları (X/Y yap, Kukla tıklandığında, 
 * Olana kadar tekrarla, Değil vb.) sisteme eklendi.
 */
// ============================================================================
// --- 1. HAREKET (Tam Sürüm) ---
// ============================================================================
Blockly.Blocks['mblock_hareket_git'] = { init: function() { this.appendValueInput("ADIM").setCheck("Number"); this.appendDummyInput().appendField("adım git"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_don_sag'] = { init: function() { this.appendValueInput("DERECE").setCheck("Number").appendField("sağa ↻"); this.appendDummyInput().appendField("derece dön"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_don_sol'] = { init: function() { this.appendValueInput("DERECE").setCheck("Number").appendField("sola ↺"); this.appendDummyInput().appendField("derece dön"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_rastgele_git'] = { init: function() { this.appendDummyInput().appendField(new Blockly.FieldDropdown([["rastgele konum","RANDOM"], ["fare oku","MOUSE"]]), "HEDEF").appendField("'a git"); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_xy_git'] = { init: function() { this.appendDummyInput().appendField("x:"); this.appendValueInput("X").setCheck("Number"); this.appendDummyInput().appendField("y:"); this.appendValueInput("Y").setCheck("Number"); this.appendDummyInput().appendField("konumuna git"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_suzul_rastgele'] = { init: function() { this.appendValueInput("SURE").setCheck("Number"); this.appendDummyInput().appendField("sn. de").appendField(new Blockly.FieldDropdown([["rastgele konum","RANDOM"], ["fare oku","MOUSE"]]), "HEDEF").appendField("'a süzül"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_suzul_xy'] = { init: function() { this.appendValueInput("SURE").setCheck("Number"); this.appendDummyInput().appendField("sn. de x:"); this.appendValueInput("X").setCheck("Number"); this.appendDummyInput().appendField("y:"); this.appendValueInput("Y").setCheck("Number"); this.appendDummyInput().appendField("'a süzül"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_yone_don'] = { init: function() { this.appendValueInput("YON").setCheck("Number"); this.appendDummyInput().appendField("yönüne dön"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_hedefe_don'] = { init: function() { this.appendDummyInput().appendField(new Blockly.FieldDropdown([["fare oku","MOUSE"]]), "HEDEF").appendField("'na doğru dön"); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_x_degistir'] = { init: function() { this.appendDummyInput().appendField("x'i"); this.appendValueInput("X").setCheck("Number"); this.appendDummyInput().appendField("değiştir"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_x_yap'] = { init: function() { this.appendDummyInput().appendField("x"); this.appendValueInput("X").setCheck("Number"); this.appendDummyInput().appendField("olsun"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_y_degistir'] = { init: function() { this.appendDummyInput().appendField("y'yi"); this.appendValueInput("Y").setCheck("Number"); this.appendDummyInput().appendField("değiştir"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_y_yap'] = { init: function() { this.appendDummyInput().appendField("y"); this.appendValueInput("Y").setCheck("Number"); this.appendDummyInput().appendField("olsun"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_kenardaysa_sek'] = { init: function() { this.appendDummyInput().appendField("kenara geldiysen sek"); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_x_konumu'] = { init: function() { this.appendDummyInput().appendField("x konumu"); this.setOutput(true, "Number"); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_y_konumu'] = { init: function() { this.appendDummyInput().appendField("y konumu"); this.setOutput(true, "Number"); this.setColour('#4C97FF'); } };
Blockly.Blocks['mblock_hareket_yonu'] = { init: function() { this.appendDummyInput().appendField("yönü"); this.setOutput(true, "Number"); this.setColour('#4C97FF'); } };

// ============================================================================
// --- 2. GÖRÜNÜM (Resimdeki Tam Liste) ---
// ============================================================================
Blockly.Blocks['mblock_gorunum_merhaba_de_sureli'] = { init: function() { this.appendValueInput("SURE").setCheck("Number"); this.appendDummyInput().appendField("saniye"); this.appendValueInput("MESAJ").setCheck("String"); this.appendDummyInput().appendField("de"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_merhaba_de'] = { init: function() { this.appendValueInput("MESAJ").setCheck("String"); this.appendDummyInput().appendField("de"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_dusun_sureli'] = { init: function() { this.appendValueInput("MESAJ").setCheck("String"); this.appendDummyInput().appendField("diye düşün"); this.appendValueInput("SURE").setCheck("Number"); this.appendDummyInput().appendField("saniye"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_dusun'] = { init: function() { this.appendValueInput("MESAJ").setCheck("String"); this.appendDummyInput().appendField("diye düşün"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_kiligina_gec'] = { init: function() { this.appendDummyInput().appendField(new Blockly.FieldDropdown([["costume1","C1"], ["costume2","C2"]]), "KILIK").appendField("kılığına geç"); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_sonraki_kilik'] = { init: function() { this.appendDummyInput().appendField("sonraki kostüm"); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_dekora_gec'] = { init: function() { this.appendDummyInput().appendField(new Blockly.FieldDropdown([["backdrop1","bg_1"], ["uzay","bg_uzay"], ["çöl","bg_col"]]), "DEKOR").appendField("dekoruna geç"); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_sonraki_dekor'] = { init: function() { this.appendDummyInput().appendField("sonraki dekor"); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_boyut_degistir'] = { init: function() { this.appendValueInput("BOYUT").setCheck("Number"); this.appendDummyInput().appendField("birim değiştir"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_boyut_yap'] = { init: function() { this.appendDummyInput().appendField("boyu %"); this.appendValueInput("BOYUT").setCheck("Number"); this.appendDummyInput().appendField("yap"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_renk_etkisi_degistir'] = { init: function() { this.appendDummyInput().appendField(new Blockly.FieldDropdown([["renk","COLOR"], ["hayalet","GHOST"]]), "ETKI").appendField("etkisini"); this.appendValueInput("DEGER").setCheck("Number"); this.appendDummyInput().appendField("arttır"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_renk_etkisi_yap'] = { init: function() { this.appendDummyInput().appendField(new Blockly.FieldDropdown([["renk","COLOR"], ["hayalet","GHOST"]]), "ETKI").appendField("etkisi"); this.appendValueInput("DEGER").setCheck("Number"); this.appendDummyInput().appendField("olsun"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_gorsel_temizle'] = { init: function() { this.appendDummyInput().appendField("görsel etkileri temizle"); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_goster'] = { init: function() { this.appendDummyInput().appendField("görün"); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_gizle'] = { init: function() { this.appendDummyInput().appendField("gizlen"); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_katmana_git'] = { init: function() { this.appendDummyInput().appendField(new Blockly.FieldDropdown([["ön","FRONT"], ["arka","BACK"]]), "KATMAN").appendField("katmanına git"); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_katman_degistir'] = { init: function() { this.appendDummyInput().appendField(new Blockly.FieldDropdown([["ileri","FORWARD"], ["geri","BACKWARD"]]), "YON"); this.appendValueInput("DEGER").setCheck("Number"); this.appendDummyInput().appendField("katmana git"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#9966FF'); } };

// --- Reporter (Yuvarlak) Bloklar ---
Blockly.Blocks['mblock_gorunum_kostum_no'] = { init: function() { this.appendDummyInput().appendField("kostüm").appendField(new Blockly.FieldDropdown([["sayı","NUMBER"], ["isim","NAME"]]), "TIP"); this.setOutput(true, ["Number", "String"]); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_dekor_no'] = { init: function() { this.appendDummyInput().appendField("dekor").appendField(new Blockly.FieldDropdown([["sayı","NUMBER"], ["isim","NAME"]]), "TIP"); this.setOutput(true, ["Number", "String"]); this.setColour('#9966FF'); } };
Blockly.Blocks['mblock_gorunum_buyukluk'] = { init: function() { this.appendDummyInput().appendField("büyüklük"); this.setOutput(true, "Number"); this.setColour('#9966FF'); } };

// --- 3. SES ---
Blockly.Blocks['mblock_ses_cal_bitene_kadar'] = { init: function() { this.appendDummyInput().appendField("sesini bitene kadar çal:").appendField(new Blockly.FieldDropdown([["Miyav","MEOW"], ["Lazer","LASER"]]), "SES"); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#CF63CF'); } };

// --- 4. OLAYLAR ---
// Başlangıç: Blockly.Blocks['mblock_olay_bayrak_tiklandiginda'] satırının yerine yapışacak
// Başlangıç: Blockly.Blocks['mblock_olay_bayrak_tiklandiginda'] satırını bul ve değiştir
Blockly.Blocks['mblock_olay_bayrak_tiklandiginda'] = { 
  init: function() { 
    // Doğrudan koda gömülü (Base64 formatında) Orijinal Yeşil Bayrak SVG'si
    var bayrakSVG = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTYgMkw2IDIyIiBzdHJva2U9IiM0QjU1NjMiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIi8+CjxwYXRoIGQ9Ik02IDRDNiA0IDEwLjUgLTEgMTUgNEMxOS41IDkgMjIgNiAyMiA2VjE0QzIyIDE0IDE5LjUgMTcgMTUgMTJDMTAuNSA3IDYgMTIgNiAxMlY0WiIgZmlsbD0iIzIyQzU1RSIvPgo8L3N2Zz4=';

    this.appendDummyInput()
        .appendField(new Blockly.FieldImage(bayrakSVG, 24, 24, "Bayrak")) // 24x24 px boyutunda SVG'yi ekledik
        .appendField("Yeşil Bayrak Tıklandığında"); 
        
    this.setNextStatement(true, null); 
    this.setColour('#FFBF00'); 
    this.hat = 'cap'; 
  } 
};
// Bitiş: mblock_olay_tus_basildiginda bloğunun hemen üzerinde kalacak

Blockly.Blocks['mblock_olay_tus_basildiginda'] = { 
  init: function() { 
    var tuslar = [
      ["boşluk", "SPACE"],
      ["yukarı ok", "ARROWUP"],
      ["aşağı ok", "ARROWDOWN"],
      ["sağ ok", "ARROWRIGHT"],
      ["sol ok", "ARROWLEFT"],
      ["hiç", "ANY"]
    ];
    
    // Alfabeyi (a-z) otomatik ekle
    "abcdefghijklmnopqrstuvwxyz".split("").forEach(function(harf) { 
        tuslar.push([harf, harf.toUpperCase()]); 
    });
    
    // Rakamları (0-9) otomatik ekle
    "0123456789".split("").forEach(function(rakam) { 
        tuslar.push([rakam, rakam]); 
    });

    this.appendDummyInput()
        .appendField(new Blockly.FieldDropdown(tuslar), "TUS")
        .appendField("tuşuna basıldığında"); 
    this.setNextStatement(true, null); 
    this.setColour('#FFBF00'); 
    this.hat = 'cap'; 
  } 
};

Blockly.Blocks['mblock_olay_kukla_tiklandiginda'] = { 
  init: function() { 
    this.appendDummyInput().appendField("👆 Bu kukla tıklandığında"); 
    this.setNextStatement(true, null); 
    this.setColour('#FFBF00'); 
    this.hat = 'cap'; 
  } 
};
// Bitiş: mblock_olay_kukla_tiklandiginda bloğunun sonundaki }; işaretinin üzerinde kalacak
// Bitiş: mblock_olay_kukla_tiklandiginda bloğunun sonundaki }; işaretinin üzerinde kalacak
// olaylar bitiş
// ==========================================
// --- 5. ALGILAMA ---
// ==========================================

Blockly.Blocks['mblock_algilama_kenara_degiyor_mu'] = {
  init: function() {
    this.appendDummyInput().appendField("kenara değiyor mu?");
    this.setOutput(true, "Boolean"); // SİHİRLİ DOKUNUŞ: Bloğu altıgen yapar
    this.setColour('#5CB1D6');       // Açık mavi (Turkuaz) renk
  }
};

Blockly.Blocks['mblock_algilama_fareye_degiyor_mu'] = {
  init: function() {
    this.appendDummyInput().appendField("fare imlecine değiyor mu?");
    this.setOutput(true, "Boolean"); 
    this.setColour('#5CB1D6');
  }
};

Blockly.Blocks['mblock_algilama_tusa_basili_mi'] = {
  init: function() {
    var tuslar = [
      ["boşluk", "SPACE"],
      ["yukarı ok", "ARROWUP"],
      ["aşağı ok", "ARROWDOWN"],
      ["sağ ok", "ARROWRIGHT"],
      ["sol ok", "ARROWLEFT"],
      ["herhangi bir", "ANY"]
    ];
    
    // Alfabeyi ve Rakamları otomatik ekle
    "abcdefghijklmnopqrstuvwxyz".split("").forEach(function(harf) { tuslar.push([harf, harf.toUpperCase()]); });
    "0123456789".split("").forEach(function(rakam) { tuslar.push([rakam, rakam]); });

    this.appendDummyInput()
        .appendField(new Blockly.FieldDropdown(tuslar), "TUS")
        .appendField("tuşuna basılı mı?");
    this.setOutput(true, "Boolean");
    this.setColour('#5CB1D6');
  }
};
// --- 5. KONTROL ---
Blockly.Blocks['mblock_kontrol_bekle'] = { init: function() { this.appendValueInput("SURE").setCheck("Number"); this.appendDummyInput().appendField("saniye bekle"); this.setInputsInline(true); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#FFAB19'); } };
Blockly.Blocks['mblock_kontrol_tekrarla'] = { init: function() { this.appendValueInput("KEZ").setCheck("Number").appendField("tekrarla"); this.appendStatementInput("YAP").setCheck(null); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#FFAB19'); } };
Blockly.Blocks['mblock_kontrol_surekli'] = { init: function() { this.appendDummyInput().appendField("sürekli tekrarla"); this.appendStatementInput("YAP").setCheck(null); this.setPreviousStatement(true, null); this.setNextStatement(false); this.setColour('#FFAB19'); } };
Blockly.Blocks['mblock_kontrol_eger_ise'] = { init: function() { this.appendValueInput("SART").setCheck("Boolean").appendField("eğer"); this.appendDummyInput().appendField("ise"); this.appendStatementInput("YAP").setCheck(null); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#FFAB19'); } };
Blockly.Blocks['mblock_kontrol_eger_degilse'] = { init: function() { this.appendValueInput("SART").setCheck("Boolean").appendField("eğer"); this.appendDummyInput().appendField("ise"); this.appendStatementInput("YAP").setCheck(null); this.appendDummyInput().appendField("değilse"); this.appendStatementInput("DEGILSEYAP").setCheck(null); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#FFAB19'); } };
Blockly.Blocks['mblock_kontrol_olana_kadar_tekrarla'] = { init: function() { this.appendValueInput("SART").setCheck("Boolean").appendField("olana kadar tekrarla"); this.appendStatementInput("YAP").setCheck(null); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#FFAB19'); } }; // YENİ

// --- 6. ALGILAMA ---
Blockly.Blocks['mblock_algilama_degiyor_mu'] = { init: function() { this.appendDummyInput().appendField(new Blockly.FieldDropdown([["fare okuna","MOUSE"], ["kenara","EDGE"], ["Bug Dragon","DRAGON"]]), "HEDEF").appendField("değiyor mu?"); this.setOutput(true, "Boolean"); this.setColour('#5CB1D6'); } };
Blockly.Blocks['mblock_algilama_renk_degiyor_mu'] = { init: function() { this.appendDummyInput().appendField("rengine değiyor mu?").appendField(new Blockly.FieldDropdown([["🔴 Kırmızı","RED"], ["🔵 Mavi","BLUE"], ["🟢 Yeşil","GREEN"], ["⚫ Siyah","BLACK"], ["⚪ Beyaz","WHITE"]]), "RENK"); this.setOutput(true, "Boolean"); this.setColour('#5CB1D6'); } };
Blockly.Blocks['mblock_algilama_sor_bekle'] = { init: function() { this.appendValueInput("SORU").setCheck("String").appendField("sor:"); this.appendDummyInput().appendField("ve bekle"); this.setPreviousStatement(true, null); this.setNextStatement(true, null); this.setColour('#5CB1D6'); } };
Blockly.Blocks['mblock_algilama_tus_basili_mi'] = { init: function() { this.appendDummyInput().appendField(new Blockly.FieldDropdown([["boşluk","SPACE"], ["sağ ok","RIGHT"], ["sol ok","LEFT"], ["yukarı ok","UP"], ["aşağı ok","DOWN"]]), "TUS").appendField("tuşu basılı mı?"); this.setOutput(true, "Boolean"); this.setColour('#5CB1D6'); } };
Blockly.Blocks['mblock_algilama_fare_basili_mi'] = { init: function() { this.appendDummyInput().appendField("fareye basılı mı?"); this.setOutput(true, "Boolean"); this.setColour('#5CB1D6'); } }; // YENİ
Blockly.Blocks['mblock_algilama_fare_x'] = { init: function() { this.appendDummyInput().appendField("farenin x'i"); this.setOutput(true, "Number"); this.setColour('#5CB1D6'); } };
Blockly.Blocks['mblock_algilama_fare_y'] = { init: function() { this.appendDummyInput().appendField("farenin y'si"); this.setOutput(true, "Number"); this.setColour('#5CB1D6'); } };

// --- 7. İŞLEMLER ---
Blockly.Blocks['mblock_islem_aritmetik'] = { init: function() { this.appendValueInput("A").setCheck("Number"); this.appendDummyInput().appendField(new Blockly.FieldDropdown([["+","ADD"], ["-","MINUS"], ["*","MULTIPLY"], ["/","DIVIDE"]]), "OP"); this.appendValueInput("B").setCheck("Number"); this.setInputsInline(true); this.setOutput(true, "Number"); this.setColour('#59C059'); } };
Blockly.Blocks['mblock_islem_karsilastirma'] = { init: function() { this.appendValueInput("A").setCheck("Number"); this.appendDummyInput().appendField(new Blockly.FieldDropdown([["=", "EQ"], ["\u2264", "LTE"], ["\u2265", "GTE"], ["<", "LT"], [">", "GT"]]), "OP"); this.appendValueInput("B").setCheck("Number"); this.setInputsInline(true); this.setOutput(true, "Boolean"); this.setColour('#59C059'); } };
Blockly.Blocks['mblock_islem_rastgele'] = { init: function() { this.appendValueInput("MIN").setCheck("Number"); this.appendDummyInput().appendField("ile"); this.appendValueInput("MAX").setCheck("Number"); this.appendDummyInput().appendField("arasında rastgele sayı"); this.setInputsInline(true); this.setOutput(true, "Number"); this.setColour('#59C059'); } };
Blockly.Blocks['mblock_islem_ve_veya'] = { init: function() { this.appendValueInput("A").setCheck("Boolean"); this.appendDummyInput().appendField(new Blockly.FieldDropdown([["ve","AND"], ["veya","OR"]]), "OP"); this.appendValueInput("B").setCheck("Boolean"); this.setInputsInline(true); this.setOutput(true, "Boolean"); this.setColour('#59C059'); } };
Blockly.Blocks['mblock_islem_degil'] = { init: function() { this.appendDummyInput().appendField("değil"); this.appendValueInput("A").setCheck("Boolean"); this.setInputsInline(true); this.setOutput(true, "Boolean"); this.setColour('#59C059'); } }; // YENİ
Blockly.Blocks['mblock_islem_birlestir'] = { init: function() { this.appendValueInput("A").setCheck("String").appendField("birleştir:"); this.appendValueInput("B").setCheck("String"); this.setInputsInline(true); this.setOutput(true, "String"); this.setColour('#59C059'); } };
Blockly.Blocks['mblock_islem_mod'] = { init: function() { this.appendValueInput("A").setCheck("Number"); this.appendDummyInput().appendField("mod"); this.appendValueInput("B").setCheck("Number"); this.setInputsInline(true); this.setOutput(true, "Number"); this.setColour('#59C059'); } };
Blockly.Blocks['mblock_islem_yuvarla'] = { init: function() { this.appendDummyInput().appendField("yuvarla"); this.appendValueInput("NUM").setCheck("Number"); this.setInputsInline(true); this.setOutput(true, "Number"); this.setColour('#59C059'); } };

// ============================================================================
// --- MERKEZİ MENÜ MOTORU (SINGLE SOURCE OF TRUTH) ---
// Hem Admin hem de Öğrenci sayfaları menülerini (Toolbox) buradan üretir.
// ============================================================================

window.MBLOCK_SABITLERI = {
    // 1. Orijinal Kategoriler
    KATEGORILER: [
        { id: 'Olaylar', renk: '#FFBF00', bloklar: ['mblock_olay_bayrak_tiklandiginda', 'mblock_olay_tus_basildiginda', 'mblock_olay_kukla_tiklandiginda'] },
        { id: 'Hareket', renk: '#4C97FF', bloklar: ['mblock_hareket_git', 'mblock_hareket_don_sag', 'mblock_hareket_don_sol', 'mblock_hareket_rastgele_git', 'mblock_hareket_xy_git', 'mblock_hareket_suzul_rastgele', 'mblock_hareket_suzul_xy', 'mblock_hareket_yone_don', 'mblock_hareket_hedefe_don', 'mblock_hareket_x_degistir', 'mblock_hareket_x_yap', 'mblock_hareket_y_degistir', 'mblock_hareket_y_yap', 'mblock_hareket_kenardaysa_sek', 'mblock_hareket_x_konumu', 'mblock_hareket_y_konumu', 'mblock_hareket_yonu'] },
        { id: 'Görünüm', renk: '#9966FF', bloklar: ['mblock_gorunum_merhaba_de_sureli', 'mblock_gorunum_merhaba_de', 'mblock_gorunum_dusun_sureli', 'mblock_gorunum_dusun', 'mblock_gorunum_kiligina_gec', 'mblock_gorunum_sonraki_kilik', 'mblock_gorunum_dekora_gec', 'mblock_gorunum_sonraki_dekor', 'mblock_gorunum_boyut_degistir', 'mblock_gorunum_boyut_yap', 'mblock_gorunum_renk_etkisi_degistir', 'mblock_gorunum_renk_etkisi_yap', 'mblock_gorunum_gorsel_temizle', 'mblock_gorunum_goster', 'mblock_gorunum_gizle', 'mblock_gorunum_katmana_git', 'mblock_gorunum_katman_degistir', 'mblock_gorunum_kostum_no', 'mblock_gorunum_dekor_no', 'mblock_gorunum_buyukluk'] },
        { id: 'Ses', renk: '#CF63CF', bloklar: ['mblock_ses_cal_bitene_kadar'] },
        { id: 'Kontrol', renk: '#FFAB19', bloklar: ['mblock_kontrol_bekle', 'mblock_kontrol_tekrarla', 'mblock_kontrol_surekli', 'mblock_kontrol_eger_ise', 'mblock_kontrol_eger_degilse', 'mblock_kontrol_olana_kadar_tekrarla'] },
        { id: 'Algılama', renk: '#5CB1D6', bloklar: ['mblock_algilama_degiyor_mu', 'mblock_algilama_renk_degiyor_mu', 'mblock_algilama_sor_bekle', 'mblock_algilama_tus_basili_mi', 'mblock_algilama_fare_basili_mi', 'mblock_algilama_fare_x', 'mblock_algilama_fare_y'] },
        { id: 'İşlemler', renk: '#59C059', bloklar: ['math_number', 'text', 'mblock_islem_aritmetik', 'mblock_islem_karsilastirma', 'mblock_islem_rastgele', 'mblock_islem_ve_veya', 'mblock_islem_degil', 'mblock_islem_birlestir', 'mblock_islem_mod', 'mblock_islem_yuvarla'] }
    ],

    // 2. Blokların Gölge (Beyaz Balon) XML Karşılıkları
    SOZLUK: {
        'mblock_olay_bayrak_tiklandiginda': '<block type="mblock_olay_bayrak_tiklandiginda"></block>',
        'mblock_olay_kukla_tiklandiginda': '<block type="mblock_olay_kukla_tiklandiginda"></block>',
        'mblock_olay_tus_basildiginda': '<block type="mblock_olay_tus_basildiginda"></block>',
        
        'mblock_hareket_git': '<block type="mblock_hareket_git"><value name="ADIM"><shadow type="math_number"><field name="NUM">10</field></shadow></value></block>',
        'mblock_hareket_don_sag': '<block type="mblock_hareket_don_sag"><value name="DERECE"><shadow type="math_number"><field name="NUM">15</field></shadow></value></block>',
        'mblock_hareket_don_sol': '<block type="mblock_hareket_don_sol"><value name="DERECE"><shadow type="math_number"><field name="NUM">15</field></shadow></value></block>',
        'mblock_hareket_rastgele_git': '<block type="mblock_hareket_rastgele_git"></block>',
        'mblock_hareket_xy_git': '<block type="mblock_hareket_xy_git"><value name="X"><shadow type="math_number"><field name="NUM">0</field></shadow></value><value name="Y"><shadow type="math_number"><field name="NUM">0</field></shadow></value></block>',
        'mblock_hareket_suzul_rastgele': '<block type="mblock_hareket_suzul_rastgele"><value name="SURE"><shadow type="math_number"><field name="NUM">1</field></shadow></value></block>',
        'mblock_hareket_suzul_xy': '<block type="mblock_hareket_suzul_xy"><value name="SURE"><shadow type="math_number"><field name="NUM">1</field></shadow></value><value name="X"><shadow type="math_number"><field name="NUM">0</field></shadow></value><value name="Y"><shadow type="math_number"><field name="NUM">0</field></shadow></value></block>',
        'mblock_hareket_yone_don': '<block type="mblock_hareket_yone_don"><value name="YON"><shadow type="math_number"><field name="NUM">90</field></shadow></value></block>',
        'mblock_hareket_hedefe_don': '<block type="mblock_hareket_hedefe_don"></block>',
        'mblock_hareket_x_degistir': '<block type="mblock_hareket_x_degistir"><value name="X"><shadow type="math_number"><field name="NUM">10</field></shadow></value></block>',
        'mblock_hareket_y_degistir': '<block type="mblock_hareket_y_degistir"><value name="Y"><shadow type="math_number"><field name="NUM">10</field></shadow></value></block>',
        'mblock_hareket_x_yap': '<block type="mblock_hareket_x_yap"><value name="X"><shadow type="math_number"><field name="NUM">0</field></shadow></value></block>',
        'mblock_hareket_y_yap': '<block type="mblock_hareket_y_yap"><value name="Y"><shadow type="math_number"><field name="NUM">0</field></shadow></value></block>',
        'mblock_hareket_kenardaysa_sek': '<block type="mblock_hareket_kenardaysa_sek"></block>',
        'mblock_hareket_x_konumu': '<block type="mblock_hareket_x_konumu"></block>',
        'mblock_hareket_y_konumu': '<block type="mblock_hareket_y_konumu"></block>',
        'mblock_hareket_yonu': '<block type="mblock_hareket_yonu"></block>',
        
        'mblock_gorunum_merhaba_de_sureli': '<block type="mblock_gorunum_merhaba_de_sureli"><value name="MESAJ"><shadow type="text"><field name="TEXT">Merhaba!</field></shadow></value><value name="SURE"><shadow type="math_number"><field name="NUM">2</field></shadow></value></block>',
        'mblock_gorunum_merhaba_de': '<block type="mblock_gorunum_merhaba_de"><value name="MESAJ"><shadow type="text"><field name="TEXT">Merhaba!</field></shadow></value></block>',
        'mblock_gorunum_dusun_sureli': '<block type="mblock_gorunum_dusun_sureli"><value name="MESAJ"><shadow type="text"><field name="TEXT">Hımm...</field></shadow></value><value name="SURE"><shadow type="math_number"><field name="NUM">2</field></shadow></value></block>',
        'mblock_gorunum_dusun': '<block type="mblock_gorunum_dusun"><value name="MESAJ"><shadow type="text"><field name="TEXT">Hımm...</field></shadow></value></block>',
        'mblock_gorunum_kiligina_gec': '<block type="mblock_gorunum_kiligina_gec"></block>',
        'mblock_gorunum_sonraki_kilik': '<block type="mblock_gorunum_sonraki_kilik"></block>',
        'mblock_gorunum_dekora_gec': '<block type="mblock_gorunum_dekora_gec"></block>',
        'mblock_gorunum_sonraki_dekor': '<block type="mblock_gorunum_sonraki_dekor"></block>',
        'mblock_gorunum_boyut_degistir': '<block type="mblock_gorunum_boyut_degistir"><value name="BOYUT"><shadow type="math_number"><field name="NUM">10</field></shadow></value></block>',
        'mblock_gorunum_boyut_yap': '<block type="mblock_gorunum_boyut_yap"><value name="BOYUT"><shadow type="math_number"><field name="NUM">100</field></shadow></value></block>',
        'mblock_gorunum_renk_etkisi_degistir': '<block type="mblock_gorunum_renk_etkisi_degistir"><value name="DEGER"><shadow type="math_number"><field name="NUM">25</field></shadow></value></block>',
        'mblock_gorunum_renk_etkisi_yap': '<block type="mblock_gorunum_renk_etkisi_yap"><value name="DEGER"><shadow type="math_number"><field name="NUM">0</field></shadow></value></block>',
        'mblock_gorunum_gorsel_temizle': '<block type="mblock_gorunum_gorsel_temizle"></block>',
        'mblock_gorunum_goster': '<block type="mblock_gorunum_goster"></block>',
        'mblock_gorunum_gizle': '<block type="mblock_gorunum_gizle"></block>',
        'mblock_gorunum_katmana_git': '<block type="mblock_gorunum_katmana_git"></block>',
        'mblock_gorunum_katman_degistir': '<block type="mblock_gorunum_katman_degistir"><value name="DEGER"><shadow type="math_number"><field name="NUM">1</field></shadow></value></block>',
        'mblock_gorunum_kostum_no': '<block type="mblock_gorunum_kostum_no"></block>',
        'mblock_gorunum_dekor_no': '<block type="mblock_gorunum_dekor_no"></block>',
        'mblock_gorunum_buyukluk': '<block type="mblock_gorunum_buyukluk"></block>',
        
        'mblock_ses_cal_bitene_kadar': '<block type="mblock_ses_cal_bitene_kadar"></block>',
        
        'mblock_kontrol_bekle': '<block type="mblock_kontrol_bekle"><value name="SURE"><shadow type="math_number"><field name="NUM">1</field></shadow></value></block>',
        'mblock_kontrol_tekrarla': '<block type="mblock_kontrol_tekrarla"><value name="KEZ"><shadow type="math_number"><field name="NUM">10</field></shadow></value></block>',
        'mblock_kontrol_surekli': '<block type="mblock_kontrol_surekli"></block>',
        'mblock_kontrol_eger_ise': '<block type="mblock_kontrol_eger_ise"></block>',
        'mblock_kontrol_eger_degilse': '<block type="mblock_kontrol_eger_degilse"></block>',
        'mblock_kontrol_olana_kadar_tekrarla': '<block type="mblock_kontrol_olana_kadar_tekrarla"></block>',
        
        'mblock_algilama_degiyor_mu': '<block type="mblock_algilama_degiyor_mu"></block>',
        'mblock_algilama_renk_degiyor_mu': '<block type="mblock_algilama_renk_degiyor_mu"></block>',
        'mblock_algilama_sor_bekle': '<block type="mblock_algilama_sor_bekle"><value name="SORU"><shadow type="text"><field name="TEXT">Adın ne?</field></shadow></value></block>',
        'mblock_algilama_tus_basili_mi': '<block type="mblock_algilama_tus_basili_mi"></block>',
        'mblock_algilama_fare_basili_mi': '<block type="mblock_algilama_fare_basili_mi"></block>',
        'mblock_algilama_fare_x': '<block type="mblock_algilama_fare_x"></block>',
        'mblock_algilama_fare_y': '<block type="mblock_algilama_fare_y"></block>',

        'math_number': '<block type="math_number"><field name="NUM">0</field></block>',
        'text': '<block type="text"><field name="TEXT"></field></block>',
        'mblock_islem_aritmetik': '<block type="mblock_islem_aritmetik"><value name="A"><shadow type="math_number"><field name="NUM"></field></shadow></value><value name="B"><shadow type="math_number"><field name="NUM"></field></shadow></value></block>',
        'mblock_islem_karsilastirma': '<block type="mblock_islem_karsilastirma"><value name="A"><shadow type="math_number"><field name="NUM">50</field></shadow></value><value name="B"><shadow type="math_number"><field name="NUM">50</field></shadow></value></block>',
        'mblock_islem_rastgele': '<block type="mblock_islem_rastgele"><value name="MIN"><shadow type="math_number"><field name="NUM">1</field></shadow></value><value name="MAX"><shadow type="math_number"><field name="NUM">10</field></shadow></value></block>',
        'mblock_islem_ve_veya': '<block type="mblock_islem_ve_veya"></block>',
        'mblock_islem_degil': '<block type="mblock_islem_degil"></block>',
        'mblock_islem_birlestir': '<block type="mblock_islem_birlestir"><value name="A"><shadow type="text"><field name="TEXT">merhaba </field></shadow></value><value name="B"><shadow type="text"><field name="TEXT">dünya</field></shadow></value></block>',
        'mblock_islem_mod': '<block type="mblock_islem_mod"><value name="A"><shadow type="math_number"><field name="NUM"></field></shadow></value><value name="B"><shadow type="math_number"><field name="NUM"></field></shadow></value></block>',
        'mblock_islem_yuvarla': '<block type="mblock_islem_yuvarla"><value name="NUM"><shadow type="math_number"><field name="NUM"></field></shadow></value></block>'
    }
};

// 3. Menü Üretici Fonksiyon (Admin "HEPSI"ni ister, Öğrenci sadece izin verilenleri)
window.toolboxOlustur = function(izinVerilenler = 'HEPSI') {
    let xml = '<xml id="toolbox" style="display: none">';
    
    window.MBLOCK_SABITLERI.KATEGORILER.forEach(kat => {
        let icerik = '';
        kat.bloklar.forEach(b => {
            if (izinVerilenler === 'HEPSI' || izinVerilenler.includes(b)) {
                if (window.MBLOCK_SABITLERI.SOZLUK[b]) {
                    icerik += window.MBLOCK_SABITLERI.SOZLUK[b];
                }
            }
        });
        if (icerik !== '') {
            xml += `<category name="${kat.id}" colour="${kat.renk}">${icerik}</category>`;
        }
    });
    
    // Değişkenler kategorisini her zaman sona ekle
    xml += '<category name="Değişkenler" custom="VARIABLE" colour="#FF8C1A"></category>';
    xml += '</xml>';
    
    return xml;
};