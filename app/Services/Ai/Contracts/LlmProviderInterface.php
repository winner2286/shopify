<?php

namespace App\Services\Ai\Contracts;

interface LlmProviderInterface
{
    /**
     * Exécute une requête de chat completion avec historique et déclaration d'outils (Function Calling).
     *
     * @param  array  $messages  Liste de messages [['role' => 'system'|'user'|'assistant'|'tool', 'content' => string|array, ...]]
     * @param  array  $tools     Schéma des outils déclarés au format JSON Schema OpenAI
     * @param  array  $options   Paramètres supplémentaires (model, temperature, max_tokens)
     * @return array  [
     *     'content' => string|null,
     *     'tool_calls' => array|null,
     *     'tokens_input' => int,
     *     'tokens_output' => int,
     *     'model_used' => string,
     *     'raw' => array,
     * ]
     */
    public function chat(array $messages, array $tools = [], array $options = []): array;
}
