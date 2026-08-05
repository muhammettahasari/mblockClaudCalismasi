# Bilinen Sorunlar, Riskler ve Teknik Borç

Kod analizinde tespit edilenler. **Öncelik sırasına göre.**
Not: Proje şu an localhost/XAMPP'te çalışıyor. Aşağıdakilerin çoğu lokal kullanımda sorun çıkarmaz — **internete açılmadan önce mutlaka çözülmeli.**

---

## 🔴 KRİTİK — İnternete açılmadan önce

### 1. Süper Admin panelleri korumasız
Bu dosyalarda **hiçbir oturum kontrolü yok**; URL'i bilen herkes girer:

| Dosya | Ne yapılabilir |
|---|---|
| `admin/ogretmen_yonetimi.php` + `api/admin_teacher_action.php` | Öğretmen onaylama, PIN görme/üretme, süre verme |
| `admin/gorev_ekle.php`, `gorev_duzenle.php`, `gorev_yonetimi.php` | Tüm müfredatı silme/değiştirme |
| `admin/admin_market.php` + `api/api_market_islem.php` | Eşya silme (disk dosyasını da siler), fiyat değiştirme |
| `admin/super_quiz_yonetimi.php` | Soru havuzunu değiştirme (`session_start()` var ama kontrol yok) |
| `admin/quiz_kontrol_merkezi.php` | Quiz başlatma/bitirme (aynı durum) |
| `api/task_save.php`, `task_update.php`, `bolum_ekle.php`, `yonetim_api.php` | Doğrudan çağrılabilir |

**Çözüm:** Ortak bir `admin/auth_kontrol.php` yaz (`if (!isset($_SESSION['super_admin'])) { header("Location: ../index.php"); exit; }`) ve her admin dosyasının en üstüne `require_once` et. Süper Admin için ayrı bir giriş ekranı gerekiyor — şu an hiç yok.

### 2. Şifreler düz metin
`ogrenciler.sifre` ve `ogretmenler.sifre` hash'siz saklanıyor, karşılaştırma `=== ` ile yapılıyor.
Ayrıca `api/get_passwords.php` öğretmene sınıfın **tüm şifrelerini** düz döndürüyor (PDF çıktısı için).

**Çözüm:** `password_hash()` / `password_verify()`. Bu, "öğretmen şifre listesini bassın" özelliğini bozar — alternatif olarak öğretmenin şifre **sıfırlaması** (yeni şifre üretip bir kez göstermesi) tasarlanmalı.

### 3. `api/bolum_bitir.php` — var olmayan sütuna INSERT
```php
INSERT INTO ogrenci_ilerleme (ogrenci_id, bolum_id) VALUES (?, ?)
```
`ogrenci_ilerleme` tablosunda **`bolum_id` sütunu yok** (`gorev_id` var). PDO `ERRMODE_EXCEPTION` açık olduğu için bu satır **exception fırlatır** ve JSON cevabı hiç dönmez.
> Ayrıca bu dosya `mevcut_bolum++` ve puan aktarımını yaparken `api/update_score.php` de benzer işi yapıyor — **iki paralel seviye atlama yolu var**, hangisinin canlı olduğu belirsiz.

**Çözüm:** Ya `ogrenci_ilerleme`'ye `bolum_id` sütunu ekle, ya INSERT'i `gorev_id`'ye çevir, ya da log'u kaldır. Sonra iki akıştan birini seç.

### 4. Öğretmen onay şifresi sabit `"123"`
`api/teacher_approval.php` içinde `$dogru_sifre = "123";` hard-coded. Tüm okullarda aynı.

**Çözüm:** `ogretmenler` tablosuna `onay_sifresi` sütunu ekle, öğretmen panelinden değiştirilebilir yap.

### 5. `password_reset.php` linki yanlış klasöre gidiyor
```php
$site_url = "http://localhost/mblock_egitim";   // ← proje klasörü "mblock_platform"
```
Gönderilen sıfırlama linki 404 verir. Ayrıca `mail()` XAMPP'te varsayılan olarak çalışmaz.

