<?php
/* ====================================================================
 * MODÜL: Savaş Arenası (Skorboard Sekmesi)
 * DOSYA YOLU: bilesenler/skorboard_modulleri/sinif_arenasi.php
 * BAĞLI OLDUĞU ANA DOSYA: bilesenler/ogrenci_skorboard.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğrencinin sınıf arkadaşlarını güç sırasına göre gördüğü ve saldırabildiği
 * liste. Her rakibin yanında neden saldıramayacağını (kalkan, sıklet farkı,
 * 5 gün kuralı, yaralı) açıklayan bir rozet çıkar.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] KOPYA MATRİS KALDIRILDI (Bilinen Sorunlar #12):
 *      Buradaki 60 satırlık stat tablosu ve skorboardGucHesapla() fonksiyonu
 *      silindi; artık bilesenler/rpg_matris.php kullanılıyor.
 *  - [2026-08] N+1 SORGU ÇÖZÜLDÜ (#10): sinifGucListesi() sınıfın tamamını
 *      tek sorguda hesaplıyor.
 *  - [2026-08] Yenilgi sonrası koruma kalkanı (kalkan_bitis) gösteriliyor (#14).
 * ==================================================================== */

require_once __DIR__ . '/../rpg_matris.php';

// Öğretmen arenayı açtı mı?
$arena_sorgu = $pdo->prepare(
    "SELECT ogr.arena_bitis
       FROM ogretmenler ogr
       JOIN ogrenciler o ON o.ogretmen_id = ogr.id
      WHERE o.id = ?"
);
$arena_sorgu->execute([$aktif_ogrenci_id]);
$arena_bitis   = $arena_sorgu->fetchColumn();
$arena_acik_mi = ($arena_bitis && strtotime($arena_bitis) > time());

if (!$arena_acik_mi):
?>
    <div id="menuArena" style="display: none; padding: 30px; text-align: center;">
        <div style="font-size: 60px; margin-bottom: 15px;">🔒</div>
        <h3 style="color: #f87171; margin: 0 0 10px 0;">Savaş Arenası Kapalı!</h3>
        <p style="color: #94a3b8; font-size: 14px;">
            Öğretmeniniz arenayı açana kadar burada savaş yapamazsınız. Antrenman yapmaya devam edin!
        </p>
    </div>
<?php else:

/* --- Sınıfın güç sıralaması: TEK sorgu (bkz. bilesenler/rpg_matris.php) --- */
$sinif_id_sorgu = $pdo->prepare("SELECT sinif_id FROM ogrenciler WHERE id = ?");
$sinif_id_sorgu->execute([$aktif_ogrenci_id]);
$benim_sinif_id = (int) $sinif_id_sorgu->fetchColumn();

$tum_sinif = sinifGucListesi($pdo, $benim_sinif_id);

/* --- Kalkan / koruma bilgilerini tek sorguda çek --- */
$ek_bilgi_sorgu = $pdo->prepare(
    "SELECT o.id, o.kalkan_bitis, a.gunluk_savunma_sayisi, a.son_savunma_tarihi
       FROM ogrenciler o
  LEFT JOIN ogrenci_avatar_durum a ON a.ogrenci_id = o.id
      WHERE o.sinif_id = ?"
);
$ek_bilgi_sorgu->execute([$benim_sinif_id]);

$ek_bilgi = [];
foreach ($ek_bilgi_sorgu->fetchAll(PDO::FETCH_ASSOC) as $satir) {
    $ek_bilgi[(int) $satir['id']] = $satir;
}

/* --- Benim sıram --- */
$benim_siram = 0;
foreach ($tum_sinif as $ogr) {
    if ($ogr['id'] === (int) $aktif_ogrenci_id) {
        $benim_siram = $ogr['sira'];
        break;
    }
}

/* --- 5 gün kuralı: kime ne zaman saldırdım? --- */
$savas_log_sorgu = $pdo->prepare(
    "SELECT savunan_id, MAX(tarih) AS son_savas
       FROM arena_savaslari WHERE saldiran_id = ? GROUP BY savunan_id"
);
$savas_log_sorgu->execute([$aktif_ogrenci_id]);

$son_savaslar = [];
foreach ($savas_log_sorgu->fetchAll(PDO::FETCH_ASSOC) as $satir) {
    $son_savaslar[(int) $satir['savunan_id']] = $satir['son_savas'];
}

$bugun = date('Y-m-d');
?>

