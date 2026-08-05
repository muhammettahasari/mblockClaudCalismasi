<?php
/* ==========================================================
   Dosya Yolu: admin/gorev_ekle.php
   İşlev: Süper Admin için yeni görev ve bölüm ekleme ekranı
   GÜVENLİK: [2026-08] Süper Admin girişi zorunlu hale getirildi (#1).
   ========================================================== */
require_once __DIR__ . '/super_auth.php';
superAdminZorunlu();

// YENİ: Bölümleri çekerken, her bölümün içindeki "En Yüksek Görev Sıra Numarasını" da çekeriz.
$bolumler_sorgu = $pdo->query("
    SELECT b.*, COALESCE(MAX(g.sira_no), 0) as max_gorev_sira 
    FROM bolumler b 
    LEFT JOIN gorevler g ON b.id = g.bolum_id 
    GROUP BY b.id 
    ORDER BY b.sira_no ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Görev Ekle - Süper Admin</title>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link href="../css/admin_style.css" rel="stylesheet">
    <style>
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 8px; color: #1e293b; }
    </style>
</head>
<body style="background: #f0f4f8;">

<div class="admin-container" style="max-width: 900px; margin: 30px auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px;">
        <h2 style="margin: 0; color: #1e293b;">➕ Yeni Görev / Bölüm Ekle</h2>
      <div style="display: flex; gap: 10px;">
            <a href="gorev_yonetimi.php" style="background: #4f46e5; color: white; padding: 10px 15px; text-decoration: none; border-radius: 6px; font-weight: bold;">📚 Müfredat Listesine Dön</a>
            <a href="ogretmen_yonetimi.php" style="background: #64748b; color: white; padding: 10px 15px; text-decoration: none; border-radius: 6px; font-weight: bold;">🏠 Anasayfa</a>
        </div>
    </div>

    <form id="gorevForm" action="../api/task_save.php" method="POST">
        
        <div class="form-group">
            <label>Bağlı Olduğu Bölüm:</label>
            <div style="display: flex; gap: 10px;">
                <select name="bolum_id" id="bolumSelect" required style="flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 15px;">
                    <option value="">Sistemden Bölüm Seçiniz...</option>
                    <?php foreach ($bolumler_sorgu as $b): ?>
                        <option value="<?= $b['id'] ?>" data-max-sira="<?= $b['max_gorev_sira'] ?>">
                            <?= htmlspecialchars($b['bolum_adi']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="yeniBolumAdi" placeholder="Veya Yeni Bölüm Ekle" style="flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 15px;">
                <button type="button" id="btnBolumEkle" style="background-color: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">Ekle</button>
            </div>
            <small id="bolumMesaj" style="color: #10b981; font-weight: bold; display: none; margin-top: 8px;">✅ Bölüm eklendi ve seçildi!</small>
        </div>

        <div class="form-group">
            <label>Görev Başlığı (Örn: İleri Marş):</label>
            <input type="text" name="baslik" required style="width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 15px; box-sizing: border-box;">
        </div>

        <div class="form-group" style="display: flex; gap: 15px;">
            <div style="flex: 1;">
                <label>Sıra No:</label>
                <input type="number" id="siraNoInput" name="sira_no" placeholder="Örn: 1" required style="width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box;">
            </div>
            <div style="flex: 1;">
                <label>Görev Puanı:</label>
                <input type="number" name="puan" placeholder="Örn: 10" required style="width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box;">
            </div>
        </div>

        <div class="form-group">
            <label>🛠️ Nasıl Yaparım? (Yönerge, Gifi vb.):</label>
            <div id="editor-nasil" style="height: 200px; background: white;"></div>
            <input type="hidden" name="nasil_yaparim" id="gizli_nasil">
        </div>

        <div class="form-group">
            <label>🎯 Görev Metni (Öğrenciden ne isteniyor?):</label>
            <div id="editor-gorev" style="height: 150px; background: white;"></div>
            <input type="hidden" name="gorev_metni" id="gizli_gorev">
        </div>

        <div class="form-group">
            <label>📺 İstenen Ekran Görüntüsü (Buraya resim/video/gif ekleyin):</label>
            <div id="editor-ekran" style="height: 150px; background: white;"></div>
            <input type="hidden" name="istenen_ekran" id="gizli_ekran">
        </div>

        <div class="form-group">
            <label>💡 Bilgi Al / İpucu (Lambaya basınca açılacak alan):</label>
            <div id="editor-ipucu" style="height: 150px; background: white;"></div>
            <input type="hidden" name="ipucu" id="gizli_ipucu">
        </div>

        <button type="submit" style="background-color: #4f46e5; color: white; padding: 15px; border: none; border-radius: 8px; font-size: 18px; font-weight: bold; width: 100%; cursor: pointer;">💾 Görevi Veritabanına Kaydet</button>
    </form>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script src="../js/editor_manager.js"></script>

<script>
    document.getElementById('bolumSelect').addEventListener('change', function() {
        var secilenSecenek = this.options[this.selectedIndex];
        
        if(secilenSecenek.value !== "") {
            // Seçilen bölümdeki en yüksek görev sırasını al (Yoksa 0 say) ve 1 ekle
            var enYuksekSira = parseInt(secilenSecenek.getAttribute('data-max-sira')) || 0;
            document.getElementById('siraNoInput').value = enYuksekSira + 1;
        } else {
            // Bölüm seçimi iptal edilirse kutuyu boşalt
            document.getElementById('siraNoInput').value = "";
        }
    });
</script>

</body>
</html>