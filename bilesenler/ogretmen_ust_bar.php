<?php
/**
 * ============================================================================
 * MODÜL: Öğretmen üst barı ve sekme menüsü (partial)
 * DOSYA YOLU: bilesenler/ogretmen_ust_bar.php
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Öğretmenin gördüğü dört sayfanın ortak tepesi: kim olduğu, okulu, sınıf
 * PIN'i, üyelik süresi, çıkış düğmesi ve sayfalar arası sekmeler.
 *
 * [2026-08] NEDEN YAZILDI?
 * Dört sayfanın her birinde ayrı bir başlık vardı; kimi PIN'i gösteriyordu
 * kimi göstermiyordu, sayfa değiştirdikçe menü yerini değiştiriyordu ve
 * "hangi sayfadayım" belli olmuyordu. Ayrıca öğretmen bir sayfadan diğerine
 * geçmek için önce panele dönmek zorundaydı. Artık her sayfada aynı sekmeler.
 *
 * ÇAĞIRMADAN ÖNCE:
 *   $OT_AKTIF — hangi sekme vurgulansın:
 *               'panel' | 'ogrenciler' | 'ilerleme' | 'quiz'
 *   $OT_KOK   — proje köküne çıkan yol (verilmezse '../' varsayılır)
 *
 * KULLANIM (sayfanın <body class="ot"> etiketinden hemen sonra):
 *   <?php $OT_AKTIF = 'panel'; require __DIR__ . '/../bilesenler/ogretmen_ust_bar.php'; ?>
 *
 * GEREKSİNİM: css/ogretmen_tema.css yüklenmiş olmalı.
 * ============================================================================
 */

require_once __DIR__ . '/guvenlik.php';
ogretmenZorunlu();

$OT_AKTIF = $OT_AKTIF ?? '';
$OT_KOK   = $OT_KOK   ?? '../';

$ot_ogretmen_id = (int) $_SESSION['ogretmen_id'];
$ot_ad          = $_SESSION['ogretmen_ad']  ?? 'Öğretmen';
$ot_okul        = $_SESSION['okul_adi']     ?? 'Okul';
$ot_pin         = $_SESSION['ogretmen_pin'] ?? '';

/* --- Üyelik süresi + onay bekleyen öğrenci sayısı (tek sorgu) --- */
$st = $pdo->prepare(
    "SELECT o.bitis_tarihi,
            (SELECT COUNT(*) FROM ogrenciler og
               JOIN siniflar s ON s.id = og.sinif_id
              WHERE s.ogretmen_id = o.id AND og.onay_durumu = 0) AS bekleyen
       FROM ogretmenler o WHERE o.id = ?"
);
$st->execute([$ot_ogretmen_id]);
$ot_bilgi = $st->fetch(PDO::FETCH_ASSOC) ?: ['bitis_tarihi' => null, 'bekleyen' => 0];

$ot_bekleyen = (int) $ot_bilgi['bekleyen'];

/* Kalan süre metni — süper admin tarafındaki hesapla aynı mantık */
$ot_uyelik_metin = 'Süresiz';
$ot_uyelik_az    = false;

if (!empty($ot_bilgi['bitis_tarihi'])) {
    $bitis = strtotime($ot_bilgi['bitis_tarihi']);
    $gun   = (int) floor(($bitis - time()) / 86400);

    if ($gun < 0) {
        $ot_uyelik_metin = 'Süre doldu';
        $ot_uyelik_az    = true;
    } elseif ($gun < 1) {
        $ot_uyelik_metin = 'Bugün bitiyor';
        $ot_uyelik_az    = true;
    } elseif ($gun < 30) {
        $ot_uyelik_metin = $gun . ' gün kaldı';
        $ot_uyelik_az    = ($gun < 7);
    } else {
        $ot_uyelik_metin = (int) floor($gun / 30) . ' ay kaldı';
    }
}

/* ============================================================================
 * SEKMELER — [2026-08] DÖRTTEN İKİYE İNDİ
 *
 * Eskiden dört sekme vardı: Canlı Takip · Öğrenciler · İlerleme · Quiz.
 * İkisi gereksizdi çünkü aynı yere iki ayrı kapı açıyordu:
 *   · İLERLEME  -> artık Canlı Takip'in İÇİNDE alt sekme. Ayrı sayfa olması
 *                  öğretmeni sınıfı ikinci kez seçmeye zorluyordu; seçtiği
 *                  sınıf sayfa değişince kayboluyordu.
 *   · QUIZ      -> panelde "Sınıf Kontrolleri" kartında zaten düğmesi var.
 *
 * Kalan iki sekme gerçekten ayrı iki iş: ders sırasında sınıfı izlemek
 * (panel) ve öğrenci kayıt/onay işleri (öğrenciler).
 * ========================================================================== */
