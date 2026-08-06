<?php
/* ==========================================================
   Dosya Yolu: api/teacher_remote.php
   İşlev: Öğretmene özel canlı veri akışı, sınıf filtreleme ve uzaktan kontrol
   ========================================================== */
session_start();
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

// Güvenlik: Sadece giriş yapmış öğretmenler bu dosyayı kullanabilir
$ogretmen_id = $_SESSION['ogretmen_id'] ?? 0;
if (!$ogretmen_id) {
    echo json_encode(["success" => false, "message" => "Yetkisiz erişim!"]);
    exit;
}

$islem = $_GET['islem'] ?? 'liste';
$ogrenci_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // 1. YENİ SINIF OLUŞTURMA
    if ($islem === 'sinif_ekle') {
        $sinif_adi = $_GET['sinif_adi'] ?? '';
        if(!empty($sinif_adi)) {
            $sorgu = $pdo->prepare("INSERT INTO siniflar (ogretmen_id, sinif_adi) VALUES (?, ?)");
            $sorgu->execute([$ogretmen_id, $sinif_adi]);
            echo json_encode(["success" => true]);
        }
        exit;
    }

    // 2. ÖĞRETMENİN SINIFLARINI GETİRME (Dropdown için)
    if ($islem === 'siniflari_getir') {
        $sorgu = $pdo->prepare("SELECT id, sinif_adi FROM siniflar WHERE ogretmen_id = ? ORDER BY sinif_adi ASC");
        $sorgu->execute([$ogretmen_id]);
        echo json_encode(["success" => true, "data" => $sorgu->fetchAll()]);
        exit;
    }

    // 3. ÖĞRENCİ LİSTESİNİ GETİRME (Filtreli ve SADECE bu öğretmenin öğrencileri)
    if ($islem === 'liste') {
        $sinif_id = isset($_GET['sinif_id']) ? (int)$_GET['sinif_id'] : 0;
        
        /* [2026-08 HATA DÜZELTMESİ] Filtre eskiden `o.ogretmen_id = ?` idi.
           O sütun kayıt anında yazılan DENORMALİZE bir kopya; öğrenci başka
           sınıfa taşınınca ya da sınıf başka öğretmene devredilince
           güncellenmiyor. Öğretmen kendi öğrencisini listede göremiyor,
           dolayısıyla kilitleyemiyor da.
           Gerçek zincir: ogretmenler -> siniflar -> ogrenciler */
        $sql = "SELECT o.*, s.sinif_adi
                  FROM ogrenciler o
                  JOIN siniflar s ON s.id = o.sinif_id
                 WHERE s.ogretmen_id = ?";
        $params = [$ogretmen_id];

        // Eğer belirli bir sınıf seçilmişse sorguya filtre ekle
        if ($sinif_id > 0) {
            $sql .= " AND o.sinif_id = ?";
            $params[] = $sinif_id;
        }

        $sql .= " ORDER BY o.kalici_puan DESC";
        $sorgu = $pdo->prepare($sql);
        $sorgu->execute($params);

        // Öğretmenin genel olarak onay bekleyen öğrencisi var mı?
        $onayKontrol = $pdo->prepare(
            "SELECT o.id FROM ogrenciler o
               JOIN siniflar s ON s.id = o.sinif_id
              WHERE s.ogretmen_id = ? AND o.onay_durumu = 0 LIMIT 1"
        );
        $onayKontrol->execute([$ogretmen_id]);
        $bekleyen_onay_var = $onayKontrol->rowCount() > 0;
        
        echo json_encode(["success" => true, "data" => $sorgu->fetchAll(), "bekleyen_onay_var" => $bekleyen_onay_var]);
        exit;
    } 
 elseif ($islem === 'kayit_onayla') {
        $sorgu = $pdo->prepare("UPDATE ogrenciler SET onay_durumu = 1 WHERE id = ? AND ogretmen_id = ?");
        $sorgu->execute([$ogrenci_id, $ogretmen_id]);
        echo json_encode(["success" => true]);
        exit;
    }
    
    // YENİ EKLENEN: KİLİDİ AÇMA İŞLEMİ (Öğrenciyi tekrar Onay Bekliyor statüsüne alır)
    elseif ($islem === 'kilit_ac') {
        $sorgu = $pdo->prepare("UPDATE ogrenciler SET durum = 'onay_bekliyor' WHERE id = ? AND ogretmen_id = ?");
        $sorgu->execute([$ogrenci_id, $ogretmen_id]);
        echo json_encode(["success" => true]);
        exit;
    }
    // 4. UZAKTAN ONAYLAMA İŞLEMİ
    elseif ($islem === 'onayla') {
        $sorgu = $pdo->prepare("UPDATE ogrenciler SET kalici_puan = kalici_puan + anlik_puan, anlik_puan = 0, durum = 'uzaktan_onaylandi' WHERE id = ? AND ogretmen_id = ?");
        $sorgu->execute([$ogrenci_id, $ogretmen_id]);
        echo json_encode(["success" => true]);
    } 
    
    // 5. UZAKTAN REDDETME / KİLİT AÇMA İŞLEMİ
    elseif ($islem === 'reddet_ac') {
        $sorgu = $pdo->prepare("UPDATE ogrenciler SET anlik_puan = 0, durum = 'uzaktan_reddedildi' WHERE id = ? AND ogretmen_id = ?");
        $sorgu->execute([$ogrenci_id, $ogretmen_id]);
        echo json_encode(["success" => true]);
    }
    // YENİ EKLENEN: ÖĞRENCİ SİLME
    elseif ($islem === 'ogrenci_sil') {
        $sorgu = $pdo->prepare("DELETE FROM ogrenciler WHERE id = ? AND ogretmen_id = ?");
        $sorgu->execute([$ogrenci_id, $ogretmen_id]);
        echo json_encode(["success" => true]);
        exit;
    }
    
    // YENİ EKLENEN: ÖĞRENCİ DÜZENLEME (KALEM İKONU)
    elseif ($islem === 'ogrenci_duzenle') {
        $veri = json_decode(file_get_contents("php://input"), true);
        $ad = trim($veri['ad_soyad'] ?? '');
        $kadi = trim($veri['kullanici_adi'] ?? '');
        $sifre = trim($veri['sifre'] ?? '');

       
     // Kendisi hariç SADECE BU OKULDA bu kullanıcı adını kullanan var mı?
        $k_kontrol = $pdo->prepare("SELECT id FROM ogrenciler WHERE kullanici_adi = ? AND id != ? AND ogretmen_id = ?");
        $k_kontrol->execute([$kadi, $ogrenci_id, $ogretmen_id]);
        if($k_kontrol->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "Bu kullanıcı adı zaten başka birinde kullanılıyor!"]);
            exit;
        }

        $sorgu = $pdo->prepare("UPDATE ogrenciler SET ad_soyad = ?, kullanici_adi = ?, sifre = ? WHERE id = ? AND ogretmen_id = ?");
        $sorgu->execute([$ad, $kadi, $sifre, $ogrenci_id, $ogretmen_id]);
        echo json_encode(["success" => true]);
        exit;
    }
    // YENİ EKLENEN: ÇOKLU SINIF VE İÇİNDEKİ ÖĞRENCİLERİ SİLME
    elseif ($islem === 'sinif_sil') {
        $veri = json_decode(file_get_contents("php://input"), true);
        $sinif_id_listesi = $veri['sinif_id_listesi'] ?? [];

        if (!empty($sinif_id_listesi)) {
            // Güvenlik: Sadece bu öğretmene ait sınıfları sil
            $inQuery = implode(',', array_fill(0, count($sinif_id_listesi), '?'));
            $params = array_merge($sinif_id_listesi, [$ogretmen_id]);

            // 1. Önce bu sınıflara ait öğrencileri kalıcı olarak sil
            $ogrSorgu = $pdo->prepare("DELETE FROM ogrenciler WHERE sinif_id IN ($inQuery) AND ogretmen_id = ?");
            $ogrSorgu->execute($params);

            // 2. Sonra sınıfların kendisini sil
            $sinifSorgu = $pdo->prepare("DELETE FROM siniflar WHERE id IN ($inQuery) AND ogretmen_id = ?");
            $sinifSorgu->execute($params);

            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => "Silinecek sınıf seçilmedi."]);
        }
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Veritabanı hatası!"]);
}
?>