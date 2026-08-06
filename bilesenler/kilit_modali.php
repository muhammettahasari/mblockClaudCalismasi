<?php
/**
 * ============================================================================
 * MODÜL: Ders Kilidi Penceresi (partial)
 * DOSYA YOLU: bilesenler/kilit_modali.php
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Öğretmen "sınıfı kilitle" dediğinde açılan pencere. Ne olacağını anlatır,
 * hazır notları tek tıkla seçtirir, öğretmen isterse kendi notunu yazar ve
 * kaydeder. Kaydettiği not bir dahaki sefere hazır olarak karşısına çıkar.
 *
 * NEDEN TARAYICININ confirm() / prompt() KUTUSU KULLANILMIYOR?
 *   1. Üst üste iki kutu çıkıyordu: önce "emin misin", sonra "notu yaz".
 *      İkincisini iptal eden öğretmen kilidin kurulup kurulmadığını bilemiyordu.
 *   2. O kutular biçimlendirilemez; sınıfa yansıtılan ekranda "localhost web
 *      sitesinin mesajı" yazısı çıkıyordu — öğrenciye kurumsal görünmüyor.
 *   3. Hazır not, kaydetme, silme gibi hiçbir şey eklenemiyordu.
 *   4. Bazı tarayıcılar arka arkaya gelen kutularda "bu sayfa daha fazla
 *      iletişim kutusu göstermesin" onay kutusu çıkarıyor; öğretmen bir kez
 *      işaretlerse prompt() sessizce null döner ve not hiç sorulmaz.
 *
 * KULLANIM (öğretmen sayfalarının </body> etiketinden önce):
 *   <?php $KILIT_MODAL_KOK = '../'; require __DIR__ . '/../bilesenler/kilit_modali.php'; ?>
 *
 * SONRA JAVASCRIPT'TEN:
 *   kilitModaliAc({
 *       islem:   'kilitle' | 'ac' | 'sinif_kilitle' | 'sinif_ac',
 *       hedefId: 12,                 // öğrenci id ya da sınıf id
 *       hedefAd: 'Ayşe Yılmaz',      // başlıkta görünür
 *       onTamam: function (cevap) { ... }   // işlem başarılıysa çağrılır
 *   });
 *
 * İSTEĞE BAĞLI DEĞİŞKEN:
 *   $KILIT_MODAL_KOK — proje köküne çıkan yol (verilmezse '' yani kök)
 *
 * BAĞLI API'LER:
 *   api/ogrenci_kilit.php     kilitle / ac / sinif_kilitle / sinif_ac
 *   api/kilit_mesajlari.php   liste / ekle / sil
 *
 * 🔒 GÜVENLİK NOTU:
 * Bu pencere sadece arayüz. Yetki kontrolü sunucuda: öğretmen başka okulun
 * öğrencisinin id'sini elle yazsa bile api/ogrenci_kilit.php 403 döner.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu; confirm() + prompt() ikilisi kaldırıldı.
 * ============================================================================
 */

$KILIT_MODAL_KOK = $KILIT_MODAL_KOK ?? '';
?>
<style>
/* ============================================================================
   DERS KİLİDİ PENCERESİ
   Öğretmen teması (css/ogretmen_tema.css) yüklü olmayabilir diye renkler
   burada kendi başına tanımlı; sayfa değişkenleri varsa onlar kullanılıyor.
   ========================================================================= */
#kilitModalPerde {
    position: fixed; inset: 0; z-index: 1200;
    display: none; align-items: center; justify-content: center;
    padding: 18px; background: rgba(15, 23, 42, .58);
    backdrop-filter: blur(3px);
    font-family: 'Segoe UI', Tahoma, sans-serif;
}
#kilitModalPerde.acik { display: flex; }

.km-panel {
    background: #fff; border-radius: 18px;
    width: 540px; max-width: 100%; max-height: 90vh;
    display: flex; flex-direction: column; overflow: hidden;
    box-shadow: 0 26px 70px rgba(15, 23, 42, .38);
    animation: kmGir .22s cubic-bezier(.2, 1.1, .5, 1);
}
@keyframes kmGir {
    from { transform: scale(.95) translateY(12px); opacity: 0; }
    to   { transform: none; opacity: 1; }
}

