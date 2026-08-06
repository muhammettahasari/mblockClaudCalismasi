<?php
/**
 * ============================================================================
 * MODÜL: Öğretmen Kilidi Perdesi (partial)
 * DOSYA YOLU: bilesenler/ogrenci_kilit_perdesi.php
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Öğretmen dersi dondurduğunda öğrencinin ekranını kaplayan kilit perdesi.
 * Ekranın ortasında büyük bir kilit, arkasındaki her şey bulanık ve
 * tıklanamaz. Öğretmen açana kadar öğrenci hiçbir şey yapamaz.
 *
 * NEDEN SAYFA YENİLENİNCE DE DURUYOR?
 * Kilit bilgisi tarayıcıda değil VERİTABANINDA (`ogrenciler.ogretmen_kilidi`).
 * Bu parça sayfa açılırken PHP tarafında sorguluyor; öğrenci F5'e bassa,
 * çıkıp tekrar girse, hatta başka bir tarayıcı açsa perde yine karşısına
 * çıkıyor. localStorage/sessionStorage KULLANILMADI — onlar temizlenebilir.
 *
 * CANLI AÇILMA:
 * Öğretmen kilidi açtığında öğrencinin sayfayı yenilemesi gerekmez;
 * radar (js/ogrenci_radar.js) 3 saniyede bir kontrol eder ve perde kalkar.
 *
 * ⚠️ TEK BAŞINA YETMEZ — SUNUCU TARAFI DA ENGELLEMELİ
 * Bu perde sadece görsel bir engel. Konsolu bilen bir öğrenci onu silebilir.
 * Gerçek koruma sunucuda: v2_gorev_tamamla.php, satin_al.php gibi yazma
 * yapan uç noktalar kilidi ayrıca kontrol eder (bkz. ogrencininKilidi()).
 *
 * KULLANIM (öğrenci sayfalarının <body> etiketinden sonra):
 *   <?php require __DIR__ . '/bilesenler/ogrenci_kilit_perdesi.php'; ?>
 *
 * DEĞİŞKEN (isteğe bağlı):
 *   $KILIT_KOK — proje köküne çıkan yol (verilmezse '' yani kök varsayılır)
 * ============================================================================
 */

/* ogrencininKilidi() bu dosyada tanımlı — guvenlik.php yeterli DEĞİL.
   (guvenlik.php zaten ilerleme_yardimcilari.php tarafından yükleniyor.) */
require_once __DIR__ . '/ilerleme_yardimcilari.php';
guvenliOturumBaslat();

$KILIT_KOK = $KILIT_KOK ?? '';

/* Öğrenci girişi yoksa (admin önizlemesi vb.) perde hiç basılmasın */
if (empty($_SESSION['ogrenci_id'])) {
    return;
}

$kilit_durum = ogrencininKilidi($pdo, (int) $_SESSION['ogrenci_id']);
?>
<style>
/* ============================================================================
   KİLİT PERDESİ
   ========================================================================= */
#ogrenciKilitPerdesi {
    position: fixed; inset: 0; z-index: 2147483646;
    display: none;
    align-items: center; justify-content: center;
    padding: 20px; text-align: center;
    background: rgba(15, 23, 42, .55);
}
#ogrenciKilitPerdesi.acik { display: flex; }

/* Sayfanın kalanı bulanıklaşır ve tıklanamaz olur */
body.kilit-modu > *:not(#ogrenciKilitPerdesi) {
    filter: blur(7px) saturate(.7);
    pointer-events: none !important;
    user-select: none;
}
body.kilit-modu { overflow: hidden; }

