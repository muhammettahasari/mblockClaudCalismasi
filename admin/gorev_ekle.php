<?php
/* ==========================================================
   Dosya Yolu: admin/gorev_ekle.php
   Kullanıcı: Süper Admin
   İşlev: Bölüm (ünite) yönetimi + Yeni Nesil (v2) Görev Tasarlayıcı

   NE İŞE YARAR (HALK DİLİNDE):
   Müfredatın kurulduğu sayfa. Üstte bölümleri (üniteleri) ekler,
   adlandırır ve sıralarsın; altta o bölüme Blockly tabanlı görev
   tasarlarsın. Öğrenci görevi tarayıcıda çözer, sistem cevabı
   sunucuda otomatik kontrol eder ve puanı anında verir.

   YENİ EKLENEN ÖZELLİKLER:
    - [2026-08] ESKİ GÖREV FORMU KALDIRILDI:
        Burada Quill editörleriyle ekran görüntüsü yapıştırılan v1 formu
        vardı. Resimle anlatılan görevler farklı dillere çevrilemediği için
        yerine v2 tasarlayıcı (Blockly cevap anahtarı) gömüldü.
        Mevcut v1 görevleri çalışmaya devam eder; admin/gorev_yonetimi.php
        üzerinden düzenlenip silinebilir.
    - [2026-08] Bölüm yönetimi bu sayfaya taşındı (ekle / yeniden adlandır /
        sırala / sil) — eskiden sadece "yeni bölüm ekle" kutusu vardı.
    - [2026-08] Süper Admin oturumu zorunlu (Bilinen Sorunlar #1).
   ========================================================== */

require_once __DIR__ . '/super_auth.php';
superAdminZorunlu();

