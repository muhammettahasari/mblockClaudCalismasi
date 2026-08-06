#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
mBlock doğrulama takımı (PHP yorumlayıcısı olmadan).

1) PHP yapı kontrolü  : yorum/dize dışı { } ( ) [ ] dengesi, PHP bloğu dengesi
2) Tanımsız fonksiyon : require zinciri geçişli çözülür
3) HTML etiket bütünlüğü : <body>/<html>/<head> etiketinin GERÇEK sonu
                           (içindeki <?php ... ?> blokları atlanarak)
4) JS sözdizimi       : <script> blokları çıkarılıp `node --check`
"""
import os, re, subprocess, sys, tempfile

KOK = sys.argv[1] if len(sys.argv) > 1 else '.'

PHP_YERLESIK = set("""
array count strlen substr trim explode implode in_array array_map array_merge array_filter
json_encode json_decode isset empty unset die exit print_r var_dump sprintf printf number_format
str_replace preg_match preg_replace preg_split preg_match_all htmlspecialchars strip_tags
file_get_contents file_put_contents file_exists unlink mkdir rmdir scandir glob basename dirname
pathinfo realpath is_dir is_file copy rename filesize date time mktime strtotime intdiv
mb_substr mb_strlen mb_strtolower mb_strtoupper strtolower strtoupper ucfirst ucwords
rand mt_rand random_int min max abs round floor ceil sqrt pow intval floatval strval boolval
array_keys array_values array_slice array_splice array_search array_column array_sum array_fill
array_key_exists array_unique array_reverse array_shift array_unshift array_pop array_push
sort rsort usort uasort uksort ksort krsort asort arsort shuffle range compact extract
header session_start session_destroy setcookie password_hash password_verify md5 sha1 hash
error_log error_reporting ini_set set_error_handler headers_sent http_response_code
function_exists class_exists method_exists property_exists defined define constant
is_array is_string is_int is_numeric is_null is_bool is_object is_callable gettype settype
str_repeat str_pad str_contains str_starts_with str_ends_with strpos stripos strrpos
nl2br sprintf vsprintf lcfirst wordwrap chunk_split strcmp strcasecmp strnatcmp similar_text
iterator_to_array call_user_func call_user_func_array func_get_args serialize unserialize
uniqid bin2hex random_bytes base64_encode base64_decode urlencode urldecode rawurlencode
filter_var checkdate microtime usleep sleep flush ob_start ob_get_clean ob_end_clean
require require_once include include_once echo print list isset
array_diff array_intersect array_flip array_combine array_chunk array_pad array_walk
""".split())

def php_dilimleri(metin):
    """PHP kod parçalarını (yorum ve dizeler ayıklanmış) döndürür."""
    parcalar = []
    for m in re.finditer(r'<\?php(.*?)(?:\?>|\Z)|<\?=(.*?)(?:\?>|\Z)', metin, re.S):
        parcalar.append(m.group(1) or m.group(2) or '')
    return parcalar

def temizle_php(kod):
    """Dizeleri ve yorumları boşlukla değiştirir (satır sayısı korunur)."""
    sonuc, i, n = [], 0, len(kod)
    while i < n:
        c = kod[i]
        if c == '/' and i + 1 < n and kod[i+1] == '/':
            j = kod.find('\n', i); j = n if j < 0 else j
            sonuc.append(' ' * (j - i)); i = j
        elif c == '#' and not (i + 1 < n and kod[i+1] == '['):
            j = kod.find('\n', i); j = n if j < 0 else j
            sonuc.append(' ' * (j - i)); i = j
        elif c == '/' and i + 1 < n and kod[i+1] == '*':
            j = kod.find('*/', i + 2); j = n if j < 0 else j + 2
            sonuc.append(''.join(ch if ch == '\n' else ' ' for ch in kod[i:j])); i = j
        elif c in '"\'':
            j = i + 1
            while j < n:
                if kod[j] == '\\': j += 2; continue
                if kod[j] == c: j += 1; break
                j += 1
            sonuc.append(''.join(ch if ch == '\n' else ' ' for ch in kod[i:j])); i = j
        elif c == '<' and kod.startswith('<<<', i):
            m = re.match(r"<<<\s*(['\"]?)(\w+)\1\r?\n", kod[i:])
            if m:
                etiket = m.group(2)
                son = re.search(r'^\s*' + etiket + r'\b', kod[i+m.end():], re.M)
                j = i + m.end() + (son.end() if son else 0)
                sonuc.append(''.join(ch if ch == '\n' else ' ' for ch in kod[i:j])); i = j
            else:
                sonuc.append(c); i += 1
        else:
            sonuc.append(c); i += 1
    return ''.join(sonuc)

def denge_kontrol(yol):
    metin = open(yol, encoding='utf-8', errors='replace').read()
    hatalar = []
    if metin.startswith('﻿'):
        hatalar.append('BOM var (çıktıdan önce byte gönderir)')
    # <?php ... ?> dengesi
    ac = len(re.findall(r'<\?php|<\?=', metin))
    kapa = len(re.findall(r'\?>', metin))
    if kapa > ac:
        hatalar.append(f'fazladan ?> ({ac} açılış, {kapa} kapanış)')
    kod = ''.join(temizle_php(p) for p in php_dilimleri(metin))
    for ac_k, kapa_k, ad in [('{', '}', 'süslü'), ('(', ')', 'parantez'), ('[', ']', 'köşeli')]:
        f = kod.count(ac_k) - kod.count(kapa_k)
        if f: hatalar.append(f'{ad} parantez dengesizliği: {f:+d}')
    return hatalar, metin, kod

# --------------------------------------------------------------------------
def dosyalari_bul(uzanti):
    for kk, klasorler, dosyalar in os.walk(KOK):
        klasorler[:] = [d for d in klasorler if d not in ('.git', 'node_modules', 'img', 'vendor')]
        for d in dosyalar:
            if d.endswith(uzanti):
                yield os.path.join(kk, d)

php_dosyalari = sorted(dosyalari_bul('.php'))
js_dosyalari  = sorted(dosyalari_bul('.js'))

print('=' * 68)
print('1) PHP YAPI KONTROLÜ')
print('=' * 68)
hata_sayisi = 0
kodlar, metinler = {}, {}
for y in php_dosyalari:
    h, metin, kod = denge_kontrol(y)
    kodlar[y], metinler[y] = kod, metin
    if h:
        hata_sayisi += len(h)
        print(f'  ❌ {os.path.relpath(y, KOK)}')
        for x in h: print(f'       {x}')
print(f'  {len(php_dosyalari)} dosya, {hata_sayisi} sorun')

# --------------------------------------------------------------------------
print()
print('=' * 68)
print('2) TANIMSIZ FONKSİYON (require zinciri geçişli)')
print('=' * 68)

tanimlar = {}   # fonksiyon adı -> tanımlandığı dosyalar
for y, kod in kodlar.items():
    for m in re.finditer(r'\bfunction\s+&?([A-Za-z_]\w*)\s*\(', kod):
        tanimlar.setdefault(m.group(1).lower(), set()).add(y)

def gereksinimler(y, gorulen=None):
    if gorulen is None: gorulen = set()
    if y in gorulen: return gorulen
    gorulen.add(y)
    for m in re.finditer(r'(?:require|include)(?:_once)?\s*\(?\s*__DIR__\s*\.\s*[\'"]([^\'"]+)', metinler.get(y, '')):
        hedef = os.path.normpath(os.path.join(os.path.dirname(y), m.group(1).lstrip('/')))
        if hedef in metinler: gereksinimler(hedef, gorulen)
    for m in re.finditer(r'(?:require|include)(?:_once)?\s*\(?\s*[\'"](\.\.?/[^\'"]+)[\'"]', metinler.get(y, '')):
        hedef = os.path.normpath(os.path.join(os.path.dirname(y), m.group(1)))
        if hedef in metinler: gereksinimler(hedef, gorulen)
    return gorulen

ANAHTAR = set("""if elseif else while for foreach switch case return function fn match array list
isset unset empty echo print new clone catch try throw and or xor as use namespace class
interface trait extends implements static public private protected const global instanceof
declare endif endwhile endforeach endfor endswitch yield require include require_once include_once""".split())

tanimsiz = 0
for y, kod in kodlar.items():
    erisim = set()
    for d in gereksinimler(y):
        for ad, yerler in tanimlar.items():
            if d in yerler: erisim.add(ad)
    for m in re.finditer(r'(?<![\w$>:\\])([A-Za-z_]\w*)\s*\(', kod):
        ad = m.group(1)
        alt = ad.lower()
        if alt in ANAHTAR or ad in PHP_YERLESIK or alt in {x.lower() for x in PHP_YERLESIK}:
            continue
        if alt not in tanimlar:      # projede hiç yok -> muhtemelen yerleşik
            continue
        if alt not in erisim:
            satir = kod[:m.start()].count('\n') + 1
            print(f'  ❌ {os.path.relpath(y, KOK)}:{satir}  {ad}() erişilebilir değil '
                  f'(tanım: {", ".join(os.path.relpath(z, KOK) for z in tanimlar[alt])})')
            tanimsiz += 1
print(f'  {len(php_dosyalari)} dosya, {tanimsiz} erişilemeyen çağrı')

# --------------------------------------------------------------------------
print()
print('=' * 68)
print('3) HTML ETİKET BÜTÜNLÜĞÜ (<body>/<html>/<head>)')
print('=' * 68)

def etiket_sonu(metin, bas):
    """<body ... > etiketinin gerçek sonu — içindeki <?php ?> blokları atlanır."""
    i, n = bas, len(metin)
    while i < n:
        if metin.startswith('<?', i):
            j = metin.find('?>', i)
            i = n if j < 0 else j + 2
            continue
        if metin[i] == '>':
            return i
        i += 1
    return -1

etiket_sayisi = bozuk = 0
for y, metin in metinler.items():
    for m in re.finditer(r'<(body|html|head)\b', metin, re.I):
        etiket_sayisi += 1
        son = etiket_sonu(metin, m.end())
        if son < 0:
            print(f'  ❌ {os.path.relpath(y, KOK)}: <{m.group(1)}> kapanmıyor'); bozuk += 1; continue
        ic = metin[m.end():son]
        # etiketin içinde gövde içeriği (başka bir etiket ya da <?php ... ?> DIŞI '<') var mı?
        ic_temiz = re.sub(r'<\?.*?\?>', '', ic, flags=re.S)
        if '<' in ic_temiz:
            satir = metin[:m.start()].count('\n') + 1
            print(f'  ❌ {os.path.relpath(y, KOK)}:{satir}: <{m.group(1)}> etiketinin İÇİNE içerik girmiş')
            bozuk += 1
print(f'  {etiket_sayisi} etiket, {bozuk} bozuk')

# --------------------------------------------------------------------------
print()
print('=' * 68)
print('4) JAVASCRIPT SÖZDİZİMİ')
print('=' * 68)

def node_kontrol(kaynak, etiket):
    with tempfile.NamedTemporaryFile('w', suffix='.js', delete=False, encoding='utf-8') as f:
        f.write(kaynak); yol = f.name
    p = subprocess.run(['node', '--check', yol], capture_output=True, text=True)
    os.unlink(yol)
    if p.returncode:
        print(f'  ❌ {etiket}')
        print('     ' + [l for l in p.stderr.strip().split(chr(10)) if 'SyntaxError' in l or 'Error:' in l][:1][0] if p.stderr else '')
        return 1
    return 0

js_hata = js_adet = 0
for y in js_dosyalari:
    js_adet += 1
    js_hata += node_kontrol(open(y, encoding='utf-8', errors='replace').read(), os.path.relpath(y, KOK))

# Gömülü <script> blokları.
# ÖNCE PHP blokları çıkarılır, SONRA <script> aranır. Ters sırada yapılırsa
# PHP yorumunun içindeki "<script" kelimesi de betik sanılıyor ve
# guvenlik.php gibi tek satır HTML içermeyen dosyalar bile hata veriyordu.
def php_ciplakla(metin):
    metin = re.sub(r'<\?=(.*?)\?>', 'null', metin, flags=re.S)   # çıktı -> JS değişmezi
    metin = re.sub(r'<\?php(.*?)\?>', '', metin, flags=re.S)     # mantık -> yok
    return re.sub(r'<\?php.*\Z', '', metin, flags=re.S)          # kapanışsız son blok

for y, metin in metinler.items():
    html = php_ciplakla(metin)
    for k, m in enumerate(re.finditer(r'<script\b[^>]*>(.*?)</script>', html, re.S | re.I)):
        govde = m.group(1)
        if not govde.strip():
            continue
        js_adet += 1
        js_hata += node_kontrol(govde, f'{os.path.relpath(y, KOK)} <script #{k+1}>')

print(f'  {js_adet} betik, {js_hata} hata')

print()
print('=' * 68)
toplam = hata_sayisi + tanimsiz + bozuk + js_hata
print(f'TOPLAM SORUN: {toplam}')
print('=' * 68)
sys.exit(1 if toplam else 0)
