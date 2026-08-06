# CLAUDE.md — mBlock Akademi Platformu

> Bu dosya Claude'un projeyi her seferinde baştan taramadan hatırlaması için hazırlandı.
> **Önce burayı oku, sonra sadece ihtiyacın olan dosyayı aç.**
> Detaylar: `docs/VERITABANI.md`, `docs/API_REFERANS.md`, `docs/STIL_REHBERI.md`, `docs/BILINEN_SORUNLAR.md`

---

## 1. Proje Nedir?

Ortaokul öğrencilerine **mBlock / blok tabanlı kodlama** öğreten, oyunlaştırılmış (RPG) bir eğitim platformu.
Öğrenci görev çözer → puan kazanır → elmasla market'ten eşya alır → avatarını güçlendirir → arenada savaşır.

- **Dil:** PHP 8.2 (framework yok, saf PDO) + Vanilla JS + MySQL/MariaDB
- **Sunucu:** XAMPP / localhost (`C:\xampp\htdocs\mblock_platform`)
- **Veritabanı:** `mblock_egitim` (root / şifresiz)
- **Arayüz dili:** Türkçe (değişken, tablo, fonksiyon adları da Türkçe)
- **Git:** `https://github.com/muhammettahasari/mblock_platform`
- **Geliştirici:** Muhammet Taha Sarı

---

> **⚠️ 2026-08 BÜYÜK REVİZYON YAPILDI.** Veritabanı değişti.
> Yeni kuruluma başlamadan önce `kurulum/KURULUM.md` oku ve
> `kurulum/migration_2026_08_guvenlik.sql` dosyasını çalıştır.

## 2. Kullanıcı Rolleri (3 Katman)

| Rol | Giriş | Panel |
|---|---|---|
| **Süper Admin** (patron) | `admin/super_giris.php` — **varsayılan `super` / `123`** | `admin/super_panel.php` (hub), `super_bakim.php`, `ogretmen_yonetimi.php`, `gorev_*.php`, `admin_market.php`, `super_quiz_yonetimi.php` |
| **Öğretmen** | `index.php` → kullanıcı adı + şifre | `admin/ogretmen_paneli.php`, `admin/ogrenci_yonetimi.php`, `admin/quiz_kontrol_merkezi.php` |
| **Öğrenci** | `index.php` → **PIN + sınıf + kullanıcı adı + şifre** | `ogrenci_paneli.php`, `profil.php`, `market.php`, `quiz_arena.php`, `duello_arena.php` |

**Kayıt akışı:**
Öğretmen kayıt olur → Süper Admin onaylar + **4 haneli PIN** üretir + üyelik süresi verir → Öğrenci o PIN ile sınıfını bulup kayıt olur → Öğretmen öğrenciyi onaylar (`onay_durumu=1`).

**Oturum anahtarları:** `$_SESSION['ogrenci_id']`, `$_SESSION['ogretmen_id']`, `$_SESSION['super_admin_id']`, `ogretmen_pin`, `okul_adi`, `sinif_id`, `hata_sayaci`, `bolum_onay_bileti`

---

## 2b. ⭐ ORTAK ÇEKİRDEK DOSYALARI (yeni kod yazarken bunları kullan)

Bu dört dosya 2026-08 revizyonunda eklendi. **Yeni PHP dosyası yazarken
`db.php`'yi doğrudan require etme, bunları kullan.**

