<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

//Base class for business-rule violations. Rendered as HTTP 422 for API

abstract class DomainRuleException extends RuntimeException
{
    public function status(): int
    {
        return 422;
    }
}
