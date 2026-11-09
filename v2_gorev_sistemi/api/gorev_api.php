<?php
/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/api/gorev_api.php
 * KULLANICI / SAYFA: Süper Admin -> Görev Tasarlayıcı ve Görev Listesi
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Adminin görev tasarlayıcıda yazdığı hikayeyi ve kurduğu blok cevap anahtarını
 * teslim alıp veritabanına kilitleyen kargo görevlisi. Ayrıca görev listeleme,
 * güncelleme, silme ve yayına alma işlerini de yapar.
 *
 * DESTEKLENEN İŞLEMLER:
 *   yeni_gorev_kaydet  — yeni görev ekler
 *   gorev_guncelle     — mevcut görevi günceller
 *   gorev_getir        — tek görevi düzenlemek için çeker
 *   gorev_listele      — bölüme göre görevleri listeler
 *   gorev_sil          — görevi siler
 *   yayin_degistir     — görevi yayına alır / taslağa çeker
 *   sira_degistir      — görevin sırasını yukarı/aşağı taşır
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] SABİT KODLAMA KALDIRILDI (Bilinen Sorunlar #17):
 *      Eskiden bolum_id=1, sira_no=1, puan=20 kodun içine gömülüydü; her görev
 *      1. bölüme, 1. sıraya kaydediliyordu. Artık admin seçiyor.
 *  - [2026-08] Süper Admin oturumu zorunlu (#1).
 *  - [2026-08] Güncelleme / silme / listeleme / yayın kontrolü eklendi.
 * ============================================================================
 */

require_once __DIR__ . '/../../bilesenler/guvenlik.php';
require_once __DIR__ . '/../../bilesenler/api_yanit.php';

superAdminZorunluApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiHata('Geçersiz istek türü!', 405);
}

$gelen = istekVerisi();
$islem = $gelen['islem'] ?? '';

/** Bölümün o anki en büyük sıra numarasının bir fazlası */
function sonrakiSira(PDO $pdo, int $bolumId): int
{
    $st = $pdo->prepare("SELECT COALESCE(MAX(sira_no), 0) + 1 FROM v2_gorevler WHERE bolum_id = ?");
    $st->execute([$bolumId]);
    return max(1, (int) $st->fetchColumn());
}

try {
    switch ($islem) {

        /* ---------------- YENİ GÖREV ---------------- */
        case 'yeni_gorev_kaydet': {
            $baslik   = trim((string) ($gelen['baslik'] ?? ''));
            $bolum_id = (int) ($gelen['bolum_id'] ?? 0);
            $puan     = (int) ($gelen['puan'] ?? 20);
            $tip      = beyazListe($gelen['gorev_tipi'] ?? 'standart', ['standart', 'bolum_sonu_canavari'], 'standart');
            $sira     = (int) ($gelen['sira_no'] ?? 0);

            if ($baslik === '') {
                apiHata('Görev başlığı boş olamaz!');
            }
            if ($bolum_id <= 0) {
                apiHata('Lütfen görevin ekleneceği bölümü seçin.');
            }

            $st = $pdo->prepare("SELECT id FROM bolumler WHERE id = ?");
            $st->execute([$bolum_id]);
            if (!$st->fetch()) {
                apiHata('Seçilen bölüm bulunamadı.');
            }

            $puan = max(1, min($puan, 500));
            if ($sira <= 0) {
                $sira = sonrakiSira($pdo, $bolum_id);
            }

            $icerik_json = json_encode($gelen['icerik'] ?? [], JSON_UNESCAPED_UNICODE);

            $st = $pdo->prepare(
                "INSERT INTO v2_gorevler (bolum_id, gorev_adi, sira_no, puan, gorev_tipi, yayinda, icerik_json)
                 VALUES (?, ?, ?, ?, ?, 1, ?)"
            );
            $st->execute([$bolum_id, $baslik, $sira, $puan, $tip, $icerik_json]);

            apiBasari(['gorev_id' => (int) $pdo->lastInsertId()], 'Görev başarıyla kaydedildi.');
        }

        /* ---------------- GÖREV GÜNCELLE ---------------- */
        case 'gorev_guncelle': {
            $gorev_id = (int) ($gelen['gorev_id'] ?? 0);
            $baslik   = trim((string) ($gelen['baslik'] ?? ''));
            $bolum_id = (int) ($gelen['bolum_id'] ?? 0);
            $puan     = max(1, min((int) ($gelen['puan'] ?? 20), 500));
            $tip      = beyazListe($gelen['gorev_tipi'] ?? 'standart', ['standart', 'bolum_sonu_canavari'], 'standart');
            $sira     = max(1, (int) ($gelen['sira_no'] ?? 1));

            if ($gorev_id <= 0) {
                apiHata('Güncellenecek görev seçilmedi.');
            }
            if ($baslik === '') {
                apiHata('Görev başlığı boş olamaz!');
            }
            if ($bolum_id <= 0) {
                apiHata('Lütfen bölüm seçin.');
            }

            /* [2026-11] Tasarlayıcının bilmediği alanları KORU.
               Müfredat görevlerinde icerik_json içinde "sahne" (dekor + kuklalar)
               var; tasarlayıcı bu alanı göndermiyor. Eskiden güncelleme bütün
               içeriği baştan yazdığı için görevi düzenleyip kaydeden admin
               kuklaları ve dekoru farkında olmadan siliyordu. */
            $yeniIcerik = is_array($gelen['icerik'] ?? null) ? $gelen['icerik'] : [];
            $st = $pdo->prepare("SELECT icerik_json FROM v2_gorevler WHERE id = ?");
            $st->execute([$gorev_id]);
            $eskiIcerik = json_decode((string) $st->fetchColumn(), true) ?: [];
            foreach ($eskiIcerik as $anahtar => $deger) {
                if (!array_key_exists($anahtar, $yeniIcerik) && in_array($anahtar, ['sahne', 'mufredat'], true)) {
                    $yeniIcerik[$anahtar] = $deger;
                }
            }
            $icerik_json = json_encode($yeniIcerik, JSON_UNESCAPED_UNICODE);

            $pdo->prepare(
                "UPDATE v2_gorevler
                    SET bolum_id = ?, gorev_adi = ?, sira_no = ?, puan = ?, gorev_tipi = ?, icerik_json = ?
                  WHERE id = ?"
            )->execute([$bolum_id, $baslik, $sira, $puan, $tip, $icerik_json, $gorev_id]);

            apiBasari(['gorev_id' => $gorev_id], 'Görev güncellendi.');
        }

        /* ---------------- GÖREV GETİR ---------------- */
        case 'gorev_getir': {
            $gorev_id = (int) ($gelen['gorev_id'] ?? 0);

            $st = $pdo->prepare("SELECT * FROM v2_gorevler WHERE id = ?");
            $st->execute([$gorev_id]);
            $gorev = $st->fetch(PDO::FETCH_ASSOC);

            if (!$gorev) {
                apiHata('Görev bulunamadı.', 404);
            }

            $gorev['icerik'] = json_decode($gorev['icerik_json'] ?? '{}', true);
            apiBasari(['gorev' => $gorev]);
        }

        /* ---------------- GÖREV LİSTELE ---------------- */
        case 'gorev_listele': {
            $bolum_id = (int) ($gelen['bolum_id'] ?? 0);

            if ($bolum_id > 0) {
                $st = $pdo->prepare(
                    "SELECT g.id, g.bolum_id, g.gorev_adi, g.sira_no, g.puan, g.gorev_tipi, g.yayinda,
                            b.bolum_adi
                       FROM v2_gorevler g
                  LEFT JOIN bolumler b ON b.id = g.bolum_id
                      WHERE g.bolum_id = ?
                      ORDER BY g.sira_no ASC, g.id ASC"
                );
                $st->execute([$bolum_id]);
            } else {
                $st = $pdo->query(
                    "SELECT g.id, g.bolum_id, g.gorev_adi, g.sira_no, g.puan, g.gorev_tipi, g.yayinda,
                            b.bolum_adi
                       FROM v2_gorevler g
                  LEFT JOIN bolumler b ON b.id = g.bolum_id
                      ORDER BY g.bolum_id ASC, g.sira_no ASC"
                );
            }

            apiBasari(['gorevler' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        /* ---------------- CEVAP ANAHTARI ÖZETİ ----------------
         * [2026-08] Öğretmen "bu görevin cevap anahtarında ne var?" diye
         * merak ettiğinde blok listesini okunabilir şekilde döndürür.
         * Öğrencinin neden eşleşmediğini anlamak için kullanılır. */
        case 'anahtar_ozeti': {
            $gorev_id = (int) ($gelen['gorev_id'] ?? 0);

            $st = $pdo->prepare("SELECT gorev_adi, icerik_json FROM v2_gorevler WHERE id = ?");
            $st->execute([$gorev_id]);
            $gorev = $st->fetch(PDO::FETCH_ASSOC);

            if (!$gorev) {
                apiHata('Görev bulunamadı.', 404);
            }

            require_once __DIR__ . '/v2_hakem.php';

            $icerik    = json_decode($gorev['icerik_json'] ?? '{}', true) ?: [];
            $blueprint = $icerik['blueprint'] ?? [];

            $ana = blueprintSadelestir($blueprint['ana_cozum'] ?? '');

            $alternatifler = [];
            foreach ((array) ($blueprint['alternatif_cozumler'] ?? []) as $i => $altXml) {
                $ham = is_array($altXml) ? ($altXml['xml'] ?? '') : $altXml;
                $alt = blueprintSadelestir($ham);
                if ($alt) {
                    $alternatifler[] = [
                        'no'      => $i + 1,
                        'bloklar' => blokTipleriniListele($alt),
                        'sayi'    => blokSay($alt),
                    ];
                }
            }

            apiBasari([
                'gorev_adi'     => $gorev['gorev_adi'],
                'ana_bloklar'   => $ana ? blokTipleriniListele($ana) : [],
                'ana_sayi'      => $ana ? blokSay($ana) : 0,
                'alternatifler' => $alternatifler,
            ]);
        }

        /* ---------------- GÖREV SİL ---------------- */
        case 'gorev_sil': {
            $gorev_id = (int) ($gelen['gorev_id'] ?? 0);
            if ($gorev_id <= 0) {
                apiHata('Silinecek görev seçilmedi.');
            }

            $pdo->prepare("DELETE FROM v2_gorevler WHERE id = ?")->execute([$gorev_id]);
            apiBasari([], 'Görev silindi.');
        }

        /* ---------------- YAYIN DURUMU ---------------- */
        case 'yayin_degistir': {
            $gorev_id = (int) ($gelen['gorev_id'] ?? 0);
            $yayinda  = !empty($gelen['yayinda']) ? 1 : 0;

            $pdo->prepare("UPDATE v2_gorevler SET yayinda = ? WHERE id = ?")->execute([$yayinda, $gorev_id]);

            apiBasari(['yayinda' => $yayinda], $yayinda ? 'Görev yayına alındı.' : 'Görev taslağa çekildi.');
        }

        /* ---------------- SIRA DEĞİŞTİR ---------------- */
        case 'sira_degistir': {
            $gorev_id = (int) ($gelen['gorev_id'] ?? 0);
            $yon      = beyazListe($gelen['yon'] ?? '', ['yukari', 'asagi']);

            if ($gorev_id <= 0 || !$yon) {
                apiHata('Geçersiz sıralama isteği.');
            }

            $st = $pdo->prepare("SELECT bolum_id, sira_no FROM v2_gorevler WHERE id = ?");
            $st->execute([$gorev_id]);
            $mevcut = $st->fetch(PDO::FETCH_ASSOC);

            if (!$mevcut) {
                apiHata('Görev bulunamadı.', 404);
            }

            $karsilastirma = ($yon === 'yukari') ? '<' : '>';
            $siralama      = ($yon === 'yukari') ? 'DESC' : 'ASC';

            $st = $pdo->prepare(
                "SELECT id, sira_no FROM v2_gorevler
                  WHERE bolum_id = ? AND sira_no {$karsilastirma} ?
                  ORDER BY sira_no {$siralama} LIMIT 1"
            );
            $st->execute([$mevcut['bolum_id'], $mevcut['sira_no']]);
            $komsu = $st->fetch(PDO::FETCH_ASSOC);

            if (!$komsu) {
                apiBasari([], 'Görev zaten uçta.');
            }

            $pdo->beginTransaction();
            $g = $pdo->prepare("UPDATE v2_gorevler SET sira_no = ? WHERE id = ?");
            $g->execute([$komsu['sira_no'], $gorev_id]);
            $g->execute([$mevcut['sira_no'], $komsu['id']]);
            $pdo->commit();

            apiBasari([], 'Sıra güncellendi.');
        }

        default:
            apiHata('Tanımsız işlem!');
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiIstisna($e, 'İşlem tamamlanamadı.');
}
