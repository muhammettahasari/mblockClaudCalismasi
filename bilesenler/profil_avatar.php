<style>
  .avatar-canvas {
        width: 1200px;
        height: 600px;
        position: relative;
        margin: 0 auto;
        overflow: hidden; /* YENİ: Büyüyen resimlerin scroll çubuğunu tetiklemesini kesin olarak engeller */
    }

    /* X (Çıkart) Butonlarını havada tutacak görünmez koordinatlar */
    #slot_kafa { top: 50px; left: 50%; transform: translateX(-50%); }
    #slot_yuz { top: 10px; left: 50%; transform: translateX(-50%); }
    #slot_zirh { top: 140px; left: 50%; transform: translateX(-50%); }
    #slot_karakter { top: 200px; right: 350px; }
    #slot_sag_el { top: 350px; left: 400px; }
    #slot_sol_el { top: 350px; right: 400px; }
    #slot_ayakkabi { bottom: 20px; left: 50%; transform: translateX(-50%); }
    #slot_binek { bottom: 150px; left: 150px; }
    #slot_evcil_hayvan { bottom: 100px; right: 150px; }

    #layer_ayakkabi { z-index: 35; }
    .siluet-katmani { z-index: 80; } /* Silüetler en üstte parlasın */
</style>

<div class="game-area" style="overflow-x: auto;" ondragover="allowDrop(event)" ondrop="drop(event)">
    <div class="avatar-canvas">

        <img id="siluet_karakter" class="layer siluet-katmani" src="img/siluetler/siluet_beden.png" style="display:none;">
        <img id="siluet_binek" class="layer siluet-katmani" src="img/siluetler/siluet_binek.png" style="display:none;">
        <img id="siluet_evcil_hayvan" class="layer siluet-katmani" src="img/siluetler/siluet_evcil.png" style="display:none;">
        <img id="siluet_sac" class="layer siluet-katmani" src="img/siluetler/siluet_sac.png" style="display:none;">
        <img id="siluet_ayakkabi" class="layer siluet-katmani" src="img/siluetler/siluet_ayakkabi.png" style="display:none;">
        <img id="siluet_sol_el" class="layer siluet-katmani" src="img/siluetler/siluet_sol_el.png" style="display:none;">
        <img id="siluet_sag_el" class="layer siluet-katmani" src="img/siluetler/siluet_sag_el.png" style="display:none;">
        <img id="siluet_kafa" class="layer siluet-katmani" src="img/siluetler/siluet_kafa.png" style="display:none;">
        <img id="siluet_yuz" class="layer siluet-katmani" src="img/siluetler/siluet_yuz.png" style="display:none;">
        <img id="siluet_zirh" class="layer siluet-katmani" src="img/siluetler/siluet_zirh.png" style="display:none;">

        <img id="layer_karakter" class="layer" style="z-index: 20;" src="<?= !empty($giyili_resimler['karakter']) ? $giyili_resimler['karakter'] : 'img/base_karakter.png' ?>" onerror="this.src='https://cdn-icons-png.flaticon.com/512/847/847969.png'">
        <img id="layer_binek" class="layer" src="<?= $giyili_resimler['binek'] ?>" <?= empty($giyili_resimler['binek']) ? 'style="display:none;"' : '' ?>>
        <img id="layer_evcil_hayvan" class="layer" src="<?= $giyili_resimler['evcil_hayvan'] ?>" <?= empty($giyili_resimler['evcil_hayvan']) ? 'style="display:none;"' : '' ?>>
        <img id="layer_sac" class="layer" src="<?= $giyili_resimler['sac'] ?>" <?= empty($giyili_resimler['sac']) ? 'style="display:none;"' : '' ?>>
        <img id="layer_ayakkabi" class="layer" src="<?= $giyili_resimler['ayakkabi'] ?>" <?= empty($giyili_resimler['ayakkabi']) ? 'style="display:none;"' : '' ?>>
        <img id="layer_sol_el" class="layer" src="<?= $giyili_resimler['sol_el'] ?>" <?= empty($giyili_resimler['sol_el']) ? 'style="display:none;"' : '' ?>>
        <img id="layer_sag_el" class="layer" src="<?= $giyili_resimler['sag_el'] ?>" <?= empty($giyili_resimler['sag_el']) ? 'style="display:none;"' : '' ?>>
        <img id="layer_kafa" class="layer" src="<?= $giyili_resimler['kafa'] ?>" <?= empty($giyili_resimler['kafa']) ? 'style="display:none;"' : '' ?>>
        <img id="layer_yuz" class="layer" style="z-index: 22;" src="<?= $giyili_resimler['yuz'] ?>" <?= empty($giyili_resimler['yuz']) ? 'style="display:none;"' : '' ?>>
        <img id="layer_zirh" class="layer" style="z-index: 25;" src="<?= $giyili_resimler['zirh'] ?>" <?= empty($giyili_resimler['zirh']) ? 'style="display:none;"' : '' ?>>

        <div class="slot" id="slot_karakter"><?php if ($giyili_resimler['karakter']): ?><button class="btn-cikart" onclick="esyaCikart('karakter')">X</button><?php endif; ?></div>
        <div class="slot" id="slot_kafa"><?php if ($giyili_resimler['kafa']): ?><button class="btn-cikart" onclick="esyaCikart('kafa')">X</button><?php endif; ?></div>
        <div class="slot" id="slot_sag_el"><?php if ($giyili_resimler['sag_el']): ?><button class="btn-cikart" onclick="esyaCikart('sag_el')">X</button><?php endif; ?></div>
        <div class="slot" id="slot_sol_el"><?php if ($giyili_resimler['sol_el']): ?><button class="btn-cikart" onclick="esyaCikart('sol_el')">X</button><?php endif; ?></div>
        <div class="slot" id="slot_ayakkabi"><?php if ($giyili_resimler['ayakkabi']): ?><button class="btn-cikart" onclick="esyaCikart('ayakkabi')">X</button><?php endif; ?></div>
        <div class="slot" id="slot_binek"><?php if ($giyili_resimler['binek']): ?><button class="btn-cikart" onclick="esyaCikart('binek')">X</button><?php endif; ?></div>
        <div class="slot" id="slot_evcil_hayvan"><?php if ($giyili_resimler['evcil_hayvan']): ?><button class="btn-cikart" onclick="esyaCikart('evcil_hayvan')">X</button><?php endif; ?></div>
        
        <div class="slot" id="slot_yuz" data-kategori="yuz" data-label="Yüz İfadesi" ondragover="allowDrop(event)" ondrop="drop(event)">
            <?php if ($giyili_resimler['yuz']): ?><button class="btn-cikart" onclick="esyaCikart('yuz')">X</button><?php endif; ?>
        </div>
        <div class="slot" id="slot_zirh" data-kategori="zirh" data-label="Göğüs Zırhı" ondragover="allowDrop(event)" ondrop="drop(event)">
            <?php if ($giyili_resimler['zirh']): ?><button class="btn-cikart" onclick="esyaCikart('zirh')">X</button><?php endif; ?>
        </div>

    </div>
</div>