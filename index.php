<?php
/* ==========================================================
   Dosya Yolu: index.php (Ana Dizin)
   İşlev: Öğrenci ve Öğretmen Giriş / Kayıt Arayüzü
   ========================================================== */
/* GÜVENLİK: [2026-08] Merkezi güvenli oturum başlatıcıya geçildi.
   Çerez artık HttpOnly + SameSite=Lax; JavaScript ile çalınamaz. */
require_once __DIR__ . '/bilesenler/guvenlik.php';
guvenliOturumBaslat();
// Sınıflar artık anlık olarak PIN kodu ile çekiliyor. Veritabanı yükü kaldırıldı.
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>mBlock Platformu | Giriş Yap</title>
    <link rel="stylesheet" href="css/main_login.css">
    <script src="<?= varlik('js/onay_kutusu.js', '') ?>"></script>
</head>

<body>

    <div class="login-container">
        <h2 class="header-title">mBlock Akademi</h2>
        <p class="header-subtitle">Kodlama Macerasına Hoş Geldin!</p>

        <div class="tab-container">
            <div class="tab-btn active" id="tabOgrenci">👨‍💻 Öğrenci</div>
            <div class="tab-btn" id="tabOgretmen">👨‍🏫 Öğretmen</div>
        </div>

        <div id="formOgrenciGiris">
            <div class="form-group">
                <label style="color:#4f46e5;">🔑 Öğretmen PIN Kodu</label>
                <div style="display:flex; gap:10px;">
                    <input type="text" id="login_ogr_pin" placeholder="Örn: 9K2A" style="border-color:#4f46e5; font-weight:bold; text-transform:uppercase;">
                    <button type="button" id="btnLoginPinSorgula" style="background:#4f46e5; color:white; border:none; padding:10px 15px; border-radius:8px; cursor:pointer; font-weight:bold;">Sınıfları Getir</button>
                </div>
                <p id="loginPinMesaj" style="font-size:12px; margin-top:5px; font-weight:bold;"></p>
            </div>

            <div id="loginGizliAlanlar" class="hidden">
                <p id="loginOgretmenBilgi" style="font-size:13px; color:#475569; margin-bottom:10px; font-weight:bold; text-align:center;"></p>
                <select id="login_ogr_sinif" name="sinif_id" required style="width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 6px; border: 2px solid #4f46e5; font-weight:bold; outline:none;">
                    <option value="">Sınıfınızı Seçin</option>
                </select>
                <div class="form-group">
                    <label>Kullanıcı Adı</label>
                    <input type="text" id="ogr_kullanici" placeholder="Kullanıcı adını gir">
                </div>
                <div class="form-group">
                    <label>Şifre</label>
                    <input type="password" id="ogr_sifre" placeholder="Şifreni gir">
                </div>
                <button class="btn-submit" id="btnOgrenciGiris">Giriş Yap</button>
                <p id="ogrGirisMesaj" style="text-align:center; font-weight:bold; font-size:14px; margin-top:10px;"></p>
            </div>

            <div class="switch-form">
                Hesabın yok mu? <a id="btnOgrenciKayitGecis" style="cursor: pointer; color: #4f46e5;">Sınıf PIN Kodu ile Kayıt Ol</a>
            </div>
        </div>

        <div id="formOgrenciKayit" class="hidden">
            <div class="form-group">
                <label>Ad Soyad</label>
                <input type="text" id="reg_ogr_ad" placeholder="Gerçek adın ve soyadın">
            </div>
            <div class="form-group">
                <label>Kullanıcı Adı</label>
                <input type="text" id="reg_ogr_kullanici" placeholder="Giriş yaparken kullanacağın ad">
            </div>
            <div class="form-group">
                <label>Şifre</label>
                <input type="password" id="reg_ogr_sifre" placeholder="Şifre belirle">
            </div>

            <div class="form-group">
                <label style="color:#10b981;">🔑 Öğretmen PIN Kodu</label>
                <div style="display:flex; gap:10px;">
                    <input type="text" id="reg_ogr_pin" placeholder="Örn: 9K2A" style="border-color:#10b981; font-weight:bold; text-transform:uppercase;">
                    <button type="button" id="btnPinSorgula" style="background:#10b981; color:white; border:none; padding:10px 15px; border-radius:8px; cursor:pointer; font-weight:bold;">Sınıfımı Bul</button>
                </div>
                <p id="pinMesaj" style="font-size:12px; margin-top:5px; font-weight:bold;"></p>
            </div>

            <div class="form-group hidden" id="sinifSecimKutusu">
                <label style="color:#4f46e5;">📌 Sınıfını Seç</label>
                <select id="reg_ogr_sinif" style="width: 100%; padding: 12px; border: 2px solid #4f46e5; border-radius: 8px; font-weight:bold; outline:none; cursor:pointer;">
                </select>
                <p id="ogretmenBilgiMetni" style="font-size:13px; color:#475569; margin-top:8px; font-weight:bold;"></p>
            </div>

            <input type="hidden" id="gizli_ogretmen_id" value="0">

            <button class="btn-submit" id="btnOgrenciKaydet" style="background:#10b981;" disabled>Önce Sınıfını Bul</button>
            <p id="ogrKayitGenelMesaj" style="text-align:center; font-weight:bold; font-size:14px; margin-top:10px;"></p>

            <div class="switch-form">
                Zaten hesabın var mı? <a id="btnOgrenciGirisGecis" style="cursor: pointer; color: #4f46e5;">Giriş Yap</a>
            </div>
        </div>

        <div id="formOgretmenGiris" class="hidden">
            <div class="form-group">
                <label>Kullanıcı Adı (Öğretmen)</label>
                <input type="text" id="login_ogrt_kullanici" placeholder="Kullanıcı adınız">
            </div>
            <div class="form-group">
                <label>Şifre</label>
                <input type="password" id="login_ogrt_sifre" placeholder="Şifreniz">
            </div>
            <div style="text-align: right; margin-bottom: 15px;">
                <a href="#" id="btnSifremiUnuttum" style="color: #ef4444; font-size: 13px; font-weight: bold; text-decoration: none; transition: 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">🔒 Şifremi Unuttum</a>
            </div>
            <button class="btn-submit" id="btnOgretmenGiris">Yönetim Paneline Gir</button>
            <p id="ogrtGirisMesaj" style="text-align:center; font-weight:bold; font-size:14px; margin-top:10px;"></p>
            <div class="switch-form">
                Sisteme katılmak ister misiniz? <a id="btnOgretmenKayitGecis" style="cursor: pointer; color: #4f46e5;">Okul Kaydı Oluştur</a>
            </div>
        </div>

        <div id="formOgretmenKayit" class="hidden">
            <div class="form-group">

                <label>Ad Soyad</label>
                <input type="text" id="reg_ogrt_ad" placeholder="Adınız Soyadınız">
            </div>
            <div class="form-group">
                <label style="color: #f59e0b;"> E-Posta Adresi</label>
                <input type="email" id="reg_ogrt_email" placeholder="Bu mail adresi şifre sıfırlamada kullanılacaktır" style="border-color: #f59e0b; font-weight: bold;">
            </div>
            <div class="form-group">
                <label>İl & İlçe</label>
                <div style="display:flex; gap:10px;">
                    <input type="text" id="reg_ogrt_il" placeholder="İl (Örn: İstanbul)">
                    <input type="text" id="reg_ogrt_ilce" placeholder="İlçe (Örn: Kadıköy)">
                </div>
            </div>

            <div class="form-group">
                <label>Okul Adı</label>
                <input type="text" id="reg_ogrt_okul" placeholder="Örn: X Ortaokulu">
            </div>
            <div class="form-group">
                <label>Kullanıcı Adı & Şifre</label>
                <div style="display:flex; gap:10px;">
                    <input type="text" id="reg_ogrt_kullanici" placeholder="Kullanıcı adı">
                    <input type="password" id="reg_ogrt_sifre" placeholder="Şifre">
                </div>
            </div>
            <div class="form-group">
                <label>Telefon & Öğrenci Sayısı</label>
                <div style="display:flex; gap:10px;">
                    <input type="text" id="reg_ogrt_tel" placeholder="Telefon numaranız">
                    <input type="number" id="reg_ogrt_kontenjan" placeholder="Öğrenci sayısı">
                </div>
            </div>

            <div class="form-group">
                <label style="color: #4f46e5;">⏳ Kaç Aylık Kullanım Talep Ediyorsunuz?</label>
                <input type="number" id="reg_ogrt_talep_ay" placeholder="Örn: 6" min="1" max="12" value="6" style="border-color: #4f46e5; font-weight: bold;">
            </div>

            <button class="btn-submit" id="btnOgretmenKaydet" style="background:#f59e0b;">Admin Onayına Gönder</button>
            <p id="ogrtKayitMesaj" style="text-align:center; font-weight:bold; font-size:14px; margin-top:10px;"></p>
            <div class="switch-form">
                Zaten kaydınız var mı? <a id="btnOgretmenGirisGecis" style="cursor: pointer; color: #4f46e5;">Giriş Yap</a>
            </div>
        </div>

        <div style="text-align:center; margin-top:22px; padding-top:16px; border-top:1px solid #e2e8f0;">
            <a href="admin/super_giris.php" style="color:#94a3b8; font-size:12px; text-decoration:none;"
               onmouseover="this.style.color='#64748b'" onmouseout="this.style.color='#94a3b8'">
                🔐 Süper Admin Girişi
            </a>
        </div>

    </div>

    <script>
        const tabs = {
            ogrGiris: document.getElementById('formOgrenciGiris'),
            ogrKayit: document.getElementById('formOgrenciKayit'),
            ogrtGiris: document.getElementById('formOgretmenGiris'),
            ogrtKayit: document.getElementById('formOgretmenKayit')
        };

        const btnOgrenci = document.getElementById('tabOgrenci');
        const btnOgretmen = document.getElementById('tabOgretmen');

        function hideAll() {
            Object.values(tabs).forEach(t => t.classList.add('hidden'));
        }

        // Üst Sekme Tıklamaları
        btnOgrenci.addEventListener('click', () => {
            hideAll();
            tabs.ogrGiris.classList.remove('hidden');
            btnOgrenci.classList.add('active');
            btnOgretmen.classList.remove('active');
        });
        btnOgretmen.addEventListener('click', () => {
            hideAll();
            tabs.ogrtGiris.classList.remove('hidden');
            btnOgretmen.classList.add('active');
            btnOgrenci.classList.remove('active');
        });

        // Form İçi Alt Link Tıklamaları
        document.getElementById('btnOgrenciKayitGecis').addEventListener('click', () => {
            hideAll();
            tabs.ogrKayit.classList.remove('hidden');
        });
        document.getElementById('btnOgrenciGirisGecis').addEventListener('click', () => {
            hideAll();
            tabs.ogrGiris.classList.remove('hidden');
        });
        document.getElementById('btnOgretmenKayitGecis').addEventListener('click', () => {
            hideAll();
            tabs.ogrtKayit.classList.remove('hidden');
        });
        document.getElementById('btnOgretmenGirisGecis').addEventListener('click', () => {
            hideAll();
            tabs.ogrtGiris.classList.remove('hidden');
        });

        // ==========================================
        // 1. ÖĞRENCİ GİRİŞ İÇİN PIN SORGULAMA İŞLEMİ
        // ==========================================
        document.getElementById('btnLoginPinSorgula').addEventListener('click', () => {
            const pin = document.getElementById('login_ogr_pin').value.trim();
            const mesaj = document.getElementById('loginPinMesaj');
            const gizliAlanlar = document.getElementById('loginGizliAlanlar');
            const sinifSelect = document.getElementById('login_ogr_sinif');
            const ogretmenBilgi = document.getElementById('loginOgretmenBilgi');

            if (pin === "") {
                mesaj.style.color = "#ef4444";
                mesaj.innerText = "Lütfen önce PIN kodunu yazın.";
                return;
            }

            mesaj.style.color = "#4f46e5";
            mesaj.innerText = "Sınıflar aranıyor...";

            fetch('api/verify_pin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        pin: pin
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        mesaj.innerText = "";
                        gizliAlanlar.classList.remove('hidden');
                        ogretmenBilgi.innerText = `🏢 ${data.okul_adi} | 👨‍🏫 ${data.ogretmen_ad}`;

                        // Sınıfları dropdown'a doldur
                        sinifSelect.innerHTML = '<option value="">Sınıfınızı Seçiniz...</option>';
                        data.siniflar.forEach(sinif => {
                            sinifSelect.add(new Option(sinif.sinif_adi, sinif.id));  // [2026-11] innerHTML yerine — XSS kalkanı
                        });
                    } else {
                        mesaj.style.color = "#ef4444";
                        mesaj.innerText = "❌ " + data.message;
                        gizliAlanlar.classList.add('hidden');
                    }
                });
        });

        // ==========================================
        // 2. ÖĞRENCİ GİRİŞ İŞLEMİ (SINIF KONTROLLÜ)
        // ==========================================
        document.getElementById('btnOgrenciGiris').addEventListener('click', () => {
            const btn = document.getElementById('btnOgrenciGiris');
            const mesajAlan = document.getElementById('ogrGirisMesaj');

            const girisVerisi = {
                sinif_id: document.getElementById('login_ogr_sinif').value,
                kullanici_adi: document.getElementById('ogr_kullanici').value.trim(),
                sifre: document.getElementById('ogr_sifre').value.trim()
            };

            if (girisVerisi.sinif_id === "") {
                mesajAlan.style.color = "#ef4444";
                mesajAlan.innerText = "Lütfen listeden sınıfınızı seçin!";
                return;
            }

            if (girisVerisi.kullanici_adi === "" || girisVerisi.sifre === "") {
                mesajAlan.style.color = "#ef4444";
                mesajAlan.innerText = "Kullanıcı adı ve şifre boş bırakılamaz!";
                return;
            }

            btn.innerText = "Giriş Yapılıyor...";
            btn.disabled = true;

            fetch('api/student_login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(girisVerisi)
                })
                .then(res => res.json())
                .then(data => {
                    btn.innerText = "Giriş Yap";
                    btn.disabled = false;

                    if (data.success) {
                        mesajAlan.style.color = "#10b981";
                        mesajAlan.innerText = "✅ " + data.message;
                        setTimeout(() => {
                            window.location.href = 'ogrenci_paneli.php';
                        }, 1500);
                    } else {
                        mesajAlan.style.color = "#ef4444";
                        mesajAlan.innerText = "❌ " + data.message;
                    }
                })
                .catch(err => {
                    btn.innerText = "Giriş Yap";
                    btn.disabled = false;
                    mesajAlan.innerText = "Bağlantı hatası oluştu!";
                });
        });

        // ==========================================
        // 3. ÖĞRENCİ PIN SORGULAMA İŞLEMİ (KAYIT İÇİN)
        // ==========================================
        document.getElementById('btnPinSorgula').addEventListener('click', () => {
            const pin = document.getElementById('reg_ogr_pin').value.trim();
            const mesaj = document.getElementById('pinMesaj');
            const sinifKutusu = document.getElementById('sinifSecimKutusu');
            const sinifSelect = document.getElementById('reg_ogr_sinif');
            const ogretmenBilgi = document.getElementById('ogretmenBilgiMetni');
            const btnKaydet = document.getElementById('btnOgrenciKaydet');
            const gizliOgretmenId = document.getElementById('gizli_ogretmen_id');

            if (pin === "") {
                mesaj.style.color = "#ef4444";
                mesaj.innerText = "Lütfen önce PIN kodunu yazın.";
                return;
            }

            mesaj.style.color = "#f59e0b";
            mesaj.innerText = "Öğretmen aranıyor...";

            fetch('api/verify_pin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        pin: pin
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        mesaj.innerText = "";
                        sinifKutusu.classList.remove('hidden');
                        ogretmenBilgi.innerText = `👨‍🏫 ${data.ogretmen_ad} | 🏢 ${data.okul_adi}`;
                        gizliOgretmenId.value = data.ogretmen_id;

                        sinifSelect.innerHTML = '<option value="0">Sınıfınızı Seçiniz...</option>';
                        data.siniflar.forEach(sinif => {
                            sinifSelect.add(new Option(sinif.sinif_adi, sinif.id));  // [2026-11] innerHTML yerine — XSS kalkanı
                        });

                        btnKaydet.disabled = false;
                        btnKaydet.innerText = "Kayıt Ol ve Sınıfa Katıl";
                    } else {
                        mesaj.style.color = "#ef4444";
                        mesaj.innerText = "❌ " + data.message;
                        sinifKutusu.classList.add('hidden');
                        btnKaydet.disabled = true;
                        btnKaydet.innerText = "Önce Sınıfını Bul";
                    }
                });
        });

        // ==========================================
        // 4. ÖĞRENCİ KAYIT İŞLEMİ
        // ==========================================
        document.getElementById('btnOgrenciKaydet').addEventListener('click', () => {
            const mesajAlan = document.getElementById('ogrKayitGenelMesaj');
            const sinifId = document.getElementById('reg_ogr_sinif').value;

            if (sinifId === "0") {
                mesajAlan.style.color = "#ef4444";
                mesajAlan.innerText = "Lütfen listeden sınıfınızı seçin!";
                return;
            }

            const kayitVerisi = {
                ad_soyad: document.getElementById('reg_ogr_ad').value,
                kullanici_adi: document.getElementById('reg_ogr_kullanici').value,
                sifre: document.getElementById('reg_ogr_sifre').value,
                ogretmen_id: document.getElementById('gizli_ogretmen_id').value,
                sinif_id: sinifId
            };

            fetch('api/student_register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(kayitVerisi)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        mesajAlan.style.color = "#10b981";
                        mesajAlan.innerText = "✅ " + data.message;

                        setTimeout(() => {
                            document.getElementById('btnOgrenciGirisGecis').click();
                            document.getElementById('ogr_kullanici').value = kayitVerisi.kullanici_adi;
                            // PIN'i de otomatik aktaralım kolaylık olsun
                            document.getElementById('login_ogr_pin').value = document.getElementById('reg_ogr_pin').value;
                        }, 2000);
                    } else {
                        mesajAlan.style.color = "#ef4444";
                        mesajAlan.innerText = "❌ " + data.message;
                    }
                });
        });

        // ==========================================
        // 5. ÖĞRETMEN KAYIT İŞLEMİ
        // ==========================================
        document.getElementById('btnOgretmenKaydet').addEventListener('click', () => {
            const btn = document.getElementById('btnOgretmenKaydet');
            const mesajAlan = document.getElementById('ogrtKayitMesaj');

            const kayitVerisi = {
                ad_soyad: document.getElementById('reg_ogrt_ad').value,
                email: document.getElementById('reg_ogrt_email').value,
                il: document.getElementById('reg_ogrt_il').value,
                ilce: document.getElementById('reg_ogrt_ilce').value,
                okul_adi: document.getElementById('reg_ogrt_okul').value,
                kullanici_adi: document.getElementById('reg_ogrt_kullanici').value,
                sifre: document.getElementById('reg_ogrt_sifre').value,
                telefon: document.getElementById('reg_ogrt_tel').value,
                kontenjan: document.getElementById('reg_ogrt_kontenjan').value,
                talep_ay: document.getElementById('reg_ogrt_talep_ay').value
            };

            btn.innerText = "Gönderiliyor...";
            btn.disabled = true;

            fetch('api/teacher_register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(kayitVerisi)
                })
                .then(res => res.json())
                .then(data => {
                    btn.innerText = "Admin Onayına Gönder";
                    btn.disabled = false;

                    if (data.success) {
                        mesajAlan.style.color = "#10b981";
                        mesajAlan.innerText = "✅ " + data.message + " Giriş ekranına yönlendiriliyorsunuz...";

                        // Formu temizle
                        document.querySelectorAll('#formOgretmenKayit input').forEach(input => input.value = '');

                        // YENİ: 2 saniye bekle, Öğretmen Giriş sekmesine tıkla ve Kullanıcı Adını otomatik doldur
                        setTimeout(() => {
                            document.getElementById('btnOgretmenGirisGecis').click();
                            document.getElementById('login_ogrt_kullanici').value = kayitVerisi.kullanici_adi;
                        }, 2000);

                    } else {
                        mesajAlan.style.color = "#ef4444";
                        mesajAlan.innerText = "❌ " + data.message;
                    }
                })
                .catch(err => {
                    btn.innerText = "Admin Onayına Gönder";
                    btn.disabled = false;
                    mesajAlan.innerText = "Bağlantı hatası oluştu!";
                });
        });

        // ==========================================
        // 6. ÖĞRETMEN GİRİŞ İŞLEMİ
        // ==========================================
        document.getElementById('btnOgretmenGiris').addEventListener('click', () => {
            const btn = document.getElementById('btnOgretmenGiris');
            const mesajAlan = document.getElementById('ogrtGirisMesaj');

            const girisVerisi = {
                kullanici_adi: document.getElementById('login_ogrt_kullanici').value,
                sifre: document.getElementById('login_ogrt_sifre').value
            };

            btn.innerText = "Kontrol ediliyor...";
            btn.disabled = true;

            fetch('api/teacher_login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(girisVerisi)
                })
                .then(res => res.json())
                .then(data => {
                    btn.innerText = "Yönetim Paneline Gir";
                    btn.disabled = false;

                    if (data.success) {
                        mesajAlan.style.color = "#10b981";
                        mesajAlan.innerHTML = "✅ " + data.message;
                        setTimeout(() => {
                            window.location.href = 'admin/ogretmen_paneli.php';
                        }, 1500);
                    } else {
                        mesajAlan.style.color = "#ef4444";
                        mesajAlan.innerHTML = "❌ " + data.message;


                        // YENİ: Süre Bitmişse Özel Talep Formu Göster
                        if (data.reason === 'expired') {
                            mesajAlan.innerHTML += `
                        <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 15px; text-align: left;">
                            <label style="font-weight: bold; color: #1e293b; display: block; margin-bottom: 8px; font-size: 14px;">⏳ Kaç Aylık Ek Süre İstiyorsunuz?</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="number" id="talepAy" value="1" min="1" max="12" style="flex: 1; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 15px;">
                                <button id="btnSureGonder" style="background: #f59e0b; color: white; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s;">Talep Et</button>
                            </div>
                        </div>`;

                            // Butona tıklanınca öğretmenin yazdığı Ay sayısını gönder
                            document.getElementById('btnSureGonder').addEventListener('click', function() {
                                const secilenAy = document.getElementById('talepAy').value;
                                this.innerText = "Gönderiliyor...";
                                this.disabled = true;

                                fetch('api/teacher_time_request.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json'
                                        },
                                        body: JSON.stringify({
                                            kullanici_adi: girisVerisi.kullanici_adi,
                                            ay: secilenAy
                                        })
                                    })
                                    .then(r => r.json())
                                    .then(d => {
                                        if (d.success) {
                                            mesajAlan.style.color = "#10b981";
                                            mesajAlan.innerHTML = "✅ <b>" + secilenAy + " Aylık</b> süre talebiniz Süper Admine başarıyla iletildi! Onaylandığında tekrar giriş yapabilirsiniz.";
                                        } else {
                                            mesajAlan.innerHTML = "❌ Hata: " + d.message;
                                        }
                                    });
                            });
                        }
                    }
                })
                .catch(err => {
                    btn.innerText = "Yönetim Paneline Gir";
                    btn.disabled = false;
                    mesajAlan.innerText = "Bağlantı hatası oluştu!";
                });
        });
        // YENİ EKLENEN KOD BAŞLANGICI: Şifremi Unuttum İşlemi
    document.getElementById('btnSifremiUnuttum').addEventListener('click', async function(e) {
        e.preventDefault();
        let emailAdresi = await metinKutusu("Şifrenizi sıfırlamak için sisteme kayıtlı E-POSTA ADRESİNİZİ giriniz:");
        if (emailAdresi !== null && emailAdresi.trim() !== "") {
            fetch('api/password_reset.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: emailAdresi.trim() })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    alert("✅ " + data.message);
                } else {
                    alert("❌ " + data.message);
                }
            });
        }
    });
    // YENİ EKLENEN KOD BİTİŞİ
    
    </script>

</body>

</html>