# Audit technique de Zanika — 17 novembre 2026

> Ce rapport décrit l'état initial avant correction. Voir [les correctifs appliqués et les limites de déploiement](CORRECTIFS.md) pour la version corrigée. Les reproductions historiques ci-dessous ne constituent plus la suite de recette ; utiliser `tests/Feature/AuditSecurityFixTest.php`.

## Décision proposée

La plateforme possède un socle fonctionnel conséquent, mais je déconseille l'ouverture commerciale avec paiements réels avant correction des contrôles d'approbation IA, des droits d'abonnement, des suspensions et de la fiabilité des paiements. Une bêta encadrée reste envisageable après traitement des priorités de sécurité.

Ce jugement repose sur le code présent et des reproductions locales. Il ne prétend pas décrire l'état réel du serveur Render.

La suite existante passe : **147 tests, 1 199 assertions** lors de la relance séquentielle avec `LOG_CHANNEL=null`. Pourtant, **11 tests de reproduction supplémentaires et une simulation JavaScript** démontrent des comportements indésirables. Une suite verte ne constitue donc pas ici une validation de sécurité ou de préparation commerciale.

## Périmètre et méthode

Examen des routes, contrôleurs, modèles, services commerciaux et IA, authentification, médias, vitrine, PWA, migrations, seeders, tests, configurations Composer/Docker/Render et automatisations. Comparaison ciblée avec le cahier des charges Word v2 du 22 octobre 2026. Consultation des sources officielles de sécurité et exécution de `composer audit --locked`.

Environnement constaté : PHP 8.4.27, Node 24.14.1 ; Laravel verrouillé en 13.38.0. Architecture Laravel monolithique avec Blade, JavaScript et CSS publics, services métier et assistant à outils. Groq est le fournisseur par défaut, OpenRouter une alternative/cascade.

**Limites :** pas de pentest du site public, de visite interactive des écrans, d'essai de charge, de paiement réel, de validation des comptes prestataires/KYC ou des reversements, ni d'inspection de l'infrastructure distante. Pas d'audit juridique, comptable ou de conformité réglementaire. Les scénarios de concurrence sont reproduits avec des instances obsolètes ou identifiés par lecture ; aucune campagne de concurrence PostgreSQL n'a été exécutée. Les clés trouvées dans le code n'ont pas été utilisées pour tester leur validité.

Seuls des documents et scripts de reproduction sont ajoutés dans ce dossier. Aucun correctif applicatif ou changement de dépendance n'a été effectué.

### Lecture des priorités

- **P0 :** bloquant avant exposition de la fonctionnalité concernée.
- **P1 :** à corriger avant commercialisation ou encaissements réels.
- **P2 :** fiabilité, coûts, qualité ou écart de spécification à traiter ensuite.

« Reproduit » désigne un scénario exécuté localement. « Lecture » désigne un défaut visible dans le code dont l'effet en production reste à vérifier. Les priorités expriment l'impact pour Zanika, pas un score CVSS.

## Constats prioritaires

### A01 — P0 — L'IA peut modifier les données sans approbation humaine

**Preuve : reproduit.** `ToolRegistry::execute()` transmet directement les arguments du modèle aux outils. Dans `UpdateProductStockOrPriceTool`, `confirmed_by_user=true` suffit à atteindre la mutation ; le jeton HMAC n'est vérifié que s'il n'est pas vide. Un appel au registre sans aucun jeton a ramené un prix de 100 à 1.

Le même schéma conditionnel apparaît notamment dans les outils de création de produits, de modification de prix en pourcentage, de livraison, de promotions et d'annulation de commande. Pour les zones de livraison, il s'agit de `ManageShippingZonesTool` ; le dispatch livreur possède par ailleurs des mutations déclarées sans confirmation.

**Impact :** un appel incorrect du modèle ou une injection de prompt peut contourner le bouton de validation. Le mécanisme cryptographique existe mais son utilisation n'est pas obligatoire.

**Correction :** retirer les paramètres de confirmation des arguments contrôlés par le modèle ; imposer la validation d'un jeton non vide côté serveur pour chaque mutation protégée, dans une voie d'exécution distincte de l'appel LLM. Faire appliquer `requiresConfirmation()` au niveau central, pas seulement dans les outils. Tester jeton absent, invalide, expiré, rejoué et appartenant à une autre boutique.

Sources : `app/Services/Ai/ToolRegistry.php`, `app/Services/Ai/Tools/UpdateProductStockOrPriceTool.php:84`, `:116`, `UpdateOrderStatusTool.php:134`.

### A02 — P1 — Les pages privées sont conservées et restituées hors ligne

**Preuve : simulation exécutée sur le véritable `public/sw.js`.** Le worker met en cache les réponses GET 200 du même domaine, y compris HTML et JSON privés. Le cache n'est pas lié à l'utilisateur et la déconnexion ne le purge pas. La simulation stocke une réponse privée `/commandes/42`, puis récupère exactement cette réponse lorsque le réseau échoue.

