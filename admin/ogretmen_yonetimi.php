<?php
/**
 * ============================================================================
 * DOSYA YOLU: admin/ogretmen_yonetimi.php
 * KULLANICI / SAYFA: Süper Admin -> Öğretmen ve Abonelik Yönetimi
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Öğretmen başvurularının onaylandığı, PIN üretildiği, abonelik süresinin
 * uzatıldığı ve öğretmenin sistemden silindiği ekran.
 *
 * TEMEL İŞLEVLER:
 *   1. Onay bekleyen başvurular  -> kart görünümü, onayla / sil
 *   2. Kayıtlı öğretmenler       -> tablo, süre uzat / şifre değiştir / sil
 *   3. Arama kutusu              -> ad, okul veya kullanıcı adına göre süz
 *   4. PIN kopyalama             -> tek tıkla panoya
 *
 * [2026-08] TASARIM BAŞTAN YAZILDI.
 * Eski hâli açık temalı, dar tablolu ve süper panelden kopuktu; işlem
 * düğmeleri hücrelere sıkışıyor, "bitiş tarihi" ham tarih olarak duruyor ve
 * öğretmenin kaç günü kaldığı görülmüyordu. Yeni hâli super_panel.php
 * paletini kullanıyor (#0f172a / #1e293b / #334155) ve şunları ekliyor:
 *   - Üstte özet sayaçlar (bekleyen / aktif / bloke / öğrenci)
 *   - Bitiş tarihinin yanında KALAN GÜN ve yaklaşan bitişlerde sarı uyarı
 *   - Her öğretmenin kaç öğrencisi olduğu (kontenjan doluluğu)
 *   - Arama kutusu
 *
 * KORUNAN DAVRANIŞLAR (bilerek değiştirilmedi):
 *   - Şifre kutusu: tıklayınca açılır, alandan çıkınca otomatik kaydedilir
 *   - Silme: 4 saniyelik geri sayım, iptal edilemez ama yanlışlıkla basmayı
 *     zorlaştırır ve satır solarak kaybolur
 *
 * BAĞLI API: api/admin_teacher_action.php
 *   işlemler: onayla | uzat | sil | sifre_degistir
 * ============================================================================
 */

require_once __DIR__ . '/super_auth.php';
superAdminZorunlu();

