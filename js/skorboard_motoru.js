/* ====================================================================
 * MODÜL: Skorboard JavaScript Motoru
 * DOSYA YOLU: js/skorboard_motoru.js
 * BAĞLI OLDUĞU ANA DOSYA: bilesenler/ogrenci_skorboard.php
 * AÇIKLAMA: Sekme geçişlerini, Arena savaşlarını ve Animasyonları yönetir.
 * ==================================================================== */

// 1. Ana Menüler Arası Geçiş
function anaMenuDegistir(hedefMenu, btn) {
    document.getElementById('menuBireysel').style.display = 'none';
    document.getElementById('menuCanavar').style.display = 'none';
    document.getElementById('menuArena').style.display = 'none';
    if(document.getElementById('menuGecmis')) document.getElementById('menuGecmis').style.display = 'none';
    
    document.querySelectorAll('.ana-btn').forEach(b => {
        b.style.background = 'rgba(0,0,0,0.3)';
        b.style.color = '#cbd5e1';
        b.style.boxShadow = 'inset 0 -3px 5px rgba(0,0,0,0.2)';
    });

    document.getElementById(hedefMenu).style.display = 'block';

   if (hedefMenu === 'menuBireysel') {
        btn.style.background = '#3b82f6';
    } else if (hedefMenu === 'menuCanavar') {
        btn.style.background = '#be185d';
    } else if (hedefMenu === 'menuArena') {
        btn.style.background = '#b91c1c'; 
    } else if (hedefMenu === 'menuGecmis') {
        btn.style.background = '#0f766e'; // Geçmiş için Teel (Koyu Turkuaz) Rengi
    }
    btn.style.color = 'white';
    btn.style.boxShadow = 'none';
}

// 2. Bireysel Lig Alt Sekme Geçişi
function sekmeDegistir(hedefTab, btn, sinifAdi) {
    let aktifRenk = (sinifAdi === 'bireysel-sekme-btn') ? '#3b82f6' : '#be185d';
    let tabloSinifi = (sinifAdi === 'bireysel-sekme-btn') ? '.sampiyona-tablosu' : '.canavar-tablosu';

    btn.closest('.leaderboard-widget').querySelectorAll(tabloSinifi).forEach(tab => tab.style.display = 'none');
    document.querySelectorAll('.' + sinifAdi).forEach(b => {
        b.style.background = 'transparent';
        b.style.color = '#cbd5e1';
    });

    document.getElementById(hedefTab).style.display = 'block';
    btn.style.background = aktifRenk;
    btn.style.color = 'white';
}

// 3. Ordu Gücü Modalı Sekme Geçişi
function orduSekmeDegistir(tabId, event) {
    document.querySelectorAll('.ordu-liste').forEach(l => l.style.display = 'none');
    document.querySelectorAll('.ordu-tab-btn').forEach(b => b.classList.remove('ordu-aktif'));
    document.getElementById(tabId).style.display = 'block';
    if(event) event.currentTarget.classList.add('ordu-aktif');
}

// 4. Aylık Savaş Kontrolü (Ayın 18'i Radarı)
document.addEventListener("DOMContentLoaded", function() {
    let bugun = new Date();
    // DİKKAT: Test için 18. Canlıya alırken 1 yapmayı unutma!
    if (bugun.getDate() === 18) {
        fetch('api/aylik_savas_motoru.php?v=' + Date.now())
            .then(response => response.text())
            .then(text => {
                try {
                    let data = JSON.parse(text);
                    if (data.status === 'basarili') {
                        const videoContainer = document.getElementById('videoIntroContainer');
                        const videoElement = document.getElementById('aylikSavasVideosu');
                        let oynatilacakVideo = (data.video === 'zafer') ? 'assets/videos/zafer.mp4' : 'assets/videos/canavar_kazandi.mp4';
                        videoElement.src = oynatilacakVideo;
                        videoContainer.style.display = 'flex';
                        videoElement.load();
                        videoElement.play().catch(error => {
                            console.log("Video oynatılamadı:", error);
                            videoContainer.style.display = 'none';
                            document.getElementById('savasRaporModal').style.display = 'flex';
                        });
                        videoElement.onended = function() {
                            videoContainer.style.display = 'none';
                            document.getElementById('savasRaporModal').style.display = 'flex';
                        };
                    }
                } catch (err) {
                    console.error("Savaş Radarı Hatası: ", err);
                }
            }).catch(err => console.error("Bağlantı Hatası: ", err));
    }
});

