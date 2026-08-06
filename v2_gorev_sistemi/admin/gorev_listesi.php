<?php
/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/admin/gorev_listesi.php
 * KULLANICI / SAYFA: Süper Admin -> Müfredat ve Görev Yönetimi (TEK EKRAN)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Müfredatın kuşbakışı görüntüsü ve tek yönetim ekranı.
 *   1. Üstte BÖLÜMLER (üniteler): ekle, adını değiştir, sırala, sil
 *   2. Altta her bölümün görevleri: sırala, düzenle, önizle, yayına al, sil
 *   3. Eski (v1) görevler de aynı ekranda görünür
 *
 * [2026-08] ÜÇ SAYFA BURADA BİRLEŞTİRİLDİ:
 *   - admin/gorev_ekle.php      (bölüm yönetimi + tasarlayıcı)  -> SİLİNDİ
 *   - admin/gorev_yonetimi.php  (müfredat listesi)              -> SİLİNDİ
 *   - v2_gorev_sistemi/admin/gorev_listesi.php (bu dosya)       -> TEK SAYFA
 *
 * Neden birleştirildi? Aynı işi yapan üç ayrı sayfa vardı; "kaç görevim var?"
 * sorusu üç farklı yerde farklı cevap veriyordu ve hangi sayfadan görev
 * ekleneceği belirsizdi. Görev TASARLAMA işi tek yerde: tasarlayici.php.
 *
 * BAĞLI API'LER:
 *   ../api/gorev_api.php        v2 görev sırala/yayın/sil
 *   ../../api/bolum_ekle.php    yeni bölüm
 *   ../../api/yonetim_api.php   bölüm adı/sıra/sil + v1 görev sırala/sil
 * ============================================================================
 */

require_once __DIR__ . '/../../admin/super_auth.php';
superAdminZorunlu();

/* --- Bölümler + içerik sayaçları (tek sorgu) --- */
$bolumler = $pdo->query(
    "SELECT b.id, b.bolum_adi, b.sira_no,
            (SELECT COUNT(*) FROM v2_gorevler v    WHERE v.bolum_id = b.id) AS v2_sayisi,
            (SELECT COUNT(*) FROM gorevler g       WHERE g.bolum_id = b.id) AS v1_sayisi,
            (SELECT COUNT(*) FROM quiz_sorulari q  WHERE q.bolum_id = b.id) AS soru_sayisi
       FROM bolumler b
      ORDER BY b.sira_no ASC, b.id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

/* --- v2 görevler --- */
$v2_ham = $pdo->query(
    "SELECT v.id, v.bolum_id, v.gorev_adi, v.puan, v.sira_no, v.yayinda, v.gorev_tipi,
            CHAR_LENGTH(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(v.icerik_json,'$.blueprint.ana_cozum')), '')) AS anahtar_boyutu,
            (SELECT COUNT(*) FROM v2_ogrenci_ilerleme i
              WHERE i.v2_gorev_id = v.id AND i.kazanilan_puan > 0) AS cozen
       FROM v2_gorevler v
      ORDER BY v.bolum_id ASC, v.sira_no ASC, v.id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$v2 = [];
foreach ($v2_ham as $g) { $v2[(int) $g['bolum_id']][] = $g; }

