<?php
/* ====================================================================
 * MODÜL: Süper Admin Kimlik Doğrulama Yardımcısı
 * DOSYA YOLU: admin/super_auth.php
 * BAĞLI OLDUĞU ANA DOSYALAR: admin/super_giris.php, super_sifre_degistir.php,
 *                            ve korumalı tüm Süper Admin panelleri
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Patron hesabının şifresini kontrol eden, ilk kurulumda varsayılan hesabı
 * kendi kendine oluşturan ve şifre değiştirme işini yapan dosya.
 * Şifre asla düz metin saklanmaz — password_hash() ile şifrelenir.
 *
 * TEMEL İŞLEVLER:
 *  1. superAdminHazirla()      — tablo boşsa varsayılan 'super / 123' hesabını kurar
 *  2. superAdminGirisDene()    — kullanıcı adı + şifre doğrular, kilit uygular
 *  3. superAdminSifreDegistir()— mevcut şifreyi doğrulayıp yenisini yazar
 *  4. superAdminBilgi()        — oturumdaki adminin kaydını döndürür
 *
 * VARSAYILAN GİRİŞ: kullanıcı adı "super", şifre "123"
 *   -> İlk girişten sonra admin/super_sifre_degistir.php üzerinden DEĞİŞTİR.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (Bilinen Sorunlar #1).
 * ==================================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';

const SUPER_VARSAYILAN_KULLANICI = 'super';
const SUPER_VARSAYILAN_SIFRE     = '123';
const SUPER_MAX_DENEME           = 5;
const SUPER_KILIT_DAKIKA         = 10;

/* --------------------------------------------------------------------
 * 1. İLK KURULUM — tablo boşsa varsayılan hesabı oluştur
 * Böylece migration'a şifre hash'i gömmek gerekmez.
 * ------------------------------------------------------------------ */
function superAdminHazirla(PDO $pdo): void
{
    try {
        $sayi = (int) $pdo->query("SELECT COUNT(*) FROM super_adminler")->fetchColumn();
        if ($sayi > 0) {
            return;
        }

        $pdo->prepare(
            "INSERT INTO super_adminler (kullanici_adi, sifre_hash, ad_soyad)
             VALUES (?, ?, ?)"
        )->execute([
            SUPER_VARSAYILAN_KULLANICI,
            password_hash(SUPER_VARSAYILAN_SIFRE, PASSWORD_DEFAULT),
            'Süper Admin',
        ]);
    } catch (PDOException $e) {
        // Tablo henüz yoksa migration çalıştırılmamıştır — sessizce geç,
        // giriş sayfası kullanıcıyı uyaracak.
        error_log('[super_auth] superAdminHazirla: ' . $e->getMessage());
    }
}

/* --------------------------------------------------------------------
 * 2. GİRİŞ DENEMESİ
 * @return array ['basarili'=>bool, 'mesaj'=>string]
 * ------------------------------------------------------------------ */
