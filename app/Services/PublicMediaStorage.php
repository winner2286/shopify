<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PublicMediaStorage
{
    public function exists(string $path): bool
    {
        return Storage::disk('public')->exists($path) || DB::table('public_media')->where('path', $path)->exists();
    }

    public function get(string $path): string
    {
        if (Storage::disk('public')->exists($path)) {
            return (string) Storage::disk('public')->get($path);
        }
        $data = DB::table('public_media')->where('path', $path)->value('data');
        $binary = is_string($data) ? base64_decode($data, true) : false;
        if ($binary === false) {
            throw new \RuntimeException('Média introuvable.');
        }

        return $binary;
    }

    public function delete(string|array $paths): bool
    {
        $paths = (array) $paths;

        return DB::transaction(function () use ($paths) {
            DB::table('public_media')->whereIn('path', $paths)->delete();
            if (! Storage::disk('public')->delete($paths)) {
                throw new \RuntimeException('Impossible de supprimer le média.');
            }

            return true;
        });
    }

    public function put(string $path, string $contents): bool
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Chemin média invalide.');
        }
        $disk = Storage::disk('public');
        if (! preg_match('#^shops/(\d+)/#', $path, $matches)) {
            if (! $disk->put($path, $contents)) {
                throw new \RuntimeException('Impossible de stocker le média.');
            }

            return true;
        }

        return DB::transaction(function () use ($path, $contents, $disk, $matches) {
            $shop = Shop::whereKey((int) $matches[1])->lockForUpdate()->firstOrFail();
            $backups = DB::table('public_media')->where('shop_id', $shop->id)->get(['path', 'size_bytes']);
            $backedUpPaths = $backups->pluck('path')->flip();
            $used = (int) $backups->where('path', '!=', $path)->sum('size_bytes');
            foreach ($disk->allFiles('shops/'.$shop->id) as $file) {
                if ($file !== $path && ! $backedUpPaths->has($file)) {
                    $used += $disk->size($file);
                }
            }
            $limit = max(0, (int) $shop->currentPlan()->storage_mb) * 1024 * 1024;
            if (! $shop->owner?->isAdmin() && $used + strlen($contents) > $limit) {
                throw ValidationException::withMessages(['storage' => 'La limite de stockage de votre forfait est atteinte.']);
            }
            if (! $disk->put($path, $contents)) {
                throw new \RuntimeException('Impossible de stocker le média.');
            }
            DB::table('public_media')->upsert([
                'shop_id' => $shop->id, 'path' => $path, 'data' => base64_encode($contents),
                'size_bytes' => strlen($contents), 'created_at' => now(), 'updated_at' => now(),
            ], ['path'], ['data', 'size_bytes', 'updated_at']);

            return true;
        });
    }
}
