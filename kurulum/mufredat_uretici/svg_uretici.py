# -*- coding: utf-8 -*-
"""
mBlock Akademi — kukla ve dekor SVG üreticisi.
Tüm çizimler özgündür (bilinen bir karakter/marka içermez).
Kukla: 120x120 viewBox, merkezde. Dekor: 480x360.
"""
import random, math

OUT = '#1e1b4b'  # kontur rengi (koyu lacivert)

PALETLER = [
    # ana, koyu, açık, vurgu
    ('#ff5d8f', '#d63a6e', '#ffc2d6', '#ffd23f'),
    ('#4cc9f0', '#2b9ccc', '#bdeefe', '#ff9f1c'),
    ('#7ae582', '#3fb950', '#cdf7d0', '#ff6b6b'),
    ('#9b5de5', '#7037b8', '#dcc6fb', '#fee440'),
    ('#ff9f1c', '#e07a00', '#ffe0b3', '#2ec4b6'),
    ('#00bbf9', '#0088c2', '#b8ecff', '#f15bb5'),
    ('#f15bb5', '#c1388b', '#fcd0ea', '#00f5d4'),
    ('#00f5d4', '#00b8a0', '#c4fff4', '#9b5de5'),
    ('#fee440', '#e0c200', '#fff8c4', '#f15bb5'),
    ('#ff6b6b', '#d64545', '#ffd0d0', '#4cc9f0'),
    ('#2ec4b6', '#1a978b', '#c6f3ef', '#ff9f1c'),
    ('#a0c4ff', '#6c95e0', '#e3edff', '#ff5d8f'),
    ('#ffadad', '#e07f7f', '#ffe3e3', '#3a86ff'),
    ('#caffbf', '#8ed97f', '#f1ffed', '#ff006e'),
    ('#3a86ff', '#1f5fcc', '#c9dcff', '#ffbe0b'),
    ('#ff006e', '#c20054', '#ffc2dc', '#8338ec'),
    ('#8338ec', '#5e1fb3', '#dac4fb', '#3a86ff'),
    ('#fb5607', '#c84000', '#ffd2bd', '#06d6a0'),
    ('#06d6a0', '#05a37a', '#c2f7e8', '#ef476f'),
    ('#ffd166', '#e0ad3a', '#fff1cc', '#118ab2'),
]


def _svg(ic, w=120, h=120, defs=''):
    return (f'<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="{h}" viewBox="0 0 {w} {h}">'
            f'<defs>{defs}</defs>{ic}</svg>')


def _grad(gid, c1, c2, yatay=False):
    x2, y2 = ('1', '0') if yatay else ('0', '1')
    return (f'<linearGradient id="{gid}" x1="0" y1="0" x2="{x2}" y2="{y2}">'
            f'<stop offset="0" stop-color="{c1}"/><stop offset="1" stop-color="{c2}"/></linearGradient>')


def _goz(x, y, r, kirp=False, bak=(0, 0)):
    if kirp:
        return (f'<path d="M{x-r},{y} Q{x},{y+r*0.8} {x+r},{y}" fill="none" stroke="{OUT}" '
                f'stroke-width="3" stroke-linecap="round"/>')
    bx, by = bak
    return (f'<circle cx="{x}" cy="{y}" r="{r}" fill="#fff" stroke="{OUT}" stroke-width="2.5"/>'
            f'<circle cx="{x+bx}" cy="{y+by}" r="{r*0.55}" fill="{OUT}"/>'
            f'<circle cx="{x+bx-r*0.2}" cy="{y+by-r*0.25}" r="{r*0.2}" fill="#fff"/>')


def _golge(cx=60, cy=112, rx=30):
    return f'<ellipse cx="{cx}" cy="{cy}" rx="{rx}" ry="5" fill="#000" opacity=".15"/>'


def _agiz(x, y, w, acik=False):
    if acik:
        return (f'<path d="M{x-w},{y} Q{x},{y+w*1.3} {x+w},{y} Z" fill="#7a1f3d" stroke="{OUT}" stroke-width="2.5" stroke-linejoin="round"/>'
                f'<path d="M{x-w*0.5},{y+w*0.55} Q{x},{y+w*0.95} {x+w*0.5},{y+w*0.55}" fill="#ff7aa2"/>')
    return f'<path d="M{x-w},{y} Q{x},{y+w*0.9} {x+w},{y}" fill="none" stroke="{OUT}" stroke-width="3" stroke-linecap="round"/>'


# ---------------------------------------------------------------------------
# KARAKTERLER — her biri (palet, kostum_no 0/1, rnd) -> iç SVG, defs
# ---------------------------------------------------------------------------
def robot(p, k, r):
    a, d, l, v = p
    anten = 'M60,24 L60,10' if k == 0 else 'M60,24 L66,9'
    kol_y = 78 if k == 0 else 62
    defs = _grad('rg', a, d)
    ic = (_golge(rx=28) +
          f'<path d="{anten}" stroke="{OUT}" stroke-width="3"/><circle cx="{60 if k==0 else 66}" cy="{9}" r="5" fill="{v}" stroke="{OUT}" stroke-width="2.5"/>'
          f'<rect x="20" y="{kol_y-6}" width="12" height="22" rx="6" fill="{l}" stroke="{OUT}" stroke-width="2.5" transform="rotate({-15 if k else 0} 26 {kol_y})"/>'
          f'<rect x="88" y="{kol_y-6}" width="12" height="22" rx="6" fill="{l}" stroke="{OUT}" stroke-width="2.5" transform="rotate({15 if k else 0} 94 {kol_y})"/>'
          f'<rect x="30" y="62" width="60" height="44" rx="12" fill="url(#rg)" stroke="{OUT}" stroke-width="3"/>'
          f'<rect x="44" y="74" width="32" height="18" rx="5" fill="{l}" stroke="{OUT}" stroke-width="2"/>'
          f'<circle cx="52" cy="83" r="3.5" fill="{v}"/><circle cx="68" cy="83" r="3.5" fill="{a}"/>'
          f'<rect x="26" y="22" width="68" height="44" rx="16" fill="url(#rg)" stroke="{OUT}" stroke-width="3"/>'
          f'<rect x="33" y="30" width="54" height="28" rx="11" fill="#1e293b"/>'
          + (f'<rect x="40" y="38" width="12" height="12" rx="4" fill="#5eead4"/><rect x="68" y="38" width="12" height="12" rx="4" fill="#5eead4"/>'
             if k == 0 else
             f'<path d="M40,46 Q46,38 52,46" stroke="#5eead4" stroke-width="4" fill="none" stroke-linecap="round"/><path d="M68,46 Q74,38 80,46" stroke="#5eead4" stroke-width="4" fill="none" stroke-linecap="round"/>'))
    return ic, defs


def uzayli(p, k, r):
    a, d, l, v = p
    defs = _grad('ug', l, a)
    goz3 = r.random() < 0.5
    ic = _golge(rx=30)
    ic += (f'<path d="M44,30 Q38,14 32,10" stroke="{OUT}" stroke-width="3" fill="none"/><circle cx="32" cy="10" r="5" fill="{v}" stroke="{OUT}" stroke-width="2"/>'
           f'<path d="M76,30 Q82,14 88,10" stroke="{OUT}" stroke-width="3" fill="none"/><circle cx="88" cy="10" r="5" fill="{v}" stroke="{OUT}" stroke-width="2"/>')
    ic += f'<path d="M22,70 Q20,26 60,24 Q100,26 98,70 Q100,108 60,108 Q20,108 22,70 Z" fill="url(#ug)" stroke="{OUT}" stroke-width="3"/>'
    if goz3:
        ic += _goz(44, 56, 9, k == 1) + _goz(76, 56, 9, k == 1) + _goz(60, 42, 7, k == 1)
    else:
        ic += _goz(60, 52, 16, k == 1, (2, 1))
    ic += _agiz(60, 80, 12, acik=(k == 1))
    ic += f'<circle cx="34" cy="74" r="5" fill="{v}" opacity=".5"/><circle cx="86" cy="74" r="5" fill="{v}" opacity=".5"/>'
    return ic, defs


