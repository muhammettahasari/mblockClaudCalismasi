# -*- coding: utf-8 -*-
"""
Blok DSL'i: görev çözümlerini kısa Python ifadeleriyle yazar,
Blockly XML'i (cevap anahtarı) ve çocuk için okunur "Kod Planı" üretir.
Gölge (shadow) tipleri mblock_bloklari.js SOZLUK ile BİREBİR aynı olmalı.
"""
from html import escape


class B:
    """Bir blok düğümü"""
    def __init__(self, t, f=None, v=None, s=None, cikti=False):
        self.t = t; self.f = f or {}; self.v = v or {}; self.s = s or {}; self.cikti = cikti

    def __repr__(self):
        return f'B({self.t})'


METIN_GIRDILERI = {('*', 'MESAJ'), ('*', 'SORU'), ('mblock_islem_birlestir', 'A'), ('mblock_islem_birlestir', 'B'),
                   ('mblock_islem_karsilastirma', 'A'), ('mblock_islem_karsilastirma', 'B')}


def _metin_mi(tip, girdi):
    return ('*', girdi) in METIN_GIRDILERI or (tip, girdi) in METIN_GIRDILERI


# ------------------------------------------------------------------ yardımcılar
def _say(x):
    if isinstance(x, float) and x.is_integer():
        x = int(x)
    return str(x)


# ---- şapkalar (gövde listesi alır)
def bayrak(*govde): return ('bayrak', None, list(govde))
def tus(t, *govde): return ('tus', t, list(govde))
def tikla(*govde): return ('tikla', None, list(govde))
def mesaj_al(m, *govde): return ('mesaj', m, list(govde))

# ---- hareket
def git(n): return B('mblock_hareket_git', v={'ADIM': n})
def sag(n): return B('mblock_hareket_don_sag', v={'DERECE': n})
def sol(n): return B('mblock_hareket_don_sol', v={'DERECE': n})
def hedefe_git(h): return B('mblock_hareket_rastgele_git', f={'HEDEF': h})
def xy(x, y): return B('mblock_hareket_xy_git', v={'X': x, 'Y': y})
def suzul(sn, x, y): return B('mblock_hareket_suzul_xy', v={'SURE': sn, 'X': x, 'Y': y})
def suzul_h(sn, h): return B('mblock_hareket_suzul_rastgele', v={'SURE': sn}, f={'HEDEF': h})
def yon(d): return B('mblock_hareket_yone_don', v={'YON': d})
def hedefe_don(h): return B('mblock_hareket_hedefe_don', f={'HEDEF': h})
def xdeg(n): return B('mblock_hareket_x_degistir', v={'X': n})
def xyap(n): return B('mblock_hareket_x_yap', v={'X': n})
def ydeg(n): return B('mblock_hareket_y_degistir', v={'Y': n})
def yyap(n): return B('mblock_hareket_y_yap', v={'Y': n})
def sek(): return B('mblock_hareket_kenardaysa_sek')
def stil(s): return B('mblock_hareket_donus_stili', f={'STIL': s})
def x_konumu(): return B('mblock_hareket_x_konumu', cikti=True)
def y_konumu(): return B('mblock_hareket_y_konumu', cikti=True)
def yonu(): return B('mblock_hareket_yonu', cikti=True)