| Dosya | Ne verir |
|---|---|
| `bilesenler/guvenlik.php` | `guvenliOturumBaslat()`, `superAdminZorunlu()`, `ogretmenZorunlu()`, `ogrenciZorunlu()`, `*ZorunluApi()`, `csrfAlani()`, `csrfDogrula()`, `csrfDogrulaSayfa()`, `temizle()`, `istekVerisi()`, `beyazListe()` |
| `bilesenler/api_yanit.php` | `apiBasari()`, `apiHata()`, `apiJson()`, `apiIstisna()` — üç eski format anahtarını (`success`/`status`/`durum`) birlikte döndürür, eski JS'ler bozulmaz |
| `bilesenler/rpg_matris.php` | `RPG_MATRIS`, `AVATAR_YUVALARI`, `statlariHesapla()`, `ogrencininGucu()`, `sinifGucListesi()` (tek sorgu), `gucOnbelleginiTemizle()`, `orduGucuOzeti()` |
| `bilesenler/canavar_ayar.php` | `CANAVAR_SAVAS_GUNU` (18), `CANAVAR_ZAFER_ESIGI` (%50), `canavaraKalanGun()`, `canavarBuyumeYuzdesi()` |
| `bilesenler/ilerleme_yardimcilari.php` | `bolumleriGetir()`, `ogrencininIlerlemesi()`, `ilerlemeHaritasi()`, `erisilebilirBolumler()`, `sonrakiGoreviBul()`, `sonrakiBolumuHesapla()`, `mevcutBolumuTazele()`, `ogrencininCezasi()`, `ogretmeninOgrencisiMi()`, `ogrencininKilidi()`, `kilitliyseReddet()` — **"öğrenci nerede?" sorusunun tek cevap mercii** |

> 🔒 **[2026-08] DERS KİLİDİ.** Öğretmen sınıfın ya da tek öğrencinin ekranını
> dondurabilir (`api/ogrenci_kilit.php`). Kilit veritabanında durur; sayfa
> yenilense, öğrenci çıkıp girse bile kalkmaz.
> **Yeni bir "öğrenci yazma" uç noktası yazarken `kilitliyseReddet($pdo, $id)`
> çağır** — perde sadece görsel, konsoldan silinebilir.
> ⚠️ `ogretmen_kilidi` ile `durum='kilitli'` AYRI ŞEY: ilki ders dondurma,
> ikincisi şifreyi 3 kez yanlış girme cezası. Biri diğerini ezmez.
> Onay + not sorma işi `bilesenler/kilit_modali.php` penceresinde
> (`kilitModaliAc({islem, hedefId, hedefAd, onTamam})`); hazır notlar oradaki
> `HAZIR` dizisinde, öğretmenin kendi kaydettikleri `kilit_mesajlari` tablosunda.

> 🚫 **[2026-08] `confirm()` / `prompt()` KULLANMA.** Onay ya da kısa metin
> gerektiren yerlerde tarayıcı kutusu değil, sayfanın kendi penceresi
> kullanılır. Gerekçe: o kutular biçimlendirilemez (sınıfa yansıtılan ekranda
> "localhost web sitesinin mesajı" yazar), üst üste gelince hangisinin ne
> sorduğu karışır ve tarayıcının "bu sayfa daha fazla iletişim kutusu
> göstermesin" seçeneği işaretlenirse `prompt()` **sessizce `null` döner** —
> yani soru hiç sorulmadan kod "iptal edildi" sanır.
> Hazır desen: `bilesenler/kilit_modali.php`.

**Standart API dosyası şablonu:**
```php
require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();   // kimlik SADECE oturumdan, asla POST'tan!
$veri = istekVerisi();               // JSON / POST / GET fark etmez

try {
    // ... iş mantığı ...
    apiBasari(['anahtar' => $deger], 'Mesaj');
} catch (Throwable $e) {
    apiIstisna($e, 'Kullanıcıya gösterilecek mesaj.');
}
```

---

## 3. Klasör Haritası

