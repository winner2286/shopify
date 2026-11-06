# STRATÉGIE DE TEST & RAPPORT DE VALIDATION — ZANIKA AI COPILOT

Ce document consigne la couverture de test, les scénarios de recette automatisés et les procédures d'exécution des tests pour le Copilote IA Zanika.

---

## 1. Vue d'Ensemble de la Couverture

L'ensemble des fonctionnalités du Copilote IA est couvert par **4 suites de tests fonctionnels et de sécurité dédiées**, totalisant **19 tests spécialisés et 116 assertions**, en complément des **75 tests unitaires et d'intégration de la plateforme existante** (426 assertions au total), tous exécutés avec un **taux de réussite de 100%**.

| Suite de Test | Fichier Source | Tests | Assertions | Statut |
| :--- | :--- | :---: | :---: | :---: |
| **Phase 1 : Cœur Agentique & Sécurité HMAC** | `tests/Feature/AiAgentPhase1Test.php` | 8 | 39 | **100% SUCCÈS** |
| **Phase 2 : Intelligence Métier, RAG & Mémoire** | `tests/Feature/AiAgentPhase2Test.php` | 6 | 42 | **100% SUCCÈS** |
| **Phase 3 : Vision Multimodale & Studio Marketing** | `tests/Feature/AiAgentPhase3Test.php` | 3 | 20 | **100% SUCCÈS** |
| **Phase 4 : Proactivité & Routine Matinale** | `tests/Feature/AiAgentPhase4Test.php` | 2 | 15 | **100% SUCCÈS** |
| **Total Dédié Copilote IA** | — | **19** | **116** | **100% SUCCÈS** |
| **Suite Globale Plateforme Zanika** | Ensemble du dossier `tests/` | **75** | **426** | **100% SUCCÈS** |

---

## 2. Détail des Scénarios Testés

### 2.1. Phase 1 : Cœur Agentique & Sécurité HMAC (`AiAgentPhase1Test.php`)
1. `test_action_token_service_generates_and_validates_hmac_token` :
   - Vérifie la structure en 3 parties du jeton HMAC (`base64(header).base64(payload).signature`).
   - Valide l'acceptation avec les paramètres conformes.
2. `test_action_token_service_rejects_expired_token` :
   - Simule un jeton généré il y a plus de 15 minutes (`subMinutes(16)`).
   - Vérifie le rejet systématique avec motif `Jeton expiré`.
3. `test_action_token_service_prevents_replay_attack` :
   - Valide une première fois le jeton (succès).
   - Re-tente une seconde validation avec le même jeton.
   - Vérifie le rejet immédiat anti-rejeu (`Jeton déjà utilisé`).
4. `test_action_token_service_rejects_tampered_payload` :
   - Altère les paramètres (tentative de modifier le prix lors de la confirmation).
   - Vérifie le rejet pour divergence de hash.
5. `test_action_token_service_enforces_shop_isolation` :
   - Tente d'utiliser un jeton émis pour la Boutique A sur la Boutique B.
   - Vérifie le blocage de sécurité inter-boutiques.
6. `test_create_product_tool_requires_confirmation_then_creates_product` :
   - Vérifie l'étape 1 : retour du statut `action_required` avec prévisualisation.
   - Vérifie l'étape 2 : création en base, génération d'un SKU unique et écriture de l'audit dans `StockMovement`.
7. `test_update_order_status_tool_handles_transitions_and_cancellation` :
   - Valide la transition normale `pending` $\rightarrow$ `confirmed`.
   - Valide que l'annulation exige une confirmation explicite avec jeton HMAC.
   - Vérifie l'appel au service métier `OrderService::cancelOrder` pour réintégrer les stocks.
8. `test_ai_orchestrator_detects_and_breaks_infinite_loops` :
   - Simule un LLM appelant en boucle le même outil avec les mêmes paramètres.
   - Vérifie l'interruption automatique de la boucle et la synthèse de secours.

---

