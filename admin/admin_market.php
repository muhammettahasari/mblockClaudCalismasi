<?php
/* ==========================================================
   Dosya Yolu: admin/admin_market.php
   İşlev: Süper Admin eşya (market) yönetim paneli
   GÜVENLİK: [2026-08] Süper Admin girişi zorunlu hale getirildi (#1).
   ========================================================== */
require_once __DIR__ . '/super_auth.php';
superAdminZorunlu();
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Gizemli Market Yönetimi</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
            padding: 20px;
        }

        .kutu {
            background: #1e293b;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #334155;
            margin-bottom: 20px;
        }

        .form-grup {
            margin-bottom: 15px;
            position: relative;
        }

        .form-grup label {
            display: block;
            margin-bottom: 5px;
            color: #94a3b8;
            font-weight: bold;
        }

        .form-grup input,
        .form-grup select {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #475569;
            background: #0f172a;
            color: white;
        }

        .btn {
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            font-weight: bold;
            color: white;
            transition: 0.3s;
            font-size: 13px;
        }

        .btn-mavi {
            background: #3b82f6;
        }

        .btn-mavi:hover {
            background: #2563eb;
        }

        .btn-yesil {
            background: #10b981;
        }

        .btn-yesil:hover {
            background: #059669;
        }

        .btn-kirmizi {
            background: #ef4444;
        }

        .btn-kirmizi:hover {
            background: #dc2626;
        }

        .btn-iptal {
            background: #f59e0b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #334155;
            vertical-align: middle;
        }

        th {
            background: #0f172a;
            color: #cbd5e1;
        }

        .item-row:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .aksiyon-alan {
            display: flex;
            gap: 5px;
            opacity: 0.3;
            transition: 0.2s;
        }

        .item-row:hover .aksiyon-alan {
            opacity: 1;
        }

        /* Satır İçi Girdi Stilleri */
        .satir-input {
            background: #0f172a;
            color: #f8fafc;
            border: 1px solid #475569;
            padding: 6px;
            border-radius: 4px;
            font-size: 13px;
            width: 100%;
        }

        .satir-input:focus {
            outline: 1px solid #3b82f6;
            border-color: #3b82f6;
        }

        .admin-resim-cerceve {
            width: 60px;
            height: 40px;
            border-radius: 6px;
            background-color: rgba(0, 0, 0, 0.4);
            border: 1px solid #334155;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .admin-resim-kutu {
            width: 220px;
            height: 140px;
            background-repeat: no-repeat;
            transform: scale(0.30);
            flex-shrink: 0;
        }

        .bg-kafa {
            background-position: 50% 25%;
            background-size: 400%;
        }

        .bg-sac {
            background-position: 50% 10%;
            background-size: 600%;
        }

        .bg-sag_el {
            background-position: 55% 90%;
            background-size: 300%;
        }

        .bg-sol_el {
            background-position: 60% 67%;
            background-size: 330%;
        }

        .bg-ayakkabi {
            background-position: 50% 95%;
            background-size: 500%;

        }

        .bg-zirh {
            background-position: 50% 50%;
            background-size: 250%;
        }

    .bg-yuz { background-position: 50% 25%; background-size: 400%; }

        .bg-karakter {
            background-position: 50% 70%;
            background-size: 110%;
        }

        .bg-saldiri_troll {
            background-position: center;
            background-size: contain;
        }

        .bg-binek {
            background-position: -20% 80%;
            background-size: 150%;
        }

        .bg-evcil_hayvan {
            background-position: 90% 90%;
            background-size: 250%;
        }
    </style>
    <script src="<?= varlik('js/onay_kutusu.js', '../') ?>"></script>
</head>