$ot_sekmeler = [
    'panel'      => ['ad' => '📡 Canlı Takip', 'yol' => 'ogretmen_paneli.php'],
    'ogrenciler' => ['ad' => '👥 Öğrenci Yönetimi', 'yol' => 'ogrenci_yonetimi.php'],
];

/* Sekmesi olmayan sayfalar (quiz merkezi gibi) için pasif konum göstergesi.
   Üst barın var oluş sebeplerinden biri "hangi sayfadayım" sorusuydu;
   sekme kalkınca o cevap kaybolmasın diye tıklanmayan bir etiket basıyoruz. */
$ot_konum_adlari = [
    'quiz'     => '🎮 Quiz Merkezi',
    'ilerleme' => '📊 İlerleme',
];
$ot_konum = (!isset($ot_sekmeler[$OT_AKTIF]) && isset($ot_konum_adlari[$OT_AKTIF]))
    ? $ot_konum_adlari[$OT_AKTIF]
    : '';
?>
<div class="ot-ustbar">
    <div class="ot-ustbar-ic">
        <div class="ot-kimlik">
            <h1><?= temizle($ot_ad) ?></h1>
            <div class="okul">🏫 <?= temizle($ot_okul) ?></div>
        </div>

        <div class="ot-ustbar-sag">
            <?php if ($ot_pin !== ''): ?>
            <div class="ot-pin" onclick="otPinKopyala(this)" title="Öğrencilerin kayıt olurken gireceği kod — kopyalamak için tıkla">
                <span class="etiket">SINIF PIN</span>
                <span class="kod"><?= temizle($ot_pin) ?></span>
            </div>
            <?php endif; ?>

            <button type="button"
                    class="ot-uyelik <?= $ot_uyelik_az ? 'az' : '' ?>"
                    onclick="otSureTalepEt()"
                    title="Süre uzatma talebi göndermek için tıkla">
                ⏳ <?= temizle($ot_uyelik_metin) ?>
            </button>

            <a href="<?= $OT_KOK ?>api/logout.php" class="ot-cikis">🚪 Çıkış</a>
        </div>
    </div>

    <nav class="ot-sekmeler">
        <?php foreach ($ot_sekmeler as $anahtar => $sekme): ?>
            <a href="<?= temizle($sekme['yol']) ?>"
               class="ot-sekme <?= $OT_AKTIF === $anahtar ? 'aktif' : '' ?>">
                <?= $sekme['ad'] ?>
                <?php if ($anahtar === 'ogrenciler' && $ot_bekleyen > 0): ?>
                    <span class="rozet-sayi"><?= $ot_bekleyen ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

        <?php if ($ot_konum !== ''): ?>
            <span class="ot-sekme aktif" aria-current="page"><?= $ot_konum ?></span>
        <?php endif; ?>
    </nav>
</div>

<script src="<?= varlik('js/onay_kutusu.js', $OT_KOK) ?>"></script>
<script>
/* PIN'i panoya kopyala — öğretmen sık sık sınıfa söylüyor */
function otPinKopyala(oge) {
    const kod = oge.querySelector('.kod');
    if (!kod) return;
    const metin = kod.textContent.trim();

    const bitir = () => {
        oge.classList.add('kopyalandi');
        setTimeout(() => oge.classList.remove('kopyalandi'), 1200);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(metin).then(bitir).catch(() => {});
        return;
    }
    const gecici = document.createElement('textarea');
    gecici.value = metin;
    document.body.appendChild(gecici);
    gecici.select();
    try { document.execCommand('copy'); bitir(); } catch (e) {}
    gecici.remove();
}

/* Süre uzatma talebi — Süper Admin'e bildirim düşer.
   Sayfa kendi otSureTalepEt tanımını yaparsa o geçerli olur. */
if (typeof window.otSureTalepEt !== 'function') {
    window.otSureTalepEt = async function () {
        const ay = await metinKutusu('Kaç aylık süre uzatma talep ediyorsun?', '1');
        if (ay === null) return;

        const sayi = parseInt(ay, 10);
        if (!sayi || sayi < 1) { alert('Geçerli bir ay sayısı gir.'); return; }

        fetch('<?= $OT_KOK ?>api/teacher_time_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ ay: sayi })
        })
        .then(r => r.json())
        .then(d => alert((d.success ? '✅ ' : '❌ ') + (d.message || d.mesaj || 'Talep gönderildi.')))
        .catch(() => alert('❌ Sunucuya ulaşılamadı.'));
    };
}
</script>
