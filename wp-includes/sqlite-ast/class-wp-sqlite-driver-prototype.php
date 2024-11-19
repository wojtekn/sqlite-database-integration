<?php

// @TODO: Remove the namespace and use statements when replacing the old driver.
namespace WIP;

use Exception;
use PDO;
use WP_MySQL_Lexer;
use WP_MySQL_Parser;
use WP_MySQL_Token;
use WP_Parser_Grammar;
use WP_Parser_Node;

$grammar = new WP_Parser_Grammar( require __DIR__ . '/../../wp-includes/mysql/mysql-grammar.php' );

/**
 * @TODO: This is just an SQLite driver prototype backed up here to be gradually
 *        moved to the WP_SQLite_Driver and deleted from here.
 */
class WP_SQLite_Driver_Prototype {
	/**
	 * @var WP_Parser_Grammar
	 */
	private $grammar;

	/**
	 * @var PDO
	 */
	private $pdo;

	private $results;

	private $has_sql_calc_found_rows = false;
	private $has_found_rows_call     = false;
	private $last_calc_rows_result   = null;

	public function __construct( PDO $pdo ) {
		global $grammar;
		$this->pdo     = $pdo;
		$this->grammar = $grammar;

		$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$pdo->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, true );
		$pdo->setAttribute( PDO::ATTR_TIMEOUT, 5 );
	}

	public function query( $query ) {
		$this->has_sql_calc_found_rows = false;
		$this->has_found_rows_call     = false;
		$this->last_calc_rows_result   = null;

		$lexer  = new WP_MySQL_Lexer( $query );
		$tokens = $lexer->remaining_tokens();

		$parser = new WP_MySQL_Parser( $this->grammar, $tokens );
		$ast    = $parser->parse();
		$expr   = $this->translate_query( $ast );
		//$expr   = $this->rewrite_sql_calc_found_rows( $expr );

		if ( null === $expr ) {
			return false;
		}

		$sqlite_query = WP_SQLite_Query_Builder::stringify( $expr );

		// Returning the query just for now for testing. In the end, we'll
		// run it and return the SQLite interaction result.
		//return $sqlite_query;

		if ( ! $sqlite_query ) {
			return false;
		}

		$is_select     = (bool) $ast->get_descendant( 'selectStatement' );
		$statement     = $this->pdo->prepare( $sqlite_query );
		$return_value  = $statement->execute();
		$this->results = $return_value;
		if ( $is_select ) {
			$this->results = $statement->fetchAll( PDO::FETCH_OBJ );
		}
		return $return_value;
	}

	public function get_error_message() {
	}

	public function get_query_results() {
		return $this->results;
	}

	private function rewrite_sql_calc_found_rows( WP_SQLite_Expression $expr ) {
		if ( $this->has_found_rows_call && ! $this->has_sql_calc_found_rows && null === $this->last_calc_rows_result ) {
			throw new Exception( 'FOUND_ROWS() called without SQL_CALC_FOUND_ROWS' );
		}

		if ( $this->has_sql_calc_found_rows ) {
			$expr_to_run = $expr;
			if ( $this->has_found_rows_call ) {
				$expr_without_found_rows = new WP_SQLite_Expression( array() );
				foreach ( $expr->elements as $k => $element ) {
					if ( WP_SQLite_Token::TYPE_IDENTIFIER === $element->type && 'FOUND_ROWS' === $element->value ) {
						$expr_without_found_rows->add_token(
							WP_SQLite_Token_Factory::value( 0 )
						);
					} else {
						$expr_without_found_rows->add_token( $element );
					}
				}
				$expr_to_run = $expr_without_found_rows;
			}

			// ...remove the LIMIT clause...
			$query = 'SELECT COUNT(*) as cnt FROM (' . WP_SQLite_Query_Builder::stringify( $expr_to_run ) . ');';

			// ...run $query...
			// $result = ...
			// $this->last_calc_rows_result = $result['cnt'];
		}

		if ( ! $this->has_found_rows_call ) {
			return $expr;
		}

		$expr_with_found_rows_result = new WP_SQLite_Expression( array() );
		foreach ( $expr->elements as $k => $element ) {
			if ( WP_SQLite_Token::TYPE_IDENTIFIER === $element->type && 'FOUND_ROWS' === $element->value ) {
				$expr_with_found_rows_result->add_token(
					WP_SQLite_Token_Factory::value( $this->last_calc_rows_result )
				);
			} else {
				$expr_with_found_rows_result->add_token( $element );
			}
		}
		return $expr_with_found_rows_result;
	}

	private function translate_query( $ast ) {
		if ( null === $ast ) {
			return null;
		}

		if ( $ast instanceof WP_MySQL_Token ) {
			$token = $ast;
			switch ( $token->id ) {
				case WP_MySQL_Lexer::EOF:
					return new WP_SQLite_Expression( array() );

				case WP_MySQL_Lexer::IDENTIFIER:
					return new WP_SQLite_Expression(
						array(
							WP_SQLite_Token_Factory::identifier(
								trim( $token->text, '`"' )
							),
						)
					);

				default:
					return new WP_SQLite_Expression(
						array(
							WP_SQLite_Token_Factory::raw( $token->text ),
						)
					);
			}
		}

		if ( ! ( $ast instanceof WP_Parser_Node ) ) {
			throw new Exception( 'translate_query only accepts WP_MySQL_Token and WP_Parser_Node instances' );
		}

		$rule_name = $ast->rule_name;

		switch ( $rule_name ) {
			case 'indexHintList':
				// SQLite doesn't support index hints. Let's skip them.
				return null;

			case 'querySpecOption':
				$token = $ast->get_token();
				switch ( $token->type ) {
					case WP_MySQL_Lexer::ALL_SYMBOL:
					case WP_MySQL_Lexer::DISTINCT_SYMBOL:
						return new WP_SQLite_Expression(
							array(
								WP_SQLite_Token_Factory::raw( $token->text ),
							)
						);
					case WP_MySQL_Lexer::SQL_CALC_FOUND_ROWS_SYMBOL:
						$this->has_sql_calc_found_rows = true;
						// Fall through to default.
					default:
						// we'll need to run the current SQL query without any
						// LIMIT clause, and then substitute the FOUND_ROWS()
						// function with a literal number of rows found.
						return new WP_SQLite_Expression( array() );
				}
				// Otherwise, fall through.

			case 'fromClause':
				// Skip `FROM DUAL`. We only care about a singular
				// FROM DUAL statement, as FROM mytable, DUAL is a syntax
				// error.
				if (
					$ast->has_token( WP_MySQL_Lexer::DUAL_SYMBOL ) &&
					! $ast->has_child( 'tableReferenceList' )
				) {
					return null;
				}
				// Otherwise, fall through.

			case 'selectOption':
			case 'interval':
			case 'intervalTimeStamp':
			case 'bitExpr':
			case 'boolPri':
			case 'lockStrengh':
			case 'orderList':
			case 'simpleExpr':
			case 'columnRef':
			case 'exprIs':
			case 'exprAnd':
			case 'primaryExprCompare':
			case 'fieldIdentifier':
			case 'dotIdentifier':
			case 'identifier':
			case 'literal':
			case 'joinedTable':
			case 'nullLiteral':
			case 'boolLiteral':
			case 'numLiteral':
			case 'textLiteral':
			case 'predicate':
			case 'predicateExprBetween':
			case 'primaryExprPredicate':
			case 'pureIdentifier':
			case 'unambiguousIdentifier':
			case 'qualifiedIdentifier':
			case 'query':
			case 'queryExpression':
			case 'queryExpressionBody':
			case 'queryExpressionParens':
			case 'queryPrimary':
			case 'querySpecification':
			case 'queryTerm':
			case 'selectAlias':
			case 'selectItem':
			case 'selectItemList':
			case 'selectStatement':
			case 'simpleExprColumnRef':
			case 'simpleExprFunction':
			case 'outerJoinType':
			case 'simpleExprSubQuery':
			case 'simpleExprLiteral':
			case 'compOp':
			case 'simpleExprList':
			case 'simpleStatement':
			case 'subquery':
			case 'exprList':
			case 'expr':
			case 'tableReferenceList':
			case 'tableReference':
			case 'tableRef':
			case 'tableAlias':
			case 'tableFactor':
			case 'singleTable':
			case 'udfExprList':
			case 'udfExpr':
			case 'withClause':
			case 'whereClause':
			case 'commonTableExpression':
			case 'derivedTable':
			case 'columnRefOrLiteral':
			case 'orderClause':
			case 'groupByClause':
			case 'lockingClauseList':
			case 'lockingClause':
			case 'havingClause':
			case 'direction':
			case 'orderExpression':
				$child_expressions = array();
				foreach ( $ast->children as $child ) {
					$child_expressions[] = $this->translate_query( $child );
				}
				return new WP_SQLite_Expression( $child_expressions );

			case 'textStringLiteral':
				return new WP_SQLite_Expression(
					array(
						$ast->has_token( WP_MySQL_Lexer::DOUBLE_QUOTED_TEXT ) ?
							WP_SQLite_Token_Factory::double_quoted_value( $ast->get_token( WP_MySQL_Lexer::DOUBLE_QUOTED_TEXT )->text ) : false,
						$ast->has_token( WP_MySQL_Lexer::SINGLE_QUOTED_TEXT ) ?
							WP_SQLite_Token_Factory::raw( $ast->get_token( WP_MySQL_Lexer::SINGLE_QUOTED_TEXT )->text ) : false,
					)
				);

			case 'functionCall':
				return $this->translate_function_call( $ast );

			case 'runtimeFunctionCall':
				return $this->translate_runtime_function_call( $ast );

			default:
				return null;
				// var_dump(count($ast->children));
				// foreach($ast->children as $child) {
				//     var_dump(get_class($child));
				//     echo $child->getText();
				//     echo "\n\n";
				// }
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw(
							$rule_name
						),
					)
				);
		}
	}

	private function translate_runtime_function_call( $ast ): WP_SQLite_Expression {
		$name_token = $ast->children[0];

		switch ( strtoupper( $name_token->text ) ) {
			case 'ADDDATE':
			case 'DATE_ADD':
				$args     = $ast->get_children( 'expr' );
				$interval = $ast->get_child( 'interval' );
				$timespan = $interval->get_child( 'intervalTimeStamp' )->get_token()->text;
				return WP_SQLite_Token_Factory::create_function(
					'DATETIME',
					array(
						$this->translate_query( $args[0] ),
						new WP_SQLite_Expression(
							array(
								WP_SQLite_Token_Factory::value( '+' ),
								WP_SQLite_Token_Factory::raw( '||' ),
								$this->translate_query( $args[1] ),
								WP_SQLite_Token_Factory::raw( '||' ),
								WP_SQLite_Token_Factory::value( $timespan ),
							)
						),
					)
				);

			case 'DATE_SUB':
				// return new WP_SQLite_Expression([
				//     SQLiteTokenFactory::raw("DATETIME("),
				//     $args[0],
				//     SQLiteTokenFactory::raw(", '-'"),
				//     $args[1],
				//     SQLiteTokenFactory::raw(" days')")
				// ]);

			case 'VALUES':
				$column = $ast->get_child()->get_descendant( 'pureIdentifier' );
				if ( ! $column ) {
					throw new Exception( 'VALUES() calls without explicit column names are unsupported' );
				}

				$colname = $column->get_token()->extract_value();
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw( 'excluded.' ),
						WP_SQLite_Token_Factory::identifier( $colname ),
					)
				);
			default:
				throw new Exception( 'Unsupported function: ' . $name_token->text );
		}
	}

	private function translate_function_call( $function_call_tree ): WP_SQLite_Expression {
		$name = $function_call_tree->get_child( 'pureIdentifier' )->get_token()->text;
		$args = array();
		foreach ( $function_call_tree->get_child( 'udfExprList' )->get_children() as $node ) {
			$args[] = $this->translate_query( $node );
		}
		switch ( strtoupper( $name ) ) {
			case 'ABS':
			case 'ACOS':
			case 'ASIN':
			case 'ATAN':
			case 'ATAN2':
			case 'COS':
			case 'DEGREES':
			case 'TRIM':
			case 'EXP':
			case 'MAX':
			case 'MIN':
			case 'FLOOR':
			case 'RADIANS':
			case 'ROUND':
			case 'SIN':
			case 'SQRT':
			case 'TAN':
			case 'TRUNCATE':
			case 'RANDOM':
			case 'PI':
			case 'LTRIM':
			case 'RTRIM':
				return WP_SQLite_Token_Factory::create_function( $name, $args );

			case 'CEIL':
			case 'CEILING':
				return WP_SQLite_Token_Factory::create_function( 'CEIL', $args );

			case 'COT':
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw( '1 / ' ),
						WP_SQLite_Token_Factory::create_function( 'TAN', $args ),
					)
				);

			case 'LN':
			case 'LOG':
			case 'LOG2':
				return WP_SQLite_Token_Factory::create_function( 'LOG', $args );

			case 'LOG10':
				return WP_SQLite_Token_Factory::create_function( 'LOG10', $args );

			// case 'MOD':
			//     return $this->transformBinaryOperation([
			//         'operator' => '%',
			//         'left' => $args[0],
			//         'right' => $args[1]
			//     ]);

			case 'POW':
			case 'POWER':
				return WP_SQLite_Token_Factory::create_function( 'POW', $args );

			// String functions
			case 'ASCII':
				return WP_SQLite_Token_Factory::create_function( 'UNICODE', $args );
			case 'CHAR_LENGTH':
			case 'LENGTH':
				return WP_SQLite_Token_Factory::create_function( 'LENGTH', $args );
			case 'CONCAT':
				$concated = array( WP_SQLite_Token_Factory::raw( '(' ) );
				foreach ( $args as $k => $arg ) {
					$concated[] = $arg;
					if ( $k < count( $args ) - 1 ) {
						$concated[] = WP_SQLite_Token_Factory::raw( '||' );
					}
				}
				$concated[] = WP_SQLite_Token_Factory::raw( ')' );
				return new WP_SQLite_Expression( $concated );
			// case 'CONCAT_WS':
			//     return new WP_SQLite_Expression([
			//         SQLiteTokenFactory::raw("REPLACE("),
			//         implode(" || ", array_slice($args, 1)),
			//         SQLiteTokenFactory::raw(", '', "),
			//         $args[0],
			//         SQLiteTokenFactory::raw(")")
			//     ]);
			case 'INSTR':
				return WP_SQLite_Token_Factory::create_function( 'INSTR', $args );
			case 'LCASE':
			case 'LOWER':
				return WP_SQLite_Token_Factory::create_function( 'LOWER', $args );
			case 'LEFT':
				return WP_SQLite_Token_Factory::create_function(
					'SUBSTR',
					array(
						$args[0],
						'1',
						$args[1],
					)
				);
			case 'LOCATE':
				return WP_SQLite_Token_Factory::create_function(
					'INSTR',
					array(
						$args[1],
						$args[0],
					)
				);
			case 'REPEAT':
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw( "REPLACE(CHAR(32), ' ', " ),
						$args[0],
						WP_SQLite_Token_Factory::raw( ')' ),
					)
				);

			case 'REPLACE':
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw( 'REPLACE(' ),
						implode( ', ', $args ),
						WP_SQLite_Token_Factory::raw( ')' ),
					)
				);
			case 'RIGHT':
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw( 'SUBSTR(' ),
						$args[0],
						WP_SQLite_Token_Factory::raw( ', -(' ),
						$args[1],
						WP_SQLite_Token_Factory::raw( '))' ),
					)
				);
			case 'SPACE':
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw( "REPLACE(CHAR(32), ' ', '')" ),
					)
				);
			case 'SUBSTRING':
			case 'SUBSTR':
				return WP_SQLite_Token_Factory::create_function( 'SUBSTR', $args );
			case 'UCASE':
			case 'UPPER':
				return WP_SQLite_Token_Factory::create_function( 'UPPER', $args );

			case 'DATE_FORMAT':
				$mysql_date_format_to_sqlite_strftime = array(
					'%a' => '%D',
					'%b' => '%M',
					'%c' => '%n',
					'%D' => '%jS',
					'%d' => '%d',
					'%e' => '%j',
					'%H' => '%H',
					'%h' => '%h',
					'%I' => '%h',
					'%i' => '%M',
					'%j' => '%z',
					'%k' => '%G',
					'%l' => '%g',
					'%M' => '%F',
					'%m' => '%m',
					'%p' => '%A',
					'%r' => '%h:%i:%s %A',
					'%S' => '%s',
					'%s' => '%s',
					'%T' => '%H:%i:%s',
					'%U' => '%W',
					'%u' => '%W',
					'%V' => '%W',
					'%v' => '%W',
					'%W' => '%l',
					'%w' => '%w',
					'%X' => '%Y',
					'%x' => '%o',
					'%Y' => '%Y',
					'%y' => '%y',
				);
				// @TODO: Implement as user defined function to avoid
				//        rewriting something that may be an expression as a string
				$format     = $args[1]->elements[0]->value;
				$new_format = strtr( $format, $mysql_date_format_to_sqlite_strftime );

				return WP_SQLite_Token_Factory::create_function(
					'STRFTIME',
					array(
						new WP_SQLite_Expression( array( WP_SQLite_Token_Factory::raw( $new_format ) ) ),
						new WP_SQLite_Expression( array( $args[0] ) ),
					)
				);
			case 'DATEDIFF':
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::create_function( 'JULIANDAY', array( $args[0] ) ),
						WP_SQLite_Token_Factory::raw( ' - ' ),
						WP_SQLite_Token_Factory::create_function( 'JULIANDAY', array( $args[1] ) ),
					)
				);
			case 'DAYNAME':
				return WP_SQLite_Token_Factory::create_function(
					'STRFTIME',
					array_merge( array( '%w' ), $args )
				);
			case 'DAY':
			case 'DAYOFMONTH':
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw( "CAST('" ),
						WP_SQLite_Token_Factory::create_function( 'STRFTIME', array_merge( array( '%d' ), $args ) ),
						WP_SQLite_Token_Factory::raw( ") AS INTEGER'" ),
					)
				);
			case 'DAYOFWEEK':
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw( "CAST('" ),
						WP_SQLite_Token_Factory::create_function( 'STRFTIME', array_merge( array( '%w' ), $args ) ),
						WP_SQLite_Token_Factory::raw( ") + 1 AS INTEGER'" ),
					)
				);
			case 'DAYOFYEAR':
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw( "CAST('" ),
						WP_SQLite_Token_Factory::create_function( 'STRFTIME', array_merge( array( '%j' ), $args ) ),
						WP_SQLite_Token_Factory::raw( ") AS INTEGER'" ),
					)
				);
			case 'HOUR':
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw( "CAST('" ),
						WP_SQLite_Token_Factory::create_function( 'STRFTIME', array_merge( array( '%H' ), $args ) ),
						WP_SQLite_Token_Factory::raw( ") AS INTEGER'" ),
					)
				);
			case 'MINUTE':
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw( "CAST('" ),
						WP_SQLite_Token_Factory::create_function( 'STRFTIME', array_merge( array( '%M' ), $args ) ),
						WP_SQLite_Token_Factory::raw( ") AS INTEGER'" ),
					)
				);
			case 'MONTH':
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw( "CAST('" ),
						WP_SQLite_Token_Factory::create_function( 'STRFTIME', array_merge( array( '%m' ), $args ) ),
						WP_SQLite_Token_Factory::raw( ") AS INTEGER'" ),
					)
				);
			case 'MONTHNAME':
				return WP_SQLite_Token_Factory::create_function( 'STRFTIME', array_merge( array( '%m' ), $args ) );
			case 'NOW':
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw( 'CURRENT_TIMESTAMP()' ),
					)
				);
			case 'SECOND':
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw( "CAST('" ),
						WP_SQLite_Token_Factory::create_function( 'STRFTIME', array_merge( array( '%S' ), $args ) ),
						WP_SQLite_Token_Factory::raw( ") AS INTEGER'" ),
					)
				);
			case 'TIMESTAMP':
				return new WP_SQLite_Expression(
					array_merge(
						array( WP_SQLite_Token_Factory::raw( 'DATETIME(' ) ),
						$args,
						array( WP_SQLite_Token_Factory::raw( ')' ) )
					)
				);
			case 'YEAR':
				return new WP_SQLite_Expression(
					array(
						WP_SQLite_Token_Factory::raw( "CAST('" ),
						WP_SQLite_Token_Factory::create_function( 'STRFTIME', array_merge( array( '%Y' ), $args ) ),
						WP_SQLite_Token_Factory::raw( ") AS INTEGER'" ),
					)
				);
			case 'FOUND_ROWS':
				$this->has_found_rows_call = true;
				return new WP_SQLite_Expression(
					array(
						// Post-processed in handleSqlCalcFoundRows()
						WP_SQLite_Token_Factory::raw( 'FOUND_ROWS' ),
					)
				);
			default:
				throw new Exception( 'Unsupported function: ' . $name );
		}
	}
}
