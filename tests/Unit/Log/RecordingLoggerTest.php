<?php

declare(strict_types=1);

namespace Contenir\Test\Tests\Unit\Log;

use Contenir\Test\Log\RecordingLogger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Stringable;

#[Group('unit')]
final class RecordingLoggerTest extends TestCase
{
    private static function logger(): RecordingLogger
    {
        $logger = new RecordingLogger();
        $logger->info('Started.', ['id' => 1]);
        $logger->error(new class implements Stringable {
            public function __toString(): string
            {
                return 'Failed.';
            }
        });
        $logger->info('Finished.');

        return $logger;
    }

    #[Test]
    public function givesEveryMessageOnePerLine(): void
    {
        static::assertSame("Started.\nFailed.\nFinished.", self::logger()->text());
    }

    #[Test]
    public function givesEveryMessageOrThoseAtOneLevel(): void
    {
        $logger = self::logger();

        static::assertSame(
            [['Started.', 'Failed.', 'Finished.'], ['Failed.']],
            [$logger->messages(), $logger->messages(LogLevel::ERROR)],
        );
    }

    #[Test]
    public function givesTheRecordsAtOneLevel(): void
    {
        static::assertSame(
            [
                ['level' => LogLevel::INFO, 'message' => 'Started.', 'context' => ['id' => 1]],
                ['level' => LogLevel::INFO, 'message' => 'Finished.', 'context' => []],
            ],
            self::logger()->records(LogLevel::INFO),
        );
    }

    #[Test]
    public function hasNoRecordsBeforeAnythingIsLogged(): void
    {
        static::assertSame([[], ''], [(new RecordingLogger())->records(), (new RecordingLogger())->text()]);
    }

    #[Test]
    public function keepsEveryRecordInTheOrderItWasLogged(): void
    {
        static::assertSame(
            [
                ['level' => LogLevel::INFO, 'message' => 'Started.', 'context' => ['id' => 1]],
                ['level' => LogLevel::ERROR, 'message' => 'Failed.', 'context' => []],
                ['level' => LogLevel::INFO, 'message' => 'Finished.', 'context' => []],
            ],
            self::logger()->records(),
        );
    }
}
