<?php

namespace App\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;

class SearchHelper
{
    /**
     * Retourne l'opérateur LIKE insensible à la casse adapté au pilote SGBD (PostgreSQL ILIKE vs SQLite/MySQL LIKE).
     */
    public static function likeOperator(?ConnectionInterface $connection = null): string
    {
        $driver = $connection?->getDriverName();

        return $driver === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * Applique un filtre de recherche texte portable (LIKE / ILIKE) sur une ou plusieurs colonnes.
     *
     * @param string|array<int, string> $columns
     */
    public static function whereLike(Builder $query, string|array $columns, string $term, string $mode = 'contains'): Builder
    {
        $columns = (array) $columns;
        $operator = self::likeOperator($query->getConnection());
        $pattern = match ($mode) {
            'prefix' => "{$term}%",
            'suffix' => "%{$term}",
            default => "%{$term}%",
        };

        return $query->where(function (Builder $sub) use ($columns, $operator, $pattern) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $sub->where($column, $operator, $pattern);
                } else {
                    $sub->orWhere($column, $operator, $pattern);
                }
            }
        });
    }
}

