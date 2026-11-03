@extends('layouts.app')

@section('title', 'ECOM AI ASSISTANT — Copilote Commercial Intelligent | ' . config('platform.name'))

@section('content')
<div class="ai-assistant-wrapper">
    <div class="ai-container">
        
        <!-- Backdrop mobile pour fermer le volet d'un simple toucher -->
        <div class="ai-sidebar-backdrop" id="ai-sidebar-backdrop" onclick="toggleAiSidebar()"></div>

        <!-- SIDEBAR : Historique des conversations & Profil IA -->
        <aside class="ai-sidebar" id="ai-sidebar">
            <div class="ai-sidebar-header">
                <div class="ai-brand-badge">
                    <div class="ai-glow-avatar">
                        <x-icon name="sparkles" :size="20" />
                    </div>
                    <div>
                        <div class="ai-assistant-title">ECOM AI ASSISTANT</div>
                        <div class="ai-status-indicator">
                            <span class="ai-status-dot"></span>
                            <span>Connecté à la boutique</span>
                        </div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:.4rem">
                    <button type="button" class="ai-new-chat-btn" id="new-chat-btn" onclick="startNewConversation()">
                        <x-icon name="plus" :size="16" />
                        <span>Nouveau</span>
                    </button>
                    <button type="button" class="ai-sidebar-close-mobile" onclick="toggleAiSidebar()" aria-label="Fermer le volet">✕</button>
                </div>
            </div>

            <!-- Jauge des crédits IA de la boutique -->
            <div class="ai-credits-widget">
                <div class="ai-credits-top">
                    <span class="ai-credits-label">Crédits IA Mensuels</span>
                    <span class="ai-credits-value"><strong>{{ $aiCreditsRemaining }}</strong> / {{ $aiCreditsMonthly }}</span>
                </div>
                @php
                    $percentRemaining = $aiCreditsMonthly > 0 ? min(100, max(0, round(($aiCreditsRemaining / $aiCreditsMonthly) * 100))) : 0;
                @endphp
                <div class="ai-credits-bar">
                    <div class="ai-credits-progress" style="width: {{ $percentRemaining }}%"></div>
                </div>
                <div class="ai-credits-footer">
                    <span>{{ $percentRemaining }}% disponible</span>
                    <a href="{{ route('subscription.checkout', 'pro') }}" class="ai-credits-upgrade">Recharger →</a>
                </div>
            </div>

            <!-- Raccourcis Modules IA Spécialisés -->
            <div style="padding:.6rem 1rem;display:flex;flex-direction:column;gap:.4rem;border-bottom:1px solid var(--border)">
                <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-soft);margin-bottom:.2rem">Modules Métier IA</div>
                <a href="{{ route('ai.studio') }}" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:.45rem;padding:.4rem .6rem;font-size:.8rem;justify-content:flex-start">
                    <x-icon name="image" size="14" /> Studio Visuel IA
                </a>
                <a href="{{ route('ai.marketing') }}" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:.45rem;padding:.4rem .6rem;font-size:.8rem;justify-content:flex-start">
                    <x-icon name="edit-3" size="14" /> Studio Marketing & Pubs
                </a>
                <a href="{{ route('ai.automations') }}" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:.45rem;padding:.4rem .6rem;font-size:.8rem;justify-content:flex-start">
                    <x-icon name="zap" size="14" /> Automatisations
                </a>
                <a href="{{ route('analytics.advisor') }}" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:.45rem;padding:.4rem .6rem;font-size:.8rem;justify-content:flex-start">
                    <x-icon name="bar-chart-2" size="14" /> Conseiller d'Affaires
                </a>
                <a href="{{ route('ai.orders.cod_review') }}" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:.45rem;padding:.4rem .6rem;font-size:.8rem;justify-content:flex-start">
                    <x-icon name="truck" size="14" /> Revue COD Intelligente
                </a>
            </div>

            <!-- Liste des conversations -->
            <div class="ai-history-section">
                <div class="ai-history-header">Historique des échanges</div>
                <div class="ai-conversation-list" id="conversation-list">
                    @forelse($conversations as $conv)
                        <div class="ai-conversation-item {{ ($activeConversation && $activeConversation->id === $conv->id) ? 'active' : '' }}" 
                             id="conv-item-{{ $conv->id }}"
                             onclick="loadConversation({{ $conv->id }})">
                            <div class="ai-conv-icon">
                                <x-icon name="message-square" :size="15" />
                            </div>
                            <div class="ai-conv-info">
                                <div class="ai-conv-title">{{ $conv->title }}</div>
                                <div class="ai-conv-date">{{ $conv->updated_at ? $conv->updated_at->diffForHumans() : '' }}</div>
                            </div>
                            <button type="button" class="ai-conv-delete-btn" title="Supprimer" onclick="deleteConversation(event, {{ $conv->id }})">
                                <x-icon name="trash" :size="13" />
                            </button>
                        </div>
                    @empty
                        <div class="ai-empty-history" id="empty-history-text">
                            Aucun échange précédent. Posez votre première question !
                        </div>
                    @endforelse
                </div>
            </div>
        </aside>

        <!-- ZONE PRINCIPALE DE CHAT -->
        <main class="ai-chat-main">
            <!-- Entête du chat -->
            <header class="ai-chat-header">
                <div style="display:flex;align-items:center;gap:0.75rem">
                    <button type="button" class="ai-sidebar-toggle-btn" onclick="toggleAiSidebar()" aria-label="Afficher le menu">
                        <x-icon name="grid" :size="18" />
                    </button>
                    <div>
                        <h1 class="ai-chat-title" id="current-chat-title">
                            {{ $activeConversation ? $activeConversation->title : 'Nouvel échange avec votre copilote' }}
                        </h1>
                        <p class="ai-chat-subtitle">
                            Boutique : <strong>{{ $shop->name ?: 'Votre boutique' }}</strong> • Analyse temps réel & actions métier
                        </p>
                    </div>
                </div>

                <div class="ai-header-badges">
                    <span class="ai-model-pill" title="Moteur de raisonnement multimodal">
                        <span class="ai-model-dot"></span> Gemini 2.5 Flash / Claude
                    </span>
                    <span class="ai-tools-pill" title="14 outils backend connectés">
                        <x-icon name="shield" :size="12" /> 14 Outils Métier Actifs
                    </span>
                </div>
            </header>

            <!-- Flux des messages -->
            <div class="ai-messages-scroll" id="ai-messages-container">
                @if(!$activeConversation || $activeConversation->messages->isEmpty())
                    <!-- Écran d'accueil de l'agent si aucun message -->
                    <div class="ai-welcome-card" id="ai-welcome-card">
                        <div class="ai-welcome-icon">
                            <x-icon name="sparkles" :size="36" />
                        </div>
                        <h2>Bonjour, je suis votre Copilote Commercial IA</h2>
                        <p>Je suis connecté en direct à votre catalogue, vos stocks, vos commandes et vos données de livraison. Comment puis-je vous aider aujourd'hui ?</p>
                        
                        <div class="ai-suggestions-grid">
                            <button type="button" class="ai-suggestion-chip" onclick="applySuggestion('Fais-moi un état des lieux complet de ma boutique et de mes stocks.')">
                                <span class="chip-icon">📦</span>
                                <div>
                                    <strong>État des stocks</strong>
                                    <span>Ruptures et réapprovisionnements</span>
                                </div>
                            </button>

                            <button type="button" class="ai-suggestion-chip" onclick="applySuggestion('Donne-moi le résumé de mes commandes et du chiffre d\'affaires de cette semaine.')">
                                <span class="chip-icon">📊</span>
                                <div>
                                    <strong>Commandes & Chiffre d'Affaires</strong>
                                    <span>Synthèse des ventes de la semaine</span>
                                </div>
                            </button>

                            <button type="button" class="ai-suggestion-chip" onclick="applySuggestion('Quelles sont les commandes en attente de confirmation Cash on Delivery (COD) ?')">
                                <span class="chip-icon">🛵</span>
                                <div>
                                    <strong>Commandes COD en attente</strong>
                                    <span>Validation et relances clients</span>
                                </div>
                            </button>

                            <button type="button" class="ai-suggestion-chip" onclick="applySuggestion('Analyse mes ventes des 30 derniers jours et propose-moi 3 recommandations d\'optimisation.')">
                                <span class="chip-icon">📈</span>
                                <div>
                                    <strong>Conseiller Commercial</strong>
                                    <span>Diagnostic & stratégie de vente</span>
                                </div>
                            </button>
                        </div>
                    </div>
                @else
                    @foreach($activeConversation->messages as $msg)
                        @if($msg->role === 'user')
                            <div class="ai-message-row user-row">
                                <div class="ai-bubble user-bubble">
                                    <div class="ai-bubble-content">{{ $msg->content }}</div>
                                    <div class="ai-bubble-time">{{ $msg->created_at ? $msg->created_at->format('H:i') : '' }}</div>
                                </div>
                            </div>
                        @elseif($msg->role === 'assistant')
                            <div class="ai-message-row assistant-row">
                                <div class="ai-avatar-mini">
                                    <x-icon name="sparkles" :size="16" />
                                </div>
                                <div class="ai-bubble assistant-bubble">
                                    <div class="ai-bubble-content markdown-rendered">
                                        {!! Str::markdown($msg->content ?? '') !!}
                                    </div>

                                    @if($msg->hasActionRequired())
                                        @php $act = $msg->action_required; @endphp
                                        <div class="ai-action-card" id="action-card-{{ $msg->id }}">
                                            <div class="ai-action-header">
                                                <x-icon name="alert-circle" :size="18" />
                                                <strong>Confirmation requise pour modification</strong>
                                            </div>
                                            <p class="ai-action-desc">{{ $act['message'] ?? 'Cette action modifiera les données de votre boutique.' }}</p>
                                            
                                            <div class="ai-action-changes">
                                                @if(isset($act['changes']['new_price']))
                                                    <div class="ai-change-item">
                                                        <span>Prix actuel :</span>
                                                        <del>{{ number_format($act['changes']['current_price'], 0, ',', ' ') }} {{ $shop->currency }}</del>
                                                        <span>→</span>
                                                        <strong>{{ number_format($act['changes']['new_price'], 0, ',', ' ') }} {{ $shop->currency }}</strong>
                                                    </div>
                                                @endif
                                                @if(isset($act['changes']['new_stock']))
                                                    <div class="ai-change-item">
                                                        <span>Stock actuel :</span>
                                                        <span>{{ $act['changes']['current_stock'] }}</span>
                                                        <span>→</span>
                                                        <strong>{{ $act['changes']['new_stock'] }} unités</strong>
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="ai-action-buttons">
                                                <button type="button" class="ai-action-btn confirm" onclick="submitActionDecision({{ $msg->id }}, true)">
                                                    <x-icon name="check" :size="15" />
                                                    <span>Valider la modification</span>
                                                </button>
                                                <button type="button" class="ai-action-btn cancel" onclick="submitActionDecision({{ $msg->id }}, false)">
                                                    <x-icon name="x" :size="15" />
                                                    <span>Annuler</span>
                                                </button>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="ai-bubble-time">{{ $msg->created_at->format('H:i') }}</div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif

                <!-- Indicateur de réflexion (Thinking Dots) -->
                <div class="ai-message-row assistant-row" id="ai-typing-indicator" style="display:none">
                    <div class="ai-avatar-mini">
                        <x-icon name="sparkles" :size="16" />
                    </div>
                    <div class="ai-bubble assistant-bubble ai-typing-bubble">
                        <span class="ai-typing-text" id="ai-typing-status">L'IA analyse vos données...</span>
                        <div class="ai-dots">
                            <span class="dot"></span>
                            <span class="dot"></span>
                            <span class="dot"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulaire d'envoi -->
            <footer class="ai-chat-input-wrapper">
                <form id="ai-chat-form" onsubmit="handleChatSubmit(event)">
                    <input type="hidden" id="current-conversation-id" value="{{ $activeConversation ? $activeConversation->id : '' }}">
                    
                    <div class="ai-input-box">
                        <textarea 
                            id="ai-user-input" 
                            name="message" 
                            rows="1" 
                            placeholder="Posez une question sur vos ventes, stocks, commandes COD ou demandez un conseil commercial..." 
                            required
                            oninput="autoResizeTextarea(this)"
                            onkeydown="handleTextareaKeydown(event)"></textarea>

                        <div class="ai-input-actions">
                            <button type="submit" class="ai-send-btn" id="ai-send-btn" title="Envoyer le message">
                                <x-icon name="send" :size="18" />
                            </button>
                        </div>
                    </div>
                </form>
                <div class="ai-input-hint">
                    <span>💡 <strong>Entrée</strong> pour envoyer, <strong>Maj+Entrée</strong> pour un saut de ligne. Connecté à la base de données sécurisée.</span>
                </div>
            </footer>
        </main>

    </div>
