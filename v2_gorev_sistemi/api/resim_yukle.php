<?php
/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/api/resim_yukle.php
 * KULLANICI / SAYFA: Süper Admin -> Görev Tasarlayıcı (Bilgi Al alanı)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Süper admin görevin "Bilgi Al" bölümüne resim eklemek istediğinde
 * (ekran görüntüsü, blok fotoğrafı, şema) bu dosya resmi sunucuya kaydeder
 * ve adresini geri döndürür. Editor.js'in beklediği cevap biçimini kullanır.
 *
 * 🔒 GÜVENLİK — BURASI EN RİSKLİ UÇ NOKTA TÜRÜ:
 * Dosya yükleme, bir web sitesinin en kolay ele geçirildiği yerdir. Alınan
 * önlemler:
 *   1. superAdminZorunluApi() — öğrenci ya da öğretmen buraya erişemez
 *   2. Uzantı BEYAZ LİSTE (jpg/jpeg/png/gif/webp) — kara liste değil.
 *      Kara liste kullansaydık ".phtml", ".php5", ".pHp" gibi türevler kaçardı.
 *   3. getimagesize() ile GERÇEKTEN resim mi diye bakılır. Uzantı kolay
 *      değiştirilir; içeriğin resim olduğunu görüntü kütüphanesi söyler.
 *   4. Dosya adı kullanıcıdan ALINMAZ, sunucu üretir. Gelen ad
 *      "../../db.php" olabilirdi.
 *   5. Boyut sınırı (4 MB) — disk doldurma denemelerine karşı.
 *
 * ⚠️ Klasöre .htaccess de yazılıyor: oraya bir şekilde PHP dosyası düşse
 * bile sunucu onu ÇALIŞTIRMASIN, düz metin gibi davransın.
 *
 * CEVAP BİÇİMİ (Editor.js image tool bunu bekler):
 *   { "success": 1, "file": { "url": "img/gorev_ipuclari/xxx.png" } }
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (Bilgi Al alanına resim eklemek için).
 * ============================================================================
 */

require_once __DIR__ . '/../../bilesenler/guvenlik.php';
require_once __DIR__ . '/../../bilesenler/api_yanit.php';

superAdminZorunluApi();

const IPUCU_KLASORU   = __DIR__ . '/../../img/gorev_ipuclari/';
const IPUCU_WEB_YOLU  = 'img/gorev_ipuclari/';
const IZINLI_UZANTI   = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const AZAMI_BOYUT     = 4 * 1024 * 1024;   // 4 MB

/** Editor.js hem başarıda hem hatada kendi biçimini bekler. */
function ipucuResimHatasi(string $mesaj): void
{
    apiBaslik();
    echo json_encode(['success' => 0, 'message' => $mesaj, 'mesaj' => $mesaj], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    /* Editor.js alanı "image" adıyla gönderir; elle çağıranlar için "dosya" da kabul */
    $dosya = $_FILES['image'] ?? $_FILES['dosya'] ?? null;

    if (!$dosya || ($dosya['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        ipucuResimHatasi('Dosya alınamadı. (Sunucu yükleme sınırını aşmış olabilir.)');
    }
    if ((int) $dosya['size'] > AZAMI_BOYUT) {
        ipucuResimHatasi('Resim çok büyük — en fazla 4 MB.');
    }

    /* --- 1. Uzantı beyaz listeden geçsin --- */
    $uzanti = strtolower(pathinfo($dosya['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($uzanti, IZINLI_UZANTI, true)) {
        ipucuResimHatasi('Sadece resim yükleyebilirsin (jpg, png, gif, webp).');
    }

    /* --- 2. İçerik GERÇEKTEN resim mi? ---
       Uzantıyı değiştirmek saniyelik iş; asıl kanıt dosyanın içi. */
    $olcu = @getimagesize($dosya['tmp_name']);
    if ($olcu === false) {
        ipucuResimHatasi('Bu dosya bir resim değil.');
    }

    /* --- 3. Klasörü hazırla --- */
    if (!is_dir(IPUCU_KLASORU) && !mkdir(IPUCU_KLASORU, 0775, true) && !is_dir(IPUCU_KLASORU)) {
        ipucuResimHatasi('Yükleme klasörü oluşturulamadı.');
    }

    /* Klasöre düşen hiçbir şey ÇALIŞTIRILMASIN — savunmanın son katmanı */
    $htaccess = IPUCU_KLASORU . '.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents(
            $htaccess,
            "php_flag engine off\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .phps\n"
            . "AddType text/plain .php .phtml .php3 .php4 .php5 .php7 .phps\n"
        );
    }

    /* --- 4. Adı SUNUCU üretsin --- */
    $yeniAd = 'ipucu_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $uzanti;

    if (!move_uploaded_file($dosya['tmp_name'], IPUCU_KLASORU . $yeniAd)) {
        ipucuResimHatasi('Resim kaydedilemedi (klasör yazılabilir mi?).');
    }

    apiBaslik();
    echo json_encode([
        'success' => 1,
        'file'    => ['url' => IPUCU_WEB_YOLU . $yeniAd],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    error_log('[mBlock] ipucu resim yükleme: ' . $e->getMessage());
    ipucuResimHatasi('Beklenmeyen bir hata oldu.');
}
