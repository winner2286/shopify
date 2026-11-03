<?php

namespace App\Services\Ai;

use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Tools\DraftCustomerMessageTool;
use App\Services\Ai\Tools\GetCatalogOverviewTool;
use App\Services\Ai\Tools\GetInventoryAlertsTool;
use App\Services\Ai\Tools\GetOrderDetailsTool;
use App\Services\Ai\Tools\GetOrdersSummaryTool;
use App\Services\Ai\Tools\GetSalesAnalyticsTool;
use App\Services\Ai\Tools\SearchProductsTool;
use App\Services\Ai\Tools\UpdateProductStockOrPriceTool;
use Illuminate\Support\Facades\Log;

class ToolRegistry
{
    /**
     * @var array<string, AiToolInterface>
     */
    protected array $tools = [];

    public function __construct()
    {
        $this->registerDefaultTools();
    }

    protected function registerDefaultTools(): void
    {
        $defaultTools = [
            new GetCatalogOverviewTool(),
            new SearchProductsTool(),
            new GetOrdersSummaryTool(),
            new GetOrderDetailsTool(),
            new GetSalesAnalyticsTool(),
            new GetInventoryAlertsTool(),
            new DraftCustomerMessageTool(),
            new UpdateProductStockOrPriceTool(),
        ];

        foreach ($defaultTools as $tool) {
            $this->register($tool);
        }
    }

    public function register(AiToolInterface $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function get(string $name): ?AiToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Renvoie la liste des schémas JSON pour la déclaration auprès du modèle (OpenAI / OpenRouter / Gemini).
     */
    public function getToolsSchema(): array
    {
        $schemas = [];
        foreach ($this->tools as $tool) {
            $schemas[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => $tool->parametersSchema(),
                ],
            ];
        }

        return $schemas;
    }

    /**
     * Exécute un outil de manière étanche pour une boutique donnée.
     */
    public function execute(string $name, Shop $shop, array $arguments, ?User $user = null): array
    {
        $tool = $this->get($name);

        if (! $tool) {
            Log::warning("ToolRegistry: tentative d'appel d'un outil inconnu '{$name}'");
            return [
                'error' => "L'outil '{$name}' n'est pas disponible dans le système.",
            ];
        }

        try {
            return $tool->execute($shop, $arguments, $user);
        } catch (\Throwable $e) {
            Log::error("ToolRegistry exception lors de l'exécution de {$name}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'error' => "Erreur lors de l'exécution de l'outil {$name}: " . $e->getMessage(),
            ];
        }
    }
}
