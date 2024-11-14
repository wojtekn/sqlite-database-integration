<?php

// @TODO: Remove the namespace and use statements when replacing the old driver.
namespace WIP;

use InvalidArgumentException;

class WP_SQLite_Token_Factory {
	private static $valid_types = array(
		WP_SQLite_Token::TYPE_RAW,
		WP_SQLite_Token::TYPE_IDENTIFIER,
		WP_SQLite_Token::TYPE_VALUE,
		WP_SQLite_Token::TYPE_OPERATOR,
	);

	private static $valid_operators = array(
		'SELECT',
		'INSERT',
		'UPDATE',
		'DELETE',
		'FROM',
		'WHERE',
		'JOIN',
		'LEFT',
		'RIGHT',
		'INNER',
		'OUTER',
		'ON',
		'AS',
		'AND',
		'OR',
		'NOT',
		'IN',
		'IS',
		'NULL',
		'GROUP',
		'BY',
		'ORDER',
		'LIMIT',
		'OFFSET',
		'HAVING',
		'UNION',
		'ALL',
		'DISTINCT',
		'WITH',
		'EXISTS',
		'CASE',
		'WHEN',
		'THEN',
		'ELSE',
		'END',
		'LIKE',
		'BETWEEN',
		'INTERVAL',
		'IF',
		'BEGIN',
		'COMMIT',
		'ROLLBACK',
		'SAVEPOINT',
		'RELEASE',
		'PRAGMA',
		'(',
		')',
		'+',
		'-',
		'*',
		'/',
		'=',
		'<>',
		'!=',
		'<',
		'<=',
		'>',
		'>=',
		';',
	);

	private static $functions = array(
		'ABS'               => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'AVG'               => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'COUNT'             => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'MAX'               => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'MIN'               => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'ROUND'             => array(
			'argCount'     => 2,
			'optionalArgs' => 1,
		),
		'SUM'               => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'LENGTH'            => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'UPPER'             => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'LOWER'             => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'COALESCE'          => array(
			'argCount'     => 2,
			'optionalArgs' => PHP_INT_MAX,
		),
		'SUBSTR'            => array(
			'argCount'     => 3,
			'optionalArgs' => 1,
		),
		'REPLACE'           => array(
			'argCount'     => 3,
			'optionalArgs' => 0,
		),
		'TRIM'              => array(
			'argCount'     => 3,
			'optionalArgs' => 2,
		),
		'DATE'              => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'TIME'              => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'DATETIME'          => array(
			'argCount'     => 2,
			'optionalArgs' => 1,
		),
		'JULIANDAY'         => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'STRFTIME'          => array(
			'argCount'     => 2,
			'optionalArgs' => 0,
		),
		'RANDOM'            => array(
			'argCount'     => 0,
			'optionalArgs' => 0,
		),
		'RANDOMBLOB'        => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'NULLIF'            => array(
			'argCount'     => 2,
			'optionalArgs' => 0,
		),
		'IFNULL'            => array(
			'argCount'     => 2,
			'optionalArgs' => 0,
		),
		'INSTR'             => array(
			'argCount'     => 2,
			'optionalArgs' => 0,
		),
		'HEX'               => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'QUOTE'             => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'LIKE'              => array(
			'argCount'     => 2,
			'optionalArgs' => 1,
		),
		'GLOB'              => array(
			'argCount'     => 2,
			'optionalArgs' => 0,
		),
		'CHAR'              => array(
			'argCount'     => 1,
			'optionalArgs' => PHP_INT_MAX,
		),
		'UNICODE'           => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'TOTAL'             => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'ZEROBLOB'          => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'PRINTF'            => array(
			'argCount'     => 2,
			'optionalArgs' => PHP_INT_MAX,
		),
		'LTRIM'             => array(
			'argCount'     => 2,
			'optionalArgs' => 1,
		),
		'RTRIM'             => array(
			'argCount'     => 2,
			'optionalArgs' => 1,
		),
		'BLOB'              => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'GROUP_CONCAT'      => array(
			'argCount'     => 1,
			'optionalArgs' => 1,
		),
		'JSON'              => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'JSON_ARRAY'        => array(
			'argCount'     => 1,
			'optionalArgs' => PHP_INT_MAX,
		),
		'JSON_OBJECT'       => array(
			'argCount'     => 1,
			'optionalArgs' => PHP_INT_MAX,
		),
		'JSON_QUOTE'        => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'JSON_VALID'        => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'JSON_ARRAY_LENGTH' => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'JSON_EXTRACT'      => array(
			'argCount'     => 2,
			'optionalArgs' => PHP_INT_MAX,
		),
		'JSON_INSERT'       => array(
			'argCount'     => 2,
			'optionalArgs' => PHP_INT_MAX,
		),
		'JSON_REPLACE'      => array(
			'argCount'     => 2,
			'optionalArgs' => PHP_INT_MAX,
		),
		'JSON_SET'          => array(
			'argCount'     => 2,
			'optionalArgs' => PHP_INT_MAX,
		),
		'JSON_PATCH'        => array(
			'argCount'     => 2,
			'optionalArgs' => 0,
		),
		'JSON_REMOVE'       => array(
			'argCount'     => 1,
			'optionalArgs' => PHP_INT_MAX,
		),
		'JSON_TYPE'         => array(
			'argCount'     => 1,
			'optionalArgs' => PHP_INT_MAX,
		),
		'JSON_DEPTH'        => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'JSON_KEYS'         => array(
			'argCount'     => 1,
			'optionalArgs' => 1,
		),
		'JSON_GROUP_ARRAY'  => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
		'JSON_GROUP_OBJECT' => array(
			'argCount'     => 1,
			'optionalArgs' => 0,
		),
	);