**Impact :** persistance des commandes, conversations et données clients dans le profil navigateur ; restitution possible après déconnexion ou changement de compte sur un appareil partagé. La simulation valide le mécanisme, sans prétendre reproduire toutes les interactions d'un navigateur réel.

**Correction :** cache limité à une liste explicite de ressources publiques statiques ; aucun cache de réponse authentifiée, reçu signé ou page de paiement. Purger l'ancien cache déployé, prévoir le nettoyage à la déconnexion et envoyer `Cache-Control: no-store` pour les contenus sensibles. Ce dernier en-tête ne suffit pas à empêcher un `cache.put()` explicite : [référence OWASP](https://cheatsheetseries.owasp.org/cheatsheets/HTML5_Security_Cheat_Sheet.html).

Sources : `public/sw.js:46`, `:98`, `:104`, `app/Http/Controllers/Auth/LoginController.php`, `resources/views/layouts/app.blade.php:38`.

### A03 — P1 — Des valeurs ressemblant à des clés Photoroom sont intégrées au script de déploiement

**Preuve : lecture.** `docker/entrypoint.sh:18-19` contient des valeurs de repli pour les clés Photoroom principale et sandbox. Leur validité et leur historique de diffusion ne sont pas vérifiés ; elles ne sont pas reproduites dans ce rapport.

**Impact :** si ces valeurs sont authentiques, leur présence dans le code ou l'image Docker expose le compte et ses crédits. Une variable manquante active silencieusement une valeur partagée.

**Correction :** supprimer les valeurs de repli, charger les secrets depuis le gestionnaire d'environnement, révoquer/renouveler les clés si elles sont réelles et vérifier l'historique Git ainsi que les images déjà distribuées. Un secret absent doit désactiver explicitement l'intégration.

### A04 — P1 — Un marchand suspendu peut republier sa boutique

**Preuve : reproduit via la route HTTP.** Un propriétaire d'une boutique `suspended` avec un produit valide envoie `POST /boutique/publier`. Le contrôleur applique la checklist puis écrit `published`, sans vérifier la suspension.

**Impact :** la sanction du super-administrateur est contournable par son destinataire.

**Correction :** interdire les transitions depuis `suspended` dans un service central de statut. Seul l'administrateur doit pouvoir lever la suspension ; une checklist de publication ne doit pas donner cette autorisation.

Source : `app/Http/Controllers/ShopSettingsController.php:137-146`.

### A05 — P1 — Un abonnement expiré conserve les droits de son forfait

**Preuve : reproduit.** `Shop::currentPlan()` et `User::currentPlan()` filtrent les statuts `active`, `trial`, `grace` sans vérifier `ends_at` ou `trial_ends_at`. Un abonnement dont l'échéance date du mois précédent conserve son forfait élevé. Aucun mécanisme d'expiration compensatoire n'a été trouvé dans les commandes planifiées examinées.

**Impact :** accès durable aux quotas et fonctionnalités payantes, notamment au nombre de boutiques, après échéance.

**Correction :** service unique d'éligibilité avec dates, période de grâce bornée et droits de repli explicites. Appliquer cette décision à chaque requête ; la tâche d'expiration peut mettre à jour les statuts mais ne doit pas être la seule protection.

Sources : `app/Models/Shop.php:58`, `app/Models/User.php:86`, `routes/console.php`.

### A06 — P1 — Une transaction sans destinataire vérifié peut être acceptée

**Preuve : reproduit avec réponse prestataire simulée.** `verifyApprovedTransaction()` accepte `SUCCESSFUL` et un montant suffisant lorsque la réponse ne contient ni `custom_id` ni `customId`. La devise n'est pas vérifiée. La reproduction appelle cette méthode avec une réponse dépourvue de rattachement à la cible et constate son acceptation.

**Impact :** la validation n'établit pas obligatoirement que le paiement concerne cette commande ou cet abonnement. L'exploitation réelle dépend des métadonnées retournées par les endpoints du prestataire ; elle n'a pas été testée contre FeexPay.

**Correction :** créer côté serveur une intention de paiement persistée, avec cible, montant exact, devise et compte bénéficiaire attendus. Exiger une preuve de correspondance vérifiable ; refuser les métadonnées indispensables absentes. Vérifier les formats réels en sandbox officielle.

Source : `app/Http/Controllers/FeexPayController.php:179-203`.

### A07 — P1 — L'anti-rejeu des paiements d'abonnement dépend d'un cache effaçable

**Preuve : reproduit.** Une même référence prolonge une première fois l'abonnement. Après suppression de sa clé `feexpay_sub_tx`, elle le prolonge de nouveau. La référence n'est pas enregistrée dans un registre de paiements d'abonnement durable. Le rattachement `feexpay_tx_target` dépend lui aussi du cache.

