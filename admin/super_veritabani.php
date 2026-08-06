<?php
/* ====================================================================
 * MODÜL: Veritabanı Sağlık Raporu (Süper Admin)
 * DOSYA YOLU: admin/super_veritabani.php
 * KULLANICI: Süper Admin
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Veritabanının o anki durumunu tek sayfada gösterir: hangi tablolar var,
 * kaç kayıt, eksik/bozuk veri var mı, quiz soru havuzu yeterli mi...
 * En altta "Raporu Kopyala" butonu var — çıktıyı kopyalayıp yardım
 * isterken yapıştırabilirsiniz. Böylece veritabanına doğrudan erişim
 * gerekmeden sorun teşhis edilebilir.
 *
 * GÜVENLİK:
 *  - Sadece Süper Admin girebilir.
 *  - SADECE OKUR. Hiçbir veriyi değiştirmez, silmez.
 *  - Şifre sütunları rapora dahil edilmez.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu.
 * ==================================================================== */

require_once __DIR__ . '/super_auth.php';
superAdminZorunlu();

/** Güvenli tek değer sorgusu */
function tekDeger(PDO $pdo, string $sql, array $p = [])
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchColumn();
    } catch (Throwable $e) {
        return 'HATA: ' . $e->getMessage();
    }
}

/** Güvenli çoklu satır sorgusu */
function satirlar(PDO $pdo, string $sql, array $p = []): array
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [['HATA' => $e->getMessage()]];
    }
}

/* ---- 1. Tablolar ve kayıt sayıları ---- */
$tablolar = [];
foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $tablolar[$t] = (int) tekDeger($pdo, "SELECT COUNT(*) FROM `" . str_replace('`', '', $t) . "`");
}

/* ---- 2. Şema kontrolü: kodun beklediği sütunlar ---- */
$beklenen = [
    'super_adminler'       => ['kullanici_adi', 'sifre_hash', 'basarisiz_deneme', 'kilit_bitis'],
    'ogretmenler'          => ['duello_bitis', 'onay_sifresi', 'arena_bitis', 'pin_kodu'],
    'quiz_oturum'          => ['ogretmen_id', 'sinif_id', 'bitis_zaman'],
    'ogrenci_ilerleme'     => ['bolum_id', 'kaynak', 'tarih'],
    'v2_gorevler'          => ['yayinda', 'icerik_json'],
    'v2_ogrenci_ilerleme'  => ['ogrenci_id', 'v2_gorev_id', 'kazanilan_puan'],
    'ogrenci_avatar_durum' => ['genel_guc', 'ceviklik', 'guc_guncelleme'],
    'ogrenciler'           => ['kalkan_bitis', 'elmas', 'mevcut_bolum'],
];

$semaSonuc = [];
foreach ($beklenen as $tablo => $sutunlar) {
    if (!isset($tablolar[$tablo])) {
        $semaSonuc[$tablo] = ['durum' => 'TABLO YOK', 'eksik' => $sutunlar];
        continue;
    }
    $mevcut = [];
    foreach ($pdo->query("SHOW COLUMNS FROM `$tablo`")->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $mevcut[] = $c['Field'];
    }
    $eksik = array_values(array_diff($sutunlar, $mevcut));
    $semaSonuc[$tablo] = ['durum' => $eksik ? 'EKSİK' : 'TAM', 'eksik' => $eksik];
}

/* ---- 3. Foreign key sayısı ---- */
$fkSayisi = (int) tekDeger(
    $pdo,
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
);

/* ---- 4. Sağlık kontrolleri ---- */
$kontroller = [];

