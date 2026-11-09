@auth
@if(!request()->routeIs('ai.assistant'))
@php
    $bubbleUser = auth()->user();
    $bubbleShop = $bubbleUser->shop ?? ($bubbleUser->isAdmin() ? \App\Models\Shop::first() : null);
    $bubbleCreditsRemaining = $bubbleShop ? $bubbleShop->aiCreditsRemaining() : 80;
    $bubbleCreditsMonthly = $bubbleShop ? $bubbleShop->aiCreditsMonthly() : 80;
    $bubbleShopName = $bubbleShop?->name ?: 'Votre boutique';
    $bubbleActiveConv = $bubbleShop ? $bubbleShop->aiConversations()
        ->where('last_message_at', '>=', now()->subHours(2))
        ->latest('last_message_at')
        ->first() : null;
    $bubbleInitialConvId = $bubbleActiveConv?->id;
@endphp

<!-- ============================================================== -->
<!-- ECOM AI ASSISTANT : BUBBLE DE CHAT FLOTTANT (STYLE SHAFRIK)   -->
<!-- ============================================================== -->

<!-- Backdrop d'occultation sur mobile -->
<div id="ai-bubble-backdrop" class="ai-bubble-backdrop" onclick="closeAiChatBubble()"></div>

<!-- Bouton flottant (Bubble Trigger) -->
<div id="ai-bubble-launcher" class="ai-bubble-launcher" onclick="toggleAiChatBubble()" role="button" aria-label="Ouvrir le copilote Zanika IA" title="Zanika Copilote IA">
    <div class="ai-bubble-halo"></div>
    <div class="ai-bubble-avatar-wrap">
        <img src="{{ asset('images/brand/02_icone_application.png') }}" alt="Zanika IA" class="ai-bubble-avatar-img" />
    </div>
    <!-- Badge étoile scintillante Zanika -->
    <div class="ai-bubble-sparkle-badge" aria-hidden="true">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
            <path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>
        </svg>
    </div>
    <!-- Badge notification ou pulsation active -->
    <span class="ai-bubble-pulse-dot"></span>
</div>

<!-- Fenêtre de Chat Flottante (Drawer / Popup) -->
<aside id="ai-chat-window" class="ai-chat-window" role="dialog" aria-labelledby="ai-chat-window-title" aria-modal="true" style="display:none;">
    <!-- En-tête du Chat Flottant -->
    <header class="ai-window-header">
        <div class="ai-header-lead">
            <div class="ai-header-avatar">
                <img src="{{ asset('images/brand/02_icone_application.png') }}" alt="Zanika IA" />
                <span class="ai-online-status"></span>
            </div>
            <div>
                <div class="ai-header-title-row">
                    <span id="ai-chat-window-title" class="ai-window-title">ZANIKA AI</span>
                    <span class="ai-chip-pill">COPILOTE</span>
                </div>
                <div class="ai-window-subtitle">Connecté à <strong>{{ Str::limit($bubbleShopName, 18) }}</strong></div>
            </div>
        </div>

        <div class="ai-header-actions">
            <!-- Solde de crédits -->
            <div class="ai-bubble-credits" title="Crédits IA restants ce mois-ci">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>
                <span id="ai-bubble-credits-val">{{ $bubbleCreditsRemaining }}</span>
            </div>

            <!-- Ouvrir en pleine page -->
            <a href="{{ route('ai.assistant') }}" class="ai-btn-icon ai-btn-expand" title="Ouvrir en plein écran" aria-label="Plein écran">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
            </a>

            <!-- Réinitialiser / Nouvelle discussion -->
            <button type="button" class="ai-btn-icon" onclick="resetAiBubbleChat()" title="Nouvelle discussion" aria-label="Nouveau chat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
            </button>

            <!-- Fermer -->
            <button type="button" class="ai-btn-icon ai-close-btn" onclick="closeAiChatBubble()" title="Fermer" aria-label="Fermer">✕</button>
        </div>
    </header>

    <!-- Zone de défilement des messages -->
    <div class="ai-window-body" id="ai-bubble-messages">
        <!-- Message de bienvenue si nouveau chat -->
        <div class="ai-welcome-box" id="ai-bubble-welcome">
            <div class="ai-welcome-avatar">
                <img src="{{ asset('images/brand/02_icone_application.png') }}" alt="Zanika Copilote" />
            </div>
            <h4>Bonjour, comment puis-je vous aider ?</h4>
            <p>Je suis votre copilote commercial Zanika, connecté à vos produits, stocks, commandes et livraisons.</p>

            <div class="ai-quick-prompts">
                <button type="button" class="ai-prompt-btn" onclick="sendAiBubblePrompt('Fais-moi un point rapide sur mes stocks et les ruptures.')">
                    <x-icon name="package" :size="14" /> <span>État des stocks & ruptures</span>
                </button>
                <button type="button" class="ai-prompt-btn" onclick="sendAiBubblePrompt('Combien de commandes COD sont en attente de confirmation ?')">
                    <x-icon name="truck" :size="14" /> <span>Commandes COD en attente</span>
                </button>
                <button type="button" class="ai-prompt-btn" onclick="sendAiBubblePrompt('Donne-moi le bilan des ventes et mes 2 meilleures actions du jour.')">
                    <x-icon name="chart" :size="14" /> <span>Bilan des ventes & opportunités</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Indicateur de saisie / réflexion IA -->
    <div class="ai-typing-indicator" id="ai-bubble-typing" style="display:none;">
        <span class="ai-typing-dot"></span>
        <span class="ai-typing-dot"></span>
        <span class="ai-typing-dot"></span>
        <span style="font-size:0.75rem;color:var(--text-soft);margin-left:4px">Réflexion et analyse...</span>
    </div>

    <!-- Pied de page avec champ de saisie -->
    <footer class="ai-window-footer">
        <form id="ai-bubble-form" onsubmit="handleAiBubbleSubmit(event)" class="ai-bubble-input-form">
            <input 
                type="text" 
                id="ai-bubble-input" 
                class="ai-bubble-input-field" 
                placeholder="Posez une question à votre copilote..." 
                autocomplete="off" 
                maxlength="1000"
            />
            <button type="submit" id="ai-bubble-send-btn" class="ai-bubble-send-btn" aria-label="Envoyer">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </form>
    </footer>
