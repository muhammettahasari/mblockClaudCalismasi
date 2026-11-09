# mBlock Akademi Müfredatı (200 Görev)

> Üretici: `kurulum/mufredat_uretici/` (Python). Görevleri değiştirmek için `bolumler_*.py` dosyalarını düzenle,
> `python3 uret.py` çalıştır, çıkan `cikti/mufredat.sql` dosyasını phpMyAdmin'de çalıştır ve `cikti/proje/` altındaki
> dosyaları proje köküne kopyala. SQL tekrar çalıştırılabilir: görevler `icerik_json.mufredat.kod` (M001…M200) ile eşleşir,
> ikinci çalıştırmada kopya oluşmaz, mevcut görev güncellenir (öğrenci ilerlemesi korunur).

## Tasarım ilkeleri

- **Kolaydan zora:** her bölüm 1-3 yeni blok tanıtır; bölümün 10. görevi o bölümün "final" görevidir.
- **1-14. bölümler:** görev metninde renkli **Kod Planı** var (çocuk blokları tanıyarak dizer). **15-20. bölümler:** sadece kullanılacak bloklar listelenir, tam çözüm **💡 Bilgi Al**'da (ödül yarıya iner).
- **Araç kutusu sade:** her görevde sadece çözümün blokları + bölümün yeni blokları + önceki bölümlerden 3 çeldirici blok görünür.
- **Her görevin kendi kuklası ve dekoru var** (`v2_gorev_sistemi/assets/images/mufredat/k|d`). Kuklalar 2 kostümlüdür (`kostüm1`, `kostüm2`).
- **Puan:** 1-5. bölüm 10, 6-10. bölüm 15, 11-15. bölüm 20, 16-19. bölüm 25, 20. bölüm 40 puan.
- **Hakem toleransı:** sayı yazımı (10 / 10.0), metinde büyük-küçük harf ve noktalama farkı hata sayılmaz.

## 1. Bölüm: İlk Adımlar 🏳️

Yeşil bayrak ve "adım git" bloğuyla kuklaları yürütüyoruz.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M001 | Robo Uyanıyor | `olay_bayrak_tiklandiginda`, `hareket_git` |
| M002 | Balık Turu | — |
| M003 | Geri Vites | — |
| M004 | Kurbağa Zıplayışı | — |
| M005 | Roket Fırlatma | — |
| M006 | Kaygan Buz | — |
| M007 | Uzaylı Keşfi | — |
| M008 | Gol Vuruşu | — |
| M009 | Çalışkan Arı | — |
| M010 | İleri Geri | — |

## 2. Bölüm: Dönüşler 🧭

Sağa, sola ve istediğimiz yöne dönmeyi öğreniyoruz.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M011 | Sağa Dön Robo | `hareket_don_sag` |
| M012 | Meraklı Kuş | `hareket_don_sol` |
| M013 | Geri Dönüş | — |
| M014 | Köşeyi Dön | — |
| M015 | Sola Bak | `hareket_yone_don` |
| M016 | Yukarı Tırman | — |
| M017 | Üç Çeyrek Tur | — |
| M018 | Zıpla Kurbağa | — |
| M019 | Zikzak Yol | — |
| M020 | Kare Tur | — |

## 3. Bölüm: Konuşan Kuklalar 💬

"de" ve "düşün" bloklarıyla kuklalarımızı konuşturuyoruz.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M021 | İlk Selam | `gorunum_merhaba_de` |
| M022 | Kısa Selam | `gorunum_merhaba_de_sureli` |
| M023 | Düşünceli Hayalet | `gorunum_dusun` |
| M024 | Tanışma | — |
| M025 | Acıkan Ahtapot | `gorunum_dusun_sureli` |
| M026 | Geldim! | — |
| M027 | İki Arkadaş | — |
| M028 | Matematik Kurbağası | — |
| M029 | Yürüyen Düşünür | — |
| M030 | Sahne Gösterisi | — |

## 4. Bölüm: Koordinatlar 📍