**Impact :** après perte du cache, un événement déjà payé peut être retraité. Le déploiement prévoit un cache fichier sur le conteneur, sans persistance dédiée dans le fichier Render examiné.

**Correction :** registre SQL des transactions prestataires avec contrainte unique, rattachement immuable à une cible et traitement transactionnel. Utiliser le cache comme accélérateur, jamais comme preuve d'encaissement.

Sources : `app/Services/FeexPayService.php:174-191`, `app/Http/Controllers/FeexPayController.php:153`, `render.yaml`.

### A08 — P1 — Les mises à jour de paiement et d'abonnement ne sont pas atomiques

**Preuve : lecture.** `handleOrderPaymentSuccess()` écrit successivement commande, paiement et historique sans transaction ni verrou SQL de la commande. `handleSubscriptionPaymentSuccess()` consomme sa clé de cache avant de mettre à jour l'abonnement.

**Impact :** échec partiel laissant une commande payée sans paiement associé à jour, ou cache marquant une référence comme traitée alors que l'activation a échoué. Deux événements concurrents peuvent aussi passer la vérification sur deux instances obsolètes.

**Correction :** transaction SQL, verrou du paiement/de la cible, état durable de traitement et reprise idempotente. Tester callback puis webhook, doublons simultanés et panne entre deux écritures.

Source : `app/Services/FeexPayService.php:113-210`.

### A09 — P1 — Le choix de devise ne pilote pas l'encaissement

**Preuve : reproduit.** Une boutique configurée en MAD crée un enregistrement de paiement en XOF. Les SDK des pages de paiement fixent aussi `currency: 'XOF'`. Les prix, totaux et affichages restent largement conçus comme des entiers FCFA ; aucune conversion monétaire ne doit être supposée.

**Impact :** divergence entre prix affiché, unité comptable et unité encaissée pour les boutiques hors XOF. Par exemple, une valeur numérique de 100 en MAD ne peut pas être transmise comme 100 XOF.

**Correction :** soit limiter clairement la V1 à XOF, soit stocker la devise de chaque commande et traiter les unités monétaires de bout en bout, avec vérification des devises effectivement supportées par le prestataire. Ne pas changer seulement le symbole visuel.

Sources : `app/Services/OrderService.php:209`, `resources/views/storefront/order_confirmation.blade.php:138`, `app/Models/Shop.php:358`, `resources/views/subscription/checkout.blade.php:149`.

### A10 — P1 — Une annulation peut restituer le stock deux fois

**Preuve : reproduit avec deux instances de la même commande chargées avant l'annulation.** La première annulation remet le stock de 9 à 10. La seconde, sur une instance obsolète encore `pending`, le porte à 11. `canBeCancelled()` est évalué avant la transaction, sans relecture verrouillée.

**Impact :** stock artificiellement augmenté, historique contradictoire, risque de survente.

**Correction :** recharger et verrouiller la commande dans la transaction, vérifier son statut après verrouillage et enregistrer une restitution unique. Garder un ordre de verrouillage cohérent pour les produits.

Source : `app/Services/OrderService.php:252-295`.

### A11 — P1 — La route marchande autorise des transitions de commande invalides

**Preuve : reproduit.** Une commande annulée passe à `delivered` via `orders.update_status`. Le contrôleur valide seulement que le statut cible appartient à une liste ; `Order::recordStatusChange()` l'écrit sans vérifier la transition. Le chemin IA possède des vérifications différentes.

**Impact :** commandes annulées réactivées sans rétablir les mouvements de stock, indicateurs et états de paiement incohérents.

**Correction :** machine à états métier unique pour toutes les entrées, transactionnelle, avec règles explicites pour chaque statut et synchronisation des paiements COD. Tester chaque couple état source/état cible.

Sources : `app/Http/Controllers/OrderController.php:286`, `app/Models/Order.php:131`, `app/Services/Ai/Tools/UpdateOrderStatusTool.php`.

### A12 — P1 — La pause de boutique n'interdit pas la commande directe

**Preuve : reproduit en visiteur.** Avec un panier existant, `POST /b/{subdomain}/commander` crée une commande pour une boutique `paused`. Le résolveur du checkout interdit seulement `suspended`, alors que la vitrine distingue aussi `draft` et `paused`.

**Impact :** une boutique présentée comme fermée continue à recevoir des commandes ; le même défaut de contrôle concerne les brouillons.

**Correction :** politique partagée d'accès public et de prise de commande. Interdire le checkout hors `published`, même si un panier a été constitué avant la pause. Distinguer l'aperçu propriétaire de la commande réelle.

Sources : `app/Http/Controllers/CheckoutController.php:21-35`, `app/Http/Controllers/StorefrontController.php:26-46`.

### A13 — P1 — Un lien confidentiel de reçu est transmis à un service QR externe

