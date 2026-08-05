<main class="main-content">
    <?php if ($toplam_gorev > 0): ?>
        <div style="background: #1e293b; color: white; padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <strong style="font-size: 18px;">🏫 <?= htmlspecialchars($okul_adi) ?></strong>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 16px; background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 8px;">
                    🎓 <?= htmlspecialchars($sinif_adi) ?>
                </span>
                <a href="api/logout.php" style="background: #ef4444; color: white; padding: 6px 15px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; transition: 0.2s; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.4); display: flex; align-items: center;" onmouseover="this.style.background='#dc2626'; this.style.transform='scale(1.05)'" onmouseout="this.style.background='#ef4444'; this.style.transform='scale(1)'">
                    🚪 Çıkış
                </a>
            </div>
        </div>

        <div class="task-header" style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
            <div>
                <span style="color: #64748b; font-weight: bold; font-size: 14px;">Bölüm <?= $aktif_bolum_id ?></span>
                <h1 id="taskTitle" class="task-title" style="margin: 5px 0 0 0; color: #1e293b; font-size: 24px;">📝 <?= htmlspecialchars($g_adi) ?></h1>
            </div>
            <div style="text-align: right;">
                <span id="taskPoints" class="task-points" style="background:#f59e0b; color:white; padding:8px 15px; border-radius:8px; font-weight:bold; font-size: 16px;">🏆 Görev Ödülü: <span id="puanDegeri"><?= $g_puan ?></span> Puan</span>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; flex-wrap: wrap; gap: 15px;">

            <div id="progressDotsContainer" style="display: flex; align-items: center; overflow-x: auto; padding-bottom: 5px;">
                <?php foreach ($gorevler as $index => $g): 
                    if ($index < $aktif_index) {
                        $renk = "#10b981"; $metin = "✓"; 
                        $imlec = "pointer";
                        $hover_efekti = "onmouseover=\"this.style.transform='scale(1.15)'\" onmouseout=\"this.style.transform='scale(1)'\"";
                    } elseif ($index == $aktif_index) {
                        $renk = "#f59e0b"; $metin = $index + 1; 
                        $imlec = "pointer"; // YENİ: Aktif turuncu görev de tıklanabilir oldu!
                        $hover_efekti = "onmouseover=\"this.style.transform='scale(1.15)'\" onmouseout=\"this.style.transform='scale(1)'\"";
                    } else {
                        $renk = "#cbd5e1"; $metin = $index + 1; 
                        $imlec = "not-allowed";
                        $hover_efekti = "";
                    }
                ?>
                    
                    <div class="progress-dot" id="dot_<?= $g['id'] ?>" <?= $hover_efekti ?> style="width: 35px; height: 35px; border-radius: 50%; background-color: <?= $renk ?>; display: flex; justify-content: center; align-items: center; color: white; font-weight: bold; font-size: 16px; z-index: 2; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: <?= $imlec ?>; transition: 0.2s; flex-shrink: 0;">
                        <?= $metin ?>
                    </div>

                    <?php if ($index < $toplam_gorev - 1): ?>
                        <div class="progress-line" id="line_<?= $g['id'] ?>" style="height: 4px; width: 40px; background-color: <?= ($index < $aktif_index) ? '#10b981' : '#cbd5e1' ?>; margin: 0 -5px; z-index: 1; flex-shrink: 0;"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div id="actionButtonsContainer" style="display: flex; gap: 10px;">
                <button id="btnNextTask" data-current-id="<?= $g_id ?>" data-next-id="<?= $sonraki_gorev_id ?>" data-puan="<?= $g_puan ?>" style="display: <?= !$son_gorev_mi ? 'inline-block' : 'none' ?>; background: #4f46e5; color: white; padding: 12px 25px; border-radius: 8px; border: none; font-weight: bold; font-size: 16px; cursor: pointer; box-shadow: 0 4px 6px rgba(79, 70, 229, 0.2);">Sıradaki Görev ➡️</button>
                <button id="btnComplete" data-puan="<?= $g_puan ?>" style="display: <?= $son_gorev_mi ? 'inline-block' : 'none' ?>; background: #10b981; color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);">Bölümü Tamamla 🏆</button>
            </div>
        </div>

        <div id="taskContent" style="margin-top:20px; display: flex; flex-direction: column; gap: 15px; flex-grow: 1;">
            <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <h3 style="margin-top: 0; color: #4f46e5; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">🛠️ Nasıl Yaparım?</h3>
                <div id="alanNasil" style="font-size:16px; line-height:1.7; color:#334155;">
                    <?= $g_nasil ?>
                </div>
            </div>

            <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; border-left: 5px solid #10b981;">
                <h3 style="margin-top: 0; color: #10b981; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">🎯 Görev</h3>
                <div id="alanGorev" style="font-size:16px; font-weight: 500; color:#1e293b;">
                    <?= $g_metin ?>
                </div>
            </div>

            <div style="display: flex; gap: 15px;">
                <div style="flex: 1; background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center; overflow: hidden;">
                    <h3 style="margin-top: 0; color: #64748b; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">📺 İstenen Ekran Görüntüsü</h3>
                    <div id="alanEkran">
                        <?= $g_ekran ?>
                    </div>
                </div>

                <div id="bilgiAlKutusu" style="flex: 1; background: #fffbeb; padding: 20px; border-radius: 12px; border: 1px solid #fde68a; text-align: center; position: relative; overflow: hidden;">
                    <h3 style="margin-top: 0; color: #d97706; border-bottom: 2px solid #fef3c7; padding-bottom: 10px;">💡 Bilgi Al</h3>
                    <div id="ipucuKapaliArayuz" style="padding: 20px;">
                        <button id="btnHint" style="background: none; border: none; font-size: 60px; cursor: pointer; transition: 0.3s; padding: 0;">💡</button>
                        <p style="color: #b45309; font-weight: bold; margin-top: 10px; font-size: 14px;">Yardım almak puanının yarısını eksiltir!</p>
                    </div>
                    <div id="ipucuAcikArayuz" style="display: none; text-align: left; color: #92400e; font-size: 15px; margin-top: 15px;">
                        <?= $g_ipucu ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div style="padding: 50px; text-align: center; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
            <h2 style="color: #475569;">Henüz sisteme bir görev eklenmemiş.</h2>
            <p style="color: #64748b;">Lütfen öğretmeninin yeni görevler tanımlamasını bekle.</p>
        </div>
    <?php endif; ?>
</main>