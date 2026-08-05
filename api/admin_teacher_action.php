<?php
/* ==========================================================
   Dosya Yolu: api/admin_teacher_action.php
   İşlev: Süper adminin öğretmenleri onaylaması, PIN üretmesi ve hediye ay eklemesi
   GÜVENLİK: [2026-08] Süper Admin oturumu zorunlu hale getirildi (#1).
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';
superAdminZorunluApi();
header('Content-Type: application/json; charset=utf-8');

$veri = json_decode(file_get_contents("php://input"), true);
$islem = $veri['islem'] ?? '';
$ogretmen_id = (int)($veri['id'] ?? 0);
$ay = (int)($veri['ay'] ?? 1); // Öğretmenin (veya adminin) formdan talep ettiği ay

// YENİ: Öğretmenin içerideki kalan günleri yanmasın diye sisteme "Hediye 1 Ay" ekliyoruz.
$hesaplanacak_ay = $ay + 1; 

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
    
    // B) KAYITLI ÖĞRETMENİN SÜRESİNİ UZAT VE KİLİDİNİ AÇ
    elseif ($islem === 'uzat') {
        
        // Hediye ayla birlikte yeni bitiş tarihini hesapla
        $bitis_tarihi = date('Y-m-d H:i:s', strtotime("+$hesaplanacak_ay months"));
        
        // Süreyi uzat ve "Talep Edilen Ay" bildirimini sıfırla
        $sorgu = $pdo->prepare("UPDATE ogretmenler SET durum = 'onaylandi', bitis_tarihi = ?, talep_edilen_ay = 0 WHERE id = ?");
        $sorgu->execute([$bitis_tarihi, $ogretmen_id]);
        
        echo json_encode(["success" => true, "message" => "Süre Uzatıldı! Talep Edilen: $ay Ay | Hediye ile Verilen: $hesaplanacak_ay Ay"]);
    }
    // C) ÖĞRETMENİ VE BAŞVURUYU TAMAMEN SİL (YOK ET)
    elseif ($islem === 'sil') {
        // Öğretmeni veritabanından tamamen kazıyoruz
        $sorgu = $pdo->prepare("DELETE FROM ogretmenler WHERE id = ?");
        $sorgu->execute([$ogretmen_id]);
        
        echo json_encode(["success" => true, "message" => "Öğretmen kaydı sistemden kalıcı olarak silindi!"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Veritabanı hatası!"]);
}
?>