<?php
/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/admin/kukla_kutuphanesi/index.php
 * KULLANICI / SAYFA: Süper Admin -> Kukla Kütüphanesi (TEK EKRAN)
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Öğrencilerin sahnede kullanacağı hazır kuklaların (sprite) yönetildiği yer.
 * Kukla ekle, adını/grubunu değiştir, kostüm ekle/sil/adlandır, kuklayı sil.
 *
 * [2026-08] ÜÇ SAYFA BURADA BİRLEŞTİRİLDİ:
 *   kukla_ekle.php  (ayrı ekleme formu) -> SİLİNDİ, form bu sayfaya alındı
 *   kukla_sil.php   (toplu silme)       -> SİLİNDİ, kukla_islem.php'ye taşındı
 *
 * YETKİ AYRIMI — ÖNEMLİ:
 * Kütüphaneye kukla eklemek/silmek SADECE Süper Admin'in işidir; buradaki
 * her şey sunucuya ve veritabanına yazılır, tüm okullar bundan etkilenir.
 * Öğrenci ise kendi sahnesinde kukla ekleyip silebilir ama bunlar SADECE
 * kendi tarayıcısında yaşar (window.projeKuklalari), sunucuya yazılmaz ve
 * çıkış yapınca kaybolur. İki taraf birbirine karışmaz.
 *
 * TASARIM: admin/super_panel.php ile aynı koyu tema (#0f172a / #1e293b).
 * ============================================================================
 */

require_once __DIR__ . '/../../../admin/super_auth.php';
superAdminZorunlu();

$kuklalar = $pdo->query(
    "SELECT id, kukla_adi, grup_adi, kostumler_json FROM v2_kukla_kutuphanesi ORDER BY grup_adi ASC, kukla_adi ASC"
)->fetchAll(PDO::FETCH_ASSOC);

/* Kostümleri çöz + grup listesi çıkar */
$gruplar      = [];
$toplamKostum = 0;

foreach ($kuklalar as &$k) {
    $k['id']        = (int) $k['id'];
    $k['kostumler'] = json_decode($k['kostumler_json'] ?? '[]', true) ?: [];
    $k['grup_adi']  = $k['grup_adi'] ?: 'Genel';

    $toplamKostum += count($k['kostumler']);
    $gruplar[$k['grup_adi']] = true;
}
unset($k);

$gruplar = array_keys($gruplar);
sort($gruplar);

/* Doğrudan açılacak kukla (kostüm ekledikten sonra geri dönüşte) */
$acilacak = isset($_GET['ac']) ? (int) $_GET['ac'] : 0;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Kukla Kütüphanesi | Süper Admin</title>
    <style>
        /* ====================================================================
           super_panel.php ile aynı palet — panelden buraya geçiş göz yormasın
           ================================================================== */
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f172a; color: #f8fafc; margin: 0; padding: 25px 20px 60px;
        }
        .sarmal { max-width: 1150px; margin: 0 auto; }

        .ust { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 22px; }
        .ust h1 { margin: 0; font-size: 24px; }
        .ust .alt { color: #94a3b8; font-size: 13px; margin-top: 4px; }
        .ust-btnler { display: flex; gap: 10px; flex-wrap: wrap; }

        .btn { padding: 9px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: bold; border: none; cursor: pointer; transition: 0.2s; display: inline-block; }
        .btn-gri  { background: #334155; color: #f8fafc; } .btn-gri:hover  { background: #475569; }
        .btn-mavi { background: #3b82f6; color: #fff; }    .btn-mavi:hover { background: #2563eb; }
        .btn-yesil{ background: #10b981; color: #fff; }    .btn-yesil:hover{ background: #059669; }
        .btn-kirmizi { background: #ef4444; color: #fff; } .btn-kirmizi:hover { background: #dc2626; }

        .istatistikler { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 26px; }
        .ist { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 16px; text-align: center; }
        .ist .sayi { font-size: 26px; font-weight: bold; display: block; }
        .ist .etiket { color: #94a3b8; font-size: 12px; margin-top: 4px; display: block; }

        .bilgi { background: #1e3a5f; border: 1px solid #2563eb; color: #bfdbfe; padding: 13px 16px; border-radius: 10px; margin-bottom: 22px; font-size: 13px; line-height: 1.6; }
        .basari { background: #064e3b; border: 1px solid #10b981; color: #a7f3d0; padding: 13px 16px; border-radius: 10px; margin-bottom: 20px; font-weight: bold; font-size: 14px; }
        .hata   { background: #7f1d1d; border: 1px solid #ef4444; color: #fecaca; padding: 13px 16px; border-radius: 10px; margin-bottom: 20px; font-weight: bold; font-size: 14px; }

        .kart { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin-bottom: 22px; }
        .kart h2 { margin: 0 0 6px 0; font-size: 17px; }
        .kart .aciklama { margin: 0 0 16px 0; color: #94a3b8; font-size: 13px; line-height: 1.6; }

        label { display: block; font-weight: bold; font-size: 13px; margin-bottom: 6px; color: #cbd5e1; }
        input[type=text], input[type=file], select {
            width: 100%; padding: 10px 12px; background: #0f172a; color: #f8fafc;
            border: 2px solid #334155; border-radius: 8px; font-size: 14px; outline: none;
        }
        input[type=text]:focus, select:focus { border-color: #3b82f6; }
        input[type=file] { padding: 9px; cursor: pointer; }

        .form-izgara { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 14px; }

        /* Filtre */
        .filtreler { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
        .filtre-btn { background: #0f172a; border: 2px solid #334155; color: #cbd5e1; padding: 7px 15px; border-radius: 20px; cursor: pointer; font-weight: bold; font-size: 13px; transition: 0.2s; }
        .filtre-btn:hover { border-color: #475569; }
        .filtre-btn.aktif { background: #3b82f6; border-color: #3b82f6; color: #fff; }

        /* Kukla ızgarası */
        .izgara { display: grid; grid-template-columns: repeat(auto-fill, minmax(185px, 1fr)); gap: 16px; }
        .kukla { background: #0f172a; border: 1px solid #334155; border-radius: 12px; overflow: hidden; transition: 0.2s; }
        .kukla:hover { border-color: #3b82f6; transform: translateY(-2px); }
        .kukla .resim { height: 150px; background: #1e293b; display: flex; align-items: center; justify-content: center; padding: 14px; position: relative; }
        .kukla .resim img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .kukla .kostum-sayisi { position: absolute; top: 9px; right: 9px; background: #f59e0b; color: #422006; font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: bold; }
        .kukla .bilgi { padding: 12px 13px 14px; }
        .kukla .ad { font-size: 14.5px; font-weight: bold; margin: 0 0 6px; }
        .kukla .grup { font-size: 11px; color: #94a3b8; background: #1e293b; display: inline-block; padding: 3px 9px; border-radius: 10px; }
        .kukla .islemler { display: flex; gap: 6px; margin-top: 11px; }
        .kukla .islemler button { flex: 1; background: #1e293b; border: 1px solid #334155; color: #cbd5e1; padding: 6px; border-radius: 7px; cursor: pointer; font-size: 12px; transition: 0.2s; }
        .kukla .islemler button:hover { background: #334155; }
        .kukla .islemler button.sil:hover { background: #7f1d1d; border-color: #991b1b; color: #fecaca; }

        .bos { text-align: center; padding: 46px; color: #94a3b8; }

        /* ---- Düzenleme penceresi ---- */
        .perde { position: fixed; inset: 0; background: rgba(2,6,23,.78); display: none; align-items: center; justify-content: center; z-index: 900; padding: 20px; }
        .perde.acik { display: flex; }
        .panel { background: #1e293b; border: 1px solid #475569; border-radius: 16px; width: 620px; max-width: 100%; max-height: 88vh; overflow-y: auto; padding: 24px; }
        .panel h2 { margin: 0 0 4px; font-size: 19px; }
        .panel .alt { color: #94a3b8; font-size: 13px; margin-bottom: 18px; }

        .kostum-izgara { display: grid; grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); gap: 11px; margin-top: 10px; }
        .kostum { background: #0f172a; border: 1px solid #334155; border-radius: 9px; padding: 9px; text-align: center; }
        .kostum .kutu { height: 78px; display: flex; align-items: center; justify-content: center; background: #1e293b; border-radius: 6px; margin-bottom: 7px; }
        .kostum .kutu img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .kostum input { font-size: 11.5px; padding: 5px 6px; text-align: center; border-width: 1px; }
        .kostum button { margin-top: 6px; width: 100%; background: #1e293b; border: 1px solid #475569; color: #fca5a5; border-radius: 6px; padding: 4px; cursor: pointer; font-size: 11px; }
        .kostum button:hover { background: #7f1d1d; }

        .ayirac { border: none; border-top: 1px solid #334155; margin: 20px 0; }
        .dugmeler { display: flex; gap: 10px; margin-top: 20px; }
        .dugmeler button { flex: 1; padding: 12px; border-radius: 9px; font-weight: bold; font-size: 14.5px; }
    </style>
</head>
<body>
<div class="sarmal">

    <div class="ust">
        <div>
            <h1>🎭 Kukla Kütüphanesi</h1>
            <div class="alt">Öğrencilerin sahnede kullanacağı hazır kuklalar. Buradaki her değişiklik tüm okulları etkiler.</div>
        </div>
        <div class="ust-btnler">
            <button type="button" class="btn btn-gri" onclick="kapatVeDon()">✖ Kapat ve Dön</button>
        </div>
    </div>

    <div class="istatistikler">
        <div class="ist"><span class="sayi"><?= count($kuklalar) ?></span><span class="etiket">KUKLA</span></div>
        <div class="ist"><span class="sayi"><?= $toplamKostum ?></span><span class="etiket">KOSTÜM</span></div>
        <div class="ist"><span class="sayi"><?= count($gruplar) ?></span><span class="etiket">GRUP</span></div>
    </div>

    <?php if (isset($_GET['durum'])): ?>
        <?php if ($_GET['durum'] === 'eklendi'): ?>
            <div class="basari">✅ "<?= temizle($_GET['ad'] ?? '') ?>" kütüphaneye eklendi.</div>
        <?php elseif ($_GET['durum'] === 'kostum_eklendi'): ?>
            <div class="basari">✅ <?= (int) ($_GET['sayi'] ?? 0) ?> kostüm eklendi.</div>
        <?php elseif ($_GET['durum'] === 'hata'): ?>
            <div class="hata">❌ <?= temizle($_GET['mesaj'] ?? 'İşlem tamamlanamadı.') ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="bilgi">
        ℹ️ <b>Öğrenciler buraya yazamaz.</b> Öğrenci kendi sahnesinde kukla ekleyip silebilir
        ama bunlar sadece kendi tarayıcısında yaşar, sunucuya kaydedilmez ve çıkış yapınca kaybolur.
        Kütüphaneye kalıcı kukla eklemek yalnızca senin yetkinde.
    </div>


    <!-- ==================== YENİ KUKLA ==================== -->
    <div class="kart">
        <h2>➕ Yeni Kukla Ekle</h2>
        <p class="aciklama">
            Bir kuklanın birden fazla görseli olabilir (yürüme, zıplama gibi). Hepsini birden seç;
            sırayla <b>costume1, costume2…</b> diye adlandırılır. PNG, SVG, JPG ve GIF kabul edilir.
        </p>

        <form action="kukla_islem.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="islem" value="kukla_ekle">

            <div class="form-izgara">
                <div>
                    <label>Kukla Adı</label>
                    <input type="text" name="kukla_adi" required placeholder="Örn: Uzaylı Ripley" maxlength="100">
                </div>
                <div>
                    <label>Grup (kategori)</label>
                    <input type="text" name="grup_adi" list="grupListesi" placeholder="Örn: Hayvanlar" maxlength="100">
                    <datalist id="grupListesi">
                        <?php foreach ($gruplar as $g): ?>
                            <option value="<?= temizle($g) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>

            <label>Kostüm Görselleri (birden fazla seçebilirsin)</label>
            <input type="file" name="kukla_resimleri[]" accept=".jpg,.jpeg,.png,.gif,.svg" multiple required>

            <button type="submit" class="btn btn-yesil" style="margin-top:14px; width:100%; padding:12px;">
                💾 Kütüphaneye Ekle
            </button>
        </form>
    </div>


    <!-- ==================== KUKLA LİSTESİ ==================== -->
    <div class="kart">
        <h2>📚 Kütüphanedeki Kuklalar</h2>
        <p class="aciklama">Bir kuklanın üzerine ✏️ ile adını, grubunu ve kostümlerini düzenleyebilirsin.</p>

        <?php if (empty($kuklalar)): ?>
            <div class="bos">Kütüphane boş. Yukarıdan ilk kuklayı ekle.</div>
        <?php else: ?>

        <div class="filtreler">
            <button class="filtre-btn aktif" onclick="filtrele('hepsi', this)">Tümü</button>
            <?php foreach ($gruplar as $g): ?>
                <button class="filtre-btn" onclick="filtrele(<?= htmlspecialchars(json_encode($g), ENT_QUOTES) ?>, this)">
                    <?= temizle($g) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="izgara" id="izgara">
            <?php foreach ($kuklalar as $k):
                $ilk    = $k['kostumler'][0] ?? null;
                $kapak  = $ilk ? ('../../' . ($ilk['yol'] ?? ($ilk['resimYolu'] ?? ''))) : '';
            ?>
            <div class="kukla" data-grup="<?= temizle($k['grup_adi']) ?>" id="kukla-<?= $k['id'] ?>">
                <div class="resim">
                    <span class="kostum-sayisi"><?= count($k['kostumler']) ?> kostüm</span>
                    <?php if ($kapak): ?>
                        <img src="<?= temizle($kapak) ?>" alt="<?= temizle($k['kukla_adi']) ?>"
                             onerror="this.style.display='none'; this.parentNode.insertAdjacentHTML('beforeend','<span style=&quot;color:#64748b;font-size:12px;&quot;>Görsel yok</span>');">
                    <?php else: ?>
                        <span style="color:#64748b;font-size:12px;">Görsel yok</span>
                    <?php endif; ?>
                </div>
                <div class="bilgi">
                    <p class="ad"><?= temizle($k['kukla_adi']) ?></p>
                    <span class="grup"><?= temizle($k['grup_adi']) ?></span>
                    <div class="islemler">
                        <button onclick="duzenle(<?= $k['id'] ?>)">✏️ Düzenle</button>
                        <button class="sil" onclick="kuklaSil(<?= $k['id'] ?>, <?= htmlspecialchars(json_encode($k['kukla_adi']), ENT_QUOTES) ?>)">🗑️</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>


<!-- ==================== DÜZENLEME PENCERESİ ==================== -->
<div class="perde" id="perde">
    <div class="panel">
        <h2 id="panelBaslik">Kuklayı Düzenle</h2>
        <div class="alt">Ad ve grup değişikliği anında kaydedilir.</div>

        <div class="form-izgara">
            <div>
                <label>Kukla Adı</label>
                <input type="text" id="duzKuklaAdi" maxlength="100">
            </div>
            <div>
                <label>Grup</label>
                <input type="text" id="duzGrupAdi" list="grupListesi" maxlength="100">
            </div>
        </div>
        <button class="btn btn-mavi" style="width:100%;" onclick="bilgiKaydet()">💾 Ad ve Grubu Kaydet</button>

        <hr class="ayirac">

        <label>Kostümler</label>
        <p class="aciklama" style="margin-bottom:6px;">
            Kostüm adına tıklayıp değiştirebilirsin; alandan çıkınca kaydedilir.
            <b>Son kostüm silinemez</b> — kuklanın en az bir görseli olmalı.
        </p>
        <div class="kostum-izgara" id="kostumIzgara"></div>

        <hr class="ayirac">

        <form action="kukla_islem.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="islem" value="kostum_ekle">
            <input type="hidden" name="kukla_id" id="kostumKuklaId">
            <label>Yeni Kostüm Ekle</label>
            <input type="file" name="kostum_dosyalari[]" accept=".jpg,.jpeg,.png,.gif,.svg" multiple required>
            <button type="submit" class="btn btn-yesil" style="width:100%; margin-top:10px;">⬆️ Kostümleri Yükle</button>
        </form>

        <div class="dugmeler">
            <button class="btn btn-gri" onclick="paneliKapat()">Kapat</button>
        </div>
    </div>
</div>


<script>
const KUKLALAR = <?= json_encode(array_column($kuklalar, null, 'id'), JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
let aktifKukla = 0;

function kacir(s){ const d=document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

function api(govde) {
    return fetch('kukla_islem.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin', body: JSON.stringify(govde)
    }).then(r => r.json());
}

/* ---------------------------------------------------------------- FİLTRE */
function filtrele(grup, btn) {
    document.querySelectorAll('.filtre-btn').forEach(b => b.classList.remove('aktif'));
    btn.classList.add('aktif');
    document.querySelectorAll('.kukla').forEach(k => {
        k.style.display = (grup === 'hepsi' || k.dataset.grup === grup) ? '' : 'none';
    });
}

/* ------------------------------------------------------------- DÜZENLEME */
function duzenle(id) {
    const k = KUKLALAR[id];
    if (!k) return;

    aktifKukla = id;
    document.getElementById('panelBaslik').textContent = '✏️ ' + k.kukla_adi;
    document.getElementById('duzKuklaAdi').value = k.kukla_adi;
    document.getElementById('duzGrupAdi').value  = k.grup_adi;
    document.getElementById('kostumKuklaId').value = id;

    const kutu = document.getElementById('kostumIzgara');
    kutu.innerHTML = '';

    (k.kostumler || []).forEach(kostum => {
        const yol = '../../' + (kostum.yol || kostum.resimYolu || '');
        const oge = document.createElement('div');
        oge.className = 'kostum';
        oge.innerHTML = `
            <div class="kutu"><img src="${kacir(yol)}" alt=""
                 onerror="this.style.display='none'"></div>
            <input type="text" value="${kacir(kostum.adi || '')}"
                   data-id="${kacir(kostum.id || '')}" maxlength="60"
                   onblur="kostumAdlandir(this)"
                   onkeydown="if(event.key==='Enter'){event.preventDefault(); this.blur();}">
            <button onclick="kostumSil('${kacir(kostum.id || '')}')">🗑️ Sil</button>`;
        kutu.appendChild(oge);
    });

    document.getElementById('perde').classList.add('acik');
}

function paneliKapat() {
    document.getElementById('perde').classList.remove('acik');
    aktifKukla = 0;
}

function bilgiKaydet() {
    const ad   = document.getElementById('duzKuklaAdi').value.trim();
    const grup = document.getElementById('duzGrupAdi').value.trim();
    if (!ad) { alert('Kukla adı boş olamaz.'); return; }

    api({ islem: 'kukla_guncelle', kukla_id: aktifKukla, kukla_adi: ad, grup_adi: grup })
        .then(d => d.success ? location.reload() : alert('❌ ' + (d.mesaj || 'Kaydedilemedi.')))
        .catch(() => alert('❌ Sunucuya ulaşılamadı.'));
}

function kostumAdlandir(kutu) {
    api({ islem: 'kostum_adlandir', kukla_id: aktifKukla, kostum_id: kutu.dataset.id, yeni_ad: kutu.value.trim() })
        .then(d => {
            if (d.success) { kutu.style.borderColor = '#10b981'; setTimeout(() => kutu.style.borderColor = '', 1500); }
            else alert('❌ ' + (d.mesaj || 'Kaydedilemedi.'));
        })
        .catch(() => alert('❌ Sunucuya ulaşılamadı.'));
}

function kostumSil(kostumId) {
    if (!confirm('Bu kostüm ve görsel dosyası kalıcı olarak silinecek.\n\nEmin misin?')) return;

    api({ islem: 'kostum_sil', kukla_id: aktifKukla, kostum_id: kostumId })
        .then(d => d.success ? location.reload() : alert('❌ ' + (d.mesaj || 'Silinemedi.')))
        .catch(() => alert('❌ Sunucuya ulaşılamadı.'));
}

function kuklaSil(id, ad) {
    if (!confirm('"' + ad + '" kuklası ve TÜM kostüm dosyaları kalıcı olarak silinecek.\n' +
                 'Bu kuklayı sahnesinde kullanan öğrencilerde görsel kırılabilir.\n\nEmin misin?')) return;

    api({ islem: 'kukla_sil', kukla_id: id })
        .then(d => {
            if (d.success) { const s = document.getElementById('kukla-' + id); if (s) s.remove(); }
            else alert('❌ ' + (d.mesaj || 'Silinemedi.'));
        })
        .catch(() => alert('❌ Sunucuya ulaşılamadı.'));
}

document.getElementById('perde').addEventListener('click', e => { if (e.target.id === 'perde') paneliKapat(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') paneliKapat(); });


/* ============================================================================
   KAPAT VE DÖN
   [2026-08 HATA DÜZELTMESİ] Burada eski sürümde son çare olarak öğrenci
   oynatıcısına gidiliyordu. Süper Admin panelden geldiğinde kendini öğrenci
   ekranında buluyordu. Artık kim olduğumuza göre doğru yere dönüyoruz.
   ========================================================================= */
function kapatVeDon() {
    // 1. Yeni sekmede açıldıysa sekmeyi kapat
    if (window.opener && !window.opener.closed) {
        window.opener.location.reload();
        window.close();
        return;
    }
    // 2. Bir iframe içindeysek üst pencereyi panele gönder
    if (window.parent && window.parent !== window) {
        try { window.parent.location.href = '../../../admin/super_panel.php'; return; }
        catch (e) { /* farklı kaynak — aşağıdaki normal yönlendirme çalışsın */ }
    }
    // 3. Normal durum: Süper Admin paneline dön
    window.location.href = '../../../admin/super_panel.php';
}

/* Kostüm yükledikten sonra aynı kuklanın penceresi tekrar açılsın */
<?php if ($acilacak > 0): ?>
window.addEventListener('DOMContentLoaded', function () { duzenle(<?= $acilacak ?>); });
<?php endif; ?>
</script>
</body>
</html>
