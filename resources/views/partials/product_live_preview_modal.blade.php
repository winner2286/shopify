<!-- ======================================================================= -->
<!-- MODALE D'APERÇU / PRÉVISUALISATION VITRINE EN DIRECT (AVANT PUBLICATION) -->
<!-- ======================================================================= -->

<div id="product-live-preview-modal" class="product-preview-backdrop" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="product-preview-title">
    <div class="product-preview-window">
        <!-- En-tête de la modale d'aperçu -->
        <header class="product-preview-header">
            <div class="product-preview-header-lead">
                <div class="product-preview-icon-wrap">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </div>
                <div>
                    <h3 id="product-preview-title" class="product-preview-title">Aperçu de la fiche produit en direct</h3>
                    <p class="product-preview-subtitle">Voici exactement comment vos clients verront cet article sur votre vitrine <strong>{{ auth()->user()->shop?->name ?? 'Zanika' }}</strong>.</p>
                </div>
            </div>

            <!-- Switcher de vue : Mobile vs Ordinateur -->
            <div class="product-preview-device-toggle">
                <button type="button" class="preview-device-btn active" id="btn-device-mobile" onclick="switchPreviewDevice('mobile')" title="Vue smartphone (375px)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                        <path d="M12 18h.01"/>
                    </svg>
                    <span>Mobile</span>
                </button>
                <button type="button" class="preview-device-btn" id="btn-device-desktop" onclick="switchPreviewDevice('desktop')" title="Vue écran large (Ordinateur)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="14" x="2" y="3" rx="2"/>
                        <line x1="8" x2="16" y1="21" y2="21"/>
                        <line x1="12" x2="12" y1="17" y2="21"/>
                    </svg>
                    <span>Ordinateur</span>
                </button>
            </div>

            <button type="button" class="product-preview-close" onclick="closeProductLivePreview()" aria-label="Fermer l'aperçu">✕</button>
        </header>

        <!-- Corps de l'aperçu : Simulation du Storefront -->
        <div class="product-preview-body" id="product-preview-scroll-body">
            <div class="preview-viewport-container mobile-view" id="preview-viewport-wrap">
                
                <!-- Bandeau Démo Vitrine -->
                <div class="preview-storefront-topbar">
                    <span class="preview-store-name">{{ auth()->user()->shop?->name ?? 'Ma Boutique' }}</span>
                    <span class="preview-mode-tag">🔒 Aperçu privé avant publication</span>
                </div>

                <!-- Simulation Fiche Produit Storefront -->
                <div class="preview-product-layout">
                    <!-- Galerie Photos -->
                    <div class="preview-gallery-col">
                        <div class="preview-main-img-box">
                            <img id="preview-main-img" src="" alt="Aperçu produit" />
                            <span id="preview-discount-badge" class="preview-discount-pill" style="display:none;">-20% ÉCONOMIE</span>
                        </div>
                        <!-- Vignettes -->
                        <div id="preview-thumbs-row" class="preview-thumbs-row"></div>
                    </div>

                    <!-- Détails & Conversion -->
                    <div class="preview-info-col">
                        <!-- Badge d'urgence / consultation en direct -->
                        <div class="preview-live-badge">
                            <span class="preview-live-dot"></span>
                            <span>3 personnes consultent cet article actuellement</span>
                        </div>

                        <!-- Catégories & Titre -->
                        <div id="preview-cats-wrap" class="preview-cats-row"></div>
                        <h1 id="preview-product-title" class="preview-product-title">Titre du produit</h1>
                        <div id="preview-sku-row" class="preview-sku" style="display:none;">Réf : <span id="preview-sku-val"></span></div>

                        <!-- Bloc Prix -->
                        <div class="preview-price-card">
                            <div class="preview-price-row">
                                <span id="preview-price-val" class="preview-current-price">0 FCFA</span>
                                <span id="preview-compare-val" class="preview-compare-price" style="display:none;">0 FCFA</span>
                            </div>
                            <div id="preview-saving-row" class="preview-saving-text" style="display:none;"></div>
                        </div>

                        <!-- Statut Stock -->
                        <div id="preview-stock-badge" class="preview-stock-status in-stock">
                            ✓ En stock — Prêt à être expédié
                        </div>

                        <!-- Boutons d'achat Storefront simulés -->
                        <div class="preview-cta-block">
                            <button type="button" class="preview-btn-whatsapp" onclick="alert('Simulation : Vos clients commanderont directement via WhatsApp avec le message pré-rempli contenant le nom du produit et son prix.');">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2m.01 1.67c2.2 0 4.26.86 5.82 2.42a8.225 8.225 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.42 0-2.81-.37-4.04-1.07l-.29-.17-3.11.82.83-3.03-.19-.3a8.21 8.21 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24m4.52 11.66c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.01-1.24-.74-.66-1.24-1.48-1.39-1.73-.14-.25-.02-.39.11-.51.11-.11.25-.29.37-.44.12-.14.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.56-1.35-.77-1.85-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.44 1.03 2.61.12.17 1.78 2.71 4.31 3.8 2.53 1.09 2.53.73 2.99.69.45-.04 1.47-.6 1.68-1.18.21-.58.21-1.08.15-1.18-.06-.1-.22-.17-.47-.29Z"/>
                                </svg>
                                <span>Commander sur WhatsApp</span>
                            </button>
                            <button type="button" class="preview-btn-cart">
                                <span>Ajouter au panier</span>
                            </button>
                        </div>

                        <!-- Réassurance E-commerce Afrique -->
                        <div class="preview-reassurance-box">
                            <div class="preview-reassurance-item">
                                <span class="reassurance-icon">💵</span>
                                <div>
                                    <strong>Paiement à la livraison (COD)</strong>
                                    <span>Réglez en espèces ou Mobile Money dès réception de votre colis.</span>
                                </div>
                            </div>
                            <div class="preview-reassurance-item">
                                <span class="reassurance-icon">⚡</span>
                                <div>
                                    <strong>Livraison rapide à domicile</strong>
                                    <span>Expédition soignée directement chez vous ou à votre bureau.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Description du produit -->
                        <div class="preview-desc-box">
                            <h3>Description du produit</h3>
                            <div id="preview-desc-content" class="preview-desc-text">
                                Aucune description fournie.
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Pied de page avec publication sélective : Brouillon vs Actif -->
        <footer class="product-preview-footer">
            <div class="preview-footer-note">
                <span class="preview-status-pill">Statut actuel configuré : <strong id="preview-current-status-lbl">Actif</strong></span>
                <span class="preview-tip-text">Vous pouvez modifier le statut ou publier directement ci-dessous.</span>
            </div>

            <div class="preview-footer-actions">
                <button type="button" class="btn btn-ghost" onclick="closeProductLivePreview()">
                    ✏️ Continuer les modifications
                </button>
                <button type="button" class="btn btn-secondary" onclick="confirmProductSubmission('hidden')">
                    💾 Enregistrer en Brouillon (Masqué)
                </button>
                <button type="button" class="btn btn-primary" onclick="confirmProductSubmission('active')" style="background:linear-gradient(135deg, #10b981, #059669);border-color:#059669;box-shadow:0 4px 14px rgba(16,185,129,0.35)">
                    🚀 Publier sur ma boutique (Actif)
                </button>
            </div>
        </footer>
    </div>
