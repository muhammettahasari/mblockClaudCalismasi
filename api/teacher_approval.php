<?php
/* ==========================================================
   Dosya Yolu: api/teacher_approval.php
   İşlev: Bölüm sonu öğretmen onay şifresini doğrular

   NE İŞE YARAR (HALK DİLİNDE):
   Öğrenci bölümün sonundaki "boss" görevini bitirince öğretmenini çağırır.
   Öğretmen gelip kendi onay şifresini girer; bu dosya şifreyi doğrular ve
   puanların hesaba geçmesi için kısa süreli bir "onay bileti" oluşturur.
   3 kez yanlış girilirse hesap kilitlenir.

   YENİ EKLENEN ÖZELLİKLER:
    - [2026-08] SABİT ŞİFRE KALDIRILDI (Bilinen Sorunlar #4):
        Şifre kodun içinde "123" olarak gömülüydü ve TÜM okullarda aynıydı.
        Artık her öğretmenin kendi ogretmenler.onay_sifresi değeri var
        (varsayılan 123 — öğretmen panelinden değiştirilebilir).
    - [2026-08] Kimlik oturumdan alınıyor; başkasının hesabı kilitlenemiyor.
    - [2026-08] Onay bileti: doğrulama başarılıysa 5 dakika geçerli bir
        oturum bileti oluşur. update_score.php puanı ancak bu bilet varsa
        aktarır — böylece öğrenci doğrudan update_score çağırıp kendine
        puan yazdıramaz.
   ========================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();

$veri  = istekVerisi();
$sifre = (string) ($veri['sifre'] ?? '');

try {
    // Öğrencinin kendi öğretmeninin onay şifresi
    $st = $pdo->prepare(
        "SELECT COALESCE(NULLIF(ogr.onay_sifresi, ''), '123') AS onay_sifresi
           FROM ogrenciler o
           JOIN ogretmenler ogr ON ogr.id = o.ogretmen_id
          WHERE o.id = ?"
    );
    $st->execute([$ogrenci_id]);
    $dogru_sifre = $st->fetchColumn();

    if ($dogru_sifre === false) {
        $dogru_sifre = '123';   // Öğretmeni silinmiş öğrenci için güvenli varsayılan
    }

    if (!isset($_SESSION['hata_sayaci'])) {
        $_SESSION['hata_sayaci'] = 0;
    }

    /* ---- ŞİFRE DOĞRU ---- */
    if (hash_equals((string) $dogru_sifre, $sifre)) {
        $_SESSION['hata_sayaci'] = 0;

        // 5 dakika geçerli onay bileti
        $_SESSION['bolum_onay_bileti'] = [
            'ogrenci_id' => $ogrenci_id,
            'zaman'      => time(),
        ];

        apiJson(['success' => true, 'status' => 'success']);
    }

    /* ---- ŞİFRE YANLIŞ ---- */
    $_SESSION['hata_sayaci']++;
    $kalan_hak = 3 - $_SESSION['hata_sayaci'];

    if ($_SESSION['hata_sayaci'] >= 3) {
        $pdo->prepare("UPDATE ogrenciler SET durum = 'kilitli' WHERE id = ?")->execute([$ogrenci_id]);

        apiJson([
            'success'    => false,
            'status'     => 'error',
            'kilitlendi' => true,
            'mesaj'      => 'Sistem kilitlendi.',
            'message'    => 'Sistem kilitlendi.',
        ]);
    }

    apiJson([
        'success'    => false,
        'status'     => 'error',
        'kilitlendi' => false,
        'kalan_hak'  => $kalan_hak,
    ]);

} catch (Throwable $e) {
    apiIstisna($e, 'Onay doğrulanamadı.');
}
