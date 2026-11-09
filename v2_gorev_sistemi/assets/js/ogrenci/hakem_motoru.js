/**
 * Dosya Yolu: v2_gorev_sistemi/assets/js/ogrenci/hakem_motoru.js
 * Kullanıcı Yetkisi ve Bağlı Olduğu Sayfa: Öğrenci -> Görev Oynatıcı Sayfası
 * Temel İşlevler:
 * 1. Canlı Derleme: Bloklar değiştiğinde arka planda sistemi günceller.
 * 2. Snippet Testi: Tıklanan bloğun çalışmasını sağlar ve çakışmaları önler.
 * YENİ EKLENEN ÖZELLİKLER:
 * - [Bugün] 800ms saatli bombası (derlemeZamanlayici) testleri öldürmesin diye düzeltildi.
 * - [Bugün] Oyun çalışırken arka plan derlemesinin oyunu kesmesi engellendi.
 * - [2026-11] Derleme ve çalıştırma sahne_motoru.js dosyasına taşındı.
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

window.sinamaModuAktif = false;
/* [2026-11] Canlı derleme, tek blok denemesi ve yeşil bayrak sahne_motoru.js'e
   taşındı (çoklu kukla + mBlock koordinatları). Bu dosyada artık sadece hakem var. */

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

    /* [2026-08] KODU TÜM KUKLALARDAN TOPLA
       Bu sistemde her kuklanın kendi kodu var (kodXml) ve Blockly çalışma
       alanı yalnızca AKTİF kuklanınkini gösterir. Eskiden hakem doğrudan
       çalışma alanını okuyordu; öğrencinin kodu başka bir kuklada kaldıysa
       (ya da kukla değiştirildiyse) hakem boş XML görüyor ve
       "Kod alanında hiç blok görünmüyor" diyordu.
       Artık aktif kuklanın canlı kodu önce kaydediliyor, sonra bütün
       kuklaların blokları tek XML'de birleştirilip gönderiliyor. */
    let cozumXml = '';
    try {
        if (typeof window.tumKuklaKodlariniTopla === 'function') {
            cozumXml = window.tumKuklaKodlariniTopla();
        } else {
            cozumXml = Blockly.Xml.domToText(Blockly.Xml.workspaceToDom(calismaAlani));
        }
    } catch (e) {
        console.error('XML üretme hatası:', e);
        hakemSonucGoster('Kodun okunurken bir sorun oluştu.', 'yanlis');
        return;
    }

    // Hiç blok yoksa sunucuya gitmeden uyar (gereksiz istek atmayalım)
    if (cozumXml.indexOf('<block') === -1) {
        hakemSonucGoster(
            '👋 Kod alanında hiç blok yok!\n\n'
            + 'Soldaki menüden blokları sürükleyip bırak, sonra tekrar dene.',
            'bilgi'
        );
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

        // [2026-08] Sorun ayıklama: sunucu ne okudu? (sadece localhost'ta gelir)
        if (data.teshis) {
            const t = data.teshis;
            console.group('🔍 Hakem teşhisi');
            console.log('Öğrenci blok sayısı :', t.ogrenci_toplam_blok);
            console.log('Anahtar blok sayısı :', t.anahtar_toplam_blok);

            // Yan yana karşılaştırma — hangi blok eksik/fazla hemen görünür
            const enUzun = Math.max(
                (t.ogrenci_bloklar || []).length,
                (t.anahtar_bloklar || []).length
            );
            const tablo = [];
            for (let i = 0; i < enUzun; i++) {
                const o = (t.ogrenci_bloklar || [])[i] || '—';
                const a = (t.anahtar_bloklar || [])[i] || '—';
                tablo.push({ '#': i + 1, 'SENİN KODUN': o, 'CEVAP ANAHTARI': a, 'aynı mı': (o === a ? '✓' : '✗') });
            }
            if (tablo.length) { console.table(tablo); }

            console.log('XML önizleme:', t.gelen_xml_onizleme);
            console.groupEnd();
        }

        if (!data.dogru) {
            hakemSonucGoster('❌ ' + (data.mesaj || 'Henüz doğru değil, tekrar dene.'), 'yanlis');
            return;
        }

        hakemSonucGoster('✅ ' + (data.mesaj || 'Doğru!'), 'dogru');

        // Skorboarddaki puanları anında tazele
        if (data.puan_verildi && typeof skorboardSenkronizeEt === 'function') {
            try { skorboardSenkronizeEt(); } catch (e) { /* yoksay */ }
        }

        /* ====================================================================
         * [2026-08] KUTLAMA + İLERLEME ÇUBUĞU
         *
         * ÖNEMLİ: OTOMATİK YÖNLENDİRME KALDIRILDI.
         * Eskiden bölüm bitince 1.5 saniye sonra öğrenci zorla
         * ogrenci_paneli.php'ye atılıyordu. Artık konfetili kutlama açılır
         * ve öğrenci "Sıradaki Görev" ya da "Burada Kal" diye kendisi seçer.
         * ================================================================== */
        kutlamaVeIlerleme(data);
    })
    .catch(err => {
        if (btn) { btn.disabled = false; }
        console.error('Hakem bağlantı hatası:', err);
        hakemSonucGoster('Sunucuya ulaşılamadı. İnternet bağlantını kontrol et.', 'yanlis');
    });
};

