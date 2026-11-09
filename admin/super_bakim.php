<?php
/* ====================================================================
 * MODÜL: Süper Admin Bakım Merkezi
 * DOSYA YOLU: admin/super_bakim.php
 * KULLANICI: Süper Admin
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Eskiden market.php her açıldığında diskteki bütün eşya resimlerini tek tek
 * kontrol edip bulamadıklarını KALICI olarak siliyordu. Klasör bir kez
 * erişilemez olsa tüm eşya kütüphanesi uçabilirdi. O tehlikeli iş buradan,
 * elle ve önce "önizleme" göstererek yapılıyor.
 *
 * TEMEL İŞLEVLER:
 *  1. Kayıp resim taraması (önce RAPOR, sonra onaylı silme)
 *  2. Klasörden yeni eşya senkronizasyonu
 *  3. Savaş gücü önbelleğini yeniden hesaplama
 *  4. Eski savaş loglarını temizleme
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (Bilinen Sorunlar #9, #14).
 * ==================================================================== */

require_once __DIR__ . '/super_auth.php';
require_once __DIR__ . '/../bilesenler/rpg_matris.php';

superAdminZorunlu();

$mesaj = '';
$mesajTuru = 'basarili';
$rapor = [];

/** Eşya resminin diskteki tam yolunu verir. */
function esyaFizikselYol(string $resimYolu): string
{
    return dirname(__DIR__) . '/' . ltrim($resimYolu, '/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfDogrulaSayfa('super_bakim.php');
    $islem = $_POST['islem'] ?? '';

    try {
        /* ---- 1. KAYIP RESİM TARAMASI (sadece rapor, hiçbir şey silmez) ---- */
        if ($islem === 'kayip_tara') {
            $esyalar = $pdo->query("SELECT id, esya_adi, kategori, resim_yolu FROM esya_kutuphanesi")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($esyalar as $e) {
                if (!is_file(esyaFizikselYol($e['resim_yolu']))) {
                    $rapor[] = $e;
                }
            }
            $mesaj = count($rapor) > 0
                ? count($rapor) . ' adet eşyanın resmi diskte bulunamadı. Aşağıdaki listeyi kontrol edip silebilirsiniz.'
                : 'Harika — bütün eşya resimleri yerli yerinde. Silinecek bir şey yok.';
            $mesajTuru = count($rapor) > 0 ? 'dikkat' : 'basarili';
        }

        /* ---- 2. KAYIP EŞYALARI SİL (onaylı) ---- */
        elseif ($islem === 'kayip_sil') {
            $silinecek = $_POST['esya_idler'] ?? [];
            $silinen = 0;

            if (!empty($silinecek)) {
                $pdo->beginTransaction();
                foreach ($silinecek as $ham) {
                    $esyaId = (int) $ham;

                    // Avatar yuvalarından çıkar
                    foreach (AVATAR_YUVALARI as $yuva) {
                        $pdo->prepare("UPDATE ogrenci_avatar_durum SET {$yuva}_id = NULL WHERE {$yuva}_id = ?")
                            ->execute([$esyaId]);
                    }
                    $pdo->prepare("DELETE FROM ogrenci_envanter WHERE esya_id = ?")->execute([$esyaId]);
                    $pdo->prepare("DELETE FROM esya_kutuphanesi WHERE id = ?")->execute([$esyaId]);
                    $silinen++;
                }
                tumGucOnbelleginiTemizle($pdo);
                $pdo->commit();
            }
            $mesaj = "{$silinen} adet kayıp eşya sistemden temizlendi ve savaş güçleri yeniden hesaplanmak üzere işaretlendi.";
        }

        /* ---- 3. KLASÖRDEN YENİ EŞYA SENKRONİZASYONU ---- */
        elseif ($islem === 'senkronize') {
            $kategoriler = array_merge(AVATAR_YUVALARI, ['saldiri_troll']);
            $eklenen = 0;

            $mevcutYollar = array_map(
                'strtolower',
                $pdo->query("SELECT resim_yolu FROM esya_kutuphanesi")->fetchAll(PDO::FETCH_COLUMN)
            );

            foreach ($kategoriler as $kat) {
                $klasor = dirname(__DIR__) . "/img/esyalar/$kat/";
                if (!is_dir($klasor)) {
                    continue;
                }

                foreach (scandir($klasor) as $dosya) {
                    if ($dosya === '.' || $dosya === '..') {
                        continue;
                    }
                    $uzanti = strtolower(pathinfo($dosya, PATHINFO_EXTENSION));
                    if (!in_array($uzanti, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                        continue;
                    }

                    $dbYolu = "img/esyalar/$kat/$dosya";
                    if (in_array(strtolower($dbYolu), $mevcutYollar, true)) {
                        continue;
                    }

                    // Dosya adından okunabilir isim ve seviye türet
                    $hamIsim = pathinfo($dosya, PATHINFO_FILENAME);
                    $isim = preg_replace('/_\d+$/', '', $hamIsim);            // sondaki _58 gibi id'yi at
                    $isim = str_replace(['_', '-'], ' ', $isim);
                    $isim = preg_replace('/(\d)\s*\.?\s*seviye/i', '$1. Seviye', $isim);
                    $isim = mb_convert_case(trim($isim), MB_CASE_TITLE, 'UTF-8');

                    /* [2026-08] Seviye 5 ("Destansı") desteklendi.
                       Veritabanında zaten seviye=5 / fiyat=50000 olan eşyalar
                       vardı ama bu kod Destansı'yı 4'e düşürüyordu; senkronizasyon
                       çalıştırılınca fiyat/güç tutarsızlığı oluşuyordu. */
                    $fiyatTablosu = [1 => 100, 2 => 500, 3 => 2000, 4 => 20000, 5 => 50000];

                    $seviye = 1;
                    $fiyat  = 100;
                    if (preg_match('/(\d)\s*\.?\s*seviye/i', $hamIsim, $m)) {
                        $seviye = max(1, min(5, (int) $m[1]));
                        $fiyat  = $fiyatTablosu[$seviye];
                    }
                    if (stripos($hamIsim, 'destans') !== false) {
                        $seviye = 5;
                        $fiyat  = 50000;
                    }

                    $pdo->prepare(
                        "INSERT INTO esya_kutuphanesi (esya_adi, kategori, resim_yolu, fiyat, seviye, aciklama)
                         VALUES (?, ?, ?, ?, ?, 'Bakım merkezinden senkronize edildi')"
                    )->execute([$isim, $kat, $dbYolu, $fiyat, $seviye]);

                    $mevcutYollar[] = strtolower($dbYolu);
                    $eklenen++;
                }
            }
            $mesaj = "Senkronizasyon tamamlandı. {$eklenen} yeni eşya eklendi. "
                   . "(Dosyalar yeniden adlandırılmadı — mevcut kayıtlar bozulmaz.)";
        }

        /* ---- 4. SAVAŞ GÜCÜ ÖNBELLEĞİNİ YENİLE ---- */
        elseif ($islem === 'guc_yenile') {
            $ogrenciler = $pdo->query("SELECT id FROM ogrenciler")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ogrenciler as $oid) {
                gucuYenidenHesapla($pdo, (int) $oid);
            }
            $mesaj = count($ogrenciler) . ' öğrencinin savaş gücü yeniden hesaplandı ve önbelleğe yazıldı.';
        }

        /* ---- 5. ESKİ LOGLARI TEMİZLE ---- */
        elseif ($islem === 'log_temizle') {
            $a = $pdo->exec("DELETE FROM arena_savaslari WHERE tarih < NOW() - INTERVAL 30 DAY");
            $b = $pdo->exec("DELETE FROM quiz_savas_log WHERE islem_tarihi < NOW() - INTERVAL 30 DAY");
            $c = $pdo->exec("DELETE FROM quiz_duellolar  WHERE olusturma_tarihi < NOW() - INTERVAL 30 DAY AND durum = 'bitti'");
            $mesaj = "Temizlik tamam: {$a} arena, {$b} quiz saldırı, {$c} düello kaydı silindi.";
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mesaj = 'Hata: ' . $e->getMessage();
        $mesajTuru = 'hata';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Bakım Merkezi | Süper Admin</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 25px 20px 60px; }
        .sarmal { max-width: 860px; margin: 0 auto; }
        .ust { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .ust h1 { margin: 0; font-size: 22px; }
        .geri { background: #334155; color: #f8fafc; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: bold; }
        .kart { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 22px; margin-bottom: 18px; }
        .kart h2 { margin: 0 0 10px 0; font-size: 16px; }
        .kart p { color: #94a3b8; font-size: 13px; line-height: 1.6; margin: 0 0 15px 0; }
        .btn { padding: 10px 18px; border: none; border-radius: 8px; color: #fff; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; }
        .btn-mavi { background: #3b82f6; } .btn-mavi:hover { background: #2563eb; }
        .btn-yesil { background: #10b981; } .btn-yesil:hover { background: #059669; }
        .btn-turuncu { background: #f59e0b; } .btn-turuncu:hover { background: #d97706; }
        .btn-kirmizi { background: #ef4444; } .btn-kirmizi:hover { background: #dc2626; }
        .uyari { padding: 14px; border-radius: 8px; margin-bottom: 22px; font-weight: bold; font-size: 14px; }
        .uyari.basarili { background: #064e3b; color: #a7f3d0; border: 1px solid #059669; }
        .uyari.hata     { background: #7f1d1d; color: #fecaca; border: 1px solid #991b1b; }
        .uyari.dikkat   { background: #78350f; color: #fde68a; border: 1px solid #d97706; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
        th, td { padding: 9px 10px; text-align: left; border-bottom: 1px solid #334155; }
        th { color: #94a3b8; font-size: 12px; text-transform: uppercase; }
        code { background: #0f172a; padding: 2px 6px; border-radius: 4px; color: #fca5a5; font-size: 12px; }
    </style>
    <script src="<?= varlik('js/onay_kutusu.js', '../') ?>"></script>
</head>
<body>
<?php require __DIR__ . '/../bilesenler/super_gezinme.php'; ?>

<div class="sarmal">

    <div class="ust">
        <h1>🔧 Bakım Merkezi</h1>
        <a href="super_panel.php" class="geri">← Panele Dön</a>
    </div>

    <?php if ($mesaj !== ''): ?>
        <div class="uyari <?= $mesajTuru ?>"><?= temizle($mesaj) ?></div>
    <?php endif; ?>

    <?php if (!empty($rapor)): ?>
    <div class="kart" style="border-color:#d97706">
        <h2>⚠️ Resmi Bulunamayan Eşyalar</h2>
        <p>
            Aşağıdaki kayıtların resim dosyası diskte yok. Silmek isterseniz işaretleyip onaylayın.
            <b>Silme işlemi geri alınamaz</b> — öğrencilerin envanterinden ve avatarlarından da kaldırılır.
        </p>
        <form method="POST" data-onay="Seçili eşyalar kalıcı olarak silinecek. Emin misiniz?">
            <?= csrfAlani() ?>
            <input type="hidden" name="islem" value="kayip_sil">
            <table>
                <tr><th><input type="checkbox" onclick="document.querySelectorAll('.sec').forEach(c=>c.checked=this.checked)"></th>
                    <th>Eşya</th><th>Kategori</th><th>Beklenen Yol</th></tr>
                <?php foreach ($rapor as $r): ?>
                <tr>
                    <td><input type="checkbox" class="sec" name="esya_idler[]" value="<?= (int) $r['id'] ?>" checked></td>
                    <td><?= temizle($r['esya_adi']) ?></td>
                    <td><?= temizle($r['kategori']) ?></td>
                    <td><code><?= temizle($r['resim_yolu']) ?></code></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <button type="submit" class="btn btn-kirmizi" style="margin-top:15px">Seçilenleri Kalıcı Sil</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="kart">
        <h2>🔍 Kayıp Resim Taraması</h2>
        <p>
            Veritabanındaki her eşyanın resim dosyası diskte var mı diye kontrol eder.
            <b>Hiçbir şey silmez</b>, sadece rapor gösterir. Bu tarama eskiden market sayfası
            her açıldığında otomatik çalışıyor ve bulamadığını anında siliyordu — o davranış kaldırıldı.
        </p>
        <form method="POST">
            <?= csrfAlani() ?>
            <input type="hidden" name="islem" value="kayip_tara">
            <button type="submit" class="btn btn-turuncu">Taramayı Başlat</button>
        </form>
    </div>

    <div class="kart">
        <h2>📥 Klasörden Eşya Senkronizasyonu</h2>
        <p>
            <code>img/esyalar/&lt;kategori&gt;/</code> klasörlerine elle attığınız yeni resimleri
            veritabanına ekler. Dosya adındaki "3_seviye" gibi ifadelerden seviyeyi ve fiyatı
            otomatik çıkarır. Var olan kayıtlara dokunmaz, dosya adlarını değiştirmez.
        </p>
        <form method="POST">
            <?= csrfAlani() ?>
            <input type="hidden" name="islem" value="senkronize">
            <button type="submit" class="btn btn-yesil">Klasörleri Tara ve Ekle</button>
        </form>
    </div>

    <div class="kart">
        <h2>⚔️ Savaş Gücü Önbelleğini Yenile</h2>
        <p>
            Her öğrencinin eşyalarından gelen toplam savaş gücünü yeniden hesaplayıp
            <code>ogrenci_avatar_durum.genel_guc</code> sütununa yazar. Arena savaşları bu
            önbellekten okur — böylece her saldırıda sınıfın tamamı yeniden hesaplanmaz.
            Stat matrisini değiştirdiyseniz bunu bir kez çalıştırın.
        </p>
        <form method="POST">
            <?= csrfAlani() ?>
            <input type="hidden" name="islem" value="guc_yenile">
            <button type="submit" class="btn btn-mavi">Güçleri Yeniden Hesapla</button>
        </form>
    </div>

    <div class="kart">
        <h2>🧹 Eski Kayıtları Temizle</h2>
        <p>
            30 günden eski arena savaşlarını, quiz saldırı loglarını ve bitmiş düelloları siler.
            Veritabanını hafifletir; öğrencilerin puanına dokunmaz.
        </p>
        <form method="POST" data-onay="30 günden eski loglar silinecek. Devam?">
            <?= csrfAlani() ?>
            <input type="hidden" name="islem" value="log_temizle">
            <button type="submit" class="btn btn-turuncu">Eski Logları Sil</button>
        </form>
    </div>

</div>
</body>
</html>
