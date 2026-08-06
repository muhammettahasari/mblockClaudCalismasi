# API Referansı

**Kolonlar:** Yol · Metot/Girdi · Ne yapar · Döndürdüğü · Auth
**Girdi tipleri:** `JSON` = `json_decode(file_get_contents("php://input"))` · `POST` = form-data · `GET` = query string

---

## 🔐 Kimlik & Oturum

| Dosya | Girdi | İşlev | Dönüş | Auth |
|---|---|---|---|---|
| `api/verify_pin.php` | JSON `{pin}` | PIN'den onaylı öğretmeni + sınıflarını bulur | `{success, ogretmen_id, ogretmen_ad, okul_adi, siniflar[]}` | yok (public) |
| `api/student_login.php` | JSON `{sinif_id, kullanici_adi, sifre}` | Öğrenci girişi; `onay_durumu=0` ise reddeder; **7 gün geçtiyse can/enerji=100** | `{success, message}` | – |
| `api/student_register.php` | JSON `{ad_soyad, kullanici_adi, sifre, ogretmen_id, sinif_id}` | Kayıt (`onay_durumu=0`, can/enerji=100) | `{success, message}` | – |
| `api/teacher_login.php` | JSON `{kullanici_adi, sifre}` | Öğretmen girişi; `durum` ve `bitis_tarihi` kontrolü | `{success, reason:'pending'\|'expired', message}` | – |
| `api/teacher_register.php` | JSON (10 alan) | Öğretmen başvurusu → `onay_bekliyor` | `{success, message}` | – |
| `api/teacher_time_request.php` | JSON `{kullanici_adi, ay}` | Süre uzatma talebi → `durum='onay_bekliyor'` | `{success}` | – |
| `api/password_reset.php` | JSON `{email}` | 32-byte token üretir, `mail()` ile link atar (1 saat) | `{success, message}` | – |
| `api/logout.php` | – | `session_destroy()` + `index.php`'ye redirect | HTTP 302 | – |

---

## 👨‍🏫 Öğretmen

| Dosya | Girdi | İşlev | Auth |
|---|---|---|---|
| `api/teacher_remote.php` | GET `islem=` | **Çok işlevli.** `liste` (sınıf filtreli öğrenci listesi + bekleyen onay bayrağı), `siniflari_getir`, `sinif_ekle`, `sinif_sil` (JSON body, öğrencileri de siler), `kayit_onayla`, `kilit_ac`, `onayla` (anlık→kalıcı puan), `reddet_ac`, `ogrenci_sil`, `ogrenci_duzenle` (JSON body) | ✅ `ogretmen_id` |
| `api/teacher_add_student.php` | JSON | Tekli öğrenci ekleme; **isim/kullanıcı adı çakışırsa otomatik sonuna sayı ekler** | ✅ |
| `api/teacher_bulk_add.php` | JSON `{sinif_id, ogrenciler[]}` | Excel'den toplu ekleme (`Ad Soyad`/`Kullanıcı Adı`/`Şifre` anahtarları), `onay_durumu=1` | ✅ |
| `api/get_passwords.php` | GET `sinif_id` | PDF çıktısı için şifre listesi | ✅ |
| `api/arena_durum_degistir.php` | POST `islem=ac\|kapat`, `sure` (dk veya `suresiz`) | PvP arenasını açar/kapatır (`ogretmenler.arena_bitis`) | ✅ |
| `api/ogretmen_radar.php` | – | Arena + Düello durumunu döner, **süresi dolanı otomatik kapatır** | ✅ |
| `api/duello/duello_kontrol.php` | POST `islem=ac\|kapat`, `sure` | Düello şalteri (`duello_ayarlari` id=1) | ✅ |

---

## 👑 Süper Admin