Sahnede x (sağ-sol) ve y (yukarı-aşağı) ile yer bulmayı öğreniyoruz.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M031 | Tam Ortası | `hareket_xy_git` |
| M032 | Sol Üst Köşe | — |
| M033 | Sağa Kay | `hareket_x_yap` |
| M034 | Aşağı İn | `hareket_y_yap` |
| M035 | Biraz Sağa | `hareket_x_degistir` |
| M036 | Asansör | `hareket_y_degistir` |
| M037 | Köşeden Köşeye | — |
| M038 | Merdiven | — |
| M039 | L Harfi | — |
| M040 | Hazine Avı | — |

## 5. Bölüm: Süzülme ve Bekleme ⏳

Kuklaları yavaşça kaydırıyor ve zamanı kontrol ediyoruz.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M041 | Süzülen Bulut Kuşu | `hareket_suzul_xy` |
| M042 | Sabırlı Robot | `kontrol_bekle` |
| M043 | Işınlanan Hayalet | `hareket_rastgele_git` |
| M044 | Gezgin Balık | `hareket_suzul_rastgele` |
| M045 | Tepe Yolu | — |
| M046 | Hazırlan Başla | — |
| M047 | Fareyi Kovala | — |
| M048 | Uzun Yolculuk | — |
| M049 | Büyük Yarış | — |
| M050 | Kuşun Gezisi | — |

## 6. Bölüm: Tekrar Döngüsü 🔁

Aynı blokları defalarca yazmak yerine "tekrarla" kullanıyoruz.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M051 | Dört Adım | `kontrol_tekrarla` |
| M052 | Sıçrayan Top | — |
| M053 | Fırıldak | — |
| M054 | Adım Adım | — |
| M055 | Beşgen Dans | — |
| M056 | Saat Kolu | — |
| M057 | Git Gel | — |
| M058 | Yükselen Balon | — |
| M059 | Kare Devriye | — |
| M060 | Hip Hip Hurra | — |

## 7. Bölüm: Kostüm ve Boyut 👕

Kostüm değiştirerek animasyon yapıyor, kuklaları büyütüp küçültüyoruz.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M061 | Göz Kırpan Robot | `gorunum_sonraki_kilik` |
| M062 | Kanat Çırp | — |
| M063 | Ağzını Aç | `gorunum_kiligina_gec` |
| M064 | Dev Boyut | `gorunum_boyut_yap` |
| M065 | Biraz Büyü | `gorunum_boyut_degistir` |
| M066 | Şişen Balon Balık | — |
| M067 | Yürüyen Ördek Penguen | — |
| M068 | Küçül ve Geç | — |
| M069 | Hazırlık | — |
| M070 | Uzaklaşan Hayalet | — |

## 8. Bölüm: Sihirli Efektler ✨

Gizlenme, görünme, renk ve hayalet efektleriyle sihir yapıyoruz.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M071 | Kaybolma Numarası | `gorunum_gizle` |
| M072 | Geri Geldim | `gorunum_goster` |
| M073 | Gökkuşağı Canavarı | `gorunum_renk_etkisi_degistir` |
| M074 | Yarı Saydam | `gorunum_renk_etkisi_yap` |
| M075 | Yavaşça Kaybol | — |
| M076 | Temiz Sayfa | `gorunum_gorsel_temizle` |
| M077 | Öne Çık | `gorunum_katmana_git` |
| M078 | Yanıp Sönen Işık | — |
| M079 | Renk Sihri | — |
| M080 | Büyük Kaybolma | — |

## 9. Bölüm: Dekor ve Müzik 🎵

Arka planı değiştiriyor, ses ve nota çalıyoruz.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M081 | Gece Oldu | `gorunum_dekora_gec` |
| M082 | Mevsim Turu | `gorunum_sonraki_dekor` |
| M083 | Miyav! | `ses_cal_bitene_kadar` |
| M084 | Lazer Atışı | `ses_cal` |
| M085 | İlk Nota | `ses_nota_cal` |
| M086 | Do Re Mi | — |
| M087 | Akor Tekrarı | — |
| M088 | Davul Solosu | — |
| M089 | Büyük Final | — |
| M090 | Yıldız Şarkısı | — |

