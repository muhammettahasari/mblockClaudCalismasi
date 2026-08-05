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

## 2. Kullanıcı Rolleri (3 Katman)

| Rol | Giriş | Panel |
|---|---|---|
| **Süper Admin** (patron) | Giriş ekranı YOK, URL ile direkt erişim | `admin/ogretmen_yonetimi.php`, `admin/gorev_*.php`, `admin/admin_market.php`, `admin/super_quiz_yonetimi.php` |
| **Öğretmen** | `index.php` → kullanıcı adı + şifre | `admin/ogretmen_paneli.php`, `admin/ogrenci_yonetimi.php`, `admin/quiz_kontrol_merkezi.php` |
| **Öğrenci** | `index.php` → **PIN + sınıf + kullanıcı adı + şifre** | `ogrenci_paneli.php`, `profil.php`, `market.php`, `quiz_arena.php`, `duello_arena.php` |

**Kayıt akışı:**
Öğretmen kayıt olur → Süper Admin onaylar + **4 haneli PIN** üretir + üyelik süresi verir → Öğrenci o PIN ile sınıfını bulup kayıt olur → Öğretmen öğrenciyi onaylar (`onay_durumu=1`).

**Oturum anahtarları:** `$_SESSION['ogrenci_id']`, `$_SESSION['ogretmen_id']`, `ogretmen_pin`, `okul_adi`, `sinif_id`, `hata_sayaci`

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

### F. Quiz Arena (çoklu, canlı)
Öğretmen `quiz_oturum` açar (bölüm + zorluk seçer) → öğrenciler `quiz_arena.php`'ye çekilir.
4 güç (her biri 1 hak): **kilic** (hedef yanlış bilirse -20/+20), **kalkan** (kılıcı geri teptirir, +20 ödül), **glitch**, **saat** (doğru bilirse 2 kat geri teper).
`quiz_savas_log` tablosu üzerinden polling ile saldırı bildirimi.

### G. Düello (1v1)
`duello_ayarlari` (id=1) tek satır global şalter. Öğretmen dakikalık açar.
Meydan oku → karşı taraf kabul → seviyeye uygun rastgele soru → ilk doğru bilen kazanır.
Puan: okuyan kazanırsa +5/-3, okunan kazanırsa +6/-6, süre biterse ikisi de ceza (-5/-3, `kazanan_id=-2`).

### H. v2 Görev Sistemi 🚧 (yarım)
Admin Blockly ile "Master Blueprint" (doğru kod dizilimi) + alternatif çözümler tasarlar → `v2_gorevler.icerik_json`.
Öğrenci `oynatici.php`'de kodu blokla kurar, `kod_tercumani.js` JS'e çevirir, `oyun_sahnesi.js` çalıştırır, `hakem_motoru.js` kontrol eder.
Ayrıca **Paper.js tabanlı Kostüm Atölyesi** (vektör çizim editörü) var.
**Son durum notu (`ne yapılacak nerede kaldım.txt`):** *"skorboardın üstündeki avatar resmini sayfaya yerleştirmeye çalışıyorum."*

---

## 5. Kritik Mimari Kurallar

1. **Framework yok.** Composer, autoload, router yok. Her PHP dosyası kendi başına çalışır ve `require_once 'db.php'` (veya `../db.php`) yapar.
2. **API sözleşmesi tutarsız.** Bazı endpoint `{"success":bool}`, bazısı `{"status":"success|error"}`, bazısı `{"durum":...}` döner. Yeni kod yazarken **o dosyanın etrafındaki mevcut kalıbı taklit et**, tek tip yapmaya çalışıp diğerlerini bozma.
3. **Girdi formatı karışık.** Bazıları `json_decode(file_get_contents("php://input"))`, bazıları `$_POST`, bazıları `$_GET`. Değiştirmeden önce çağıran JS'i kontrol et.
4. **CSS çoğunlukla inline.** `css/` klasöründe sadece 4 dosya var; asıl stil `<style>` bloklarında ve `style=""` içinde. (bkz. `docs/STIL_REHBERI.md`)
5. **Yorum başlıkları zorunlu.** Her dosyanın başında Türkçe blok yorum var: dosya yolu, kullanıcı/sayfa, "halk dilinde işlevi", temel işlevler, yeni eklenen özellikler. **Yeni dosya oluştururken aynı formatı kullan.**
6. **`js/db.php`** kök `db.php`'nin gereksiz kopyası — kullanılmıyor.

---

## 6. Değişiklik Yaparken Kontrol Listesi

- [ ] Dosya başına Türkçe açıklama bloğu eklendi mi?
- [ ] Stat matrisi değiştiyse **3 dosyada birden** güncellendi mi?
- [ ] Yeni tablo/sütun eklendiyse `docs/VERITABANI.md` güncellendi mi?
- [ ] Yeni endpoint eklendiyse `docs/API_REFERANS.md` güncellendi mi?
- [ ] Session kontrolü var mı? (Çoğu admin dosyasında **yok** — bkz. `docs/BILINEN_SORUNLAR.md` #1)
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