**Preuve : lecture.** Le reçu construit une URL `api.qrserver.com` dont le paramètre `data` contient `publicReceiptUrl()`, donc le jeton autorisant l'accès au reçu. Ce jeton HMAC est déterministe et n'expire pas, malgré le message d'erreur évoquant un lien expiré.

**Impact :** le service QR reçoit un lien porteur d'autorisation vers les données client. Ce n'est pas une fuite constatée chez ce prestataire, mais une transmission explicite visible dans le template.

**Correction :** générer les QR localement ou côté serveur sans transmettre le lien à un tiers ; choisir et documenter une politique d'expiration/révocation des reçus. Ajouter la vérification des dépendances externes du reçu.

Sources : `resources/views/orders/receipt.blade.php:722`, `app/Models/Order.php:167-189`, `app/Http/Controllers/OrderController.php:250`.

### A14 — P1 — La génération publicitaire contrôle les crédits sans les débiter

**Preuve : lecture de la chaîne complète.** `AdvertisingController::store()` vérifie un crédit restant, mais `AiAdvertisingService::generate()` et `VideoAdGeneratorService::generateVideoCampaign()` ne débitent pas les crédits IA généraux. Les crédits Photoroom sont gérés séparément. `regenerate()` ne répète même pas les contrôles de quota et de forfait et transmet `$request->all()` au service.

**Impact :** générations répétées alors que le forfait est censé les limiter, consommation externe non maîtrisée ; restrictions contournables par les chemins de régénération.

**Correction :** appliquer la tarification et les autorisations dans un service commun à création/régénération, valider les surcharges et réserver les crédits atomiquement avant l'appel externe, avec remboursement contrôlé en cas d'échec.

Sources : `app/Http/Controllers/AdvertisingController.php:120-160`, `app/Services/AiAdvertisingService.php`, `app/Services/VideoAdGeneratorService.php:400`.

### A15 — P1 — Des dépendances verrouillées font l'objet d'avis de sécurité

**Preuve : `composer audit --locked --format=json` exécuté le 17 novembre 2026, sortie 1.** Huit avis sur cinq paquets, aucun paquet abandonné signalé.

| Paquet verrouillé | Version | Avis détectés | Version corrective indiquée dans la branche concernée |
| --- | --- | --- | --- |
| laravel/framework | 13.38.0 | 1 | 13.40.0 |
| symfony/http-foundation | 8.1.9 | 4 | 8.1.11 |
| symfony/http-kernel | 8.1.9 | 1 | 8.1.11 |
| symfony/mime | 8.1.7 | 1 | 8.1.10 |
| symfony/routing | 8.1.9 | 1 | 8.1.11 |

Identifiants retournés : `PKSA-yxbx-3c9s-cjt9`, `PKSA-nxjs-s6hn-vb6m`, `PKSA-24ph-xkrh-y79j`, `PKSA-pr6q-2w5s-kzzf`, `PKSA-b2gg-vk1g-6d5q`, `PKSA-3gxv-7xhq-mgmz`, `PKSA-wtxr-p26d-nn42`, `PKSA-y8c5-1b7f-r1gj`.

**Nuance :** présence d'une version concernée ne démontre pas l'exploitabilité de tous les avis dans Zanika. Par exemple, un avis concerne le raccourci `private_ranges`, alors que l'application configure actuellement `trustProxies('*')`. La topologie de proxy et les règles réellement déployées doivent être étudiées séparément.

**Correction :** mise à jour ciblée avec résolution Composer compatible, validation des exigences PHP et réexécution des tests et de l'audit. Ne pas modifier seulement les numéros dans le lock à la main. Sources : [avis Symfony sur les proxies](https://github.com/symfony/symfony/security/advisories/GHSA-xg2q-4rrq-657q), [correctif MIME](https://symfony.com/cve-2026-45070), [avis Laravel signalé par Composer](https://github.com/advisories/GHSA-63hm-g96r-2m2q).

## Fiabilité, coûts et exploitation

### A16 — P2 — Le checkout conserve un ancien prix sans politique explicite de validité

**Preuve : reproduit.** Ajouter au panier à 100, modifier le catalogue à 900, puis commander donne une ligne à 100. Les produits sont verrouillés et leur stock vérifié, mais le sous-total et les lignes utilisent les prix enregistrés en session.

**Impact :** perte de maîtrise des prix après augmentation et absence de durée de validité d'une offre de panier. Cela pourrait être un choix commercial, mais aucune politique explicite ne l'encadre.

**Correction :** recalculer les prix depuis les produits verrouillés et présenter les changements avant validation, ou créer un véritable devis de panier avec échéance. Source : `app/Services/CartService.php`, `app/Services/OrderService.php:80`, `:172-178`.

### A17 — P2 — Les quotas ne sont pas réservés atomiquement et des échecs sont masqués