```
mblock_platform/
├── db.php                  # PDO bağlantısı — HER dosya bunu require eder
├── index.php               # Giriş/kayıt (4 form: öğr.giriş, öğr.kayıt, öğrtmn.giriş, öğrtmn.kayıt)
├── ogrenci_paneli.php      # Öğrenci ana ekranı (görev + skorboard)
├── profil.php              # Avatar giydirme + stat hesaplama (760 satır)
├── market.php              # Elmasla eşya satın alma
├── quiz_arena.php          # Çoklu quiz savaşı (829 satır)
├── duello_arena.php        # 1v1 düello
├── sifre_sifirla.php       # Token ile şifre sıfırlama
│
├── api/                    # ~45 endpoint, çoğu JSON döner (bkz docs/API_REFERANS.md)
│   └── duello/             # Düello alt API'leri (istek_at, yanitla, cevap, dinle, senkron, zaman_doldu)
├── admin/                  # Süper Admin + Öğretmen panelleri
├── bilesenler/             # PHP partial'ları (include edilen parçalar)
│   └── skorboard_modulleri/  # profil_ve_puanlar, bireysel_lig, ayin_canavari, sinif_arenasi, savas_gecmisi
├── js/                     # progress_manager, avatar_manager, skorboard_motoru, editor_manager
├── css/                    # student_style, admin_style, teacher_panel, main_login
├── img/esyalar/<kategori>/ # Avatar eşya PNG'leri (dosya adı = "<seviye>_seviye_<ad>_<id>.png")
│
└── v2_gorev_sistemi/       # 🚧 YENİ NESİL GÖREV SİSTEMİ (geliştirme aşamasında, eski sistemden AYRI)
    ├── admin/gorev_listesi.php           # MÜFREDAT: bölüm yönetimi + tüm görevler (TEK EKRAN)
    ├── admin/tasarlayici.php          # Blockly ile "Blueprint" (cevap anahtarı) tasarlama
    ├── admin/kukla_kutuphanesi/       # Kukla (sprite) + kostüm yükleme
    ├── ogrenci/oynatici.php           # Öğrencinin kod yazıp çalıştırdığı sahne (846 satır)
    ├── api/gorev_api.php, kukla_api.php
    └── assets/js/
        ├── admin/    mblock_bloklari.js (Blockly blok tanımları), blueprint_motoru.js, metin_editoru.js (Editor.js), api_yoneticisi.js
        └── ogrenci/  kod_tercumani.js (Blockly→JS), oyun_sahnesi.js (2D fizik motoru),
                      hakem_motoru.js (cevap kontrol), coklu_kukla_motoru.js,
                      kostum_atolyesi.js + kostum_atolyesi/ (Paper.js çizim editörü, ~3100 satır)
```

---

## 4. Ana Sistemler (Oyun Mekanikleri)

### A. Görev & Puan Sistemi (v1 — aktif)
`bolumler` → `gorevler` (sıralı). Öğrenci görev çözer → `anlik_puan` birikir → bölüm sonunda öğretmen şifresi (**şu an sabit `"123"`**) ile onaylanır → `kalici_puan` + `elmas`'a aktarılır, `can`/`enerji` 100'e döner.
- 3 kez yanlış şifre → `durum='kilitli'` (öğretmen açar)
- `js/progress_manager.js` görev geçişini, ipucu ve "ajan tuzağı"nı yönetir

### B. Avatar & Stat Matrisi
`ogrenci_avatar_durum` tablosunda 10 slot: `karakter, yuz, sac, kafa, zirh, sag_el, sol_el, ayakkabi, binek, evcil_hayvan`.
Her kategori × eşya seviyesi → 6 stat üretir: **guc, savascilik, beceri, liderlik, karizma, ceviklik** (alt 0, üst 100).

> ⚠️ Bu matris **3 yerde kopyalanmış**: `profil.php`, `api/arena_savas_motoru.php`, `bilesenler/skorboard_modulleri/sinif_arenasi.php`. Birini değiştirirsen üçünü de değiştir.

```php
'evcil_hayvan' => ['guc'=>4,'savascilik'=>3,'beceri'=>5,'liderlik'=>10,'karizma'=>8, 'ceviklik'=>0],
'binek'        => ['guc'=>0,'savascilik'=>2,'beceri'=>3,'liderlik'=>10,'karizma'=>7, 'ceviklik'=>12],
'ayakkabi'     => ['guc'=>2,'savascilik'=>2,'beceri'=>4,'liderlik'=>0, 'karizma'=>0, 'ceviklik'=>10],
'sol_el'       => ['guc'=>4,'savascilik'=>3,'beceri'=>0,'liderlik'=>0, 'karizma'=>2, 'ceviklik'=>-1],
'sag_el'       => ['guc'=>5,'savascilik'=>6,'beceri'=>8,'liderlik'=>0, 'karizma'=>3, 'ceviklik'=>-1],
'kafa'         => ['guc'=>3,'savascilik'=>2,'beceri'=>0,'liderlik'=>0, 'karizma'=>0, 'ceviklik'=>-1],
'zirh'         => ['guc'=>2,'savascilik'=>2,'beceri'=>0,'liderlik'=>0, 'karizma'=>0, 'ceviklik'=>-1],
'yuz'          => ['guc'=>0,'savascilik'=>0,'beceri'=>0,'liderlik'=>2, 'karizma'=>15,'ceviklik'=>0],
```

