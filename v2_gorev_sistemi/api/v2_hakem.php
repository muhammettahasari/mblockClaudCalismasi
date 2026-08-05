<?php
/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/api/v2_hakem.php
 * KULLANICI / SAYFA: Sunucu tarafı yardımcı (doğrudan çağrılmaz)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Öğrencinin dizdiği blokların, adminin hazırladığı "cevap anahtarı"
 * (Master Blueprint) ile aynı olup olmadığına karar veren hakem.
 *
 * NEDEN SUNUCUDA?
 * Kontrol tarayıcıda yapılsaydı öğrenci konsoldan "ben kazandım" diyebilirdi.
 * Bu yüzden öğrencinin blok haritası (XML) sunucuya gönderilir, karşılaştırma
 * BURADA yapılır ve puan ancak buradan onay çıkarsa verilir.
 *
 * NASIL KARŞILAŞTIRIR?
 * Blockly XML'inde blokların id'si, ekrandaki x/y konumu ve boşluklar her
 * seferinde değişir; bunlar anlamsızdır. Hakem önce XML'i "sadeleştirir":
 *   - id, x, y, blok ismi gibi gürültüyü atar
 *   - geriye blok TİPİ, alan DEĞERLERİ ve blokların BAĞLANMA SIRASI kalır
 * Sonra iki sadeleşmiş yapıyı karşılaştırır. Böylece öğrenci blokları
 * ekranın neresine koyarsa koysun, doğru dizdiyse kabul edilir.
 *
 * TEMEL İŞLEVLER:
 *  1. blueprintSadelestir($xml)   — XML'i karşılaştırılabilir yapıya çevirir
 *  2. cozumDogruMu($ogrenciXml, $blueprint) — ana çözüm + alternatifleri dener
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (Bilinen Sorunlar #17).
 * ============================================================================
 */

/**
 * Blockly XML metnini karşılaştırılabilir sade bir diziye çevirir.
 * Anlamsız veriler (id, x, y, editör bilgisi) atılır.
 *
 * @return array|null  XML bozuksa null
 */
function blueprintSadelestir(?string $xml): ?array
{
    $xml = trim((string) $xml);
    if ($xml === '') {
        return null;
    }

    $oncekiAyar = libxml_use_internal_errors(true);
    $kok = simplexml_load_string($xml);
    libxml_clear_errors();
    libxml_use_internal_errors($oncekiAyar);

    if ($kok === false) {
        return null;
    }

    $bloklar = [];
    foreach ($kok->children() as $cocuk) {
        if ($cocuk->getName() === 'block') {
            $bloklar[] = blokSadelestir($cocuk);
        }
    }

    // Üst seviye blok yığınlarının sırası kullanıcıya göre değişebilir → sırala
    usort($bloklar, static fn($a, $b) => strcmp(json_encode($a), json_encode($b)));

    return $bloklar;
}

/** Tek bir <block> düğümünü ve altındaki her şeyi sadeleştirir. */
function blokSadelestir(SimpleXMLElement $blok): array
{
    $sonuc = [
        't' => (string) ($blok['type'] ?? ''),   // blok tipi
        'f' => [],                                // alanlar (field)
        'g' => [],                                // girdiler (value / statement)
        'n' => null,                              // alta bağlanan blok (next)
    ];

    foreach ($blok->children() as $cocuk) {
        $ad = $cocuk->getName();

        switch ($ad) {
            case 'field':
                // Örn: <field name="NUM">10</field>
                $sonuc['f'][(string) $cocuk['name']] = trim((string) $cocuk);
                break;

            case 'value':      // Sayı / metin yuvası
            case 'statement':  // İçine blok girilen yuva (döngü gövdesi vb.)
                $icBloklar = [];
                foreach ($cocuk->children() as $icCocuk) {
                    if ($icCocuk->getName() === 'block' || $icCocuk->getName() === 'shadow') {
                        $icBloklar[] = blokSadelestir($icCocuk);
                    }
                }
                if ($icBloklar) {
                    $sonuc['g'][(string) $cocuk['name']] = $icBloklar;
                }
                break;

            case 'next':
                foreach ($cocuk->children() as $icCocuk) {
                    if ($icCocuk->getName() === 'block') {
                        $sonuc['n'] = blokSadelestir($icCocuk);
                        break;
                    }
                }
                break;

            // mutation, comment, data gibi düğümler karşılaştırmada dikkate alınmaz
        }
    }

    // Alan sırası XML'de değişebilir → anahtara göre sırala
    ksort($sonuc['f']);
    ksort($sonuc['g']);

    return $sonuc;
}

/**
 * Öğrencinin çözümü, cevap anahtarlarından herhangi biriyle eşleşiyor mu?
 *
 * @param string $ogrenciXml  Öğrencinin blok haritası (Blockly XML)
 * @param array  $blueprint   ['ana_cozum' => xml, 'alternatif_cozumler' => [xml, ...]]
 * @return array ['dogru' => bool, 'eslesen' => 'ana'|'alternatif_2'|null, 'sebep' => string]
 */
function cozumDogruMu(string $ogrenciXml, array $blueprint): array
{
    $ogrenci = blueprintSadelestir($ogrenciXml);

    if ($ogrenci === null) {
        return ['dogru' => false, 'eslesen' => null, 'sebep' => 'Kod okunamadı.'];
    }
    if ($ogrenci === []) {
        return ['dogru' => false, 'eslesen' => null, 'sebep' => 'Hiç blok eklememişsin.'];
    }

    $ogrenciImza = json_encode($ogrenci, JSON_UNESCAPED_UNICODE);

    // 1. Ana çözüm
    $ana = blueprintSadelestir($blueprint['ana_cozum'] ?? '');
    if ($ana !== null && $ana !== []
        && json_encode($ana, JSON_UNESCAPED_UNICODE) === $ogrenciImza) {
        return ['dogru' => true, 'eslesen' => 'ana', 'sebep' => 'Ana çözümle birebir eşleşti.'];
    }

    // 2. Alternatif çözümler
    $alternatifler = $blueprint['alternatif_cozumler'] ?? [];
    if (is_array($alternatifler)) {
        foreach (array_values($alternatifler) as $i => $altXml) {
            $alt = blueprintSadelestir(is_array($altXml) ? ($altXml['xml'] ?? '') : $altXml);
            if ($alt !== null && $alt !== []
                && json_encode($alt, JSON_UNESCAPED_UNICODE) === $ogrenciImza) {
                return [
                    'dogru'   => true,
                    'eslesen' => 'alternatif_' . ($i + 1),
                    'sebep'   => 'Alternatif çözümle eşleşti.',
                ];
            }
        }
    }

    // 3. Yaklaşan ipucu: blok sayısı farkına göre yönlendir
    $ogrenciBlokSayisi = blokSay($ogrenci);
    $anaBlokSayisi     = $ana ? blokSay($ana) : 0;

    if ($anaBlokSayisi > 0 && $ogrenciBlokSayisi < $anaBlokSayisi) {
        $sebep = 'Eksik blok var gibi görünüyor. Görev metnini tekrar oku.';
    } elseif ($anaBlokSayisi > 0 && $ogrenciBlokSayisi > $anaBlokSayisi) {
        $sebep = 'Fazladan blok kullanmışsın. Gereksiz blokları kaldırmayı dene.';
    } else {
        $sebep = 'Bloklar doğru ama sıralama ya da içlerindeki değerler farklı.';
    }

    return ['dogru' => false, 'eslesen' => null, 'sebep' => $sebep];
}

/** Sadeleşmiş yapıdaki toplam blok sayısını verir (ipucu üretmek için). */
function blokSay($dugum): int
{
    if (is_array($dugum) && isset($dugum['t'])) {
        $sayi = 1;
        foreach ($dugum['g'] as $grup) {
            foreach ($grup as $ic) {
                $sayi += blokSay($ic);
            }
        }
        if ($dugum['n']) {
            $sayi += blokSay($dugum['n']);
        }
        return $sayi;
    }

    $sayi = 0;
    if (is_array($dugum)) {
        foreach ($dugum as $eleman) {
            $sayi += blokSay($eleman);
        }
    }
    return $sayi;
}
