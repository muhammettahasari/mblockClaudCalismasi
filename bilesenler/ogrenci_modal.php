<?php
/**
 * @author Muhammet Taha Sarı
 * @description Öğrenci panelinde kullanılan bilgi, uyarı ve öğretmen şifre onayı ekranlarının (modalların) HTML altyapısını barındırır. Sayfayı şişirmemek için ayrı bir modül olarak tasarlanmıştır ve 'progress_manager.js' tarafından kontrol edilir.
 * @updates
 *  - [2026-08] Kilit ekranındaki ayrı setInterval kaldırıldı; artık
 *    js/ogrenci_radar.js üzerindeki TEK radara abone (Bilinen Sorunlar #11).
 */
?>

<?php if ($kilitli_mi): ?>
<div class="lock-screen" id="kilitEkrani">
    <h1>🔒 SİSTEM KİLİTLENDİ</h1>
    <p>Güvenlik ihlali tespit edildi. 3 kez hatalı veya yetkisiz şifre girişi denendiği için hesabınız güvenlik moduna alınmıştır.</p>
    <p>Lütfen öğretmeninizden kendi paneli üzerinden kilidi açmasını isteyin.</p>
</div>
<script src="js/ogrenci_radar.js"></script>
<script>
    (function () {
        let yonlendirildi = false;

        mBlockRadar.abone(function (veri) {
            if (yonlendirildi) return;

            if (veri.durum === 'onay_bekliyor') {
                document.getElementById('kilitEkrani').style.display = 'none';
            } else if (veri.durum === 'uzaktan_onaylandi') {
                yonlendirildi = true;
                mBlockRadar.durdur();
                alert("Öğretmen uzaktan onay verdi! Puanlar eklendi.");
                location.href = 'ogrenci_paneli.php?bolum=<?= (int) $aktif_bolum_id + 1 ?>';
            } else if (veri.durum === 'uzaktan_reddedildi') {
                yonlendirildi = true;
                mBlockRadar.durdur();
                alert("Öğretmen uzaktan REDDETTİ! Bölüm başa sarıyor.");
                location.href = 'ogrenci_paneli.php?bolum=<?= (int) $aktif_bolum_id ?>&gorev=<?= (int) $ilk_gorev_id ?>';
            }
        });
    })();
</script>
<?php endif; ?>

<?php if ($toplam_gorev > 0): ?>
<div class="modal-overlay" id="approvalModal">
    <div class="modal-box">
        <button class="btn-close" id="btnCloseModal">X</button>
        <div id="passwordArea">
            <h2 style="color:#1e293b; margin-top:10px;">Bölüm Sonu Onayı</h2>
            <p style="color:#64748b; font-size:15px; margin-bottom:25px;">Tüm görevleri tamamladın! Lütfen öğretmeninden onay şifresini girmesini iste.</p>
            <div style="display: flex; justify-content: center; align-items: center; gap: 10px;">
                <div style="position: relative; display: inline-block;">
                    <input type="password" id="teacherPassword" placeholder="Öğretmen Şifresi" style="padding:12px; border-radius:8px; border:2px solid #cbd5e1; outline:none; font-size:16px; width:200px; text-align:center; padding-right: 35px;">
                    <div id="hiddenTrigger" style="position: absolute; width: 35px; height: 100%; top: 0; right: 0; cursor: text; z-index: 10; background: transparent;"></div>
                </div>
                <button id="btnFakeSubmit" style="background:#4f46e5; color:white; border:none; padding:12px 20px; border-radius:8px; font-weight:bold; cursor:pointer; font-size:16px;">Sorgula</button>
            </div>
            <p id="approvalMessage" class="message" style="margin-top:20px; font-weight:bold; font-size:14px;"></p>
        </div>
        <div id="actionArea" style="display:none; text-align:center;">
            <div style="font-size: 50px; margin-bottom: 10px;">🛡️</div>
            <h2 style="color:#10b981; margin:0;" id="successTitle">Yetki Doğrulandı</h2>
            <p style="color:#475569;">Lütfen öğrencinin çözümünü değerlendirin:</p>
            <div style="display:flex; gap:10px; margin-top:15px;">
                <button id="btnTeacherApprove" style="background:#10b981; color:white; border:none; padding:12px 15px; border-radius:8px; font-weight:bold; cursor:pointer; width:100%;">✅ Onayla</button>
                <button id="btnTeacherReject" style="background:#ef4444; color:white; border:none; padding:12px 15px; border-radius:8px; font-weight:bold; cursor:pointer; width:100%;">❌ Başa Dön</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>