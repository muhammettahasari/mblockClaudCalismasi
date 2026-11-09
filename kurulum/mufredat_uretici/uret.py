# -*- coding: utf-8 -*-
"""
Müfredat üreticisi: 200 görev -> SVG dosyaları + icerik_json + SQL
Çıktı: cikti/proje/... (proje köküne kopyalanacak dosyalar), cikti/mufredat.sql, cikti/gorevler.json
"""
import json, os, random, shutil, time
import svg_uretici as S
import dsl
from bolumler_1 import BOLUMLER
import bolumler_2, bolumler_3  # noqa: F401 (BOLUMLER'e ekler)

KOK = os.path.dirname(os.path.abspath(__file__))
CIKTI = os.path.join(KOK, 'cikti')
VARLIK_YOL = 'v2_gorev_sistemi/assets/images/mufredat'      # site köküne göre
PROJE = os.path.join(CIKTI, 'proje')

PUAN = {**{i: 10 for i in range(1, 6)}, **{i: 15 for i in range(6, 11)}, **{i: 20 for i in range(11, 16)},
        **{i: 25 for i in range(16, 20)}, 20: 40}

HTACCESS = open(os.path.join(KOK, 'htaccess_yukleme.txt'), encoding='utf-8').read()


def editorjs(bloklar):
    return {'time': int(time.time() * 1000), 'blocks': bloklar, 'version': '2.31.6'}


def p(metin): return {'type': 'paragraph', 'data': {'text': metin}}
def h(metin, sv=3): return {'type': 'header', 'data': {'text': metin, 'level': sv}}


def satirlar_html(satirlar):
    return ''.join(f'<span style="display:block;margin:4px 0">{s}</span>' for s in satirlar)


