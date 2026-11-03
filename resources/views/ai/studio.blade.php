@extends('layouts.app')

@section('title', 'Studio Visuel IA — Générateur d\'Images & Affiches | ' . config('platform.name'))

@section('content')
<div class="container dash">
    <div class="dash-head">
        <div>
            <p style="margin:0"><a href="{{ route('ai.assistant') }}" style="color:var(--brand-1);text-decoration:none">← Retour à l'Assistant IA</a></p>
            <div style="display:flex;align-items:center;gap:.65rem;margin-top:.35rem">
                <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg, #e11d48, #7c3aed);display:flex;align-items:center;justify-content:center;color:#fff">
                    <x-icon name="sparkles" :size="20" />
                </div>
                <div>
                    <h1 style="margin:0;font-size:1.5rem;font-weight:800;letter-spacing:-0.02em">Studio Visuel IA</h1>
                    <p style="margin:0;font-size:.85rem;color:var(--text-soft)">Générez des photos de produits en showroom, affiches publicitaires et bannières réseaux sociaux via le moteur Flux HD.</p>
                </div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:.75rem">
            <span class="ai-studio-credit-badge">
                <x-icon name="sparkles" :size="14" />
                <span>5 crédits / visuel</span>
            </span>
        </div>
    </div>

    <div class="ai-studio-grid">
        <!-- FORMULAIRE DE CRÉATION -->
        <section class="card panel ai-studio-form-panel">
            <h3 style="margin-top:0;display:flex;align-items:center;gap:.5rem">
                <span>1. Définir le visuel</span>
            </h3>

            <form id="ai-visual-form" onsubmit="handleGenerateVisual(event)">
                <!-- Option 1 : Choix d'un produit existant -->
                <div class="field">
                    <label for="product_select">Produit de votre catalogue (optionnel)</label>
                    <select id="product_select" class="input" onchange="handleProductSelect(this)">
                        <option value="">-- Visuel libre ou personnalisé --</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" data-title="{{ $p->title }}">
                                {{ $p->title }} ({{ number_format($p->price, 0, ',', ' ') }} {{ $shop->currency }})
                            </option>
                        @endforeach
                    </select>
                    <div class="hint">Sélectionnez un produit pour pré-remplir la description et y associer l'image générée.</div>
                </div>

                <!-- Description / Sujet de l'image -->
                <div class="field">
                    <label for="visual_subject">Description du sujet ou du produit <span style="color:var(--danger)">*</span></label>
                    <textarea id="visual_subject" name="subject" class="input" rows="3" placeholder="Ex: Élégante robe en wax moderne avec finitions dorées portée par un mannequin souriant..." required></textarea>
                </div>

                <!-- Choix du style visuel -->
                <div class="field">
                    <label>Style de présentation</label>
                    <div class="ai-styles-selector">
                        <label class="ai-style-card active">
                            <input type="radio" name="style" value="studio_minimalist" checked>
                            <div class="style-icon">🏢</div>
                            <div class="style-title">Studio Minimaliste</div>
                            <div class="style-desc">Fond épuré, éclairage commercial doux</div>
                        </label>

                        <label class="ai-style-card">
                            <input type="radio" name="style" value="luxury_showroom">
                            <div class="style-icon">✨</div>
                            <div class="style-title">Showroom Luxe</div>
                            <div class="style-desc">Marbre, reflets dorés, ambiance premium</div>
                        </label>

                        <label class="ai-style-card">
                            <input type="radio" name="style" value="lifestyle_urban">
                            <div class="style-icon">🏙️</div>
                            <div class="style-title">Urbain Authentique</div>
                            <div class="style-desc">Style de vie moderne en Afrique de l'Ouest</div>
                        </label>

                        <label class="ai-style-card">
                            <input type="radio" name="style" value="flash_sale">
                            <div class="style-icon">🔥</div>
                            <div class="style-title">Promo & Vente Flash</div>
                            <div class="style-desc">Affiche publicitaire percutante et dynamique</div>
                        </label>

                        <label class="ai-style-card">
                            <input type="radio" name="style" value="tropical_african">
                            <div class="style-icon">🌿</div>
                            <div class="style-title">Afro Chic Chaleureux</div>
                            <div class="style-desc">Terracotta, plantes, lumière dorée</div>
                        </label>
                    </div>
                </div>

                <!-- Choix du format -->
                <div class="field">
                    <label>Format d'utilisation</label>
                    <div class="ai-format-selector">
                        <label class="ai-format-btn active">
                            <input type="radio" name="format" value="1:1" checked>
                            <strong>1:1 Carré</strong>
                            <span>WhatsApp / Instagram Post</span>
                        </label>
                        <label class="ai-format-btn">
                            <input type="radio" name="format" value="9:16">
                            <strong>9:16 Story</strong>
                            <span>Statuts WhatsApp / Reels / TikTok</span>
                        </label>
                        <label class="ai-format-btn">
                            <input type="radio" name="format" value="16:9">
                            <strong>16:9 Bannière</strong>
                            <span>Bannière de boutique & Facebook</span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="ai-generate-btn" style="width:100%;padding:.85rem;font-size:1rem;display:flex;align-items:center;justify-content:center;gap:.5rem">
                    <x-icon name="sparkles" :size="18" />
                    <span id="btn-text">Générer le visuel haute définition</span>
                </button>
            </form>
        </section>

        <!-- ESPACE RÉSULTAT & APERÇU -->
        <section class="card panel ai-studio-preview-panel">
            <h3 style="margin-top:0">2. Aperçu du visuel généré</h3>

            <div class="ai-preview-canvas" id="preview-canvas">
                <!-- État par défaut -->
                <div class="ai-canvas-empty" id="canvas-empty">
                    <div class="empty-icon">
                        <x-icon name="sparkles" :size="48" />
                    </div>
                    <h4>Votre création apparaîtra ici</h4>
                    <p>Sélectionnez un style, un format et lancez la génération pour voir votre produit sublimé par l'IA.</p>
                </div>

                <!-- État de chargement avec animation -->
                <div class="ai-canvas-loading" id="canvas-loading" style="display:none">
                    <div class="ai-spinner"></div>
                    <h4>Génération haute résolution en cours...</h4>
                    <p id="loading-step-text">Application du style showroom et rendu des lumières studio...</p>
                </div>

                <!-- Image finale générée -->
                <div class="ai-canvas-result" id="canvas-result" style="display:none">
                    <img id="generated-img" src="" alt="Visuel IA généré">
                    <div class="ai-result-actions">
                        <a id="download-link" href="#" download="visuel-ia.jpg" class="btn btn-sm" style="background:#fff;color:#000;font-weight:700">
                            <x-icon name="download" :size="15" />
                            <span>Télécharger HD</span>
                        </a>
                        <button type="button" class="btn btn-sm btn-primary" onclick="alert('Image enregistrée dans votre galerie de boutique avec succès !')">
                            <x-icon name="check" :size="15" />
                            <span>Enregistré dans la boutique</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- HISTORIQUE DES VISUELS RÉCENTS -->
    @if($recentVisuals->isNotEmpty())
        <div style="margin-top:2.5rem">
            <h3 style="margin-bottom:1rem">Derniers visuels générés pour votre boutique</h3>
            <div class="ai-recent-visuals-grid">
                @foreach($recentVisuals as $vis)
                    @php 
                        $out = $vis->output_data ?? [];
                        $path = $out['path'] ?? null;
                    @endphp
                    @if($path)
                        <div class="ai-visual-card">
                            <img src="{{ Storage::disk('public')->url($path) }}" alt="Visuel" loading="lazy">
                            <div class="ai-visual-meta">
                                <span>{{ $vis->created_at->diffForHumans() }}</span>
                                <a href="{{ Storage::disk('public')->url($path) }}" download="visuel-{{ $vis->id }}.jpg" class="download-mini-btn" title="Télécharger">
                                    <x-icon name="download" :size="14" />
                                </a>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>