$kontroller[] = [
    'ad'    => 'Cevap anahtarı boş olan YAYINDAKİ v2 görevleri',
    'deger' => (int) tekDeger($pdo,
        "SELECT COUNT(*) FROM v2_gorevler
          WHERE yayinda = 1
            AND (JSON_EXTRACT(icerik_json,'$.blueprint.ana_cozum') IS NULL
                 OR TRIM(JSON_UNQUOTE(JSON_EXTRACT(icerik_json,'$.blueprint.ana_cozum'))) = ''
                 OR JSON_UNQUOTE(JSON_EXTRACT(icerik_json,'$.blueprint.ana_cozum')) = 'null')"),
    'iyi'   => 0,
    'not'   => 'Öğrenci bu görevi bitiremez, bölüm ilerlemesi kilitlenir. kurulum/veri_bakim_2026_08.sql madde 1',
];

$kontroller[] = [
    'ad'    => 'Öğretmensiz + sınıfsız öğrenci',
    'deger' => (int) tekDeger($pdo, "SELECT COUNT(*) FROM ogrenciler WHERE ogretmen_id IS NULL AND sinif_id IS NULL"),
    'iyi'   => 0,
    'not'   => 'Giriş yapamaz ama Türkiye sıralamasında görünür. veri_bakim madde 2',
];

$kontroller[] = [
    'ad'    => 'Sahipsiz (öğretmeni olmayan) AKTİF quiz oturumu',
    'deger' => (int) tekDeger($pdo, "SELECT COUNT(*) FROM quiz_oturum WHERE durum='aktif' AND ogretmen_id IS NULL"),
    'iyi'   => 0,
    'not'   => 'Eski global oturum kalıntısı; öğrenciler yanlış quize çekilebilir.',
];

$kontroller[] = [
    'ad'    => 'Toplam quiz sorusu',
    'deger' => (int) tekDeger($pdo, "SELECT COUNT(*) FROM quiz_sorulari"),
    'iyi'   => null,
    'not'   => 'Az ise quiz arenası boş görünür.',
];

$kontroller[] = [
    'ad'    => 'Süper admin hesabı',
    'deger' => (int) tekDeger($pdo, "SELECT COUNT(*) FROM super_adminler"),
    'iyi'   => null,
    'not'   => '0 ise ilk girişte otomatik oluşur (super/123).',
];

$kontroller[] = [
    'ad'    => 'Güç önbelleği hesaplanmamış öğrenci',
    'deger' => (int) tekDeger($pdo, "SELECT COUNT(*) FROM ogrenci_avatar_durum WHERE guc_guncelleme IS NULL"),
    'iyi'   => null,
    'not'   => 'Bakım Merkezi > Güçleri Yeniden Hesapla ile doldurulur.',
];

/* ---- 5. Kapsam raporları ---- */
$quizKapsam = satirlar($pdo,
    "SELECT b.sira_no, b.bolum_adi, q.zorluk_seviyesi, COUNT(*) AS adet
       FROM quiz_sorulari q JOIN bolumler b ON b.id = q.bolum_id
      GROUP BY b.id, q.zorluk_seviyesi ORDER BY b.sira_no, q.zorluk_seviyesi");

$bolumKapsam = satirlar($pdo,
    "SELECT b.sira_no, b.bolum_adi,
            COUNT(v.id) AS v2_toplam,
            COALESCE(SUM(v.yayinda = 1),0) AS v2_yayinda,
            (SELECT COUNT(*) FROM gorevler g WHERE g.bolum_id = b.id) AS v1_gorev,
            (SELECT COUNT(*) FROM quiz_sorulari q WHERE q.bolum_id = b.id) AS soru
       FROM bolumler b LEFT JOIN v2_gorevler v ON v.bolum_id = b.id
      GROUP BY b.id ORDER BY b.sira_no");

$ogretmenDurum = satirlar($pdo,
    "SELECT o.id, o.kullanici_adi, o.okul_adi, o.pin_kodu, o.durum,
            DATE(o.bitis_tarihi) AS lisans_bitis,
            DATEDIFF(o.bitis_tarihi, NOW()) AS kalan_gun,
            (SELECT COUNT(*) FROM siniflar s WHERE s.ogretmen_id = o.id) AS sinif_sayisi,
            (SELECT COUNT(*) FROM ogrenciler g WHERE g.ogretmen_id = o.id) AS ogrenci_sayisi
       FROM ogretmenler o ORDER BY o.id");

$ogrenciSeviye = satirlar($pdo,
    "SELECT mevcut_bolum, COUNT(*) AS adet FROM ogrenciler GROUP BY mevcut_bolum ORDER BY mevcut_bolum");

$surumBilgi = [
    'PHP'        => PHP_VERSION,
    'Sunucu'     => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
    'Veritabanı' => tekDeger($pdo, "SELECT DATABASE()"),
    'Karakter'   => tekDeger($pdo, "SELECT @@character_set_database"),
    'Tarih'      => date('d.m.Y H:i'),
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Veritabanı Sağlık Raporu | Süper Admin</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 25px 20px 60px; }
        .sarmal { max-width: 1000px; margin: 0 auto; }
        .ust { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 22px; }
        .ust h1 { margin: 0; font-size: 22px; }
        .btn { padding: 9px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: bold; border: none; cursor: pointer; }
        .btn-gri { background: #334155; color: #f8fafc; }
        .btn-mavi { background: #3b82f6; color: #fff; }
        .kart { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 22px; margin-bottom: 18px; }
        .kart h2 { margin: 0 0 14px 0; font-size: 16px; padding-bottom: 10px; border-bottom: 1px solid #334155; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #334155; }
        th { color: #94a3b8; font-size: 11px; text-transform: uppercase; }
        .iyi { color: #10b981; font-weight: bold; }
        .kotu { color: #ef4444; font-weight: bold; }
        .notr { color: #94a3b8; }
        .kucuk { color: #64748b; font-size: 12px; }
        .izgara { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px; }
        .sayac { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 11px 13px; }
        .sayac b { display: block; font-size: 19px; }
        pre { background: #020617; border: 1px solid #334155; border-radius: 8px; padding: 15px; overflow-x: auto; font-size: 12px; color: #cbd5e1; white-space: pre-wrap; max-height: 420px; }
    </style>
</head>
<body>
<?php require __DIR__ . '/../bilesenler/super_gezinme.php'; ?>

<div class="sarmal">

    <div class="ust">
        <div>
            <h1>🩺 Veritabanı Sağlık Raporu</h1>
            <p class="kucuk" style="margin:6px 0 0 0;">
                Bu sayfa <b>sadece okur</b> — hiçbir veriyi değiştirmez. Şifreler rapora dahil edilmez.
            </p>
        </div>
        <div style="display:flex; gap:10px;">
            <button class="btn btn-mavi" onclick="raporuKopyala()">📋 Raporu Kopyala</button>
            <a href="super_panel.php" class="btn btn-gri">← Panel</a>
        </div>
    </div>

    <div class="kart">
        <h2>⚙️ Sistem</h2>
        <div class="izgara">
            <?php foreach ($surumBilgi as $ad => $deger): ?>
                <div class="sayac"><span class="kucuk"><?= temizle($ad) ?></span><b><?= temizle((string) $deger) ?></b></div>
            <?php endforeach; ?>
            <div class="sayac"><span class="kucuk">Foreign Key</span><b class="<?= $fkSayisi >= 20 ? 'iyi' : 'kotu' ?>"><?= $fkSayisi ?></b></div>
        </div>
    </div>

    <div class="kart">
        <h2>🧬 Şema Kontrolü (kodun beklediği sütunlar)</h2>
        <table>
            <tr><th>Tablo</th><th style="width:110px">Durum</th><th>Eksik sütunlar</th></tr>
            <?php foreach ($semaSonuc as $tablo => $s): ?>
            <tr>
                <td><?= temizle($tablo) ?></td>
                <td class="<?= $s['durum'] === 'TAM' ? 'iyi' : 'kotu' ?>"><?= $s['durum'] === 'TAM' ? '✅ TAM' : '❌ ' . $s['durum'] ?></td>
                <td class="kucuk"><?= $s['eksik'] ? temizle(implode(', ', $s['eksik'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="kart">
        <h2>🔍 Sağlık Kontrolleri</h2>
        <table>
            <tr><th>Kontrol</th><th style="width:90px">Sonuç</th><th>Not</th></tr>
            <?php foreach ($kontroller as $k):
                $sinif = 'notr';
                if ($k['iyi'] !== null) { $sinif = ((int) $k['deger'] === (int) $k['iyi']) ? 'iyi' : 'kotu'; }
            ?>
            <tr>
                <td><?= temizle($k['ad']) ?></td>
                <td class="<?= $sinif ?>"><?= (int) $k['deger'] ?></td>
                <td class="kucuk"><?= temizle($k['not']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="kart">
        <h2>📚 Bölüm Kapsamı</h2>
        <table>
            <tr><th>Sıra</th><th>Bölüm</th><th>v2 (yayında/toplam)</th><th>v1 görev</th><th>Quiz soru</th></tr>
            <?php foreach ($bolumKapsam as $b): ?>
            <tr>
                <td><?= (int) ($b['sira_no'] ?? 0) ?></td>
                <td><?= temizle($b['bolum_adi'] ?? '') ?></td>
                <td><?= (int) ($b['v2_yayinda'] ?? 0) ?> / <?= (int) ($b['v2_toplam'] ?? 0) ?></td>
                <td><?= (int) ($b['v1_gorev'] ?? 0) ?></td>
                <td class="<?= (int) ($b['soru'] ?? 0) === 0 ? 'kotu' : '' ?>"><?= (int) ($b['soru'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p class="kucuk" style="margin-bottom:0">Quiz sorusu 0 olan bölümler için quiz başlatılamaz.</p>
    </div>

    <div class="kart">
        <h2>🎯 Quiz Soru Havuzu (bölüm × zorluk)</h2>
        <?php if (empty($quizKapsam)): ?>
            <p class="kotu" style="margin:0">Soru havuzu boş.</p>
        <?php else: ?>
        <table>
            <tr><th>Bölüm</th><th>Zorluk</th><th>Adet</th></tr>
            <?php foreach ($quizKapsam as $q): ?>
            <tr>
                <td><?= (int) ($q['sira_no'] ?? 0) ?>. <?= temizle($q['bolum_adi'] ?? '') ?></td>
                <td><?= temizle($q['zorluk_seviyesi'] ?? '') ?></td>
                <td><?= (int) ($q['adet'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>

    <div class="kart">
        <h2>👨‍🏫 Öğretmenler</h2>
        <table>
            <tr><th>Kullanıcı</th><th>Okul</th><th>PIN</th><th>Durum</th><th>Lisans</th><th>Sınıf</th><th>Öğrenci</th></tr>
            <?php foreach ($ogretmenDurum as $o): ?>
            <tr>
                <td><?= temizle($o['kullanici_adi'] ?? '') ?></td>
                <td class="kucuk"><?= temizle($o['okul_adi'] ?? '') ?></td>
                <td style="font-family:monospace"><?= temizle($o['pin_kodu'] ?? '') ?></td>
                <td><?= temizle($o['durum'] ?? '') ?></td>
                <td class="<?= (int) ($o['kalan_gun'] ?? 0) < 0 ? 'kotu' : '' ?>">
                    <?= temizle((string) ($o['lisans_bitis'] ?? '—')) ?>
                    <span class="kucuk">(<?= (int) ($o['kalan_gun'] ?? 0) ?> gün)</span>
                </td>
                <td><?= (int) ($o['sinif_sayisi'] ?? 0) ?></td>
                <td><?= (int) ($o['ogrenci_sayisi'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="kart">
        <h2>📊 Tablo Kayıt Sayıları</h2>
        <div class="izgara">
            <?php foreach ($tablolar as $t => $adet): ?>
                <div class="sayac"><span class="kucuk"><?= temizle($t) ?></span><b><?= (int) $adet ?></b></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="kart">
        <h2>📋 Kopyalanabilir Metin Rapor</h2>
        <p class="kucuk">Yardım isterken bu metni kopyalayıp yapıştırabilirsiniz. Kişisel veri içermez.</p>
        <pre id="metinRapor"><?php
            echo "== mBlock Veritabani Raporu — " . date('d.m.Y H:i') . " ==\n\n";
            echo "[SISTEM]\n";
            foreach ($surumBilgi as $a => $d) { echo "  $a: $d\n"; }
            echo "  Foreign Key: $fkSayisi\n";

            echo "\n[SEMA]\n";
            foreach ($semaSonuc as $t => $s) {
                echo "  $t: {$s['durum']}" . ($s['eksik'] ? ' -> eksik: ' . implode(',', $s['eksik']) : '') . "\n";
            }

            echo "\n[SAGLIK]\n";
            foreach ($kontroller as $k) { echo "  {$k['ad']}: {$k['deger']}\n"; }

            echo "\n[BOLUM KAPSAMI]\n";
            foreach ($bolumKapsam as $b) {
                echo "  {$b['sira_no']}. {$b['bolum_adi']} | v2 yayinda: {$b['v2_yayinda']}/{$b['v2_toplam']} | v1: {$b['v1_gorev']} | soru: {$b['soru']}\n";
            }

            echo "\n[QUIZ HAVUZU]\n";
            if (!$quizKapsam) { echo "  (bos)\n"; }
            foreach ($quizKapsam as $q) { echo "  {$q['sira_no']}. bolum / {$q['zorluk_seviyesi']}: {$q['adet']} soru\n"; }

            echo "\n[OGRENCI SEVIYELERI]\n";
            foreach ($ogrenciSeviye as $o) { echo "  bolum {$o['mevcut_bolum']}: {$o['adet']} ogrenci\n"; }

            echo "\n[TABLO SAYILARI]\n";
            foreach ($tablolar as $t => $adet) { echo "  $t: $adet\n"; }
        ?></pre>
    </div>

</div>

<script>
function raporuKopyala() {
    const metin = document.getElementById('metinRapor').innerText;
    navigator.clipboard.writeText(metin)
        .then(() => alert('✅ Rapor panoya kopyalandı. Sohbete yapıştırabilirsiniz.'))
        .catch(() => {
            // Eski tarayıcılar için yedek yöntem
            const alan = document.createElement('textarea');
            alan.value = metin;
            document.body.appendChild(alan);
            alan.select();
            document.execCommand('copy');
            alan.remove();
            alert('✅ Rapor panoya kopyalandı.');
        });
}
</script>

</body>
</html>
