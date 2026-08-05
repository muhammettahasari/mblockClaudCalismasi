<?php
/* ==========================================================
   Dosya Yolu: api/update_score.php
   İşlev: Öğretmen onayı sonrası puan + elmas ekler, can/enerjiyi doldurur

   NE İŞE YARAR (HALK DİLİNDE):
   Bölüm sonunda öğretmen şifresini doğru girdiyse, öğrencinin bekleyen
   puanı kalıcı puanına ve elmas kasasına aktarılır; can ve enerji dolar.

   YENİ EKLENEN ÖZELLİKLER:
    - [2026-08] KRİTİK AÇIK KAPATILDI (Bilinen Sorunlar #8):
        Eskiden bu dosya ogrenci_id'yi istek gövdesinden alıyor ve HİÇBİR
        doğrulama yapmıyordu. Bir öğrenci tarayıcı konsolundan
        {"islem":"onayla","ogrenci_id":X} göndererek öğretmen onayı olmadan
        kendine (hatta başkasına) puan ve elmas yazdırabiliyordu.
        Artık:
          (a) kimlik oturumdan alınır,
          (b) teacher_approval.php'nin verdiği 5 dakikalık ONAY BİLETİ şart,
          (c) bilet tek kullanımlıktır (kullanılınca silinir).
    - [2026-08] Bölüm seviyesi de burada artırılıyor; bolum_bitir.php ile
        oluşan çift akış tekleştirildi.
   ========================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';
require_once __DIR__ . '/../bilesenler/rpg_matris.php';

$ogrenci_id = ogrenciZorunluApi();

$veri  = istekVerisi();
$islem = $veri['islem'] ?? '';

/** Onay bileti geçerli mi? (teacher_approval.php tarafından oluşturulur) */
function onayBiletiGecerliMi(int $ogrenciId): bool
{
    $bilet = $_SESSION['bolum_onay_bileti'] ?? null;

    return is_array($bilet)
        && (int) ($bilet['ogrenci_id'] ?? 0) === $ogrenciId
        && (time() - (int) ($bilet['zaman'] ?? 0)) <= 300;   // 5 dakika
}

try {
    /* ---- ONAYLA ---- */
    if ($islem === 'onayla') {

        if (!onayBiletiGecerliMi($ogrenci_id)) {
            apiHata('Öğretmen onayı bulunamadı ya da süresi doldu. Lütfen öğretmeninizi çağırıp şifreyi tekrar girdirin.', 403);
        }

        $pdo->beginTransaction();

        // Bekleyen puanı oku (log için)
        $st = $pdo->prepare("SELECT anlik_puan, mevcut_bolum FROM ogrenciler WHERE id = ? FOR UPDATE");
        $st->execute([$ogrenci_id]);
        $ogr = $st->fetch(PDO::FETCH_ASSOC);

        if (!$ogr) {
            $pdo->rollBack();
            apiHata('Öğrenci bulunamadı.', 404);
        }

        $kazanilan = (int) $ogr['anlik_puan'];
        $bitenBolum = (int) $ogr['mevcut_bolum'];

        // Puan + elmas aktar, can/enerji doldur, bir sonraki bölüme geç
        $pdo->prepare(
            "UPDATE ogrenciler
                SET kalici_puan  = kalici_puan + anlik_puan,
                    elmas        = elmas + anlik_puan,
                    can          = 100,
                    enerji       = 100,
                    anlik_puan   = 0,
                    mevcut_bolum = mevcut_bolum + 1,
                    durum        = 'aktif'
              WHERE id = ?"
        )->execute([$ogrenci_id]);

        // Arşiv kaydı (bolum_bitir.php'deki bozuk INSERT'in doğru hâli)
        $pdo->prepare(
            "INSERT INTO ogrenci_ilerleme (ogrenci_id, bolum_id, kaynak, durum, bekleyen_puan)
             VALUES (?, ?, 'v1', 'onaylandi', ?)"
        )->execute([$ogrenci_id, $bitenBolum, $kazanilan]);

        $pdo->commit();

        // Bilet tek kullanımlık
        unset($_SESSION['bolum_onay_bileti'], $_SESSION['hata_sayaci']);

        apiBasari(
            ['kazanilan_puan' => $kazanilan, 'yeni_bolum' => $bitenBolum + 1],
            'Görev başarıyla onaylandı! Puanlar ve Elmaslar hesaba eklendi, Can/Enerji dolduruldu.'
        );
    }

    /* ---- REDDET / BAŞA SAR ---- */
    if ($islem === 'reddet_basa_sar') {
        // Bu işlem puan VERMEZ, sadece sıfırlar — bilet gerekmez
        $pdo->prepare("UPDATE ogrenciler SET anlik_puan = 0, durum = 'aktif' WHERE id = ?")
            ->execute([$ogrenci_id]);

        unset($_SESSION['bolum_onay_bileti'], $_SESSION['hata_sayaci']);

        apiBasari([], 'Bölüm başa sarıldı.');
    }

    apiHata('Geçersiz işlem.');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiIstisna($e, 'Puan aktarılamadı.');
}
