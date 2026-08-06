<?php
/**
 * ============================================================================
 * MODÜL: Süper Admin gezinme düğmesi (partial)
 * DOSYA YOLU: bilesenler/super_gezinme.php
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Süper Admin'in hangi sayfada olursa olsun tek tıkla ana panele dönebilmesi
 * için ekranın sol altına sabitlenen küçük bir "🏠 Ana Panel" düğmesi.
 *
 * NEDEN GEREKLİ?
 * [2026-08] Süper Admin sayfalarının bir kısmında panele dönüş bağlantısı hiç
 * yoktu (admin_market.php, ogretmen_yonetimi.php, super_quiz_yonetimi.php,
 * gorev_duzenle.php). Kullanıcı o sayfalara girdiğinde geri dönmek için
 * tarayıcının geri tuşunu kullanmak ya da adresi elle yazmak zorunda kalıyordu.
 *
 * NEDEN SABİT (fixed) BİR DÜĞME?
 * Süper Admin sayfalarının her birinin kendi üst bar tasarımı var; kimi koyu
 * kimi açık tema. Her sayfanın başlığına ayrı ayrı bağlantı eklemek yerine
 * layout'tan bağımsız, hepsinde aynı yerde duran tek bir düğme kullanıyoruz.
 * Sol altta durur; sayfaların sağ üstteki düğme kümeleriyle çakışmaz.
 *
 * KULLANIM — sayfanın <body> etiketinden SONRA tek satır:
 *   <?php require __DIR__ . '/../bilesenler/super_gezinme.php'; ?>
 *
 * DEĞİŞKEN (isteğe bağlı, require'dan ÖNCE tanımla):
 *   $SUPER_KOK — proje köküne çıkan yol. Verilmezse otomatik hesaplanır.
 * ============================================================================
 */

/* Sayfa proje kökünden kaç klasör derinde? Otomatik bul. */
if (!isset($SUPER_KOK)) {
    $kok    = realpath(dirname(__DIR__));                 // proje kökü
    $sayfa  = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__));
    $derinlik = 1;

    if ($kok && $sayfa && str_starts_with($sayfa, $kok)) {
        $fark     = trim(str_replace($kok, '', $sayfa), DIRECTORY_SEPARATOR);
        $derinlik = $fark === '' ? 0 : count(explode(DIRECTORY_SEPARATOR, $fark));
    }

    $SUPER_KOK = str_repeat('../', max(0, $derinlik));
}

/* Zaten ana paneldeysek düğmeyi gösterme */
$suAnkiDosya = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($suAnkiDosya === 'super_panel.php') {
    return;
}
?>
<style>
    /* Süper Admin gezinme düğmesi — her sayfada aynı yerde, aynı görünümde */
    #superGezinme {
        position: fixed; left: 16px; bottom: 16px; z-index: 99990;
        display: flex; gap: 8px; align-items: center;
        font-family: 'Segoe UI', Tahoma, sans-serif;
    }
    #superGezinme a {
        display: inline-flex; align-items: center; gap: 7px;
        background: #1e293b; color: #f8fafc;
        border: 1px solid #475569; border-radius: 999px;
        padding: 9px 16px; font-size: 13px; font-weight: bold;
        text-decoration: none; box-shadow: 0 6px 18px rgba(2, 6, 23, .38);
        transition: .18s;
    }
    #superGezinme a:hover {
        background: #334155; border-color: #64748b; transform: translateY(-1px);
    }
    #superGezinme a.cikis { background: #7f1d1d; border-color: #991b1b; color: #fecaca; }
    #superGezinme a.cikis:hover { background: #991b1b; }

    /* Dar ekranda sadece ikon kalsın */
    @media (max-width: 640px) {
        #superGezinme a .yazi { display: none; }
        #superGezinme a { padding: 10px 12px; }
    }

    /* Yazdırırken görünmesin */
    @media print { #superGezinme { display: none; } }
</style>

<div id="superGezinme">
    <a href="<?= $SUPER_KOK ?>admin/super_panel.php" title="Süper Admin ana paneline dön">
        🏠 <span class="yazi">Ana Panel</span>
    </a>
    <a href="<?= $SUPER_KOK ?>admin/super_cikis.php" class="cikis" title="Süper Admin oturumunu kapat">
        🚪 <span class="yazi">Çıkış</span>
    </a>
</div>
