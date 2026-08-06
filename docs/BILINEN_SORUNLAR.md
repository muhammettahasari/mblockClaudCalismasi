# Bilinen Sorunlar — Durum Tablosu

**Son güncelleme: 2026-08 revizyonu**
Aşağıdaki numaralar ilk analiz raporundaki numaralarla aynıdır.

---

## ✅ ÇÖZÜLENLER

| # | Sorun | Nasıl çözüldü |
|---|---|---|
| **1** | Süper Admin panelleri korumasız | `admin/super_giris.php` + `super_auth.php` + `bilesenler/guvenlik.php`. 6 panel ve 7 admin API'si `superAdminZorunlu()` ile korundu. Şifre `password_hash()`, 5 hatada 10 dk kilit, CSRF token. Panel: `admin/super_panel.php` |
| **3** | `bolum_bitir.php` var olmayan sütuna INSERT | Migration `ogrenci_ilerleme`'ye `bolum_id` + `kaynak` + `tarih` ekledi. Çift seviye-atlama akışı `update_score.php`'de tekleştirildi. |
| **4** | Öğretmen onay şifresi sabit `"123"` | `ogretmenler.onay_sifresi` sütunu eklendi, her öğretmen kendi şifresini kullanıyor (varsayılan `123`). |
| **6** | Quiz oturumu global | `quiz_oturum`'a `ogretmen_id` + `sinif_id`. Öğretmen quiz açarken **sınıf seçmek zorunda**. 6 quiz API'si filtrelendi. |
| **7** | Düello şalteri global | `ogretmenler.duello_bitis`'e taşındı. Meydan okuma **aynı sınıfla** sınırlandı. `duello_ayarlari` kullanım dışı. |
| **8** | API'lerde sahiplik kontrolü yok | Tüm öğrenci API'lerinde kimlik yalnızca oturumdan. `get_task.php` seviye kilidi, `avatar_guncelle.php` envanter kontrolü. |
| **9** | `market.php` yıkıcı temizlik döngüsü | Kaldırıldı. Güvenli sürümü `admin/super_bakim.php`'de — önce RAPOR gösterir, onay ister. |
| **10** | Arena N+1 sorgu | `sinifGucListesi()` sınıfın tamamını **tek sorguda** hesaplıyor. `genel_guc` önbelleği aktif. |
| **11** | Dört ayrı polling | `api/ogrenci_radar.php` + `js/ogrenci_radar.js` — tek istek, abone modeli. Sekme arka plandayken durur. |
| **12** | Stat matrisi 3 kopya | `bilesenler/rpg_matris.php` — tek kaynak. |
| **13** | `js/db.php` gereksiz kopya | Silindi. |
| **14** | Ölü sütunlar | `genel_guc`/`ceviklik` → güç önbelleği. `kalkan_bitis` → yenilgi sonrası 2 saat koruma. `uyelik_bitis_tarihi` → `bitis_tarihi` ile senkron. |
| **15** | API cevap formatı 5 çeşit | `bilesenler/api_yanit.php` üç anahtarı birlikte döndürüyor — eski JS'ler bozulmadan tekleşti. |
| **16** | Foreign key yok | Migration'da `ON DELETE CASCADE` FK'ler + yetim satır temizliği + performans indeksleri. |
| **17** | v2 sistemi yarım | Sunucu tarafı hakem (`v2_hakem.php`), Blockly enjeksiyonu, görev listesi/düzenleme, sabit `bolum_id=1` kaldırıldı, v1 puan ekonomisine bağlandı. |
| **18** | Canavar istekleri | Zafer eşiği %50, dev canavar yumurtaya dönmüyor, iyileşme Türkiye gücüyle orantılı, savaş günü tek sabitte (`CANAVAR_SAVAS_GUNU`). |
| **19** | Repo hijyeni | `.gitignore` + temiz `git init` + ilk commit. Kişisel veri içeren SQL dökümü hariç tutuldu. |

### Analiz sırasında bulunan EK açıklar (raporda yoktu)

