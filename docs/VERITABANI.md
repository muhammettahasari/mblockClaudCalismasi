# Veritabanı Referansı — `mblock_egitim`

MariaDB 10.4 / InnoDB / `utf8mb4`
Bağlantı: `db.php` → PDO, `localhost`, `root`, şifre boş, `ERRMODE_EXCEPTION`, `FETCH_ASSOC` varsayılan.

**Toplam 20 tablo:** 18 tanesi `mblock_egitim (8).sql` dökümünde var, **2 tanesi (`v2_*`) dökümde YOK** — sadece kodda referanslanıyor.

---

## 👤 Kullanıcı & Organizasyon

### `ogretmenler`
Öğretmen hesabı + üyelik/lisans yönetimi.
| Sütun | Tip | Not |
|---|---|---|
| `id` | int PK | |
| `ad_soyad`, `okul_adi`, `il`, `ilce`, `telefon`, `email` | varchar | |
| `kullanici_adi`, `sifre` | varchar | ⚠️ **şifre düz metin** |
| `kontenjan` | int | talep edilen öğrenci sayısı |
| `pin_kodu` | varchar(10) | **4 hane, benzersiz** — öğrenci kaydının anahtarı, admin onayında üretilir |
| `durum` | enum | `onay_bekliyor` / `onaylandi` / `reddedildi` |
| `bitis_tarihi` | datetime | **giriş kontrolü buna bakar** |
| `uyelik_bitis_tarihi` | date | kullanılmıyor gibi (ölü sütun) |
| `talep_edilen_ay` | int | süre uzatma talebi |
| `reset_token`, `reset_expires` | | şifre sıfırlama (1 saat geçerli) |
| `arena_bitis` | datetime | **PvP arenasının açık kalma saati** — `NULL` = kapalı, `2099-12-31` = süresiz |

### `siniflar`
`id`, `ogretmen_id`, `sinif_adi`, `olusturulma_tarihi`

### `ogrenciler`
| Sütun | Tip | Not |
|---|---|---|
| `id`, `ad_soyad` | | |
| `kullanici_adi`, `sifre` | | ⚠️ düz metin; benzersizlik **öğretmen bazında** |
| `ogretmen_id`, `sinif_id` | int | |
| `onay_durumu` | tinyint | 0 = öğretmen onayı bekliyor (giriş yapamaz) |
| `durum` | varchar | `aktif` / `onay_bekliyor` / `kilitli` / `uzaktan_onaylandi` / `uzaktan_reddedildi` |
| `kalici_puan` | int | **sıralama para birimi** — liderlik tabloları buna göre |
| `anlik_puan` | int | bölüm içinde biriken, onay bekleyen puan |
| `elmas` | int | **harcama para birimi** — market bunu düşer |
| `mevcut_bolum` | int | öğrencinin seviyesi (anti-hile kontrolü) |
| `can` | int | 0–100, savaşta azalır |
| `enerji` | int | 0–100, saldırıda azalır (arena 20, canavar 33) |
| `kalkan_bitis` | datetime | *kodda kullanılmıyor* |
| `son_dolum_tarihi` | datetime | **7 günde bir** girişte can/enerji 100'e döner |

---

## 📚 Görev (v1)

### `bolumler`
`id`, `bolum_adi`, `sira_no`

