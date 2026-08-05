/* ==========================================================
   YENİ: ÇİFT MOTORLU DİNAMİK SİLÜET VE MIKNATIS SİSTEMİ
   ========================================================== */

function drag(ev) {
  let kart = ev.target.closest(".item-card");
  if (!kart) return;

  let kat = kart.getAttribute("data-kategori");
  ev.dataTransfer.setData("esya_id", kart.getAttribute("data-id"));
  ev.dataTransfer.setData("kategori", kat);
  ev.dataTransfer.setData("resim_yolu", kart.getAttribute("data-resim"));

  // YENİ MIKNATIS ZEKA: Önce karakterin üzerinde zaten o türden bir eşya var mı bak
  let gercekKatman = document.getElementById("layer_" + kat);
  let yedekSiluet = document.getElementById("siluet_" + kat);

  // Eğer gerçek eşya varsa (ve gizli değilse), onu bembeyaz parlayan bir silüete çevir!
  if (
    gercekKatman &&
    gercekKatman.getAttribute("src") &&
    gercekKatman.style.display !== "none"
  ) {
    gercekKatman.classList.add("mevcut-parlama");
  }
  // Eğer o bölge boşsa, senin img/siluetler/ klasörüne koyduğun yedek silüeti göster
  else if (yedekSiluet) {
    yedekSiluet.style.display = "block";
  }
}

function allowDrop(ev) {
  ev.preventDefault(); // Tüm ekranı bırakılabilir hale getirir
}

function drop(ev) {
  ev.preventDefault();

  // Eşya bırakıldığı an ekrandaki tüm parlamaları ve yedek silüetleri temizle
  document
    .querySelectorAll(".siluet-katmani")
    .forEach((s) => (s.style.display = "none"));
  document
    .querySelectorAll(".layer")
    .forEach((l) => l.classList.remove("mevcut-parlama"));

  const esyaId = ev.dataTransfer.getData("esya_id");
  const esyaKategori = ev.dataTransfer.getData("kategori");
  const resimYolu = ev.dataTransfer.getData("resim_yolu");

  if (!esyaKategori) return;

  // Hedef katmanı bul ve yeni resmi yapıştır
  let hedefKatman = document.getElementById("layer_" + esyaKategori);

  if (hedefKatman) {
    hedefKatman.src = resimYolu;
    hedefKatman.style.display = "block";
    // YENİ: Hazine Sandığındaki Kutuyu Yeşile Boyama
    document
      .querySelectorAll(`.item-card[data-kategori="${esyaKategori}"]`)
      .forEach((k) => k.classList.remove("aktif-giyili"));
    let seciliKart = document.querySelector(`.item-card[data-id="${esyaId}"]`);
    if (seciliKart) seciliKart.classList.add("aktif-giyili");
  }

  // X Butonunu ilgili havada duran yuvaya ekle
  let hedefYuva = document.getElementById("slot_" + esyaKategori);
  if (hedefYuva) {
    hedefYuva.innerHTML = `<button class="btn-cikart" onclick="esyaCikart('${esyaKategori}')">X</button>`;
  }

  // Veritabanına kaydet
  fetch("api/avatar_guncelle.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      kategori: esyaKategori,
      esya_id: esyaId,
      islem: "giy",
    }),
  })
    .then((res) => res.json())
    .then((data) => {
      if (!data.success) alert("Hata: " + data.message);
    });
}

// Çocuk eşyayı boşluğa atıp sürüklemeyi iptal ederse tüm parlamaları kapat
document.addEventListener("dragend", function (ev) {
  document
    .querySelectorAll(".siluet-katmani")
    .forEach((s) => (s.style.display = "none"));
  document
    .querySelectorAll(".layer")
    .forEach((l) => l.classList.remove("mevcut-parlama"));
});

// Eşyayı karakterden çıkartma (X Butonu)
function esyaCikart(yuvaKategori) {
  let hedefKatman = document.getElementById("layer_" + yuvaKategori);
  if (hedefKatman) {
    if (yuvaKategori === "karakter") {
      hedefKatman.src = "img/base_karakter.png";
      hedefKatman.style.display = "block";
    } else {
      hedefKatman.src = "";
      hedefKatman.style.display = "none";
    }
  }

  let hedefYuva = document.getElementById("slot_" + yuvaKategori);
  if (hedefYuva) hedefYuva.innerHTML = "";
  // YENİ: Çıkartılan eşyanın yeşil çerçevesini hazine sandığından kaldır
    document.querySelectorAll(`.item-card[data-kategori="${yuvaKategori}"]`).forEach(k => k.classList.remove('aktif-giyili'));

  fetch("api/avatar_guncelle.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ kategori: yuvaKategori, islem: "cikart" }),
  });
}
