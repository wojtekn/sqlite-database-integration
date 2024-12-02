<?php

// @TODO: Remove the namespace and use statements when replacing the old driver.
namespace WIP;

use Exception;
use PDO;
use PDOException;
use PDOStatement;
use SQLite3;
use WP_MySQL_Lexer;
use WP_MySQL_Parser;
use WP_MySQL_Token;
use WP_Parser_Grammar;
use WP_Parser_Node;
use WP_SQLite_PDO_User_Defined_Functions;

class WP_SQLite_Driver {
	const GRAMMAR_PATH = __DIR__ . '/../../wp-includes/mysql/mysql-grammar.php';

	const SQLITE_BUSY   = 5;
	const SQLITE_LOCKED = 6;

	const DATA_TYPE_MAP = array(
		// Numeric data types:
		WP_MySQL_Lexer::BIT_SYMBOL                => 'INTEGER',
		WP_MySQL_Lexer::BOOL_SYMBOL               => 'INTEGER',
		WP_MySQL_Lexer::BOOLEAN_SYMBOL            => 'INTEGER',
		WP_MySQL_Lexer::TINYINT_SYMBOL            => 'INTEGER',
		WP_MySQL_Lexer::SMALLINT_SYMBOL           => 'INTEGER',
		WP_MySQL_Lexer::MEDIUMINT_SYMBOL          => 'INTEGER',
		WP_MySQL_Lexer::INT_SYMBOL                => 'INTEGER',
		WP_MySQL_Lexer::INTEGER_SYMBOL            => 'INTEGER',
		WP_MySQL_Lexer::BIGINT_SYMBOL             => 'INTEGER',
		WP_MySQL_Lexer::FLOAT_SYMBOL              => 'REAL',
		WP_MySQL_Lexer::DOUBLE_SYMBOL             => 'REAL',
		WP_MySQL_Lexer::REAL_SYMBOL               => 'REAL',
		WP_MySQL_Lexer::DECIMAL_SYMBOL            => 'REAL',
		WP_MySQL_Lexer::DEC_SYMBOL                => 'REAL',
		WP_MySQL_Lexer::FIXED_SYMBOL              => 'REAL',
		WP_MySQL_Lexer::NUMERIC_SYMBOL            => 'REAL',

		// String data types:
		WP_MySQL_Lexer::CHAR_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::VARCHAR_SYMBOL            => 'TEXT',
		WP_MySQL_Lexer::NCHAR_SYMBOL              => 'TEXT',
		WP_MySQL_Lexer::NVARCHAR_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::TINYTEXT_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::TEXT_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::MEDIUMTEXT_SYMBOL         => 'TEXT',
		WP_MySQL_Lexer::LONGTEXT_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::ENUM_SYMBOL               => 'TEXT',

		// Date and time data types:
		WP_MySQL_Lexer::DATE_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::TIME_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::DATETIME_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::TIMESTAMP_SYMBOL          => 'TEXT',
		WP_MySQL_Lexer::YEAR_SYMBOL               => 'TEXT',

		// Binary data types:
		WP_MySQL_Lexer::BINARY_SYMBOL             => 'INTEGER',
		WP_MySQL_Lexer::VARBINARY_SYMBOL          => 'BLOB',
		WP_MySQL_Lexer::TINYBLOB_SYMBOL           => 'BLOB',
		WP_MySQL_Lexer::BLOB_SYMBOL               => 'BLOB',
		WP_MySQL_Lexer::MEDIUMBLOB_SYMBOL         => 'BLOB',
		WP_MySQL_Lexer::LONGBLOB_SYMBOL           => 'BLOB',

		// Spatial data types:
		WP_MySQL_Lexer::GEOMETRY_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::POINT_SYMBOL              => 'TEXT',
		WP_MySQL_Lexer::LINESTRING_SYMBOL         => 'TEXT',
		WP_MySQL_Lexer::POLYGON_SYMBOL            => 'TEXT',
		WP_MySQL_Lexer::MULTIPOINT_SYMBOL         => 'TEXT',
		WP_MySQL_Lexer::MULTILINESTRING_SYMBOL    => 'TEXT',
		WP_MySQL_Lexer::MULTIPOLYGON_SYMBOL       => 'TEXT',
		WP_MySQL_Lexer::GEOMCOLLECTION_SYMBOL     => 'TEXT',
		WP_MySQL_Lexer::GEOMETRYCOLLECTION_SYMBOL => 'TEXT',

		// SERIAL, SET, and JSON types are handled in the translation process.
	);

	const DATA_TYPES_CACHE_TABLE = '_mysql_data_types_cache';

