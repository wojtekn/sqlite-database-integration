<?php

// @TODO: Remove the namespace and use statements when replacing the old driver.
namespace WIP;

use InvalidArgumentException;

class WP_SQLite_Query_Builder {
	private $expression;

	public static function stringify( WP_SQLite_Expression $expression ) {
		return ( new WP_SQLite_Query_Builder( $expression ) )->build_query();
	}

	public function __construct( WP_SQLite_Expression $expression ) {
		$this->expression = $expression;
	}

	public function build_query(): string {
		$query_parts = array();
		foreach ( $this->expression->get_tokens() as $element ) {
			if ( $element instanceof WP_SQLite_Token ) {
				$query_parts[] = $this->process_token( $element );
			} elseif ( $element instanceof WP_SQLite_Expression ) {
				$query_parts[] = '(' . ( new self( $element ) )->build_query() . ')';
			}
		}
		return implode( ' ', $query_parts );
	}

	private function process_token( WP_SQLite_Token $token ): string {
		switch ( $token->type ) {
			case WP_SQLite_Token::TYPE_OPERATOR:
			case WP_SQLite_Token::TYPE_RAW:
			case WP_SQLite_Token::TYPE_VALUE:
				return $token->value;
			case WP_SQLite_Token::TYPE_IDENTIFIER:
				return '"' . str_replace( '"', '""', $token->value ) . '"';
			default:
				throw new InvalidArgumentException( 'Unknown token type: ' . $token->type );
		}
	}
}