def canavar(p, k, r):
    a, d, l, v = p
    defs = _grad('cg', a, d)
    ic = _golge(rx=34)
    # tüyler (dalgalı kenar)
    n = 14
    pts = []
    for i in range(n * 2):
        ang = math.pi * 2 * i / (n * 2)
        rr = 42 if i % 2 == 0 else 37
        pts.append(f'{60 + rr*math.cos(ang):.1f},{66 + rr*0.9*math.sin(ang):.1f}')
    ic += f'<path d="M30,34 L24,12 L42,28 Z" fill="{v}" stroke="{OUT}" stroke-width="2.5" stroke-linejoin="round"/><path d="M90,34 L96,12 L78,28 Z" fill="{v}" stroke="{OUT}" stroke-width="2.5" stroke-linejoin="round"/>'
    ic += f'<polygon points="{" ".join(pts)}" fill="url(#cg)" stroke="{OUT}" stroke-width="3" stroke-linejoin="round"/>'
    ic += f'<ellipse cx="60" cy="80" rx="22" ry="16" fill="{l}" opacity=".8"/>'
    ic += _goz(46, 54, 10, k == 1) + _goz(74, 54, 10, k == 1)
    if k == 1:
        ic += f'<path d="M42,76 Q60,98 78,76 Z" fill="#7a1f3d" stroke="{OUT}" stroke-width="2.5"/><path d="M48,77 L52,84 L56,77 M64,77 L68,84 L72,77" fill="#fff" stroke="{OUT}" stroke-width="1.5"/>'
    else:
        ic += f'<path d="M44,76 Q60,90 76,76" fill="none" stroke="{OUT}" stroke-width="3" stroke-linecap="round"/><path d="M52,79 L55,85 L58,80" fill="#fff" stroke="{OUT}" stroke-width="1.5"/>'
    return ic, defs


def balik(p, k, r):
    a, d, l, v = p
    defs = _grad('bg', l, a)
    kuyruk = 'M24,60 L4,40 L8,60 L4,80 Z' if k == 0 else 'M24,60 L2,46 L10,60 L2,74 Z'
    ic = (f'<path d="{kuyruk}" fill="{v}" stroke="{OUT}" stroke-width="3" stroke-linejoin="round"/>'
          f'<path d="M56,34 Q66,18 80,34" fill="{v}" stroke="{OUT}" stroke-width="2.5"/>'
          f'<ellipse cx="62" cy="60" rx="42" ry="28" fill="url(#bg)" stroke="{OUT}" stroke-width="3"/>'
          f'<path d="M44,40 Q36,60 44,80" fill="none" stroke="{d}" stroke-width="3" opacity=".6"/>'
          f'<path d="M58,66 Q66,78 74,66" fill="{v}" stroke="{OUT}" stroke-width="2" opacity=".9"/>'
          + _goz(84, 52, 9, False, (2, 0)) +
          (f'<circle cx="104" cy="66" r="3" fill="{OUT}"/>' if k == 0 else f'<ellipse cx="103" cy="66" rx="4" ry="5" fill="#7a1f3d" stroke="{OUT}" stroke-width="2"/><circle cx="112" cy="54" r="3" fill="none" stroke="#7dd3fc" stroke-width="1.5"/><circle cx="116" cy="44" r="4" fill="none" stroke="#7dd3fc" stroke-width="1.5"/>'))
    return ic, defs


def kus(p, k, r):
    a, d, l, v = p
    defs = _grad('kg', a, d)
    kanat = (f'<path d="M40,66 Q20,50 26,36 Q44,44 54,62 Z" fill="{l}" stroke="{OUT}" stroke-width="2.5"/>' if k == 0 else
             f'<path d="M42,64 Q18,76 24,92 Q44,86 54,68 Z" fill="{l}" stroke="{OUT}" stroke-width="2.5"/>')
    ic = (_golge(rx=24) +
          f'<path d="M52,100 L48,110 M68,100 L72,110" stroke="{v}" stroke-width="4" stroke-linecap="round"/>'
          f'<path d="M58,28 Q52,14 62,16 Q60,22 66,24" fill="{v}" stroke="{OUT}" stroke-width="2"/>'
          f'<ellipse cx="60" cy="66" rx="34" ry="36" fill="url(#kg)" stroke="{OUT}" stroke-width="3"/>'
          f'<ellipse cx="62" cy="78" rx="20" ry="18" fill="{l}" opacity=".85"/>'
          + kanat + _goz(50, 52, 8, False, (1, 0)) + _goz(72, 52, 8, False, (1, 0)) +
          f'<path d="M56,62 L66,62 L61,72 Z" fill="{v}" stroke="{OUT}" stroke-width="2.5" stroke-linejoin="round"/>')
    return ic, defs


def ejderha(p, k, r):
    a, d, l, v = p
    defs = _grad('eg', a, d)
    kanat = 'M30,58 Q8,34 14,22 Q28,36 40,46 Z' if k == 0 else 'M30,58 Q4,52 6,38 Q24,42 40,48 Z'
    kanat2 = 'M90,58 Q112,34 106,22 Q92,36 80,46 Z' if k == 0 else 'M90,58 Q116,52 114,38 Q96,42 80,48 Z'
    ic = (_golge(rx=30) +
          f'<path d="{kanat}" fill="{v}" stroke="{OUT}" stroke-width="2.5" stroke-linejoin="round"/><path d="{kanat2}" fill="{v}" stroke="{OUT}" stroke-width="2.5" stroke-linejoin="round"/>'
          f'<path d="M84,96 Q110,100 108,84" fill="none" stroke="{OUT}" stroke-width="7" stroke-linecap="round"/><path d="M84,96 Q110,100 108,84" fill="none" stroke="{a}" stroke-width="3.5" stroke-linecap="round"/>'
          f'<ellipse cx="60" cy="82" rx="28" ry="24" fill="url(#eg)" stroke="{OUT}" stroke-width="3"/>'
          f'<ellipse cx="60" cy="88" rx="16" ry="14" fill="{l}"/>'
          f'<path d="M44,30 L40,16 L52,26 M76,30 L80,16 L68,26" fill="{v}" stroke="{OUT}" stroke-width="2.5" stroke-linejoin="round"/>'
          f'<ellipse cx="60" cy="46" rx="30" ry="24" fill="url(#eg)" stroke="{OUT}" stroke-width="3"/>'
          + _goz(48, 42, 8, False) + _goz(72, 42, 8, False) +
          f'<circle cx="54" cy="58" r="2" fill="{OUT}"/><circle cx="66" cy="58" r="2" fill="{OUT}"/>'
          + (f'<path d="M50,62 Q60,68 70,62" fill="none" stroke="{OUT}" stroke-width="2.5" stroke-linecap="round"/>' if k == 0 else
             f'<path d="M60,64 Q66,74 58,80 Q72,78 76,68 Q70,72 66,64 Z" fill="#ff9f1c" stroke="#e07a00" stroke-width="1.5"/>'))
    return ic, defs


def hayalet(p, k, r):
    a, d, l, v = p
    defs = _grad('hg', '#ffffff', l)
    alt = 'Q30,104 38,96 Q46,108 54,98 Q62,108 70,98 Q78,108 86,98 Q94,104 92,90' if k == 0 else 'Q32,98 40,104 Q48,94 56,104 Q64,94 72,104 Q80,94 88,104 Q94,96 92,90'
    ic = (_golge(rx=22, cy=114) +
          f'<path d="M28,90 Q24,22 60,20 Q96,22 92,90 {alt} Z" fill="url(#hg)" stroke="{OUT}" stroke-width="3" stroke-linejoin="round"/>'
          + _goz(48, 54, 8, k == 1) + _goz(72, 54, 8, k == 1) +
          f'<ellipse cx="60" cy="74" rx="6" ry="{8 if k==0 else 4}" fill="{OUT}"/>'
          f'<circle cx="38" cy="66" r="5" fill="{a}" opacity=".45"/><circle cx="82" cy="66" r="5" fill="{a}" opacity=".45"/>')
    return ic, defs


