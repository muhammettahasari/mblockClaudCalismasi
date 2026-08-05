/* ==========================================================
   YENİ VE KUSURSUZ Dosya Yolu: js/progress_manager.js
   İşlev: Görev geçişleri, eksiksiz veri basımı, İpucu Sistemi
   ========================================================== */
// --- SES MOTORU ---
var ogretmenUyariSesi = new Audio('assets/sounds/ogretmenuyari.mp3');

let hataSayaci = 0;
 
let ajanTuzagiAktif = false;
let syncInterval;

// --- 🛡️ GÜVENLİK DUVARI: PHP'den değişkenler boş gelirse çökmeyi önle ---
let safePendingScore = 0;
if (typeof currentPendingScore !== "undefined" && !isNaN(currentPendingScore)) {
  safePendingScore = currentPendingScore;
} else {
  // PHP boş gönderdiyse çökme, ekranda yazan puanı (Turuncu Kutu) bulup al!
  let valP = document.getElementById("valPending");
  safePendingScore = valP ? parseInt(valP.innerText) || 0 : 0;
}

let safeOgrenciId =
  typeof currentOgrenciId !== "undefined" ? currentOgrenciId : 0;
let safeBolumId = typeof currentBolumId !== "undefined" ? currentBolumId : 1;
let safeIlkGorevId = typeof ilkGorevId !== "undefined" ? ilkGorevId : 0;
// -------------------------------------------------------------------------

// 1. DİNAMİK VE KUSURSUZ GÖREV GEÇİŞİ
const progressDots = document.querySelectorAll(".progress-dot");
const taskSequence = Array.from(progressDots).map((dot) =>
  dot.id.replace("dot_", ""),
);

let enIleriGorevIndex = Array.from(progressDots).findIndex(
  (dot) =>
    dot.style.backgroundColor === "rgb(245, 158, 11)" ||
    dot.style.backgroundColor === "#f59e0b",
);
if (enIleriGorevIndex === -1) enIleriGorevIndex = 0;

function loadTask(taskId, isReview = false) {
  fetch("api/get_task.php?gorev_id=" + taskId)
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        document.getElementById("taskTitle").innerText =
          "📝 " + data.task.baslik;
        document.getElementById("puanDegeri").innerText = data.task.puan;
        document.getElementById("alanNasil").innerHTML =
          data.task.nasil_yaparim ||
          '<span style="color: #94a3b8;">Açıklama yok.</span>';
        document.getElementById("alanGorev").innerHTML =
          data.task.gorev_metni ||
          '<span style="color: #94a3b8;">Görev metni bulunamadı.</span>';
        document.getElementById("alanEkran").innerHTML =
          data.task.istenen_ekran ||
          '<span style="color: #94a3b8;">Görsel eklenmemiş.</span>';
        document.getElementById("ipucuAcikArayuz").innerHTML =
          data.task.ipucu ||
          '<span style="color: #94a3b8;">İpucu bulunmuyor.</span>';

        const ipucuKapali = document.getElementById("ipucuKapaliArayuz");
        const ipucuAcik = document.getElementById("ipucuAcikArayuz");
        if (ipucuKapali) ipucuKapali.style.display = "block";
        if (ipucuAcik) ipucuAcik.style.display = "none";

        const btnNextTask = document.getElementById("btnNextTask");
        const btnComplete = document.getElementById("btnComplete");

        if (isReview) {
          if (btnNextTask) btnNextTask.style.display = "none";
          if (btnComplete) btnComplete.style.display = "none";
        } else {
          if (btnNextTask) {
            btnNextTask.setAttribute("data-current-id", data.task.id);
            btnNextTask.setAttribute("data-puan", data.task.puan);

            if (data.task.son_gorev_mi) {
              btnNextTask.style.display = "none";
              if (btnComplete) {
                btnComplete.setAttribute("data-puan", data.task.puan);
                btnComplete.style.display = "inline-block";
              }
            } else {
              btnNextTask.setAttribute(
                "data-next-id",
                data.task.sonraki_gorev_id,
              );
              btnNextTask.style.display = "inline-block";
              if (btnComplete) btnComplete.style.display = "none";
            }
          }
        }
      } else {
        alert("Görev çekilirken bir hata oluştu: " + data.message);
      }
    })
    .catch((err) => {
      console.error("API Hatası:", err);
      alert(
        "Sistemsel Uyarı: Bu görev yüklenemedi! Muhtemelen göreve eklenen resim/GIF boyutu çok yüksek. Lütfen öğretmeninizden o görselleri küçültmesini isteyin.",
      );
    });
}

