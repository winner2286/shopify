<?php

namespace App\Services\Ai\Contracts;

use App\Models\Shop;
use App\Models\User;

interface AiToolInterface
{
    /**
     * Nom technique unique de l'outil (ex: 'get_catalog_overview', 'search_orders')
     */
    public function name(): string;

    /**
     * Description détaillée pour le LLM expliquant la fonction et les cas d'usage
     */
    public function description(): string;

    /**
     * Schéma JSON des paramètres acceptés par l'outil
     */
    public function parametersSchema(): array;

    /**
     * Indique si l'outil modifie des données critiques (prix, stock, commande)
     * et nécessite une validation explicite du commerçant avant exécution.
     */
    public function requiresConfirmation(): bool;

    /**
     * Exécute l'action de manière hermétique sur la boutique spécifiée.
     * Ne doit JAMAIS accepter de requête SQL brute.
     *
     * @param  Shop  $shop  Boutique courante (isolation multi-boutique)
     * @param  array  $arguments  Arguments validés transmis par le LLM
     * @param  User|null  $user  Utilisateur à l'origine de l'action
     * @return array  Résultat structuré retourné au LLM et au frontend
     */
    public function execute(Shop $shop, array $arguments, ?User $user = null): array;
}