### C. Market & Ekonomi
Para birimi **💎 elmas** (puan DEĞİL — puan sıralama için, elmas harcamak için).
Eşya fiyatı otomatik, dosya adındaki seviyeden: `1.Seviye=100`, `2=500`, `3=2000`, `4=20000`, `Destans=50000`.
`saldiri_troll` kategorisi tek kullanımlık, defalarca alınabilir; diğerleri 1 kez.

### D. Savaş Arenası (PvP, sınıf içi)
`api/arena_savas_motoru.php` — öğretmen `arena_bitis` ile zamanlı açar.
Kurallar: aynı rakibe **5 gün** ara, saldırıya **20 enerji**, savunanda **günde max 3 savunma** kalkanı, sınıf güç sıralamasında **±5 sıra** farkı.
Hasar = `genel_guc × rand(0.8–1.2) × (çeviklik şansıyla 1.5 kritik)`. Puan ödülü güç farkına göre 5–20 arası. HP kaybı = hasar/10 (min 5, max 40). Loglar 30 günde otomatik silinir.

### E. Ayın Canavarı (global PvE)
`dunya_canavari` — her ayın **18'inde** `api/aylik_savas_motoru.php` çalışır; tüm okul ortalamalarının toplamı canavara hasar verir.
Canavar ölürse yumurtadan doğar (yeni can = Türkiye ortalaması × 1.15), ölmezse "dev" olarak büyür.
Ayrıca `api/canavara_saldir.php` ile öğrenci envanterinden eşya feda edip (33 enerji) `seviye×20` hasar vurabilir.

### F. Quiz Arena (çoklu, canlı) — **SINIF BAZLI**
Öğretmen `quiz_oturum` açar (**sınıf** + bölüm + zorluk seçer) → SADECE o sınıfın öğrencileri çekilir.
`quiz_oturum.ogretmen_id` + `sinif_id` bu bağı kurar.

**Akış:** öğretmen başlatır → `api/ogrenci_radar.php` (3 sn) → `api/quiz_baskin.php`
perdesi + **3-2-1 geri sayım** → `quiz_arena.php` → öğretmen bitirince
`api/quiz_arena_kontrol.php` öğrenciyi köye yollar.

> ⚠️ **SAHİPLİK ZİNCİRİ:** Oturum-öğrenci eşleşmesinde `ogrenciler.ogretmen_id`
> sütununu KULLANMA — o denormalize bir kopya, sınıf devredilince bayatlıyor ve
> öğrenci quiz davetini alamıyor. Doğru zincir `ogretmenler → siniflar → ogrenciler`,
> yani `siniflar.ogretmen_id`.

> ⚠️ **SORU HAVUZU ORTAK:** `quiz_sorulari` hem quiz hem düello tarafından kullanılır.
> Düello zorluk ayrımı yapmaz ve "öğrencinin bölümü ve altı" diye kısıtlar — bu yüzden
> **en alt bölümde her zaman soru bulunmalı**, yoksa düello "soru bulunamadı" deyip iptal olur.
> Örnek seed: `kurulum/soru_havuzu_2026_08.sql`
4 güç (her biri 1 hak): **kilic** (hedef yanlış bilirse -20/+20), **kalkan** (kılıcı geri teptirir, +20 ödül), **glitch**, **saat** (doğru bilirse 2 kat geri teper).

### G. Düello (1v1) — **SINIF İÇİ**
Şalter: **`ogretmenler.duello_bitis`** (arena ile aynı desen; eski global `duello_ayarlari` KULLANIM DIŞI).
Meydan okuma sadece **aynı sınıftaki** öğrenciye yapılabilir.
Puan: okuyan kazanırsa +5/-3, okunan kazanırsa +6/-6, süre biterse ikisi de ceza (-5/-3, `kazanan_id=-2`).

### H. v2 Görev Sistemi ✅ (çalışır durumda)
Admin `tasarlayici.php`'de Blockly ile "Master Blueprint" (cevap anahtarı) + alternatif çözümler kurar, **bölüm/sıra/puan seçer** → `v2_gorevler.icerik_json`.
`admin/gorev_listesi.php` ile görevler listelenir, sıralanır, yayına alınır.

