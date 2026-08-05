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

    /* [2026-08] BOŞTA DURAN (YETİM) BLOKLARI YOK SAY
       Çalışma alanında hiçbir yere bağlanmamış bloklar KOD ÜRETMEZ —
       kod_tercumani.js zaten onları atlıyor ("başıboş blokları engelle").
       Ama hakem onları sayıyordu. Sonuç: admin tasarlarken bir sayı bloğunu
       boşta unutunca cevap anahtarı 1 blok fazla görünüyor ve hiçbir öğrenci
       görevi tamamlayamıyordu.
       Kural: en az bir ŞAPKA bloğu (mblock_olay_*) varsa, üst seviyedeki
       diğer her şey ölü koddur ve yok sayılır. Hiç şapka yoksa dokunmuyoruz
       (eski görevler bozulmasın). */
    $sapkaVarMi = false;
    foreach ($bloklar as $b) {
        if (strpos($b['t'], 'mblock_olay_') === 0) {
            $sapkaVarMi = true;
            break;
        }
    }

    if ($sapkaVarMi) {
        $bloklar = array_values(array_filter($bloklar, static function ($b) {
            return strpos($b['t'], 'mblock_olay_') === 0;
        }));
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

    /* 3. AKILLI İPUCU
       [2026-08] Eskiden sadece "Eksik blok var" deniyordu ve öğrenci hangi
       bloğu unuttuğunu bilemiyordu. Artık eksik/fazla blokların ADI yazılıyor. */
    $ogrenciTipler = blokTipSayaci($ogrenci);
    $anaTipler     = $ana ? blokTipSayaci($ana) : [];

    $eksikler = [];
    foreach ($anaTipler as $tip => $adet) {
        $fark = $adet - ($ogrenciTipler[$tip] ?? 0);
        if ($fark > 0 && $tip !== 'math_number' && $tip !== 'text') {
            $eksikler[] = blokAdi($tip) . ($fark > 1 ? " ({$fark} tane)" : '');
        }
    }

    $fazlalar = [];
    foreach ($ogrenciTipler as $tip => $adet) {
        $fark = $adet - ($anaTipler[$tip] ?? 0);
        if ($fark > 0 && $tip !== 'math_number' && $tip !== 'text') {
            $fazlalar[] = blokAdi($tip) . ($fark > 1 ? " ({$fark} tane)" : '');
        }
    }

    if ($eksikler && !$fazlalar) {
        $sebep = 'Şu blok(lar) eksik: ' . implode(', ', $eksikler);
    } elseif ($fazlalar && !$eksikler) {
        $sebep = 'Şu blok(lar) fazla: ' . implode(', ', $fazlalar) . '. Gereksiz olanları sil.';
    } elseif ($eksikler && $fazlalar) {
        $sebep = 'Eksik: ' . implode(', ', $eksikler) . ' | Fazla: ' . implode(', ', $fazlalar);
    } else {
        /* Blok TÜRLERİ aynı → fark ya sıralamada ya da sayı/metin değerlerinde.
           [2026-08] Hangi satırda ayrıldığını söyleyelim; "değerler farklı"
           demek öğrenciye hiçbir şey anlatmıyordu. */
        $ogrenciSatirlar = blokTipleriniListele($ogrenci);
        $anaSatirlar     = $ana ? blokTipleriniListele($ana) : [];

        $ilkFark = null;
        $adet    = max(count($ogrenciSatirlar), count($anaSatirlar));
        for ($i = 0; $i < $adet; $i++) {
            $seninki  = $ogrenciSatirlar[$i] ?? '(yok)';
            $olmasiGereken = $anaSatirlar[$i] ?? '(yok)';
            if ($seninki !== $olmasiGereken) {
                $ilkFark = [trim($seninki), trim($olmasiGereken)];
                break;
            }
        }

        if ($ilkFark) {
            // Sayı/metin satırlarını öğrencinin anlayacağı dile çevir
            $okunur = static function (string $satir): string {
                if (preg_match('/^math_number\(NUM=(.*)\)$/', $satir, $m)) {
                    return 'sayı: ' . $m[1];
                }
                if (preg_match('/^text\(TEXT=(.*)\)$/', $satir, $m)) {
                    return 'metin: "' . $m[1] . '"';
                }
                if (preg_match('/^(\w+)\((.*)\)$/', $satir, $m)) {
                    return blokAdi($m[1]) . ' (' . $m[2] . ')';
                }
                return $satir === '(yok)' ? 'hiçbir şey' : blokAdi($satir);
            };

            $sebep = 'Bloklar doğru ama bir değer tutmuyor → '
                   . 'sende: ' . $okunur($ilkFark[0])
                   . ' | olması gereken: ' . $okunur($ilkFark[1]);
        } else {
            $sebep = 'Bloklar doğru ama sıralama farklı. Blokların yukarıdan aşağı dizilişini kontrol et.';
        }
    }

    return ['dogru' => false, 'eslesen' => null, 'sebep' => $sebep, 'teshis' => $teshis];
}

