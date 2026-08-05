<?php
/**
 * ============================================================================
 * DOSYA YOLU: admin/ogrenci_ilerleme.php
 * KULLANICI / SAYFA: Öğretmen -> Öğrenci İlerleme Takibi ve Geri Gönderme
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Öğretmen bu sayfada sınıfındaki her öğrencinin hangi bölümde olduğunu
 * tek bakışta görür. Bir öğrencinin yanındaki ✏️ kalemine tıklayınca
 * o öğrencinin BİTİRDİĞİ bölümler listelenir; öğretmen "bunu tekrar yapsın"
 * dediği bölümleri işaretleyip geri gönderir.
 *
 * NE GÖRÜNÜR, NE GÖRÜNMEZ?
 * Öğrencinin HENÜZ ULAŞMADIĞI bölümler listede çıkmaz (kilitli rozetiyle
 * gösterilir ama seçilemez). Öğretmen sadece öğrencinin gerçekten yaptığı
 * işi geri alabilir.
 *
 * CEZA:
 * Puan düşmez (lig/arena/canavar zincirini bozardı). Sadece o bölümlerden
 * kazanılan ELMAS geri alınır ve elmas eksiye düşebilir. Öğrencinin
 * avatarında kırmızı ceza efekti belirir, görevler tekrar çözülünce kalkar.
 *
 * TEMEL İŞLEVLER:
 *  - Sınıf seçimi + öğrenci listesi (bölüm, yüzde, ceza rozeti)
 *  - Öğrenci seçilince bölüm/görev ağacı
 *  - Onaylı sıfırlama (önce önizleme, sonra uygula)
 *
 * GÜVENLİK: ogretmenZorunlu() + tüm sorgular sinif.ogretmen_id ile sınırlı.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu.
 * ============================================================================
 */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/ilerleme_yardimcilari.php';

ogretmenZorunlu();

$ogretmen_id = (int) $_SESSION['ogretmen_id'];
$okul_adi    = $_SESSION['okul_adi'] ?? 'Okulum';

/* ---- Öğretmenin sınıfları ---- */
$st = $pdo->prepare("SELECT id, sinif_adi FROM siniflar WHERE ogretmen_id = ? ORDER BY sinif_adi ASC");
$st->execute([$ogretmen_id]);
$siniflar = $st->fetchAll(PDO::FETCH_ASSOC);

$seciliSinif = (int) ($_GET['sinif_id'] ?? 0);
$gecerliSinifIdler = array_map(fn($s) => (int) $s['id'], $siniflar);
if ($seciliSinif > 0 && !in_array($seciliSinif, $gecerliSinifIdler, true)) {
    $seciliSinif = 0; // başka öğretmenin sınıfı istenmiş
}
if ($seciliSinif === 0 && $siniflar) {
    $seciliSinif = (int) $siniflar[0]['id'];
}