# ---- görünüm
def de(m): return B('mblock_gorunum_merhaba_de', v={'MESAJ': m})
def de_s(m, sn): return B('mblock_gorunum_merhaba_de_sureli', v={'MESAJ': m, 'SURE': sn})
def dusun(m): return B('mblock_gorunum_dusun', v={'MESAJ': m})
def dusun_s(m, sn): return B('mblock_gorunum_dusun_sureli', v={'MESAJ': m, 'SURE': sn})
def kostum(ad): return B('mblock_gorunum_kiligina_gec', f={'KILIK': ad})
def sonraki_kostum(): return B('mblock_gorunum_sonraki_kilik')
def dekor(ad): return B('mblock_gorunum_dekora_gec', f={'DEKOR': ad})
def sonraki_dekor(): return B('mblock_gorunum_sonraki_dekor')
def boyut_deg(n): return B('mblock_gorunum_boyut_degistir', v={'BOYUT': n})
def boyut_yap(n): return B('mblock_gorunum_boyut_yap', v={'BOYUT': n})
def efekt_deg(e, n): return B('mblock_gorunum_renk_etkisi_degistir', f={'ETKI': e}, v={'DEGER': n})
def efekt_yap(e, n): return B('mblock_gorunum_renk_etkisi_yap', f={'ETKI': e}, v={'DEGER': n})
def efekt_temizle(): return B('mblock_gorunum_gorsel_temizle')
def goster(): return B('mblock_gorunum_goster')
def gizle(): return B('mblock_gorunum_gizle')
def katman(k): return B('mblock_gorunum_katmana_git', f={'KATMAN': k})
def katman_deg(yon_, n): return B('mblock_gorunum_katman_degistir', f={'YON': yon_}, v={'DEGER': n})
def kostum_no(tip='NUMBER'): return B('mblock_gorunum_kostum_no', f={'TIP': tip}, cikti=True)
def buyukluk(): return B('mblock_gorunum_buyukluk', cikti=True)

# ---- ses
def ses_bitene(s): return B('mblock_ses_cal_bitene_kadar', f={'SES': s})
def ses(s): return B('mblock_ses_cal', f={'SES': s})
def nota(n, vurus): return B('mblock_ses_nota_cal', f={'NOTA': str(n)}, v={'VURUS': vurus})
def ses_durdur(): return B('mblock_ses_durdur')

# ---- olaylar
def yayinla(m): return B('mblock_olay_mesaj_yayinla', f={'MESAJ': m})

# ---- kontrol
def bekle(sn): return B('mblock_kontrol_bekle', v={'SURE': sn})
def tekrarla(n, *govde): return B('mblock_kontrol_tekrarla', v={'KEZ': n}, s={'YAP': list(govde)})
def surekli(*govde): return B('mblock_kontrol_surekli', s={'YAP': list(govde)})
def eger(kosul, *govde): return B('mblock_kontrol_eger_ise', v={'SART': kosul}, s={'YAP': list(govde)})
def eger_degilse(kosul, evet, hayir): return B('mblock_kontrol_eger_degilse', v={'SART': kosul}, s={'YAP': list(evet), 'DEGILSEYAP': list(hayir)})
def olana_kadar(kosul, *govde): return B('mblock_kontrol_olana_kadar_tekrarla', v={'SART': kosul}, s={'YAP': list(govde)})
def olana_kadar_bekle(kosul): return B('mblock_kontrol_olana_kadar_bekle', v={'SART': kosul})
def durdur(t='TUMU'): return B('mblock_kontrol_durdur', f={'TUR': t})

# ---- algılama
def degiyor(h): return B('mblock_algilama_degiyor_mu', f={'HEDEF': h}, cikti=True)
def renk_degiyor(r): return B('mblock_algilama_renk_degiyor_mu', f={'RENK': r}, cikti=True)
def tus_basili(t): return B('mblock_algilama_tus_basili_mi', f={'TUS': t}, cikti=True)
def fare_basili(): return B('mblock_algilama_fare_basili_mi', cikti=True)
def fare_x(): return B('mblock_algilama_fare_x', cikti=True)
def fare_y(): return B('mblock_algilama_fare_y', cikti=True)
def sor(m): return B('mblock_algilama_sor_bekle', v={'SORU': m})
def cevap(): return B('mblock_algilama_cevap', cikti=True)
def zamanlayici(): return B('mblock_algilama_zamanlayici', cikti=True)
def zaman_sifirla(): return B('mblock_algilama_zamanlayici_sifirla')

# ---- işlemler
def islem(a, op, b):
    kod = {'+': 'ADD', '-': 'MINUS', '*': 'MULTIPLY', '/': 'DIVIDE'}[op]
    return B('mblock_islem_aritmetik', f={'OP': kod}, v={'A': a, 'B': b}, cikti=True)
def karsilastir(a, op, b):
    kod = {'=': 'EQ', '<': 'LT', '>': 'GT', '<=': 'LTE', '>=': 'GTE'}[op]
    return B('mblock_islem_karsilastirma', f={'OP': kod}, v={'A': a, 'B': b}, cikti=True)