/* --- Başlık --- */
.km-bas {
    padding: 20px 22px 16px; color: #fff;
    display: flex; align-items: flex-start; gap: 14px;
}
.km-bas.kilit { background: linear-gradient(135deg, #7c3aed, #5b21b6); }
.km-bas.coz   { background: linear-gradient(135deg, #059669, #047857); }
.km-bas .km-ikon { font-size: 30px; line-height: 1; }
.km-bas h3 { margin: 0 0 4px; font-size: 18px; font-weight: 800; }
.km-bas p  { margin: 0; font-size: 13px; line-height: 1.55; color: rgba(255,255,255,.9); }

/* --- Gövde --- */
.km-govde { padding: 18px 22px; overflow-y: auto; }

.km-uyari {
    background: #fef2f2; border: 1px solid #fecaca; color: #7f1d1d;
    border-radius: 11px; padding: 12px 14px; font-size: 13px; line-height: 1.6;
    margin-bottom: 16px;
}
.km-uyari b { color: #991b1b; }

.km-etiket {
    display: block; font-size: 11.5px; font-weight: 800; letter-spacing: .5px;
    text-transform: uppercase; color: #64748b; margin: 0 0 8px;
}
.km-etiket .ipucu { text-transform: none; letter-spacing: 0; font-weight: 600; color: #94a3b8; }

.km-cipler { display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 16px; }
.km-cip {
    display: inline-flex; align-items: center; gap: 6px;
    background: #f1f5f9; border: 1.5px solid #e2e8f0; color: #334155;
    border-radius: 999px; padding: 7px 13px;
    font-size: 13px; font-weight: 600; cursor: pointer;
    transition: .15s; font-family: inherit;
}
.km-cip:hover  { background: #ede9fe; border-color: #c4b5fd; color: #5b21b6; }
.km-cip.secili { background: #6d28d9; border-color: #6d28d9; color: #fff; }
.km-cip .km-sil {
    border: none; background: rgba(0,0,0,.08); color: inherit;
    width: 17px; height: 17px; border-radius: 50%; cursor: pointer;
    font-size: 11px; line-height: 1; padding: 0;
    display: inline-flex; align-items: center; justify-content: center;
}
.km-cip .km-sil:hover { background: #dc2626; color: #fff; }
.km-bos-not { color: #94a3b8; font-size: 12.5px; font-style: italic; margin: 0 0 16px; }

.km-metin {
    width: 100%; min-height: 74px; resize: vertical;
    border: 2px solid #e2e8f0; border-radius: 11px; padding: 11px 13px;
    font-size: 14.5px; font-family: inherit; color: #1e293b; outline: none;
    transition: .15s;
}
.km-metin:focus { border-color: #6d28d9; box-shadow: 0 0 0 3px rgba(109,40,217,.12); }

.km-metin-alt {
    display: flex; justify-content: space-between; align-items: center;
    gap: 10px; margin-top: 8px; flex-wrap: wrap;
}
.km-sayac { font-size: 12px; color: #94a3b8; font-weight: 600; }
.km-sayac.dolu { color: #dc2626; }
.km-kaydet {
    background: #ecfdf5; border: 1.5px solid #a7f3d0; color: #047857;
    border-radius: 8px; padding: 6px 12px; font-size: 12.5px; font-weight: 700;
    cursor: pointer; font-family: inherit; transition: .15s;
}
.km-kaydet:hover:not(:disabled) { background: #d1fae5; }
.km-kaydet:disabled { opacity: .45; cursor: not-allowed; }

/* --- Alt düğmeler --- */
.km-alt {
    display: flex; gap: 10px; padding: 16px 22px;
    border-top: 1px solid #e2e8f0; background: #f8fafc;
}
.km-btn {
    flex: 1; padding: 12px; border: none; border-radius: 10px;
    font-size: 14.5px; font-weight: 800; cursor: pointer;
    font-family: inherit; transition: .15s;
}
.km-btn:disabled { opacity: .6; cursor: wait; }
.km-btn-iptal { background: #fff; border: 1.5px solid #cbd5e1; color: #475569; flex: 0 0 130px; }
.km-btn-iptal:hover:not(:disabled) { background: #f1f5f9; }
.km-btn-onay  { background: #7c3aed; color: #fff; }
.km-btn-onay:hover:not(:disabled) { background: #6d28d9; }
.km-btn-onay.coz { background: #059669; }
.km-btn-onay.coz:hover:not(:disabled) { background: #047857; }

/* --- Geri bildirim şeridi --- */
.km-durum {
    display: none; margin: 0 22px 16px; border-radius: 10px;
    padding: 11px 14px; font-size: 13.5px; font-weight: 700;
}
.km-durum.gorunur { display: block; }
.km-durum.basari { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
.km-durum.hata   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

@media (max-width: 560px) {
    .km-alt { flex-direction: column-reverse; }
    .km-btn-iptal { flex: 1 1 auto; }
}
</style>

<div id="kilitModalPerde" role="dialog" aria-modal="true" aria-labelledby="kmBaslik">
    <div class="km-panel">
        <div class="km-bas kilit" id="kmBas">
            <span class="km-ikon" id="kmIkon">⏸️</span>
            <div>
                <h3 id="kmBaslik">Ekranı Kilitle</h3>
                <p id="kmAltBaslik"></p>
            </div>
        </div>

        <div class="km-govde">
            <div class="km-uyari" id="kmUyari"></div>

            <div id="kmMesajBolumu">
                <span class="km-etiket">
                    Hazır notlar
                    <span class="ipucu">— tıkla, alttaki kutuya düşsün</span>
                </span>
                <div class="km-cipler" id="kmHazirCipler"></div>

                <span class="km-etiket">Senin kayıtlı notların</span>
                <div class="km-cipler" id="kmKendiCipler"></div>
                <p class="km-bos-not" id="kmBosNot">
                    Henüz kayıtlı notun yok. Aşağıya yaz ve <b>💾 Kaydet</b>'e bas; bir dahaki sefere burada çıkar.
                </p>

                <span class="km-etiket">Öğrenciye görünecek not <span class="ipucu">— isteğe bağlı</span></span>
                <textarea id="kmMetin" class="km-metin" maxlength="160"
                          placeholder="Örn: Tahtaya bakın, örneği birlikte çözeceğiz."></textarea>
                <div class="km-metin-alt">
                    <span class="km-sayac" id="kmSayac">0 / 160</span>
                    <button type="button" class="km-kaydet" id="kmKaydetBtn" disabled>💾 Bu notu kaydet</button>
                </div>
            </div>
        </div>

        <div class="km-durum" id="kmDurum"></div>

        <div class="km-alt">
            <button type="button" class="km-btn km-btn-iptal" id="kmIptalBtn">İptal</button>
            <button type="button" class="km-btn km-btn-onay"  id="kmOnayBtn">⏸️ Kilitle</button>
        </div>
    </div>
</div>

<script>
/* ============================================================================
   DERS KİLİDİ PENCERESİ — tarayıcı tarafı
   Tek bir global fonksiyon açar: kilitModaliAc(ayar)
   ========================================================================= */
(function () {
    'use strict';

    var KOK = <?= json_encode($KILIT_MODAL_KOK, JSON_UNESCAPED_SLASHES) ?>;

    /* Herkese gelen hazır notlar. Veritabanında DEĞİL, burada:
       her yeni öğretmen için kopyalamak gerekmesin ve metni düzeltmek
       tek satır olsun. Öğretmenin kendi notları API'den gelir. */
    var HAZIR = [
        '👀 Tahtaya bakın!',
        '✋ Ellerinizi klavyeden çekin',
        '🔔 Teneffüs',
        '☕ Kısa bir mola',
        '🤫 Sessiz olun, uyarı!',
        '📝 Şimdi anlatıyorum, dinleyin',
        '🧹 Toplanma vakti'
    ];

    /* Her işlemin metni tek yerde — pencere bunlara göre şekil alıyor. */
    var METIN = {
        kilitle: {
            ikon: '⏸️', renk: 'kilit', mesajVar: true,
            baslik: function (ad) { return ad + ' — Ekranı Kilitle'; },
            alt: 'Bu öğrencinin ekranı donacak.',
            uyari: 'Öğrencinin ekranında büyük bir kilit çıkacak, arka plan bulanıklaşacak. ' +
                   '<b>Sen açana kadar</b> görev tamamlayamaz, market alışverişi yapamaz. ' +
                   'Sayfayı yenilese, çıkıp girse, başka tarayıcı açsa bile kilit durur.',
            onay: '⏸️ Kilitle'
        },
        ac: {
            ikon: '▶️', renk: 'coz', mesajVar: false,
            baslik: function (ad) { return ad + ' — Kilidi Çöz'; },
            alt: 'Bu öğrenci kaldığı yerden devam edecek.',
            uyari: 'Kilit kalkacak ve öğrenci sayfayı yenilemeden çalışmaya devam edebilecek. ' +
                   'Şifreyi 3 kez yanlış girdiği için oluşan <b>ceza kilidi ayrı bir şeydir</b>, o kalkmaz.',
            onay: '▶️ Kilidi Çöz'
        },
        sinif_kilitle: {
            ikon: '🔒', renk: 'kilit', mesajVar: true,
            baslik: function (ad) { return '"' + ad + '" sınıfının TAMAMI kilitlenecek'; },
            alt: 'Sınıftaki onaylı bütün öğrenciler etkilenir.',
            uyari: 'Bütün öğrencilerin ekranında kilit çıkacak ve <b>sen açana kadar</b> hiçbiri ' +
                   'ilerleme kaydedemeyecek. Sadece bu sınıf etkilenir, diğer sınıfların çalışması sürer.',
            onay: '🔒 Sınıfı Kilitle'
        },
        sinif_ac: {
            ikon: '▶️', renk: 'coz', mesajVar: false,
            baslik: function (ad) { return '"' + ad + '" sınıfının kilidi açılacak'; },
            alt: 'Herkes kaldığı yerden devam edecek.',
            uyari: 'Sınıftaki tüm kilitler kalkacak. Öğrencilerin sayfayı yenilemesine gerek yok, ' +
                   'ekranları birkaç saniye içinde kendiliğinden açılır.',
            onay: '▶️ Sınıfı Çöz'
        }
    };

    var perde    = document.getElementById('kilitModalPerde');
    if (!perde) { return; }

    var bas      = document.getElementById('kmBas');
    var ikonEl   = document.getElementById('kmIkon');
    var baslikEl = document.getElementById('kmBaslik');
    var altEl    = document.getElementById('kmAltBaslik');
    var uyariEl  = document.getElementById('kmUyari');
    var bolumEl  = document.getElementById('kmMesajBolumu');
    var hazirEl  = document.getElementById('kmHazirCipler');
    var kendiEl  = document.getElementById('kmKendiCipler');
    var bosNotEl = document.getElementById('kmBosNot');
    var metinEl  = document.getElementById('kmMetin');
    var sayacEl  = document.getElementById('kmSayac');
    var kaydetEl = document.getElementById('kmKaydetBtn');
    var durumEl  = document.getElementById('kmDurum');
    var iptalEl  = document.getElementById('kmIptalBtn');
    var onayEl   = document.getElementById('kmOnayBtn');

    var ayar        = null;     // açık pencerenin ayarları
    var kendiListe  = null;     // API'den gelen kayıtlı notlar (bir kez çekilir)
    var calisiyor   = false;    // istek uçuşta mı

    /* ----------------------------------------------------------------------
       Yardımcılar
       -------------------------------------------------------------------- */
    function istek(dosya, govde) {
        return fetch(KOK + 'api/' + dosya, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(govde)
        }).then(function (r) { return r.json(); });
    }

    function durumGoster(tur, metin) {
        durumEl.className = 'km-durum gorunur ' + tur;
        durumEl.textContent = metin;
    }
    function durumGizle() {
        durumEl.className = 'km-durum';
        durumEl.textContent = '';
    }

    /* ----------------------------------------------------------------------
       Çipler
       -------------------------------------------------------------------- */
    function cipYap(metin, silId) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'km-cip';

        /* textContent — öğretmenin yazdığı not HTML olarak yorumlanmasın */
        var yazi = document.createElement('span');
        yazi.textContent = metin;
        b.appendChild(yazi);

        b.addEventListener('click', function () { metniSec(metin); });

        if (silId) {
            var sil = document.createElement('span');
            sil.className = 'km-sil';
            sil.textContent = '✕';
            sil.title = 'Bu kayıtlı notu sil';
            sil.setAttribute('role', 'button');
            sil.addEventListener('click', function (e) {
                e.stopPropagation();          // çipi seçmesin, sadece silsin
                notSil(silId);
            });
            b.appendChild(sil);
        }
        return b;
    }

    function ciplerCiz() {
        hazirEl.innerHTML = '';
        HAZIR.forEach(function (m) { hazirEl.appendChild(cipYap(m, null)); });

        kendiEl.innerHTML = '';
        var varMi = kendiListe && kendiListe.length > 0;
        if (varMi) {
            kendiListe.forEach(function (k) { kendiEl.appendChild(cipYap(k.mesaj, k.id)); });
        }
        bosNotEl.style.display = varMi ? 'none' : '';
        seciliIsaretle();
    }

    function seciliIsaretle() {
        var simdi = metinEl.value.trim();
        var hepsi = perde.querySelectorAll('.km-cip');
        for (var i = 0; i < hepsi.length; i++) {
            var yazi = hepsi[i].firstChild ? hepsi[i].firstChild.textContent : '';
            hepsi[i].classList.toggle('secili', yazi === simdi && simdi !== '');
        }
    }

    function metniSec(metin) {
        /* Aynı çipe ikinci kez basmak seçimi kaldırsın — yanlış tıklayan
           öğretmen kutuyu elle temizlemek zorunda kalmasın. */
        metinEl.value = (metinEl.value.trim() === metin) ? '' : metin;
        metinGuncelle();
        metinEl.focus();
    }

    function metinGuncelle() {
        var uzunluk = metinEl.value.length;
        sayacEl.textContent = uzunluk + ' / 160';
        sayacEl.classList.toggle('dolu', uzunluk >= 155);

        var yazi = metinEl.value.trim();
        var zatenVar = HAZIR.indexOf(yazi) !== -1 ||
            (kendiListe || []).some(function (k) { return k.mesaj === yazi; });

        kaydetEl.disabled = (yazi === '' || zatenVar);
        kaydetEl.textContent = zatenVar && yazi !== '' ? '✔️ Zaten kayıtlı' : '💾 Bu notu kaydet';
        seciliIsaretle();
    }

    /* ----------------------------------------------------------------------
       Kayıtlı notlar — listele / ekle / sil
       -------------------------------------------------------------------- */
    function notlariCek() {
        if (kendiListe !== null) { ciplerCiz(); return; }   // bir kez yeter

        istek('kilit_mesajlari.php', { islem: 'liste' })
            .then(function (d) {
                kendiListe = (d.success && d.mesajlar) ? d.mesajlar : [];
                ciplerCiz();
                metinGuncelle();
            })
            .catch(function () {
                /* Liste çekilemese bile pencere çalışsın — not yazmak
                   kilidin ön şartı değil. */
                kendiListe = [];
                ciplerCiz();
            });
    }

    function notKaydet() {
        var yazi = metinEl.value.trim();
        if (yazi === '') { return; }

        kaydetEl.disabled = true;
        istek('kilit_mesajlari.php', { islem: 'ekle', mesaj: yazi })
            .then(function (d) {
                if (!d.success) { durumGoster('hata', '❌ ' + (d.mesaj || d.message || 'Kaydedilemedi.')); return; }
                kendiListe = d.mesajlar || [];
                ciplerCiz();
                metinGuncelle();
                durumGoster('basari', '💾 Not kaydedildi, bundan sonra hazır listende.');
                setTimeout(durumGizle, 2500);
            })
            .catch(function () { durumGoster('hata', '❌ Sunucuya ulaşılamadı.'); });
    }

    function notSil(id) {
        istek('kilit_mesajlari.php', { islem: 'sil', id: id })
            .then(function (d) {
                if (!d.success) { durumGoster('hata', '❌ ' + (d.mesaj || d.message || 'Silinemedi.')); return; }
                kendiListe = d.mesajlar || [];
                ciplerCiz();
                metinGuncelle();
            })
            .catch(function () { durumGoster('hata', '❌ Sunucuya ulaşılamadı.'); });
    }

    /* ----------------------------------------------------------------------
       Aç / kapat / uygula
       -------------------------------------------------------------------- */
    function kapat() {
        if (calisiyor) { return; }        // istek uçarken kapanmasın
        perde.classList.remove('acik');
        document.removeEventListener('keydown', klavye);
        ayar = null;
    }

    function klavye(e) {
        if (e.key === 'Escape') { kapat(); }
        /* Ctrl+Enter = onayla. Enter tek başına DEĞİL: not kutusunda
           satır başı yapmak isteyen öğretmen kazara kilit kurmasın. */
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { uygula(); }
    }

    function uygula() {
        if (!ayar || calisiyor) { return; }

        var tarif = METIN[ayar.islem];
        var govde = { islem: ayar.islem };

        if (ayar.islem === 'kilitle' || ayar.islem === 'ac') {
            govde.ogrenci_id = ayar.hedefId;
        } else {
            govde.sinif_id = ayar.hedefId;
        }
        if (tarif.mesajVar) {
            govde.mesaj = metinEl.value.trim();
        }

        calisiyor = true;
        onayEl.disabled = true;
        iptalEl.disabled = true;
        onayEl.textContent = '⏳ Uygulanıyor…';
        durumGizle();

        istek('ogrenci_kilit.php', govde)
            .then(function (d) {
                calisiyor = false;
                iptalEl.disabled = false;

                if (!d.success) {
                    onayEl.disabled = false;
                    onayEl.textContent = tarif.onay;
                    durumGoster('hata', '❌ ' + (d.mesaj || d.message || 'İşlem başarısız.'));
                    return;
                }

                durumGoster('basari', '✅ ' + (d.mesaj || d.message || 'Tamamlandı.'));
                var geri = ayar && ayar.onTamam;
                setTimeout(function () {
                    kapat();
                    if (typeof geri === 'function') { geri(d); }
                }, 750);
            })
            .catch(function () {
                calisiyor = false;
                onayEl.disabled = false;
                iptalEl.disabled = false;
                onayEl.textContent = tarif.onay;
                durumGoster('hata', '❌ Sunucuya ulaşılamadı.');
            });
    }

    /* ----------------------------------------------------------------------
       Dışarıya açılan tek kapı
       -------------------------------------------------------------------- */
    window.kilitModaliAc = function (secenek) {
        var tarif = METIN[secenek && secenek.islem];
        if (!tarif) {
            console.error('kilitModaliAc: bilinmeyen işlem ->', secenek && secenek.islem);
            return;
        }

        ayar = secenek;
        calisiyor = false;

        ikonEl.textContent = tarif.ikon;
        bas.className = 'km-bas ' + tarif.renk;
        baslikEl.textContent = tarif.baslik(secenek.hedefAd || '');
        altEl.textContent = tarif.alt;
        uyariEl.innerHTML = tarif.uyari;          // sabit metin, kullanıcı verisi yok

        bolumEl.style.display = tarif.mesajVar ? '' : 'none';

        onayEl.textContent = tarif.onay;
        onayEl.disabled = false;
        onayEl.classList.toggle('coz', tarif.renk === 'coz');
        iptalEl.disabled = false;

        metinEl.value = '';
        metinGuncelle();
        durumGizle();

        perde.classList.add('acik');
        document.addEventListener('keydown', klavye);

        if (tarif.mesajVar) {
            notlariCek();
            setTimeout(function () { metinEl.focus(); }, 60);
        } else {
            setTimeout(function () { onayEl.focus(); }, 60);
        }
    };

    /* --- Olay bağlantıları --- */
    iptalEl.addEventListener('click', kapat);
    onayEl.addEventListener('click', uygula);
    kaydetEl.addEventListener('click', notKaydet);
    metinEl.addEventListener('input', metinGuncelle);
    perde.addEventListener('mousedown', function (e) {
        if (e.target === perde) { kapat(); }   // boşluğa tıkla, kapansın
    });
})();
</script>
