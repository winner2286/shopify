# RÉPERTOIRE OFFICIEL DES OUTILS — ZANIKA AI COPILOT

Ce document répertorie l'ensemble des 24 outils métier intégrés au registre de l'IA (`App\Services\Ai\ToolRegistry`). Chaque outil opère sous une isolation multi-tenant stricte (`shop_id`).

---

## 1. Classification & Niveaux de Sensibilité

| Niveau | Catégorie | Mécanisme de Sécurité | Exemples d'Outils |
| :--- | :--- | :--- | :--- |
| **Lecture Seule (Read-Only)** | Consultation & Analytics | Exécution immédiate, lecture sans impact sur les données. | `get_catalog_overview`, `get_sales_analytics`, `search_products`. |
| **Génération Créative** | Marketing & Studio Vidéo | Génération de contenu textuel et storyboards sans altération de la base. | `generate_marketing_copy`, `generate_video_ad_script`. |
| **Mutation Sensible (Write / Sensitive)** | Modifications Opérationnelles | **Human-In-The-Loop obligatoire** : émission d'un jeton HMAC-SHA256, prévisualisation pour le commerçant, validation explicite. | `create_product`, `update_order_status`, `update_product_stock_or_price`, `manage_shipping_zones`, `manage_discount_codes`. |

---

## 2. Inventaire Exhaustif des Outils

### 2.1. Gestion du Catalogue et des Produits

#### `get_catalog_overview`
- **Description** : Renvoie la vue d'ensemble du catalogue (nombre de produits actifs/brouillons, valeur totale du stock, catégories).
- **Sensibilité** : Lecture seule.
- **Paramètres** : Aucun paramètre obligatoire.

#### `search_products`
- **Description** : Recherche dans le catalogue de la boutique par mot-clé, catégorie ou statut de stock.
- **Sensibilité** : Lecture seule.
- **Paramètres** :
  - `query` (string, optionnel) : Mot-clé recherché dans le titre ou la description.
  - `category_id` (integer, optionnel) : Filtrer par catégorie.
  - `in_stock_only` (boolean, optionnel) : Exclure les ruptures.

#### `create_product`
- **Description** : Propose puis crée un nouveau produit dans le catalogue après validation.
- **Sensibilité** : **Mutation Sensible (Confirmation Requise)**.
- **Vérifications Métier** :
  - Quota de produits selon le plan (`$shop->canAddProduct()`).
  - Validation HMAC du jeton (`ActionTokenService`).
  - Enregistrement automatique de l'audit de stock initial dans `stock_movements`.
- **Paramètres** :
  - `title` (string, requis) : Nom du produit.
  - `price` (integer, requis) : Prix en FCFA.
  - `stock` (integer, optionnel) : Quantité initiale.
  - `compare_at_price` (integer, optionnel) : Prix barré d'origine.
  - `category_name` (string, optionnel) : Nom de la catégorie (créée si inexistante).
  - `sku` (string, optionnel) : Référence interne.
  - `confirmed_by_user` (boolean, optionnel) : `true` lors de la validation.
  - `action_token` (string, optionnel) : Jeton HMAC.

#### `create_product_from_photo`
- **Description** : Analyse une photo de produit via Vision Multimodale (IA), extrait le titre vendeur, la description persuasive, la catégorie et le prix recommandé, puis prépare la création de la fiche produit.
- **Sensibilité** : **Mutation Sensible (Confirmation Requise)**.
- **Paramètres** :
  - `image_data` (string, requis) : Chaîne Base64 ou Data URI de la photo.
  - `merchant_hint` (string, optionnel) : Indication textuelle du commerçant.
  - `price_override` (integer, optionnel) : Prix forcé par le commerçant.
  - `stock` (integer, optionnel) : Quantité initiale (défaut : 10).
  - `confirmed_by_user` / `action_token` : Jeton et flag de confirmation.

#### `update_product_stock_or_price`
- **Description** : Modifie le stock ou le prix d'un produit spécifique avec traçabilité complète.
- **Sensibilité** : **Mutation Sensible (Confirmation Requise)**.
- **Audit** : Crée un mouvement `StockMovement` avec motif et identifiant de l'auteur.
- **Paramètres** :
  - `product_id` (integer, requis) : ID du produit.
  - `new_price` (integer, optionnel) : Nouveau prix en FCFA.
  - `new_stock` (integer, optionnel) : Nouvelle quantité en stock.
  - `confirmed_by_user` / `action_token` : Jeton et validation.

