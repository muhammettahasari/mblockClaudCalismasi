/* Panelin sekme + yoklama mantığını GERÇEK kaynak koddan çalıştırır.
   Amaç: "sadece açık sekme yoklama yapar" iddiasını kanıtlamak. */
import fs from 'fs';
import vm from 'vm';

const dosya = '/sessions/wizardly-epic-brown/mnt/mblock_platform/admin/ogretmen_paneli.php';
let metin = fs.readFileSync(dosya, 'utf8');
metin = metin.replace(/<\?=(.*?)\?>/gs, 'null').replace(/<\?php(.*?)\?>/gs, '');

// Sekme/yoklama mantığını taşıyan ilk büyük <script> bloğu
const bloklar = [...metin.matchAll(/<script\b[^>]*>(.*?)<\/script>/gis)].map(m => m[1]);
const kod = bloklar.find(b => b.includes('function gorunumDegistir'));
if (!kod) { console.log('❌ blok bulunamadı'); process.exit(1); }

/* --- Sahte DOM --- */
const sayac = { teacher_remote: 0, sinif_ilerleme: 0, kilit: 0 };
function ogeYap(id) {
  const siniflar = new Set();
  return {
    id, value: '0', textContent: '', innerHTML: '',
    style: new Proxy({}, { get: (t,k)=>t[k]??'', set:(t,k,v)=>{t[k]=v;return true;} }),
    options: [{ value: '0', text: 'Tüm sınıflar' }, { value: '7', text: '5A' }],
    selectedIndex: 0,
    classList: { add: c=>siniflar.add(c), remove: c=>siniflar.delete(c),
                 toggle: (c,v)=>{ v?siniflar.add(c):siniflar.delete(c); },
                 contains: c=>siniflar.has(c) },
    querySelectorAll: () => [], querySelector: () => null,
    addEventListener: () => {}, focus: () => {},
  };
}
const ogeler = {};
const document = {
  hidden: false,
  getElementById: id => (ogeler[id] ??= ogeYap(id)),
  querySelector: () => null, querySelectorAll: () => [],
  addEventListener: () => {},
};
const zamanlayicilar = [];
const context = {
  document, console,
  window: { location: new URL('http://localhost/mblock_platform/admin/ogretmen_paneli.php') },
  history: { replaceState: () => {} },
  URL, URLSearchParams,
  setInterval: (f, ms) => { zamanlayicilar.push({ f, ms }); return zamanlayicilar.length; },
  setTimeout: () => 0, clearInterval: () => {},
  fetch: (u) => {
    if (u.includes('teacher_remote'))        sayac.teacher_remote++;
    else if (u.includes('sinif_ilerleme'))   sayac.sinif_ilerleme++;
    else if (u.includes('ogrenci_kilit'))    sayac.kilit++;
    return Promise.resolve({ json: () => Promise.resolve({ success: true, data: [], ogrenciler: [], damga: 'x' }) });
  },
  kilitModaliAc: () => {},
};
context.globalThis = context;
vm.createContext(context);
vm.runInContext(kod, context);

const say = () => ({ ...sayac });
const sifirla = () => { sayac.teacher_remote = 0; sayac.sinif_ilerleme = 0; };
// Gerçek tarayıcıda fetch cevabı mikro görevde çözülür; simülasyon da
// her turdan sonra kuyruğu boşaltmalı, yoksa 'istek uçuşta' bayrağı takılı kalır.
const bos = () => new Promise(r => setImmediate(r));
const tik = async n => { for (let i=0;i<n;i++) { zamanlayicilar.forEach(z => z.f()); await bos(); await bos(); } };

console.log('Kurulan zamanlayıcılar:', zamanlayicilar.map(z => z.ms + 'ms').join(', '));
console.log();

