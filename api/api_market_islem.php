<?php
/* ==========================================================
   Dosya Yolu: api/api_market_islem.php
   İşlev: Süper Adminin market eşyalarını yönetmesi (listele/ekle/sil/güncelle)
   GÜVENLİK: [2026-08] Süper Admin oturumu zorunlu hale getirildi (#1).
   NOT: Buradaki 'senkronize_et' dosyaları diskte YENİDEN ADLANDIRIR (riskli).
        Güvenli sürümü admin/super_bakim.php içindedir — yeni kurulumlarda onu kullanın.
   ========================================================== */
require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/rpg_matris.php';
superAdminZorunluApi();
header('Content-Type: application/json');

$islem = $_POST['islem'] ?? $_GET['islem'] ?? '';

// 1. EŞYALARI LİSTELEME (GELİŞMİŞ SIRALAMA EKLENDİ)
if ($islem == 'listele') {
    $kategori = $_GET['kategori'] ?? 'hepsi';
    $arama = $_GET['arama'] ?? '';
    $siralama = $_GET['siralama'] ?? 'yeni'; 

    $sorgu = "SELECT * FROM esya_kutuphanesi WHERE 1=1";
    $parametreler = [];

    if ($kategori != 'hepsi') {
        $sorgu .= " AND kategori = ?";
        $parametreler[] = $kategori;
    }
    if ($arama != '') {
        $sorgu .= " AND esya_adi LIKE ?";
        $parametreler[] = "%$arama%";
    }

    // YENİ SIRALAMA MANTIĞI
    if ($siralama == 'yeni') { $sorgu .= " ORDER BY id DESC"; }
    elseif ($siralama == 'eski') { $sorgu .= " ORDER BY id ASC"; }
    elseif ($siralama == 'fiyat_az') { $sorgu .= " ORDER BY fiyat ASC"; }
    elseif ($siralama == 'fiyat_za') { $sorgu .= " ORDER BY fiyat DESC"; }
    elseif ($siralama == 'ad_az') { $sorgu .= " ORDER BY esya_adi ASC"; }
    elseif ($siralama == 'ad_za') { $sorgu .= " ORDER BY esya_adi DESC"; }
    else { $sorgu .= " ORDER BY id DESC"; }
    
    $stmt = $pdo->prepare($sorgu);
    $stmt->execute($parametreler);
    $esyalar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $esyalar]);
    exit;
}

// 2. YENİ EŞYA EKLEME
if ($islem == 'ekle') {
    $esya_adi = $_POST['esya_adi'] ?? '';
    $kategori = $_POST['kategori'] ?? '';
    $puan = $_POST['puan'] ?? 50;

    if (!isset($_FILES['resim']) || $_FILES['resim']['error'] != 0) {
        echo json_encode(['success' => false, 'message' => 'Lütfen bir resim seçin.']);
        exit;
    }

    $dosya = $_FILES['resim'];
    $uzanti = strtolower(pathinfo($dosya['name'], PATHINFO_EXTENSION));
    $orijinal_isim = pathinfo($dosya['name'], PATHINFO_FILENAME);
    
    $degisecekler = ['ç'=>'c','ğ'=>'g','ı'=>'i','i'=>'i','ö'=>'o','ş'=>'s','ü'=>'u','Ç'=>'C','Ğ'=>'G','İ'=>'I','Ö'=>'O','Ş'=>'S','Ü'=>'U'];
    $orijinal_isim = strtr($orijinal_isim, $degisecekler);
    $guvenli_isim = preg_replace('/[^A-Za-z0-9\-]/', '_', $orijinal_isim);
    
    $yeni_dosya_adi = strtolower($guvenli_isim) . '_' . time() . '.' . $uzanti; 
    $hedef_klasor = "../img/esyalar/$kategori/";
    $veritabani_yolu = "img/esyalar/$kategori/$yeni_dosya_adi"; 

    if (move_uploaded_file($dosya['tmp_name'], $hedef_klasor . $yeni_dosya_adi)) {
        $ekle = $pdo->prepare("INSERT INTO esya_kutuphanesi (esya_adi, kategori, resim_yolu, fiyat, aciklama) VALUES (?, ?, ?, ?, 'Admin Eklendi')");
        $ekle->execute([$esya_adi, $kategori, $veritabani_yolu, $puan]);
        echo json_encode(['success' => true, 'message' => 'Eşya başarıyla eklendi!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Dosya yüklenemedi.']);
    }
    exit;
}

