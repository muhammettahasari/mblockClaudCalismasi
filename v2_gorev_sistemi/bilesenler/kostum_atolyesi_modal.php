<?php
/**
 * ============================================================================
 * MODÜL: Kostüm Atölyesi penceresi — PAYLAŞILAN GÖVDE (partial)
 * DOSYA YOLU: v2_gorev_sistemi/bilesenler/kostum_atolyesi_modal.php
 *
 * NE İŞE YARAR? (HALK DİLİNDE):
 * Paper.js tabanlı vektör çizim editörünün TÜM arayüzü (araç çubukları,
 * renk paleti, tuval, kostüm şeridi). Tek kopya olarak burada durur.
 *
 * KİMLER KULLANIR?
 *   1. v2_gorev_sistemi/ogrenci/oynatici.php
 *      Öğrenci kendi kuklasının kostümünü çizer. Sonuç SADECE tarayıcıda
 *      kalır (window.projeKuklalari), sunucuya yazılmaz, çıkışta kaybolur.
 *   2. v2_gorev_sistemi/admin/kukla_kutuphanesi/index.php
 *      Süper Admin kütüphanedeki kostümü düzenler ve SUNUCUYA KAYDEDER.
 *
 * [2026-08] Bu blok eskiden oynatici.php içine gömülüydü ve atölyeyi başka
 * bir sayfada kullanmak imkânsızdı. Ortak parçaya alındı.
 *
 * ÇAĞIRAN SAYFANIN YÜKLEMESİ GEREKENLER:
 *   - paper-full.min.js
 *   - assets/css/ogrenci_oynatici.css (atölye stilleri orada)
 *   - kostum_atolyesi/renk_ve_gradyan.js, transform_ve_secim.js,
 *     araclar_ve_yardimcilar.js, kostum_atolyesi.js
 *   - window.projeKuklalari ve window.aktifKuklaId global değişkenleri
 * ============================================================================
 */
