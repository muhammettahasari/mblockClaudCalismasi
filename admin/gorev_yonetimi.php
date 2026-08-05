<?php
/* ==========================================================
   Dosya Yolu: admin/gorev_yonetimi.php
   Kullanıcı: Süper Admin
   İşlev: MÜFREDATIN TEK EKRANI — bölümler + altındaki TÜM görevler

   NE İŞE YARAR (HALK DİLİNDE):
   Müfredatın kuşbakışı görüntüsü. Her bölümün altında hem yeni nesil
   (v2 / Blockly) hem eski (v1 / ekran görüntülü) görevler bir arada görünür.
   Buradan sıralar, düzenler, yayına alır veya silersin.

   YENİ EKLENEN ÖZELLİKLER:
    - [2026-08] v2 GÖREVLER DE LİSTELENİYOR:
        Bu sayfa eskiden SADECE `gorevler` (v1) tablosunu gösteriyordu.
        v2 tarafındaki 38 görev burada hiç görünmüyordu; bu yüzden
        "kaç görevim var?" sorusu iki farklı sayfada iki farklı cevap
        veriyordu. Artık ikisi tek ekranda, ayrı rozetlerle.
    - [2026-08] v2 görevlerde yayın aç/kapa ve çözen öğrenci sayısı.
    - [2026-08] Süper Admin girişi zorunlu (#1).
   ========================================================== */

require_once __DIR__ . '/super_auth.php';
superAdminZorunlu();

