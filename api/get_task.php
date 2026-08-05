<?php
/* ==========================================================
   Dosya Yolu: api/get_task.php
   İşlev: İstenen görevi bulup JavaScript'e JSON olarak döndürür

   NE İŞE YARAR (HALK DİLİNDE):
   Öğrenci "Sıradaki Görev" dediğinde, o görevin metnini, ipucunu ve
   sonraki görevin numarasını gönderen dosya.

   YENİ EKLENEN ÖZELLİKLER:
    - [2026-08] SEVİYE KİLİDİ (Bilinen Sorunlar #8):
        Eskiden oturum ve seviye kontrolü yoktu; adres çubuğuna gorev_id
        yazan herkes ileri bölümlerin görevlerini ve İPUÇLARINI okuyabiliyordu.
        Artık öğrenci yalnızca kendi mevcut_bolum seviyesine kadar olan
        görevleri çekebilir.
   ========================================================== */

require_once __DIR__ . '/../bilesenler/guvenlik.php';
require_once __DIR__ . '/../bilesenler/api_yanit.php';

$ogrenci_id = ogrenciZorunluApi();
$gorev_id   = isset($_GET['gorev_id']) ? (int) $_GET['gorev_id'] : 0;

if ($gorev_id <= 0) {
    apiHata('Görev numarası eksik.');
}

try {
    // 1. Öğrencinin ulaştığı seviye
    $st = $pdo->prepare("SELECT mevcut_bolum FROM ogrenciler WHERE id = ?");
    $st->execute([$ogrenci_id]);
    $ogrencinin_seviyesi = max(1, (int) ($st->fetchColumn() ?: 1));

    // 2. Görevi çek
    $st = $pdo->prepare("SELECT * FROM gorevler WHERE id = ?");
    $st->execute([$gorev_id]);
    $gorev = $st->fetch(PDO::FETCH_ASSOC);

    if (!$gorev) {
        apiHata('Görev bulunamadı.', 404);
    }

    // 3. ANTİ-HİLE: Öğrenci henüz ulaşmadığı bölümün görevini isteyemez
    if ((int) $gorev['bolum_id'] > $ogrencinin_seviyesi) {
        apiHata('Bu bölüme henüz ulaşmadınız. Önce önceki bölümleri tamamlayın.', 403);
    }

    // 4. Aynı bölümdeki bir sonraki görev
    $st = $pdo->prepare(
        "SELECT id FROM gorevler
          WHERE bolum_id = ? AND sira_no > ?
          ORDER BY sira_no ASC, id ASC LIMIT 1"
    );
    $st->execute([$gorev['bolum_id'], $gorev['sira_no']]);
    $sonraki = $st->fetch(PDO::FETCH_ASSOC);

    $gorev['son_gorev_mi']     = !$sonraki;
    $gorev['sonraki_gorev_id'] = $sonraki ? (int) $sonraki['id'] : null;

    apiJson(['success' => true, 'status' => 'success', 'task' => $gorev]);

} catch (Throwable $e) {
    apiIstisna($e, 'Görev yüklenemedi.');
}