</div>

<style>
/* Modale Plein Écran & Backdrop */
.product-preview-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(4, 7, 15, 0.85);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    box-sizing: border-box;
    animation: previewFadeIn 0.2s ease-out;
}

@keyframes previewFadeIn {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}

.product-preview-window {
    background: var(--bg, #090d16);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 20px;
    width: 100%;
    max-width: 1100px;
    height: 92vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7);
}

/* En-tête */
.product-preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.4rem;
    background: var(--surface, #111827);
    border-bottom: 1px solid var(--border, rgba(255, 255, 255, 0.08));
    gap: 1rem;
    flex-shrink: 0;
}

.product-preview-header-lead {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.product-preview-icon-wrap {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #e11d48, #7c3aed);
    color: #fff;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.product-preview-title {
    font-size: 1.05rem;
    font-weight: 700;
    margin: 0;
    color: var(--text, #fff);
}

.product-preview-subtitle {
    font-size: 0.8rem;
    color: var(--text-soft, #94a3b8);
    margin: 0;
}

.product-preview-device-toggle {
    display: flex;
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
    border-radius: 10px;
    padding: 3px;
    gap: 3px;
}

.preview-device-btn {
    border: none;
    background: transparent;
    color: var(--text-soft, #94a3b8);
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.35rem 0.8rem;
    border-radius: 7px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.15s ease;
}

.preview-device-btn.active {
    background: var(--brand-primary, #e11d48);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(225, 29, 72, 0.4);
}

.product-preview-close {
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: var(--text-soft, #94a3b8);
    font-size: 1.1rem;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    cursor: pointer;
    display: grid;
    place-items: center;
    transition: all 0.15s;
}

.product-preview-close:hover {
    color: #fff;
    border-color: #e11d48;
    background: rgba(225, 29, 72, 0.15);
}

/* Zone Déroulante */
.product-preview-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    background: #080c14;
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

/* Cadre du Viewport Storefront */
.preview-viewport-container {
    background: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 18px;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
    overflow: hidden;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-sizing: border-box;
}

.preview-viewport-container.mobile-view {
    width: 395px;
    max-width: 100%;
}

.preview-viewport-container.desktop-view {
    width: 100%;
    max-width: 980px;
}

.preview-storefront-topbar {
    background: #090d16;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding: 0.75rem 1.1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.preview-store-name {
    font-weight: 800;
    font-size: 0.9rem;
    color: #fff;
    letter-spacing: -0.01em;
}

.preview-mode-tag {
    font-size: 0.7rem;
    font-weight: 700;
    color: #fbbf24;
    background: rgba(251, 191, 36, 0.1);
    border: 1px solid rgba(251, 191, 36, 0.25);
    padding: 0.2rem 0.55rem;
    border-radius: 99px;
}

/* Mise en page de la Fiche Produit */
.preview-product-layout {
    padding: 1.25rem;
    display: grid;
    gap: 1.5rem;
}

.preview-viewport-container.desktop-view .preview-product-layout {
    grid-template-columns: 420px 1fr;
    padding: 2.2rem;
    gap: 2.5rem;
}

/* Image Principale */
.preview-main-img-box {
    position: relative;
    width: 100%;
    aspect-ratio: 1;
    border-radius: 14px;
    overflow: hidden;
    background: #1e293b;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.preview-main-img-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.preview-discount-pill {
    position: absolute;
    top: 10px;
    left: 10px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #ffffff;
    font-size: 0.78rem;
    font-weight: 800;
    padding: 0.3rem 0.65rem;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    letter-spacing: 0.02em;
}

.preview-thumbs-row {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.75rem;
    overflow-x: auto;
    padding-bottom: 0.25rem;
}

.preview-thumb-btn {
    width: 58px;
    height: 58px;
    border-radius: 8px;
    border: 2px solid transparent;
    overflow: hidden;
    padding: 0;
    cursor: pointer;
    background: #1e293b;
    flex-shrink: 0;
}

.preview-thumb-btn.active {
    border-color: #e11d48;
}

.preview-thumb-btn img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

/* Colonne Info */
.preview-info-col {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.preview-live-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.25);
    color: #f87171;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.3rem 0.75rem;
    border-radius: 99px;
    align-self: flex-start;
}

.preview-live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #ef4444;
    box-shadow: 0 0 8px #ef4444;
}

.preview-cats-row {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.preview-cat-pill {
    font-size: 0.75rem;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.07);
    color: #94a3b8;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
}

.preview-product-title {
    font-size: 1.35rem;
    font-weight: 800;
    color: #ffffff;
    margin: 0;
    line-height: 1.3;
}

.preview-sku {
    font-size: 0.78rem;
    color: #64748b;
}

/* Carte Prix */
.preview-price-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 0.85rem 1rem;
}

.preview-price-row {
    display: flex;
    align-items: baseline;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.preview-current-price {
    font-size: 1.7rem;
    font-weight: 900;
    color: #ffffff;
    letter-spacing: -0.02em;
}

.preview-compare-price {
    font-size: 1.05rem;
    color: #64748b;
    text-decoration: line-through;
}

.preview-saving-text {
    font-size: 0.8rem;
    color: #10b981;
    font-weight: 700;
    margin-top: 0.25rem;
}

.preview-stock-status {
    font-size: 0.82rem;
    font-weight: 700;
    padding: 0.4rem 0.75rem;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.preview-stock-status.in-stock {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.25);
}

.preview-stock-status.out-of-stock {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.25);
}

/* Boutons d'action */
.preview-cta-block {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    margin-top: 0.5rem;
}

.preview-btn-whatsapp {
    background: #25D366;
    color: #ffffff;
    border: none;
    font-weight: 800;
    font-size: 0.95rem;
    padding: 0.85rem 1.2rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(37, 211, 102, 0.35);
    transition: transform 0.15s ease;
}

.preview-btn-whatsapp:hover {
    transform: translateY(-1px);
}

.preview-btn-cart {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.15);
    font-weight: 700;
    font-size: 0.88rem;
    padding: 0.75rem 1.2rem;
    border-radius: 12px;
    cursor: default;
}

/* Réassurance */
.preview-reassurance-box {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 12px;
    padding: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.preview-reassurance-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 0.8rem;
}

.preview-reassurance-item .reassurance-icon {
    font-size: 1.15rem;
    flex-shrink: 0;
}

.preview-reassurance-item strong {
    color: #ffffff;
    display: block;
}

.preview-reassurance-item span {
    color: #94a3b8;
    font-size: 0.75rem;
}

/* Description */
.preview-desc-box {
    margin-top: 0.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding-top: 1rem;
}

.preview-desc-box h3 {
    font-size: 0.95rem;
    font-weight: 700;
    color: #ffffff;
    margin: 0 0 0.5rem;
}

.preview-desc-text {
    font-size: 0.85rem;
    color: #cbd5e1;
    line-height: 1.6;
    word-break: break-word;
}

/* Barre de Publication Fixée */
.product-preview-footer {
    background: var(--surface, #111827);
    border-top: 1px solid var(--border, rgba(255, 255, 255, 0.08));
    padding: 1rem 1.4rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    flex-shrink: 0;
}

.preview-footer-note {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.preview-status-pill {
    font-size: 0.82rem;
    color: #e2e8f0;
}

.preview-tip-text {
    font-size: 0.75rem;
    color: #94a3b8;
}

.preview-footer-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .product-preview-window {
        height: 100vh;
        max-height: 100vh;
        border-radius: 0;
    }
    .product-preview-backdrop {
        padding: 0;
    }
    .product-preview-footer {
        flex-direction: column;
        align-items: stretch;
    }
    .preview-footer-actions {
        flex-direction: column;
        width: 100%;
    }
    .preview-footer-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
window.currentPreviewDevice = 'mobile';

function switchPreviewDevice(device) {
    window.currentPreviewDevice = device;
    const wrap = document.getElementById('preview-viewport-wrap');
    const btnMobile = document.getElementById('btn-device-mobile');
    const btnDesktop = document.getElementById('btn-device-desktop');
    
    if (device === 'desktop') {
        wrap.classList.remove('mobile-view');
        wrap.classList.add('desktop-view');
        btnDesktop.classList.add('active');
        btnMobile.classList.remove('active');
    } else {
        wrap.classList.remove('desktop-view');
        wrap.classList.add('mobile-view');
        btnMobile.classList.add('active');
        btnDesktop.classList.remove('active');
    }
}

function openProductLivePreview() {
    const modal = document.getElementById('product-live-preview-modal');
    if (!modal) return;

    // 1. Récupération des données du formulaire
    const titleVal = document.getElementById('title')?.value?.trim() || 'Titre du produit';
    const descVal = document.getElementById('description')?.value?.trim() || 'Aucune description rédigée.';
    const priceVal = parseInt(document.getElementById('price')?.value, 10) || 0;
    const compareVal = parseInt(document.getElementById('compare_at_price')?.value, 10) || 0;
    const skuVal = document.getElementById('sku')?.value?.trim() || '';
    const statusSelect = document.getElementById('status');
    const currentStatus = statusSelect ? statusSelect.value : 'active';

    // Stock
    const trackStockCheck = document.getElementById('track_stock');
    const trackStock = trackStockCheck ? trackStockCheck.checked : true;
    const stockVal = parseInt(document.getElementById('stock')?.value, 10) || 0;

    // Catégories
    const catCheckboxes = document.querySelectorAll('input[name="category_ids[]"]:checked');
    const catNames = [];
    catCheckboxes.forEach(cb => {
        const lbl = cb.closest('label');
        if (lbl) {
            catNames.push(lbl.textContent.trim());
        }
    });

    // Photos
    let photoUrls = [];

    // Photo IA générée/retouchée
    const aiTemp = document.getElementById('ai_temp_photo_path')?.value;
    if (aiTemp) {
        photoUrls.push(aiTemp);
    }

    // Fichiers uploadés
    if (typeof selectedFiles !== 'undefined' && selectedFiles.length > 0) {
        selectedFiles.forEach(f => {
            photoUrls.push(URL.createObjectURL(f));
        });
    } else {
        // En mode édition : vérifier les photos déjà existantes dans le DOM
        const existingImgs = document.querySelectorAll('.gallery-item img');
        existingImgs.forEach(img => {
            if (img.src) photoUrls.push(img.src);
        });
    }

    // Si aucune photo, placeholder
    if (photoUrls.length === 0) {
        photoUrls.push('/api/placeholder-image?id=preview');
    }

    // 2. Peuplement des éléments du Storefront Simulé
    document.getElementById('preview-product-title').textContent = titleVal;
    
    // Description avec sauts de ligne
    const descFormatted = descVal.replace(/\n/g, '<br>');
    document.getElementById('preview-desc-content').innerHTML = descFormatted;

    // Prix
    document.getElementById('preview-price-val').textContent = formatFcfaNumber(priceVal) + ' FCFA';
    
    const compareEl = document.getElementById('preview-compare-val');
    const discountBadge = document.getElementById('preview-discount-badge');
    const savingRow = document.getElementById('preview-saving-row');

    if (compareVal > priceVal && priceVal > 0) {
        const discountPct = Math.round(((compareVal - priceVal) / compareVal) * 100);
        const savingAmt = compareVal - priceVal;

        compareEl.style.display = 'inline';
        compareEl.textContent = formatFcfaNumber(compareVal) + ' FCFA';

        discountBadge.style.display = 'block';
        discountBadge.textContent = '-' + discountPct + '% ÉCONOMIE';

        savingRow.style.display = 'block';
        savingRow.textContent = '🎉 Vous économisez ' + formatFcfaNumber(savingAmt) + ' FCFA';
    } else {
        compareEl.style.display = 'none';
        discountBadge.style.display = 'none';
        savingRow.style.display = 'none';
    }

    // SKU
    const skuRow = document.getElementById('preview-sku-row');
    if (skuVal) {
        skuRow.style.display = 'block';
        document.getElementById('preview-sku-val').textContent = skuVal;
    } else {
        skuRow.style.display = 'none';
    }

    // Catégories
    const catsWrap = document.getElementById('preview-cats-wrap');
    catsWrap.innerHTML = '';
    if (catNames.length > 0) {
        catNames.forEach(c => {
            const span = document.createElement('span');
            span.className = 'preview-cat-pill';
            span.textContent = c;
            catsWrap.appendChild(span);
        });
    }

    // Stock
    const stockBadge = document.getElementById('preview-stock-badge');
    if (!trackStock) {
        stockBadge.className = 'preview-stock-status in-stock';
        stockBadge.textContent = '✓ En stock — Stock illimité';
    } else if (stockVal <= 0) {
        stockBadge.className = 'preview-stock-status out-of-stock';
        stockBadge.textContent = '✕ Épuisé pour le moment';
    } else if (stockVal <= 3) {
        stockBadge.className = 'preview-stock-status out-of-stock';
        stockBadge.textContent = '⚠️ Attention : Plus que ' + stockVal + ' article(s) en stock !';
    } else {
        stockBadge.className = 'preview-stock-status in-stock';
        stockBadge.textContent = '✓ En stock (' + stockVal + ' disponibles) — Expédié sous 24h';
    }

    // Galerie
    const mainImg = document.getElementById('preview-main-img');
    const thumbsRow = document.getElementById('preview-thumbs-row');
    thumbsRow.innerHTML = '';

    mainImg.src = photoUrls[0];

    if (photoUrls.length > 1) {
        thumbsRow.style.display = 'flex';
        photoUrls.forEach((url, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'preview-thumb-btn' + (idx === 0 ? ' active' : '');
            btn.innerHTML = `<img src="${url}" alt="Vignette ${idx+1}">`;
            btn.onclick = function() {
                mainImg.src = url;
                document.querySelectorAll('.preview-thumb-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            };
            thumbsRow.appendChild(btn);
        });
    } else {
        thumbsRow.style.display = 'none';
    }

    // Statut
    const statusLbl = document.getElementById('preview-current-status-lbl');
    if (statusLbl) {
        statusLbl.textContent = currentStatus === 'active' ? 'Actif (Visible en ligne)' : 'Brouillon (Masqué)';
        statusLbl.style.color = currentStatus === 'active' ? '#10b981' : '#f59e0b';
    }

    // Afficher la modale
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeProductLivePreview() {
    const modal = document.getElementById('product-live-preview-modal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function confirmProductSubmission(statusValue) {
    const statusSelect = document.getElementById('status');
    if (statusSelect) {
        statusSelect.value = statusValue;
    }

    // Déclencher la soumission du formulaire principal
    const form = document.querySelector('form[action*="products"]');
    if (form) {
        closeProductLivePreview();
        form.submit();
    }
}

function formatFcfaNumber(val) {
    return String(val).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

// Échap pour fermer
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeProductLivePreview();
    }
});
</script>