**Preuve : lecture.** Les chemins IA contrôlent les crédits puis appellent le fournisseur avant de les incrémenter. Plusieurs requêtes peuvent partir avec le même solde. `safeIncrementCreditColumn()` peut tenter de modifier le schéma SQL pendant une requête et journalise finalement l'échec sans le propager.

**Impact :** dépassement sous concurrence et service externe fourni sans débit comptable fiable. Les limites de produits et de boutiques utilisent aussi des contrôles « compter puis créer » séparés.

**Correction :** réservation SQL conditionnelle ou registre de crédits, transaction et mécanisme de compensation ; aucune DDL de secours dans une requête métier. Sources : `app/Models/Shop.php:160-185`, `app/Services/Ai/AiOrchestrator.php:55`, `app/Http/Controllers/AiProductController.php:61-103`, `ShopController.php:49`.

### A18 — P2 — L'onboarding peut être rejoué et contourne ses limites cumulées

**Preuve : duplication reproduite ; limites constatées par lecture.** Deux exécutions successives de `generateShop()` avec les mêmes données créent deux nouveaux produits modèles chacune. Aucun contrôle de `full_generations` n'encadre `runGeneration()`. Les photos sont limitées à 20 par requête, puis ajoutées à la liste déjà enregistrée sans plafond cumulé. Les créations de produits du générateur ne passent pas par `canAddProduct()`.

**Impact :** doublons, consommation et catalogue gonflés, quotas contournés, dégradation de la boutique lors d'un double clic ou d'une relance.

**Correction :** opération de génération identifiée et idempotente, garde sur l'état d'onboarding, contrôle cumulé des photos et application des quotas dans le service. Sources : `app/Http/Controllers/OnboardingController.php:139-193`, `app/Services/AiGenerationService.php:18`, `:208`.

### A19 — P2 — Un fichier image invalide est écrit dans le stockage public avant son rejet

**Preuve : reproduit.** Envoyer un fichier `invalid.jpg` contenant du HTML renvoie 422, mais le contenu reste dans `shops/{id}/products/ai_draft_*.jpg`. Le chemin base64 suit aussi l'ordre écriture puis validation, sans limite applicative de longueur de chaîne.

**Impact :** persistance de fichiers refusés, déchets et espace disque consommé. Leur interprétation comme contenu actif dépend du mode de distribution et du type MIME ; aucune exécution XSS n'est revendiquée dans cet audit.

**Correction :** valider taille décodée, dimensions, format et contenu avant écriture ; réencoder les images ; stocker les brouillons temporairement avec expiration et nettoyage. Source : `app/Http/Controllers/AiProductController.php:49-96`.

### A20 — P2 — Les limites de stockage et le nettoyage des médias ne sont pas appliqués

**Preuve : lecture/recherche.** `storage_mb` est configurable et affiché mais aucun contrôle de volume n'a été trouvé dans les chemins d'upload. Les brouillons IA ne disposent pas d'un nettoyage identifié. L'endpoint public de restitution génère et écrit des images pour des chemins manquants sans authentification ni limitation de débit dans sa route.

**Impact :** croissance non bornée du stockage et de certaines données base64 ; possibilité d'amplifier les écritures/traitements par requêtes publiques sur des chemins distincts.

**Correction :** mesurer les octets par boutique, contrôler avant upload, nettoyer les brouillons et limiter la restitution aux médias connus avec noms/extensions autorisés. Source : `app/Http/Controllers/MediaController.php:63-151`, `routes/web.php` (route `media.serve`), `app/Models/Plan.php`.

### A21 — P1 — Le déploiement peut déclarer le service prêt malgré une migration échouée

**Preuve : lecture.** L'entrypoint exécute migrations, seeders et tests de configuration avec `|| true`. Le Dockerfile masque aussi un échec initial de `composer install`. Un échec de migration n'empêche donc pas nécessairement Nginx de démarrer.

**Impact :** application partiellement migrée exposée au trafic, avec erreurs et réparations de fortune pendant les requêtes.

**Correction :** étapes de build et de release bloquantes, migration dans une phase de déploiement contrôlée, vérification de disponibilité métier et plan de retour arrière. Sources : `Dockerfile:50`, `docker/entrypoint.sh:80-95`, `:113`.

### A22 — P2 — Les déploiements peuvent écraser la configuration commerciale ; le web peut lancer des migrations

**Preuve : lecture.** `PlanSeeder` utilise `updateOrCreate` et est exécuté à chaque démarrage du service web. Des tarifs et quotas modifiés dans l'administration peuvent être remplacés par les constantes du seeder. `HomeController` peut lancer `migrate --force` et ce même seeder depuis une requête publique quand certains prix sont absents/invalides.

**Impact :** réglages administrateur perdus, opérations DDL déclenchées par le trafic, droits SQL trop larges pour le processus web.

**Correction :** données initiales créées une seule fois, mises à jour commerciales explicites et versionnées, migrations réservées au déploiement. Sources : `database/seeders/PlanSeeder.php:79`, `docker/entrypoint.sh:83`, `app/Http/Controllers/HomeController.php:20-35`.

