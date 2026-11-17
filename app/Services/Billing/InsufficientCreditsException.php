<?php

namespace App\Services\Billing;

use RuntimeException;

class InsufficientCreditsException extends RuntimeException
{
    public function __construct(public readonly int $required, public readonly int $available)
    {
        parent::__construct('Not enough credits for this generation.');
    }
}