?>
    <div class="atolye-overlay" id="kostumAtolyesiModal">
        <div class="atolye-pencere">
            <div class="atolye-ust-bar">
                <h2 id="atolyeKuklaAdiBaslik">🎨 Kostüm Atölyesi</h2>
                <div class="atolye-butonlar">
                    <button class="btn-atolye btn-removebg" onclick="arkaplanKaldiracGit()" title="Yapay Zeka ile Arka Planı Sil">✨ Arka Planı Kaldır</button>
                    <button class="btn-atolye btn-indir" onclick="kuklayiIndir()" title="Kuklayı Bilgisayara İndir">📦 İndir (.zip)</button>
                    <button class="btn-atolye btn-kaydet" onclick="atolyeKaydet()">💾 Kaydet / Çık</button>
                <button type="button" onclick="window.atolyeKapatmayiDene()" class="atolye-kapat-btn">✖</button>
                </div>
            </div>

            <div class="atolye-govde">

                <div class="atolye-sol-panel" id="atolyeKostumListesi"></div>

                <div class="atolye-orta-panel">

                    <div class="ust-kontrol-paneli">
                        <div class="kontrol-satir">
                            <div class="kontrol-grup">
                                📝 <input type="text" id="ayarKostumAdi" value="Kostüm 1" onchange="kostumAdiniGuncelle(this.value)" title="Kostüm Adı">
                            </div>
                            <div class="kontrol-grup" style="position: relative;" id="grupDolgu">
                                <label>Dolgu:</label>
                                <div class="renk-btn-gorsel" id="btnGorselDolgu" onclick="renkPaneliAc('dolgu')"></div>
                                <div class="renk-popover" id="popoverDolgu">
                                    <div class="arac-grid dortlu">
                                        <button class="arac-btn aktif" onclick="renkTipiSec('dolgu', 'duz')" title="Düz Renk"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                                                <path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" />
                                            </svg></button>
                                        <button class="arac-btn" onclick="renkTipiSec('dolgu', 'yok')" title="Şeffaf (Yok)"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <line x1="3" y1="3" x2="21" y2="21" />
                                            </svg></button>
                                        <button class="arac-btn" onclick="renkTipiSec('dolgu', 'linear')" title="Doğrusal (Linear)"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <line x1="12" y1="3" x2="12" y2="21" />
                                            </svg></button>
                                        <button class="arac-btn" onclick="renkTipiSec('dolgu', 'radial')" title="Radyal (Radial)"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <circle cx="12" cy="12" r="5" />
                                            </svg></button>
                                    </div>
                                    <div id="icerikDolgu_duz"><input type="color" id="renkDolguDuz" value="#3b82f6" oninput="renkGuncelle('dolgu')" style="width:100%; height:30px; cursor:pointer;"></div>
                                    <div id="icerikDolgu_gradyan" style="display:none;">
                                        <div class="gradyan-kapsayici" onclick="gradyanDurakEkle(event, 'dolgu')">
                                            <div class="gradyan-cubuk" id="cubukGorselDolgu"></div>
                                            <div id="duraklarDolgu"></div>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <input type="color" id="renkDolguDurak" oninput="seciliDurakRenginiGuncelle('dolgu')" style="width:50px; height:25px;">
                                            <span style="font-size:10px; color:#64748b;">(Silmek için noktayı aşağı çek)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="kontrol-grup" style="position: relative;" id="grupCizgi">
                                <label>Çizgi:</label>
                                <div class="renk-btn-gorsel" id="btnGorselCizgi" style="background:#1e293b;" onclick="renkPaneliAc('cizgi')"></div>
                                <div class="renk-popover" id="popoverCizgi">
                                    <div class="arac-grid dortlu">
                                        <button class="arac-btn aktif" onclick="renkTipiSec('cizgi', 'duz')" title="Düz Renk"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                                                <path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" />
                                            </svg></button>
                                        <button class="arac-btn" onclick="renkTipiSec('cizgi', 'yok')" title="Şeffaf (Yok)"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <line x1="3" y1="3" x2="21" y2="21" />
                                            </svg></button>
                                        <button class="arac-btn" onclick="renkTipiSec('cizgi', 'linear')" title="Doğrusal (Linear)"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <line x1="12" y1="3" x2="12" y2="21" />
                                            </svg></button>
                                        <button class="arac-btn" onclick="renkTipiSec('cizgi', 'radial')" title="Radyal (Radial)"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <circle cx="12" cy="12" r="5" />
                                            </svg></button>
                                    </div>
                                    <div id="icerikCizgi_duz"><input type="color" id="renkCizgiDuz" value="#1e293b" oninput="renkGuncelle('cizgi')" style="width:100%; height:30px; cursor:pointer;"></div>
                                    <div id="icerikCizgi_gradyan" style="display:none;">
                                        <div class="gradyan-kapsayici" onclick="gradyanDurakEkle(event, 'cizgi')">
                                            <div class="gradyan-cubuk" id="cubukGorselCizgi"></div>
                                            <div id="duraklarCizgi"></div>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <input type="color" id="renkCizgiDurak" oninput="seciliDurakRenginiGuncelle('cizgi')" style="width:50px; height:25px;">
                                            <span style="font-size:10px; color:#64748b;">(Silmek için noktayı aşağı çek)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="kontrol-grup">
                                <button class="arac-btn" onclick="atolyeGeriAl()" title="Geri Al (Ctrl+Z)" style="padding: 4px 8px;"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                                        <path d="M3 3v5h5" />
                                    </svg></button>
                                <button class="arac-btn" onclick="atolyeIleriAl()" title="İleri Al (Ctrl+Y)" style="padding: 4px 8px;"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                                        <path d="M21 12a9 9 0 1 1-9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
                                        <path d="M21 3v5h-5" />
                                    </svg></button>
                            </div>
                            <div class="kontrol-grup">
                                <button class="arac-btn" onclick="katmanYukari()" title="Üste Al" style="padding: 4px 8px;"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                                        <polyline points="18 15 12 9 6 15" />
                                    </svg> Üste Al</button>
                                <button class="arac-btn" onclick="katmanAsagi()" title="Alta Gönder" style="padding: 4px 8px;"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                                        <polyline points="6 9 12 15 18 9" />
                                    </svg> Alta Gönder</button>
                            </div>
                        </div>
                        <div class="kontrol-satir baglamsal" id="context-shape">
                            <div class="kontrol-grup kalinlik-popover-grup" id="grupKalinlik">
                                <label id="lblAyarKalinlik">Çizgi Kalınlığı:</label>

                                <button type="button" class="kalinlik-ozet-btn" id="btnKalinlikPopover">
                                    <input type="number" id="ayarKalinlik" value="3" min="0" max="100">
                                    <span>px</span>
                                </button>

                                <div class="kalinlik-popover" id="popoverKalinlik">
                                    <input
                                        type="range"
                                        id="ayarKalinlikSlider"
                                        min="1"
                                        max="60"
                                        step="1"
                                        value="3">
                                </div>
                            </div>

                            <div class="kontrol-grup" id="grupCizgiTipi">
                                <label>Çizgi Türü:</label>
                                <select id="ayarCizgiTipi" onchange="cizgiTipiGuncelle()">
                                    <option value="solid">Düz Çizgi</option>
                                    <option value="dashed">Kesik Kesik</option>
                                </select>
                            </div>
                        </div>

                        <div class="kontrol-satir baglamsal" id="context-text">
                            <div class="kontrol-grup">
                                <label>Font Tipi:</label>
                                <select id="ayarFont">
                                    <option value="Arial">Arial</option>
                                    <option value="Verdana">Verdana</option>
                                    <option value="Courier New">Courier</option>
                                </select>
                            </div>
                            <div class="kontrol-grup"><label>Punto:</label><input type="number" id="ayarPunto" value="24" min="8" max="150"> px</div>
                        </div>
                        <div class="kontrol-satir baglamsal" id="context-olcum">
                            <div class="kontrol-grup">
                                <label>Ölçüm Birimi:</label>
                                <select id="ayarOlcumBirim">
                                    <option value="px">Piksel (px)</option>
                                    <option value="cm">Santimetre (cm)</option>
                                    <option value="mm">Milimetre (mm)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="canvas-container-div" id="atolyeCanvasWrapper">
                        <canvas id="cizimTuvali" resize></canvas>

                         <div id="atolyeZoomKontrol"
     class="atolye-zoom-kontrol"
     onmousedown="event.stopPropagation()"
     onclick="event.stopPropagation()">

    <!-- Seçim / Ok -->
    <button type="button"
            id="btnAtolyeSecimMini"
            class="zoom-sonrasi"
            onclick="window.atolyeSecimModuAc()"
            title="Seçim Aracı">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 4l7.5 16 2.5-7.5L21.5 10 4 4z" />
        </svg>
    </button>

    <!-- Pan / El -->
    <button type="button"
        id="btnAtolyePanMini"
        class="zoom-sonrasi"
        onclick="window.atolyePanModuAc()"
        title="Kaydır / Pan">
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M8.5 11V5.5a1.5 1.5 0 0 1 3 0V10" />
        <path d="M11.5 10V4.5a1.5 1.5 0 0 1 3 0V10" />
        <path d="M14.5 10V6.5a1.5 1.5 0 0 1 3 0V14" />
        <path d="M8.5 10V8.5a1.5 1.5 0 0 0-3 0V15a6.5 6.5 0 0 0 13 0v-3.5a1.5 1.5 0 0 0-3 0V14" />
    </svg>