function superAdminGirisDene(PDO $pdo, string $kullanici, string $sifre): array
{
    $kullanici = trim($kullanici);

    if ($kullanici === '' || $sifre === '') {
        return ['basarili' => false, 'mesaj' => 'Kullanıcı adı ve şifre boş bırakılamaz.'];
    }

    $st = $pdo->prepare("SELECT * FROM super_adminler WHERE kullanici_adi = ?");
    $st->execute([$kullanici]);
    $admin = $st->fetch(PDO::FETCH_ASSOC);

    // Kilitli mi?
    if ($admin && !empty($admin['kilit_bitis']) && strtotime($admin['kilit_bitis']) > time()) {
        $kalan = (int) ceil((strtotime($admin['kilit_bitis']) - time()) / 60);
        return ['basarili' => false, 'mesaj' => "Hesap geçici olarak kilitli. {$kalan} dakika sonra tekrar deneyin."];
    }

    // Kullanıcı yoksa bile aynı süre harcansın (kullanıcı adı sızdırmayı önler)
    $hash = $admin['sifre_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidin';

    if (!$admin || !password_verify($sifre, $hash)) {
        if ($admin) {
            $deneme = (int) $admin['basarisiz_deneme'] + 1;

            if ($deneme >= SUPER_MAX_DENEME) {
                $pdo->prepare(
                    "UPDATE super_adminler
                        SET basarisiz_deneme = 0, kilit_bitis = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                      WHERE id = ?"
                )->execute([SUPER_KILIT_DAKIKA, $admin['id']]);

                return ['basarili' => false,
                        'mesaj' => 'Çok fazla hatalı deneme. Hesap ' . SUPER_KILIT_DAKIKA . ' dakika kilitlendi.'];
            }

            $pdo->prepare("UPDATE super_adminler SET basarisiz_deneme = ? WHERE id = ?")
                ->execute([$deneme, $admin['id']]);

            $kalanHak = SUPER_MAX_DENEME - $deneme;
            return ['basarili' => false, 'mesaj' => "Kullanıcı adı veya şifre hatalı. Kalan hak: {$kalanHak}"];
        }

        return ['basarili' => false, 'mesaj' => 'Kullanıcı adı veya şifre hatalı.'];
    }

    // Şifreleme algoritması güncellendiyse hash'i tazele
    if (password_needs_rehash($admin['sifre_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare("UPDATE super_adminler SET sifre_hash = ? WHERE id = ?")
            ->execute([password_hash($sifre, PASSWORD_DEFAULT), $admin['id']]);
    }

    $pdo->prepare(
        "UPDATE super_adminler SET son_giris = NOW(), basarisiz_deneme = 0, kilit_bitis = NULL WHERE id = ?"
    )->execute([$admin['id']]);

    // Oturumu tazeleyip kimliği yaz
    oturumKimligiYenile();
    $_SESSION['super_admin_id']  = (int) $admin['id'];
    $_SESSION['super_admin_ad']  = $admin['ad_soyad'] ?: 'Süper Admin';
    $_SESSION['super_kullanici'] = $admin['kullanici_adi'];

    return ['basarili' => true, 'mesaj' => 'Giriş başarılı.'];
}

/* --------------------------------------------------------------------
 * 3. ŞİFRE DEĞİŞTİRME
 * ------------------------------------------------------------------ */
function superAdminSifreDegistir(PDO $pdo, int $adminId, string $mevcut, string $yeni, string $yeniTekrar): array
{
    if ($yeni !== $yeniTekrar) {
        return ['basarili' => false, 'mesaj' => 'Yeni şifreler birbiriyle eşleşmiyor.'];
    }
    if (strlen($yeni) < 3) {
        return ['basarili' => false, 'mesaj' => 'Yeni şifre en az 3 karakter olmalı.'];
    }

    $st = $pdo->prepare("SELECT sifre_hash FROM super_adminler WHERE id = ?");
    $st->execute([$adminId]);
    $mevcutHash = $st->fetchColumn();

    if (!$mevcutHash || !password_verify($mevcut, $mevcutHash)) {
        return ['basarili' => false, 'mesaj' => 'Mevcut şifreniz hatalı.'];
    }

    $pdo->prepare("UPDATE super_adminler SET sifre_hash = ? WHERE id = ?")
        ->execute([password_hash($yeni, PASSWORD_DEFAULT), $adminId]);

    return ['basarili' => true, 'mesaj' => 'Şifreniz başarıyla değiştirildi.'];
}

/* --------------------------------------------------------------------
 * 4. KULLANICI ADI DEĞİŞTİRME
 * ------------------------------------------------------------------ */
function superAdminKullaniciDegistir(PDO $pdo, int $adminId, string $yeniKullanici, string $sifre): array
{
    $yeniKullanici = trim($yeniKullanici);

    if ($yeniKullanici === '' || strlen($yeniKullanici) < 3) {
        return ['basarili' => false, 'mesaj' => 'Kullanıcı adı en az 3 karakter olmalı.'];
    }

    $st = $pdo->prepare("SELECT sifre_hash FROM super_adminler WHERE id = ?");
    $st->execute([$adminId]);
    $hash = $st->fetchColumn();

    if (!$hash || !password_verify($sifre, $hash)) {
        return ['basarili' => false, 'mesaj' => 'Şifreniz hatalı, kullanıcı adı değiştirilemedi.'];
    }

    $st = $pdo->prepare("SELECT id FROM super_adminler WHERE kullanici_adi = ? AND id <> ?");
    $st->execute([$yeniKullanici, $adminId]);
    if ($st->fetch()) {
        return ['basarili' => false, 'mesaj' => 'Bu kullanıcı adı zaten kullanımda.'];
    }

    $pdo->prepare("UPDATE super_adminler SET kullanici_adi = ? WHERE id = ?")
        ->execute([$yeniKullanici, $adminId]);

    $_SESSION['super_kullanici'] = $yeniKullanici;

    return ['basarili' => true, 'mesaj' => 'Kullanıcı adınız güncellendi.'];
}

/* --------------------------------------------------------------------
 * 5. OTURUMDAKİ ADMİNİN KAYDI
 * ------------------------------------------------------------------ */
function superAdminBilgi(PDO $pdo): ?array
{
    guvenliOturumBaslat();
    if (empty($_SESSION['super_admin_id'])) {
        return null;
    }
    $st = $pdo->prepare("SELECT id, kullanici_adi, ad_soyad, son_giris FROM super_adminler WHERE id = ?");
    $st->execute([$_SESSION['super_admin_id']]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Varsayılan şifre hâlâ kullanılıyor mu? Panelde uyarı göstermek için. */
function superAdminVarsayilanSifreMi(PDO $pdo, int $adminId): bool
{
    $st = $pdo->prepare("SELECT sifre_hash FROM super_adminler WHERE id = ?");
    $st->execute([$adminId]);
    $hash = $st->fetchColumn();
    return $hash ? password_verify(SUPER_VARSAYILAN_SIFRE, $hash) : false;
}
