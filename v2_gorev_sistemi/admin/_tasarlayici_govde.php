<?php
/**
 * ============================================================================
 * MODÜL: v2 Görev Tasarlayıcı — PAYLAŞILAN GÖVDE (partial)
 * DOSYA YOLU: v2_gorev_sistemi/admin/_tasarlayici_govde.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Görev tasarlama formunun (başlık + bölüm/sıra/puan + hikaye editörü +
 * Blockly cevap anahtarı) tek kopyası.
 *
 * [2026-08] Eskiden bu gövdeyi İKİ sayfa kullanıyordu (tasarlayici.php ve
 * admin/gorev_ekle.php). gorev_ekle.php kaldırıldı; artık tek kullanıcısı
 * tasarlayici.php. Gövde yine ayrı dosyada tutuluyor çünkü form uzun ve
 * sayfanın kendi iskeletinden ayrı okunması kolaylık sağlıyor.
 *
 * ÇAĞIRMADAN ÖNCE TANIMLANMASI GEREKENLER:
 *   $V2_TABAN        — v2_gorev_sistemi/ klasörüne giden yol ('../')
 *   $KOK_ONEK        — proje KÖKÜNE çıkan yol ('../../', varlik() için)
 *   $bolumler        — bolumler tablosundan satırlar
 *   $duzenlenen      — düzenleme modundaysa v2_gorevler satırı, yoksa null
 *   $duzenlenenId    — düzenlenen görevin id'si (yoksa 0)
 *   $onerilenSira    — önerilen sıra numarası
 *   $onSecilenBolum  — açılışta seçili gelecek bölüm id'si
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu; tasarlayıcı gövdesi tek kaynağa indirildi.
 *  - [2026-08] $onSecilenBolum ile bölüm ön seçimi.
 *  - [2026-08] Blockly alanlarına TAM EKRAN düğmesi eklendi.
 * ============================================================================
 */

$V2_TABAN       = $V2_TABAN       ?? '../';
$KOK_ONEK       = $KOK_ONEK       ?? '../../';
$bolumler       = $bolumler       ?? [];
$duzenlenen     = $duzenlenen     ?? null;
$duzenlenenId   = $duzenlenenId   ?? 0;
$onerilenSira   = $onerilenSira   ?? 1;
$onSecilenBolum = $onSecilenBolum ?? 0;
?>

