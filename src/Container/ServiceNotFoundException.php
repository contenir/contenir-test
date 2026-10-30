<?php

declare(strict_types=1);

namespace Contenir\Test\Container;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * @api
 */
final class ServiceNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
    public static function for(string $id): self
    {
        return new self("No service or factory for \"{$id}\".");
    }
}