> ⚠️ **[2026-08] MÜFREDATIN TEK ADRESİ VAR.** Aynı işi yapan üç sayfa birleştirildi:
> `admin/gorev_ekle.php` ve `admin/gorev_yonetimi.php` **SİLİNDİ**.
> Bölüm yönetimi + v2/v1 görev listesi → `v2_gorev_sistemi/admin/gorev_listesi.php`
> Görev tasarlama → `v2_gorev_sistemi/admin/tasarlayici.php` (`?bolum=N` ile ön seçim)
> Yeni bir "görev ekle" bağlantısı yazarken bu ikisinden birini kullan, yenisini açma.

Öğrenci `oynatici.php?gorev_id=N`'de kodu blokla kurar → **"Görevimi Kontrol Et"** →
`hakem_motoru.js` workspace XML'ini `api/v2_gorev_tamamla.php`'ye gönderir →
**`api/v2_hakem.php` SUNUCUDA** cevap anahtarıyla karşılaştırır (XML sadeleştirilip
id/x/y atılır, blok tipi + alan değerleri + bağlanma sırası karşılaştırılır) →
doğruysa **anında** `kalici_puan` + `elmas` yazılır. Bekleyen puan / öğretmen şifresi YOK.

`v2_ogrenci_ilerleme` aynı görevden iki kez puan alınmasını engeller.
Bölümün tüm görevleri bitince `mevcut_bolum` otomatik artar.

Ayrıca **Paper.js tabanlı Kostüm Atölyesi** (vektör çizim editörü) var.

> 💡 **[2026-08] BİLGİ AL ÇALIŞIR HALE GELDİ.** Tasarlayıcıda **4. Bölge**
> (yazı + resim editörü ve *bilgi amaçlı* blok alanı) →
> `v2_gorevler.icerik_json.ipucu = {metin, bloklar}`.
> Öğrenci "💡 Bilgi Al"a basınca uyarı çıkar: **ödül yarıya iner**
> (`max(1, round(puan/2))`, 25→13). Kabul ederse `v2_ipucu_alindi`'ye satır
> düşer ve `v2_gorev_tamamla.php` ödülü yarıya indirir.
> · Bilgi alanı **boşsa** ceza da yok.
> · 4. Bölge'deki bloklar **cevap anahtarı DEĞİL**; öğrenciye `readOnly`
>   gösterilir, hakem onlara bakmaz. Cevap anahtarı 2. Bölge.
> · Yuvarlama **üç yerde aynı** olmalı: `v2_ipucu.php`, `v2_gorev_tamamla.php`,
>   `ogrenci/oynatici.php` (düğmedeki sayı).
> · İçerik `durum` isteğinde gönderilmez — yoksa F12'den bedava okunur.

> 🖼️ **[2026-08] SAYFA AŞAĞI BÜYÜYOR.** Oynatıcıda `height:100vh` +
> `overflow:hidden` kaldırıldı; artık `min-height:100vh` ve sayfa kaydırılabilir.
> Şerit `sticky`. İki sütun `align-items:stretch` ile aynı boyda, yani sol panel
> uzayınca Blockly de uzuyor — kod alanının boyu değişince `svgResize` çağıran
> bir `ResizeObserver` var (`resize` olayı yetmez, o sadece PENCERE değişince
> tetiklenir). 900px altında sütunlar alt alta geçer.