def rastgele(a, b): return B('mblock_islem_rastgele', v={'MIN': a, 'MAX': b}, cikti=True)
def ve(a, b): return B('mblock_islem_ve_veya', f={'OP': 'AND'}, v={'A': a, 'B': b}, cikti=True)
def veya(a, b): return B('mblock_islem_ve_veya', f={'OP': 'OR'}, v={'A': a, 'B': b}, cikti=True)
def degil(a): return B('mblock_islem_degil', v={'A': a}, cikti=True)
def birlestir(a, b): return B('mblock_islem_birlestir', v={'A': a, 'B': b}, cikti=True)
def mod(a, b): return B('mblock_islem_mod', v={'A': a, 'B': b}, cikti=True)
def yuvarla(a): return B('mblock_islem_yuvarla', v={'NUM': a}, cikti=True)

# ---- kalem
def kalem_temizle(): return B('mblock_kalem_temizle')
def damga(): return B('mblock_kalem_damga')
def bastir(): return B('mblock_kalem_bastir')
def kaldir(): return B('mblock_kalem_kaldir')
def kalem_renk(r): return B('mblock_kalem_renk_yap', f={'RENK': r})
def kalinlik(n): return B('mblock_kalem_kalinlik_yap', v={'KALINLIK': n})
def kalinlik_deg(n): return B('mblock_kalem_kalinlik_degistir', v={'KALINLIK': n})

# ---- değişkenler
def d_yap(ad, deger): return B('variables_set', f={'VAR': ad}, v={'VALUE': deger})
def d_deg(ad, n): return B('math_change', f={'VAR': ad}, v={'DELTA': n})
def d(ad): return B('variables_get', f={'VAR': ad}, cikti=True)


SAPKA_TIP = {'bayrak': 'mblock_olay_bayrak_tiklandiginda', 'tus': 'mblock_olay_tus_basildiginda',
             'tikla': 'mblock_olay_kukla_tiklandiginda', 'mesaj': 'mblock_olay_mesaj_alindiginda'}


# ------------------------------------------------------------------ XML
def _deger_xml(sahip, girdi, deger, dvar):
    if isinstance(deger, B):
        return f'<value name="{girdi}">{_blok_xml(deger, dvar)}</value>'
    if _metin_mi(sahip, girdi):
        return f'<value name="{girdi}"><shadow type="text"><field name="TEXT">{escape(_say(deger), quote=False)}</field></shadow></value>'
    return f'<value name="{girdi}"><shadow type="math_number"><field name="NUM">{escape(_say(deger), quote=False)}</field></shadow></value>'


def _blok_xml(b, dvar, sonrakiler=None):
    x = f'<block type="{b.t}">'
    for k, v in b.f.items():
        if k == 'VAR':
            dvar.add(v)
            x += f'<field name="VAR" id="v_{_vid(v)}">{escape(v, quote=False)}</field>'
        else:
            x += f'<field name="{k}">{escape(str(v), quote=False)}</field>'
    for k, v in b.v.items():
        x += _deger_xml(b.t, k, v, dvar)
    for k, liste in b.s.items():
        if liste:
            x += f'<statement name="{k}">{_dizi_xml(liste, dvar)}</statement>'
    if sonrakiler:
        x += f'<next>{_dizi_xml(sonrakiler, dvar)}</next>'
    return x + '</block>'


def _dizi_xml(liste, dvar):
    return _blok_xml(liste[0], dvar, liste[1:]) if liste else ''


def _vid(ad):
    import hashlib
    return hashlib.md5(ad.encode()).hexdigest()[:10]


def betik_xml(betik, dvar, x=30, y=30):
    tur, param, govde = betik
    tip = SAPKA_TIP[tur]
    alan = ''
    if tur == 'tus': alan = f'<field name="TUS">{param}</field>'
    if tur == 'mesaj': alan = f'<field name="MESAJ">{escape(param, quote=False)}</field>'
    ic = f'<next>{_dizi_xml(govde, dvar)}</next>' if govde else ''
    return f'<block type="{tip}" x="{x}" y="{y}">{alan}{ic}</block>'