progressDots.forEach((dot, index) => {
  dot.addEventListener("click", function () {
    if (index > enIleriGorevIndex) return;

    const taskId = this.id.replace("dot_", "");
    const isReview = index < enIleriGorevIndex;

    loadTask(taskId, isReview);
  });
});

const btnNextTask = document.getElementById("btnNextTask");
if (btnNextTask) {
  btnNextTask.addEventListener("click", function () {
    const currentTaskId = this.getAttribute("data-current-id");
    const currentIndex = taskSequence.indexOf(currentTaskId);

    if (currentIndex === -1 || currentIndex >= taskSequence.length - 1) return;

    const nextTaskId = taskSequence[currentIndex + 1];
    const taskPoints = parseInt(this.getAttribute("data-puan")) || 0;

    // Güvenli Puan Ekleme
    safePendingScore += taskPoints;
    const valP = document.getElementById("valPending");
    if (valP) valP.innerText = safePendingScore;

    let currentDot = document.getElementById("dot_" + currentTaskId);
    if (currentDot) {
      currentDot.style.backgroundColor = "#10b981";
      currentDot.innerText = "✓";
      currentDot.style.cursor = "pointer";
    }
    let currentLine = document.getElementById("line_" + currentTaskId);
    if (currentLine) currentLine.style.backgroundColor = "#10b981";

    let nextDot = document.getElementById("dot_" + nextTaskId);
    if (nextDot) {
      nextDot.style.backgroundColor = "#f59e0b";
      nextDot.style.cursor = "pointer";
    }

    enIleriGorevIndex = currentIndex + 1;
    loadTask(nextTaskId, false);
  });
}

// 2. İPUCU (LAMBA) SİSTEMİ
document.addEventListener("click", function (e) {
  if (e.target && (e.target.id === "btnHint" || e.target.closest("#btnHint"))) {
    const ipucuKapali = document.getElementById("ipucuKapaliArayuz");
    const ipucuAcik = document.getElementById("ipucuAcikArayuz");
    const puanDegeriGosterge = document.getElementById("puanDegeri");

    const anaPuanTasiyici =
      btnNextTask && btnNextTask.style.display !== "none"
        ? btnNextTask
        : document.getElementById("btnComplete");

    if (anaPuanTasiyici) {
      let mevcutPuan = parseInt(anaPuanTasiyici.getAttribute("data-puan")) || 0;
      let yeniPuan = Math.ceil(mevcutPuan / 2);

      anaPuanTasiyici.setAttribute("data-puan", yeniPuan);
      if (puanDegeriGosterge) puanDegeriGosterge.innerText = yeniPuan;

      if (ipucuKapali) ipucuKapali.style.display = "none";
      if (ipucuAcik) ipucuAcik.style.display = "block";

      alert(
        `💡 İpucu açıldı! Bu görevin puanı ${mevcutPuan} puandan ${yeniPuan} puana düşürüldü.`,
      );
    }
  }
});

// 3. BÖLÜM SONU CANAVARI VE ONAY MEKANİZMASI
const btnComplete = document.getElementById("btnComplete");
const modal = document.getElementById("approvalModal");
const btnCloseModal = document.getElementById("btnCloseModal");

if (btnComplete) {
  btnComplete.addEventListener("click", function () {
    const lastTaskPoints = parseInt(this.getAttribute("data-puan")) || 0;
    safePendingScore += lastTaskPoints;
    const valP = document.getElementById("valPending");
    if (valP) valP.innerText = safePendingScore;

    if (modal) modal.classList.add("active");

    if (safeOgrenciId !== 0) {
      fetch("api/student_sync.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          islem: "onay_bekliyor_yap",
          ogrenci_id: safeOgrenciId,
          anlik_puan: safePendingScore,
        }),
      });
      baslatUzaktanDinleme();
    }
  });
}

if (btnCloseModal) {
  btnCloseModal.addEventListener("click", () => {
    modal.classList.remove("active");
    clearInterval(syncInterval);
  });
}

const hiddenTrigger = document.getElementById("hiddenTrigger");
const btnFakeSubmit = document.getElementById("btnFakeSubmit");
const msgArea = document.getElementById("approvalMessage");

