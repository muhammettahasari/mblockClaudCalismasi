<?php
/**
 * Dosya Yolu: v2_gorev_sistemi/admin/kukla_kutuphanesi/index.php
 * Kullanıcı Yetkisi ve Bağlı Olduğu Sayfa: Süper Admin -> Kukla Kütüphanesi Ana Sayfası
 * Halk Dilinde İşlevi: Sisteme eklenmiş tüm kuklaları kartlar halinde gösteren, gruplarına (kategorilerine) göre filtreleme imkanı sunan görsel vitrin sayfasıdır.
 * Temel İşlevler:
 * 1. Veritabanındaki tüm kuklaları çeker.
 * 2. Kuklaların ait olduğu grupları tespit edip dinamik filtre butonları oluşturur.
 * 3. JSON formatındaki kostüm paketini açarak "costume1" resmini kartın kapak fotoğrafı yapar.
 * YENİ EKLENEN ÖZELLİKLER:
 * - [Bugün] Dinamik grup filtreleme (JavaScript ile anında arama) özelliği eklendi.
 */

/* GÜVENLİK: [2026-08] Süper Admin oturumu zorunlu (Bilinen Sorunlar #1). */
require_once __DIR__ . '/../../../admin/super_auth.php';
superAdminZorunlu();

// Tüm kuklaları veritabanından çek
$sorgu = $pdo->query("SELECT * FROM v2_kukla_kutuphanesi ORDER BY id DESC");
$kuklalar = $sorgu->fetchAll(PDO::FETCH_ASSOC);

