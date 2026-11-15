<?php

namespace Database\Seeders;

use App\Models\AiKnowledgeChunk;
use Illuminate\Database\Seeder;

class AiKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $chunks = [
            // =========================================================================
            // 1. NAVIGATION & GESTION DU CATALOGUE
            // =========================================================================
            [
                'category' => 'catalog_rules',
                'title' => 'Comment ajouter un nouveau produit sur Zanika',
                'slug' => 'guide-ajouter-produit',
                'keywords' => ['ajouter', 'nouveau', 'produit', 'creer', 'article', 'catalogue', 'stock', 'prix', 'fiche'],
                'target_routes' => 'products.create, products.index, ai.studio',
                'sort_order' => 1,
                'content' => <<<TEXT
Pour ajouter un produit dans votre boutique Zanika, vous disposez de 3 méthodes officielles :
Méthode 1 — Depuis l'interface Catalogue :
1. Rendez-vous dans le menu latéral : cliquez sur "Produits" (URL : /produits).
2. Cliquez sur le bouton "+ Ajouter un produit" en haut à droite (URL : /produits/creer).
3. Renseignez les informations clés :
   - Titre du produit (nom clair, précis et vendeur).
   - Catégorie (à sélectionner parmi vos catégories existantes ou à créer dans /categories).
   - Prix de vente en FCFA (entier positif sans virgule).
   - Prix d'origine barré (optionnel, champ "compare_at_price" pour afficher une remise en %).
   - Gestion de stock : cochez "Suivre le stock", indiquez le stock initial et le seuil d'alerte de stock faible (ex: 5 unités).
   - Description détaillée mettant en avant les bénéfices client.
   - Photos du produit (vous pouvez aussi les sublimer via le Studio Visuel IA).
4. Cliquez sur "Enregistrer le produit".

Méthode 2 — Création directe avec votre Copilote IA :
Demandez dans le chat : « Crée un produit [Nom] à [Prix] FCFA avec [Stock] unités en stock ». L'assistant prépare la fiche complète via l'outil create_product et affiche un panneau de validation sécurisé en 1 clic.

Méthode 3 — Création par simple photo (Vision IA) :
Envoyez ou analysez la photo de votre article via l'outil create_product_from_photo ou le Studio IA : l'IA rédige le titre, la description, suggère une fourchette de prix en FCFA et prépare la mise en vente sous votre validation.
TEXT
            ],

            [
                'category' => 'catalog_rules',
                'title' => 'Comment modifier le prix ou le stock d\'un article',
                'slug' => 'guide-modifier-prix-stock',
                'keywords' => ['modifier', 'changer', 'prix', 'stock', 'tarif', 'mettre a jour', 'reajuster', 'article'],
                'target_routes' => 'products.edit, products.index',
                'sort_order' => 2,
                'content' => <<<TEXT
Il existe 2 manières officielles de modifier le prix ou le stock d'un produit sur Zanika :
Méthode 1 (Manuelle via l'interface) :
1. Allez dans le menu "Produits" (/produits).
2. Cliquez sur le produit concerné ou sur son bouton "Modifier" (/produits/{id}/modifier).
3. Modifiez le champ "Prix" ou "Stock disponible".
4. Cliquez sur "Mettre à jour". Chaque modification de stock enregistre automatiquement un mouvement d'audit traçable dans stock_movements.

Méthode 2 (Assistée via l'assistant ECOM AI) :
Demandez directement dans le chat : « Change le prix du produit [Nom ou ID] à 15 000 FCFA », « Applique une baisse de 10% sur [Produit] » ou « Mets le stock de [Nom] à 25 unités ».
L'assistant prépare l'action via update_product_stock_or_price ou adjust_product_price_percentage et affiche une carte de confirmation sécurisée par jeton HMAC ("Valider la modification"). Aucune donnée n'est modifiée sans votre validation explicite.
TEXT
            ],

            [
                'category' => 'catalog_rules',
                'title' => 'Comment savoir si un produit est en rupture de stock ou proche de la rupture',
                'slug' => 'guide-rupture-stock-alertes',
                'keywords' => ['rupture', 'stock', 'epuise', 'seuil', 'alerte', 'critique', 'inventaire', 'savoir', 'manque'],
                'target_routes' => 'products.index, dashboard',
                'sort_order' => 3,
                'content' => <<<TEXT
Pour savoir immédiatement si un produit est en rupture de stock ou sous son seuil critique :
1. Sur le Tableau de bord (/tableau-de-bord) : le bloc "Alertes de stock" signale en temps réel les articles épuisés ou proches de la rupture.
2. Dans le Catalogue Produits (/produits) :
   - Un badge rouge "Rupture" s'affiche dès que le stock est à 0.
   - Un badge orange "Stock faible" s'affiche dès que le stock est inférieur ou égal au seuil d'alerte (low_stock_threshold, par défaut 5 unités).
3. Via l'assistant IA : demandez « Comment savoir si un produit est en rupture de stock ? » ou « Quels sont mes produits en rupture ? ». L'assistant interroge votre base en temps réel avec l'outil get_inventory_alerts et vous liste les articles critiques avec le manque à gagner estimé.
4. Via les Automatisations IA (/assistant-ia/automatisations) : l'alerte proactive de stock faible surveille automatiquement votre inventaire.
TEXT
            ],

            [
                'category' => 'catalog_rules',
                'title' => 'Comment créer une réduction, une promotion ou un code promo sur Zanika',
                'slug' => 'guide-creer-reduction-promo',
                'keywords' => ['reduction', 'promotion', 'code promo', 'solde', 'prix barre', 'remise', 'rabais', 'offre', 'coupon'],
                'target_routes' => 'products.edit, ads.create, ai.marketing',
                'sort_order' => 4,
                'content' => <<<TEXT
Sur Zanika, vous disposez de 3 mécanismes complémentaires pour créer une réduction :
1. Le Prix Barré sur une fiche produit (accessible à tous les forfaits) :
   - Ouvrez la fiche du produit (/produits/{id}/modifier).
   - Renseignez un "Prix barré / Prix d'origine" (compare_at_price) supérieur au prix de vente actuel (ex: Prix de vente = 12 000 FCFA, Prix barré = 15 000 FCFA).
   - La vitrine publique affiche automatiquement le prix barré et un macaron de remise (ex: -20%).
   - Vous pouvez aussi demander à l'IA : « Applique une remise de 15% sur [Produit] » (outil adjust_product_price_percentage).

2. Les Codes Promotionnels (inclus dès la formule Pro et Premium) :
   - Vous pouvez créer un code promo (ex: BIENVENUE10, TABASKI20) offrant un pourcentage de remise (jusqu'à 90%) ou un montant fixe en FCFA, avec un montant minimum de commande, un plafond d'utilisations et une durée de validité.
   - Demandez à l'IA : « Crée un code promo PROMO15 de 15% valable 14 jours » (outil manage_discount_codes avec confirmation sécurisée).

3. Le Studio Marketing & Publicités (/publicite/creer ou /assistant-ia/marketing) :
   - Génère automatiquement les affiches promotionnelles, vidéos TikTok/Statuts WhatsApp et textes de vente annonçant votre réduction.
TEXT
            ],

            // =========================================================================
            // 2. COMMANDES, EXPÉDITIONS, RETOURS & CASH ON DELIVERY (COD)
            // =========================================================================
            [
                'category' => 'orders_cod',
                'title' => 'Comment consulter, filtrer et traiter mes commandes',
                'slug' => 'guide-consulter-commandes',
                'keywords' => ['commandes', 'consulter', 'voir', 'statut', 'livraison', 'suivi', 'ventes', 'facture', 'recu'],
                'target_routes' => 'orders.index, orders.show',
                'sort_order' => 5,
                'content' => <<<TEXT
Pour consulter et gérer vos commandes sur Zanika :
1. Cliquez sur "Commandes" dans le menu principal (/commandes).
2. Filtrez vos commandes par statut grâce aux onglets :
   - "En attente" (pending) : nouvelles commandes à reconfirmer rapidement avec le client.
   - "Confirmées" (confirmed) : commandes validées prêtes à être préparées.
   - "Expédiées" (shipped) : colis remis au coursier / livreur.
   - "Livrées" (delivered) : colis livré au client et encaissé.
   - "Annulées" (cancelled) : commandes annulées (avec remise en stock automatique des articles).
3. Cliquez sur une commande (/commandes/{id}) pour :
   - Voir le détail des articles, le quartier de livraison, le téléphone WhatsApp du client et l'historique des statuts.
   - Télécharger ou partager le reçu officiel (/commandes/{id}/recu).
   - Dépêcher la commande à un livreur sur WhatsApp en 1 clic.
4. Via l'assistant IA : demandez « Résume mes commandes du jour » (outil get_orders_summary) ou « Donne-moi les détails de la commande #[Numéro] » (outil get_order_details).
TEXT
            ],

            [
                'category' => 'orders_cod',
                'title' => 'Comment dépêcher une commande à un livreur sur WhatsApp',
                'slug' => 'guide-depecher-livreur-whatsapp',
                'keywords' => ['livreur', 'coursier', 'depecher', 'moto', 'zemidjan', 'whatsapp', 'expedition', 'feuille de route'],
                'target_routes' => 'orders.show',
                'sort_order' => 6,
                'content' => <<<TEXT
Zanika facilite la coordination avec vos livreurs et coursiers moto :
1. Ouvrez la fiche de la commande (/commandes/{id}) et cliquez sur "Dépêcher au livreur", ou demandez à l'IA : « Prépare la feuille de route livreur pour la commande #[Numéro] » (outil dispatch_order_to_courier).
2. Le système génère un message WhatsApp structuré prêt à envoyer contenant :
   - Le numéro de commande, le nom et le téléphone direct du client.
   - La zone et l'adresse précise / repère de livraison.
   - La liste des articles à remettre.
   - Le montant exact à encaisser à la livraison (en cas de COD) ou la mention "Déjà payé par Mobile Money".
TEXT
            ],

            [
                'category' => 'orders_cod',
                'title' => 'Comment configurer les zones et tarifs de livraison',
                'slug' => 'guide-zones-livraison',
                'keywords' => ['livraison', 'zones', 'frais', 'expedition', 'tarif', 'quartier', 'ville', 'transport'],
                'target_routes' => 'shipping.index',
                'sort_order' => 7,
                'content' => <<<TEXT
Pour gérer vos zones et frais de livraison :
1. Allez dans le menu "Livraison" (/livraison).
2. Ajoutez ou modifiez vos zones tarifaires :
   - Nom de la zone (ex: "Lomé Intra-muros", "Grand Lomé", "Cotonou Centre", "Abidjan Sud", "Retrait en boutique").
   - Tarif de livraison en FCFA (ex: 1 000 FCFA, 1 500 FCFA, 0 FCFA pour le retrait).
   - Seuil de livraison gratuite optionnel (free_shipping_threshold, ex: livraison offerte dès 30 000 FCFA d'achat).
   - Délai estimé (ex: "Livraison express en 2h à 4h", "Sous 24h").
3. Vous pouvez aussi demander à votre copilote IA : « Liste mes zones de livraison » ou « Ajoute une zone de livraison Baguida à 2 000 FCFA » (outil manage_shipping_zones).
TEXT
            ],

            [
                'category' => 'orders_cod',
                'title' => 'Comment gérer les annulations, retours clients et réclamations SAV',
                'slug' => 'guide-retours-annulations-sav',
                'keywords' => ['annuler', 'annulation', 'retour', 'remboursement', 'reclamation', 'sav', 'litige', 'stock'],
                'target_routes' => 'orders.show, shop.pages.index',
                'sort_order' => 8,
                'content' => <<<TEXT
Règles métier Zanika pour les annulations et retours :
1. Annulation d'une commande :
   - Lorsqu'une commande est annulée (via la fiche commande /commandes/{id} ou via l'outil IA update_order_status avec confirmation obligatoire), Zanika restitue automatiquement les quantités commandées dans le stock physique de chaque produit et crée une écriture d'audit "cancellation" dans stock_movements.
2. Gestion d'une réclamation ou demande de retour client :
   - Répondez toujours avec empathie dans l'heure sur WhatsApp (utilisez l'outil answer_customer_inquiry ou draft_customer_message).
   - Si le produit présente un défaut ou une erreur de taille, proposez un échange rapide ou un avoir valable sur la boutique afin de préserver la trésorerie et la confiance du client.
   - Précisez clairement vos conditions d'échange (ex: sous 48h dans l'emballage d'origine) sur votre page boutique "Livraison & Retours" (/boutique/pages).
TEXT
            ],

            // =========================================================================
            // 3. PAIEMENTS, STUDIO IA, CRM & PARAMÈTRES PLATEFORME
            // =========================================================================
            [
                'category' => 'payments_geniuspay',
                'title' => 'Comment fonctionnent les paiements Mobile Money (GeniusPay) et Cash on Delivery',
                'slug' => 'guide-systeme-paiement',
                'keywords' => ['paiement', 'tmoney', 'moov', 'moov money', 'flooz', 'geniuspay', 'feexpay', 'cash', 'especes', 'carte', 'mobile money', 'wave', 'momo'],
                'target_routes' => 'shop.settings, storefront.checkout',
                'sort_order' => 9,
                'content' => <<<TEXT
Zanika combine deux modes d'encaissement complémentaires :
1. Paiement à la livraison (Cash on Delivery - COD) :
   - Le client valide sa commande en ligne et règle en espèces ou par transfert Mobile Money au moment où le livreur lui remet le colis.
   - Vous suivez vos encaissements COD en attente dans /assistant-ia/commandes-cod ou en demandant « Fais le point sur ma trésorerie COD en attente » (outil get_pending_cod_review).
2. Paiement en ligne instantané via GeniusPay :
   - Compatible avec T-Money (Mixx by Yas), Moov Money, MTN Mobile Money, Orange Money, Wave et Cartes Bancaires (Visa / Mastercard).
   - Dès validation du paiement par l'opérateur, le webhook sécurisé passe automatiquement la commande au statut de paiement "Payée" (paid) et confirme la commande.
TEXT
            ],

            [
                'category' => 'navigation_ui',
                'title' => 'Comment utiliser le Studio Photo IA et le Studio Publicitaire Vidéo',
                'slug' => 'guide-studios-visuel-video-ia',
                'keywords' => ['studio', 'photo', 'image', 'detourage', 'packshot', 'video', 'tiktok', 'publicite', 'affiche'],
                'target_routes' => 'ai.studio, ads.index, ads.create, ai.marketing',
                'sort_order' => 10,
                'content' => <<<TEXT
Zanika met à votre disposition deux studios créatifs propulsés par l'IA :
1. Studio Visuel Produit IA (/assistant-ia/studio) :
   - Détoure automatiquement la photo réelle de votre produit sans jamais déformer ni inventer l'objet (conservation 100% fidèle).
   - Génère 4 mises en scène professionnelles au format carré 1:1 : Studio Blanc Épuré (standard Amazon/Zara), Ambiance Douce Ivoire, Décor Lifestyle adapté à la catégorie (parquet, marbre, dressing) et Podium Minimaliste Luxe, tout en conservant votre photo originale.
2. Studio Publicitaire & Générateur Vidéo (/publicite et /publicite/creer) :
   - Crée des campagnes complètes pour TikTok, Instagram Reels, Facebook et Statuts WhatsApp.
   - Génère 3 variations de textes persuasifs et des scripts vidéo de 20 à 25 secondes synchronisés scène par scène avec voix-off (prononciation naturelle des prix en francs CFA).
TEXT
            ],

            [
                'category' => 'navigation_ui',
                'title' => 'Comment gérer les clients (CRM), les paramètres boutique et les rôles utilisateurs',
                'slug' => 'guide-crm-parametres-roles',
                'keywords' => ['clients', 'crm', 'fidelisation', 'parametres', 'boutique', 'pages', 'roles', 'forfaits', 'abonnement'],
                'target_routes' => 'customers.index, shop.settings, shop.pages.index, subscription.checkout',
                'sort_order' => 11,
                'content' => <<<TEXT
Organisation de votre espace commerçant Zanika :
1. Fichier Clients CRM (/clients) :
   - Agrège automatiquement chaque acheteur avec son historique de commandes, son chiffre d'affaires cumulé (LTV) et son contact WhatsApp pour vos campagnes de fidélisation et relances VIP.
2. Paramètres & Pages de la Boutique (/boutique/parametres et /boutique/pages) :
   - Configurez le nom de la boutique, le sous-domaine (.zanika.shop), le logo, la couleur de marque, le numéro WhatsApp et les 4 pages légales/informatives (À propos, Contact, Livraison, CGV).
3. Rôles et Sécurité Multi-Tenant :
   - Rôle Marchand (merchant) : accès exclusif et strictement isolé aux données de sa propre boutique.
   - Rôle Administrateur Plateforme (admin) : supervision technique globale de Zanika, gestion des forfaits (Essentiel 10 000 FCFA, Pro 25 000 FCFA, Premium 50 000 FCFA) et audit de santé.
TEXT
            ],

            // =========================================================================
            // 4. PLAYBOOKS MÉTIER E-COMMERCE & MARCHÉS AFRICAINS
            // =========================================================================
            [
                'category' => 'africa_playbook',
                'title' => 'Playbook : Réduire les refus et faux bonds en Cash on Delivery (COD)',
                'slug' => 'playbook-anti-refus-cod',
                'keywords' => ['cod', 'cash on delivery', 'refus', 'annulations', 'livraison', 'faux bond', 'reconfirmation'],
                'target_routes' => 'orders.index, ai.orders.cod_review',
                'sort_order' => 12,
                'content' => <<<TEXT
En Afrique de l'Ouest, le taux d'annulation en Cash on Delivery dépasse 25% sans méthodologie stricte.
Protocole officiel Zanika pour abaisser les refus sous les 7% :
1. Règle d'or des 30 minutes :
   - Appelez le client ou envoyez une note vocale WhatsApp chaleureuse dans les 30 minutes suivant la commande.
2. Validation des 3 points clés avant d'envoyer le coursier :
   - Disponibilité physique du client sur le créneau horaire précis.
   - Confirmation du montant total exact (articles + frais de livraison en FCFA) prêt en espèces ou Mobile Money.
   - Obtention d'un repère géographique précis (ex: "Devant la pharmacie X", "À 100m du carrefour Y") en complément du nom de quartier.
3. Filtrage de risque avec l'outil IA analyze_cod_risk :
   - Vérifiez l'historique du numéro de téléphone et le montant du panier. Pour une première commande de montant élevé (> 50 000 FCFA) ou en province, demandez une avance symbolique des frais de port (1 000 à 2 000 FCFA) par Mobile Money pour verrouiller l'engagement.
TEXT
            ],

            [
                'category' => 'africa_playbook',
                'title' => 'Playbook : Psychologie d\'achat, gestion des objections et négociation sur WhatsApp',
                'slug' => 'playbook-negociation-whatsapp',
                'keywords' => ['whatsapp', 'negociation', 'rabais', 'prix', 'objection', 'vente', 'persuasion', 'cher', 'fidelisation'],
                'target_routes' => 'ai.marketing',
                'sort_order' => 13,
                'content' => <<<TEXT
Techniques de persuasion commerciale éthique sur WhatsApp et les réseaux sociaux :
1. Face à l'objection « C'est trop cher / Fais-moi un prix » :
   - Ne bradez jamais votre prix unitaire sèchement (cela détruit votre marge et la valeur perçue).
   - Justifiez d'abord la valeur (qualité des finitions, durabilité, service après-vente).
   - Proposez une contrepartie gagnant-gagnant :
     * L'Offre Pack Duo (Bundling) : « L'unité est à 15 000 FCFA, mais si vous en prenez 2 aujourd'hui, je vous offre 3 000 FCFA de remise immédiate sur le pack ».
     * Le Geste sur la Livraison : « Je maintiens le prix boutique mais je prends en charge la moitié (ou la totalité) de vos frais de livraison aujourd'hui si on valide votre commande avant 16h ».
2. Relance et Fidélisation (Rétention) :
   - 72h après une livraison réussie, envoyez un message de suivi courtois pour vérifier la satisfaction du client et lui offrir un privilège VIP sur son prochain achat.
TEXT
            ],

            [
                'category' => 'africa_playbook',
                'title' => 'Playbook : Diagnostic de rentabilité (« Beaucoup de commandes mais bénéfices faibles »)',
                'slug' => 'playbook-diagnostic-rentabilite',
                'keywords' => ['benefice', 'benefices', 'rentabilite', 'marge', 'marges', 'faibles', 'perte', 'couts', 'diagnostic', 'chiffre affaires'],
                'target_routes' => 'analytics.index, analytics.advisor',
                'sort_order' => 14,
                'content' => <<<TEXT
Lorsqu'un commerçant indique avoir beaucoup de commandes mais des bénéfices faibles, l'agent IA doit impérativement diagnostiquer ces 4 causes racines chiffrées :
1. Frais logistiques mal répercutés :
   - Si le coursier moto coûte 1 500 ou 2 000 FCFA mais que la zone de livraison est facturée 1 000 FCFA au client, chaque commande grignote la marge nette.
   - Action : Réviser les tarifs dans /livraison et fixer un seuil de panier minimum (free_shipping_threshold) rentable.
2. Coût caché des retours et refus Cash on Delivery (COD) :
   - Chaque colis refusé à la livraison fait perdre les frais de course aller-retour et bloque le stock.
   - Action : Appliquer le protocole de reconfirmation WhatsApp sous 30 minutes et analyser les commandes via get_pending_cod_review.
3. Panier moyen (AOV) trop faible par rapport au coût d'acquisition :
   - Vendre un seul article à faible prix unitaire ne couvre pas les frais de publicité et de traitement.
   - Action : Créer des packs de 2 ou 3 articles complémentaires ou proposer un produit additionnel (upsell/cross-sell) lors de la confirmation WhatsApp.
4. Remises excessives ou capital immobilisé dans le stock dormant :
   - Identifier les produits à faible rotation via get_underperforming_products pour libérer la trésorerie et concentrer le budget pub sur les best-sellers à forte marge.
TEXT
            ],

            [
                'category' => 'africa_playbook',
                'title' => 'Playbook : Spécificités des marchés e-commerce africains par pays (sans généralisation)',
                'slug' => 'playbook-marches-africains-specifiques',
                'keywords' => ['togo', 'benin', 'cote ivoire', 'senegal', 'burkina', 'cameroun', 'maroc', 'afrique', 'devise', 'quartiers'],
                'target_routes' => 'shipping.index, shop.settings',
                'sort_order' => 15,
                'content' => <<<TEXT
Chaque marché africain possède ses propres codes monétaires, logistiques et culturels qu'il ne faut jamais confondre :
- Togo (XOF / FCFA) : Paiements dominés par T-Money (Mixx by Yas) et Moov Money. Livraison urbaine par coursiers moto (zemidjans) structurée autour de Lomé Intra-muros, Grand Lomé (Agoè, Adidogomé, Baguida) et l'intérieur (Kara, Sokodé, Atakpamé). Français et expressions locales (Éwé, Mina).
- Bénin (XOF / FCFA) : Paiements MTN Mobile Money et Moov Money. Logistique active sur Cotonou, Abomey-Calavi et Porto-Novo. Français, Fon, Yoruba.
- Côte d'Ivoire (XOF / FCFA) : Forte pénétration de Wave, Orange Money et MTN MoMo. Tarification logistique par communes d'Abidjan (Cocody, Yopougon, Marcory, Plateau, Treichville, Abobo, Riviera) et expéditions vers Bouaké/Yamoussoukro.
- Sénégal (XOF / FCFA) : Wave est quasi-hégémonique aux côtés d'Orange Money. Livraison par zones à Dakar (Plateau, Almadies, Mermoz, Parcelles Assainies, Pikine, Guédiawaye). Français et Wolof.
- Maroc (MAD / Dirham) : Paiement COD très répandu via sociétés de messagerie (Amana, Cathedis, Ozex) et paiement en ligne CMI. Communication commerciale fluide en Français et en Darija marocain.
TEXT
            ],

            [
                'category' => 'ecommerce_strategy',
                'title' => 'Playbook : Copywriting, SEO E-Commerce et Optimisation des Fiches Produits',
                'slug' => 'playbook-copywriting-seo-conversion',
                'keywords' => ['seo', 'copywriting', 'description', 'titre', 'conversion', 'fiche produit', 'optimiser', 'vendre'],
                'target_routes' => 'products.edit, ai.marketing',
                'sort_order' => 16,
                'content' => <<<TEXT
Méthode pour transformer une fiche produit ordinaire en page à haute conversion sur Zanika :
1. Titre optimisé SEO & WhatsApp (max 60 caractères) :
   - Structure : [Type d'article] + [Matière ou Caractéristique clé] + [Bénéfice ou Style]. Exemple : « Robe Longue Wax Authentique — Coupe Élégante & Confort ».
2. Description persuasive selon la structure PAS / AIDA :
   - Accroche émotionnelle centrée sur le client (et non sur des caractéristiques froides).
   - Liste à puces de 3 à 4 bénéfices concrets (confort, durabilité, gain de temps, garantie).
   - Guide des tailles ou conseils d'utilisation/entretien pour lever les doutes avant l'achat.
   - Rassurance logistique en fin de fiche (livraison rapide, paiement Mobile Money ou à la livraison).
3. Visuels de confiance :
   - Au moins 2 photos nettes (packshot fond blanc + mise en scène lifestyle via le Studio Visuel IA /assistant-ia/studio).
TEXT
            ],

            // =========================================================================
            // 5. MULTI-BOUTIQUES, FORFAITS, ONBOARDING & GESTION AVANCÉE (NOUVEAU)
            // =========================================================================
            [
                'category' => 'navigation_ui',
                'title' => 'Comment créer plusieurs boutiques et comprendre les limites par forfait',
                'slug' => 'guide-multi-boutiques-forfaits',
                'keywords' => ['multi-boutiques', 'plusieurs boutiques', 'creer boutique', 'nouvelle boutique', 'forfait', 'essentiel', 'pro', 'premium', 'limite', 'quota', 'basculer'],
                'target_routes' => 'shops.create, dashboard, subscription.checkout',
                'sort_order' => 17,
                'content' => <<<TEXT
Sur Zanika, un même compte commerçant peut créer et piloter plusieurs boutiques distinctes selon son forfait actif :
1. Quotas de boutiques par formule d'abonnement :
   - Formule Essentiel (10 000 FCFA / mois) : 1 boutique incluse, jusqu'à 50 produits, 80 crédits IA/mois.
   - Formule Pro (15 000 FCFA / mois) : Jusqu'à 3 boutiques incluses, 300 produits par boutique, 350 crédits IA/mois, codes promo, alertes de rupture et scripts TikTok.
   - Formule Premium (30 000 FCFA / mois) : Boutiques illimitées (jusqu'à 999), 2 500 produits, 1 200 crédits IA/mois, export comptable et support VIP.
2. Comment créer une nouvelle boutique (/boutiques/creer) :
   - Depuis le Tableau de bord (/tableau-de-bord), cliquez sur le bouton « + Nouvelle boutique ».
   - Le formulaire minimaliste ne demande que l'essentiel : Nom de la boutique (avec aperçu instantané du sous-domaine .zanika.shop), Pays et Devise (FCFA, MAD, EUR, USD).
   - Si votre limite de boutiques est atteinte, Zanika vous invite automatiquement à passer au forfait supérieur (/abonnement/paiement/pro ou premium).
3. Comment basculer d'une boutique à l'autre :
   - Utilisez le sélecteur de boutique en haut du Tableau de bord pour changer de boutique active en 1 clic sans vous déconnecter.
TEXT
            ],

            [
                'category' => 'catalog_rules',
                'title' => 'Comment ajuster rapidement le stock d\'un produit sans justificatif obligatoire',
                'slug' => 'guide-ajustement-rapide-stock',
                'keywords' => ['ajustement', 'rapide', 'stock', 'variation', 'motif', 'optionnel', 'justificatif', 'inventaire', 'quantite', 'seuil'],
                'target_routes' => 'products.edit, products.index',
                'sort_order' => 18,
                'content' => <<<TEXT
Zanika simplifie la mise à jour quotidienne de vos stocks sur mobile comme sur ordinateur :
1. Ajustement direct sur la fiche produit (/produits/{id}/modifier) :
   - Dans la section "Gestion du stock", saisissez directement la nouvelle quantité en stock ou la variation (+5, -2) : le motif ou justificatif écrit est 100% optionnel (non obligatoire).
   - Si vous laissez le champ motif vide, Zanika enregistre automatiquement "Ajustement manuel" dans le journal d'audit stock_movements.
   - Vous pouvez également définir le "Seuil d'alerte stock bas" (par défaut 3 ou 5 unités) pour être prévenu avant la rupture.
2. Ajustement par commande vocale ou texte via Zanika AI :
   - Dites simplement : « Mets le stock de [Produit] à 20 » ou « Ajoute 10 unités au stock de [Produit] ». L'IA prépare la mise à jour via update_product_stock_or_price et vous demande une confirmation en 1 clic.
TEXT
            ],

            [
                'category' => 'navigation_ui',
                'title' => 'Comment choisir entre Sublimer mes photos avec l\'IA ou garder mes photos originales',
                'slug' => 'guide-choix-traitement-photo-onboarding',
                'keywords' => ['photo', 'onboarding', 'sublimer', 'original', 'originale', 'brut', 'studio ia', 'retouche', 'garder', 'qualite'],
                'target_routes' => 'onboarding.step3, ai.studio, products.create',
                'sort_order' => 19,
                'content' => <<<TEXT
Lors de la création de votre boutique (/onboarding/photos) ou de l'ajout d'articles, Zanika vous laisse le contrôle total sur le rendu de vos photos produits :
1. Option « Sublimer avec l'IA » (Recommandé — mode 'studio') :
   - L'IA équilibre la lumière, renforce la netteté, nettoie l'arrière-plan et présente votre article sur un fond studio propre et professionnel, tout en respectant à 100% la forme et la couleur réelle de votre produit.
   - Un badge vert « Studio IA » identifie les visuels sublimés.
2. Option « Garder telles quelles » (Photo originale brute — mode 'original') :
   - Vos photos originales sont conservées exactement telles que vous les avez prises ou importées depuis votre téléphone, sans détourage ni modification de l'arrière-plan.
   - Idéal si vous avez déjà photographié vos articles dans un décor réel de votre boutique ou sur un mannequin.
TEXT
            ],

            [
                'category' => 'marketing_ads',
                'title' => 'Comment générer des affiches promotionnelles (Flyers) et des vidéos publicitaires TikTok',
                'slug' => 'guide-affiches-flyers-videos-tiktok',
                'keywords' => ['affiche', 'flyer', 'poster', 'visuel', 'tiktok', 'reels', 'statut whatsapp', 'video', 'publicite', 'campagne', 'audio'],
                'target_routes' => 'ads.flyers, ads.create, ads.index',
                'sort_order' => 20,
                'content' => <<<TEXT
Pour attirer des clients depuis WhatsApp, TikTok, Instagram et Facebook sans graphiste :
1. Générateur d'Affiches & Flyers Promotionnels (/publicite/affiches) :
   - Sélectionnez un produit de votre catalogue, choisissez un modèle d'affiche (Promo Flash, Nouvel Arrivage, Spécial Fêtes, Luxe Minimaliste), personnalisez l'accroche et téléchargez l'image HD prête à être postée en Statut WhatsApp ou sur Facebook/Instagram.
2. Créateur de Campagnes & Vidéos Publicitaires IA (/publicite/creer) :
   - Sélectionnez un ou plusieurs produits et la plateforme cible (TikTok, Instagram Reels, Facebook Ads, WhatsApp).
   - L'IA rédige 3 variantes de textes publicitaires (accroche, corps persuasif, appel à l'action) et un script vidéo complet scène par scène.
   - Vous pouvez exporter la vidéo promotionnelle avec voix-off IA ou importer votre propre piste audio personnalisée (/publicite/{ad}/upload-audio).
TEXT
            ],

            [
                'category' => 'navigation_ui',
                'title' => 'Comment fonctionnent la Mémoire IA de la boutique et les Automatisations Proactives',
                'slug' => 'guide-memoire-ia-automatisations',
                'keywords' => ['memoire', 'souvenir', 'objectif', 'automatisation', 'proactif', 'briefing', 'matinal', 'conseiller'],
                'target_routes' => 'ai.memory.index, ai.automations, analytics.advisor',
                'sort_order' => 21,
                'content' => <<<TEXT
Zanika AI n'est pas un simple chatbot : il mémorise le contexte de votre entreprise et travaille en arrière-plan.
1. Mémoire Intelligente de la Boutique (/assistant-ia/memoire) :
   - Enregistre automatiquement ou manuellement vos objectifs financiers (ex: "Atteindre 2 000 000 FCFA ce mois"), vos habitudes logistiques, votre ton de marque et vos règles de remise.
   - Vous pouvez consulter, modifier ou supprimer chaque souvenir à tout moment dans /assistant-ia/memoire.
2. Automatisations IA (/assistant-ia/automatisations) :
   - Activez ou mettez en pause des tâches autonomes : surveillance quotidienne des ruptures de stock, détection des commandes COD à risque, relance des produits dormants et génération du Briefing Matinal.
   - Le bouton « Exécuter maintenant » lance immédiatement une analyse complète de la boutique.
3. Conseiller Analytique IA (/statistiques/conseiller-ia) :
   - Fournit un diagnostic stratégique sur l'évolution de votre chiffre d'affaires, votre taux de conversion et vos meilleures ventes avec export CSV (/statistiques/exporter).
TEXT
            ],

            [
                'category' => 'africa_playbook',
                'title' => 'Playbook : Calendrier Commercial & Campagnes Saisonnières en Afrique (Tabaski, Ramadan, Fêtes)',
                'slug' => 'playbook-campagnes-saisonnieres-afrique',
                'keywords' => ['tabaski', 'ramadan', 'korite', 'aid', 'noel', 'fin annee', 'rentree', 'saint valentin', 'black friday', 'saison', 'fete', 'stock', 'pack'],
                'target_routes' => 'ads.create, discounts.index, ai.marketing',
                'sort_order' => 22,
                'content' => <<<TEXT
Réussir ses pics de ventes saisonniers sur les marchés africains (Togo, Bénin, Côte d'Ivoire, Sénégal, Cameroun, Maroc) :
1. Ramadan & Korité (Aïd el-Fitr) / Tabaski (Aïd el-Kébir) :
   - Anticipez votre stock 15 à 20 jours avant la fête et lancez les précommandes (forte demande sur le prêt-à-porter traditionnel, bazin, wax, chaussures, parfums, paniers garnis et décoration).
   - Créez un code promo dédié (ex: TABASKI15 ou RAMADAN10 dans /codes-promo) et proposez des "Packs Famille" ou "Coffrets Cadeaux".
   - Attention logistique : bloquez les expéditions interurbaines 72h avant le jour J et concentrez les coursiers moto sur la livraison express urbaine jusqu'à la veille au soir.
2. Fêtes de Fin d'Année (Décembre — Noël & Nouvel An) :
   - Période de plus forte liquidité de l'année. Mettez en avant les idées cadeaux, les tenues de soirée et les offres "2 achetés = le 3ème à -50%".
3. Rentrée Scolaire & Universitaire (Septembre - Octobre) et Saint-Valentin (14 Février) :
   - À la Saint-Valentin, créez des coffrets "Pour Lui / Pour Elle" prêts à offrir avec livraison discrète au bureau ou à domicile.
TEXT
            ],

            [
                'category' => 'africa_playbook',
                'title' => 'Playbook : Comment refuser poliment la vente à crédit ("Je paie à la fin du mois") sur WhatsApp',
                'slug' => 'playbook-refuser-credit-whatsapp',
                'keywords' => ['credit', 'fin du mois', 'avance', 'acompte', 'reservation', 'garder', 'payer apres', 'dette'],
                'target_routes' => 'ai.assistant, orders.index',
                'sort_order' => 23,
                'content' => <<<TEXT
La vente à crédit non encadrée est la première cause de faillite des petits commerces en Afrique. Voici comment refuser le crédit sur WhatsApp sans jamais braquer ni perdre le client :
1. Règle absolue : Ne jamais livrer un produit sans encaissement intégral (au départ via Mobile Money ou à la remise en main propre COD).
2. La technique de la "Réservation Privilège avec Acompte" :
   - Au lieu de dire sèchement « Nous ne faisons pas crédit », répondez avec chaleur :
   - « Je comprends tout à fait ! Pour la comptabilité de la boutique, le système ne permet pas de sortir un article sans règlement complet. En revanche, comme il ne reste que quelques pièces en stock, vous pouvez verser une petite avance de réservation de 2 000 ou 3 000 FCFA par T-Money / Wave / MoMo : je vous mets l'article de côté à votre nom et on vous le livre dès que vous recevez votre virement en fin de mois ! »
TEXT
            ],

            [
                'category' => 'navigation_ui',
                'title' => 'Sécurité des comptes, verrouillage anti-force brute et publication de la boutique',
                'slug' => 'guide-securite-publication-boutique',
                'keywords' => ['securite', 'mot de passe', 'verrouillage', 'tentatives', 'publier', 'pause', 'maintenance', 'sous-domaine', 'recu'],
                'target_routes' => 'shop.settings, login',
                'sort_order' => 24,
                'content' => <<<TEXT
Sécurité et cycle de vie d'une boutique sur Zanika :
1. Protection contre les mots de passe erronés (Anti-Brute Force) :
   - Après 5 tentatives de connexion infructueuses sur /connexion, l'accès est temporairement bloqué pendant 60 secondes (Rate Limiting Laravel) afin de protéger le compte marchand contre le piratage.
2. Publication et Mise en Pause de la boutique (/boutique/parametres) :
   - Une nouvelle boutique démarre en mode "Brouillon" (draft). Dès qu'au moins un produit actif avec un prix supérieur à 0 FCFA est prêt, cliquez sur « Publier ma boutique » (/boutique/publier) pour la rendre publique sur votre sous-domaine.
   - Si vous partez en congé ou faites un inventaire, cliquez sur « Mettre en pause » (/boutique/pause) : vos visiteurs verront un écran de maintenance élégant avec votre contact WhatsApp.
3. Reçus publics vérifiables :
   - Chaque commande possède un lien de reçu numérique (/recu/{orderNumber}) partageable sur WhatsApp pour rassurer immédiatement l'acheteur.
TEXT
            ],
        ];

        foreach ($chunks as $chunk) {
            AiKnowledgeChunk::updateOrCreate(
                ['slug' => $chunk['slug']],
                $chunk
            );
        }
    }
}