> 🏠 **[2026-08] SÜPER ADMİN GEZİNME.** Yeni bir Süper Admin sayfası yazarken
> `<body>`den hemen sonra tek satır ekle — sol alta 🏠 Ana Panel + 🚪 Çıkış gelir:
> `<?php require __DIR__ . '/../bilesenler/super_gezinme.php'; ?>`
> (kök yolu otomatik hesaplanır, `super_panel.php`'de kendini gizler)

> ⚠️ **[2026-08] KUKLA YETKİ AYRIMI.**
> **Süper Admin** → `v2_gorev_sistemi/admin/kukla_kutuphanesi/index.php` (tek ekran:
> ekle, adlandır, kostüm ekle/sil, kukla sil). Yazma işlemleri `kukla_islem.php`.
> `kukla_ekle.php` ve `kukla_sil.php` **SİLİNDİ**.
> **Öğrenci** → kuklaları yalnızca `window.projeKuklalari` içinde, yani **tarayıcıda**
> yaşar; sunucuya yazılmaz, çıkışta kaybolur. Öğrenci ekranındaki admin kısayolları
> ve `adminIslemModal` iframe'i kaldırıldı. Öğrencinin gördüğü tek uç nokta
> `api/kukla_api.php` ve o sadece `SELECT` yapar.

### I. İlerleme Çubuğu & Öğretmen Sıfırlaması ✅ (2026-08)
Oynatıcının tepesinde **bölüm › görev** başlığı ve renkli görev daireleri var
(`ilerleme_cubugu.js` + `ilerleme_cubugu.css`). Dairelere tıklanınca eski görevlere
dönülebilir (puan ikinci kez verilmez). **Otomatik geçiş YOK** — görev bitince
konfetili kutlama açılır (`kutlama.js`), öğrenci "Sıradaki Görev"e kendisi basar.

Öğretmen sınıfının ilerlemesini **panelde, "Canlı Görev Takibi" kartının
📊 İlerleme sekmesinde** görür; ✏️ ile bir öğrencinin bölümlerini işaretleyip
**tekrar yaptırır** (`api/ogrenci_ilerleme_sifirla.php`).
Tablo **canlı**dır (10 sn yoklama, `api/sinif_ilerleme_durumu.php`).

> 🗂️ **[2026-08] ÖĞRETMEN GEZİNMESİ SADELEŞTİ.** Dört sekme ikiye indi:
> **📡 Canlı Takip** ve **👥 Öğrenciler**. Kalkanlar ve nedenleri:
> · *İlerleme* → Canlı Takip'in İÇİNDE alt sekme oldu (`?gorunum=ilerleme`).
>   Ayrı sayfayken öğretmen sınıfı ikinci kez seçmek zorunda kalıyordu.
>   `admin/ogrenci_ilerleme.php` artık sadece 301 yönlendirmesi.
> · *Quiz* → panelde "Sınıf Kontrolleri" kartında zaten düğmesi vardı.
> Sekmesi olmayan sayfalar (quiz merkezi) üst barda **pasif konum etiketi**
> gösterir; `$ot_konum_adlari` dizisine eklenir.
> Yeni bir öğretmen sayfası açmadan önce sor: bu gerçekten ayrı bir iş mi,
> yoksa mevcut bir kartın sekmesi mi olmalı?

> ⚡ **CANLI YOKLAMA KURALI:** Yoklanan uç noktalar öğrenci sayısından **bağımsız**
> sabit sayıda sorgu çalıştırmalı. Sınıf verisi tek `GROUP BY` ile çekilir; döngü
> içinde asla sorgu atılmaz. Frenler: sekme arkadaysa dur, modal açıkken dur,
> üst üste istek atma, 3 hatadan sonra aralığı 3 katına çıkar, `damga` değişmediyse
> DOM'a dokunma. Ölçek ve yük tablosu için `docs/API_REFERANS.md`.

> ⚠️ **CEZA KURALI:** `kalici_puan`'a asla dokunma — lig/arena/canavar ona bağlı.
> Ceza sadece **elmas**tan kesilir ve **eksiye düşebilir**. Sıfırlanan görev tekrar
> çözülünce **sadece elmas** iade edilir (puan zaten alınmamıştı; yoksa ceza ödüle döner).
> "Cezalı mı?" bayrağı sütunda tutulmaz, `kazanilan_puan=0 AND sifirlanma_tarihi IS NOT NULL`
> sorgusundan türetilir → kırmızı avatar efekti görevler bitince kendiliğinden kalkar.

> ⚠️ **`mevcut_bolum` artık sadece kilit tavanıdır.** `mevcut_bolum + 1` mantığı kaldırıldı;
> sıradaki bölüm `sonrakiBolumuHesapla()` ile "tamamlanmamış ilk bölüm" olarak bulunur.
> Böylece 1'e geri gönderilen öğrenci 1'i bitirince 2-3-4 tamamsa doğrudan 5'e döner.

> ⚠️ **"Sıradaki görev"i SUNUCU söyler, tarayıcı tahmin etmez.**
> `sonrakiGoreviBul()` önce **sıfırlanan görevleri** verir (ceza kapansın), sonra ilk
> çözülmemiş görevi. Cevaptaki `sonraki_gorev_id` ile kart kapanınca doğrudan
> `oynatici.php?gorev_id=N&t=<zaman>` adresine gidilir — `t=` önbelleği kırar.
> Hepsi bittiyse `sonErisilenGorev()` öğrenciyi son görevde bırakır;
> **yayındaki ilk göreve düşürme**, yoksa öğrenci 1. bölümde takılı kalır.

---

## 5. Kritik Mimari Kurallar

1. **Framework yok.** Composer, autoload, router yok. Her PHP dosyası kendi başına çalışır.
   Ama artık `db.php`'yi doğrudan çağırma → `bilesenler/guvenlik.php` kullan (o zaten db'yi yükler).
