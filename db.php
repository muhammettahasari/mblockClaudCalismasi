<?php
/* ==========================================================
   YENİ KODLARIN BAŞLANGICI
   Veritabanı bağlantı ayarları (PDO Kullanılarak)
   ========================================================== */

$host = 'localhost';
$dbname = 'mblock_egitim';
$username = 'root'; // XAMPP varsayılan kullanıcı adı
$password = '';     // XAMPP varsayılan şifre (boş)

try {
    // Veritabanına bağlanma işlemi
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Hata yakalama modunu aktif et (Hataları detaylı görmek için)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verileri her zaman ilişkisel dizi (associative array) olarak çek
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Bağlantı başarısız olursa çalışmayı durdur ve hatayı ekrana bas
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

/* ==========================================================
   YENİ KODLARIN BİTİŞİ
   ========================================================== */
?>