## 10. Bölüm: Klavye ve Tıklama ⌨️

Kuklaları klavyeyle yönetiyor, tıklayınca tepki vermelerini sağlıyoruz.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M091 | Sağa Yürü | `olay_tus_basildiginda` |
| M092 | Geri Yürü | — |
| M093 | İki Yöne | — |
| M094 | Yukarı Aşağı | — |
| M095 | Zıpla! | — |
| M096 | Gıdıklanan Canavar | `olay_kukla_tiklandiginda` |
| M097 | Balon Patlat | — |
| M098 | Yakala Beni | — |
| M099 | Dört Yön | — |
| M100 | Oyuna Hazır | — |

## 11. Bölüm: Sonsuz Döngü 🔄

"sürekli tekrarla" ile hiç durmayan hareketler yapıyoruz.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M101 | Durmayan Araba | `kontrol_surekli` |
| M102 | Sekme Topu | `hareket_kenardaysa_sek` |
| M103 | Ters Dönmesin | `hareket_donus_stili` |
| M104 | Döner Yıldız | — |
| M105 | Sonsuz Dans | — |
| M106 | Çapraz Top | — |
| M107 | Disko Işığı | — |
| M108 | Takipçi Arı | `hareket_hedefe_don` |
| M109 | Sihirli İmleç | — |
| M110 | Akvaryum | — |

## 12. Bölüm: Mesajlaşan Kuklalar 📢

Kuklalar birbirine mesaj göndererek birlikte çalışıyor.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M111 | Selam Mesajı | `olay_mesaj_yayinla`, `olay_mesaj_alindiginda` |
| M112 | Hep Birlikte Zıpla | — |
| M113 | Start Verildi | — |
| M114 | Sıra Sende | — |
| M115 | Dans Partisi | — |
| M116 | Kapı Zili | — |
| M117 | Hazine Bulundu | — |
| M118 | Orkestra | — |
| M119 | Işınlanma Portalı | — |
| M120 | Üç Perdelik Hikâye | — |

## 13. Bölüm: Kalemle Çizim ✏️

Kuklalar yürüdükleri yerde iz bırakıyor: şekiller çiziyoruz!

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M121 | İlk Çizgi | `kalem_bastir` |
| M122 | L Çizimi | — |
| M123 | Kare Çiz | — |
| M124 | Üçgen Çiz | — |
| M125 | Kesik Çizgi | `kalem_kaldir` |
| M126 | Dikdörtgen | — |
| M127 | Merdiven Çiz | — |
| M128 | Altıgen Bal Peteği | — |
| M129 | Temiz Başlangıç | `kalem_temizle` |
| M130 | İki Kare | — |

## 14. Bölüm: Renkli Sanat 🎨

Kalem rengi, kalınlık ve iç içe döngülerle sanat eserleri yapıyoruz.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M131 | Kırmızı Çizgi | `kalem_renk_yap` |
| M132 | Kalın Fırça | `kalem_kalinlik_yap` |
| M133 | Mavi Kare | — |
| M134 | Yıldız Çiz | — |
| M135 | Daire Çiz | — |
| M136 | Damga Sırası | `kalem_damga` |
| M137 | İncelen Çizgi | `kalem_kalinlik_degistir` |
| M138 | Kare Çiçek | — |
| M139 | Renkli Çizgiler | — |
| M140 | Sekizgen Rozet | — |

## 15. Bölüm: Eğer... İse 🤔