</aside>

<!-- ============================================================== -->
<!-- STYLES CSS DU BUBBLE DE CHAT FLOTTANT                           -->
<!-- ============================================================== -->
<style>
/* 1. Bouton Déclencheur Flottant (Bubble) */
.ai-bubble-launcher {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 62px;
    height: 62px;
    border-radius: 50%;
    cursor: pointer;
    z-index: 99990;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #090c15;
    border: 2px solid rgba(255, 45, 109, 0.45);
    box-shadow: 0 10px 28px -4px rgba(225, 29, 72, 0.5), 0 0 0 2.5px rgba(255, 255, 255, 0.95), 0 0 22px rgba(255, 45, 109, 0.35);
    transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.25s ease;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

[data-theme='dark'] .ai-bubble-launcher {
    box-shadow: 0 10px 28px -4px rgba(225, 29, 72, 0.5), 0 0 0 2.5px rgba(255, 255, 255, 0.15), 0 0 22px rgba(255, 45, 109, 0.35);
}

@media (max-width: 768px) {
    .ai-bubble-launcher {
        /* Positionné juste au-dessus de la bottom-nav mobile (~60px) pour être accessible sous le pouce */
        bottom: calc(74px + env(safe-area-inset-bottom, 0px));
        right: 16px;
        width: 58px;
        height: 58px;
    }
}

.ai-bubble-launcher:hover {
    transform: scale(1.08) translateY(-2px);
    box-shadow: 0 14px 35px -4px rgba(225, 29, 72, 0.65), 0 0 0 2.5px #ffffff, 0 0 28px rgba(255, 45, 109, 0.5);
}

.ai-bubble-launcher:active {
    transform: scale(0.95);
}

.ai-bubble-avatar-wrap {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #090c15;
}

.ai-bubble-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

/* Badge étincelle rose/crimson Zanika */
.ai-bubble-sparkle-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 20px;
    height: 20px;
    background: linear-gradient(135deg, #FF2D6D, #BE123C);
    color: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(225, 29, 72, 0.6);
    border: 2px solid #ffffff;
    animation: aiSparkleSpin 4s ease-in-out infinite;
}

@keyframes aiSparkleSpin {
    0%, 100% { transform: scale(1) rotate(0deg); }
    50% { transform: scale(1.15) rotate(15deg); }
}

.ai-bubble-pulse-dot {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 12px;
    height: 12px;
    background: #10b981;
    border-radius: 50%;
    border: 2px solid #ffffff;
}

/* Halo doux animé aux couleurs Zanika autour du bubble */
.ai-bubble-halo {
    position: absolute;
    inset: -6px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 45, 109, 0.32) 0%, transparent 70%);
    z-index: -1;
    animation: aiHaloPulse 3s ease-in-out infinite;
}

@keyframes aiHaloPulse {
    0%, 100% { transform: scale(0.95); opacity: 0.5; }
    50% { transform: scale(1.15); opacity: 0.9; }
}