// 5. Sınıf Arenası Saldırı Fonksiyonu
async function arenadaSaldir(hedefId, hedefAd) {
    if (!await onayKutusu(hedefAd + ' adlı öğrenciye saldırmak istiyor musun? (Bedel: 20 Enerji)')) return;

    let formData = new FormData();
    formData.append('hedef_id', hedefId);

    fetch('api/arena_savas_motoru.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error("Sunucudan gelen yanıt bozuk: " + text);
        }

        if (data.status === 'error') {
            alert("HATA: " + data.mesaj);
            return;
        }

        // KÖKTEN ÇÖZÜM: Eski ve içi boş overlay'i bulursan ACIMADAN SİL!
        let eskiOverlay = document.getElementById('arenaSonucOverlay');
        if (eskiOverlay) {
            eskiOverlay.remove(); // Eskiyi DOM'dan tamamen siliyoruz ki bizi yanıltmasın
        }

        // Şimdi %100 sıfırdan, eksiksiz bir şekilde yeniden inşa et
        let overlay = document.createElement('div');
        overlay.id = 'arenaSonucOverlay';
        overlay.style.cssText = 'display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 99999; backdrop-filter: blur(5px);';
        overlay.innerHTML = `
            <div id="arenaSonucModal" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.5); width: 400px; background: #1e293b; border-radius: 15px; padding: 25px; text-align: center; box-shadow: 0 0 30px rgba(0,0,0,0.5); opacity: 0; transition: all 0.3s ease-out; border: 2px solid #334155;">
                <div id="arenaSonucBaslik" style="font-size: 28px; font-weight: bold; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 2px; text-shadow: 0 0 10px rgba(255,255,255,0.5);"></div>
                <hr style="border: 0; height: 1px; background: linear-gradient(90deg, transparent, #475569, transparent); margin: 15px 0;">
                <div id="arenaSonucMesaj" style="color: #e2e8f0; font-size: 16px; line-height: 1.5; margin-bottom: 20px; font-weight: 500;"></div>
                <div id="arenaSonucStats" style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 15px; margin-bottom: 20px; font-size: 13px; color: #94a3b8; display: flex; flex-direction: column; gap: 8px;">
                    <div id="myDamageStat"></div>
                    <div id="enemyDamageStat"></div>
                </div>
                <button onclick="window.closeArenaResult()" style="background: linear-gradient(180deg, #475569, #1e293b); color: white; border: 1px solid #64748b; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px; transition: 0.2s; box-shadow: 0 3px 0 #0f172a;">Savaş Meydanına Dön</button>
            </div>
        `;
        document.body.appendChild(overlay);
        
        // Animasyon CSS'lerini de dinamik olarak sayfaya göm (YOKSA)
        if (!document.getElementById('arenaStyles')) {
            let style = document.createElement('style');
            style.id = 'arenaStyles';
            style.innerHTML = `
                #arenaSonucOverlay.aktif #arenaSonucModal { transform: translate(-50%, -50%) scale(1) !important; opacity: 1 !important; }
                @keyframes kritikPulse { 0% { transform: scale(1); text-shadow: 0 0 5px #facc15; } 50% { transform: scale(1.1); text-shadow: 0 0 20px #facc15, 0 0 30px #f87171; } 100% { transform: scale(1); text-shadow: 0 0 5px #facc15; } }
            `;
            document.head.appendChild(style);
        }

        // Artık HTML'in orada olduğundan %1.000 eminiz, güvenle seçebiliriz:
        const modal = document.getElementById('arenaSonucModal');
        const baslik = document.getElementById('arenaSonucBaslik');
        const mesaj = document.getElementById('arenaSonucMesaj');
        const myDamage = document.getElementById('myDamageStat');
        const enemyDamage = document.getElementById('enemyDamageStat');

        if (data.mesaj.includes('ZAFER')) { 
            baslik.innerHTML = '🏆 ZAFER! 🏆';
            baslik.style.color = '#facc15';
            modal.style.border = '2px solid #facc15';
            modal.style.boxShadow = '0 0 30px rgba(250, 204, 21, 0.5)';
        } else {
            baslik.innerHTML = '💀 BOZGUN 💀';
            baslik.style.color = '#ef4444';
            modal.style.border = '2px solid #ef4444';
            modal.style.boxShadow = '0 0 30px rgba(239, 68, 68, 0.5)';
        }

        mesaj.innerHTML = data.mesaj;

        let myCritHtml = data.saldiran_kritik ? '<b style="color:#facc15; animation: kritikPulse 1s infinite;">(KRİTİK!)</b>' : '';
        let enemyCritHtml = data.savunan_kritik ? '<b style="color:#facc15; animation: kritikPulse 1s infinite;">(KRİTİK!)</b>' : '';

        myDamage.innerHTML = `Vurduğun Hasar: <b style="color:white; font-size:14px;">${data.saldiran_hasar}</b> ${myCritHtml}`;
        enemyDamage.innerHTML = `Aldığın Hasar: <b style="color:white; font-size:14px;">${data.savunan_hasar}</b> ${enemyCritHtml}`;

        overlay.style.display = 'block';
        setTimeout(() => overlay.classList.add('aktif'), 10);
    })
    .catch(err => {
        alert("Sistemsel Hata: " + err.message);
        setTimeout(() => location.reload(), 2000);
    });
}