#### `adjust_product_price_percentage`
- **Description** : Applique une hausse ou une baisse en pourcentage sur le prix d'un produit ou d'une sélection.
- **Sensibilité** : **Mutation Sensible (Confirmation Requise)**.
- **Paramètres** :
  - `product_id` (integer, requis) : ID du produit.
  - `percentage` (number, requis) : Pourcentage d'ajustement (ex: `-10` pour -10%, `15` pour +15%).
  - `confirmed_by_user` / `action_token` : Jeton et validation.

#### `get_inventory_alerts`
- **Description** : Liste les produits en rupture de stock critique ou approchant de leur seuil d'alerte.
- **Sensibilité** : Lecture seule.

#### `get_underperforming_products`
- **Description** : Identifie les produits à rotation lente (aucun achat sur les 30 derniers jours malgré du stock disponible) pour suggérer des actions de déstockage.
- **Sensibilité** : Lecture seule.

---

### 2.2. Gestion des Commandes & Logistique COD

#### `get_orders_summary`
- **Description** : Synthétise le carnet de commandes sur une période donnée (en attente, confirmées, expédiées, livrées, annulées).
- **Sensibilité** : Lecture seule.
- **Paramètres** :
  - `period` (string, optionnel) : `'today'`, `'yesterday'`, `'this_week'`, `'this_month'`.
  - `status` (string, optionnel) : Filtrer par statut de commande.

#### `get_order_details`
- **Description** : Affiche les détails complets d'une commande (articles, adresse client, coordonnées de livraison, mode de paiement, historique).
- **Sensibilité** : Lecture seule.
- **Paramètres** :
  - `order_id` (integer, requis) : ID de la commande.

#### `update_order_status`
- **Description** : Modifie le statut opérationnel d'une commande selon la machine d'état officielle (`pending` $\rightarrow$ `confirmed` $\rightarrow$ `shipped` $\rightarrow$ `delivered`). En cas d'annulation (`cancelled`), requiert une confirmation HMAC et réintègre automatiquement les articles dans les stocks du catalogue (`OrderService::cancelOrder`).
- **Sensibilité** : **Mutation Sensible (Confirmation Requise pour Annulation)**.
- **Paramètres** :
  - `order_id` (integer, requis) : ID de la commande.
  - `new_status` (string, requis) : Statut cible.
  - `reason` (string, optionnel) : Motif du changement ou de l'annulation.
  - `confirmed_by_user` / `action_token` : Validation requise pour annulation.

#### `analyze_cod_risk`
- **Description** : Évalue le score de risque de refus d'une commande en Cash on Delivery (fiabilité du numéro de téléphone, historique des commandes du client, distance et cohérence de l'adresse).
- **Sensibilité** : Lecture seule.
- **Paramètres** :
  - `order_id` (integer, requis) : ID de la commande.

#### `get_pending_cod_review`
- **Description** : Récupère la liste des commandes COD en attente de validation téléphonique par le commerçant avant envoi au livreur moto.
- **Sensibilité** : Lecture seule.

#### `dispatch_order_to_courier`
- **Description** : Prépare la fiche d'expédition pour le coursier moto avec instruction de livraison et montant précis à encaisser.
- **Sensibilité** : Opération logistique.
- **Paramètres** :
  - `order_id` (integer, requis) : ID de la commande.
  - `courier_notes` (string, optionnel) : Remarques spécifiques (ex: "Appeler avant d'arriver").

---

### 2.3. Tarifs de Livraison & Codes Promotionnels

#### `manage_shipping_zones`
- **Description** : Consulte, crée ou modifie les zones et forfaits de livraison (ex: Lomé Intra-muros, Grand Lomé, Intérieur du pays, seuil de gratuité).
- **Sensibilité** : **Mutation Sensible pour Création et Modification (Confirmation Requise)**.
- **Paramètres** :
  - `action` (string, requis) : `'list'`, `'create'`, ou `'update'`.
  - `zone_id` (integer, optionnel) : ID de la zone en cas de mise à jour.
  - `name` (string, optionnel) : Intitulé de la zone géographique.
  - `price` (integer, optionnel) : Tarif de livraison en FCFA.
  - `free_shipping_threshold` (integer, optionnel) : Montant pour livraison offerte.
  - `delivery_estimate` (string, optionnel) : Délai indicatif estimé.
  - `confirmed_by_user` / `action_token` : Validation requise.

