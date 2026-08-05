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