// Güvence: Kapatma fonksiyonunu da kutuyu silecek şekilde güncelliyoruz
window.closeArenaResult = function() {
    const overlay = document.getElementById('arenaSonucOverlay');
    if(overlay) {
        overlay.classList.remove('aktif');
        setTimeout(() => {
            overlay.remove(); // Kapanırken HTML'i de çöpe at ki arkada iz kalmasın!
            location.reload();
        }, 300);
    } else {
        location.reload();
    }
};

// Güvence: Kapatma fonksiyonunu da global yapıyoruz ki buton %100 bulabilsin
window.closeArenaResult = function() {
    const overlay = document.getElementById('arenaSonucOverlay');
    if(overlay) {
        overlay.classList.remove('aktif');
        setTimeout(() => {
            overlay.style.display = 'none';
            location.reload();
        }, 300);
    } else {
        location.reload();
    }
};

function closeArenaResult() {
    const overlay = document.getElementById('arenaSonucOverlay');
    overlay.classList.remove('aktif');
    setTimeout(() => {
        overlay.style.display = 'none';
        location.reload();
    }, 300);
}
// ====================================================================
// 6. CANLI ARENA RADARI (Öğretmenin Butonunu Anlık Takip Eder)
// ====================================================================
// [2026-08] Kendi setInterval'ı kaldırıldı; js/ogrenci_radar.js üzerindeki
// TEK birleşik radara abone olunuyor (Bilinen Sorunlar #11).
let anlikArenaDurumu = null;

if (typeof mBlockRadar !== 'undefined') {
    mBlockRadar.abone(function (veri) {
        // Öğrenci skorboardunda değilsek boşuna çalışmasın
        if (!document.getElementById('menuArena')) return;
        if (!veri.arena) return;

        const acik = veri.arena.acik;

        if (anlikArenaDurumu === null) {
            // İlk turda mevcut durumu hafızaya al (açık mı, kapalı mı?)
            anlikArenaDurumu = acik;
            return;
        }

        if (anlikArenaDurumu !== acik) {
            // DURUM DEĞİŞTİ! (Öğretmen butona bastı veya süre doldu)
            anlikArenaDurumu = acik;

            new Audio('assets/sounds/ogretmenuyari.mp3')
                .play()
                .catch(function () { /* tarayıcı sesi engelledi */ });

            if (acik) {
                alert("⚔️ MÜJDE! Öğretmeniniz Savaş Arenası'nın kapılarını açtı! Meydana bekleniyorsunuz.");
            } else {
                alert("🔒 Savaş Arenası Kapatıldı veya Süre Doldu! Tüm savaşçılar kışlalarına dönüyor...");
            }

            // Öğrenci F5'e basmadan arayüzü yenileyip kilidi açıyoruz/kapatıyoruz
            window.location.reload();
        }
    });
}