/* --- v1 (eski) görevler --- */
$v1_ham = $pdo->query(
    "SELECT id, bolum_id, baslik, puan, sira_no FROM gorevler ORDER BY bolum_id ASC, sira_no ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$v1 = [];
foreach ($v1_ham as $g) { $v1[(int) $g['bolum_id']][] = $g; }

$toplamV2 = count($v2_ham);
$toplamV1 = count($v1_ham);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Müfredat ve Görev Yönetimi | Süper Admin</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 25px 20px 60px; }
        .sarmal { max-width: 1100px; margin: 0 auto; }

        .ust { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 22px; }
        .ust h1 { margin: 0; font-size: 22px; }
        .ust p { margin: 6px 0 0 0; color: #94a3b8; font-size: 13px; }
        .btnler { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { padding: 9px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: bold; border: none; cursor: pointer; transition: 0.2s; display: inline-block; }
        .btn-gri { background: #334155; color: #f8fafc; } .btn-gri:hover { background: #475569; }
        .btn-mor { background: #8b5cf6; color: #fff; }    .btn-mor:hover { background: #7c3aed; }

        .kart { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin-bottom: 22px; }
        .kart h2 { margin: 0 0 6px 0; font-size: 17px; }
        .kart .aciklama { margin: 0 0 16px 0; color: #94a3b8; font-size: 13px; line-height: 1.6; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px 10px; text-align: left; border-bottom: 1px solid #334155; font-size: 14px; }
        th { color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: .4px; }

        .mini-btn { background: none; border: 1px solid #475569; color: #cbd5e1; padding: 4px 9px; border-radius: 6px; cursor: pointer; font-size: 12px; margin-right: 3px; text-decoration: none; display: inline-block; transition: 0.2s; }
        .mini-btn:hover { background: #334155; }
        .mini-btn.sil:hover { background: #7f1d1d; border-color: #991b1b; color: #fecaca; }

        .bolum-adi { width: 100%; background: #0f172a; border: 2px solid #334155; color: #f8fafc; padding: 8px 10px; border-radius: 7px; font-size: 14px; font-weight: bold; outline: none; }
        .bolum-adi:focus { border-color: #8b5cf6; }
        .bolum-adi.kaydedildi { border-color: #10b981; }

        .rozet { padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: bold; margin-right: 4px; display: inline-block; }
        .rozet-v2 { background: #4c1d95; color: #ddd6fe; }
        .rozet-v1 { background: #0c4a6e; color: #bae6fd; }
        .rozet-soru { background: #78350f; color: #fde68a; }
        .rozet-yayin { background: #064e3b; color: #a7f3d0; }
        .rozet-taslak { background: #78350f; color: #fde68a; }
        .rozet-boss { background: #7f1d1d; color: #fecaca; }
        .rozet-uyari { background: #7f1d1d; color: #fecaca; }
        .rozet-cozen { background: #334155; color: #cbd5e1; }

        .ekle-satiri { display: flex; gap: 10px; margin-top: 16px; }
        .ekle-satiri input { flex: 1; padding: 11px; background: #0f172a; border: 2px solid #334155; color: #f8fafc; border-radius: 8px; font-size: 15px; outline: none; }
        .ekle-satiri input:focus { border-color: #10b981; }
        .btn-ekle { background: #10b981; color: #fff; border: none; padding: 11px 22px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 15px; }
        .btn-ekle:hover { background: #059669; }

        /* ================================================================
           BÖLÜM KARTLARI — [2026-08] arka plandan ayrışsın diye açık ton,
           ayrıca tek/çift bölümler iki farklı tonda ki sınırları belli olsun.
           ================================================================ */
        .bolum-blok { border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; border: 1px solid #3b4a63; }
        .bolum-blok.ton-a { background: #1c2942; }
        .bolum-blok.ton-b { background: #223150; }

        .bolum-ust { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding-bottom: 10px; margin-bottom: 12px; border-bottom: 1px solid rgba(148,163,184,.22); }
        .bolum-ust h3 { margin: 0; font-size: 16px; }
        .bolum-ust .no { color: #a78bfa; font-weight: 900; margin-right: 6px; }

        .grup-basligi { font-size: 11.5px; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; margin: 12px 0 7px; }

        .gorev-satiri { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; background: rgba(15,23,42,.55); border: 1px solid #334155; border-radius: 9px; padding: 10px 12px; margin-bottom: 7px; }
        .gorev-satiri.taslak { opacity: .68; border-style: dashed; }
        .gorev-satiri .sira { color: #64748b; font-weight: bold; }
        .gorev-satiri .ad { font-size: 14.5px; font-weight: 500; }

        .bos { text-align: center; padding: 26px; color: #94a3b8; font-size: 13.5px; }
        .bos-satir { color: #64748b; font-size: 13px; font-style: italic; padding: 6px 2px; }

        .ozet { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .ozet div { background: #0f172a; border: 1px solid #334155; border-radius: 9px; padding: 9px 15px; font-size: 13px; }
        .ozet b { color: #a78bfa; font-size: 16px; }
    </style>
</head>
<body>
<?php require __DIR__ . '/../../bilesenler/super_gezinme.php'; ?>

<div class="sarmal">

    <div class="ust">
        <div>
            <h1>📚 Müfredat ve Görev Yönetimi</h1>
            <p>Önce bölümü (üniteyi) oluştur, sonra "Yeni Görev Tasarla" ile içine görev ekle.</p>
        </div>
        <div class="btnler">
            <a href="tasarlayici.php" class="btn btn-mor">+ Yeni Görev Tasarla</a>
            <a href="../../admin/super_panel.php" class="btn btn-gri">← Panele Dön</a>
        </div>
    </div>

    <div class="ozet">
        <div><b><?= count($bolumler) ?></b> bölüm</div>
        <div><b><?= $toplamV2 ?></b> yeni nesil görev</div>
        <?php if ($toplamV1 > 0): ?><div><b><?= $toplamV1 ?></b> eski (v1) görev</div><?php endif; ?>
    </div>


    <!-- ==================== 1. BÖLÜM YÖNETİMİ ==================== -->
    <div class="kart">
        <h2>1️⃣ Bölümler (Üniteler)</h2>
        <p class="aciklama">
            Bölüm adına tıklayıp değiştirebilirsin — alandan çıkınca otomatik kaydedilir.
            Oklarla sırayı değiştir. Öğrenciler bölümleri bu sırayla görür.
        </p>

        <?php if (empty($bolumler)): ?>
            <div class="bos">Henüz hiç bölüm yok. Aşağıdan ilk bölümü ekle.</div>
        <?php else: ?>
        <table>
            <tr>
                <th style="width:70px">Sıra</th>
                <th>Bölüm Adı</th>
                <th style="width:240px">İçerik</th>
                <th style="width:70px">İşlem</th>
            </tr>
            <?php foreach ($bolumler as $b): ?>
            <tr>
                <td>
                    <button class="mini-btn" onclick="bolumSirala(<?= (int) $b['id'] ?>,'yukari')" title="Yukarı">▲</button>
                    <button class="mini-btn" onclick="bolumSirala(<?= (int) $b['id'] ?>,'asagi')" title="Aşağı">▼</button>
                </td>
                <td>
                    <input type="text" class="bolum-adi" value="<?= temizle($b['bolum_adi']) ?>"
                           data-id="<?= (int) $b['id'] ?>" data-orijinal="<?= temizle($b['bolum_adi']) ?>"
                           onblur="bolumAdiKaydet(this)"
                           onkeydown="if(event.key==='Enter'){event.preventDefault(); this.blur();}">
                </td>
                <td>
                    <span class="rozet rozet-v2"><?= (int) $b['v2_sayisi'] ?> görev</span>
                    <?php if ((int) $b['v1_sayisi'] > 0): ?>
                        <span class="rozet rozet-v1"><?= (int) $b['v1_sayisi'] ?> eski</span>
                    <?php endif; ?>
                    <?php if ((int) $b['soru_sayisi'] > 0): ?>
                        <span class="rozet rozet-soru"><?= (int) $b['soru_sayisi'] ?> quiz</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="mini-btn sil" onclick="bolumSil(<?= (int) $b['id'] ?>, <?= htmlspecialchars(json_encode($b['bolum_adi']), ENT_QUOTES) ?>, <?= (int) $b['v1_sayisi'] + (int) $b['v2_sayisi'] + (int) $b['soru_sayisi'] ?>)">🗑️</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <div class="ekle-satiri">
            <input type="text" id="yeniBolumAdi" placeholder="Yeni bölüm adı (Örn: Döngüler)"
                   onkeydown="if(event.key==='Enter'){event.preventDefault(); bolumEkle();}">
            <button class="btn-ekle" onclick="bolumEkle()">+ Bölüm Ekle</button>
        </div>
    </div>


    <!-- ==================== 2. GÖREVLER ==================== -->
    <div class="kart">
        <h2>2️⃣ Görevler</h2>
        <p class="aciklama">
            Her bölümün altında o bölümün görevleri sırayla listelenir.
            🔑 düğmesi cevap anahtarında hangi blokların olduğunu gösterir.
        </p>

        <?php if (empty($bolumler)): ?>
            <div class="bos">Önce bir bölüm ekle.</div>
        <?php else: ?>
            <?php foreach ($bolumler as $i => $b): $b_id = (int) $b['id']; ?>
            <div class="bolum-blok <?= $i % 2 === 0 ? 'ton-a' : 'ton-b' ?>">

                <div class="bolum-ust">
                    <h3><span class="no"><?= (int) $b['sira_no'] ?>.</span><?= temizle($b['bolum_adi']) ?></h3>
                    <a class="mini-btn" href="tasarlayici.php?bolum=<?= $b_id ?>">+ Bu bölüme görev ekle</a>
                </div>

                <!-- ---------- v2 (yeni nesil) görevler ---------- -->
                <?php if (!empty($v2[$b_id])): ?>
                    <?php foreach ($v2[$b_id] as $g): $g_id = (int) $g['id']; $yayinda = (int) $g['yayinda'] === 1; ?>
                    <div class="gorev-satiri <?= $yayinda ? '' : 'taslak' ?>" id="satir_v2_<?= $g_id ?>">
                        <div style="flex:1; min-width:230px;">
                            <span class="sira">Görev <?= (int) $g['sira_no'] ?>:</span>
                            <span class="ad"><?= temizle($g['gorev_adi']) ?></span>
                            <span class="rozet rozet-cozen">🏆 <?= (int) $g['puan'] ?></span>
                            <?php if ($g['gorev_tipi'] === 'bolum_sonu_canavari'): ?>
                                <span class="rozet rozet-boss">💀 Boss</span>
                            <?php endif; ?>
                            <?php if (!$yayinda): ?>
                                <span class="rozet rozet-taslak">○ Taslak</span>
                            <?php endif; ?>
                            <?php if ((int) $g['anahtar_boyutu'] < 30): ?>
                                <span class="rozet rozet-uyari" title="Cevap anahtarı boş veya çok kısa — öğrenci bu görevi bitiremez">⚠️ Anahtar yok</span>
                            <?php endif; ?>
                            <span class="rozet rozet-cozen">👤 <?= (int) $g['cozen'] ?> çözdü</span>
                        </div>
                        <div>
                            <button class="mini-btn" onclick="v2Sirala(<?= $g_id ?>,'yukari')" title="Yukarı taşı">▲</button>
                            <button class="mini-btn" onclick="v2Sirala(<?= $g_id ?>,'asagi')" title="Aşağı taşı">▼</button>
                            <a class="mini-btn" href="tasarlayici.php?id=<?= $g_id ?>">✏️ Düzenle</a>
                            <a class="mini-btn" href="../ogrenci/oynatici.php?gorev_id=<?= $g_id ?>" target="_blank">👁️</a>
                            <button class="mini-btn" onclick="anahtarGoster(<?= $g_id ?>)" title="Cevap anahtarında hangi bloklar var?">🔑</button>
                            <button class="mini-btn" onclick="v2Yayin(<?= $g_id ?>, <?= $yayinda ? 0 : 1 ?>)"
                                    title="<?= $yayinda ? 'Taslağa çek' : 'Yayına al' ?>"><?= $yayinda ? '⏸️' : '▶️' ?></button>
                            <button class="mini-btn sil" onclick="v2Sil(<?= $g_id ?>, <?= htmlspecialchars(json_encode($g['gorev_adi']), ENT_QUOTES) ?>)">🗑️</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bos-satir">Bu bölümde henüz görev yok.</div>
                <?php endif; ?>

                <!-- ---------- v1 (eski) görevler ---------- -->
                <?php if (!empty($v1[$b_id])): ?>
                    <div class="grup-basligi">📄 Eski görevler (mBlock ekran görüntülü) — yenisi eklenmiyor</div>
                    <?php foreach ($v1[$b_id] as $g): $g_id = (int) $g['id']; ?>
                    <div class="gorev-satiri" id="satir_gorev_<?= $g_id ?>">
                        <div style="flex:1; min-width:230px;">
                            <span class="sira">Görev <?= (int) $g['sira_no'] ?>:</span>
                            <span class="ad"><?= temizle($g['baslik']) ?></span>
                            <span class="rozet rozet-cozen">🏆 <?= (int) $g['puan'] ?></span>
                            <span class="rozet rozet-v1">v1</span>
                        </div>
                        <div>
                            <button class="mini-btn" onclick="v1Sirala(<?= $g_id ?>,'yukari')" title="Yukarı taşı">▲</button>
                            <button class="mini-btn" onclick="v1Sirala(<?= $g_id ?>,'asagi')" title="Aşağı taşı">▼</button>
                            <a class="mini-btn" href="../../admin/gorev_duzenle.php?id=<?= $g_id ?>">✏️ Düzenle</a>
                            <button class="mini-btn sil" onclick="v1Sil(<?= $g_id ?>, <?= htmlspecialchars(json_encode($g['baslik']), ENT_QUOTES) ?>)">🗑️</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>


<!-- Cevap anahtarı önizleme penceresi -->
<div id="anahtarPerde" style="display:none; position:fixed; inset:0; background:rgba(2,6,23,.75); z-index:900; align-items:center; justify-content:center; padding:20px;">
    <div style="background:#1e293b; border:1px solid #475569; border-radius:14px; max-width:520px; width:100%; max-height:80vh; overflow:auto; padding:22px;">
        <h3 style="margin:0 0 12px; font-size:17px;" id="anahtarBaslik">Cevap Anahtarı</h3>
        <div id="anahtarIcerik" style="font-size:13.5px; line-height:1.7; color:#cbd5e1;">Yükleniyor…</div>
        <button class="btn btn-gri" style="width:100%; margin-top:16px;" onclick="anahtarKapat()">Kapat</button>
    </div>
</div>


<script>
/* ============================================================================
   API yardımcıları
   ========================================================================= */
function v2Api(govde) {
    return fetch('../api/gorev_api.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin', body: JSON.stringify(govde)
    }).then(r => r.json());
}

function yonetimApi(govde) {
    return fetch('../../api/yonetim_api.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin', body: JSON.stringify(govde)
    }).then(r => r.json());
}

function hataGoster(d, varsayilan) {
    alert('❌ ' + (d && (d.mesaj || d.message) ? (d.mesaj || d.message) : varsayilan));
}


/* ============================================================================
   BÖLÜM YÖNETİMİ
   ========================================================================= */
function bolumEkle() {
    const kutu = document.getElementById('yeniBolumAdi');
    const ad = kutu.value.trim();
    if (!ad) { kutu.focus(); return; }

    fetch('../../api/bolum_ekle.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin', body: JSON.stringify({ bolum_adi: ad })
    })
    .then(r => r.json())
    .then(d => d.success ? location.reload() : hataGoster(d, 'Bölüm eklenemedi.'))
    .catch(() => alert('❌ Sunucuya ulaşılamadı.'));
}

function bolumAdiKaydet(kutu) {
    const yeni = kutu.value.trim();
    const eski = kutu.dataset.orijinal;
    if (yeni === eski) return;
    if (!yeni) { kutu.value = eski; return; }

    yonetimApi({ islem: 'bolum_duzenle', tur: 'bolum', id: kutu.dataset.id, yeni_ad: yeni })
        .then(d => {
            if (d.success) {
                kutu.dataset.orijinal = yeni;
                kutu.classList.add('kaydedildi');
                setTimeout(() => kutu.classList.remove('kaydedildi'), 2000);
            } else {
                kutu.value = eski;
                hataGoster(d, 'Kaydedilemedi.');
            }
        })
        .catch(() => { kutu.value = eski; alert('❌ Sunucuya ulaşılamadı.'); });
}

function bolumSirala(id, yon) {
    yonetimApi({ islem: 'sira_degistir', tur: 'bolum', id: id, yon: yon })
        .then(d => { if (d.success) location.reload(); else hataGoster(d, 'Sıra değiştirilemedi.'); })
        .catch(() => alert('❌ Sunucuya ulaşılamadı.'));
}

function bolumSil(id, ad, icerikSayisi) {
    let uyari = '"' + ad + '" bölümü silinecek.';
    if (icerikSayisi > 0) {
        uyari += '\n\n⚠️ DİKKAT: Bu bölüme bağlı ' + icerikSayisi +
                 ' görev/soru da BİRLİKTE SİLİNİR ve geri alınamaz!';
    }
    uyari += '\n\nDevam edilsin mi?';
    if (!confirm(uyari)) return;

    yonetimApi({ islem: 'sil', tur: 'bolum', id: id })
        .then(d => { if (d.success) location.reload(); else hataGoster(d, 'Silinemedi.'); })
        .catch(() => alert('❌ Sunucuya ulaşılamadı.'));
}


/* ============================================================================
   v2 GÖREVLER
   ========================================================================= */
function v2Sirala(id, yon) {
    v2Api({ islem: 'sira_degistir', gorev_id: id, yon: yon })
        .then(d => { if (d.success) location.reload(); else hataGoster(d, 'İşlem başarısız'); });
}

function v2Yayin(id, yayinda) {
    v2Api({ islem: 'yayin_degistir', gorev_id: id, yayinda: yayinda })
        .then(d => { if (d.success) location.reload(); else hataGoster(d, 'İşlem başarısız'); });
}

function v2Sil(id, ad) {
    if (!confirm('"' + ad + '" görevi kalıcı olarak silinecek.\n' +
                 'Öğrencilerin bu görevden aldığı puanlar hesaplarında kalır.\n\nEmin misiniz?')) return;

    v2Api({ islem: 'gorev_sil', gorev_id: id })
        .then(d => {
            if (d.success) {
                const satir = document.getElementById('satir_v2_' + id);
                if (satir) satir.remove();
            } else { hataGoster(d, 'Silinemedi'); }
        });
}

/* Cevap anahtarında hangi bloklar var? — öğrenci neden geçemiyor sorusu için */
function anahtarGoster(id) {
    const perde = document.getElementById('anahtarPerde');
    perde.style.display = 'flex';
    document.getElementById('anahtarIcerik').textContent = 'Yükleniyor…';

    v2Api({ islem: 'anahtar_ozeti', gorev_id: id }).then(d => {
        if (!d.success) { document.getElementById('anahtarIcerik').textContent = d.mesaj || 'Alınamadı.'; return; }

        document.getElementById('anahtarBaslik').textContent = '🔑 ' + (d.gorev_adi || 'Cevap Anahtarı');

        let html = '<b>Ana çözüm (' + d.ana_sayi + ' blok):</b><ul style="margin:6px 0 0;padding-left:20px;">';
        (d.ana_bloklar || []).forEach(b => { html += '<li>' + b + '</li>'; });
        html += '</ul>';

        (d.alternatifler || []).forEach(a => {
            html += '<b style="display:block;margin-top:12px;">Alternatif ' + a.no + ' (' + a.sayi + ' blok):</b>';
            html += '<ul style="margin:6px 0 0;padding-left:20px;">';
            (a.bloklar || []).forEach(b => { html += '<li>' + b + '</li>'; });
            html += '</ul>';
        });

        if (d.ana_sayi === 0) {
            html = '<div style="color:#fca5a5;">⚠️ Bu görevin cevap anahtarı boş. ' +
                   'Öğrenci görevi bitiremez — tasarlayıcıdan ana çözümü kur.</div>';
        }

        document.getElementById('anahtarIcerik').innerHTML = html;
    });
}

function anahtarKapat() { document.getElementById('anahtarPerde').style.display = 'none'; }

document.getElementById('anahtarPerde').addEventListener('click', e => {
    if (e.target.id === 'anahtarPerde') anahtarKapat();
});


/* ============================================================================
   v1 (ESKİ) GÖREVLER — sadece sırala ve sil; yenisi eklenmiyor
   ========================================================================= */
function v1Sirala(id, yon) {
    yonetimApi({ islem: 'sira_degistir', tur: 'gorev', id: id, yon: yon })
        .then(d => { if (d.success) location.reload(); else hataGoster(d, 'Sıra değiştirilemedi.'); })
        .catch(() => alert('❌ Sunucuya ulaşılamadı.'));
}

function v1Sil(id, ad) {
    if (!confirm('"' + ad + '" (eski v1 görev) kalıcı olarak silinecek.\n\nEmin misiniz?')) return;

    yonetimApi({ islem: 'sil', tur: 'gorev', id: id })
        .then(d => {
            if (d.success) {
                const satir = document.getElementById('satir_gorev_' + id);
                if (satir) satir.remove();
            } else { hataGoster(d, 'Silinemedi.'); }
        })
        .catch(() => alert('❌ Sunucuya ulaşılamadı.'));
}
</script>
</body>
</html>