def roket(p, k, r):
    a, d, l, v = p
    defs = _grad('rkg', '#ffffff', l, True)
    alev = (f'<path d="M48,96 Q60,124 72,96 Z" fill="#ff9f1c" stroke="#e07a00" stroke-width="2"/><path d="M54,96 Q60,114 66,96 Z" fill="#fee440"/>' if k == 1 else
            f'<path d="M50,96 Q60,110 70,96 Z" fill="#ff9f1c" stroke="#e07a00" stroke-width="2"/>')
    # mBlock kuralı: kostüm SAĞA bakar (yön 90). Roket yatay çizilir; yön 0 verilince yukarı döner.
    ic = ('<g transform="rotate(90 60 60)">' + alev +
          f'<path d="M40,80 L26,98 L42,96 Z M80,80 L94,98 L78,96 Z" fill="{a}" stroke="{OUT}" stroke-width="2.5" stroke-linejoin="round"/>'
          f'<path d="M60,6 Q84,30 82,72 L80,96 L40,96 L38,72 Q36,30 60,6 Z" fill="url(#rkg)" stroke="{OUT}" stroke-width="3"/>'
          f'<path d="M60,6 Q72,16 77,30 L43,30 Q48,16 60,6 Z" fill="{a}" stroke="{OUT}" stroke-width="2.5"/>'
          f'<circle cx="60" cy="54" r="12" fill="#7dd3fc" stroke="{OUT}" stroke-width="3"/><circle cx="56" cy="50" r="4" fill="#fff" opacity=".8"/>'
          f'<rect x="44" y="82" width="32" height="8" rx="3" fill="{v}" stroke="{OUT}" stroke-width="2"/></g>')
    return ic, defs


def araba(p, k, r):
    a, d, l, v = p
    defs = _grad('ag', a, d)
    teker = lambda x: (f'<circle cx="{x}" cy="88" r="13" fill="#334155" stroke="{OUT}" stroke-width="3"/><circle cx="{x}" cy="88" r="5" fill="#cbd5e1"/>'
                       + (f'<path d="M{x-9},88 L{x+9},88" stroke="#cbd5e1" stroke-width="2"/>' if k == 0 else f'<path d="M{x},79 L{x},97" stroke="#cbd5e1" stroke-width="2"/>'))
    ic = (_golge(rx=46, cy=104) +
          f'<path d="M14,84 L14,66 Q16,58 26,56 L38,56 L50,38 Q54,34 62,34 L84,34 Q92,34 96,42 L104,56 Q114,58 114,68 L114,84 Z" fill="url(#ag)" stroke="{OUT}" stroke-width="3" stroke-linejoin="round"/>'
          f'<path d="M54,42 L82,42 L92,56 L44,56 Z" fill="#bae6fd" stroke="{OUT}" stroke-width="2.5" stroke-linejoin="round"/>'
          f'<path d="M66,42 L66,56" stroke="{OUT}" stroke-width="2.5"/>'
          f'<rect x="104" y="64" width="10" height="6" rx="2" fill="{v}"/>'
          f'<path d="M14,72 L114,72" stroke="{l}" stroke-width="3" opacity=".7"/>'
          + teker(36) + teker(92))
    if k == 1:
        ic += '<path d="M4,62 L-2,62 M6,72 L-4,72 M4,80 L-2,80" stroke="#94a3b8" stroke-width="3" stroke-linecap="round"/>'
    return ic, defs


def top(p, k, r):
    a, d, l, v = p
    defs = f'<radialGradient id="tg" cx=".35" cy=".3" r=".75"><stop offset="0" stop-color="{l}"/><stop offset="1" stop-color="{a}"/></radialGradient>'
    ic = (_golge(rx=26) +
          f'<circle cx="60" cy="62" r="38" fill="url(#tg)" stroke="{OUT}" stroke-width="3"/>'
          + (f'<path d="M24,62 Q60,{40 if k==0 else 84} 96,62" fill="none" stroke="{v}" stroke-width="6"/><path d="M60,24 Q{40 if k==0 else 80},62 60,100" fill="none" stroke="{v}" stroke-width="6"/>')
          + '<ellipse cx="46" cy="44" rx="9" ry="6" fill="#fff" opacity=".6" transform="rotate(-30 46 44)"/>')
    return ic, defs


def yildiz(p, k, r):
    a, d, l, v = p
    defs = _grad('yg', '#fff7b0', v)
    pts = []
    for i in range(10):
        ang = -math.pi / 2 + i * math.pi / 5
        rr = 50 if i % 2 == 0 else 22
        pts.append(f'{60+rr*math.cos(ang):.1f},{62+rr*math.sin(ang):.1f}')
    ic = (f'<polygon points="{" ".join(pts)}" fill="url(#yg)" stroke="{OUT}" stroke-width="3" stroke-linejoin="round"/>'
          + _goz(50, 60, 6, k == 1) + _goz(70, 60, 6, k == 1) + _agiz(60, 72, 7, acik=(k == 1)) +
          f'<circle cx="42" cy="70" r="4" fill="{a}" opacity=".6"/><circle cx="78" cy="70" r="4" fill="{a}" opacity=".6"/>')
    return ic, defs


def ugur_bocegi(p, k, r):
    a, d, l, v = p
    defs = _grad('ubg', a, d)
    acik = 10 if k == 1 else 0
    ic = (_golge(rx=30) +
          f'<path d="M48,30 Q40,14 34,12 M72,30 Q80,14 86,12" stroke="{OUT}" stroke-width="3" fill="none" stroke-linecap="round"/>'
          f'<circle cx="34" cy="12" r="4" fill="{OUT}"/><circle cx="86" cy="12" r="4" fill="{OUT}"/>'
          f'<path d="M24,58 L12,52 M24,74 L10,76 M26,90 L14,98 M96,58 L108,52 M96,74 L110,76 M94,90 L106,98" stroke="{OUT}" stroke-width="3.5" stroke-linecap="round"/>'
          f'<ellipse cx="60" cy="72" rx="36" ry="38" fill="#1e293b" stroke="{OUT}" stroke-width="3"/>'
          f'<path d="M60,36 Q{24-acik},40 26,76 Q30,106 60,108 Z" fill="url(#ubg)" stroke="{OUT}" stroke-width="3"/>'
          f'<path d="M60,36 Q{96+acik},40 94,76 Q90,106 60,108 Z" fill="url(#ubg)" stroke="{OUT}" stroke-width="3"/>'
          f'<circle cx="44" cy="62" r="6" fill="{OUT}"/><circle cx="40" cy="86" r="5" fill="{OUT}"/><circle cx="76" cy="62" r="6" fill="{OUT}"/><circle cx="80" cy="86" r="5" fill="{OUT}"/>'
          f'<ellipse cx="60" cy="34" rx="20" ry="14" fill="#1e293b" stroke="{OUT}" stroke-width="3"/>'
          + _goz(52, 32, 5) + _goz(68, 32, 5))
    return ic, defs


def ahtapot(p, k, r):
    a, d, l, v = p
    defs = _grad('ahg', l, a)
    kollar = ''
    for i, x in enumerate([30, 44, 60, 76, 90]):
        dx = (6 if (i + k) % 2 else -6)
        kollar += f'<path d="M{x},78 Q{x+dx},96 {x-dx},108" stroke="{OUT}" stroke-width="12" fill="none" stroke-linecap="round"/><path d="M{x},78 Q{x+dx},96 {x-dx},108" stroke="{a}" stroke-width="7" fill="none" stroke-linecap="round"/>'
    ic = (kollar +
          f'<path d="M22,74 Q18,14 60,14 Q102,14 98,74 Q60,88 22,74 Z" fill="url(#ahg)" stroke="{OUT}" stroke-width="3"/>'
          f'<circle cx="40" cy="30" r="5" fill="{l}" opacity=".7"/><circle cx="82" cy="36" r="4" fill="{l}" opacity=".7"/>'
          + _goz(46, 50, 9, k == 1) + _goz(74, 50, 9, k == 1) + _agiz(60, 66, 8, acik=False))
    return ic, defs