</div>

<style>
/* ==========================================================================
   ECOM AI ASSISTANT — MODERN PREMIUM STYLING
   ========================================================================== */
.ai-assistant-wrapper {
    background: var(--bg, #09090b);
    min-height: calc(100vh - 70px);
    color: var(--text, #f4f4f5);
    display: flex;
    flex-direction: column;
}

.ai-container {
    display: flex;
    flex: 1;
    max-width: 1600px;
    width: 100%;
    margin: 0 auto;
    height: calc(100vh - 70px);
    position: relative;
    overflow: hidden;
}

/* SIDEBAR */
.ai-sidebar {
    width: 320px;
    background: var(--surface, #121217);
    border-right: 1px solid var(--border, rgba(255,255,255,0.08));
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 100;
}

.ai-sidebar-header {
    padding: 1.25rem 1rem 1rem;
    border-bottom: 1px solid var(--border, rgba(255,255,255,0.06));
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.ai-brand-badge {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.ai-glow-avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, #e11d48, #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    box-shadow: 0 4px 16px rgba(225, 29, 72, 0.35);
}

.ai-assistant-title {
    font-size: 0.95rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: #ffffff;
}

.ai-status-indicator {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    color: #10b981;
    font-weight: 600;
}

.ai-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 8px #10b981;
    animation: ai-pulse 2s infinite;
}

@keyframes ai-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(1.1); }
}

.ai-new-chat-btn {
    width: 100%;
    background: linear-gradient(135deg, rgba(225, 29, 72, 0.15), rgba(124, 58, 237, 0.15));
    border: 1px solid rgba(225, 29, 72, 0.3);
    color: #ffffff;
    padding: 0.65rem 0.9rem;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.ai-new-chat-btn:hover {
    background: linear-gradient(135deg, rgba(225, 29, 72, 0.25), rgba(124, 58, 237, 0.25));
    border-color: rgba(225, 29, 72, 0.6);
    transform: translateY(-1px);
}

/* CRÉDITS WIDGET */
.ai-credits-widget {
    margin: 1rem 1rem 0;
    padding: 0.85rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--border, rgba(255, 255, 255, 0.06));
    border-radius: 10px;
}

.ai-credits-top {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    margin-bottom: 0.4rem;
}

.ai-credits-label {
    color: var(--text-muted, #a1a1aa);
}

.ai-credits-bar {
    height: 6px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 99px;
    overflow: hidden;
    margin-bottom: 0.4rem;
}

.ai-credits-progress {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #3b82f6);
    border-radius: 99px;
    transition: width 0.3s ease;
}

.ai-credits-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.7rem;
    color: var(--text-muted, #a1a1aa);
}

.ai-credits-upgrade {
    color: #e11d48;
    text-decoration: none;
    font-weight: 700;
}

.ai-credits-upgrade:hover {
    text-decoration: underline;
}

/* HISTORIQUE */
.ai-history-section {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.ai-history-header {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted, #71717a);
    font-weight: 700;
    margin-bottom: 0.4rem;
    padding-left: 0.25rem;
}

.ai-conversation-list {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.ai-conversation-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.65rem 0.75rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
    position: relative;
    color: var(--text-muted, #d4d4d8);
}

.ai-conversation-item:hover {
    background: rgba(255, 255, 255, 0.05);
    color: #ffffff;
}

.ai-conversation-item.active {
    background: rgba(225, 29, 72, 0.12);
    border-left: 3px solid #e11d48;
    color: #ffffff;
}

.ai-conv-icon {
    opacity: 0.6;
}

.ai-conv-info {
    flex: 1;
    min-width: 0;
}

.ai-conv-title {
    font-size: 0.82rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ai-conv-date {
    font-size: 0.68rem;
    color: var(--text-muted, #71717a);
}

.ai-conv-delete-btn {
    opacity: 0;
    background: transparent;
    border: none;
    color: #ef4444;
    padding: 4px;
    border-radius: 4px;
    cursor: pointer;
    transition: opacity 0.15s ease;
}

.ai-conversation-item:hover .ai-conv-delete-btn {
    opacity: 1;
}

.ai-empty-history {
    font-size: 0.8rem;
    color: var(--text-muted, #71717a);
    text-align: center;
    padding: 2rem 1rem;
    line-height: 1.4;
}

/* CHAT MAIN */
.ai-chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    height: 100%;
    min-width: 0;
    background: radial-gradient(circle at 50% 0%, rgba(225, 29, 72, 0.04) 0%, transparent 60%);
}

.ai-chat-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border, rgba(255,255,255,0.06));
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    background: rgba(18, 18, 23, 0.6);
    backdrop-filter: blur(12px);
}

.ai-sidebar-toggle-btn {
    display: none;
    background: transparent;
    border: 1px solid var(--border, rgba(255,255,255,0.1));
    color: #ffffff;
    padding: 6px;
    border-radius: 6px;
    cursor: pointer;
}

.ai-chat-title {
    font-size: 1.15rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    margin: 0;
    color: #ffffff;
}

.ai-chat-subtitle {
    font-size: 0.78rem;
    color: var(--text-muted, #a1a1aa);
    margin: 0.2rem 0 0;
}

.ai-header-badges {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.ai-model-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 4px 10px;
    border-radius: 99px;
    color: #e4e4e7;
}

.ai-model-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #8b5cf6;
}

.ai-tools-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 600;
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.25);
    color: #10b981;
    padding: 4px 10px;
    border-radius: 99px;
}

/* MESSAGES SCROLL */
.ai-messages-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem 2rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.ai-welcome-card {
    margin: auto;
    max-width: 680px;
    text-align: center;
    padding: 2.5rem 1.5rem;
}

.ai-welcome-icon {
    width: 64px;
    height: 64px;
    border-radius: 20px;
    background: linear-gradient(135deg, #e11d48, #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
    color: #ffffff;
    box-shadow: 0 8px 24px rgba(225, 29, 72, 0.4);
}

.ai-welcome-card h2 {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0 0 0.5rem;
    color: #ffffff;
}

.ai-welcome-card p {
    font-size: 0.92rem;
    color: var(--text-muted, #a1a1aa);
    line-height: 1.5;
    margin: 0 0 2rem;
}

.ai-suggestions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 0.85rem;
    text-align: left;
}

.ai-suggestion-chip {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
    padding: 1rem;
    border-radius: 12px;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
    color: inherit;
    font-family: inherit;
}

.ai-suggestion-chip:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(225, 29, 72, 0.4);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.25);
}

.ai-suggestion-chip .chip-icon {
    font-size: 1.3rem;
    line-height: 1;
}

.ai-suggestion-chip strong {
    display: block;
    font-size: 0.88rem;
    color: #ffffff;
    margin-bottom: 0.15rem;
}

.ai-suggestion-chip span {
    display: block;
    font-size: 0.75rem;
    color: var(--text-muted, #a1a1aa);
}

/* BUBBLES */
.ai-message-row {
    display: flex;
    gap: 0.75rem;
    max-width: 880px;
    width: 100%;
}

.user-row {
    margin-left: auto;
    justify-content: flex-end;
}

.assistant-row {
    margin-right: auto;
    justify-content: flex-start;
}

.ai-avatar-mini {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: linear-gradient(135deg, #e11d48, #7c3aed);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 4px;
}

.ai-bubble {
    padding: 1rem 1.25rem;
    border-radius: 14px;
    position: relative;
    line-height: 1.6;
    font-size: 0.92rem;
}

.user-bubble {
    background: linear-gradient(135deg, #9e1b32, #be123c);
    color: #ffffff;
    border-bottom-right-radius: 4px;
    box-shadow: 0 4px 14px rgba(158, 27, 50, 0.25);
}

.assistant-bubble {
    background: var(--surface, #18181f);
    border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
    color: var(--text, #f4f4f5);
    border-bottom-left-radius: 4px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.ai-bubble-time {
    font-size: 0.65rem;
    opacity: 0.55;
    margin-top: 0.4rem;
    text-align: right;
}

/* MARKDOWN STYLING */
.markdown-rendered h1, 
.markdown-rendered h2, 
.markdown-rendered h3 {
    margin: 1rem 0 0.4rem;
    color: #ffffff;
    font-weight: 700;
}

.markdown-rendered h1:first-child, 
.markdown-rendered h2:first-child, 
.markdown-rendered h3:first-child {
    margin-top: 0;
}

.markdown-rendered h3 {
    font-size: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding-bottom: 0.3rem;
}

.markdown-rendered p {
    margin: 0.4rem 0 0.8rem;
}

.markdown-rendered p:last-child {
    margin-bottom: 0;
}

.markdown-rendered ul, 
.markdown-rendered ol {
    margin: 0.4rem 0 0.8rem;
    padding-left: 1.25rem;
}

.markdown-rendered li {
    margin-bottom: 0.3rem;
}

.markdown-rendered strong {
    color: #ffffff;
    font-weight: 700;
}

.markdown-rendered table {
    width: 100%;
    border-collapse: collapse;
    margin: 0.75rem 0;
    font-size: 0.82rem;
}

.markdown-rendered th, 
.markdown-rendered td {
    padding: 0.5rem 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    text-align: left;
}

.markdown-rendered th {
    background: rgba(255, 255, 255, 0.05);
    font-weight: 700;
    color: #ffffff;
}

/* SENSITIVE ACTION CARD */
.ai-action-card {
    margin-top: 0.85rem;
    padding: 1rem;
    background: rgba(225, 29, 72, 0.08);
    border: 1px solid rgba(225, 29, 72, 0.35);
    border-radius: 10px;
}

.ai-action-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #f43f5e;
    margin-bottom: 0.4rem;
    font-size: 0.9rem;
}

.ai-action-desc {
    font-size: 0.84rem;
    margin: 0 0 0.75rem;
}

.ai-action-changes {
    background: rgba(0, 0, 0, 0.3);
    padding: 0.6rem 0.75rem;
    border-radius: 6px;
    margin-bottom: 0.85rem;
    font-size: 0.82rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.ai-change-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ai-action-buttons {
    display: flex;
    gap: 0.6rem;
}

.ai-action-btn {
    padding: 0.5rem 0.9rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    transition: all 0.15s ease;
}

.ai-action-btn.confirm {
    background: #10b981;
    color: #ffffff;
}

.ai-action-btn.confirm:hover {
    background: #059669;
}

.ai-action-btn.cancel {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

.ai-action-btn.cancel:hover {
    background: rgba(255, 255, 255, 0.2);
}

/* TYPING DOTS */
.ai-typing-bubble {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.15rem;
}

.ai-typing-text {
    font-size: 0.82rem;
    color: var(--text-muted, #a1a1aa);
}

.ai-dots {
    display: inline-flex;
    gap: 4px;
}

.ai-dots .dot {
    width: 6px;
    height: 6px;
    background: #e11d48;
    border-radius: 50%;
    animation: ai-bounce 1.4s infinite ease-in-out both;
}

.ai-dots .dot:nth-child(1) { animation-delay: -0.32s; }
.ai-dots .dot:nth-child(2) { animation-delay: -0.16s; }

@keyframes ai-bounce {
    0%, 80%, 100% { transform: scale(0); opacity: 0.4; }
    40% { transform: scale(1); opacity: 1; }
}

/* INPUT FOOTER */
.ai-chat-input-wrapper {
    padding: 1rem 1.5rem 1.25rem;
    border-top: 1px solid var(--border, rgba(255, 255, 255, 0.06));
    background: rgba(18, 18, 23, 0.7);
    backdrop-filter: blur(12px);
}

.ai-input-box {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid var(--border, rgba(255, 255, 255, 0.12));
    border-radius: 14px;
    padding: 0.6rem 0.85rem;
    display: flex;
    align-items: flex-end;
    gap: 0.75rem;
    transition: all 0.2s ease;
}

.ai-input-box:focus-within {
    border-color: #e11d48;
    background: rgba(255, 255, 255, 0.06);
    box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.2);
}

#ai-user-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    resize: none;
    color: #ffffff;
    font-size: 0.92rem;
    font-family: inherit;
    line-height: 1.5;
    max-height: 160px;
    padding: 0.3rem 0;
}

#ai-user-input::placeholder {
    color: var(--text-muted, #71717a);
}

.ai-input-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ai-send-btn {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #e11d48, #be123c);
    border: none;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.ai-send-btn:hover {
    background: linear-gradient(135deg, #f43f5e, #e11d48);
    transform: scale(1.05);
}

.ai-send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.ai-input-hint {
    font-size: 0.7rem;
    color: var(--text-muted, #71717a);
    margin-top: 0.4rem;
    text-align: center;
}

/* RESPONSIVE MOBILE ULTRA FLUIDE */
.ai-sidebar-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(4px);
    z-index: 998;
    opacity: 0;
    transition: opacity 0.25s ease;
}

.ai-sidebar-backdrop.open {
    display: block;
    opacity: 1;
}

.ai-sidebar-close-mobile {
    display: none;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: #fff;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.95rem;
    align-items: center;
    justify-content: center;
}

@media (max-width: 991px) {
    .ai-sidebar {
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        width: 82%;
        max-width: 330px;
        transform: translateX(-100%);
        box-shadow: 0 10px 40px rgba(0,0,0,0.7);
        z-index: 999;
    }

    .ai-sidebar.open {
        transform: translateX(0);
    }

    .ai-sidebar-toggle-btn {
        display: inline-flex;
    }

    .ai-sidebar-close-mobile {
        display: inline-flex;
    }

    .ai-messages-scroll {
        padding: 1rem 0.85rem;
    }

    .ai-bubble {
        max-width: 94%;
        font-size: 0.88rem;
    }

    .ai-suggestions-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .ai-assistant-wrapper {
        min-height: calc(100dvh - 58px);
    }

    .ai-container {
        height: calc(100dvh - 58px - 76px) !important;
    }

    .ai-chat-header {
        padding: 0.65rem 0.85rem;
    }

    .ai-chat-title {
        font-size: 0.98rem;
    }

    .ai-chat-subtitle {
        font-size: 0.72rem;
    }

    .ai-chat-input-wrapper {
        padding: 0.6rem 0.75rem;
    }

    .ai-input-hint {
        display: none;
    }
}

/* ==========================================================================
   SUPPORT DU MODE CLAIR (data-theme="light")
   Résolution des contrastes, boutons, bulles et saisie en mode clair
   ========================================================================== */
[data-theme='light'] .ai-assistant-wrapper {
    background: #f1f5f9;
    color: #0f172a;
}

[data-theme='light'] .ai-container {
    background: #ffffff;
    border-color: #e2e8f0;
}

[data-theme='light'] .ai-sidebar {
    background: #f8fafc;
    border-right-color: #e2e8f0;
}

[data-theme='light'] .ai-sidebar-header {
    border-bottom-color: #e2e8f0;
}

[data-theme='light'] .ai-assistant-title {
    color: #0f172a;
}

[data-theme='light'] .ai-sidebar-close-mobile {
    background: #e2e8f0;
    color: #0f172a;
}

/* Bouton + Nouveau en mode clair : texte bordeaux lisible */
[data-theme='light'] .ai-new-chat-btn {
    background: linear-gradient(135deg, rgba(225, 29, 72, 0.1), rgba(124, 58, 237, 0.1));
    border-color: rgba(225, 29, 72, 0.35);
    color: #be123c !important;
    font-weight: 800;
}

[data-theme='light'] .ai-new-chat-btn:hover {
    background: linear-gradient(135deg, rgba(225, 29, 72, 0.2), rgba(124, 58, 237, 0.2));
    border-color: rgba(225, 29, 72, 0.6);
    color: #9f1239 !important;
}

[data-theme='light'] .ai-credits-widget {
    background: #ffffff;
    border-color: #e2e8f0;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

[data-theme='light'] .ai-credits-label {
    color: #64748b;
}

[data-theme='light'] .ai-credits-value {
    color: #0f172a;
}

[data-theme='light'] .ai-credits-value strong {
    color: #0f172a;
}

[data-theme='light'] .ai-credits-bar {
    background: #e2e8f0;
}

[data-theme='light'] .ai-credits-footer {
    color: #64748b;
}

[data-theme='light'] .ai-history-header {
    color: #64748b;
}

[data-theme='light'] .ai-conversation-item {
    color: #334155;
}

[data-theme='light'] .ai-conversation-item:hover {
    background: #edf2f7;
    color: #0f172a;
}

[data-theme='light'] .ai-conversation-item.active {
    background: rgba(225, 29, 72, 0.09);
    border-left-color: #e11d48;
    color: #be123c;
}

[data-theme='light'] .ai-conv-date {
    color: #94a3b8;
}

[data-theme='light'] .ai-empty-history {
    color: #64748b;
}

/* Zone principale du chat en mode clair */
[data-theme='light'] .ai-chat-main {
    background: #ffffff;
}

[data-theme='light'] .ai-chat-header {
    background: rgba(255, 255, 255, 0.95);
    border-bottom-color: #e2e8f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

[data-theme='light'] .ai-chat-title {
    color: #0f172a;
}

[data-theme='light'] .ai-chat-subtitle {
    color: #64748b;
}

[data-theme='light'] .ai-sidebar-toggle-btn {
    border-color: #cbd5e1;
    color: #0f172a;
}

[data-theme='light'] .ai-model-pill {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #334155;
}

/* Cartes d'accueil et suggestions en mode clair */
[data-theme='light'] .ai-welcome-card h2 {
    color: #0f172a;
}

[data-theme='light'] .ai-welcome-card p {
    color: #64748b;
}

[data-theme='light'] .ai-suggestion-chip {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #0f172a;
}

[data-theme='light'] .ai-suggestion-chip:hover {
    background: #ffffff;
    border-color: rgba(225, 29, 72, 0.4);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

[data-theme='light'] .ai-suggestion-chip strong {
    color: #0f172a;
}

[data-theme='light'] .ai-suggestion-chip span {
    color: #64748b;
}

/* Bulles de messages de l'assistant en mode clair */
[data-theme='light'] .assistant-bubble {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #1e293b;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

[data-theme='light'] .markdown-rendered h1,
[data-theme='light'] .markdown-rendered h2,
[data-theme='light'] .markdown-rendered h3 {
    color: #0f172a;
    border-bottom-color: #e2e8f0;
}

[data-theme='light'] .markdown-rendered p {
    color: #1e293b;
}

[data-theme='light'] .markdown-rendered strong {
    color: #0f172a;
}

[data-theme='light'] .markdown-rendered table th {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #e2e8f0;
}

[data-theme='light'] .markdown-rendered table td {
    border-color: #e2e8f0;
    color: #1e293b;
}

[data-theme='light'] .markdown-rendered ul,
[data-theme='light'] .markdown-rendered ol {
    color: #1e293b;
}

[data-theme='light'] .ai-bubble-time {
    color: #94a3b8;
}

/* Cartes d'action en mode clair */
[data-theme='light'] .ai-action-card {
    background: rgba(225, 29, 72, 0.04);
    border-color: rgba(225, 29, 72, 0.25);
}

[data-theme='light'] .ai-action-card strong {
    color: #be123c;
}

[data-theme='light'] .ai-action-desc {
    color: #334155;
}

[data-theme='light'] .ai-action-btn.cancel {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}

[data-theme='light'] .ai-action-btn.cancel:hover {
    background: #e2e8f0;
    color: #0f172a;
}

/* Zone de saisie en bas en mode clair */
[data-theme='light'] .ai-chat-input-wrapper {
    background: #ffffff;
    border-top-color: #e2e8f0;
}

[data-theme='light'] .ai-input-box {
    background: #f8fafc;
    border-color: #cbd5e1;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}

[data-theme='light'] .ai-input-box:focus-within {
    background: #ffffff;
    border-color: #e11d48;
    box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.15);
}

[data-theme='light'] .ai-input-box textarea {
    color: #0f172a;
}

[data-theme='light'] .ai-input-box textarea::placeholder {
    color: #94a3b8;
}

[data-theme='light'] .ai-input-hint {
    color: #64748b;
}

[data-theme='light'] .ai-typing-text {
    color: #64748b;
}
</style>

<script>
// ==========================================================================
// ECOM AI ASSISTANT CLIENT ENGINE
// ==========================================================================
let isSending = false;

function autoResizeTextarea(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 160) + 'px';
}

function handleTextareaKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('ai-chat-form').dispatchEvent(new Event('submit', { cancelable: true }));
    }
}

function toggleAiSidebar() {
    const sidebar = document.getElementById('ai-sidebar');
    const backdrop = document.getElementById('ai-sidebar-backdrop');
    sidebar.classList.toggle('open');
    if (backdrop) {
        backdrop.classList.toggle('open');
    }
}

function applySuggestion(text) {
    const input = document.getElementById('ai-user-input');
    input.value = text;
    autoResizeTextarea(input);
    document.getElementById('ai-chat-form').dispatchEvent(new Event('submit', { cancelable: true }));
}

function startNewConversation() {
    document.getElementById('current-conversation-id').value = '';
    document.getElementById('current-chat-title').innerText = 'Nouvel échange avec votre copilote';
    
    // Réinitialiser la liste active
    document.querySelectorAll('.ai-conversation-item').forEach(el => el.classList.remove('active'));

    // Remplacer par l'écran d'accueil
    const container = document.getElementById('ai-messages-container');
    container.innerHTML = `
        <div class="ai-welcome-card" id="ai-welcome-card">
            <div class="ai-welcome-icon">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
            </div>
            <h2>Nouvelle session engagée</h2>
            <p>Posez n'importe quelle question sur vos stocks, vos commandes Cash on Delivery ou demandez un audit des ventes.</p>
        </div>
    `;
    document.getElementById('ai-user-input').focus();
}

async function loadConversation(conversationId) {
    try {
        const res = await fetch(`/assistant-ia/conversations/${conversationId}`, {
            headers: { 'Accept': 'application/json' }
        });
        if (!res.ok) throw new Error('Impossible de charger la conversation');

        const data = await res.json();
        document.getElementById('current-conversation-id').value = data.id;
        document.getElementById('current-chat-title').innerText = data.title;

        // Mise à jour de la classe active
        document.querySelectorAll('.ai-conversation-item').forEach(el => el.classList.remove('active'));
        const activeItem = document.getElementById(`conv-item-${data.id}`);
        if (activeItem) activeItem.classList.add('active');

        // Rendu des messages
        const container = document.getElementById('ai-messages-container');
        container.innerHTML = '';

        data.messages.forEach(msg => {
            appendMessageToUi(msg.role, msg.content, msg.created_at, msg.action_required, msg.id);
        });

        scrollToBottom();

        // Fermer la sidebar sur mobile si ouverte
        const sidebar = document.getElementById('ai-sidebar');
        if (sidebar.classList.contains('open')) sidebar.classList.remove('open');

    } catch (err) {
        console.error(err);
    }
}

async function deleteConversation(event, conversationId) {
    event.stopPropagation();
    if (!confirm('Voulez-vous vraiment supprimer cet échange ?')) return;

    try {
        const res = await fetch(`/assistant-ia/conversations/${conversationId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });

        if (res.ok) {
            const item = document.getElementById(`conv-item-${conversationId}`);
            if (item) item.remove();

            if (document.getElementById('current-conversation-id').value == conversationId) {
                startNewConversation();
            }
        }
    } catch (err) {
        console.error(err);
    }
}

async function handleChatSubmit(e) {
    e.preventDefault();
    if (isSending) return;

    const input = document.getElementById('ai-user-input');
    const message = input.value.trim();
    if (!message) return;

    const conversationId = document.getElementById('current-conversation-id').value;

    // Supprimer la carte de bienvenue si présente
    const welcomeCard = document.getElementById('ai-welcome-card');
    if (welcomeCard) welcomeCard.remove();

    // Afficher immédiatement le message utilisateur dans le flux
    appendMessageToUi('user', message, new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
    input.value = '';
    autoResizeTextarea(input);

    // Activer l'indicateur de typing
    isSending = true;
    document.getElementById('ai-send-btn').disabled = true;
    const typingIndicator = document.getElementById('ai-typing-indicator');
    const container = document.getElementById('ai-messages-container');
    container.appendChild(typingIndicator);
    typingIndicator.style.display = 'flex';
    scrollToBottom();

    try {
        const res = await fetch('{{ route('ai.message.send') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                message: message,
                conversation_id: conversationId ? parseInt(conversationId) : null
            })
        });

        const data = await res.json();
        typingIndicator.style.display = 'none';

        if (data.status === 'quota_exceeded') {
            appendMessageToUi('assistant', `⚠️ **Crédits Épuisés** : ${data.message}`);
        } else if (data.status === 'success') {
            document.getElementById('current-conversation-id').value = data.conversation_id;
            document.getElementById('current-chat-title').innerText = data.conversation_title;

            // Ajouter à l'historique de la sidebar si nouveau
            updateSidebarConversation(data.conversation_id, data.conversation_title);

            appendMessageToUi('assistant', data.message.content, data.message.created_at, data.message.action_required, data.message.id);

            // Mettre à jour le compteur de crédits
            if (data.remaining_credits !== undefined) {
                updateCreditsDisplay(data.remaining_credits);
            }
        } else {
            appendMessageToUi('assistant', "Une erreur est survenue lors de l'exécution de votre requête. Veuillez réessayer.");
        }

    } catch (err) {
        typingIndicator.style.display = 'none';
        appendMessageToUi('assistant', "Erreur de connexion avec le serveur IA.");
        console.error(err);
    } finally {
        isSending = false;
        document.getElementById('ai-send-btn').disabled = false;
        scrollToBottom();
        input.focus();
    }
}

async function submitActionDecision(messageId, approved) {
    const card = document.getElementById(`action-card-${messageId}`);
    if (card) {
        card.style.opacity = '0.5';
        card.style.pointerEvents = 'none';
    }

    try {
        const res = await fetch('{{ route('ai.action.approve') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                message_id: messageId,
                approved: approved
            })
        });

        const data = await res.json();
        if (data.status === 'success') {
            if (card) card.remove();
            appendMessageToUi('assistant', approved ? "✅ **Action confirmée et appliquée à la boutique !**" : "🚫 Action annulée.");
        }
    } catch (err) {
        console.error(err);
        if (card) {
            card.style.opacity = '1';
            card.style.pointerEvents = 'auto';
        }
    }
}

function appendMessageToUi(role, content, time = '', actionRequired = null, messageId = null) {
    const container = document.getElementById('ai-messages-container');
    const row = document.createElement('div');
    row.className = `ai-message-row ${role === 'user' ? 'user-row' : 'assistant-row'}`;

    if (role === 'user') {
        row.innerHTML = `
            <div class="ai-bubble user-bubble">
                <div class="ai-bubble-content">${escapeHtml(content)}</div>
                <div class="ai-bubble-time">${time}</div>
            </div>
        `;
    } else {
        let actionCardHtml = '';
        if (actionRequired && actionRequired.status === 'action_required') {
            const ch = actionRequired.changes || {};
            let priceChange = ch.new_price ? `<div class="ai-change-item"><span>Prix :</span> <del>${formatNumber(ch.current_price)}</del> → <strong>${formatNumber(ch.new_price)} FCFA</strong></div>` : '';
            let stockChange = ch.new_stock !== undefined ? `<div class="ai-change-item"><span>Stock :</span> ${ch.current_stock} → <strong>${ch.new_stock} unités</strong></div>` : '';

            actionCardHtml = `
                <div class="ai-action-card" id="action-card-${messageId}">
                    <div class="ai-action-header">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <strong>Confirmation requise pour modification</strong>
                    </div>
                    <p class="ai-action-desc">${escapeHtml(actionRequired.message || 'Confirmez-vous cette opération ?')}</p>
                    <div class="ai-action-changes">
                        ${priceChange}
                        ${stockChange}
                    </div>
                    <div class="ai-action-buttons">
                        <button type="button" class="ai-action-btn confirm" onclick="submitActionDecision(${messageId}, true)">
                            ✓ Valider la modification
                        </button>
                        <button type="button" class="ai-action-btn cancel" onclick="submitActionDecision(${messageId}, false)">
                            ✕ Annuler
                        </button>
                    </div>
                </div>
            `;
        }

        row.innerHTML = `
            <div class="ai-avatar-mini">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
            </div>
            <div class="ai-bubble assistant-bubble">
                <div class="ai-bubble-content markdown-rendered">${renderSimpleMarkdown(content)}</div>
                ${actionCardHtml}
                <div class="ai-bubble-time">${time}</div>
            </div>
        `;
    }

    container.appendChild(row);
    scrollToBottom();
}

function updateSidebarConversation(id, title) {
    const list = document.getElementById('conversation-list');
    const existing = document.getElementById(`conv-item-${id}`);
    if (existing) {
        existing.querySelector('.ai-conv-title').innerText = title;
        return;
    }

    const emptyText = document.getElementById('empty-history-text');
    if (emptyText) emptyText.remove();

    const item = document.createElement('div');
    item.className = 'ai-conversation-item active';
    item.id = `conv-item-${id}`;
    item.onclick = () => loadConversation(id);
    item.innerHTML = `
        <div class="ai-conv-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div class="ai-conv-info">
            <div class="ai-conv-title">${escapeHtml(title)}</div>
            <div class="ai-conv-date">À l'instant</div>
        </div>
        <button type="button" class="ai-conv-delete-btn" title="Supprimer" onclick="deleteConversation(event, ${id})">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        </button>
    `;
    list.prepend(item);
}

function updateCreditsDisplay(remaining) {
    const val = document.querySelector('.ai-credits-value strong');
    if (val) val.innerText = remaining;
}

function scrollToBottom() {
    const container = document.getElementById('ai-messages-container');
    container.scrollTop = container.scrollHeight;
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

function formatNumber(num) {
    return new Intl.NumberFormat('fr-FR').format(num || 0);
}

function renderSimpleMarkdown(text) {
    if (!text) return '';
    let html = escapeHtml(text);

    // Titres
    html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
    html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
    html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>');

    // Gras et italique
    html = html.replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/gim, '<em>$1</em>');

    // Listes à puces
    html = html.replace(/^\* (.*$)/gim, '<li>$1</li>');
    html = html.replace(/^- (.*$)/gim, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>)/gim, '<ul>$1</ul>');

    // Retours à la ligne
    html = html.replace(/\n\n/g, '<p></p>');
    html = html.replace(/\n/g, '<br>');

    return html;
}

document.addEventListener('DOMContentLoaded', () => {
    scrollToBottom();
    const input = document.getElementById('ai-user-input');
    if (input) input.focus();
});
</script>
@endsection