### A23 — P1 — L'exécution réelle des tâches planifiées n'est pas configurée dans le déploiement fourni

**Preuve : lecture.** `routes/console.php` définit rappels, automatisations horaires et remise à zéro des crédits IA. Le Dockerfile, l'entrypoint et le fichier Render examinés ne lancent ni cron `schedule:run` ni `schedule:work`. Le workflow GitHub ne fait que des pings. La commande `ai:morning-briefing` n'a pas de planification dédiée trouvée, malgré la documentation évoquant 07h00.

**Impact :** rappels, automatisations et remise à zéro des quotas peuvent ne jamais s'exécuter. Une configuration distante hors dépôt pourrait compenser : elle reste à vérifier. Le reset mensuel présent ne remet à zéro que `ai_credits_used`, pas `photoroom_credits_used` ; ces derniers sont remis à zéro lors d'un renouvellement.

**Correction :** ordonnanceur externe ou service dédié, surveillance de dernière exécution, fuseau horaire explicite et définition d'un cycle unique de facturation/crédits. Sources : `routes/console.php:281-314`, `render.yaml`, `.github/workflows/keep-alive.yml`.

### A24 — P1 — La persistance des médias est incomplète dans l'architecture de déploiement

**Preuve : lecture.** Aucun disque persistant ni stockage objet n'est configuré dans le fichier Render fourni. Les fichiers sont locaux. La sauvegarde `image_data` privilégie la miniature, réutilisée pour restaurer le chemin principal ; elle ne conserve donc pas nécessairement la pleine résolution. Les logos personnalisés sont enregistrés par chemin sans copie binaire équivalente dans le modèle boutique.

**Impact :** après perte du disque local : logos absents, images principales dégradées, visuels/audio publicitaires potentiellement manquants. Les photos de catalogue de secours peuvent masquer la perte en affichant une image qui n'est pas celle du produit.

**Correction :** stockage objet durable pour les originaux et rendus, politique de sauvegarde et restauration testée. Le fallback ne doit pas substituer durablement une photo différente à un produit client. Sources : `app/Services/ImageService.php:175-177`, `app/Http/Controllers/MediaController.php:93-140`, `app/Http/Controllers/OnboardingController.php:255-266`, `render.yaml`.

### A25 — P2 — Les journaux IA mélangent mesures et valeurs prédéfinies

**Preuve : lecture.** La génération de boutique journalise systématiquement `gemini-2.5-flash`, 850 tokens d'entrée et 620 de sortie, alors que le profil, les thèmes, les catégories et textes sont majoritairement construits localement. La publicité enregistre elle aussi des tokens, un coût et une durée fixes. Les produits générés ont une confiance fixée à 92.

**Impact :** coût par boutique et marge SaaS non mesurables de manière fiable ; présentation d'un score de confiance qui n'est pas une évaluation réelle. Cela n'implique pas l'absence d'IA dans le studio photo ou les autres services.

**Correction :** provenance réelle de chaque résultat (`template`, fournisseur appelé, fallback local), usage mesuré par appel, coût calculé et durée chronométrée ; score absent si aucune méthode de calibration n'existe. Sources : `app/Services/AiGenerationService.php:68-73`, `:231`, `:261`, `app/Services/AiAdvertisingService.php:101-107`.

### A26 — P2 — Les indicateurs commerciaux et les engagements V1 restent ambigus

**Preuve : lecture et comparaison ciblée au cahier des charges.**

- `totalRevenue` additionne les commandes non annulées, même impayées ; le panier moyen utilise le nombre total incluant les annulées. Il faut distinguer volume commandé, livré et encaissé, et utiliser des périmètres cohérents.
- Le lien boutique est `/b/{subdomain}`, pas `maboutique.domaine.com` prévu dans le cahier des charges. Aucun routage par domaine de boutique n'est fourni.
- La V1 du document prévoit français/anglais. Les vues examinées sont rédigées en français et aucune chaîne de traduction anglaise complète n'a été trouvée ; le seul fichier sous `lang/` est la pagination française.
- Les deux layouts principaux imposent `maximum-scale=1.0, user-scalable=no`, ce qui demande de désactiver le zoom et nuit à l'accessibilité selon le navigateur.
- Le README reste celui du squelette Laravel. La documentation IA contient des descriptions de protections qui ne correspondent pas à leur caractère obligatoire réel. Aucun workflow de tests n'a été trouvé, seulement le keep-alive. Les contrôles de sécurité et le déploiement ne sont donc pas vérifiés automatiquement dans le workflow fourni.

**Correction :** définir les métriques, aligner les promesses V1 et le comportement réel, autoriser le zoom, compléter les traductions décidées, documenter installation/exploitation et ajouter une CI bloquante. Sources : `DashboardController.php:30`, `AnalyticsController.php:29-30`, `Shop.php:439`, `resources/views/layouts/app.blade.php:5`, `resources/views/storefront/layout.blade.php:5`, `README.md`, `.github/workflows/`.

