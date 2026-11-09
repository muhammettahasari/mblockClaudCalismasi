<?php
/* ====================================================================
 * MODÜL: Öğretmen Quiz Kontrol Merkezi (Canlı Radar & Podyum Sürümü)
 * DOSYA: admin/quiz_kontrol_merkezi.php
 * KULLANICI: Öğretmen
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğretmenin quiz savaşını başlattığı, öğrencilerin puanlarını canlı izlediği
 * ve savaşı bitirip podyumu gösterdiği ekran.
 *
 * TEMEL İŞLEVLER:
 *  1. Sınıf + bölüm + zorluk seçerek quiz oturumu açar
 *  2. Saniyede bir canlı sıralamayı günceller
 *  3. Savaşı bitirip puanları kalıcı hesaba aktarır ve podyum gösterir
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] SINIF BAZLI OTURUM (Bilinen Sorunlar #6):
 *      Eskiden quiz_oturum tablosunun sahibi yoktu; bir öğretmen quiz açınca
 *      sistemdeki TÜM okulların öğrencileri aynı arenaya çekiliyor ve aynı
 *      sıralamada yarışıyordu. Artık her oturum bir öğretmene ve bir sınıfa
 *      aittir; başka sınıflar etkilenmez.
 *  - [2026-08] Öğretmen oturumu zorunlu; başka öğretmenin oturumu bitirilemez.
 *  - [2026-08] Öğrenci adları XSS'e karşı temizleniyor.
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
ogretmenZorunlu();

$ogretmen_id = (int) $_SESSION['ogretmen_id'];

/* Öğretmenin sınıfları (oturum açarken ve filtrelerken kullanılır) */
$sinifSorgu = $pdo->prepare("SELECT id, sinif_adi FROM siniflar WHERE ogretmen_id = ? ORDER BY sinif_adi ASC");
$sinifSorgu->execute([$ogretmen_id]);
$siniflar = $sinifSorgu->fetchAll(PDO::FETCH_ASSOC);

/* --------------------------------------------------------------------
 * 1. SAVAŞI BİTİR VE PODYUMU GÖSTER
 * Sadece BU öğretmenin aktif oturumu bitirilir.
 * ------------------------------------------------------------------ */