def cozum_xml(betikler):
    """betikler: [(kukla_adi, betik), ...] -> tek XML (hakem tüm kuklaları birleştirir)"""
    dvar = set()
    govde = ''
    for i, (_, b) in enumerate(betikler):
        govde += betik_xml(b, dvar, 30, 30 + i * 180)
    dx = ''
    if dvar:
        dx = '<variables>' + ''.join(f'<variable id="v_{_vid(v)}">{escape(v, quote=False)}</variable>' for v in sorted(dvar)) + '</variables>'
    return f'<xml xmlns="https://developers.google.com/blockly/xml">{dx}{govde}</xml>'


def kukla_xml(betikler_kukla):
    dvar = set(); govde = ''
    for i, b in enumerate(betikler_kukla):
        govde += betik_xml(b, dvar, 30, 30 + i * 180)
    dx = ''
    if dvar:
        dx = '<variables>' + ''.join(f'<variable id="v_{_vid(v)}">{escape(v, quote=False)}</variable>' for v in sorted(dvar)) + '</variables>'
    return f'<xml xmlns="https://developers.google.com/blockly/xml">{dx}{govde}</xml>'


# ------------------------------------------------------------------ blok tipleri (izin listesi)
def tipler(betikler):
    st = set()
    def gez(b):
        st.add(b.t)
        for v in b.v.values():
            if isinstance(v, B): gez(v)
        for l in b.s.values():
            for x in l: gez(x)
    for _, (tur, _, govde) in betikler:
        st.add(SAPKA_TIP[tur])
        for b in govde: gez(b)
    return st


# ------------------------------------------------------------------ okunur Kod Planı
KATEGORI_RENK = {
    'olay': '#FFBF00', 'hareket': '#4C97FF', 'gorunum': '#9966FF', 'ses': '#CF63CF', 'kontrol': '#FFAB19',
    'algilama': '#5CB1D6', 'islem': '#59C059', 'kalem': '#0FBD8C', 'degisken': '#FF8C1A'
}


def _kat(t):
    if t.startswith('mblock_olay'): return 'olay'
    if t.startswith('mblock_hareket'): return 'hareket'
    if t.startswith('mblock_gorunum'): return 'gorunum'
    if t.startswith('mblock_ses'): return 'ses'
    if t.startswith('mblock_kontrol'): return 'kontrol'
    if t.startswith('mblock_algilama'): return 'algilama'
    if t.startswith('mblock_islem'): return 'islem'
    if t.startswith('mblock_kalem'): return 'kalem'
    return 'degisken'


TUS_ADLARI = {'SPACE': 'boşluk', 'ARROWUP': 'yukarı ok', 'ARROWDOWN': 'aşağı ok', 'ARROWRIGHT': 'sağ ok', 'ARROWLEFT': 'sol ok', 'ANY': 'herhangi bir',
              'RIGHT': 'sağ ok', 'LEFT': 'sol ok', 'UP': 'yukarı ok', 'DOWN': 'aşağı ok'}
HEDEF_ADLARI = {'RANDOM': 'rastgele konum', 'MOUSE': 'fare oku', 'EDGE': 'kenar'}
RENK_ADLARI = {'RED': '🔴 Kırmızı', 'BLUE': '🔵 Mavi', 'GREEN': '🟢 Yeşil', 'YELLOW': '🟡 Sarı', 'ORANGE': '🟠 Turuncu',
               'PURPLE': '🟣 Mor', 'PINK': '🩷 Pembe', 'BLACK': '⚫ Siyah', 'WHITE': '⚪ Beyaz'}
SES_ADLARI = {'MEOW': 'Miyav', 'LASER': 'Lazer', 'JUMP': 'Zıplama', 'BEEP': 'Bip', 'CLAP': 'Alkış', 'DRUM': 'Davul',
              'MAGIC': 'Sihir', 'POP': 'Patlama', 'WIN': 'Zafer'}
NOTA_ADLARI = {'60': 'Do', '62': 'Re', '64': 'Mi', '65': 'Fa', '67': 'Sol', '69': 'La', '71': 'Si', '72': 'İnce Do'}