| Dosya | Açık | Durum |
|---|---|---|
| `api/update_score.php` | Konsoldan `{"islem":"onayla","ogrenci_id":X}` göndererek **öğretmen onayı olmadan** kendine (veya başkasına) puan+elmas yazdırılabiliyordu | ✅ Onay bileti sistemi (5 dk, tek kullanımlık) |
| `api/quiz_saldiri_sonuc.php` | Hiç oturum kontrolü yoktu; herhangi biri istediği log için istediği puanı yazdırabiliyordu | ✅ Savunan doğrulaması + tek kullanım + puan kırpma |
| `api/duello/zaman_doldu.php` | Oturum kontrolü yoktu; düello numarasını bilen herkes iki öğrencinin puanını düşürebiliyordu | ✅ Katılımcı doğrulaması |
| `api/duello/yanitla.php` | Başkasının meydan okumasını kabul/red edilebiliyordu | ✅ `meydan_okunan_id` doğrulaması |
| `api/duello/cevap.php` | Başkasının düellosunu cevaplayıp puan dağıtabiliyordu | ✅ Taraf doğrulaması + yarış durumu koruması |
| `duello_arena.php` | Adres çubuğuna id yazan herkes başkasının sorusunu görebiliyordu | ✅ Katılımcı kontrolü |
| `api/satin_al.php` | Yarış durumu: hızlı ardışık istekle bakiyeden fazlası harcanabiliyordu | ✅ `FOR UPDATE` kilidi + şartlı UPDATE |
| `api/student_sync.php` | `ogrenci_id` istekten geliyordu → başkasının hesabı kilitlenebiliyordu | ✅ Oturum kimliği |
| `market.php`, `admin/quiz_kontrol_merkezi.php` | Sıralama parametresi SQL'e doğrudan gömülüyordu | ✅ Beyaz liste |
| `quiz_kontrol_merkezi.php`, `duello_radari.php` | Öğrenci adı `innerHTML` ile basılıyordu (XSS) | ✅ `textContent` ile kaçırma |
| `v2/.../hakem_motoru.js` | `hakemiCagir()` ve `ipucuGoster()` butonlarda çağrılıyor ama **hiç tanımlı değildi** | ✅ Yazıldı |
| `v2/ogrenci/oynatici.php` | `<div id="blocklyOgrenci">` vardı ama **Blockly hiç enjekte edilmiyordu** | ✅ Enjeksiyon + araç kutusu eklendi |
| `v2/api/kukla_api.php` | Kukla kütüphanesi tamamen açıktı | ✅ Giriş zorunlu |

---

## 🐞 KULLANIM TESTİNDE ÇIKAN HATALAR (2026-08, ikinci tur)

| # | Belirti | Kök neden | Çözüm |
|---|---|---|---|
| **A** | Öğrenci çıkış yapınca **404** | `ogrenci_paneli.php` `cikis.php`'ye link veriyordu ama **böyle bir dosya hiç yoktu** | `cikis.php` yazıldı. Oturum çerezi de siliniyor (eskiden `session_destroy()` çerezi bırakıyordu). `api/logout.php` de mutlak yola çevrildi. |
| **B** | Düelloya tıklayınca tepki yok<br>`meydanOku is not defined` | Düello kodu `duello_radari.php` içine gömülüydü, sadece ana sayfada tanımlıydı. `oynatici.php` (iframe) skorboardu kopyalayınca **butonlar geliyor, fonksiyonlar gelmiyordu**; buton `with(window.parent)` ile fonksiyonu bulmaya çalışıp patlıyordu. | Kod `js/duello_motoru.js` adlı bağımsız, yol-duyarlı modüle taşındı. Hem ana sayfaya hem iframe'e yükleniyor. `oynatici.php` artık komutu **önce kendi penceresinde**, olmazsa ana pencerede çalıştırıyor. |
| **C** | Quiz başlıyor, davet gidiyor ama **soru gelmiyor** | Soru eklerken zorluk **serbest metin** ("Seç veya Yaz…"), quiz başlatırken ise **4 sabit seçenek** (Kolay/Orta/Zor/Boss) vardı. Admin farklı bir kelime yazdıysa hiçbir soru eşleşmiyordu. | Quiz başlatma ekranı artık zorluk seçeneklerini **veritabanındaki gerçek değerlerden** üretiyor ve her seçeneğin yanında soru sayısını gösteriyor. Ayrıca 0 soru bulunursa quiz **başlatılmıyor**, öğretmene uyarı çıkıyor. |
| **D** | Profilde avatar giydirme alanı **siyah/boş** | `profil.php` içinde `json_encode($matris)` kalmıştı. Stat matrisi ortak dosyaya taşınınca `$matris` yok oldu; PHP 8 "Undefined variable" uyarısını `<script>` bloğunun **içine** basıyor, JavaScript bozuluyor ve sürükle-bırak dahil tüm script ölüyordu. | `RPG_MATRIS` sabitinden besleniyor. Ayrıca tüm projede "tanımsız değişken" taraması yapıldı — başka örnek yok. |
| **E** | — | `js/editor_manager.js` artık hiçbir sayfadan çağrılmıyordu ve çağrılsa `btnBolumEkle` null hatası verecekti | Silindi. |

