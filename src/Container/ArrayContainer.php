<?php

declare(strict_types=1);

namespace Contenir\Test\Container;

use Override;
use Psr\Container\ContainerInterface;
use Throwable;

use function array_key_exists;

/**
 * A PSR-11 container over a plain array, for testing factories without a real
 * service manager.
 *
 * Services are returned as given. Factories are called with the container the
 * first time their service is asked for, and the result is kept, so a service
 * built from another is built once, as a shared service would be.
 *
 *     $container = new ArrayContainer(
 *         ['config' => ['site' => ['url' => 'https://example.test']]],
 *         [Mailer::class => static fn(ContainerInterface $c): Mailer => new Mailer($c->get('config'))],
 *     );
 *
 * @api
 */
final class ArrayContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed>                              $services
     * @param array<string, callable(ContainerInterface): mixed> $factories
     */
    public function __construct(
        private array $services = [],
        private readonly array $factories = [],
    ) {}

    /**
     * @throws ServiceNotFoundException When there is no service or factory for $id.
     * @throws ServiceFailedException When the factory for $id throws.
     */
    #[Override]
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->services)) {
            return $this->services[$id];
        }

        if (! array_key_exists($id, $this->factories)) {
            throw ServiceNotFoundException::for($id);
        }

        try {
            return $this->services[$id] = $this->factories[$id]($this);
        } catch (Throwable $e) {
            throw ServiceFailedException::for($id, $e);
        }
    }

    #[Override]
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services) || array_key_exists($id, $this->factories);
    }

    /**
     * Gives $id the service, in place of any it or its factory had.
     */
    public function set(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }
}