def _v(x):
    """değer -> okunur metin (HTML)"""
    if isinstance(x, B):
        return '(' + _metin_ic(x) + ')'
    if isinstance(x, str):
        return '<b>«' + escape(x) + '»</b>'
    return '<b>' + _say(x) + '</b>'


def _metin_ic(b):
    t, f, v = b.t, b.f, b.v
    H = lambda h: escape(HEDEF_ADLARI.get(h, h))
    M = {
        'mblock_hareket_git': lambda: f'{_v(v["ADIM"])} adım git',
        'mblock_hareket_don_sag': lambda: f'sağa ↻ {_v(v["DERECE"])} derece dön',
        'mblock_hareket_don_sol': lambda: f'sola ↺ {_v(v["DERECE"])} derece dön',
        'mblock_hareket_rastgele_git': lambda: f'<b>{H(f["HEDEF"])}</b>\'a git',
        'mblock_hareket_xy_git': lambda: f'x: {_v(v["X"])} y: {_v(v["Y"])} konumuna git',
        'mblock_hareket_suzul_xy': lambda: f'{_v(v["SURE"])} sn. de x: {_v(v["X"])} y: {_v(v["Y"])}\'a süzül',
        'mblock_hareket_suzul_rastgele': lambda: f'{_v(v["SURE"])} sn. de <b>{H(f["HEDEF"])}</b>\'a süzül',
        'mblock_hareket_yone_don': lambda: f'{_v(v["YON"])} yönüne dön',
        'mblock_hareket_hedefe_don': lambda: f'<b>{H(f["HEDEF"])}</b>\'na doğru dön',
        'mblock_hareket_x_degistir': lambda: f'x\'i {_v(v["X"])} değiştir',
        'mblock_hareket_x_yap': lambda: f'x {_v(v["X"])} olsun',
        'mblock_hareket_y_degistir': lambda: f'y\'yi {_v(v["Y"])} değiştir',
        'mblock_hareket_y_yap': lambda: f'y {_v(v["Y"])} olsun',
        'mblock_hareket_kenardaysa_sek': lambda: 'kenara geldiysen sek',
        'mblock_hareket_donus_stili': lambda: 'dönüş stilini <b>' + {'SOLSAG': 'sol-sağ', 'HER': 'her yöne', 'YOK': 'döndürme'}[f['STIL']] + '</b> yap',
        'mblock_hareket_x_konumu': lambda: 'x konumu',
        'mblock_hareket_y_konumu': lambda: 'y konumu',
        'mblock_hareket_yonu': lambda: 'yönü',
        'mblock_gorunum_merhaba_de': lambda: f'{_v(v["MESAJ"])} de',
        'mblock_gorunum_merhaba_de_sureli': lambda: f'{_v(v["MESAJ"])} de {_v(v["SURE"])} saniye',
        'mblock_gorunum_dusun': lambda: f'{_v(v["MESAJ"])} diye düşün',
        'mblock_gorunum_dusun_sureli': lambda: f'{_v(v["MESAJ"])} diye düşün {_v(v["SURE"])} saniye',
        'mblock_gorunum_kiligina_gec': lambda: f'<b>{escape(f["KILIK"])}</b> kostümüne geç',
        'mblock_gorunum_sonraki_kilik': lambda: 'sonraki kostüm',
        'mblock_gorunum_dekora_gec': lambda: f'<b>{escape(f["DEKOR"])}</b> dekoruna geç',
        'mblock_gorunum_sonraki_dekor': lambda: 'sonraki dekor',
        'mblock_gorunum_boyut_degistir': lambda: f'boyutu {_v(v["BOYUT"])} birim değiştir',
        'mblock_gorunum_boyut_yap': lambda: f'boyu % {_v(v["BOYUT"])} yap',
        'mblock_gorunum_renk_etkisi_degistir': lambda: f'<b>{"renk" if f["ETKI"]=="COLOR" else "hayalet"}</b> etkisini {_v(v["DEGER"])} arttır',
        'mblock_gorunum_renk_etkisi_yap': lambda: f'<b>{"renk" if f["ETKI"]=="COLOR" else "hayalet"}</b> etkisi {_v(v["DEGER"])} olsun',
        'mblock_gorunum_gorsel_temizle': lambda: 'görsel etkileri temizle',
        'mblock_gorunum_goster': lambda: 'görün',
        'mblock_gorunum_gizle': lambda: 'gizlen',
        'mblock_gorunum_katmana_git': lambda: f'<b>{"en ön" if f["KATMAN"]=="FRONT" else "en arka"}</b> katmana git',
        'mblock_gorunum_katman_degistir': lambda: f'<b>{"ileri" if f["YON"]=="FORWARD" else "geri"}</b> {_v(v["DEGER"])} katman git',
        'mblock_gorunum_kostum_no': lambda: 'kostüm ' + ('sayı' if f['TIP'] == 'NUMBER' else 'isim'),
        'mblock_gorunum_buyukluk': lambda: 'büyüklük',
        'mblock_ses_cal_bitene_kadar': lambda: f'<b>{SES_ADLARI[f["SES"]]}</b> sesini bitene kadar çal',
        'mblock_ses_cal': lambda: f'<b>{SES_ADLARI[f["SES"]]}</b> sesini çal',
        'mblock_ses_nota_cal': lambda: f'🎵 <b>{NOTA_ADLARI[f["NOTA"]]}</b> notasını {_v(v["VURUS"])} vuruş çal',
        'mblock_ses_durdur': lambda: 'tüm sesleri durdur',
        'mblock_olay_mesaj_yayinla': lambda: f'📢 <b>«{escape(f["MESAJ"])}»</b> mesajını yayınla',
        'mblock_kontrol_bekle': lambda: f'{_v(v["SURE"])} saniye bekle',
        'mblock_kontrol_tekrarla': lambda: f'{_v(v["KEZ"])} kez tekrarla',
        'mblock_kontrol_surekli': lambda: 'sürekli tekrarla',
        'mblock_kontrol_eger_ise': lambda: f'eğer {_v(v["SART"])} ise',
        'mblock_kontrol_eger_degilse': lambda: f'eğer {_v(v["SART"])} ise … değilse',
        'mblock_kontrol_olana_kadar_tekrarla': lambda: f'{_v(v["SART"])} olana kadar tekrarla',
        'mblock_kontrol_olana_kadar_bekle': lambda: f'{_v(v["SART"])} olana kadar bekle',
        'mblock_kontrol_durdur': lambda: '⛔ durdur: <b>' + ('tümü' if f['TUR'] == 'TUMU' else 'bu betik') + '</b>',
        'mblock_algilama_degiyor_mu': lambda: f'<b>{H(f["HEDEF"])}</b>\'a değiyor mu?',
        'mblock_algilama_renk_degiyor_mu': lambda: f'<b>{RENK_ADLARI[f["RENK"]]}</b> rengine değiyor mu?',
        'mblock_algilama_tus_basili_mi': lambda: f'<b>{TUS_ADLARI[f["TUS"]]}</b> tuşu basılı mı?',
        'mblock_algilama_fare_basili_mi': lambda: 'fareye basılı mı?',
        'mblock_algilama_fare_x': lambda: 'farenin x\'i',
        'mblock_algilama_fare_y': lambda: 'farenin y\'si',
        'mblock_algilama_sor_bekle': lambda: f'sor: {_v(v["SORU"])} ve bekle',
        'mblock_algilama_cevap': lambda: 'cevap',
        'mblock_algilama_zamanlayici': lambda: '⏱️ zamanlayıcı',
        'mblock_algilama_zamanlayici_sifirla': lambda: 'zamanlayıcıyı sıfırla',
        'mblock_islem_aritmetik': lambda: f'{_v(v["A"])} <b>{ {"ADD":"+","MINUS":"-","MULTIPLY":"*","DIVIDE":"/"}[f["OP"]] }</b> {_v(v["B"])}',
        'mblock_islem_karsilastirma': lambda: f'{_v(v["A"])} <b>{ {"EQ":"=","LT":"<","GT":">","LTE":"≤","GTE":"≥"}[f["OP"]] }</b> {_v(v["B"])}',
        'mblock_islem_rastgele': lambda: f'{_v(v["MIN"])} ile {_v(v["MAX"])} arasında rastgele sayı',
        'mblock_islem_ve_veya': lambda: f'{_v(v["A"])} <b>{"ve" if f["OP"]=="AND" else "veya"}</b> {_v(v["B"])}',
        'mblock_islem_degil': lambda: f'değil {_v(v["A"])}',
        'mblock_islem_birlestir': lambda: f'birleştir: {_v(v["A"])} {_v(v["B"])}',
        'mblock_islem_mod': lambda: f'{_v(v["A"])} mod {_v(v["B"])}',
        'mblock_islem_yuvarla': lambda: f'yuvarla {_v(v["NUM"])}',
        'mblock_kalem_temizle': lambda: '✏️ tümünü temizle',
        'mblock_kalem_damga': lambda: '✏️ damga bas',
        'mblock_kalem_bastir': lambda: '✏️ kalemi bastır',
        'mblock_kalem_kaldir': lambda: '✏️ kalemi kaldır',
        'mblock_kalem_renk_yap': lambda: f'✏️ kalem rengini <b>{RENK_ADLARI[f["RENK"]]}</b> yap',
        'mblock_kalem_kalinlik_yap': lambda: f'✏️ kalem kalınlığını {_v(v["KALINLIK"])} yap',
        'mblock_kalem_kalinlik_degistir': lambda: f'✏️ kalem kalınlığını {_v(v["KALINLIK"])} değiştir',
        'variables_set': lambda: f'<b>{escape(f["VAR"])}</b> değerini {_v(v["VALUE"])} yap',
        'math_change': lambda: f'<b>{escape(f["VAR"])}</b> değerini {_v(v["DELTA"])} değiştir',
        'variables_get': lambda: f'{escape(f["VAR"])}',
    }
    return M[t]()


