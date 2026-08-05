<?php
/* ====================================================================
 * MODÜL: Quiz ve Soru Havuzu Yönetimi
 * DOSYA: admin/super_quiz_yonetimi.php
 * AÇIKLAMA: bolumler tablosu ile %100 entegre, resim destekli soru yönetim paneli.
 * GÜVENLİK: [2026-08] Süper Admin girişi zorunlu hale getirildi (#1).
 * ==================================================================== */

require_once __DIR__ . '/super_auth.php';
superAdminZorunlu();

function resimYukle($fileInputName)
{
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION);
        $yeniAd = uniqid('quiz_') . '.' . $ext;
        $hedef = '../img/quiz/' . $yeniAd;

        if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $hedef)) {
            return 'img/quiz/' . $yeniAd;
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['islem'])) {
   // --- BURASI YENİ EKLENEN KODLARIN BAŞLANGICI ---
    $bolum_id = $_POST['bolum_id'];
    $zorluk = $_POST['zorluk_seviyesi'];
    
    // YENİ: Süre ve Puan değişkenlerini formdan çek
    $sure = isset($_POST['sure']) ? (int)$_POST['sure'] : 20;
    $puan = isset($_POST['puan']) ? (int)$_POST['puan'] : 20;

    $soru_metni = $_POST['soru_metni'];
    $dogru_cevap = $_POST['dogru_cevap'];

    $cevap_a = $_POST['cevap_a_metni'];
    $cevap_b = $_POST['cevap_b_metni'];
    $cevap_c = $_POST['cevap_c_metni'];
    $cevap_d = $_POST['cevap_d_metni'];

    $soru_medya = resimYukle('soru_medya') ?: $_POST['eski_soru_medya'];
    $medya_a = resimYukle('cevap_a_medya') ?: $_POST['eski_a_medya'];
    $medya_b = resimYukle('cevap_b_medya') ?: $_POST['eski_b_medya'];
    $medya_c = resimYukle('cevap_c_medya') ?: $_POST['eski_c_medya'];
    $medya_d = resimYukle('cevap_d_medya') ?: $_POST['eski_d_medya'];

    if ($_POST['islem'] == 'ekle') {
        // YENİ: sure ve puan sütunları eklendi
        $sql = "INSERT INTO quiz_sorulari (bolum_id, zorluk_seviyesi, sure, puan, soru_metni, soru_medya, cevap_a_metni, cevap_a_medya, cevap_b_metni, cevap_b_medya, cevap_c_metni, cevap_c_medya, cevap_d_metni, cevap_d_medya, dogru_cevap) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$bolum_id, $zorluk, $sure, $puan, $soru_metni, $soru_medya, $cevap_a, $medya_a, $cevap_b, $medya_b, $cevap_c, $medya_c, $cevap_d, $medya_d, $dogru_cevap]);
    } elseif ($_POST['islem'] == 'duzenle') {
        $soru_id = $_POST['soru_id'];
        // YENİ: sure ve puan sütunları güncellendi
        $sql = "UPDATE quiz_sorulari SET bolum_id=?, zorluk_seviyesi=?, sure=?, puan=?, soru_metni=?, soru_medya=?, cevap_a_metni=?, cevap_a_medya=?, cevap_b_metni=?, cevap_b_medya=?, cevap_c_metni=?, cevap_c_medya=?, cevap_d_metni=?, cevap_d_medya=?, dogru_cevap=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$bolum_id, $zorluk, $sure, $puan, $soru_metni, $soru_medya, $cevap_a, $medya_a, $cevap_b, $medya_b, $cevap_c, $medya_c, $cevap_d, $medya_d, $dogru_cevap, $soru_id]);
    }
    header("Location: super_quiz_yonetimi.php");
    exit;
}

if (isset($_GET['sil'])) {
    $sil_id = (int)$_GET['sil'];
    $pdo->prepare("DELETE FROM quiz_sorulari WHERE id = ?")->execute([$sil_id]);
    header("Location: super_quiz_yonetimi.php");
    exit;
}

$bolumler_sorgu = $pdo->query("SELECT * FROM bolumler ORDER BY sira_no ASC");
$bolumler = $bolumler_sorgu->fetchAll(PDO::FETCH_ASSOC);

$sorular_sorgu = $pdo->query("SELECT q.*, b.bolum_adi, b.sira_no FROM quiz_sorulari q JOIN bolumler b ON q.bolum_id = b.id ORDER BY b.sira_no ASC, q.id ASC");
$tum_sorular = $sorular_sorgu->fetchAll(PDO::FETCH_ASSOC);

