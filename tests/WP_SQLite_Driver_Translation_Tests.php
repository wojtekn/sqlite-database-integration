<?php

require_once __DIR__ . '/../wp-includes/sqlite-ast/class-wp-sqlite-driver.php';
require_once __DIR__ . '/../wp-includes/sqlite-ast/class-wp-sqlite-token-factory.php';
require_once __DIR__ . '/../wp-includes/sqlite-ast/class-wp-sqlite-token.php';

use PHPUnit\Framework\TestCase;
use WIP\WP_SQLite_Driver;

class WP_SQLite_Driver_Translation_Tests extends TestCase {
	const GRAMMAR_PATH = __DIR__ . '/../wp-includes/mysql/mysql-grammar.php';

	/**
	 * @var WP_Parser_Grammar
	 */
	private static $grammar;

	public static function setUpBeforeClass(): void {
		self::$grammar = new WP_Parser_Grammar( include self::GRAMMAR_PATH );
	}

	public function testSelect(): void {
		$this->assertQuery(
			'SELECT 1',
			'SELECT 1'
		);

		$this->assertQuery(
			'SELECT * FROM "t"',
			'SELECT * FROM t'
		);

		$this->assertQuery(
			'SELECT "c" FROM "t"',
			'SELECT c FROM t'
		);

		$this->assertQuery(
			'SELECT ALL "c" FROM "t"',
			'SELECT ALL c FROM t'
		);

		$this->assertQuery(
			'SELECT DISTINCT "c" FROM "t"',
			'SELECT DISTINCT c FROM t'
		);

		$this->assertQuery(
			'SELECT "c1" , "c2" FROM "t"',
			'SELECT c1, c2 FROM t'
		);

		$this->assertQuery(
			'SELECT "t"."c" FROM "t"',
			'SELECT t.c FROM t'
		);

		$this->assertQuery(
			'SELECT "c1" FROM "t" WHERE "c2" = \'abc\'',
			"SELECT c1 FROM t WHERE c2 = 'abc'"
		);

		$this->assertQuery(
			'SELECT "c" FROM "t" GROUP BY "c"',
			'SELECT c FROM t GROUP BY c'
		);

		$this->assertQuery(
			'SELECT "c" FROM "t" ORDER BY "c" ASC',
			'SELECT c FROM t ORDER BY c ASC'
		);

		$this->assertQuery(
			'SELECT "c" FROM "t" LIMIT 10',
			'SELECT c FROM t LIMIT 10'
		);

		$this->assertQuery(
			'SELECT "c" FROM "t" GROUP BY "c" HAVING COUNT ( "c" ) > 1',
			'SELECT c FROM t GROUP BY c HAVING COUNT(c) > 1'
		);

		$this->assertQuery(
			'SELECT * FROM "t1" LEFT JOIN "t2" ON "t1"."id" = "t2"."t1_id" WHERE "t1"."name" = \'abc\'',
			"SELECT * FROM t1 LEFT JOIN t2 ON t1.id = t2.t1_id WHERE t1.name = 'abc'"
		);
	}

	public function testInsert(): void {
		$this->assertQuery(
			'INSERT INTO "t" ( "c" ) VALUES ( 1 )',
			'INSERT INTO t (c) VALUES (1)'
		);

		$this->assertQuery(
			'INSERT INTO "s"."t" ( "c" ) VALUES ( 1 )',
			'INSERT INTO s.t (c) VALUES (1)'
		);

		$this->assertQuery(
			'INSERT INTO "t" ( "c1" , "c2" ) VALUES ( 1 , 2 )',
			'INSERT INTO t (c1, c2) VALUES (1, 2)'
		);

		$this->assertQuery(
			'INSERT INTO "t" ( "c" ) VALUES ( 1 ) , ( 2 )',
			'INSERT INTO t (c) VALUES (1), (2)'
		);

		$this->assertQuery(
			'INSERT INTO "t1" SELECT * FROM "t2"',
			'INSERT INTO t1 SELECT * FROM t2'
		);
	}

	public function testReplace(): void {
		$this->assertQuery(
			'REPLACE INTO "t" ( "c" ) VALUES ( 1 )',
			'REPLACE INTO t (c) VALUES (1)'
		);

		$this->assertQuery(
			'REPLACE INTO "s"."t" ( "c" ) VALUES ( 1 )',
			'REPLACE INTO s.t (c) VALUES (1)'
		);

		$this->assertQuery(
			'REPLACE INTO "t" ( "c1" , "c2" ) VALUES ( 1 , 2 )',
			'REPLACE INTO t (c1, c2) VALUES (1, 2)'
		);

		$this->assertQuery(
			'REPLACE INTO "t" ( "c" ) VALUES ( 1 ) , ( 2 )',
			'REPLACE INTO t (c) VALUES (1), (2)'
		);

		$this->assertQuery(
			'REPLACE INTO "t1" SELECT * FROM "t2"',
			'REPLACE INTO t1 SELECT * FROM t2'
		);
	}

	public function testUpdate(): void {
		$this->assertQuery(
			'UPDATE "t" SET "c" = 1',
			'UPDATE t SET c = 1'
		);

		$this->assertQuery(
			'UPDATE "s"."t" SET "c" = 1',
			'UPDATE s.t SET c = 1'
		);

		$this->assertQuery(
			'UPDATE "t" SET "c1" = 1 , "c2" = 2',
			'UPDATE t SET c1 = 1, c2 = 2'
		);

		$this->assertQuery(
			'UPDATE "t" SET "c" = 1 WHERE "c" = 2',
			'UPDATE t SET c = 1 WHERE c = 2'
		);
	}

	public function testDelete(): void {
		$this->assertQuery(
			'DELETE FROM "t"',
			'DELETE FROM t'
		);

		$this->assertQuery(
			'DELETE FROM "s"."t"',
			'DELETE FROM s.t'
		);

		$this->assertQuery(
			'DELETE FROM "t" WHERE "c" = 1',
			'DELETE FROM t WHERE c = 1'
		);
	}

	private function assertQuery( $expected, string $query ): void {
		$driver = new WP_SQLite_Driver( new PDO( 'sqlite::memory:' ) );
		$driver->query( $query );

		$executed_queries = array_column( $driver->executed_sqlite_queries, 'sql' );
		if ( count( $executed_queries ) > 2 ) {
			// Remove BEGIN and COMMIT/ROLLBACK queries.
			$executed_queries = array_values( array_slice( $executed_queries, 1, -1, true ) );
		}

		if ( ! is_array( $expected ) ) {
			$expected = array( $expected );
		}
		$this->assertSame( $expected, $executed_queries );
	}
}
