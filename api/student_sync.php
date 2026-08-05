<?php
/* ==========================================================
   Dosya Yolu: api/student_sync.php
   İşlev: Anlık puanı yazar, öğrenci durumunu günceller, uzaktan yanıtı dinler

   NE İŞE YARAR (HALK DİLİNDE):
   Öğrenci görev çözdükçe biriken "bekleyen puanı" veritabanına yazar ve
   öğretmen uzaktan onay/red verdiğinde bunu öğrencinin ekranına bildirir.

   YENİ EKLENEN ÖZELLİKLER:
    - [2026-08] KİMLİK TAKLİDİ ENGELLENDİ (Bilinen Sorunlar #8):
        Eskiden ogrenci_id istek gövdesinden okunuyordu; bir öğrenci
        başkasının numarasını yazarak onun hesabını KİLİTLEYEBİLİYOR ya da
        puanını değiştirebiliyordu. Artık kimlik SADECE oturumdan alınır.
    - [2026-08] Anlık puan, bölümdeki toplam görev puanına kırpılıyor (hile freni).
   ========================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();   // Kimlik yalnızca oturumdan!

/** Öğrencinin bulunduğu bölümdeki toplam görev puanı = anlık puan tavanı */
function anlikPuanTavani(PDO $pdo, int $ogrenciId): int
{
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(g.puan), 0)
           FROM ogrenciler o
           JOIN gorevler g ON g.bolum_id = o.mevcut_bolum
          WHERE o.id = ?"
    );
    $st->execute([$ogrenciId]);
    return max(50, (int) $st->fetchColumn());   // boş bölümde de kilitlenmesin
}

try {
    /* ---- GET: Durum dinleme ---- */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (($_GET['islem'] ?? '') !== 'dinle') {
            apiHata('Geçersiz işlem.');
        }

        $st = $pdo->prepare("SELECT durum FROM ogrenciler WHERE id = ?");
        $st->execute([$ogrenci_id]);
        $durum = $st->fetchColumn() ?: 'aktif';

        // Kilit açıldıysa arka plandaki hata sayacını sıfırla
        if ($durum !== 'kilitli') {
            unset($_SESSION['hata_sayaci']);
        }

        apiJson(['success' => true, 'durum' => $durum]);
    }

    /* ---- POST: Durum / puan güncelleme ---- */
    $veri  = istekVerisi();
    $islem = $veri['islem'] ?? '';

    if ($islem === 'anlik_puan_guncelle') {
        $puan = max(0, min((int) ($veri['puan'] ?? 0), anlikPuanTavani($pdo, $ogrenci_id)));
        $pdo->prepare("UPDATE ogrenciler SET anlik_puan = ? WHERE id = ?")->execute([$puan, $ogrenci_id]);
        apiJson(['success' => true, 'puan' => $puan]);
    }

    if ($islem === 'onay_bekliyor_yap') {
        $puan = max(0, min((int) ($veri['anlik_puan'] ?? 0), anlikPuanTavani($pdo, $ogrenci_id)));
        $pdo->prepare("UPDATE ogrenciler SET durum = 'onay_bekliyor', anlik_puan = ? WHERE id = ?")
            ->execute([$puan, $ogrenci_id]);
        apiJson(['success' => true]);
    }

    if ($islem === 'kilitle') {
        $pdo->prepare("UPDATE ogrenciler SET durum = 'kilitli' WHERE id = ?")->execute([$ogrenci_id]);
        apiJson(['success' => true]);
    }

    apiHata('Geçersiz işlem.');

} catch (Throwable $e) {
    apiIstisna($e, 'İşlem tamamlanamadı.');
}
