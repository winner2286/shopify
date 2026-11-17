# Correctifs de l'audit Zanika

Cette livraison corrige le code local audité le 17 novembre 2026. Le rapport initial reste un état historique avant correction. Aucun déploiement distant, encaissement réel ou changement de compte prestataire n'a été effectué.

## Changements

| Audit | Traitement livré |
| --- | --- |
| A01 | Arguments d'approbation retirés du schéma LLM et ignorés dans la voie d'exécution du modèle. Jeton non vide obligatoire dans les outils protégés. Toutes les transitions IA de commande demandent une approbation ; produire une fiche livreur ne modifie plus son statut. |
| A02 | Worker version 2 limité aux ressources publiques statiques ; aucune interception des pages/JSON privés ni des URL signées. Suppression des anciens caches Zanika à l'activation et en-têtes `private, no-store` sur les réponses sensibles. |
| A03 | Suppression des clés Photoroom de repli du script Docker. Révocation des anciennes clés réelles à effectuer dans le compte externe. |
| A04 | Une boutique suspendue ne peut pas être republiée par son marchand. |
| A05 | Les droits vérifient dates de début, échéances et fin d'essai. Une tâche actualise aussi les statuts expirés ; l'autorisation ne dépend pas de son passage. Les anciens abonnements actifs sans échéance restent manuellement gérés. |
| A06 | Vérification obligatoire de la cible, du montant exact, de la devise et du compte bénéficiaire dans la réponse serveur. Référence limitée à un format sûr. Un champ manquant entraîne un refus. |
| A07–A08 | Registre SQL `payment_transactions`, référence unique, transaction et verrous sur la cible. Un échec d'activation annule aussi l'écriture du registre. Le cache n'est plus la preuve d'un encaissement. |
| A09 | Devise enregistrée sur la commande et son paiement, reçus/confirmations/messages avec cette devise. La V1 refuse l'encaissement en ligne hors XOF et conserve les règlements directs/COD dans les autres devises. Pas de conversion implicite. |
| A10 | L'annulation relit la commande sous verrou avant toute restitution du stock. |
| A11 | Transitions de commande contrôlées sous verrou dans le modèle, historique cohérent et règlement COD synchronisé à la livraison. L'annulation utilise son service de restitution. |
| A12 | Le checkout et le service de commande refusent une boutique non publiée. |
| A13 | QR du reçu généré localement avec BaconQrCode, sans appel au service QR externe. |
| A14 | Crédit réservé pour création/régénération publicitaire et contrôles de canal appliqués dans le service commun. Surcharges de régénération validées. |
| A15 | Laravel 13.42.0 et correctifs Symfony installés ; audit Composer sans avis au contrôle final. |
| A16 | Un prix devenu obsolète dans le panier bloque la commande et demande de confirmer le nouveau prix, sans vente à l'ancien montant. |
| A17 | Débits conditionnels atomiques ; réservation avant les principaux appels IA/Photoroom, restitution en cas d'échec ou de fallback non facturable. Plus de modification de schéma depuis le modèle boutique ; une colonne de quota manquante désactive Photoroom au profit du moteur local. |
| A18 | Génération de boutique idempotente pour les mêmes données, quota de générations complètes et de produits, plafond cumulé de 20 photos. |
| A19 | Validation du contenu/dimensions/taille avant stockage de la photo analysée. Un fichier refusé n'est plus conservé. |
| A20 | Écritures de médias regroupées dans `PublicMediaStorage`, quota vérifié sous verrou, sauvegardes comptées même après perte du disque. Nettoyage quotidien des brouillons IA non rattachés de plus de 24 heures. |
| A21 | Installation de dépendances, migrations, caches et configuration Nginx bloquent la livraison en cas d'échec. Une `APP_KEY` de production absente ne provoque plus une génération silencieuse. |
| A22 | Suppression des migrations/seeders depuis l'accueil ; création des formules manquantes sans écraser les réglages administrateur. |
| A23 | `schedule:work` intégré au conteneur, briefing 07h00, rappels, automatisations, expiration et entretien planifiés. Reset mensuel des deux types de crédits. Le service doit rester actif pour exécuter ses horaires. |
| A24 | Sauvegarde SQL des nouveaux médias, logos et pleine résolution ; restauration des médias connus, sans création de fichiers pour un chemin inconnu ni substitution par une photo de catalogue. Commande de sauvegarde des anciens médias encore présents. |
| A25 | Le moteur local est identifié comme tel, sans tokens fictifs ni confiance à 92. Journal publicitaire avec source réelle, usage retourné et durée mesurée ; les coûts indisponibles sont signalés et ne signifient pas gratuité. Les coûts du chat restent une estimation indicative. |
| A26 | Montant encaissé et panier moyen sur les commandes payées non annulées ; GMV administrateur séparé par devise. Zoom autorisé, promesses d'adresse alignées sur `/b/…`, README d'exploitation et CI ajoutés. V1 française documentée ; pas de prétention à une traduction complète ou à des sous-domaines DNS livrés. |

