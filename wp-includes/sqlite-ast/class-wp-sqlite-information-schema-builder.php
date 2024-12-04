<?php

// @TODO: Remove the namespace and use statements when replacing the old driver.
namespace WIP;

use WP_MySQL_Lexer;
use WP_MySQL_Token;
use WP_Parser_Node;

class WP_SQLite_Information_Schema_Builder {
	/**
	 * Tables that emulate MySQL "information_schema".
	 *
	 *  - TABLES
	 *  - VIEWS
	 *  - COLUMNS
	 *  - STATISTICS (indexes)
	 *  - TABLE_CONSTRAINTS (PK, UNIQUE, FK)
	 *  - CHECK_CONSTRAINTS
	 *  - KEY_COLUMN_USAGE (foreign keys)
	 *  - REFERENTIAL_CONSTRAINTS (foreign keys)
	 *  - TRIGGERS
	 */
	const CREATE_INFORMATION_SCHEMA_QUERIES = array(
		// TABLES
		"CREATE TABLE IF NOT EXISTS _mysql_information_schema_tables (
			TABLE_CATALOG TEXT NOT NULL DEFAULT 'def',   -- always 'def'
			TABLE_SCHEMA TEXT NOT NULL,                  -- database name
			TABLE_NAME TEXT NOT NULL,                    -- table name
			TABLE_TYPE TEXT NOT NULL,                    -- 'BASE TABLE' or 'VIEW'
			ENGINE TEXT NOT NULL,                        -- storage engine
			VERSION INTEGER NOT NULL DEFAULT 10,         -- unused, in MySQL 8 hardcoded to 10
			ROW_FORMAT TEXT NOT NULL,                    -- row storage format @TODO - implement
			TABLE_ROWS INTEGER NOT NULL DEFAULT 0,       -- not implemented
			AVG_ROW_LENGTH INTEGER NOT NULL DEFAULT 0,   -- not implemented
			DATA_LENGTH INTEGER NOT NULL DEFAULT 0,      -- not implemented
			MAX_DATA_LENGTH INTEGER NOT NULL DEFAULT 0,  -- not implemented
			INDEX_LENGTH INTEGER NOT NULL DEFAULT 0,     -- not implemented
			DATA_FREE INTEGER NOT NULL DEFAULT 0,        -- not implemented
			AUTO_INCREMENT INTEGER,                      -- not implemented
			CREATE_TIME TEXT NOT NULL                    -- table creation timestamp
				DEFAULT CURRENT_TIMESTAMP,
			UPDATE_TIME TEXT,                            -- table update time
			CHECK_TIME TEXT,                             -- not implemented
			TABLE_COLLATION TEXT NOT NULL,               -- table collation
			CHECKSUM INTEGER,                            -- not implemented
			CREATE_OPTIONS TEXT,                         -- extra CREATE TABLE options
			TABLE_COMMENT TEXT NOT NULL DEFAULT ''       -- comment
		) STRICT",

