<?php
/* ====================================================================
 * MODÜL: Bireysel Lig (Sınıf, Okul, Türkiye Sıralaması)
 * DOSYA YOLU: bilesenler/skorboard_modulleri/bireysel_lig.php
 * BAĞLI OLDUĞU ANA DOSYA: bilesenler/ogrenci_skorboard.php
 * AÇIKLAMA: Öğrencilerin sadece puanlarına göre sıralandığı liste.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Düello şalteri artık ÖĞRETMENE ÖZEL (Bilinen Sorunlar #7).
 *      Eskiden duello_ayarlari (id=1) tek satır global şalterdi; bir öğretmen
 *      düelloyu açınca bütün okullarda açılıyordu.
 *  - [2026-08] Düello butonu sadece SINIF sekmesinde gösteriliyor; okul ve
 *      Türkiye sekmelerinde meydan okuma yapılamaz (sınıf içi kuralı).
 * ==================================================================== */

// DÜELLO ŞALTERİ: Öğrencinin kendi öğretmeninin penceresi açık mı?
$duello_sorgu = $pdo->prepare(
    "SELECT ogr.duello_bitis
       FROM ogrenciler o
       JOIN ogretmenler ogr ON ogr.id = o.ogretmen_id
      WHERE o.id = ?"
);
$duello_sorgu->execute([$aktif_ogrenci_id]);
$duello_bitis = $duello_sorgu->fetchColumn();

$arena_acik_mi = ($duello_bitis && strtotime($duello_bitis) > time());
?>
<div id="menuBireysel" style="display: block;">
    
    <div style="display: flex; background: rgba(0,0,0,0.2);">
        <button onclick="sekmeDegistir('tabSinif', this, 'bireysel-sekme-btn')" class="bireysel-sekme-btn aktif-sekme" style="flex: 1; padding: 12px 5px; background: #3b82f6; color: white; border: none; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 13px;">Sınıf</button>
        <button onclick="sekmeDegistir('tabOkul', this, 'bireysel-sekme-btn')" class="bireysel-sekme-btn" style="flex: 1; padding: 12px 5px; background: transparent; color: #cbd5e1; border: none; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 13px;">Okul</button>
        <button onclick="sekmeDegistir('tabTurkiye', this, 'bireysel-sekme-btn')" class="bireysel-sekme-btn" style="flex: 1; padding: 12px 5px; background: transparent; color: #cbd5e1; border: none; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 13px;">Türkiye</button>
    </div>

    <div style="padding: 15px; max-height: 350px; overflow-y: auto; overflow-x: hidden;">
        
        <div id="tabSinif" class="sampiyona-tablosu" style="display: block;">
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php $sira = 1;
                foreach ($liste_sinif as $lider):
                    $benim_mi = ($lider['id'] == $aktif_ogrenci_id);
                    $bg_renk = $benim_mi ? 'background: #10b981; border-radius: 6px; padding-left: 10px; padding-right: 10px; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);' : '';
                    $metin_renk = $benim_mi ? 'color: white !important;' : 'color: #cbd5e1 !important;';
                ?>
                    <li style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); <?= $bg_renk ?>">
                        <span style="width: 25px; font-weight: bold; <?= $metin_renk ?>"><?= $sira ?></span>
                        <span style="flex: 1; font-weight: <?= $benim_mi ? 'bold' : 'normal' ?>; <?= $metin_renk ?>"><?= htmlspecialchars($lider['ad_soyad']) ?></span>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-weight: bold; <?= $metin_renk ?>"><?= $lider['kalici_puan'] ?> Puan</span>
                             
                         <?php if (!$benim_mi): ?>
                                <button class="btn-duello-aktif" onclick="meydanOku(<?= $lider['id'] ?>, '<?= addslashes($lider['ad_soyad']) ?>')" 
                                        style="background: #ef4444; color: white; border: none; padding: 4px 8px; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 11px; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.4); transition: 0.2s; <?= $arena_acik_mi ? 'display:inline-block;' : 'display:none;' ?>"
                                        onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                    ⚔️ Düello
                                </button>
                                
                                <button class="btn-duello-pasif" disabled 
                                        style="background: #475569; color: #94a3b8; border: none; padding: 4px 8px; border-radius: 5px; cursor: not-allowed; font-weight: bold; font-size: 11px; <?= $arena_acik_mi ? 'display:none;' : 'display:inline-block;' ?>"
                                        title="Düello şu an kapalı!">
                                    🔒 Düello Kapalı
                                </button>
                            <?php endif; ?>
                             
                        </div>
                    </li>
                <?php $sira++;
                endforeach; ?>
            </ul>
        </div>

        <div id="tabOkul" class="sampiyona-tablosu" style="display: none;">
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php $sira = 1;
                foreach ($liste_okul as $lider):
                    $benim_mi = ($lider['id'] == $aktif_ogrenci_id);
                    $bg_renk = $benim_mi ? 'background: #10b981; border-radius: 6px; padding-left: 10px; padding-right: 10px; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);' : '';
                    $metin_renk = $benim_mi ? 'color: white !important;' : 'color: #cbd5e1 !important;';
                ?>
                    <li style="display: flex; flex-direction: column; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); <?= $bg_renk ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                            <span style="font-weight: <?= $benim_mi ? 'bold' : 'normal' ?>; <?= $metin_renk ?>"><span style="font-weight: bold; margin-right: 5px;"><?= $sira ?>.</span> <?= htmlspecialchars($lider['ad_soyad']) ?></span>
                            <span style="font-weight: bold; <?= $metin_renk ?>"><?= $lider['kalici_puan'] ?> Puan</span>
                        </div>
                        <div style="font-size: 11px; margin-left: 20px; color: rgba(255,255,255,0.5); <?= $benim_mi ? 'color: rgba(255,255,255,0.8);' : '' ?>">Sınıf: <?= htmlspecialchars($lider['sinif_adi'] ?? 'Bilinmiyor') ?></div>
                    </li>
                <?php $sira++;
                endforeach; ?>
            </ul>
        </div>

        <div id="tabTurkiye" class="sampiyona-tablosu" style="display: none;">
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php $sira = 1;
                foreach ($liste_tr as $lider):
                    $benim_mi = ($lider['id'] == $aktif_ogrenci_id);
                    $bg_renk = $benim_mi ? 'background: #10b981; border-radius: 6px; padding-left: 10px; padding-right: 10px; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);' : '';
                    $metin_renk = $benim_mi ? 'color: white !important;' : 'color: #cbd5e1 !important;';
                ?>
                    <li style="display: flex; flex-direction: column; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); <?= $bg_renk ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                            <span style="font-weight: <?= $benim_mi ? 'bold' : 'normal' ?>; <?= $metin_renk ?>"><span style="font-weight: bold; margin-right: 5px;"><?= $sira ?>.</span> <?= htmlspecialchars($lider['ad_soyad']) ?></span>
                            <span style="font-weight: bold; <?= $metin_renk ?>"><?= $lider['kalici_puan'] ?> Puan</span>
                        </div>
                        <div style="font-size: 11px; margin-left: 20px; color: rgba(255,255,255,0.5); <?= $benim_mi ? 'color: rgba(255,255,255,0.8);' : '' ?>">Okul: <?= htmlspecialchars($lider['okul_adi'] ?? 'Bilinmiyor') ?></div>
                    </li>
                <?php $sira++;
                endforeach; ?>
            </ul>
        </div>

    </div>
</div>