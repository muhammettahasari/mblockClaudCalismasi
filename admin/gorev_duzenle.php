<?php
/* ==========================================================
   Dosya Yolu: admin/gorev_duzenle.php
   İşlev: Seçilen görevin verilerini 4'lü editörde düzenleme ekranı
   GÜVENLİK: [2026-08] Süper Admin girişi zorunlu hale getirildi (#1).
   ========================================================== */
require_once __DIR__ . '/super_auth.php';
superAdminZorunlu();

// URL'den gelen ID'yi al
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    die("Görev ID bulunamadı.");
}

// Görevin mevcut bilgilerini çek
$stmt = $pdo->prepare("SELECT * FROM gorevler WHERE id = ?");
$stmt->execute([$id]);
$gorev = $stmt->fetch();

if (!$gorev) {
    die("Görev bulunamadı.");
}

// Bölümleri listelemek için çek
$bolumler = $pdo->query("SELECT * FROM bolumler ORDER BY sira_no ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Görev Düzenle - Süper Admin</title>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link href="../css/admin_style.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../bilesenler/super_gezinme.php'; ?>


<div class="admin-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0; color: #1e293b;">✏️ Görevi Düzenle</h2>
        <a href="../v2_gorev_sistemi/admin/gorev_listesi.php" style="background: #64748b; color: white; padding: 8px 15px; text-decoration: none; border-radius: 6px; font-weight: bold;">⬅️ Listeye Dön</a>
    </div>
    
    <form id="gorevForm" action="../api/task_update.php" method="POST">
        <input type="hidden" name="gorev_id" value="<?= $gorev['id'] ?>">
        
        <div class="form-group">
            <label>Bağlı Olduğu Bölüm:</label>
            <select name="bolum_id" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                <option value="">Bölüm Seçiniz...</option>
                <?php foreach ($bolumler as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $b['id'] == $gorev['bolum_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['bolum_adi']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Görev Başlığı:</label>
            <input type="text" name="baslik" value="<?= htmlspecialchars($gorev['baslik']) ?>" required>
        </div>

        <div class="form-group">
            <label>Sıra No / Puan:</label>
            <input type="number" name="sira_no" value="<?= $gorev['sira_no'] ?>" style="width: 48%;" required>
            <input type="number" name="puan" value="<?= $gorev['puan'] ?>" style="width: 48%;" required>
        </div>

        <div class="form-group">
            <label>Nasıl Yaparım? (Yönerge, Gifi vb.):</label>
            <div id="editor-nasil" style="height: 200px; background: white;"><?= $gorev['nasil_yaparim'] ?></div>
            <input type="hidden" name="nasil_yaparim" id="gizli_nasil">
        </div>

        <div class="form-group">
            <label>Görev Metni (Öğrenciden ne isteniyor?):</label>
            <div id="editor-gorev" style="height: 150px; background: white;"><?= $gorev['gorev_metni'] ?></div>
            <input type="hidden" name="gorev_metni" id="gizli_gorev">
        </div>

        <div class="form-group">
            <label>İstenen Ekran Görüntüsü (Buraya resim/video/gif ekleyin):</label>
            <div id="editor-ekran" style="height: 150px; background: white;"><?= $gorev['istenen_ekran'] ?></div>
            <input type="hidden" name="istenen_ekran" id="gizli_ekran">
        </div>

        <div class="form-group">
            <label>Bilgi Al / İpucu (Lambaya basınca açılacak alan):</label>
            <div id="editor-ipucu" style="height: 150px; background: white;"><?= $gorev['ipucu'] ?></div>
            <input type="hidden" name="ipucu" id="gizli_ipucu">
        </div>

        <button type="submit" class="btn-submit" style="background-color: #4f46e5; width: 100%;">Değişiklikleri Kaydet</button>
    </form>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>
// EDİTÖR (QUILL) AYARLARI VE BAŞLATILMASI
const toolbarOptions = [
    [{ 'header': [1, 2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
    ['image', 'link', 'video'], 
    ['clean']
];

let activeQuill = null;
function imageHandler() {
    activeQuill = this.quill; 
    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/*');
    input.click();

    input.onchange = () => {
        const file = input.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const base64Image = e.target.result;
                const range = activeQuill.getSelection(true);
                activeQuill.insertEmbed(range.index, 'image', base64Image);
                activeQuill.setSelection(range.index + 1);
            };
            reader.readAsDataURL(file);
        }
    };
}

const editorConfig = {
    theme: 'snow',
    modules: { toolbar: { container: toolbarOptions, handlers: { image: imageHandler } } }
};

const editorNasil = new Quill('#editor-nasil', editorConfig);
const editorGorev = new Quill('#editor-gorev', editorConfig);
const editorEkran = new Quill('#editor-ekran', editorConfig);
const editorIpucu = new Quill('#editor-ipucu', editorConfig);

// FORM GÖNDERİLİRKEN VERİLERİ TOPLA
document.getElementById('gorevForm').addEventListener('submit', function(e) {
    document.getElementById('gizli_nasil').value = editorNasil.root.innerHTML;
    document.getElementById('gizli_gorev').value = editorGorev.root.innerHTML;
    document.getElementById('gizli_ekran').value = editorEkran.root.innerHTML;
    document.getElementById('gizli_ipucu').value = editorIpucu.root.innerHTML;
});
</script>

</body>
</html>