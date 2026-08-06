<?php
/* ==========================================================
   Dosya Yolu: admin/ogrenci_yonetimi.php
   İşlev: Öğretmenin sınıf ve öğrenci yönetim ekranı
   GÜVENLİK: [2026-08] Merkezi oturum kapıcısına geçildi (bilesenler/guvenlik.php).
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';
ogretmenZorunlu();

$ogretmen_id = $_SESSION['ogretmen_id'];
$okul_adi = $_SESSION['okul_adi'] ?? 'Okul Bilinmiyor';

require_once '../db.php';
$sinifSorgu = $pdo->prepare("SELECT id, sinif_adi FROM siniflar WHERE ogretmen_id = ? ORDER BY sinif_adi ASC");
$sinifSorgu->execute([$ogretmen_id]);
$siniflar = $sinifSorgu->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Öğrenciler | mBlock Akademi</title>
    <!-- [2026-08] Ortak öğretmen teması. teacher_panel.css hâlâ yükleniyor
         çünkü bu sayfadaki modal/animasyon sınıflarının bir kısmı orada. -->
    <link rel="stylesheet" href="../css/ogretmen_tema.css">
    <link rel="stylesheet" href="../css/teacher_panel.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-box {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .modal-box h3 {
            margin-top: 0;
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #475569;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .btn-grup {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-modal {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            color: white;
        }

        .btn-cancel {
            background: #94a3b8;
        }

        .btn-save {
            background: #10b981;
        }

        .btn-excel {
            background: #10b981;
        }

        .btn-pdf {
            background: #3b82f6;
        }

        .btn-manual {
            background: #3b82f6;
        }

        .edit-input {
            width: 100%;
            padding: 6px;
            border: 2px solid #3b82f6;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }

        .kirmizi-uyari {
            color: #ef4444 !important;
            font-weight: bold;
        }

        .kirmizi-satir {
            background-color: #fef2f2 !important;
        }

        .btn-action {
            padding: 8px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            color: white;
            font-weight: bold;

        }

        /* YENİ: Sınıf Oluşturma Animasyon Stilleri */
        #classSuccessOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(5px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            flex-direction: column;
        }

        .success-text {
            font-size: 32px;
            font-weight: bold;
            color: #10b981;
            text-align: center;
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            z-index: 10;
            text-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stars-container {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
            top: 0;
            left: 0;
        }

        .star {
            position: absolute;
            font-size: 30px;
            animation: scatter 1s ease-out forwards;
            left: 50%;
            top: 50%;
        }

        @keyframes scatter {
            0% {
                transform: translate(-50%, -50%) scale(0.5);
                opacity: 1;
            }

            100% {
                transform: translate(var(--tx), var(--ty)) scale(1.5) rotate(360deg);
                opacity: 0;
            }
        }

        @keyframes popIn {
            0% {
                transform: scale(0.5);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
</head>

<body class="ot">
<?php $OT_AKTIF = 'ogrenciler'; require __DIR__ . '/../bilesenler/ogretmen_ust_bar.php'; ?>

    <div id="classSuccessOverlay">
        <div class="stars-container" id="starsContainer"></div>
        <div class="success-text" id="classSuccessText"></div>
    </div>

    <div class="ot-sarmal">

        <!-- Onay bekleyen kayıt uyarısı -->
        <div id="onayUyarisi" class="ot-hata-kutu" style="display:none; font-weight:800;">
            🚨 Onay bekleyen öğrenciler var — aşağıdaki listede yeşil onay düğmesine bas.
        </div>

        <!-- ==================== 1. SINIFLAR ====================
             [2026-08] Sınıf işlemleri ve öğrenci işlemleri eskiden aynı gri
             kutuda karışıktı. Artık iki ayrı kart: önce sınıfı seç/oluştur,
             sonra o sınıfa öğrenci ekle. -->
        <div class="ot-kart">
            <div class="ot-kart-baslik">
                <h2>🏫 Sınıflar</h2>
                <span class="ot-rozet ot-rozet-mor"><?= count($siniflar) ?> sınıf</span>
            </div>
            <p class="aciklama">Önce listelemek istediğin sınıfı seç. Yeni sınıf açabilir ya da boş sınıfları silebilirsin.</p>

            <div style="display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap;">
                <div style="flex:1 1 260px;">
                    <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Listelenecek sınıf</label>
                    <select id="sinifSelect" class="ot-girdi" onchange="ogrencileriCek(true)">
                        <option value="0">Tüm sınıflar</option>
                        <?php foreach ($siniflar as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= temizle($s['sinif_adi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="flex:1 1 260px;">
                    <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Yeni sınıf oluştur</label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" id="yeniSinifAdi" class="ot-girdi" placeholder="Örn: 5-A" style="flex:2;">
                        <button id="btnSinifEkle" class="ot-btn ot-btn-ana" style="flex:1;">Oluştur</button>
                    </div>
                </div>

                <button class="ot-btn ot-btn-sade" onclick="acModal('modalSinifSil')"
                        style="color:#b91c1c; border-color:#fca5a5;">🗑️ Sınıf Sil</button>
            </div>
        </div>


        <!-- ==================== 2. ÖĞRENCİ EKLEME ==================== -->
        <div class="ot-kart">
            <div class="ot-kart-baslik">
                <h2>➕ Öğrenci Ekle</h2>
            </div>
            <p class="aciklama">
                Tek tek ekleyebilir ya da Excel'den toplu aktarabilirsin.
                Şifre listesini PDF olarak indirip sınıfa dağıtabilirsin.
            </p>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button class="ot-btn ot-btn-ana"     onclick="ogrenciEkleModalAc('modalSingle')">👤 Tek Öğrenci</button>
                <button class="ot-btn ot-btn-yesil"   onclick="ogrenciEkleModalAc('modalExcel')">📊 Excel'den Toplu</button>
                <button class="ot-btn ot-btn-sade"    onclick="pdfIndir()">📄 Şifre Listesi (PDF)</button>
            </div>
        </div>


        <!-- ==================== 3. ÖĞRENCİ LİSTESİ ==================== -->
        <div class="ot-kart">
            <div class="ot-kart-baslik">
                <h2>👥 Öğrenci Listesi</h2>
                <span class="ot-canli"><span class="ot-canli-nokta"></span> Canlı</span>
            </div>
            <p class="aciklama">Ada tıklayıp düzenleyebilir, onay bekleyenleri buradan onaylayabilirsin.</p>

        <div class="ot-tablo-sarmal">
        <table class="ot-tablo student-table">
            <thead>
                <tr>
                    <th>Öğrenci Adı</th>
                    <th>Sınıfı</th>
                    <th>Kullanıcı Adı</th>
                    <th>Şifre</th>
                    <th style="text-align: center;">Kayıt Onay</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody id="ogrenciListesi">
                <tr>
                    <td colspan="6" class="ot-bos">Öğrenciler yükleniyor…</td>
                </tr>
            </tbody>
        </table>
        </div><!-- /.ot-tablo-sarmal -->
        </div><!-- /.ot-kart -->
    </div><!-- /.ot-sarmal -->

    <div class="modal-overlay" id="modalSingle">
        <div class="modal-box">
            <h3>👤 Manuel Öğrenci Ekle</h3>
            <div class="form-group">
                <label>Sınıf Seçiniz</label>
                <select id="singleSinifId">
                    <option value="">Sınıf Seçiniz...</option>
                    <?php foreach ($siniflar as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['sinif_adi']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Ad Soyad:</label><input type="text" id="singleAd"></div>
            <div class="form-group"><label>Kullanıcı Adı:</label><input type="text" id="singleKadi"></div>
            <div class="form-group"><label>Şifre:</label><input type="text" id="singleSifre"></div>
            <div class="btn-grup">
                <button class="btn-modal btn-cancel" onclick="kapatModal('modalSingle')">İptal</button>
                <button class="btn-modal btn-save" onclick="tekliOgrenciKaydet()">Kaydet</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalExcel">
        <div class="modal-box">
            <h3>📊 Excel'den Toplu Ekle</h3>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 15px;">Sütun Başlıkları: <br><code>Ad Soyad</code> | <code>Kullanıcı Adı</code> | <code>Şifre</code></p>
            <div class="form-group">
                <label>Sınıf Seçiniz</label>
                <select id="excelSinifId">
                    <option value="">Sınıf Seçiniz...</option>
                    <?php foreach ($siniflar as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['sinif_adi']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Excel Dosyası Seçin:</label><input type="file" id="excelDosya" accept=".xlsx, .xls"></div>
            <div class="btn-grup">
                <button class="btn-modal btn-cancel" onclick="kapatModal('modalExcel')">İptal</button>
                <button class="btn-modal btn-save" onclick="excelVerisiniYukle()">Yükle</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalSinifSil">
        <div class="modal-box">
            <h3>🗑️ Sınıf Sil</h3>
            <p style="font-size: 13px; color: #ef4444; margin-bottom: 15px; font-weight:bold;">Dikkat: Sınıf silindiğinde içindeki TÜM öğrenciler de kalıcı olarak silinir!</p>

            <div class="form-group" style="max-height: 200px; overflow-y: auto; border: 1px solid #cbd5e1; padding: 10px; border-radius: 6px;">
                <?php if (empty($siniflar)): ?>
                    <p style="color:#64748b; font-size:14px;">Silinecek sınıf bulunmuyor.</p>
                <?php else: ?>
                    <?php foreach ($siniflar as $s): ?>
                        <label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 8px; margin-bottom: 8px; border-bottom: 1px solid #f1f5f9; padding-bottom: 5px;">
                            <input type="checkbox" class="sinif-sil-cb" value="<?= $s['id'] ?>"> <?= htmlspecialchars($s['sinif_adi']) ?>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div id="sinifSilSayacAlani" style="display:none; background: #fee2e2; padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 15px;">
                <strong style="color:#ef4444; font-size: 16px;">🚨 Sınıflar siliniyor... Son <span id="sinifSilSayac" style="font-size:20px;">5</span> saniye!</strong>
                <button id="btnSinifSilIptal" style="margin-top: 10px; background:#3b82f6; color:white; padding:8px 20px; font-size: 14px; border:none; border-radius:6px; cursor:pointer; font-weight:bold; width: 100%;">⏳ İPTAL ET</button>
            </div>

            <div class="btn-grup" id="sinifSilBtnGrup">
                <button class="btn-modal btn-cancel" onclick="kapatModal('modalSinifSil')">Vazgeç</button>
                <button class="btn-modal btn-pdf" onclick="siniflariSilBaslat()">🗑️ Seçilileri Sil</button>
            </div>
        </div>
    </div>

    <div id="pdfSablon" style="display:none; padding: 30px; background: white;">
        <h2 style="text-align:center; color:#1e293b; margin-bottom: 5px;">ÖĞRENCİ ŞİFRE LİSTESİ</h2>
        <h3 id="pdfOkulSinifBaslik" style="text-align:center; color:#475569; border-bottom:2px solid #e2e8f0; padding-bottom:15px; margin-top:0;"></h3>
        <table style="width:100%; border-collapse: collapse; margin-top:20px;">
            <thead>
                <tr style="background:#f1f5f9; text-align:left;">
                    <th style="padding:10px; border:1px solid #cbd5e1;">Ad Soyad</th>
                    <th style="padding:10px; border:1px solid #cbd5e1;">Kullanıcı Adı</th>
                    <th style="padding:10px; border:1px solid #cbd5e1;">Şifre</th>
                </tr>
            </thead>
            <tbody id="pdfTabloGövdesi"></tbody>
        </table>
    </div>

    <script>
        function acModal(id) {
            document.getElementById(id).style.display = 'flex';
        }

        function kapatModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function tekliOgrenciKaydet() {
            const sinif_id = document.getElementById('singleSinifId').value;
            const ad_soyad = document.getElementById('singleAd').value.trim();
            const kullanici_adi = document.getElementById('singleKadi').value.trim();
            const sifre = document.getElementById('singleSifre').value.trim();

            if (!sinif_id || !ad_soyad || !kullanici_adi || !sifre) {
                alert("Tüm alanları doldurun!");
                return;
            }

            fetch('../api/teacher_add_student.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    sinif_id,
                    ad_soyad,
                    kullanici_adi,
                    sifre
                })
            }).then(res => res.json()).then(data => {
                alert(data.message);
                if (data.success) {
                    kapatModal('modalSingle');
                    document.getElementById('singleAd').value = '';
                    document.getElementById('singleKadi').value = '';
                    document.getElementById('singleSifre').value = '';
                    ogrencileriCek(true);
                }
            });
        }

        function excelVerisiniYukle() {
            const sinif_id = document.getElementById('excelSinifId').value;
            const dosyaGirdisi = document.getElementById('excelDosya');

            if (!sinif_id) {
                alert("Sınıf seçin!");
                return;
            }
            if (dosyaGirdisi.files.length === 0) {
                alert("Dosya seçin!");
                return;
            }

            const dosya = dosyaGirdisi.files[0];
            const okuyucu = new FileReader();

            okuyucu.onload = function(e) {
                const workbook = XLSX.read(new Uint8Array(e.target.result), {
                    type: 'array'
                });
                const ogrenciListesi = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);

                if (ogrenciListesi.length === 0) {
                    alert("Veri bulunamadı.");
                    return;
                }

                fetch('../api/teacher_bulk_add.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        sinif_id: sinif_id,
                        ogrenciler: ogrenciListesi
                    })
                }).then(res => res.json()).then(data => {
                    alert(data.message);
                    if (data.success) {
                        kapatModal('modalExcel');
                        dosyaGirdisi.value = "";
                        ogrencileriCek(true);
                    }
                });
            };
            okuyucu.readAsArrayBuffer(dosya);
        }

        function pdfIndir() {
            const sinifSelect = document.getElementById('sinifSelect');
            const sinif_id = sinifSelect.value;
            const sinifAdi = sinifSelect.options[sinifSelect.selectedIndex].text;

            if (sinif_id == "0") {
                alert("Önce indireceğiniz sınıfı üstteki listeden seçin!");
                return;
            }

            fetch('../api/get_passwords.php?sinif_id=' + sinif_id).then(res => res.json()).then(data => {
                if (data.success && data.data.length > 0) {
                    document.getElementById('pdfOkulSinifBaslik').innerText = `<?= htmlspecialchars($okul_adi) ?> - ${sinifAdi}`;
                    const tbody = document.getElementById('pdfTabloGövdesi');
                    tbody.innerHTML = '';
                    data.data.forEach(ogr => {
                        tbody.innerHTML += `<tr><td style="padding:8px; border:1px solid #cbd5e1;">${ogr.ad_soyad}</td><td style="padding:8px; border:1px solid #cbd5e1;">${ogr.kullanici_adi}</td><td style="padding:8px; border:1px solid #cbd5e1;">${ogr.sifre}</td></tr>`;
                    });
                    const element = document.getElementById('pdfSablon');
                    element.style.display = 'block';
                    html2pdf().from(element).set({
                        margin: 10,
                        filename: `${sinifAdi}_Sifreler.pdf`,
                        html2canvas: {
                            scale: 2
                        },
                        jsPDF: {
                            orientation: 'portrait'
                        }
                    }).save().then(() => {
                        element.style.display = 'none';
                    });
                } else {
                    alert("Bu sınıfta öğrenci bulunmuyor.");
                }
            });
        }

        let sinifSilInterval;
        let sinifSilTimeout;

        function siniflariSilBaslat() {
            const seciliSiniflar = Array.from(document.querySelectorAll('.sinif-sil-cb:checked')).map(cb => cb.value);
            if (seciliSiniflar.length === 0) {
                alert("Lütfen silinecek en az bir sınıf seçin!");
                return;
            }
            if (!confirm("Seçili sınıfları ve içlerindeki tüm öğrencileri silmek istediğinize emin misiniz?")) return;

            document.getElementById('sinifSilBtnGrup').style.display = 'none';
            document.getElementById('sinifSilSayacAlani').style.display = 'block';

            let saniye = 5;
            document.getElementById('sinifSilSayac').innerText = saniye;

            sinifSilInterval = setInterval(() => {
                saniye--;
                document.getElementById('sinifSilSayac').innerText = saniye;
            }, 1000);

            sinifSilTimeout = setTimeout(() => {
                clearInterval(sinifSilInterval);
                fetch('../api/teacher_remote.php?islem=sinif_sil', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        sinif_id_listesi: seciliSiniflar
                    })
                }).then(res => res.json()).then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || "Silme işlemi başarısız.");
                        sinifSilIptalEt();
                    }
                });
            }, 5000);
        }

        document.getElementById('btnSinifSilIptal').addEventListener('click', sinifSilIptalEt);

        function sinifSilIptalEt() {
            clearTimeout(sinifSilTimeout);
            clearInterval(sinifSilInterval);
            document.getElementById('sinifSilBtnGrup').style.display = 'flex';
            document.getElementById('sinifSilSayacAlani').style.display = 'none';
        }

        // === ÖĞRENCİ LİSTESİ VE ONAY İŞLEMLERİ ===
        let aktifEditId = null;

        function ogrencileriCek(zorlaYenile = false) {
            if (aktifEditId !== null && !zorlaYenile) return;
            let secilenSinifId = document.getElementById('sinifSelect').value;

            fetch('../api/teacher_remote.php?islem=liste&sinif_id=' + secilenSinifId).then(res => res.json()).then(veri => {
                if (veri.success) {
                    let html = '';
                    veri.data.forEach(ogr => {
                        let sinifAdi = ogr.sinif_adi || 'Sınıfsız';

                        // YENİ ONAY ALANI
                        let onayAlani = '';
                        if (ogr.onay_durumu == 0) {
                            onayAlani = `<div style="display:flex; justify-content:center; gap:5px;">
                                    <button class="btn-action" style="background: #10b981;" onclick="kayitOnayla(${ogr.id})" title="Öğrenciyi Onayla">✅</button>
                                    <button class="btn-action" style="background: #ef4444;" onclick="ogrenciSil(${ogr.id})" title="Reddet ve Sil">❌</button>
                                 </div>`;
                        } else {
                            onayAlani = `<span style="color:#10b981; font-weight:bold; display:block; text-align:center;">✔️ Onaylı</span>`;
                        }

                        html += `<tr id="row_${ogr.id}" class="ogrenci-satir" data-orj-ad="${ogr.ad_soyad}" data-sinif-id="${ogr.sinif_id}">
                    <td>
                        <strong class="view-mode ogr-ad-span">${ogr.ad_soyad}</strong>
                        <input type="text" class="edit-mode edit-input" id="edit_ad_${ogr.id}" value="${ogr.ad_soyad}" style="display:none;">
                    </td>
                    <td><span class="view-mode">${sinifAdi}</span></td>
                    <td>
                        <span class="view-mode">${ogr.kullanici_adi}</span>
                        <input type="text" class="edit-mode edit-input" id="edit_kadi_${ogr.id}" value="${ogr.kullanici_adi}" style="display:none;">
                    </td>
                    <td>
                        <span class="view-mode">${ogr.sifre}</span>
                        <input type="text" class="edit-mode edit-input" id="edit_sifre_${ogr.id}" value="${ogr.sifre}" style="display:none;">
                    </td>
                    <td>${onayAlani}</td>
                    <td>
                        <div class="view-mode" style="display: flex; gap: 5px;">
                            <button class="btn-action" style="background: #3b82f6;" onclick="editModAc(${ogr.id})" title="Düzenle">✏️</button>
                            <button class="btn-action" style="background: #ef4444;" onclick="ogrenciSil(${ogr.id})" title="Sil">🗑️</button>
                        </div>
                        <div class="edit-mode" style="display:none; gap: 5px;">
                            <button class="btn-action" style="background: #10b981;" onclick="ogrenciKaydet(${ogr.id})" title="Kaydet">✅</button>
                            <button class="btn-action" style="background: #94a3b8;" onclick="editModKapat()" title="İptal">❌</button>
                        </div>
                    </td>
                </tr>`;
                    });
                    if (veri.data.length === 0) html = '<tr><td colspan="6" style="text-align:center; padding:20px;">Bu sınıfta öğrenci yok.</td></tr>';
                    document.getElementById('ogrenciListesi').innerHTML = html;
                    renkleriGuncelle();

                    // YENİ: Onay uyarısını göster/gizle
                    if (veri.bekleyen_onay_var) {
                        document.getElementById('onayUyarisi').style.display = 'block';
                    } else {
                        document.getElementById('onayUyarisi').style.display = 'none';
                    }
                }
            });
        }

        // KAYIT ONAYLAMA FONKSİYONU EKLENDİ
        function kayitOnayla(id) {
            fetch(`../api/teacher_remote.php?islem=kayit_onayla&id=${id}`).then(res => res.json()).then(data => {
                if (data.success) ogrencileriCek(true);
            });
        }

        function renkleriGuncelle() {
            let adSayilari = {};
            document.querySelectorAll('.ogrenci-satir').forEach(row => {
                let orjAd = row.getAttribute('data-orj-ad');
                let sinifId = row.getAttribute('data-sinif-id');
                let kokAdVeSinif = orjAd.replace(/\s+\d+$/, '').trim().toLowerCase() + "_" + sinifId;
                adSayilari[kokAdVeSinif] = (adSayilari[kokAdVeSinif] || 0) + 1;
            });
            document.querySelectorAll('.ogrenci-satir').forEach(row => {
                let orjAd = row.getAttribute('data-orj-ad');
                let sinifId = row.getAttribute('data-sinif-id');
                let kokAdVeSinif = orjAd.replace(/\s+\d+$/, '').trim().toLowerCase() + "_" + sinifId;
                let adSpan = row.querySelector('.ogr-ad-span');

                if (adSayilari[kokAdVeSinif] > 1) {
                    adSpan.classList.add('kirmizi-uyari');
                    row.classList.add('kirmizi-satir');
                } else {
                    adSpan.classList.remove('kirmizi-uyari');
                    row.classList.remove('kirmizi-satir');
                }
            });
        }

        function editModAc(id) {
            if (aktifEditId !== null) editModKapat();
            aktifEditId = id;
            const row = document.getElementById('row_' + id);
            row.querySelectorAll('.view-mode').forEach(el => el.style.display = 'none');
            row.querySelectorAll('.edit-mode').forEach(el => el.style.display = 'inline-block');
        }

        function editModKapat() {
            aktifEditId = null;
            ogrencileriCek(true);
        }

        function ogrenciSil(id) {
            if (!confirm("Öğrenciyi tamamen silmek istediğinize emin misiniz?")) return;
            fetch(`../api/teacher_remote.php?islem=ogrenci_sil&id=${id}`).then(res => res.json()).then(data => {
                if (data.success) ogrencileriCek(true);
            });
        }

        function ogrenciKaydet(id) {
            const ad = document.getElementById('edit_ad_' + id).value.trim();
            const kadi = document.getElementById('edit_kadi_' + id).value.trim();
            const sifre = document.getElementById('edit_sifre_' + id).value.trim();
            if (ad === '' || kadi === '' || sifre === '') {
                alert("Boş alan bırakılamaz!");
                return;
            }

            fetch(`../api/teacher_remote.php?islem=ogrenci_duzenle&id=${id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ad_soyad: ad,
                    kullanici_adi: kadi,
                    sifre: sifre
                })
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    aktifEditId = null;
                    ogrencileriCek(true);
                } else {
                    alert(data.message);
                }
            });
        }

        // YENİ: Öğrenci Eklerken Sınıf Kontrolü Yapan Mekanizma
        const kayitliSinifSayisi = <?= count($siniflar) ?>;

        function ogrenciEkleModalAc(modalId) {
            if (kayitliSinifSayisi === 0) {
                alert("Lütfen önce sınıflarınızı oluşturunuz 👇");
                document.getElementById('yeniSinifAdi').focus(); // Otomatik olarak sınıf ekleme kutusunu seçer!
                return;
            }
            acModal(modalId);
        }

        // YENİ: Sınıf Oluşturma Başarı Animasyonu (Yıldız Yağmuru)
        function showClassSuccessAnimation(className) {
            const overlay = document.getElementById('classSuccessOverlay');
            const textEl = document.getElementById('classSuccessText');
            const starsContainer = document.getElementById('starsContainer');

            textEl.innerHTML = `🎉 <br> <span style="color:#4f46e5;">${className}</span> <br> Sınıfı Başarıyla Oluşturuldu!`;
            overlay.style.display = 'flex';

            starsContainer.innerHTML = '';
            const colors = ['#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#8b5cf6'];

            // 20 adet yıldızı rastgele etrafa saç
            for (let i = 0; i < 20; i++) {
                let star = document.createElement('div');
                star.className = 'star';
                star.innerText = '⭐';
                star.style.color = colors[Math.floor(Math.random() * colors.length)];

                let angle = Math.random() * Math.PI * 2;
                let distance = 100 + Math.random() * 200; // Ekrana yayılma mesafesi

                star.style.setProperty('--tx', `calc(-50% + ${Math.cos(angle) * distance}px)`);
                star.style.setProperty('--ty', `calc(-50% + ${Math.sin(angle) * distance}px)`);

                starsContainer.appendChild(star);
            }

            // 2 Saniye sonra sayfayı yenileyerek sınıfı listeye düşür
            setTimeout(() => {
                location.reload();
            }, 2000);
        }

        // GÜNCELLENEN: Sınıf Oluştur Butonu Tıklama İşlemi
        document.getElementById('btnSinifEkle').addEventListener('click', function() {
            let sinifAdi = document.getElementById('yeniSinifAdi').value.trim();
            if (sinifAdi === '') return;

            let btn = this;
            btn.disabled = true;
            btn.innerText = 'Oluşturuluyor...';

            fetch(`../api/teacher_remote.php?islem=sinif_ekle&sinif_adi=${encodeURIComponent(sinifAdi)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Başarılı olursa ekranı flulaştır ve yıldızları patlat!
                        showClassSuccessAnimation(sinifAdi);
                    } else {
                        alert("Sınıf oluşturulurken hata oluştu.");
                        btn.disabled = false;
                        btn.innerText = 'Sınıf Oluştur';
                    }
                });
        });

        ogrencileriCek(true);
        setInterval(() => ogrencileriCek(false), 10000);
    </script>

</body>

</html>