<style>
.ai-studio-credit-badge {
    background: linear-gradient(135deg, rgba(225, 29, 72, 0.15), rgba(124, 58, 237, 0.15));
    border: 1px solid rgba(225, 29, 72, 0.35);
    color: #f43f5e;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 0.4rem 0.8rem;
    border-radius: 99px;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.ai-studio-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-top: 1.5rem;
}

/* Styles cards selector */
.ai-styles-selector {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 0.6rem;
}

.ai-style-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
    border-radius: 10px;
    padding: 0.75rem 0.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.ai-style-card input[type="radio"] {
    display: none;
}

.ai-style-card:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(225, 29, 72, 0.4);
}

.ai-style-card.active {
    background: rgba(225, 29, 72, 0.12);
    border-color: #e11d48;
    box-shadow: 0 0 0 2px rgba(225, 29, 72, 0.25);
}

.style-icon {
    font-size: 1.5rem;
    margin-bottom: 0.3rem;
}

.style-title {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text, #fff);
    margin-bottom: 0.15rem;
}

.style-desc {
    font-size: 0.68rem;
    color: var(--text-soft, #a1a1aa);
    line-height: 1.2;
}

/* Format selector */
.ai-format-selector {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.6rem;
}

.ai-format-btn {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
    border-radius: 8px;
    padding: 0.6rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
}

.ai-format-btn input[type="radio"] {
    display: none;
}

.ai-format-btn.active {
    background: rgba(225, 29, 72, 0.12);
    border-color: #e11d48;
}

.ai-format-btn strong {
    font-size: 0.82rem;
    color: var(--text, #fff);
}

.ai-format-btn span {
    font-size: 0.68rem;
    color: var(--text-soft, #a1a1aa);
}

/* Preview Canvas */
.ai-preview-canvas {
    min-height: 440px;
    background: rgba(0, 0, 0, 0.2);
    border: 2px dashed var(--border, rgba(255, 255, 255, 0.1));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
}

.ai-canvas-empty {
    text-align: center;
    max-width: 320px;
    color: var(--text-soft, #a1a1aa);
}

.empty-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.05);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    color: #e11d48;
}

.ai-canvas-empty h4 {
    margin: 0 0 0.35rem;
    color: var(--text, #fff);
}

.ai-canvas-loading {
    text-align: center;
}

.ai-spinner {
    width: 44px;
    height: 44px;
    border: 3px solid rgba(225, 29, 72, 0.2);
    border-top-color: #e11d48;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1.25rem;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.ai-canvas-result {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.ai-canvas-result img {
    max-height: 420px;
    max-width: 100%;
    border-radius: 10px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
    object-fit: contain;
}

.ai-result-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    justify-content: center;
}

/* Recent visuals */
.ai-recent-visuals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 1rem;
}

.ai-visual-card {
    border-radius: 10px;
    overflow: hidden;
    background: var(--surface, #18181f);
    border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
    position: relative;
    aspect-ratio: 1;
}

.ai-visual-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.ai-visual-meta {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
    padding: 0.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.7rem;
    color: #fff;
}

.download-mini-btn {
    color: #fff;
    background: rgba(255,255,255,0.2);
    padding: 4px;
    border-radius: 4px;
    display: flex;
    align-items: center;
}

@media (max-width: 900px) {
    .ai-studio-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.querySelectorAll('.ai-style-card input').forEach(input => {
    input.addEventListener('change', () => {
        document.querySelectorAll('.ai-style-card').forEach(c => c.classList.remove('active'));
        input.closest('.ai-style-card').classList.add('active');
    });
});

document.querySelectorAll('.ai-format-btn input').forEach(input => {
    input.addEventListener('change', () => {
        document.querySelectorAll('.ai-format-btn').forEach(c => c.classList.remove('active'));
        input.closest('.ai-format-btn').classList.add('active');
    });
});

function handleProductSelect(select) {
    const selectedOpt = select.options[select.selectedIndex];
    const title = selectedOpt.getAttribute('data-title');
    if (title) {
        document.getElementById('visual_subject').value = `${title} présenté de manière attrayante et élégante`;
    }
}

async function handleGenerateVisual(e) {
    e.preventDefault();

    const form = document.getElementById('ai-visual-form');
    const subject = document.getElementById('visual_subject').value.trim();
    if (!subject) return;

    const style = form.querySelector('input[name="style"]:checked').value;
    const format = form.querySelector('input[name="format"]:checked').value;
    const productId = document.getElementById('product_select').value;

    const btn = document.getElementById('ai-generate-btn');
    const btnText = document.getElementById('btn-text');
    btn.disabled = true;
    btnText.innerText = 'Génération en cours...';

    const canvasEmpty = document.getElementById('canvas-empty');
    const canvasLoading = document.getElementById('canvas-loading');
    const canvasResult = document.getElementById('canvas-result');

    if (canvasEmpty) canvasEmpty.style.display = 'none';
    if (canvasResult) canvasResult.style.display = 'none';
    canvasLoading.style.display = 'block';

    try {
        const res = await fetch('{{ route('ai.studio.generate') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                subject: subject,
                style: style,
                format: format,
                product_id: productId ? parseInt(productId) : null
            })
        });

        const data = await res.json();
        canvasLoading.style.display = 'none';

        if (data.status === 'success') {
            document.getElementById('generated-img').src = data.image_url;
            document.getElementById('download-link').href = data.image_url;
            canvasResult.style.display = 'flex';
        } else {
            alert(data.message || 'Erreur lors de la génération du visuel.');
            if (canvasEmpty) canvasEmpty.style.display = 'block';
        }

    } catch (err) {
        canvasLoading.style.display = 'none';
        if (canvasEmpty) canvasEmpty.style.display = 'block';
        alert('Erreur de connexion au serveur IA.');
        console.error(err);
    } finally {
        btn.disabled = false;
        btnText.innerText = 'Générer le visuel haute définition';
    }
}
</script>
@endsection