## Points de vigilance supplémentaires à instruire

Ces éléments demandent une décision produit ou une validation d'environnement avant de les qualifier davantage :

- Le SDK navigateur reçoit la valeur configurée comme token FeexPay. Vérifier sa nature et ses permissions auprès du prestataire : ne pas supposer qu'un token accepté dans une intégration JavaScript possède les droits restreints d'une clé publique.
- L'intégration utilise un compte prestataire global. Confirmer le parcours contractuel de reversement aux marchands, le rapprochement et la séparation des fonds ; aucun audit des comptes ou des contrats n'a été réalisé.
- `trustProxies('*')`, la confiance dans `X-Forwarded-Proto` et l'absence de filtrage explicite des hôtes doivent être évalués avec la topologie Render réelle. Ne pas déduire une exploitation du seul paramétrage.
- Les numéros de commande sont construits par comptage puis contrôle d'existence, avec contrainte SQL unique mais sans réservation/retry identifié. Deux commandes simultanées peuvent entrer en collision ; à tester sur PostgreSQL.
- `expires_at` est enregistré pour les paiements sans traitement automatique d'expiration retrouvé. Définir les réservations de stock et les effets d'un abandon de paiement. Les commandes publiques peuvent immobiliser du stock avant encaissement ; le throttle IP seul ne résout pas les commandes abusives.
- Le login compte les échecs par lecture puis écriture et distingue les comptes verrouillés. Vérifier les tentatives concurrentes, les protections anti-énumération et la résilience à une panne email. Les codes OTP disposent de limites, mais leur consommation concurrente n'a pas été testée.
- Certaines exceptions sont renvoyées telles quelles aux utilisateurs ; filtrer les messages SQL/API et conserver les détails dans la journalisation interne.
- Les URL `maps_url` sont validées comme chaînes plutôt qu'URL HTTP(S). Corriger cette validation et tester les protocoles actifs sans conclure ici à une XSS démontrée.
- Les appels IA/photos sont synchrones et peuvent s'accumuler dans des transactions longues. Mesurer temps de réponse, mémoire GD, latence réseau et occupation des workers sous charge avant dimensionnement.
- Les créations de produit et le stockage de ses images ne constituent pas une opération atomique de bout en bout. Prévoir état intermédiaire et reprise si le traitement photo échoue.

## Protections présentes à conserver

- Mots de passe hachés, validation email par code à durée limitée et tentatives bornées, verrouillage après échecs, renouvellement de session au login, suppression des sessions après réinitialisation.
- CSRF dans les formulaires et exclusions explicites pour les callbacks ; simulations de paiement limitées à `local/testing`.
- Contrôles explicites `shop_id` dans les principaux parcours, scope boutique pour les marchands connectés et vérification d'appartenance des fichiers IA. Cela ne remplace pas la vérification des nouveaux endpoints.
- Verrouillage des produits et du code promotionnel à la création de commande, transaction de création, historique et mouvements de stock.
- Vérification serveur du statut des transactions plutôt que confiance dans le seul paramètre de callback.
- Encodage des textes Blade, rendu Markdown avec HTML retiré et liens dangereux interdits dans le rendu serveur du chat ; contrôle des couleurs/initiales des logos SVG générés.
- En-têtes de sécurité de base et suite de tests fonctionnels couvrant l'authentification, le catalogue, les commandes, l'onboarding, l'IA et l'administration.

Le scope boutique ne restreint pas les requêtes lorsque personne n'est authentifié : chaque endpoint public doit donc continuer à filtrer lui-même les données autorisées. Aucun accès transversal arbitraire aux commandes d'un autre marchand n'est démontré par cet audit.

## Résultats des vérifications

| Contrôle | Résultat |
| --- | --- |
| `composer validate --no-check-publish` | Valide |
| `composer check-platform-reqs --no-dev` | Exigences déclarées satisfaites dans le PHP local |
| Suite existante, relance séquentielle avec `LOG_CHANNEL=null` | 147/147, 1 199 assertions, environ 82 s |
| Tests de reproduction isolés | 11/11, 20 assertions, environ 2 s |
| Simulation du service worker réel | Réponse privée mise en cache et rendue hors ligne |
| `composer audit --locked --format=json` | 8 avis, 5 paquets ; sortie 1 |
| Test de charge / PostgreSQL concurrent / navigateur réel | Non exécutés |
| Build Docker / paiement réel / comptes externes | Non exécutés |

Les premiers essais globaux ont été lancés avec chevauchement et ont rencontré des erreurs d'écriture `No space left on device` ainsi que des échecs sur les images/publicités. Les stockages factices partagés peuvent aussi perturber des exécutions simultanées ; la cause exacte de tous ces premiers échecs n'est pas établie. Ils ne sont pas utilisés comme preuve d'une régression métier. La relance globale, seule et sans journalisation fichier, est verte. La capacité d'écriture de l'environnement reste donc à contrôler séparément.

