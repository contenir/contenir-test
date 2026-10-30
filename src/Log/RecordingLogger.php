<?php

declare(strict_types=1);

namespace Contenir\Test\Log;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

use function array_column;
use function implode;

/**
 * A PSR-3 logger that keeps every record, for a test to read back.
 *
 * @api
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    private array $records = [];

    /**
     * @param array<mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }

    /**
     * Every message, or only those at $level.
     *
     * @return list<string>
     */
    public function messages(mixed $level = null): array
    {
        return array_column($this->records($level), 'message');
    }

    /**
     * Every record in the order it was logged, or only those at $level.
     *
     * @return list<array{level: mixed, message: string, context: array<mixed>}>
     */
    public function records(mixed $level = null): array
    {
        $records = [];
        foreach ($this->records as $record) {
            if (null !== $level && $record['level'] !== $level) {
                continue;
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * Every message, one per line.
     */
    public function text(): string
    {
        return implode("\n", $this->messages());
    }
}
