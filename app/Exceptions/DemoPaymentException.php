<?php

namespace App\Exceptions;

use RuntimeException;

class DemoPaymentException extends RuntimeException
{
    public function __construct(public string $reason, public int $httpStatus = 422)
    {
        parent::__construct($reason);
    }
}
