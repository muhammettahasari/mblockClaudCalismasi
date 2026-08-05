<?php
/**
 * Dosya Yolu: v2_gorev_sistemi/admin/kukla_kutuphanesi/kukla_ekle.php
 * Kullanıcı Yetkisi ve Bağlı Olduğu Sayfa: Süper Admin -> Kukla Kütüphanesi
 * Halk Dilinde İşlevi: Adminin sisteme yeni bir kukla eklemek için isim, grup ve çoklu resim (kostüm) seçtiği görsel form sayfasıdır.
 * Temel İşlevler:
 * 1. Çoklu resim yüklemeye (multiple) izin veren HTML formunu sunar.
 * 2. Form verilerini kukla_islem.php'ye gönderir.
 * 3. İşlem sonucuna göre uyarı mesajı gösterir.
 * YENİ EKLENEN ÖZELLİKLER:
 * - [Bugün] Veritabanından mevcut grupları çekip tıklanabilir akıllı etiketler (tag) olarak sunma özelliği eklendi.
 */
/* GÜVENLİK: [2026-08] Süper Admin oturumu zorunlu (Bilinen Sorunlar #1). */
require_once __DIR__ . '/../../../admin/super_auth.php';
superAdminZorunlu();

// Mevcut grupları veritabanından benzersiz (DISTINCT) olarak çek
$grupSorgu = $pdo->query("SELECT DISTINCT grup_adi FROM v2_kukla_kutuphanesi WHERE grup_adi != '' ORDER BY grup_adi ASC");
$mevcutGruplar = $grupSorgu->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kukla Ekle - Kütüphane</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f1f5f9; padding: 20px; }
        .form-kutu { background: white; max-width: 500px; margin: 0 auto; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .form-baslik { margin-top: 0; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 20px; }
        .form-grup { margin-bottom: 15px; }
        .form-grup label { display: block; font-weight: bold; color: #475569; margin-bottom: 5px; }
        .form-grup input[type="text"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; outline: none; transition: 0.2s; }
        .form-grup input[type="text"]:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .form-grup input[type="file"] { padding: 10px; background: #f8fafc; width: 100%; border: 1px dashed #cbd5e1; border-radius: 6px; box-sizing: border-box; cursor: pointer; }
        
        /* Akıllı Etiketler (Tag) CSS'i */
        .mevcut-gruplar { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .grup-etiket { background: #f8fafc; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 20px; font-size: 13px; color: #475569; cursor: pointer; transition: 0.2s; user-select: none; }
        .grup-etiket:hover { background: #e2e8f0; border-color: #94a3b8; }
        .grup-etiket.secili { background: #3b82f6; color: white; border-color: #2563eb; font-weight: bold; }

        .btn-kaydet { background: #10b981; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; transition: 0.2s; margin-top: 10px; }
        .btn-kaydet:hover { background: #059669; }
        
        .uyari { padding: 10px; border-radius: 6px; margin-bottom: 15px; font-weight: bold; text-align: center; }
        .uyari.basarili { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .uyari.hata { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>

    <div class="form-kutu">
        <h2 class="form-baslik">🎭 Yeni Kukla Ekle</h2>

        <?php
        if (isset($_GET['durum'])) {
            if ($_GET['durum'] == 'basarili') {
                echo '<div class="uyari basarili">✅ Kukla başarıyla kütüphaneye eklendi!</div>';
            } elseif ($_GET['durum'] == 'uzanti_hata') {
                echo '<div class="uyari hata">❌ Sadece resim dosyaları yüklenebilir (JPG, PNG, GIF, SVG).</div>';
            } else {
                echo '<div class="uyari hata">❌ Yükleme sırasında bir hata oluştu.</div>';
            }
        }
        ?>

        <form action="kukla_islem.php" method="POST" enctype="multipart/form-data">
            <div class="form-grup">
                <label>Kukla Adı (Örn: Sevimli Panda)</label>
                <input type="text" name="kukla_adi" required placeholder="Kuklanın sahnede görünecek adı">
            </div>

            <div class="form-grup">
                <label>Grup Adı</label>
                <input type="text" name="grup_adi" id="grupAdiInput" required placeholder="Yeni yazın veya aşağıdan seçin">
                
                <?php if (!empty($mevcutGruplar)): ?>
                    <div class="mevcut-gruplar" id="grupEtiketleri">
                        <?php foreach ($mevcutGruplar as $grup): ?>
                            <span class="grup-etiket" onclick="grupSec(this, '<?= htmlspecialchars($grup) ?>')">
                                <?= htmlspecialchars($grup) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-grup">
                <label>Kukla Kostümleri (Çoklu Seçim Yapabilirsiniz)</label>
                <input type="file" name="kukla_resimleri[]" accept="image/*" multiple required>
                <small style="color: #64748b; font-size: 12px; display: block; margin-top: 5px;">
                    * Ctrl tuşuna basılı tutarak birden fazla resim seçebilirsiniz.
                </small>
            </div>

            <button type="submit" name="kukla_kaydet" class="btn-kaydet">💾 Kütüphaneye Kaydet</button>
        </form>
    </div>

    <script>
        function grupSec(etiket, grupAdi) {
            // 1. Tıklanan etiketin adını input kutusuna yazdır
            document.getElementById('grupAdiInput').value = grupAdi;

            // 2. Diğer etiketlerin maviliğini (seçili durumunu) kaldır
            let etiketler = document.querySelectorAll('.grup-etiket');
            etiketler.forEach(e => e.classList.remove('secili'));

            // 3. Tıklanan etiketi mavi (seçili) yap
            etiket.classList.add('secili');
        }

        // Kullanıcı kendi elle yazmaya başlarsa etiketlerdeki mavi seçimi kaldır
        document.getElementById('grupAdiInput').addEventListener('input', function() {
            let etiketler = document.querySelectorAll('.grup-etiket');
            etiketler.forEach(e => {
                if(e.innerText.trim() === this.value.trim()) {
                    e.classList.add('secili'); // Eğer yazdığı şey mevcut bir grupsa onu mavi yap
                } else {
                    e.classList.remove('secili');
                }
            });
        });
    </script>

</body>
</html>