| Dosya | Girdi | İşlev | Auth |
|---|---|---|---|
| `api/admin_teacher_action.php` | JSON `{islem, id, ay}` | `onayla` (**PIN üretir**, `bitis_tarihi = +ay+1 ay`), red/silme | ❌ **YOK** |
| `api/bolum_ekle.php` | JSON `{bolum_adi}` | Yeni bölüm (sıra_no otomatik) | ❌ |
| `api/task_save.php` | POST (form) | Yeni görev ekler → HTML sayfa döner | ❌ |
| `api/task_update.php` | POST (form) | Görev günceller → HTML döner | ❌ |
| `api/yonetim_api.php` | JSON `{islem, tur, id}` | `sil`, `bolum_duzenle`, `sira_degistir` (yukarı/aşağı) | ❌ |
| `api/api_market_islem.php` | POST/GET `islem=` | `listele` (kategori/arama/sıralama), `ekle` (resim upload), `sil` (toplu; dosyayı da siler, envanteri temizler), `guncelle`, `hizli_guncelle`, `toplu_guncelle`, **`senkronize_et`** (klasörü tarar, dosya adından isim+fiyat türetir, DB'ye ekler) | ❌ |

---

## 🎓 Öğrenci — Görev & Puan

| Dosya | Girdi | İşlev |
|---|---|---|
| `api/get_task.php` | GET `gorev_id` | Görevi + `sonraki_gorev_id` + `son_gorev_mi` döner |
| `api/student_sync.php` | GET `islem=dinle` / POST JSON | `dinle` (durum polling) · `anlik_puan_guncelle` · `onay_bekliyor_yap` · `kilitle` |
| `api/teacher_approval.php` | JSON `{sifre, ogrenci_id}` | ⚠️ **Şifre sabit `"123"`.** 3 hata → `durum='kilitli'`. Sayaç `$_SESSION['hata_sayaci']` |
| `api/update_score.php` | JSON `{islem, ogrenci_id}` | `onayla` → `kalici_puan += anlik_puan`, `elmas += anlik_puan`, can/enerji=100 · `reddet_basa_sar` → anlık puanı sıfırla |
| `api/bolum_bitir.php` | – (session) | `mevcut_bolum++`, puan aktar, `ogrenci_ilerleme`'ye log — ⚠️ **log INSERT'i hatalı** (bkz. BILINEN_SORUNLAR #3) |

---

## 🎒 Market & Avatar

| Dosya | Girdi | İşlev |
|---|---|---|
| `api/satin_al.php` | JSON `{esya_id}` | Elmas kontrolü → **transaction** → elmas düş + envantere ekle. `saldiri_troll` tekrar alınabilir | `{success, kalan_elmas, tekrar_alinabilir}` |
| `api/avatar_guncelle.php` | JSON veya POST `{kategori, esya_id, islem}` | `giy` / `cikart` — kategori **beyaz listeden** kontrol edilir |

---

## ⚔️ Savaş

| Dosya | Girdi | İşlev |
|---|---|---|
| `api/arena_savas_motoru.php` | POST `hedef_id` | **Ana PvP motoru.** Dinamik güç hesabı → arena açık mı, 5 gün kuralı, 20 enerji, can>0, günlük 3 savunma kalkanı, ±5 sıra kuralı → zar at → puan/HP güncelle → logla → 30 gün temizliği |
| `api/arena_durum_kontrol.php` | – | Öğrenci için arena açık mı → `{success, acik}` |
| `api/canavara_saldir.php` | POST `islem=envanter_getir\|saldir`, `envanter_id` | Eşyayı **feda eder** (DELETE) + 33 enerji → `seviye×20` hasar → canavar canı düşer, log atılır |
| `api/aylik_savas_motoru.php` | – (cron/manuel) | **Sadece ayın 18'inde çalışır.** Okul ortalamalarının toplamı = hasar. Canavar ölürse yumurta doğar (×1.15), ölmezse "dev" büyür. Video türü: can ≤ %50 → `zafer`, değilse `canavar_kazandi` |

---

## 🎯 Quiz Arena

| Dosya | Girdi | İşlev |
|---|---|---|
| `api/quiz_durum_kontrol.php` | – | Global aktif oturum var mı → `{aktif, oturum_id}` (tüm panellerde polling) |
| `api/quiz_arena_kontrol.php` | GET `oturum_id` | Oturum hâlâ aktif mi → `{durum}` |
| `api/quiz_aktif_hedefler.php` | GET `oturum_id` | Kendisi hariç oyuncular (puana göre sıralı) |
| `api/quiz_puan_guncelle.php` | POST `oturum_id, puan, cozulen_soru` | Puanı yazar → `"OK"` |
| `api/quiz_canli_tablo.php` | GET `oturum_id` | Öğretmen radarı için canlı sıralama |
| `api/quiz_guc_kullan.php` | POST `oturum_id, guc_turu, hedef_id` | Hakkı 0'lar. `kalkan` → kendini korur. `kilic` + hedefte kalkan varsa → **kalkan kırılır, hedef +20, saldıran -20**. Diğerleri `quiz_savas_log`'a `bekliyor` olarak yazılır |
| `api/quiz_saldiri_dinle.php` | GET `oturum_id` | Gelen saldırıyı çeker ve `savunan_gordu_mu=1` işaretler |
| `api/quiz_saldiri_sonuc.php` | POST `log_id, dogru_mu, normal_puan` | Yanlış → savunan -20 / saldıran +20. Doğru + kılıç/glitch → savunan normal puan. Doğru + saat → **geri teper**: savunan +2×, saldıran -2× |
| `api/quiz_baskin.php` | *(include)* | ⚠️ PHP değil, **HTML overlay parçası** — `ogrenci_paneli.php`, `profil.php`, `market.php` içine include edilir ("SAVAŞ BAŞLADI" ekranı) |

---

## 🤺 Düello (`api/duello/`)

| Dosya | Girdi | İşlev |
|---|---|---|
| `istek_at.php` | POST `hedef_id` | Arena açık mı + bekleyen düello var mı → yeni kayıt (`bekliyor`) |
| `dinle.php` | – | **Polling merkezi.** Gelen istek var mı / kabul edildi mi (arenaya git) / arena aktif mi. Tekrar tetiklemeyi `$_SESSION['son_gidilen_saldiri']` ile engeller |
| `yanitla.php` | POST `duello_id, yanit=kabul\|red` | Kabulde öğrencinin `mevcut_bolum` seviyesine uygun **rastgele soru** seçer → `kabul_edildi` |
| `cevap.php` | POST `duello_id, cevap` | İlk doğru bilen kazanır. Okuyan kazanırsa **+5/-3**, okunan kazanırsa **+6/-6**. Yanlışta sadece yanlış bilen ceza yer, `kazanan_id=-1` |
| `senkron.php` | GET `duello_id` | Maç bitti mi → `{oyun_bitti, kazandim_mi, berabere_mi, zaman_doldu}` |
| `zaman_doldu.php` | POST `duello_id` | İkisine de ceza (okuyan -5, okunan -3), `kazanan_id=-2` |

---

## 🚧 v2 Görev Sistemi

| Dosya | Girdi | İşlev | Auth |
|---|---|---|---|
| `v2_gorev_sistemi/api/gorev_api.php` | JSON `{islem:'yeni_gorev_kaydet', baslik, icerik}` | `v2_gorevler`'e kaydeder — ⚠️ **`bolum_id=1`, `sira_no=1`, `puan=20` sabit kodlanmış** | ❌ |
| `v2_gorev_sistemi/api/kukla_api.php` | – | Tüm kuklaları JSON döner → `{durum, veri[]}` | ❌ |
| `.../admin/kukla_kutuphanesi/kukla_islem.php` | POST multipart | Çoklu kostüm yükler (`jpg,jpeg,png,gif,svg`), `uniqid("kostum_")` ile adlandırır | ❌ |
| `.../admin/kukla_kutuphanesi/kukla_sil.php` | | Kukla siler | ❌ |

---

## Polling Aralıkları (performans notu)

Tarayıcı sürekli şu endpoint'leri çağırıyor — düzenleme yaparken yük artışına dikkat:

- `api/duello/dinle.php` → her öğrenci sayfasında (`bilesenler/duello_radari.php`)
- `api/quiz_durum_kontrol.php` → her öğrenci sayfasında
- `api/student_sync.php?islem=dinle` → kilit ekranında
- `api/ogretmen_radar.php` → öğretmen panelinde
- `api/quiz_canli_tablo.php` → quiz kontrol merkezinde
- `api/quiz_saldiri_dinle.php` → quiz arenasında
- `api/arena_durum_kontrol.php` → skorboard'da

---

## 📊 İlerleme Çubuğu & Öğretmen Sıfırlaması (2026-08)

| Dosya | Girdi | İşlev | Auth |
|---|---|---|---|
| `v2_gorev_sistemi/api/ilerleme_haritasi.php` | JSON `{islem:'harita'\|'gorev_ac'\|'bildirim_oku', gorev_id?}` | Öğrencinin bölüm/görev haritası, ceza durumu, öğretmen bildirimi. `gorev_ac` "kaldığın yer"i günceller. | ✅ öğrenci |
| `api/ogrenci_ilerleme_sifirla.php` | JSON `{islem:'onizleme'\|'sifirla', ogrenci_id, bolum_idler[], sebep?}` | Seçilen bölümleri sıfırlar, elmas cezası uygular. `onizleme` hiçbir şey yazmaz. | ✅ öğretmen + **kendi öğrencisi** |

**Görev durumu etiketleri** (`ilerlemeHaritasi()` çıktısı):

| Etiket | Renk | Anlamı |
|---|---|---|
| `tamam` | 🟢 yeşil | Çözüldü, puanı alındı |
| `aktif` | 🟠 turuncu | Şu an oynanıyor / sıradaki |
| `sifirlandi` | 🔴 kırmızı | Öğretmen tekrar yapılmasını istedi |
| `bekliyor` | ⚪ gri | Sırası gelmedi |
| `kilitli` | 🔒 | Bölüme hiç ulaşılmadı, tıklanamaz |

**Ceza modeli (önemli):**

- `kalici_puan`'a **asla dokunulmaz** — lig sıralaması, arena gücü ve canavar hasarı ona bağlı.
- Ceza sadece **elmas** üzerinden verilir ve **eksiye düşebilir**.
- Sıfırlanan görev tekrar çözülünce **sadece elmas** iade edilir, puan ikinci kez yazılmaz
  (`v2_gorev_tamamla.php` → `$sifirlanmis` kontrolü). Aksi halde ceza ödüle dönerdi.
- "Cezalı mı?" bilgisi ayrı bir sütunda tutulmaz; `kazanilan_puan = 0 AND sifirlanma_tarihi IS NOT NULL`
  sorgusundan türetilir → görevler bitince kırmızı efekt kendiliğinden kalkar.

### Sıradaki görevi SUNUCU söyler (2026-08 hata düzeltmesi)

`v2_gorev_tamamla.php` ve `ilerleme_haritasi.php` cevaplarına `sonraki_gorev_id`
eklendi. Kart kapanınca tarayıcı doğrudan `oynatici.php?gorev_id=N&t=<zaman>`
adresine gider.

**Neden:** Eskiden tarayıcı sadece `oynatici.php`ye gidiyor, sayfanın doğru
görevi seçmesi umuluyordu. İki hata çıkıyordu:

1. Adres değişmediği için tarayıcı sayfayı **önbellekten** veriyor, ekran hiç
   yenilenmiyordu. (`t=` zaman damgası bunu kırar, `location.replace` geri
   tuşunun eski göreve dönmesini engeller.)
2. Öğretmen ceza verince öğrenci 1. bölüme gidiyor ama bitirince **geri
   dönemiyordu**.

**`sonrakiGoreviBul()` öncelik sırası:**

1. Öğretmenin sıfırladığı görevler (önce ceza kapanır)
2. Kalan çözülmemiş görevlerin ilki (bölüm sırası → görev sırası)
3. Hiçbiri yoksa `null` → `sonErisilenGorev()` devreye girer

> ⚠️ Sıfırlanan görevlerin ayrı bir adımda öne alınması şart. Önceden "sıfırlanan
> görev zaten önde olur" varsayılmıştı; öğretmen yeni görev ekleyince sıralama
> değişiyor ve öğrenci ceza görevi ile yeni görev arasında gidip geliyordu.

> ⚠️ Tüm görevler bitince `oynatici.php` **yayındaki ilk göreve DÜŞMEMELİ**.
> `sonErisilenGorev()` öğrenciyi ulaştığı son görevde bırakır; aksi hâlde her
> şeyi bitiren öğrenci 1. bölümün 1. görevine atılıp orada takılı kalıyordu.

---

## ⚡ Canlı Güncelleme ve Yük (2026-08)

| Uç nokta | Kim yoklar | Aralık | Sorgu (sabit) |
|---|---|---|---|
| `v2_gorev_sistemi/api/ilerleme_haritasi.php` | Öğrenci (oynatıcı) | 10 sn | ~4 |
| `api/sinif_ilerleme_durumu.php` | Öğretmen (ilerleme takibi) | 10 sn | 5 |

**Kritik tasarım kuralı:** İkisi de öğrenci sayısından **bağımsız** sabit sorgu
çalıştırır. Öğretmen uç noktası tüm sınıfı tek `GROUP BY` ile çeker — 40 kişilik
sınıf için de 400 kişilik için de 5 sorgu. Döngü içinde asla sorgu atmayın.

**Ortak frenler (ikisinde de var):**

- Sekme arka plandaysa istek atılmaz (`document.hidden`)
- Modal/panel açıkken durur
- Üst üste istek gitmez (`calisiyor` bayrağı)
- Arka arkaya 3 hata → aralık 3 katına çıkar
- Öğretmen cevabında `damga` (md5) var; değişmediyse DOM'a dokunulmaz

**Kaba yük tahmini:**

| Ölçek | İstek/sn | Sorgu/sn |
|---|---|---|
| 1 sınıf (30 öğrenci) | ~3 | ~12 |
| 1 okul (300 öğrenci) | ~31 | ~126 |
| 10 okul (3000 öğrenci) | ~312 | ~1260 |

XAMPP/tek sunucu bir okulu (~300 öğrenci) rahat taşır. Binlerce eşzamanlı
öğrenciye çıkılırsa sırasıyla: **(1)** aralığı 30 sn yapın (yük üçte bire iner),
**(2)** cevabı 5 sn'lik APCu/Redis önbelleğine alın (anahtar: öğretmen için
`sinif_id`), **(3)** ancak ondan sonra WebSocket/SSE düşünün.

> ⚠️ Öğretmen sayfası `admin/ogrenci_ilerleme.php` de artık sabit sorgu kullanır.
> Öğrencilerin bölüm haritaları sayfaya **gömülmez**; ✏️ kalemine basılınca
> `ogrenci_ilerleme_sifirla.php` → `islem: 'harita'` ile tek öğrenci için çekilir.

---

## 🔒 Ders Kilidi (2026-08)

| Dosya | Girdi | İşlev | Auth |
|---|---|---|---|
| `api/ogrenci_kilit.php` | JSON `{islem, ogrenci_id\|sinif_id, mesaj?}` | Tek öğrenci / tüm sınıf kilitle-aç, durum sorgusu | ✅ öğretmen + **kendi sınıfı** |

İşlemler: `kilitle` · `ac` · `sinif_kilitle` · `sinif_ac` · `durum`

**Kilit üç katmanda çalışır:**

1. **Veritabanı** — `ogrenciler.ogretmen_kilidi`. Sayfa yenilense, öğrenci
   çıkıp girse, başka tarayıcı açsa bile kilit durur. `localStorage`
   KULLANILMADI; o temizlenebilir.
2. **Perde** — `bilesenler/ogrenci_kilit_perdesi.php`. Sayfa açılırken PHP
   tarafında sorgular; canlı açılma için radara abone olur (3 sn).
3. **Sunucu zorlaması** — `kilitliyseReddet()` yazma yapan uç noktalarda
   çağrılır ve **423 Locked** döner.

> ⚠️ **PERDE TEK BAŞINA YETMEZ.** Konsolu bilen bir öğrenci perdeyi silebilir.
> Yeni bir "öğrenci yazma" uç noktası eklerken `kilitliyseReddet($pdo, $id)`
> çağırmayı unutma. Şu an korunanlar: `v2_gorev_tamamla.php`, `satin_al.php`,
> `update_score.php`, `duello/istek_at.php`, `quiz_puan_guncelle.php`.

> ⚠️ **SAHİPLİK:** `ogrenciler.ogretmen_id` **kullanılmaz**. Zincir
> `ogretmenler → siniflar → ogrenciler`. Başka okulun öğrencisine istek
> atılırsa 403 döner.
