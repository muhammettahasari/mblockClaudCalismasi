<?php
/* ====================================================================
 * MODÜL: Skorboard Ana İskeleti (Container)
 * DOSYA YOLU: bilesenler/ogrenci_skorboard.php
 * AÇIKLAMA: Skorboard'u oluşturan tüm alt modülleri tek bir çatı altında toplar.
 * ==================================================================== */
?>

<aside class="sidebar">

    <?php require_once 'skorboard_modulleri/profil_ve_puanlar.php'; ?>
    <?php
    // Öğrencinin öğretmeninin arena durumunu çek
    $arena_sorgu = $pdo->prepare("SELECT ogr.arena_bitis FROM ogretmenler ogr JOIN ogrenciler o ON o.ogretmen_id = ogr.id WHERE o.id = ?");
    $arena_sorgu->execute([$aktif_ogrenci_id]);
    $arena_bitis = $arena_sorgu->fetchColumn();
    $arena_acik_mi = ($arena_bitis && strtotime($arena_bitis) > time());
    $arena_icon = $arena_acik_mi ? '⚔️' : '🔒';
    ?>

    <div style="display: flex; gap: 5px; margin-bottom: 10px; padding: 0 5px;">
        <button onclick="anaMenuDegistir('menuBireysel', this)" class="ana-btn aktif" style="flex: 1; padding: 10px; background: #3b82f6; color: white; border: none; border-radius: 8px 8px 0 0; font-weight: bold; cursor: pointer; transition: 0.3s;">🏆 Bireysel Lig</button>
        <button onclick="anaMenuDegistir('menuCanavar', this)" class="ana-btn" style="flex: 1; padding: 10px; background: rgba(0,0,0,0.3); color: #cbd5e1; border: none; border-radius: 8px 8px 0 0; font-weight: bold; cursor: pointer; transition: 0.3s; box-shadow: inset 0 -3px 5px rgba(0,0,0,0.2);">🐉 Ayın Canavarı</button>
        <button onclick="anaMenuDegistir('menuArena', this)" class="ana-btn" style="flex: 1; padding: 10px; background: rgba(0,0,0,0.3); color: #cbd5e1; border: none; border-radius: 8px 8px 0 0; font-weight: bold; cursor: pointer; transition: 0.3s; box-shadow: inset 0 -3px 5px rgba(0,0,0,0.2);"><?= $arena_icon ?> Savaş Arenası</button>
        <button onclick="anaMenuDegistir('menuGecmis', this)" class="ana-btn" style="flex: 1; padding: 10px; background: rgba(0,0,0,0.3); color: #cbd5e1; border: none; border-radius: 8px 8px 0 0; font-weight: bold; cursor: pointer; transition: 0.3s; box-shadow: inset 0 -3px 5px rgba(0,0,0,0.2);">📜 Savaş Geçmişi</button>
    </div>

    <div class="leaderboard-widget" style="background: rgba(255,255,255,0.05); border-radius: 0 0 12px 12px; overflow: hidden; margin-top: -10px;">

        <?php require_once 'skorboard_modulleri/bireysel_lig.php'; ?>

        <?php require_once 'skorboard_modulleri/ayin_canavari.php'; ?>

        <?php require_once 'skorboard_modulleri/sinif_arenasi.php'; ?>
        <?php require_once 'skorboard_modulleri/sinif_arenasi.php'; ?>

        <?php require_once 'skorboard_modulleri/savas_gecmisi.php'; ?>
    </div>

    <script>
        const currentOgrenciId = <?= $aktif_ogrenci_id ?? 0 ?>;
    </script>

    <!-- [2026-08] Radar önce yüklenmeli; skorboard_motoru ona abone oluyor (#11) -->
    <script src="<?= varlik('js/ogrenci_radar.js', '') ?>"></script>
    <script src="<?= varlik('js/skorboard_motoru.js', '') ?>"></script>

</aside>