Durcissement complémentaire : hôtes de production limités à `APP_URL` et aux alias explicitement autorisés, proxies limités aux réseaux déclarés, suppression de la confiance manuelle dans `X-Forwarded-Proto`, vérification TLS des appels cURL de détourage réactivée. La clé serveur de paiement n'est plus envoyée dans le HTML ; une clé SDK explicitement publiable est distincte.

## Base locale et données

La connexion active a été vérifiée : SQLite dans `plateforme/database/database.sqlite`. Une sauvegarde SQLite cohérente a été créée avant migration : `database/database.sqlite.before-audit-20261117` (ignorée par Git).

Trois migrations sont appliquées localement : registre des transactions, devise de commande/sauvegarde logo, table `public_media`. Les effectifs utilisateurs, boutiques, produits et commandes restent identiques à la sauvegarde. La commande `media:backup-existing` a sauvegardé 35 fichiers référencés, avec 0 fichier absent et 0 échec. Aucun fichier source utilisateur n'a été supprimé.

La copie SQL est un mécanisme de secours pour une petite installation : elle augmente la taille de la base et ne remplace ni les sauvegardes de PostgreSQL/SQLite ni un stockage objet à plus grande échelle. Les originaux déjà absents avant sauvegarde ne peuvent pas être reconstruits. Les anciennes pistes audio rangées sous `ads/audio` doivent être contrôlées avant destruction de l'ancien disque ; les nouvelles pistes sont rangées par boutique et sauvegardées.

## Validation

La vérification finale locale passe : **176 tests, 1 291 assertions**, dont 29 cas ajoutés dans `AuditSecurityFixTest` sur les limites de sécurité et l'intégrité commerciale. Les 200 fichiers PHP contrôlés passent l'analyse syntaxique. La simulation du service worker confirme l'absence de cache et de restitution hors ligne des réponses privées, ainsi que l'absence d'interception des ressources signées. Composer valide le manifeste, les exigences de plateforme sont satisfaites et l'audit des dépendances verrouillées ne signale aucune vulnérabilité connue. Le formatage Pint, la syntaxe JavaScript, la syntaxe Bash du démarrage et `git diff --check` ont été vérifiés.

Les anciens scénarios de `docs/audit-2026-11-17/AuditVerificationTest.php` prouvaient les défauts : ils ne doivent pas être utilisés pour valider la version corrigée.

Contrôles à rejouer depuis `plateforme` :

```powershell
php vendor/bin/phpunit --do-not-cache-result
node docs/audit-2026-11-17/verify-service-worker.mjs
composer validate --no-check-publish
composer check-platform-reqs --no-dev
composer audit --locked
php artisan schedule:list
php vendor/bin/pint --dirty --format agent
```

La CI GitHub reprend ces contrôles principaux, mais aucune exécution distante de cette nouvelle CI n'est revendiquée. Docker n'a pas répondu au contrôle de disponibilité ; aucune construction de l'image n'est déclarée validée. La syntaxe du script de démarrage a été vérifiée avec Bash.

## À valider avant une mise en production

1. **Paiements :** configurer une clé SDK réellement publiable, distincte de la clé serveur ; confirmer auprès du prestataire sa nature et ses droits. Valider en sandbox les champs de réponse `custom_id`, `amount`, `currency`, `shop_id`/`shopId`/`shop.id`/`shop._id`, le bénéficiaire et les doublons callback/webhook. Le contrôle refuse les données insuffisantes ; une adaptation documentée au format réel peut être nécessaire.
2. **Historique des encaissements :** le nouveau registre protège les références traitées depuis sa mise en place. Les anciennes transactions d'abonnement, auparavant connues seulement du cache, doivent être rapprochées des relevés du prestataire avant de rouvrir des callbacks historiques. Ne pas annoncer un remboursement ou un reversement sans confirmation externe.
3. **Secrets :** renouveler/révoquer les anciennes clés Photoroom si elles étaient authentiques. Aucune clé n'a été testée ni révoquée à distance.
4. **Hôtes et proxies :** `APP_URL` doit correspondre à l'adresse réelle. Déclarer les alias et le domaine technique Render nécessaires aux sondes dans `TRUSTED_HOSTS`, et les CIDR réels dans `TRUSTED_PROXIES`. Ne pas rétablir une confiance universelle pour contourner une erreur de configuration.
5. **Disponibilité :** un hébergement en veille ne garantit pas l'exécution des tâches à heure fixe. Utiliser un ordonnanceur toujours actif ou un cron externe, et un cache de verrous partagé si plusieurs instances sont déployées.
6. **Données anciennes :** les commandes antérieures à la nouvelle colonne sont initialisées en XOF, comme les anciens paiements. Rapprocher manuellement les anciennes commandes hors XOF : aucune conversion rétroactive ni réécriture de leur montant n'a été inventée. Vérifier également les abonnements actifs historiques sans date de fin.
7. **Recette distante :** construction Docker, migration PostgreSQL, concurrence réelle, redémarrage du conteneur, purge des anciens caches sur navigateurs installés et parcours de paiement effectif. Les tests SQLite et la simulation du worker ne remplacent pas ces vérifications.