<form id="v2GorevForm">
    <!-- [2026-08] Bölüm / sıra / puan alanları eklendi (Bilinen Sorunlar #17).
         Eskiden bu değerler gorev_api.php içinde 1, 1, 20 olarak SABİT kodlanmıştı;
         hazırlanan her görev 1. bölümün 1. sırasına kaydediliyordu. -->
    <input type="hidden" id="duzenlenenGorevId" value="<?= (int) $duzenlenenId ?>">

    <div class="form-group" style="margin-bottom: 20px;">
        <label style="font-weight: bold; color: #1e293b; display: block; margin-bottom: 8px;">Görev Başlığı:</label>
        <input type="text" id="gorevBasligi" placeholder="Örn: Ripley Uzayda Kayboldu"
               value="<?= $duzenlenen ? temizle($duzenlenen['gorev_adi']) : '' ?>"
               style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 16px;">
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:15px; margin-bottom:25px;">
        <div>
            <label style="font-weight: bold; color: #1e293b; display: block; margin-bottom: 8px;">📚 Bölüm:</label>
            <select id="gorevBolumId" style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:6px; font-size:15px;">
                <?php foreach ($bolumler as $b): ?>
                    <option value="<?= (int) $b['id'] ?>"
                        <?= ((int) $onSecilenBolum === (int) $b['id']) ? 'selected' : '' ?>>
                        <?= (int) $b['sira_no'] ?>. <?= temizle($b['bolum_adi']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="font-weight: bold; color: #1e293b; display: block; margin-bottom: 8px;">🔢 Sıra No:</label>
            <input type="number" id="gorevSiraNo" min="1" value="<?= (int) $onerilenSira ?>"
                   style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:6px; font-size:15px;">
        </div>

        <div>
            <label style="font-weight: bold; color: #1e293b; display: block; margin-bottom: 8px;">🏆 Puan:</label>
            <input type="number" id="gorevPuan" min="1" max="500"
                   value="<?= $duzenlenen ? (int) $duzenlenen['puan'] : 20 ?>"
                   style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:6px; font-size:15px;">
        </div>

        <div>
            <label style="font-weight: bold; color: #1e293b; display: block; margin-bottom: 8px;">🎯 Görev Tipi:</label>
            <select id="gorevTipi" style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:6px; font-size:15px;">
                <option value="standart" <?= ($duzenlenen && $duzenlenen['gorev_tipi'] === 'standart') ? 'selected' : '' ?>>Standart</option>
                <option value="bolum_sonu_canavari" <?= ($duzenlenen && $duzenlenen['gorev_tipi'] === 'bolum_sonu_canavari') ? 'selected' : '' ?>>💀 Bölüm Sonu Canavarı</option>
            </select>
        </div>
    </div>

    <label style="font-weight: bold; color: #1e293b; display: block; margin-bottom: 8px;">1. Bölge: Görev Metni (Hikaye ve Kurallar):</label>
    <p style="color: #64748b; margin-bottom: 10px; font-size: 13px;">Eğik çizgi (/) tuşuna basarak metin içine kısa kod ekleyebilirsiniz.</p>
    <div id="v2-editorjs" class="editor-alani"></div>

    <div style="margin-top: 40px; border-top: 2px dashed #cbd5e1; padding-top: 25px;">
        <label style="font-weight: bold; color: #d97706; display: block; margin-bottom: 8px;">2. Bölge: Ana Çözüm (Master Blueprint):</label>
        <p style="color: #64748b; margin-bottom: 15px; font-size: 13px;">
            Öğrencinin yapmasını beklediğiniz %100 doğru kod dizilimini buraya kurun.
            Sistem öğrencileri bu haritaya göre <b>sunucuda</b> test edecek.
        </p>
        <!-- [2026-08] TAM EKRAN: geniş ekranda rahat kod kurup geri dönebilmek için -->
        <div class="kod-sarmal" id="sarmalAnaCozum">
            <button type="button" class="tam-ekran-btn" onclick="kodAlaniTamEkran('sarmalAnaCozum')"
                    title="Tam ekran aç / kapat">⛶ Tam Ekran</button>
            <div id="blocklyAnaCozum" style="height: 500px; width: 100%; border: 2px solid #d97706; border-radius: 8px;"></div>
        </div>
    </div>

    <div style="margin-top: 40px; border-top: 2px dashed #cbd5e1; padding-top: 25px;">
        <label style="font-weight: bold; color: #10b981; display: block; margin-bottom: 8px;">3. Bölge: Alternatif Çözümler (İsteğe Bağlı):</label>
        <p style="color: #64748b; margin-bottom: 15px; font-size: 13px;">
            Bu görevin birden fazla doğru yapılış şekli varsa (Örn: Döngü yerine alt alta yazmak),
            yeni çözüm alanları ekleyip referansları çoğaltabilirsiniz.
        </p>

        <div id="alternatifCozumlerKonteyneri"></div>

        <button type="button" id="btnAlternatifEkle" style="background: #10b981; color: white; border: none; padding: 10px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 10px;">+ Yeni Alternatif Çözüm Ekle</button>
    </div>

    <button type="button" class="kaydet-btn" id="btnGoreviKaydet" style="margin-top: 30px; width: 100%; font-size: 18px;">
        💾 <?= $duzenlenen ? 'Değişiklikleri Kaydet' : 'Tüm Görevi ve Çözüm Haritalarını Kaydet' ?>
    </button>
</form>

<script>
    // Düzenleme modunda mevcut görevin verisini JS'e aktar
    window.v2DuzenlenenGorev = <?= $duzenlenen
        ? json_encode([
            'id'     => (int) $duzenlenen['id'],
            'icerik' => json_decode($duzenlenen['icerik_json'] ?? '{}', true),
          ], JSON_UNESCAPED_UNICODE)
        : 'null' ?>;

    // [2026-08] API yolu sayfaya göre değişir (tasarlayici.php vs gorev_ekle.php)
    window.V2_API_TABAN = <?= json_encode($V2_TABAN . 'api/') ?>;

    // Görev listesine dönüş bağlantısı
    window.V2_LISTE_YOLU = <?= json_encode($V2_TABAN . 'admin/gorev_listesi.php') ?>;

    /* ========================================================================
     * [2026-08] KOD ALANI TAM EKRAN
     * Blockly tuvali dar bir kutuya sıkışınca uzun kod dizilimlerini kurmak
     * zorlaşıyor. Bu düğme kutuyu ekranın tamamına yayar, tekrar basınca
     * eski hâline döndürür. Blockly tuvalini kendi ölçüsüne göre çizdiği için
     * her iki durumda da svgResize çağırmak ZORUNLU; yoksa bloklar kayar.
     * ====================================================================== */
    window.kodAlaniTamEkran = function (sarmalId) {
        var sarmal = document.getElementById(sarmalId);
        if (!sarmal) return;

        var acildi = sarmal.classList.toggle('tam-ekran');
        document.body.classList.toggle('kod-tam-ekran-acik', acildi);

        var dugme = sarmal.querySelector('.tam-ekran-btn');
        if (dugme) dugme.textContent = acildi ? '✕ Küçült' : '⛶ Tam Ekran';

        // Blockly'nin tuvali yeni boyutu ölçsün
        setTimeout(function () {
            try {
                if (typeof Blockly === 'undefined' || !Blockly.svgResize) return;
                var kutu = sarmal.querySelector('[id^="blockly"]');
                if (!kutu) return;

                // Bu kutuya bağlı çalışma alanını bul
                var ws = (window.blueprintAlanlari && window.blueprintAlanlari[kutu.id])
                      || Blockly.getMainWorkspace();
                if (ws) Blockly.svgResize(ws);
            } catch (e) { /* Blockly hazır değilse sorun değil */ }
            window.dispatchEvent(new Event('resize'));
        }, 60);
    };

    /* ESC ile tam ekrandan çık */
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var acik = document.querySelector('.kod-sarmal.tam-ekran');
        if (acik) window.kodAlaniTamEkran(acik.id);
    });