// 3. EŞYA SİLME (TOPLU SİLME DAHİL)
if ($islem == 'sil') {
    $id_listesi = isset($_POST['id']) ? [$_POST['id']] : ($_POST['idler'] ?? []);

    foreach ($id_listesi as $esya_id) {
        $esya = $pdo->prepare("SELECT resim_yolu, kategori FROM esya_kutuphanesi WHERE id = ?");
        $esya->execute([$esya_id]);
        $veri = $esya->fetch();

        if ($veri) {
            $fiziksel_yol = "../" . $veri['resim_yolu'];
            if (file_exists($fiziksel_yol)) { unlink($fiziksel_yol); } 

            $kategori = $veri['kategori'];
            
           if (in_array($kategori, ['sag_el', 'sol_el', 'kafa', 'binek', 'evcil_hayvan', 'sac', 'karakter', 'ayakkabi', 'zirh', 'yuz'])) {
                $pdo->prepare("UPDATE ogrenci_avatar_durum SET {$kategori}_id = NULL WHERE {$kategori}_id = ?")->execute([$esya_id]);
            }

            $pdo->prepare("DELETE FROM ogrenci_envanter WHERE esya_id = ?")->execute([$esya_id]);
            $pdo->prepare("DELETE FROM esya_kutuphanesi WHERE id = ?")->execute([$esya_id]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Silindi.']);
    exit;
}

// 4. EŞYA GÜNCELLEME (Ana Formdan)
if ($islem == 'guncelle') {
    $id = $_POST['id'] ?? 0;
    $esya_adi = $_POST['esya_adi'] ?? '';
    $kategori = $_POST['kategori'] ?? '';
    $puan = $_POST['puan'] ?? 50;

    if (!$id) { echo json_encode(['success' => false]); exit; }

    if (isset($_FILES['resim']) && $_FILES['resim']['error'] == 0) {
        $dosya = $_FILES['resim'];
        $uzanti = strtolower(pathinfo($dosya['name'], PATHINFO_EXTENSION));
        $orijinal_isim = preg_replace('/[^A-Za-z0-9\-]/', '_', strtr(pathinfo($dosya['name'], PATHINFO_FILENAME), ['ç'=>'c','ğ'=>'g','ı'=>'i','i'=>'i','ö'=>'o','ş'=>'s','ü'=>'u']));
        $yeni_dosya_adi = strtolower($orijinal_isim) . '_' . time() . '.' . $uzanti; 
        $hedef_klasor = "../img/esyalar/$kategori/";
        $veritabani_yolu = "img/esyalar/$kategori/$yeni_dosya_adi"; 

        $eski_sorgu = $pdo->prepare("SELECT resim_yolu FROM esya_kutuphanesi WHERE id = ?");
        $eski_sorgu->execute([$id]);
        $eski_veri = $eski_sorgu->fetch();
        if ($eski_veri && file_exists("../" . $eski_veri['resim_yolu'])) { unlink("../" . $eski_veri['resim_yolu']); }

        if (move_uploaded_file($dosya['tmp_name'], $hedef_klasor . $yeni_dosya_adi)) {
            $pdo->prepare("UPDATE esya_kutuphanesi SET esya_adi = ?, kategori = ?, fiyat = ?, resim_yolu = ? WHERE id = ?")->execute([$esya_adi, $kategori, $puan, $veritabani_yolu, $id]);
        }
    } else {
        $pdo->prepare("UPDATE esya_kutuphanesi SET esya_adi = ?, kategori = ?, fiyat = ? WHERE id = ?")->execute([$esya_adi, $kategori, $puan, $id]);
    }
    echo json_encode(['success' => true, 'message' => 'Güncellendi!']); exit;
}

// 5. SATIR İÇİ HIZLI KAYDETME MOTORU (Yeni)
if ($islem == 'hizli_guncelle') {
    $id = $_POST['id'] ?? 0;
    $esya_adi = $_POST['esya_adi'] ?? '';
    $fiyat = $_POST['fiyat'] ?? 50;

    if ($id) {
        $pdo->prepare("UPDATE esya_kutuphanesi SET esya_adi = ?, fiyat = ? WHERE id = ?")->execute([$esya_adi, $fiyat, $id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// 6. TOPLU SEVİYE UYGULAMA MOTORU (Yeni)
if ($islem == 'toplu_guncelle') {
    $veriler = json_decode($_POST['veriler'], true);
    if (is_array($veriler)) {
        foreach ($veriler as $v) {
            $pdo->prepare("UPDATE esya_kutuphanesi SET esya_adi = ?, fiyat = ? WHERE id = ?")->execute([$v['esya_adi'], $v['fiyat'], $v['id']]);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// 7. KLASÖRLERİ TARA (SENKRONİZASYON)
if ($islem == 'senkronize_et') {
   $kategoriler = ['sag_el', 'sol_el', 'kafa', 'binek', 'evcil_hayvan', 'sac', 'karakter', 'ayakkabi', 'zirh', 'yuz'];
    $eklenen_sayisi = 0;
    $mevcutlar_sorgu = $pdo->query("SELECT resim_yolu FROM esya_kutuphanesi")->fetchAll(PDO::FETCH_COLUMN);
    $mevcut_yollar = array_map('strtolower', $mevcutlar_sorgu);

    foreach ($kategoriler as $kat) {
        $klasor_yolu = "../img/esyalar/$kat/";
        if (!is_dir($klasor_yolu)) continue; 

        $dosyalar = scandir($klasor_yolu);
        foreach ($dosyalar as $dosya) {
            if ($dosya == '.' || $dosya == '..') continue;
            $uzanti = strtolower(pathinfo($dosya, PATHINFO_EXTENSION));
            if (!in_array($uzanti, ['png', 'jpg', 'jpeg'])) continue;

            $eski_fiziksel_yol = "../img/esyalar/$kat/$dosya"; 
            $eski_db_yolu = "img/esyalar/$kat/$dosya"; 
            
            if (!in_array(strtolower($eski_db_yolu), $mevcut_yollar)) {
                $ham_isim = pathinfo($dosya, PATHINFO_FILENAME);
                $isim = str_replace(['_', '-'], ' ', $ham_isim);
                $isim = preg_replace('/(\d)\s*seviye/i', '$1. Seviye', $isim);
                $isim = mb_convert_case($isim, MB_CASE_TITLE, "UTF-8");

                $degisecekler = ['ç'=>'c','ğ'=>'g','ı'=>'i','i'=>'i','ö'=>'o','ş'=>'s','ü'=>'u','Ç'=>'C','Ğ'=>'G','İ'=>'I','Ö'=>'O','Ş'=>'S','Ü'=>'U', ' '=>'_'];
                $temiz_isim = strtr($ham_isim, $degisecekler);
                $guvenli_isim = preg_replace('/[^A-Za-z0-9\-]/', '_', $temiz_isim);
                $yeni_dosya_adi = strtolower($guvenli_isim) . '_' . rand(1000,9999) . '.' . $uzanti;
                
                $yeni_fiziksel_yol = "../img/esyalar/$kat/$yeni_dosya_adi";
                $yeni_db_yolu = "img/esyalar/$kat/$yeni_dosya_adi";

                if (rename($eski_fiziksel_yol, $yeni_fiziksel_yol)) {
                    $puan = 50; 
                    if (stripos($isim, '1. Seviye') !== false) $puan = 100;
                    elseif (stripos($isim, '2. Seviye') !== false) $puan = 500;
                    elseif (stripos($isim, '3. Seviye') !== false) $puan = 2000;
                    elseif (stripos($isim, '4. Seviye') !== false) $puan = 20000;
                    elseif (stripos($isim, 'Destans') !== false) $puan = 50000;

                    $pdo->prepare("INSERT INTO esya_kutuphanesi (esya_adi, kategori, resim_yolu, fiyat, aciklama) VALUES (?, ?, ?, ?, 'Otomatik Senkronizasyon')")
                        ->execute([$isim, $kat, $yeni_db_yolu, $puan]);
                    $eklenen_sayisi++;
                }
            }
        }
    }
    echo json_encode(['success' => true, 'message' => "Senkronizasyon tamamlandı! $eklenen_sayisi adet eşya eklendi."]);
    exit;
}
?>