	public static function register_function( string $name, int $arg_count, int $optional_args = 0 ): void {
		self::$functions[ strtoupper( $name ) ] = array(
			'argCount'     => $arg_count,
			'optionalArgs' => $optional_args,
		);
	}

	public static function raw( string $value ): WP_SQLite_Token {
		return self::create( WP_SQLite_Token::TYPE_RAW, $value );
	}

	public static function identifier( string $value ): WP_SQLite_Token {
		return self::create( WP_SQLite_Token::TYPE_IDENTIFIER, $value );
	}

	public static function value( $value ): WP_SQLite_Token {
		return self::create( WP_SQLite_Token::TYPE_VALUE, self::escape_value( $value ) );
	}

	public static function double_quoted_value( $value ): WP_SQLite_Token {
		$value = substr( $value, 1, -1 );
		$value = str_replace( '\"', '"', $value );
		$value = str_replace( '""', '"', $value );
		return self::create( WP_SQLite_Token::TYPE_VALUE, self::escape_value( $value ) );
	}

	public static function operator( string $value ): WP_SQLite_Token {
		$upper_value = strtoupper( $value );
		if ( ! in_array( $upper_value, self::$valid_operators, true ) ) {
			throw new InvalidArgumentException( "Invalid SQLite operator or keyword: $value" );
		}
		return self::create( WP_SQLite_Token::TYPE_OPERATOR, $upper_value );
	}

	public static function create_function( string $name, array $expressions ): WP_SQLite_Expression {
		$upper_name = strtoupper( $name );
		if ( ! isset( self::$functions[ $upper_name ] ) ) {
			throw new InvalidArgumentException( "Unknown SQLite function: $name" );
		}

		$function_spec = self::$functions[ $upper_name ];
		$min_args      = $function_spec['argCount'] - $function_spec['optionalArgs'];
		$max_args      = $function_spec['argCount'];

		if ( count( $expressions ) < $min_args || count( $expressions ) > $max_args ) {
			throw new InvalidArgumentException(
				"Function $name expects between $min_args and $max_args arguments, " .
				count( $expressions ) . ' given.'
			);
		}

		$tokens   = array();
		$tokens[] = self::raw( $upper_name );
		$tokens[] = self::raw( '(' );

		foreach ( $expressions as $index => $expression ) {
			if ( $index > 0 ) {
				$tokens[] = self::raw( ',' );
			}
			if ( ! $expression instanceof Expression ) {
				throw new InvalidArgumentException( 'All arguments must be instances of Expression' );
			}
			$tokens = array_merge( $tokens, $expression->elements );
		}

		$tokens[] = self::raw( ')' );

		return new WP_SQLite_Expression( $tokens );
	}

	private static function create( string $type, string $value ): WP_SQLite_Token {
		if ( ! in_array( $type, self::$valid_types, true ) ) {
			throw new InvalidArgumentException( "Invalid token type: $type" );
		}
		return new WP_SQLite_Token( $type, $value );
	}

	private static function escape_value( $value ): string {
		if ( is_string( $value ) ) {
			// Ensure the string is valid UTF-8, replace invalid characters with an empty string
			$value = mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );

			// Escape single quotes by doubling them
			$value = str_replace( "'", "''", $value );

			// Escape backslashes by doubling them
			$value = str_replace( '\\', '\\\\', $value );

			// Remove null characters
			$value = str_replace( "\0", '', $value );

			// Return the escaped string enclosed in single quotes
			return "'" . $value . "'";
		} elseif ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		} elseif ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		} elseif ( is_null( $value ) ) {
			return 'NULL';
		} else {
			throw new InvalidArgumentException( 'Unsupported value type: ' . gettype( $value ) );
		}
	}
}