/* ============================================================================
 * KUTLAMA VE İLERLEME TAZELEME
 * [2026-08] İlerleme çubuğu özelliğiyle eklendi.
 *
 * Görev doğru çözüldükten sonra:
 *   1. Üstteki ilerleme şeridini sunucudan tazeler (daire yeşile döner)
 *   2. Duruma göre kutlama kartını açar:
 *        - Tüm görevler bitti  -> 👑 taç
 *        - Bölüm bitti         -> 🏆 kupa
 *        - Ceza görevi kapandı -> 💪 / 🎊
 *        - İlk görev           -> 🎉 "İlk görevini geçtin!"
 *        - Normal görev        -> 🎉
 *
 * KARTTA TEK DÜĞME VAR: "Kapat".
 * Kapatınca sayfa yenilenir; sunucu "sıradaki çözülmemiş görev"i seçtiği için
 * öğrenci otomatik olarak bir sonraki göreve (ceza aldıysa tekrar etmesi
 * gereken göreve) geçer. Kart kapanana kadar hiçbir şey olmaz.
 *
 * Puanı daha önce alınmış bir görev tekrar çözüldüyse kart açılmaz,
 * sadece şerit tazelenir (öğrenci kendi isteğiyle tekrar oynuyordur).
 * ========================================================================== */