### Aynı turda yapılan geliştirmeler

- **Öğretmen şifre yönetimi:** Süper Admin → Öğretmen Yönetimi tablosuna
  kullanıcı adı + şifre sütunu eklendi. Şifre yıldızlı görünür, tıklayınca
  açılır, değiştirip çıkınca **otomatik kaydedilir**.
- **`admin/gorev_ekle.php` yeniden yazıldı:** Eski Quill/ekran-görüntülü v1
  formu kaldırıldı; sayfa artık **bölüm yönetimi (ekle/adlandır/sırala/sil)**
  + **gömülü v2 Blockly tasarlayıcı**. Tasarlayıcı gövdesi
  `v2_gorev_sistemi/admin/_tasarlayici_govde.php` içinde tek kaynak olarak
  duruyor, `tasarlayici.php` de aynı dosyayı kullanıyor.
  Mevcut v1 görevleri çalışmaya devam ediyor (`admin/gorev_yonetimi.php`).

---

## ⚠️ BİLİNÇLİ OLARAK BIRAKILANLAR

### #2 — Öğrenci/öğretmen şifreleri düz metin
**Karar:** Şimdilik böyle kalacak (kullanıcı tercihi).
**Sebep:** `api/get_passwords.php` öğretmenin sınıfa şifre listesi bastırmasını sağlıyor;
ortaokul öğrencileri şifrelerini unutuyor. Hash'lenirse bu özellik çalışmaz.
**Sadece Süper Admin şifresi hash'li.**

