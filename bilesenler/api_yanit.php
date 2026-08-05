<?php
/* ====================================================================
 * MODÜL: Standart API Cevap Üreticisi
 * DOSYA YOLU: bilesenler/api_yanit.php
 * BAĞLI OLDUĞU ANA DOSYA: api/ altındaki tüm JSON uç noktaları
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Projede beş farklı cevap formatı vardı: {success:true}, {status:"success"},
 * {durum:"basarili"}, "OK" ve ham HTML. Bu dosya hepsini TEK seferde
 * gönderir — yani yeni kod tek bir fonksiyon çağırır ama eski JavaScript'ler
 * de kendi bekledikleri anahtarı bulmaya devam eder. Hiçbir şey bozulmaz.
 *
 * TEMEL İŞLEVLER:
 *  1. apiBasari($veri, $mesaj)  — başarılı cevap
 *  2. apiHata($mesaj, $kod)     — hatalı cevap
 *  3. apiJson($dizi)            — ham dizi göndermek gerektiğinde
 *
 * KULLANIM:
 *    require_once __DIR__ . '/../bilesenler/api_yanit.php';
 *    apiBasari(['kalan_elmas' => 120], 'Satın alma tamamlandı.');
 *    apiHata('Yeterli elmasın yok!');
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (Bilinen Sorunlar #15).
 * ==================================================================== */

if (!function_exists('apiBaslik')) {

    function apiBaslik(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
    }

    /**
     * Başarılı cevap. Üç eski formatın anahtarlarını birlikte gönderir.
     * @param array  $veri  Cevaba eklenecek ek alanlar (kalan_elmas, data, vb.)
     * @param string $mesaj Kullanıcıya gösterilecek mesaj
     */
    function apiBasari(array $veri = [], string $mesaj = ''): void
    {
        apiBaslik();
        echo json_encode(array_merge([
            'success' => true,
            'status'  => 'success',
            'durum'   => 'basarili',
            'message' => $mesaj,
            'mesaj'   => $mesaj,
        ], $veri), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Hatalı cevap.
     * @param string $mesaj Kullanıcıya gösterilecek hata
     * @param int    $kod   HTTP durum kodu (400 istemci hatası, 500 sunucu)
     * @param array  $veri  Ek alanlar (reason, kilitlendi, kalan_hak, vb.)
     */
    function apiHata(string $mesaj, int $kod = 400, array $veri = []): void
    {
        apiBaslik();
        if (!headers_sent()) {
            http_response_code($kod);
        }
        echo json_encode(array_merge([
            'success' => false,
            'status'  => 'error',
            'durum'   => 'hata',
            'message' => $mesaj,
            'mesaj'   => $mesaj,
        ], $veri), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Liste gibi ham dizi döndürmek gerektiğinde (ör. sıralama tablosu). */
    function apiJson($veri): void
    {
        apiBaslik();
        echo json_encode($veri, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Beklenmeyen hataları güvenli şekilde döndürür.
     * Geliştirmede detayı gösterir, canlıda gizler (bilgi sızıntısını önler).
     */
    function apiIstisna(Throwable $e, string $kullaniciMesaji = 'Sistemsel bir hata oluştu.'): void
    {
        $gelistirmeModu = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);
        error_log('[mBlock API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

        apiHata(
            $gelistirmeModu ? $kullaniciMesaji . ' (' . $e->getMessage() . ')' : $kullaniciMesaji,
            500
        );
    }
}