/* 2. Fenêtre de Chat Flottante (Drawer / Popup) */
.ai-chat-window {
    position: fixed;
    bottom: 96px;
    right: 24px;
    width: 390px;
    height: 580px;
    max-height: calc(100vh - 120px);
    background: var(--bg-card, #101423);
    border: 1px solid rgba(255, 45, 109, 0.25);
    border-radius: 20px;
    box-shadow: 0 25px 60px -10px rgba(0, 0, 0, 0.6), 0 0 35px rgba(255, 45, 109, 0.18);
    display: flex;
    flex-direction: column;
    z-index: 99995;
    overflow: hidden;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    animation: aiWindowPop 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes aiWindowPop {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.94);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (max-width: 768px) {
    .ai-chat-window {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        max-width: 100vw !important;
        height: 100% !important;
        height: 100dvh !important;
        max-height: 100dvh !important;
        margin: 0 !important;
        border-radius: 0 !important;
        border: none !important;
        box-shadow: none !important;
        z-index: 99999 !important;
        display: flex;
        flex-direction: column;
        animation: aiDrawerSlideUpMobile 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
        background: #0d111d;
    }

    @keyframes aiDrawerSlideUpMobile {
        from {
            transform: translateY(100%);
            opacity: 0.9;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .ai-window-header {
        padding-top: max(0.85rem, env(safe-area-inset-top, 0px)) !important;
        padding-left: 1rem !important;
        padding-right: 1rem !important;
        padding-bottom: 0.75rem !important;
        background: rgba(16, 20, 35, 0.98) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
        flex-shrink: 0;
    }

    .ai-btn-expand {
        display: none !important;
    }

    .ai-btn-icon {
        width: 36px !important;
        height: 36px !important;
        border-radius: 10px !important;
    }

    .ai-btn-icon.ai-close-btn {
        width: 36px !important;
        height: 36px !important;
        background: rgba(255, 255, 255, 0.08) !important;
        border-radius: 10px !important;
        font-size: 1.15rem !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .ai-window-body {
        padding: 1.25rem 1rem !important;
        background: #090c16 !important;
    }

    .ai-welcome-box {
        padding: 1.25rem 0.5rem 0.5rem !important;
    }

    .ai-welcome-avatar {
        width: 64px !important;
        height: 64px !important;
    }

    .ai-welcome-box h4 {
        font-size: 1.05rem !important;
    }

    .ai-welcome-box p {
        font-size: 0.85rem !important;
        max-width: 320px !important;
    }

    .ai-prompt-btn {
        padding: 0.75rem 1rem !important;
        font-size: 0.86rem !important;
        min-height: 46px !important;
        border-radius: 14px !important;
    }

    .ai-window-footer {
        padding-bottom: max(1rem, env(safe-area-inset-bottom, 0px)) !important;
        padding-top: 0.75rem !important;
        padding-left: 1rem !important;
        padding-right: 1rem !important;
        background: rgba(16, 20, 35, 0.98) !important;
        border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
        flex-shrink: 0;
    }

    .ai-bubble-input-form {
        min-height: 48px !important;
        padding: 0.35rem 0.45rem 0.35rem 1rem !important;
    }

    .ai-bubble-input-field,
    input#ai-bubble-input {
        font-size: 16px !important; /* EMPÊCHE L'AUTO-ZOOM SUR IOS SAFARI */
        touch-action: manipulation !important;
        -webkit-text-size-adjust: 100% !important;
    }

    .ai-bubble-send-btn {
        width: 38px !important;
        height: 38px !important;
    }

    .ai-bubble-backdrop {
        display: none;
        position: fixed !important;
        inset: 0 !important;
        background: #090c16 !important;
        z-index: 99994 !important;
    }

    .ai-bubble-backdrop.active {
        display: block !important;
    }

    /* Mode clair sur mobile */
    [data-theme='light'] .ai-bubble-backdrop {
        background: #ffffff !important;
    }

    [data-theme='light'] .ai-chat-window {
        background: #ffffff !important;
    }

    [data-theme='light'] .ai-window-header {
        background: #ffffff !important;
        border-bottom: 1px solid #e2e8f0 !important;
    }

    [data-theme='light'] .ai-btn-icon.ai-close-btn {
        background: #f1f5f9 !important;
        color: #0f172a !important;
    }

    [data-theme='light'] .ai-window-body {
        background: #f8fafc !important;
    }

    [data-theme='light'] .ai-window-footer {
        background: #ffffff !important;
        border-top: 1px solid #e2e8f0 !important;
    }

    [data-theme='light'] .ai-bubble-input-form {
        background: #f1f5f9 !important;
        border-color: #cbd5e1 !important;
    }

    [data-theme='light'] .ai-bubble-input-field {
        color: #0f172a !important;
    }
}

/* Verrouillage strict et absolu du scroll en arrière-plan lorsque la bulle est ouverte sur mobile */
body.ai-chat-open {
    position: fixed !important;
    width: 100% !important;
    height: 100% !important;
    overflow: hidden !important;
    touch-action: none !important;
}

/* En-tête */
.ai-window-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.ai-header-lead {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.ai-header-avatar {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    position: relative;
    overflow: hidden;
    background: #090c15;
    box-shadow: 0 2px 8px rgba(225, 29, 72, 0.35);
    border: 1.5px solid rgba(255, 45, 109, 0.35);
    flex-shrink: 0;
}

.ai-header-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.ai-online-status {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 9px;
    height: 9px;
    background: #10b981;
    border-radius: 50%;
    border: 1.5px solid #ffffff;
}

.ai-header-title-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.ai-window-title {
    font-size: 0.88rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--text-primary, #ffffff);
}

.ai-chip-pill {
    font-size: 0.62rem;
    font-weight: 800;
    background: linear-gradient(135deg, #FF2D6D, #BE123C);
    color: #ffffff;
    padding: 1px 6px;
    border-radius: 6px;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 6px rgba(225, 29, 72, 0.35);
}

.ai-window-subtitle {
    font-size: 0.72rem;
    color: var(--text-soft, #94a3b8);
}

.ai-header-actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.ai-bubble-credits {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.5rem;
    background: rgba(255, 45, 109, 0.12);
    border: 1px solid rgba(255, 45, 109, 0.3);
    color: #FF2D6D;
    border-radius: 9999px;
    font-size: 0.72rem;
    font-weight: 700;
}

.ai-btn-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: transparent;
    border: none;
    color: var(--text-soft, #94a3b8);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
}

.ai-btn-icon:hover {
    background: rgba(255, 255, 255, 0.08);
    color: var(--text-primary, #ffffff);
}

.ai-close-btn {
    font-size: 1rem;
    font-weight: 700;
}

/* Corps du chat & messages */
.ai-window-body {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    background: var(--bg-primary, #090c16);
    -webkit-overflow-scrolling: touch;
}

/* Accueil & suggestions */
.ai-welcome-box {
    text-align: center;
    padding: 1.5rem 0.5rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.ai-welcome-avatar {
    width: 58px;
    height: 58px;
    border-radius: 14px;
    margin-bottom: 0.75rem;
    background: #090c15;
    box-shadow: 0 8px 24px rgba(225, 29, 72, 0.35);
    border: 2px solid rgba(255, 45, 109, 0.35);
    overflow: hidden;
}

.ai-welcome-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.ai-welcome-box h4 {
    font-size: 0.95rem;
    font-weight: 700;
    margin-bottom: 0.35rem;
    color: var(--text-primary, #ffffff);
}

.ai-welcome-box p {
    font-size: 0.78rem;
    color: var(--text-soft, #94a3b8);
    line-height: 1.4;
    max-width: 280px;
    margin-bottom: 1rem;
}

.ai-quick-prompts {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.ai-prompt-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.55rem 0.8rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 12px;
    color: var(--text-primary, #ffffff);
    font-size: 0.78rem;
    font-weight: 500;
    text-align: left;
    cursor: pointer;
    transition: all 0.15s ease;
    font-family: inherit;
}

.ai-prompt-btn:hover {
    background: rgba(255, 45, 109, 0.1);
    border-color: rgba(255, 45, 109, 0.35);
    color: #FF2D6D;
    transform: translateX(2px);
}

/* Lignes de messages */
.ai-bubble-msg {
    display: flex;
    flex-direction: column;
    max-width: 86%;
    animation: aiMsgIn 0.2s ease-out;
}

@keyframes aiMsgIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

.ai-bubble-msg.user {
    align-self: flex-end;
}

.ai-bubble-msg.assistant {
    align-self: flex-start;
}

.ai-msg-bubble {
    padding: 0.65rem 0.85rem;
    border-radius: 16px;
    font-size: 0.82rem;
    line-height: 1.5;
    word-break: break-word;
}

.ai-bubble-msg.user .ai-msg-bubble {
    background: linear-gradient(135deg, #E11D48, #9F1239);
    color: #ffffff;
    border-bottom-right-radius: 4px;
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.3);
}

.ai-bubble-msg.assistant .ai-msg-bubble {
    background: rgba(255, 255, 255, 0.06);
    color: var(--text-primary, #f1f5f9);
    border-bottom-left-radius: 4px;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.ai-msg-time {
    font-size: 0.65rem;
    color: var(--text-soft, #64748b);
    margin-top: 3px;
    padding: 0 4px;
}

.ai-bubble-msg.user .ai-msg-time {
    align-self: flex-end;
}

/* Cartes d'action sensible */
.ai-bubble-action-box {
    margin-top: 0.5rem;
    padding: 0.65rem;
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 12px;
    font-size: 0.78rem;
}

.ai-bubble-action-box strong {
    color: #fbbf24;
    display: block;
    margin-bottom: 0.35rem;
}

.ai-bubble-action-btns {
    display: flex;
    gap: 0.4rem;
    margin-top: 0.4rem;
}

.ai-action-btn-yes {
    background: #10b981;
    color: #ffffff;
    border: none;
    padding: 0.35rem 0.65rem;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 700;
    cursor: pointer;
}

.ai-action-btn-no {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-soft, #94a3b8);
    border: 1px solid rgba(255, 255, 255, 0.15);
    padding: 0.35rem 0.65rem;
    border-radius: 8px;
    font-size: 0.72rem;
    cursor: pointer;
}

/* Indicateur de saisie */
.ai-typing-indicator {
    padding: 0.4rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    background: rgba(255, 255, 255, 0.02);
}

.ai-typing-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #FF2D6D;
    animation: aiTypingBounce 1.2s infinite ease-in-out;
}

.ai-typing-dot:nth-child(2) { animation-delay: 0.2s; }
.ai-typing-dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes aiTypingBounce {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
    30% { transform: translateY(-4px); opacity: 1; }
}

/* Pied de page et barre d'envoi */
.ai-window-footer {
    padding: 0.75rem 0.85rem;
    background: rgba(255, 255, 255, 0.02);
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.ai-bubble-input-form {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 9999px;
    padding: 0.3rem 0.4rem 0.3rem 0.9rem;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.ai-bubble-input-form:focus-within {
    border-color: rgba(255, 45, 109, 0.6);
    box-shadow: 0 0 0 3px rgba(255, 45, 109, 0.18);
}

.ai-bubble-input-field {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: var(--text-primary, #ffffff);
    font-size: 16px !important; /* STRICTEMENT 16px pour éliminer tout auto-zoom sur iOS */
    font-family: inherit;
    line-height: 1.4;
    padding: 6px 0;
}

.ai-bubble-input-field::placeholder {
    color: var(--text-soft, #64748b);
}

.ai-bubble-send-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #FF2D6D, #BE123C);
    color: #ffffff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.ai-bubble-send-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 10px rgba(225, 29, 72, 0.5);
}

.ai-bubble-send-btn:active {
    transform: scale(0.95);
}

.ai-bubble-send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ==============================================================
   MODE CLAIR : FENÊTRE & BULLE FLOTTANTE IA
   ============================================================== */
[data-theme='light'] .ai-chat-window {
    background: #ffffff;
    border-color: #e2e8f0;
    box-shadow: 0 25px 60px -10px rgba(0, 0, 0, 0.15), 0 0 30px rgba(255, 45, 109, 0.12);
}

[data-theme='light'] .ai-window-header {
    background: #f8fafc;
    border-bottom-color: #e2e8f0;
}

[data-theme='light'] .ai-window-title {
    color: #0f172a;
}

[data-theme='light'] .ai-window-subtitle {
    color: #64748b;
}

[data-theme='light'] .ai-btn-icon {
    color: #64748b;
}

[data-theme='light'] .ai-btn-icon:hover {
    background: #edf2f7;
    color: #0f172a;
}

[data-theme='light'] .ai-window-body {
    background: #ffffff;
}

[data-theme='light'] .ai-welcome-box h4 {
    color: #0f172a;
}

[data-theme='light'] .ai-welcome-box p {
    color: #64748b;
}

[data-theme='light'] .ai-prompt-btn {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #334155;
}

[data-theme='light'] .ai-prompt-btn:hover {
    background: rgba(255, 45, 109, 0.08);
    border-color: rgba(255, 45, 109, 0.3);
    color: #BE123C;
}

[data-theme='light'] .ai-bubble-msg.assistant .ai-msg-bubble {
    background: #f1f5f9;
    border-color: #e2e8f0;
    color: #0f172a;
}

[data-theme='light'] .ai-msg-time {
    color: #94a3b8;
}

[data-theme='light'] .ai-window-footer {
    background: #f8fafc;
    border-top-color: #e2e8f0;
}

[data-theme='light'] .ai-bubble-input-form {
    background: #ffffff;
    border-color: #cbd5e1;
}

[data-theme='light'] .ai-bubble-input-form:focus-within {
    border-color: #E11D48;
    box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.18);
}

[data-theme='light'] .ai-bubble-input-field {
    color: #0f172a;
}

[data-theme='light'] .ai-bubble-input-field::placeholder {
    color: #94a3b8;
}
</style>

<!-- ============================================================== -->
<!-- LOGIQUE JAVASCRIPT DU BUBBLE & CHAT EN TEMPS RÉEL              -->
<!-- ============================================================== -->
<script>
(function() {
    let currentConversationId = {{ $bubbleInitialConvId ? (int) $bubbleInitialConvId : 'null' }};
    if (!currentConversationId) {
        const saved = localStorage.getItem('zanika_ai_bubble_conv_id');
        if (saved) currentConversationId = parseInt(saved, 10);
    }
    let isSending = false;
    let hasLoadedHistory = false;

    let savedScrollY = 0;

    async function loadActiveConversationIfNeeded() {
        if (hasLoadedHistory) return;
        hasLoadedHistory = true;

        let targetConvId = currentConversationId;
        if (!targetConvId) {
            const saved = localStorage.getItem('zanika_ai_bubble_conv_id');
            if (saved) targetConvId = parseInt(saved, 10);
        }

        let fetchUrl = targetConvId 
            ? "{{ url('/assistant-ia/conversations') }}/" + targetConvId
            : "{{ route('ai.conversations.active') }}";

        try {
            const resp = await fetch(fetchUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': "{{ csrf_token() }}"
                }
            });
            if (resp.ok) {
                const data = await resp.json();
                if (data.id) {
                    currentConversationId = data.id;
                    localStorage.setItem('zanika_ai_bubble_conv_id', data.id);
                }
                if (data.remaining_credits !== undefined) {
                    const credEl = document.getElementById('ai-bubble-credits-val');
                    if (credEl) credEl.textContent = data.remaining_credits;
                }
                if (data.messages && data.messages.length > 0) {
                    const welcome = document.getElementById('ai-bubble-welcome');
                    if (welcome) welcome.style.display = 'none';

                    const msgContainer = document.getElementById('ai-bubble-messages');
                    const existing = msgContainer.querySelectorAll('.ai-bubble-msg');
                    existing.forEach(el => el.remove());

                    data.messages.forEach(m => {
                        if (m.role === 'user' || m.role === 'assistant') {
                            if (m.content) {
                                appendAiBubbleMessage(m.role, m.content, m.action_required, m.id);
                            }
                        }
                    });
                    scrollAiBubbleToBottom();
                }
            }
        } catch (err) {
            console.warn('Erreur chargement conversation active copilote:', err);
        }
    }

    function syncWithVisualViewport() {
        if (window.innerWidth > 768) return;
        const win = document.getElementById('ai-chat-window');
        if (!win || win.style.display === 'none') return;

        if (window.visualViewport) {
            const vh = window.visualViewport.height;
            const vtop = window.visualViewport.offsetTop;
            win.style.height = `${vh}px`;
            win.style.top = `${vtop}px`;
            win.style.bottom = 'auto';
            scrollAiBubbleToBottom();
        }
    }

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncWithVisualViewport);
        window.visualViewport.addEventListener('scroll', syncWithVisualViewport);
    }

    // Bascule ouverture/fermeture du widget
    window.toggleAiChatBubble = function() {
        const win = document.getElementById('ai-chat-window');
        const backdrop = document.getElementById('ai-bubble-backdrop');
        const launcher = document.getElementById('ai-bubble-launcher');
        if (!win) return;

        const isClosed = win.style.display === 'none' || !win.style.display;
        if (isClosed) {
            loadActiveConversationIfNeeded();
            savedScrollY = window.scrollY || window.pageYOffset || 0;
            win.style.display = 'flex';
            if (backdrop) backdrop.classList.add('active');
            if (window.innerWidth <= 768) {
                document.body.style.top = `-${savedScrollY}px`;
                document.body.classList.add('ai-chat-open');
                if (launcher) launcher.style.display = 'none';
                syncWithVisualViewport();
            }
            setTimeout(() => {
                const input = document.getElementById('ai-bubble-input');
                if (input && window.innerWidth > 768) input.focus();
                scrollAiBubbleToBottom();
                syncWithVisualViewport();
            }, 80);
        } else {
            closeAiChatBubble();
        }
    };

    window.closeAiChatBubble = function() {
        const win = document.getElementById('ai-chat-window');
        const backdrop = document.getElementById('ai-bubble-backdrop');
        const launcher = document.getElementById('ai-bubble-launcher');
        if (win) {
            win.style.display = 'none';
            win.style.height = '';
            win.style.top = '';
            win.style.bottom = '';
        }
        if (backdrop) backdrop.classList.remove('active');
        if (launcher) launcher.style.display = 'flex';

        document.body.classList.remove('ai-chat-open');
        document.body.style.top = '';
        if (savedScrollY) {
            window.scrollTo(0, savedScrollY);
        }

        // Dissiper le focus pour fermer le clavier iOS et garantir le maintien du zoom 1:1
        if (document.activeElement && (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA')) {
            document.activeElement.blur();
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('ai-bubble-input');
        if (input) {
            input.addEventListener('focus', function() {
                setTimeout(syncWithVisualViewport, 50);
                setTimeout(syncWithVisualViewport, 250);
            });
            input.addEventListener('blur', function() {
                setTimeout(syncWithVisualViewport, 100);
            });
        }

        // Fermer le clavier virtuel si l'utilisateur touche ou fait défiler les messages
        const msgContainer = document.getElementById('ai-bubble-messages');
        if (msgContainer) {
            msgContainer.addEventListener('touchstart', function() {
                if (input && document.activeElement === input) {
                    input.blur();
                }
            }, { passive: true });
        }
    });

    // Fermeture propre avec la touche Échap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const win = document.getElementById('ai-chat-window');
            if (win && win.style.display === 'flex') {
                closeAiChatBubble();
            }
        }
    });

    window.resetAiBubbleChat = function() {
        currentConversationId = null;
        window.__forceNewConversation = true;
        localStorage.removeItem('zanika_ai_bubble_conv_id');
        hasLoadedHistory = true;

        const msgContainer = document.getElementById('ai-bubble-messages');
        if (!msgContainer) return;
        msgContainer.innerHTML = `
            <div class="ai-welcome-box" id="ai-bubble-welcome">
                <div class="ai-welcome-avatar">
                    <img src="{{ asset('images/brand/02_icone_application.png') }}" alt="Zanika IA" />
                </div>
                <h4>Nouvelle discussion initialisée</h4>
                <p>Que souhaitez-vous demander ou analyser aujourd'hui ?</p>
                <div class="ai-quick-prompts">
                    <button type="button" class="ai-prompt-btn" onclick="sendAiBubblePrompt('Fais-moi un point rapide sur mes stocks et les ruptures.')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg> <span>État des stocks & ruptures</span>
                    </button>
                    <button type="button" class="ai-prompt-btn" onclick="sendAiBubblePrompt('Combien de commandes COD sont en attente de confirmation ?')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg> <span>Commandes COD en attente</span>
                    </button>
                    <button type="button" class="ai-prompt-btn" onclick="sendAiBubblePrompt('Donne-moi le bilan des ventes et mes 2 meilleures actions du jour.')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/></svg> <span>Bilan des ventes & opportunités</span>
                    </button>
                </div>
            </div>
        `;
    };

    window.sendAiBubblePrompt = function(promptText) {
        const input = document.getElementById('ai-bubble-input');
        if (!input) return;
        input.value = promptText;
        handleAiBubbleSubmit(new Event('submit'));
    };

    window.handleAiBubbleSubmit = async function(e) {
        if (e && e.preventDefault) e.preventDefault();
        if (isSending) return;

        const input = document.getElementById('ai-bubble-input');
        const text = input ? input.value.trim() : '';
        if (!text) return;

        input.value = '';
        if (window.innerWidth <= 768) {
            input.blur(); // Ferme le clavier sur mobile pour libérer tout l'écran
        } else {
            input.focus();
        }

        // Masquer l'écran d'accueil s'il est encore présent
        const welcome = document.getElementById('ai-bubble-welcome');
        if (welcome) welcome.style.display = 'none';

        // 1. Ajouter le message utilisateur dans le flux
        appendAiBubbleMessage('user', text);
        scrollAiBubbleToBottom();

        // 2. Afficher l'indicateur de saisie
        const typing = document.getElementById('ai-bubble-typing');
        if (typing) typing.style.display = 'flex';
        scrollAiBubbleToBottom();

        isSending = true;

        try {
            const response = await fetch("{{ route('ai.message.send') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': "{{ csrf_token() }}",
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    message: text,
                    conversation_id: currentConversationId,
                    force_new: !!window.__forceNewConversation
                })
            });

            if (response.status === 401) {
                appendAiBubbleMessage('assistant', "Votre session a expiré suite à la mise à jour de la plateforme. Veuillez actualiser la page (F5) pour vous reconnecter.");
                return;
            }
            if (response.status === 419) {
                appendAiBubbleMessage('assistant', "Le jeton de sécurité a été renouvelé lors du redéploiement. Veuillez rafraîchir la page (F5).");
                return;
            }

            const data = await response.json();

            if (data.status === 'success') {
                currentConversationId = data.conversation_id;
                window.__forceNewConversation = false;
                localStorage.setItem('zanika_ai_bubble_conv_id', currentConversationId);

                // Mettre à jour les crédits dans l'en-tête
                const remaining = data.remaining_credits ?? data.credits_remaining;
                if (remaining !== undefined) {
                    const credEl = document.getElementById('ai-bubble-credits-val');
                    if (credEl) credEl.textContent = remaining;
                }

                // Afficher la réponse de l'assistant
                const assistantContent = data.message?.content || data.response || "Demande traitée avec succès.";
                const messageId = data.message?.id || null;
                const actionRequired = data.message?.action_required || data.action_required || null;

                appendAiBubbleMessage('assistant', assistantContent, actionRequired, messageId);
            } else {
                appendAiBubbleMessage('assistant', data.message || "Désolé, une erreur est survenue lors de l'échange.");
            }
        } catch (err) {
            console.error('Erreur bubble IA:', err);
            appendAiBubbleMessage('assistant', "Impossible de contacter l'assistant IA pour le moment. Veuillez vérifier votre connexion.");
        } finally {
            if (typing) typing.style.display = 'none';
            isSending = false;
            scrollAiBubbleToBottom();
        }
    };

    function appendAiBubbleMessage(role, content, actionRequired = null, messageId = null) {
        const msgContainer = document.getElementById('ai-bubble-messages');
        if (!msgContainer) return;

        const now = new Date();
        const timeStr = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');

        const row = document.createElement('div');
        row.className = `ai-bubble-msg ${role}`;

        let actionHtml = '';
        if (actionRequired && actionRequired.status === 'pending') {
            actionHtml = `
                <div class="ai-bubble-action-box" id="bubble-act-${actionRequired.message_id}">
                    <strong>Action requise : ${escapeHtml(actionRequired.title || 'Confirmation')}</strong>
                    <div>${escapeHtml(actionRequired.description || '')}</div>
                    <div class="ai-bubble-action-btns">
                        <button type="button" class="ai-action-btn-yes" onclick="approveAiBubbleAction(${actionRequired.message_id}, true)">Approuver</button>
                        <button type="button" class="ai-action-btn-no" onclick="approveAiBubbleAction(${actionRequired.message_id}, false)">Refuser</button>
                    </div>
                </div>
            `;
        }

        let feedbackHtml = '';
        if (role === 'assistant' && messageId) {
            feedbackHtml = `
                <span class="ai-bubble-feedback" id="b-fb-${messageId}" style="margin-right:auto;display:inline-flex;gap:4px">
                    <button type="button" class="ai-fb-btn" onclick="sendAiBubbleFeedback(${messageId}, 'helpful')" title="Utile" style="background:none;border:none;cursor:pointer;color:var(--text-soft);padding:0 2px;display:inline-flex;align-items:center"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h3Z"/></svg></button>
                    <button type="button" class="ai-fb-btn" onclick="sendAiBubbleFeedback(${messageId}, 'incorrect')" title="À améliorer" style="background:none;border:none;cursor:pointer;color:var(--text-soft);padding:0 2px;display:inline-flex;align-items:center"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-3Z"/></svg></button>
                </span>
            `;
        }

        row.innerHTML = `
            <div class="ai-msg-bubble">
                <div class="ai-bubble-text">${formatSimpleMarkdown(content)}</div>
                ${actionHtml}
            </div>
            <div class="ai-msg-time" style="display:flex;align-items:center;justify-content:flex-end;gap:6px">
                ${feedbackHtml}
                <span>${timeStr}</span>
            </div>
        `;

        msgContainer.appendChild(row);

        if (role === 'assistant') {
            setTimeout(() => {
                row.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 60);
        } else {
            scrollAiBubbleToBottom();
        }
    }

    window.sendAiBubbleFeedback = async function(messageId, rating) {
        const el = document.getElementById(`b-fb-${messageId}`);
        if (el) el.innerHTML = `<span style="font-size:0.7rem;color:var(--text-soft)">...</span>`;

        try {
            const resp = await fetch("{{ route('ai.feedback.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ message_id: messageId, rating: rating })
            });
            const d = await resp.json();
            if (el) {
                el.innerHTML = rating === 'helpful' 
                    ? `<span style="font-size:0.7rem;color:#10b981;font-weight:600">✓ Merci !</span>`
                    : `<span style="font-size:0.7rem;color:#f59e0b;font-weight:600">✓ Noté</span>`;
            }
        } catch (e) {
            if (el) el.innerHTML = '';
        }
    };

    window.approveAiBubbleAction = async function(messageId, approved) {
        const box = document.getElementById(`bubble-act-${messageId}`);
        if (box) {
            box.innerHTML = `<span style="color:#fbbf24">Traitement de votre décision...</span>`;
        }

        try {
            const resp = await fetch("{{ route('ai.action.approve') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': "{{ csrf_token() }}",
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ message_id: messageId, approved: approved })
            });
            const res = await resp.json();
            if (box) {
                box.innerHTML = `<span style="color:${approved ? '#10b981' : '#ef4444'};font-weight:700">${approved ? '✓ Action approuvée & exécutée' : '✕ Action refusée'}</span>`;
            }
            if (res.response) {
                appendAiBubbleMessage('assistant', res.response);
                scrollAiBubbleToBottom();
            }
        } catch (e) {
            if (box) box.innerHTML = `<span style="color:#ef4444">Erreur lors de la validation.</span>`;
        }
    };

    function scrollAiBubbleToBottom() {
        const body = document.getElementById('ai-bubble-messages');
        if (body) {
            body.scrollTop = body.scrollHeight;
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function formatSimpleMarkdown(text) {
        if (!text) return '';
        let safe = escapeHtml(text);
        // Gras **text**
        safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Puces - text
        safe = safe.replace(/(?:^|\n)-\s+(.*?)(?=\n|$)/g, '<br>• $1');
        // Retours à la ligne
        safe = safe.replace(/\n/g, '<br>');
        return safe;
    }
})();
</script>
@endif
@endauth
