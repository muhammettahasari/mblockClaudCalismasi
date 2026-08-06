<?php
/* ==========================================================
   Dosya Yolu: admin/ogretmen_yonetimi.php
   İşlev: Patronun öğretmenleri onayladığı ve süre belirlediği panel
   GÜVENLİK: [2026-08] Süper Admin girişi zorunlu hale getirildi (#1).
   ========================================================== */
require_once __DIR__ . '/super_auth.php';
superAdminZorunlu();

// 1. Yeni başvuranlar (Onay Bekleyenler)
$bekleyenler = $pdo->query("SELECT * FROM ogretmenler WHERE durum = 'onay_bekliyor'")->fetchAll();

// 2. Sistemde kayıtlı olanlar (Onaylılar ve Süresi Dolup Bloke Olanlar)
$kayitlilar = $pdo->query("SELECT * FROM ogretmenler WHERE durum = 'onaylandi'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Süper Admin | Öğretmen Yönetimi</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f4f8;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        h1 {
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }

        h3 {
            color: #4f46e5;
            margin-top: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
            color: #475569;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }

        .bg-wait {
            background: #f59e0b;
        }

        .bg-active {
            background: #10b981;
        }

        .bg-bloke {
            background: #ef4444;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            color: white;
            font-weight: bold;
        }

        .btn-approve {
            background: #4f46e5;
        }

        .btn-renew {
            background: #10b981;
        }

        input[type="number"] {
            width: 60px;
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        /* YENİ: Silme Butonu Stili */
        .btn-delete {
            background: #ef4444;
            margin-left: 8px;
        }
        .btn-delete:disabled {
            background: #fca5a5;
            cursor: not-allowed;
            transform: scale(0.95);
        }
    </style>
</head>

<body>
<?php require __DIR__ . '/../bilesenler/super_gezinme.php'; ?>


    <div class="container">
        <div style="display: flex; justify-content: flex-end; gap: 15px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #e2e8f0;">
            <a href="../v2_gorev_sistemi/admin/gorev_listesi.php" style="background: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">➕ Yeni Görev / Bölüm Ekle</a>
            <a href="../v2_gorev_sistemi/admin/gorev_listesi.php" style="background: #4f46e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">📚 Müfredat ve Görev Yönetimi ➡️</a>
            <a href="admin_market.php" style="background: #8b5cf6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">🛒 Gizemli Market Yönetimi</a>
        </div>
        <h1>👑 Süper Admin Kontrol Merkezi</h1>

        <h3>⏳ Onay Bekleyen Yeni Başvurular</h3>
        <table>
            <tr>
                <th>Ad Soyad</th>
                <th>Okul Adı</th>
                <th>Telefon</th>
                <th>Kontenjan</th>
                <th>Lisans Süresi (Ay)</th>
                <th>İşlem</th>
            </tr>
            <?php foreach ($bekleyenler as $ogr): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($ogr['ad_soyad']) ?></strong></td>
                    <td><?= htmlspecialchars($ogr['okul_adi']) ?></td>
                    <td><?= htmlspecialchars($ogr['telefon']) ?></td>
                    <td><?= $ogr['kontenjan'] ?> Öğrenci</td>
                    <td>
                      <input type="number" id="ay_onay_<?= $ogr['id'] ?>" value="<?= isset($ogr['talep_edilen_ay']) && $ogr['talep_edilen_ay'] > 0 ? $ogr['talep_edilen_ay'] : 1 ?>" min="1" style="width: 60px;"> Ay
                    </td>
                   <td>
                        <button class="btn btn-approve" onclick="islemYap('onayla', <?= $ogr['id'] ?>)">Onayla & PIN Üret</button>
                        <button class="btn btn-delete" id="btn_sil_<?= $ogr['id'] ?>" onclick="ogretmenSil(<?= $ogr['id'] ?>, this)">❌ Sil</button>
                    </td>
                </tr>
            <?php endforeach;
            if (count($bekleyenler) == 0) echo "<tr><td colspan='6'>Bekleyen başvuru yok.</td></tr>"; ?>
        </table>

        <h3>✅ Kayıtlı Öğretmenler ve Abonelik Durumları</h3>
        <table>
            <tr>
                <th>Ad Soyad</th>
                <th>Okul Adı</th>
                <th>Kullanıcı Adı</th>
                <th>Şifre</th>
                <th>Sınıf PIN Kodu</th>
                <th>Bitiş Tarihi</th>
                <th>Durum</th>
                <th>Süre Uzat / Kilidi Kaldır</th>
            </tr>
            <?php
            foreach ($kayitlilar as $ogr):
                // Şu anki zaman ile öğretmenin bitiş tarihini karşılaştırıyoruz
                $bitis_zamani = strtotime($ogr['bitis_tarihi']);
                $su_an = time();
                $bloke_mi = ($bitis_zamani < $su_an); // Süresi geçmişse TRUE döner
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($ogr['ad_soyad']) ?></strong></td>
                    <td><?= htmlspecialchars($ogr['okul_adi']) ?></td>
                    <td><span style="font-family:monospace; color:#475569;"><?= htmlspecialchars($ogr['kullanici_adi'] ?? '') ?></span></td>

                    <!-- [2026-08] ŞİFRE YÖNETİMİ EKLENDİ
                         Yıldızlı görünür, tıklayınca açılır, değiştirip alandan
                         çıkınca (veya Enter'a basınca) otomatik kaydedilir. -->
                    <td>
                        <input type="password"
                               class="sifre-kutu"
                               id="sifre_<?= (int) $ogr['id'] ?>"
                               value="<?= htmlspecialchars($ogr['sifre'] ?? '') ?>"
                               data-orijinal="<?= htmlspecialchars($ogr['sifre'] ?? '') ?>"
                               data-id="<?= (int) $ogr['id'] ?>"
                               onfocus="this.type='text'; this.select();"
                               onblur="this.type='password'; sifreKaydet(this);"
                               onkeydown="if(event.key==='Enter'){event.preventDefault(); this.blur();}"
                               title="Tıklayın, değiştirin, dışarı çıkınca otomatik kaydedilir">
                        <span class="sifre-durum" id="sifre_durum_<?= (int) $ogr['id'] ?>"></span>
                    </td>

                    <td><span style="font-family:monospace; font-size:18px; font-weight:bold; color:#4f46e5;"><?= htmlspecialchars($ogr['pin_kodu'] ?? '') ?></span></td>
                    <td><?= date('d.m.Y H:i', $bitis_zamani) ?></td>
                    <td>
                        <?php if ($bloke_mi): ?>
                            <span class="badge bg-bloke">BLOKE (Süresi Bitti)</span>
                        <?php else: ?>
                            <span class="badge bg-active">Aktif</span>
                        <?php endif; ?>
                    </td>
                  <td>
                        <input type="number" id="ay_uzat_<?= $ogr['id'] ?>" value="<?= isset($ogr['talep_edilen_ay']) && $ogr['talep_edilen_ay'] > 0 ? $ogr['talep_edilen_ay'] : 1 ?>" min="1" style="width: 60px;"> Ay
                        <button class="btn btn-renew" onclick="islemYap('uzat', <?= $ogr['id'] ?>)">Süreyi Ekle</button>
                        <button class="btn btn-delete" id="btn_sil_<?= $ogr['id'] ?>" onclick="ogretmenSil(<?= $ogr['id'] ?>, this)">❌ Sil</button>
                    </td>
                </tr>
            <?php endforeach;
            if (count($kayitlilar) == 0) echo "<tr><td colspan='8'>Kayıtlı öğretmen bulunmuyor.</td></tr>"; ?>
        </table>
    </div>

    <style>
        /* [2026-08] Şifre sütunu stilleri */
        .sifre-kutu {
            width: 130px; padding: 7px 9px; border: 2px solid #cbd5e1; border-radius: 6px;
            font-family: monospace; font-size: 14px; outline: none; transition: 0.2s; background: #fff;
        }
        .sifre-kutu:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.12); }
        .sifre-kutu.kaydedildi { border-color: #10b981; background: #ecfdf5; }
        .sifre-kutu.hata { border-color: #ef4444; background: #fef2f2; }
        .sifre-durum { font-size: 11px; font-weight: bold; margin-left: 6px; white-space: nowrap; }
    </style>

    <script>
        /* ================================================================
         * [2026-08] ŞİFRE OTOMATİK KAYDETME
         * Kutuya tıklanınca şifre görünür, alandan çıkınca gizlenir.
         * Değer değiştiyse otomatik kaydedilir — ayrı bir "Kaydet" yok.
         * ============================================================== */
        function sifreKaydet(kutu) {
            const id       = kutu.dataset.id;
            const yeni     = kutu.value.trim();
            const orijinal = kutu.dataset.orijinal;
            const durum    = document.getElementById('sifre_durum_' + id);

            kutu.classList.remove('kaydedildi', 'hata');

            // Değişmediyse hiçbir şey yapma
            if (yeni === orijinal) { durum.textContent = ''; return; }

            if (yeni === '') {
                kutu.value = orijinal;
                kutu.classList.add('hata');
                durum.style.color = '#ef4444';
                durum.textContent = 'boş olamaz';
                setTimeout(() => { kutu.classList.remove('hata'); durum.textContent = ''; }, 2500);
                return;
            }

            durum.style.color = '#64748b';
            durum.textContent = 'kaydediliyor...';

            fetch('../api/admin_teacher_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ islem: 'sifre_degistir', id: id, sifre: yeni })
            })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        kutu.dataset.orijinal = yeni;
                        kutu.classList.add('kaydedildi');
                        durum.style.color = '#10b981';
                        durum.textContent = '✓ kaydedildi';
                        setTimeout(() => { kutu.classList.remove('kaydedildi'); durum.textContent = ''; }, 2500);
                    } else {
                        kutu.value = orijinal;
                        kutu.classList.add('hata');
                        durum.style.color = '#ef4444';
                        durum.textContent = d.message || d.mesaj || 'hata';
                    }
                })
                .catch(() => {
                    kutu.value = orijinal;
                    kutu.classList.add('hata');
                    durum.style.color = '#ef4444';
                    durum.textContent = 'bağlantı hatası';
                });
        }

        // API'ye komut gönderen JS Fonksiyonu
        function islemYap(islemTuru, id) {
            // Hangi kutudan ay bilgisini alacağımızı belirliyoruz
            let ayInputId = islemTuru === 'onayla' ? 'ay_onay_' + id : 'ay_uzat_' + id;
            let secilenAy = document.getElementById(ayInputId).value;

            fetch('../api/admin_teacher_action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        islem: islemTuru,
                        id: id,
                        ay: secilenAy
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload(); // Sayfayı yenile ki tablolar güncellensin
                    } else {
                        alert("Hata: " + data.message);
                    }
                });
        }
        // YENİ: 4 Saniyelik Animasyonlu Silme Mekanizması
        function ogretmenSil(id, btn) {
            if (!confirm("Bu öğretmeni ve tüm başvuru verilerini tamamen SİLMEK istediğinize emin misiniz?")) return;

            let tr = btn.closest('tr'); // Tıklanan butonun olduğu tablo satırı
            let orijinalMetin = btn.innerHTML;
            let saniye = 4;

            btn.disabled = true; // Yanlışlıkla tekrar basılmasın diye butonu kilitle
            tr.style.transition = "all 0.5s ease"; 
            tr.style.backgroundColor = '#fee2e2'; // Satır kırmızımsı bir uyarı rengine dönsün

            // 1'er saniyelik geri sayım döngüsü başlat
            let geriSayim = setInterval(() => {
                saniye--;
                
                if (saniye > 0) {
                    btn.innerHTML = `⏳ Siliniyor... (${saniye})`;
                    tr.style.opacity = (saniye / 4) + 0.2; // Satır yavaş yavaş solgunlaşsın (hayalet efekt)
                } else {
                    // 4 Saniye bitti! Döngüyü durdur ve veritabanına ölüm fermanını gönder
                    clearInterval(geriSayim);
                    btn.innerHTML = `🗑️ Yok Edildi`;

                    fetch('../api/admin_teacher_action.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ islem: 'sil', id: id })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            // Başarılıysa satırı ekrandan tamamen kaydırarak yok et
                            tr.style.transform = "translateX(-100%)";
                            setTimeout(() => { tr.remove(); }, 400);
                        } else {
                            alert("Hata: " + data.message);
                            btn.disabled = false;
                            btn.innerHTML = orijinalMetin;
                            tr.style.backgroundColor = '';
                            tr.style.opacity = 1;
                        }
                    });
                }
            }, 1000); // Her 1000ms (1 saniye) de bir çalışır
        }
    </script>

</body>

</html>