// Filtre butonları için benzersiz grup isimlerini bul
$gruplar = array_unique(array_column($kuklalar, 'grup_adi'));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kukla Kütüphanesi</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f8fafc; padding: 30px; margin: 0; }
        .kutuphane-container { max-width: 1200px; margin: 0 auto; }
        
        /* Üst Başlık ve Butonlar */
        .ust-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; }
        .baslik { margin: 0; color: #1e293b; font-size: 28px; }
        .btn-yeni { background: #3b82f6; color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; transition: 0.2s; display: inline-block; }
        .btn-yeni:hover { background: #2563eb; }

        /* Filtreleme Menüsü */
        .filtre-alani { display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; }
        .filtre-btn { background: white; border: 2px solid #cbd5e1; padding: 8px 16px; border-radius: 20px; cursor: pointer; font-weight: bold; color: #475569; transition: 0.2s; }
        .filtre-btn:hover { border-color: #94a3b8; }
        .filtre-btn.aktif { background: #10b981; border-color: #10b981; color: white; }

        /* Kukla Grid Sistemi */
        .grid-alani { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        
        /* Kukla Kartları */
        .kukla-kart { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: 0.3s; text-align: center; padding-bottom: 15px; border: 1px solid #e2e8f0; }
        .kukla-kart:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        .resim-alani { height: 180px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; padding: 15px; position: relative; }
        .resim-alani img { max-width: 100%; max-height: 100%; object-fit: contain; }
        
        .kukla-bilgi { padding: 15px 10px 0; }
        .kukla-isim { margin: 0; font-size: 16px; color: #1e293b; font-weight: bold; }
        .kukla-grup { font-size: 12px; color: #64748b; margin-top: 5px; background: #e2e8f0; display: inline-block; padding: 3px 8px; border-radius: 10px; }
        .kostum-sayisi { position: absolute; top: 10px; right: 10px; background: #f59e0b; color: white; font-size: 11px; padding: 4px 8px; border-radius: 12px; font-weight: bold; }
    </style>
</head>
<body>

<div class="kutuphane-container">
    
 <div class="ust-bar">
        <h1 class="baslik">🎨 Kukla Kütüphanesi</h1>
        <div style="display: flex; gap: 10px; align-items: center;">
            
            <!-- YENİ: KAPAT VE YENİLE BUTONU -->
            <button type="button" onclick="kapatVeYenile()" style="background: #64748b; color: white; padding: 10px 20px; border-radius: 8px; font-weight: bold; border: none; cursor: pointer; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                ✖ Kapat ve Dön
            </button>

            <!-- Sil Butonu -->
            <button type="submit" form="kuklaSilForm" onclick="return confirm('Seçili kuklaları ve onlara ait resim dosyalarını KALICI olarak silmek istediğinize emin misiniz?');" style="background: #ef4444; color: white; padding: 10px 20px; border-radius: 8px; font-weight: bold; border: none; cursor: pointer; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                🗑️ Seçilenleri Sil
            </button>
            
            <a href="kukla_ekle.php" class="btn-yeni">+ Yeni Kukla Ekle</a>
        </div>
    </div>

    <!-- Silinme İşlemi Başarılıysa Gösterilecek Mesaj -->
    <?php if (isset($_GET['durum']) && $_GET['durum'] == 'silindi'): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold; border: 1px solid #a7f3d0;">
            ✅ Seçilen kuklalar ve fiziksel dosyaları sistemden başarıyla temizlendi!
        </div>
    <?php endif; ?>

    <div class="filtre-alani">
        <button class="filtre-btn aktif" onclick="filtrele('hepsi', this)">Tümü</button>
        <?php foreach($gruplar as $grup): ?>
            <button class="filtre-btn" onclick="filtrele('<?= htmlspecialchars($grup) ?>', this)">
                <?= htmlspecialchars($grup) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Silme işlemini yakalayacak form sarmalayıcısı -->
    <form id="kuklaSilForm" action="kukla_sil.php" method="POST">
        <div class="grid-alani" id="kuklaGrid">
            <?php foreach($kuklalar as $kukla): 
                $kostumler = json_decode($kukla['kostumler_json'], true);
                $kostumSayisi = is_array($kostumler) ? count($kostumler) : 0;
                
                $kapakResmi = "";
                if ($kostumSayisi > 0) {
                    $resim = isset($kostumler[0]['yol']) ? $kostumler[0]['yol'] : (isset($kostumler[0]['resimYolu']) ? $kostumler[0]['resimYolu'] : "");
                    $kapakResmi = "../../" . $resim;
                }
            ?>
                <div class="kukla-kart" data-grup="<?= htmlspecialchars($kukla['grup_adi']) ?>">
                    <div class="resim-alani">
                        <!-- TİK İŞARETİ (CHECKBOX) BURADA -->
                        <input type="checkbox" name="silinecekler[]" value="<?= $kukla['id'] ?>" style="position: absolute; top: 12px; left: 12px; transform: scale(1.5); cursor: pointer; z-index: 10; accent-color: #ef4444;">
                        
                        <span class="kostum-sayisi"><?= $kostumSayisi ?> Kostüm</span>
                        <img src="<?= $kapakResmi ?>" alt="<?= htmlspecialchars($kukla['kukla_adi']) ?>" onerror="this.src='https://via.placeholder.com/150?text=Kırık+Resim'">
                    </div>
                    <div class="kukla-bilgi">
                        <h3 class="kukla-isim"><?= htmlspecialchars($kukla['kukla_adi']) ?></h3>
                        <span class="kukla-grup"><?= htmlspecialchars($kukla['grup_adi']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </form>

</div>

<script>
    function filtrele(kategori, tiklananButon) {
        // 1. Buton renklerini ayarla
        let butonlar = document.querySelectorAll('.filtre-btn');
        butonlar.forEach(btn => btn.classList.remove('aktif'));
        tiklananButon.classList.add('aktif');

        // 2. Kartları gizle veya göster
        let kartlar = document.querySelectorAll('.kukla-kart');
        kartlar.forEach(kart => {
            if (kategori === 'hepsi' || kart.getAttribute('data-grup') === kategori) {
                kart.style.display = 'block';
            } else {
                kart.style.display = 'none';
            }
        });
    }
</script>
<script>
    function kapatVeYenile() {
        // 1. Durum: Eğer bu sayfa oynatici.php içindeki Iframe'de açılmışsa
        if (window.parent && typeof window.parent.adminPenceresiKapat === 'function') {
            window.parent.adminPenceresiKapat(); 
        } 
        // 2. Durum: Eğer yanlışlıkla yeni sekmede açılmışsa
        else if (window.opener && !window.opener.closed) {
            window.opener.location.reload(); 
            window.close(); 
        } 
        // 3. Durum: Doğrudan adrese girilmişse
        else {
            window.location.href = '/mblock_platform/v2_gorev_sistemi/ogrenci/oynatici.php';
        }
    }
</script>
</body>
</html>