		// COLUMNS
		"CREATE TABLE IF NOT EXISTS _mysql_information_schema_columns (
			TABLE_CATALOG TEXT NOT NULL DEFAULT 'def',   -- always 'def'
			TABLE_SCHEMA TEXT NOT NULL,                  -- database name
			TABLE_NAME TEXT NOT NULL,                    -- table name
			COLUMN_NAME TEXT NOT NULL,                   -- column name
			ORDINAL_POSITION INTEGER NOT NULL,           -- column position
			COLUMN_DEFAULT TEXT,                         -- default value, NULL for both NULL and none
			IS_NULLABLE TEXT NOT NULL,                   -- 'YES' or 'NO'
			DATA_TYPE TEXT NOT NULL,                     -- data type (without length, precision, etc.)
			CHARACTER_MAXIMUM_LENGTH INTEGER,			 -- max length for string columns in characters
			CHARACTER_OCTET_LENGTH INTEGER,              -- max length for string columns in bytes
			NUMERIC_PRECISION INTEGER,                   -- number precision for numeric columns
			NUMERIC_SCALE INTEGER,                       -- number scale for numeric columns
			DATETIME_PRECISION INTEGER,                  -- fractional seconds precision for temporal columns
			CHARACTER_SET_NAME TEXT,                     -- charset for string columns
			COLLATION_NAME TEXT,                         -- collation for string columns
			COLUMN_TYPE TEXT NOT NULL,                   -- full data type (with length, precision, etc.)
			COLUMN_KEY TEXT NOT NULL DEFAULT '',		 -- if column is indexed ('', 'PRI', 'UNI', 'MUL')
			EXTRA TEXT NOT NULL DEFAULT '',              -- AUTO_INCREMENT, VIRTUAL, STORED, etc.
			PRIVILEGES TEXT NOT NULL,                    -- not implemented
			COLUMN_COMMENT TEXT NOT NULL DEFAULT '',     -- comment
			GENERATION_EXPRESSION TEXT NOT NULL DEFAULT '', -- expression for generated columns
			SRS_ID INTEGER                               -- not implemented
		) STRICT",

		// VIEWS
		'CREATE TABLE IF NOT EXISTS _mysql_information_schema_views (
			TABLE_CATALOG TEXT NOT NULL,
			TABLE_SCHEMA TEXT NOT NULL,
			TABLE_NAME TEXT NOT NULL,
			VIEW_DEFINITION TEXT NOT NULL,
			CHECK_OPTION TEXT NOT NULL,
			IS_UPDATABLE TEXT NOT NULL,
			DEFINER TEXT NOT NULL,
			SECURITY_TYPE TEXT NOT NULL,
			CHARACTER_SET_CLIENT TEXT NOT NULL,
			COLLATION_CONNECTION TEXT NOT NULL,
			ALGORITHM TEXT NOT NULL
		) STRICT',

		// STATISTICS (indexes)
		"CREATE TABLE IF NOT EXISTS _mysql_information_schema_statistics (
			TABLE_CATALOG TEXT NOT NULL DEFAULT 'def',   -- always 'def'
			TABLE_SCHEMA TEXT NOT NULL,                  -- database name
			TABLE_NAME TEXT NOT NULL,                    -- table name
			NON_UNIQUE INTEGER NOT NULL,                 -- 0 for unique indexes, 1 otherwise
			INDEX_SCHEMA TEXT NOT NULL,                  -- index database name
			INDEX_NAME TEXT NOT NULL,                    -- index name, for PKs always 'PRIMARY'
			SEQ_IN_INDEX INTEGER NOT NULL,               -- column position in index (from 1)
			COLUMN_NAME TEXT,                            -- column name (NULL for functional indexes)
			COLLATION TEXT,                              -- column sort in the index ('A', 'D', or NULL)
			CARDINALITY INTEGER,                         -- not implemented
			SUB_PART INTEGER,                            -- number of indexed chars, NULL for full column
			PACKED TEXT,                                 -- not implemented
			NULLABLE TEXT NOT NULL,                      -- 'YES' if column can contain NULL, '' otherwise
			INDEX_TYPE TEXT NOT NULL,                    -- 'BTREE', 'FULLTEXT', 'SPATIAL'
			COMMENT TEXT NOT NULL DEFAULT '',            -- not implemented
			INDEX_COMMENT TEXT NOT NULL DEFAULT '',      -- index comment
			IS_VISIBLE TEXT NOT NULL DEFAULT 'YES',      -- 'NO' if column is hidden, 'YES' otherwise
			EXPRESSION TEXT                              -- expression for functional indexes
		) STRICT",

		// TABLE_CONSTRAINTS
		'CREATE TABLE IF NOT EXISTS _mysql_information_schema_table_constraints (
			CONSTRAINT_CATALOG TEXT NOT NULL,
			CONSTRAINT_SCHEMA TEXT NOT NULL,
			CONSTRAINT_NAME TEXT NOT NULL,
			TABLE_SCHEMA TEXT NOT NULL,
			TABLE_NAME TEXT NOT NULL,
			CONSTRAINT_TYPE TEXT NOT NULL
		) STRICT',

		// CHECK_CONSTRAINTS
		'CREATE TABLE IF NOT EXISTS _mysql_information_schema_check_constraints (
			CONSTRAINT_CATALOG TEXT NOT NULL,
			CONSTRAINT_SCHEMA TEXT NOT NULL,
			TABLE_NAME TEXT NOT NULL,
			CONSTRAINT_NAME TEXT NOT NULL,
			CHECK_CLAUSE TEXT NOT NULL
		) STRICT',

		// KEY_COLUMN_USAGE
		'CREATE TABLE IF NOT EXISTS _mysql_information_schema_key_column_usage (
			CONSTRAINT_CATALOG TEXT NOT NULL,
			CONSTRAINT_SCHEMA TEXT NOT NULL,
			CONSTRAINT_NAME TEXT NOT NULL,
			TABLE_CATALOG TEXT NOT NULL,
			TABLE_SCHEMA TEXT NOT NULL,
			TABLE_NAME TEXT NOT NULL,
			COLUMN_NAME TEXT NOT NULL,
			ORDINAL_POSITION INTEGER NOT NULL,
			POSITION_IN_UNIQUE_CONSTRAINT INTEGER,
			REFERENCED_TABLE_SCHEMA TEXT,
			REFERENCED_TABLE_NAME TEXT,
			REFERENCED_COLUMN_NAME TEXT
		) STRICT',

		// REFERENTIAL_CONSTRAINTS
		'CREATE TABLE IF NOT EXISTS _mysql_information_schema_referential_constraints (
			CONSTRAINT_CATALOG TEXT NOT NULL,
			CONSTRAINT_SCHEMA TEXT NOT NULL,
			CONSTRAINT_NAME TEXT NOT NULL,
			UNIQUE_CONSTRAINT_CATALOG TEXT NOT NULL,
			UNIQUE_CONSTRAINT_SCHEMA TEXT NOT NULL,
			UNIQUE_CONSTRAINT_NAME TEXT,
			MATCH_OPTION TEXT NOT NULL,
			UPDATE_RULE TEXT NOT NULL,
			DELETE_RULE TEXT NOT NULL,
			REFERENCED_TABLE_NAME TEXT NOT NULL
		) STRICT',

		// TRIGGERS
		'CREATE TABLE IF NOT EXISTS _mysql_information_schema_triggers (
			TRIGGER_CATALOG TEXT NOT NULL,
			TRIGGER_SCHEMA TEXT NOT NULL,
			TRIGGER_NAME TEXT NOT NULL,
			EVENT_MANIPULATION TEXT NOT NULL,
			EVENT_OBJECT_CATALOG TEXT NOT NULL,
			EVENT_OBJECT_SCHEMA TEXT NOT NULL,
			EVENT_OBJECT_TABLE TEXT NOT NULL,
			ACTION_ORDER INTEGER NOT NULL,
			ACTION_CONDITION TEXT,
			ACTION_STATEMENT TEXT NOT NULL,
			ACTION_ORIENTATION TEXT NOT NULL,
			ACTION_TIMING TEXT NOT NULL,
			ACTION_REFERENCE_OLD_TABLE TEXT,
			ACTION_REFERENCE_NEW_TABLE TEXT,
			ACTION_REFERENCE_OLD_ROW TEXT NOT NULL,
			ACTION_REFERENCE_NEW_ROW TEXT NOT NULL,
			CREATED TEXT,
			SQL_MODE TEXT NOT NULL,
			DEFINER TEXT NOT NULL,
			CHARACTER_SET_CLIENT TEXT NOT NULL,
			COLLATION_CONNECTION TEXT NOT NULL,
			DATABASE_COLLATION TEXT NOT NULL
		) STRICT',
	);

	const TOKEN_TO_TYPE_MAP = array(
		WP_MySQL_Lexer::INT_SYMBOL                => 'int',
		WP_MySQL_Lexer::TINYINT_SYMBOL            => 'tinyint',
		WP_MySQL_Lexer::SMALLINT_SYMBOL           => 'smallint',
		WP_MySQL_Lexer::MEDIUMINT_SYMBOL          => 'mediumint',
		WP_MySQL_Lexer::BIGINT_SYMBOL             => 'bigint',
		WP_MySQL_Lexer::REAL_SYMBOL               => 'double',
		WP_MySQL_Lexer::DOUBLE_SYMBOL             => 'double',
		WP_MySQL_Lexer::FLOAT_SYMBOL              => 'float',
		WP_MySQL_Lexer::DECIMAL_SYMBOL            => 'decimal',
		WP_MySQL_Lexer::NUMERIC_SYMBOL            => 'decimal',
		WP_MySQL_Lexer::FIXED_SYMBOL              => 'decimal',
		WP_MySQL_Lexer::BIT_SYMBOL                => 'bit',
		WP_MySQL_Lexer::BOOL_SYMBOL               => 'tinyint',
		WP_MySQL_Lexer::BOOLEAN_SYMBOL            => 'tinyint',
		WP_MySQL_Lexer::BINARY_SYMBOL             => 'binary',
		WP_MySQL_Lexer::VARBINARY_SYMBOL          => 'varbinary',
		WP_MySQL_Lexer::YEAR_SYMBOL               => 'year',
		WP_MySQL_Lexer::DATE_SYMBOL               => 'date',
		WP_MySQL_Lexer::TIME_SYMBOL               => 'time',
		WP_MySQL_Lexer::TIMESTAMP_SYMBOL          => 'timestamp',
		WP_MySQL_Lexer::DATETIME_SYMBOL           => 'datetime',
		WP_MySQL_Lexer::TINYBLOB_SYMBOL           => 'tinyblob',
		WP_MySQL_Lexer::BLOB_SYMBOL               => 'blob',
		WP_MySQL_Lexer::MEDIUMBLOB_SYMBOL         => 'mediumblob',
		WP_MySQL_Lexer::LONGBLOB_SYMBOL           => 'longblob',
		WP_MySQL_Lexer::TINYTEXT_SYMBOL           => 'tinytext',
		WP_MySQL_Lexer::TEXT_SYMBOL               => 'text',
		WP_MySQL_Lexer::MEDIUMTEXT_SYMBOL         => 'mediumtext',
		WP_MySQL_Lexer::LONGTEXT_SYMBOL           => 'longtext',
		WP_MySQL_Lexer::ENUM_SYMBOL               => 'enum',
		WP_MySQL_Lexer::SET_SYMBOL                => 'set',
		WP_MySQL_Lexer::SERIAL_SYMBOL             => 'bigint',
		WP_MySQL_Lexer::GEOMETRY_SYMBOL           => 'geometry',
		WP_MySQL_Lexer::GEOMETRYCOLLECTION_SYMBOL => 'geometrycollection',
		WP_MySQL_Lexer::POINT_SYMBOL              => 'point',
		WP_MySQL_Lexer::MULTIPOINT_SYMBOL         => 'multipoint',
		WP_MySQL_Lexer::LINESTRING_SYMBOL         => 'linestring',
		WP_MySQL_Lexer::MULTILINESTRING_SYMBOL    => 'multilinestring',
		WP_MySQL_Lexer::POLYGON_SYMBOL            => 'polygon',
		WP_MySQL_Lexer::MULTIPOLYGON_SYMBOL       => 'multipolygon',
		WP_MySQL_Lexer::JSON_SYMBOL               => 'json',
	);

	/**
	 * Database name.
	 *
	 * @var string
	 */
	private $db_name;

	/**
	 * Query callback.
	 *
	 * @var callable
	 */
	private $query_callback;

	public function __construct( string $db_name, callable $query_callback ) {
		$this->db_name        = $db_name;
		$this->query_callback = $query_callback;
	}

	public function ensure_tables(): void {
		foreach ( self::CREATE_INFORMATION_SCHEMA_QUERIES as $query ) {
			$this->query( $query );
		}
	}

	public function create_table( WP_Parser_Node $node ): void {
		$table_name = $this->get_value( $node->get_descendant_node( 'tableName' ) );
		$engine     = $this->get_table_engine( $node );
		$row_format = 'MyISAM' === $engine ? 'FIXED' : 'DYNAMIC';
		$collate    = $this->get_table_collation( $node );

		// Get list of columns that are part of standalone PRIMARY KEY constraint.
		// This is needed to determine which columns are NOT NULL implicitly.
		// The list doesn't include columns with PRIMARY KEY defined inline.
		$primary_key_constraint_columns = array();
		foreach ( $node->get_descendant_nodes( 'tableConstraintDef' ) as $constraint ) {
			if ( null !== $constraint->get_descendant_token( WP_MySQL_Lexer::PRIMARY_SYMBOL ) ) {
				foreach ( $constraint->get_descendant_nodes( 'keyPart' ) as $key_part ) {
					$primary_key_constraint_columns[] = $this->get_value(
						$key_part->get_child_node( 'identifier' )
					);
				}
			}
		}

		// 1. INFORMATION_SCHEMA.TABLES:
		$this->insert_values(
			'_mysql_information_schema_tables',
			array(
				'table_schema'    => $this->db_name,
				'table_name'      => $table_name,
				'table_type'      => 'BASE TABLE',
				'engine'          => $engine,
				'row_format'      => $row_format,
				'table_collation' => $collate,
			)
		);

		// 2. INFORMATION_SCHEMA.COLUMNS:
		$position        = 1;
		$column_info_map = array();
		foreach ( $node->get_descendant_nodes( 'columnDefinition' ) as $column ) {
			$name     = $this->get_value( $column->get_child_node( 'columnName' ) );
			$default  = $this->get_column_default( $column );
			$nullable = $this->get_column_nullable( $column, $name, $primary_key_constraint_columns );
			$key      = $this->get_column_key( $node, $column );
			$comment  = $this->get_column_comment( $column );
			$extra    = $this->get_column_extra( $column );

			list ( $data_type, $column_type )    = $this->get_column_data_types( $column );
			list ( $char_length, $octet_length ) = $this->get_column_lengths( $column, $data_type );
			list ( $precision, $scale )          = $this->get_column_numeric_attributes( $column, $data_type );
			list ( $charset, $collation )        = $this->get_column_charset_and_collation( $column, $data_type );
			$datetime_precision                  = $this->get_column_datetime_precision( $column, $data_type );
			$generation_expression               = $this->get_column_generation_expression( $column );

			$this->insert_values(
				'_mysql_information_schema_columns',
				array(
					'table_schema'             => $this->db_name,
					'table_name'               => $table_name,
					'column_name'              => $name,
					'ordinal_position'         => $position,
					'column_default'           => $default,
					'is_nullable'              => $nullable,
					'data_type'                => $data_type,
					'character_maximum_length' => $char_length,
					'character_octet_length'   => $octet_length,
					'numeric_precision'        => $precision,
					'numeric_scale'            => $scale,
					'datetime_precision'       => $datetime_precision,
					'character_set_name'       => $charset,
					'collation_name'           => $collation,
					'column_type'              => $column_type,
					'column_key'               => $key,
					'extra'                    => $extra,
					'privileges'               => 'select,insert,update,references',
					'column_comment'           => $comment,
					'generation_expression'    => $generation_expression,
					'srs_id'                   => null, // not implemented
				)
			);
			$position += 1;

			// Store column info needed for indexes and constraints.
			$column_info_map[ $name ] = array(
				'nullable'                 => $nullable,
				'data_type'                => $data_type,
				'character_maximum_length' => $char_length,
			);
		}

		// 3. INFORMATION_SCHEMA.STATISTICS (indexes):
		foreach ( $node->get_descendant_nodes( 'tableConstraintDef' ) as $constraint ) {
			$child = $constraint->get_child();
			if ( $child instanceof WP_Parser_Node ) {
				$child = $child->get_children()[1];
			}

			if ( ! $child instanceof WP_MySQL_Token ) {
				continue;
			}

			// Get first index column data type (needed for index type).
			$first_index_part   = $constraint->get_descendant_node( 'keyListVariants' );
			$first_column_name  = $this->get_index_name( $first_index_part );
			$first_column_type  = $column_info_map[ $first_column_name ]['data_type'] ?? null;
			$has_spatial_column = null !== $first_column_type && $this->is_spatial_data_type( $first_column_type );

			$non_unique = $this->get_index_non_unique( $child );
			$index_name = $this->get_index_name( $constraint );
			$index_type = $this->get_index_type( $constraint, $child, $has_spatial_column );

			$seq_in_index = 1;
			foreach ( $constraint->get_descendant_nodes( 'keyListVariants' ) as $key ) {
				$column_name = $this->get_index_column_name( $key );
				$collation   = $this->get_index_column_collation( $key, $index_type );
				$nullable    = $column_info_map[ $column_name ]['nullable'] ? 'YES' : '';

				$sub_part = $this->get_index_column_sub_part(
					$key,
					$column_info_map[ $column_name ]['character_maximum_length'],
					$has_spatial_column
				);

				/**
				 * SUB_PART INTEGER,                            -- number of indexed chars, NULL for full column
				 * COMMENT TEXT NOT NULL DEFAULT '',            -- not implemented
				 * INDEX_COMMENT TEXT NOT NULL DEFAULT '',      -- index comment
				 * IS_VISIBLE TEXT NOT NULL DEFAULT 'YES',      -- 'NO' if column is hidden, 'YES' otherwise
				 * EXPRESSION TEXT                              -- expression for functional indexes
				*/

				$this->insert_values(
					'_mysql_information_schema_statistics',
					array(
						'table_schema'  => $this->db_name,
						'table_name'    => $table_name,
						'non_unique'    => $non_unique,
						'index_schema'  => $this->db_name,
						'index_name'    => $index_name,
						'seq_in_index'  => $seq_in_index,
						'column_name'   => $column_name,
						'collation'     => $collation,
						'cardinality'   => 0, // not implemented
						'sub_part'      => $sub_part,
						'packed'        => null, // not implemented
						'nullable'      => $nullable,
						'index_type'    => $index_type,
						'comment'       => '', // not implemented
						'index_comment' => '',
						'is_visible'    => 'YES',
						'expression'    => null,
					)
				);
			}
		}
	}

	private function get_table_engine( WP_Parser_Node $node ): string {
		$engine_node = $node->get_descendant_node( 'engineRef' );
		if ( null === $engine_node ) {
			return 'InnoDB';
		}

		$engine = strtoupper( $this->get_value( $engine_node ) );
		if ( 'INNODB' === $engine ) {
			return 'InnoDB';
		} elseif ( 'MYISAM' === $engine ) {
			return 'MyISAM';
		}
		return $engine;
	}

	private function get_table_collation( WP_Parser_Node $node ): string {
		$collate_node = $node->get_descendant_node( 'collationName' );
		if ( null === $collate_node ) {
			return 'utf8mb4_general_ci';
		}
		return strtolower( $this->get_value( $collate_node ) );
	}

	private function insert_values( string $table_name, array $data ): void {
		$this->query(
			'
				INSERT INTO ' . $table_name . ' (' . implode( ', ', array_keys( $data ) ) . ')
				VALUES (' . implode( ', ', array_fill( 0, count( $data ), '?' ) ) . ')
			',
			array_values( $data )
		);
	}

	private function get_column_charset_and_collation( WP_Parser_Node $node, string $data_type ): array {
		if ( ! (
			'char' === $data_type
			|| 'varchar' === $data_type
			|| 'tinytext' === $data_type
			|| 'text' === $data_type
			|| 'mediumtext' === $data_type
			|| 'longtext' === $data_type
			|| 'enum' === $data_type
			|| 'set' === $data_type
		) ) {
			return array( null, null );
		}

		$charset   = null;
		$collation = null;
		$is_binary = false;

		$charset_node = $node->get_descendant_node( 'charsetWithOptBinary' );
		if ( null !== $charset_node ) {
			$charset_name_node = $charset_node->get_child_node( 'charsetName' );
			if ( null !== $charset_name_node ) {
				$charset = strtolower( $this->get_value( $charset_name_node ) );
			} elseif ( $charset_node->has_child_token( WP_MySQL_Lexer::ASCII_SYMBOL ) ) {
				$charset = 'latin1';
			} elseif ( $charset_node->has_child_token( WP_MySQL_Lexer::UNICODE_SYMBOL ) ) {
				$charset = 'ucs2';
			} elseif ( $charset_node->has_child_token( WP_MySQL_Lexer::BYTE_SYMBOL ) ) {
				// @TODO: This changes varchar to varbinary.
			}

			// @TODO: DEFAULT

			if ( $charset_node->has_child_token( WP_MySQL_Lexer::BINARY_SYMBOL ) ) {
				$is_binary = true;
			}
		}

		$collation_node = $node->get_descendant_node( 'collationName' );
		if ( null !== $collation_node ) {
			$collation = strtolower( $this->get_value( $collation_node ) );
		}

		// Defaults.
		// @TODO: These are hardcoded now. We should get them from table/DB.
		if ( null === $charset && null === $collation ) {
			$charset   = 'utf8mb4';
			$collation = 'utf8mb4_general_ci';

			// @TODO: BINARY (seems to change varchar to varbinary).
			// @TODO: DEFAULT
		}

		// If only one of charset/collation is set, the other one is derived.
		if ( null === $collation ) {
			$collation = $charset . ( $is_binary ? '_bin' : '_general_ci' );
		} elseif ( null === $charset ) {
			$charset = substr( $collation, 0, strpos( $collation, '_' ) );
		}

		return array( $charset, $collation );
	}

	private function get_column_data_types( WP_Parser_Node $node ): array {
		$type_node = $node->get_descendant_node( 'dataType' );
		$type      = $type_node->get_descendant_tokens();
		$token     = $type[0];

		// Normalize types.
		if ( isset( self::TOKEN_TO_TYPE_MAP[ $token->id ] ) ) {
			$type = self::TOKEN_TO_TYPE_MAP[ $token->id ];
		} elseif (
			// VARCHAR/NVARCHAR
			// NCHAR/NATIONAL VARCHAR
			// CHAR/CHARACTER/NCHAR VARYING
			// NATIONAL CHAR/CHARACTER VARYING
			WP_MySQL_Lexer::VARCHAR_SYMBOL === $token->id
			|| WP_MySQL_Lexer::NVARCHAR_SYMBOL === $token->id
			|| ( isset( $type[1] ) && WP_MySQL_Lexer::VARCHAR_SYMBOL === $type[1]->id )
			|| ( isset( $type[1] ) && WP_MySQL_Lexer::VARYING_SYMBOL === $type[1]->id )
			|| ( isset( $type[2] ) && WP_MySQL_Lexer::VARYING_SYMBOL === $type[2]->id )
		) {
			$type = 'varchar';
		} elseif (
			// CHAR, NCHAR, NATIONAL CHAR
			WP_MySQL_Lexer::CHAR_SYMBOL === $token->id
			|| WP_MySQL_Lexer::NCHAR_SYMBOL === $token->id
			|| isset( $type[1] ) && WP_MySQL_Lexer::CHAR_SYMBOL === $type[1]->id
		) {
			$type = 'char';
		} elseif (
			// LONG VARBINARY
			WP_MySQL_Lexer::LONG_SYMBOL === $token->id
			&& isset( $type[1] ) && WP_MySQL_Lexer::VARBINARY_SYMBOL === $type[1]->id
		) {
			$type = 'mediumblob';
		} elseif (
			// LONG CHAR/CHARACTER, LONG CHAR/CHARACTER VARYING
			WP_MySQL_Lexer::LONG_SYMBOL === $token->id
			&& isset( $type[1] ) && WP_MySQL_Lexer::CHAR_SYMBOL === $type[1]->id
		) {
			$type = 'mediumtext';
		} elseif (
			// LONG VARCHAR
			WP_MySQL_Lexer::LONG_SYMBOL === $token->id
			&& isset( $type[1] ) && WP_MySQL_Lexer::VARCHAR_SYMBOL === $type[1]->id
		) {
			$type = 'mediumtext';
		} else {
			throw new \RuntimeException( 'Unknown data type: ' . $token->value );
		}

		// Get full type.
		$full_type = $type;
		if ( 'enum' === $type || 'set' === $type ) {
			$full_type .= $this->get_value( $type_node->get_descendant_node( 'stringList' ) );
		}

		$field_length = $type_node->get_descendant_node( 'fieldLength' );
		if ( null !== $field_length ) {
			if ( 'decimal' === $type || 'float' === $type || 'double' === $type ) {
				$full_type .= rtrim( $this->get_value( $field_length ), ')' ) . ',0)';
			} else {
				$full_type .= $this->get_value( $field_length );
			}
		}

		$precision = $type_node->get_descendant_node( 'precision' );
		if ( null !== $precision ) {
			$full_type .= $this->get_value( $precision );
		}

		$datetime_precision = $type_node->get_descendant_node( 'typeDatetimePrecision' );
		if ( null !== $datetime_precision ) {
			$full_type .= $this->get_value( $datetime_precision );
		}

		if (
			WP_MySQL_Lexer::BOOL_SYMBOL === $token->id
			|| WP_MySQL_Lexer::BOOLEAN_SYMBOL === $token->id
		) {
			$full_type .= '(1)'; // Add length for booleans.
		}

		if ( null === $field_length && null === $precision ) {
			if ( 'decimal' === $type ) {
				$full_type .= '(10,0)'; // Add default precision for decimals.
			} elseif ( 'char' === $type || 'bit' === $type || 'binary' === $type ) {
				$full_type .= '(1)';    // Add default length for char, bit, binary.
			}
		}

		if ( $type_node->get_descendant_token( WP_MySQL_Lexer::UNSIGNED_SYMBOL ) ) {
			$full_type .= ' unsigned';
		}
		if ( $type_node->get_descendant_token( WP_MySQL_Lexer::ZEROFILL_SYMBOL ) ) {
			$full_type .= ' zerofill';
		}

		return array( $type, $full_type );
	}

	private function get_column_default( WP_Parser_Node $node ): ?string {
		foreach ( $node->get_descendant_nodes( 'columnAttribute' ) as $attr ) {
			if ( $attr->has_child_token( WP_MySQL_Lexer::DEFAULT_SYMBOL ) ) {
				// @TODO: MySQL seems to normalize default values for numeric
				//        columns, such as 1.0 to 1, 1e3 to 1000, etc.
				return substr( $this->get_value( $attr ), strlen( 'DEFAULT' ) );
			}
		}
		return null;
	}

	private function get_column_nullable(
		WP_Parser_Node $node,
		string $column_name,
		array $primary_key_constraint_columns
	): string {
		if ( in_array( $column_name, $primary_key_constraint_columns, true ) ) {
			return 'NO';
		}

		foreach ( $node->get_descendant_nodes( 'columnAttribute' ) as $attr ) {
			// PRIMARY KEY columns are always NOT NULL.
			if ( $attr->has_child_token( WP_MySQL_Lexer::KEY_SYMBOL ) ) {
				return 'NO';
			}

			// Check for NOT NULL attribute.
			if (
				$attr->has_child_token( WP_MySQL_Lexer::NOT_SYMBOL )
				&& $attr->has_child_node( 'nullLiteral' )
			) {
				return 'NO';
			}
		}
		return 'YES';
	}

	private function get_column_key(
		WP_Parser_Node $table_node,
		WP_Parser_Node $column_node
	): string {
		// 1. PRI: Column is a primary key or its any component.
		if ( null !== $column_node->get_descendant_token( WP_MySQL_Lexer::PRIMARY_SYMBOL ) ) {
			return 'PRI';
		}

		$first_in_unique = false;
		$first_in_index  = false;
		foreach ( $table_node->get_descendant_nodes( 'tableConstraintDef' ) as $constraint ) {
			$is_primary = null !== $constraint->get_descendant_token( WP_MySQL_Lexer::KEY_SYMBOL );
			$is_unique  = null !== $constraint->get_descendant_token( WP_MySQL_Lexer::UNIQUE_SYMBOL );
			$is_index   = null !== $constraint->get_descendant_token( WP_MySQL_Lexer::INDEX_SYMBOL );

			if ( ! $is_primary && ! $is_unique && ! $is_index ) {
				continue;
			}

			$list = $constraint->get_descendant_node( 'keyListVariants' );
			foreach ( $list->get_descendant_nodes( 'identifier' ) as $i => $identifier ) {
				// @TODO: case-insensitive comparison with UTF-8 support.
				$column_name = $this->get_value(
					$column_node->get_child_node( 'columnName' )
				);
				if ( $column_name !== $this->get_value( $identifier ) ) {
					continue;
				}

				if ( $is_primary ) {
					return 'PRI';
				}

				if ( 0 === $i && $is_unique ) {
					$first_in_unique = true;
				} elseif ( 0 === $i && $is_index ) {
					$first_in_index = true;
				}
			}
		}

		// 2. UNI: Column is UNIQUE or its first component.
		if (
			$first_in_unique
			|| null !== $column_node->get_descendant_token( WP_MySQL_Lexer::UNIQUE_SYMBOL )
		) {
			return 'UNI';
		}

		// 3. MUL: Column is first component of a non-unique index.
		if (
			$first_in_index
			|| null !== $column_node->get_descendant_token( WP_MySQL_Lexer::INDEX_SYMBOL )
		) {
			return 'MUL';
		}

		return '';
	}

	private function get_column_lengths( WP_Parser_Node $node, string $column_type ): array {
		// Text and blob types.
		if ( 'tinytext' === $column_type || 'tinyblob' === $column_type ) {
			return array( 255, 255 );
		} elseif ( 'text' === $column_type || 'blob' === $column_type ) {
			return array( 65535, 65535 );
		} elseif ( 'mediumtext' === $column_type || 'mediumblob' === $column_type ) {
			return array( 16777215, 16777215 );
		} elseif ( 'longtext' === $column_type || 'longblob' === $column_type ) {
			return array( 4294967295, 4294967295 );
		}

		// For CHAR, VARCHAR, BINARY, VARBINARY, we need to check the field length.
		if (
			'char' === $column_type
			|| 'binary' === $column_type
			|| 'varchar' === $column_type
			|| 'varbinary' === $column_type
		) {
			$field_length = $node->get_descendant_node( 'fieldLength' );
			if ( null === $field_length ) {
				$length = 1;
			} else {
				$length = (int) trim( $this->get_value( $field_length ), '()' );
			}

			if ( 'char' === $column_type || 'varchar' === $column_type ) {
				// @TODO: The second number probably depends on the charset.
				//        We also need to handle NCHAR, NVARCHAR, etc.
				return array( $length, 4 * $length );
			} else {
				return array( $length, $length );
			}
		}

		return array( null, null );
	}

	private function get_column_numeric_attributes( WP_Parser_Node $node, string $data_type ): array {
		if ( 'tinyint' === $data_type ) {
			return array( 3, 0 );
		} elseif ( 'smallint' === $data_type ) {
			return array( 5, 0 );
		} elseif ( 'mediumint' === $data_type ) {
			return array( 7, 0 );
		} elseif ( 'int' === $data_type ) {
			return array( 10, 0 );
		} elseif ( 'bigint' === $data_type ) {
			if ( null !== $node->get_descendant_token( WP_MySQL_Lexer::UNSIGNED_SYMBOL ) ) {
				return array( 20, 0 );
			}
			return array( 19, 0 );
		}

		// For bit columns, we need to check the precision.
		if ( 'bit' === $data_type ) {
			$field_length = $node->get_descendant_node( 'fieldLength' );
			if ( null === $field_length ) {
				return array( 1, null );
			}
			return array( (int) trim( $this->get_value( $field_length ), '()' ), null );
		}

		// For floating point numbers, we need to check the precision and scale.
		$precision      = null;
		$scale          = null;
		$precision_node = $node->get_descendant_node( 'precision' );
		if ( null !== $precision_node ) {
			$values    = $precision_node->get_descendant_tokens( WP_MySQL_Lexer::INT_NUMBER );
			$precision = (int) $values[0]->value;
			$scale     = (int) $values[1]->value;
		}

		if ( 'float' === $data_type ) {
			return array( $precision ?? 12, $scale );
		} elseif ( 'double' === $data_type ) {
			return array( $precision ?? 22, $scale );
		} elseif ( 'decimal' === $data_type ) {
			if ( null === $precision ) {
				// Only precision can be specified ("fieldLength" in the grammar).
				$field_length = $node->get_descendant_node( 'fieldLength' );
				if ( null !== $field_length ) {
					$precision = (int) trim( $this->get_value( $field_length ), '()' );
				}
			}
			return array( $precision ?? 10, $scale ?? 0 );
		}

		return array( null, null );
	}

	private function get_column_datetime_precision( WP_Parser_Node $node, string $data_type ): ?int {
		if ( 'time' === $data_type || 'datetime' === $data_type || 'timestamp' === $data_type ) {
			$precision = $node->get_descendant_node( 'typeDatetimePrecision' );
			if ( null === $precision ) {
				return 0;
			} else {
				return (int) $this->get_value( $precision );
			}
		}
		return null;
	}

	private function get_column_extra( WP_Parser_Node $node ): string {
		$extra = '';
		foreach ( $node->get_descendant_nodes( 'columnAttribute' ) as $attr ) {
			if ( $attr->has_child_token( WP_MySQL_Lexer::AUTO_INCREMENT_SYMBOL ) ) {
				return 'auto_increment';
			}
			if (
				$attr->has_child_token( WP_MySQL_Lexer::ON_SYMBOL )
				&& $attr->has_child_token( WP_MySQL_Lexer::UPDATE_SYMBOL )
			) {
				return 'on update CURRENT_TIMESTAMP';
			}
		}

		if ( $node->get_descendant_token( WP_MySQL_Lexer::VIRTUAL_SYMBOL ) ) {
			$extra = 'VIRTUAL GENERATED';
		} elseif ( $node->get_descendant_token( WP_MySQL_Lexer::STORED_SYMBOL ) ) {
			$extra = 'STORED GENERATED';
		}
		return $extra;
	}

	private function get_column_comment( WP_Parser_Node $node ): string {
		foreach ( $node->get_descendant_nodes( 'columnAttribute' ) as $attr ) {
			if ( $attr->has_child_token( WP_MySQL_Lexer::COMMENT_SYMBOL ) ) {
				return $this->get_value( $attr->get_child_node( 'textLiteral' ) );
			}
		}
		return '';
	}

	private function get_column_generation_expression( WP_Parser_Node $node ): string {
		if ( null !== $node->get_descendant_token( WP_MySQL_Lexer::GENERATED_SYMBOL ) ) {
			$expr = $node->get_descendant_node( 'exprWithParentheses' );
			return $this->get_value( $expr );
		}
		return '';
	}

	private function is_spatial_data_type( string $data_type ): bool {
		return 'geometry' === $data_type
			|| 'geometrycollection' === $data_type
			|| 'point' === $data_type
			|| 'multipoint' === $data_type
			|| 'linestring' === $data_type
			|| 'multilinestring' === $data_type
			|| 'polygon' === $data_type
			|| 'multipolygon' === $data_type;
	}

	private function get_index_non_unique( WP_MySQL_Token $token ): int {
		if (
			WP_MySQL_Lexer::PRIMARY_SYMBOL === $token->id
			|| WP_MySQL_Lexer::UNIQUE_SYMBOL === $token->id
		) {
			return 0;
		}
		return 1;
	}

	private function get_index_name( WP_Parser_Node $node ): string {
		if ( $node->get_descendant_token( WP_MySQL_Lexer::PRIMARY_SYMBOL ) ) {
			return 'PRIMARY';
		}

		$name_node = $node->get_descendant_node( 'indexName' );
		if ( null === $name_node ) {
			/*
			 * In MySQL, the default index name equals the first column name.
			 * For functional indexes, the string "functional_index" is used.
			 * If the name is already used, we need to append a number.
			 */
			$subnode = $node->get_child_node()->get_child_node();
			if ( 'exprWithParentheses' === $subnode->rule_name ) {
				$name = 'functional_index';
			} else {
				$name = $this->get_value( $subnode );
			}

			// @TODO: Check if the name is already used.
			return $name;
		}
		return $this->get_value( $name_node );
	}

	private function get_index_type(
		WP_Parser_Node $node,
		WP_MySQL_Token $token,
		bool $has_spatial_column
	): string {
		// Handle "USING ..." clause.
		$index_type = $node->get_descendant_node( 'indexTypeClause' );
		if ( null !== $index_type ) {
			$index_type = strtoupper(
				$this->get_value( $index_type->get_child_node( 'indexType' ) )
			);
			if ( 'RTREE' === $index_type ) {
				return 'SPATIAL';
			} elseif ( 'HASH' === $index_type ) {
				// InnoDB uses BTREE even when HASH is specified.
				return 'BTREE';
			}
			return $index_type;
		}

		// Derive index type from its definition.
		if ( WP_MySQL_Lexer::FULLTEXT_SYMBOL === $token->id ) {
			return 'FULLTEXT';
		} elseif ( WP_MySQL_Lexer::SPATIAL_SYMBOL === $token->id ) {
			return 'SPATIAL';
		}

		// Spatial indexes are also derived from column data type.
		if ( $has_spatial_column ) {
			return 'SPATIAL';
		}

		return 'BTREE';
	}

	private function get_index_column_name( WP_Parser_Node $node ): ?string {
		$key_part = $node->get_child_node( 'keyPart' );
		if ( null === $key_part ) {
			return null;
		}
		return $this->get_value( $node->get_descendant_node( 'identifier' ) );
	}

	private function get_index_column_collation( WP_Parser_Node $node, string $index_type ): ?string {
		if ( 'FULLTEXT' === $index_type ) {
			return null;
		}

		$collate_node = $node->get_descendant_node( 'collationName' );
		if ( null === $collate_node ) {
			return null;
		}
		$collate = strtoupper( $this->get_value( $collate_node ) );
		return 'DESC' === $collate ? 'D' : 'A';
	}

	private function get_index_column_sub_part(
		WP_Parser_Node $node,
		?int $max_length,
		bool $is_spatial
	): ?int {
		$field_length = $node->get_descendant_node( 'fieldLength' );
		if ( null === $field_length ) {
			if ( $is_spatial ) {
				return 32;
			}
			return null;
		}

		$value = (int) trim( $this->get_value( $field_length ), '()' );
		if ( null !== $max_length && $value >= $max_length ) {
			return $max_length;
		}
		return $value;
	}

	private function get_value( WP_Parser_Node $node ): string {
		$full_value = '';
		foreach ( $node->get_children() as $child ) {
			if ( $child instanceof WP_Parser_Node ) {
				$value = $this->get_value( $child );
			} elseif ( WP_MySQL_Lexer::BACK_TICK_QUOTED_ID === $child->id ) {
				$value = substr( $child->value, 1, -1 );
				$value = str_replace( '\`', '`', $value );
				$value = str_replace( '``', '`', $value );
			} elseif ( WP_MySQL_Lexer::SINGLE_QUOTED_TEXT === $child->id ) {
				$value = $child->value;
				$value = substr( $value, 1, -1 );
				$value = str_replace( '\"', '"', $value );
				$value = str_replace( '""', '"', $value );
			} elseif ( WP_MySQL_Lexer::DOUBLE_QUOTED_TEXT === $child->id ) {
				$value = $child->value;
				$value = substr( $value, 1, -1 );
				$value = str_replace( '\"', '"', $value );
				$value = str_replace( '""', '"', $value );
			} else {
				$value = $child->value;
			}
			$full_value .= $value;
		}
		return $full_value;
	}

	private function query( string $query, array $params = array() ): void {
		( $this->query_callback )( $query, $params );
	}
}