**Incident d'environnement confirmé en fin d'audit :** le lancement d'une dernière commande de vérification a été refusé avant création du processus avec `Espace insuffisant sur le disque (os error 112)`, pendant l'écriture du journal de l'outil. Cela confirme une limite de capacité d'écriture, sans en établir le volume responsable. Aucun fichier utilisateur n'a été supprimé. Les résultats des exécutions terminées précédemment restent ceux indiqués ci-dessus ; la dernière vérification Git/syntaxe n'a pas pu démarrer.

### Vérifications après libération d'espace

À la demande du porteur du projet, les contrôles ont été repris après libération d'espace disque :

- La suite existante passe **147/147 tests, 1 199 assertions**, en environ **57 secondes**, sans forcer `LOG_CHANNEL=null` cette fois. Aucune erreur d'espace disque n'a été rapportée pendant cette exécution.
- Les reproductions d'audit passent **11/11, 20 assertions** : elles confirment toujours les défauts décrits, qui n'ont pas été corrigés.
- La simulation du service worker confirme à nouveau la mise en cache d'une réponse privée et sa restitution hors ligne.
- Vérification de syntaxe de **193 fichiers PHP** dans `app`, `config`, `routes`, `database` et `tests`, ainsi que du fichier PHP de reproduction : aucune erreur.
- Vérification de syntaxe des **7 fichiers JavaScript** de `public/js` et `resources/js` : aucune erreur.
- Le rapport contient bien les **26 constats** et sa fin est complète. Git ne signale que le nouveau dossier documentaire `docs/audit-2026-11-17/` ; aucun fichier applicatif suivi n'a été modifié.

Le blocage d'écriture n'a donc pas réapparu dans ces contrôles. Le volume libre exact n'a pas été obtenu par l'outil utilisé ; aucune estimation de capacité restante n'est avancée. Les limites de périmètre initiales (serveur distant, navigateur réel, paiements réels et charge) restent valables.

### Commandes pour rejouer les preuves

Depuis la racine `plateforme`, avec les dépendances déjà installées :

```powershell
$env:LOG_CHANNEL = 'null'
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
php vendor/phpunit/phpunit/phpunit --do-not-cache-result docs/audit-2026-11-17/AuditVerificationTest.php
node docs/audit-2026-11-17/verify-service-worker.mjs
composer audit --locked --format=json
```

Les 11 tests ajoutés sont **des preuves du comportement actuel** : leur réussite confirme les défauts, pas la sécurité. Après correction, les transformer en tests exigeant le comportement sûr. Ils sont hors du dossier `tests/` et ne sont pas ajoutés à la suite habituelle. Ils utilisent SQLite en mémoire, stockage factice et services externes neutralisés. La simulation JavaScript ne lance ni navigateur ni requête réseau.

## Ordre de correction proposé

1. **Sécurité immédiate :** rendre le jeton d'approbation obligatoire (A01), exclure les données privées du cache et purger l'ancien (A02), traiter les secrets (A03), verrouiller les suspensions (A04), cesser la transmission des reçus signés au service QR (A13).
2. **Encaissements et contrats SaaS :** expiration des droits (A05), intentions de paiement et registre SQL idempotent/transactionnel (A06–A08), devise cohérente (A09), quotas publicitaires et dépendances (A14–A15).
3. **Intégrité commerciale :** annulation unique, transitions d'état et fermeture du checkout (A10–A12), politique de prix du panier et réservation des crédits (A16–A17).
4. **Exploitation :** génération idempotente, uploads validés, stockage durable, releases bloquantes et ordonnanceur surveillé (A18–A24).
5. **Mesure et recette :** provenance/coûts réels, métriques commerciales, accessibilité, traduction, CI et documentation (A25–A26).

### Conditions minimales avant lancement commercial

- Les reproductions de sécurité sont converties en tests du comportement sûr et passent.
- Suite complète verte, sans masquer les erreurs de stockage ; scénarios de concurrence validés sur PostgreSQL.
- Paiements sandbox bout en bout : montant/devise/cible incorrects, doublons, replay après redémarrage, pannes partielles et abonnement expiré.
- Vérification réelle de persistance des images/logos, sauvegarde et restauration, ordonnanceur et alertes de panne.
- Recette mobile et navigateur : déconnexion/changement de compte, mode hors ligne, création/publication, achat et suivi de commande.
- Procédure de rotation de secrets et de mise à jour des dépendances, comptes prestataires et reversements validés par le responsable du projet.

La priorité est de consolider les contrôles centraux plutôt que d'ajouter des modules : états de commande/boutique, droits SaaS, paiements et quotas doivent chacun avoir une seule implémentation métier réutilisée par le web, l'IA et les tâches planifiées.