.kilit-kart {
    background: linear-gradient(160deg, #ffffff, #f8fafc);
    border-radius: 22px; padding: 34px 30px 28px;
    width: 380px; max-width: 100%;
    box-shadow: 0 26px 70px rgba(2, 6, 23, .45), 0 0 0 4px rgba(239, 68, 68, .35);
    font-family: 'Segoe UI', Tahoma, sans-serif;
    animation: kilitGir .4s cubic-bezier(.2, 1.2, .5, 1);
}
@keyframes kilitGir {
    from { transform: scale(.85) translateY(16px); opacity: 0; }
    to   { transform: none; opacity: 1; }
}

.kilit-kart .simge {
    font-size: 68px; line-height: 1; margin-bottom: 10px;
    animation: kilitSalla 2.6s ease-in-out infinite;
}
@keyframes kilitSalla {
    0%, 88%, 100% { transform: rotate(0); }
    91% { transform: rotate(-7deg); }
    95% { transform: rotate(7deg); }
}

.kilit-kart h2 { margin: 0 0 8px; font-size: 23px; color: #b91c1c; font-weight: 900; }
.kilit-kart p  { margin: 0; color: #475569; font-size: 14.5px; line-height: 1.6; }

.kilit-not {
    background: #fef2f2; border: 1px solid #fca5a5; color: #7f1d1d;
    border-radius: 11px; padding: 12px 14px; margin-top: 16px;
    font-size: 14px; font-style: italic; text-align: left;
}

.kilit-sure {
    margin-top: 16px; color: #94a3b8; font-size: 12.5px; font-weight: 700;
    display: flex; align-items: center; justify-content: center; gap: 7px;
}
.kilit-nokta {
    width: 8px; height: 8px; border-radius: 50%; background: #ef4444;
    animation: kilitNabiz 1.6s ease-out infinite;
}
@keyframes kilitNabiz {
    0%   { box-shadow: 0 0 0 0 rgba(239, 68, 68, .6); }
    70%  { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}
</style>

<div id="ogrenciKilitPerdesi" class="<?= $kilit_durum['kilitli'] ? 'acik' : '' ?>"
     role="alertdialog" aria-live="assertive">
    <div class="kilit-kart">
        <div class="simge">🔒</div>
        <h2>Ekranın Kilitli</h2>
        <p>Öğretmenin dersi durdurdu.<br>Lütfen <b>tahtaya bak</b> ve öğretmenini dinle.</p>

        <div class="kilit-not" id="kilitNotu" style="<?= empty($kilit_durum['mesaj']) ? 'display:none;' : '' ?>">
            <?= temizle($kilit_durum['mesaj'] ?? '') ?>
        </div>

        <div class="kilit-sure">
            <span class="kilit-nokta"></span>
            <span id="kilitSuresi">Öğretmenin açması bekleniyor…</span>
        </div>
    </div>
</div>

<script src="<?= varlik('js/ogrenci_radar.js', $KILIT_KOK) ?>"></script>
<script>
(function () {
    'use strict';

    var perde   = document.getElementById('ogrenciKilitPerdesi');
    var notKutu = document.getElementById('kilitNotu');
    var sureKutu= document.getElementById('kilitSuresi');
    if (!perde) return;

    /* Sayfa açılırken sunucudan gelen durum */
    var kilitli = <?= $kilit_durum['kilitli'] ? 'true' : 'false' ?>;
    var basladi = <?= (int) ($kilit_durum['saniye'] ?? 0) ?>;   // kaç saniyedir kilitli
    var sayac   = null;

    function kacir(m) { var d = document.createElement('div'); d.textContent = m || ''; return d.innerHTML; }

    function sureYaz() {
        basladi++;
        var dk = Math.floor(basladi / 60), sn = basladi % 60;
        sureKutu.textContent = dk > 0
            ? (dk + ' dakika ' + sn + ' saniyedir kilitli')
            : (sn + ' saniyedir kilitli');
    }

    function perdeyiAc(mesaj, gecenSaniye) {
        if (typeof gecenSaniye === 'number') basladi = gecenSaniye;

        perde.classList.add('acik');
        document.body.classList.add('kilit-modu');

        if (mesaj) { notKutu.innerHTML = kacir(mesaj); notKutu.style.display = ''; }
        else       { notKutu.style.display = 'none'; }

        if (!sayac) { sureYaz(); sayac = setInterval(sureYaz, 1000); }
    }

    function perdeyiKapat() {
        perde.classList.remove('acik');
        document.body.classList.remove('kilit-modu');
        if (sayac) { clearInterval(sayac); sayac = null; }
    }

    /* Sayfa ilk açıldığında kilitliyse hemen göster */
    if (kilitli) {
        perdeyiAc(<?= json_encode($kilit_durum['mesaj'] ?? '', JSON_UNESCAPED_UNICODE) ?>, basladi);
    }

    /* CANLI: radar 3 saniyede bir kontrol eder.
       Öğretmen kilidi açınca öğrencinin sayfayı yenilemesi gerekmez. */
    if (window.mBlockRadar && typeof window.mBlockRadar.abone === 'function') {
        window.mBlockRadar.abone(function (veri) {
            if (!veri || !veri.kilit) return;

            if (veri.kilit.var && !perde.classList.contains('acik')) {
                perdeyiAc(veri.kilit.mesaj, veri.kilit.saniye);
            } else if (!veri.kilit.var && perde.classList.contains('acik')) {
                perdeyiKapat();
            }
        });
    }
})();
</script>
