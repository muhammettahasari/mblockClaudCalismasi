<?php
/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/admin/gorev_listesi.php
 * KULLANICI / SAYFA: Süper Admin -> v2 Görev Yönetimi
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * v2 sisteminde hazırlanmış bütün görevlerin listesi. Buradan görev
 * düzenlenir, sırası değiştirilir, yayına alınır/taslağa çekilir, silinir
 * ve öğrencinin göreceği hâli önizlenir.
 *
 * NEDEN GEREKLİ?
 * Önceden v2'de görev listesi yoktu; oynatici.php her zaman "son eklenen"
 * görevi açıyordu. Yani birden fazla görev hazırlanamıyordu.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (Bilinen Sorunlar #17).
 * ============================================================================
 */

require_once __DIR__ . '/../../admin/super_auth.php';
superAdminZorunlu();

$bolumler = $pdo->query("SELECT id, bolum_adi, sira_no FROM bolumler ORDER BY sira_no ASC")->fetchAll(PDO::FETCH_ASSOC);

$secilenBolum = isset($_GET['bolum']) ? (int) $_GET['bolum'] : 0;

if ($secilenBolum > 0) {
    $st = $pdo->prepare(
        "SELECT g.*, b.bolum_adi,
                (SELECT COUNT(*) FROM v2_ogrenci_ilerleme i WHERE i.v2_gorev_id = g.id AND i.kazanilan_puan > 0) AS cozen_sayisi
           FROM v2_gorevler g
      LEFT JOIN bolumler b ON b.id = g.bolum_id
          WHERE g.bolum_id = ?
          ORDER BY g.sira_no ASC, g.id ASC"
    );
    $st->execute([$secilenBolum]);
} else {
    $st = $pdo->query(
        "SELECT g.*, b.bolum_adi,
                (SELECT COUNT(*) FROM v2_ogrenci_ilerleme i WHERE i.v2_gorev_id = g.id AND i.kazanilan_puan > 0) AS cozen_sayisi
           FROM v2_gorevler g
      LEFT JOIN bolumler b ON b.id = g.bolum_id
          ORDER BY g.bolum_id ASC, g.sira_no ASC"
    );
}
$gorevler = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>v2 Görev Yönetimi | Süper Admin</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 25px 20px 60px; }
        .sarmal { max-width: 1050px; margin: 0 auto; }
        .ust { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 22px; }
        .ust h1 { margin: 0; font-size: 22px; }
        .btnler { display: flex; gap: 10px; }
        .btn { padding: 9px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: bold; border: none; cursor: pointer; transition: 0.2s; display: inline-block; }
        .btn-gri { background: #334155; color: #f8fafc; } .btn-gri:hover { background: #475569; }
        .btn-mor { background: #8b5cf6; color: #fff; } .btn-mor:hover { background: #7c3aed; }
        .kart { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; }
        .filtre { margin-bottom: 18px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        select { padding: 9px 12px; background: #0f172a; color: #f8fafc; border: 2px solid #334155; border-radius: 8px; font-size: 14px; outline: none; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #334155; font-size: 14px; }
        th { color: #94a3b8; font-size: 12px; text-transform: uppercase; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        .rozet { padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .rozet-yayin { background: #064e3b; color: #a7f3d0; }
        .rozet-taslak { background: #78350f; color: #fde68a; }
        .rozet-boss { background: #7f1d1d; color: #fecaca; }
        .islem-btn { background: none; border: 1px solid #475569; color: #cbd5e1; padding: 4px 9px; border-radius: 6px; cursor: pointer; font-size: 12px; margin-right: 4px; transition: 0.2s; }
        .islem-btn:hover { background: #334155; }
        .islem-btn.sil:hover { background: #7f1d1d; border-color: #991b1b; color: #fecaca; }
        .bos { text-align: center; padding: 50px 20px; color: #94a3b8; }
    </style>
</head>
<body>
<div class="sarmal">

    <div class="ust">
        <div>
            <h1>🚀 v2 Görev Yönetimi</h1>
            <p style="margin:6px 0 0 0; color:#94a3b8; font-size:13px;">
                Tarayıcı içi Blockly görevleri — çözüm otomatik kontrol edilir, puan anında verilir.
            </p>
        </div>
        <div class="btnler">
            <a href="tasarlayici.php" class="btn btn-mor">+ Yeni Görev Tasarla</a>
            <a href="../../admin/super_panel.php" class="btn btn-gri">← Panele Dön</a>
        </div>
    </div>

    <div class="kart">
        <div class="filtre">
            <label style="color:#94a3b8; font-size:13px; font-weight:bold;">Bölüm filtresi:</label>
            <select onchange="location.href='gorev_listesi.php?bolum=' + this.value">
                <option value="0">Tüm bölümler</option>
                <?php foreach ($bolumler as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= $secilenBolum === (int) $b['id'] ? 'selected' : '' ?>>
                        <?= (int) $b['sira_no'] ?>. <?= temizle($b['bolum_adi']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span style="color:#64748b; font-size:13px;"><?= count($gorevler) ?> görev</span>
        </div>

        <?php if (empty($gorevler)): ?>
            <div class="bos">
                Bu filtreye uyan görev yok.<br>
                <a href="tasarlayici.php" style="color:#8b5cf6; font-weight:bold;">İlk görevi tasarla →</a>
            </div>
        <?php else: ?>
        <table>
            <tr>
                <th style="width:60px">Sıra</th>
                <th>Görev Adı</th>
                <th style="width:150px">Bölüm</th>
                <th style="width:70px">Puan</th>
                <th style="width:90px">Çözen</th>
                <th style="width:100px">Durum</th>
                <th style="width:210px">İşlemler</th>
            </tr>
            <?php foreach ($gorevler as $g): ?>
            <tr id="satir-<?= (int) $g['id'] ?>">
                <td>
                    <button class="islem-btn" onclick="siraDegistir(<?= (int) $g['id'] ?>, 'yukari')" title="Yukarı taşı">▲</button>
                    <button class="islem-btn" onclick="siraDegistir(<?= (int) $g['id'] ?>, 'asagi')" title="Aşağı taşı">▼</button>
                </td>
                <td>
                    <b><?= temizle($g['gorev_adi']) ?></b>
                    <?php if ($g['gorev_tipi'] === 'bolum_sonu_canavari'): ?>
                        <span class="rozet rozet-boss">💀 Boss</span>
                    <?php endif; ?>
                </td>
                <td style="color:#94a3b8"><?= temizle($g['bolum_adi'] ?? '—') ?></td>
                <td><?= (int) $g['puan'] ?></td>
                <td style="color:#94a3b8"><?= (int) $g['cozen_sayisi'] ?> öğrenci</td>
                <td>
                    <span class="rozet <?= $g['yayinda'] ? 'rozet-yayin' : 'rozet-taslak' ?>">
                        <?= $g['yayinda'] ? '● Yayında' : '○ Taslak' ?>
                    </span>
                </td>
                <td>
                    <a class="islem-btn" href="tasarlayici.php?id=<?= (int) $g['id'] ?>">✏️ Düzenle</a>
                    <a class="islem-btn" href="../ogrenci/oynatici.php?gorev_id=<?= (int) $g['id'] ?>" target="_blank">👁️ Önizle</a>
                    <button class="islem-btn" onclick="yayinDegistir(<?= (int) $g['id'] ?>, <?= $g['yayinda'] ? 0 : 1 ?>)">
                        <?= $g['yayinda'] ? '⏸️' : '▶️' ?>
                    </button>
                    <button class="islem-btn sil" onclick="goreviSil(<?= (int) $g['id'] ?>, <?= htmlspecialchars(json_encode($g['gorev_adi']), ENT_QUOTES) ?>)">🗑️</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
function apiGonder(govde) {
    return fetch('../api/gorev_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(govde)
    }).then(r => r.json());
}

function siraDegistir(id, yon) {
    apiGonder({ islem: 'sira_degistir', gorev_id: id, yon: yon })
        .then(d => { if (d.success) location.reload(); else alert('❌ ' + (d.mesaj || 'İşlem başarısız')); });
}

function yayinDegistir(id, yayinda) {
    apiGonder({ islem: 'yayin_degistir', gorev_id: id, yayinda: yayinda })
        .then(d => { if (d.success) location.reload(); else alert('❌ ' + (d.mesaj || 'İşlem başarısız')); });
}

function goreviSil(id, ad) {
    if (!confirm('"' + ad + '" görevi kalıcı olarak silinecek.\nÖğrencilerin bu görevden aldığı puanlar hesaplarında kalır.\n\nEmin misiniz?')) return;

    apiGonder({ islem: 'gorev_sil', gorev_id: id })
        .then(d => {
            if (d.success) {
                const satir = document.getElementById('satir-' + id);
                if (satir) satir.remove();
            } else {
                alert('❌ ' + (d.mesaj || 'Silinemedi'));
            }
        });
}
</script>
</body>
</html>
