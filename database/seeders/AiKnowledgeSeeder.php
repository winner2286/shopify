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
                'keywords' => ['ajouter', 'nouveau', 'produit', 'creer', 'article', 'catalogue', 'stock', 'prix'],
                'target_routes' => 'products.create, products.index',
                'sort_order' => 1,
                'content' => <<<TEXT
Pour ajouter un produit dans votre boutique Zanika :
1. Rendez-vous dans le menu latéral : cliquez sur "Catalogue" puis sur "Produits" (URL : /produits).
2. Cliquez sur le bouton "+ Ajouter un produit" en haut à droite (URL : /produits/create).
3. Renseignez les informations obligatoires :
   - Titre du produit (nom clair et vendeur).
   - Catégorie (à sélectionner parmi vos catégories existantes ou à créer dans /categories).
   - Prix de vente en FCFA (chiffre entier sans virgule).
   - Prix d'origine barré (optionnel, champ "compare_at_price" pour afficher une promotion).
   - Gestion de stock : cochez "Suivre le stock", indiquez le stock initial et le seuil d'alerte de stock faible (ex: 5 unités).
   - Description : description détaillée avec bénéfices du produit.
   - Photos du produit : chargez vos visuels nets (le studio IA peut aussi optimiser vos photos).
4. Cliquez sur "Enregistrer le produit". Le produit devient immédiatement visible sur votre vitrine publique.
TEXT
            ],

            [
                'category' => 'catalog_rules',
                'title' => 'Comment modifier le prix ou le stock d\'un article',
                'slug' => 'guide-modifier-prix-stock',
                'keywords' => ['modifier', 'changer', 'prix', 'stock', 'tarif', 'mettre a jour', 'reajuster'],
                'target_routes' => 'products.edit, products.index',
                'sort_order' => 2,
                'content' => <<<TEXT
Il existe 2 manières de modifier le prix ou le stock d'un produit :
Méthode 1 (Manuelle via l'interface) :
1. Allez dans le menu "Produits" (/produits).
2. Cliquez sur le produit concerné ou sur son bouton "Modifier" (/produits/{id}/edit).
3. Modifiez le champ "Prix" ou "Stock disponible".
4. Cliquez sur "Mettre à jour". Chaque modification de stock enregistre automatiquement un historique traçable dans stock_movements.

Méthode 2 (Directe via l'assistant ECOM AI) :
Demandez simplement dans le chat : « Change le prix du produit [Nom ou ID] à 15 000 FCFA » ou « Ajoute 20 unités au stock de [Nom] ».
L'assistant prépare l'action avec l'outil de mise à jour sécurisé et vous affiche un bouton interactif de validation ("Valider la modification"). Aucune donnée n'est altérée sans votre accord formel en 1 clic.
TEXT
            ],

            [
                'category' => 'catalog_rules',
                'title' => 'Comment savoir si un produit est en rupture de stock ou proche de la rupture',
                'slug' => 'guide-rupture-stock-alertes',
                'keywords' => ['rupture', 'stock', 'epuise', 'seuil', 'alerte', 'critique', 'inventaire'],
                'target_routes' => 'products.index, dashboard',
                'sort_order' => 3,
                'content' => <<<TEXT
Pour identifier les produits en rupture ou faibles en stock :
1. Sur le Tableau de bord (/tableau-de-bord) : l'indicateur "Alertes de stock" liste les articles ayant atteint ou dépassé leur seuil critique.
2. Dans la liste des produits (/produits) :
   - Un badge rouge "Rupture" s'affiche dès que le stock est inférieur ou égal à 0.
   - Un badge orange "Stock faible" s'affiche dès que le stock est inférieur ou égal au seuil défini (low_stock_threshold).
3. Avec l'assistant IA : posez la question « Quels sont mes produits en rupture ? » ou « Donne-moi l'état de mes stocks ». L'assistant appelle immédiatement l'outil get_inventory_alerts et vous présente un récapitulatif clair avec actions d'urgence recommandées.
TEXT
            ],

            [
                'category' => 'catalog_rules',
                'title' => 'Comment créer une réduction ou une promotion sur Zanika',
                'slug' => 'guide-creer-reduction-promo',
                'keywords' => ['reduction', 'promotion', 'solde', 'prix barre', 'remise', 'rabais', 'offre'],
                'target_routes' => 'products.edit, ads.create, ai.marketing',
                'sort_order' => 4,
                'content' => <<<TEXT
Sur la plateforme Zanika, les promotions et réductions s'appliquent via 2 mécanismes officiels :
1. Le Prix Barré sur la fiche produit :
   - Modifiez votre produit (/produits/{id}/edit).
   - Renseignez le champ "Prix d'origine / Prix barré" (compare_at_price) avec une valeur supérieure à votre prix de vente normal (price).
   - Exemple : Prix normal = 12 000 FCFA, Prix d'origine = 15 000 FCFA.
   - La vitrine publique affichera automatiquement le prix d'origine barré et un badge promo de pourcentage d'économie (ex: -20%).

2. Le Studio Marketing & Affiches Promo (/publicite/creer ou /assistant-ia/marketing) :
   - Créez une affiche ou une publication en sélectionnant l'angle psychologique "discount / promotion".
   - Indiquez le pourcentage ou le montant de la remise. L'IA génère les visuels avec macarons promotionnels et le texte de persuasion adapté aux réseaux sociaux.
TEXT
            ],

            // =========================================================================
            // 2. COMMANDES, EXPÉDITIONS & CASH ON DELIVERY (COD)
            // =========================================================================
            [
                'category' => 'orders_cod',
                'title' => 'Comment consulter, gérer et traiter les commandes',
                'slug' => 'guide-consulter-commandes',
                'keywords' => ['commandes', 'consulter', 'statut', 'livraison', 'suivi', 'ventes', 'facture', 'recu'],
                'target_routes' => 'orders.index, orders.show',
                'sort_order' => 5,
                'content' => <<<TEXT
Pour consulter et gérer vos commandes sur Zanika :
1. Allez dans le menu "Commandes" (/commandes).
2. Utilisez les onglets de filtres en haut de page :
   - "En attente" (pending) : nouvelles commandes passées par les clients nécessitant validation.
   - "Confirmées" (confirmed) : commandes validées avec le client.
   - "Expédiées" (shipped) : colis confié au livreur.
   - "Livrées" (delivered) : colis remis et encaissé.
   - "Annulées" (cancelled) : commandes rejetées ou annulées avec motif consigné.
3. Cliquez sur une commande pour ouvrir sa fiche détaillée (/commandes/{id}) :
   - Coordonnées client (nom, numéro de téléphone direct, quartier).
   - Articles commandés et quantités.
   - Frais de livraison et montant total en FCFA.
   - Reçu imprimable / téléchargeable (/commandes/{id}/recu).
4. Actions rapides disponibles sur la fiche :
   - Bouton "Dépêcher au livreur" (génère le bon WhatsApp).
   - Bouton "Marquer comme encaissée" (met à jour le paiement à paid).
   - Bouton "Annuler la commande" (remet les articles en stock automatiquement).
TEXT
            ],

            [
                'category' => 'orders_cod',
                'title' => 'Comment dépêcher une commande à un livreur sur WhatsApp',
                'slug' => 'guide-depecher-livreur-whatsapp',
                'keywords' => ['livreur', 'coursier', 'depecher', 'moto', 'zemidjan', 'whatsapp', 'expedition'],
                'target_routes' => 'orders.show',
                'sort_order' => 6,
                'content' => <<<TEXT
Zanika intègre un système direct d'affectation de livreurs adapté au contexte ouest-africain :
1. Ouvrez la commande confirmée dans "Commandes" (/commandes/{id}).
2. Cliquez sur le bouton "Dépêcher au livreur".
3. L'application génère un lien WhatsApp pré-rempli contenant :
   - L'adresse de livraison et le quartier du client.
   - Le contact téléphonique du destinataire avec lien d'appel direct.
   - Le détail des articles à livrer.
   - Le montant exact à encaisser auprès du client (valeur des articles + frais de livraison).
4. Choisissez le livreur dans vos contacts WhatsApp et envoyez le message en 1 seconde. Le coursier dispose immédiatement de toute la feuille de route sans erreur de transmission.
TEXT
            ],

            [
                'category' => 'orders_cod',
                'title' => 'Comment configurer les zones et tarifs de livraison',
                'slug' => 'guide-zones-livraison',
                'keywords' => ['livraison', 'zones', 'frais', 'expedition', 'tarif', 'quartier', 'ville'],
                'target_routes' => 'shipping.index',
                'sort_order' => 7,
                'content' => <<<TEXT
Pour paramétrer les frais de livraison payés par vos clients lors du passage de commande :
1. Rendez-vous dans le menu "Livraison" (/livraison).
2. Cliquez sur "Ajouter une zone de livraison".
3. Renseignez :
   - Nom de la zone (ex: "Lomé Intra-Muros", "Lomé Périphérie", "Cotonou & Calavi", "Abidjan Zone 1").
   - Tarif forfaitaire de livraison en FCFA (ex: 1 000 FCFA, 1 500 FCFA, 2 500 FCFA).
   - Délai indicatif estimé (ex: "Le jour même en 3h", "24h à 48h").
4. Cliquez sur "Enregistrer".
Lors du passage de commande sur votre vitrine (/b/{sous-domaine}/commander), le client choisit sa zone dans la liste déroulante et les frais s'ajoutent automatiquement au total du panier.
TEXT
            ],

            // =========================================================================
            // 3. PAIEMENTS & MODALITÉS D'ENCAISSEMENT
            // =========================================================================
            [
                'category' => 'payments_feexpay',
                'title' => 'Comment fonctionnent les paiements Mobile Money (T-Money, Flooz) et Cash on Delivery',
                'slug' => 'guide-systeme-paiement',
                'keywords' => ['paiement', 'tmoney', 'flooz', 'feexpay', 'cash', 'especes', 'carte', 'mobile money'],
                'target_routes' => 'shop.settings, storefront.checkout',
                'sort_order' => 8,
                'content' => <<<TEXT
Zanika supporte l'ensemble des modes d'encaissement adaptés au commerce électronique africain :
1. Paiement à la livraison (Cash on Delivery - COD) :
   - Mode privilégié par plus de 75% des consommateurs en Afrique de l'Ouest.
   - Le client commande sans payer en amont ; le paiement en espèces ou transfert direct s'effectue à la réception du colis entre les mains du coursier.
   - Une fois l'argent récupéré du livreur, cliquez sur "Marquer comme encaissée" sur la fiche commande.

2. Mobile Money et Cartes Bancaires (via FeexPay) :
   - Intégration officielle FeexPay supportant : T-Money (Togo), Flooz (Togo / Bénin), MTN MoMo, Orange Money, Moov Money et Cartes Visa/Mastercard.
   - Le client peut régler directement en ligne lors du paiement. Le statut de la commande passe automatiquement à "Payée" dès confirmation de l'opérateur via le webhook sécurisé.
TEXT
            ],

            // =========================================================================
            // 4. PARAMÈTRES, VITRINE & PAGES BOUTIQUE
            // =========================================================================
            [
                'category' => 'navigation_ui',
                'title' => 'Comment personnaliser les paramètres, coordonnées et pages de la boutique',
                'slug' => 'guide-parametres-boutique-pages',
                'keywords' => ['parametres', 'boutique', 'logo', 'couleur', 'telephone', 'pages', 'apropos', 'faq'],
                'target_routes' => 'shop.settings, shop.pages.index',
                'sort_order' => 9,
                'content' => <<<TEXT
Pour configurer votre identité de marque et vos pages de contenu :
1. Menu "Paramètres boutique" (/boutique/parametres) :
   - Nom commercial de la boutique.
   - Sous-domaine officiel (ex: maboutique.zanika.shop).
   - Coordonnées de contact : numéro WhatsApp de service client, email, adresse physique de localisation.
   - Identité visuelle : logo de la marque, couleur principale d'accentuation (HEX).
   - Bouton de mise en pause temporaire de la vitrine ou de republication.
2. Menu "Pages de la boutique" (/boutique/pages) :
   - Personnalisez le texte de vos 4 pages obligatoires :
     * Page "À propos de nous" (/boutique/pages/about/modifier).
     * Page "Livraison & Retours" (/boutique/pages/shipping_policy/modifier).
     * Page "Questions Fréquentes (FAQ)" (/boutique/pages/faq/modifier).
     * Page "Conditions Générales de Vente (CGV)" (/boutique/pages/terms/modifier).
TEXT
            ],

            [
                'category' => 'navigation_ui',
                'title' => 'Quels sont les forfaits d\'abonnement et quotas de la plateforme Zanika',
                'slug' => 'guide-forfaits-abonnements',
                'keywords' => ['forfaits', 'abonnement', 'prix', 'tarifs', 'essentiel', 'pro', 'premium', 'credits ia'],
                'target_routes' => 'subscription.checkout',
                'sort_order' => 10,
                'content' => <<<TEXT
La plateforme Zanika propose 3 formules d'abonnement mensuel adaptées à chaque taille de commerce :
1. Formule "Essentiel" (10 000 FCFA / mois) :
   - Jusqu'à 50 produits au catalogue.
   - 2 Go de stockage médias.
   - 80 crédits IA par mois (générations marketing et assistant).
   - Idéal pour débuter et tester son activité en ligne.
2. Formule "Pro" (25 000 FCFA / mois) :
   - Jusqu'à 200 produits.
   - 5 Go de stockage médias.
   - 250 crédits IA par mois.
   - Codes promos et réductions avancées.
   - Idéal pour les boutiques en forte croissance.
3. Formule "Premium" (50 000 FCFA / mois) :
   - Produits illimités.
   - 20 Go de stockage médias.
   - 600 crédits IA par mois.
   - Support prioritaire VIP et conseiller d'affaires dédié.
TEXT
            ],

            // =========================================================================
            // 5. PLAYBOOK DU COMMERCE ÉLECTRONIQUE EN AFRIQUE DE L'OUEST
            // =========================================================================
            [
                'category' => 'africa_playbook',
                'title' => 'Playbook : Réduire les refus et faux bonds en Cash on Delivery (COD)',
                'slug' => 'playbook-anti-refus-cod',
                'keywords' => ['cod', 'cash on delivery', 'refus', 'annulations', 'livraison', 'faux bond', 'reconfirmation'],
                'target_routes' => 'orders.index, ai.orders.cod_review',
                'sort_order' => 11,
                'content' => <<<TEXT
En Afrique de l'Ouest, le taux d'annulation des commandes en Cash on Delivery peut dépasser 25% si aucun processus rigoureux n'est appliqué.
Voici le protocole d'or Zanika pour maintenir vos annulations sous la barre des 7% :
1. Règle des 30 minutes :
   - Dès qu'une commande COD arrive, contactez le client par appel téléphonique ou message vocal WhatsApp dans les 30 minutes pour reconfirmer.
   - Un message écrit seul est souvent ignoré. La voix crée un engagement moral fort.
2. Script de validation type :
   « Bonjour M./Mme [Nom] ! C'est [Nom Boutique] concernant votre commande de [Article] passée à l'instant. Nous préparons votre colis. Pouvez-vous nous confirmer que vous serez bien à [Quartier/Repère] cet après-midi entre 14h et 17h avec le montant exact de [Montant] FCFA pour le livreur ? »
3. Exiger un repère géographique précis :
   En Afrique de l'Ouest, les adresses postales n'existent pas dans la majorité des quartiers. Demandez systématiquement un repère bien connu (ex: "En face de la pharmacie X", "Carrefour Y derrière l'école Z").
4. Si le client ne décroche pas après 2 tentatives : Ne dépêchez JAMAIS le livreur. Mettez la commande en attente pour éviter de payer les frais de déplacement du coursier pour rien.
TEXT
            ],

            [
                'category' => 'africa_playbook',
                'title' => 'Playbook : Psychologie d\'achat et négociation commerciale sur WhatsApp',
                'slug' => 'playbook-negociation-whatsapp',
                'keywords' => ['whatsapp', 'negociation', 'rabais', 'prix', 'objection', 'vente', 'persuasion'],
                'target_routes' => 'ai.marketing',
                'sort_order' => 12,
                'content' => <<<TEXT
Sur le marché africain, marchander et négocier fait partie intégrante de la culture d'achat. Refuser sèchement une remise fait fuir le prospect.
Voici la méthode de négociation éthique recommandée par Zanika :
1. Ne baissez jamais votre prix sans contrepartie :
   Si vous cédez immédiatement à un rabais, le client se dit que votre produit était trop cher ou de mauvaise qualité.
2. La technique du pack (Bundle) :
   « Le prix unitaire est fixé à 15 000 FCFA pour garantir notre qualité supérieure. Mais si vous en prenez 2 aujourd'hui pour vous et un proche, je vous laisse les deux à 26 000 FCFA (soit 2 000 FCFA d'économie immédiate). »
3. La gratuité des frais de livraison comme geste commercial :
   Offrir la livraison (ex: 1 500 FCFA) coûte souvent bien moins cher à votre marge qu'une réduction de 20% sur le produit, et le client a le sentiment valorisant d'avoir obtenu un privilège.
4. Rassurer par la preuve sociale :
   Envoyez 1 capture d'écran d'un client satisfait du même quartier ou une courte vidéo montrant le produit réel déballé pour éliminer la peur de l'arnaque en ligne.
TEXT
            ],

            [
                'category' => 'africa_playbook',
                'title' => 'Playbook : Diagnostic de rentabilité (« Beaucoup de commandes mais peu de bénéfices »)',
                'slug' => 'playbook-diagnostic-rentabilite',
                'keywords' => ['benefice', 'rentabilite', 'marge', 'perte', 'couts', 'diagnostic', 'chiffre affaires'],
                'target_routes' => 'analytics.index, analytics.advisor',
                'sort_order' => 13,
                'content' => <<<TEXT
Lorsqu'un commerçant constate beaucoup de commandes mais des profits décevants, l'assistant IA effectue un audit méthodique selon ces 4 causes racines :
1. Cause 1 : Frais de livraison réels non répercutés :
   Le commerçant facture 1 000 FCFA au client, mais le livreur prend 1 500 ou 2 000 FCFA pour les zones éloignées. La boutique subit 500 à 1 000 FCFA de perte sèche par colis.
   Solution : Ajuster les zones de livraison dans /livraison ou instaurer un panier minimum pour la livraison subventionnée.
2. Cause 2 : Pertes liées aux annulations COD après expédition :
   Le commerçant paye la course aller-retour du livreur (ex: 1 500 FCFA) pour un colis qui revient non livré. Sur 10 commandes, si 3 sont refusées, la marge des 7 autres est engloutie.
   Solution : Protocole strict de pré-validation téléphonique avant expédition.
3. Cause 3 : Marges brutes trop serrées :
   Calculer la marge unitaire : Marge = Prix de Vente - Coût d'achat - Emballage - Publicité par vente. Si la marge nette unitaire est inférieure à 30%, le commerce est fragile.
4. Cause 4 : Érosion par remises informelles incontrôlées :
   Remplacer les rabais sur le prix unitaire par des offres groupées pour faire grimper le panier moyen.
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