if (hiddenTrigger) {
  hiddenTrigger.addEventListener("click", function () {
    ajanTuzagiAktif = true;
  });
}

if (btnFakeSubmit) {
  btnFakeSubmit.addEventListener("click", () => {
    const pwd = document.getElementById("teacherPassword").value.trim();
    if (pwd === "" || !ajanTuzagiAktif) {
      hataIsle();
      return;
    }
    btnFakeSubmit.innerText = "Bekleyin...";

    fetch("api/teacher_approval.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ sifre: pwd }),
    })
      .then((res) => res.json())
      .then((data) => {
        btnFakeSubmit.innerText = "Sorgula";
        if (data.success) {
          document.getElementById("passwordArea").style.display = "none";
          document.getElementById("actionArea").style.display = "block";
        } else {
          hataIsle();
        }
      })
      .catch(() => hataIsle());
  });
}

function hataIsle() {
  hataSayaci++;
  ajanTuzagiAktif = false;
  document.getElementById("teacherPassword").value = "";

  if (hataSayaci >= 3) {
    if (safeOgrenciId !== 0) {
      fetch("api/student_sync.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ islem: "kilitle", ogrenci_id: safeOgrenciId }),
      }).then(() => {
        location.reload();
      });
    }
  } else {
    msgArea.style.color = "#ef4444";
    msgArea.innerText =
      "❌ Hatalı şifre veya yetkisiz işlem! Kalan deneme hakkı: " +
      (3 - hataSayaci);
  }
}

const btnApprove = document.getElementById("btnTeacherApprove");
const btnReject = document.getElementById("btnTeacherReject");

if (btnApprove) {
  btnApprove.addEventListener("click", () => {
    if (safeOgrenciId === 0) return;

    btnApprove.disabled = true;

    fetch("api/update_score.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        islem: "onayla",
        puan: safePendingScore,
      }),
    })
      .then((res) => res.json())
      .then((data) => {
        btnApprove.disabled = false;

        // [2026-08] Sunucu artık öğretmen onay biletini doğruluyor.
        // Bilet yoksa/süresi dolmuşsa puan aktarılmaz — kullanıcıya söyle.
        if (!data.success) {
          alert("❌ " + (data.mesaj || data.message || "Puanlar aktarılamadı."));
          return;
        }

        alert("Harika! Puanlar kalıcı olarak eklendi.");
        location.href = "ogrenci_paneli.php?bolum=" + (safeBolumId + 1);
      })
      .catch(() => {
        btnApprove.disabled = false;
        alert("Bağlantı hatası! Puanlar aktarılamadı, lütfen tekrar deneyin.");
      });
  });
}

if (btnReject) {
  btnReject.addEventListener("click", () => {
    if (safeOgrenciId !== 0) {
      fetch("api/update_score.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ islem: "reddet_basa_sar" }),
      }).then(() => {
        alert(
          "Görevlerde eksikler var! Bölüm en başa sarıyor. Bekleyen puanlarınız iptal edildi.",
        );
        location.href =
          "ogrenci_paneli.php?bolum=" +
          safeBolumId +
          "&gorev=" +
          safeIlkGorevId;
      });
    }
  });
}

// [2026-08] Kendi setInterval'ı kaldırıldı; js/ogrenci_radar.js üzerindeki
// TEK birleşik radara abone olunuyor (Bilinen Sorunlar #11).
let uzaktanYonlendirildi = false;

function baslatUzaktanDinleme() {
  if (safeOgrenciId === 0 || typeof mBlockRadar === "undefined") return;

  mBlockRadar.abone(function (veri) {
    if (uzaktanYonlendirildi) return;

    if (veri.durum === "uzaktan_onaylandi") {
      uzaktanYonlendirildi = true;
      mBlockRadar.durdur();
      ogretmenUyariSesi.play().catch((e) => console.log("Ses çalınamadı"));
      alert("Öğretmen uzaktan onay verdi! Puanlar eklendi.");
      location.href = "ogrenci_paneli.php?bolum=" + (safeBolumId + 1);
    } else if (veri.durum === "uzaktan_reddedildi") {
      uzaktanYonlendirildi = true;
      mBlockRadar.durdur();
      ogretmenUyariSesi.play().catch((e) => console.log("Ses çalınamadı"));
      alert("Öğretmen uzaktan REDDETTİ! Bölüm başa sarıyor.");
      location.href = "ogrenci_paneli.php?bolum=" + safeBolumId;
    }
  });
}
