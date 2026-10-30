<?php

declare(strict_types=1);

namespace Contenir\Test\Container;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * A factory threw while building a service; the factory's exception is the previous one.
 *
 * @api
 */
final class ServiceFailedException extends RuntimeException implements ContainerExceptionInterface
{
    public static function for(string $id, Throwable $previous): self
    {
        return new self("The factory for \"{$id}\" failed: {$previous->getMessage()}", previous: $previous);
    }
}
