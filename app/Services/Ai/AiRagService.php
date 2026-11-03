<?php

namespace App\Services\Ai;

use App\Models\AiKnowledgeChunk;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class AiRagService
{
    /**
     * Recherche les fragments de connaissances les plus pertinents pour une requête donnée.
     *
     * @param string $query Requête utilisateur ou intention
     * @param string|null $category Filtre optionnel de catégorie
     * @param int $limit Nombre maximal de fragments à renvoyer
     * @return \Illuminate\Support\Collection
     */
    public function search(string $query, ?string $category = null, int $limit = 3): \Illuminate\Support\Collection
    {
        $normalizedQuery = mb_strtolower(trim($query));
        if (empty($normalizedQuery)) {
            return collect();
        }

        // Nettoyage et extraction des mots-clés significatifs (> 2 caractères)
        $stopWords = ['dans', 'pour', 'avec', 'sans', 'sous', 'vers', 'chez', 'comment', 'faire', 'avoir', 'etre', 'mon', 'mes', 'ma', 'ton', 'tes', 'ta', 'son', 'ses', 'sa', 'les', 'des', 'une', 'qui', 'que', 'quoi', 'dont'];
        $rawWords = preg_split('/[\s,\.\?\!\:\;\/\(\)\'\"\-]+/u', $normalizedQuery, -1, PREG_SPLIT_NO_EMPTY);
        $keywords = array_values(array_filter($rawWords, fn($w) => mb_strlen($w) >= 3 && ! in_array($w, $stopWords, true)));

        $queryBuilder = AiKnowledgeChunk::query()->where('is_active', true);
        if ($category) {
            $queryBuilder->where('category', $category);
        }

        $allChunks = $queryBuilder->get();

        if ($allChunks->isEmpty()) {
            return collect();
        }

        // Calcul du score de pertinence pour chaque chunk
        $scored = $allChunks->map(function (AiKnowledgeChunk $chunk) use ($normalizedQuery, $keywords) {
            $score = 0;
            $titleLower = mb_strtolower($chunk->title);
            $contentLower = mb_strtolower($chunk->content);
            $slugLower = mb_strtolower($chunk->slug);
            $chunkKeywords = array_map('mb_strtolower', (array) ($chunk->keywords ?? []));

            // 1. Correspondance exacte sur l'expression complète
            if (str_contains($titleLower, $normalizedQuery)) {
                $score += 25;
            }
            if (str_contains($contentLower, $normalizedQuery)) {
                $score += 10;
            }

            // 2. Correspondance sur les mots-clés indexés
            foreach ($keywords as $kw) {
                // Correspondance dans le tableau de mots-clés
                foreach ($chunkKeywords as $ckw) {
                    if ($ckw === $kw || str_contains($ckw, $kw) || str_contains($kw, $ckw)) {
                        $score += 8;
                    }
                }

                // Titre
                if (str_contains($titleLower, $kw)) {
                    $score += 5;
                }

                // Slug
                if (str_contains($slugLower, $kw)) {
                    $score += 4;
                }

                // Contenu
                if (str_contains($contentLower, $kw)) {
                    $score += 1;
                }
            }

            // Bonus pour l'ordre de priorité défini
            $score += max(0, 10 - $chunk->sort_order);

            $chunk->relevance_score = $score;
            return $chunk;
        });

        // Filtrer les chunks avec un score minimal et trier par pertinence décroissante
        return $scored
            ->filter(fn($c) => $c->relevance_score >= 5)
            ->sortByDesc('relevance_score')
            ->take($limit)
            ->values();
    }

    /**
     * Formate une collection de chunks pour injection dans le System Prompt.
     */
    public function formatForPrompt(\Illuminate\Support\Collection $chunks): string
    {
        if ($chunks->isEmpty()) {
            return '';
        }

        $output = "--- BASE DE CONNAISSANCES OFFICIELLE DE LA PLATEFORME ZANIKA (RAG) ---\n";
        $output .= "Utilise STRICTEMENT les informations ci-dessous pour répondre aux questions de navigation, règles métier et fonctionnalités. Ne jamais inventer de fausse route ou de fonctionnalité non documentée.\n\n";

        foreach ($chunks as $index => $chunk) {
            $num = $index + 1;
            $output .= "[GUIDE {$num}] : {$chunk->title} (Catégorie: {$chunk->category})\n";
            if ($chunk->target_routes) {
                $output .= "Routes d'accès : {$chunk->target_routes}\n";
            }
            $output .= trim($chunk->content) . "\n\n";
        }

        $output .= "--- FIN DE LA BASE DE CONNAISSANCES ---";

        return $output;
    }
}