### 2.2. Phase 2 : RAG Hybride, Mémoire & Outils Métier (`AiAgentPhase2Test.php`)
1. `test_shipping_zones_tool_lists_and_proposes_creation_with_hmac` :
   - Initialisation automatique des zones par défaut si vides.
   - Proposition d'ajout de zone avec tarif et seuil de gratuité.
   - Validation et enregistrement effectif en base.
2. `test_discount_codes_tool_respects_plan_subscription_quota` :
   - Vérifie que le forfait Essentiel se voit refuser l'accès aux codes promo (`feature_restricted`).
3. `test_discount_codes_tool_creates_code_with_hmac_confirmation` :
   - Vérifie l'éligibilité sur forfait Pro.
   - Proposition et validation d'un code -20% dès 25 000 FCFA d'achat.
   - Test des méthodes de calcul de remise `calculateDiscount` et validité `isValid`.
4. `test_discount_codes_tool_rejects_excessive_percentage` :
   - Rejette les pourcentages abusifs (> 90%).
5. `test_ai_shop_memory_service_isolates_and_manages_4_axes` :
   - Extraction automatique des 4 axes (niche, ville/zone, objectif de CA, style concis).
   - Vérification de l'isolation absolue vis-à-vis d'une boutique concurrente (0 fuite).
   - Droit à l'oubli sélectif (`forgetMemory`) et effacement total (`forgetAllMemories`).
6. `test_ai_rag_hybrid_retrieval_and_anti_injection_formatting` :
   - Recherche floue n-gram avec faute de frappe (« livraion » $\rightarrow$ « livraison »).
   - Formatage sécurisé avec balises XML passives anti-prompt-injection.

---

### 2.3. Phase 3 : Vision Multimodale & Studio Marketing (`AiAgentPhase3Test.php`)
1. `test_create_product_from_photo_flow` :
   - Analyse d'image simulée extrayant titre, description, prix recommandé et catégorie.
   - Proposition avec jeton HMAC.
   - Confirmation et création effective du produit avec son audit de stock initial.
   - Vérification de l'échec de sécurité en cas de modification frauduleuse du prix.
2. `test_generate_video_ad_script_creates_campaign_for_tiktok` :
   - Génération d'une campagne TikTok complète avec voix off, découpage en scènes et produits réels.
3. `test_generate_video_ad_script_multi_tenant_isolation` :
   - Tente d'inclure un produit appartenant à une autre boutique.
   - Vérifie le rejet d'accès immédiat.

---

### 2.4. Phase 4 : Proactivité & Routine Matinale (`AiAgentPhase4Test.php`)
1. `test_morning_briefing_command_dry_run_does_not_persist` :
   - Exécution de `php artisan ai:morning-briefing --dry-run`.
   - Vérifie que le rapport s'affiche sans persister de données en base.
2. `test_morning_briefing_command_persists_insight_and_memory` :
   - Calcul des ventes de la veille, des commandes COD à expédier et des alertes de rupture.
   - Vérifie la persistance d'un insight proactif `morning_briefing` et la mise à jour de la mémoire long terme.

---

## 3. Commandes d'Exécution des Tests

### Exécuter l'ensemble des suites du Copilote IA
```bash
php vendor/phpunit/phpunit/phpunit \
  tests/Feature/AiAgentPhase1Test.php \
  tests/Feature/AiAgentPhase2Test.php \
  tests/Feature/AiAgentPhase3Test.php \
  tests/Feature/AiAgentPhase4Test.php
```

### Exécuter une suite spécifique
```bash
# Phase 1 : Sécurité & ReAct Loop
php vendor/phpunit/phpunit/phpunit tests/Feature/AiAgentPhase1Test.php

# Phase 2 : RAG & Mémoire
php vendor/phpunit/phpunit/phpunit tests/Feature/AiAgentPhase2Test.php

# Phase 3 : Vision & Studio Vidéo
php vendor/phpunit/phpunit/phpunit tests/Feature/AiAgentPhase3Test.php

# Phase 4 : Routine Proactive
php vendor/phpunit/phpunit/phpunit tests/Feature/AiAgentPhase4Test.php
```

### Exécuter l'intégralité des tests de la plateforme Zanika
```bash
php vendor/phpunit/phpunit/phpunit
```
