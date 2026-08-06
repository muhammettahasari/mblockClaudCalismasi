<?php
/* ====================================================================
 * MODÜL: Gizemli Market (Öğrenci)
 * DOSYA: market.php
 *
 * NE İŞE YARAR (HALK DİLİNDE):
 * Öğrencinin görevlerden kazandığı 💎 elmaslarla avatarına eşya aldığı vitrin.
 *
 * YENİ EKLENEN ÖZELLİKLER:
 *  - [2026-08] YIKICI TEMİZLİK DÖNGÜSÜ KALDIRILDI (Bilinen Sorunlar #9):
 *      Sayfanın en üstünde, HER açılışta bütün eşyaların resmi diskte var mı
 *      diye tek tek kontrol edilip bulunamayanlar KALICI olarak siliniyordu
 *      (envanterden ve avatarlardan da). Bu hem ~120 disk okuması demekti hem
 *      de klasör bir kez erişilemez olsa tüm eşya kütüphanesi uçabilirdi.
 *      Bu bakım işi artık admin/super_bakim.php içinde, önce RAPOR gösteren
 *      ve onay isteyen güvenli bir sürüm olarak duruyor.
 *  - [2026-08] Sıralama seçeneği beyaz listeye alındı (SQL'e gömülüyordu).
 * ==================================================================== */

require_once __DIR__ . '/bilesenler/guvenlik.php';
ogrenciZorunlu();

$ogrenci_id = (int) $_SESSION['ogrenci_id'];

// Öğrenci bakiyesi
$puan_sorgu = $pdo->prepare("SELECT elmas, ad_soyad FROM ogrenciler WHERE id = ?");
$puan_sorgu->execute([$ogrenci_id]);
$ogrenci = $puan_sorgu->fetch();
$bakiye  = (int) ($ogrenci['elmas'] ?? 0);

/* --- SIRALAMA: kullanıcı girdisi doğrudan SQL'e gömüldüğü için beyaz liste --- */
$siralamaSecenekleri = [
    'yeni'          => 'id DESC',
    'eski'          => 'id ASC',
    'seviye_yuksek' => 'seviye DESC',
    'seviye_dusuk'  => 'seviye ASC',
    'fiyat_dusuk'   => 'fiyat ASC',
    'fiyat_yuksek'  => 'fiyat DESC',
    'ad_az'         => 'esya_adi ASC',
    'ad_za'         => 'esya_adi DESC',
];

$sirala       = $_GET['sirala'] ?? 'yeni';
$sql_siralama = 'ORDER BY ' . ($siralamaSecenekleri[$sirala] ?? $siralamaSecenekleri['yeni']);

$esyalar = $pdo->query("SELECT * FROM esya_kutuphanesi $sql_siralama")->fetchAll(PDO::FETCH_ASSOC);