def penguen(p, k, r):
    a, d, l, v = p
    defs = _grad('pg', '#334155', '#0f172a')
    kanat = -20 if k == 1 else 0
    ic = (_golge(rx=26) +
          f'<path d="M44,104 L36,110 L52,110 Z M76,104 L84,110 L68,110 Z" fill="#ff9f1c" stroke="{OUT}" stroke-width="2"/>'
          f'<path d="M28,62 Q14,80 22,96" stroke="{OUT}" stroke-width="10" fill="none" stroke-linecap="round" transform="rotate({kanat} 28 62)"/>'
          f'<path d="M92,62 Q106,80 98,96" stroke="{OUT}" stroke-width="10" fill="none" stroke-linecap="round" transform="rotate({-kanat} 92 62)"/>'
          f'<ellipse cx="60" cy="66" rx="34" ry="42" fill="url(#pg)" stroke="{OUT}" stroke-width="3"/>'
          f'<ellipse cx="60" cy="74" rx="24" ry="32" fill="#fff"/>'
          f'<path d="M30,40 Q60,{24} 90,40 L90,46 Q60,34 30,46 Z" fill="{a}" stroke="{OUT}" stroke-width="2"/>'
          + _goz(50, 52, 7, k == 1) + _goz(70, 52, 7, k == 1) +
          f'<path d="M54,62 L66,62 L60,70 Z" fill="#ff9f1c" stroke="{OUT}" stroke-width="2" stroke-linejoin="round"/>'
          f'<circle cx="40" cy="64" r="4" fill="{v}" opacity=".5"/><circle cx="80" cy="64" r="4" fill="{v}" opacity=".5"/>')
    return ic, defs


def kurbaga(p, k, r):
    a, d, l, v = p
    defs = _grad('kbg', a, d)
    bacak = 'M28,96 Q10,104 18,112' if k == 0 else 'M28,90 Q2,80 6,64'
    bacak2 = 'M92,96 Q110,104 102,112' if k == 0 else 'M92,90 Q118,80 114,64'
    ic = (_golge(rx=32) +
          f'<path d="{bacak}" stroke="{OUT}" stroke-width="11" fill="none" stroke-linecap="round"/><path d="{bacak}" stroke="{a}" stroke-width="6" fill="none" stroke-linecap="round"/>'
          f'<path d="{bacak2}" stroke="{OUT}" stroke-width="11" fill="none" stroke-linecap="round"/><path d="{bacak2}" stroke="{a}" stroke-width="6" fill="none" stroke-linecap="round"/>'
          f'<ellipse cx="60" cy="76" rx="40" ry="30" fill="url(#kbg)" stroke="{OUT}" stroke-width="3"/>'
          f'<ellipse cx="60" cy="86" rx="24" ry="16" fill="{l}"/>'
          f'<circle cx="40" cy="44" r="15" fill="url(#kbg)" stroke="{OUT}" stroke-width="3"/><circle cx="80" cy="44" r="15" fill="url(#kbg)" stroke="{OUT}" stroke-width="3"/>'
          + _goz(40, 44, 9, False) + _goz(80, 44, 9, False) +
          f'<path d="M38,70 Q60,{86 if k==0 else 92} 82,70" fill="{"none" if k==0 else "#7a1f3d"}" stroke="{OUT}" stroke-width="3" stroke-linecap="round"/>'
          f'<circle cx="32" cy="76" r="5" fill="{v}" opacity=".45"/><circle cx="88" cy="76" r="5" fill="{v}" opacity=".45"/>')
    return ic, defs


def ari(p, k, r):
    a, d, l, v = p
    defs = _grad('arg', '#fee440', '#f9c80e')
    kanat_y = 20 if k == 0 else 30
    ic = (f'<ellipse cx="44" cy="{kanat_y+14}" rx="16" ry="22" fill="#e0f2fe" stroke="{OUT}" stroke-width="2.5" opacity=".9" transform="rotate(-20 44 {kanat_y+14})"/>'
          f'<ellipse cx="72" cy="{kanat_y+14}" rx="16" ry="22" fill="#e0f2fe" stroke="{OUT}" stroke-width="2.5" opacity=".9" transform="rotate(20 72 {kanat_y+14})"/>'
          f'<path d="M100,66 L114,66 L100,72 Z" fill="{OUT}"/>'
          f'<ellipse cx="62" cy="68" rx="40" ry="28" fill="url(#arg)" stroke="{OUT}" stroke-width="3"/>'
          f'<path d="M58,41 Q52,68 58,95 M76,44 Q70,68 76,92 M92,52 Q88,68 92,84" stroke="{OUT}" stroke-width="7" fill="none"/>'
          f'<circle cx="30" cy="62" r="20" fill="url(#arg)" stroke="{OUT}" stroke-width="3"/>'
          f'<path d="M24,44 Q18,30 12,28 M36,44 Q38,30 44,26" stroke="{OUT}" stroke-width="2.5" fill="none"/>'
          + _goz(24, 58, 6, k == 1) + _goz(38, 58, 6, k == 1) + _agiz(30, 70, 5, acik=(k == 1)))
    return ic, defs


def slime(p, k, r):
    a, d, l, v = p
    defs = _grad('sg', l, a)
    if k == 0:
        govde = 'M16,104 Q14,44 60,40 Q106,44 104,104 Z'
    else:
        govde = 'M24,104 Q20,30 60,26 Q100,30 96,104 Z'
    ic = (_golge(rx=40) +
          f'<path d="{govde}" fill="url(#sg)" stroke="{OUT}" stroke-width="3" stroke-linejoin="round"/>'
          f'<ellipse cx="40" cy="{56 if k==0 else 44}" rx="8" ry="5" fill="#fff" opacity=".6"/>'
          + _goz(46, 72 if k == 0 else 62, 8) + _goz(74, 72 if k == 0 else 62, 8) + _agiz(60, 88 if k == 0 else 80, 8, acik=(k == 1)))
    return ic, defs


# --- nesneler (toplanabilir / hedef) ---
def elma(p, k, r):
    a, d, l, v = p
    renk = '#ef4444' if k == 0 else '#22c55e'
    defs = f'<radialGradient id="eg2" cx=".35" cy=".35" r=".8"><stop offset="0" stop-color="#fff" stop-opacity=".6"/><stop offset=".3" stop-color="{renk}"/><stop offset="1" stop-color="{renk}"/></radialGradient>'
    ic = (_golge(rx=24) + f'<path d="M60,34 Q86,18 100,46 Q110,86 82,104 Q70,110 60,102 Q50,110 38,104 Q10,86 20,46 Q34,18 60,34 Z" fill="url(#eg2)" stroke="{OUT}" stroke-width="3"/>'
          f'<path d="M60,34 Q58,20 64,12" stroke="#78350f" stroke-width="4" fill="none" stroke-linecap="round"/>'
          f'<path d="M64,22 Q80,10 88,20 Q76,30 64,22 Z" fill="#22c55e" stroke="{OUT}" stroke-width="2"/>')
    return ic, defs


def hazine(p, k, r):
    a, d, l, v = p
    defs = _grad('hzg', '#b45309', '#78350f')
    kapak = ('M18,52 Q60,26 102,52 L102,60 L18,60 Z' if k == 0 else 'M18,52 Q30,12 70,10 L76,18 Q40,22 30,56 Z')
    ic = (_golge(rx=44, cy=108) +
          (f'<path d="M30,58 L90,58 L82,40 L38,40 Z" fill="#fee440" opacity=".9"/><circle cx="50" cy="46" r="6" fill="#facc15" stroke="#ca8a04" stroke-width="1.5"/><circle cx="68" cy="44" r="7" fill="#facc15" stroke="#ca8a04" stroke-width="1.5"/><path d="M58,28 l3,-8 l3,8 l8,0 l-6,5 l3,8 l-8,-5 l-8,5 l3,-8 l-6,-5 Z" fill="#fff" opacity=".9"/>' if k == 1 else '') +
          f'<rect x="18" y="58" width="84" height="46" rx="6" fill="url(#hzg)" stroke="{OUT}" stroke-width="3"/>'
          f'<path d="{kapak}" fill="url(#hzg)" stroke="{OUT}" stroke-width="3" stroke-linejoin="round"/>'
          f'<rect x="18" y="66" width="84" height="8" fill="#facc15" stroke="{OUT}" stroke-width="2"/>'
          f'<rect x="52" y="62" width="16" height="22" rx="3" fill="#facc15" stroke="{OUT}" stroke-width="2.5"/><circle cx="60" cy="72" r="3" fill="{OUT}"/>')
    return ic, defs


def kalp(p, k, r):
    a, d, l, v = p
    s = 1.0 if k == 0 else 1.12
    defs = _grad('klg', '#ff8fab', '#ff006e')
    ic = (f'<g transform="translate(60 62) scale({s}) translate(-60 -62)"><path d="M60,100 Q14,70 18,42 Q22,18 44,20 Q56,22 60,36 Q64,22 76,20 Q98,18 102,42 Q106,70 60,100 Z" fill="url(#klg)" stroke="{OUT}" stroke-width="3" stroke-linejoin="round"/>'
          f'<ellipse cx="38" cy="38" rx="8" ry="5" fill="#fff" opacity=".6" transform="rotate(-30 38 38)"/></g>')
    return ic, defs


