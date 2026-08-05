<style>
    /* 1. MİNYATÜR SAHNE ÇERÇEVESİ */
    .envanter-resim-cerceve {
        width: 100%;
        height: 75px;
        border-radius: 6px;
        margin-bottom: 5px;
        background-color: rgba(255, 255, 255, 0.05);
        overflow: hidden;
        /* Taşan kısımları jilet gibi keser */
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* 2. GİZLİ MARKET KUTUSU (Senin ayarlarının bozulmaması için) */
    .envanter-resim-kutu {
        width: 220px;
        /* Marketteki kutu genişliğini taklit eder */
        height: 140px;
        /* Marketteki kutu yüksekliğini taklit eder */
        background-repeat: no-repeat;
        transform: scale(0.50);
        /* O dev kutuyu 75 piksellik alana sığacak kadar küçültürüz */
        flex-shrink: 0;
    }

    /* 3. SENİN BELİRLEDİĞİN KUSURSUZ KAMERA AYARLARI */
    .bg-kafa {
        background-position: 50% 25%;
        background-size: 400%;
    }

    .bg-sac {
        background-position: 50% 10%;
        background-size: 600%;
    }

    .bg-sag_el {
        background-position: 55% 90%;
        background-size: 300%;
    }

    .bg-sol_el {
        background-position: 60% 67%;
        background-size: 330%;
    }

    .bg-ayakkabi {
        background-position: 50% 95%;
        background-size: 500%;
    }

    .bg-binek {
        background-position: -20% 80%;
        background-size: 150%;
    }

    .bg-evcil_hayvan {
        background-position: 90% 90%;
        background-size: 250%;
    }

    .bg-karakter {
        background-position: 50% 70%;
        background-size: 110%;
    }

    .bg-saldiri_troll {
        background-position: center;
        background-size: contain;
    }
    .bg-zirh {
        background-position: 50% 50%;
        background-size: 250%;
    }

    .bg-yuz {
        background-position: 50% 25%;
        background-size: 400%;
        filter: drop-shadow(0 0 3px rgba(255, 255, 255, 0.8));
    }

    /* İKONLU FİLTRE MENÜSÜ CSS */
    .envanter-filtre-kutu {
        display: flex;
        gap: 8px;
        margin-bottom: 15px;
        overflow-x: auto;
        padding-bottom: 8px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .btn-filtre {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid #475569;
        border-radius: 8px;
        cursor: pointer;
        padding: 6px 12px;
        font-size: 18px;
        transition: 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .btn-filtre:hover,
    .btn-filtre.aktif {
        background: #3b82f6;
        border-color: #60a5fa;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
    }
</style>

<?php
// HOVER (Title) İÇİN TÜRKÇE KATEGORİ İSİMLERİ
$kategori_isimleri = [
    'sag_el' => '🗡️ Sağ El (Silah)',
    'sol_el' => '🛡️ Sol El (Kalkan)',
    'kafa' => '🪖 Şapka / Miğfer',
    'binek' => '🐎 Binek Hayvan',
    'evcil_hayvan' => '🐺 Evcil Hayvan',
    'sac' => '💇 Saç',
    'saldiri_troll' => '🔮 Büyü',
    'karakter' => '🧍 Beden',
    'ayakkabi' => '🥾 Ayakkabı'
];
?>

<div class="inventory-area">
    <h2 style="margin-top: 0; color: #f59e0b; padding-bottom: 10px;">📦 Hazine Sandığın</h2>

    <div class="envanter-filtre-kutu">
        <button class="btn-filtre aktif" onclick="envanterFiltrele('hepsi', this)" title="Tüm Eşyalar">🌟</button>
        <button class="btn-filtre" onclick="envanterFiltrele('karakter', this)" title="Bedenler">🧍</button>
        <button class="btn-filtre" onclick="envanterFiltrele('kafa', this)" title="Şapka / Miğfer">🪖</button>
        <button class="btn-filtre" onclick="envanterFiltrele('sag_el', this)" title="Sağ El (Silah)">🗡️</button>
        <button class="btn-filtre" onclick="envanterFiltrele('sol_el', this)" title="Sol El (Kalkan)">🛡️</button>
        <button class="btn-filtre" onclick="envanterFiltrele('ayakkabi', this)" title="Ayakkabılar">🥾</button>
        <button class="btn-filtre" onclick="envanterFiltrele('binek', this)" title="Binek Hayvanlar">🐎</button>
        <button class="btn-filtre" onclick="envanterFiltrele('evcil_hayvan', this)" title="Evcil Hayvanlar">🐺</button>
        <button class="btn-filtre" onclick="envanterFiltrele('zirh', this)" title="Göğüs Zırhları">🛡️</button>
        <button class="btn-filtre" onclick="envanterFiltrele('yuz', this)" title="Yüz ve İfadeler">🧔</button>
    </div>

    <div class="inventory-grid" id="envanterListesi">
        <?php if (count($sahip_olunan_esyalar) > 0): ?>
            <?php foreach ($sahip_olunan_esyalar as $esya): ?>
                <div class="item-card envanter-kart" draggable="true" ondragstart="drag(event)"
                    data-id="<?= $esya['id'] ?>"
                    data-kategori="<?= $esya['kategori'] ?>"
                    data-resim="<?= htmlspecialchars($esya['resim_yolu']) ?>"
                    title="<?= $kategori_isimleri[$esya['kategori']] ?? 'Eşya' ?>">

                    <div class="envanter-resim-cerceve">
                        <div class="envanter-resim-kutu bg-<?= $esya['kategori'] ?>" style="background-image: url('<?= htmlspecialchars($esya['resim_yolu']) ?>');"></div>
                    </div>

                    <p><?= htmlspecialchars($esya['esya_adi']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #94a3b8; grid-column: 1 / -1; text-align: center; margin-top: 50px;">Sandığın bomboş!</p>
        <?php endif; ?>
    </div>
</div>

<script>
    function envanterFiltrele(kategori, btnEl) {
        document.querySelectorAll('.btn-filtre').forEach(b => b.classList.remove('aktif'));
        btnEl.classList.add('aktif');

        document.querySelectorAll('.envanter-kart').forEach(kart => {
            if (kategori === 'hepsi' || kart.getAttribute('data-kategori') === kategori) {
                kart.style.display = 'block';
            } else {
                kart.style.display = 'none';
            }
        });
    }
</script>