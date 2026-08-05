<?php
/* ==========================================================
   Dosya Yolu: api/satin_al.php
   İşlev: Markette elmasla eşya satın alma

   NE İŞE YARAR (HALK DİLİNDE):
   Öğrenci markette bir eşyaya tıkladığında elmasını düşen ve eşyayı
   hazine sandığına (envantere) ekleyen dosya.

   KURALLAR:
    - Para birimi ELMAS'tır (puan değil — puan sıralama için)
    - 'saldiri_troll' kategorisi tek kullanımlık, defalarca alınabilir
    - Diğer eşyalar öğrenci başına 1 kez alınabilir

   YENİ EKLENEN ÖZELLİKLER:
    - [2026-08] YARIŞ DURUMU (race condition) KAPATILDI:
        Eskiden bakiye okunup sonra düşülüyordu; hızlı arka arkaya istek
        gönderen bir öğrenci elmasından fazlasını harcayabiliyordu.
        Artık bakiye transaction içinde FOR UPDATE ile kilitleniyor ve
        elmas düşümü "elmas >= fiyat" şartıyla yapılıyor.
    - [2026-08] Güç önbelleği temizleniyor (yeni eşya savaş gücünü etkiler).
   ========================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';
require_once __DIR__ . '/../bilesenler/rpg_matris.php';

$ogrenci_id = ogrenciZorunluApi();

$veri    = istekVerisi();
$esya_id = isset($veri['esya_id']) ? (int) $veri['esya_id'] : 0;

if ($esya_id <= 0) {
    apiHata('Eşya seçilmedi.');
}

try {
    // 1. Eşyayı bul
    $st = $pdo->prepare("SELECT id, esya_adi, kategori, fiyat FROM esya_kutuphanesi WHERE id = ?");
    $st->execute([$esya_id]);
    $esya = $st->fetch(PDO::FETCH_ASSOC);

    if (!$esya) {
        apiHata('Böyle bir eşya markette bulunamadı.', 404);
    }

    $fiyat          = max(0, (int) $esya['fiyat']);
    $trollBuyusuMu  = ($esya['kategori'] === 'saldiri_troll');

    $pdo->beginTransaction();

    // 2. Bakiyeyi KİLİTLE (aynı anda gelen ikinci istek beklesin)
    $st = $pdo->prepare("SELECT elmas FROM ogrenciler WHERE id = ? FOR UPDATE");
    $st->execute([$ogrenci_id]);
    $mevcutElmas = (int) $st->fetchColumn();

    if ($mevcutElmas < $fiyat) {
        $pdo->rollBack();
        apiHata('Bu eşyayı almak için yeterli 💎 Elmasın yok!');
    }

    // 3. Tekrar alım kontrolü (troll büyüleri hariç)
    if (!$trollBuyusuMu) {
        $st = $pdo->prepare("SELECT id FROM ogrenci_envanter WHERE ogrenci_id = ? AND esya_id = ?");
        $st->execute([$ogrenci_id, $esya_id]);

        if ($st->fetch()) {
            $pdo->rollBack();
            apiHata('Bu eşyaya zaten sahipsin.');
        }
    }

    // 4. Elmasi tahsil et — şart cümlesi ek güvenlik katmanı
    $dus = $pdo->prepare("UPDATE ogrenciler SET elmas = elmas - ? WHERE id = ? AND elmas >= ?");
    $dus->execute([$fiyat, $ogrenci_id, $fiyat]);

    if ($dus->rowCount() === 0) {
        $pdo->rollBack();
        apiHata('Bakiyeniz bu işlem sırasında değişti, lütfen tekrar deneyin.');
    }

    // 5. Envantere ekle
    $pdo->prepare("INSERT INTO ogrenci_envanter (ogrenci_id, esya_id) VALUES (?, ?)")
        ->execute([$ogrenci_id, $esya_id]);

    $pdo->commit();

    // Yeni eşya savaş gücünü etkileyebilir → önbelleği düşür
    gucOnbelleginiTemizle($pdo, $ogrenci_id);

    apiJson([
        'success'           => true,
        'status'            => 'success',
        'kalan_elmas'       => $mevcutElmas - $fiyat,
        'tekrar_alinabilir' => $trollBuyusuMu,
        'mesaj'             => $esya['esya_adi'] . ' satın alındı!',
        'message'           => $esya['esya_adi'] . ' satın alındı!',
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiIstisna($e, 'Satın alma tamamlanamadı.');
}
