<?php

declare(strict_types=1);

namespace Contenir\Test\Tests\Unit\Container;

use Contenir\Test\Container\ArrayContainer;
use Contenir\Test\Container\ServiceFailedException;
use Contenir\Test\Container\ServiceNotFoundException;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;

#[Group('unit')]
final class ArrayContainerTest extends TestCase
{
    #[Test]
    public function aFailingFactoryFailsWithItsExceptionAsThePreviousOne(): void
    {
        $failure   = new LogicException('Bad config.');
        $container = new ArrayContainer(factories: [
            'service' => static fn(): never => throw $failure,
        ]);

        try {
            $container->get('service');
            static::fail('The factory did not fail.');
        } catch (ServiceFailedException $e) {
            static::assertSame(
                ['The factory for "service" failed: Bad config.', $failure],
                [$e->getMessage(), $e->getPrevious()],
            );
        }
    }

    #[Test]
    public function aMissingServiceIsNotFound(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('No service or factory for "missing".');

        (new ArrayContainer())->get('missing');
    }

    #[Test]
    public function aServiceIsPreferredToAFactoryForTheSameId(): void
    {
        $container = new ArrayContainer(['service' => 'given'], ['service' => static fn(): string => 'built']);

        static::assertSame('given', $container->get('service'));
    }

    #[Test]
    public function aServiceSetReplacesTheOneGivenAndTheFactory(): void
    {
        $container = new ArrayContainer(['given' => 1], ['built' => static fn(): int => 2]);
        $container->set('given', 3);
        $container->set('built', 4);
        $container->set('added', 5);

        static::assertSame(
            [3, 4, 5],
            [$container->get('given'), $container->get('built'), $container->get('added')],
        );
    }

    #[Test]
    public function buildsAServiceWithItsFactoryOnceAndKeepsIt(): void
    {
        $built     = 0;
        $container = new ArrayContainer(['config' => ['name' => 'site']], [
            'service' => static function (ContainerInterface $container) use (&$built): stdClass {
                $built++;
                $service       = new stdClass();
                $service->name = $container->get('config')['name'];

                return $service;
            },
        ]);

        $first = $container->get('service');

        static::assertSame([$first, 'site', 1], [$container->get('service'), $first->name, $built]);
    }

    #[Test]
    public function givesANullServiceRatherThanNotFindingIt(): void
    {
        $container = new ArrayContainer(['service' => null]);

        static::assertSame([true, null], [$container->has('service'), $container->get('service')]);
    }

    #[Test]
    public function givesAServiceAsItWasGiven(): void
    {
        $service = new stdClass();

        static::assertSame($service, (new ArrayContainer(['service' => $service]))->get('service'));
    }

    #[Test]
    public function hasTheServicesAndFactoriesItWasGivenAndNothingElse(): void
    {
        $container = new ArrayContainer(['service' => 1], ['built' => static fn(): int => 2]);

        static::assertSame(
            [true, true, false],
            [$container->has('service'), $container->has('built'), $container->has('missing')],
        );
    }
}
