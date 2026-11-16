<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PhotoroomService
{
    protected string $apiKey;
    protected string $segmentUrl;
    protected string $editUrl;

    public function __construct()
    {
        $this->apiKey = trim((string) config('services.photoroom.key', ''));
        $this->segmentUrl = trim((string) config('services.photoroom.segment_url', 'https://sdk.photoroom.com/v1/segment'));
        $this->editUrl = trim((string) config('services.photoroom.edit_url', 'https://image-api.photoroom.com/v2/edit'));
    }

    protected function apiKey(): string
    {
        $candidates = $this->candidateApiKeys();

        return $candidates[0] ?? '';
    }

    /**
     * Retourne les clés API Photoroom candidates par ordre de priorité : Live puis Sandbox.
     * Permet de basculer automatiquement sur la clé Sandbox si le quota gratuit Live (402/403/429) est épuisé.
     *
     * @return array<int, string>
     */
    protected function candidateApiKeys(): array
    {
        $candidates = [
            trim((string) config('services.photoroom.key', $this->apiKey)),
            trim((string) config('services.photoroom.sandbox_key', '')),
        ];

        return array_values(array_unique(array_filter($candidates, fn (string $k) => $k !== '')));
    }

    protected function segmentUrl(): string
    {
        return trim((string) config('services.photoroom.segment_url', $this->segmentUrl ?: 'https://sdk.photoroom.com/v1/segment'));
    }

    protected function editUrl(): string
    {
        return trim((string) config('services.photoroom.edit_url', $this->editUrl ?: 'https://image-api.photoroom.com/v2/edit'));
    }

    /**
     * Vérifie si la clé API Photoroom est configurée et prête à l'emploi.
     */
    public function isConfigured(): bool
    {
        return $this->candidateApiKeys() !== [];
    }

    /**
     * Détourage haute précision d'un produit via l'API Photoroom v1/segment.
     * Retourne les octets d'un PNG transparent (RGBA) ou null en cas d'indisponibilité.
     *
     * @param string $imageBinary Binaire source de la photo
     * @param array<string, mixed> $options Options supplémentaires (crop, size, format)
     */
    public function removeBackground(string $imageBinary, array $options = []): ?string
    {
        $fields = [
            'format' => (string) ($options['format'] ?? 'png'),
            'channels' => (string) ($options['channels'] ?? 'rgba'),
            'size' => (string) ($options['size'] ?? 'full'),
            'crop' => isset($options['crop']) && $options['crop'] ? 'true' : 'false',
        ];

        return $this->sendMultipartWithFallback(
            url: $this->segmentUrl(),
            fileField: 'image_file',
            imageBinary: $imageBinary,
            fields: $fields,
            accept: 'image/png, application/json',
            timeout: 25,
            logLabel: 'Photoroom v1/segment'
        );
    }

    /**
     * Édition Studio & Scène IA complète via Photoroom Image Editing v2/edit
     * (détourage, ré-éclairage IA ai.auto, ombre douce ai.soft, fond couleur ou décor IA).
     *
     * @param string $imageBinary Binaire source de la photo
     * @param array<string, mixed> $params Paramètres Image Editing v2
     */
    public function editProductImage(string $imageBinary, array $params = []): ?string
    {
        $fields = [
            'removeBackground' => (string) ($params['removeBackground'] ?? 'true'),
            'lighting.mode' => (string) ($params['lighting.mode'] ?? 'ai.auto'),
            'shadow.mode' => (string) ($params['shadow.mode'] ?? 'ai.soft'),
            'padding' => (string) ($params['padding'] ?? '0.10'),
            'outputSize' => (string) ($params['outputSize'] ?? '1000x1000'),
            'export.format' => (string) ($params['export.format'] ?? 'png'),
        ];

        if (! empty($params['background.prompt'])) {
            $fields['background.prompt'] = (string) $params['background.prompt'];
            if (! empty($params['background.guidance.scale'])) {
                $fields['background.guidance.scale'] = (string) $params['background.guidance.scale'];
            }
        } else {
            $fields['background.color'] = ltrim((string) ($params['background.color'] ?? 'FFFFFF'), '#');
        }

        return $this->sendMultipartWithFallback(
            url: $this->editUrl(),
            fileField: 'imageFile',
            imageBinary: $imageBinary,
            fields: $fields,
            accept: 'image/png, image/jpeg, image/webp, application/json',
            timeout: 30,
            logLabel: 'Photoroom v2/edit'
        );
    }

    /**
     * Exécute un appel multipart vers Photoroom en basculant automatiquement de la clé Live
     * vers la clé Sandbox si la première échoue (401/402/403/429/timeout).
     *
     * @param array<string, string> $fields
     */
    protected function sendMultipartWithFallback(
        string $url,
        string $fileField,
        string $imageBinary,
        array $fields,
        string $accept,
        int $timeout,
        string $logLabel
    ): ?string {
        $keys = $this->candidateApiKeys();
        if ($keys === [] || trim($imageBinary) === '') {
            return null;
        }

        foreach ($keys as $index => $apiKey) {
            try {
                $client = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'Accept' => $accept,
                ])->timeout($timeout);

                if (! app()->environment('production')) {
                    $client = $client->withoutVerifying();
                }

                $response = $client
                    ->attach($fileField, $imageBinary, 'product.jpg')
                    ->post($url, $fields);

                if ($response->successful()) {
                    $body = $response->body();
                    if ($this->isValidImageBinary($body)) {
                        return $body;
                    }
                    Log::warning("{$logLabel} returned non-image payload.", ['key_index' => $index]);
                    continue;
                }

                Log::warning("{$logLabel} HTTP error", [
                    'key_index' => $index,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 300),
                ]);
            } catch (\Throwable $e) {
                Log::warning("{$logLabel} exception: " . $e->getMessage(), ['key_index' => $index]);
            }
        }

        return null;
    }

    /**
     * Génère un packshot studio ou une scène e-commerce complète adaptée aux styles Zanika.
     * En cas d'échec de v2/edit (ex: forfait v1 uniquement), bascule automatiquement sur v1/segment.
     */
    public function studioPackshot(string $imageBinary, string $style = 'studio_white', string $outputSize = '1000x1000'): ?string
    {
        if (! $this->isConfigured() || trim($imageBinary) === '') {
            return null;
        }

        if ($style === 'transparent') {
            return $this->removeBackground($imageBinary, ['format' => 'png']);
        }

        $params = $this->mapStyleToEditParams($style, $outputSize);
        $edited = $this->editProductImage($imageBinary, $params);

        if ($edited !== null) {
            return $edited;
        }

        // Repli sur v1/segment haute précision afin que le studio local compose le décor
        return $this->removeBackground($imageBinary, ['format' => 'png']);
    }

    /**
     * Traduit un identifiant de style Zanika en paramètres Photoroom Image Editing v2.
     *
     * @return array<string, string>
     */
    public function mapStyleToEditParams(string $style, string $outputSize = '1000x1000'): array
    {
        $base = [
            'removeBackground' => 'true',
            'lighting.mode' => 'ai.auto',
            'shadow.mode' => 'ai.soft',
            'padding' => '0.10',
            'outputSize' => $outputSize,
            'export.format' => 'png',
        ];

        return match ($style) {
            'studio_white', 'white', 'studio_minimalist' => array_merge($base, [
                'background.color' => 'FFFFFF',
            ]),
            'studio_ambient', 'ambient', 'ambient_warm' => array_merge($base, [
                'background.color' => 'FAF7F2',
            ]),
            'studio_dark', 'dark' => array_merge($base, [
                'background.color' => '1C1D24',
            ]),
            'podium', 'studio_podium' => array_merge($base, [
                'background.prompt' => 'Minimalist architectural limestone podium in a soft sunlit luxury studio, subtle zenith spotlight, neutral warm beige tones, commercial packshot photography',
            ]),
            'luxury_showroom' => array_merge($base, [
                'background.prompt' => 'Ultra-luxurious modern boutique showroom, polished white and gold-veined marble display surface, soft warm gallery spotlights, editorial commercial photography',
            ]),
            'lifestyle', 'lifestyle_staged' => array_merge($base, [
                'background.prompt' => 'Refined contemporary West African boutique table with soft morning window light, travertine stone surface and warm natural shadows, authentic e-commerce photography',
            ]),
            'lifestyle_urban' => array_merge($base, [
                'background.prompt' => 'Trendy modern sunlit lifestyle boutique counter, golden hour sunlight, warm aesthetic shallow depth of field, photorealistic commercial photography',
            ]),
            'flash_sale' => array_merge($base, [
                'background.prompt' => 'Bold modern commercial advertising studio pedestal, vibrant warm crimson and rose studio rim light glow, sleek reflective surface, high contrast product showcase',
            ]),
            'tropical_african' => array_merge($base, [
                'background.prompt' => 'Modern Afro-chic minimalist studio, warm terracotta backdrop with subtle monstera leaf shadows and golden morning sunlight, editorial lookbook photography',
            ]),
            default => array_merge($base, [
                'background.color' => 'FFFFFF',
            ]),
        };
    }

    /**
     * Vérifie que les octets reçus correspondent bien à une image valide décodable par GD.
     */
    protected function isValidImageBinary(string $binary): bool
    {
        if (strlen($binary) < 200) {
            return false;
        }

        if (! function_exists('imagecreatefromstring')) {
            // Vérifier au moins les en-têtes magiques PNG / JPEG / WebP
            return str_starts_with($binary, "\x89PNG")
                || str_starts_with($binary, "\xFF\xD8\xFF")
                || str_starts_with($binary, 'RIFF');
        }

        $img = @imagecreatefromstring($binary);
        if ($img === false) {
            return false;
        }

        imagedestroy($img);
        return true;
    }
}
