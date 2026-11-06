<!-- ======================================================================= -->
<!-- ZANIKA AI PRODUCT PHOTOGRAPHY STUDIO — STUDIO PHOTO PROFESSIONNEL IA     -->
<!-- ======================================================================= -->
<div id="ai-variations-modal" style="display:none;position:fixed;inset:0;background:rgba(8,10,15,0.88);backdrop-filter:blur(10px);z-index:99999;align-items:center;justify-content:center;padding:1rem;overflow-y:auto;box-sizing:border-box">
    <div style="background:var(--surface, #18181b);border:1px solid rgba(255,255,255,0.12);border-radius:24px;max-width:820px;width:100%;padding:1.6rem;box-shadow:0 30px 80px rgba(0,0,0,0.85);position:relative;margin:auto;box-sizing:border-box;color:var(--text, #f4f4f5)">
        
        <!-- Bouton Fermer -->
        <button type="button" onclick="closeAiVariationsModal()" style="position:absolute;top:1.1rem;right:1.1rem;width:36px;height:36px;border-radius:50%;border:1px solid rgba(255,255,255,0.15);background:rgba(255,255,255,0.06);color:var(--text, #fff);font-size:1.4rem;display:grid;place-items:center;cursor:pointer;line-height:1;z-index:10;transition:all .15s" aria-label="Fermer" onmouseover="this.style.background='rgba(255,255,255,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.06)'">×</button>

        <!-- En-tête du Studio -->
        <div style="display:flex;align-items:flex-start;gap:.9rem;margin-bottom:1.1rem;padding-right:2.8rem">
            <div style="width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg, #9e1b32, #c5223d);display:grid;place-items:center;color:#fff;box-shadow:0 6px 18px rgba(158,27,50,0.45);flex-shrink:0">
                <x-icon name="sparkles" size="24" />
            </div>
            <div>
                <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
                    <span style="font-size:.72rem;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#f43f5e;background:rgba(244,63,94,0.12);border:1px solid rgba(244,63,94,0.25);padding:2px 8px;border-radius:6px">Zanika AI Studio</span>
                    <span id="ai-modal-quality-score-pill" style="font-size:.72rem;font-weight:700;color:#10b981;background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.25);padding:2px 8px;border-radius:6px">✓ 98% Qualité E-commerce Pro</span>
                </div>
                <h2 style="font-size:1.35rem;font-weight:800;color:var(--text, #fff);margin:.3rem 0 .2rem 0;letter-spacing:-0.3px">Studio Photo IA Professionnel</h2>
                <p style="font-size:.85rem;color:var(--text-soft, #a1a1aa);margin:0;line-height:1.4">
                    Transformation packshot e-commerce 1:1 • <strong>Conservation absolue du produit original</strong> (zéro déformation, zéro hallucination).
                </p>
            </div>
        </div>

        <!-- Contexte & Catégorie Détectée -->
        <div id="ai-modal-category-hint" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:1.2rem;background:rgba(158,27,50,0.08);border:1px solid rgba(158,27,50,0.25);border-radius:12px;padding:.6rem 1rem;font-size:.84rem">
            <div style="display:flex;align-items:center;gap:.5rem">
                <span style="color:#f43f5e;font-weight:700">Article détecté :</span>
                <span id="ai-modal-detected-product" style="font-weight:600;color:var(--text, #fff)">Article de vente</span>
            </div>
            <div style="display:flex;align-items:center;gap:.4rem;font-size:.78rem;color:var(--text-soft, #a1a1aa)">
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981"></span>
                <span>Détourage haute précision & ombres physiques</span>
            </div>
        </div>

        <!-- Onglets du Studio (Comparateur vs Galerie de styles) -->
        <div style="display:flex;gap:.5rem;margin-bottom:1.1rem;border-bottom:1px solid rgba(255,255,255,0.1);padding-bottom:.6rem">
            <button type="button" id="tab-btn-compare" onclick="switchAiStudioTab('compare')" style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);color:#fff;font-weight:700;font-size:.86rem;padding:.45rem 1rem;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:all .15s">
                <span>⚡ Comparatif Avant / Après</span>
            </button>
            <button type="button" id="tab-btn-gallery" onclick="switchAiStudioTab('gallery')" style="background:transparent;border:1px solid transparent;color:var(--text-soft, #a1a1aa);font-weight:600;font-size:.86rem;padding:.45rem 1rem;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:all .15s">
                <span>🖼️ Galerie des 4 Styles Studio</span>
            </button>
        </div>

        <!-- VUE 1 : COMPARATIF AVANT / APRÈS (Interactive Side-by-Side) -->
        <div id="ai-studio-view-compare" style="display:block;margin-bottom:1.3rem">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.1rem;margin-bottom:1rem" class="studio-compare-grid">
                
                <!-- Colonne Gauche : Photo Originale -->
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:.85rem;display:flex;flex-direction:column;gap:.6rem">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-soft, #a1a1aa)">Avant (Originale)</span>
                        <span style="font-size:.72rem;background:rgba(255,255,255,0.1);padding:2px 7px;border-radius:5px;color:#fff">Votre cliché</span>
                    </div>
                    <div style="position:relative;width:100%;aspect-ratio:1/1;border-radius:12px;overflow:hidden;background:#000;display:flex;align-items:center;justify-content:center">
                        <img id="ai-compare-source-img" src="" alt="Photo originale" style="width:100%;height:100%;object-fit:contain">
                    </div>
                    <span style="font-size:.75rem;color:var(--text-soft, #a1a1aa);text-align:center">Cliché brut sans retouche</span>
                </div>

                <!-- Colonne Droite : Rendu Studio IA -->
                <div style="background:rgba(158,27,50,0.06);border:1.5px solid rgba(197,34,61,0.35);border-radius:16px;padding:.85rem;display:flex;flex-direction:column;gap:.6rem;box-shadow:0 8px 24px rgba(158,27,50,0.25)">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;color:#f43f5e">Après (Studio IA)</span>
                        <span id="ai-compare-style-badge" style="font-size:.72rem;background:linear-gradient(135deg, #9e1b32, #c5223d);padding:2px 8px;border-radius:5px;color:#fff;font-weight:700">Studio Blanc</span>
                    </div>
                    <div style="position:relative;width:100%;aspect-ratio:1/1;border-radius:12px;overflow:hidden;background:#ffffff;display:flex;align-items:center;justify-content:center">
                        <img id="ai-compare-target-img" src="" alt="Packshot studio IA" style="width:100%;height:100%;object-fit:cover">
                        
                        <!-- Bouton loupe aperçu plein écran -->
                        <button type="button" onclick="zoomAiVariation(document.getElementById('ai-compare-target-img').src)" title="Agrandir pour vérifier chaque détail" style="position:absolute;top:8px;right:8px;width:32px;height:32px;border-radius:50%;background:rgba(0,0,0,0.7);border:1px solid rgba(255,255,255,0.25);color:#fff;display:grid;place-items:center;cursor:pointer;backdrop-filter:blur(4px);z-index:5">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        </button>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span id="ai-compare-style-title" style="font-size:.78rem;font-weight:700;color:var(--text, #fff)">Studio Blanc Épuré</span>
                        <span style="font-size:.72rem;color:#10b981;font-weight:600">✓ Produit 100% fidèle</span>
                    </div>
                </div>

            </div>

            <!-- Barre de sélection rapide de style sous le comparateur -->
            <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:.75rem .9rem">
                <div style="font-size:.78rem;font-weight:700;color:var(--text-soft, #a1a1aa);margin-bottom:.55rem;text-transform:uppercase;letter-spacing:0.5px">
                    Changer l'ambiance du packshot en 1 clic :
                </div>
                <div id="ai-compare-style-buttons" style="display:flex;gap:.5rem;flex-wrap:wrap">
                    <!-- Rempli par JavaScript avec les styles disponibles -->
                </div>
            </div>
        </div>

        <!-- VUE 2 : GRILLE DE TOUS LES STYLES STUDIO -->
        <div id="ai-studio-view-gallery" style="display:none;margin-bottom:1.3rem">
            <div id="ai-variations-grid" style="display:grid;grid-template-columns:repeat(2, 1fr);gap:1rem">
                <!-- Injecté par JavaScript -->
            </div>
        </div>

        <!-- Contrôle Qualité Automatique (Quality Gate Checklist) -->
        <div style="background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.2);border-radius:12px;padding:.75rem 1rem;margin-bottom:1.4rem;display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:.6rem;font-size:.78rem">
            <div style="display:flex;align-items:center;gap:.4rem;color:#10b981;font-weight:600">
                <span>✓</span> <span style="color:var(--text, #fff)">Produit 100% conservé</span>
            </div>
            <div style="display:flex;align-items:center;gap:.4rem;color:#10b981;font-weight:600">
                <span>✓</span> <span style="color:var(--text, #fff)">Détourage professionnel</span>
            </div>
            <div style="display:flex;align-items:center;gap:.4rem;color:#10b981;font-weight:600">
                <span>✓</span> <span style="color:var(--text, #fff)">Cadrage carré 1:1 centré</span>
            </div>
            <div style="display:flex;align-items:center;gap:.4rem;color:#10b981;font-weight:600">
                <span>✓</span> <span style="color:var(--text, #fff)">Ombres physiques naturelles</span>
            </div>
        </div>

        <!-- Barre d'Actions Principales (Section 6 & Règle Commerçant a le dernier mot) -->
        <div style="display:flex;flex-direction:column;gap:.75rem">
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:.75rem" class="studio-actions-grid">
                <!-- Bouton 1 : Valider et utiliser cette image -->
                <button type="button" id="btn-confirm-ai-selection" onclick="confirmAiPhotoSelection()" class="btn" style="background:linear-gradient(135deg, #9e1b32, #c5223d);color:#fff;border:none;font-weight:800;padding:.95rem 1.4rem;border-radius:14px;font-size:1rem;display:flex;align-items:center;justify-content:center;gap:.6rem;box-shadow:0 6px 20px rgba(158,27,50,0.45);cursor:pointer;transition:transform .15s, box-shadow .15s" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'">
                    <x-icon name="check" size="20" />
                    <span id="btn-confirm-text">Utiliser cette photo studio</span>
                </button>

                <!-- Bouton 2 : Réessayer / Générer une autre variante -->
                <button type="button" onclick="requestAnotherAiVariant()" id="btn-retry-variant" class="btn btn-ghost" style="border:1px solid rgba(255,255,255,0.18);background:rgba(255,255,255,0.06);color:var(--text, #fff);font-weight:700;padding:.95rem 1.1rem;border-radius:14px;font-size:.9rem;display:flex;align-items:center;justify-content:center;gap:.45rem;cursor:pointer;transition:all .15s">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.19"/></svg>
                    <span>Réessayer (+6 cr)</span>
                </button>
            </div>

            <!-- Bouton 3 : Conserver l'originale sans modification -->
            <button type="button" onclick="keepOriginalPhoto()" class="btn btn-ghost" style="border:none;color:var(--text-soft, #a1a1aa);font-size:.85rem;font-weight:600;cursor:pointer;padding:.4rem;text-align:center;text-decoration:underline">
                Conserver ma photo originale sans modification
            </button>
        </div>
    </div>
</div>

<!-- Modal Aperçu Plein Écran (Lightbox haute résolution) -->
<div id="ai-photo-lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.94);backdrop-filter:blur(12px);z-index:100000;align-items:center;justify-content:center;padding:1.5rem" onclick="closeAiLightbox()">
    <div style="position:relative;max-width:92vw;max-height:88vh;display:flex;align-items:center;justify-content:center" onclick="event.stopPropagation()">
        <img id="ai-lightbox-img" src="" alt="Aperçu grand format" style="max-width:100%;max-height:88vh;border-radius:14px;box-shadow:0 16px 50px rgba(0,0,0,0.9);object-fit:contain">
        <button type="button" onclick="closeAiLightbox()" style="position:absolute;top:-14px;right:-14px;width:38px;height:38px;border-radius:50%;background:#ffffff;color:#000;border:none;font-size:20px;font-weight:bold;cursor:pointer;display:grid;place-items:center;box-shadow:0 6px 16px rgba(0,0,0,0.5)">×</button>
    </div>
</div>

<style>
@media (max-width: 640px) {
    .studio-compare-grid {
        grid-template-columns: 1fr !important;
    }
    .studio-actions-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>
