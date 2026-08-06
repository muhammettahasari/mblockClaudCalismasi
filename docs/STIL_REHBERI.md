# Stil Rehberi — mBlock Akademi

Bu proje **Tailwind kullanmıyor**, ama renk paleti Tailwind'in `slate/blue/emerald/amber/red` skalasıyla birebir aynı.
Yeni ekran yaparken **aşağıdaki hex kodlarının dışına çıkma** — tutarlılık buna bağlı.

---

## 1. Renk Paleti (koddaki gerçek kullanım sıklığına göre)

### Nötr / Zemin (slate)
| Hex | Kullanım |
|---|---|
| `#0f172a` | En koyu zemin — arena, market, admin market body |
| `#1e293b` | Kart / panel zemini (koyu tema), başlık metni (açık tema) |
| `#334155` | Koyu tema ikincil zemin, border |
| `#475569` | Gövde metni (açık tema), label |
| `#64748b` | Yardımcı/soluk metin |
| `#94a3b8` | En soluk metin, koyu temada açıklama |
| `#cbd5e1` | Input border, koyu temada metin |
| `#e2e8f0` | Ayırıcı çizgi, açık border |
| `#f1f5f9` | Sayfa arka planı (açık tema) |
| `#f8fafc` | Kart içi açık zemin |
| `#f0f4f8` | `student_style.css` body arka planı |

### Aksiyon Renkleri
| Rol | Ana | Hover/Koyu | Açık/Zemin |
|---|---|---|---|
| **Birincil / Mavi** | `#3b82f6` | `#2563eb` | – |
| **Marka / İndigo** | `#4f46e5` | – | – |
| **Başarı / Yeşil** | `#10b981` | `#059669` | `#d1fae5` (metin `#065f46`) |
| **Uyarı / Turuncu** | `#f59e0b` | `#d97706` | – |
| **Tehlike / Kırmızı** | `#ef4444` | `#dc2626` | `#fee2e2` (metin `#991b1b`) |
| **Vurgu / Sarı** | `#facc15` | – | – |
| **Mor (nadir)** | `#8b5cf6` | – | – |

### Anlamsal Eşleştirme (projede tutarlı kullanılıyor)
- 🔵 `#3b82f6` → bilgi, sekme aktif, birincil buton
- 🟣 `#4f46e5` → öğrenci girişi / PIN alanları / bölüm rozeti
- 🟢 `#10b981` → kayıt ol, onayla, başarı mesajı, "açık" durumu
- 🟠 `#f59e0b` → puan/ödül rozetleri, admin onayı bekleyen, süre uyarısı
- 🔴 `#ef4444` → çıkış, sil, hata mesajı, kilit ekranı
- 🟡 `#facc15` → elmas / altın / şampiyon vurgusu

---

## 2. Tipografi

```css
font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
```
Tek font ailesi. Web font yüklenmiyor. Arena başlıklarında istisna: `'Impact', sans-serif`.

