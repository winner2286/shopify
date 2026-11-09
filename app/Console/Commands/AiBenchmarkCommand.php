<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\AiBenchmarkAndDatasetService;
use Illuminate\Console\Command;

class AiBenchmarkCommand extends Command
{
    protected $signature = 'ai:benchmark {--export-dataset : Exporter également le dataset JSONL de fine-tuning}';

    protected $description = 'Exécute le benchmark de qualité, exactitude, raisonnement métier et sécurité de l\'agent IA Zanika';

    public function handle(AiBenchmarkAndDatasetService $service): int
    {
        $this->info('=================================================================');
        $this->info('   ZANIKA AI — BENCHMARK DE QUALITÉ, PRÉCISION & SÉCURITÉ');
        $this->info('=================================================================');

        $user = User::first() ?? new User(['name' => 'Marchand Test', 'role' => 'merchant']);
        $shop = Shop::withoutGlobalScopes()->first() ?? new Shop(['name' => 'Boutique Benchmark Lomé', 'currency' => 'FCFA']);

        $report = $service->runBenchmark($shop, $user);

        foreach ($report['results'] as $res) {
            $icon = $res['passed'] ? '✅ PASS' : '❌ FAIL';
            $this->line(sprintf(
                '%s | [%s] %s — Précision: %s%% (%sms)',
                $icon,
                $res['category'],
                $res['id'],
                $res['accuracy_percent'],
                $res['latency_ms']
            ));
        }

        $this->newLine();
        $this->info("Score Global : {$report['global_score_percent']}% ({$report['passed_cases']}/{$report['total_cases']}) en {$report['total_duration_ms']}ms");
        $this->comment("Verdict Fine-Tuning : {$report['fine_tuning_recommendation']}");

        if ($this->option('export-dataset')) {
            $export = $service->exportFineTuningDataset();
            $this->info("Dataset SFT JSONL généré ({$export['examples_count']} scénarios certifiés) : {$export['path']}");
        }

        return self::SUCCESS;
    }
}

