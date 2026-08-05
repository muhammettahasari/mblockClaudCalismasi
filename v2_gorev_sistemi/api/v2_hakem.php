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
 *   - id, x, y, editör bilgisi gibi gürültüyü atar
 *   - geriye blok TİPİ, alan DEĞERLERİ ve blokların BAĞLANMA SIRASI kalır
 * Sonra iki sadeleşmiş yapıyı karşılaştırır. Böylece öğrenci blokları
 * ekranın neresine koyarsa koysun, doğru dizdiyse kabul edilir.
 *
 * TEMEL İŞLEVLER:
 *  1. blueprintSadelestir($xml)   — XML'i karşılaştırılabilir yapıya çevirir
 *  2. cozumDogruMu($ogrenciXml, $blueprint) — ana çözüm + alternatifleri dener
 *  3. hakemTeshis($xml)           — sorun ayıklama için özet bilgi
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (Bilinen Sorunlar #17).
 *  - [2026-08] KRİTİK: XML NAMESPACE SORUNU ÇÖZÜLDÜ.
 *      Blockly XML'i varsayılan namespace ile yazar:
 *          <xml xmlns="https://developers.google.com/blockly/xml">
 *      SimpleXML'de $kok->children() bu namespace'teki çocukları DÖNDÜRMEZ.
 *      Bu yüzden öğrenci ne yaparsa yapsın sadeleştirme [] dönüyor,
 *      hakem her seferinde "Hiç blok eklememişsin." diyordu. Üstelik cevap
 *      anahtarı da aynı şekilde boş okunduğu için hiçbir görev ASLA doğru
 *      sayılamazdı.
 *      Çözüm: SimpleXML yerine DOMDocument + localName kullanılıyor;
 *      localName namespace'ten tamamen bağımsızdır. Ayrıca güvenlik için
 *      xmlns bildirimi de temizleniyor.
 * ============================================================================
 */

/**
 * Blockly XML metnini karşılaştırılabilir sade bir diziye çevirir.
 * Anlamsız veriler (id, x, y, editör bilgisi) atılır.
 *
 * @return array|null  XML bozuksa null, blok yoksa boş dizi
 */
function blueprintSadelestir(?string $xml): ?array
{
    $xml = trim((string) $xml);
    if ($xml === '') {
        return null;
    }

    // Güvenlik ağı: namespace bildirimlerini temizle (DOM zaten localName kullanıyor)
    $xml = preg_replace('/\s+xmlns(:\w+)?\s*=\s*(["\'])[^"\']*\2/', '', $xml);

    $oncekiAyar = libxml_use_internal_errors(true);

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $yuklendi = $dom->loadXML($xml, LIBXML_NONET);

    libxml_clear_errors();
    libxml_use_internal_errors($oncekiAyar);

    if (!$yuklendi || !$dom->documentElement) {
        return null;
    }

    $bloklar = [];
    foreach ($dom->documentElement->childNodes as $cocuk) {
        // localName namespace ön ekini yok sayar — <block> de <b:block> de yakalanır
        if ($cocuk->nodeType === XML_ELEMENT_NODE && $cocuk->localName === 'block') {
            $bloklar[] = blokSadelestir($cocuk);
        }
    }

    // Üst seviye blok yığınlarının sırası kullanıcıya göre değişebilir → sırala
    usort($bloklar, static fn($a, $b) => strcmp(
        json_encode($a, JSON_UNESCAPED_UNICODE),
        json_encode($b, JSON_UNESCAPED_UNICODE)
    ));

    return $bloklar;
}

/** Tek bir <block> düğümünü ve altındaki her şeyi sadeleştirir. */
function blokSadelestir(DOMElement $blok): array
{
    $sonuc = [
        't' => (string) $blok->getAttribute('type'),   // blok tipi
        'f' => [],                                      // alanlar (field)
        'g' => [],                                      // girdiler (value / statement)
        'n' => null,                                    // alta bağlanan blok (next)
    ];

    foreach ($blok->childNodes as $cocuk) {
        if ($cocuk->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        switch ($cocuk->localName) {
            case 'field':
                // Örn: <field name="NUM">10</field>
                $sonuc['f'][$cocuk->getAttribute('name')] = trim($cocuk->textContent);
                break;

            case 'value':      // Sayı / metin yuvası
            case 'statement':  // İçine blok girilen yuva (döngü gövdesi vb.)
                $icBloklar = [];
                foreach ($cocuk->childNodes as $ic) {
                    if ($ic->nodeType === XML_ELEMENT_NODE
                        && ($ic->localName === 'block' || $ic->localName === 'shadow')) {
                        $icBloklar[] = blokSadelestir($ic);
                    }
                }
                if ($icBloklar) {
                    $sonuc['g'][$cocuk->getAttribute('name')] = $icBloklar;
                }
                break;

            case 'next':
                foreach ($cocuk->childNodes as $ic) {
                    if ($ic->nodeType === XML_ELEMENT_NODE && $ic->localName === 'block') {
                        $sonuc['n'] = blokSadelestir($ic);
                        break 2;
                    }
                }
                break;

            // mutation, comment, data gibi düğümler karşılaştırmada dikkate alınmaz
        }
    }

    ksort($sonuc['f']);
    ksort($sonuc['g']);

    return $sonuc;
}

/**
 * Öğrencinin çözümü, cevap anahtarlarından herhangi biriyle eşleşiyor mu?
 *
 * @param string $ogrenciXml  Öğrencinin blok haritası (Blockly XML)
 * @param array  $blueprint   ['ana_cozum' => xml, 'alternatif_cozumler' => [xml, ...]]
 * @return array ['dogru'=>bool, 'eslesen'=>string|null, 'sebep'=>string, 'teshis'=>array]
 */
function cozumDogruMu(string $ogrenciXml, array $blueprint): array
{
    $ogrenci = blueprintSadelestir($ogrenciXml);
    $ana     = blueprintSadelestir($blueprint['ana_cozum'] ?? '');

    // Sorun ayıklama için sayılar + blok listeleri (yanlış cevapta arayüze gönderilir)
    $teshis = [
        'gelen_xml_uzunluk'   => strlen($ogrenciXml),
        'ogrenci_ust_blok'    => is_array($ogrenci) ? count($ogrenci) : -1,
        'ogrenci_toplam_blok' => is_array($ogrenci) ? blokSay($ogrenci) : -1,
        'anahtar_ust_blok'    => is_array($ana) ? count($ana) : -1,
        'anahtar_toplam_blok' => is_array($ana) ? blokSay($ana) : -1,
        // [2026-08] Hangi blokların farklı olduğunu görebilmek için düz liste
        'ogrenci_bloklar'     => is_array($ogrenci) ? blokTipleriniListele($ogrenci) : [],
        'anahtar_bloklar'     => is_array($ana) ? blokTipleriniListele($ana) : [],
    ];

    if ($ogrenci === null) {
        return ['dogru' => false, 'eslesen' => null,
                'sebep' => 'Kodun okunamadı. Sayfayı yenileyip tekrar dene.', 'teshis' => $teshis];
    }
    if ($ogrenci === []) {
        return ['dogru' => false, 'eslesen' => null,
                'sebep' => 'Kod alanında hiç blok görünmüyor. Blokları soldaki menüden sürükleyip bırak.',
                'teshis' => $teshis];
    }

    $ogrenciImza = json_encode($ogrenci, JSON_UNESCAPED_UNICODE);

    // 1. Ana çözüm
    if ($ana !== null && $ana !== []
        && json_encode($ana, JSON_UNESCAPED_UNICODE) === $ogrenciImza) {
        return ['dogru' => true, 'eslesen' => 'ana',
                'sebep' => 'Ana çözümle birebir eşleşti.', 'teshis' => $teshis];
    }

    // 2. Alternatif çözümler
    $alternatifler = $blueprint['alternatif_cozumler'] ?? [];
    if (is_array($alternatifler)) {
        foreach (array_values($alternatifler) as $i => $altXml) {
            $ham = is_array($altXml) ? ($altXml['xml'] ?? '') : $altXml;
            $alt = blueprintSadelestir($ham);
            if ($alt !== null && $alt !== []
                && json_encode($alt, JSON_UNESCAPED_UNICODE) === $ogrenciImza) {
                return ['dogru' => true, 'eslesen' => 'alternatif_' . ($i + 1),
                        'sebep' => 'Alternatif çözümle eşleşti.', 'teshis' => $teshis];
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

    return ['dogru' => false, 'eslesen' => null, 'sebep' => $sebep, 'teshis' => $teshis];
}

/**
 * [2026-08] Sadeleşmiş yapıyı okunabilir düz listeye çevirir.
 * Örn: ["mblock_olay_tus_basildiginda(TUS=SPACE)", "  mblock_hareket_git",
 *       "    math_number(NUM=10)", ...]
 * Öğretmenin "cevap anahtarında fazladan ne var?" sorusunu yanıtlar.
 */
function blokTipleriniListele($dugum, int $derinlik = 0): array
{
    $liste = [];

    // Tek bir blok mu?
    if (is_array($dugum) && isset($dugum['t'])) {
        $etiket = str_repeat('  ', $derinlik) . $dugum['t'];

        if (!empty($dugum['f'])) {
            $alanlar = [];
            foreach ($dugum['f'] as $ad => $deger) {
                $alanlar[] = $ad . '=' . $deger;
            }
            $etiket .= '(' . implode(', ', $alanlar) . ')';
        }
        $liste[] = $etiket;

        // İçindeki yuvalar (value / statement)
        foreach ($dugum['g'] as $yuvaAdi => $grup) {
            foreach ($grup as $ic) {
                foreach (blokTipleriniListele($ic, $derinlik + 1) as $satir) {
                    $liste[] = $satir;
                }
            }
        }

        // Alta bağlanan blok — aynı seviyede devam eder
        if ($dugum['n']) {
            foreach (blokTipleriniListele($dugum['n'], $derinlik) as $satir) {
                $liste[] = $satir;
            }
        }

        return $liste;
    }

    // Blok listesi
    if (is_array($dugum)) {
        foreach ($dugum as $eleman) {
            foreach (blokTipleriniListele($eleman, $derinlik) as $satir) {
                $liste[] = $satir;
            }
        }
    }

    return $liste;
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