| Bağlam | Boyut | Ağırlık |
|---|---|---|
| Sayfa başlığı (h1/h2) | 24–28px | bold |
| Kart başlığı | 18px | bold |
| Gövde | 14–16px | normal |
| Yardımcı / label | 13px | bold (label'lar kalın) |
| Küçük not | 12px | – |
| Arena efekt yazısı | 60px | bold + `letter-spacing: 5px` |

> Not: Projede `font-weight: bold` inline olarak çok yoğun kullanılıyor. Label'lar neredeyse her zaman kalın.

---

## 3. Şekil & Gölge

```css
border-radius: 6px;    /* input, küçük buton */
border-radius: 8px;    /* buton, rozet, select */
border-radius: 12px;   /* kart, panel, modal */
border-radius: 20px;   /* pill / etiket (tag) */

border: 1px solid #e2e8f0;   /* açık tema kart */
border: 2px solid #cbd5e1;   /* input (odaklanınca renk değişir) */
border: 2px dashed #cbd5e1;  /* bölüm ayırıcı */

box-shadow: 0 4px 6px rgba(0,0,0,0.05);    /* kart */
box-shadow: 0 10px 25px rgba(0,0,0,0.05);  /* modal / login kutusu */
box-shadow: 0 0 0 3px rgba(59,130,246,0.1); /* input focus ring */
```

**Boşluk:** kart içi `padding: 20px` veya `30px`; form grubu `margin-bottom: 15px`–`25px`; flex `gap: 10px`.

---

## 4. Buton Kalıbı

```html
<button style="background:#10b981; color:white; border:none;
               padding:10px 15px; border-radius:8px;
               cursor:pointer; font-weight:bold; transition:0.2s;">
  Kaydet
</button>
```

Hover efekti inline JS ile veriliyor (projede yaygın kalıp):
```html
onmouseover="this.style.background='#059669'; this.style.transform='scale(1.05)'"
onmouseout="this.style.background='#10b981'; this.style.transform='scale(1)'"
```

---

## 5. Emoji Kullanımı (proje kimliği)

Emoji burada dekorasyon değil, **arayüz dili**. Yeni özellik eklerken aynı sözlüğü kullan:

| Emoji | Anlam |
|---|---|
| 👨‍💻 / 👨‍🏫 | Öğrenci / Öğretmen |
| 🏫 / 🎓 | Okul / Sınıf |
| 🔑 | PIN kodu |
| 🏆 | Puan, ödül, lig |
| 💎 | Elmas (para birimi) |
| ❤️ / ⚡ | Can / Enerji |
| ⚔️ / 🛡️ | Saldırı / Kalkan (arena) |
| 🐉 / 🥚 | Ayın Canavarı / yumurta evresi |
| 🔒 / 🚪 | Kilitli / Çıkış |
| ⏳ / ⌛ | Süre, zaman bükücü |
| ✅ / ❌ | Başarı / Hata mesajı öneki |
| 📜 | Savaş geçmişi |
| 🎭 | Kukla (v2) |
| 🚀 / 💾 | Yeni sistem / Kaydet |

---

## 6. Mesaj Kalıbı

Tüm AJAX geri bildirimleri şu formatta:
```js
mesajAlan.style.color = "#10b981";  mesajAlan.innerText = "✅ " + data.message;  // başarı
mesajAlan.style.color = "#ef4444";  mesajAlan.innerText = "❌ " + data.message;  // hata
```
Buton "yükleniyor" durumu: `btn.innerText = "Gönderiliyor..."; btn.disabled = true;` → cevap gelince geri al.

Başarıdan sonra yönlendirme her zaman gecikmeli: `setTimeout(() => location.href = '...', 1500)`.

---

## 7. Kod Yazım Kuralları

### Dosya başlığı — **her yeni dosyada zorunlu**
```php
<?php
/* ====================================================================
 * MODÜL: <Kısa ad>
 * DOSYA YOLU: <tam yol>
 * BAĞLI OLDUĞU ANA DOSYA: <varsa>
 * AÇIKLAMA / NE İŞE YARAR (HALK DİLİNDE): <1-3 cümle, sade Türkçe>
 * TEMEL İŞLEMLER:
 *   1. …
 * YENİ EKLENEN ÖZELLİKLER:
 *   - [Tarih] …
 * ==================================================================== */
```

### İsimlendirme
- Dosya, değişken, fonksiyon, tablo, sütun → **Türkçe, snake_case** (`ogrenci_id`, `dinamikGucHesapla`, `sinifSecimKutusu`)
- JS fonksiyonları → **Türkçe camelCase** (`anaMenuDegistir`, `arenadaSaldir`, `bildirimGoster`)
- Türkçe karakter dosya adında **kullanılmaz** (`ogretmen_paneli.php`, `sifre_sifirla.php`)

### PDO kalıbı
```php
require_once 'db.php';                    // kökte
require_once '../db.php';                 // api/ ve admin/ içinde
require_once '../../db.php';              // v2_gorev_sistemi/api/ içinde
require_once '../../../db.php';           // v2_gorev_sistemi/admin/kukla_kutuphanesi/ içinde

$sorgu = $pdo->prepare("SELECT ... WHERE id = ?");
$sorgu->execute([$id]);
$veri = $sorgu->fetch();                  // FETCH_ASSOC varsayılan
```
**Her zaman prepared statement.** Tablo/sütun adı dinamikse mutlaka **beyaz liste** (`in_array`) ile kontrol et.

### Çok adımlı yazma işlemi → transaction
```php
$pdo->beginTransaction();
try { /* … */ $pdo->commit(); }
catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); }
```

### Çıktı güvenliği
Kullanıcı verisi ekrana basılırken **her zaman** `htmlspecialchars()`.

### Cache busting
Sık değişen JS/CSS'te: `<script src="js/x.js?v=<?= time() ?>"></script>`

---

## 8. Layout Kalıpları

**Öğrenci paneli** (`css/student_style.css`): `display:flex`, sol `.task-panel` **flex:7**, sağ `.sidebar` **flex:3**, `body { height:100vh; overflow:hidden }` — dış scroll kapalı, iç panellerde scroll.

**Admin/öğretmen paneli:** `max-width: 1000px; margin: 20px auto;` ortalanmış beyaz kart.

**Login:** ekran ortasında `max-width: 400px` kutu, üstte sekme (`.tab-btn.active`), formlar `.hidden` class'ı ile değiştirilir.

**Modal:** `position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:999999;` — arena efektlerinde `z-index: 2147483647` kullanılıyor.

---

## 🎨 [2026-08] İKİ AYRI TEMA — bilerek farklı

| Kim | Tema | Dosya | Neden |
|---|---|---|---|
| **Süper Admin** | KOYU (#0f172a) | sayfa içi `<style>` | Sistem sahibinin arka oda ekranı |
| **Öğretmen** | AÇIK | `css/ogretmen_tema.css` | Sınıfta projeksiyona yansıtılıyor; koyu zemin yansıtıldığında okunmuyor |

İkisini birleştirme — ayrım kasıtlı.

### Öğretmen teması nasıl kullanılır

```php
<link rel="stylesheet" href="../css/ogretmen_tema.css">
...
<body class="ot">
<?php $OT_AKTIF = 'panel'; require __DIR__ . '/../bilesenler/ogretmen_ust_bar.php'; ?>
<div class="ot-sarmal"> ... </div>
```

`$OT_AKTIF` değerleri: `panel` | `ogrenciler` | `ilerleme` | `quiz`

**Hazır sınıflar:** `.ot-kart` `.ot-sayac` `.ot-btn` (+`-ana -yesil -mavi -turuncu -kirmizi -sade -kucuk`)
`.ot-girdi` `.ot-tablo` `.ot-rozet` (+`-yesil -turuncu -kirmizi -mavi -mor -gri`)
`.ot-bilgi` `.ot-uyari-kutu` `.ot-basari-kutu` `.ot-hata-kutu` `.ot-perde` `.ot-panel`
`.ot-cubuk` `.ot-canli` `.ot-bos`

### Üst bar ne veriyor

Öğretmenin adı, okulu, **tıklayınca kopyalanan sınıf PIN'i**, kalan üyelik süresi
(tıklayınca süre talebi), çıkış ve dört sayfa arası sekmeler. Onay bekleyen
öğrenci varsa "Öğrenciler" sekmesinde kırmızı sayı rozeti çıkar.

> ⚠️ Üst bar `ogretmenZorunlu()` çağırır ve `$pdo` bekler. `guvenlik.php` zaten
> yüklü olduğu için ayrıca require etmeye gerek yok.
