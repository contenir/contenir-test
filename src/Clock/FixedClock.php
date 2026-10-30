<?php

declare(strict_types=1);

namespace Contenir\Test\Clock;

use DateInvalidTimeZoneException;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Override;
use Psr\Clock\ClockInterface;

/**
 * A PSR-20 clock stopped at one moment, so dates a test sees are predictable.
 *
 *     $clock = FixedClock::at('2026-10-30 09:00', 'Australia/Melbourne');
 *
 * @api
 */
final readonly class FixedClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $now,
    ) {}

    /**
     * A clock stopped at $time, read in $timezone.
     *
     * @throws DateInvalidTimeZoneException When $timezone isn't one.
     * @throws DateMalformedStringException When $time isn't a date and time.
     */
    public static function at(string $time, string $timezone = 'UTC'): self
    {
        return new self(new DateTimeImmutable($time, new DateTimeZone($timezone)));
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
