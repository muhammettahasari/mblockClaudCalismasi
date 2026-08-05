/**
 * Dosya Yolu: v2_gorev_sistemi/assets/js/ogrenci/hakem_motoru.js
 * Kullanıcı Yetkisi ve Bağlı Olduğu Sayfa: Öğrenci -> Görev Oynatıcı Sayfası
 * Temel İşlevler:
 * 1. Canlı Derleme: Bloklar değiştiğinde arka planda sistemi günceller.
 * 2. Snippet Testi: Tıklanan bloğun çalışmasını sağlar ve çakışmaları önler.
 * YENİ EKLENEN ÖZELLİKLER:
 * - [Bugün] 800ms saatli bombası (derlemeZamanlayici) testleri öldürmesin diye düzeltildi.
 * - [Bugün] Oyun çalışırken arka plan derlemesinin oyunu kesmesi engellendi.
 * - [2026-08] GERÇEK HAKEM EKLENDİ (Bilinen Sorunlar #17):
 *     Bu dosya adı "hakem" olmasına rağmen aslında hiç kontrol yapmıyordu,
 *     sadece kodu çalıştırıyordu. Üstelik oynatici.php'deki
 *     "✅ Görevimi Kontrol Et" butonu hakemiCagir() fonksiyonunu çağırıyor
 *     ama böyle bir fonksiyon HİÇBİR YERDE tanımlı değildi — butona basınca
 *     konsola hata düşüyordu. Artık:
 *       - hakemiCagir()  : çözümü sunucuya gönderir, kararı ekrana basar
 *       - ipucuGoster()  : görev metnindeki ipucunu gösterir
 *     Karşılaştırma SUNUCUDA (v2_hakem.php) yapılır; tarayıcıda yapılsaydı
 *     öğrenci konsoldan "kazandım" diyerek bedava puan alabilirdi.
 */

window.derlemeZamanlayici = null; // Kronometreyi artık global yaptık ki her yerden durdurabilelim
window.sinamaModuAktif = false; 

window.canliDerleyiciyiBaslat = function(workspace) {
    workspace.addChangeListener(function(event) {
        
        // 1. TIKLAMA (SNIPPET TESTING) DEDEKTÖRÜ
        let isClick = (event.type === Blockly.Events.CLICK) || (event.type === Blockly.Events.UI && event.element === 'click');
        
        if (isClick && event.blockId) {
            let tiklananBlok = workspace.getBlockById(event.blockId);
            
            if (tiklananBlok) {
                // KESİN ÇÖZÜM 1: Öğrenci test için tıkladıysa, bekleyen 800ms bombasını iptal et!
                clearTimeout(window.derlemeZamanlayici);
                
                window.sinamaModuAktif = true; 
                let uretilenKod = "";

                if (tiklananBlok.type.startsWith("mblock_olay_")) {
                    let altBlok = tiklananBlok.getNextBlock();
                    if (altBlok) {
                        uretilenKod = jsMotoru.blockToCode(altBlok);
                    }
                } else {
                    uretilenKod = jsMotoru.blockToCode(tiklananBlok);
                }
                
                window.sinamaModuAktif = false; 
                
                if (uretilenKod) {
                    try {
                        // KESİN ÇÖZÜM 2: Üst üste test tıklamalarında motorun çıldırmasını önle
                        window.aktifOyunDonguleri = []; 
                        window.oyunMotoruCalisiyor = true; 
                       eval("(async () => {\n" + uretilenKod + "\n})();");
                    } catch (hata) {
                        console.error("Test Çalıştırma Hatası:", hata);
                    }
                }
            }
            return; 
        }

        // 2. NORMAL CANLI DERLEME
        if (event.type === Blockly.Events.BLOCK_MOVE || event.type === Blockly.Events.BLOCK_CHANGE || event.type === Blockly.Events.BLOCK_DELETE || event.type === Blockly.Events.BLOCK_CREATE) {
            clearTimeout(window.derlemeZamanlayici);
            window.derlemeZamanlayici = setTimeout(() => {
                // KESİN ÇÖZÜM 3: Oyun şu an akıyorsa (oynanıyorsa), derleme yapıp oyunu bölme!
                if (!window.oyunMotoruCalisiyor) {
                    window.arkaPlandaDerle(workspace);
                }
            }, 800);
        }
    });
};

// Sessizce kodları okuyan ve JS'ye çeviren işçi
window.arkaPlandaDerle = function(workspace) {
    try {
        window.aktifOyunDonguleri = []; 
        window.oyunTetikleyicileri = { tuslar: {}, bayrak: [], kukla: [] };
        let uretilenKod = jsMotoru.workspaceToCode(workspace);
        console.log("ÜRETİLEN KOD:", uretilenKod);
        eval(uretilenKod);
    } catch (hata) {
        console.error("Canlı Derleme Hatası:", hata);
    }
};

// Ekranda "Yeşil Bayrak" butonuna basıldığında tetikler
window.yesilBayraklariTetikle = function() {
    // KESİN ÇÖZÜM 4: Yeşil bayrağa basınca da 800ms bombasını iptal et
    clearTimeout(window.derlemeZamanlayici);
    
    window.oyunuDurdur(); 
    
    // Son saniye eklenen bloklar varsa, oynamaya başlamadan önce acil derleme yap
    if (window.ogrenciWorkspace) {
        window.arkaPlandaDerle(window.ogrenciWorkspace);
    }
    
    window.oyunMotoruCalisiyor = true; 
    console.log("🟢 Oyun Başladı!");

    if(window.oyunTetikleyicileri && window.oyunTetikleyicileri.bayrak) {
        window.oyunTetikleyicileri.bayrak.forEach(cb => cb());
    }
};