def _hap(metin, kat, derinlik=0):
    renk = KATEGORI_RENK[kat]
    girinti = '&nbsp;' * (derinlik * 6) + ('↳ ' if derinlik else '')
    return (f'{girinti}<span style="display:inline-block;background:{renk};color:#fff;border-radius:12px;'
            f'padding:2px 10px;font-weight:700;font-size:13.5px;box-shadow:0 2px 0 rgba(0,0,0,.18)">{metin}</span>')


def sapka_metni(tur, param):
    if tur == 'bayrak': return '🏳️ Yeşil Bayrak Tıklandığında'
    if tur == 'tus': return f'⌨️ <b>{TUS_ADLARI.get(param, param.lower())}</b> tuşuna basıldığında'
    if tur == 'tikla': return '👆 Bu kukla tıklandığında'
    return f'📩 <b>«{escape(param)}»</b> mesajını aldığımda'


def plan_satirlari(betik):
    tur, param, govde = betik
    satirlar = [_hap(sapka_metni(tur, param), 'olay')]
    def gez(liste, dz):
        for b in liste:
            if b.t == 'mblock_kontrol_eger_degilse':
                satirlar.append(_hap(f'eğer {_v(b.v["SART"])} ise', 'kontrol', dz))
                gez(b.s.get('YAP', []), dz + 1)
                satirlar.append(_hap('değilse', 'kontrol', dz))
                gez(b.s.get('DEGILSEYAP', []), dz + 1)
                continue
            satirlar.append(_hap(_metin_ic(b), _kat(b.t), dz))
            for k in ('YAP',):
                if k in b.s: gez(b.s[k], dz + 1)
    gez(govde, 1)
    return satirlar


def blok_hapi(b):
    """Kullanılacak blok listesi için tek hap"""
    return _hap(_metin_ic(b), _kat(b.t))


def tum_bloklar(betikler):
    """(kukla, betik) listesindeki tüm komut blokları (iç içe dahil) — tekrarsız, sırayla"""
    sonuc, gorulen = [], set()
    def ekle(h):
        if h not in gorulen:
            gorulen.add(h); sonuc.append(h)
    for _, (tur, param, govde) in betikler:
        ekle(_hap(sapka_metni(tur, param), 'olay'))
        def gez(liste):
            for b in liste:
                ekle(blok_hapi(b) if b.t != 'mblock_kontrol_eger_degilse' else _hap(f'eğer {_v(b.v["SART"])} ise … değilse', 'kontrol'))
                for l in b.s.values(): gez(l)
        gez(govde)
    return sonuc