2. **Kimlik ASLA istekten alınmaz.** `$_POST['ogrenci_id']` gibi bir şey yazma.
   Her zaman `ogrenciZorunluApi()` / `ogretmenZorunluApi()` / `superAdminZorunluApi()`.
   *(Eski kodda bu yüzden herkes başkasına puan yazdırabiliyordu.)*
3. **Sorguya gömülecek her kullanıcı girdisi beyaz listeden geçer.**
   Sütun adı, tablo adı, ORDER BY yönü → `beyazListe($deger, [...])`.
4. **API cevabı** → `apiBasari()` / `apiHata()` kullan (`bilesenler/api_yanit.php`).
   Üç eski format anahtarını birlikte döndürdüğü için mevcut JS'ler bozulmaz.
5. **Girdi formatı karışık.** `istekVerisi()` JSON/POST/GET farkını sen düşünmeden çözer.
6. **Çıktı temizliği:** ekrana basılan her kullanıcı verisi `temizle()` (htmlspecialchars) ile.
   JS tarafında `innerHTML` kullanıyorsan önce `textContent` ile kaçır.
7. **CSS çoğunlukla inline.** `css/` klasöründe 4 dosya var; asıl stil `<style>` bloklarında. (bkz. `docs/STIL_REHBERI.md`)
8. **Yorum başlıkları zorunlu.** Her dosyanın başında Türkçe blok yorum: dosya yolu, kullanıcı/sayfa, "halk dilinde işlevi", temel işlevler, yeni eklenen özellikler.
9. **Çok kiracılık:** Yeni bir özellik yazarken *"bu başka bir okulun/sınıfın verisine sızar mı?"* diye sor.
   Quiz ve düello bu yüzden yeniden yazıldı.

---

## 6. Değişiklik Yaparken Kontrol Listesi

- [ ] Dosya başına Türkçe açıklama bloğu eklendi mi?
- [ ] `guvenlik.php` kapıcısı eklendi mi? (`*Zorunlu()` / `*ZorunluApi()`)
- [ ] Kimlik oturumdan mı alınıyor (istekten DEĞİL)?
- [ ] Sorguya gömülen kullanıcı girdisi `beyazListe()`'den geçti mi?
- [ ] Sınıf/öğretmen filtresi var mı? (başka okulun verisi sızmasın)
- [ ] Ekrana basılan veri `temizle()`'den geçti mi?
- [ ] Stat matrisi değiştiyse `bilesenler/rpg_matris.php` (**tek yer**) güncellendi mi?
- [ ] Avatar/envanter değiştiyse `gucOnbelleginiTemizle()` çağrıldı mı?
- [ ] Yeni tablo/sütun → `docs/VERITABANI.md` + `kurulum/` altına migration
- [ ] Yeni endpoint → `docs/API_REFERANS.md`
- [ ] Renkler `docs/STIL_REHBERI.md` paletinden mi?

---

## 7. Hızlı Komutlar

```bash
# Git (tipsGit.md'den)
git add . && git commit -m "mesaj" && git push
git fetch && git pull
git checkout .          # son commit'e temiz dönüş

# DB yedeği
mysqldump -u root mblock_egitim > yedek.sql
```

`v2_gorev_sistemi/TumdosyayapisiveVeritabaniyapisiVerir.py` → tüm dosya + DB yapısını xlsx'e döker
(gerekli: `pip install pandas openpyxl mysql-connector-python`)