<div id="menuArena" style="display: none; padding: 15px; max-height: 400px; overflow-y: auto;">
    <div style="background: rgba(185, 28, 28, 0.1); border: 1px dashed #ef4444; color: #fca5a5; padding: 10px; border-radius: 8px; text-align: center; font-size: 11px; margin-bottom: 15px;">
        Saldırı Bedeli: <b style="color:#facc15;">20 Enerji ⚡</b><br>
        Sadece <b>5 sıra altındaki/üstündeki</b> rakiplere saldırabilirsin. Aynı kişiye 5 günde 1 kez saldırılabilir.
    </div>

    <ul style="list-style: none; padding: 0; margin: 0;">
        <?php
        $rakip_sayisi = 0;

        foreach ($tum_sinif as $rakip):
            if ($rakip['id'] === (int) $aktif_ogrenci_id) {
                continue;
            }
            $rakip_sayisi++;

            $sira_no = $rakip['sira'];
            $ek      = $ek_bilgi[$rakip['id']] ?? [];

            // Günlük savunma kalkanı (lider için 2, diğerleri için 3 savunma)
            $savunma_sayisi = (($ek['son_savunma_tarihi'] ?? null) === $bugun)
                ? (int) ($ek['gunluk_savunma_sayisi'] ?? 0)
                : 0;
            $kalkan_aktif_mi = ($sira_no === 1 && $savunma_sayisi >= 2)
                            || ($sira_no > 1 && $savunma_sayisi >= 3);

            // Yenilgi sonrası koruma kalkanı (kalkan_bitis)
            $koruma_bitis    = $ek['kalkan_bitis'] ?? null;
            $koruma_aktif_mi = ($koruma_bitis && strtotime($koruma_bitis) > time());
            $koruma_dk       = $koruma_aktif_mi ? (int) ceil((strtotime($koruma_bitis) - time()) / 60) : 0;

            $siklet_uygun_mu = (abs($benim_siram - $sira_no) <= 5);
            $agir_yarali_mi  = ($rakip['can'] <= 0);

            // 5 gün kuralı
            $son_saldirim            = $son_savaslar[$rakip['id']] ?? null;
            $bes_gun_kurali_ihlal_mi = false;
            $kalan_gun_metni         = '';

            if ($son_saldirim) {
                $gecen = time() - strtotime($son_saldirim);
                if ($gecen < (5 * 86400)) {
                    $bes_gun_kurali_ihlal_mi = true;
                    $kalan_gun_metni = ceil(((5 * 86400) - $gecen) / 86400) . ' Gün Bekle';
                }
            }

            $pasif_stil = 'background: #475569; border: none; padding: 6px 10px; border-radius: 5px; font-size: 11px; cursor: not-allowed; opacity: 0.7;';

            if ($kalkan_aktif_mi) {
                $buton_html = '<button disabled style="' . $pasif_stil . ' color: #94a3b8;">🛡️ Kalkan</button>';
            } elseif ($koruma_aktif_mi) {
                $buton_html = '<button disabled style="' . $pasif_stil . ' color: #93c5fd;">🛡️ Koruma (' . $koruma_dk . ' dk)</button>';
            } elseif ($agir_yarali_mi) {
                $buton_html = '<button disabled style="' . $pasif_stil . ' color: #f87171;">❤️ Yaralı</button>';
            } elseif ($bes_gun_kurali_ihlal_mi) {
                $buton_html = '<button disabled style="' . $pasif_stil . ' color: #facc15;">⏳ ' . temizle($kalan_gun_metni) . '</button>';
            } elseif (!$siklet_uygun_mu) {
                $buton_html = '<button disabled style="' . $pasif_stil . ' color: #94a3b8;">⚖️ Sıra Farkı</button>';
            } else {
                $buton_html = '<button onclick="arenadaSaldir(' . (int) $rakip['id'] . ', '
                    . htmlspecialchars(json_encode($rakip['ad_soyad'], JSON_UNESCAPED_UNICODE), ENT_QUOTES)
                    . ')" style="background: linear-gradient(90deg, #ef4444, #b91c1c); color: white; border: none; padding: 6px 15px; border-radius: 5px; font-weight: bold; cursor: pointer; transition: 0.2s; box-shadow: 0 0 10px rgba(239, 68, 68, 0.4);">⚔️ Saldır</button>';
            }
        ?>
        <li style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: rgba(0,0,0,0.2); border-radius: 8px; margin-bottom: 8px; border-left: 3px solid <?= ($sira_no === 1) ? '#facc15' : '#334155' ?>;">
            <div style="display: flex; flex-direction: column;">
                <span style="color: white; font-weight: bold; font-size: 14px;">
                    <?= (int) $sira_no ?>. <?= temizle($rakip['ad_soyad']) ?> <?= ($sira_no === 1) ? '👑' : '' ?>
                </span>
                <div style="display: flex; gap: 10px; font-size: 11px; margin-top: 4px; color: #94a3b8;">
                    <span style="color: #f87171;">HP: <?= (int) $rakip['can'] ?>/100</span>
                    <span style="color: #a78bfa;">Güç: <?= (int) $rakip['genel_guc'] ?></span>
                </div>
            </div>
            <div><?= $buton_html ?></div>
        </li>
        <?php endforeach; ?>

        <?php if ($rakip_sayisi === 0): ?>
            <div style="color:#94a3b8; text-align:center;">Sınıfta meydan okunacak kimse yok!</div>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>
