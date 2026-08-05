# Kurulum ve Geçiş Rehberi

> Bu güncelleme veritabanı yapısını değiştiriyor. **Sırayı atlamayın.**

---

## 1. ÖNCE YEDEK AL (zorunlu)

```bash
cd C:\xampp\htdocs\mblock_platform
mysqldump -u root mblock_egitim > kurulum\yedek_migration_oncesi.sql
```

Bir şeyler ters giderse geri dönüş:
```bash
mysql -u root mblock_egitim < kurulum\yedek_migration_oncesi.sql
```

---

## 2. MIGRATION'I ÇALIŞTIR

**Yöntem A — komut satırı:**
```bash
mysql -u root mblock_egitim < kurulum\migration_2026_08_guvenlik.sql
```

**Yöntem B — phpMyAdmin:**
`mblock_egitim` veritabanını seç → **SQL** sekmesi → dosyanın içeriğini yapıştır → Git.

> ⚠️ 8. bölümdeki FOREIGN KEY blokları ikinci çalıştırmada
> `Duplicate foreign key constraint name` hatası verir. **Bu normaldir**,
> migration'ın geri kalanı etkilenmez.

### Migration ne yapıyor?

| # | İşlem |
|---|---|
| 1 | `super_adminler` tablosu oluşturulur |
| 2 | `quiz_oturum`'a `ogretmen_id` + `sinif_id` eklenir (sınıf bazlı quiz) |
| 3 | `ogretmenler`'e `duello_bitis` + `onay_sifresi` eklenir |
| 4 | `ogrenci_ilerleme`'ye `bolum_id` eklenir (bozuk INSERT'in sebebi) |
| 5 | `v2_ogrenci_ilerleme` tablosu + `v2_gorevler.yayinda` sütunu |
| 6 | Performans indeksleri |
| 7 | Yetim satırlar temizlenir (FK ekleyebilmek için zorunlu) |
| 8 | Foreign key'ler (ON DELETE CASCADE) |
| 9 | Ölü sütunlar aktifleştirilir |
| 10 | Öğretmen onay şifresi varsayılan `123` yapılır |

---

## 3. SÜPER ADMİN'E GİRİŞ YAP

Tarayıcıda:
```
http://localhost/mblock_platform/admin/super_giris.php
```
(Ana giriş sayfasının en altında da "🔐 Süper Admin Girişi" bağlantısı var.)

**Varsayılan giriş:**
- Kullanıcı adı: `super`
- Şifre: `123`

> Hesap ilk açılışta otomatik oluşturulur. Şifre `password_hash()` ile
> saklanır, düz metin hâli hiçbir yerde tutulmaz.
> **İlk girişten sonra `Hesap Ayarları`ndan değiştirin.**

5 hatalı denemede hesap 10 dakika kilitlenir.

---

## 4. GÜÇ ÖNBELLEĞİNİ DOLDUR (bir kez)

Süper Admin Paneli → **🔧 Bakım İşlemleri** → **Güçleri Yeniden Hesapla**

Bu, her öğrencinin eşyalarından gelen savaş gücünü hesaplayıp
`ogrenci_avatar_durum.genel_guc` sütununa yazar. Arena savaşları artık
bu önbellekten okuyor — her saldırıda sınıfın tamamı yeniden hesaplanmıyor.

---

## 5. KONTROL LİSTESİ

Migration sonrası şunları test edin:

- [ ] `admin/super_giris.php` → super/123 ile giriş
- [ ] Süper Admin panelinden öğretmen onaylama + PIN üretme
- [ ] Öğretmen girişi → **Quiz Başlat** (artık **sınıf seçimi zorunlu**)
- [ ] Başka bir sınıfın öğrencisi o quize çekilmemeli
- [ ] Öğretmen → Düello Aç → sadece kendi öğrencilerinde açılmalı
- [ ] Öğrenci → sadece **kendi sınıfındakine** meydan okuyabilmeli
- [ ] Öğrenci → market → eşya al → profil → giy (güç güncellenmeli)
- [ ] Arena savaşı → sıklet/5 gün/enerji kuralları çalışmalı
- [ ] Bölüm sonu → öğretmen şifresi (`123`) → puan aktarımı
- [ ] v2: Süper Admin → Görev Tasarlayıcı → bölüm+puan seçip kaydet
- [ ] v2: Öğrenci → oynatici.php → blokları diz → "Görevimi Kontrol Et"

---

## 6. GİT DEPOSU

Local depo hazır ve ilk commit atıldı. GitHub'a göndermek için:

**1. GitHub'da yeni bir repo aç** (README/gitignore EKLEME, boş olsun):
   `https://github.com/new` → ad: `mblockClaudCalismasi`

**2. Şu iki komutu çalıştır:**
```bash
cd C:\xampp\htdocs\mblock_platform
git remote add origin https://github.com/muhammettahasari/mblockClaudCalismasi.git
git push -u origin main
```

### ⚠️ Veritabanı dökümü bilerek repoya dahil EDİLMEDİ

`mblock_egitim.sql` dosyası `.gitignore`'a eklendi çünkü:
- Gerçek öğrenci ad-soyadlarını içeriyor
- Şifreler **düz metin** olarak duruyor
- 6.4 MB (repoyu şişirir)

Private repo bile olsa kişisel veriyi GitHub'a koymamak doğru olan.
Kurulum için `kurulum/migration_2026_08_guvenlik.sql` yeterli.

Yedeklerini ayrı, senkronize edilmeyen bir klasörde tut.

---

## 7. HÂLÂ AÇIK KALAN KONULAR

| Konu | Durum | Not |
|---|---|---|
| Öğrenci/öğretmen şifreleri düz metin | **Bilinçli tercih** | Öğretmenin sınıfa şifre listesi bastırabilmesi için. Canlıya çıkmadan önce yeniden değerlendirin. |
| `password_reset.php` link adresi | Düzeltilmeli | İçindeki `http://localhost/mblock_egitim` → `mblock_platform` olmalı; ayrıca XAMPP'te `mail()` varsayılan olarak çalışmaz. |
| Quiz puanı istemciden geliyor | Kısmen çözüldü | Artık üst sınıra kırpılıyor ama tam sunucu otoritesi için quiz akışının yeniden yazılması gerekir. |
| v1 ve v2 bir arada | Geçiş dönemi | v1 (ekran görüntülü + öğretmen onayı) ve v2 (otomatik kontrol) şu an paralel çalışıyor. |
