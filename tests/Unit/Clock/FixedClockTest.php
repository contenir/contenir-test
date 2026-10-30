<?php

declare(strict_types=1);

namespace Contenir\Test\Tests\Unit\Clock;

use Contenir\Test\Clock\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class FixedClockTest extends TestCase
{
    #[Test]
    public function aClockAtATimeReadsItInTheTimezoneGiven(): void
    {
        static::assertSame(
            '2026-10-30T09:00:00+11:00',
            FixedClock::at('2026-10-30 09:00', 'Australia/Melbourne')->now()->format('c'),
        );
    }

    #[Test]
    public function aClockAtATimeReadsItInUtc(): void
    {
        static::assertSame('2026-10-30T09:00:00+00:00', FixedClock::at('2026-10-30 09:00')->now()->format('c'));
    }

    #[Test]
    public function givesTheSameMomentEveryTime(): void
    {
        $now   = new DateTimeImmutable('2026-10-30 09:00:00');
        $clock = new FixedClock($now);

        static::assertSame([$now, $now], [$clock->now(), $clock->now()]);
    }
}
