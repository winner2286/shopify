<?php

use App\Models\AiGeneration;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Corrige les coûts surévalués de 1000x de manière agnostique (SQLite, MySQL, PostgreSQL)
        if (\Illuminate\Support\Facades\Schema::hasTable('ai_generations')) {
            AiGeneration::withoutGlobalScopes()
                ->where('cost_estimated_fcfa', '>', 50)
                ->chunkById(100, function ($generations) {
                    foreach ($generations as $g) {
                        $costInputUsd = (($g->prompt_tokens ?? 0) / 1000000) * 0.15;
                        $costOutputUsd = (($g->completion_tokens ?? 0) / 1000000) * 0.60;
                        $g->cost_estimated_fcfa = max(1, (int) round(($costInputUsd + $costOutputUsd) * 650));
                        $g->saveQuietly();
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas de rollback nécessaire
    }
};