#### `manage_discount_codes`
- **Description** : Gère les codes promo de la boutique (-X% ou rabais fixe en FCFA).
- **Sensibilité** : **Mutation Sensible (Confirmation Requise)**.
- **Vérifications Métier** :
  - Réservé aux forfaits **Pro et Premium** (`$shop->canUseFeature('promo_codes')`).
  - Plafond maximal de sécurité : rabais en pourcentage limité à 90%.
  - Validation HMAC scellée avec date de fin et seuil minimal d'achat.
- **Paramètres** :
  - `action` (string, requis) : `'list'`, `'create'`, ou `'toggle'`.
  - `code` (string, optionnel) : Code textuel (ex: `'BIENVENUE15'`).
  - `type` (string, optionnel) : `'percentage'` ou `'fixed'`.
  - `value` (integer, optionnel) : Valeur de la réduction.
  - `min_order_amount` (integer, optionnel) : Panier minimum requis en FCFA.
  - `max_uses` (integer, optionnel) : Nombre maximal d'utilisations.
  - `days_valid` (integer, optionnel) : Durée de validité en jours.
  - `confirmed_by_user` / `action_token` : Validation requise.

---

### 2.4. Marketing, Ventes & Studio Vidéo

#### `generate_marketing_copy`
- **Description** : Génère des accroches et textes de vente pour statuts WhatsApp, Facebook Ads ou SMS basés sur le catalogue réel.
- **Sensibilité** : Génération créative (Read-only).
- **Paramètres** :
  - `channel` (string, requis) : `'whatsapp'`, `'facebook_ads'`, `'sms'`, `'bundle_offer'`.
  - `product_title_or_id` (string, optionnel) : Produit spécifique ou offre générale.
  - `angle` (string, optionnel) : `'urgency'`, `'social_proof'`, `'discount'`, `'benefit'`.

#### `generate_video_ad_script`
- **Description** : Génère un script vidéo complet, un découpage en scènes (storyboard) et un appel à l'action calibré pour TikTok, Reels Instagram ou WhatsApp avec les produits et prix réels du catalogue.
- **Sensibilité** : Génération créative (Read-only).
- **Paramètres** :
  - `platform` (string, optionnel) : `'tiktok'`, `'instagram'`, `'whatsapp'`, `'facebook'`, `'general'`.
  - `angle` (string, optionnel) : `'nouveautes'`, `'bestsellers'`, `'promotions'`, `'coup_de_coeur'`, `'boutique'`.
  - `product_id` (integer, optionnel) : Produit vedette spécifique.
  - `tone` (string, optionnel) : `'dynamique'`, `'luxe'`, `'fun'`, `'urgent'`, `'chaleureux'`.
  - `custom_message` (string, optionnel) : Consigne particulière du marchand.

#### `draft_customer_message`
- **Description** : Rédige une réponse professionnelle et personnalisée pour un client (confirmation de commande, suivi coursier, réponse à une réclamation).
- **Sensibilité** : Lecture seule / Rédaction.

#### `answer_customer_inquiry`
- **Description** : Simule la réponse à une question fréquente d'acheteur en s'appuyant sur les règles et conditions réelles de la boutique.
- **Sensibilité** : Lecture seule.

---

### 2.5. Analytics & Recommandations d'Affaires

#### `get_sales_analytics`
- **Description** : Analyse détaillée du chiffre d'affaires, panier moyen et taux de conversion sur une période choisie.
- **Sensibilité** : Lecture seule.

#### `compare_sales_periods`
- **Description** : Compare deux périodes commerciales consécutives (ex: cette semaine vs semaine dernière) et identifie les tendances.
- **Sensibilité** : Lecture seule.

#### `get_weekly_sales_report`
- **Description** : Génère le rapport hebdomadaire consolidé des performances de la boutique.
- **Sensibilité** : Lecture seule.

#### `get_business_recommendations`
- **Description** : Recommandations stratégiques personnalisées basées sur l'état actuel de la boutique (stocks dormants, opportunités de packs, calendrier commercial ouest-africain).
- **Sensibilité** : Lecture seule.
