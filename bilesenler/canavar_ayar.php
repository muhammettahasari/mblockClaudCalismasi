<?php
/* ====================================================================
 * MODÜL: Ayın Canavarı Ayarları
 * DOSYA YOLU: bilesenler/canavar_ayar.php
 * BAĞLI OLDUĞU ANA DOSYALAR:
 *    api/aylik_savas_motoru.php
 *    bilesenler/skorboard_modulleri/ayin_canavari.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Canavar sisteminin bütün ayarları burada. Eskiden "savaş günü" motorun
 * içinde 18, ekranda ise "ayın 1'i" yazıyordu; iki yer birbirini tutmuyordu.
 * Artık tek yerden değiştirilir.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] Dosya oluşturuldu (Bilinen Sorunlar #18 / metin.txt istekleri).
 * ==================================================================== */

/** Aylık savaşın yapıldığı gün (ayın kaçı). Motor bu günde çalışır. */
const CANAVAR_SAVAS_GUNU = 18;

/**
 * Zafer eşiği (%). Canavarın canı bu yüzdenin ALTINA inerse 'zafer' videosu,
 * inmezse 'canavar_kazandi' videosu oynar.
 */
const CANAVAR_ZAFER_ESIGI = 50;

/**
 * Canavar ÖLÜRSE: yeni yumurtanın canı = onu öldüren Türkiye gücü × bu çarpan.
 * 1.15 = her ay %15 daha zor.
 */
const CANAVAR_YUMURTA_CARPANI = 1.15;

/**
 * Canavar ÖLMEZSE: yumurtaya dönmez, dev olarak büyür.
 * yeni_max = eski_max + (türkiye_gücü × bu çarpan)
 */
const CANAVAR_BUYUME_CARPANI = 1.0;

/** Hiç öğrenci yokken bile canavarın en az sahip olacağı can. */
const CANAVAR_TABAN_CAN = 100000;

/**
 * Yumurta evresindeki canavarın görsel büyüme süresi (gün).
 * Ekrandaki "Büyüme %" çubuğu bu süreye göre dolar.
 */
const CANAVAR_BUYUME_SURESI = CANAVAR_SAVAS_GUNU;

/**
 * Savaşa kaç gün kaldığını hesaplar.
 * Savaş günü geçtiyse gelecek ayın savaş gününe kalan günü verir.
 */
function canavaraKalanGun(): int
{
    $bugun = (int) date('j');

    if ($bugun < CANAVAR_SAVAS_GUNU) {
        return CANAVAR_SAVAS_GUNU - $bugun;
    }
    if ($bugun === CANAVAR_SAVAS_GUNU) {
        return 0;
    }

    // Savaş geçti → gelecek ayın savaş gününe kalan
    $buAyinGunSayisi = (int) date('t');
    return ($buAyinGunSayisi - $bugun) + CANAVAR_SAVAS_GUNU;
}

/** Yumurta evresi için görsel büyüme yüzdesi (0-100). */
function canavarBuyumeYuzdesi(): int
{
    $bugun = (int) date('j');
    $oran  = ($bugun / max(1, CANAVAR_BUYUME_SURESI)) * 100;
    return (int) max(0, min(100, round($oran)));
}
