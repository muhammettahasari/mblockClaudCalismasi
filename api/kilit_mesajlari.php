<?php
/**
 * ============================================================================
 * DOSYA YOLU: api/kilit_mesajlari.php
 * KULLANICI / SAYFA: Öğretmen -> admin/ogretmen_paneli.php (Ders Kilidi penceresi)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Öğretmen sınıfı kilitlerken öğrencilere not bırakıyor. Sık kullandığı
 * notları ("Sınav var, kimse dokunmasın") kaydedip bir tıkla seçebilsin diye
 * bu dosya var. Kayıtlı mesajları listeler, yenisini ekler, gereksizi siler.
 *
 * DESTEKLENEN İŞLEMLER:
 *   liste  Öğretmenin kayıtlı mesajları (çok kullanılan önce)
 *   ekle   Yeni mesaj kaydet — aynısı varsa tekrar eklenmez
 *   sil    Kayıtlı mesajı sil
 *
 * ⚠️ HAZIR MESAJLAR BURADA DEĞİL:
 * "Tahtaya bakın", "Teneffüs" gibi herkese gelen hazır seçenekler koda gömülü
 * (bilesenler/kilit_modali.php). Bu dosya SADECE öğretmenin kendi yazdıklarını
 * yönetir. Karıştırma: hazır olanlar silinemez, öğretmeninkiler silinebilir.
 *
 * 🔒 GÜVENLİK — ÇOK KİRACILIK:
 * Her sorguda `ogretmen_id = <oturumdaki>` şartı var. Kimlik oturumdan gelir,
 * istekten ASLA. Başka öğretmenin mesaj id'si gönderilirse silme 0 satır
 * etkiler ve "bulunamadı" döner.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (confirm/prompt yerine gelen kilit penceresi).
 * ============================================================================
 */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogretmen_id = ogretmenZorunluApi();

$gelen = istekVerisi();
$islem = beyazListe($gelen['islem'] ?? 'liste', ['liste', 'ekle', 'sil'], '');

if ($islem === '') {
    apiHata('Tanımsız işlem.');
}

/** Öğretmenin kayıtlı mesajlarını getirir (tek sorgu). */
function mesajlariGetir(PDO $pdo, int $ogretmenId): array
{
    $st = $pdo->prepare(
        "SELECT id, mesaj, kullanim_sayisi
           FROM kilit_mesajlari
          WHERE ogretmen_id = ?
          ORDER BY kullanim_sayisi DESC, id DESC
          LIMIT 30"
    );
    $st->execute([$ogretmenId]);

    return array_map(fn($r) => [
        'id'      => (int) $r['id'],
        'mesaj'   => $r['mesaj'],
        'kullanim' => (int) $r['kullanim_sayisi'],
    ], $st->fetchAll(PDO::FETCH_ASSOC));
}

try {
    switch ($islem) {

        /* ------------------------------------------------------------------
         * LİSTE
         * ---------------------------------------------------------------- */
        case 'liste':
            apiBasari(['mesajlar' => mesajlariGetir($pdo, $ogretmen_id)]);
            break;

        /* ------------------------------------------------------------------
         * EKLE
         * Sütun 160 karakter; kesmek yerine uzunsa uyarıyoruz ki öğretmen
         * yazdığının yarısının kaybolduğunu sonradan fark etmesin.
         * ---------------------------------------------------------------- */
        case 'ekle': {
            $mesaj = trim((string) ($gelen['mesaj'] ?? ''));

            if ($mesaj === '') {
                apiHata('Kaydedilecek mesaj boş.');
            }
            if (mb_strlen($mesaj) > 160) {
                apiHata('Mesaj çok uzun (en fazla 160 karakter).');
            }

            /* Liste sonsuza kadar büyümesin — 30 tavanı */
            $sayi = $pdo->prepare("SELECT COUNT(*) FROM kilit_mesajlari WHERE ogretmen_id = ?");
            $sayi->execute([$ogretmen_id]);
            if ((int) $sayi->fetchColumn() >= 30) {
                apiHata('En fazla 30 mesaj kaydedebilirsin. Önce kullanmadığın birini sil.');
            }

            /* Aynı metin ikinci kez eklenmesin.
               UNIQUE anahtar zaten engelliyor; IGNORE ile hata fırlatmadan geçiyoruz. */
            $pdo->prepare(
                "INSERT IGNORE INTO kilit_mesajlari (ogretmen_id, mesaj) VALUES (?, ?)"
            )->execute([$ogretmen_id, $mesaj]);

            apiBasari(['mesajlar' => mesajlariGetir($pdo, $ogretmen_id)], 'Mesaj kaydedildi.');
            break;
        }

        /* ------------------------------------------------------------------
         * SİL
         * ---------------------------------------------------------------- */
        case 'sil': {
            $id = (int) ($gelen['id'] ?? 0);

            if ($id <= 0) {
                apiHata('Silinecek mesaj seçilmedi.');
            }

            /* ogretmen_id şartı olmadan bu satır başka öğretmenin mesajını
               silerdi — sahiplik WHERE'in içinde, ayrı sorgu gerekmiyor. */
            $st = $pdo->prepare("DELETE FROM kilit_mesajlari WHERE id = ? AND ogretmen_id = ?");
            $st->execute([$id, $ogretmen_id]);

            if ($st->rowCount() === 0) {
                apiHata('Mesaj bulunamadı.', 404);
            }

            apiBasari(['mesajlar' => mesajlariGetir($pdo, $ogretmen_id)], 'Mesaj silindi.');
            break;
        }
    }

} catch (Throwable $e) {
    /* [2026-08] Migration çalıştırılmamışsa buraya "Base table or view not
       found" düşüyor. Ham SQL metnini öğretmene göstermek anlamsız — ne
       yapacağını söyleyen bir cümle veriyoruz. `kurulum` bayrağıyla arayüz
       "kayıtlı notlar kapalı" diyebiliyor, hazır notlar çalışmaya devam ediyor. */
    if (str_contains($e->getMessage(), '42S02') || str_contains($e->getMessage(), "kilit_mesajlari' doesn't exist")) {
        apiHata(
            'Kayıtlı not özelliği henüz kurulmamış. phpMyAdmin\'de '
            . 'kurulum/migration_2026_08_kilit_mesajlari.sql dosyasını çalıştır.',
            503,
            ['kurulum' => false]
        );
    }
    apiIstisna($e, 'Mesaj işlemi tamamlanamadı.');
}
