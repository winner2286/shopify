<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;

class RembgService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.rembg.enabled')
            && is_executable((string) config('services.rembg.python'))
            && is_file((string) config('services.rembg.model_path'));
    }

    public function removeBackground(string $binary): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $lock = fopen(storage_path('framework/cache/rembg.lock'), 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw ValidationException::withMessages(['photo' => 'Un détourage est déjà en cours. Réessayez dans quelques instants ; vos crédits sont restitués.']);
        }

        try {
            $process = Process::path(base_path())->env([
                'OMP_NUM_THREADS' => '1', 'OPENBLAS_NUM_THREADS' => '1',
                'NUMBA_DISABLE_JIT' => '1', 'PYTHONDONTWRITEBYTECODE' => '1',
                'U2NET_HOME' => dirname((string) config('services.rembg.model_path')),
            ])->input($binary)->timeout((int) config('services.rembg.timeout', 45))->run([
                (string) config('services.rembg.python'),
                base_path('docker/rembg/cutout.py'),
                (string) config('services.rembg.model_path'),
            ]);
            $result = $process->output();
            $size = @getimagesizefromstring($result);
            if (! $process->successful() || strlen($result) > 8 * 1024 * 1024 || ! $size || $size[2] !== IMAGETYPE_PNG || max($size[0], $size[1]) > 1200) {
                Log::warning('RemBG cutout rejected', ['exit_code' => $process->exitCode()]);

                return null;
            }

            return $result;
        } catch (\Throwable $exception) {
            Log::warning('RemBG cutout failed', ['exception' => $exception::class]);

            return null;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
