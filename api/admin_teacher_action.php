<?php
/* ============================================================================
   DOSYA YOLU: api/admin_teacher_action.php
   KULLANICI / SAYFA: Süper Admin -> admin/ogretmen_yonetimi.php

   NE İŞE YARAR (HALK DİLİNDE):
   Öğretmen başvurusunu onaylama, PIN üretme, abonelik süresini uzatma/azaltma,
   aboneliği iptal etme, şifre değiştirme ve kaydı silme işlemlerinin motoru.

   DESTEKLENEN İŞLEMLER:
     onayla           Başvuruyu onayla + PIN üret + süre ver
     uzat             Mevcut bitişe ay EKLE
     sure_azalt       Mevcut bitişten ay DÜŞ (kısmi iade)
     aboneligi_bitir  Süreyi hemen sonlandır — VERİ SİLİNMEZ
     sifre_degistir   Giriş şifresini değiştir
     sil              Kaydı kalıcı olarak sil

   ⚠️ [2026-08] TARİH KARŞILAŞTIRMASI HAKKINDA ÖNEMLİ NOT
   api/teacher_login.php girişte SADECE TARİHE bakar (iki tarafa da
   setTime(0,0,0) uygular), saate bakmaz. Yani bitiş tarihi "bugün" olan
   öğretmen bugün HÂLÂ GİREBİLİR. Bu yüzden aboneliği anında kesmek için
   tarihi DÜNE çekiyoruz — "bugün" yazmak işe yaramaz.

   GÜVENLİK: [2026-08] Süper Admin oturumu zorunlu hale getirildi (#1).
   ========================================================================= */
require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';
superAdminZorunluApi();
header('Content-Type: application/json; charset=utf-8');

$veri = json_decode(file_get_contents("php://input"), true);
$islem = $veri['islem'] ?? '';
$ogretmen_id = (int)($veri['id'] ?? 0);
$ay = (int)($veri['ay'] ?? 1); // Öğretmenin (veya adminin) formdan talep ettiği ay

// Öğretmenin içerideki kalan günleri yanmasın diye ilk onayda "Hediye 1 Ay"
$hesaplanacak_ay = $ay + 1;

/** Girişi anında engelleyen tarih (bkz. yukarıdaki not — "bugün" yetmez) */
function engelliTarih(): string
{
    return date('Y-m-d H:i:s', strtotime('-1 day'));
}

/** Öğretmenin mevcut bitiş tarihini okur; yoksa null döner */
function mevcutBitis(PDO $pdo, int $ogretmenId): ?string
{
    $st = $pdo->prepare("SELECT bitis_tarihi FROM ogretmenler WHERE id = ?");
    $st->execute([$ogretmenId]);
    $deger = $st->fetchColumn();
    return $deger ?: null;
}

// Rastgele 4 haneli Benzersiz PIN Üretici
function pinUret($pdo) {
    $karakterler = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    while (true) {
        $pin = '';
        for ($i = 0; $i < 4; $i++) {
            $pin .= $karakterler[rand(0, strlen($karakterler) - 1)];
        }
        $kontrol = $pdo->prepare("SELECT id FROM ogretmenler WHERE pin_kodu = ?");
        $kontrol->execute([$pin]);
        if ($kontrol->rowCount() == 0) return $pin;
    }
}

