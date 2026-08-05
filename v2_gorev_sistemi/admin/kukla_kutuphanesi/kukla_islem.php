<?php
/**
 * Dosya Yolu: v2_gorev_sistemi/admin/kukla_islem.php
 * Kullanıcı Yetkisi ve Bağlı Olduğu Sayfa: Süper Admin -> Kukla Kütüphanesi İşlem Dosyası
 * Halk Dilinde İşlevi: Adminin yüklediği çoklu kukla resimlerini alıp, sunucudaki assets/images klasörüne taşıyan ve JSON paketi halinde veritabanına kaydeden arka plan motorudur.
 * Temel İşlevler:
 * 1. Çoklu resim yükleme işlemini döngü ile yakalar.
 * 2. __DIR__ kullanarak dosyaları tam hedefine (assets/images/kuklalar) kaydeder.
 * 3. Resimleri "costume1", "costume2" gibi isimlendirerek veritabanına yazar.
 * YENİ EKLENEN ÖZELLİKLER:
 * - [Bugün] Dosya yükleme yolu __DIR__ sabiti ile mutlak yola çevrildi.
 */

/* GÜVENLİK: [2026-08] Süper Admin oturumu zorunlu (Bilinen Sorunlar #1). */
require_once __DIR__ . '/../../../admin/super_auth.php';
superAdminZorunlu();

if (isset($_POST['kukla_kaydet'])) {
    
    $kuklaAdi = trim($_POST['kukla_adi']);
    $grupAdi = trim($_POST['grup_adi']);
    
    // SİHİRLİ DOKUNUŞ: __DIR__ bulunduğumuz klasörü (admin) verir. Oradan 1 tık geri çıkıp assets'e giriyoruz.
    // Bu sayede dosyalar tam olarak: C:\xampp\htdocs\mblock_platform\v2_gorev_sistemi\assets\images\kuklalar\ içine gidecek.
    // SİHİRLİ DOKUNUŞ: __DIR__ 'kukla_kutuphanesi' klasörüdür. 
    // 2 tık geri çıkıp (/../../) ana sistemdeki 'assets' klasörüne girmemiz gerekiyor!
    $hedefKlasor = __DIR__ . "/../../assets/images/kuklalar/";
    
    if (!file_exists($hedefKlasor)) { 
        mkdir($hedefKlasor, 0777, true); 
    }

    $izinVerilenUzantilar = ["jpg", "jpeg", "png", "gif", "svg"];
    $kostumlerDizisi = [];
    $basariSayisi = 0;

    $dosyaSayisi = count($_FILES['kukla_resimleri']['name']);

for ($i = 0; $i < $dosyaSayisi; $i++) {
        $dosyaAdi = $_FILES['kukla_resimleri']['name'][$i];
        $dosyaUzantisi = strtolower(pathinfo($dosyaAdi, PATHINFO_EXTENSION));
        
        if (in_array($dosyaUzantisi, $izinVerilenUzantilar)) {
            $yeniDosyaAdi = uniqid("kostum_") . "." . $dosyaUzantisi;
            $hedefDosya = $hedefKlasor . $yeniDosyaAdi;
            
            // ÇÖZÜM BURADA: Başında "../" olmadan sadece klasör yolunu kaydediyoruz.
            // Çünkü Öğrenci tarafındaki JS kodların zaten otomatik olarak "../" ekliyor!
        // Öğrenci arayüzündeki (coklu_kukla_motoru.js) kodlarının okuyabilmesi için saf yol (../ olmadan)
            $veritabaniYolu = "assets/images/kuklalar/" . $yeniDosyaAdi;

            if (move_uploaded_file($_FILES['kukla_resimleri']['tmp_name'][$i], $hedefDosya)) {
                $basariSayisi++;
                
                $kostumlerDizisi[] = [
                    "id" => "k_" . time() . "_" . $basariSayisi,
                    "adi" => "costume" . $basariSayisi,
                    "resimYolu" => $veritabaniYolu, // YENİ: Kostüm Atölyesi bunu okuyacak
                    "yol" => $veritabaniYolu,       // ESKİ: Öğrenci alt paneli (Kırık resmin sebebi buydu!) bunu okuyacak
                    "cizimData" => null
                ];
            }
        }
    }

    if ($basariSayisi > 0) {
        $kostumlerJSON = json_encode($kostumlerDizisi, JSON_UNESCAPED_UNICODE);

        $sorgu = $pdo->prepare("INSERT INTO v2_kukla_kutuphanesi (kukla_adi, grup_adi, kostumler_json) VALUES (?, ?, ?)");
        $sorgu->execute([$kuklaAdi, $grupAdi, $kostumlerJSON]);

        header("Location: kukla_ekle.php?durum=basarili");
        exit();
    } else {
        header("Location: kukla_ekle.php?durum=hata");
        exit();
    }
} else {
    header("Location: kukla_ekle.php");
    exit();
}
?>