<?php

declare(strict_types=1);

namespace Contenir\Test\Database;

use function array_filter;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function preg_replace_callback;
use function strtr;
use function trim;

/**
 * Translates a MySQL schema dump, such as a site's database/schema.sql, into
 * statements SQLite accepts, so tests get the real tables without a MySQL
 * server. MySQL stays the schema of record; this is not a second schema.
 *
 * SQLite already accepts MySQL's type names (int unsigned, varchar(255),
 * datetime, longtext, JSON), so they are kept. What goes or changes:
 *
 * - Comments go, and SET, LOCK TABLES and UNLOCK TABLES statements.
 * - Table options after the closing parenthesis (ENGINE, CHARSET, COLLATE) go.
 * - An AUTO_INCREMENT column becomes INTEGER PRIMARY KEY AUTOINCREMENT, and
 *   the table's separate PRIMARY KEY goes.
 * - enum(...) becomes TEXT; CHARACTER SET, COLLATE, COMMENT and ON UPDATE
 *   CURRENT_TIMESTAMP on a column go.
 * - KEY, INDEX, FULLTEXT and SPATIAL indexes go; UNIQUE KEY name (columns)
 *   becomes UNIQUE (columns). Foreign keys and checks stay.
 *
 * Quoted text is set aside first and put back last, so a comma, parenthesis,
 * keyword or comment marker inside a value or name is never read as SQL.
 *
 * @api
 */
final class SqliteSchema
{
    private const string QUOTED_OR_COMMENT = <<<'REGEX'
        /(--[^\n]*|\#[^\n]*|\/\*.*?(?:\*\/|\z))|('(?:[^'\\]|\\.|'')*(?:'|\z)|"(?:[^"\\]|\\.|"")*(?:"|\z)|`[^`]*(?:`|\z))/s
        REGEX;

    private const string STATEMENT = '/^\s*(?:SET|LOCK\s+TABLES|UNLOCK\s+TABLES)\b[^;]*;[ \t]*\n?/mi';

    private const string TABLE = '/(CREATE\s+TABLE\b[^(;]*)\((?<body>(?:[^()]++|(?<group>\((?:[^()]++|(?&group))*\)))*)\)[^;]*(?:;|\z)/i';

    private const string DEFINITION = '/(?:[^(),]++|(?<group>\((?:[^()]++|(?&group))*\)))+/';

    private const string AUTO_INCREMENT = '/^(\x01\d+\x01|\w+)\s.*\bAUTO_INCREMENT\b/is';

    private const string PRIMARY_KEY = '/^PRIMARY\s+KEY\b/i';

    private const string INDEX = '/^(?:KEY|INDEX|FULLTEXT|SPATIAL)\b/i';

    private const array REWRITES = [
        '/\benum\s*\([^)]*\)/i'                                         => 'TEXT',
        '/\s+(?:CHARACTER\s+SET|CHARSET)\s+\w+/i'                       => '',
        '/\s+COLLATE\s+\w+/i'                                           => '',
        '/\s+COMMENT\s+\x01\d+\x01/i'                                   => '',
        '/\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP(?:\(\d*\))?/i'             => '',
        '/^UNIQUE\s*(?:(?:KEY|INDEX)\b\s*)?(?:\x01\d+\x01|\w+)?\s*\(/i' => 'UNIQUE (',
    ];

    public static function fromMysql(string $mysql): string
    {
        /** @var array<string, string> $quoted */
        $quoted = [];
        $sql    = (string) preg_replace_callback(
            self::QUOTED_OR_COMMENT,
            /** @param array<array-key, string> $match */
            static function (array $match) use (&$quoted): string {
                if (null === ($match[2] ?? null)) {
                    return '';
                }

                $placeholder          = "\x01" . count($quoted) . "\x01";
                $quoted[$placeholder] = $match[2];

                return $placeholder;
            },
            $mysql,
        );

        $sql = (string) preg_replace(self::STATEMENT, replacement: '', subject: $sql);
        $sql = (string) preg_replace_callback(
            self::TABLE,
            /** @param array<array-key, string> $match */
            static fn(array $match): string => ($match[1] ?? '') . '(' . self::body($match['body'] ?? '') . ');',
            $sql,
        );

        return strtr($sql, $quoted);
    }

    private static function body(string $body): string
    {
        $matches = [];
        preg_match_all(self::DEFINITION, $body, $matches);
        $definitions = array_map(trim(...), $matches[0] ?? []);
        $dropped     = [self::INDEX];
        foreach ($definitions as $definition) {
            if (1 !== preg_match(self::AUTO_INCREMENT, $definition)) {
                continue;
            }

            $dropped[] = self::PRIMARY_KEY;
        }

        $definitions = array_filter(
            $definitions,
            static fn(string $definition): bool => (
                [] === array_filter(
                    $dropped,
                    static fn(string $pattern): bool => 1 === preg_match($pattern, $definition),
                )
            ),
        );

        return "\n  " . implode(",\n  ", array_map(self::definition(...), $definitions)) . "\n";
    }

    private static function definition(string $definition): string
    {
        $name = [];
        if (1 === preg_match(self::AUTO_INCREMENT, $definition, $name)) {
            return ($name[1] ?? '') . ' INTEGER PRIMARY KEY AUTOINCREMENT';
        }

        return (string) preg_replace(array_keys(self::REWRITES), self::REWRITES, $definition);
    }
}