try {
    // A) ÖĞRETMENİ ONAYLA (ESKİ PIN KORUMALI VE HEDİYE AYLIKLI)
    if ($islem === 'onayla') {
        
        // Önce öğretmenin halihazırda bir PIN kodu var mı diye kontrol et (Öğrencileri silinmesin diye)
        $pin_kontrol = $pdo->prepare("SELECT pin_kodu FROM ogretmenler WHERE id = ?");
        $pin_kontrol->execute([$ogretmen_id]);
        $mevcut_ogretmen = $pin_kontrol->fetch();

        $kullanilacak_pin = $mevcut_ogretmen['pin_kodu'] ?? '';

        // Eğer PIN kodu yoksa (yani sisteme ilk defa giriyorsa) yeni PIN üret
        if (empty($kullanilacak_pin)) {
            $kullanilacak_pin = pinUret($pdo);
        }

        // Hediye ayla birlikte yeni bitiş tarihini hesapla
        $bitis_tarihi = date('Y-m-d H:i:s', strtotime("+$hesaplanacak_ay months")); 
        
        // Öğretmeni onayla, PIN'ini kaydet ve "Talep Edilen Ay" bildirimini sıfırla
        $sorgu = $pdo->prepare("UPDATE ogretmenler SET durum = 'onaylandi', pin_kodu = ?, bitis_tarihi = ?, talep_edilen_ay = 0 WHERE id = ?");
        $sorgu->execute([$kullanilacak_pin, $bitis_tarihi, $ogretmen_id]);
        
        echo json_encode(["success" => true, "message" => "Öğretmen Onaylandı! Talep Edilen: $ay Ay | Hediye ile Verilen: $hesaplanacak_ay Ay"]);
    } 
    
    /* B) SÜREYİ UZAT
     *
     * ⚠️ [2026-08 HATA DÜZELTMESİ] Burada eskiden şu vardı:
     *        $bitis_tarihi = strtotime("+$hesaplanacak_ay months")
     *    Yani yeni bitiş NOW()'dan hesaplanıyordu, mevcut bitişe EKLENMİYORDU.
     *    Sonuç: 10 ayı kalan öğretmene 1 ay "eklediğinde" öğretmen 9 ay
     *    KAYBEDİYORDU. Düğmenin adı "Süreyi Ekle" ama yaptığı iş sıfırlamaktı.
     *
     *    Doğrusu: yeni bitiş = (kalan süre varsa mevcut bitiş, yoksa bugün) + ay
     *    Süresi çoktan dolmuş öğretmene eklerken geçmişten değil BUGÜNDEN
     *    başlıyoruz; yoksa eklenen ay geçmişte erir.
     */
    elseif ($islem === 'uzat') {

        if ($ay < 1) {
            echo json_encode(["success" => false, "message" => "Eklenecek ay en az 1 olmalı."]);
            exit;
        }

        $mevcut = mevcutBitis($pdo, $ogretmen_id);
        $baslangic = ($mevcut && strtotime($mevcut) > time()) ? strtotime($mevcut) : time();

        $bitis_tarihi = date('Y-m-d H:i:s', strtotime("+$ay months", $baslangic));

        $sorgu = $pdo->prepare("UPDATE ogretmenler SET durum = 'onaylandi', bitis_tarihi = ?, talep_edilen_ay = 0 WHERE id = ?");
        $sorgu->execute([$bitis_tarihi, $ogretmen_id]);

        echo json_encode([
            "success" => true,
            "message" => "$ay ay eklendi. Yeni bitiş: " . date('d.m.Y', strtotime($bitis_tarihi))
        ]);
    }

    /* B2) SÜREYİ AZALT — kısmi iade
     *
     * Senaryo: Öğretmen 15 ay aldı, 3 ay kullandı, kalanın parasını geri
     * istiyor. 12 ay düşülür; geçmişe taşarsa abonelik hemen kapanır.
     * HİÇBİR VERİ SİLİNMEZ — PIN, sınıflar, öğrenciler, puanlar yerinde kalır.
     */
    elseif ($islem === 'sure_azalt') {

        if ($ay < 1) {
            echo json_encode(["success" => false, "message" => "Düşülecek ay en az 1 olmalı."]);
            exit;
        }

        $mevcut = mevcutBitis($pdo, $ogretmen_id);
        if (!$mevcut) {
            echo json_encode(["success" => false, "message" => "Öğretmenin bitiş tarihi zaten yok."]);
            exit;
        }

        $yeni = strtotime("-$ay months", strtotime($mevcut));

        // Geçmişe taştıysa aboneliği kapat (düne çek — bkz. dosya başındaki not)
        $kapandi = ($yeni <= time());
        $bitis_tarihi = $kapandi ? engelliTarih() : date('Y-m-d H:i:s', $yeni);

        $sorgu = $pdo->prepare("UPDATE ogretmenler SET bitis_tarihi = ? WHERE id = ?");
        $sorgu->execute([$bitis_tarihi, $ogretmen_id]);

        echo json_encode([
            "success" => true,
            "message" => $kapandi
                ? "$ay ay düşüldü. Kalan süre bitti, abonelik KAPANDI. Verilerin hepsi duruyor."
                : "$ay ay düşüldü. Yeni bitiş: " . date('d.m.Y', strtotime($bitis_tarihi))
        ]);
    }

    /* B3) ABONELİĞİ HEMEN BİTİR — veri silinmez
     *
     * "Öğretmen 15 ay aldı, vazgeçti, parasını geri istiyor" durumu.
     * Kaydı silmek yanlış olurdu: PIN'i, sınıfları, öğrencilerin puanları,
     * çözdüğü görevler hepsi gider ve geri dönüşü olmaz. Bunun yerine sadece
     * bitiş tarihini düne çekiyoruz. Öğretmen giriş yapamaz ama parasını
     * yatırıp geri dönerse "Süreyi Ekle" ile kaldığı yerden devam eder.
     */
    elseif ($islem === 'aboneligi_bitir') {

        $bitis_tarihi = engelliTarih();

        $sorgu = $pdo->prepare("UPDATE ogretmenler SET bitis_tarihi = ?, talep_edilen_ay = 0 WHERE id = ?");
        $sorgu->execute([$bitis_tarihi, $ogretmen_id]);

        if ($sorgu->rowCount() === 0) {
            $kontrol = $pdo->prepare("SELECT id FROM ogretmenler WHERE id = ?");
            $kontrol->execute([$ogretmen_id]);
            if (!$kontrol->fetch()) {
                echo json_encode(["success" => false, "message" => "Öğretmen bulunamadı."]);
                exit;
            }
        }

        echo json_encode([
            "success" => true,
            "message" => "Abonelik kapatıldı. Öğretmen giriş yapamaz.\n"
                       . "Hiçbir veri silinmedi — PIN, sınıflar, öğrenciler ve puanlar duruyor.\n"
                       . "Yeniden açmak için 'Süre Ekle' yeterli."
        ]);
    }
    // C) ÖĞRETMENİ VE BAŞVURUYU TAMAMEN SİL (YOK ET)
    elseif ($islem === 'sil') {
        // Öğretmeni veritabanından tamamen kazıyoruz
        $sorgu = $pdo->prepare("DELETE FROM ogretmenler WHERE id = ?");
        $sorgu->execute([$ogretmen_id]);

        echo json_encode(["success" => true, "message" => "Öğretmen kaydı sistemden kalıcı olarak silindi!"]);
    }

    /* D) ÖĞRETMENİN GİRİŞ ŞİFRESİNİ DEĞİŞTİR
     * [2026-08] Süper Adminin öğretmen yönetimi tablosundan şifreyi
     * doğrudan düzenleyebilmesi için eklendi. Öğretmen şifresini unuttuğunda
     * ya da yeni öğretmene şifre verirken kullanılır.
     * Şifre alanı öğretmen girişinde düz karşılaştırıldığı için burada da
     * düz metin yazılıyor (bkz. docs/BILINEN_SORUNLAR.md #2). */
    elseif ($islem === 'sifre_degistir') {
        $yeniSifre = trim((string) ($veri['sifre'] ?? ''));

        if ($ogretmen_id <= 0) {
            echo json_encode(["success" => false, "message" => "Öğretmen seçilmedi."]);
            exit;
        }
        if ($yeniSifre === '') {
            echo json_encode(["success" => false, "message" => "Şifre boş olamaz."]);
            exit;
        }
        if (mb_strlen($yeniSifre) > 255) {
            echo json_encode(["success" => false, "message" => "Şifre çok uzun."]);
            exit;
        }

        $sorgu = $pdo->prepare("UPDATE ogretmenler SET sifre = ? WHERE id = ?");
        $sorgu->execute([$yeniSifre, $ogretmen_id]);

        if ($sorgu->rowCount() === 0) {
            // Aynı değer yazıldıysa rowCount 0 döner; kaydın varlığını doğrula
            $kontrol = $pdo->prepare("SELECT id FROM ogretmenler WHERE id = ?");
            $kontrol->execute([$ogretmen_id]);
            if (!$kontrol->fetch()) {
                echo json_encode(["success" => false, "message" => "Öğretmen bulunamadı."]);
                exit;
            }
        }

        echo json_encode(["success" => true, "message" => "Şifre güncellendi."]);
    }

    else {
        echo json_encode(["success" => false, "message" => "Tanımsız işlem."]);
    }
} catch (PDOException $e) {
    error_log('[admin_teacher_action] ' . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Veritabanı hatası!"]);
}
?>