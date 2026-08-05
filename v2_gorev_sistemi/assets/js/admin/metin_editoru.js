/**
 * ============================================================================
 * Dosya Yolu: v2_gorev_sistemi/assets/js/admin/metin_editoru.js
 * * Kullanıcı Yetkisi ve Bağlı Olduğu Sayfa: Süper Admin -> Görev Tasarlayıcı (tasarlayici.php)
 * * Halk Dilinde İşlevi: Bu dosya, adminin sayfanın en üstündeki görev hikayesini 
 * yazdığı ve '/' tuşuna basarak hızlıca kukla/kod etiketleri eklediği metin 
 * editörünü çalıştırır.
 * * Temel İşlevler:
 * - Editor.js çekirdeğini başlatır.
 * - '/' tuşuna basıldığında açılan kısa kod menüsünü (Slash Menu) oluşturur.
 * - Seçilen kısa kodu (Örn: [kukla:ripley]) imlecin bulunduğu yere yapıştırır.
 * * YENİ EKLENEN ÖZELLİKLER:
 * * ============================================================================
 */

// 1. mBlock Kısa Kod Sözlüğümüz
const mblockSozlugu = [
    { baslik: 'Kukla: Ripley', kod: '[kukla:ripley]', ikon: '👩‍🚀' },
    { baslik: 'Kukla: Robot', kod: '[kukla:robot]', ikon: '🤖' },
    { baslik: 'Döngü: Sürekli Tekrarla', kod: '[dongu:surekli_tekrarla]', ikon: '🔄' },
    { baslik: 'Blok: 10 Adım Git', kod: '[blok:10_adim_git]', ikon: '👣' }
];

// 2. Editor.js'i Başlat
// Diğer dosyalardan (api_yoneticisi.js) erişilebilmesi için window objesine ekliyoruz.
window.v2Editor = new EditorJS({ 
    holder: 'v2-editorjs', 
    placeholder: 'Görev yazın veya mBlock kısa kodları eklemek için / tuşuna basın...' 
});

// 3. Eğik Çizgi (/) Menüsü Arayüzünü Oluştur
const slashMenu = document.createElement('div');
slashMenu.id = 'slash-menu';
slashMenu.style.display = 'none'; 
slashMenu.style.position = 'absolute'; 
slashMenu.style.background = '#ffffff';
slashMenu.style.border = '1px solid #cbd5e1'; 
slashMenu.style.borderRadius = '8px'; 
slashMenu.style.width = '300px'; 
slashMenu.style.zIndex = '99999';
slashMenu.style.boxShadow = '0 10px 25px rgba(0,0,0,0.15)';
document.body.appendChild(slashMenu);

mblockSozlugu.forEach(oge => {
    let div = document.createElement('div');
    div.innerHTML = `<span style="font-size:18px; margin-right:10px;">${oge.ikon}</span> <strong>${oge.baslik}</strong>`;
    div.style.padding = '10px 15px'; 
    div.style.cursor = 'pointer'; 
    div.style.borderBottom = '1px solid #f1f5f9';
    
    div.onmouseover = function() { this.style.backgroundColor = '#f8fafc'; }
    div.onmouseout = function() { this.style.backgroundColor = 'transparent'; }
    div.onmousedown = function(e) { e.preventDefault(); }; // Odak kaybını önle
    
    div.onclick = function() {
        document.execCommand('delete', false, null); // Baştaki '/' işaretini sil
        document.execCommand('insertText', false, oge.kod + ' '); // Kodu yapıştır
        slashMenu.style.display = 'none';
    };
    slashMenu.appendChild(div);
});

// 4. Tuş Dinleyicileri (Menüyü Aç / Kapat)
document.addEventListener('keyup', function(e) {
    if (!e.target.closest('#v2-editorjs')) return;
    
    if (e.key === '/' || e.code === 'NumpadDivide') {
        const secim = window.getSelection();
        if (secim.rangeCount > 0) {
            let rect = secim.getRangeAt(0).getBoundingClientRect();
            if (rect.width === 0 && rect.height === 0) rect = e.target.getBoundingClientRect();
            slashMenu.style.left = (rect.left + window.scrollX) + 'px';
            slashMenu.style.top = (rect.bottom + window.scrollY + 10) + 'px';
            slashMenu.style.display = 'block'; 
        }
    } else if (e.key === 'Escape' || e.key === ' ' || e.key === 'Backspace') {
        slashMenu.style.display = 'none'; 
    }
});

document.addEventListener('click', function(e) {
    if (!slashMenu.contains(e.target) && !e.target.closest('#v2-editorjs')) {
        slashMenu.style.display = 'none';
    }
});