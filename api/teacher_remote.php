<?php
/* ==========================================================
   Dosya Yolu: api/teacher_remote.php
   Kullanıcı: Öğretmen (ogretmen_paneli.php, ogrenci_yonetimi.php)
   Halk dilinde: Öğretmenin sınıf/öğrenci listesini getirir; öğrenci onaylama,
   düzenleme, silme, sınıf ekleme/silme gibi uzaktan işlemleri yapar.

   YENİ EKLENEN ÖZELLİKLER:
   - [2026-11] Kapıcı ogretmenZorunluApi() (rol damgası + köken kontrolü).
   - [2026-11] SAHİPLİK ZİNCİRİ: bütün yazma işlemleri artık
     `ogrenciler.ogretmen_id` (bayat kopya) yerine
     ogretmenler → siniflar → ogrenciler zinciriyle doğrulanır.
   - [2026-11] DEĞİŞTİREN işlemler SADECE POST ile çalışır. Eskiden
     ?islem=ogrenci_sil&id=5 gibi bir bağlantıya tıklatmak öğrenci
     sildirmeye yetiyordu (CSRF).
   - [2026-11] Ad / kullanıcı adı / sınıf adı metinSadelestir() ile temizlenir.
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';

$ogretmen_id = ogretmenZorunluApi();
header('Content-Type: application/json; charset=utf-8');

function tcCevap(array $veri): void
{
    echo json_encode($veri, JSON_UNESCAPED_UNICODE);
    exit;
}

$islem = (string) ($_GET['islem'] ?? 'liste');
$govde = istekVerisi();
$ogrenci_id = (int) ($_GET['id'] ?? ($govde['id'] ?? 0));

$OKUMA_ISLEMLERI = ['liste', 'siniflari_getir'];
$YAZMA_ISLEMLERI = ['sinif_ekle', 'kayit_onayla', 'kilit_ac', 'onayla', 'reddet_ac',
                    'ogrenci_sil', 'ogrenci_duzenle', 'sinif_sil'];

if (!in_array($islem, array_merge($OKUMA_ISLEMLERI, $YAZMA_ISLEMLERI), true)) {
    tcCevap(["success" => false, "message" => "Bilinmeyen işlem."]);
}
if (in_array($islem, $YAZMA_ISLEMLERI, true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    tcCevap(["success" => false, "message" => "Bu işlem sadece POST ile yapılabilir."]);
}

// "Bu öğrenci benim sınıflarımdan birinde mi?" — tek doğru zincir
const BENIM_OGRENCIM = "sinif_id IN (SELECT id FROM siniflar WHERE ogretmen_id = ?)";

try {
    // 1. YENİ SINIF OLUŞTURMA
    if ($islem === 'sinif_ekle') {
        $sinif_adi = metinSadelestir($govde['sinif_adi'] ?? ($_GET['sinif_adi'] ?? ''), 50);
        if ($sinif_adi === '') {
            tcCevap(["success" => false, "message" => "Sınıf adı boş olamaz."]);
        }
        $pdo->prepare("INSERT INTO siniflar (ogretmen_id, sinif_adi) VALUES (?, ?)")
            ->execute([$ogretmen_id, $sinif_adi]);
        tcCevap(["success" => true]);
    }

    // 2. ÖĞRETMENİN SINIFLARI (Dropdown için)
    if ($islem === 'siniflari_getir') {
        $sorgu = $pdo->prepare("SELECT id, sinif_adi FROM siniflar WHERE ogretmen_id = ? ORDER BY sinif_adi ASC");
        $sorgu->execute([$ogretmen_id]);
        tcCevap(["success" => true, "data" => $sorgu->fetchAll()]);
    }

    // 3. ÖĞRENCİ LİSTESİ (sadece bu öğretmenin sınıfları)
    if ($islem === 'liste') {
        $sinif_id = (int) ($_GET['sinif_id'] ?? 0);

        $sql = "SELECT o.*, s.sinif_adi
                  FROM ogrenciler o
                  JOIN siniflar s ON s.id = o.sinif_id
                 WHERE s.ogretmen_id = ?";
        $params = [$ogretmen_id];
        if ($sinif_id > 0) {
            $sql .= " AND o.sinif_id = ?";
            $params[] = $sinif_id;
        }
        $sql .= " ORDER BY o.kalici_puan DESC";
        $sorgu = $pdo->prepare($sql);
        $sorgu->execute($params);

        $onayKontrol = $pdo->prepare(
            "SELECT o.id FROM ogrenciler o
               JOIN siniflar s ON s.id = o.sinif_id
              WHERE s.ogretmen_id = ? AND o.onay_durumu = 0 LIMIT 1"
        );
        $onayKontrol->execute([$ogretmen_id]);

        tcCevap(["success" => true, "data" => $sorgu->fetchAll(),
                 "bekleyen_onay_var" => (bool) $onayKontrol->fetch()]);
    }

    // --- Tek öğrenci üzerinde yazma işlemleri ---
    $tekOgrenciSql = [
        'kayit_onayla' => "UPDATE ogrenciler SET onay_durumu = 1",
        'kilit_ac'     => "UPDATE ogrenciler SET durum = 'onay_bekliyor'",
        'onayla'       => "UPDATE ogrenciler SET kalici_puan = kalici_puan + anlik_puan, anlik_puan = 0, durum = 'uzaktan_onaylandi'",
        'reddet_ac'    => "UPDATE ogrenciler SET anlik_puan = 0, durum = 'uzaktan_reddedildi'",
        'ogrenci_sil'  => "DELETE FROM ogrenciler",
    ];
    if (isset($tekOgrenciSql[$islem])) {
        if ($ogrenci_id <= 0) {
            tcCevap(["success" => false, "message" => "Öğrenci seçilmedi."]);
        }
        $st = $pdo->prepare($tekOgrenciSql[$islem] . " WHERE id = ? AND " . BENIM_OGRENCIM);
        $st->execute([$ogrenci_id, $ogretmen_id]);
        tcCevap(["success" => $st->rowCount() > 0 || $islem !== 'ogrenci_sil',
                 "message" => $st->rowCount() > 0 ? '' : 'Öğrenci bulunamadı ya da sizin sınıfınızda değil.']);
    }

    // ÖĞRENCİ DÜZENLEME (kalem ikonu)
    if ($islem === 'ogrenci_duzenle') {
        $ad    = metinSadelestir($govde['ad_soyad'] ?? '', 60);
        $kadi  = metinSadelestir($govde['kullanici_adi'] ?? '', 40);
        $sifre = trim((string) ($govde['sifre'] ?? ''));

        if ($ad === '' || $kadi === '' || $sifre === '') {
            tcCevap(["success" => false, "message" => "Ad, kullanıcı adı ve şifre boş olamaz."]);
        }

        // Öğrenci benim mi?
        $st = $pdo->prepare("SELECT id FROM ogrenciler WHERE id = ? AND " . BENIM_OGRENCIM);
        $st->execute([$ogrenci_id, $ogretmen_id]);
        if (!$st->fetch()) {
            tcCevap(["success" => false, "message" => "Öğrenci bulunamadı ya da sizin sınıfınızda değil."]);
        }

        // Kendisi hariç bu öğretmenin sınıflarında aynı kullanıcı adı var mı?
        $k = $pdo->prepare("SELECT id FROM ogrenciler WHERE kullanici_adi = ? AND id != ? AND " . BENIM_OGRENCIM);
        $k->execute([$kadi, $ogrenci_id, $ogretmen_id]);
        if ($k->fetch()) {
            tcCevap(["success" => false, "message" => "Bu kullanıcı adı zaten başka birinde kullanılıyor!"]);
        }

        $pdo->prepare("UPDATE ogrenciler SET ad_soyad = ?, kullanici_adi = ?, sifre = ? WHERE id = ? AND " . BENIM_OGRENCIM)
            ->execute([$ad, $kadi, $sifre, $ogrenci_id, $ogretmen_id]);
        tcCevap(["success" => true]);
    }

    // ÇOKLU SINIF VE İÇİNDEKİ ÖĞRENCİLERİ SİLME
    if ($islem === 'sinif_sil') {
        $liste = array_values(array_filter(array_map('intval', (array) ($govde['sinif_id_listesi'] ?? [])), fn($x) => $x > 0));
        if (!$liste) {
            tcCevap(["success" => false, "message" => "Silinecek sınıf seçilmedi."]);
        }

        // Sadece bu öğretmene ait olanları ayıkla
        $yer = implode(',', array_fill(0, count($liste), '?'));
        $st  = $pdo->prepare("SELECT id FROM siniflar WHERE id IN ($yer) AND ogretmen_id = ?");
        $st->execute(array_merge($liste, [$ogretmen_id]));
        $benimkiler = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

        if ($benimkiler) {
            $yer = implode(',', array_fill(0, count($benimkiler), '?'));
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM ogrenciler WHERE sinif_id IN ($yer)")->execute($benimkiler);
            $pdo->prepare("DELETE FROM siniflar WHERE id IN ($yer) AND ogretmen_id = ?")
                ->execute(array_merge($benimkiler, [$ogretmen_id]));
            $pdo->commit();
        }
        tcCevap(["success" => true]);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[teacher_remote] ' . $e->getMessage());
    tcCevap(["success" => false, "message" => "Veritabanı hatası!"]);
}
