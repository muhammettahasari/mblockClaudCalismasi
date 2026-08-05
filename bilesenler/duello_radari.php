<script>
    // --- 1. SES DOSYALARI VE BİLDİRİM MOTORU ---
  var uyariSesi = new Audio('assets/sounds/uyari.mp3');
var ogretmenUyariSesi = new Audio('assets/sounds/ogretmenuyari.mp3');

    function bildirimGoster(mesaj, tur = 'bilgi') {
        let kutu = document.createElement('div');
        kutu.style.position = 'fixed'; kutu.style.top = '20px'; kutu.style.left = '50%';
        kutu.style.transform = 'translateX(-50%)'; kutu.style.background = tur === 'hata' ? '#ef4444' : '#f59e0b';
        kutu.style.color = 'white'; kutu.style.padding = '15px 30px'; kutu.style.borderRadius = '12px';
        kutu.style.boxShadow = '0 10px 25px rgba(0,0,0,0.5)'; kutu.style.zIndex = '999999';
        kutu.style.fontWeight = 'bold'; kutu.style.fontSize = '16px'; kutu.style.textAlign = 'center';
        kutu.innerText = mesaj;
        document.body.appendChild(kutu);
        
        setTimeout(() => {
            kutu.style.opacity = '0'; kutu.style.transition = '0.5s';
            setTimeout(() => kutu.remove(), 500);
        }, 3000);
    }

    // --- 2. CANLI İSTEK PENCERESİ (MODAL) OLUŞTURUCU ---
    // Tarayıcının standart confirm kutusu yerine bu şık kutuyu kullanacağız
    let istekModal = document.createElement('div');
    istekModal.id = 'duelloIstekModal';
    istekModal.style.cssText = 'display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); z-index:999999; justify-content:center; align-items:center; backdrop-filter:blur(5px);';
    istekModal.innerHTML = `
        <div style="background:#1e293b; padding:30px; border-radius:16px; border:2px solid #ef4444; width:350px; text-align:center; box-shadow:0 0 40px rgba(239,68,68,0.4); color:white;">
            <div id="istekModalIkon" style="font-size:50px; margin-bottom:10px; animation: pulse 1s infinite;">🚨</div>
            <h2 id="istekModalBaslik" style="margin-top:0; color:#ef4444;">DÜELLO İSTEĞİ!</h2>
            <p id="istekModalMetin" style="font-size:15px; margin-bottom:15px; color:#cbd5e1;"></p>
            <div style="font-size:26px; font-weight:bold; color:#facc15; margin-bottom:20px; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">⏳ <span id="istekModalSayac">5</span></div>
            <div id="istekModalButonlar" style="display:flex; gap:10px;"></div>
        </div>
    `;
    document.body.appendChild(istekModal);

    let istekSayaciInterval = null;

    // --- 3. RAKİBE İSTEK ATAN KISIM (SALDIRAN) ---
    function meydanOku(hedefId, hedefAd) {
        if(!confirm(`⚔️ ${hedefAd} adlı savaşçıya meydan okumak üzeresin! Emin misin?`)) return;
        
        let formData = new FormData();
        formData.append('hedef_id', hedefId);

        fetch('api/duello/istek_at.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                // İstek başarıyla gitti, Saldıranın ekranında 5 saniye geri sayım başlat!
                document.getElementById('istekModalIkon').innerText = '⚔️';
                document.getElementById('istekModalBaslik').innerText = 'MEYDAN OKUNDU!';
                document.getElementById('istekModalBaslik').style.color = '#3b82f6';
                istekModal.querySelector('div>div').style.borderColor = '#3b82f6';
                document.getElementById('istekModalMetin').innerText = `${hedefAd} adlı savaşçının cevabı bekleniyor...`;
                document.getElementById('istekModalButonlar').innerHTML = ''; // Bekleyen kişi buton göremez
                
                let sayac = 5;
                document.getElementById('istekModalSayac').innerText = sayac;
                istekModal.style.display = 'flex';

                clearInterval(istekSayaciInterval);
                istekSayaciInterval = setInterval(() => {
                    sayac--;
                    document.getElementById('istekModalSayac').innerText = sayac;
                    if(sayac <= 0) {
                        clearInterval(istekSayaciInterval);
                        istekModal.style.display = 'none';
                        bildirimGoster("Rakip cevap vermedi, istek zaman aşımına uğradı.", "hata");
                    }
                }, 1000);

            } else {
                bildirimGoster(data.mesaj, 'hata'); 
            }
        }).catch(e => console.error("Ateşleme Hatası:", e));
    }

   // --- 4. GELEN İSTEKLERİ DİNLEYEN SENSÖR (BİRLEŞİK RADAR) ---
    // [2026-08] Kendi setInterval'ı kaldırıldı; js/ogrenci_radar.js üzerindeki
    // TEK radara abone olunuyor (Bilinen Sorunlar #11).
    let sonArenaDurumu = null;
    let duelloDinlemeAktif = true;

    mBlockRadar.abone((radarVerisi) => {
        if (!duelloDinlemeAktif) return;

        // Birleşik radarın düello bölümünü eski değişken adına eşle
        const data = radarVerisi.duello || {};

        {
           // --- CANLI BUTON GÜNCELLEYİCİ (KÖKTEN ÇÖZÜM) ---
            if (sonArenaDurumu !== null && sonArenaDurumu !== data.arena_aktif) {
                sonArenaDurumu = data.arena_aktif; // Döngüye girmemesi için durumu hemen güncelle
                
                if (data.arena_aktif) {
                    try { if(typeof ogretmenUyariSesi !== 'undefined') ogretmenUyariSesi.play(); } catch(e){}
                    alert("⚔️ MÜJDE! Düello Arenası Açıldı! Artık rakiplerinize meydan okuyabilirsiniz.");
                } else {
                    try { if(typeof ogretmenUyariSesi !== 'undefined') ogretmenUyariSesi.play(); } catch(e){}
                    alert("🔒 Düello Arenası Kapatıldı veya Süre Doldu! Kılıçlar kınına dönüyor...");
                }
                
                // Butonları JavaScript ile aramak yerine, sayfayı otomatik yenileyip 
                // PHP'nin en güncel ve doğru butonları basmasını sağlıyoruz. (Savaş Arenasındaki gibi)
                window.location.reload(); 
            }
            sonArenaDurumu = data.arena_aktif;
            // ----------------------------------------------

            // RAKİBİN SAVAŞI KABUL ETTİ, SENİ DE ARENAYA ÇEKİYORUZ!
            if (data.arenaya_git) {
                duelloDinlemeAktif = false;
                mBlockRadar.durdur();
                if(typeof istekSayaciInterval !== 'undefined') clearInterval(istekSayaciInterval);
                window.location.href = 'duello_arena.php?id=' + data.duello_id;
                return;
            }

            // SANA BİRİ MEYDAN OKUDUYSA (SAVUNAN EKRANI)
            if (data.gelen_istek) {
                duelloDinlemeAktif = false; // Başka istek gelmesin diye dinlemeyi durdur
                
                // 🎵 UYARI SESİNİ ÇAL (Güvenli)
                try { if(typeof uyariSesi !== 'undefined') uyariSesi.play(); } catch(e){}
                
                // ÖZEL UYARI PENCERESİNİ DÜZENLE VE GÖSTER
                let istekModal = document.getElementById('duelloIstekModal');
                if(istekModal) {
                    document.getElementById('istekModalIkon').innerText = '🚨';
                    document.getElementById('istekModalBaslik').innerText = 'DÜELLO İSTEĞİ!';
                    document.getElementById('istekModalBaslik').style.color = '#ef4444';
                    istekModal.querySelector('div>div').style.borderColor = '#ef4444';
                    // XSS koruması: öğrenci adı HTML olarak yorumlanmasın
                    const guvenliAd = document.createElement('div');
                    guvenliAd.textContent = data.meydan_okuyan_ad || 'Bir arkadaşın';
                    document.getElementById('istekModalMetin').innerHTML = `<b>${guvenliAd.innerHTML}</b> sana meydan okuyor!<br><br><span style="font-size:12px; color:#94a3b8; background:#0f172a; padding:5px 10px; border-radius:5px;">Kazanırsan +6 Puan, Kaybedersen -3 Puan</span>`;
                    
                    document.getElementById('istekModalButonlar').innerHTML = `
                        <button id="btnReddet" style="flex:1; padding:12px; background:#ef4444; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:bold; transition:0.2s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'">❌ Reddet</button>
                        <button id="btnKabul" style="flex:1; padding:12px; background:#10b981; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:bold; transition:0.2s;" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'">✔️ Kabul Et</button>
                    `;

                    let sayac = 5;
                    document.getElementById('istekModalSayac').innerText = sayac;
                    istekModal.style.display = 'flex';

                    let yanitVerildi = false;

                    const yanitGonder = (yanit) => {
                        if(yanitVerildi) return;
                        yanitVerildi = true;
                        if(typeof istekSayaciInterval !== 'undefined') clearInterval(istekSayaciInterval);
                        istekModal.style.display = 'none';

                        let formData = new FormData();
                        formData.append('duello_id', data.duello_id);
                        formData.append('yanit', yanit);

                        fetch('api/duello/yanitla.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(yanitData => {
                            if(yanit === 'kabul' && yanitData.status === 'basladi') {
                                bildirimGoster("⚔️ Savaş başlıyor! Arenaya ışınlanıyorsunuz...");
                                window.location.href = 'duello_arena.php?id=' + data.duello_id;
                            } else {
                                if(yanit === 'red') bildirimGoster("Düelloyu reddettin veya süre doldu.", "hata");
                                setTimeout(() => location.reload(), 1000); 
                            }
                        });
                    };

                    document.getElementById('btnReddet').onclick = () => yanitGonder('red');
                    document.getElementById('btnKabul').onclick = () => yanitGonder('kabul');

                    if(typeof istekSayaciInterval !== 'undefined') clearInterval(istekSayaciInterval);
                    istekSayaciInterval = setInterval(() => {
                        sayac--;
                        document.getElementById('istekModalSayac').innerText = sayac;
                        if(sayac <= 0) yanitGonder('red'); 
                    }, 1000);
                }
            }
        }
    });
</script>