/**
 * [2026-08] Blok tiplerinin öğrenciye gösterilecek Türkçe adları.
 * "Eksik blok var" demek yerine "Eksik: 'Merhaba! de' bloğu" diyebilmek için.
 */
function blokAdi(string $tip): string
{
    static $sozluk = [
        // Olaylar
        'mblock_olay_bayrak_tiklandiginda'   => '🏳️ Yeşil bayrak tıklandığında',
        'mblock_olay_tus_basildiginda'       => '⌨️ Tuşa basıldığında',
        'mblock_olay_kukla_tiklandiginda'    => '🖱️ Kuklaya tıklandığında',
        // Hareket
        'mblock_hareket_git'                 => '👣 adım git',
        'mblock_hareket_don_sag'             => '↻ sağa dön',
        'mblock_hareket_don_sol'             => '↺ sola dön',
        'mblock_hareket_xy_git'              => '📍 x,y konumuna git',
        'mblock_hareket_rastgele_git'        => '🎲 rastgele konuma git',
        'mblock_hareket_suzul_xy'            => '🕊️ x,y konumuna süzül',
        'mblock_hareket_suzul_rastgele'      => '🕊️ rastgele konuma süzül',
        'mblock_hareket_yone_don'            => '🧭 yönüne dön',
        'mblock_hareket_hedefe_don'          => '🧭 hedefe doğru dön',
        'mblock_hareket_x_yap'               => 'x konumunu ayarla',
        'mblock_hareket_y_yap'               => 'y konumunu ayarla',
        'mblock_hareket_x_degistir'          => "x'i değiştir",
        'mblock_hareket_y_degistir'          => "y'yi değiştir",
        'mblock_hareket_kenardaysa_sek'      => '🪃 kenara gelince sek',
        // Görünüm
        'mblock_gorunum_merhaba_de'          => '💬 ... de',
        'mblock_gorunum_merhaba_de_sureli'   => '💬 ... saniye boyunca de',
        'mblock_gorunum_dusun'               => '💭 ... düşün',
        'mblock_gorunum_dusun_sureli'        => '💭 ... saniye boyunca düşün',
        'mblock_gorunum_goster'              => '👁️ göster',
        'mblock_gorunum_gizle'               => '🚫 gizle',
        'mblock_gorunum_sonraki_kilik'       => '👕 sonraki kostüm',
        'mblock_gorunum_kiligina_gec'        => '👕 kostüme geç',
        'mblock_gorunum_boyut_yap'           => '📏 boyutu ayarla',
        'mblock_gorunum_boyut_degistir'      => '📏 boyutu değiştir',
        'mblock_gorunum_sonraki_dekor'       => '🖼️ sonraki dekor',
        'mblock_gorunum_dekora_gec'          => '🖼️ dekora geç',
        // Kontrol
        'mblock_kontrol_bekle'               => '⏳ saniye bekle',
        'mblock_kontrol_tekrarla'            => '🔁 kez tekrarla',
        'mblock_kontrol_surekli'             => '🔄 sürekli tekrarla',
        'mblock_kontrol_eger_ise'            => '❓ eğer ... ise',
        'mblock_kontrol_eger_degilse'        => '❓ eğer ... değilse',
        'mblock_kontrol_olana_kadar_tekrarla'=> '🔁 ... olana kadar tekrarla',
        // Ses
        'mblock_ses_cal_bitene_kadar'        => '🔊 sesi bitene kadar çal',
        // Değerler
        'math_number'                        => 'sayı',
        'text'                               => 'metin',
    ];

    if (isset($sozluk[$tip])) {
        return $sozluk[$tip];
    }

    // Sözlükte yoksa tip adından okunabilir bir ad türet
    $sade = preg_replace('/^mblock_(hareket|gorunum|ses|olay|kontrol|algilama|islem)_/', '', $tip);
    return str_replace('_', ' ', $sade);
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

/**
 * [2026-08] Sadeleşmiş yapıdaki blok tiplerini sayar.
 * Örn: ['mblock_hareket_git' => 1, 'math_number' => 2]
 * Eksik/fazla blok ipucu üretmek için kullanılır.
 */
function blokTipSayaci($dugum, array &$sayac = []): array
{
    if (is_array($dugum) && isset($dugum['t'])) {
        $tip = $dugum['t'];
        $sayac[$tip] = ($sayac[$tip] ?? 0) + 1;

        foreach ($dugum['g'] as $grup) {
            foreach ($grup as $ic) {
                blokTipSayaci($ic, $sayac);
            }
        }
        if ($dugum['n']) {
            blokTipSayaci($dugum['n'], $sayac);
        }
        return $sayac;
    }

    if (is_array($dugum)) {
        foreach ($dugum as $eleman) {
            blokTipSayaci($eleman, $sayac);
        }
    }
    return $sayac;
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
