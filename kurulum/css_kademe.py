#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSS KADEME (cascade) KONTROLÜ — öğrenci oynatıcısı yerleşimi

NEDEN VAR?
`ogrenci_oynatici.css` gövdeye `height:100vh; overflow:hidden` veriyor.
Yerleşim düzeltmesi `ilerleme_cubugu.css` içinde bunları EZMEK zorunda.
İki kez ıskaladım:
  1. `min-height:100vh` yazdım — `height`'i ezmez.
  2. `overflow-x:hidden` yazdım — `overflow:hidden` KISALTMA özelliği,
     hem x hem y demek; sadece x'i ezince y hidden kaldı ve sayfa
     kaydırılamadı.
Bu betik kısaltmaları açıp gerçek kazananı hesaplar.

Sadece göz kararı okumak yerine ölçüyoruz: aynı seçici (tek sınıf / element)
söz konusu olduğunda SONRA yüklenen dosya kazanır, `!important` her şeyi ezer.
"""
import re, sys, os

KOK = sys.argv[1] if len(sys.argv) > 1 else '.'

# oynatici.php'deki <link> sırası
DOSYALAR = [
    'v2_gorev_sistemi/assets/css/tasarlayici_style.css',
    'v2_gorev_sistemi/assets/css/ogrenci_oynatici.css',
    'v2_gorev_sistemi/assets/css/ilerleme_cubugu.css',
]

# Kısaltma -> ürettiği alt özellikler (bu proje için gereken kadarı)
KISALTMALAR = {
    'overflow': ['overflow-x', 'overflow-y'],
    'margin':   ['margin-top', 'margin-right', 'margin-bottom', 'margin-left'],
    'padding':  ['padding-top', 'padding-right', 'padding-bottom', 'padding-left'],
    'flex':     ['flex-grow', 'flex-shrink', 'flex-basis'],
}

def yorumsuz(m):
    return re.sub(r'/\*.*?\*/', '', m, flags=re.S)

def kurallar(metin):
    """(seçiciler, bildirimler) çiftleri — @media içindekiler atlanır."""
    metin = yorumsuz(metin)
    # @media bloklarını çıkar (ayrı koşul; ana kademeye karışmasın)
    metin = re.sub(r'@media[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}', '', metin, flags=re.S)
    for m in re.finditer(r'([^{}]+)\{([^{}]*)\}', metin):
        sec = m.group(1).strip()
        if sec.startswith('@'):
            continue
        yield sec, m.group(2)

def bildirimler(govde):
    for parca in govde.split(';'):
        if ':' not in parca:
            continue
        ad, _, deger = parca.partition(':')
        ad = ad.strip().lower()
        deger = deger.strip()
        if not ad or not deger:
            continue
        onemli = '!important' in deger
        deger = deger.replace('!important', '').strip()
        yield ad, deger, onemli

def kademe(hedef_secici):
    """hedef için etkin (özellik -> (değer, kaynak)) tablosu."""
    etkin = {}          # ozellik -> (deger, onemli, dosya, sira)
    sira = 0
    for yol in DOSYALAR:
        tam = os.path.join(KOK, yol)
        if not os.path.exists(tam):
            continue
        metin = open(tam, encoding='utf-8', errors='replace').read()
        for sec, govde in kurallar(metin):
            secililer = [s.strip() for s in sec.split(',')]
            if hedef_secici not in secililer:
                continue
            for ad, deger, onemli in bildirimler(govde):
                sira += 1
                hedefler = [(a, deger) for a in KISALTMALAR.get(ad, [ad])]
                for alt, d in hedefler:
                    onceki = etkin.get(alt)
                    if onceki and onceki[1] and not onemli:
                        continue     # önceki !important, bu değil -> kazanamaz
                    etkin[alt] = (d, onemli, os.path.basename(yol), sira)
    return etkin

def rapor(secici, beklenenler):
    print(f'--- {secici} ---')
    e = kademe(secici)
    hata = 0
    for ozellik, kural in beklenenler.items():
        varolan = e.get(ozellik)
        deger = varolan[0] if varolan else '(tanımsız)'
        kaynak = f'{varolan[2]}' if varolan else '-'
        onemli = ' !important' if varolan and varolan[1] else ''
        tamam = kural(deger)
        if not tamam:
            hata += 1
        print(f'  {"✅" if tamam else "❌"} {ozellik:14s} = {deger}{onemli}   [{kaynak}]')
    return hata

print('=' * 66)
print('ÖĞRENCİ OYNATICISI — YERLEŞİM KADEMESİ')
print('=' * 66)

toplam = 0

toplam += rapor('body', {
    # Sabit yükseklik OLMAMALI, yoksa sayfa ekrana hapsolur
    'height':     lambda v: v in ('auto',),
    'min-height': lambda v: '100vh' in v,
    # İkisi de hidden OLMAMALI, yoksa aşağı kaydırılamaz
    'overflow-y': lambda v: v in ('visible', 'auto', 'scroll'),
    'overflow-x': lambda v: v in ('visible', 'hidden', 'auto'),
    'flex-direction': lambda v: v == 'column',
})

print()
toplam += rapor('.sol-panel', {
    # Sol panel kendi içinde kaymamalı; sayfa kaysın
    'overflow-y': lambda v: v == 'visible',
    'height':     lambda v: v == 'auto',
})

print()
toplam += rapor('.sag-panel', {
    # Sol panelle aynı boya gelsin diye height:100% auto'ya çekilmeli
    'height': lambda v: v == 'auto',
})

print()
toplam += rapor('.calisma-alani', {
    # overflow:hidden burada da olmamalı
    'overflow-y': lambda v: v in ('visible', '(tanımsız)'),
    'align-items': lambda v: v == 'stretch',
})

print()
print('=' * 66)
print(f'KADEME SORUNU: {toplam}')
print('=' * 66)
sys.exit(1 if toplam else 0)