def elmas(p, k, r):
    a, d, l, v = p
    defs = _grad('elg', l, a)
    ic = (f'<path d="M30,40 L46,22 L74,22 L90,40 L60,100 Z" fill="url(#elg)" stroke="{OUT}" stroke-width="3" stroke-linejoin="round"/>'
          f'<path d="M30,40 L90,40 M46,22 L54,40 L60,100 L66,40 L74,22 M54,40 L60,22 L66,40" fill="none" stroke="{OUT}" stroke-width="2" stroke-linejoin="round" opacity=".7"/>'
          + ('<path d="M92,18 l3,-8 l3,8 l8,3 l-8,3 l-3,8 l-3,-8 l-8,-3 Z" fill="#fff" stroke="#facc15" stroke-width="1.5"/>' if k == 1 else ''))
    return ic, defs


def bayrak(p, k, r):
    a, d, l, v = p
    defs = ''
    dalga = 'M44,18 Q62,8 80,18 Q98,28 104,18 L104,52 Q98,62 80,52 Q62,42 44,52 Z' if k == 0 else 'M44,18 Q62,28 80,18 Q98,8 104,22 L104,56 Q98,42 80,52 Q62,62 44,52 Z'
    ic = (_golge(rx=18, cx=42) + f'<rect x="38" y="10" width="7" height="100" rx="3" fill="#94a3b8" stroke="{OUT}" stroke-width="2.5"/>'
          f'<path d="{dalga}" fill="{a}" stroke="{OUT}" stroke-width="3" stroke-linejoin="round"/>'
          f'<path d="M60,26 l4,8 l9,1 l-7,6 l2,9 l-8,-5 l-8,5 l2,-9 l-7,-6 l9,-1 Z" fill="{v}" stroke="{OUT}" stroke-width="1.5"/>')
    return ic, defs


def portal(p, k, r):
    a, d, l, v = p
    defs = f'<radialGradient id="ptg" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#fff"/><stop offset=".4" stop-color="{l}"/><stop offset="1" stop-color="{a}"/></radialGradient>'
    don = 0 if k == 0 else 45
    ic = (f'<ellipse cx="60" cy="60" rx="40" ry="50" fill="url(#ptg)" stroke="{OUT}" stroke-width="3"/>'
          f'<g transform="rotate({don} 60 60)"><path d="M60,20 Q90,40 70,60 Q50,80 60,100" fill="none" stroke="{v}" stroke-width="4" opacity=".8"/><path d="M30,50 Q60,40 70,60" fill="none" stroke="#fff" stroke-width="3" opacity=".8"/></g>')
    return ic, defs


def kedi(p, k, r):
    """Yuvarlak, sevimli kedi (bilinen bir karaktere benzemeyen; pastel, büyük kafa)"""
    a, d, l, v = p
    defs = _grad('kdg', l, a)
    kuyruk = 'M88,92 Q112,90 108,62' if k == 0 else 'M88,92 Q118,96 112,110'
    ic = (_golge(rx=30) +
          f'<path d="{kuyruk}" stroke="{OUT}" stroke-width="11" fill="none" stroke-linecap="round"/><path d="{kuyruk}" stroke="{a}" stroke-width="6" fill="none" stroke-linecap="round"/>'
          f'<ellipse cx="60" cy="90" rx="28" ry="20" fill="url(#kdg)" stroke="{OUT}" stroke-width="3"/>'
          f'<path d="M28,40 L26,12 L50,28 Z M92,40 L94,12 L70,28 Z" fill="url(#kdg)" stroke="{OUT}" stroke-width="3" stroke-linejoin="round"/>'
          f'<path d="M32,34 L31,20 L44,29 Z M88,34 L89,20 L76,29 Z" fill="{v}" opacity=".7"/>'
          f'<ellipse cx="60" cy="50" rx="36" ry="30" fill="url(#kdg)" stroke="{OUT}" stroke-width="3"/>'
          + _goz(46, 48, 8, k == 1) + _goz(74, 48, 8, k == 1) +
          f'<path d="M57,60 L63,60 L60,64 Z" fill="{v}" stroke="{OUT}" stroke-width="1.5"/>'
          f'<path d="M60,64 Q56,70 52,66 M60,64 Q64,70 68,66" stroke="{OUT}" stroke-width="2" fill="none" stroke-linecap="round"/>'
          f'<path d="M30,58 L16,56 M30,62 L16,66 M90,58 L104,56 M90,62 L104,66" stroke="{OUT}" stroke-width="1.8" stroke-linecap="round"/>'
          f'<circle cx="38" cy="60" r="4" fill="{v}" opacity=".5"/><circle cx="82" cy="60" r="4" fill="{v}" opacity=".5"/>')
    return ic, defs


def kopek(p, k, r):
    a, d, l, v = p
    defs = _grad('kpg', l, a)
    kulak_d = 0 if k == 0 else -25
    ic = (_golge(rx=30) +
          f'<ellipse cx="60" cy="92" rx="26" ry="18" fill="url(#kpg)" stroke="{OUT}" stroke-width="3"/>'
          f'<ellipse cx="26" cy="46" rx="12" ry="24" fill="{d}" stroke="{OUT}" stroke-width="3" transform="rotate({20+kulak_d} 26 30)"/>'
          f'<ellipse cx="94" cy="46" rx="12" ry="24" fill="{d}" stroke="{OUT}" stroke-width="3" transform="rotate({-20-kulak_d} 94 30)"/>'
          f'<ellipse cx="60" cy="50" rx="34" ry="30" fill="url(#kpg)" stroke="{OUT}" stroke-width="3"/>'
          f'<ellipse cx="60" cy="64" rx="16" ry="12" fill="#fff" stroke="{OUT}" stroke-width="2"/>'
          + _goz(46, 46, 7) + _goz(74, 46, 7) +
          f'<ellipse cx="60" cy="58" rx="6" ry="4.5" fill="{OUT}"/>'
          + (f'<path d="M54,68 Q60,72 66,68" stroke="{OUT}" stroke-width="2.2" fill="none" stroke-linecap="round"/>' if k == 0 else
             f'<path d="M54,67 Q60,72 66,67" stroke="{OUT}" stroke-width="2.2" fill="none"/><path d="M56,69 Q60,84 64,69 Z" fill="#ff7aa2" stroke="{OUT}" stroke-width="1.5"/>'))
    return ic, defs


KARAKTERLER = {
    'robot': robot, 'uzayli': uzayli, 'canavar': canavar, 'balik': balik, 'kus': kus, 'ejderha': ejderha,
    'hayalet': hayalet, 'roket': roket, 'araba': araba, 'top': top, 'yildiz': yildiz, 'ugur_bocegi': ugur_bocegi,
    'ahtapot': ahtapot, 'penguen': penguen, 'kurbaga': kurbaga, 'ari': ari, 'slime': slime, 'kedi': kedi, 'kopek': kopek,
    'elma': elma, 'hazine': hazine, 'kalp': kalp, 'elmas': elmas, 'bayrak': bayrak, 'portal': portal,
}


def kukla_svg(tur, palet_no, kostum_no, tohum=0):
    r = random.Random(tohum)
    p = PALETLER[palet_no % len(PALETLER)]
    ic, defs = KARAKTERLER[tur](p, kostum_no, r)
    # id çakışmasın diye gradyan adlarını benzersizleştir
    ek = f'_{tur}_{palet_no}_{kostum_no}'
    for g in ['rg', 'ug', 'cg', 'bg', 'kg', 'eg', 'hg', 'rkg', 'ag', 'tg', 'yg', 'ubg', 'ahg', 'pg', 'kbg', 'arg', 'sg', 'eg2', 'hzg', 'klg', 'elg', 'ptg', 'kdg', 'kpg']:
        defs = defs.replace(f'id="{g}"', f'id="{g}{ek}"')
        ic = ic.replace(f'url(#{g})', f'url(#{g}{ek})')
    return _svg(ic, defs=defs)