/* --- Bölümler + her birindeki görev sayıları --- */
$bolumler = $pdo->query(
    "SELECT b.id, b.bolum_adi, b.sira_no,
            (SELECT COUNT(*) FROM v2_gorevler v WHERE v.bolum_id = b.id) AS v2_sayisi,
            (SELECT COUNT(*) FROM gorevler g     WHERE g.bolum_id = b.id) AS v1_sayisi,
            (SELECT COUNT(*) FROM quiz_sorulari q WHERE q.bolum_id = b.id) AS soru_sayisi
       FROM bolumler b
      ORDER BY b.sira_no ASC, b.id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

/* --- Tasarlayıcı için değişkenler (paylaşılan gövde bunları bekler) --- */
$duzenlenenId = isset($_GET['gorev']) ? (int) $_GET['gorev'] : 0;
$duzenlenen   = null;

if ($duzenlenenId > 0) {
    $st = $pdo->prepare("SELECT * FROM v2_gorevler WHERE id = ?");
    $st->execute([$duzenlenenId]);
    $duzenlenen = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

$onerilenSira = 1;
if ($duzenlenen) {
    $onerilenSira = (int) $duzenlenen['sira_no'];
} elseif ($bolumler) {
    $st = $pdo->prepare("SELECT COALESCE(MAX(sira_no),0)+1 FROM v2_gorevler WHERE bolum_id = ?");
    $st->execute([$bolumler[0]['id']]);
    $onerilenSira = (int) $st->fetchColumn();
}

// Paylaşılan gövde bu sayfada v2 klasörüne şu yoldan ulaşır
$V2_TABAN = '../v2_gorev_sistemi/';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Bölüm Yönetimi ve Görev Tasarlayıcı | Süper Admin</title>
    <link href="../v2_gorev_sistemi/assets/css/tasarlayici_style.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; padding: 25px 20px 60px; }
        .sarmal { max-width: 1000px; margin: 0 auto; }

        .ust { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 22px; }
        .ust h1 { margin: 0; font-size: 23px; color: #1e293b; }
        .ust p { margin: 6px 0 0 0; color: #64748b; font-size: 13px; }
        .ust-btn { padding: 9px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: bold; background: #334155; color: #fff; }
        .ust-btn:hover { background: #475569; }

        .kart { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 25px; margin-bottom: 22px; box-shadow: 0 4px 6px rgba(0,0,0,0.04); }
        .kart h2 { margin: 0 0 6px 0; font-size: 18px; color: #1e293b; }
        .kart .aciklama { color: #64748b; font-size: 13px; margin: 0 0 18px 0; line-height: 1.6; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        th { color: #64748b; font-size: 12px; text-transform: uppercase; }
        tr:hover td { background: #f8fafc; }

        .bolum-adi { border: 2px solid transparent; background: transparent; padding: 7px 9px; border-radius: 6px; font-size: 14px; font-weight: bold; color: #1e293b; width: 100%; outline: none; transition: 0.2s; }
        .bolum-adi:hover { border-color: #e2e8f0; }
        .bolum-adi:focus { border-color: #4f46e5; background: #fff; }
        .bolum-adi.kaydedildi { border-color: #10b981; background: #ecfdf5; }

        .mini-btn { background: #fff; border: 1px solid #cbd5e1; color: #475569; padding: 5px 9px; border-radius: 6px; cursor: pointer; font-size: 12px; margin-right: 3px; transition: 0.2s; }
        .mini-btn:hover { background: #f1f5f9; }
        .mini-btn.sil:hover { background: #fee2e2; border-color: #fecaca; color: #991b1b; }

        .rozet { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: bold; margin-right: 4px; }
        .rozet-v2 { background: #ede9fe; color: #6d28d9; }
        .rozet-v1 { background: #e0f2fe; color: #0369a1; }
        .rozet-soru { background: #fef3c7; color: #92400e; }

        .ekle-satiri { display: flex; gap: 10px; margin-top: 18px; }
        .ekle-satiri input { flex: 1; padding: 11px; border: 2px solid #cbd5e1; border-radius: 8px; font-size: 15px; outline: none; }
        .ekle-satiri input:focus { border-color: #10b981; }
        .btn-ekle { background: #10b981; color: #fff; border: none; padding: 11px 22px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 15px; }
        .btn-ekle:hover { background: #059669; }

        .bilgi-kutu { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 14px 16px; border-radius: 10px; font-size: 13px; line-height: 1.6; margin-bottom: 20px; }
        .bos { text-align: center; padding: 30px; color: #94a3b8; }
    </style>
</head>
<body>

<div class="sarmal">

    <div class="ust">
        <div>
            <h1>📚 Bölüm Yönetimi ve Görev Tasarlayıcı</h1>
            <p>Önce bölümü (üniteyi) oluştur, sonra altına görev tasarla.</p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="../v2_gorev_sistemi/admin/gorev_listesi.php" class="ust-btn" style="background:#8b5cf6;">🚀 v2 Görev Listesi</a>
            <a href="gorev_yonetimi.php" class="ust-btn" style="background:#0ea5e9;">📄 Eski (v1) Görevler</a>
            <a href="super_panel.php" class="ust-btn">← Panel</a>
        </div>
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
                <th style="width:230px">İçerik</th>
                <th style="width:150px">İşlem</th>
            </tr>
            <?php foreach ($bolumler as $b): ?>
            <tr id="bolum-<?= (int) $b['id'] ?>">
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
                    <span class="rozet rozet-v2"><?= (int) $b['v2_sayisi'] ?> v2 görev</span>
                    <?php if ((int) $b['v1_sayisi'] > 0): ?>
                        <span class="rozet rozet-v1"><?= (int) $b['v1_sayisi'] ?> v1</span>
                    <?php endif; ?>
                    <?php if ((int) $b['soru_sayisi'] > 0): ?>
                        <span class="rozet rozet-soru"><?= (int) $b['soru_sayisi'] ?> quiz</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a class="mini-btn" href="../v2_gorev_sistemi/admin/gorev_listesi.php?bolum=<?= (int) $b['id'] ?>">Görevler</a>
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

    <!-- ==================== 2. GÖREV TASARLAYICI (v2) ==================== -->
    <div class="kart">
        <h2>2️⃣ <?= $duzenlenen ? 'Görevi Düzenle' : 'Yeni Görev Tasarla' ?></h2>
        <p class="aciklama">
            Hikâyeyi yaz, sonra öğrencinin kurmasını beklediğin doğru kod dizilimini
            <b>Ana Çözüm</b> alanına kur. Öğrenci "Görevimi Kontrol Et" dediğinde sistem
            cevabı sunucuda karşılaştırır ve doğruysa puanı anında verir —
            öğretmen şifresi gerekmez.
        </p>

        <div class="bilgi-kutu">
            ℹ️ <b>Eski görev formu (v1) buradan kaldırıldı.</b>
            Ekran görüntüsüyle anlatılan görevler farklı dillere çevrilemediği için
            yerini bu sisteme bıraktı. Mevcut v1 görevlerin çalışmaya devam ediyor;
            onları <a href="gorev_yonetimi.php">Eski (v1) Görevler</a> sayfasından
            düzenleyip silebilirsin.
        </div>

        <?php if (empty($bolumler)): ?>
            <div class="bos" style="color:#991b1b;">
                ⚠️ Görev tasarlamadan önce yukarıdan en az bir <b>bölüm</b> eklemelisin.
            </div>
        <?php else: ?>
            <div class="v2-container" style="max-width:none; margin:0; padding:0; background:transparent; box-shadow:none;">
                <?php require __DIR__ . '/../v2_gorev_sistemi/admin/_tasarlayici_govde.php'; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
/* ====================================================================
 * BÖLÜM YÖNETİMİ — hepsi api/yonetim_api.php ve api/bolum_ekle.php ile
 * ================================================================== */

function bolumEkle() {
    const kutu = document.getElementById('yeniBolumAdi');
    const ad = kutu.value.trim();
    if (!ad) { kutu.focus(); return; }

    fetch('../api/bolum_ekle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ bolum_adi: ad })
    })
        .then(r => r.json())
        .then(d => {
            if (d.success) { location.reload(); }
            else { alert('❌ ' + (d.message || d.mesaj || 'Bölüm eklenemedi.')); }
        })
        .catch(() => alert('❌ Sunucuya ulaşılamadı.'));
}

function bolumAdiKaydet(kutu) {
    const yeni = kutu.value.trim();
    const eski = kutu.dataset.orijinal;
    if (yeni === eski) return;

    if (!yeni) { kutu.value = eski; return; }

    fetch('../api/yonetim_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ islem: 'bolum_duzenle', tur: 'bolum', id: kutu.dataset.id, yeni_ad: yeni })
    })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                kutu.dataset.orijinal = yeni;
                kutu.classList.add('kaydedildi');
                setTimeout(() => kutu.classList.remove('kaydedildi'), 2000);
            } else {
                kutu.value = eski;
                alert('❌ ' + (d.message || d.mesaj || 'Kaydedilemedi.'));
            }
        })
        .catch(() => { kutu.value = eski; alert('❌ Sunucuya ulaşılamadı.'); });
}

function bolumSirala(id, yon) {
    fetch('../api/yonetim_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ islem: 'sira_degistir', tur: 'bolum', id: id, yon: yon })
    })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); })
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

    fetch('../api/yonetim_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ islem: 'sil', tur: 'bolum', id: id })
    })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
            else alert('❌ ' + (d.message || d.mesaj || 'Silinemedi.'));
        })
        .catch(() => alert('❌ Sunucuya ulaşılamadı.'));
}
</script>

</body>
</html>