/* ============================================================================
 * GERÇEK HAKEM — "✅ Görevimi Kontrol Et" butonunun beyni
 * [2026-08] Bilinen Sorunlar #17
 * ========================================================================== */

/** Sonuç kutusuna renkli mesaj basar. */
function hakemSonucGoster(mesaj, tur) {
    const kutu = document.getElementById('hakemSonucKutusu');
    if (!kutu) {
        alert(mesaj);
        return;
    }

    const renkler = {
        dogru:   { bg: '#064e3b', kenar: '#059669', yazi: '#a7f3d0' },
        yanlis:  { bg: '#7f1d1d', kenar: '#991b1b', yazi: '#fecaca' },
        bilgi:   { bg: '#1e3a8a', kenar: '#2563eb', yazi: '#bfdbfe' },
        bekle:   { bg: '#334155', kenar: '#475569', yazi: '#cbd5e1' }
    };
    const r = renkler[tur] || renkler.bilgi;

    kutu.style.display      = 'block';
    kutu.style.background   = r.bg;
    kutu.style.border       = '1px solid ' + r.kenar;
    kutu.style.color        = r.yazi;
    kutu.textContent        = mesaj;
}

/**
 * Öğrencinin blok haritasını (XML) sunucuya gönderip kararı alır.
 * Puanı sunucu verir — tarayıcı sadece sonucu gösterir.
 */
window.hakemiCagir = function () {
    const btn = document.getElementById('btnHakem');

    if (!window.v2AktifGorevId) {
        hakemSonucGoster('Şu an açık bir görev yok.', 'bilgi');
        return;
    }
    // Bazı sürümlerde workspace mainWorkspace altında durur — ikisini de dene
    const calismaAlani = window.ogrenciWorkspace
        || (typeof Blockly !== 'undefined' ? Blockly.mainWorkspace : null);

    if (!calismaAlani) {
        hakemSonucGoster('Kod alanı henüz hazır değil, birkaç saniye sonra tekrar dene.', 'bilgi');
        return;
    }

    // Blockly çalışma alanını XML metnine çevir
    let cozumXml = '';
    try {
        const dom = Blockly.Xml.workspaceToDom(calismaAlani);
        cozumXml = Blockly.Xml.domToText(dom);
    } catch (e) {
        console.error('XML üretme hatası:', e);
        hakemSonucGoster('Kodun okunurken bir sorun oluştu.', 'yanlis');
        return;
    }

    if (btn) { btn.disabled = true; }
    hakemSonucGoster('⏳ Çözümün kontrol ediliyor...', 'bekle');

    fetch('../api/v2_gorev_tamamla.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
            gorev_id: window.v2AktifGorevId,
            cozum_xml: cozumXml
        })
    })
    .then(r => r.json())
    .then(data => {
        if (btn) { btn.disabled = false; }

        if (!data.dogru) {
            hakemSonucGoster('❌ ' + (data.mesaj || 'Henüz doğru değil, tekrar dene.'), 'yanlis');
            return;
        }

        hakemSonucGoster('✅ ' + (data.mesaj || 'Doğru!'), 'dogru');

        // Skorboarddaki puanları anında tazele
        if (data.puan_verildi && typeof skorboardSenkronizeEt === 'function') {
            try { skorboardSenkronizeEt(); } catch (e) { /* yoksay */ }
        }

        if (data.bolum_bitti) {
            setTimeout(function () {
                alert('🏆 Bu bölümdeki tüm görevleri bitirdin! Yeni bölüme geçiyorsun.');
                window.location.href = '../../ogrenci_paneli.php';
            }, 1500);
        }
    })
    .catch(err => {
        if (btn) { btn.disabled = false; }
        console.error('Hakem bağlantı hatası:', err);
        hakemSonucGoster('Sunucuya ulaşılamadı. İnternet bağlantını kontrol et.', 'yanlis');
    });
};

/**
 * "💡 Bilgi Al" butonu — görev metnindeki ipucu bloklarını gösterir.
 * (Bu fonksiyon da eksikti; butona basınca hata veriyordu.)
 */
window.ipucuGoster = function () {
    let ipucu = '';

    // Görev paketindeki hikaye metninde "ipucu" geçen blokları topla
    try {
        const paket = window.v2GorevPaketi || null;
        const bloklar = paket && paket.hikaye_metni ? (paket.hikaye_metni.blocks || []) : [];

        bloklar.forEach(function (b) {
            const metin = (b.data && (b.data.text || b.data.caption)) || '';
            if (metin && /ipucu|ipuçları|bilgi/i.test(metin)) {
                ipucu += metin.replace(/<[^>]*>/g, '') + '\n';
            }
        });
    } catch (e) { /* yoksay */ }

    if (!ipucu.trim()) {
        ipucu = 'Bu görev için ayrı bir ipucu tanımlanmamış.\n\n'
              + 'Görev metnini baştan oku, blokları sırayla dizmeyi dene ve '
              + 'her bloğa tıklayarak ne yaptığını gör.';
    }

    hakemSonucGoster('💡 ' + ipucu.trim(), 'bilgi');
};
