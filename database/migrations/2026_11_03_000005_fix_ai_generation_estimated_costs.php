<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Corrige les coûts surévalués de 1000x suite à la coquille de formule par million de tokens
        DB::statement("
            UPDATE ai_generations
            SET cost_estimated_fcfa = GREATEST(1, ROUND(
                ((COALESCE(prompt_tokens, 0) / 1000000.0) * 0.15 + (COALESCE(completion_tokens, 0) / 1000000.0) * 0.60) * 650
            ))
            WHERE type = 'ai_assistant_chat' OR cost_estimated_fcfa > 50
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas de rollback nécessaire
    }
};