	const CREATE_DATA_TYPES_CACHE_TABLE = 'CREATE TABLE IF NOT EXISTS _mysql_data_types_cache (
		`table` TEXT NOT NULL,
		`column_or_index` TEXT NOT NULL,
		`mysql_type` TEXT NOT NULL,
		PRIMARY KEY(`table`, `column_or_index`)
	);';

	/**
	 * @var WP_Parser_Grammar
	 */
	private static $grammar;

	/**
	 * Class variable to reference to the PDO instance.
	 *
	 * @access private
	 *
	 * @var PDO object
	 */
	private $pdo;

	/**
	 * The database version.
	 *
	 * This is used here to avoid PHP warnings in the health screen.
	 *
	 * @var string
	 */
	public $client_info = '';

	/**
	 * Last executed MySQL query.
	 *
	 * @var string
	 */
	public $mysql_query;

	/**
	 * A list of executed SQLite queries.
	 *
	 * @var array
	 */
	public $executed_sqlite_queries = array();

	/**
	 * The affected table name.
	 *
	 * @var array
	 */
	private $table_name = array();

	/**
	 * The type of the executed query (SELECT, INSERT, etc).
	 *
	 * @var array
	 */
	private $query_type = array();

	/**
	 * The columns to insert.
	 *
	 * @var array
	 */
	private $insert_columns = array();

	/**
	 * Class variable to store the result of the query.
	 *
	 * @access private
	 *
	 * @var array reference to the PHP object
	 */
	private $results = null;

	/**
	 * Class variable to check if there is an error.
	 *
	 * @var boolean
	 */
	public $is_error = false;

	/**
	 * Class variable to store the file name and function to cause error.
	 *
	 * @access private
	 *
	 * @var array
	 */
	private $errors;

	/**
	 * Class variable to store the error messages.
	 *
	 * @access private
	 *
	 * @var array
	 */
	private $error_messages = array();

	/**
	 * Class variable to store the affected row id.
	 *
	 * @var int integer
	 * @access private
	 */
	private $last_insert_id;

	/**
	 * Class variable to store the number of rows affected.
	 *
	 * @var int integer
	 */
	private $affected_rows;

	/**
	 * Variable to emulate MySQL affected row.
	 *
	 * @var integer
	 */
	private $num_rows;

	/**
	 * Return value from query().
	 *
	 * Each query has its own return value.
	 *
	 * @var mixed
	 */
	private $return_value;

	/**
	 * Variable to keep track of nested transactions level.
	 *
	 * @var int
	 */
	private $transaction_level = 0;

	/**
	 * Value returned by the last exec().
	 *
	 * @var mixed
	 */
	private $last_exec_returned;

	/**
	 * The PDO fetch mode passed to query().
	 *
	 * @var mixed
	 */
	private $pdo_fetch_mode;

	/**
	 * Associative array with list of system (non-WordPress) tables.
	 *
	 * @var array  [tablename => tablename]
	 */
	private $sqlite_system_tables = array();

	/**
	 * The last error message from SQLite.
	 *
	 * @var string
	 */
	private $last_sqlite_error;

	/**
	 * Constructor.
	 *
	 * Create PDO object, set user defined functions and initialize other settings.
	 * Don't use parent::__construct() because this class does not only returns
	 * PDO instance but many others jobs.
	 *
	 * @param PDO $pdo The PDO object.
	 */
	public function __construct( $pdo = null ) {
		if ( ! $pdo ) {
			if ( ! is_file( FQDB ) ) {
				$this->prepare_directory();
			}

			$locked      = false;
			$status      = 0;
			$err_message = '';
			do {
				try {
					$options = array(
						PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
						PDO::ATTR_STRINGIFY_FETCHES => true,
						PDO::ATTR_TIMEOUT           => 5,
					);

					$dsn = 'sqlite:' . FQDB;
					$pdo = new PDO( $dsn, null, null, $options ); // phpcs:ignore WordPress.DB.RestrictedClasses
				} catch ( PDOException $ex ) {
					$status = $ex->getCode();
					if ( self::SQLITE_BUSY === $status || self::SQLITE_LOCKED === $status ) {
						$locked = true;
					} else {
						$err_message = $ex->getMessage();
					}
				}
			} while ( $locked );

			if ( $status > 0 ) {
				$message                = sprintf(
					'<p>%s</p><p>%s</p><p>%s</p>',
					'Database initialization error!',
					"Code: $status",
					"Error Message: $err_message"
				);
				$this->is_error         = true;
				$this->error_messages[] = $message;
				return;
			}
		}

		new WP_SQLite_PDO_User_Defined_Functions( $pdo );

		// MySQL data comes across stringified by default.
		$pdo->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, true ); // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
		$pdo->query( self::CREATE_DATA_TYPES_CACHE_TABLE );

		/*
		 * A list of system tables lets us emulate information_schema
		 * queries without returning extra tables.
		 */
		$this->sqlite_system_tables ['sqlite_sequence']              = 'sqlite_sequence';
		$this->sqlite_system_tables [ self::DATA_TYPES_CACHE_TABLE ] = self::DATA_TYPES_CACHE_TABLE;

		$this->pdo = $pdo;

		// Load MySQL grammar.
		if ( null === self::$grammar ) {
			self::$grammar = new WP_Parser_Grammar( require self::GRAMMAR_PATH );
		}

		// Fixes a warning in the site-health screen.
		$this->client_info = SQLite3::version()['versionString'];

		register_shutdown_function( array( $this, '__destruct' ) );

		// WordPress happens to use no foreign keys.
		$statement = $this->pdo->query( 'PRAGMA foreign_keys' );
		// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
		if ( $statement->fetchColumn( 0 ) == '0' ) {
			$this->pdo->query( 'PRAGMA foreign_keys = ON' );
		}
		$this->pdo->query( 'PRAGMA encoding="UTF-8";' );

		$valid_journal_modes = array( 'DELETE', 'TRUNCATE', 'PERSIST', 'MEMORY', 'WAL', 'OFF' );
		if ( defined( 'SQLITE_JOURNAL_MODE' ) && in_array( SQLITE_JOURNAL_MODE, $valid_journal_modes, true ) ) {
			$this->pdo->query( 'PRAGMA journal_mode = ' . SQLITE_JOURNAL_MODE );
		}
	}

	/**
	 * Destructor
	 *
	 * If SQLITE_MEM_DEBUG constant is defined, append information about
	 * memory usage into database/mem_debug.txt.
	 *
	 * This definition is changed since version 1.7.
	 */
	public function __destruct() {
		if ( defined( 'SQLITE_MEM_DEBUG' ) && SQLITE_MEM_DEBUG ) {
			$max = ini_get( 'memory_limit' );
			if ( is_null( $max ) ) {
				$message = sprintf(
					'[%s] Memory_limit is not set in php.ini file.',
					gmdate( 'Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] )
				);
				error_log( $message );
				return;
			}
			if ( stripos( $max, 'M' ) !== false ) {
				$max = (int) $max * MB_IN_BYTES;
			}
			$peak = memory_get_peak_usage( true );
			$used = round( (int) $peak / (int) $max * 100, 2 );
			if ( $used > 90 ) {
				$message = sprintf(
					"[%s] Memory peak usage warning: %s %% used. (max: %sM, now: %sM)\n",
					gmdate( 'Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ),
					$used,
					$max,
					$peak
				);
				error_log( $message );
			}
		}
	}

	/**
	 * Get the PDO object.
	 *
	 * @return PDO
	 */
	public function get_pdo() {
		return $this->pdo;
	}

	/**
	 * Method to return inserted row id.
	 */
	public function get_insert_id() {
		return $this->last_insert_id;
	}

	/**
	 * Method to return the number of rows affected.
	 */
	public function get_affected_rows() {
		return $this->affected_rows;
	}

	/**
	 * Method to execute query().
	 *
	 * Divide the query types into seven different ones. That is to say:
	 *
	 * 1. SELECT SQL_CALC_FOUND_ROWS
	 * 2. INSERT
	 * 3. CREATE TABLE(INDEX)
	 * 4. ALTER TABLE
	 * 5. SHOW VARIABLES
	 * 6. DROP INDEX
	 * 7. THE OTHERS
	 *
	 * #1 is just a tricky play. See the private function handle_sql_count() in query.class.php.
	 * From #2 through #5 call different functions respectively.
	 * #6 call the ALTER TABLE query.
	 * #7 is a normal process: sequentially call prepare_query() and execute_query().
	 *
	 * #1 process has been changed since version 1.5.1.
	 *
	 * @param string $statement          Full SQL statement string.
	 * @param int    $mode               Not used.
	 * @param array  ...$fetch_mode_args Not used.
	 *
	 * @see PDO::query()
	 *
	 * @throws Exception    If the query could not run.
	 * @throws PDOException If the translated query could not run.
	 *
	 * @return mixed according to the query type
	 */
	public function query( $statement, $mode = PDO::FETCH_OBJ, ...$fetch_mode_args ) { // phpcs:ignore WordPress.DB.RestrictedClasses
		$this->flush();
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters queries before they are translated and run.
			 *
			 * Return a non-null value to cause query() to return early with that result.
			 * Use this filter to intercept queries that don't work correctly in SQLite.
			 *
			 * From within the filter you can do
			 *  function filter_sql ($result, $translator, $statement, $mode, $fetch_mode_args) {
			 *     if ( intercepting this query  ) {
			 *       return $translator->execute_sqlite_query( $statement );
			 *     }
			 *     return $result;
			 *   }
			 *
			 * @param null|array $result Default null to continue with the query.
			 * @param object     $translator The translator object. You can call $translator->execute_sqlite_query().
			 * @param string     $statement The statement passed.
			 * @param int        $mode Fetch mode: PDO::FETCH_OBJ, PDO::FETCH_CLASS, etc.
			 * @param array      $fetch_mode_args Variable arguments passed to query.
			 *
			 * @returns null|array Null to proceed, or an array containing a resultset.
			 * @since 2.1.0
			 */
			$pre = apply_filters( 'pre_query_sqlite_db', null, $this, $statement, $mode, $fetch_mode_args );
			if ( null !== $pre ) {
				return $pre;
			}
		}
		$this->pdo_fetch_mode = $mode;
		$this->mysql_query    = $statement;
		if (
			preg_match( '/^\s*START TRANSACTION/i', $statement )
			|| preg_match( '/^\s*BEGIN/i', $statement )
		) {
			return $this->begin_transaction();
		}
		if ( preg_match( '/^\s*COMMIT/i', $statement ) ) {
			return $this->commit();
		}
		if ( preg_match( '/^\s*ROLLBACK/i', $statement ) ) {
			return $this->rollback();
		}

		try {
			// Parse the MySQL query.
			$lexer  = new WP_MySQL_Lexer( $statement );
			$tokens = $lexer->remaining_tokens();

			$parser = new WP_MySQL_Parser( self::$grammar, $tokens );
			$ast    = $parser->parse();

			if ( null === $ast ) {
				throw new Exception( 'Failed to parse the MySQL query.' );
			}

			// Perform all the queries in a nested transaction.
			$this->begin_transaction();

			do {
				$error = null;
				try {
					$this->execute_mysql_query( $ast );
				} catch ( PDOException $error ) {
					if ( $error->getCode() !== self::SQLITE_BUSY ) {
						throw $error;
					}
				}
			} while ( $error );

			if ( function_exists( 'do_action' ) ) {
				/**
				 * Notifies that a query has been translated and executed.
				 *
				 * @param string $query The executed SQL query.
				 * @param string $query_type The type of the SQL query (e.g. SELECT, INSERT, UPDATE, DELETE).
				 * @param string $table_name The name of the table affected by the SQL query.
				 * @param array $insert_columns The columns affected by the INSERT query (if applicable).
				 * @param int $last_insert_id The ID of the last inserted row (if applicable).
				 * @param int $affected_rows The number of affected rows (if applicable).
				 *
				 * @since 0.1.0
				 */
				do_action(
					'sqlite_translated_query_executed',
					$this->mysql_query,
					$this->query_type,
					$this->table_name,
					$this->insert_columns,
					$this->last_insert_id,
					$this->affected_rows
				);
			}

			// Commit the nested transaction.
			$this->commit();

			return $this->return_value;
		} catch ( Exception $err ) {
			// Rollback the nested transaction.
			$this->rollback();
			if ( defined( 'PDO_DEBUG' ) && PDO_DEBUG === true ) {
				throw $err;
			}
			return $this->handle_error( $err );
		}
	}

	/**
	 * Method to return the queried result data.
	 *
	 * @return mixed
	 */
	public function get_query_results() {
		return $this->results;
	}

	/**
	 * Method to return the number of rows from the queried result.
	 */
	public function get_num_rows() {
		return $this->num_rows;
	}

	/**
	 * Method to return the queried results according to the query types.
	 *
	 * @return mixed
	 */
	public function get_return_value() {
		return $this->return_value;
	}

	/**
	 * Executes a query in SQLite.
	 *
	 * @param mixed $sql The query to execute.
	 * @param mixed $params The parameters to bind to the query.
	 * @throws PDOException If the query could not be executed.
	 * @return object {
	 *     The result of the query.
	 *
	 *     @type PDOStatement $stmt The executed statement
	 *     @type * $result The value returned by $stmt.
	 * }
	 */
	public function execute_sqlite_query( $sql, $params = array() ) {
		$this->executed_sqlite_queries[] = array(
			'sql'    => $sql,
			'params' => $params,
		);

		$stmt = $this->pdo->prepare( $sql );
		if ( false === $stmt || null === $stmt ) {
			$this->last_exec_returned = null;
			$info                     = $this->pdo->errorInfo();
			$this->last_sqlite_error  = $info[0] . ' ' . $info[2];
			throw new PDOException( implode( ' ', array( 'Error:', $info[0], $info[2], 'SQLite:', $sql ) ), $info[1] );
		}
		$returned                 = $stmt->execute( $params );
		$this->last_exec_returned = $returned;
		if ( ! $returned ) {
			$info                    = $stmt->errorInfo();
			$this->last_sqlite_error = $info[0] . ' ' . $info[2];
			throw new PDOException( implode( ' ', array( 'Error:', $info[0], $info[2], 'SQLite:', $sql ) ), $info[1] );
		}

		return $stmt;
	}

	/**
	 * Method to return error messages.
	 *
	 * @throws Exception If error is found.
	 *
	 * @return string
	 */
	public function get_error_message() {
		if ( count( $this->error_messages ) === 0 ) {
			$this->is_error       = false;
			$this->error_messages = array();
			return '';
		}

		if ( false === $this->is_error ) {
			return '';
		}

		$output  = '<div style="clear:both">&nbsp;</div>' . PHP_EOL;
		$output .= '<div class="queries" style="clear:both;margin-bottom:2px;border:red dotted thin;">' . PHP_EOL;
		$output .= '<p>MySQL query:</p>' . PHP_EOL;
		$output .= '<p>' . $this->mysql_query . '</p>' . PHP_EOL;
		$output .= '<p>Queries made or created this session were:</p>' . PHP_EOL;
		$output .= '<ol>' . PHP_EOL;
		foreach ( $this->executed_sqlite_queries as $q ) {
			$message = "Executing: {$q['sql']} | " . ( $q['params'] ? 'parameters: ' . implode( ', ', $q['params'] ) : '(no parameters)' );

			$output .= '<li>' . htmlspecialchars( $message ) . '</li>' . PHP_EOL;
		}
		$output .= '</ol>' . PHP_EOL;
		$output .= '</div>' . PHP_EOL;
		foreach ( $this->error_messages as $num => $m ) {
			$output .= '<div style="clear:both;margin-bottom:2px;border:red dotted thin;" class="error_message" style="border-bottom:dotted blue thin;">' . PHP_EOL;
			$output .= sprintf(
				'Error occurred at line %1$d in Function %2$s. Error message was: %3$s.',
				(int) $this->errors[ $num ]['line'],
				'<code>' . htmlspecialchars( $this->errors[ $num ]['function'] ) . '</code>',
				$m
			) . PHP_EOL;
			$output .= '</div>' . PHP_EOL;
		}

		try {
			throw new Exception();
		} catch ( Exception $e ) {
			$output .= '<p>Backtrace:</p>' . PHP_EOL;
			$output .= '<pre>' . $e->getTraceAsString() . '</pre>' . PHP_EOL;
		}

		return $output;
	}

	/**
	 * Begin a new transaction or nested transaction.
	 *
	 * @return boolean
	 */
	public function begin_transaction() {
		$success = false;
		try {
			if ( 0 === $this->transaction_level ) {
				$this->execute_sqlite_query( 'BEGIN' );
			} else {
				$this->execute_sqlite_query( 'SAVEPOINT LEVEL' . $this->transaction_level );
			}
			$success = $this->last_exec_returned;
		} finally {
			if ( $success ) {
				++$this->transaction_level;
				if ( function_exists( 'do_action' ) ) {
					/**
					 * Notifies that a transaction-related query has been translated and executed.
					 *
					 * @param string $command The SQL statement (one of "START TRANSACTION", "COMMIT", "ROLLBACK").
					 * @param bool $success Whether the SQL statement was successful or not.
					 * @param int $nesting_level The nesting level of the transaction.
					 *
					 * @since 0.1.0
					 */
					do_action( 'sqlite_transaction_query_executed', 'START TRANSACTION', (bool) $this->last_exec_returned, $this->transaction_level - 1 );
				}
			}
		}
		return $success;
	}

	/**
	 * Commit the current transaction or nested transaction.
	 *
	 * @return boolean True on success, false on failure.
	 */
	public function commit() {
		if ( 0 === $this->transaction_level ) {
			return false;
		}

		--$this->transaction_level;
		if ( 0 === $this->transaction_level ) {
			$this->execute_sqlite_query( 'COMMIT' );
		} else {
			$this->execute_sqlite_query( 'RELEASE SAVEPOINT LEVEL' . $this->transaction_level );
		}

		if ( function_exists( 'do_action' ) ) {
			do_action( 'sqlite_transaction_query_executed', 'COMMIT', (bool) $this->last_exec_returned, $this->transaction_level );
		}
		return $this->last_exec_returned;
	}

	/**
	 * Rollback the current transaction or nested transaction.
	 *
	 * @return boolean True on success, false on failure.
	 */
	public function rollback() {
		if ( 0 === $this->transaction_level ) {
			return false;
		}

		--$this->transaction_level;
		if ( 0 === $this->transaction_level ) {
			$this->execute_sqlite_query( 'ROLLBACK' );
		} else {
			$this->execute_sqlite_query( 'ROLLBACK TO SAVEPOINT LEVEL' . $this->transaction_level );
		}
		if ( function_exists( 'do_action' ) ) {
			do_action( 'sqlite_transaction_query_executed', 'ROLLBACK', (bool) $this->last_exec_returned, $this->transaction_level );
		}
		return $this->last_exec_returned;
	}

	/**
	 * Executes a MySQL query in SQLite.
	 *
	 * @param string $query The query.
	 *
	 * @throws Exception If the query is not supported.
	 */
	private function execute_mysql_query( WP_Parser_Node $ast ) {
		if ( 'query' !== $ast->rule_name ) {
			throw new Exception( sprintf( 'Expected "query" node, got: "%s"', $ast->rule_name ) );
		}

		$children = $ast->get_child_nodes();
		if ( count( $children ) !== 1 ) {
			throw new Exception( sprintf( 'Expected 1 child, got: %d', count( $children ) ) );
		}

		$ast = $children[0]->get_child_node();
		switch ( $ast->rule_name ) {
			case 'selectStatement':
				$this->query_type = 'SELECT';
				$query            = $this->translate( $ast->get_child() );
				$stmt             = $this->execute_sqlite_query( $query );
				$this->set_results_from_fetched_data(
					$stmt->fetchAll( $this->pdo_fetch_mode )
				);
				break;
			case 'insertStatement':
			case 'updateStatement':
				$this->execute_update_statement( $ast );
				break;
			case 'replaceStatement':
			case 'deleteStatement':
				if ( 'insertStatement' === $ast->rule_name ) {
					$this->query_type = 'INSERT';
				} elseif ( 'replaceStatement' === $ast->rule_name ) {
					$this->query_type = 'REPLACE';
				} elseif ( 'deleteStatement' === $ast->rule_name ) {
					$this->query_type = 'DELETE';
				}
				$query = $this->translate( $ast );
				$this->execute_sqlite_query( $query );
				$this->set_result_from_affected_rows();
				break;
			case 'createStatement':
				$this->query_type = 'CREATE';
				$subtree          = $ast->get_child_node();
				switch ( $subtree->rule_name ) {
					case 'createTable':
						$this->execute_create_table_statement( $ast );
						break;
					default:
						throw new Exception(
							sprintf(
								'Unsupported statement type: "%s" > "%s"',
								$ast->rule_name,
								$subtree->rule_name
							)
						);
				}
				break;
			case 'alterStatement':
				$this->query_type = 'ALTER';
				$subtree          = $ast->get_child_node();
				switch ( $subtree->rule_name ) {
					case 'alterTable':
						$this->execute_alter_table_statement( $ast );
						break;
					default:
						throw new Exception(
							sprintf(
								'Unsupported statement type: "%s" > "%s"',
								$ast->rule_name,
								$subtree->rule_name
							)
						);
				}
				break;
			case 'dropStatement':
				$this->query_type = 'DROP';
				$query            = $this->translate( $ast );
				$this->execute_sqlite_query( $query );
				$this->set_result_from_affected_rows();
				break;
			case 'setStatement':
				/*
				 * It would be lovely to support at least SET autocommit,
				 * but I don't think that is even possible with SQLite.
				 */
				$this->results = 0;
				break;
			default:
				throw new Exception( sprintf( 'Unsupported statement type: "%s"', $ast->rule_name ) );
		}
	}

	private function execute_update_statement( WP_Parser_Node $node ): void {
		// @TODO: Add support for UPDATE with multiple tables and JOINs.
		//        SQLite supports them in the FROM clause.

		$has_order = $node->has_child_node( 'orderClause' );
		$has_limit = $node->has_child_node( 'simpleLimitClause' );

		/*
		 * SQLite doesn't support UPDATE with ORDER BY/LIMIT.
		 * We need to use a subquery to emulate this behavior.
		 *
		 * For instance, the following query:
		 *   UPDATE t SET c = 1 WHERE c = 2 LIMIT 1;
		 * Will be rewritten to:
		 *   UPDATE t SET c = 1 WHERE rowid IN ( SELECT rowid FROM t WHERE c = 2 LIMIT 1 );
		 */
		if ( $has_order || $has_limit ) {
			$subquery = 'SELECT rowid FROM ' . $this->translate_sequence(
				array(
					$node->get_descendant_node( 'tableReference' ),
					$node->get_descendant_node( 'whereClause' ),
					$node->get_descendant_node( 'orderClause' ),
					$node->get_descendant_node( 'simpleLimitClause' ),
				)
			);

			$update_nodes = array();
			foreach ( $node->get_children() as $child ) {
				$update_nodes[] = $child;
				if (
					$child instanceof WP_Parser_Node
					&& 'updateList' === $child->rule_name
				) {
					// Skip WHERE, ORDER BY, and LIMIT.
					break;
				}
			}
			$query = $this->translate_sequence( $update_nodes )
				. ' WHERE rowid IN ( ' . $subquery . ' )';
		} else {
			$query = $this->translate( $node );
		}
		$this->execute_sqlite_query( $query );
		$this->set_result_from_affected_rows();
	}

	private function execute_create_table_statement( WP_Parser_Node $node ): void {
		$element_list = $node->get_descendant_node( 'tableElementList' );
		if ( null === $element_list ) {
			$query = $this->translate( $node );
			$this->execute_sqlite_query( $query );
			$this->set_result_from_affected_rows();
			return;
		}

		/*
		 * We need to handle some differences between MySQL and SQLite:
		 *
		 * 1. Inline index definitions:
		 *
		 *  In MySQL, we can define an index inline with a column definition.
		 *  In SQLite, we need to define indexes separately, using extra queries.
		 *
		 * 2. Column and constraint definition order:
		 *
		 *  In MySQL, column and constraint definitions can be arbitrarily mixed.
		 *  In SQLite, column definitions must come first, followed by constraints.
		 *
		 * 2. Auto-increment:
		 *
		 *  In MySQL, there can at most one AUTO_INCREMENT column, and it must be
		 *  a PRIMARY KEY, or the first column in a multi-column KEY.
		 *
		 *  In SQLite, there can at most one AUTOINCREMENT column, and it must be
		 *  a PRIMARY KEY, defined inline on a single column.
		 *
		 *  Therefore, the following valid MySQL construct is not supported:
		 *    CREATE TABLE t ( a INT AUTO_INCREMENT, b INT, PRIMARY KEY (a, b) );
		 *  @TODO: Support it with a single-column PK and a multi-column UNIQUE KEY.
		 */

		// Collect column, index, and constraint nodes.
		$columns                = array();
		$constraints            = array();
		$indexes                = array();
		$has_autoincrement      = false;
		$primary_key_constraint = null; // Does not include inline PK definition.

		foreach ( $element_list->get_descendant_nodes( 'columnDefinition' ) as $child ) {
			if ( null !== $child->get_descendant_token( WP_MySQL_Lexer::AUTO_INCREMENT_SYMBOL ) ) {
				$has_autoincrement = true;
			}
			// @TODO: Collect inline index definitions.
			$columns[] = $child;
		}

		foreach ( $element_list->get_descendant_nodes( 'tableConstraintDef' ) as $child ) {
			if ( null !== $child->get_descendant_token( WP_MySQL_Lexer::PRIMARY_SYMBOL ) ) {
				$primary_key_constraint = $child;
			} else {
				$constraints[] = $child;
			}
		}

		/*
		 * If we have a PRIMARY KEY constraint:
		 *  1. Without auto-increment, we can put it back to the list of constraints.
		 *  2. With auto-increment, we need to later move it to the column definition.
		 */
		if ( null !== $primary_key_constraint ) {
			if ( ! $has_autoincrement ) {
				$constraints[] = $primary_key_constraint;
			} elseif ( count( $primary_key_constraint->get_descendant_nodes( 'keyPart' ) ) > 1 ) {
				throw $this->not_supported_exception(
					'Composite primary key with AUTO_INCREMENT'
				);
			}
		}

		$query_parts = array( 'CREATE' );
		foreach ( $node->get_child_node()->get_children() as $child ) {
			if ( $child instanceof WP_Parser_Node && 'tableElementList' === $child->rule_name ) {
				$query_parts[] = $this->translate_sequence( array_merge( $columns, $constraints ), ' , ' );
			} else {
				$part = $this->translate( $child );
				if ( null !== $part ) {
					$query_parts[] = $part;
				}
			}
		}

		// @TODO: Execute queries for inline index definitions.

		$this->execute_sqlite_query( implode( ' ', $query_parts ) );
		$this->set_result_from_affected_rows();
	}

	private function execute_alter_table_statement( WP_Parser_Node $node ): void {
		$table_name = $this->translate( $node->get_descendant_node( 'tableRef' ) );
		$actions    = $node->get_descendant_nodes( 'alterListItem' );

		/*
		 * SQLite supports only a small subset of MySQL ALTER TABLE statement.
		 * We need to handle some differences and emulate some operations:
		 *
		 * 1. Multiple operations in a single ALTER TABLE statement.
		 *
		 *  SQLite doesn't support multiple operations in a single ALTER TABLE
		 *  statement. We need to execute each operation as a separate query.
		 *
		 * 2. ADD COLUMN in SQLite doesn't support some valid MySQL constructs:
		 *
		 *  - Adding a column with PRIMARY KEY or UNIQUE constraint.
		 *  - Adding a column with AUTOINCREMENT.
		 *  - Adding a column with CURRENT_TIME, CURRENT_DATE, CURRENT_TIMESTAMP,
		 *    or an expression in parentheses as a default value.
		 *  - Adding a NOT NULL column without a default value when the table is
		 *    not empty. In MySQL, this depends on the data type and SQL mode.
		 *
		 *    @TODO: Address these nuances.
		 */
		foreach ( $actions as $action ) {
			$token = $action->get_child_token();

			// ADD column/constraint.
			if ( WP_MySQL_Lexer::ADD_SYMBOL === $token->id ) {
				// ADD COLUMN.
				$field_definition = $action->get_descendant_node( 'fieldDefinition' );
				if ( null !== $field_definition ) {
					$field_name = $this->translate( $action->get_child_node( 'identifier' ) );
					$field      = $this->translate( $field_definition );
					$this->execute_sqlite_query(
						sprintf( 'ALTER TABLE %s ADD COLUMN %s %s', $table_name, $field_name, $field )
					);
				}

				// ADD CONSTRAINT.
				$constraint = $action->get_descendant_node( 'tableConstraintDef' );
				if ( null !== $constraint ) {
					$constraint_name = $this->translate( $constraint->get_child_node( 'identifier' ) );
					$constraint      = $this->translate( $constraint );
					$this->execute_sqlite_query(
						sprintf( 'ALTER TABLE %s ADD CONSTRAINT %s %s', $table_name, $constraint_name, $constraint )
					);
				}
			} elseif ( WP_MySQL_Lexer::DROP_SYMBOL === $token->id ) {
				// DROP COLUMN.
				$field_name = $this->translate( $action->get_child_node( 'columnInternalRef' ) );
				if ( null !== $field_name ) {
					$this->execute_sqlite_query(
						sprintf( 'ALTER TABLE %s DROP COLUMN %s', $table_name, $field_name )
					);
				}
			}
		}

		$this->set_result_from_affected_rows();
	}

	private function translate( $ast ) {
		if ( null === $ast ) {
			return null;
		}

		if ( $ast instanceof WP_MySQL_Token ) {
			return $this->translate_token( $ast );
		}

		if ( ! $ast instanceof WP_Parser_Node ) {
			throw new Exception( 'translate_query only accepts WP_MySQL_Token and WP_Parser_Node instances' );
		}

		$rule_name = $ast->rule_name;
		switch ( $rule_name ) {
			case 'qualifiedIdentifier':
			case 'dotIdentifier':
				return $this->translate_sequence( $ast->get_children(), '' );
			case 'identifierKeyword':
				return '"' . $this->translate( $ast->get_child() ) . '"';
			case 'textStringLiteral':
				$token = $ast->get_child_token();
				if ( WP_MySQL_Lexer::DOUBLE_QUOTED_TEXT === $token->id ) {
					return WP_SQLite_Token_Factory::double_quoted_value( $token->value )->value;
				}
				if ( WP_MySQL_Lexer::SINGLE_QUOTED_TEXT === $token->id ) {
					return WP_SQLite_Token_Factory::raw( $token->value )->value;
				}
				throw $this->invalid_input_exception();
			case 'dataType':
			case 'nchar':
				$child = $ast->get_child();
				if ( $child instanceof WP_Parser_Node ) {
					return $this->translate( $child );
				}

				// Handle optional prefixes (data type is the second token):
				//  1. LONG VARCHAR, LONG CHAR(ACTER) VARYING, LONG VARBINARY.
				//  2. NATIONAL CHAR, NATIONAL VARCHAR, NATIONAL CHAR(ACTER) VARYING.
				if ( WP_MySQL_Lexer::LONG_SYMBOL === $child->id ) {
					$child = $ast->get_child_tokens()[1] ?? null;
				} elseif ( WP_MySQL_Lexer::NATIONAL_SYMBOL === $child->id ) {
					$child = $ast->get_child_tokens()[1] ?? null;
				}

				if ( null === $child ) {
					throw $this->invalid_input_exception();
				}

				$type = self::DATA_TYPE_MAP[ $child->id ] ?? null;
				if ( null !== $type ) {
					return $type;
				}

				// SERIAL is an alias for BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE.
				if ( WP_MySQL_Lexer::SERIAL_SYMBOL === $child->id ) {
					return 'INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE';
				}

				// @TODO: Handle SET and JSON.
				throw $this->not_supported_exception(
					sprintf( 'data type: %s', $child->value )
				);
			case 'systemVariable':
				// @TODO: Emulate some system variables, or use reasonable defaults.
				//        See: https://dev.mysql.com/doc/refman/8.4/en/server-system-variable-reference.html
				//        See: https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html

				// When we have no value, it's reasonable to use NULL.
				return 'NULL';
			case 'fieldDefinition':
				/*
				 * In SQLite, there is the a quirk for backward compatibility:
				 *  1. INTEGER PRIMARY KEY creates an alias of ROWID.
				 *  2. INT PRIMARY KEY will  not alias of ROWID.
				 *
				 * Therefore, we want to:
				 *  1. Use INTEGER PRIMARY KEY for when we have AUTOINCREMENT.
				 *  2. Use INT PRIMARY KEY otherwise.
				 */
				$has_primary_key   = $ast->get_descendant_token( WP_MySQL_Lexer::KEY_SYMBOL ) !== null;
				$has_autoincrement = $ast->get_descendant_token( WP_MySQL_Lexer::AUTO_INCREMENT_SYMBOL ) !== null;
				$children          = $ast->get_children();
				$data_type_node    = array_shift( $children );
				$data_type         = $this->translate( $data_type_node );
				if ( $has_primary_key && 'INTEGER' === $data_type ) {
					$data_type = $has_autoincrement ? 'INTEGER' : 'INT';
				}

				$attributes = $this->translate_sequence( $children );
				$definition = $data_type . ( null === $attributes ? '' : " $attributes" );

				/*
				 * In SQLite, AUTOINCREMENT must always be preceded by PRIMARY KEY.
				 * Therefore, we remove both PRIMARY KEY and AUTOINCREMENT from
				 * column attributes, and append them here in SQLite-friendly way.
				 */
				if ( $has_autoincrement ) {
					return $definition . ' PRIMARY KEY AUTOINCREMENT';
				} elseif ( $has_primary_key ) {
					return $definition . ' PRIMARY KEY';
				}
				return $definition;
			case 'columnAttribute':
			case 'gcolAttribute':
				/*
				 * Remove PRIMARY KEY and AUTOINCREMENT from the column attributes.
				 * They are handled in the "fieldDefinition" node.
				 */
				if ( $ast->has_child_token( WP_MySQL_Lexer::KEY_SYMBOL ) ) {
					return null;
				}
				if ( $ast->has_child_token( WP_MySQL_Lexer::AUTO_INCREMENT_SYMBOL ) ) {
					return null;
				}
				return $this->translate_sequence( $ast->get_children() );
			case 'createTableOptions':
				return $this->translate_sequence( $ast->get_children(), ', ' );
			case 'createTableOption':
				if ( $ast->get_child_token( WP_MySQL_Lexer::ENGINE_SYMBOL ) ) {
					return null;
				}
				return $this->translate_sequence( $ast->get_children() );
			case 'duplicateAsQueryExpression':
				// @TODO: How to handle IGNORE/REPLACE?

				// The "AS" keyword is optional in MySQL, but required in SQLite.
				return 'AS ' . $this->translate( $ast->get_child_node() );
			default:
				return $this->translate_sequence( $ast->get_children() );
		}
	}

	private function translate_token( WP_MySQL_Token $token ) {
		switch ( $token->id ) {
			case WP_MySQL_Lexer::EOF:
				return null;
			case WP_MySQL_Lexer::IDENTIFIER:
				return '"' . trim( $token->value, '`"' ) . '"';
			case WP_MySQL_Lexer::AUTO_INCREMENT_SYMBOL:
				return 'AUTOINCREMENT';
			default:
				return $token->value;
		}
	}

	private function translate_sequence( array $nodes, string $separator = ' ' ): ?string {
		$parts = array();
		foreach ( $nodes as $node ) {
			if ( null === $node ) {
				continue;
			}

			$translated = $this->translate( $node );
			if ( null === $translated ) {
				continue;
			}
			$parts[] = $translated;
		}
		if ( 0 === count( $parts ) ) {
			return null;
		}
		return implode( $separator, $parts );
	}

	/**
	 * This method makes database directory and .htaccess file.
	 *
	 * It is executed only once when the installation begins.
	 */
	private function prepare_directory() {
		global $wpdb;
		$u = umask( 0000 );
		if ( ! is_dir( FQDBDIR ) ) {
			if ( ! @mkdir( FQDBDIR, 0704, true ) ) {
				umask( $u );
				wp_die( 'Unable to create the required directory! Please check your server settings.', 'Error!' );
			}
		}
		if ( ! is_writable( FQDBDIR ) ) {
			umask( $u );
			$message = 'Unable to create a file in the directory! Please check your server settings.';
			wp_die( $message, 'Error!' );
		}
		if ( ! is_file( FQDBDIR . '.htaccess' ) ) {
			$fh = fopen( FQDBDIR . '.htaccess', 'w' );
			if ( ! $fh ) {
				umask( $u );
				echo 'Unable to create a file in the directory! Please check your server settings.';

				return false;
			}
			fwrite( $fh, 'DENY FROM ALL' );
			fclose( $fh );
		}
		if ( ! is_file( FQDBDIR . 'index.php' ) ) {
			$fh = fopen( FQDBDIR . 'index.php', 'w' );
			if ( ! $fh ) {
				umask( $u );
				echo 'Unable to create a file in the directory! Please check your server settings.';

				return false;
			}
			fwrite( $fh, '<?php // Silence is gold. ?>' );
			fclose( $fh );
		}
		umask( $u );

		return true;
	}

	/**
	 * Method to clear previous data.
	 */
	private function flush() {
		$this->mysql_query             = '';
		$this->results                 = null;
		$this->last_exec_returned      = null;
		$this->table_name              = null;
		$this->last_insert_id          = null;
		$this->affected_rows           = null;
		$this->insert_columns          = array();
		$this->num_rows                = null;
		$this->return_value            = null;
		$this->error_messages          = array();
		$this->is_error                = false;
		$this->executed_sqlite_queries = array();
	}

	/**
	 * Method to set the results from the fetched data.
	 *
	 * @param array $data The data to set.
	 */
	private function set_results_from_fetched_data( $data ) {
		if ( null === $this->results ) {
			$this->results = $data;
		}
		if ( is_array( $this->results ) ) {
			$this->num_rows               = count( $this->results );
			$this->last_select_found_rows = count( $this->results );
		}
		$this->return_value = $this->results;
	}

	/**
	 * Method to set the results from the affected rows.
	 *
	 * @param int|null $override Override the affected rows.
	 */
	private function set_result_from_affected_rows( $override = null ) {
		/*
		 * SELECT CHANGES() is a workaround for the fact that
		 * $stmt->rowCount() returns "0" (zero) with the
		 * SQLite driver at all times.
		 * Source: https://www.php.net/manual/en/pdostatement.rowcount.php
		 */
		if ( null === $override ) {
			$this->affected_rows = (int) $this->execute_sqlite_query( 'select changes()' )->fetch()[0];
		} else {
			$this->affected_rows = $override;
		}
		$this->return_value = $this->affected_rows;
		$this->num_rows     = $this->affected_rows;
		$this->results      = $this->affected_rows;
	}

	/**
	 * Error handler.
	 *
	 * @param Exception $err Exception object.
	 *
	 * @return bool Always false.
	 */
	private function handle_error( Exception $err ) {
		$message = $err->getMessage();
		$this->set_error( __LINE__, __FUNCTION__, $message );
		$this->return_value = false;
		return false;
	}

	/**
	 * Method to format the error messages and put out to the file.
	 *
	 * When $wpdb::suppress_errors is set to true or $wpdb::show_errors is set to false,
	 * the error messages are ignored.
	 *
	 * @param string $line          Where the error occurred.
	 * @param string $function_name Indicate the function name where the error occurred.
	 * @param string $message       The message.
	 *
	 * @return boolean|void
	 */
	private function set_error( $line, $function_name, $message ) {
		$this->errors[]         = array(
			'line'     => $line,
			'function' => $function_name,
		);
		$this->error_messages[] = $message;
		$this->is_error         = true;
	}

	private function invalid_input_exception() {
		throw new Exception( 'MySQL query syntax error.' );
	}

	private function not_supported_exception( string $cause ): Exception {
		return new Exception(
			sprintf( 'MySQL query not supported. Cause: %s', $cause )
		);
	}
}
