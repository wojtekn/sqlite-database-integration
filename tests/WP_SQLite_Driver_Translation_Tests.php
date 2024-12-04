<?php

require_once __DIR__ . '/../wp-includes/sqlite-ast/class-wp-sqlite-driver.php';
require_once __DIR__ . '/../wp-includes/sqlite-ast/class-wp-sqlite-information-schema-builder.php';
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

	/**
	 * @var WP_SQLite_Driver
	 */
	private $driver;

	public static function setUpBeforeClass(): void {
		self::$grammar = new WP_Parser_Grammar( include self::GRAMMAR_PATH );
	}

	public function setUp(): void {
		$this->driver = new WP_SQLite_Driver( 'wp', new PDO( 'sqlite::memory:' ) );
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

		// UPDATE with LIMIT.
		$this->assertQuery(
			'UPDATE "t" SET "c" = 1 WHERE rowid IN ( SELECT rowid FROM "t" LIMIT 1 )',
			'UPDATE t SET c = 1 LIMIT 1'
		);

		// UPDATE with ORDER BY and LIMIT.
		$this->assertQuery(
			'UPDATE "t" SET "c" = 1 WHERE rowid IN ( SELECT rowid FROM "t" ORDER BY "c" ASC LIMIT 1 )',
			'UPDATE t SET c = 1 ORDER BY c ASC LIMIT 1'
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

	public function testCreateTable(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "id" INTEGER )',
			'CREATE TABLE t (id INT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
			)
		);
	}

	public function testCreateTableWithMultipleColumns(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "id" INTEGER , "name" TEXT , "score" REAL DEFAULT 0.0 )',
			'CREATE TABLE t (id INT, name TEXT, score FLOAT DEFAULT 0.0)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'name', 2, null, 'YES', 'text', 65535, 65535, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'text', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'score', 3, '0.0', 'YES', 'float', null, null, 12, null, null, null, null, 'float', '', '', 'select,insert,update,references', '', '', null)",
			)
		);
	}

	public function testCreateTableWithBasicConstraints(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT )',
			'CREATE TABLE t (id INT NOT NULL PRIMARY KEY AUTO_INCREMENT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'NO', 'int', null, null, 10, 0, null, null, null, 'int', 'PRI', 'auto_increment', 'select,insert,update,references', '', '', null)",
			)
		);
	}

	public function testCreateTableWithEngine(): void {
		// ENGINE is not supported in SQLite, we save it in information schema.
		$this->assertQuery(
			'CREATE TABLE "t" ( "id" INTEGER )',
			'CREATE TABLE t (id INT) ENGINE=MyISAM'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'MyISAM', 'FIXED', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
			)
		);
	}

	public function testCreateTableWithCollate(): void {
		// COLLATE is not supported in SQLite, we save it in information schema.
		$this->assertQuery(
			'CREATE TABLE "t" ( "id" INTEGER )',
			'CREATE TABLE t (id INT) COLLATE utf8mb4_czech_ci'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_czech_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
			)
		);
	}

	public function testCreateTableWithPrimaryKey(): void {
		/*
		 * PRIMARY KEY without AUTOINCREMENT:
		 * In this case, integer must be represented as INT, not INTEGER. SQLite
		 * treats "INTEGER PRIMARY KEY" as an alias for ROWID, causing unintended
		 * auto-increment-like behavior for a non-autoincrement column.
		 *
		 * See:
		 *  https://www.sqlite.org/lang_createtable.html#rowids_and_the_integer_primary_key
		 */
		$this->assertQuery(
			'CREATE TABLE "t" ( "id" INT PRIMARY KEY )',
			'CREATE TABLE t (id INT PRIMARY KEY)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'NO', 'int', null, null, 10, 0, null, null, null, 'int', 'PRI', '', 'select,insert,update,references', '', '', null)",
			)
		);
	}

	public function testCreateTableWithPrimaryKeyAndAutoincrement(): void {
		// With AUTOINCREMENT, we expect "INTEGER".
		$this->assertQuery(
			'CREATE TABLE "t1" ( "id" INTEGER PRIMARY KEY AUTOINCREMENT )',
			'CREATE TABLE t1 (id INT PRIMARY KEY AUTO_INCREMENT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't1', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't1', 'id', 1, null, 'NO', 'int', null, null, 10, 0, null, null, null, 'int', 'PRI', 'auto_increment', 'select,insert,update,references', '', '', null)",
			)
		);

		// In SQLite, PRIMARY KEY must come before AUTOINCREMENT.
		$this->assertQuery(
			'CREATE TABLE "t2" ( "id" INTEGER PRIMARY KEY AUTOINCREMENT )',
			'CREATE TABLE t2 (id INT AUTO_INCREMENT PRIMARY KEY)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't2', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't2', 'id', 1, null, 'NO', 'int', null, null, 10, 0, null, null, null, 'int', 'PRI', 'auto_increment', 'select,insert,update,references', '', '', null)",
			)
		);

		// In SQLite, AUTOINCREMENT cannot be specified separately from PRIMARY KEY.
		$this->assertQuery(
			'CREATE TABLE "t3" ( "id" INTEGER PRIMARY KEY AUTOINCREMENT )',
			'CREATE TABLE t3 (id INT AUTO_INCREMENT, PRIMARY KEY(id))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't3', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't3', 'id', 1, null, 'NO', 'int', null, null, 10, 0, null, null, null, 'int', 'PRI', 'auto_increment', 'select,insert,update,references', '', '', null)",
			)
		);
	}

	public function testCreateTableWithIfNotExists(): void {
		$this->assertQuery(
			'CREATE TABLE IF NOT EXISTS "t" ( "id" INTEGER )',
			'CREATE TABLE IF NOT EXISTS t (id INT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
			)
		);
	}

	public function testCreateTableFromSelectQuery(): void {
		// CREATE TABLE AS SELECT ...
		$this->assertQuery(
			'CREATE TABLE "t1" AS SELECT * FROM "t2"',
			'CREATE TABLE t1 AS SELECT * FROM t2'
		);

		// CREATE TABLE SELECT ...
		// The "AS" keyword is optional in MySQL, but required in SQLite.
		$this->assertQuery(
			'CREATE TABLE "t1" AS SELECT * FROM "t2"',
			'CREATE TABLE t1 SELECT * FROM t2'
		);
	}

	public function testCreateTemporaryTable(): void {
		$this->assertQuery(
			'CREATE TEMPORARY TABLE "t" ( "id" INTEGER )',
			'CREATE TEMPORARY TABLE t (id INT)'
		);

		// With IF NOT EXISTS.
		$this->assertQuery(
			'CREATE TEMPORARY TABLE IF NOT EXISTS "t" ( "id" INTEGER )',
			'CREATE TEMPORARY TABLE IF NOT EXISTS t (id INT)'
		);
	}

	public function testAlterTableAddColumn(): void {
		$this->assertQuery(
			'ALTER TABLE "t" ADD COLUMN "a" INTEGER',
			'ALTER TABLE t ADD a INT'
		);
	}

	public function testAlterTableAddMultipleColumns(): void {
		$this->driver->get_pdo()->exec( 'CREATE TABLE t (id INT)' );
		$this->assertQuery(
			array(
				'ALTER TABLE "t" ADD COLUMN "a" INTEGER',
				'ALTER TABLE "t" ADD COLUMN "b" TEXT',
				'ALTER TABLE "t" ADD COLUMN "c" INTEGER',
			),
			'ALTER TABLE t ADD a INT, ADD b TEXT, ADD c BOOL'
		);
	}

	public function testAlterTableDropColumn(): void {
		$this->assertQuery(
			'ALTER TABLE "t" DROP COLUMN "a"',
			'ALTER TABLE t DROP a'
		);
	}

	public function testAlterTableDropMultipleColumns(): void {
		$this->driver->get_pdo()->exec( 'CREATE TABLE t (a INT, b INT)' );
		$this->assertQuery(
			array(
				'ALTER TABLE "t" DROP COLUMN "a"',
				'ALTER TABLE "t" DROP COLUMN "b"',
			),
			'ALTER TABLE t DROP a, DROP b'
		);
	}

	public function testAlterTableAddAndDropColumns(): void {
		$this->driver->get_pdo()->exec( 'CREATE TABLE t (a INT)' );
		$this->assertQuery(
			array(
				'ALTER TABLE "t" ADD COLUMN "b" INTEGER',
				'ALTER TABLE "t" DROP COLUMN "a"',
			),
			'ALTER TABLE t ADD b INT, DROP a'
		);
	}

	public function testBitDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "i1" INTEGER , "i2" INTEGER )',
			'CREATE TABLE t (i1 BIT, i2 BIT(10))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i1', 1, null, 'YES', 'bit', null, null, 1, null, null, null, null, 'bit(1)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i2', 2, null, 'YES', 'bit', null, null, 10, null, null, null, null, 'bit(10)', '', '', 'select,insert,update,references', '', '', null)",
			)
		);
	}

	public function testBooleanDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "i1" INTEGER , "i2" INTEGER )',
			'CREATE TABLE t (i1 BOOL, i2 BOOLEAN)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i1', 1, null, 'YES', 'tinyint', null, null, 3, 0, null, null, null, 'tinyint(1)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i2', 2, null, 'YES', 'tinyint', null, null, 3, 0, null, null, null, 'tinyint(1)', '', '', 'select,insert,update,references', '', '', null)",
			)
		);
	}

	public function testIntegerDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "i1" INTEGER , "i2" INTEGER , "i3" INTEGER , "i4" INTEGER , "i5" INTEGER , "i6" INTEGER )',
			'CREATE TABLE t (i1 TINYINT, i2 SMALLINT, i3 MEDIUMINT, i4 INT, i5 INTEGER, i6 BIGINT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i1', 1, null, 'YES', 'tinyint', null, null, 3, 0, null, null, null, 'tinyint', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i2', 2, null, 'YES', 'smallint', null, null, 5, 0, null, null, null, 'smallint', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i3', 3, null, 'YES', 'mediumint', null, null, 7, 0, null, null, null, 'mediumint', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i4', 4, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i5', 5, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i6', 6, null, 'YES', 'bigint', null, null, 19, 0, null, null, null, 'bigint', '', '', 'select,insert,update,references', '', '', null)",
			)
		);
	}

	public function testFloatDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "f1" REAL , "f2" REAL , "f3" REAL , "f4" REAL )',
			'CREATE TABLE t (f1 FLOAT, f2 DOUBLE, f3 DOUBLE PRECISION, f4 REAL)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f1', 1, null, 'YES', 'float', null, null, 12, null, null, null, null, 'float', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f2', 2, null, 'YES', 'double', null, null, 22, null, null, null, null, 'double', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f3', 3, null, 'YES', 'double', null, null, 22, null, null, null, null, 'double', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f4', 4, null, 'YES', 'double', null, null, 22, null, null, null, null, 'double', '', '', 'select,insert,update,references', '', '', null)",
			)
		);
	}

	public function testDecimalTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "f1" REAL , "f2" REAL , "f3" REAL , "f4" REAL )',
			'CREATE TABLE t (f1 DECIMAL, f2 DEC, f3 FIXED, f4 NUMERIC)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f1', 1, null, 'YES', 'decimal', null, null, 10, 0, null, null, null, 'decimal(10,0)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f2', 2, null, 'YES', 'decimal', null, null, 10, 0, null, null, null, 'decimal(10,0)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f3', 3, null, 'YES', 'decimal', null, null, 10, 0, null, null, null, 'decimal(10,0)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f4', 4, null, 'YES', 'decimal', null, null, 10, 0, null, null, null, 'decimal(10,0)', '', '', 'select,insert,update,references', '', '', null)",
			)
		);
	}

	public function testCharDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "c1" TEXT , "c2" TEXT )',
			'CREATE TABLE t (c1 CHAR, c2 CHAR(10))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c1', 1, null, 'YES', 'char', 1, 4, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'char(1)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c2', 2, null, 'YES', 'char', 10, 40, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'char(10)', '', '', 'select,insert,update,references', '', '', null)",
			)
		);
	}

	public function testVarcharDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "c1" TEXT , "c2" TEXT , "c3" TEXT )',
			'CREATE TABLE t (c1 VARCHAR(255), c2 CHAR VARYING(255), c3 CHARACTER VARYING(255))'
		);
	}

	public function testNationalCharDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "c1" TEXT , "c2" TEXT )',
			'CREATE TABLE t (c1 NATIONAL CHAR, c2 NCHAR)'
		);
	}

	public function testNcharVarcharDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "c1" TEXT , "c2" TEXT , "c3" TEXT )',
			'CREATE TABLE t (c1 NCHAR VARCHAR(255), c2 NCHAR VARYING(255), c3 NVARCHAR(255))'
		);
	}

	public function testNationalVarcharDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "c1" TEXT , "c2" TEXT , "c3" TEXT )',
			'CREATE TABLE t (c1 NATIONAL VARCHAR(255), c2 NATIONAL CHAR VARYING(255), c3 NATIONAL CHARACTER VARYING(255))'
		);
	}

	public function testTextDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "t1" TEXT , "t2" TEXT , "t3" TEXT , "t4" TEXT )',
			'CREATE TABLE t (t1 TINYTEXT, t2 TEXT, t3 MEDIUMTEXT, t4 LONGTEXT)'
		);
	}

	public function testEnumDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "e" TEXT )',
			'CREATE TABLE t (e ENUM("a", "b", "c"))'
		);
	}

	public function testDateAndTimeDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "d" TEXT , "t" TEXT , "dt" TEXT , "ts" TEXT , "y" TEXT )',
			'CREATE TABLE t (d DATE, t TIME, dt DATETIME, ts TIMESTAMP, y YEAR)'
		);
	}

	public function testBinaryDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "b" INTEGER , "v" BLOB )',
			'CREATE TABLE t (b BINARY, v VARBINARY(255))'
		);
	}

	public function testBlobDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "b1" BLOB , "b2" BLOB , "b3" BLOB , "b4" BLOB )',
			'CREATE TABLE t (b1 TINYBLOB, b2 BLOB, b3 MEDIUMBLOB, b4 LONGBLOB)'
		);
	}

	public function testBasicSpatialDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "g1" TEXT , "g2" TEXT , "g3" TEXT , "g4" TEXT )',
			'CREATE TABLE t (g1 GEOMETRY, g2 POINT, g3 LINESTRING, g4 POLYGON)'
		);
	}

	public function testMultiObjectSpatialDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "g1" TEXT , "g2" TEXT , "g3" TEXT )',
			'CREATE TABLE t (g1 MULTIPOINT, g2 MULTILINESTRING, g3 MULTIPOLYGON)'
		);
	}

	public function testGeometryCollectionDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "g1" TEXT , "g2" TEXT )',
			'CREATE TABLE t (g1 GEOMCOLLECTION, g2 GEOMETRYCOLLECTION)'
		);
	}

	public function testSerialDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE )',
			'CREATE TABLE t (id SERIAL)'
		);
	}

	public function testSystemVariables(): void {
		$this->assertQuery(
			'SELECT NULL',
			'SELECT @@sql_mode'
		);

		$this->assertQuery(
			'SELECT NULL',
			'SELECT @@SESSION.sql_mode'
		);

		$this->assertQuery(
			'SELECT NULL',
			'SELECT @@GLOBAL.sql_mode'
		);
	}

	private function assertQuery( $expected, string $query ): void {
		$this->driver->query( $query );

		// Check for SQLite syntax errors.
		// This ensures that invalid SQLite syntax will always fail, even if it
		// was the expected result. It prevents us from using wrong assertions.
		$error = $this->driver->get_error_message();
		if ( $error && preg_match( '/(SQLSTATE\[HY000].+syntax error\.)/i', $error, $matches ) ) {
			$this->fail(
				sprintf( "SQLite syntax error: %s\nMySQL query: %s", $matches[1], $query )
			);
		}

		$executed_queries = array_column( $this->driver->executed_sqlite_queries, 'sql' );

		// Remove BEGIN and COMMIT/ROLLBACK queries.
		if ( count( $executed_queries ) > 2 ) {
			$executed_queries = array_values( array_slice( $executed_queries, 1, -1, true ) );
		}

		// Remove "information_schema" queries.
		$executed_queries = array_values(
			array_filter(
				$executed_queries,
				function ( $query ) {
					return ! str_contains( $query, '_mysql_information_schema_' );
				}
			)
		);

		// Remove "select changes()" executed after some queries.
		if (
			count( $executed_queries ) > 1
			&& 'select changes()' === $executed_queries[ count( $executed_queries ) - 1 ] ) {
			array_pop( $executed_queries );
		}

		if ( ! is_array( $expected ) ) {
			$expected = array( $expected );
		}
		$this->assertSame( $expected, $executed_queries );
	}

	private function assertExecutedInformationSchemaQueries( array $expected ): void {
		// Collect and normalize "information_schema" queries.
		$queries = array();
		foreach ( $this->driver->executed_sqlite_queries as $query ) {
			if ( ! str_contains( $query['sql'], '_mysql_information_schema_' ) ) {
				continue;
			}

			// Normalize whitespace.
			$sql = trim( preg_replace( '/\s+/', ' ', $query['sql'] ) );

			// Inline parameters.
			$sql       = str_replace( '?', '%s', $sql );
			$queries[] = sprintf(
				$sql,
				...array_map(
					function ( $param ) {
						if ( null === $param ) {
							return 'null';
						}
						if ( is_string( $param ) ) {
							return $this->driver->get_pdo()->quote( $param );
						}
						return $param;
					},
					$query['params']
				)
			);
		}
		$this->assertSame( $expected, $queries );
	}
}