---

## 🟠 YÜKSEK — Çok kiracılık (multi-tenant) sızıntıları

### 6. Quiz oturumu global
`quiz_oturum` tablosunun **öğretmen/sınıf bağı yok**. `api/quiz_durum_kontrol.php`:
```php
SELECT id FROM quiz_oturum WHERE durum = 'aktif' ORDER BY id DESC LIMIT 1
```
→ Bir öğretmen quiz başlatınca **sistemdeki tüm okulların öğrencileri** arenaya çekilir ve aynı sıralamada yarışır.

**Çözüm:** `quiz_oturum`'a `ogretmen_id` (ve/veya `sinif_id`) ekle, tüm sorgulara filtre koy.

### 7. Düello şalteri global
`duello_ayarlari` tek satır (`id=1`). Bir öğretmen düelloyu açınca **herkese açılır**.
Ayrıca `api/duello/yanitla.php` soruyu `bolum_id <= mevcut_bolum` ile seçiyor ama karşı tarafın sınıfı/öğretmeni hiç kontrol edilmiyor → **farklı okuldaki öğrencilere meydan okunabilir**.

**Çözüm:** `duello_ayarlari`'nı `ogretmenler.duello_bitis` sütununa taşı (arena ile aynı desen), düello hedeflerini `sinif_id` ile sınırla.

### 8. `api/get_task.php` sahiplik kontrolü yapmıyor
Herhangi bir `gorev_id` ile çağrılabilir; öğrencinin `mevcut_bolum` seviyesi kontrol edilmiyor. `ogrenci_paneli.php` sayfa tarafında anti-hile var ama API'de yok.

---

## 🟡 ORTA — Performans

### 9. `market.php` her açılışta tüm eşyaları diskten kontrol ediyor
Sayfanın en üstünde: tüm `esya_kutuphanesi` çekiliyor, her satır için `file_exists()` çağrılıyor, bulunamayan **kalıcı olarak siliniyor** (envanterden ve avatarlardan da).
- Her market ziyaretinde N adet disk I/O (şu an ~120 eşya)
- **Yıkıcı:** klasör bir sebeple erişilemezse (izin, mount, yol hatası) tüm eşya kütüphanesi silinir

**Çözüm:** Bu temizliği market'ten çıkar; `api/api_market_islem.php?islem=senkronize_et` gibi manuel/planlı bir bakım işine taşı.

### 10. `arena_savas_motoru.php` — N+1 sorgu
Her saldırıda sınıftaki **her öğrenci için** `dinamikGucHesapla()` çağrılıyor; bu fonksiyon öğrenci başına 2 sorgu yapıyor. 30 kişilik sınıfta tek saldırı = ~65 sorgu.

**Çözüm:** Sınıfın tüm avatar + eşya verisini tek JOIN ile çek, PHP'de hesapla. Ya da hesaplanmış gücü `ogrenci_avatar_durum.genel_guc` sütununa yaz (sütun zaten var, kullanılmıyor) ve avatar değişince güncelle.

### 11. Polling yoğun
Her öğrenci sayfasında eşzamanlı en az 3 `setInterval` fetch dönüyor (düello dinle, quiz durum, arena durum). 30 kişilik sınıfta saniyede onlarca istek.

**Çözüm:** Tek bir `api/ogrenci_radar.php` yazıp üç kontrolü birleştir; aralıkları uzat (2–5 sn).

---

## 🟢 DÜŞÜK — Kod kalitesi / teknik borç

### 12. Stat matrisi 3 yerde kopyalanmış
`profil.php`, `api/arena_savas_motoru.php`, `bilesenler/skorboard_modulleri/sinif_arenasi.php`.
**Çözüm:** `bilesenler/rpg_matris.php` oluştur, üçünde de `require_once` et.

