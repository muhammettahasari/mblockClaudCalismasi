/* ==========================================================
   GÜNCELLENMİŞ KOD: js/editor_manager.js
   İşlev: 4 Farklı Zengin Metin Editörünü ve Bölüm Eklemeyi Yönetir
   ========================================================== */

// 1. YENİ BÖLÜM EKLEME AJAX İŞLEMİ
document.getElementById('btnBolumEkle').addEventListener('click', function() {
    const yeniBolumAdi = document.getElementById('yeniBolumAdi').value.trim();
    if (yeniBolumAdi === '') return;

    fetch('../api/bolum_ekle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ bolum_adi: yeniBolumAdi })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('bolumSelect');
            const option = document.createElement('option');
            option.value = data.id;
            option.text = data.adi;
            select.add(option);
            select.value = data.id; // Yeni ekleneni otomatik seç
            
            document.getElementById('yeniBolumAdi').value = '';
            document.getElementById('bolumMesaj').style.display = 'block';
            setTimeout(() => { document.getElementById('bolumMesaj').style.display = 'none'; }, 3000);
        } else {
            alert('Bölüm eklenirken bir hata oluştu.');
        }
    });
});

// 2. EDİTÖR (QUILL) AYARLARI
const toolbarOptions = [
    [{ 'header': [1, 2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
    ['image', 'link', 'video'], 
    ['clean']
];

// Ortak Resim/GIF Seçici
// Ortak Resim/GIF Seçici (GÜVENLİK SINIRLAMASI EKLENDİ)
function imageHandler() {
    const activeQuill = this.quill; 
    
    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/*');
    input.click();

    input.onchange = () => {
        const file = input.files[0];
        if (file) {
            // YENİ: Maksimum Dosya Boyutu Sınırı (1.5 MB = 1.5 * 1024 * 1024 bytes)
            const maxBoyut = 1.5 * 1024 * 1024; 
            
            if (file.size > maxBoyut) {
                alert("⚠️ HATA: Seçtiğiniz dosya çok büyük (" + (file.size / (1024 * 1024)).toFixed(2) + " MB)! Sistemin kilitlenmemesi için lütfen maksimum 1.5 MB boyutunda resim veya GIF yükleyin. Gerekirse görsellerinizi internette 'GIF Compressor' yazarak küçültebilirsiniz.");
                return; // Resim büyükse işlemi burada durdur
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                const base64Image = e.target.result;
                const range = activeQuill.getSelection(true) || { index: activeQuill.getLength() };
                activeQuill.insertEmbed(range.index, 'image', base64Image);
                activeQuill.setSelection(range.index + 1);
            };
            reader.readAsDataURL(file);
        }
    };
}

// YENİ: Her editörün birbirine karışmasını engelleyen "Bağımsız Ayar Üretici"
function getEditorConfig() {
    return {
        theme: 'snow',
        modules: { 
            toolbar: { 
                container: toolbarOptions, 
                handlers: { image: imageHandler } 
            } 
        }
    };
}

// 4 Ayrı Editörü Başlat (Artık 4'ü de birbirinden tamamen bağımsız ve sorunsuz!)
const editorNasil = new Quill('#editor-nasil', getEditorConfig());
const editorGorev = new Quill('#editor-gorev', getEditorConfig());
const editorEkran = new Quill('#editor-ekran', getEditorConfig());
const editorIpucu = new Quill('#editor-ipucu', getEditorConfig());

// 3. FORM GÖNDERİLİRKEN VERİLERİ GİZLİ INPUTLARA AKTAR
document.getElementById('gorevForm').addEventListener('submit', function(e) {
    document.getElementById('gizli_nasil').value = editorNasil.root.innerHTML;
    document.getElementById('gizli_gorev').value = editorGorev.root.innerHTML;
    document.getElementById('gizli_ekran').value = editorEkran.root.innerHTML;
    document.getElementById('gizli_ipucu').value = editorIpucu.root.innerHTML;
});
// 4. YENİ: KOPYALA-YAPIŞTIR VE SÜRÜKLE-BIRAK (Dosya ve GIF Desteği)
function setupPasteHandler(quillInstance) {
    quillInstance.root.addEventListener('paste', function(e) {
        if (e.clipboardData && e.clipboardData.files && e.clipboardData.files.length) {
            e.preventDefault(); // Varsayılan yapıştırmayı durdur (Bozulmasını engelle)
            
            Array.from(e.clipboardData.files).forEach(file => {
                if (file.type.match(/^image\//)) {
                    const maxBoyut = 1.5 * 1024 * 1024; 
                    
                    if (file.size > maxBoyut) {
                        alert("⚠️ HATA: Kopyaladığınız dosya çok büyük (" + (file.size / (1024 * 1024)).toFixed(2) + " MB)! Sistemin kilitlenmemesi için lütfen maksimum 1.5 MB boyutunda görsel yapıştırın.");
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = (event) => {
                        const base64Image = event.target.result;
                        const range = quillInstance.getSelection(true) || { index: quillInstance.getLength() };
                        quillInstance.insertEmbed(range.index, 'image', base64Image);
                        quillInstance.setSelection(range.index + 1);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    });
}

// 4 Editöre de Kopyala-Yapıştır özelliğini tanımlıyoruz
setupPasteHandler(editorNasil);
setupPasteHandler(editorGorev);
setupPasteHandler(editorEkran);
setupPasteHandler(editorIpucu);