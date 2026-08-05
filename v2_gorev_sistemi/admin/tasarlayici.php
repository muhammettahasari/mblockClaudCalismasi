<?php
/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/admin/tasarlayici.php
 * KULLANICI / SAYFA: Süper Admin -> Görev Tasarlayıcı (tasarlayici.php) sayfası.
 * * * NE İŞE YARAR? (HALK DİLİNDE):
 * Süper Admin'in görevleri yazdığı, Ana Çözüm (Blueprint) ve Alternatif 
 * Çözümleri tasarladığı modüler arayüzdür. İşlem yükü burada yapılmaz, 
 * her şey alt klasörlerdeki JS modüllerine dağıtılmıştır.
 * * * TEMEL İŞLEMLER:
 * 1. Editor.js metin editörü alanını barındırır.
 * 2. Ana Çözüm (Master Blueprint) Blockly alanını barındırır.
 * 3. Alternatif çözüm ekleme butonunu ve konteynerini barındırır.
 * * * YENİ EKLENEN ÖZELLİKLER:
 * - [Bugün] Kodlar modüler JS dosyalarına bölündü.
 * - [Bugün] Manuel Hakem kuralları silinip, Blueprint (Referans Harita) arayüzüne geçildi.
 * - [2026-08] Süper Admin oturumu zorunlu hale getirildi (Bilinen Sorunlar #1).
 * - [2026-08] Bölüm / sıra no / puan alanları eklendi; sabit kodlama kaldırıldı (#17).
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

// Seçili bölüm için bir sonraki sıra numarasını öner
$onerilenSira = 1;
if ($duzenlenen) {
    $onerilenSira = (int) $duzenlenen['sira_no'];
} elseif ($bolumler) {
    $st = $pdo->prepare("SELECT COALESCE(MAX(sira_no),0)+1 FROM v2_gorevler WHERE bolum_id = ?");
    $st->execute([$bolumler[0]['id']]);
    $onerilenSira = (int) $st->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
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
        <a href="gorev_listesi.php" style="background:#334155; color:#fff; padding:9px 16px; border-radius:8px; text-decoration:none; font-weight:bold; font-size:14px;">← Görev Listesi</a>
    </div>

    <?php if (empty($bolumler)): ?>
        <div style="background:#fee2e2; border:1px solid #fecaca; color:#991b1b; padding:16px; border-radius:10px; margin-top:20px; font-weight:bold;">
            ⚠️ Sistemde hiç bölüm yok. Önce
            <a href="../../admin/gorev_ekle.php" style="color:#991b1b;">Bölüm Ekle</a>
            sayfasından bir bölüm oluşturun.
        </div>
    <?php endif; ?>

    <form id="v2GorevForm">
        <!-- [2026-08] Bölüm / sıra / puan alanları eklendi (Bilinen Sorunlar #17).
             Eskiden bu değerler gorev_api.php içinde 1, 1, 20 olarak SABİT kodlanmıştı;
             hazırlanan her görev 1. bölümün 1. sırasına kaydediliyordu. -->
        <input type="hidden" id="duzenlenenGorevId" value="<?= (int) $duzenlenenId ?>">

        <div class="form-group" style="margin-bottom: 20px;">
            <label style="font-weight: bold; color: #1e293b; display: block; margin-bottom: 8px;">Görev Başlığı:</label>
            <input type="text" id="gorevBasligi" placeholder="Örn: Ripley Uzayda Kayboldu"
                   value="<?= $duzenlenen ? temizle($duzenlenen['gorev_adi']) : '' ?>"
                   style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 16px;">
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:15px; margin-bottom:25px;">
            <div>
                <label style="font-weight: bold; color: #1e293b; display: block; margin-bottom: 8px;">📚 Bölüm:</label>
                <select id="gorevBolumId" style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:6px; font-size:15px;">
                    <?php foreach ($bolumler as $b): ?>
                        <option value="<?= (int) $b['id'] ?>"
                            <?= ($duzenlenen && (int) $duzenlenen['bolum_id'] === (int) $b['id']) ? 'selected' : '' ?>>
                            <?= (int) $b['sira_no'] ?>. <?= temizle($b['bolum_adi']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="font-weight: bold; color: #1e293b; display: block; margin-bottom: 8px;">🔢 Sıra No:</label>
                <input type="number" id="gorevSiraNo" min="1" value="<?= (int) $onerilenSira ?>"
                       style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:6px; font-size:15px;">
            </div>

            <div>
                <label style="font-weight: bold; color: #1e293b; display: block; margin-bottom: 8px;">🏆 Puan:</label>
                <input type="number" id="gorevPuan" min="1" max="500"
                       value="<?= $duzenlenen ? (int) $duzenlenen['puan'] : 20 ?>"
                       style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:6px; font-size:15px;">
            </div>

            <div>
                <label style="font-weight: bold; color: #1e293b; display: block; margin-bottom: 8px;">🎯 Görev Tipi:</label>
                <select id="gorevTipi" style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:6px; font-size:15px;">
                    <option value="standart" <?= ($duzenlenen && $duzenlenen['gorev_tipi'] === 'standart') ? 'selected' : '' ?>>Standart</option>
                    <option value="bolum_sonu_canavari" <?= ($duzenlenen && $duzenlenen['gorev_tipi'] === 'bolum_sonu_canavari') ? 'selected' : '' ?>>💀 Bölüm Sonu Canavarı</option>
                </select>
            </div>
        </div>

        <label style="font-weight: bold; color: #1e293b; display: block; margin-bottom: 8px;">1. Bölge: Görev Metni (Hikaye ve Kurallar):</label>
        <p style="color: #64748b; margin-bottom: 10px; font-size: 13px;">Eğik çizgi (/) tuşuna basarak metin içine kısa kod ekleyebilirsiniz.</p>
        <div id="v2-editorjs" class="editor-alani"></div>
        
        <div style="margin-top: 40px; border-top: 2px dashed #cbd5e1; padding-top: 25px;">
            <label style="font-weight: bold; color: #d97706; display: block; margin-bottom: 8px;">2. Bölge: Ana Çözüm (Master Blueprint):</label>
            <p style="color: #64748b; margin-bottom: 15px; font-size: 13px;">Öğrencinin yapmasını beklediğiniz %100 doğru kod dizilimini buraya kurun. Sistem öğrencileri bu haritaya göre test edecek.</p>
            <div id="blocklyAnaCozum" style="height: 500px; width: 100%; border: 2px solid #d97706; border-radius: 8px;"></div>
        </div>

        <div style="margin-top: 40px; border-top: 2px dashed #cbd5e1; padding-top: 25px;">
            <label style="font-weight: bold; color: #10b981; display: block; margin-bottom: 8px;">3. Bölge: Alternatif Çözümler (İsteğe Bağlı):</label>
            <p style="color: #64748b; margin-bottom: 15px; font-size: 13px;">Bu görevin birden fazla doğru yapılış şekli varsa (Örn: Döngü yerine alt alta yazmak), yeni çözüm alanları ekleyip referansları çoğaltabilirsiniz.</p>
            
            <div id="alternatifCozumlerKonteyneri">
                </div>
            
            <button type="button" id="btnAlternatifEkle" style="background: #10b981; color: white; border: none; padding: 10px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 10px;">+ Yeni Alternatif Çözüm Ekle</button>
        </div>

      
        
        <button type="button" class="kaydet-btn" id="btnGoreviKaydet" style="margin-top: 30px; width: 100%; font-size: 18px;">
            💾 <?= $duzenlenen ? 'Değişiklikleri Kaydet' : 'Tüm Görevi ve Çözüm Haritalarını Kaydet' ?>
        </button>
    </form>
</div>

<script>
    // Düzenleme modunda mevcut görevin verisini JS'e aktar
    window.v2DuzenlenenGorev = <?= $duzenlenen
        ? json_encode([
            'id'     => (int) $duzenlenen['id'],
            'icerik' => json_decode($duzenlenen['icerik_json'] ?? '{}', true),
          ], JSON_UNESCAPED_UNICODE)
        : 'null' ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/header@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/list@latest"></script>
<script src="https://unpkg.com/blockly/blockly_compressed.js"></script>
<script src="https://unpkg.com/blockly/blocks_compressed.js"></script>
<script src="https://unpkg.com/blockly/msg/tr.js"></script>

<script src="../assets/js/admin/mblock_bloklari.js"></script>    <script src="../assets/js/admin/metin_editoru.js"></script>      <script src="../assets/js/admin/blueprint_motoru.js"></script>   <script src="../assets/js/admin/api_yoneticisi.js"></script>     </body>
</html> 