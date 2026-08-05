
# !!!!!!!!!!!!! SQL E KODLA BAĞLAN pip install pandas openpyxl mysql-connector-python BU KODU TERMİNALE YAPIŞTIR SONRA ÇALIŞTIR.
import os
import pandas as pd
import mysql.connector

# ---------------------------------------------------
# PROJE KLASÖRÜ
# Buraya projenizin ana klasör yolunu yazın
# Örnek: r"C:\xampp\htdocs\mblock_platform"
# ---------------------------------------------------

proje_klasoru = r"C:\xampp\htdocs\mblock_platform"


# ---------------------------------------------------
# VERİTABANI BAĞLANTI BİLGİLERİ
# XAMPP varsayılan ayarlar genelde şu şekildedir
# Eğer phpMyAdmin'e giriş yaparken farklı kullanıcı
# adı veya şifre kullanıyorsanız burayı değiştirin
# ---------------------------------------------------

db_host = "localhost"
db_user = "root"
db_password = ""   # XAMPP'te genelde boş olur

# ---------------------------------------------------
# VERİTABANI ADI
# Bu iki tırnak arasına kendi veritabanı adınızı yazın
# Sizin veritabanınız: mblock_egitim
# ---------------------------------------------------

db_name = "mblock_egitim"


# ---------------------------------------------------
# DOSYA TARAYICI AYARLARI
# Listeye dahil edilmeyecek dosya uzantıları
# ---------------------------------------------------

haric_uzantilar = [".png", ".jpg", ".jpeg"]


# ---------------------------------------------------
# DOSYA LİSTESİNİ TOPLAMA
# ---------------------------------------------------

dosya_verileri = []

for root, dirs, files in os.walk(proje_klasoru):

    # .git klasörünü tarama
    if ".git" in dirs:
        dirs.remove(".git")

    for f in files:

        uzanti = os.path.splitext(f)[1].lower()

        # resim dosyalarını atla
        if uzanti in haric_uzantilar:
            continue

        dosya_verileri.append([root, f])


df_dosyalar = pd.DataFrame(dosya_verileri, columns=["Klasor", "Dosya"])


# ---------------------------------------------------
# VERİTABANI YAPISINI ÇEKME
# ---------------------------------------------------

baglanti = mysql.connector.connect(
    host=db_host,
    user=db_user,
    password=db_password,
    database=db_name
)

cursor = baglanti.cursor()

# ---------------------------------------------------
# Bu sorgu sadece veritabanı yapısını okur
# Hiçbir veriyi değiştirmez veya silmez
# ---------------------------------------------------

sorgu = """
SELECT 
TABLE_NAME,
COLUMN_NAME,
COLUMN_TYPE,
COLUMN_KEY
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = %s
ORDER BY TABLE_NAME, ORDINAL_POSITION
"""

cursor.execute(sorgu, (db_name,))

veritabani_verileri = []

for tablo, kolon, tip, anahtar in cursor.fetchall():
    veritabani_verileri.append([tablo, kolon, tip, anahtar])

df_veritabani = pd.DataFrame(
    veritabani_verileri,
    columns=["Tablo", "Kolon", "Tip", "Anahtar"]
)

cursor.close()
baglanti.close()


# ---------------------------------------------------
# EXCEL RAPORU OLUŞTURMA
# ---------------------------------------------------

# Bu dosya script ile aynı klasöre oluşturulur
excel_dosya_adi = "proje_analiz_raporu.xlsx"

with pd.ExcelWriter(excel_dosya_adi) as writer:
    df_dosyalar.to_excel(writer, sheet_name="Dosyalar", index=False)
    df_veritabani.to_excel(writer, sheet_name="Veritabani", index=False)

print("Excel raporu oluşturuldu:", excel_dosya_adi)