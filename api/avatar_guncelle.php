<?php
/* ==========================================================
   Dosya Yolu: api/avatar_guncelle.php
   İşlev: Öğrencinin avatarına eşya giydirir / çıkarır

   NE İŞE YARAR (HALK DİLİNDE):
   Profil sayfasında bir eşyayı karaktere sürükleyince ya da X'e basınca
   çalışan dosya. Eşyayı ilgili yuvaya takar veya çıkarır.

   YENİ EKLENEN ÖZELLİKLER:
    - [2026-08] SAHİPLİK KONTROLÜ (Bilinen Sorunlar #8):
        Eskiden envanterde olmayan bir eşyanın numarası gönderilerek
        satın alınmamış eşyalar giyilebiliyordu (ve savaş gücü artıyordu).
        Artık eşya gerçekten öğrencinin envanterinde ve doğru kategoride mi
        diye kontrol ediliyor.
    - [2026-08] Güç önbelleği temizleniyor (bilesenler/rpg_matris.php) — avatar
        değişince savaş gücü bir sonraki okumada yeniden hesaplanır (#14).
   ========================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';
require_once __DIR__ . '/../bilesenler/rpg_matris.php';

$ogrenci_id = ogrenciZorunluApi();

$veri     = istekVerisi();
$kategori = beyazListe($veri['kategori'] ?? '', AVATAR_YUVALARI);   // SQL'e gömülüyor → beyaz liste şart
$islem    = beyazListe($veri['islem'] ?? '', ['giy', 'cikart']);
$esya_id  = isset($veri['esya_id']) ? (int) $veri['esya_id'] : 0;

if (!$kategori || !$islem) {
    apiHata('Geçersiz eşya yuvası veya işlem.');
}

try {
    // Avatar satırı yoksa oluştur
    $pdo->prepare("INSERT IGNORE INTO ogrenci_avatar_durum (ogrenci_id) VALUES (?)")->execute([$ogrenci_id]);

    if ($islem === 'giy') {
        if ($esya_id <= 0) {
            apiHata('Eşya seçilmedi.');
        }

        // SAHİPLİK + KATEGORİ DOĞRULAMASI
        $st = $pdo->prepare(
            "SELECT e.id
               FROM ogrenci_envanter oe
               JOIN esya_kutuphanesi e ON e.id = oe.esya_id
              WHERE oe.ogrenci_id = ? AND e.id = ? AND e.kategori = ?
              LIMIT 1"
        );
        $st->execute([$ogrenci_id, $esya_id, $kategori]);

        if (!$st->fetch()) {
            apiHata('Bu eşyaya sahip değilsin ya da bu yuvaya takılamaz.', 403);
        }

        $pdo->prepare("UPDATE ogrenci_avatar_durum SET {$kategori}_id = ? WHERE ogrenci_id = ?")
            ->execute([$esya_id, $ogrenci_id]);
    } else {
        $pdo->prepare("UPDATE ogrenci_avatar_durum SET {$kategori}_id = NULL WHERE ogrenci_id = ?")
            ->execute([$ogrenci_id]);
    }

    // Savaş gücü değişti → önbelleği düşür
    gucOnbelleginiTemizle($pdo, $ogrenci_id);

    apiBasari([], $islem === 'giy' ? 'Eşya kuşanıldı.' : 'Eşya çıkarıldı.');

} catch (Throwable $e) {
    apiIstisna($e, 'Avatar güncellenemedi.');
}