/* ---- Seçili sınıfın öğrencileri ---- */
$ogrenciler = [];
if ($seciliSinif > 0) {
    $st = $pdo->prepare(
        "SELECT o.id, o.ad_soyad, o.kullanici_adi, o.mevcut_bolum, o.elmas, o.kalici_puan
           FROM ogrenciler o
          WHERE o.sinif_id = ? AND o.onay_durumu = 1
          ORDER BY o.ad_soyad ASC"
    );
    $st->execute([$seciliSinif]);
    $ogrenciler = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ---- Her öğrenci için ilerleme özeti (tek tek, ama sınıf küçük) ---- */
$ozetler = [];
foreach ($ogrenciler as $o) {
    $oid = (int) $o['id'];
    ilerlemeOnbelleginiTemizle($oid);
    $harita = ilerlemeHaritasi($pdo, $oid);
    $ceza   = ogrencininCezasi($pdo, $oid);

    /* Şu an hangi bölümde? = tamamlanmamış ilk açık bölüm */
    $suAnkiBolum = '—';
    foreach ($harita['bolumler'] as $b) {
        if ($b['acik'] && $b['toplam'] > 0 && !$b['bitti']) {
            $suAnkiBolum = $b['ad'];
            break;
        }
    }
    if ($suAnkiBolum === '—' && $harita['cozulen_gorev'] > 0) {
        $suAnkiBolum = '🏁 Tümü tamam';
    }

    $ozetler[$oid] = [
        'bolum'  => $suAnkiBolum,
        'yuzde'  => $harita['genel_yuzde'],
        'cozulen'=> $harita['cozulen_gorev'],
        'toplam' => $harita['toplam_gorev'],
        'ceza'   => $ceza,
    ];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Öğrenci İlerleme Takibi</title>
<link rel="stylesheet" href="<?= varlik('css/teacher_panel.css', '../') ?>">
<style>
    :root{
        --mor:#7c3aed; --mor-koyu:#5b21b6;
        --yesil:#10b981; --turuncu:#f59e0b; --kirmizi:#ef4444;
        --gri:#cbd5e1; --metin:#1e293b; --soluk:#64748b;
    }
    body{ font-family:'Segoe UI',system-ui,sans-serif; background:#f1f5f9; margin:0; color:var(--metin); }
    .ust{ background:linear-gradient(135deg,var(--mor),var(--mor-koyu)); color:#fff; padding:22px 30px;
          display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
    .ust h1{ margin:0; font-size:22px; }
    .ust a{ color:#fff; text-decoration:none; background:rgba(255,255,255,.18);
            padding:9px 16px; border-radius:8px; font-weight:600; }
    .kap{ max-width:1200px; margin:24px auto; padding:0 18px; }
    .kart{ background:#fff; border-radius:14px; padding:22px; box-shadow:0 3px 14px rgba(0,0,0,.07); margin-bottom:22px; }

    select,input[type=text]{ padding:10px 12px; border:1px solid var(--gri); border-radius:8px; font-size:15px; }

    table{ width:100%; border-collapse:collapse; }
    th,td{ padding:12px 10px; text-align:left; border-bottom:1px solid #e2e8f0; }
    th{ background:#f8fafc; font-size:13px; text-transform:uppercase; letter-spacing:.4px; color:var(--soluk); }
    tr:hover td{ background:#faf5ff; }

    .cubuk{ background:#e2e8f0; border-radius:99px; height:9px; width:130px; overflow:hidden; }
    .cubuk span{ display:block; height:100%; background:linear-gradient(90deg,var(--yesil),#34d399); }

    .rozet{ display:inline-block; padding:3px 10px; border-radius:99px; font-size:12px; font-weight:700; }
    .rozet.ceza{ background:#fee2e2; color:#b91c1c; }
    .rozet.temiz{ background:#dcfce7; color:#15803d; }

    .kalem{ background:var(--mor); color:#fff; border:none; border-radius:8px;
            padding:8px 14px; cursor:pointer; font-size:14px; font-weight:600; }
    .kalem:hover{ background:var(--mor-koyu); }

    /* ---- Sıfırlama paneli ---- */
    .perde{ position:fixed; inset:0; background:rgba(15,23,42,.6); display:none;
            align-items:center; justify-content:center; z-index:900; padding:16px; }
    .perde.acik{ display:flex; }
    .panel{ background:#fff; border-radius:16px; width:640px; max-width:100%;
            max-height:88vh; overflow:auto; padding:26px; }
    .panel h2{ margin:0 0 4px; font-size:20px; }
    .panel .alt{ color:var(--soluk); font-size:14px; margin-bottom:18px; }

    .bolum-satir{ display:flex; align-items:center; gap:12px; padding:12px 14px;
                  border:2px solid #e2e8f0; border-radius:10px; margin-bottom:10px; }
    .bolum-satir.secili{ border-color:var(--kirmizi); background:#fef2f2; }
    .bolum-satir.kilitli{ opacity:.45; background:#f8fafc; }
    .bolum-satir input{ width:19px; height:19px; cursor:pointer; }
    .bolum-bilgi{ flex:1; }
    .bolum-bilgi b{ display:block; }
    .bolum-bilgi small{ color:var(--soluk); }

    .noktalar{ display:flex; gap:5px; flex-wrap:wrap; margin-top:6px; }
    .nokta{ width:13px; height:13px; border-radius:50%; background:var(--gri); }
    .nokta.tamam{ background:var(--yesil); }
    .nokta.sifirlandi{ background:var(--kirmizi); }
    .nokta.aktif{ background:var(--turuncu); }

    .uyari{ background:#fffbeb; border-left:4px solid var(--turuncu);
            padding:14px 16px; border-radius:8px; font-size:14px; margin:16px 0; }
    .tehlike{ background:#fef2f2; border-left:4px solid var(--kirmizi);
              padding:14px 16px; border-radius:8px; font-size:14px; margin:16px 0; }

    .dugmeler{ display:flex; gap:10px; margin-top:20px; }
    .dugmeler button{ flex:1; padding:12px; border:none; border-radius:9px;
                      font-weight:700; cursor:pointer; font-size:15px; }
    .btn-vazgec{ background:#e2e8f0; color:var(--metin); }
    .btn-onayla{ background:var(--kirmizi); color:#fff; }
    .btn-onayla:disabled{ background:var(--gri); cursor:not-allowed; }

    .bos{ text-align:center; padding:44px; color:var(--soluk); }
</style>
</head>
<body>

<div class="ust">
    <h1>📊 Öğrenci İlerleme Takibi</h1>
    <div>
        <a href="<?= varlik('admin/ogretmen_paneli.php', '../') ?>">← Panele Dön</a>
    </div>
</div>

<div class="kap">

    <div class="kart">
        <form method="get" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <label style="font-weight:700;">Sınıf:</label>
            <select name="sinif_id" onchange="this.form.submit()">
                <?php foreach ($siniflar as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $seciliSinif === (int) $s['id'] ? 'selected' : '' ?>>
                        <?= temizle($s['sinif_adi']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span style="color:#64748b;font-size:14px;"><?= temizle($okul_adi) ?></span>
        </form>
    </div>

    <div class="kart">
        <?php if (!$ogrenciler): ?>
            <div class="bos">Bu sınıfta onaylı öğrenci yok.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Öğrenci</th>
                    <th>Bulunduğu Bölüm</th>
                    <th>İlerleme</th>
                    <th>💎 Elmas</th>
                    <th>Durum</th>
                    <th style="text-align:right;">İşlem</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ogrenciler as $o):
                $oid = (int) $o['id'];
                $oz  = $ozetler[$oid]; ?>
                <tr>
                    <td>
                        <b><?= temizle($o['ad_soyad']) ?></b><br>
                        <small style="color:#64748b;">@<?= temizle($o['kullanici_adi']) ?></small>
                    </td>
                    <td><?= temizle($oz['bolum']) ?></td>
                    <td>
                        <div class="cubuk"><span style="width:<?= (int) $oz['yuzde'] ?>%"></span></div>
                        <small style="color:#64748b;">
                            <?= (int) $oz['cozulen'] ?> / <?= (int) $oz['toplam'] ?> görev
                            (%<?= (int) $oz['yuzde'] ?>)
                        </small>
                    </td>
                    <td style="<?= (int) $o['elmas'] < 0 ? 'color:#b91c1c;font-weight:700;' : '' ?>">
                        <?= (int) $o['elmas'] ?>
                    </td>
                    <td>
                        <?php if ($oz['ceza']['cezali']): ?>
                            <span class="rozet ceza">⚠️ <?= (int) $oz['ceza']['gorev_sayisi'] ?> görev tekrar</span>
                        <?php else: ?>
                            <span class="rozet temiz">✓ Temiz</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <button class="kalem" onclick="paneliAc(<?= $oid ?>)">✏️ Düzenle</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>


<!-- ===================== SIFIRLAMA PANELİ ===================== -->
<div class="perde" id="perde">
  <div class="panel">
      <h2 id="panelBaslik">Öğrenci</h2>
      <div class="alt">Tekrar yapmasını istediğin bölümleri işaretle.</div>

      <div id="bolumListesi"></div>

      <div class="uyari">
          <b>Ulaşılmamış bölümler seçilemez.</b> Öğrencinin henüz gelmediği bölümler
          soluk ve kilitli görünür — geri alınacak bir işi yok.
      </div>

      <div class="form-alan" style="margin-top:6px;">
          <label style="font-weight:700;display:block;margin-bottom:6px;">
              Gerekçe <small style="font-weight:400;color:#64748b;">(isteğe bağlı, öğrenciye gösterilir)</small>
          </label>
          <input type="text" id="sebep" maxlength="255" style="width:100%;"
                 placeholder="Örn: Konuyu pekiştirmen için 1. bölümü tekrar edelim.">
      </div>

      <div id="onizleme"></div>

      <div class="dugmeler">
          <button class="btn-vazgec" onclick="paneliKapat()">Vazgeç</button>
          <button class="btn-onayla" id="btnOnayla" disabled onclick="adimIlerle()">Devam</button>
      </div>
  </div>
</div>

<script>
/* ============================================================================
   Öğrenci ilerleme paneli — iki adımlı sıfırlama
   Adım 1: bölüm seç -> "Devam" -> sunucudan ÖNİZLEME iste (hiçbir şey yazılmaz)
   Adım 2: önizlemeyi gör -> "Evet, Sıfırla" -> gerçek işlem
   Böylece öğretmen kaç elmas cezası verileceğini görmeden onaylamaz.
   ========================================================================= */

const HARITALAR   = <?= json_encode(array_map(
    fn($o) => ilerlemeHaritasi($pdo, (int) $o['id']),
    array_combine(array_map(fn($o) => (int) $o['id'], $ogrenciler), $ogrenciler)
), JSON_UNESCAPED_UNICODE) ?>;

const ADLAR = <?= json_encode(
    array_combine(
        array_map(fn($o) => (int) $o['id'], $ogrenciler),
        array_map(fn($o) => $o['ad_soyad'], $ogrenciler)
    ) ?: new stdClass(),
    JSON_UNESCAPED_UNICODE
) ?>;

let aktifOgrenci = 0;
let adim         = 1;

function kacir(s){ const d=document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

function paneliAc(ogrenciId){
    aktifOgrenci = ogrenciId;
    adim = 1;
    document.getElementById('panelBaslik').textContent = ADLAR[ogrenciId] || 'Öğrenci';
    document.getElementById('sebep').value = '';
    document.getElementById('onizleme').innerHTML = '';
    document.getElementById('btnOnayla').textContent = 'Devam';
    document.getElementById('btnOnayla').disabled = true;

    const harita = HARITALAR[ogrenciId];
    const kutu   = document.getElementById('bolumListesi');
    kutu.innerHTML = '';

    if(!harita || !harita.bolumler || !harita.bolumler.length){
        kutu.innerHTML = '<div class="bos">Henüz görev tanımlanmamış.</div>';
        document.getElementById('perde').classList.add('acik');
        return;
    }

    harita.bolumler.forEach(b => {
        // Öğrencinin hiç dokunmadığı bölümler: göster ama kilitli
        const dokundu = b.cozulen > 0;
        const satir   = document.createElement('label');
        satir.className = 'bolum-satir' + (dokundu ? '' : ' kilitli');

        const noktalar = b.gorevler.map(g =>
            `<span class="nokta ${g.durum}" title="${kacir(g.ad)}"></span>`).join('');

        satir.innerHTML = `
            <input type="checkbox" value="${b.id}" ${dokundu ? '' : 'disabled'}>
            <div class="bolum-bilgi">
                <b>${dokundu ? '' : '🔒 '}${kacir(b.ad)}</b>
                <small>${b.cozulen} / ${b.toplam} görev tamamlandı</small>
                <div class="noktalar">${noktalar}</div>
            </div>`;

        const kutucuk = satir.querySelector('input');
        kutucuk.addEventListener('change', () => {
            satir.classList.toggle('secili', kutucuk.checked);
            secimiKontrolEt();
        });

        kutu.appendChild(satir);
    });

    document.getElementById('perde').classList.add('acik');
}

function paneliKapat(){
    document.getElementById('perde').classList.remove('acik');
    aktifOgrenci = 0;
}

function secilenBolumler(){
    return [...document.querySelectorAll('#bolumListesi input:checked')].map(i => parseInt(i.value,10));
}

function secimiKontrolEt(){
    // Seçim değişince önizlemeyi geçersiz kıl, adım 1'e dön
    if(adim === 2){
        adim = 1;
        document.getElementById('onizleme').innerHTML = '';
        document.getElementById('btnOnayla').textContent = 'Devam';
    }
    document.getElementById('btnOnayla').disabled = secilenBolumler().length === 0;
}

async function istek(islem){
    const cevap = await fetch('<?= varlik('api/ogrenci_ilerleme_sifirla.php', '../') ?>', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            islem,
            ogrenci_id: aktifOgrenci,
            bolum_idler: secilenBolumler(),
            sebep: document.getElementById('sebep').value
        })
    });
    return cevap.json();
}

async function adimIlerle(){
    const btn = document.getElementById('btnOnayla');
    btn.disabled = true;

    try{
        if(adim === 1){
            const s = await istek('onizleme');
            if(!s.success){ alert(s.mesaj || s.message || 'Hata'); btn.disabled=false; return; }

            const v = s.veri || s.data || s;
            document.getElementById('onizleme').innerHTML = `
              <div class="tehlike">
                <b>Onaylarsan ne olacak?</b>
                <ul style="margin:10px 0 0;padding-left:20px;">
                  <li><b>${v.gorev_sayisi}</b> görev sıfırlanacak, öğrenci bunları tekrar çözecek.</li>
                  <li>Elmas cezası: <b>-${v.elmas_cezasi} 💎</b>
                      (${v.mevcut_elmas} → <b style="color:${v.yeni_elmas<0?'#b91c1c':'inherit'}">${v.yeni_elmas}</b>)</li>
                  <li>Puanına <b>dokunulmayacak</b> — lig sıralaması ve arena gücü bozulmaz.</li>
                  <li>Avatarında kırmızı ceza efekti çıkacak; görevleri bitirince kalkacak.</li>
                </ul>
              </div>`;

            adim = 2;
            btn.textContent = '🔴 Evet, Sıfırla';
            btn.disabled = false;
            return;
        }

        const s = await istek('sifirla');
        if(!s.success){ alert(s.mesaj || s.message || 'Hata'); btn.disabled=false; return; }

        alert(s.mesaj || s.message || 'Sıfırlandı.');
        location.reload();

    }catch(e){
        alert('Bağlantı hatası: ' + e.message);
        btn.disabled = false;
    }
}

document.getElementById('perde').addEventListener('click', e => {
    if(e.target.id === 'perde') paneliKapat();
});
document.addEventListener('keydown', e => {
    if(e.key === 'Escape') paneliKapat();
});
</script>
</body>
</html>
