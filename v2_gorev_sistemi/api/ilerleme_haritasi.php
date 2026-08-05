<?php
/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/api/ilerleme_haritasi.php
 * KULLANICI / SAYFA: Öğrenci -> Görev Oynatıcı (üstteki ilerleme çubuğu)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Oynatıcı sayfasının tepesindeki "Bölüm 1 › Görev 3" kutucuğunu ve
 * altındaki renkli ilerleme dairelerini besleyen veri kaynağı.
 * Hangi görev yeşil (bitti), hangisi turuncu (şu an), hangisi gri (sırası
 * gelmedi), hangisi kırmızı (öğretmen geri gönderdi) — hepsini söyler.
 *
 * DESTEKLENEN İŞLEMLER:
 *   harita        — tam ilerleme haritası (varsayılan)
 *   gorev_ac      — öğrenci bir daireye tıkladığında o görevi aktif yapar
 *   bildirim_oku  — "öğretmenin seni geri gönderdi" uyarısını kapatır
 *
 * GÜVENLİK:
 *   Kimlik SADECE oturumdan alınır. Öğrenci başka öğrencinin haritasını
 *   isteyemez; gorev_id gönderse bile erişemediği bölüm reddedilir.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (ilerleme çubuğu özelliği).
 * ============================================================================
 */

require_once __DIR__ . '/../../bilesenler/guvenlik.php';
require_once __DIR__ . '/../../bilesenler/api_yanit.php';
require_once __DIR__ . '/../../bilesenler/ilerleme_yardimcilari.php';

$ogrenci_id = ogrenciZorunluApi();

$gelen = istekVerisi();
$islem = beyazListe($gelen['islem'] ?? 'harita', ['harita', 'gorev_ac', 'bildirim_oku'], 'harita');

try {
    switch ($islem) {

        /* ------------------------------------------------------------------
         * BİLDİRİM OKUNDU — "öğretmenin seni geri gönderdi" kutusunu kapat
         * ---------------------------------------------------------------- */
        case 'bildirim_oku': {
            $pdo->prepare(
                "UPDATE v2_sifirlama_gunlugu SET okundu = 1 WHERE ogrenci_id = ? AND okundu = 0"
            )->execute([$ogrenci_id]);

            apiBasari([], 'Bildirim okundu.');
        }

        /* ------------------------------------------------------------------
         * GÖREV AÇ — öğrenci ilerleme çubuğundaki bir daireye tıkladı
         * Otomatik geçiş YOK; öğrenci nereye gideceğini kendisi seçiyor.
         * ---------------------------------------------------------------- */
        case 'gorev_ac': {
            $gorev_id = (int) ($gelen['gorev_id'] ?? 0);
            if ($gorev_id <= 0) {
                apiHata('Görev seçilmedi.');
            }

            $st = $pdo->prepare(
                "SELECT g.id, g.bolum_id, g.gorev_adi, g.yayinda
                   FROM v2_gorevler g WHERE g.id = ?"
            );
            $st->execute([$gorev_id]);
            $gorev = $st->fetch(PDO::FETCH_ASSOC);

            if (!$gorev || (int) $gorev['yayinda'] !== 1) {
                apiHata('Bu görev şu anda oynanamıyor.', 404);
            }

            // Bölümün kilidi açık mı?
            $erisilen = erisilebilirBolumler($pdo, $ogrenci_id);
            if (!in_array((int) $gorev['bolum_id'], $erisilen, true)) {
                apiHata('Bu bölüme henüz ulaşmadın. Önceki görevleri tamamla.', 403);
            }

            $pdo->prepare("UPDATE ogrenciler SET aktif_v2_gorev_id = ? WHERE id = ?")
                ->execute([$gorev_id, $ogrenci_id]);

            apiBasari([
                'gorev_id'  => $gorev_id,
                'gorev_adi' => $gorev['gorev_adi'],
            ], 'Görev açıldı.');
        }

        /* ------------------------------------------------------------------
         * HARİTA — tam ilerleme tablosu
         * ---------------------------------------------------------------- */
        case 'harita':
        default: {
            $aktif = isset($gelen['aktif_gorev_id']) ? (int) $gelen['aktif_gorev_id'] : 0;

            $harita   = ilerlemeHaritasi($pdo, $ogrenci_id, $aktif > 0 ? $aktif : null);
            $ceza     = ogrencininCezasi($pdo, $ogrenci_id);
            $bildirim = okunmamisSifirlamaBildirimi($pdo, $ogrenci_id);

            $st = $pdo->prepare("SELECT kalici_puan, elmas FROM ogrenciler WHERE id = ?");
            $st->execute([$ogrenci_id]);
            $bakiye = $st->fetch(PDO::FETCH_ASSOC) ?: ['kalici_puan' => 0, 'elmas' => 0];

            /* [2026-08] Sıradaki görevi de söylüyoruz.
               Öğretmen ceza verdiğinde açılan kırmızı kart kapanınca tarayıcı
               doğrudan bu göreve gidiyor; hangi bölüme döneceğini tahmin
               etmesi gerekmiyor. */
            $sonraki = sonrakiGoreviBul($pdo, $ogrenci_id) ?: sonErisilenGorev($pdo, $ogrenci_id);

            apiBasari([
                'harita'           => $harita,
                'ceza'             => $ceza,
                'bildirim'         => $bildirim,
                'sonraki_gorev_id' => (int) ($sonraki ?: 0),
                'bakiye'   => [
                    'puan'  => (int) $bakiye['kalici_puan'],
                    'elmas' => (int) $bakiye['elmas'],
                ],
            ]);
        }
    }

} catch (Throwable $e) {
    apiIstisna($e, 'İlerleme bilgisi alınamadı.');
}