</script>

<style>
/* ============================================================================
   KOD ALANI TAM EKRAN — [2026-08]
   ========================================================================= */
.kod-sarmal { position: relative; }

.tam-ekran-btn {
    position: absolute; top: 8px; right: 8px; z-index: 20;
    background: rgba(15,23,42,.82); color: #fff; border: none;
    padding: 7px 13px; border-radius: 8px;
    font-size: 12.5px; font-weight: bold; cursor: pointer;
    transition: .15s;
}
.tam-ekran-btn:hover { background: #0f172a; }

.kod-sarmal.tam-ekran {
    position: fixed; inset: 0; z-index: 9500;
    background: #f8fafc; padding: 52px 14px 14px;
    margin: 0; border-radius: 0;
}
.kod-sarmal.tam-ekran > div[id^="blockly"] {
    height: 100% !important;
    border-radius: 10px;
}
.kod-sarmal.tam-ekran .tam-ekran-btn {
    top: 12px; right: 16px; background: #dc2626; padding: 9px 18px; font-size: 14px;
}

/* Tam ekran açıkken arkadaki sayfa kaymasın */
body.kod-tam-ekran-acik { overflow: hidden; }
</style>

<script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/header@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/list@latest"></script>
<script src="https://unpkg.com/blockly/blockly_compressed.js"></script>
<script src="https://unpkg.com/blockly/blocks_compressed.js"></script>
<script src="https://unpkg.com/blockly/msg/tr.js"></script>

<!-- [2026-08] Önbellek kırıcı (?v=dosya-zamanı) eklendi.
     Bu olmadan tarayıcı ESKİ api_yoneticisi.js'i çalıştırıyor ve o sürüm
     bolum_id göndermediği için "Lütfen görevin ekleneceği bölümü seçin"
     hatası alınıyordu — bölüm seçili olsa bile. -->
<script src="<?= varlik('v2_gorev_sistemi/assets/js/admin/mblock_bloklari.js', $KOK_ONEK) ?>"></script>
<script src="<?= varlik('v2_gorev_sistemi/assets/js/admin/metin_editoru.js', $KOK_ONEK) ?>"></script>
<script src="<?= varlik('v2_gorev_sistemi/assets/js/admin/blueprint_motoru.js', $KOK_ONEK) ?>"></script>
<script src="<?= varlik('v2_gorev_sistemi/assets/js/admin/api_yoneticisi.js', $KOK_ONEK) ?>"></script>
