<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Ai\ActionTokenService;
use App\Services\Ai\AiOrchestrator;
use App\Services\Ai\Contracts\LlmProviderInterface;
use App\Services\Ai\ToolRegistry;
use App\Services\Ai\Tools\CreateProductTool;
use App\Services\Ai\Tools\UpdateOrderStatusTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAgentPhase1Test extends TestCase
{
    use RefreshDatabase;

    protected Shop $shop;
    protected User $user;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::firstOrCreate(
            ['slug' => 'essentiel'],
            [
                'name' => 'Essentiel',
                'monthly_price' => 10000,
                'max_products' => 10,
                'storage_mb' => 2048,
                'ai_credits' => 80,
                'full_generations' => 1,
                'active' => true,
            ]
        );

        $this->user = User::factory()->create([
            'email' => 'marchand@zanika.test',
            'role' => User::ROLE_MERCHANT,
            'email_verified_at' => now(),
        ]);

        $this->shop = Shop::create([
            'user_id' => $this->user->id,
            'name' => 'Boutique Test Lomé',
            'subdomain' => 'test-lome',
            'status' => Shop::PUBLISHED,
            'currency' => 'XOF',
        ]);
    }

    public function test_action_token_service_generates_and_validates_hmac_token(): void
    {
        $service = new ActionTokenService();
        $params = [
            'product_id' => 10,
            'new_price' => 15000,
            'new_stock' => 25,
        ];

        $token = $service->generateToken($this->shop, $this->user, 'update_product_stock_or_price', $params);
        $this->assertNotEmpty($token);
        $this->assertStringContainsString('.', $token);

        // Validation nominale
        $validation = $service->validateToken($token, $this->shop, $this->user, 'update_product_stock_or_price', $params);
        $this->assertTrue($validation['valid']);

        // Altération des paramètres
        $tamperedParams = $params;
        $tamperedParams['new_price'] = 1000;
        $tamperedToken = $service->generateToken($this->shop, $this->user, 'update_product_stock_or_price', $params);
        $tamperedCheck = $service->validateToken($tamperedToken, $this->shop, $this->user, 'update_product_stock_or_price', $tamperedParams);
        $this->assertFalse($tamperedCheck['valid']);
        $this->assertStringContainsString('altérés', $tamperedCheck['error']);

        // Protection anti-rejeu (le premier jeton valide a déjà consommé son nonce)
        $replayCheck = $service->validateToken($token, $this->shop, $this->user, 'update_product_stock_or_price', $params);
        $this->assertFalse($replayCheck['valid']);
        $this->assertStringContainsString('déjà été exécutée', $replayCheck['error']);

        // Violation d'isolation boutique
        $otherShop = Shop::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Boutique Concurrente',
            'subdomain' => 'concurrente',
            'status' => Shop::PUBLISHED,
        ]);
        $freshToken = $service->generateToken($this->shop, $this->user, 'update_product_stock_or_price', $params);
        $isolationCheck = $service->validateToken($freshToken, $otherShop, $this->user, 'update_product_stock_or_price', $params);
        $this->assertFalse($isolationCheck['valid']);
        $this->assertStringContainsString('isolation', $isolationCheck['error']);
    }

    public function test_create_product_tool_requires_confirmation_and_generates_action_token(): void
    {
        $tool = new CreateProductTool();
        $args = [
            'title' => 'Robe Wax Royale',
            'price' => 25000,
            'compare_at_price' => 30000,
            'stock' => 15,
            'category_name' => 'Mode Femme',
            'description' => 'Superbe robe confectionnée en tissu wax hollandais.',
        ];

        // Étape 1 : proposition sans confirmation
        $result = $tool->execute($this->shop, $args, $this->user);

        $this->assertEquals('action_required', $result['status']);
        $this->assertEquals('create_product', $result['action_type']);
        $this->assertNotEmpty($result['action_token']);
        $this->assertEquals('Robe Wax Royale', $result['preview']['title']);
        $this->assertEquals(25000, $result['preview']['price']);

        // Aucun produit en base à cette étape
        $this->assertEquals(0, Product::withoutGlobalScopes()->where('shop_id', $this->shop->id)->count());
    }

    public function test_create_product_tool_executes_after_confirmation_and_audits_stock(): void
    {
        $tool = new CreateProductTool();
        $args = [
            'title' => 'Chemise Batik Élégance',
            'price' => 18000,
            'stock' => 8,
            'category_name' => 'Artisanat',
            'description' => 'Chemise batik faite à la main.',
        ];

        // 1. Obtenir le token HMAC
        $step1 = $tool->execute($this->shop, $args, $this->user);
        $token = $step1['action_token'];

        // 2. Confirmer avec le jeton et le payload exact validé
        $step2Args = array_merge($step1['product_payload'], [
            'confirmed_by_user' => true,
            'action_token' => $token,
        ]);
        $step2 = $tool->execute($this->shop, $step2Args, $this->user);

        $this->assertEquals('success', $step2['status']);
        $this->assertDatabaseHas('products', [
            'shop_id' => $this->shop->id,
            'title' => 'Chemise Batik Élégance',
            'price' => 18000,
            'stock' => 8,
            'created_via' => 'ai_copilot',
        ]);

        // Vérifier l'audit StockMovement
        $createdProduct = Product::withoutGlobalScopes()->where('shop_id', $this->shop->id)->first();
        $this->assertNotNull($createdProduct);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $createdProduct->id,
            'quantity_change' => 8,
            'new_stock' => 8,
            'reason' => 'initial_creation',
        ]);
    }

    public function test_create_product_tool_rejects_tampered_token(): void
    {
        $tool = new CreateProductTool();
        $args = [
            'title' => 'Montre Or 24K',
            'price' => 150000,
            'stock' => 5,
        ];

        $step1 = $tool->execute($this->shop, $args, $this->user);
        $token = $step1['action_token'];

        // Altération du prix de 150 000 à 1 500
        $tamperedArgs = array_merge($args, [
            'price' => 1500,
            'confirmed_by_user' => true,
            'action_token' => $token,
        ]);

        $step2 = $tool->execute($this->shop, $tamperedArgs, $this->user);
        $this->assertEquals('security_rejection', $step2['status']);
        $this->assertStringContainsString('altérés', $step2['error']);
    }

    public function test_update_order_status_tool_confirmed_and_shipped(): void
    {
        $order = Order::withoutGlobalScopes()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ZNK-202611-TEST01',
            'customer_name' => 'Afiwa Mensah',
            'customer_phone' => '+22890123456',
            'delivery_zone_name' => 'Lomé Intra-Muros',
            'delivery_address' => 'Quartier Adidogomé',
            'subtotal' => 20000,
            'shipping_cost' => 1000,
            'total' => 21000,
            'payment_method' => Order::PAYMENT_CASH,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => Order::STATUS_PENDING,
        ]);

        $tool = new UpdateOrderStatusTool();

        // 1. Passage de pending à confirmed (sans blocage car progression normale)
        $result = $tool->execute($this->shop, [
            'order_id' => $order->id,
            'new_status' => 'confirmed',
        ], $this->user);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('confirmed', $order->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => 'confirmed',
        ]);

        // 2. Passage de confirmed à shipped
        $resultShipped = $tool->execute($this->shop, [
            'order_id' => $order->id,
            'new_status' => 'shipped',
        ], $this->user);

        $this->assertEquals('success', $resultShipped['status']);
        $this->assertEquals('shipped', $order->fresh()->status);
    }

    public function test_update_order_status_tool_cancels_with_confirmation_and_restores_stock(): void
    {
        $product = Product::withoutGlobalScopes()->create([
            'shop_id' => $this->shop->id,
            'title' => 'Chaussures Cuir Noir',
            'slug' => 'chaussures-cuir-noir-test',
            'price' => 35000,
            'stock' => 5,
            'track_stock' => true,
            'status' => 'active',
        ]);

        $order = Order::withoutGlobalScopes()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ZNK-202611-CANCEL01',
            'customer_name' => 'Komla Agbo',
            'customer_phone' => '+22891234567',
            'delivery_zone_name' => 'Grand Lomé',
            'delivery_address' => 'Agoè Assiyéyé',
            'subtotal' => 35000,
            'shipping_cost' => 1500,
            'total' => 36500,
            'payment_method' => Order::PAYMENT_CASH,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => Order::STATUS_PENDING,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => $product->title,
            'unit_price' => 35000,
            'quantity' => 2,
            'total_price' => 70000,
        ]);

        $tool = new UpdateOrderStatusTool();

        // 1. Demande d'annulation -> action_required obligatoire
        $step1 = $tool->execute($this->shop, [
            'order_id' => $order->id,
            'new_status' => 'cancelled',
            'reason' => 'Client injoignable après 3 appels',
        ], $this->user);

        $this->assertEquals('action_required', $step1['status']);
        $this->assertNotEmpty($step1['action_token']);
        $token = $step1['action_token'];

        // Le stock est toujours à 5
        $this->assertEquals(5, $product->fresh()->stock);

        // 2. Validation avec jeton HMAC
        $step2 = $tool->execute($this->shop, [
            'order_id' => $order->id,
            'new_status' => 'cancelled',
            'reason' => 'Client injoignable après 3 appels',
            'confirmed_by_user' => true,
            'action_token' => $token,
        ], $this->user);

        $this->assertEquals('success', $step2['status']);
        $this->assertEquals(Order::STATUS_CANCELLED, $order->fresh()->status);

        // Le stock doit avoir été restitué (+2 unités = 7)
        $this->assertEquals(7, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity_change' => 2,
            'new_stock' => 7,
            'reason' => 'cancellation',
        ]);
    }

    public function test_multi_step_react_loop_orchestration(): void
    {
        // Mock du LLM pour simuler 2 appels d'outils successifs (ReAct Multi-Step)
        // Tour 1 : le LLM appelle search_products
        // Tour 2 : le LLM reçoit les résultats et appelle get_catalog_overview
        // Tour 3 : le LLM fournit la synthèse finale
        $mockLlm = new class implements LlmProviderInterface {
            private int $callCount = 0;

            public function chat(array $messages, array $tools = [], array $options = []): array
            {
                $this->callCount++;

                if ($this->callCount === 1) {
                    return [
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_step_1',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_catalog_overview',
                                    'arguments' => json_encode(['limit' => 5]),
                                ],
                            ],
                        ],
                        'tokens_input' => 150,
                        'tokens_output' => 50,
                        'model_used' => 'test-llm',
                    ];
                }

                if ($this->callCount === 2) {
                    return [
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_step_2',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_orders_summary',
                                    'arguments' => json_encode(['days' => 7]),
                                ],
                            ],
                        ],
                        'tokens_input' => 250,
                        'tokens_output' => 60,
                        'model_used' => 'test-llm',
                    ];
                }

                return [
                    'content' => "Synthèse complète : Votre catalogue comporte 0 article et vous avez 0 commande cette semaine.",
                    'tool_calls' => null,
                    'tokens_input' => 350,
                    'tokens_output' => 80,
                    'model_used' => 'test-llm',
                ];
            }
        };

        $orchestrator = new AiOrchestrator(
            llm: $mockLlm,
            tools: new ToolRegistry()
        );

        $result = $orchestrator->sendMessage($this->shop, $this->user, "Fais une analyse croisée de mon catalogue et de mes commandes de la semaine.");

        $this->assertEquals('success', $result['status']);
        $this->assertStringContainsString('Synthèse complète', $result['message']['content']);
        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $result['conversation_id'],
            'tool_name' => 'get_catalog_overview',
        ]);
        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $result['conversation_id'],
            'tool_name' => 'get_orders_summary',
        ]);
    }

    public function test_loop_detection_breaks_infinite_tool_repetition(): void
    {
        // Mock simulant un LLM bloqué dans une boucle en appelant exactement le même outil avec les mêmes arguments
        $mockLoopLlm = new class implements LlmProviderInterface {
            private int $callCount = 0;

            public function chat(array $messages, array $tools = [], array $options = []): array
            {
                $this->callCount++;
                // Renvoie indéfiniment le même tool_call
                return [
                    'content' => "Boucle infinie",
                    'tool_calls' => [
                        [
                            'id' => 'call_loop_' . $this->callCount,
                            'type' => 'function',
                            'function' => [
                                'name' => 'get_orders_summary',
                                'arguments' => json_encode(['days' => 7]),
                            ],
                        ],
                    ],
                    'tokens_input' => 100,
                    'tokens_output' => 30,
                    'model_used' => 'test-llm',
                ];
            }
        };

        $orchestrator = new AiOrchestrator(
            llm: $mockLoopLlm,
            tools: new ToolRegistry()
        );

        $result = $orchestrator->sendMessage($this->shop, $this->user, "Test de détection de boucle répétitive.");

        // Doit s'interrompre proprement sans dépasser max_iterations et sans boucle infinie
        $this->assertEquals('success', $result['status']);
        $this->assertNotEmpty($result['message']['content']);
    }
}
