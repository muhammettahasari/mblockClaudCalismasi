/**
 * ============================================================================
 * DOSYA YOLU: v2_gorev_sistemi/assets/js/admin/api_yoneticisi.js
 * KULLANICI / SAYFA: Süper Admin -> Görev Tasarlayıcı
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Bu dosya projenin kargo şirketidir. Admin "Kaydet" dediğinde devreye girer.
 * Üstteki metinleri, alttaki ana çözümü, alternatif çözümleri ve görevin
 * ayarlarını (bölüm, sıra, puan, tip) tek bir JSON kutusuna koyup gönderir.
 *
 * TEMEL İŞLEMLER:
 *  1. "btnGoreviKaydet" butonunu dinler
 *  2. Editor.js ve Blueprint verilerini çeker
 *  3. Yeni kayıt mı güncelleme mi olduğuna karar verir
 *  4. api/gorev_api.php'ye POST eder
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [Önceki] Veri paketleme sistemi Blueprint mimarisine göre güncellendi.
 *  - [2026-08] Bölüm / sıra / puan / tip alanları gönderiliyor (#17).
 *      Bu değerler eskiden sunucuda 1, 1, 20 olarak sabit kodlanmıştı.
 *  - [2026-08] Düzenleme modu: ?id=12 ile açıldığında güncelleme yapar.
 *  - [2026-08] Ana çözüm boşsa uyarı verir (cevap anahtarı olmadan görev
 *      öğrenci tarafında kontrol edilemez).
 * ============================================================================
 */

document.getElementById('btnGoreviKaydet').addEventListener('click', function () {
    const btn = this;

    const baslik   = (document.getElementById('gorevBasligi').value || '').trim();
    const bolumEl  = document.getElementById('gorevBolumId');
    const bolum_id = bolumEl ? parseInt(bolumEl.value, 10) : 0;
    const sira_no  = parseInt(document.getElementById('gorevSiraNo').value, 10) || 0;
    const puan     = parseInt(document.getElementById('gorevPuan').value, 10) || 20;
    const tip      = document.getElementById('gorevTipi').value || 'standart';
    const gorev_id = parseInt(document.getElementById('duzenlenenGorevId').value, 10) || 0;

    if (!baslik) {
        alert("⚠️ Lütfen göreve bir başlık verin!");
        return;
    }
    if (!bolum_id) {
        alert("⚠️ Lütfen görevin ekleneceği bölümü seçin!");
        return;
    }

    btn.disabled = true;
    const eskiYazi = btn.innerHTML;
    btn.innerHTML = '⏳ Kaydediliyor...';

    const hataylaBitir = (mesaj) => {
        btn.disabled = false;
        btn.innerHTML = eskiYazi;
        alert(mesaj);
    };

    // 1. Metin editöründen hikayeyi al
    window.v2Editor.save().then((editorVerisi) => {

        // 2. Blueprint motorundan blok haritalarını al
        const blueprintVerisi   = window.getBlueprintData();
        const kullanilanBloklar = window.getKullanilanBlokTipleri();

        // Cevap anahtarı yoksa öğrenci tarafında kontrol yapılamaz
        if (!blueprintVerisi || !blueprintVerisi.ana_cozum || blueprintVerisi.ana_cozum.trim() === '') {
            hataylaBitir("⚠️ Ana Çözüm (Master Blueprint) boş!\n\nÖğrencinin cevabı buna göre kontrol edilecek. Lütfen 2. Bölge'ye doğru kod dizilimini kurun.");
            return;
        }

        /* [2026-08] VERİ KAYBI FRENİ
           Düzenleme modunda kayıtlı hikâye yüklenemezse (bozuk JSON, eski
           sürüm vb.) editör boş görünür; öyle kaydedilirse görevin metni
           silinir. Kayıtta metin VARDI ama şimdi BOŞSA kullanıcıya soralım. */
        const yeniBlokSayisi = (editorVerisi && Array.isArray(editorVerisi.blocks)) ? editorVerisi.blocks.length : 0;
        const kayitli = window.v2DuzenlenenGorev;
        const eskiBlokSayisi = (kayitli && kayitli.icerik && kayitli.icerik.hikaye_metni
                                && Array.isArray(kayitli.icerik.hikaye_metni.blocks))
                               ? kayitli.icerik.hikaye_metni.blocks.length : 0;

        if (gorev_id > 0 && eskiBlokSayisi > 0 && yeniBlokSayisi === 0) {
            if (!confirm("⚠️ DİKKAT: Bu görevin kayıtlı metni vardı ama şu an editör BOŞ.\n\n"
                       + "Kaydedersen görev metni SİLİNİR.\n\nYine de devam edilsin mi?")) {
                btn.disabled = false;
                btn.innerHTML = eskiYazi;
                return;
            }
        }

        const superPaket = {
            hikaye_metni: editorVerisi,
            blueprint: blueprintVerisi,
            izin_verilen_bloklar: kullanilanBloklar
        };

        const govde = {
            islem:      gorev_id > 0 ? 'gorev_guncelle' : 'yeni_gorev_kaydet',
            gorev_id:   gorev_id,
            baslik:     baslik,
            bolum_id:   bolum_id,
            sira_no:    sira_no,
            puan:       puan,
            gorev_tipi: tip,
            icerik:     superPaket
        };

        // [2026-08] API yolu sayfaya göre değişiyor:
        //   v2_gorev_sistemi/admin/tasarlayici.php -> ../api/
        //   admin/gorev_ekle.php                   -> ../v2_gorev_sistemi/api/
        const apiTaban = window.V2_API_TABAN || '../api/';

        fetch(apiTaban + 'gorev_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(govde)
        })
        .then(cevap => cevap.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = eskiYazi;

            if (data.success === true || data.status === 'success') {
                alert("✅ MUHTEŞEM! " + (data.mesaj || data.message || "Görev kaydedildi."));
                const liste = window.V2_LISTE_YOLU || 'gorev_listesi.php';
                window.location.href = liste + '?bolum=' + bolum_id;
            } else {
                alert("❌ Hata: " + (data.mesaj || data.message || "Sunucudan hata detayı gelmedi."));
            }
        })
        .catch(err => {
            console.error('Kargo Hatası:', err);
            hataylaBitir("❌ Sunucuya bağlanırken bir hata oluştu.");
        });

    }).catch((error) => {
        console.error('Editor.js Veri Çekme Hatası:', error);
        hataylaBitir("⚠️ Görev metni alınırken bir hata oluştu.");
    });
});