// SENARYO 1 — canlı sekme açık, 10 tur
sifirla(); await tik(10);
let s = say();
console.log('1) CANLI sekmesi açıkken 10 tur:');
console.log(`   teacher_remote : ${s.teacher_remote}  (beklenen: 10)`);
console.log(`   ilerleme       : ${s.sinif_ilerleme}  (beklenen: 0 — görünmeyen tablo yoklanmaz)`);
console.log(`   ${s.teacher_remote===10 && s.sinif_ilerleme===0 ? '✅' : '❌'}`);

// SENARYO 2 — ilerleme sekmesine geç, sınıf seçili
console.log();
ogeler['sinifSelect'].value = '7';
ogeler['sinifSelect'].selectedIndex = 1;
sifirla();
context.gorunumDegistir('ilerleme');
await bos(); await bos();   // geçiş isteğinin cevabı gelsin
const gecis = say();
await tik(10);
s = say();
console.log('2) İLERLEME sekmesine geçip 10 tur (sınıf 5A seçili):');
console.log(`   geçiş anında hemen çekti mi : ${gecis.sinif_ilerleme===1 ? '✅ evet' : '❌ hayır'}`);
console.log(`   teacher_remote : ${s.teacher_remote}  (beklenen: 0 — canlı durdu)`);
console.log(`   ilerleme       : ${s.sinif_ilerleme}  (beklenen: 11 = 1 geçiş + 10 tur)`);
console.log(`   ${s.teacher_remote===0 && s.sinif_ilerleme===11 ? '✅' : '❌'}`);

// SENARYO 3 — "Tüm sınıflar" seçiliyken ilerleme
console.log();
ogeler['sinifSelect'].value = '0';
ogeler['sinifSelect'].selectedIndex = 0;
sifirla(); await tik(5);
s = say();
console.log('3) İLERLEME açık ama "Tüm sınıflar" seçili, 5 tur:');
console.log(`   ilerleme isteği : ${s.sinif_ilerleme}  (beklenen: 0 — sunucuya boşuna gidilmez)`);
console.log(`   uyarı görünür mi: ${ogeler['ilerlemeSinifUyari'].style.display === '' ? '✅ evet' : '❌ hayır'}`);
console.log(`   tablo gizli mi  : ${ogeler['ilerlemeTabloSarmal'].style.display === 'none' ? '✅ evet' : '❌ hayır'}`);
console.log(`   ${s.sinif_ilerleme===0 ? '✅' : '❌'}`);

// SENARYO 4 — sekme arka planda
console.log();
ogeler['sinifSelect'].value = '7'; ogeler['sinifSelect'].selectedIndex = 1;
document.hidden = true;
sifirla(); await tik(5); s = say();
console.log('4) Tarayıcı sekmesi ARKA PLANDA, 5 tur:');
console.log(`   ilerleme isteği : ${s.sinif_ilerleme}  (beklenen: 0)`);
console.log(`   ${s.sinif_ilerleme===0 ? '✅' : '❌'}`);
document.hidden = false;

// SENARYO 5 — sıfırlama paneli açıkken
console.log();
ogeler['sifirlaPerde'].classList.add('acik');
sifirla(); await tik(5); s = say();
console.log('5) Sıfırlama paneli AÇIKKEN, 5 tur:');
console.log(`   ilerleme isteği : ${s.sinif_ilerleme}  (beklenen: 0 — öğretmenin işi bölünmesin)`);
console.log(`   ${s.sinif_ilerleme===0 ? '✅' : '❌'}`);
ogeler['sifirlaPerde'].classList.remove('acik');

// SENARYO 6 — canlıya dön
console.log();
sifirla(); context.gorunumDegistir('canli'); await tik(10); s = say();
console.log('6) CANLI sekmesine geri dön, 10 tur:');
console.log(`   teacher_remote : ${s.teacher_remote}  (beklenen: 11)`);
console.log(`   ilerleme       : ${s.sinif_ilerleme}  (beklenen: 0)`);
console.log(`   ${s.teacher_remote===11 && s.sinif_ilerleme===0 ? '✅' : '❌'}`);