</button>
    <!-- Sahneye sığdır -->
    <button type="button"
            onclick="window.atolyeEkranaSigdir()"
            title="Ekrana Sığdır">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="3.2"></circle>
            <line x1="12" y1="2.5" x2="12" y2="6"></line>
            <line x1="12" y1="18" x2="12" y2="21.5"></line>
            <line x1="2.5" y1="12" x2="6" y2="12"></line>
            <line x1="18" y1="12" x2="21.5" y2="12"></line>
        </svg>
    </button>

    <!-- Yakınlaştır -->
    <button type="button"
            onclick="window.atolyeZoomla(1.2)"
            title="Yakınlaştır">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="8.5"></circle>
            <line x1="12" y1="8" x2="12" y2="16"></line>
            <line x1="8" y1="12" x2="16" y2="12"></line>
        </svg>
    </button>

    <!-- Uzaklaştır -->
    <button type="button"
            onclick="window.atolyeZoomla(0.8)"
            title="Uzaklaştır">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="8.5"></circle>
            <line x1="8" y1="12" x2="16" y2="12"></line>
        </svg>
    </button>
</div>
                    </div>
                </div>

                <div class="atolye-sag-panel" style="width: 260px;">
                    <div class="arac-baslik">Araçlar (Tools)</div>
                    <div class="arac-grid uclu">
                        <button class="arac-btn aktif" id="btnAracSecim" onclick="aracSec('secim')" title="Seç (V)"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none">
                                <path d="M4 4l7.5 16 2.5-7.5L21.5 10 4 4z" />
                            </svg><span>Seç</span></button>
                        <button class="arac-btn" id="btnAracNokta" onclick="aracSec('nokta')" title="Düğüm (A)"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" fill="none">
                                <path d="M12 4v4m0 8v4M4 12h4m8 0h4" />
                                <rect x="9" y="9" width="6" height="6" />
                            </svg><span>Nokta</span></button>
                     <button class="arac-btn" id="btnAracEl" onclick="aracSec('el')" title="Kaydır (Space)">
    <svg viewBox="0 0 24 24" width="22" height="22"
         stroke="currentColor" stroke-width="1.5"
         stroke-linecap="round" stroke-linejoin="round" fill="none">
        <path d="M8.5 11V5.5a1.5 1.5 0 0 1 3 0V10" />
        <path d="M11.5 10V4.5a1.5 1.5 0 0 1 3 0V10" />
        <path d="M14.5 10V6.5a1.5 1.5 0 0 1 3 0V14" />
        <path d="M8.5 10V8.5a1.5 1.5 0 0 0-3 0V15a6.5 6.5 0 0 0 13 0v-3.5a1.5 1.5 0 0 0-3 0V14" />
    </svg>
    <span>Kaydır</span>