/* [2026-11] Artık POST + CSRF. Eskiden ?islem=quiz_bitir bağlantısı puanları
   kalıcıya aktarıp quiz'i bitirmeye yetiyordu. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['islem'] ?? '') === 'quiz_bitir') {
    csrfDogrulaSayfa('quiz_kontrol_merkezi.php');

    $st = $pdo->prepare("SELECT * FROM quiz_oturum WHERE ogretmen_id = ? AND durum = 'aktif' ORDER BY id DESC LIMIT 1");
    $st->execute([$ogretmen_id]);
    $oturum = $st->fetch(PDO::FETCH_ASSOC);

    if (!$oturum) {
        header('Location: quiz_kontrol_merkezi.php');
        exit;
    }

    /* [2026-08] TÜM sıralama çekiliyor, sadece ilk 3 değil.
       Öğrenciler de aynı tabloyu görüyor (api/quiz_skor_tablosu.php);
       öğretmende eksik liste durması kafa karıştırıcıydı. */
    $st = $pdo->prepare(
        "SELECT o.ad_soyad, qo.puan, qo.cozulen_soru_sayisi
           FROM quiz_ogrenci qo
           JOIN ogrenciler o ON qo.ogrenci_id = o.id
          WHERE qo.oturum_id = ?
          ORDER BY qo.puan DESC, qo.cozulen_soru_sayisi DESC, o.ad_soyad ASC"
    );
    $st->execute([$oturum['id']]);
    $tumSiralama = $st->fetchAll(PDO::FETCH_ASSOC);
    $kazananlar  = array_slice($tumSiralama, 0, 3);   // podyum için ilk üç

    // Kazanılan/kaybedilen puanları kalıcı hesaba aktar (0'ın altına inmez)
    $pdo->prepare(
        "UPDATE ogrenciler o
           JOIN quiz_ogrenci qo ON o.id = qo.ogrenci_id
            SET o.kalici_puan = GREATEST(0, o.kalici_puan + qo.puan)
          WHERE qo.oturum_id = ? AND qo.puan <> 0"
    )->execute([$oturum['id']]);

    // SADECE bu oturumu kapat
    $pdo->prepare("UPDATE quiz_oturum SET durum = 'bitti', bitis_zaman = NOW() WHERE id = ?")
        ->execute([$oturum['id']]);
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <title>Savaşın Sonucu | Şampiyonlar</title>
        <!-- [2026-08] OTOMATİK YÖNLENDİRME KALDIRILDI.
             Eskiden 7 saniye sonra zorla ogretmen_paneli.php'ye atıyordu.
             Öğretmen tabloyu tahtaya yansıtıp sınıfla konuşmak istiyor;
             ekran elinden alınıyordu. Artık dönüş düğmeye bağlı. -->
        <style>
            body { background: #020617; color: white; font-family: 'Segoe UI', sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; min-height: 100vh; margin: 0; padding: 30px 20px 50px; text-align: center; }
            h1 { font-size: 60px; color: #facc15; text-transform: uppercase; letter-spacing: 5px; text-shadow: 0 0 30px rgba(250, 204, 21, 0.5); margin-bottom: 50px; animation: kupaSallan 2s infinite ease-in-out; }
            .podium-container { display: flex; align-items: flex-end; justify-content: center; gap: 20px; height: 300px; }
            .podium-box { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; width: 150px; border-radius: 15px 15px 0 0; box-shadow: 0 -10px 30px rgba(0,0,0,0.5); position: relative; animation: yuksel 1.5s ease-out; }
            .rank-name { font-size: 20px; font-weight: bold; margin-bottom: 10px; padding: 0 10px; word-wrap: break-word; text-shadow: 1px 1px 2px black; }
            .rank-score { font-size: 24px; font-family: monospace; font-weight: 900; background: rgba(0,0,0,0.5); padding: 5px 10px; border-radius: 8px; margin-bottom: 15px; }
            .p1 { height: 280px; background: linear-gradient(0deg, #ca8a04, #fef08a); z-index: 3; }
            .p2 { height: 220px; background: linear-gradient(0deg, #475569, #cbd5e1); z-index: 2; }
            .p3 { height: 180px; background: linear-gradient(0deg, #9a3412, #fdba74); z-index: 1; }
            .badge { font-size: 50px; position: absolute; top: -60px; }
            .redirect-text { margin-top: 50px; color: #94a3b8; font-size: 18px; animation: flash 1s infinite; }
            @keyframes yuksel { 0% { height: 0; opacity: 0; } 100% { opacity: 1; } }
            @keyframes kupaSallan { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }
            @keyframes flash { 0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; } }
        </style>
        <script src="<?= varlik('js/onay_kutusu.js', '../') ?>"></script>
</head>
    <body>
        <h1>🏆 SAVAŞIN ŞAMPİYONLARI 🏆</h1>
        <audio autoplay src="../assets/sounds/quizbittiskorboard.mp3"></audio>
        <div class="podium-container">
            <?php if (isset($kazananlar[1])): ?>
                <div class="podium-box p2">
                    <div class="badge">🥈</div>
                    <div class="rank-name"><?= temizle($kazananlar[1]['ad_soyad']) ?></div>
                    <div class="rank-score"><?= (int) $kazananlar[1]['puan'] ?> Puan</div>
                </div>
            <?php endif; ?>

            <?php if (isset($kazananlar[0])): ?>
                <div class="podium-box p1" style="transform: scale(1.1);">
                    <div class="badge">👑</div>
                    <div class="rank-name"><?= temizle($kazananlar[0]['ad_soyad']) ?></div>
                    <div class="rank-score"><?= (int) $kazananlar[0]['puan'] ?> Puan</div>
                </div>
            <?php endif; ?>

            <?php if (isset($kazananlar[2])): ?>
                <div class="podium-box p3">
                    <div class="badge">🥉</div>
                    <div class="rank-name"><?= temizle($kazananlar[2]['ad_soyad']) ?></div>
                    <div class="rank-score"><?= (int) $kazananlar[2]['puan'] ?> Puan</div>
                </div>
            <?php endif; ?>
        </div>
        <!-- [2026-08] TAM SIRALAMA — öğrencilerin gördüğü tablonun aynısı -->
        <div style="width:100%; max-width:640px; margin-top:44px;">
            <h2 style="color:#94a3b8; font-size:15px; letter-spacing:2px; text-transform:uppercase; margin-bottom:14px;">
                Tam Sıralama · <?= temizle($oturum['sinif_adi'] ?? '') ?> · <?= count($tumSiralama) ?> savaşçı
            </h2>

            <div style="background:#0f172a; border:1px solid #334155; border-radius:12px; overflow:hidden; max-height:44vh; overflow-y:auto;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="position:sticky; top:0; background:#1e293b;">
                            <th style="padding:11px 8px; color:#94a3b8; font-size:11px; text-align:center; width:60px;">SIRA</th>
                            <th style="padding:11px 8px; color:#94a3b8; font-size:11px; text-align:left;">SAVAŞÇI</th>
                            <th style="padding:11px 8px; color:#94a3b8; font-size:11px; text-align:center;">SORU</th>
                            <th style="padding:11px 8px; color:#94a3b8; font-size:11px; text-align:right;">PUAN</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($tumSiralama)): ?>
                        <tr><td colspan="4" style="padding:28px; color:#94a3b8;">Bu savaşta kimse puan toplayamadı.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tumSiralama as $i => $r):
                            $madalya = [1 => '🥇', 2 => '🥈', 3 => '🥉'][$i + 1] ?? (($i + 1) . '.'); ?>
                        <tr style="border-top:1px solid #1e293b;">
                            <td style="padding:11px 8px; text-align:center; font-size:18px;"><?= $madalya ?></td>
                            <td style="padding:11px 8px; text-align:left; font-weight:600; color:#e2e8f0;"><?= temizle($r['ad_soyad']) ?></td>
                            <td style="padding:11px 8px; text-align:center; color:#94a3b8; font-size:13px;"><?= (int) $r['cozulen_soru_sayisi'] ?></td>
                            <td style="padding:11px 8px; text-align:right; font-weight:900; color:#10b981; font-size:17px;"><?= (int) $r['puan'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <a href="ogretmen_paneli.php"
               style="display:inline-block; margin-top:26px; background:linear-gradient(135deg,#3b82f6,#2563eb);
                      color:#fff; text-decoration:none; padding:15px 36px; border-radius:11px;
                      font-weight:bold; font-size:16px; box-shadow:0 6px 18px rgba(59,130,246,.4);">
                🏫 Yönetim Paneline Dön
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/* --------------------------------------------------------------------
 * 2. QUİZ BAŞLATMA
 * Artık sınıf seçimi ZORUNLU — oturum o sınıfa kilitlenir.
 * ------------------------------------------------------------------ */
$formHatasi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['islem'] ?? '') === 'quiz_baslat') {
    csrfDogrulaSayfa('quiz_kontrol_merkezi.php');

    $sinif_id = (int) ($_POST['sinif_id'] ?? 0);
    $zorluk   = !empty($_POST['zorluk_seviyeleri']) ? implode(',', array_map('strval', $_POST['zorluk_seviyeleri'])) : 'Orta';
    $bolumler_secilen = !empty($_POST['bolumler']) ? array_map('intval', $_POST['bolumler']) : [];

    // Sınıf gerçekten bu öğretmene ait mi?
    $sahiplik = false;
    foreach ($siniflar as $s) {
        if ((int) $s['id'] === $sinif_id) {
            $sahiplik = true;
            break;
        }
    }

    if (!$sahiplik) {
        $formHatasi = 'Lütfen kendi sınıflarınızdan birini seçin.';
    } elseif (empty($bolumler_secilen)) {
        $formHatasi = 'En az bir bölüm seçmelisiniz.';
    } elseif (empty($_POST['zorluk_seviyeleri'])) {
        $formHatasi = 'En az bir zorluk seviyesi seçmelisiniz.';
    } else {
        /* [2026-08] BOŞ QUIZ FRENİ (Bilinen Sorunlar — quiz soruları gelmiyor):
           Öğretmen quiz başlatıyor, öğrencilere davet gidiyor ama arenada
           "Bu bölümde hiç canavar (soru) yok!" yazıyordu. Sebep: soru havuzu
           seçilen bölüm+zorluk kombinasyonuyla eşleşmiyordu.
           Artık oturum açılmadan ÖNCE kaç soru bulunduğu kontrol ediliyor. */
        $zorlukListesi = array_map('strval', $_POST['zorluk_seviyeleri']);
        $bIn = implode(',', array_fill(0, count($bolumler_secilen), '?'));
        $zIn = implode(',', array_fill(0, count($zorlukListesi), '?'));

        $sayimSorgu = $pdo->prepare(
            "SELECT COUNT(*) FROM quiz_sorulari WHERE bolum_id IN ($bIn) AND zorluk_seviyesi IN ($zIn)"
        );
        $sayimSorgu->execute(array_merge($bolumler_secilen, $zorlukListesi));
        $bulunanSoru = (int) $sayimSorgu->fetchColumn();

        if ($bulunanSoru === 0) {
            $formHatasi = 'Seçtiğiniz bölüm ve zorluk kombinasyonunda HİÇ SORU YOK. '
                        . 'Quiz başlatılmadı. Aşağıdaki listede her seçeneğin yanındaki '
                        . 'soru sayısına bakarak seçim yapın.';
        }
    }

    if ($formHatasi === '' && $sahiplik) {
        // Aynı sınıfta zaten açık bir oturum varsa onu kapat (çift oturum olmasın)
        $pdo->prepare(
            "UPDATE quiz_oturum SET durum = 'bitti', bitis_zaman = NOW()
              WHERE ogretmen_id = ? AND sinif_id = ? AND durum = 'aktif'"
        )->execute([$ogretmen_id, $sinif_id]);

        $pdo->prepare(
            "INSERT INTO quiz_oturum (ogretmen_id, sinif_id, bolum_idler, zorluk_seviyesi, durum, baslangic_zaman)
             VALUES (?, ?, ?, ?, 'aktif', NOW())"
        )->execute([$ogretmen_id, $sinif_id, implode(',', $bolumler_secilen), $zorluk]);

        header('Location: quiz_kontrol_merkezi.php');
        exit;
    }
}

/* --------------------------------------------------------------------
 * 3. EKRAN VERİSİ — sadece bu öğretmenin aktif oturumu
 * ------------------------------------------------------------------ */
$st = $pdo->prepare(
    "SELECT qo.*, s.sinif_adi
       FROM quiz_oturum qo
  LEFT JOIN siniflar s ON s.id = qo.sinif_id
      WHERE qo.ogretmen_id = ? AND qo.durum = 'aktif'
      ORDER BY qo.id DESC LIMIT 1"
);
$st->execute([$ogretmen_id]);
$aktif_oturum = $st->fetch(PDO::FETCH_ASSOC);

/* [2026-08] SORU HAVUZU GERÇEĞE GÖRE LİSTELENİYOR
   Sorun: Zorluk seviyesi soru ekleme ekranında SERBEST METİN olarak
   giriliyor ("Seç veya Yaz..."), ama burada Kolay/Orta/Zor/Boss diye
   4 sabit seçenek vardı. Admin farklı bir şey yazdıysa (örn. "1. Seviye")
   hiçbir soru eşleşmiyor, öğrenci arenaya girip boş ekran görüyordu.
   Çözüm: seçenekler artık veritabanındaki GERÇEK değerlerden üretiliyor
   ve her birinin yanında kaç soru olduğu yazıyor. */

// Bölümler + içindeki soru sayısı
$bolumler = $pdo->query(
    "SELECT b.id, b.bolum_adi, b.sira_no,
            (SELECT COUNT(*) FROM quiz_sorulari q WHERE q.bolum_id = b.id) AS soru_sayisi
       FROM bolumler b
      ORDER BY b.sira_no ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Veritabanında gerçekten kullanılan zorluk seviyeleri + adetleri
$zorlukSeviyeleri = $pdo->query(
    "SELECT zorluk_seviyesi, COUNT(*) AS adet
       FROM quiz_sorulari
      WHERE zorluk_seviyesi IS NOT NULL AND TRIM(zorluk_seviyesi) <> ''
      GROUP BY zorluk_seviyesi
      ORDER BY adet DESC, zorluk_seviyesi ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$toplamSoru = (int) $pdo->query("SELECT COUNT(*) FROM quiz_sorulari")->fetchColumn();

// Öğrencilerin hangi bölüme kadar geldiği — öğretmene ipucu olsun
$enYuksekBolum = 0;
$st = $pdo->prepare(   // [2026-11] sahiplik zinciri: siniflar.ogretmen_id
    "SELECT COALESCE(MAX(o.mevcut_bolum), 1)
       FROM ogrenciler o JOIN siniflar s ON s.id = o.sinif_id
      WHERE s.ogretmen_id = ?"
);
$st->execute([$ogretmen_id]);
$enYuksekBolum = (int) $st->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Canlı Radar | Savaş Merkezi</title>
    <style>
        body { background: #0f172a; color: white; font-family: 'Segoe UI', sans-serif; padding: 20px; margin: 0; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: #1e293b; border-radius: 15px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid #334155; }
        .form-group { margin-bottom: 20px; }
        .form-group label.baslik { display: block; font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #94a3b8; }
        .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .checkbox-label { display: flex; align-items: center; gap: 10px; background: #334155; padding: 15px; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .checkbox-label:hover { background: #475569; }
        .checkbox-label input { width: 20px; height: 20px; cursor: pointer; }
        select.sinif-sec { width: 100%; padding: 15px; background: #334155; color: #fff; border: 2px solid #475569; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; outline: none; }
        select.sinif-sec:focus { border-color: #22c55e; }
        .btn-start { background: linear-gradient(135deg, #16a34a, #22c55e); color: white; font-size: 20px; font-weight: bold; border: none; padding: 15px 40px; border-radius: 10px; cursor: pointer; width: 100%; margin-top: 20px; transition: 0.3s; box-shadow: 0 0 20px rgba(34, 197, 94, 0.4); }
        .btn-start:hover { transform: scale(1.02); box-shadow: 0 0 30px rgba(34, 197, 94, 0.6); }
        .btn-stop { background: linear-gradient(135deg, #b91c1c, #ef4444); color: white; text-decoration: none; padding: 15px 30px; border-radius: 10px; font-weight: bold; font-size: 18px; display: inline-block; box-shadow: 0 0 20px rgba(239, 68, 68, 0.4); transition: 0.3s; }
        .btn-stop:hover { box-shadow: 0 0 30px rgba(239, 68, 68, 0.6); }
        .radar-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #334155; padding-bottom: 20px; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .player-row { display: flex; align-items: center; background: #334155; padding: 15px 20px; border-radius: 10px; margin-bottom: 10px; font-size: 18px; font-weight: bold; transition: all 0.4s ease-in-out; }
        .rank-1 { background: linear-gradient(90deg, #ca8a04, #eab308); color: black; transform: scale(1.03); border: 2px solid #fef08a; box-shadow: 0 0 20px rgba(234, 179, 8, 0.3); z-index: 3; }
        .rank-2 { background: linear-gradient(90deg, #94a3b8, #cbd5e1); color: black; }
        .rank-3 { background: linear-gradient(90deg, #9a3412, #c2410c); color: white; }
        .rank-badge { width: 50px; font-size: 24px; font-weight: 900; }
        .player-name { flex: 1; }
        .player-score { font-size: 24px; font-family: monospace; background: rgba(0,0,0,0.2); padding: 5px 15px; border-radius: 8px; position: relative; }
        .player-stats { font-size: 14px; opacity: 0.8; margin-right: 20px; }
        .flash-green { animation: flashAnim 1.5s ease-out; }
        @keyframes flashAnim { 0% { background: #22c55e; transform: scale(1.02); box-shadow: 0 0 20px #22c55e; color: white; } 100% { background: #334155; transform: scale(1); box-shadow: none; } }
        .pulse { animation: pulseAnim 2s infinite; }
        @keyframes pulseAnim { 0% { opacity: 1; } 50% { opacity: 0.5; color: #ef4444; } 100% { opacity: 1; } }
        .rozet-sinif { background: #3b82f6; padding: 6px 14px; border-radius: 20px; font-size: 14px; font-weight: bold; }
        .hata-kutu { background: #7f1d1d; color: #fecaca; border: 1px solid #991b1b; padding: 14px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .bos-uyari { background: #78350f; color: #fde68a; border: 1px solid #d97706; padding: 18px; border-radius: 10px; font-weight: bold; text-align: center; }
        .geri-link { display:inline-block; margin-bottom:18px; color:#94a3b8; text-decoration:none; font-size:14px; font-weight:bold; }
        .geri-link:hover { color:#f8fafc; }
    </style>
    <link rel="stylesheet" href="../css/ogretmen_tema.css">
    <script src="<?= varlik('js/onay_kutusu.js', '../') ?>"></script>
</head>
<body class="ot">
<?php $OT_AKTIF = 'quiz'; require __DIR__ . '/../bilesenler/ogretmen_ust_bar.php'; ?>


<div class="container">
    <a href="ogretmen_paneli.php" class="geri-link">← Öğretmen Paneline Dön</a>

    <?php if (!$aktif_oturum): ?>
        <h1 style="text-align: center; font-size: 36px; margin-bottom: 30px;">⚔️ Savaş Arenasını Kur</h1>

        <div class="card">
            <?php if ($formHatasi !== ''): ?>
                <div class="hata-kutu">❌ <?= temizle($formHatasi) ?></div>
            <?php endif; ?>

            <?php if (empty($siniflar)): ?>
                <div class="bos-uyari">
                    Henüz hiç sınıfınız yok. Quiz başlatmadan önce
                    <a href="ogrenci_yonetimi.php" style="color:#fde68a">Öğrenci Yönetimi</a>
                    sayfasından bir sınıf oluşturun.
                </div>
            <?php else: ?>
            <form action="quiz_kontrol_merkezi.php" method="POST" data-onay="Seçtiğiniz sınıfın ekranları kararacak ve arenaya çekilecekler. Başlatılsın mı?">
                <?= csrfAlani() ?>
                <input type="hidden" name="islem" value="quiz_baslat">

                <div class="form-group">
                    <label class="baslik">🎓 Hangi Sınıf Savaşacak?</label>
                    <select name="sinif_id" class="sinif-sec" required>
                        <option value="">— Sınıf seçin —</option>
                        <?php foreach ($siniflar as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= temizle($s['sinif_adi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p style="color:#64748b; font-size:13px; margin:8px 0 0 0;">
                        Quiz yalnızca seçtiğiniz sınıfın öğrencilerine açılır. Diğer sınıflar ve okullar etkilenmez.
                    </p>
                </div>

                <?php if ($toplamSoru === 0): ?>
                    <div class="bos-uyari" style="margin-bottom:20px;">
                        ⚠️ Soru havuzu tamamen boş. Quiz başlatmadan önce
                        <a href="super_quiz_yonetimi.php" style="color:#fde68a">Quiz Yönetimi</a>
                        sayfasından soru eklenmeli. (Bu sayfaya Süper Admin erişebilir.)
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="baslik">📌 Arenaya Dahil Edilecek Bölümler</label>
                    <p style="color:#64748b; font-size:13px; margin:0 0 10px 0;">
                        Parantez içindeki sayı o bölümdeki soru adedidir. Sıfır olanları seçmeyin.
                        Öğrencileriniz şu an en fazla <b><?= (int) $enYuksekBolum ?>. bölümde</b>.
                    </p>
                    <div class="checkbox-grid">
                        <?php foreach ($bolumler as $b):
                            $adet = (int) $b['soru_sayisi'];
                            $bos  = ($adet === 0);
                        ?>
                            <label class="checkbox-label" style="<?= $bos ? 'opacity:0.45; cursor:not-allowed;' : '' ?>">
                                <input type="checkbox" name="bolumler[]" value="<?= (int) $b['id'] ?>"
                                       class="bolum-kutu" data-adet="<?= $adet ?>" <?= $bos ? 'disabled' : '' ?>>
                                <span>
                                    <?= (int) $b['sira_no'] ?>. <?= temizle($b['bolum_adi']) ?>
                                    <span style="color:<?= $bos ? '#ef4444' : '#94a3b8' ?>; font-weight:normal; font-size:12px;">
                                        (<?= $adet ?> soru)
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 30px;">
                    <label class="baslik">🔥 Canavarların Zorluk Seviyesi</label>

                    <?php if (empty($zorlukSeviyeleri)): ?>
                        <div class="bos-uyari">
                            Soru havuzunda hiç zorluk seviyesi tanımlı değil.
                        </div>
                    <?php else: ?>
                        <p style="color:#64748b; font-size:13px; margin:0 0 10px 0;">
                            Bu liste soru havuzundaki <b>gerçek</b> seviyelerden üretiliyor —
                            soru eklerken hangi kelimeyi yazdıysanız burada o görünür.
                        </p>
                        <div class="checkbox-grid">
                            <?php
                            $ikonlar = ['kolay' => '🟢', 'orta' => '🟡', 'zor' => '🔴', 'boss' => '💀'];
                            foreach ($zorlukSeviyeleri as $z):
                                $ad   = (string) $z['zorluk_seviyesi'];
                                $ikon = $ikonlar[mb_strtolower($ad, 'UTF-8')] ?? '⚔️';
                            ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="zorluk_seviyeleri[]"
                                           value="<?= temizle($ad) ?>" class="zorluk-kutu"
                                           data-adet="<?= (int) $z['adet'] ?>" checked>
                                    <span>
                                        <?= $ikon ?> <?= temizle($ad) ?>
                                        <span style="color:#94a3b8; font-weight:normal; font-size:12px;">
                                            (<?= (int) $z['adet'] ?>)
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-start">
                    ⚔️ SAVAŞI (QUİZİ) BAŞLAT
                </button>
            </form>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="card">
            <audio autoplay loop src="../assets/sounds/quizogretmenfon.mp3"></audio>
            <div class="radar-header">
                <div>
                    <h1 style="margin: 0; color: #22c55e;" class="pulse">🔴 CANLI SAVAŞ RADARI AKTİF</h1>
                    <p style="margin: 8px 0 0 0; color: #94a3b8;">
                        <span class="rozet-sinif">🎓 <?= temizle($aktif_oturum['sinif_adi'] ?? 'Sınıf') ?></span>
                        &nbsp; Sıralama saniyede bir güncelleniyor...
                    </p>
                </div>
                <form method="POST" action="quiz_kontrol_merkezi.php" style="display:inline"
                      data-onay="Savaşı bitirdiğiniz an kazananlar podyumu çıkar ve oyun sonlanır. Emin misiniz?">
                    <?= csrfAlani() ?>
                    <input type="hidden" name="islem" value="quiz_bitir">
                    <button type="submit" class="btn-stop" style="border:none;cursor:pointer;font:inherit">🛑 SAVAŞI BİTİR</button>
                </form>
            </div>

            <div id="leaderboardArea">
                <div style="text-align: center; padding: 50px; color: #94a3b8; font-size: 18px;">Öğrenciler Bekleniyor... 📡</div>
            </div>
        </div>

        <script>
            let oncekiPuanlar = {};

            // XSS koruması: öğrenci adı HTML olarak yorumlanmasın
            function kacir(metin) {
                const d = document.createElement('div');
                d.textContent = metin == null ? '' : String(metin);
                return d.innerHTML;
            }

            setInterval(() => {
                fetch('../api/quiz_canli_tablo.php?oturum_id=<?= (int) $aktif_oturum['id'] ?>')
                    .then(res => res.json())
                    .then(data => {
                        const area = document.getElementById('leaderboardArea');
                        if (!Array.isArray(data) || data.length === 0) return;

                        let html = '';
                        data.forEach((ogrenci, index) => {
                            let siraClass = '';
                            let madalya = `${index + 1}.`;

                            if (index === 0) { siraClass = 'rank-1'; madalya = '👑'; }
                            else if (index === 1) { siraClass = 'rank-2'; madalya = '🥈'; }
                            else if (index === 2) { siraClass = 'rank-3'; madalya = '🥉'; }

                            const adKey = ogrenci.ad_soyad;
                            let flashClass = '';
                            let artisYazisi = '';

                            if (oncekiPuanlar[adKey] !== undefined && ogrenci.puan > oncekiPuanlar[adKey]) {
                                flashClass = 'flash-green';
                                const fark = ogrenci.puan - oncekiPuanlar[adKey];
                                artisYazisi = `<span style="color:#4ade80; font-size:14px; position:absolute; right:-50px; top:10px;">(+${fark}) 🚀</span>`;
                            }
                            oncekiPuanlar[adKey] = ogrenci.puan;

                            html += `
                                <div class="player-row ${siraClass} ${flashClass}">
                                    <div class="rank-badge">${madalya}</div>
                                    <div class="player-name">${kacir(ogrenci.ad_soyad)}</div>
                                    <div class="player-stats">Çözülen: ${parseInt(ogrenci.cozulen_soru_sayisi) || 0}</div>
                                    <div class="player-score">${parseInt(ogrenci.puan) || 0} Puan ${artisYazisi}</div>
                                </div>
                            `;
                        });

                        area.innerHTML = html;
                    })
                    .catch(e => console.log("Radarda anlık kesinti..."));
            }, 1500);
        </script>
    <?php endif; ?>
</div>

</body>
</html>