> Canlıya çıkmadan önce yeniden değerlendirin. Alternatif: öğretmen öğrenciye
> yeni şifre atar ve bir kez görür (hash'li ama pratik).

---

## 🔧 KALAN İŞLER

| Öncelik | İş | Not |
|---|---|---|
| Orta | `api/password_reset.php` içindeki `http://localhost/mblock_egitim` → `mblock_platform` olmalı | Sıfırlama linki 404 veriyor. Ayrıca XAMPP'te `mail()` varsayılan çalışmaz — SMTP gerekir. |
| Orta | Quiz puanı hâlâ istemciden geliyor | Üst sınıra kırpılıyor ama tam sunucu otoritesi için quiz akışının (soru sırası + süre sunucuda) yeniden yazılması gerekir. |
| Düşük | v1 ve v2 paralel çalışıyor | Geçiş tamamlanınca v1 (`gorevler` + ekran görüntüsü) emekliye ayrılabilir. |
| Düşük | `api/api_market_islem.php` içindeki `senkronize_et` dosyaları diskte yeniden adlandırıyor | Güvenli sürümü `super_bakim.php`'de. Eski uç nokta admin korumalı ama kaldırılabilir. |
| Düşük | `admin/gorev_ekle.php` / `gorev_duzenle.php` formlarında CSRF yok | Süper Admin korumalı oldukları için risk düşük. |

---

## ✅ 2026-08 — Görev yönetimi sayfaları birleştirildi

**Sorun:** Aynı işi yapan üç ayrı sayfa vardı ve hangisinin güncel olduğu belirsizdi:

| Eski sayfa | Ne yapıyordu | Akıbeti |
|---|---|---|
| `admin/gorev_ekle.php` | Bölüm yönetimi + gömülü v2 tasarlayıcı | **SİLİNDİ** |
| `admin/gorev_yonetimi.php` | Müfredat listesi (v1 + v2) | **SİLİNDİ** |
| `v2_gorev_sistemi/admin/gorev_listesi.php` | v2 görev listesi | **Tek müfredat ekranı oldu** |

"Kaç görevim var?" sorusu üç yerde üç farklı cevap veriyordu; görev tasarlayıcı
iki ayrı adresten açılabildiği için hangisinin güncel olduğu anlaşılmıyordu.

**Yeni düzen (iki sayfa):**

- `v2_gorev_sistemi/admin/gorev_listesi.php` — **Müfredat ve Görev Yönetimi**
  Bölüm ekle/adlandır/sırala/sil + her bölümün altında v2 ve v1 görevleri,
  cevap anahtarı önizlemesi (🔑), yayın aç/kapa, çözen öğrenci sayısı.
  Bölüm kartları tek/çift farklı tonda (`.ton-a` / `.ton-b`) ki sınırları belli olsun.
- `v2_gorev_sistemi/admin/tasarlayici.php` — **Görev Tasarlayıcı**
  `?id=N` düzenleme, `?bolum=N` ile bölüm ön seçimi.

**Güncellenen bağlantılar:** `admin/super_panel.php` (üç kart → iki kart),
`admin/ogretmen_yonetimi.php`, `admin/gorev_duzenle.php`, `api/task_save.php`,
`api/task_update.php`.

> ⚠️ `admin/gorev_duzenle.php` KORUNDU — eski v1 görevlerini düzenlemenin tek yolu.
> Müfredat sayfasındaki v1 satırlarından bağlanır.

---

## ✅ 2026-08 — Kukla kütüphanesi: yetki ayrımı ve birleştirme

**Sorun 1 — yanlış yere dönüş.** Süper Admin panelden kütüphaneye girip
"Kapat ve Dön" dediğinde `oynatici.php`ye, yani **öğrenci ekranına** düşüyordu.
Sebep: `kapatVeYenile()` fonksiyonunun son çaresi sabit olarak öğrenci
oynatıcısına gidiyordu. Artık Süper Admin paneline dönüyor.

**Sorun 2 — öğrenci kütüphaneyi bozabiliyordu.** Öğrencinin "Kukla Seç"
penceresinde **🗑️ Kukla Sil** ve **➕ Yeni Kukla Tanımla** düğmeleri vardı;
ikisi de Süper Admin sayfalarını iframe içinde açıyordu. Sayfalar
`superAdminZorunlu()` ile korunduğu için gerçek bir sızıntı yoktu ama arayüzün
öğrenciye bu düğmeleri göstermesi baştan yanlıştı. Kaldırıldı; `adminIslemModal`
iframe penceresi de silindi.

### Yeni yetki ayrımı

| | Öğrenci | Süper Admin |
|---|---|---|
| Nerede yaşar | `window.projeKuklalari` (tarayıcı) | `v2_kukla_kutuphanesi` (veritabanı) |
| Kukla ekle | ✅ kütüphaneden seçerek / boş çizerek | ✅ dosya yükleyerek |
| Yeniden adlandır | ✅ sadece kendinde | ✅ kalıcı |
| Sil | ✅ sadece kendi sahnesinden | ✅ kalıcı (dosyalarıyla) |
| Kostüm ekle/sil | ✅ Kostüm Atölyesi'nde çizerek | ✅ dosya yükleyerek |
| Çıkışta | **kaybolur** | kalır |

Öğrenci tarafındaki tek okuma noktası `v2_gorev_sistemi/api/kukla_api.php` —
sadece `SELECT` yapar, yazma yolu yoktur.

### Sayfa birleştirme

| Eski dosya | Akıbeti |
|---|---|
| `kukla_kutuphanesi/kukla_ekle.php` | **SİLİNDİ** — form `index.php` içine alındı |
| `kukla_kutuphanesi/kukla_sil.php` | **SİLİNDİ** — `kukla_islem.php`'ye taşındı |
| `kukla_kutuphanesi/kukla_islem.php` | Tüm yazma işlemlerinin tek motoru oldu |
| `kukla_kutuphanesi/index.php` | Tek yönetim ekranı (liste + ekle + düzenle) |

`kukla_islem.php` işlemleri: `kukla_ekle`, `kostum_ekle` (multipart),
`kukla_guncelle`, `kostum_adlandir`, `kostum_sil`, `kukla_sil` (JSON).

> ⚠️ **Dosya silme güvenliği:** `kostumDosyasiSil()` `realpath()` ile hedefin
> kuklalar klasörünün içinde kaldığını doğrular. Veritabanına elle
> `../../db.php` gibi bir yol yazılmış olsa bile o dosya silinemez.

**Tasarım:** Kütüphane `admin/super_panel.php` paletine geçirildi
(#0f172a zemin, #1e293b kart, #334155 kenarlık) — panelden geçişte göz yormuyor.

---

## ✅ 2026-08 — Süper Admin gezinme ve kukla kütüphanesi ikinci tur

### Gezinme: her sayfada "Ana Panel"

Süper Admin sayfalarının bir kısmında panele dönüş bağlantısı **hiç yoktu**
(`admin_market.php`, `ogretmen_yonetimi.php`, `super_quiz_yonetimi.php`,
`gorev_duzenle.php`). Kullanıcı o sayfalara girince tarayıcının geri tuşuna
ya da adres çubuğuna mahkûmdu.

**Çözüm:** `bilesenler/super_gezinme.php` — sol alta sabitlenen
🏠 Ana Panel + 🚪 Çıkış düğmeleri. Sayfanın `<body>` etiketinden sonra tek satır:

```php
<?php require __DIR__ . '/../bilesenler/super_gezinme.php'; ?>
```

Kök yolu otomatik hesaplanır (`$SUPER_KOK` ile elle de verilebilir).
`super_panel.php`'de kendini gizler.

> Neden sabit (fixed) düğme? Süper Admin sayfalarının her birinin kendi üst bar
> tasarımı var — kimi koyu kimi açık. Her başlığa ayrı ayrı bağlantı eklemek
> yerine layout'tan bağımsız tek bir düğme kullanıldı. Sol altta durur, sayfaların
> sağ üstteki düğme kümeleriyle çakışmaz.

### Kostüm Atölyesi artık Süper Admin'de de var

Atölye arayüzü (386 satır) `oynatici.php` içine gömülüydü, başka sayfada
kullanmak imkânsızdı. `v2_gorev_sistemi/bilesenler/kostum_atolyesi_modal.php`
ortak parçasına alındı.

| | Öğrenci | Süper Admin |
|---|---|---|
| Editör | aynı Paper.js motoru | aynı Paper.js motoru |
| Kayıt | `window.projeKuklalari` (tarayıcı) | `kostum_cizim_kaydet` → PNG dosyası + `cizimData` |
| Kalıcı mı | ❌ çıkışta kaybolur | ✅ kütüphaneye yazılır |

Admin tarafı atölyeyi `window.projeKuklalari` / `window.aktifKuklaId` yapısını
taklit ederek açar; böylece editörün **tek satırı bile** değişmedi.
Üst bara sadece admin sayfasında "💾 Kütüphaneye Kaydet" düğmesi eklenir.

> ⚠️ `index.php` `ogrenci_oynatici.css`'i yükler (atölye stilleri orada).
> O dosya gövdeye `display:flex; height:100vh; overflow:hidden` verir —
> oynatıcının iki panelli düzeni için. Kütüphane sayfası normal akan bir sayfa
> olduğundan bu üç kural kendi `<style>` bloğunda `!important` ile geri alınır.

### Kostüm sıralama ve grup önerisi

- **Sürükle-bırak sıralama:** Kostüm kartları fareyle taşınabilir; 1. sıradaki
  kostüm sahnede varsayılan görünendir. Yeni sıra `kostum_sirala` ile anında
  yazılır. Sunucu tarafı sıralama listesindeki eksik id'leri sona ekler ve
  sayı tutmuyorsa işlemi reddeder — kostüm kaybolmaz.
- **Grup adı:** `<datalist>` yerine kendi öneri listesi yazıldı (datalist'in
  görünümü tarayıcıdan tarayıcıya değişiyor ve koyu temada okunmuyordu).
  Yazdıkça **önce başlayanlar, sonra içerenler** listelenir; tam eşleşme yoksa
  "➕ Yeni grup" satırı çıkar. Altta mevcut gruplar tek tıkla seçilebilen çip
  olarak durur. Türkçe karakterler için `toLocaleLowerCase('tr')` kullanılır.

---

## ✅ 2026-08 — Quiz akışı uçtan uca çalışır hâle getirildi

### Asıl hata: sahiplik zinciri denormalize sütuna dayanıyordu

Öğretmen quiz başlatıyor ama öğrencinin ekranında hiçbir şey olmuyordu.
Sebep, üç ayrı yerde tekrarlanan şu koşuldu:

```sql
JOIN ogrenciler o ON o.sinif_id = qo.sinif_id
                 AND o.ogretmen_id = qo.ogretmen_id   -- ⚠️ kırılgan
```

`ogrenciler.ogretmen_id` kayıt anında yazılan bir **kopya**. Öğrenci başka
sınıfa taşındığında ya da sınıf başka öğretmene devredildiğinde
güncellenmiyor. O anda:

- `api/ogrenci_radar.php` → quiz'i hiç görmüyor, davet gitmiyor
- `quiz_arena.php` → "Bu savaş sizin sınıfınıza ait değil"
- `api/quiz_arena_kontrol.php` → arenaya girse bile 2 saniye sonra atıyor

**Çözüm:** Üçünde de öğretmen artık **sınıftan** türetiliyor
(`ogretmenler → siniflar → ogrenciler`). `ogrenciler.ogretmen_id` yalnızca
sınıfı olmayan eski kayıtlar için `COALESCE` yedeği olarak duruyor.

> ⚠️ Yeni bir quiz/arena sorgusu yazarken `ogrenciler.ogretmen_id` ile
> eşleştirme YAPMA. `siniflar.ogretmen_id` kullan.

### Geri sayım eklendi

`api/quiz_baskin.php` perdeyi açıp **sabit 2.5 saniye** bekleyip yönlendiriyordu;
öğrenci ne olduğunu anlamadan kendini arenada buluyordu. Artık daralan bir halka
ile 3 → 2 → 1 geri sayıyor ve ses eşlik ediyor. (`assets/sounds/ucsaniyegerisayim.mp3`
zaten projede duruyordu ama hiçbir yerde çalınmıyordu.)

Ayrıca **sonsuz döngü** önlendi: öğrenci arenadan panele dönerse quiz hâlâ aktif
olduğu için radar yine "aktif" der ve perde tekrar açılırdı. Gösterilen oturum id'si
`sessionStorage`'da tutuluyor, aynı oturum için perde bir kez açılıyor.

### Ölü dosya silindi

`api/quiz_durum_kontrol.php` — radar birleştirmesinden sonra çağıranı kalmamıştı.
Silindi; durmaya devam etseydi biri ikinci bir yoklayıcı eklemeye kalkışabilirdi.

### Soru havuzu: quiz ve düello ORTAK kullanır

`kurulum/soru_havuzu_2026_08.sql` ile 5 soru eklendi.

| | Nereden çeker | Zorluk filtresi |
|---|---|---|
| Quiz Arena | öğretmenin seçtiği bölüm + zorluk | ✅ var |
| Düello | öğrencinin bölümü ve altı | ❌ yok |

> ⚠️ Düello zorluk ayrımı yapmaz ve "öğrencinin bulunduğu bölüm ve altı" diye
> kısıtlar. Sorular yalnızca üst bölümlere eklenirse yeni öğrenciler düello
> kabul ettiğinde *"Havuzda seviyenize uygun soru bulunamadı"* alır ve düello
> iptal olur. Bu yüzden **her zaman en alt bölümde de soru bulunsun.**

---

## ✅ 2026-08 — Düello adaleti ve savaş sonu skor tablosu

### ⚖️ Düello: "ilk POST kazanır" haksızlığı kaldırıldı

**Sorun:** `api/duello/cevap.php`'ye **ilk ulaşan** kazanıyordu. Ama iki
oyuncunun ekranı aynı anda hazır olmuyor:

- Biri sayfayı 1 saniye geç açar
- Birinin interneti yavaştır, isteği 300 ms geç varır
- Telefon/tarayıcı farkı geri sayımı kaydırır

Sonuç: **daha geç tıklayan biri, sırf ağı hızlı diye kazanabiliyordu.**

**Çözüm — kronometre istemcide, soru göründüğü an başlar:**

1. Geri sayım perdesi kalkıp şıklar tıklanabilir olduğu an
   `soruBaslangicMs = performance.now()` (her oyuncu kendi ekranına göre)
2. Cevapla birlikte `sure_ms` gönderilir
3. Sunucu maçı **anında bitirmez**, cevabı kaydedip rakibi bekler
   (`status: 'bekleniyor'` → bekleme perdesi)
4. İki cevap gelince: **en küçük süreli doğru cevap** kazanır

`Date.now()` değil `performance.now()` kullanıldı — sistem saati değişse bile
geriye gitmez.

**Hile freni:** `sure_ms` istemciden geliyor, bu yüzden sunucuda
`max(150, min(gelen, soru_suresi + 2sn))` ile kırpılıyor. 150 ms insan
refleksinin altı; taban iki oyuncu için de aynı olduğundan belirleyici olamıyor.

**Yan etkiler ve çözümleri:**

| Risk | Çözüm |
|---|---|
| Cevap verdim, rakip tarayıcısını kapattı → sonsuza kadar beklerim | Süre sayacı artık cevap verince DURMUYOR, sadece maç bitince duruyor |
| Süre doldu ama biri doğru bilmişti | `zaman_doldu.php` artık gelen cevaplara bakıyor — bilen KAZANIR (eskiden ikisi de ceza yiyordu) |
| İki istemci aynı anda maçı bitirmeye çalışır | `WHERE durum = 'kabul_edildi'` + `rowCount()` yarış koruması |
| Rakip cevapladı bilgisi kopyaya yol açar mı | `senkron.php` sadece "bastı" der, **hangi şıkkı** söylemez |

> ⚠️ Gerekli migration: `kurulum/migration_2026_08_duello_sure.sql`
> (`okuyan_cevap`, `okuyan_sure_ms`, `okunan_cevap`, `okunan_sure_ms`)

### 🏆 Savaş sonu skor tablosu — öğrenci ve öğretmen aynı tabloyu görür

**Öğrenci** (`quiz_arena.php`, ayrı sayfa YOK):
"SAVAŞ SONA ERDİ" → 3 saniye → sıralama tablosu + kendi sırası → **🏘️ Köye Dön**

**Öğretmen** (`admin/quiz_kontrol_merkezi.php`):
Podyum (ilk 3) + **tam sıralama** + **🏫 Yönetim Paneline Dön**

> ⚠️ Öğretmen ekranındaki **7 saniyelik otomatik yönlendirme kaldırıldı.**
> Öğretmen tabloyu tahtaya yansıtıp sınıfla konuşmak isterken ekran elinden
> alınıyordu. Dönüş artık düğmeye bağlı.

Tek uç nokta: `api/quiz_skor_tablosu.php` — öğretmen kendi oturumunu, öğrenci
kendi sınıfının oturumunu görebilir. İki ayrı dosya yazılsaydı sıralama mantığı
iki yerde durur, biri değişince taraflar farklı tablo görürdü.

---

## ✅ 2026-08 — Abonelik iptali ve "Süreyi Ekle" hatası

### 🐛 "Süreyi Ekle" düğmesi süreyi UZATMIYOR, SIFIRLIYORDU

`api/admin_teacher_action.php` içindeki `uzat` işlemi şunu yapıyordu:

```php
$bitis_tarihi = date('Y-m-d H:i:s', strtotime("+$hesaplanacak_ay months"));
```

Yeni bitiş **NOW()'dan** hesaplanıyor, mevcut bitişe **eklenmiyordu**.
Sonuç: 10 ayı kalan öğretmene 1 ay "eklediğinde" öğretmen **243 gün
kaybediyordu**. Düğmenin adı "Süreyi Ekle" ama yaptığı iş sıfırlamaktı.

**Düzeltme:** `yeni bitiş = (kalan süre varsa mevcut bitiş, yoksa bugün) + ay`
Süresi çoktan dolmuş öğretmene eklerken geçmişten değil bugünden başlanır;
yoksa eklenen ay geçmişte erir.

### Abonelik iptali — veri silmeden

**Senaryo:** Öğretmen 15 ay aldı, vazgeçti, para iadesi istiyor. Kaydı silmek
geri dönüşü olmayan bir kayıp: PIN, sınıflar, öğrencilerin puanları ve çözdüğü
görevler hepsi gider.

Üç ayrı işlem eklendi:

| İşlem | Ne yapar | Veri |
|---|---|---|
| `uzat` (+ Ay) | Mevcut bitişe ay ekler | — |
| `sure_azalt` (− Ay) | Mevcut bitişten ay düşer (kısmi iade) | korunur |
| `aboneligi_bitir` (⏹) | Erişimi hemen keser | **korunur** |
| `sil` | Kaydı ve her şeyi yok eder | **silinir** |

Abonelik kapatılan öğretmen `+ Ay` ile kaldığı yerden geri döner.

> ⚠️ **TARİH KARŞILAŞTIRMASI TUZAĞI**
> `api/teacher_login.php` girişte **sadece TARİHE** bakar — iki tarafa da
> `setTime(0,0,0)` uygular, saate bakmaz. Yani `bitis_tarihi = NOW()` yazmak
> öğretmeni **engellemez**, bugün hâlâ girebilir.
> Bu yüzden iptalde tarih **DÜNE** çekilir (`engelliTarih()`).
> Aynı tuzağa düşmemek için yeni bir "hemen engelle" kodu yazarken bunu hatırla.

**Arayüz:** "⏹ Aboneliği Bitir" turuncu, "Sil" kırmızı — karıştırılmasın diye
farklı renk ve tablonun üstünde ikisinin farkını anlatan bir bilgi kutusu var.

---

## ✅ 2026-08 — "Call to undefined function" ve doğrulama boşluğu

### Hata

Ders kilidi eklendikten sonra öğrenci sayfası açılmıyordu:

```
Fatal error: Call to undefined function ogrencininKilidi()
in bilesenler/ogrenci_kilit_perdesi.php:45
```

**Sebep:** Perde `guvenlik.php`'yi require ediyordu ama `ogrencininKilidi()`
`ilerleme_yardimcilari.php` içinde tanımlı. Doğru dosya yüklenmemişti.

### Asıl mesele: doğrulama yöntemi eksikti

Söz dizimi denetimi (parantez/tırnak dengesi) bu hata sınıfını **yakalayamaz** —
dosya sözdizimsel olarak kusursuz, sorun çalışma anında ortaya çıkıyor.

**Eklenen denetim:** her dosyanın çağırdığı proje fonksiyonlarının, o dosyadan
ulaşılabilen `require` zinciri içinde tanımlı olup olmadığını kontrol eder.
Geçişli zinciri çözer (A → B → C), partial'ları ayrı raporlar.

Bu denetim çalıştırıldığında **ikinci bir sorun** daha çıktı:

### Bonus: kök dizinde bozuk yetim dosya

`superveritabapani.php` — `admin/super_veritabani.php`'nin kopyası, kök dizinde,
adı yanlış yazılmış. İki sorunu vardı:

1. `<?php` etiketinden **önce** çöp metin (`per veritabani · PHP`) — editörden
   kopyalanırken sekme başlığı da yapışmış. Bu metin tarayıcıya basılıyordu.
2. Hiçbir require yok → `superAdminZorunlu()` tanımsız → **Fatal error**, ve
   hata mesajı sunucu klasör yolunu sızdırıyordu.

Hiçbir yerden çağrılmıyordu ama web kökündeydi; adresi tahmin eden herkes
açabilirdi. **Silindi.**

> ⚠️ **KURAL:** Yeni bir partial yazarken, kullandığı fonksiyonların tanımlı
> olduğu dosyayı KENDİSİ require etmeli — "beni include eden sayfa zaten
> yüklüyordur" varsayımı kırılgan. Partial başka bir sayfaya taşındığı anda
> patlar.

### Devamı: CSS'in ekrana metin olarak dökülmesi

Kilit perdesi eklendikten sonra öğrenci ekranının tepesine ham CSS dökülüyordu.

**Sebep:** `oynatici.php`'nin `<body>` etiketi düz değil, içinde PHP var:

```php
<body<?= $cezaDurumu['cezali'] ? ' class="ceza-modu"' : '' ?>>
```

Perdeyi ekleyen betik `<body[^>]*>` deseniyle arıyordu. `[^>]*` ilk `>`
karakterinde durur — ve o `>` PHP bloğunun kapanışındaki (`?>`) `>` idi.
Sonuç: perde satırı **etiketin ortasına** girdi.

```php
<body<?= ... ?>
<?php require '...ogrenci_kilit_perdesi.php'; ?>   ← etiketin İÇİNDE
>
```

Tarayıcı `<body` gördü, öznitelik bekledi, ilk `>` karakterinde etiketi kapattı.
O `>` perdenin `<style>` etiketinin `>`'iydi. Böylece `<style>` yutuldu, içindeki
CSS gövdeye **metin** olarak düştü. `<b>` gibi sonraki etiketler normal
çalışıyordu — kafa karıştıran da buydu.

**Eklenen denetim:** `<body>`, `<html>`, `<head>` etiketlerinin içine gövde
içeriği (`<style>`, `<script>`, `<div>`…) girmiş mi diye tarar. PHP bloklarını
atlayarak etiketin gerçek bitişini bulur.

> ⚠️ **KURAL:** Bir sayfaya `<body>` sonrası içerik eklerken `<body[^>]*>`
> deseniyle arama YAPMA. Etiket içinde `<?= ... ?>` varsa desen yanlış yerde
> biter. Etiketin bitişini PHP bloklarını atlayarak bul.
