<?php
/* ====================================================================
 * MODÜL: Düello Radarı (kök sayfalar için ince sarmalayıcı)
 * DOSYA YOLU: bilesenler/duello_radari.php
 * BAĞLI OLDUĞU ANA DOSYALAR: ogrenci_paneli.php, profil.php, market.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Düello motorunu sayfaya bağlar. Motorun kendisi js/duello_motoru.js
 * dosyasındadır.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] "meydanOku is not defined" HATASI ÇÖZÜLDÜ:
 *      Bütün düello kodu bu dosyanın içine gömülüydü. oynatici.php (iframe)
 *      skorboardu kopyalayınca butonlar geliyor ama fonksiyonlar gelmiyordu;
 *      buton `window.parent` üzerinden fonksiyona ulaşmaya çalışıp
 *      "meydanOku is not defined" hatası veriyordu.
 *      Kod artık js/duello_motoru.js içinde ve iframe'e de yükleniyor.
 * ==================================================================== */
?>
<script>
    // Kök sayfadayız: API yolları öneksiz, gelen istekleri BU sayfa dinler.
    window.MBLOCK_TABAN = '';
    window.MBLOCK_DUELLO_DINLE = true;
</script>
<script src="<?= varlik('js/ogrenci_radar.js', '') ?>"></script>
<script src="<?= varlik('js/duello_motoru.js', '') ?>"></script>