<body>
<?php require __DIR__ . '/../bilesenler/super_gezinme.php'; ?>


    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>🛒 Market Envanter Yönetimi</h2>
        <a href="ogretmen_yonetimi.php" class="btn" style="background: #475569; text-decoration: none; padding: 10px 15px; font-size: 15px;">⬅️ Ana Panele Dön</a>
    </div>

    <div class="kutu">
        <h3 id="formBaslik" style="margin-top:0; color:#10b981;">➕ Yeni Eşya Ekle / Düzenle</h3>
        <form id="esyaEkleForm" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-grup" style="flex: 1; min-width: 200px;">
                <label>Resim Seç (.png)</label>
                <input type="file" id="resimSec" accept="image/png">
                <small id="resimNot" style="color:#f59e0b; display:none; font-size:11px; position: absolute; bottom: -18px; left: 0;">(Resmi değiştirmeyeceksen boş bırak)</small>
            </div>
            <div class="form-grup" style="flex: 1; min-width: 200px;">
                <label>Eşya Adı</label>
                <input type="text" id="esyaAdi" placeholder="Örn: Demir Çizme">
            </div>
            <div class="form-grup" style="flex: 1; min-width: 120px;">
                <label>Seviye Seç</label>
                <select id="esyaSeviye" onchange="seviyeAyarla()">
                    <option value="">Seviye Yok</option>
                    <option value="1. Seviye">1. Seviye</option>
                    <option value="2. Seviye">2. Seviye</option>
                    <option value="3. Seviye">3. Seviye</option>
                    <option value="4. Seviye">4. Seviye</option>
                    <option value="Destansı">Destansı</option>
                </select>
            </div>
            <div class="form-grup" style="flex: 1; min-width: 100px;">
                <label>Puan</label>
                <input type="number" id="esyaPuan" placeholder="Örn: 100">
            </div>
            <div class="form-grup" style="flex: 1; min-width: 150px;">
                <label>Kategori</label>
                <select id="esyaKategori">
                    <option value="sag_el">Sağ El (Silah)</option>
                    <option value="sol_el">Sol El (Kalkan)</option>
                    <option value="kafa">Şapka / Miğfer</option>
                    <option value="ayakkabi">Ayakkabı</option>
                    <option value="zirh">Zırh (Göğüs)</option>
                    <option value="yuz">Yüz (Sakal, Göz)</option>
                    <option value="binek">Binek Hayvan</option>
                    <option value="evcil_hayvan">Evcil Hayvan</option>
                    <option value="sac">Saç</option>
                    <option value="karakter">Beden</option>
                </select>
            </div>
            <div class="form-grup" style="display: flex; gap: 10px;">
                <button type="button" id="btnKaydet" class="btn btn-mavi" onclick="esyaKaydet()" style="padding: 10px 20px;">Kataloğa Ekle</button>
                <button type="button" id="btnIptal" class="btn btn-kirmizi" onclick="duzenlemeIptal()" style="padding: 10px 20px; display:none;">İptal</button>
            </div>
        </form>
    </div>

    <div class="kutu">
        <div style="background: #1e293b; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 15px; align-items: center; border: 1px solid #475569;">
            <span style="color: #94a3b8; font-weight: bold;">☑️ Seçilileri:</span>

            <select id="topluSeviye" style="padding: 8px; border-radius: 6px; border: 1px solid #3b82f6; background: #0f172a; color: white; font-weight: bold;">
                <option value="">-- Hızlı Seviye Uygula --</option>
                <option value="1. Seviye">Tümünü 1. Seviye Yap</option>
                <option value="2. Seviye">Tümünü 2. Seviye Yap</option>
                <option value="3. Seviye">Tümünü 3. Seviye Yap</option>
                <option value="4. Seviye">Tümünü 4. Seviye Yap</option>
                <option value="Destansı">Tümünü Destansı Yap</option>
                <option value="">Tümünden Seviyeyi Kaldır</option>
            </select>
            <button class="btn btn-mavi" onclick="topluSeviyeUygula()">⚡ Otomatik İşle</button>

            <span style="color:#475569; margin: 0 10px;">|</span>
            <button class="btn btn-kirmizi" id="btnTopluSil" onclick="topluSilBaslat()"><span style="font-size: 15px;">🗑️</span> Toplu Sil</button>
        </div>

        <div style="display: flex; justify-content: space-between; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; gap: 10px;">
                <input type="text" id="filtreArama" onkeyup="esyalariGetir()" placeholder="🔍 İsme göre ara..." style="padding: 8px; border-radius: 6px; border: 1px solid #475569; background: #0f172a; color: white;">

                <select id="filtreKategori" onchange="esyalariGetir()" style="padding: 8px; border-radius: 6px; border: 1px solid #475569; background: #0f172a; color: white;">
                    <option value="hepsi">Tüm Kategoriler</option>
                    <option value="sag_el">Sağ El (Silah)</option>
                    <option value="sol_el">Sol El (Kalkan)</option>
                    <option value="kafa">Şapka / Miğfer</option>
                    <option value="binek">Binek Hayvan</option>
                    <option value="evcil_hayvan">Evcil Hayvan</option>
                    <option value="ayakkabi">Ayakkabı</option>
                    <option value="zirh">🛡️ Zırh (Göğüs)</option>
                    <option value="yuz">🧔 Yüz (Sakal, Göz)</option>
                    <option value="sac">Saç</option>
                    <option value="karakter">Beden</option>
                </select>

                <select id="filtreSiralama" onchange="esyalariGetir()" style="padding: 8px; border-radius: 6px; border: 1px solid #10b981; background: #0f172a; color: #10b981; font-weight: bold;">
                    <option value="yeni">⏱️ Son Eklenenler</option>
                    <option value="eski">⏳ İlk Eklenenler</option>
                    <option value="ad_az">🔤 A'dan Z'ye</option>
                    <option value="ad_za">🔤 Z'den A'ya</option>
                    <option value="fiyat_az">💎 Ucuzdan Pahalıya</option>
                    <option value="fiyat_za">💎 Pahalıdan Ucuza</option>
                </select>
            </div>

            <button class="btn" style="background: #8b5cf6;" onclick="klasorleriSenkronizeEt()" id="btnSync">🔄 Klasörleri Tara ve Senkronize Et</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 30px;"><input type="checkbox" id="tumunuSec" onclick="tumunuSecTetikle(this)"></th>
                    <th style="width: 70px;">Görsel</th>
                    <th>Eşya Adı (Düzenlenebilir)</th>
                    <th style="width: 140px;">Hızlı Seviye Seç</th>
                    <th style="width: 80px;">Puan</th>
                    <th style="width: 100px;">Kategori</th>
                    <th style="text-align: right; width: 200px;">İşlemler</th>
                </tr>
            </thead>
            <tbody id="tabloGövdesi">
            </tbody>
        </table>
    </div>

    <script>
        let guncellenecekId = null;
        let hamEsyaAdi = "";

        // ANA FORM İŞLEMLERİ 
        document.getElementById('resimSec').addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                let orjinalAd = e.target.files[0].name.replace(/\.[^/.]+$/, "");
                let dosyaAdi = orjinalAd.replace(/[-_]/g, " ");
                dosyaAdi = dosyaAdi.split(" ").map(kelime => kelime.charAt(0).toUpperCase() + kelime.slice(1)).join(" ");
                dosyaAdi = dosyaAdi.replace(/(\d)\s*Seviye/gi, "$1. Seviye");

                let seviyeKutusu = document.getElementById('esyaSeviye');
                if (dosyaAdi.match(/1\.\s*Seviye/i)) seviyeKutusu.value = "1. Seviye";
                else if (dosyaAdi.match(/2\.\s*Seviye/i)) seviyeKutusu.value = "2. Seviye";
                else if (dosyaAdi.match(/3\.\s*Seviye/i)) seviyeKutusu.value = "3. Seviye";
                else if (dosyaAdi.match(/4\.\s*Seviye/i)) seviyeKutusu.value = "4. Seviye";
                else if (dosyaAdi.match(/Destans/i)) seviyeKutusu.value = "Destansı";
                else seviyeKutusu.value = "";

                hamEsyaAdi = dosyaAdi.replace(/1\.\s*Seviye\s*|2\.\s*Seviye\s*|3\.\s*Seviye\s*|4\.\s*Seviye\s*|Destansı\s*|Destansi\s*/gi, "").trim();
                seviyeAyarla();
            }
        });

        function seviyeAyarla() {
            let seviye = document.getElementById('esyaSeviye').value;
            let puanInput = document.getElementById('esyaPuan');
            let isimInput = document.getElementById('esyaAdi');

            let ekrandakiIsim = isimInput.value.replace(/1\.\s*Seviye\s*|2\.\s*Seviye\s*|3\.\s*Seviye\s*|4\.\s*Seviye\s*|Destansı\s*|Destansi\s*/gi, "").trim();
            let suAnkiIsim = hamEsyaAdi !== "" ? hamEsyaAdi : ekrandakiIsim;

            isimInput.value = seviye !== "" ? seviye + " " + suAnkiIsim : suAnkiIsim;

            if (seviye === "1. Seviye") puanInput.value = "100";
            else if (seviye === "2. Seviye") puanInput.value = "500";
            else if (seviye === "3. Seviye") puanInput.value = "2000";
            else if (seviye === "4. Seviye") puanInput.value = "20000";
            else if (seviye === "Destansı") puanInput.value = "50000";
            else puanInput.value = "50";
        }

        function esyaKaydet() {
            let formData = new FormData();
            let resim = document.getElementById('resimSec').files[0];
            if (!guncellenecekId && !resim) {
                alert("Lütfen bir resim seçin!");
                return;
            }

            formData.append('islem', guncellenecekId ? 'guncelle' : 'ekle');
            if (guncellenecekId) formData.append('id', guncellenecekId);
            if (resim) formData.append('resim', resim);
            formData.append('esya_adi', document.getElementById('esyaAdi').value);
            formData.append('puan', document.getElementById('esyaPuan').value);
            formData.append('kategori', document.getElementById('esyaKategori').value);

            fetch('../api/api_market_islem.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        esyalariGetir();
                        duzenlemeIptal();
                    } else {
                        alert("Hata!");
                    }
                });
        }

        function duzenleModu(id, ad, fiyat, kategori) {
            guncellenecekId = id;
            hamEsyaAdi = "";
            document.getElementById('esyaAdi').value = ad;
            document.getElementById('esyaPuan').value = fiyat;
            document.getElementById('esyaKategori').value = kategori;

            let seviyeKutusu = document.getElementById('esyaSeviye');
            if (ad.match(/1\.\s*Seviye/i)) seviyeKutusu.value = "1. Seviye";
            else if (ad.match(/2\.\s*Seviye/i)) seviyeKutusu.value = "2. Seviye";
            else if (ad.match(/3\.\s*Seviye/i)) seviyeKutusu.value = "3. Seviye";
            else if (ad.match(/4\.\s*Seviye/i)) seviyeKutusu.value = "4. Seviye";
            else if (ad.match(/Destans/i)) seviyeKutusu.value = "Destansı";
            else seviyeKutusu.value = "";

            document.getElementById('formBaslik').innerHTML = "✏️ Eşya Düzenle";
            document.getElementById('btnKaydet').innerHTML = "Güncelle";
            document.getElementById('btnIptal').style.display = "block";
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        function duzenlemeIptal() {
            guncellenecekId = null;
            hamEsyaAdi = "";
            document.getElementById('esyaEkleForm').reset();
            document.getElementById('formBaslik').innerHTML = "➕ Yeni Eşya Ekle";
            document.getElementById('btnKaydet').innerHTML = "Kataloğa Ekle";
            document.getElementById('btnIptal').style.display = "none";
        }

        // ==========================================
        // YENİ: SATIR İÇİ (INLINE) LİSTELEME VE KAYIT
        // ==========================================
        function esyalariGetir() {
            let kategori = document.getElementById('filtreKategori').value;
            let arama = document.getElementById('filtreArama').value;
            let siralama = document.getElementById('filtreSiralama').value;

            fetch(`../api/api_market_islem.php?islem=listele&kategori=${kategori}&arama=${arama}&siralama=${siralama}`)
                .then(res => res.json())
                .then(data => {
                    let html = '';
                    data.data.forEach(esya => {
                        let guvenliAd = esya.esya_adi.replace(/'/g, "&#39;");
                        let safAd = esya.esya_adi.replace(/1\.\s*Seviye\s*|2\.\s*Seviye\s*|3\.\s*Seviye\s*|4\.\s*Seviye\s*|Destansı\s*|Destansi\s*/gi, "").trim();

                        let s1 = esya.esya_adi.match(/1\.\s*Seviye/i) ? 'selected' : '';
                        let s2 = esya.esya_adi.match(/2\.\s*Seviye/i) ? 'selected' : '';
                        let s3 = esya.esya_adi.match(/3\.\s*Seviye/i) ? 'selected' : '';
                        let s4 = esya.esya_adi.match(/4\.\s*Seviye/i) ? 'selected' : '';
                        let sD = esya.esya_adi.match(/Destans/i) ? 'selected' : '';

                        html += `
                    <tr class="item-row" id="satir_${esya.id}">
                        <td><input type="checkbox" class="satir-sec" value="${esya.id}"></td>
                        <td>
                            <div class="admin-resim-cerceve">
                                <div class="admin-resim-kutu bg-${esya.kategori}" style="background-image: url('../${esya.resim_yolu}');"></div>
                            </div>
                        </td>
                        <td>
                            <input type="text" id="isim_${esya.id}" value="${guvenliAd}" class="satir-input">
                            <input type="hidden" id="ham_isim_${esya.id}" value="${safAd}">
                        </td>
                        <td>
                            <select id="seviye_${esya.id}" onchange="satirSeviyeDegisti(${esya.id})" class="satir-input" style="border-color:#3b82f6;">
                                <option value="">Yok</option>
                                <option value="1. Seviye" ${s1}>1. Seviye</option>
                                <option value="2. Seviye" ${s2}>2. Seviye</option>
                                <option value="3. Seviye" ${s3}>3. Seviye</option>
                                <option value="4. Seviye" ${s4}>4. Seviye</option>
                                <option value="Destansı" ${sD}>Destansı</option>
                            </select>
                        </td>
                        <td><input type="number" id="puan_${esya.id}" value="${esya.fiyat}" class="satir-input"></td>
                        <td style="color:#94a3b8; font-size: 12px;">${esya.kategori}</td>
                        <td style="text-align: right;">
                            <div class="aksiyon-alan" style="justify-content: flex-end;">
                                <button class="btn btn-yesil" id="btnKaydet_${esya.id}" onclick="satirKaydet(${esya.id})" title="Hızlı Kaydet">💾 Kaydet</button>
                                <button class="btn btn-mavi" onclick="duzenleModu(${esya.id}, '${guvenliAd}', ${esya.fiyat}, '${esya.kategori}')" title="Resmi ve detayları düzenle">✏️</button>
                                <button class="btn btn-kirmizi" id="btnSil_${esya.id}" onclick="tekilSilBaslat(${esya.id})" title="Sil">🗑️</button>
                            </div>
                        </td>
                    </tr>`;
                    });
                    document.getElementById('tabloGövdesi').innerHTML = html;
                });
        }

        function satirSeviyeDegisti(id) {
            let seviye = document.getElementById(`seviye_${id}`).value;
            let isimInput = document.getElementById(`isim_${id}`);
            let puanInput = document.getElementById(`puan_${id}`);
            let hamAd = document.getElementById(`ham_isim_${id}`).value;

            isimInput.value = seviye !== "" ? seviye + " " + hamAd : hamAd;

            if (seviye === "1. Seviye") puanInput.value = "100";
            else if (seviye === "2. Seviye") puanInput.value = "500";
            else if (seviye === "3. Seviye") puanInput.value = "2000";
            else if (seviye === "4. Seviye") puanInput.value = "20000";
            else if (seviye === "Destansı") puanInput.value = "50000";
            else puanInput.value = "50";
        }

        function satirKaydet(id) {
            let btn = document.getElementById(`btnKaydet_${id}`);
            let isim = document.getElementById(`isim_${id}`).value;
            let puan = document.getElementById(`puan_${id}`).value;

            btn.innerHTML = "⏳";
            let fd = new FormData();
            fd.append('islem', 'hizli_guncelle');
            fd.append('id', id);
            fd.append('esya_adi', isim);
            fd.append('fiyat', puan);

            fetch('../api/api_market_islem.php', {
                    method: 'POST',
                    body: fd
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        btn.innerHTML = "✅";
                        setTimeout(() => btn.innerHTML = "💾 Kaydet", 2000);
                    }
                });
        }

        // ==========================================
        // TOPLU İŞLEM MOTORU (BULK ACTIONS)
        // ==========================================
        function tumunuSecTetikle(anaCheckbox) {
            let kutular = document.querySelectorAll('.satir-sec');
            kutular.forEach(kutu => kutu.checked = anaCheckbox.checked);
        }

        function seciliIdleriAl() {
            let secililer = [];
            document.querySelectorAll('.satir-sec:checked').forEach(kutu => secililer.push(kutu.value));
            return secililer;
        }

        // --- ZAMANLAYICI DEĞİŞKENİ ---
        let silmeZamanlayicilari = {};

        // --- 1. TOPLU SİLME İŞLEMLERİ ---
        async function topluSilBaslat() {
            let idler = seciliIdleriAl();
            if (idler.length === 0) {
                alert("Lütfen tablodan eşya seçin.");
                return;
            }
            if (!await onayKutusu(`Seçilen ${idler.length} eşya kalıcı olarak silinecek. Emin misiniz?`)) return;

            let btn = document.getElementById('btnTopluSil');
            let sayac = 3;
            btn.classList.remove('btn-kirmizi');
            btn.classList.add('btn-iptal');
            btn.innerHTML = "⏳ İptal (" + sayac + ")";
            btn.setAttribute('onclick', `topluSilIptal()`);

            silmeZamanlayicilari['toplu'] = setInterval(() => {
                sayac--;
                if (sayac > 0) {
                    btn.innerHTML = "⏳ İptal (" + sayac + ")";
                } else {
                    clearInterval(silmeZamanlayicilari['toplu']);
                    btn.innerHTML = "Silindi!";
                    btn.disabled = true;
                    gercektenSil(idler, true);
                }
            }, 1000);
        }

        function topluSilIptal() {
            clearInterval(silmeZamanlayicilari['toplu']);
            let btn = document.getElementById('btnTopluSil');
            btn.classList.remove('btn-iptal');
            btn.classList.add('btn-kirmizi');
            btn.innerHTML = `<span style="font-size: 15px;">🗑️</span> Toplu Sil`;
            btn.setAttribute('onclick', `topluSilBaslat()`);
        }

        // --- 2. TEKİL (SATIR İÇİ) SİLME İŞLEMLERİ ---
        async function tekilSilBaslat(id) {
            if (!await onayKutusu("Bu eşyayı silmek istediğinize emin misiniz?")) return;
            let btn = document.getElementById('btnSil_' + id);
            let sayac = 3;
            btn.classList.remove('btn-kirmizi');
            btn.classList.add('btn-iptal');
            btn.innerHTML = "⏳ (" + sayac + ")";
            btn.setAttribute('onclick', `tekilSilIptal(${id})`);

            silmeZamanlayicilari['tekil_' + id] = setInterval(() => {
                sayac--;
                if (sayac > 0) {
                    btn.innerHTML = "⏳ (" + sayac + ")";
                } else {
                    clearInterval(silmeZamanlayicilari['tekil_' + id]);
                    btn.innerHTML = "Silindi";
                    btn.disabled = true;
                    gercektenSil([id]);
                }
            }, 1000);
        }

        function tekilSilIptal(id) {
            clearInterval(silmeZamanlayicilari['tekil_' + id]);
            let btn = document.getElementById('btnSil_' + id);
            btn.classList.remove('btn-iptal');
            btn.classList.add('btn-kirmizi');
            btn.innerHTML = "🗑️";
            btn.setAttribute('onclick', `tekilSilBaslat(${id})`);
        }

        // --- 3. VERİTABANINDAN GERÇEKTEN SİLME ---
        function gercektenSil(idDizisi, isToplu = false) {
            let fd = new FormData();
            fd.append('islem', 'sil');
            idDizisi.forEach(id => fd.append('idler[]', id));
            fetch('../api/api_market_islem.php', {
                    method: 'POST',
                    body: fd
                })
                .then(res => res.json())
                .then(data => {
                    esyalariGetir();
                    document.getElementById('tumunuSec').checked = false;
                    if (isToplu) {
                        let btn = document.getElementById('btnTopluSil');
                        btn.classList.remove('btn-iptal');
                        btn.classList.add('btn-kirmizi');
                        btn.innerHTML = `<span style="font-size: 15px;">🗑️</span> Toplu Sil`;
                        btn.disabled = false;
                        btn.setAttribute('onclick', `topluSilBaslat()`);
                    }
                });
        }

        function topluSeviyeUygula() {
            let idler = seciliIdleriAl();
            let hedefSeviye = document.getElementById('topluSeviye').value;

            if (idler.length === 0) {
                alert("Lütfen tablodan eşya seçin.");
                return;
            }

            let guncellenecekVeriler = [];
            idler.forEach(id => {
                document.getElementById(`seviye_${id}`).value = hedefSeviye;
                satirSeviyeDegisti(id);
                guncellenecekVeriler.push({
                    id: id,
                    esya_adi: document.getElementById(`isim_${id}`).value,
                    fiyat: document.getElementById(`puan_${id}`).value
                });
            });

            let fd = new FormData();
            fd.append('islem', 'toplu_guncelle');
            fd.append('veriler', JSON.stringify(guncellenecekVeriler));

            fetch('../api/api_market_islem.php', {
                    method: 'POST',
                    body: fd
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(`✅ ${idler.length} adet eşya başarıyla güncellendi!`);
                        document.getElementById('tumunuSec').checked = false;
                        document.querySelectorAll('.satir-sec').forEach(k => k.checked = false);
                    }
                });
        }

        function klasorleriSenkronizeEt() {
            let btn = document.getElementById('btnSync');
            let eskiYazi = btn.innerHTML;
            btn.innerHTML = "⏳ Klasörler Taranıyor...";
            btn.disabled = true;

            let fd = new FormData();
            fd.append('islem', 'senkronize_et');
            fetch('../api/api_market_islem.php', {
                    method: 'POST',
                    body: fd
                })
                .then(res => res.json())
                .then(data => {
                    btn.innerHTML = eskiYazi;
                    btn.disabled = false;
                    if (data.success) {
                        alert("🎉 " + data.message);
                        document.getElementById('filtreSiralama').value = 'yeni';
                        esyalariGetir();
                    }
                })
                .catch(err => {
                    btn.innerHTML = eskiYazi;
                    btn.disabled = false;
                    alert("Tarama sırasında bir hata oluştu!");
                });
        }

        window.onload = esyalariGetir;
    </script>
</body>

</html>