<?php
/* ====================================================================
 * MODÜL: Profil, Avatar ve Puan Kartları
 * DOSYA YOLU: bilesenler/skorboard_modulleri/profil_ve_puanlar.php
 * BAĞLI OLDUĞU ANA DOSYA: bilesenler/ogrenci_skorboard.php
 * AÇIKLAMA: Öğrencinin vesikalık avatarını, karşılama metnini ve 
 * Kalıcı/Bekleyen puan kutularını listeler.
 * ==================================================================== */

// Vesikalık için avatar katmanlarını çek
$vesikalik_sorgu = $pdo->prepare("
SELECT 
        k.resim_yolu as karakter_resim,
        y.resim_yolu as yuz_resim,
        s.resim_yolu as sac_resim,
        kf.resim_yolu as kafa_resim,
        z.resim_yolu as zirh_resim
    FROM ogrenci_avatar_durum oad
    LEFT JOIN esya_kutuphanesi k ON oad.karakter_id = k.id
    LEFT JOIN esya_kutuphanesi y ON oad.yuz_id = y.id
    LEFT JOIN esya_kutuphanesi s ON oad.sac_id = s.id
    LEFT JOIN esya_kutuphanesi kf ON oad.kafa_id = kf.id
    LEFT JOIN esya_kutuphanesi z ON oad.zirh_id = z.id
    WHERE oad.ogrenci_id = ?
");
$vesikalik_sorgu->execute([$aktif_ogrenci_id]);
$vesikalik = $vesikalik_sorgu->fetch(PDO::FETCH_ASSOC);

/* [2026-08] CEZA DURUMU — öğretmen bir bölümü tekrar ettirdiyse avatar kızarır.
   Ayrı bir "cezali" sütunu yok; bilgi doğrudan ilerleme tablosundan türetilir,
   öğrenci o görevleri bitirince efekt kendiliğinden kalkar. */
require_once __DIR__ . '/../ilerleme_yardimcilari.php';
$ceza_durumu = ogrencininCezasi($pdo, (int) $aktif_ogrenci_id);
?>

<?php if ($ceza_durumu['cezali']): ?>
<style>
    /* Kırmızı ceza efekti — öğrenci sıfırlanan görevleri bitirince kalkar */
    .profile-circle.ceza-efekti::after{
        content:''; position:absolute; inset:0; border-radius:50%;
        background:radial-gradient(circle at 50% 40%, rgba(239,68,68,.5), rgba(185,28,28,.8));
        mix-blend-mode:multiply; pointer-events:none; z-index:9;
        animation:cezaSis 2.4s ease-in-out infinite;
    }
    .profile-circle.ceza-efekti img{ filter:saturate(.5) contrast(1.05); }
    @keyframes cezaSis{ 0%,100%{opacity:.55} 50%{opacity:.9} }

    .ceza-sarmal{ position:relative; display:inline-block; }
    .ceza-rozet{
        position:absolute; top:2px; right:2px; z-index:20;
        background:#ef4444; color:#fff; font-size:11px; font-weight:800;
        min-width:22px; height:22px; padding:0 6px; border-radius:99px;
        border:2px solid #0f172a; display:flex; align-items:center; justify-content:center;
        box-shadow:0 2px 8px rgba(239,68,68,.6);
    }
    .ceza-notu{
        background:rgba(239,68,68,.15); border:1px solid rgba(239,68,68,.45);
        color:#fca5a5; border-radius:9px; padding:9px 12px;
        font-size:12px; line-height:1.5; margin:0 0 16px; text-align:left;
    }
    .ceza-notu b{ color:#fecaca; }
</style>
<?php endif; ?>

<a href="profil.php" style="text-decoration: none; display: block; text-align: center; margin-bottom: 20px; transition: 0.3s; cursor: pointer;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
    <div class="profile-circle<?= $ceza_durumu['cezali'] ? ' ceza-efekti' : '' ?>" style="width: 100px; height: 100px; border-radius: 50%; border: 4px solid <?= $ceza_durumu['cezali'] ? '#ef4444' : '#3b82f6' ?>; margin: 0 auto 10px auto; background-color: #0f172a; display: flex; justify-content: center; align-items: center; overflow: hidden; position: relative;">
        <?php if ($vesikalik && $vesikalik['karakter_resim']): ?>
            <div style="position: absolute; width: 350px; height: 350px; left: 50%; top: 50%; transform: translate(-50%, -35%); pointer-events: none;">
                <img src="<?= htmlspecialchars($vesikalik['karakter_resim']) ?>" style="position: absolute; top:0; left:0; width: 100%; height: 100%; object-fit: contain; z-index: 1;">
                <?php if ($vesikalik['zirh_resim']): ?>
                    <img src="<?= htmlspecialchars($vesikalik['zirh_resim']) ?>" style="position: absolute; top:0; left:0; width: 100%; height: 100%; object-fit: contain; z-index: 2;">
                <?php endif; ?>
                <?php if ($vesikalik['yuz_resim']): ?>
                    <img src="<?= htmlspecialchars($vesikalik['yuz_resim']) ?>" style="position: absolute; top:2px; left:-105px; width: 160%; height: 100%; object-fit: contain; z-index: 3;">
                <?php endif; ?>
                <?php if ($vesikalik['sac_resim']): ?>
                    <img src="<?= htmlspecialchars($vesikalik['sac_resim']) ?>" style="position: absolute; top:0; left:0; width: 100%; height: 100%; object-fit: contain; z-index: 4;">
                <?php endif; ?>
                <?php if ($vesikalik['kafa_resim']): ?>
                    <img src="<?= htmlspecialchars($vesikalik['kafa_resim']) ?>" style="position: absolute; top:37px; left:-105px; width: 160%; 100%; object-fit: contain; z-index: 5;">
                <?php endif; ?>
            </div>
        <?php else: ?>
            <span style="font-size: 40px; font-weight: bold; color: white;"><?= mb_substr($aktif_ogrenci_ad, 0, 1) ?></span>
        <?php endif; ?>

        <?php if ($ceza_durumu['cezali']): ?>
            <span class="ceza-rozet" title="Tekrar yapman gereken görev sayısı"><?= (int) $ceza_durumu['gorev_sayisi'] ?></span>
        <?php endif; ?>
    </div>
    <h2 class="welcome-text" style="font-size: 20px; margin: 0; color: white;">Merhaba, <?= htmlspecialchars($aktif_ogrenci_ad) ?>!</h2>
    <p style="margin: 5px 0 0 0; font-size: 12px; color: #94a3b8;">Avatarını Düzenle 👤</p>
</a>

<?php if ($ceza_durumu['cezali']): ?>
<div class="ceza-notu">
    🔴 <b>Öğretmenin <?= (int) $ceza_durumu['gorev_sayisi'] ?> görevi tekrar yapmanı istedi.</b><br>
    <?php if (!empty($ceza_durumu['bolumler'])): ?>
        Bölüm: <?= htmlspecialchars($ceza_durumu['bolumler']) ?><br>
    <?php endif; ?>
    <?php if (!empty($ceza_durumu['sebep'])): ?>
        “<?= htmlspecialchars($ceza_durumu['sebep']) ?>”<br>
    <?php endif; ?>
    Bu görevleri çözünce kesilen elmasların geri gelecek ve kırmızı efekt kalkacak.
</div>
<?php endif; ?>

<a href="market.php" style="display: block; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: white; text-align: center; padding: 15px; border-radius: 12px; text-decoration: none; font-weight: bold; font-size: 16px; margin-bottom: 15px; box-shadow: 0 4px 10px rgba(99, 102, 241, 0.4); transition: 0.3s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
    🛒 Silah ve Büyü Marketi
</a>

<div class="score-cards" style="display: flex; gap: 15px; margin-bottom: 40px;">
    <div class="score-card green" style="flex: 1; background: #10b981; padding: 15px; border-radius: 12px; text-align: center;">
        <div class="score-title" style="font-size: 11px; font-weight: bold; margin-bottom: 5px; color:#ecfdf5 !important;">KALICI PUAN</div>
        <div id="valPermanent" class="score-value" style="font-size: 28px; font-weight: 900; color: white;"><?= $gercek_kalici_puan ?></div>
    </div>
    <div class="score-card orange" style="flex: 1; background: #f59e0b; padding: 15px; border-radius: 12px; text-align: center;">
        <div class="score-title" style="font-size: 11px; font-weight: bold; margin-bottom: 5px; color:#fffbeb !important;">BEKLEYEN PUAN</div>
        <div id="valPending" class="score-value" style="font-size: 28px; font-weight: 900; color: white;"><?= $bekleyen_puan ?></div>
    </div>
</div>