Kuklalar koşullara göre karar veriyor: "eğer şu olursa bunu yap".

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M141 | Duvar Bekçisi | `kontrol_eger_ise`, `algilama_degiyor_mu` |
| M142 | Basılı Tut | `algilama_tus_basili_mi` |
| M143 | Akıcı Kontrol | — |
| M144 | Bitiş Çizgisi | `algilama_renk_degiyor_mu` |
| M145 | Elma Avcısı | — |
| M146 | Dokunmatik Elmas | — |
| M147 | Labirent Gezgini | — |
| M148 | Bas ve Değiş | `algilama_fare_basili_mi` |
| M149 | Kanat Çırp | — |
| M150 | Hazine Avcısı | — |

## 16. Bölüm: Değilse ve Olana Kadar ⏸️

İki yollu kararlar veriyor, bir şey olana kadar tekrar ediyoruz.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M151 | Gülümseyen Uzaylı | `kontrol_eger_degilse` |
| M152 | Kenara Kadar | `kontrol_olana_kadar_tekrarla` |
| M153 | Bitişe Koş | — |
| M154 | Başlat Düğmesi | `kontrol_olana_kadar_bekle` |
| M155 | Şişir Söndür | — |
| M156 | Bayrağa Ulaş | — |
| M157 | Tıklayınca Kaç | — |
| M158 | Acil Fren | `kontrol_durdur` |
| M159 | Görünmez Hayalet | — |
| M160 | Lav Atlayıcı | — |

## 17. Bölüm: Matematik Sihri ➕

Toplama, çarpma, rastgele sayılar ve mantık işlemleri.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M161 | Hesap Makinesi | `islem_aritmetik` |
| M162 | Çarpım Tablosu | — |
| M163 | Şans Adımı | `islem_rastgele` |
| M164 | Rastgele Işınlan | — |
| M165 | Sihirli Boyut | — |
| M166 | Sağda mı Solda mı | `islem_karsilastirma`, `hareket_x_konumu` |
| M167 | Süper Zıplama | `islem_ve_veya` |
| M168 | Merhaba Dünya | `islem_birlestir` |
| M169 | Mayın Tarlası | — |
| M170 | Uykucu Kedi | `islem_degil` |

## 18. Bölüm: Değişkenler 📦

Skor, sayaç, can gibi değerleri hafızada tutuyoruz.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M171 | İlk Puan | `variables_set`, `math_change` |
| M172 | Tıklama Sayacı | — |
| M173 | Sayan Robot | `variables_get` |
| M174 | Hız Kontrolü | — |
| M175 | Can Hakkı | — |
| M176 | Kat Kat Yüksel | — |
| M177 | Üç Saniye Dans | `algilama_zamanlayici`, `algilama_zamanlayici_sifirla` |
| M178 | Elma Toplayıcı | — |
| M179 | Şişen Balon | — |
| M180 | Geri Sayım | — |

## 19. Bölüm: Soru-Cevap 💬

Kullanıcıya soru soruyor, cevaba göre karar veriyoruz.

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M181 | Adın Ne? | `algilama_sor_bekle`, `algilama_cevap` |
| M182 | Kaç Adım? | — |
| M183 | Kaç Tur? | — |
| M184 | Toplama Sorusu | — |
| M185 | Tek mi Çift mi | `islem_mod` |
| M186 | Yuvarlak Sayılar | `islem_yuvarla` |
| M187 | Yaşını Söyle | — |
| M188 | Renk Tahmini | — |
| M189 | İstediğin Boy | — |
| M190 | Çarpım Ustası | — |

## 20. Bölüm: Büyük Projeler 🏆

Öğrendiğin her şeyi birleştirip kendi oyunlarını yapıyorsun!

| Kod | Görev | Yeni bloklar |
|---|---|---|
| M191 | Labirentten Kaçış | — |
| M192 | Elma Yarışı | — |
| M193 | Uzay Savunması | `hareket_y_konumu` |
| M194 | Akvaryum Dünyası | — |
| M195 | Çizim Makinesi | — |
| M196 | Ritim Makinesi | — |
| M197 | Hayalet Kovalamaca | — |
| M198 | Zıplayan Top | — |
| M199 | Bilgi Yarışması | — |
| M200 | Mezuniyet Töreni | — |