function kutlamaVeIlerleme(data) {
    var kutlamaVar = !!data.puan_verildi;

    /* İlerleme çubuğu yüklü değilse (eski sayfa) sadece mesajla yetin */
    if (typeof window.ilerlemeyiTazele !== 'function') {
        if (kutlamaVar && typeof window.kutlamaGoster === 'function') {
            window.kutlamaGoster({
                tur: data.bolum_bitti ? 'bolum' : 'gorev',
                baslik: data.bolum_bitti ? 'Bölümü Bitirdin!' : 'Tebrikler!',
                altMetin: 'Görevi doğru çözdün.',
                puan: (data.puan_odulu != null ? data.puan_odulu : data.kazanilan) || 0,
                elmas: (data.elmas_odulu != null ? data.elmas_odulu : data.kazanilan) || 0
            });
        }
        return;
    }

    window.ilerlemeyiTazele().then(function (v) {
        if (!kutlamaVar || typeof window.kutlamaGoster !== 'function') return;

        var harita = (v && v.harita) || null;
        var cozulenToplam = 0, tumuBitti = false;

        if (harita) {
            cozulenToplam = harita.cozulen_gorev | 0;
        }

        /* [2026-08] "Hepsi bitti mi?" ve "sıradaki görev hangisi?" sorularının
           cevabını SUNUCU verir. Tarayıcının haritadan tahmin etmesi, öğretmen
           yeni görev eklediğinde ya da ceza verdiğinde yanlış sonuç üretiyordu. */
        tumuBitti = !!data.hepsi_bitti;

        /* --- Hangi kutlama? --- */
        var ayar;

        if (tumuBitti) {
            ayar = {
                tur: 'tumu',
                baslik: 'Tüm Görevleri Bitirdin!',
                altMetin: 'Bütün bölümleri tamamladın. Öğretmenin yeni görevler ekleyene kadar '
                        + 'arenada ve markette keyfine bak!'
            };
        } else if (data.bolum_bitti) {
            ayar = {
                tur: 'bolum',
                baslik: 'Bölümü Tamamladın!',
                altMetin: 'Bu bölümdeki bütün görevleri çözdün. Yeni bölümün kilidi açıldı. '
                        + 'Kapat düğmesine basınca sıradaki göreve geçeceksin.'
            };
        } else if (data.tekrar_cozum) {
            /* Öğretmenin geri gönderdiği bir görev tekrar çözüldü:
               puan zaten hiç alınmamıştı, sadece elmas borcu kapanıyor. */
            var cezaKaldi = data.ceza && data.ceza.cezali;
            ayar = {
                tur: 'gorev',
                ikon: cezaKaldi ? '💪' : '🎊',
                baslik: cezaKaldi ? 'Bir Görev Daha Kapandı!' : 'Cezan Kalktı!',
                altMetin: cezaKaldi
                    ? 'Öğretmenin tekrar istediği görevi çözdün. Kalan: '
                      + (data.ceza.gorev_sayisi | 0) + ' görev.'
                    : 'Tekrar etmen gereken bütün görevleri bitirdin. Kırmızı efekt kalktı!'
            };
        } else if (cozulenToplam === 1) {
            ayar = {
                tur: 'gorev',
                baslik: 'İlk Görevini Geçtin!',
                altMetin: 'Harika bir başlangıç. Böyle devam edersen bu işi çözersin.'
            };
        } else {
            ayar = {
                tur: 'gorev',
                baslik: 'Tebrikler!',
                altMetin: 'Görevi doğru çözdün. Kapat düğmesine basınca sıradaki göreve geçeceksin.'
            };
        }

        ayar.puan   = (data.puan_odulu  != null ? data.puan_odulu  : data.kazanilan) || 0;
        ayar.elmas  = (data.elmas_odulu != null ? data.elmas_odulu : data.kazanilan) || 0;

        /* Kart kapanınca SUNUCUNUN söylediği göreve gidilir.
           Bu, cezadan sonra doğru bölüme dönmeyi garantiler.

           [2026-08 HATA DÜZELTMESİ] Eskiden "her şey bittiyse boşuna yenileme"
           diye yenile=false yapılıyordu. Ama ceza görevleri bitip sıradaki
           görev bulunamadığında da bu dala düşülüyor, kart kapanıyor ve ekran
           olduğu gibi kalıyordu; öğrenci elle F5'e basmak zorunda kalıyordu.
           Artık kart HER ZAMAN yönlendirir. Gidilecek görev yoksa bile sayfa
           tazelenir — bu ucuz bir işlem ve ekranın takılı kalmasını engeller. */
        ayar.gorevId = data.sonraki_gorev_id | 0;
        ayar.sonraki = tumuBitti ? '' : (data.sonraki_gorev_ad || '');
        ayar.yenile  = true;

        // Sonuç mesajı okunsun diye kısa bir nefes payı
        setTimeout(function () { window.kutlamaGoster(ayar); }, 450);
    });
}


/* ============================================================================
 * "💡 Bilgi Al" — [2026-08] BU DOSYADAN TAŞINDI
 *
 * Burada görev metninde "ipucu" kelimesi arayan bir TAHMİN motoru vardı.
 * Neredeyse her görevde "bu görev için ayrı bir ipucu tanımlanmamış" diyordu,
 * çünkü tasarlayıcıda ipucu yazılacak bir alan hiç yoktu — düğme vardı,
 * arkasında bir şey yoktu.
 *
 * Artık gerçek bir içerik alanı var (tasarlayıcı 4. Bölge) ve düğmenin
 * tamamı burada:
 *     v2_gorev_sistemi/assets/js/ogrenci/bilgi_al.js
 *
 * O dosya window.ipucuGoster'ı tanımlar ve oynatici.php'de BU DOSYADAN SONRA
 * yüklenir. Buraya ikinci bir tanım yazma — hangisinin kazandığı yükleme
 * sırasına kalır ve bulunması zor bir hataya dönüşür.
 * ========================================================================== */
