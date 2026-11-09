<?php

namespace Tests\Unit;

use App\Services\Ai\AiOrchestrator;
use Tests\TestCase;

class AiGreetingCleanerTest extends TestCase
{
    public function test_greeting_is_kept_when_user_greets_even_in_ongoing_conversation(): void
    {
        $o = app(AiOrchestrator::class);

        $out = $o->cleanUnwantedGreetings('Bonjour ! Content de te retrouver sur akif 😊', 'bonjour', 3);

        $this->assertStringStartsWith('Bonjour', $out);
    }

    public function test_greeting_is_stripped_when_user_did_not_greet(): void
    {
        $o = app(AiOrchestrator::class);

        $out = $o->cleanUnwantedGreetings('Bonjour ! Voici tes commandes.', 'mes commandes ?', 3);

        $this->assertStringStartsNotWith('Bonjour', $out);
    }
}
