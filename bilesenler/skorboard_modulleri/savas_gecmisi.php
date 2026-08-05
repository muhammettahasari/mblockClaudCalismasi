<?php
/* ====================================================================
 * MODÜL: Savaş Geçmişi ve İstihbarat
 * DOSYA YOLU: bilesenler/skorboard_modulleri/savas_gecmisi.php
 * BAĞLI OLDUĞU ANA DOSYA: bilesenler/ogrenci_skorboard.php
 * AÇIKLAMA: Öğrencinin son 50 savaşını ve istatistiklerini gösterir.
 * ==================================================================== */

// 1. KANLI BIÇAKLI (Bana en çok saldıran kim?)
$kanli_bicakli_sorgu = $pdo->prepare("SELECT o.ad_soyad, COUNT(*) as saldiri_sayisi FROM arena_savaslari a JOIN ogrenciler o ON a.saldiran_id = o.id WHERE a.savunan_id = ? GROUP BY a.saldiran_id ORDER BY saldiri_sayisi DESC LIMIT 1");
$kanli_bicakli_sorgu->execute([$aktif_ogrenci_id]);
$kanli_bicakli = $kanli_bicakli_sorgu->fetch(PDO::FETCH_ASSOC);

// 2. ÖLÜMCÜL DARBE (Bana tek seferde en yüksek hasarı kim vurdu?)
$olumcul_sorgu = $pdo->prepare("SELECT o.ad_soyad, a.savunan_hasar as alinan_hasar FROM arena_savaslari a JOIN ogrenciler o ON a.saldiran_id = o.id WHERE a.savunan_id = ? ORDER BY a.savunan_hasar DESC LIMIT 1");
$olumcul_sorgu->execute([$aktif_ogrenci_id]);
$olumcul_darbe = $olumcul_sorgu->fetch(PDO::FETCH_ASSOC);