$bolumlendirilmis_sorular = [];
foreach ($tum_sorular as $soru) {
    $grup_adi = $soru['sira_no'] . ". Bölüm: " . $soru['bolum_adi'];
    $bolumlendirilmis_sorular[$grup_adi][] = $soru;
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Quiz ve Soru Havuzu Yönetimi</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            margin: 0;
            padding: 20px;
        }

        .header-area {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .btn-add {
            background: #10b981;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);
            transition: 0.2s;
        }

        .btn-add:hover {
            background: #059669;
        }

        .module-card {
            background: white;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .module-header {
            background: #1e293b;
            color: white;
            padding: 15px 20px;
            font-size: 18px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .question-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            transition: 0.2s;
        }

        .question-row:hover {
            background: #f1f5f9;
        }

        .question-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .question-title {
            font-size: 16px;
            color: #334155;
            font-weight: 500;
        }

        .badge {
            background: #fef08a;
            color: #854d0e;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 6px;
            border: none;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            color: white;
        }

        .btn-edit {
            background: #3b82f6;
        }

        .btn-delete {
            background: #ef4444;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            overflow-y: auto;
            padding: 20px 0;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #334155;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .option-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>

    <div class="header-area">
        <h1 style="margin: 0; color: #1e293b;">📚 Quiz Soru Havuzu Yönetimi</h1>
        <div style="display: flex; gap: 15px; align-items: center;">
            <a href="ogretmen_yonetimi.php" class="btn" style="background: #64748b; color: white; text-decoration: none; padding: 12px 20px; border-radius: 8px; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: 0.2s;" onmouseover="this.style.background='#475569'" onmouseout="this.style.background='#64748b'">🏠 Anasayfa</a>
            <button class="btn-add" onclick="modalAc('ekle')">+ Yeni Soru Ekle</button>
        </div>
    </div>

    <?php if (empty($bolumlendirilmis_sorular)): ?>
        <div style="background: white; padding: 30px; text-align: center; color: #64748b; border-radius: 10px;">Henüz hiç soru eklenmemiş. Havuz bomboş!</div>
    <?php else: ?>
        <?php foreach ($bolumlendirilmis_sorular as $bolum_adi => $sorular): ?>
            <div class="module-card">
                <div class="module-header">
                    📁 <?= htmlspecialchars($bolum_adi) ?> <span style="font-size: 14px; font-weight: normal; color: #94a3b8; margin-left: auto;">(<?= count($sorular) ?> Soru)</span>
                </div>
                <?php foreach ($sorular as $index => $soru): ?>
                    <div class="question-row">
                        <div class="question-info">
                            <span style="color: #64748b; font-weight: bold;">Soru <?= $index + 1 ?>:</span>
                            <span class="question-title">
                                <?= htmlspecialchars(mb_substr(strip_tags($soru['soru_metni']), 0, 50)) ?><span style="color:#94a3b8"><?= strlen(strip_tags($soru['soru_metni'])) > 50 ? '...' : '' ?></span>
                            </span>
                            <span class="badge">Seviye: <?= htmlspecialchars($soru['zorluk_seviyesi']) ?></span>
                            <?php if ($soru['soru_medya']): ?> <span title="Görsel İçerir">🖼️</span> <?php endif; ?>
                        </div>
                        <div class="action-btns">
                            <button class="btn btn-edit" onclick='modalAc("duzenle", <?= json_encode($soru) ?>)'>✏️ Düzenle</button>
                            <button class="btn btn-delete" onclick="if(confirm('Bu soruyu silmek istediğinize emin misiniz?')) window.location.href='super_quiz_yonetimi.php?sil=<?= $soru['id'] ?>'">🗑️ Sil</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div id="soruModal" class="modal">
        <div class="modal-content">
            <h2 id="modalBaslik" style="margin-top: 0; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">Yeni Soru Ekle</h2>
            <form action="super_quiz_yonetimi.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="islem" id="formIslem" value="ekle">
                <input type="hidden" name="soru_id" id="soru_id" value="">

                <div class="grid-2">
                    <div class="form-group">
                        <label>Bağlı Olduğu Bölüm:</label>
                        <select name="bolum_id" id="bolum_id" class="form-control" required>
                            <option value="">-- Bölüm Seçiniz --</option>
                            <?php foreach ($bolumler as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= $b['sira_no'] ?>. Bölüm: <?= htmlspecialchars($b['bolum_adi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Zorluk Seviyesi:</label>
                        <input type="text" name="zorluk_seviyesi" id="zorluk_seviyesi" class="form-control" list="zorluk_listesi" placeholder="Seç veya Yaz..." required>
                        <datalist id="zorluk_listesi">
                            <option value="Kolay">
                            <option value="Orta">
                            <option value="Zor">
                            <option value="Boss">
                        </datalist>
                    </div>
                </div>
                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label style="font-weight:bold; color:#3b82f6;">⏱️ Soru Süresi (Saniye)</label>
                        <input type="number" name="sure" id="sure" value="20" required style="width:100%; padding:10px; border-radius:5px; border:1px solid #ccc;">
                    </div>
                    <div style="flex: 1;">
                        <label style="font-weight:bold; color:#10b981;">⭐ Maksimum Puan</label>
                        <input type="number" name="puan" id="puan" value="20" required style="width:100%; padding:10px; border-radius:5px; border:1px solid #ccc;">
                    </div>
                </div>
                <div class="form-group">
                    <label>Soru Metni:</label>
                    <textarea name="soru_metni" id="soru_metni" class="form-control rich-editor" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label>Soru Ana Medyası (Geleneksel Yükleme) - İsteğe Bağlı:</label>
                    <input type="file" name="soru_medya" accept="image/*" class="form-control">
                    <input type="hidden" name="eski_soru_medya" id="eski_soru_medya">
                    <div id="onizleme_soru" style="color: #3b82f6; font-size: 12px; margin-top: 5px;"></div>
                </div>

                <hr style="border: 0; height: 1px; background: #cbd5e1; margin: 20px 0;">
                <h3 style="color: #475569; margin-bottom: 15px;">Şıklar ve Doğru Cevap</h3>

                <div class="grid-2">
                    <div class="option-box">
                        <label><input type="radio" name="dogru_cevap" id="dogru_a" value="A" required> 🟢 A Şıkkı (Doğru İse İşaretle)</label>
                        <textarea name="cevap_a_metni" id="cevap_a_metni" class="form-control rich-editor" style="margin-top: 5px;"></textarea>
                        <input type="file" name="cevap_a_medya" accept="image/*" class="form-control" style="margin-top: 5px;">
                        <input type="hidden" name="eski_a_medya" id="eski_a_medya">
                    </div>
                    <div class="option-box">
                        <label><input type="radio" name="dogru_cevap" id="dogru_b" value="B" required> 🟢 B Şıkkı</label>
                        <textarea name="cevap_b_metni" id="cevap_b_metni" class="form-control rich-editor" style="margin-top: 5px;"></textarea>
                        <input type="file" name="cevap_b_medya" accept="image/*" class="form-control" style="margin-top: 5px;">
                        <input type="hidden" name="eski_b_medya" id="eski_b_medya">
                    </div>
                    <div class="option-box">
                        <label><input type="radio" name="dogru_cevap" id="dogru_c" value="C" required> 🟢 C Şıkkı</label>
                        <textarea name="cevap_c_metni" id="cevap_c_metni" class="form-control rich-editor" style="margin-top: 5px;"></textarea>
                        <input type="file" name="cevap_c_medya" accept="image/*" class="form-control" style="margin-top: 5px;">
                        <input type="hidden" name="eski_c_medya" id="eski_c_medya">
                    </div>
                    <div class="option-box">
                        <label><input type="radio" name="dogru_cevap" id="dogru_d" value="D" required> 🟢 D Şıkkı</label>
                        <textarea name="cevap_d_metni" id="cevap_d_metni" class="form-control rich-editor" style="margin-top: 5px;"></textarea>
                        <input type="file" name="cevap_d_medya" accept="image/*" class="form-control" style="margin-top: 5px;">
                        <input type="hidden" name="eski_d_medya" id="eski_d_medya">
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn" style="background: #94a3b8; flex: 1; padding: 12px;" onclick="document.getElementById('soruModal').style.display='none'">İptal</button>
                    <button type="submit" class="btn" style="background: #10b981; flex: 1; padding: 12px;">💾 Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"></script>
    <script>
        // Editörleri başlatan özel fonksiyon
        function editoruBaslat() {
            tinymce.init({
                selector: '.rich-editor',
                plugins: 'image link lists paste',
                toolbar: 'undo redo | bold italic | numlist bullist | image',
                paste_data_images: true, // Kopyala/Yapıştır ile resim ekleme
                height: 150,
                menubar: false,
                branding: false,
                setup: function(editor) {
                    editor.on('change', function() {
                        editor.save();
                    });
                }
            });
        }

        function modalAc(islemTuru, soruData = null) {
            document.getElementById('formIslem').value = islemTuru;

            // 1. ÖNCE MODALI GÖRÜNÜR YAP Kİ EDİTÖR DONMASIN!
            document.getElementById('soruModal').style.display = 'flex';

            // 2. ESKİ EDİTÖRLERİ SİL VE TERTEMİZ YENİDEN BAŞLAT
            tinymce.remove();
            setTimeout(() => {
                editoruBaslat();

                // 3. İÇERİKLERİ YÜKLE
                setTimeout(() => {
                    if (islemTuru === 'ekle') {
                        document.getElementById('modalBaslik').innerText = 'Yeni Soru Ekle';
                        document.getElementById('soru_id').value = '';
                        document.getElementById('bolum_id').value = '';
                        document.getElementById('zorluk_seviyesi').value = '';
                        document.getElementById('sure').value = '20';
                        document.getElementById('puan').value = '20';
                        if (tinymce.get('soru_metni')) tinymce.get('soru_metni').setContent('');
                        if (tinymce.get('cevap_a_metni')) tinymce.get('cevap_a_metni').setContent('');
                        if (tinymce.get('cevap_b_metni')) tinymce.get('cevap_b_metni').setContent('');
                        if (tinymce.get('cevap_c_metni')) tinymce.get('cevap_c_metni').setContent('');
                        if (tinymce.get('cevap_d_metni')) tinymce.get('cevap_d_metni').setContent('');

                        ['eski_soru_medya', 'eski_a_medya', 'eski_b_medya', 'eski_c_medya', 'eski_d_medya'].forEach(id => document.getElementById(id).value = '');
                        document.getElementById('onizleme_soru').innerText = '';
                        document.querySelectorAll('input[name="dogru_cevap"]').forEach(r => r.checked = false);

                    } else if (islemTuru === 'duzenle' && soruData) {
                        document.getElementById('modalBaslik').innerText = 'Soruyu Düzenle';
                        document.getElementById('soru_id').value = soruData.id;
                        document.getElementById('bolum_id').value = soruData.bolum_id;
                        document.getElementById('zorluk_seviyesi').value = soruData.zorluk_seviyesi;
                        document.getElementById('sure').value = soruData.sure || 20;
                        document.getElementById('puan').value = soruData.puan || 20;
                        if (tinymce.get('soru_metni')) tinymce.get('soru_metni').setContent(soruData.soru_metni || '');
                        if (tinymce.get('cevap_a_metni')) tinymce.get('cevap_a_metni').setContent(soruData.cevap_a_metni || '');
                        if (tinymce.get('cevap_b_metni')) tinymce.get('cevap_b_metni').setContent(soruData.cevap_b_metni || '');
                        if (tinymce.get('cevap_c_metni')) tinymce.get('cevap_c_metni').setContent(soruData.cevap_c_metni || '');
                        if (tinymce.get('cevap_d_metni')) tinymce.get('cevap_d_metni').setContent(soruData.cevap_d_metni || '');

                        document.getElementById('eski_soru_medya').value = soruData.soru_medya || '';
                        document.getElementById('eski_a_medya').value = soruData.cevap_a_medya || '';
                        document.getElementById('eski_b_medya').value = soruData.cevap_b_medya || '';
                        document.getElementById('eski_c_medya').value = soruData.cevap_c_medya || '';
                        document.getElementById('eski_d_medya').value = soruData.cevap_d_medya || '';

                        document.getElementById('onizleme_soru').innerText = soruData.soru_medya ? 'Mevcut Görsel: ' + soruData.soru_medya : '';

                        if (soruData.dogru_cevap) {
                            let radio = document.getElementById('dogru_' + soruData.dogru_cevap.toLowerCase());
                            if (radio) radio.checked = true;
                        }
                    }
                }, 100); // Verileri yazmak için ufak gecikme
            }, 50); // Editörü başlatmak için ufak gecikme
        }

        // Dışarı tıklayınca modalı kapat ve editörü hafızadan sil (donmaması için)
        window.onclick = function(event) {
            let modal = document.getElementById('soruModal');
            if (event.target == modal) {
                modal.style.display = "none";
                tinymce.remove();
            }
        }
    </script>
</body>

</html>