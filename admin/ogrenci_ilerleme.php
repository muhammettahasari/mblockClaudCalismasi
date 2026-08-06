<?php
/**
 * ============================================================================
 * DOSYA YOLU: admin/ogrenci_ilerleme.php
 * KULLANICI / SAYFA: Öğretmen -> (artık ayrı sayfa değil) yönlendirme
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Bu sayfa 2026-08'de öğretmen paneline TAŞINDI. Artık ilerleme tablosu
 * "Canlı Görev Takibi" kartının içinde bir sekme. Dosya sadece eski
 * bağlantıları (yer imi, tarayıcı geçmişi, başka sayfadaki linkler) yeni
 * adrese yönlendirmek için duruyor.
 *
 * NEDEN BİRLEŞTİRİLDİ?
 * İki sayfa da "sınıfım ne durumda?" sorusunu cevaplıyordu ama ayrıydılar.
 * Öğretmen panelde 5A'yı seçiyor, ilerlemeye geçince seçim kayboluyor ve
 * 5A'yı BİR DAHA seçmesi gerekiyordu. Aynı ders içinde iki sayfa arasında
 * gidip gelen öğretmen için gereksiz bir sürtünmeydi.
 *
 * NEDEN DOSYA SİLİNMEDİ?
 * Öğretmenlerin tarayıcısında bu adrese yer imi olabilir. Silseydik "sayfa
 * bulunamadı" alırlardı ve nereye gideceklerini bilemezlerdi. Yönlendirme
 * seçili sınıfı da taşıyor, yani kaldığı yerden devam ediyor.
 *
 * YENİ ADRES:
 *   admin/ogretmen_paneli.php?gorunum=ilerleme[&sinif_id=N]
 * ============================================================================
 */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
ogretmenZorunlu();

/* Sınıf seçimi korunsun; öğretmen aynı sınıfın tablosunu görsün.
   Sahiplik kontrolü panelde ve api/sinif_ilerleme_durumu.php'de zaten var —
   burada sadece sayı olup olmadığına bakıyoruz. */
$sinif_id = (int) ($_GET['sinif_id'] ?? 0);

$hedef = 'ogretmen_paneli.php?gorunum=ilerleme';
if ($sinif_id > 0) {
    $hedef .= '&sinif_id=' . $sinif_id;
}

header('Location: ' . $hedef, true, 301);
exit;