// 3. SON 50 SAVAŞIN DETAYLI LİSTESİ VE SİCİL HESAPLAMASI
$gecmis_sorgu = $pdo->prepare("
    SELECT a.*, 
           saldiran.ad_soyad as saldiran_ad, 
           savunan.ad_soyad as savunan_ad 
    FROM arena_savaslari a 
    JOIN ogrenciler saldiran ON a.saldiran_id = saldiran.id 
    JOIN ogrenciler savunan ON a.savunan_id = savunan.id 
    WHERE a.saldiran_id = ? OR a.savunan_id = ? 
    ORDER BY a.tarih DESC LIMIT 50
");
$gecmis_sorgu->execute([$aktif_ogrenci_id, $aktif_ogrenci_id]);
$savaslar = $gecmis_sorgu->fetchAll(PDO::FETCH_ASSOC);

$zafer_sayisi = 0;
$bozgun_sayisi = 0;

foreach ($savaslar as $s) {
    if ($s['kazanan_id'] == $aktif_ogrenci_id) {
        $zafer_sayisi++;
    } else {
        $bozgun_sayisi++;
    }
}
?>

<div id="menuGecmis" style="display: none; padding: 15px; max-height: 400px; overflow-y: auto;">
    
    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
        <div style="flex: 1; background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 8px; padding: 10px; text-align: center;">
            <div style="font-size: 20px; margin-bottom: 5px;">🎯</div>
            <div style="font-size: 10px; color: #fca5a5; font-weight: bold;">KANLI BIÇAKLI</div>
            <div style="font-size: 12px; color: white; font-weight: bold; margin-top: 5px;">
                <?= $kanli_bicakli ? htmlspecialchars($kanli_bicakli['ad_soyad']) . "<br><span style='color:#ef4444'> " . $kanli_bicakli['saldiri_sayisi'] . " Saldırı</span>" : "Henüz düşmanın yok." ?>
            </div>
        </div>
        
        <div style="flex: 1; background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b; border-radius: 8px; padding: 10px; text-align: center;">
            <div style="font-size: 20px; margin-bottom: 5px;">💀</div>
            <div style="font-size: 10px; color: #fcd34d; font-weight: bold;">ÖLÜMCÜL DARBE</div>
            <div style="font-size: 12px; color: white; font-weight: bold; margin-top: 5px;">
                <?= $olumcul_darbe ? htmlspecialchars($olumcul_darbe['ad_soyad']) . "<br><span style='color:#f59e0b'> -" . $olumcul_darbe['alinan_hasar'] . " Hasar </span>" : "Hiç ağır yara almadın." ?>
            </div>
        </div>

        <div style="flex: 1; background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 10px; text-align: center;">
            <div style="font-size: 20px; margin-bottom: 5px;">📜</div>
            <div style="font-size: 10px; color: #6ee7b7; font-weight: bold;">SAVAŞ SİCİLİ</div>
            <div style="font-size: 12px; font-weight: bold; margin-top: 5px;">
                <span style="color: #10b981;"><?= $zafer_sayisi ?> Zafer</span><br>
                <span style="color: #ef4444;"><?= $bozgun_sayisi ?> Bozgun</span>
            </div>
        </div>
    </div>

    <h3 style="color: #94a3b8; font-size: 12px; border-bottom: 1px solid #334155; padding-bottom: 5px; margin-bottom: 10px;">SON 50 SAVAŞ KAYDI (1 Aylık)</h3>
    <div style="display: flex; flex-direction: column; gap: 8px;">
        <?php if (empty($savaslar)): ?>
            <div style="text-align: center; color: #64748b; font-size: 12px; padding: 20px;">Savaş meydanında henüz hiç kan dökmedin.</div>
        <?php else: ?>
            <?php foreach ($savaslar as $s): 
                $ben_saldirdim = ($s['saldiran_id'] == $aktif_ogrenci_id);
                $ben_kazandim = ($s['kazanan_id'] == $aktif_ogrenci_id);
                
                $tarih_formatli = date('d.m.Y H:i', strtotime($s['tarih']));
                
                // Durum İkonları ve Renkler
                if ($ben_saldirdim) {
                    $eylem_ikon = "⚔️";
                    $eylem_metin = "Saldırdın:";
                    $rakip_ad = $s['savunan_ad'];
                    $hasar_metni = "Vurduğun: " . $s['saldiran_hasar'] . " | Aldığın: " . $s['savunan_hasar'];
                } else {
                    $eylem_ikon = "🛡️";
                    $eylem_metin = "Savundun:";
                    $rakip_ad = $s['saldiran_ad'];
                    $hasar_metni = "Aldığın: " . $s['saldiran_hasar'] . " | Savuşturduğun: " . $s['savunan_hasar'];
                }

                $arkaplan = $ben_kazandim ? "rgba(16, 185, 129, 0.05)" : "rgba(239, 68, 68, 0.05)";
                $kenarlik = $ben_kazandim ? "#10b981" : "#ef4444";
                $sonuc_rozet = $ben_kazandim ? "<span style='background:#10b981; color:white; padding: 2px 6px; border-radius:4px; font-size:10px;'>ZAFER</span>" : "<span style='background:#ef4444; color:white; padding: 2px 6px; border-radius:4px; font-size:10px;'>BOZGUN</span>";
            ?>
                <div style="background: <?= $arkaplan ?>; border-left: 4px solid <?= $kenarlik ?>; padding: 10px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 10px; color: #64748b; margin-bottom: 4px;"><?= $tarih_formatli ?></div>
                        <div style="font-size: 13px; color: white; font-weight: bold;">
                            <?= $eylem_ikon ?> <span style="color: #94a3b8; font-weight: normal;"><?= $eylem_metin ?></span> <?= htmlspecialchars($rakip_ad) ?>
                        </div>
                        <div style="font-size: 11px; color: #a1a1aa; margin-top: 4px;"><?= $hasar_metni ?></div>
                    </div>
                    <div>
                        <?= $sonuc_rozet ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>