# ---------------------------------------------------------------------------
# DEKORLAR (480 x 360)
# ---------------------------------------------------------------------------
GOK_PALET = {
    'gunduz': ('#7dd3fc', '#e0f2fe'), 'gun_batimi': ('#f472b6', '#fde68a'), 'gece': ('#1e1b4b', '#4338ca'),
    'uzay': ('#0b1026', '#312e81'), 'pastel': ('#c4b5fd', '#fbcfe8'), 'sabah': ('#a5f3fc', '#fef9c3'),
}


def _dekor(ic, defs=''):
    return _svg(ic, 480, 360, defs)


def _bulut(x, y, s, op=0.95):
    return (f'<g opacity="{op}" transform="translate({x} {y}) scale({s})"><ellipse cx="0" cy="0" rx="34" ry="18" fill="#fff"/>'
            f'<ellipse cx="-24" cy="6" rx="22" ry="14" fill="#fff"/><ellipse cx="26" cy="6" rx="24" ry="14" fill="#fff"/><ellipse cx="6" cy="-12" rx="20" ry="16" fill="#fff"/></g>')


def _agac(x, y, s, c1, c2):
    return (f'<g transform="translate({x} {y}) scale({s})"><rect x="-6" y="-10" width="12" height="40" rx="4" fill="#92400e"/>'
            f'<circle cx="0" cy="-30" r="30" fill="{c1}"/><circle cx="-18" cy="-14" r="20" fill="{c2}"/><circle cx="18" cy="-16" r="22" fill="{c2}"/></g>')