def main():
    if os.path.exists(CIKTI):
        shutil.rmtree(CIKTI)
    os.makedirs(os.path.join(PROJE, VARLIK_YOL, 'k'))
    os.makedirs(os.path.join(PROJE, VARLIK_YOL, 'd'))
    with open(os.path.join(PROJE, VARLIK_YOL, '.htaccess'), 'w', encoding='utf-8') as f:
        f.write(HTACCESS)

    assert len(BOLUMLER) == 20, len(BOLUMLER)
    palet_sayac = {}
    yazilan_kukla = set()
    ogrenilen = []          # önceki bölümlerde tanıtılan bloklar
    gorevler = []
    no = 0
    for bi, b in enumerate(BOLUMLER, 1):
        assert len(b['gorevler']) == 10, (b['ad'], len(b['gorevler']))
        bolum_yeni = []
        for gi, g in enumerate(b['gorevler'], 1):
            no += 1
            kod = f'M{no:03d}'
            rnd = random.Random(no)

            # ---------------- kuklalar
            sahne_kuklalar = []
            for k in g['kuklalar']:
                if k['palet'] is None:
                    i = palet_sayac.get(k['tur'], 0)
                    palet_sayac[k['tur']] = i + 1
                    palet = (i * 7 + len(k['tur'])) % len(S.PALETLER)
                else:
                    palet = k['palet']
                kostumler = []
                for kn in (0, 1):
                    ad = f"{k['tur']}_{palet:02d}_{kn+1}.svg"
                    if ad not in yazilan_kukla:
                        with open(os.path.join(PROJE, VARLIK_YOL, 'k', ad), 'w', encoding='utf-8') as f:
                            f.write(S.kukla_svg(k['tur'], palet, kn))
                        yazilan_kukla.add(ad)
                    kostumler.append({'adi': f'kostüm{kn+1}', 'yol': f'{VARLIK_YOL}/k/{ad}'})
                sahne_kuklalar.append({'adi': k['ad'], 'x': k['x'], 'y': k['y'], 'boyut': k['boyut'], 'yon': k['yon'],
                                       'donus_stili': k['stil'], 'goster': k['goster'], 'kostumler': kostumler})

            # ---------------- dekor(lar)
            dekorlar = g['dekorlar'] or [('dekor1', g['dekor'][0], g['dekor'][1])]
            sahne_dekorlar = []
            for di, (dad, tema, var) in enumerate(dekorlar, 1):
                ad = f'g{no:03d}_{di}.svg'
                with open(os.path.join(PROJE, VARLIK_YOL, 'd', ad), 'w', encoding='utf-8') as f:
                    f.write(S.dekor_svg(tema, no * 31 + di, var))
                sahne_dekorlar.append({'adi': dad, 'yol': f'{VARLIK_YOL}/d/{ad}'})

            # ---------------- cevap anahtarı
            betikler = g['betikler']
            cozum = dsl.cozum_xml(betikler)
            tipler = dsl.tipler(betikler)
            for y in g['yeni']:
                if y not in bolum_yeni: bolum_yeni.append(y)

            # ---------------- araç kutusu: çözüm + bölümün yenileri + birkaç çeldirici
            izin = set(tipler) | set(bolum_yeni)
            celdirici = [t for t in ogrenilen if t not in izin and not t.startswith('variables') and t != 'math_change']
            rnd.shuffle(celdirici)
            izin |= set(celdirici[:3])
            degiskenli = bool(g['degiskenler']) or any(t.startswith('variables') or t == 'math_change' for t in tipler)
            izin = sorted(t for t in izin if not t.startswith('variables') and t != 'math_change')
            if degiskenli:
                izin.append('DEGISKENLER')

            # ---------------- görev metni (Editor.js)
            kukla_adlari = [k['ad'] for k in g['kuklalar']]
            kod_kuklalari = []
            for ad, _ in betikler:
                if ad not in kod_kuklalari: kod_kuklalari.append(ad)
            metin = [p(g['hikaye']), h('🎯 Görevin'), p(g['hedef'])]
            if g['plan']:
                metin.append(h('🧩 Kod Planı'))
                for ad in kod_kuklalari:
                    satir = []
                    for kad, bt in betikler:
                        if kad == ad:
                            satir += dsl.plan_satirlari(bt) + ['']
                    onek = f'<b>🎭 {ad} kuklasının kodu:</b><br>' if len(kod_kuklalari) > 1 else ''
                    metin.append(p(onek + satirlar_html([s for s in satir if s])))
            else:
                metin.append(h('🧰 Kullanacağın Bloklar'))
                metin.append(p(' '.join(dsl.tum_bloklar(betikler))))
                metin.append(p('<i>Blokların sırasını sen bul! Takılırsan <b>💡 Bilgi Al</b> düğmesi çözümü gösterir (ödül yarıya iner).</i>'))
            metin.append(p('💡 ' + g['ipucu']))
            if len(kod_kuklalari) > 1:
                metin.append(p('ℹ️ Her kuklanın kodu ayrıdır: sahnenin altındaki listeden kuklaya tıklayınca onun kod alanı açılır.'))
            if degiskenli:
                metin.append(p('📦 Değişkenleri <b>Değişkenler</b> kategorisinde bulursun' + (
                    ' (' + ', '.join(f'<b>{v}</b>' for v in g['degiskenler']) + ' hazır)' if g['degiskenler'] else '') + '.'))

            # ---------------- Bilgi Al
            ipucu_metin = [p('İşte çözümün planı. Blokları kendin dizip <b>yeşil bayrakla</b> dene!')]
            for ad in kod_kuklalari:
                satir = []
                for kad, bt in betikler:
                    if kad == ad: satir += dsl.plan_satirlari(bt) + ['']
                onek = f'<b>🎭 {ad}:</b><br>' if len(kod_kuklalari) > 1 else ''
                ipucu_metin.append(p(onek + satirlar_html([s for s in satir if s])))

            icerik = {
                'hikaye_metni': editorjs(metin),
                'blueprint': {'ana_cozum': cozum, 'alternatif_cozumler': []},
                'izin_verilen_bloklar': izin,
                'ipucu': {'metin': editorjs(ipucu_metin), 'bloklar': cozum},
                'sahne': {'dekorlar': sahne_dekorlar, 'kuklalar': sahne_kuklalar, 'degiskenler': g['degiskenler']},
                'mufredat': {'kod': kod, 'bolum': bi, 'sira': gi,
                             'kukla_kodlari': {ad: dsl.kukla_xml([bt for kad, bt in betikler if kad == ad]) for ad in kod_kuklalari}},
            }
            gorevler.append({'kod': kod, 'bolum': bi, 'sira': gi, 'baslik': g['baslik'], 'puan': PUAN[bi], 'icerik': icerik})
        ogrenilen += [t for t in bolum_yeni if t not in ogrenilen]

    # ---------------- SQL
    def q(s):
        return "'" + s.replace('\\', '\\\\').replace("'", "''") + "'"

    sql = ['-- mBlock Akademi müfredatı (200 görev) — tekrar çalıştırılabilir',
           '-- Üretildi: ' + time.strftime('%Y-%m-%d %H:%M'), 'SET NAMES utf8mb4;',
           'CREATE TABLE IF NOT EXISTS yedek_20261109_bolumler AS SELECT * FROM bolumler;',
           'CREATE TABLE IF NOT EXISTS yedek_20261109_v2_gorevler AS SELECT * FROM v2_gorevler;',
           '-- Eski deneme görevleri: silinmez, yayından kalkar ve Arşiv bölümüne taşınır',
           "INSERT INTO bolumler (id, bolum_adi, sira_no) VALUES (99, 'Arşiv: Eski Deneme Görevleri', 999) ON DUPLICATE KEY UPDATE bolum_adi=VALUES(bolum_adi), sira_no=VALUES(sira_no);",
           "UPDATE v2_gorevler SET yayinda = 0, bolum_id = 99 WHERE JSON_EXTRACT(icerik_json, '$.mufredat.kod') IS NULL;"]
    for bi, b in enumerate(BOLUMLER, 1):
        sql.append(f"INSERT INTO bolumler (id, bolum_adi, sira_no) VALUES ({bi}, {q(b['ad'])}, {bi}) "
                   f"ON DUPLICATE KEY UPDATE bolum_adi=VALUES(bolum_adi), sira_no=VALUES(sira_no);")
    for g in gorevler:
        js = json.dumps(g['icerik'], ensure_ascii=False, separators=(',', ':'))
        kod = g['kod']
        sql.append(
            f"UPDATE v2_gorevler SET bolum_id={g['bolum']}, gorev_adi={q(g['baslik'])}, sira_no={g['sira']}, puan={g['puan']}, yayinda=1, icerik_json={q(js)} "
            f"WHERE JSON_UNQUOTE(JSON_EXTRACT(icerik_json, '$.mufredat.kod')) = '{kod}';")
        sql.append(
            f"INSERT INTO v2_gorevler (bolum_id, gorev_adi, sira_no, puan, gorev_tipi, yayinda, icerik_json) "
            f"SELECT {g['bolum']}, {q(g['baslik'])}, {g['sira']}, {g['puan']}, 'standart', 1, {q(js)} FROM DUAL "
            f"WHERE NOT EXISTS (SELECT 1 FROM v2_gorevler WHERE JSON_UNQUOTE(JSON_EXTRACT(icerik_json, '$.mufredat.kod')) = '{kod}');")
    with open(os.path.join(CIKTI, 'mufredat.sql'), 'w', encoding='utf-8') as f:
        f.write('\n'.join(sql) + '\n')
    with open(os.path.join(CIKTI, 'gorevler.json'), 'w', encoding='utf-8') as f:
        json.dump(gorevler, f, ensure_ascii=False)
    print('görev:', len(gorevler), 'kukla dosyası:', len(yazilan_kukla), 'dekor:', len(os.listdir(os.path.join(PROJE, VARLIK_YOL, 'd'))))


if __name__ == '__main__':
    main()
