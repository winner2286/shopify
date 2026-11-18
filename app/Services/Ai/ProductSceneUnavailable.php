<?php

namespace App\Services\Ai;

use RuntimeException;

class ProductSceneUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message);
    }
}
