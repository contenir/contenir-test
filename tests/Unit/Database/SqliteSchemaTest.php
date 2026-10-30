<?php

declare(strict_types=1);

namespace Contenir\Test\Tests\Unit\Database;

use Contenir\Test\Database\SqliteSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SqliteSchemaTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function schemaProvider(): array
    {
        return [
            'types SQLite accepts are kept, table options go'                     => [
                'CREATE TABLE IF NOT EXISTS `t` (`id` int unsigned NOT NULL, `name` varchar(255) DEFAULT NULL,'
                    . ' `price` decimal(10,2), `body` longtext) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
                "CREATE TABLE IF NOT EXISTS `t` (\n  `id` int unsigned NOT NULL,\n  `name` varchar(255) DEFAULT NULL,"
                    . "\n  `price` decimal(10,2),\n  `body` longtext\n);",
            ],
            'an auto-increment column becomes the primary key'                    => [
                'CREATE TABLE t (`id` int unsigned NOT NULL AUTO_INCREMENT, a int, PRIMARY KEY (`id`));',
                "CREATE TABLE t (\n  `id` INTEGER PRIMARY KEY AUTOINCREMENT,\n  a int\n);",
            ],
            'an auto-increment column after others'                               => [
                'CREATE TABLE t (a int, `id` int NOT NULL AUTO_INCREMENT, b int, PRIMARY KEY (`id`));',
                "CREATE TABLE t (\n  a int,\n  `id` INTEGER PRIMARY KEY AUTOINCREMENT,\n  b int\n);",
            ],
            'keywords in lower case'                                              => [
                'create table t (id int auto_increment, primary key (id), key k (id)) engine=innodb;',
                "create table t (\n  id INTEGER PRIMARY KEY AUTOINCREMENT\n);",
            ],
            'a primary key without an auto-increment column stays'                => [
                'CREATE TABLE t (a int, b int, PRIMARY KEY (a,b));',
                "CREATE TABLE t (\n  a int,\n  b int,\n  PRIMARY KEY (a,b)\n);",
            ],
            'an enum becomes text'                                                => [
                "CREATE TABLE t (`s` enum('a','b(c)','it''s','d\\'e') NOT NULL DEFAULT 'a', `e` ENUM (\"x\",\"y\"));",
                "CREATE TABLE t (\n  `s` TEXT NOT NULL DEFAULT 'a',\n  `e` TEXT\n);",
            ],
            'a column loses its character set, collation and comment'             => [
                "CREATE TABLE t (`n` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'it\\'s a, name' NOT NULL,"
                    . " `m` text CHARSET latin1 COMMENT 'it''s');",
                "CREATE TABLE t (\n  `n` varchar(9) NOT NULL,\n  `m` text\n);",
            ],
            'a timestamp loses its update'                                        => [
                'CREATE TABLE t (`u` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
                    . ' `v` datetime(6) ON UPDATE CURRENT_TIMESTAMP(6) NOT NULL);',
                "CREATE TABLE t (\n  `u` datetime DEFAULT CURRENT_TIMESTAMP,\n  `v` datetime(6) NOT NULL\n);",
            ],
            'indexes go'                                                          => [
                'CREATE TABLE t (a int, KEY `k` (`a`), INDEX i (a), FULLTEXT KEY f (a), SPATIAL KEY s (a), KEY(a));',
                "CREATE TABLE t (\n  a int\n);",
            ],
            'unique keys keep only their columns'                                 => [
                'CREATE TABLE t (a int, UNIQUE KEY `u` (`a`,`b`), UNIQUE INDEX u2 (b), UNIQUE (c), UNIQUE KEY(d), UNIQUE u3(e));',
                "CREATE TABLE t (\n  a int,\n  UNIQUE (`a`,`b`),\n  UNIQUE (b),\n  UNIQUE (c),\n  UNIQUE (d),\n  UNIQUE (e)\n);",
            ],
            'foreign keys and checks stay'                                        => [
                'CREATE TABLE t (a int, CONSTRAINT `fk` FOREIGN KEY (`a`) REFERENCES `p` (`id`) ON DELETE CASCADE,'
                    . ' FOREIGN KEY (a) REFERENCES p(id), CHECK (a > 0));',
                "CREATE TABLE t (\n  a int,\n  CONSTRAINT `fk` FOREIGN KEY (`a`) REFERENCES `p` (`id`) ON DELETE CASCADE,"
                    . "\n  FOREIGN KEY (a) REFERENCES p(id),\n  CHECK (a > 0)\n);",
            ],
            'quoted commas, parentheses and comment markers stay in their values' => [
                "CREATE TABLE t (a varchar(9) DEFAULT 'x,y)', b varchar(9) DEFAULT \"(z\", c varchar(9) DEFAULT 'q\\' -- r',"
                    . ' `d,e` int);',
                "CREATE TABLE t (\n  a varchar(9) DEFAULT 'x,y)',\n  b varchar(9) DEFAULT \"(z\",\n  c varchar(9) DEFAULT 'q\\' -- r',"
                    . "\n  `d,e` int\n);",
            ],
            'SET and table lock statements go'                                    => [
                "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nLOCK TABLES `t` WRITE;\nUNLOCK TABLES;\nDROP TABLE IF EXISTS `t`;",
                'DROP TABLE IF EXISTS `t`;',
            ],
            'comments go, with any quotes in them'                                => [
                "-- it's\n# why's\n/* a 'b */CREATE TABLE t (\n  -- the id's column\n  id int /* x's */ # y's\n);",
                "\n\nCREATE TABLE t (\n  id int\n);",
            ],
            'a comment at the end goes'                                           => [
                "CREATE TABLE t (a int);\n-- trailing",
                "CREATE TABLE t (\n  a int\n);\n",
            ],
            'an unclosed block comment goes'                                      => [
                "CREATE TABLE t (a int);\n/* open",
                "CREATE TABLE t (\n  a int\n);\n",
            ],
            'every table and the statements between them'                         => [
                "DROP TABLE IF EXISTS `a`;\nCREATE TABLE `a` (x int);\nCREATE TABLE b (y int) ENGINE=InnoDB;\nINSERT INTO b VALUES (1);\n",
                "DROP TABLE IF EXISTS `a`;\nCREATE TABLE `a` (\n  x int\n);\nCREATE TABLE b (\n  y int\n);\nINSERT INTO b VALUES (1);\n",
            ],
            'an unclosed table is left as it is'                                  => [
                'CREATE TABLE t (a int, b varchar(9) DEFAULT \'x\\\'',
                'CREATE TABLE t (a int, b varchar(9) DEFAULT \'x\\\'',
            ],
            'a table without its semicolon ends the schema'                       => [
                'CREATE TABLE t (a int) ENGINE=InnoDB',
                "CREATE TABLE t (\n  a int\n);",
            ],
            'no tables'                                                           => ['', ''],
        ];
    }

    #[Test]
    #[DataProvider('schemaProvider')]
    public function translatesAMysqlSchemaForSqlite(string $mysql, string $expected): void
    {
        static::assertSame($expected, SqliteSchema::fromMysql($mysql));
    }
}