def dekor_svg(tema, tohum=0, varyant=0):
    r = random.Random(tohum * 7919 + varyant)
    if tema == 'uzay':
        defs = _grad('dg', '#0b1026', ['#312e81', '#4c1d95', '#1e3a8a', '#0f766e'][varyant % 4])
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        for _ in range(70):
            ic += f'<circle cx="{r.randint(0,480)}" cy="{r.randint(0,360)}" r="{r.choice([0.8,1.2,1.6,2.2])}" fill="#fff" opacity="{r.uniform(.4,1):.2f}"/>'
        for _ in range(r.randint(2, 3)):
            c = r.choice(PALETLER)
            x, y, rr = r.randint(40, 440), r.randint(40, 200), r.randint(18, 46)
            ic += f'<circle cx="{x}" cy="{y}" r="{rr}" fill="{c[0]}"/><circle cx="{x-rr*.3}" cy="{y-rr*.3}" r="{rr*.35}" fill="{c[2]}" opacity=".5"/>'
            if r.random() < .5:
                ic += f'<ellipse cx="{x}" cy="{y}" rx="{rr*1.6}" ry="{rr*.35}" fill="none" stroke="{c[3]}" stroke-width="4" opacity=".8"/>'
        ic += f'<path d="M0,330 Q120,{300+r.randint(-10,10)} 240,325 T480,320 L480,360 L0,360 Z" fill="#475569"/><path d="M0,345 Q140,330 260,342 T480,338 L480,360 L0,360 Z" fill="#334155"/>'
        for _ in range(5):
            ic += f'<ellipse cx="{r.randint(20,460)}" cy="{r.randint(338,352)}" rx="{r.randint(8,18)}" ry="4" fill="#1e293b"/>'
        return _dekor(ic, defs)
    if tema in ('okyanus', 'sualti'):
        defs = _grad('dg', ['#38bdf8', '#22d3ee', '#0ea5e9', '#2dd4bf'][varyant % 4], '#0c4a6e')
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        for i in range(5):
            ic += f'<path d="M{r.randint(0,480)},0 L{r.randint(0,480)},360" stroke="#fff" stroke-width="{r.randint(14,30)}" opacity=".05"/>'
        ic += '<path d="M0,310 Q100,290 200,305 T400,300 T480,302 L480,360 L0,360 Z" fill="#fcd34d"/><path d="M0,330 Q120,318 240,330 T480,325 L480,360 L0,360 Z" fill="#f59e0b" opacity=".5"/>'
        for _ in range(7):
            x = r.randint(10, 470); h = r.randint(40, 110); c = r.choice(['#22c55e', '#16a34a', '#84cc16', '#f472b6'])
            ic += f'<path d="M{x},312 Q{x-14},{312-h/2} {x},{312-h} Q{x+14},{312-h/2} {x},312" fill="none" stroke="{c}" stroke-width="7" stroke-linecap="round"/>'
        for _ in range(16):
            ic += f'<circle cx="{r.randint(0,480)}" cy="{r.randint(10,290)}" r="{r.randint(3,9)}" fill="none" stroke="#e0f2fe" stroke-width="2" opacity=".6"/>'
        for _ in range(3):
            x = r.randint(30, 450)
            ic += f'<path d="M{x},318 q-10,-18 0,-26 q10,8 0,26" fill="#fb7185"/><circle cx="{x+20}" cy="316" r="7" fill="#fde68a"/>'
        return _dekor(ic, defs)
    if tema == 'orman':
        gok = r.choice(['gunduz', 'sabah', 'gun_batimi'])
        defs = _grad('dg', GOK_PALET[gok][0], GOK_PALET[gok][1])
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        ic += f'<circle cx="{r.randint(60,420)}" cy="60" r="30" fill="#fde047"/>'
        for _ in range(3): ic += _bulut(r.randint(40, 440), r.randint(40, 110), r.uniform(.6, 1.1))
        ic += '<path d="M0,240 Q120,200 240,230 T480,215 L480,360 L0,360 Z" fill="#86efac"/>'
        for _ in range(6): ic += _agac(r.randint(10, 470), r.randint(214, 236), r.uniform(.7, 1.1), '#16a34a', '#22c55e')
        ic += '<path d="M0,280 Q160,260 300,280 T480,270 L480,360 L0,360 Z" fill="#4ade80"/>'
        for _ in range(14):
            x = r.randint(0, 480); y = r.randint(290, 350); c = r.choice(['#f472b6', '#facc15', '#fb923c', '#a78bfa', '#fff'])
            ic += f'<circle cx="{x}" cy="{y}" r="4" fill="{c}"/><circle cx="{x}" cy="{y}" r="1.6" fill="#fde047"/>'
        return _dekor(ic, defs)
    if tema == 'sehir':
        defs = _grad('dg', '#1e1b4b', ['#7c3aed', '#db2777', '#2563eb', '#0d9488'][varyant % 4])
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        for _ in range(30): ic += f'<circle cx="{r.randint(0,480)}" cy="{r.randint(0,150)}" r="1.2" fill="#fff" opacity=".8"/>'
        ic += f'<circle cx="{r.randint(60,420)}" cy="56" r="24" fill="#fef3c7"/>'
        x = -10
        while x < 480:
            w = r.randint(40, 80); h = r.randint(110, 230); c = r.choice(['#312e81', '#3730a3', '#4c1d95', '#1e3a8a', '#581c87'])
            ic += f'<rect x="{x}" y="{310-h}" width="{w}" height="{h}" fill="{c}"/>'
            for wy in range(310 - h + 12, 300, 18):
                for wx in range(x + 8, x + w - 8, 14):
                    if r.random() < .55:
                        ic += f'<rect x="{wx}" y="{wy}" width="7" height="9" rx="1" fill="{r.choice(["#fde047","#fef9c3","#f9a8d4","#67e8f9"])}" opacity=".9"/>'
            x += w + r.randint(2, 10)
        ic += '<rect x="0" y="310" width="480" height="50" fill="#1f2937"/>'
        for xx in range(10, 480, 60): ic += f'<rect x="{xx}" y="333" width="34" height="5" rx="2" fill="#facc15"/>'
        return _dekor(ic, defs)
    if tema == 'col':
        defs = _grad('dg', '#fb923c', '#fde68a')
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        ic += f'<circle cx="{r.randint(300,440)}" cy="70" r="40" fill="#fff7ed" opacity=".9"/>'
        ic += '<path d="M0,250 Q120,210 240,245 T480,230 L480,360 L0,360 Z" fill="#fbbf24"/><path d="M0,290 Q160,260 320,290 T480,280 L480,360 L0,360 Z" fill="#f59e0b"/>'
        for _ in range(4):
            x = r.randint(30, 450); y = r.randint(250, 300); h = r.randint(40, 70)
            ic += (f'<g fill="#16a34a" stroke="#14532d" stroke-width="2.5"><rect x="{x-8}" y="{y-h}" width="16" height="{h}" rx="8"/>'
                   f'<path d="M{x-8},{y-h*0.5} q-16,0 -16,-16 l0,-8" fill="none" stroke-width="10" stroke="#16a34a" stroke-linecap="round"/>'
                   f'<path d="M{x+8},{y-h*0.6} q14,0 14,-14 l0,-6" fill="none" stroke-width="10" stroke="#16a34a" stroke-linecap="round"/></g>')
        return _dekor(ic, defs)
    if tema == 'seker':
        defs = _grad('dg', '#fbcfe8', '#c4b5fd')
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        for _ in range(3): ic += _bulut(r.randint(40, 440), r.randint(30, 100), r.uniform(.6, 1))
        ic += '<path d="M0,250 Q80,190 170,240 T340,225 T480,235 L480,360 L0,360 Z" fill="#f9a8d4"/><path d="M0,300 Q140,270 260,300 T480,290 L480,360 L0,360 Z" fill="#f472b6"/>'
        for _ in range(5):
            x = r.randint(20, 460); y = r.randint(220, 270); c = r.choice(PALETLER)
            ic += (f'<rect x="{x-2}" y="{y}" width="4" height="50" fill="#fff"/>'
                    f'<circle cx="{x}" cy="{y}" r="20" fill="{c[0]}" stroke="#fff" stroke-width="4"/>'
                    f'<path d="M{x-12},{y} A12,12 0 0 1 {x+12},{y}" fill="none" stroke="#fff" stroke-width="3"/>')
        for _ in range(12):
            ic += f'<rect x="{r.randint(0,470)}" y="{r.randint(300,350)}" width="10" height="4" rx="2" fill="{r.choice(["#fde047","#38bdf8","#a3e635","#fff"])}" transform="rotate({r.randint(0,180)})"/>'
        return _dekor(ic, defs)
    if tema == 'kar':
        defs = _grad('dg', '#bae6fd', '#f0f9ff')
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        ic += '<path d="M0,200 L80,110 L150,190 L240,90 L330,190 L400,120 L480,200 L480,360 L0,360 Z" fill="#93c5fd"/>'
        ic += '<path d="M80,110 L60,135 L80,128 L98,132 Z M240,90 L214,122 L240,112 L262,120 Z M400,120 L382,143 L400,136 L417,140 Z" fill="#fff"/>'
        ic += '<path d="M0,260 Q120,230 240,255 T480,245 L480,360 L0,360 Z" fill="#fff"/>'
        for _ in range(4):
            x = r.randint(20, 460); y = r.randint(250, 280)
            ic += f'<path d="M{x},{y} l-22,0 l22,-50 l22,50 Z" fill="#15803d"/><path d="M{x-10},{y-22} l10,-6 l10,6" stroke="#fff" stroke-width="4" fill="none"/>'
        for _ in range(40):
            ic += f'<circle cx="{r.randint(0,480)}" cy="{r.randint(0,360)}" r="{r.choice([1.5,2,3])}" fill="#fff" opacity=".9"/>'
        return _dekor(ic, defs)
    if tema == 'volkan':
        defs = _grad('dg', '#7f1d1d', '#f97316')
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        ic += '<path d="M120,260 L210,90 L270,90 L360,260 Z" fill="#44403c"/><path d="M210,90 L270,90 L258,120 L240,104 L222,124 Z" fill="#f97316"/>'
        ic += '<path d="M232,88 Q220,40 250,20 Q240,50 262,70 Z" fill="#fbbf24" opacity=".8"/>'
        for _ in range(6): ic += f'<circle cx="{r.randint(180,300)}" cy="{r.randint(10,70)}" r="{r.randint(6,14)}" fill="#57534e" opacity=".7"/>'
        ic += '<path d="M0,260 L480,260 L480,360 L0,360 Z" fill="#292524"/>'
        ic += '<path d="M0,300 Q120,284 240,300 T480,296 L480,320 Q360,330 240,318 T0,322 Z" fill="#ef4444"/>'
        return _dekor(ic, defs)
    if tema == 'gokyuzu':
        gok = ['gunduz', 'pastel', 'sabah', 'gun_batimi'][varyant % 4]
        defs = _grad('dg', GOK_PALET[gok][0], GOK_PALET[gok][1])
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        if r.random() < .6:
            cx, cy = r.randint(150, 330), 330
            for i, c in enumerate(['#ef4444', '#f97316', '#facc15', '#22c55e', '#3b82f6', '#a855f7']):
                ic += f'<path d="M{cx-200+i*12},{cy} A{200-i*12},{200-i*12} 0 0 1 {cx+200-i*12},{cy}" fill="none" stroke="{c}" stroke-width="12" opacity=".75"/>'
        for _ in range(6): ic += _bulut(r.randint(20, 460), r.randint(30, 320), r.uniform(.7, 1.4), r.uniform(.8, 1))
        return _dekor(ic, defs)
    if tema == 'stadyum':
        defs = _grad('dg', '#22c55e', '#15803d')
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        for i in range(0, 480, 60): ic += f'<rect x="{i}" y="0" width="30" height="360" fill="#fff" opacity=".06"/>'
        ic += ('<rect x="20" y="20" width="440" height="320" fill="none" stroke="#fff" stroke-width="4"/>'
               '<line x1="240" y1="20" x2="240" y2="340" stroke="#fff" stroke-width="4"/><circle cx="240" cy="180" r="50" fill="none" stroke="#fff" stroke-width="4"/>'
               '<rect x="20" y="120" width="50" height="120" fill="none" stroke="#fff" stroke-width="4"/><rect x="410" y="120" width="50" height="120" fill="none" stroke="#fff" stroke-width="4"/>'
               '<rect x="4" y="150" width="16" height="60" fill="#e2e8f0" stroke="#fff" stroke-width="2"/><rect x="460" y="150" width="16" height="60" fill="#e2e8f0" stroke="#fff" stroke-width="2"/>')
        return _dekor(ic, defs)
    if tema == 'laboratuvar':
        defs = _grad('dg', '#e0e7ff', '#c7d2fe')
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        for x in range(0, 480, 30): ic += f'<line x1="{x}" y1="0" x2="{x}" y2="260" stroke="#a5b4fc" stroke-width="1" opacity=".5"/>'
        for y in range(0, 260, 30): ic += f'<line x1="0" y1="{y}" x2="480" y2="{y}" stroke="#a5b4fc" stroke-width="1" opacity=".5"/>'
        ic += '<rect x="0" y="260" width="480" height="100" fill="#64748b"/><rect x="0" y="256" width="480" height="10" fill="#94a3b8"/>'
        for _ in range(5):
            x = r.randint(20, 450); c = r.choice(['#22c55e', '#ec4899', '#38bdf8', '#facc15', '#a855f7'])
            ic += f'<path d="M{x},200 l0,-28 l-4,0 l0,-6 l20,0 l0,6 l-4,0 l0,28 l14,52 l-40,0 Z" fill="#f8fafc" stroke="#334155" stroke-width="2.5"/><path d="M{x-6},236 l26,0 l7,20 l-40,0 Z" fill="{c}"/>'
        return _dekor(ic, defs)
    if tema == 'kale':
        defs = _grad('dg', ['#93c5fd', '#fbcfe8', '#c4b5fd', '#fde68a'][varyant % 4], '#f8fafc')
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        for _ in range(2): ic += _bulut(r.randint(40, 440), r.randint(30, 80), .8)
        ic += '<rect x="120" y="120" width="240" height="170" fill="#cbd5e1" stroke="#64748b" stroke-width="3"/>'
        for x in range(120, 360, 30): ic += f'<rect x="{x}" y="104" width="18" height="18" fill="#cbd5e1" stroke="#64748b" stroke-width="3"/>'
        for tx in (90, 330):
            ic += f'<rect x="{tx}" y="80" width="60" height="210" fill="#e2e8f0" stroke="#64748b" stroke-width="3"/><path d="M{tx-8},80 L{tx+30},30 L{tx+68},80 Z" fill="{r.choice(["#ef4444","#8b5cf6","#3b82f6","#ec4899"])}" stroke="#64748b" stroke-width="3"/>'
        ic += '<path d="M210,290 L210,220 Q240,190 270,220 L270,290 Z" fill="#92400e" stroke="#451a03" stroke-width="3"/>'
        ic += '<rect x="0" y="290" width="480" height="70" fill="#4ade80"/>'
        return _dekor(ic, defs)
    if tema == 'plaj':
        defs = _grad('dg', '#7dd3fc', '#fef9c3')
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        ic += f'<circle cx="{r.randint(60,420)}" cy="70" r="34" fill="#fde047"/>'
        ic += '<rect x="0" y="180" width="480" height="70" fill="#0ea5e9"/><path d="M0,190 Q30,182 60,190 T120,190 T180,190 T240,190 T300,190 T360,190 T420,190 T480,190" fill="none" stroke="#e0f2fe" stroke-width="3"/>'
        ic += '<path d="M0,240 Q120,226 240,240 T480,236 L480,360 L0,360 Z" fill="#fde68a"/>'
        x = r.randint(60, 420)
        ic += f'<line x1="{x}" y1="300" x2="{x+10}" y2="220" stroke="#78350f" stroke-width="4"/><path d="M{x-40},230 Q{x+10},180 {x+60},230 Z" fill="{r.choice(["#ef4444","#ec4899","#8b5cf6"])}"/>'
        for _ in range(4): ic += f'<path d="M{r.randint(10,470)},{r.randint(290,345)} l6,-10 l6,10 l-12,0" fill="#fb923c"/>'
        return _dekor(ic, defs)
    if tema == 'izgara':
        # Koordinat öğrenme dekoru: eksenler + sayılar
        defs = ''
        ic = f'<rect width="480" height="360" fill="{["#f8fafc","#fefce8","#f0fdf4","#eff6ff"][varyant%4]}"/>'
        for x in range(0, 481, 40): ic += f'<line x1="{x}" y1="0" x2="{x}" y2="360" stroke="#cbd5e1" stroke-width="1"/>'
        for y in range(0, 361, 40): ic += f'<line x1="0" y1="{y}" x2="480" y2="{y}" stroke="#cbd5e1" stroke-width="1"/>'
        ic += '<line x1="0" y1="180" x2="480" y2="180" stroke="#ef4444" stroke-width="2.5"/><line x1="240" y1="0" x2="240" y2="360" stroke="#3b82f6" stroke-width="2.5"/>'
        for x in range(-200, 201, 40):
            if x: ic += f'<text x="{240+x}" y="194" font-size="10" font-family="Arial" font-weight="bold" fill="#b91c1c" text-anchor="middle">{x}</text>'
        for y in range(-160, 161, 40):
            if y: ic += f'<text x="246" y="{180-y+4}" font-size="10" font-family="Arial" font-weight="bold" fill="#1d4ed8">{y}</text>'
        ic += '<text x="468" y="174" font-size="14" font-family="Arial" font-weight="bold" fill="#b91c1c">x</text><text x="226" y="16" font-size="14" font-family="Arial" font-weight="bold" fill="#1d4ed8">y</text>'
        return _dekor(ic, defs)
    if tema == 'parkur':
        # Renk algılama: YEŞİL bitiş, KIRMIZI lav/tehlike, MAVİ su (saf renkler)
        defs = _grad('dg', ['#bae6fd', '#ddd6fe', '#fde68a', '#fbcfe8'][varyant % 4], '#f8fafc')
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        for _ in range(3): ic += _bulut(r.randint(40, 380), r.randint(30, 140), r.uniform(.6, 1))
        ic += '<path d="M0,300 L0,240 Q60,226 120,240 L120,300 Z" fill="#cbd5e1" opacity=".7"/><path d="M300,300 L300,250 Q350,236 400,250 L400,300 Z" fill="#cbd5e1" opacity=".7"/>'
        ic += '<rect x="0" y="300" width="480" height="60" fill="#a8a29e"/><rect x="0" y="300" width="480" height="6" fill="#78716c"/>'
        for x in range(10, 470, 36): ic += f'<rect x="{x}" y="318" width="20" height="8" rx="3" fill="#78716c" opacity=".6"/>'
        ic += '<rect x="420" y="0" width="60" height="360" fill="#16a34a"/>'  # yeşil bitiş
        for y in range(0, 360, 30): ic += f'<rect x="{420+(15 if (y//30)%2 else 0)}" y="{y}" width="15" height="15" fill="#fff" opacity=".35"/>'
        if varyant % 2 == 0:
            ic += '<rect x="180" y="300" width="90" height="60" fill="#dc2626"/><path d="M180,300 q22,-12 45,0 t45,0" fill="#f97316"/>'
        else:
            ic += '<rect x="180" y="300" width="90" height="60" fill="#2563eb"/><path d="M180,304 q22,-8 45,0 t45,0" fill="none" stroke="#93c5fd" stroke-width="3"/>'
        ic += '<text x="450" y="30" font-size="16" font-family="Arial" font-weight="bold" fill="#fff" text-anchor="middle">🏁</text>'
        return _dekor(ic, defs)
    if tema == 'labirent':
        defs = ''
        zemin = ['#fef3c7', '#e0f2fe', '#fce7f3', '#dcfce7'][varyant % 4]
        ic = f'<rect width="480" height="360" fill="{zemin}"/>'
        duvar = '#1d4ed8'  # MAVİ duvar (renk algılama: BLUE)
        ic += f'<rect x="0" y="0" width="480" height="14" fill="{duvar}"/><rect x="0" y="346" width="480" height="14" fill="{duvar}"/>'
        ic += f'<rect x="0" y="0" width="14" height="360" fill="{duvar}"/><rect x="466" y="0" width="14" height="360" fill="{duvar}"/>'
        for x in (120, 240, 360):
            acik = r.choice([60, 150, 240]) if x != 240 else r.choice([90, 200])
            ic += f'<rect x="{x-7}" y="0" width="14" height="{acik}" fill="{duvar}"/><rect x="{x-7}" y="{acik+90}" width="14" height="{360-acik-90}" fill="{duvar}"/>'
        ic += '<rect x="400" y="140" width="60" height="80" rx="10" fill="#16a34a"/>'  # yeşil hedef
        return _dekor(ic, defs)
    if tema == 'sahne':
        # Konser / gösteri sahnesi (ses ve nota bölümleri için)
        defs = _grad('dg', '#1e1b4b', '#701a75')
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        for x in (80, 240, 400):
            ic += f'<path d="M{x},0 L{x-70},300 L{x+70},300 Z" fill="{r.choice(["#fde047","#f472b6","#67e8f9","#a78bfa"])}" opacity=".18"/>'
        ic += '<path d="M0,0 Q60,120 30,300 L0,300 Z M480,0 Q420,120 450,300 L480,300 Z" fill="#be123c"/>'
        ic += '<rect x="0" y="290" width="480" height="70" fill="#78350f"/><rect x="0" y="290" width="480" height="8" fill="#a16207"/>'
        for _ in range(8): ic += f'<text x="{r.randint(20,460)}" y="{r.randint(30,250)}" font-size="{r.randint(16,26)}" fill="#fff" opacity=".35">♪</text>'
        return _dekor(ic, defs)
    if tema == 'oyun_odasi':
        defs = _grad('dg', ['#fde68a', '#bbf7d0', '#bfdbfe', '#fbcfe8'][varyant % 4], '#ffffff')
        ic = '<rect width="480" height="360" fill="url(#dg)"/>'
        ic += '<rect x="0" y="250" width="480" height="110" fill="#d6a76a"/>'
        for x in range(0, 480, 60): ic += f'<line x1="{x}" y1="250" x2="{x-30}" y2="360" stroke="#b08050" stroke-width="2"/>'
        ic += f'<rect x="300" y="60" width="120" height="90" rx="8" fill="#7dd3fc" stroke="#fff" stroke-width="8"/><line x1="360" y1="60" x2="360" y2="150" stroke="#fff" stroke-width="6"/>'
        ic += '<rect x="40" y="70" width="140" height="10" fill="#92400e"/>'
        for i, c in enumerate(['#ef4444', '#3b82f6', '#22c55e', '#f59e0b']):
            ic += f'<rect x="{50+i*30}" y="{40}" width="22" height="30" rx="3" fill="{c}"/>'
        return _dekor(ic, defs)
    raise ValueError(tema)


TEMALAR = ['uzay', 'okyanus', 'orman', 'sehir', 'col', 'seker', 'kar', 'volkan', 'gokyuzu', 'stadyum',
           'laboratuvar', 'kale', 'plaj', 'izgara', 'parkur', 'labirent', 'sahne', 'oyun_odasi']