/* --- Bölümler --- */
$bolumler = $pdo->query("SELECT * FROM bolumler ORDER BY sira_no ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

/* --- v1 görevler (eski sistem: gorevler tablosu) --- */
$v1_ham = $pdo->query(
    "SELECT id, bolum_id, baslik, puan, sira_no FROM gorevler ORDER BY bolum_id ASC, sira_no ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$v1 = [];
foreach ($v1_ham as $g) {
    $v1[$g['bolum_id']][] = $g;
}

/* --- v2 görevler (yeni sistem: v2_gorevler tablosu) --- */
$v2_ham = $pdo->query(
    "SELECT v.id, v.bolum_id, v.gorev_adi, v.puan, v.sira_no, v.yayinda, v.gorev_tipi,
            CHAR_LENGTH(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(v.icerik_json,'$.blueprint.ana_cozum')), '')) AS anahtar_boyutu,
            (SELECT COUNT(*) FROM v2_ogrenci_ilerleme i
              WHERE i.v2_gorev_id = v.id AND i.kazanilan_puan > 0) AS cozen
       FROM v2_gorevler v
      ORDER BY v.bolum_id ASC, v.sira_no ASC, v.id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$v2 = [];
foreach ($v2_ham as $g) {
    $v2[$g['bolum_id']][] = $g;
}

$toplamV1 = count($v1_ham);
$toplamV2 = count($v2_ham);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Müfredat ve Görev Yönetimi | Süper Admin</title>
    <link href="../css/admin_style.css" rel="stylesheet">
    <style>
        body { background: #f0f4f8; font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; }
        .yonetim-container { max-width: 1060px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }

        .bolum-karti { border: 2px solid #e2e8f0; border-radius: 10px; margin-bottom: 22px; overflow: hidden; }
        .bolum-baslik { background: #1e293b; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }

        .grup-basligi { padding: 10px 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; display: flex; justify-content: space-between; align-items: center; }
        .grup-v2 { background: #ede9fe; color: #6d28d9; border-bottom: 1px solid #ddd6fe; }
        .grup-v1 { background: #e0f2fe; color: #0369a1; border-bottom: 1px solid #bae6fd; }

        .gorev-satiri { display: flex; justify-content: space-between; align-items: center; padding: 13px 20px; border-bottom: 1px solid #f1f5f9; background: #fff; transition: 0.2s; gap: 12px; flex-wrap: wrap; }
        .gorev-satiri:hover { background: #f8fafc; }
        .gorev-satiri.taslak { background: #fffbeb; }

        .btn-grup { display: flex; gap: 6px; flex-wrap: wrap; }
        .btn-icon { background: #e2e8f0; border: none; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; transition: 0.2s; text-decoration: none; color: #334155; display: inline-block; }
        .btn-icon:hover { background: #cbd5e1; }
        .btn-edit { background: #3b82f6; color: white; } .btn-edit:hover { background: #2563eb; }
        .btn-delete { background: #ef4444; color: white; } .btn-delete:hover { background: #dc2626; }
        .btn-yayin { background: #10b981; color: white; } .btn-yayin:hover { background: #059669; }
        .btn-taslak { background: #f59e0b; color: white; } .btn-taslak:hover { background: #d97706; }

        .etiket { font-size: 12px; padding: 2px 8px; border-radius: 4px; font-weight: bold; margin-left: 6px; white-space: nowrap; }
        .et-puan   { background: #fef3c7; color: #d97706; }
        .et-cozen  { background: #dcfce7; color: #166534; }
        .et-taslak { background: #fed7aa; color: #9a3412; }
        .et-boss   { background: #fee2e2; color: #991b1b; }
        .et-uyari  { background: #fee2e2; color: #991b1b; }

        .sayac-kutu { background: #fee2e2; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box; }
        .bos-satir { padding: 14px 20px; text-align: center; color: #94a3b8; font-size: 13px; }

        .bilgi { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 14px 18px; border-radius: 10px; font-size: 13px; line-height: 1.7; margin-bottom: 22px; }
        .ozet { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
        .ozet div { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 16px; font-size: 13px; }
        .ozet b { font-size: 18px; display: block; }
    </style>
</head>
<body>

<div class="yonetim-container">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; flex-wrap: wrap; gap: 12px;">
        <h2 style="margin: 0; color: #1e293b;">📚 Müfredat ve Görev Yönetimi</h2>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="super_quiz_yonetimi.php" style="background: #8b5cf6; color: white; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: bold;">🧠 Quiz Yönetimi</a>
            <a href="super_panel.php" style="background: #64748b; color: white; padding: 10px 18px; text-decoration: none; border-radius: 8px; font-weight: bold;">🏠 Panel</a>
            <a href="gorev_ekle.php" style="background: #10b981; color: white; padding: 10px 18px; text-decoration: none; border-radius: 8px; font-weight: bold;">➕ Yeni Görev / Bölüm Ekle</a>
        </div>
    </div>

    <div class="bilgi">
        <b>Görev nereden eklenir?</b> Tek yerden: <a href="gorev_ekle.php">➕ Yeni Görev / Bölüm Ekle</a>.
        O sayfada üstte bölümleri yönetir, altta <b>Blockly tasarlayıcıyla</b> görev kurarsın.<br>
        Bu ekran ise müfredatın <b>tamamını</b> gösterir:
        <span style="background:#ede9fe; color:#6d28d9; padding:1px 7px; border-radius:4px; font-weight:bold;">🚀 v2</span>
        tarayıcı içinde çözülür, sistem otomatik kontrol eder, puan anında verilir. &nbsp;
        <span style="background:#e0f2fe; color:#0369a1; padding:1px 7px; border-radius:4px; font-weight:bold;">📄 v1</span>
        eski sistem: mBlock programında yapılır, öğretmen şifresiyle onaylanır. Çalışmaya devam eder ama yenisi eklenmez.
    </div>

    <div class="ozet">
        <div><b style="color:#8b5cf6;"><?= $toplamV2 ?></b> v2 görev (yeni)</div>
        <div><b style="color:#0ea5e9;"><?= $toplamV1 ?></b> v1 görev (eski)</div>
        <div><b style="color:#1e293b;"><?= count($bolumler) ?></b> bölüm</div>
    </div>

    <?php if (empty($bolumler)): ?>
        <div class="bos-satir">Henüz bölüm yok. <a href="gorev_ekle.php">Bölüm ekleyerek başla →</a></div>
    <?php endif; ?>

    <?php foreach ($bolumler as $b): $b_id = (int) $b['id']; ?>
    <div class="bolum-karti" id="satir_bolum_<?= $b_id ?>">

        <div class="bolum-baslik">
            <div style="font-size: 17px; font-weight: bold;">
                📁 <span id="bolum_metin_<?= $b_id ?>"><?= temizle($b['bolum_adi']) ?></span>
                <span style="font-size:12px; font-weight:normal; opacity:.7;">
                    (<?= count($v2[$b_id] ?? []) ?> v2 · <?= count($v1[$b_id] ?? []) ?> v1)
                </span>
            </div>
            <div class="btn-grup">
                <button class="btn-icon" onclick="siraDegistir('bolum', <?= $b_id ?>, 'yukari')" title="Yukarı Taşı">⬆️</button>
                <button class="btn-icon" onclick="siraDegistir('bolum', <?= $b_id ?>, 'asagi')" title="Aşağı Taşı">⬇️</button>
                <button class="btn-icon btn-edit" onclick="bolumDuzenle(<?= $b_id ?>)">✏️ Düzenle</button>
                <button class="btn-icon btn-delete" onclick="silmeBaslat(this, 'bolum', <?= $b_id ?>)">🗑️ Sil</button>
            </div>
        </div>

        <!-- ============ v2 GÖREVLER ============ -->
        <div class="grup-basligi grup-v2">
            <span>🚀 Yeni Nesil Görevler (Blockly · otomatik kontrol)</span>
            <a href="gorev_ekle.php" style="color:#6d28d9; text-decoration:none; font-weight:bold;">+ Bu bölüme görev ekle</a>
        </div>

        <?php if (!empty($v2[$b_id])): ?>
            <?php foreach ($v2[$b_id] as $g): $g_id = (int) $g['id']; $yayinda = (int) $g['yayinda'] === 1; ?>
            <div class="gorev-satiri <?= $yayinda ? '' : 'taslak' ?>" id="satir_v2_<?= $g_id ?>">
                <div style="flex:1; min-width:220px;">
                    <span style="color: #64748b; font-weight: bold; margin-right: 8px;">Görev <?= (int) $g['sira_no'] ?>:</span>
                    <span style="color: #1e293b; font-size: 15px; font-weight: 500;"><?= temizle($g['gorev_adi']) ?></span>
                    <span class="etiket et-puan">🏆 <?= (int) $g['puan'] ?></span>
                    <?php if ($g['gorev_tipi'] === 'bolum_sonu_canavari'): ?>
                        <span class="etiket et-boss">💀 Boss</span>
                    <?php endif; ?>
                    <?php if (!$yayinda): ?>
                        <span class="etiket et-taslak">○ Taslak</span>
                    <?php endif; ?>
                    <?php if ((int) $g['anahtar_boyutu'] < 30): ?>
                        <span class="etiket et-uyari" title="Cevap anahtarı boş veya çok kısa — öğrenci bu görevi bitiremez">⚠️ Anahtar yok</span>
                    <?php endif; ?>
                    <span class="etiket et-cozen">👤 <?= (int) $g['cozen'] ?> çözdü</span>
                </div>
                <div class="btn-grup">
                    <button class="btn-icon" onclick="v2Sirala(<?= $g_id ?>, 'yukari')" title="Yukarı Taşı">⬆️</button>
                    <button class="btn-icon" onclick="v2Sirala(<?= $g_id ?>, 'asagi')" title="Aşağı Taşı">⬇️</button>
                    <a href="../v2_gorev_sistemi/admin/tasarlayici.php?id=<?= $g_id ?>" class="btn-icon btn-edit">✏️ Düzenle</a>
                    <a href="../v2_gorev_sistemi/ogrenci/oynatici.php?gorev_id=<?= $g_id ?>" target="_blank" class="btn-icon">👁️</a>
                    <button class="btn-icon" onclick="anahtarGoster(<?= $g_id ?>)" title="Cevap anahtarında hangi bloklar var?">🔑</button>
                    <button class="btn-icon <?= $yayinda ? 'btn-taslak' : 'btn-yayin' ?>"
                            onclick="v2Yayin(<?= $g_id ?>, <?= $yayinda ? 0 : 1 ?>)"
                            title="<?= $yayinda ? 'Taslağa çek' : 'Yayına al' ?>">
                        <?= $yayinda ? '⏸️' : '▶️' ?>
                    </button>
                    <button class="btn-icon btn-delete" onclick="v2Sil(<?= $g_id ?>, <?= htmlspecialchars(json_encode($g['gorev_adi']), ENT_QUOTES) ?>)">🗑️</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="bos-satir">Bu bölümde henüz yeni nesil görev yok.</div>
        <?php endif; ?>

        <!-- ============ v1 GÖREVLER ============ -->
        <?php if (!empty($v1[$b_id])): ?>
            <div class="grup-basligi grup-v1">
                <span>📄 Eski Görevler (mBlock programı · öğretmen onaylı)</span>
                <span style="font-weight:normal; text-transform:none;">Yenisi eklenmiyor — mevcutlar çalışmaya devam ediyor</span>
            </div>
            <?php foreach ($v1[$b_id] as $g): $g_id = (int) $g['id']; ?>
            <div class="gorev-satiri" id="satir_gorev_<?= $g_id ?>">
                <div style="flex:1; min-width:220px;">
                    <span style="color: #64748b; font-weight: bold; margin-right: 8px;">Görev <?= (int) $g['sira_no'] ?>:</span>
                    <span style="color: #1e293b; font-size: 15px; font-weight: 500;"><?= temizle($g['baslik']) ?></span>
                    <span class="etiket et-puan">🏆 <?= (int) $g['puan'] ?> Puan</span>
                </div>
                <div class="btn-grup">
                    <button class="btn-icon" onclick="siraDegistir('gorev', <?= $g_id ?>, 'yukari')" title="Yukarı Taşı">⬆️</button>
                    <button class="btn-icon" onclick="siraDegistir('gorev', <?= $g_id ?>, 'asagi')" title="Aşağı Taşı">⬇️</button>
                    <a href="gorev_duzenle.php?id=<?= $g_id ?>" class="btn-icon btn-edit">✏️ Düzenle</a>
                    <button class="btn-icon btn-delete" onclick="silmeBaslat(this, 'gorev', <?= $g_id ?>)">🗑️ Sil</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
    <?php endforeach; ?>

</div>

<script>
/* ====================================================================
 * 1. SİLME (v1 görevler ve bölümler için)
 *
 * [2026-08] 5 SANİYELİK GERİ SAYIM KALDIRILDI:
 * Eskiden silme işlemi 5 saniyelik bir setTimeout ile bekletiliyordu.
 * Kullanıcı o 5 saniye içinde sayfayı yenilerse veya başka sayfaya
 * geçerse zamanlayıcı hiç çalışmıyor ve KAYIT SİLİNMİYORDU — ekranda
 * "sildim ama hâlâ duruyor" durumu bundan kaynaklanıyordu.
 * Artık onaydan hemen sonra siliniyor ve sonuç doğrulanıyor.
 * ================================================================== */
function silmeBaslat(buton, tur, id) {
    const isim = tur === 'bolum'
        ? 'Bu bölüm VE içindeki tüm görevler (v1 + v2) ile quiz soruları'
        : 'Bu görev';

    if (!confirm(`⚠️ ${isim} kalıcı olarak silinecek.\nBu işlem geri alınamaz.\n\nDevam edilsin mi?`)) return;

    const satir = document.getElementById(`satir_${tur}_${id}`);
    const normalIcerik = satir.innerHTML;

    // Silme sürerken satırı kilitle
    satir.innerHTML = `<div class="sayac-kutu"><strong style="color:#ef4444;">⏳ Siliniyor...</strong></div>`;

    fetch('../api/yonetim_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ islem: 'sil', tur: tur, id: id })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            satir.style.transition = 'opacity .3s';
            satir.style.opacity = '0';
            setTimeout(() => satir.remove(), 300);
        } else {
            satir.innerHTML = normalIcerik;
            alert('❌ Silinemedi: ' + (d.message || d.mesaj || 'Bilinmeyen hata'));
        }
    })
    .catch(() => {
        satir.innerHTML = normalIcerik;
        alert('❌ Sunucuya ulaşılamadı, silme işlemi yapılmadı.');
    });
}

/* 2. v1 GÖREV / BÖLÜM SIRALAMA */
function siraDegistir(tur, id, yon) {
    fetch('../api/yonetim_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ islem: 'sira_degistir', tur: tur, id: id, yon: yon })
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.message || d.mesaj); });
}

/* 3. BÖLÜM ADI DÜZENLE */
function bolumDuzenle(id) {
    const span = document.getElementById(`bolum_metin_${id}`);
    const mevcut = span.innerText.trim();
    const yeni = prompt("Bölümün yeni adını girin:", mevcut);
    if (!yeni || yeni.trim() === "" || yeni === mevcut) return;

    fetch('../api/yonetim_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ islem: 'bolum_duzenle', tur: 'bolum', id: id, yeni_ad: yeni.trim() })
    })
    .then(r => r.json())
    .then(d => { if (d.success) span.innerText = yeni.trim(); else alert("Bölüm adı güncellenemedi."); });
}

/* ====================================================================
 * 4. v2 GÖREV İŞLEMLERİ  (v2_gorev_sistemi/api/gorev_api.php)
 * ================================================================== */
function v2Api(govde) {
    return fetch('../v2_gorev_sistemi/api/gorev_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(govde)
    }).then(r => r.json());
}

function v2Sirala(id, yon) {
    v2Api({ islem: 'sira_degistir', gorev_id: id, yon: yon })
        .then(d => { if (d.success) location.reload(); else alert('❌ ' + (d.mesaj || 'İşlem başarısız')); });
}

function v2Yayin(id, yayinda) {
    v2Api({ islem: 'yayin_degistir', gorev_id: id, yayinda: yayinda })
        .then(d => { if (d.success) location.reload(); else alert('❌ ' + (d.mesaj || 'İşlem başarısız')); });
}

/* [2026-08] CEVAP ANAHTARINI GÖSTER
   Öğrenci doğru yaptığını düşünüp eşleşme alamadığında, anahtarın gerçekte
   hangi blokları beklediğini görmek için. En sık sebep: tasarlarken
   çalışma alanında unutulmuş fazladan bloklar. */
function anahtarGoster(id) {
    v2Api({ islem: 'anahtar_ozeti', gorev_id: id })
        .then(d => {
            if (!d.success) { alert('❌ ' + (d.mesaj || 'Anahtar okunamadı')); return; }

            let metin = '🔑 "' + d.gorev_adi + '" cevap anahtarı\n';
            metin += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n';

            if (!d.ana_bloklar || d.ana_bloklar.length === 0) {
                metin += '⚠️ ANA ÇÖZÜM BOŞ!\n\n'
                       + 'Öğrenci bu görevi asla tamamlayamaz.\n'
                       + 'Düzenle butonuna basıp cevap anahtarını kurman gerekiyor.';
            } else {
                metin += 'Ana çözüm (' + d.ana_sayi + ' blok):\n';
                metin += d.ana_bloklar.join('\n') + '\n';

                (d.alternatifler || []).forEach(a => {
                    metin += '\nAlternatif ' + a.no + ' (' + a.sayi + ' blok):\n';
                    metin += a.bloklar.join('\n') + '\n';
                });

                metin += '\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n'
                       + 'Öğrencinin kodu BUNUNLA BİREBİR aynı olmalı.\n'
                       + 'Fazladan blok varsa Düzenle > 2. Bölge\'den sil.';
            }
            alert(metin);
        })
        .catch(() => alert('❌ Sunucuya ulaşılamadı.'));
}

function v2Sil(id, ad) {
    if (!confirm('"' + ad + '" görevi kalıcı olarak silinecek.\nÖğrencilerin bu görevden aldığı puanlar hesaplarında kalır.\n\nEmin misiniz?')) return;

    v2Api({ islem: 'gorev_sil', gorev_id: id })
        .then(d => {
            if (d.success) {
                const satir = document.getElementById('satir_v2_' + id);
                if (satir) satir.remove();
            } else {
                alert('❌ ' + (d.mesaj || 'Silinemedi'));
            }
        });
}
</script>

</body>
</html>
