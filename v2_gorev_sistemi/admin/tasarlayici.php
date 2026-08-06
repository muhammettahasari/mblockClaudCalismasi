<?php
/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/admin/tasarlayici.php
 * KULLANICI / SAYFA: Süper Admin -> Görev Tasarlayıcı
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Süper Admin'in görevleri yazdığı, Ana Çözüm (Blueprint) ve Alternatif
 * Çözümleri tasarladığı bağımsız sayfa.
 *
 * NOT: Formun kendisi _tasarlayici_govde.php içindedir.
 *
 * [2026-08] GÖREV TASARLAMANIN TEK ADRESİ BURASI.
 * Eskiden aynı tasarlayıcı admin/gorev_ekle.php içine de gömülüydü; iki ayrı
 * adresten aynı iş yapılıyor, hangisinin güncel olduğu belirsizdi.
 * gorev_ekle.php kaldırıldı, içindeki bölüm yönetimi gorev_listesi.php'ye taşındı.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [Önceki] Kodlar modüler JS dosyalarına bölündü, Blueprint arayüzü geldi.
 *  - [2026-08] Süper Admin oturumu zorunlu hale getirildi (Bilinen Sorunlar #1).
 *  - [2026-08] Bölüm / sıra no / puan alanları eklendi; sabit kodlama kaldırıldı (#17).
 *  - [2026-08] ?bolum=N ile açılırsa o bölüm hazır seçili gelir.
 * ============================================================================
 */

require_once __DIR__ . '/../../admin/super_auth.php';
superAdminZorunlu();

// Düzenleme modu: ?id=12 ile gelen mevcut görevi yükler
$duzenlenenId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$duzenlenen   = null;

if ($duzenlenenId > 0) {
    $st = $pdo->prepare("SELECT * FROM v2_gorevler WHERE id = ?");
    $st->execute([$duzenlenenId]);
    $duzenlenen = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

$bolumler = $pdo->query("SELECT id, bolum_adi, sira_no FROM bolumler ORDER BY sira_no ASC")->fetchAll(PDO::FETCH_ASSOC);

/* [2026-08] "+ Bu bölüme görev ekle" bağlantısıyla gelindiğinde o bölüm
   hazır seçili olsun; admin listeden geldiği bölümü tekrar aramasın. */
$onSecilenBolum = isset($_GET['bolum']) ? (int) $_GET['bolum'] : 0;
if ($duzenlenen) {
    $onSecilenBolum = (int) $duzenlenen['bolum_id'];
} elseif ($onSecilenBolum <= 0 && $bolumler) {
    $onSecilenBolum = (int) $bolumler[0]['id'];
}

// Seçili bölüm için bir sonraki sıra numarasını öner
$onerilenSira = 1;
if ($duzenlenen) {
    $onerilenSira = (int) $duzenlenen['sira_no'];
} elseif ($onSecilenBolum > 0) {
    $st = $pdo->prepare("SELECT COALESCE(MAX(sira_no),0)+1 FROM v2_gorevler WHERE bolum_id = ?");
    $st->execute([$onSecilenBolum]);
    $onerilenSira = (int) $st->fetchColumn();
}

// Paylaşılan gövde için yollar (bu sayfa v2_gorev_sistemi/admin/ içinde)
$V2_TABAN = '../';      // v2_gorev_sistemi/ klasörüne
$KOK_ONEK = '../../';   // proje köküne (önbellek kırıcı için)
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>V2 Görev Tasarlayıcı (Modüler Blueprint)</title>
    <link href="../assets/css/tasarlayici_style.css" rel="stylesheet">
</head>
<body>

<div class="v2-container">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <h2 style="margin:0;">
            🚀 <?= $duzenlenen ? 'Görevi Düzenle' : 'Yeni Nesil Görev Tasarlayıcı' ?>
            <span style="font-size:14px; font-weight:normal; color:#64748b;">(Blueprint Sistemi)</span>
        </h2>
        <div style="display:flex; gap:10px;">
            <a href="gorev_listesi.php" style="background:#334155; color:#fff; padding:9px 16px; border-radius:8px; text-decoration:none; font-weight:bold; font-size:14px;">← Müfredat</a>
            <a href="../../admin/super_panel.php" style="background:#64748b; color:#fff; padding:9px 16px; border-radius:8px; text-decoration:none; font-weight:bold; font-size:14px;">Panel</a>
        </div>
    </div>

    <?php if (empty($bolumler)): ?>
        <div style="background:#fee2e2; border:1px solid #fecaca; color:#991b1b; padding:16px; border-radius:10px; margin-top:20px; font-weight:bold;">
            ⚠️ Sistemde hiç bölüm yok. Önce
            <a href="gorev_listesi.php" style="color:#991b1b;">Müfredat sayfasından</a>
            bir bölüm oluşturun.
        </div>
    <?php endif; ?>

    <?php require __DIR__ . '/_tasarlayici_govde.php'; ?>
</div>

</body>
</html>