/* --- 1. Onay bekleyen başvurular --- */
$bekleyenler = $pdo->query(
    "SELECT * FROM ogretmenler WHERE durum = 'onay_bekliyor' ORDER BY id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

/* --- 2. Kayıtlı öğretmenler + öğrenci sayısı (tek sorgu, N+1 yok) --- */
$kayitlilar = $pdo->query(
    "SELECT o.*,
            (SELECT COUNT(*) FROM ogrenciler og
               JOIN siniflar s ON s.id = og.sinif_id
              WHERE s.ogretmen_id = o.id) AS ogrenci_sayisi,
            (SELECT COUNT(*) FROM siniflar s WHERE s.ogretmen_id = o.id) AS sinif_sayisi
       FROM ogretmenler o
      WHERE o.durum = 'onaylandi'
      ORDER BY o.okul_adi ASC, o.ad_soyad ASC"
)->fetchAll(PDO::FETCH_ASSOC);

/* --- Özet sayaçlar --- */
$suAn         = time();
$aktifSayi    = 0;
$blokeSayi    = 0;
$yakindaBiten = 0;   // 7 günden az kalanlar
$toplamOgrenci = 0;

foreach ($kayitlilar as $o) {
    $bitis = strtotime($o['bitis_tarihi'] ?? '');
    $toplamOgrenci += (int) $o['ogrenci_sayisi'];

    if (!$bitis || $bitis < $suAn) {
        $blokeSayi++;
    } else {
        $aktifSayi++;
        if (($bitis - $suAn) < 7 * 86400) {
            $yakindaBiten++;
        }
    }
}

/** Kalan süreyi insan diliyle yazar */
function kalanSureMetni(?string $bitisTarihi): array
{
    $bitis = strtotime($bitisTarihi ?? '');
    if (!$bitis) {
        return ['metin' => 'Tarih yok', 'sinif' => 'bloke', 'gun' => 0];
    }

    $fark = $bitis - time();
    $gun  = (int) floor($fark / 86400);

    if ($fark < 0) {
        return ['metin' => abs($gun) . ' gün önce bitti', 'sinif' => 'bloke', 'gun' => $gun];
    }
    if ($gun < 1) {
        return ['metin' => 'Bugün bitiyor', 'sinif' => 'uyari', 'gun' => 0];
    }
    if ($gun < 7) {
        return ['metin' => $gun . ' gün kaldı', 'sinif' => 'uyari', 'gun' => $gun];
    }
    return ['metin' => $gun . ' gün kaldı', 'sinif' => 'aktif', 'gun' => $gun];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Öğretmen Yönetimi | Süper Admin</title>
    <style>
        /* ====================================================================
           super_panel.php ile aynı palet — panelden buraya geçiş göz yormasın
           ================================================================== */
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f172a; color: #f8fafc; margin: 0; padding: 25px 20px 70px;
        }
        .sarmal { max-width: 1180px; margin: 0 auto; }

        /* ---- Üst başlık ---- */
        .ust { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 24px; }
        .ust h1 { margin: 0; font-size: 24px; }
        .ust .alt { color: #94a3b8; font-size: 13px; margin-top: 5px; }
        .ust-btnler { display: flex; gap: 9px; flex-wrap: wrap; }

        .btn { padding: 9px 16px; border-radius: 8px; text-decoration: none; font-size: 13.5px; font-weight: bold; border: none; cursor: pointer; transition: .18s; display: inline-block; }
        .btn-gri   { background: #334155; color: #f8fafc; } .btn-gri:hover   { background: #475569; }
        .btn-mor   { background: #8b5cf6; color: #fff; }    .btn-mor:hover   { background: #7c3aed; }
        .btn-yesil { background: #10b981; color: #fff; }    .btn-yesil:hover { background: #059669; }
        .btn-mavi  { background: #3b82f6; color: #fff; }    .btn-mavi:hover  { background: #2563eb; }

        /* ---- Özet sayaçlar ---- */
        .istatistikler { display: grid; grid-template-columns: repeat(auto-fit, minmax(155px, 1fr)); gap: 12px; margin-bottom: 26px; }
        .ist { background: #1e293b; border: 1px solid #334155; border-radius: 11px; padding: 16px; text-align: center; }
        .ist .sayi { font-size: 27px; font-weight: 900; display: block; line-height: 1.1; }
        .ist .etiket { color: #94a3b8; font-size: 11.5px; margin-top: 5px; display: block; letter-spacing: .4px; }
        .ist.vurgu { border-color: #f59e0b; background: linear-gradient(160deg, #292014, #1e293b); }

        /* ---- Kart ---- */
        .kart { background: #1e293b; border: 1px solid #334155; border-radius: 13px; padding: 22px; margin-bottom: 24px; }
        .kart-baslik { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 6px; }
        .kart h2 { margin: 0; font-size: 17px; }
        .kart .aciklama { margin: 0 0 18px 0; color: #94a3b8; font-size: 13px; line-height: 1.6; }

        /* ---- Bekleyen başvuru kartları ---- */
        .basvurular { display: grid; grid-template-columns: repeat(auto-fill, minmax(310px, 1fr)); gap: 14px; }
        .basvuru {
            background: linear-gradient(160deg, #2a2413, #1e293b);
            border: 1px solid #a16207; border-radius: 12px; padding: 17px;
        }
        .basvuru .ad { font-size: 16px; font-weight: 800; margin: 0 0 3px; }
        .basvuru .okul { color: #fcd34d; font-size: 13px; margin: 0 0 12px; }
        .basvuru .satir { display: flex; justify-content: space-between; font-size: 13px; padding: 5px 0; border-top: 1px solid rgba(148,163,184,.16); }
        .basvuru .satir span:first-child { color: #94a3b8; }
        .basvuru .islem { display: flex; gap: 8px; align-items: center; margin-top: 14px; flex-wrap: wrap; }

        /* ---- Tablo ---- */
        .tablo-sarmal { overflow-x: auto; border: 1px solid #334155; border-radius: 11px; }
        table { width: 100%; border-collapse: collapse; min-width: 940px; }
        th { background: #0f172a; color: #94a3b8; font-size: 11px; text-transform: uppercase; letter-spacing: .5px;
             padding: 12px 10px; text-align: left; position: sticky; top: 0; }
        td { padding: 13px 10px; border-top: 1px solid #334155; font-size: 13.5px; vertical-align: middle; }
        tr:hover td { background: rgba(139, 92, 246, .06); }

        .ad-hucre b { font-size: 14.5px; display: block; }
        .ad-hucre small { color: #94a3b8; font-size: 12px; }

        /* ---- Rozetler ---- */
        .rozet { display: inline-block; padding: 4px 11px; border-radius: 999px; font-size: 11.5px; font-weight: 800; white-space: nowrap; }
        .rozet.aktif  { background: #064e3b; color: #a7f3d0; }
        .rozet.uyari  { background: #78350f; color: #fde68a; }
        .rozet.bloke  { background: #7f1d1d; color: #fecaca; }
        .rozet.notr   { background: #334155; color: #cbd5e1; }

        /* ---- PIN ---- */
        .pin {
            font-family: 'Consolas', monospace; font-size: 17px; font-weight: 900;
            color: #a78bfa; background: #0f172a; border: 1px dashed #6d28d9;
            padding: 5px 11px; border-radius: 7px; cursor: pointer; transition: .15s;
        }
        .pin:hover { background: #2e1065; color: #ddd6fe; }
        .pin.kopyalandi { border-color: #10b981; color: #6ee7b7; }

        /* ---- Girdiler ---- */
        input[type=number], input[type=password], input[type=text] {
            background: #0f172a; color: #f8fafc; border: 2px solid #334155;
            border-radius: 7px; padding: 7px 9px; font-size: 13.5px; outline: none; transition: .15s;
        }
        input:focus { border-color: #8b5cf6; }
        .ay-kutu { width: 62px; text-align: center; }

        .sifre-kutu { width: 128px; font-family: 'Consolas', monospace; }
        .sifre-kutu.kaydedildi { border-color: #10b981; background: #052e22; }
        .sifre-kutu.hata { border-color: #ef4444; background: #2b1111; }
        .sifre-durum { font-size: 11px; font-weight: bold; margin-left: 6px; white-space: nowrap; }

        /* ---- İşlem düğmeleri ---- */
        .islem-btn {
            background: #334155; border: 1px solid #475569; color: #e2e8f0;
            padding: 7px 12px; border-radius: 7px; cursor: pointer; font-size: 12.5px;
            font-weight: bold; transition: .15s; white-space: nowrap;
        }
        .islem-btn:hover { background: #475569; }
        .islem-btn.onay  { background: #059669; border-color: #10b981; }
        .islem-btn.onay:hover { background: #10b981; }
        .islem-btn.uzat  { background: #1d4ed8; border-color: #3b82f6; }
        .islem-btn.uzat:hover { background: #2563eb; }
        .islem-btn.sil   { background: transparent; border-color: #7f1d1d; color: #fca5a5; }
        .islem-btn.sil:hover { background: #7f1d1d; color: #fecaca; }
        .islem-btn:disabled { opacity: .55; cursor: not-allowed; }

        /* ---- Arama ---- */
        .arama { position: relative; }
        .arama input { width: 260px; padding-left: 34px; }
        .arama::before { content: '🔍'; position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 13px; opacity: .6; }

        .bos { text-align: center; padding: 40px 20px; color: #94a3b8; font-size: 14px; }

        /* Silme animasyonu */
        tr.siliniyor td, .basvuru.siliniyor { background: rgba(127,29,29,.35) !important; transition: .5s; }

        @media (max-width: 720px) {
            .arama input { width: 100%; }
            .ust h1 { font-size: 20px; }
        }
    </style>
</head>
<body>
<?php require __DIR__ . '/../bilesenler/super_gezinme.php'; ?>

<div class="sarmal">

    <div class="ust">
        <div>
            <h1>👨‍🏫 Öğretmen Yönetimi</h1>
            <div class="alt">Başvuruları onayla, PIN üret, abonelik süresi ver.</div>
        </div>
        <div class="ust-btnler">
            <a href="../v2_gorev_sistemi/admin/gorev_listesi.php" class="btn btn-mor">📚 Müfredat</a>
            <a href="admin_market.php" class="btn btn-yesil">🛒 Market</a>
            <a href="super_quiz_yonetimi.php" class="btn btn-mavi">🎯 Soru Havuzu</a>
        </div>
    </div>

    <!-- ==================== ÖZET ==================== -->
    <div class="istatistikler">
        <div class="ist <?= count($bekleyenler) > 0 ? 'vurgu' : '' ?>">
            <span class="sayi" style="color:<?= count($bekleyenler) > 0 ? '#fbbf24' : '#64748b' ?>;"><?= count($bekleyenler) ?></span>
            <span class="etiket">ONAY BEKLİYOR</span>
        </div>
        <div class="ist">
            <span class="sayi" style="color:#34d399;"><?= $aktifSayi ?></span>
            <span class="etiket">AKTİF ÖĞRETMEN</span>
        </div>
        <div class="ist <?= $yakindaBiten > 0 ? 'vurgu' : '' ?>">
            <span class="sayi" style="color:<?= $yakindaBiten > 0 ? '#fbbf24' : '#64748b' ?>;"><?= $yakindaBiten ?></span>
            <span class="etiket">SÜRESİ AZALAN</span>
        </div>
        <div class="ist">
            <span class="sayi" style="color:<?= $blokeSayi > 0 ? '#f87171' : '#64748b' ?>;"><?= $blokeSayi ?></span>
            <span class="etiket">SÜRESİ BİTMİŞ</span>
        </div>
        <div class="ist">
            <span class="sayi" style="color:#60a5fa;"><?= $toplamOgrenci ?></span>
            <span class="etiket">TOPLAM ÖĞRENCİ</span>
        </div>
    </div>


    <!-- ==================== ONAY BEKLEYENLER ==================== -->
    <div class="kart">
        <div class="kart-baslik">
            <h2>⏳ Onay Bekleyen Başvurular</h2>
            <?php if (count($bekleyenler) > 0): ?>
                <span class="rozet uyari"><?= count($bekleyenler) ?> başvuru</span>
            <?php endif; ?>
        </div>
        <p class="aciklama">
            Onayladığında öğretmene otomatik <b>4 haneli sınıf PIN'i</b> üretilir.
            Öğrenciler bu PIN ile kayıt olur. Süreyi ay olarak belirle.
        </p>

        <?php if (empty($bekleyenler)): ?>
            <div class="bos">✅ Bekleyen başvuru yok.</div>
        <?php else: ?>
        <div class="basvurular">
            <?php foreach ($bekleyenler as $ogr):
                $bid = (int) $ogr['id'];
                $onerilenAy = (isset($ogr['talep_edilen_ay']) && (int) $ogr['talep_edilen_ay'] > 0)
                            ? (int) $ogr['talep_edilen_ay'] : 1; ?>
            <div class="basvuru" id="basvuru_<?= $bid ?>">
                <p class="ad"><?= temizle($ogr['ad_soyad']) ?></p>
                <p class="okul">🏫 <?= temizle($ogr['okul_adi']) ?></p>

                <div class="satir"><span>Telefon</span><span><?= temizle($ogr['telefon'] ?: '—') ?></span></div>
                <div class="satir"><span>Kontenjan</span><span><?= (int) $ogr['kontenjan'] ?> öğrenci</span></div>
                <div class="satir"><span>Talep ettiği süre</span><span><?= $onerilenAy ?> ay</span></div>

                <div class="islem">
                    <input type="number" class="ay-kutu" id="ay_onay_<?= $bid ?>"
                           value="<?= $onerilenAy ?>" min="1" max="120" title="Verilecek süre (ay)">
                    <span style="color:#94a3b8; font-size:12.5px;">ay</span>
                    <button class="islem-btn onay" onclick="islemYap('onayla', <?= $bid ?>)">✓ Onayla &amp; PIN Üret</button>
                    <button class="islem-btn sil" id="btn_sil_<?= $bid ?>" onclick="ogretmenSil(<?= $bid ?>, this)">Sil</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>


    <!-- ==================== KAYITLI ÖĞRETMENLER ==================== -->
    <div class="kart">
        <div class="kart-baslik">
            <h2>✅ Kayıtlı Öğretmenler</h2>
            <div class="arama">
                <input type="text" id="aramaKutusu" placeholder="Ad, okul veya kullanıcı adı ara…"
                       oninput="ogretmenSuz(this.value)" autocomplete="off">
            </div>
        </div>
        <p class="aciklama">
            Şifreye tıklayınca görünür; değiştirip alandan çıktığında <b>otomatik kaydedilir</b>.
            PIN'e tıklayarak panoya kopyalayabilirsin.
        </p>

        <?php if (empty($kayitlilar)): ?>
            <div class="bos">Henüz kayıtlı öğretmen yok.</div>
        <?php else: ?>
        <div class="tablo-sarmal">
            <table id="ogretmenTablosu">
                <thead>
                    <tr>
                        <th>Öğretmen</th>
                        <th>Kullanıcı / Şifre</th>
                        <th style="text-align:center;">PIN</th>
                        <th style="text-align:center;">Öğrenci</th>
                        <th>Abonelik</th>
                        <th>Süre Uzat</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($kayitlilar as $ogr):
                    $oid   = (int) $ogr['id'];
                    $kalan = kalanSureMetni($ogr['bitis_tarihi'] ?? null);
                    $onerilenAy = (isset($ogr['talep_edilen_ay']) && (int) $ogr['talep_edilen_ay'] > 0)
                                ? (int) $ogr['talep_edilen_ay'] : 1;

                    $aramaMetni = mb_strtolower(
                        ($ogr['ad_soyad'] ?? '') . ' ' . ($ogr['okul_adi'] ?? '') . ' ' . ($ogr['kullanici_adi'] ?? ''),
                        'UTF-8'
                    );
                ?>
                <tr id="satir_<?= $oid ?>" data-ara="<?= temizle($aramaMetni) ?>">
                    <td class="ad-hucre">
                        <b><?= temizle($ogr['ad_soyad']) ?></b>
                        <small>🏫 <?= temizle($ogr['okul_adi']) ?></small>
                    </td>

                    <td>
                        <div style="font-family:Consolas,monospace; color:#cbd5e1; font-size:13px; margin-bottom:5px;">
                            <?= temizle($ogr['kullanici_adi'] ?? '—') ?>
                        </div>
                        <input type="password"
                               class="sifre-kutu"
                               id="sifre_<?= $oid ?>"
                               value="<?= temizle($ogr['sifre'] ?? '') ?>"
                               data-orijinal="<?= temizle($ogr['sifre'] ?? '') ?>"
                               data-id="<?= $oid ?>"
                               onfocus="this.type='text'; this.select();"
                               onblur="this.type='password'; sifreKaydet(this);"
                               onkeydown="if(event.key==='Enter'){event.preventDefault(); this.blur();}"
                               title="Tıkla, değiştir, dışarı çık — otomatik kaydedilir">
                        <span class="sifre-durum" id="sifre_durum_<?= $oid ?>"></span>
                    </td>

                    <td style="text-align:center;">
                        <span class="pin" onclick="pinKopyala(this)" title="Kopyalamak için tıkla"><?= temizle($ogr['pin_kodu'] ?? '—') ?></span>
                    </td>

                    <td style="text-align:center;">
                        <span class="rozet notr"><?= (int) $ogr['ogrenci_sayisi'] ?> / <?= (int) $ogr['kontenjan'] ?></span>
                        <div style="color:#64748b; font-size:11px; margin-top:4px;"><?= (int) $ogr['sinif_sayisi'] ?> sınıf</div>
                    </td>

                    <td>
                        <span class="rozet <?= $kalan['sinif'] ?>"><?= temizle($kalan['metin']) ?></span>
                        <div style="color:#64748b; font-size:11.5px; margin-top:5px;">
                            <?= !empty($ogr['bitis_tarihi']) ? date('d.m.Y H:i', strtotime($ogr['bitis_tarihi'])) : '—' ?>
                        </div>
                    </td>

                    <td>
                        <div style="display:flex; gap:7px; align-items:center; flex-wrap:wrap;">
                            <input type="number" class="ay-kutu" id="ay_uzat_<?= $oid ?>"
                                   value="<?= $onerilenAy ?>" min="1" max="120" title="Eklenecek süre (ay)">
                            <button class="islem-btn uzat" onclick="islemYap('uzat', <?= $oid ?>)">+ Süre</button>
                            <button class="islem-btn sil" id="btn_sil_<?= $oid ?>" onclick="ogretmenSil(<?= $oid ?>, this)">Sil</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="aramaBos" class="bos" style="display:none;">Aramanla eşleşen öğretmen yok.</div>
        <?php endif; ?>
    </div>
</div>


<script>
const API = '../api/admin_teacher_action.php';

function apiCagir(govde) {
    return fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(govde)
    }).then(r => r.json());
}


/* ============================================================================
   ARAMA — sunucuya gitmeden, satırları gizleyip gösterir
   ========================================================================= */
function ogretmenSuz(metin) {
    const arama = (metin || '').toLocaleLowerCase('tr').trim();
    const satirlar = document.querySelectorAll('#ogretmenTablosu tbody tr');
    let gorunen = 0;

    satirlar.forEach(tr => {
        const uygun = arama === '' || (tr.dataset.ara || '').includes(arama);
        tr.style.display = uygun ? '' : 'none';
        if (uygun) gorunen++;
    });

    const bosKutu = document.getElementById('aramaBos');
    if (bosKutu) bosKutu.style.display = gorunen === 0 ? 'block' : 'none';
}


/* ============================================================================
   PIN KOPYALA
   ========================================================================= */
function pinKopyala(oge) {
    const pin = (oge.textContent || '').trim();
    if (!pin || pin === '—') return;

    const bitir = () => {
        const eski = oge.textContent;
        oge.classList.add('kopyalandi');
        oge.textContent = '✓ kopyalandı';
        setTimeout(() => { oge.textContent = eski; oge.classList.remove('kopyalandi'); }, 1200);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(pin).then(bitir).catch(() => {});
        return;
    }
    // Eski tarayıcı yedeği
    const gecici = document.createElement('textarea');
    gecici.value = pin;
    document.body.appendChild(gecici);
    gecici.select();
    try { document.execCommand('copy'); bitir(); } catch (e) {}
    gecici.remove();
}


/* ============================================================================
   ŞİFRE OTOMATİK KAYDETME
   Kutuya tıklanınca şifre görünür, alandan çıkınca gizlenir ve değiştiyse
   kaydedilir — ayrı bir "Kaydet" düğmesi yok.
   ========================================================================= */
function sifreKaydet(kutu) {
    const id       = kutu.dataset.id;
    const yeni     = kutu.value.trim();
    const orijinal = kutu.dataset.orijinal;
    const durum    = document.getElementById('sifre_durum_' + id);

    kutu.classList.remove('kaydedildi', 'hata');

    if (yeni === orijinal) { durum.textContent = ''; return; }

    if (yeni === '') {
        kutu.value = orijinal;
        kutu.classList.add('hata');
        durum.style.color = '#f87171';
        durum.textContent = 'boş olamaz';
        setTimeout(() => { kutu.classList.remove('hata'); durum.textContent = ''; }, 2500);
        return;
    }

    durum.style.color = '#94a3b8';
    durum.textContent = 'kaydediliyor…';

    apiCagir({ islem: 'sifre_degistir', id: id, sifre: yeni })
        .then(d => {
            if (d.success) {
                kutu.dataset.orijinal = yeni;
                kutu.classList.add('kaydedildi');
                durum.style.color = '#34d399';
                durum.textContent = '✓ kaydedildi';
                setTimeout(() => { kutu.classList.remove('kaydedildi'); durum.textContent = ''; }, 2500);
            } else {
                kutu.value = orijinal;
                kutu.classList.add('hata');
                durum.style.color = '#f87171';
                durum.textContent = d.message || d.mesaj || 'hata';
            }
        })
        .catch(() => {
            kutu.value = orijinal;
            kutu.classList.add('hata');
            durum.style.color = '#f87171';
            durum.textContent = 'bağlantı hatası';
        });
}


/* ============================================================================
   ONAYLA / SÜRE UZAT
   ========================================================================= */
function islemYap(islemTuru, id) {
    const kutuId = (islemTuru === 'onayla') ? ('ay_onay_' + id) : ('ay_uzat_' + id);
    const kutu   = document.getElementById(kutuId);
    const ay     = kutu ? parseInt(kutu.value, 10) : 1;

    if (!ay || ay < 1) { alert('Geçerli bir ay değeri gir.'); if (kutu) kutu.focus(); return; }

    apiCagir({ islem: islemTuru, id: id, ay: ay })
        .then(d => {
            if (d.success) {
                alert(d.message || d.mesaj || 'İşlem tamam.');
                location.reload();
            } else {
                alert('❌ ' + (d.message || d.mesaj || 'İşlem başarısız.'));
            }
        })
        .catch(() => alert('❌ Sunucuya ulaşılamadı.'));
}


/* ============================================================================
   SİLME — 4 saniyelik geri sayım
   Geri sayım iptal edilemez ama yanlışlıkla basmayı zorlaştırır ve ne
   olduğunu görsel olarak anlatır (satır solar, sonra kayarak kaybolur).
   ========================================================================= */
function ogretmenSil(id, btn) {
    if (!confirm('Bu öğretmeni ve tüm başvuru verilerini KALICI olarak silmek istediğine emin misin?')) return;

    // Satır (tablo) ya da kart (bekleyen başvuru) — hangisiyse onu bul
    const kap = btn.closest('tr') || btn.closest('.basvuru');
    const orijinalMetin = btn.innerHTML;
    let saniye = 4;

    btn.disabled = true;
    if (kap) {
        kap.classList.add('siliniyor');
        kap.style.transition = 'all .5s ease';
    }

    const geriSayim = setInterval(() => {
        saniye--;

        if (saniye > 0) {
            btn.innerHTML = '⏳ ' + saniye;
            if (kap) kap.style.opacity = (saniye / 4) + 0.2;
            return;
        }

        clearInterval(geriSayim);
        btn.innerHTML = '🗑️';

        apiCagir({ islem: 'sil', id: id })
            .then(d => {
                if (d.success) {
                    if (kap) {
                        kap.style.transform = 'translateX(-110%)';
                        setTimeout(() => kap.remove(), 420);
                    }
                } else {
                    alert('❌ ' + (d.message || d.mesaj || 'Silinemedi.'));
                    btn.disabled = false;
                    btn.innerHTML = orijinalMetin;
                    if (kap) { kap.classList.remove('siliniyor'); kap.style.opacity = 1; }
                }
            })
            .catch(() => {
                alert('❌ Sunucuya ulaşılamadı.');
                btn.disabled = false;
                btn.innerHTML = orijinalMetin;
                if (kap) { kap.classList.remove('siliniyor'); kap.style.opacity = 1; }
            });
    }, 1000);
}
</script>
</body>
</html>
