<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\AiBenchmarkAndDatasetService;
use Illuminate\Console\Command;

class AiTrainCommand extends Command
{
    protected $signature = 'ai:train';

    protected $description = 'Prépare les exemples IA, actualise le RAG et contrôle le moteur local sans réentraîner les modèles cloud';

    public function handle(AiBenchmarkAndDatasetService $service): int
    {
        $this->info('=========================================================================');
        $this->info('   ZANIKA AI — PRÉPARATION DES DONNÉES & CALIBRATION LOCALE');
        $this->warn('Aucun modèle cloud ne sera réentraîné. Les exemples synthétiques nécessitent une revue humaine avant tout fine-tuning.');
        $this->info('=========================================================================');

        $user = User::first() ?? new User(['name' => 'Marchand Test', 'role' => 'merchant']);
        $shop = Shop::withoutGlobalScopes()->first() ?? new Shop(['name' => 'Boutique Pilote Lomé', 'currency' => 'FCFA']);

        $result = $service->trainAllModels($shop, $user);
        $manifest = $result['manifest'];
        $benchmark = $result['benchmark'];

        $this->newLine();
        $this->info('1. BASE DE CONNAISSANCES HYBRIDE RAG (BM25 + Trigrammes Sørensen-Dice)');
        $this->line('   • Fragments experts actifs : '.$manifest['rag_knowledge_base']['active_chunks_count']);
        foreach ($manifest['rag_knowledge_base']['categories'] as $cat => $count) {
            $this->line("     - {$cat} : {$count} guide(s)");
        }

        $this->newLine();
        $this->info('2. EXEMPLES PÉDAGOGIQUES GÉNÉRÉS (JSONL, non validés pour un entraînement fournisseur)');
        $this->line('   • Dataset SFT (Supervised Fine-Tuning) : '.$manifest['datasets']['sft']['examples_count'].' conversations ('.count($manifest['datasets']['sft']['categories_covered']).' catégories)');
        $this->line('     Fichier : '.$manifest['datasets']['sft']['path']);
        $this->line('   • Dataset DPO (Direct Preference Optimization) : '.$manifest['datasets']['dpo']['pairs_count'].' paires {prompt, chosen, rejected}');
        $this->line('     Fichier : '.$manifest['datasets']['dpo']['path']);
        $this->line('   • Dataset Tool-Calling (Function Calling) : '.$manifest['datasets']['tool_calling']['examples_count'].' scénarios d\'outils');
        $this->line('     Fichier : '.$manifest['datasets']['tool_calling']['path']);
        $this->line('   • Manifeste d\'entraînement : '.$result['manifest_path']);
        $this->line('   • Direction créative TikTok/Reels : '.$manifest['datasets']['creative_direction']['examples_count'].' exemples fictifs');

        $this->newLine();
        $this->info('3. CONTRÔLES LOCAUX RAG & ARCHITECTURE ('.$benchmark['total_cases'].' cas ; aucune évaluation de modèle cloud)');
        foreach ($benchmark['results'] as $res) {
            $icon = $res['passed'] ? '[PASS]' : '[FAIL]';
            $this->line(sprintf(
                '   %s [%s] %s — Précision: %s%% (%sms)',
                $icon,
                $res['category'],
                $res['id'],
                $res['accuracy_percent'],
                $res['latency_ms']
            ));
        }

        $this->newLine();
        $this->info("SCORE GLOBAL : {$benchmark['global_score_percent']}% ({$benchmark['passed_cases']}/{$benchmark['total_cases']}) en {$manifest['pipeline_duration_ms']}ms");

        return $benchmark['global_score_percent'] >= 90.0 ? self::SUCCESS : self::FAILURE;
    }
}