$envanter_sorgu = $pdo->prepare("SELECT esya_id FROM ogrenci_envanter WHERE ogrenci_id = ?");
$envanter_sorgu->execute([$ogrenci_id]);
$sahip_olunanlar = $envanter_sorgu->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Silah ve Büyü Marketi</title>
    <style>
        body {
            background: #0f172a;
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            color: white;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .header {
            background: #1e293b;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            z-index: 100;
        }

        .bakiye-kutusu {
            background: #10b981;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 20px;
        }

        .btn-geri {
            background: #3b82f6;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
        }

        .market-layout {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* SOL MENÜ (KATEGORİLER) */
        .sidebar-menu {
            width: 250px;
            background: #1e293b;
            padding: 20px;
            overflow-y: auto;
            border-right: 2px solid #334155;
        }

        .kategori-btn {
            display: block;
            width: 100%;
            text-align: left;
            background: transparent;
            color: #cbd5e1;
            border: none;
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: bold;
            transition: 0.2s;
        }

        .kategori-btn:hover,
        .kategori-btn.aktif {
            background: #3b82f6;
            color: white;
        }

        /* SAĞ İÇERİK (EŞYALAR) */
        .market-container {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .grid-layout {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }

       /* 1. EŞYA KARTI (Düzeni korumak için flex yapısı) */
        .esya-kart { background: #1e293b; border: 2px solid #334155; border-radius: 16px; padding: 15px; text-align: center; transition: 0.3s; display: flex; flex-direction: column; justify-content: space-between; }
        .esya-kart:hover { transform: translateY(-5px); border-color: #f59e0b; }
        /* KUTULARIN ÜZERİNDEKİ TÜR ETİKETİ */
        .kategori-etiket { position: absolute; top: 8px; left: 8px; background: rgba(0, 0, 0, 0.7); color: #cbd5e1; padding: 4px 8px; font-size: 11px; border-radius: 6px; font-weight: bold; z-index: 10; border: 1px solid #475569; pointer-events: none; }

        /* 2. ARKA PLAN KAMERASI KUTUSU (Asla taşmaz, boşluk bırakmaz) */
        .esya-resim-kutu {
            width: 100%;
            height: 140px;
            border-radius: 8px;
            background-color: rgba(0,0,0,0.3);
            background-repeat: no-repeat;
            margin-bottom: 12px;
        }

        /* 3. KATEGORİLERE ÖZEL ODAKLANMA VE YAKINLAŞTIRMA (Zoom) */
        .bg-kafa { background-position: 50% 25%; background-size: 400%; }
        .bg-sac { background-position: 50% 10%; background-size: 600%; }
        .bg-sag_el { background-position: 55% 90%; background-size: 300%; } /* Sol taraftaki kılıç */
        .bg-sol_el { background-position: 60% 67%; background-size: 330%; } /* Sağ taraftaki kalkan */
        .bg-ayakkabi { background-position: 50% 95%; background-size: 500%; }
        .bg-zirh { background-position: 50% 50%; background-size: 250%; }
.bg-yuz { background-position: 50% 25%; background-size: 400%; }
        .bg-binek { background-position: -20% 80%; background-size: 150%; } /* Sol alttaki binek */
        .bg-evcil_hayvan { background-position: 90% 90%; background-size: 250%; } /* Sağ alttaki hayvan */
        .bg-karakter { background-position: 50% 70%; background-size: 110%; }
        .bg-saldiri_troll { background-position: center; background-size: contain; }


        .btn-satin-al {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn-aktif {
            background: #f59e0b;
            color: white;
        }

        .btn-yetersiz {
            background: #475569;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .btn-sahip {
            background: #10b981;
            color: white;
            cursor: not-allowed;
        }
    </style>
</head>

<body>
<?php $KILIT_KOK = ''; require __DIR__ . '/bilesenler/ogrenci_kilit_perdesi.php'; ?>


    <div class="header">
       <div style="display: flex; gap: 15px;">
            <a href="ogrenci_paneli.php" class="btn-geri">⬅️ Maceraya Dön</a>
            <a href="profil.php" class="btn-geri" style="background: #8b5cf6;">🛡️ Savaşçını Tasarla</a>
        </div>
     <h1 style="margin: 0; color: #f59e0b;">🛒 GİZEMLİ MARKET</h1>
        
        <div style="display: flex; gap: 15px; align-items: center;">
            <form method="GET" id="filtreForm">
                <select name="sirala" onchange="document.getElementById('filtreForm').submit();" style="padding: 10px; border-radius: 8px; border: 2px solid #cbd5e1; font-weight: bold; color: #334155; cursor: pointer; outline: none;">
                    <option value="yeni" <?= (isset($_GET['sirala']) && $_GET['sirala'] == 'yeni') ? 'selected' : '' ?>>🌟 En Son Eklenenler</option>
                    <option value="seviye_yuksek" <?= (isset($_GET['sirala']) && $_GET['sirala'] == 'seviye_yuksek') ? 'selected' : '' ?>>⚔️ Seviye (En Yüksek)</option>
                    <option value="seviye_dusuk" <?= (isset($_GET['sirala']) && $_GET['sirala'] == 'seviye_dusuk') ? 'selected' : '' ?>>🛡️ Seviye (En Düşük)</option>
                    <option value="fiyat_dusuk" <?= (isset($_GET['sirala']) && $_GET['sirala'] == 'fiyat_dusuk') ? 'selected' : '' ?>>💎 Fiyat (Ucuzdan Pahalıya)</option>
                    <option value="fiyat_yuksek" <?= (isset($_GET['sirala']) && $_GET['sirala'] == 'fiyat_yuksek') ? 'selected' : '' ?>>👑 Fiyat (Pahalıdan Ucuza)</option>
                    <option value="ad_az" <?= (isset($_GET['sirala']) && $_GET['sirala'] == 'ad_az') ? 'selected' : '' ?>>🔤 İsim (A'dan Z'ye)</option>
                    <option value="ad_za" <?= (isset($_GET['sirala']) && $_GET['sirala'] == 'ad_za') ? 'selected' : '' ?>>🔤 İsim (Z'den A'ya)</option>
                </select>
                <?php if(isset($_GET['kat'])): ?>
                    <input type="hidden" name="kat" value="<?= htmlspecialchars($_GET['kat']) ?>">
                <?php endif; ?>
            </form>
            <div class="bakiye-kutusu">Cüzdan: <span id="guncelBakiye"><?= $bakiye ?></span> 💎 Elmas</div>
        </div>
    </div>

    <div class="market-layout">
        <div class="sidebar-menu">
            <h3 style="color: #94a3b8; margin-top: 0; border-bottom: 1px solid #334155; padding-bottom: 10px;">Kategoriler</h3>
            <button class="kategori-btn aktif" onclick="filtrele('hepsi', this)">🌟 Tüm Eşyalar</button>
            <button class="kategori-btn" onclick="filtrele('karakter', this)">🧍 Karakter Bedenleri</button>
            <button class="kategori-btn" onclick="filtrele('zirh', this)">🛡️ Göğüs Zırhları</button>
<button class="kategori-btn" onclick="filtrele('yuz', this)">🧔 Yüz ve İfadeler</button>
            <button class="kategori-btn" onclick="filtrele('kafa', this)">🪖 Şapka ve Miğfer</button>
            <button class="kategori-btn" onclick="filtrele('sag_el', this)">🗡️ Sağ El (Silah)</button>
            <button class="kategori-btn" onclick="filtrele('sol_el', this)">🛡️ Sol El (Kalkan)</button>
            <button class="kategori-btn" onclick="filtrele('ayakkabi', this)">🥾 Ayakkabılar</button>
            <button class="kategori-btn" onclick="filtrele('binek', this)">🐎 Binek Hayvanlar</button>
            <button class="kategori-btn" onclick="filtrele('evcil_hayvan', this)">🐺 Evcil Hayvanlar</button>
            <button class="kategori-btn" onclick="filtrele('saldiri_troll', this)">🔮 Troll Büyüleri</button>
        </div>

        <div class="market-container">
            <div class="grid-layout" id="esyaListesi">
                <?php foreach ($esyalar as $esya):
                    $zaten_sahip_mi = in_array($esya['id'], $sahip_olunanlar);
                    $bakiye_yeterli_mi = ($bakiye >= $esya['fiyat']);

                    if ($zaten_sahip_mi && $esya['kategori'] !== 'saldiri_troll') {
                        $btn = "<button class='btn-satin-al btn-sahip' disabled>✅ Sende Var</button>";
                 } elseif (!$bakiye_yeterli_mi) {
                        $btn = "<button class='btn-satin-al btn-yetersiz' disabled>❌ Elmas Yetersiz</button>";
                    } else {
                        $btn = "<button class='btn-satin-al btn-aktif' onclick=\"satinAl({$esya['id']}, '{$esya['esya_adi']}')\" id='btn_{$esya['id']}'>Satın Al</button>";
                    }
                ?>
                    <div class="esya-kart" data-kategori="<?= $esya['kategori'] ?>">
                    
                  <?php
                    $etiketler = [
                        'sag_el' => '🗡️ Sağ El', 'sol_el' => '🛡️ Sol El', 'kafa' => '🪖 Miğfer',
                        'binek' => '🐎 Binek', 'evcil_hayvan' => '🐺 Evcil Hayvan', 'sac' => '💇 Saç',
                        'saldiri_troll' => '🔮 Büyü', 'karakter' => '🧍 Beden', 'ayakkabi' => '🥾 Ayakkabı', 'zirh' => '🛡️ Zırh', 'yuz' => '🧔 Yüz'
                    ];
                    ?>
                    <div class="esya-resim-kutu bg-<?= $esya['kategori'] ?>" style="background-image: url('<?= htmlspecialchars($esya['resim_yolu']) ?>');">
                        <div class="kategori-etiket"><?= $etiketler[$esya['kategori']] ?? 'Eşya' ?></div>
                    </div>
                    
                   <div>
                        <h3 style="font-size: 16px; margin: 5px 0; color: #f8fafc;">
                            <?php 
                                // Seviye 5 ise Destansı yazsın, değilse Yıldız ve Seviye numarası koysun
                                $seviye_yazisi = $esya['seviye'] == 5 ? '👑 Destansı' : '⭐ ' . $esya['seviye'] . '. Seviye';
                            ?>
                            <span style="display:block; font-size:12px; color:#94a3b8; margin-bottom:4px; font-weight:normal;"><?= $seviye_yazisi ?></span>
                            <?= htmlspecialchars($esya['esya_adi']) ?>
                        </h3>
                        <div style="font-size: 18px; font-weight: bold; color: #f59e0b; margin: 10px 0;">💎 <?= $esya['fiyat'] ?> Elmas</div>
                    </div>
                    
                    <?= $btn ?>

                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Kategori Filtreleme Motoru
        function filtrele(kategori, btnEl) {
            document.querySelectorAll('.kategori-btn').forEach(b => b.classList.remove('aktif'));
            btnEl.classList.add('aktif');

            document.querySelectorAll('.esya-kart').forEach(kart => {
                if (kategori === 'hepsi' || kart.getAttribute('data-kategori') === kategori) {
                    kart.style.display = 'block';
                } else {
                    kart.style.display = 'none';
                }
            });
        }

        // Satın Alma Motoru
       function satinAl(esyaId, esyaAdi) {
            if (!confirm(`"${esyaAdi}" adlı eşyayı satın almak istiyor musun?`)) return;
            fetch('api/satin_al.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ esya_id: esyaId })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert("🎉 Eşya hazine sandığına eklendi.");
                        let aktifKategori = document.querySelector('.kategori-btn.aktif') ? document.querySelector('.kategori-btn.aktif').getAttribute('onclick').match(/'([^']+)'/)[1] : 'hepsi';
                        window.location.href = 'market.php?kat=' + aktifKategori;
                    } else alert("❌ Hata: " + data.message);
                });
        }

        // Sayfa yüklendiğinde URL'de seçili bir kategori varsa otomatik olarak onu aç
        document.addEventListener('DOMContentLoaded', () => {
            let urlParams = new URLSearchParams(window.location.search);
            let aktifKat = urlParams.get('kat');
            if (aktifKat) {
                let btn = document.querySelector(`button[onclick*="'${aktifKat}'"]`);
                if(btn) filtrele(aktifKat, btn);
            }
        });
    </script>


<script>
        const marketMuzigi = new Audio('assets/sounds/market_sound.mp3');
        marketMuzigi.loop = true;  // Müzik bitince başa sarar
        marketMuzigi.volume = 0.3; // Sakince, arkaplanda yormadan çalması için ses seviyesi (%30)

        // Sayfa açılır açılmaz çalmayı dene
        marketMuzigi.play().catch(() => {
            // Tarayıcının güvenlik duvarı otomatik sesi engellerse, öğrenci ekrana (herhangi bir yere) tıkladığı an müziği başlat
            document.body.addEventListener('click', () => {
                if (marketMuzigi.paused) marketMuzigi.play();
            }, { once: true });
        });
    </script>
<?php include 'api/quiz_baskin.php'; ?>
<?php include 'bilesenler/duello_radari.php'; ?>
</body>

</html>