# Zanika

Plateforme e-commerce Laravel : création guidée de boutiques, catalogue, commandes, paiement, assistant IA, studio photo et publicités. Interface V1 française, boutiques accessibles sous `/b/{identifiant}`. Les sous-domaines DNS et une interface entièrement bilingue ne font pas partie de cette version livrée.

## Environnement

PHP 8.4 avec GD/WebP, SQLite ou PostgreSQL, Composer 2 et Node 24 pour les contrôles JavaScript. Laravel et les versions exactes des bibliothèques sont verrouillés dans `composer.lock`.

## Installation locale

```powershell
composer install
if (-not (Test-Path .env)) {
    Copy-Item .env.example .env
    php artisan key:generate
}
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Ne pas remplacer un `.env` existant : conserver les réglages et les secrets locaux. Le frontend principal utilise les fichiers de `public/css` et `public/js`. La configuration Vite est également présente pour les ressources de `resources/`.

## Vérifications

```powershell
php vendor/bin/phpunit --do-not-cache-result
node docs/audit-2026-11-17/verify-service-worker.mjs
composer validate --no-check-publish
composer audit --locked
php vendor/bin/pint --dirty --format agent
```

Les tests fonctionnels utilisent SQLite en mémoire et des intégrations simulées. Les régressions des correctifs sont dans `tests/Feature/AuditSecurityFixTest.php`. L'ancien `docs/audit-2026-11-17/AuditVerificationTest.php` décrit les défauts avant correction : ce n'est pas la suite de validation de la version corrigée.

## Paiements

Les commandes conservent leur devise. L'encaissement en ligne de la V1 est limité à XOF ; les autres devises restent disponibles pour le paiement direct ou à la livraison, sans conversion automatique.

Configurer le compte bénéficiaire `FEEXPAY_SHOP_ID` et la clé serveur `FEEXPAY_TOKEN` (ou leurs alias `GENIUSPAY_*`). La clé serveur n'est plus injectée dans le navigateur. `FEEXPAY_SDK_TOKEN` doit être une clé explicitement autorisée à être exposée par le prestataire ; aucune valeur de repli vers la clé serveur n'est utilisée. Sans clé SDK adaptée, le bouton de paiement en ligne reste indisponible. Ne pas y copier une clé secrète non publiable.

La vérification serveur exige statut de succès, référence, cible `custom_id`, montant exact, devise et identifiant du compte bénéficiaire. Vérifier les réponses réelles des endpoints en sandbox avant toute activation LIVE. Un champ requis absent entraîne le refus du paiement, même si le navigateur annonce un succès. Les simulations de paiement sont désactivées en production.

Le registre SQL `payment_transactions` empêche le retraitement d'une même référence et rattache ses écritures à la même transaction SQL. Les abonnements actifs historiques sans échéance restent considérés comme manuellement gérés ; les régulariser depuis l'administration si une échéance mensuelle est attendue.

## Médias et stockage

`PublicMediaStorage` applique les quotas par boutique et conserve une copie des nouveaux médias dans `public_media`. Les images produit et logos disposent aussi d'une sauvegarde en base. Une perte du disque local peut être compensée pour les médias sauvegardés ; les originaux déjà perdus avant sauvegarde ne peuvent pas être recréés.

Avant de remplacer un ancien conteneur dont les fichiers ne sont pas persistants :

```powershell
php artisan media:backup-existing --no-interaction
```

Cette commande sauvegarde les médias référencés encore présents et signale les absences, sans inventer d'images de remplacement. Les anciennes pistes audio sous `ads/audio` nécessitent un contrôle séparé avant destruction de l'ancien disque. Pour un volume commercial important, utiliser un stockage objet durable et adapter explicitement les chemins locaux ; changer uniquement `FILESYSTEM_DISK` ne suffit pas. Sauvegarder PostgreSQL/SQLite séparément : une copie SQL des médias ne remplace pas une sauvegarde de la base.

Les brouillons `ai_draft_*` non rattachés sont nettoyés après 24 heures. Les fichiers de produit, logos et médias publicitaires référencés ne sont pas ciblés par cette règle.

## Exploitation

```powershell
php artisan migrate --force
php artisan db:seed --class=PlanSeeder --force
php artisan schedule:list
```

Les migrations sont des opérations de déploiement. Une requête HTTP ne migre plus la base. Le seeder crée les formules manquantes et conserve les tarifs/quotas modifiés dans l'administration.

L'image Docker exécute `schedule:work` avec le serveur web (`RUN_SCHEDULER=true` par défaut). Sur un hébergement qui se met en veille, prévoir un ordonnanceur permanent ou un cron externe ; plusieurs instances demandent un cache de verrous partagé. Rappels à 09h00, briefing à 07h00, automatisations horaires et quotas remis à zéro le premier du mois : horaires du fuseau configuré dans Laravel. Les renouvellements réinitialisent également les quotas selon le comportement commercial existant.

Une `APP_KEY` stable doit être fournie en production. Une migration ou configuration Nginx invalide empêche le démarrage. Les secrets Photoroom sont fournis exclusivement par l'environnement ; les anciennes clés présentes dans le code doivent être révoquées si elles étaient réelles.

Configurer `APP_URL` et, si nécessaire, `TRUSTED_HOSTS` pour les alias/domaine technique Render des sondes. `TRUSTED_PROXIES` contient les adresses/CIDR des proxies autorisés ; les en-têtes d'un client public non approuvé sont ignorés.

## Audit et état des correctifs

- [Rapport initial](docs/audit-2026-11-17/RAPPORT_AUDIT.md)
- [Correctifs appliqués et limites de déploiement](docs/audit-2026-11-17/CORRECTIFS.md)

Aucun déploiement distant ni encaissement réel n'est effectué par les scripts de test. La CI GitHub exécute la suite et l'audit des dépendances ; ses résultats distants ne sont pas assimilés à une exécution locale.
