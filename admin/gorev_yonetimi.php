<?php
/* ==========================================================
   Dosya Yolu: admin/gorev_yonetimi.php
   İşlev: Süper Admin görev ve bölüm listeleme, sıralama, silme ekranı
   GÜVENLİK: [2026-08] Süper Admin girişi zorunlu hale getirildi (#1).
   ========================================================== */
require_once __DIR__ . '/super_auth.php';
superAdminZorunlu();

// Tüm bölümleri ve altındaki görevleri çek
$bolumler = $pdo->query("SELECT * FROM bolumler ORDER BY sira_no ASC")->fetchAll();
$gorevler_ham = $pdo->query("SELECT id, bolum_id, baslik, puan, sira_no FROM gorevler ORDER BY bolum_id ASC, sira_no ASC")->fetchAll();

// Görevleri bölümlerine göre grupla
$gorevler = [];
foreach ($gorevler_ham as $g) {
    $gorevler[$g['bolum_id']][] = $g;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Süper Admin - Görev Yönetimi</title>
    <link href="../css/admin_style.css" rel="stylesheet">
    <style>
        .yonetim-container { max-width: 1000px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .bolum-karti { border: 2px solid #e2e8f0; border-radius: 8px; margin-bottom: 20px; overflow: hidden; }
        
        .bolum-baslik { background: #1e293b; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .gorev-satiri { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #f1f5f9; background: #fff; transition: 0.3s; }
        .gorev-satiri:hover { background: #f8fafc; }
        
        .btn-grup { display: flex; gap: 8px; }
        .btn-icon { background: #e2e8f0; border: none; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .btn-icon:hover { background: #cbd5e1; }
        .btn-edit { background: #3b82f6; color: white; } .btn-edit:hover { background: #2563eb; }
        .btn-delete { background: #ef4444; color: white; } .btn-delete:hover { background: #dc2626; }
        
        .sayac-kutu { background: #fee2e2; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box; }
    </style>
</head>
<body style="background: #f0f4f8; font-family: 'Segoe UI', sans-serif; margin: 0;">

<div class="yonetim-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin: 0; color: #1e293b;">📚 Müfredat ve Görev Yönetimi</h2>
     <div style="display: flex; gap: 10px;">
        <a href="super_quiz_yonetimi.php" style="background: #8b5cf6; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-left: 10px; display: inline-block; box-shadow: 0 4px 6px rgba(139, 92, 246, 0.3); transition: 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
            🧠 Quiz Yönetimi
        </a>
                <a href="ogretmen_yonetimi.php" style="background: #64748b; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: bold;">🏠 Anasayfa</a>
                <a href="gorev_ekle.php" style="background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: bold;">➕ Yeni Görev/Bölüm Ekle</a>
            </div>
    </div>

    <?php foreach ($bolumler as $b): $b_id = $b['id']; ?>
    <div class="bolum-karti" id="satir_bolum_<?= $b_id ?>">
        
        <div class="bolum-baslik">
            <div style="font-size: 18px; font-weight: bold;">📁 <span id="bolum_metin_<?= $b_id ?>"><?= htmlspecialchars($b['bolum_adi']) ?></span></div>
            <div class="btn-grup">
                <button class="btn-icon" onclick="siraDegistir('bolum', <?= $b_id ?>, 'yukari')" title="Yukarı Taşı">⬆️</button>
                <button class="btn-icon" onclick="siraDegistir('bolum', <?= $b_id ?>, 'asagi')" title="Aşağı Taşı">⬇️</button>
                <button class="btn-icon btn-edit" onclick="bolumDuzenle(<?= $b_id ?>)">✏️ Düzenle</button>
                <button class="btn-icon btn-delete" onclick="silmeBaslat(this, 'bolum', <?= $b_id ?>)">🗑️ Sil</button>
            </div>
        </div>

        <div style="background: #f8fafc; padding: 10px;">
            <?php if (!empty($gorevler[$b_id])): ?>
                <?php foreach ($gorevler[$b_id] as $g): $g_id = $g['id']; ?>
                    <div class="gorev-satiri" id="satir_gorev_<?= $g_id ?>">
                        <div>
                            <span style="color: #64748b; font-weight: bold; margin-right: 10px;">Görev <?= $g['sira_no'] ?>:</span> 
                            <span style="color: #1e293b; font-size: 16px; font-weight: 500;"><?= htmlspecialchars($g['baslik']) ?></span>
                            <span style="margin-left: 10px; font-size: 13px; background: #fef3c7; color: #d97706; padding: 2px 8px; border-radius: 4px; font-weight: bold;">🏆 <?= $g['puan'] ?> Puan</span>
                        </div>
                        <div class="btn-grup">
                            <button class="btn-icon" onclick="siraDegistir('gorev', <?= $g_id ?>, 'yukari')" title="Yukarı Taşı">⬆️</button>
                            <button class="btn-icon" onclick="siraDegistir('gorev', <?= $g_id ?>, 'asagi')" title="Aşağı Taşı">⬇️</button>
                            <a href="gorev_duzenle.php?id=<?= $g_id ?>" class="btn-icon btn-edit" style="text-decoration: none; font-size: 13px;">✏️ Düzenle</a>
                            <button class="btn-icon btn-delete" onclick="silmeBaslat(this, 'gorev', <?= $g_id ?>)">🗑️ Sil</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding: 15px; text-align: center; color: #94a3b8;">Bu bölümde henüz görev bulunmuyor.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<script>
// 1. HARİKA 5 SANİYELİK İPTAL EDİLEBİLİR SİLME SİSTEMİ
function silmeBaslat(buton, tur, id) {
    if(!confirm("Dikkat! Silmek istediğinize emin misiniz?")) return;
    
    const satir = document.getElementById(`satir_${tur}_${id}`);
    const normalIcerik = satir.innerHTML; // İptal edilirse geri yüklemek için mevcut HTML'i hafızaya al
    let saniye = 5;
    
    // Satırı kırmızı geri sayım moduna çevir
    satir.innerHTML = `
        <div class="sayac-kutu">
            <strong style="color:#ef4444; font-size: 16px;">🚨 ${tur === 'bolum' ? 'Bölüm ve içindeki tüm görevler' : 'Görev'} kalıcı olarak siliniyor... Son <span id="sayac_${tur}_${id}" style="font-size:20px;">${saniye}</span> saniye!</strong>
            <button id="btn_iptal_${tur}_${id}" style="background:#3b82f6; color:white; padding:8px 20px; font-size: 16px; border:none; border-radius:6px; cursor:pointer; font-weight:bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">⏳ İPTAL ET</button>
        </div>
    `;
    
    const sayacGosterge = document.getElementById(`sayac_${tur}_${id}`);
    const btnIptal = document.getElementById(`btn_iptal_${tur}_${id}`);
    
    // Geri sayımı başlat
    const interval = setInterval(() => {
        saniye--;
        if(sayacGosterge) sayacGosterge.innerText = saniye;
    }, 1000);
    
    // 5 Saniye dolunca AJAX ile veritabanından tamamen sil
    const timeout = setTimeout(() => {
        clearInterval(interval);
        fetch(`../api/yonetim_api.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ islem: 'sil', tur: tur, id: id })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                satir.remove(); // Ekranda yok et
            } else {
                alert("Silme işlemi başarısız oldu.");
                satir.innerHTML = normalIcerik;
            }
        });
    }, 5000);
    
    // İptal Butonuna Basılırsa
    btnIptal.onclick = () => {
        clearTimeout(timeout); // Silmeyi durdur
        clearInterval(interval); // Sayacı durdur
        satir.innerHTML = normalIcerik; // Satırı eski haline geri döndür
    };
}

// 2. SIRALAMA DEĞİŞTİR (YUKARI/AŞAĞI)
function siraDegistir(tur, id, yon) {
    fetch(`../api/yonetim_api.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ islem: 'sira_degistir', tur: tur, id: id, yon: yon })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            location.reload(); // Sıralama değişince sayfayı yenile ki yeni sırayı görsün
        } else {
            alert(data.message); // Örn: Zaten en uçtasınız.
        }
    });
}

// 3. BÖLÜM ADINI DÜZENLEME (Basit Promp Ekranı İle)
function bolumDuzenle(id) {
    const span = document.getElementById(`bolum_metin_${id}`);
    const mevcutAd = span.innerText;
    const yeniAd = prompt("Bölümün yeni adını girin:", mevcutAd);
    
    if (yeniAd && yeniAd.trim() !== "" && yeniAd !== mevcutAd) {
        fetch(`../api/yonetim_api.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ islem: 'bolum_duzenle', tur: 'bolum', id: id, yeni_ad: yeniAd.trim() })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                span.innerText = yeniAd.trim();
            } else {
                alert("Bölüm adı güncellenemedi.");
            }
        });
    }
}
</script>

</body>
</html>