</button>
                        <button class="arac-btn" id="btnAracKalem" onclick="aracSec('kalem')" title="Pen Tool"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <path d="M12 19l7-7 3 3-7 7-3-3z" />
                                <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z" />
                                <path d="M2 2l7.586 7.586" />
                                <circle cx="11" cy="11" r="2" />
                            </svg><span>Kalem</span></button>
                        <button class="arac-btn" id="btnAracGradyan" onclick="aracSec('gradyan')" title="Degrade Yönü Aracı">
                            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" fill="none">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <line x1="3" y1="3" x2="21" y2="21" />
                                <circle cx="7" cy="7" r="2" fill="currentColor" />
                                <circle cx="17" cy="17" r="2" fill="currentColor" />
                            </svg><span>Degrade</span>
                        </button>
                        <button class="arac-btn" id="btnAracFirca" onclick="aracSec('firca')" title="Fırça"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <path d="M10 18h4v-4H10v4z" />
                                <path d="M10 14c-1.5-2.5-4-5-4-8a6 6 0 0 1 12 0c0 3-2.5 5.5-4 8" />
                            </svg><span>Fırça</span></button>
                        <button class="arac-btn" id="btnAracSilgi" onclick="aracSec('silgi')" title="Silgi"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                <path d="M20 20H7L3 16C2 15 2 13 3 12L13 2C14 1 16 1 17 2L22 7C23 8 23 10 22 11L14 19Z" />
                                <path d="M8 7L17 16" />
                            </svg><span>Silgi</span></button>
                        <button class="arac-btn" id="btnAracDuz_cizgi" onclick="aracSec('duz_cizgi')" title="Çizgi"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none">
                                <line x1="4" y1="20" x2="20" y2="4" />
                            </svg><span>Çizgi</span></button>
                        <button class="arac-btn" id="btnAracMetin" onclick="aracSec('metin')" title="Yazı"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none">
                                <path d="M4 7V4h16v3M12 4v16M9 20h6" />
                            </svg><span>Yazı</span></button>
                        <button class="arac-btn" id="btnAracKutu" onclick="aracSec('kutu')" title="Kutu"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" fill="none">
                                <rect x="4" y="5" width="16" height="14" rx="2" />
                            </svg><span>Kutu</span></button>
                        <button class="arac-btn" id="btnAracDaire" onclick="aracSec('daire')" title="Daire"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" fill="none">
                                <circle cx="12" cy="12" r="9" />
                            </svg><span>Daire</span></button>
                        <button class="arac-btn" id="btnAracCokgen" onclick="aracSec('cokgen')" title="Çokgen"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none">
                                <polygon points="12 2 21 8 18 19 6 19 3 8" />
                            </svg><span>Çokgen</span></button>
                        <button class="arac-btn" id="btnAracOlcum" onclick="aracSec('olcum')" title="Ölçüm"><svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none">
                                <path d="M21 8L8 21M3 16L16 3" />
                                <path d="M12 7l3 3M8 11l3 3M4 15l3 3" />
                            </svg><span>Ölçüm</span></button>
                    </div>

                    <div id="grupPenModlari" style="display: none; flex-direction: column; gap: 5px; margin-top: 15px; background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <label style="font-size: 12px; font-weight: bold; color: #475569;">✒️ Kalem Çizim Modu:</label>
                        <div class="arac-btn-grup" style="display: flex; gap: 5px;">
                            <button type="button" class="arac-btn aktif" id="btnPenNormal" onclick="window.penModuDegistir('normal')" title="Normal Çizim (Kısayol Yok)" style="flex:1; padding:5px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 19l7-7 3 3-7 7-3-3z"></path>
                                    <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"></path>
                                    <path d="M2 2l7.586 7.586"></path>
                                </svg>
                            </button>
                            <button type="button" class="arac-btn" id="btnPenCorner" onclick="window.penModuDegistir('corner')" title="Sivri Köşe Bükme (Kısayol: Ctrl)" style="flex:1; padding:5px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="4 20 12 4 20 20"></polyline>
                                    <circle cx="12" cy="4" r="2" fill="currentColor"></circle>
                                </svg>
                            </button>
                            <button type="button" class="arac-btn" id="btnPenSmooth" onclick="window.penModuDegistir('smooth')" title="Yumuşak Eğri (Kısayol: Shift)" style="flex:1; padding:5px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 20 C 10 20, 14 4, 20 4"></path>
                                    <circle cx="12" cy="12" r="2" fill="currentColor"></circle>
                                </svg>
                            </button>
                            <button type="button" class="arac-btn" id="btnPenCurve" onclick="window.penModuDegistir('curve')" title="Şişkin/Oval Kavis (Kısayol: Alt)" style="flex:1; padding:5px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 20 Q 12 -4 20 20"></path>
                                    <circle cx="12" cy="8" r="2" fill="currentColor"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="arac-baslik" style="margin-top: 15px;">Boolean İşlemleri</div>
                    <div class="arac-grid uclu">
                        <button class="arac-btn" onclick="atolyeBoolean('unite')" title="Birleştir"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="1.5" fill="none">
                                <rect x="4" y="4" width="10" height="10" rx="2" />
                                <rect x="10" y="10" width="10" height="10" rx="2" />
                            </svg></button>
                        <button class="arac-btn" onclick="atolyeBoolean('subtract')" title="Üsttekini Çıkar"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="1.5" fill="none">
                                <rect x="4" y="4" width="10" height="10" rx="2" />
                                <circle cx="15" cy="15" r="5" />
                            </svg></button>
                        <button class="arac-btn" onclick="atolyeBoolean('intersect')" title="Kesişim"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="1.5" fill="none">
                                <circle cx="9" cy="12" r="6" />
                                <circle cx="15" cy="12" r="6" />
                            </svg></button>
                    </div>

                    <div class="arac-baslik" style="margin-top: 15px;">Hizalama</div>
                    <div class="arac-grid uclu">
                        <button class="arac-btn" onclick="atolyeHizala('left')">⇤<br><span>Sol</span></button>
                        <button class="arac-btn" onclick="atolyeHizala('center')">⇥⇤<br><span>Orta</span></button>
                        <button class="arac-btn" onclick="atolyeHizala('right')">⇥<br><span>Sağ</span></button>
                        <button class="arac-btn" onclick="atolyeHizala('top')">⇡<br><span>Üst</span></button>
                        <button class="arac-btn" onclick="atolyeHizala('middle')">⇡⇣<br><span>Merkez</span></button>
                        <button class="arac-btn" onclick="atolyeHizala('bottom')">⇣<br><span>Alt</span></button>
                    </div>

                    <div class="arac-baslik" style="margin-top: 15px;">Görünüm & Ayar</div>
                    <div class="ayar-kutu" style="margin-bottom: 10px; text-align:center;">
                        <label style="font-size: 11px; cursor: pointer; font-weight: bold; color:#3b82f6;"><input type="checkbox" id="cbSnapAktif"> 🧲 Izgaraya Yapış (Snap)</label>
                    </div>
                    <div class="ayar-kutu" style="display: flex; gap: 5px; margin-bottom: 5px;">
                        <button class="arac-btn" onclick="atolyeZoomla(1.2)" title="Yakınlaştır" style="flex:1; padding: 5px;"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                <line x1="11" y1="8" x2="11" y2="14" />
                                <line x1="8" y1="11" x2="14" y2="11" />
                            </svg></button>
                        <button class="arac-btn" onclick="atolyeZoomla(0.8)" title="Uzaklaştır" style="flex:1; padding: 5px;"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                <line x1="8" y1="11" x2="14" y2="11" />
                            </svg></button>
                        <button class="arac-btn" onclick="atolyeEkranaSigdir()" title="Ekrana Sığdır" style="flex: 1; padding: 5px;"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <path d="M8 8h8v8H8z" />
                            </svg></button>
                    </div>
                    <div class="arac-grid">
                        <button class="arac-btn" onclick="document.getElementById('kilavuzYukleyici').click()" style="padding: 8px; font-size: 11px;">📄 Resim/SVG Ekle</button>
                        <button class="arac-btn" onclick="atolyeSvgIndir()" style="padding: 8px; font-size: 11px; background: #fffbeb; border-color: #f59e0b; color: #d97706;">📦 SVG İndir</button>
                    </div>
                    <input type="file" id="kilavuzYukleyici" accept="image/*,.svg" style="display: none;" onchange="kilavuzResimYukle(event)">
                </div>

            </div>


        </div>
    </div>
