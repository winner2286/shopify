<?php

namespace App\Services\Ai;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ActionTokenService
{
    /**
     * Génère un jeton signé HMAC pour autoriser une mutation sensible.
     *
     * @param Shop $shop Boutique concernée
     * @param User $user Utilisateur authentifié initiant l'action
     * @param string $toolName Nom de l'outil exécuté
     * @param array $parameters Paramètres exacts de la mutation
     * @param int $ttlMinutes Durée de validité (défaut 15 minutes)
     * @return string
     */
    public function generateToken(Shop $shop, User $user, string $toolName, array $parameters, int $ttlMinutes = 15): string
    {
        $normalizedParams = $this->normalizeParameters($parameters);
        $paramHash = hash('sha256', json_encode($normalizedParams, JSON_UNESCAPED_UNICODE));
        $expiresAt = now()->addMinutes($ttlMinutes)->timestamp;
        $nonce = Str::random(24);

        $payload = [
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'tool_name' => $toolName,
            'param_hash' => $paramHash,
            'expires_at' => $expiresAt,
            'nonce' => $nonce,
        ];

        $encodedPayload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $secretKey = (string) (config('app.key') ?: 'zanika-default-action-secret');
        $signature = hash_hmac('sha256', $encodedPayload, $secretKey);

        return "{$encodedPayload}.{$signature}";
    }

    /**
     * Valide l'intégrité, l'authenticité, l'éligibilité et la non-réutilisation du jeton.
     *
     * @param string $tokenString Jeton d'action fourni
     * @param Shop $shop Boutique active
     * @param User $user Utilisateur authentifié confirmant l'action
     * @param string $toolName Outil à exécuter
     * @param array $parameters Paramètres transmis pour l'exécution
     * @return array{valid: bool, error?: string, payload?: array}
     */
    public function validateToken(string $tokenString, Shop $shop, User $user, string $toolName, array $parameters): array
    {
        $parts = explode('.', trim($tokenString));
        if (count($parts) !== 2) {
            return ['valid' => false, 'error' => 'Format de jeton invalide.'];
        }

        [$encodedPayload, $providedSignature] = $parts;

        $secretKey = (string) (config('app.key') ?: 'zanika-default-action-secret');
        $expectedSignature = hash_hmac('sha256', $encodedPayload, $secretKey);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            return ['valid' => false, 'error' => 'Signature de sécurité invalide ou jeton corrompu.'];
        }

        $decodedJson = base64_decode(strtr($encodedPayload, '-_', '+/'));
        $payload = json_decode((string) $decodedJson, true);

        if (! is_array($payload)) {
            return ['valid' => false, 'error' => 'Charge utile du jeton illisible.'];
        }

        // 1. Vérification d'expiration
        if (($payload['expires_at'] ?? 0) < time()) {
            return ['valid' => false, 'error' => 'Ce jeton d\'approbation a expiré (délai maximal : 15 minutes). Veuillez réitérer votre demande.'];
        }

        // 2. Vérification d'isolation multi-tenant
        if ((int) ($payload['shop_id'] ?? 0) !== $shop->id) {
            return ['valid' => false, 'error' => 'Action non autorisée : violation d\'isolation de boutique.'];
        }

        // 3. Vérification de l'utilisateur initiateur
        if (! $user->isAdmin() && (int) ($payload['user_id'] ?? 0) !== $user->id) {
            return ['valid' => false, 'error' => 'Action non autorisée : utilisateur non habilité à valider cette action.'];
        }

        // 4. Vérification de l'outil
        if (($payload['tool_name'] ?? '') !== $toolName) {
            return ['valid' => false, 'error' => 'Incohérence d\'outil : ce jeton n\'est pas attribué à cette commande.'];
        }

        // 5. Vérification de l'intégrité des paramètres
        $normalizedCurrentParams = $this->normalizeParameters($parameters);
        $currentHash = hash('sha256', json_encode($normalizedCurrentParams, JSON_UNESCAPED_UNICODE));

        if (! hash_equals($payload['param_hash'] ?? '', $currentHash)) {
            return ['valid' => false, 'error' => 'Les paramètres d\'exécution ont été altérés ou ne correspondent pas à la demande initiale.'];
        }

        // 6. Protection anti-rejeu (Usage Unique)
        $nonce = $payload['nonce'] ?? null;
        if (! empty($nonce)) {
            $cacheKey = "ai_action_nonce:{$nonce}";
            $ttlSeconds = max(60, ($payload['expires_at'] - time()));
            if (! Cache::add($cacheKey, true, $ttlSeconds)) {
                return ['valid' => false, 'error' => 'Cette action a déjà été exécutée (jeton à usage unique).'];
            }
        }

        return ['valid' => true, 'payload' => $payload];
    }

    /**
     * Normalise récursivement les paramètres pour un hash stable indépendant de l'ordre des clés.
     */
    protected function normalizeParameters(array $params): array
    {
        // Retirer les clés d'exécution non structurelles
        unset($params['confirmed_by_user'], $params['action_token']);

        ksort($params);
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = $this->normalizeParameters($value);
            }
        }

        return $params;
    }
}