### 13. `js/db.php` gereksiz kopya
Kök `db.php` ile birebir aynı, hiçbir yerden çağrılmıyor. Silinebilir. (JS klasöründe PHP olması ayrıca kafa karıştırıcı.)

### 14. `ogrenci_avatar_durum`'da ölü sütunlar
`genel_guc`, `can`, `enerji`, `ceviklik` — DB'de var ama savaş motoru bunları okumuyor, her seferinde yeniden hesaplıyor. `ogrenciler.kalkan_bitis` de kullanılmıyor. `ogretmenler.uyelik_bitis_tarihi` de öyle (`bitis_tarihi` kullanılıyor).

### 15. API cevap formatı tutarsız
`{success:bool}` / `{status:"success"}` / `{durum:"basarili"}` / `"OK"` / ham HTML — beş farklı kalıp.
`task_save.php` ve `task_update.php` JSON yerine **tam HTML sayfa** döndürüyor.

### 16. Foreign key yok
Hiçbir tabloda FK tanımı yok. Öğrenci silinince `ogrenci_envanter`, `ogrenci_avatar_durum`, `arena_savaslari`, `quiz_ogrenci` satırları yetim kalıyor. `teacher_remote.php?islem=ogrenci_sil` sadece `ogrenciler`'den siliyor.

### 17. v2 sistemi yarım
- `gorev_api.php` → `bolum_id=1`, `sira_no=1`, `puan=20` **sabit kodlanmış**
- `ogrenci/oynatici.php` → `SELECT * FROM v2_gorevler ORDER BY id DESC LIMIT 1` — hep **son eklenen görevi** açar, görev seçimi yok
- `v2_gorevler` / `v2_kukla_kutuphanesi` tabloları SQL dökümünde yok → yeni kuruluma taşınmıyor
- `kostumler_json` içinde `resimYolu` ve `yol` aynı değeri iki kez tutuyor
- v2, v1 puan/bölüm sistemine **hiç bağlanmamış** (öğrenci v2'de görev bitirince puan almıyor)
- Kalınan yer: *"skorboardın üstündeki avatar resmini sayfaya yerleştirmeye çalışıyorum"*

### 18. Bekleyen tasarım isteği (`assets/videos/metin.txt`)
Canavar sisteminde istenen ama **henüz yapılmamış** değişiklikler:
- Can %50 altına düşmeden "zafer" videosu oynamamalı (2 video: `canavar_kazandi.mp4` / `zafer.mp4`)
- Canavar ölmediği sürece yumurtaya dönmesin, "dev ejderha" görseli kalsın
- "KRALLIK SAVUNMA MERKEZİ" ekranına sınıf/okul/il toplam güç göstergeleri
- Dashboard üstünde canavarın gücü + görseli

### 19. Repo hijyeni
`git status`: 15+ silinmiş dosya commit'lenmemiş (ekran görüntüleri, .docx notlar). `.rar` ve `.xlsx` dosyaları repoda. `.gitignore` yok.

---

## Önerilen Yol Haritası

| Sıra | İş | Etki |
|---|---|---|
| 1 | Süper Admin girişi + `auth_kontrol.php` (#1) | Kritik güvenlik |
| 2 | `bolum_bitir.php` düzelt, çift akışı tekleştir (#3) | Bozuk özellik |
| 3 | Şifre hash'leme + şifre listesi akışını yeniden tasarla (#2) | Kritik güvenlik |
| 4 | Quiz + Düello'yu öğretmen/sınıf bazına indir (#6, #7) | Çok kiracılık |
| 5 | `market.php` temizlik döngüsünü çıkar (#9) | Performans + veri güvenliği |
| 6 | Stat matrisini tek dosyaya al (#12) | Bakım kolaylığı |
| 7 | Arena güç hesabını optimize et (#10) | Performans |
| 8 | v2'yi v1 puan sistemine bağla (#17) | Ürün |