### `gorevler`
`id`, `bolum_id`, `baslik`, `sira_no`, `puan` (varsayılan 10)
İçerik alanları (hepsi `longtext`, Quill editörü HTML'i):
`icerik` (eski), `nasil_yaparim`, `gorev_metni`, `istenen_ekran`, `ipucu`

### `ogrenci_ilerleme`
`id`, `ogrenci_id`, `gorev_id`, `durum` enum(`beklemede`/`onaylandi`/`reddedildi`), `bekleyen_puan`
> ⚠️ `api/bolum_bitir.php` buraya `bolum_id` sütununa INSERT etmeye çalışıyor — **böyle bir sütun yok**, sorgu patlar.

---

## 🎒 Market & Avatar

### `esya_kutuphanesi`
| Sütun | Not |
|---|---|
| `id`, `esya_adi`, `aciklama` | |
| `kategori` | `karakter, yuz, sac, kafa, zirh, sag_el, sol_el, ayakkabi, binek, evcil_hayvan, saldiri_troll` |
| `resim_yolu` | `img/esyalar/<kategori>/<dosya>.png` (başında `../` YOK) |
| `fiyat` | elmas cinsinden |
| `seviye` | 1–4, **hasar ve stat çarpanı** |

### `ogrenci_envanter`
`id`, `ogrenci_id`, `esya_id`, `satin_alma_tarihi`
(Bir eşya `saldiri_troll` değilse öğrenci başına 1 satır; canavara saldırırken satır **silinir**.)

### `ogrenci_avatar_durum`
PK: `ogrenci_id` (1:1)
Slot sütunları: `karakter_id, yuz_id, sac_id, kafa_id, zirh_id, sag_el_id, sol_el_id, ayakkabi_id, binek_id, evcil_hayvan_id`
Diğer: `cinsiyet` enum(erkek/kiz), `genel_guc`, `can`, `enerji`, `ceviklik` (*bu 4'ü DB'de tutuluyor ama savaşta anlık hesaplanıyor — ölü veri*), `gunluk_savunma_sayisi`, `son_savunma_tarihi`

---

## ⚔️ Savaş

### `arena_savaslari` (PvP log)
`id`, `saldiran_id`, `savunan_id`, `kazanan_id`, `saldiran_hasar`, `savunan_hasar`, `tarih`
→ 30 günden eskiler her savaşta otomatik silinir.

### `dunya_canavari`
`id`, `adi`, `max_can`, `mevcut_can`, `durum` enum(`aktif`/`olu`), `savas_tarihi` varchar(7) = `YYYY-MM`, `savas_yapildi_mi`, `tur` (`yeni` = yumurta / `dev`), `olusturma_tarihi`

### `canavar_hasar_log`
`id`, `canavar_id`, `ogrenci_id`, `hasar_miktari`, `saldiri_tarihi`

---

## 🎯 Quiz & Düello

### `quiz_sorulari`
`id`, `bolum_id`, `zorluk_seviyesi` (varchar), `sure` (sn, vars. 20), `puan` (vars. 20), `soru_metni`, `soru_medya`,
`cevap_a_metni`…`cevap_d_metni` + `cevap_a_medya`…`cevap_d_medya`, `dogru_cevap` enum(A/B/C/D)

### `quiz_oturum`
`id`, `bolum_idler` (virgüllü: `"1,2,3"`), `zorluk_seviyesi` (virgüllü), `durum` enum(`bekliyor`/`aktif`/`bitti`), `baslangic_zaman`
> ⚠️ Öğretmene bağlı değil — **global**. Aynı anda tüm okullar aynı oturumu görür.

### `quiz_ogrenci`
`id`, `oturum_id`, `ogrenci_id`, `puan`, `cozulen_soru_sayisi`, `kalkan_aktif`,
güç hakları: `kilic_hakki`, `kalkan_hakki`, `glitch_hakki`, `saat_hakki` (her biri 1), `son_guncelleme`

### `quiz_savas_log`
`id`, `oturum_id`, `saldiran_id`, `savunan_id`, `yetenek_turu` enum(`kilic`/`glitch`/`saat`),
`durum` enum(`bekliyor`/`kalkan_kirdi`/`basarili`/`geri_tepti`),
`saldiran_puan_etkisi`, `savunan_puan_etkisi`, `savunan_gordu_mu`, `saldiran_gordu_mu`, `islem_tarihi`

### `quiz_duellolar`
`id`, `meydan_okuyan_id`, `meydan_okunan_id`, `soru_id`, `durum` (`bekliyor`/`kabul_edildi`/`reddedildi`/`bitti`),
`kazanan_id` → **özel değerler: `-1` = kimse bilemedi, `-2` = zaman aşımı**, `olusturma_tarihi`

### `duello_ayarlari`
**Tek satır (`id=1`) global şalter.** `durum` enum(`acik`/`kapali`), `bitis_zamani`

---

## 🚧 v2 Tabloları (SQL dökümünde YOK — koddan çıkarıldı)

### `v2_gorevler`
`id`, `bolum_id`, `gorev_adi`, `sira_no`, `puan`, `gorev_tipi` (`standart`), `icerik_json`
`icerik_json` yapısı:
```json
{
  "hikaye_metni": { /* Editor.js blokları */ },
  "blueprint": { "ana_cozum": "<xml>...</xml>", "alternatif_cozumler": ["<xml>…"] }
}
```

### `v2_kukla_kutuphanesi`
`id`, `kukla_adi`, `grup_adi`, `kostumler_json`
`kostumler_json` yapısı:
```json
[{ "id":"k_<ts>_1", "adi":"costume1",
   "resimYolu":"assets/images/kuklalar/kostum_xxx.svg",
   "yol":"assets/images/kuklalar/kostum_xxx.svg",
   "cizimData": null }]
```
> `resimYolu` (Kostüm Atölyesi okur) ve `yol` (öğrenci alt paneli okur) **aynı değeri iki kez** tutar — geriye dönük uyumluluk için.

---

## İlişki Şeması (özet)

```
ogretmenler ─1:N─ siniflar ─1:N─ ogrenciler
     │                                │
     │ (pin_kodu ile kayıt)           ├─1:1─ ogrenci_avatar_durum ──> esya_kutuphanesi (10 slot FK)
     │                                ├─1:N─ ogrenci_envanter ──────> esya_kutuphanesi
     │                                ├─1:N─ ogrenci_ilerleme ──────> gorevler
     │                                ├─1:N─ arena_savaslari (saldiran/savunan/kazanan)
     │                                ├─1:N─ canavar_hasar_log ─────> dunya_canavari
     │                                ├─1:N─ quiz_ogrenci ──────────> quiz_oturum
     │                                └─1:N─ quiz_duellolar (okuyan/okunan)
     └─ arena_bitis (PvP şalteri)

bolumler ─1:N─ gorevler
bolumler ─1:N─ quiz_sorulari
duello_ayarlari (tek satır, global)
```

> ⚠️ **Dökümde hiç FOREIGN KEY tanımı yok.** Bütünlük tamamen PHP tarafında. Öğrenci silinince `arena_savaslari`, `ogrenci_envanter`, `ogrenci_avatar_durum` satırları **yetim kalır**.
