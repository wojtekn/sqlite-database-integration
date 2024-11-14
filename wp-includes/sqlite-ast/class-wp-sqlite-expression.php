<?php

// @TODO: Remove the namespace and use statements when replacing the old driver.
namespace WIP;

class WP_SQLite_Expression {
	public $elements;

	public function __construct( array $elements = array() ) {
		$new_elements = array();
		$elements     = array_filter( $elements );
		foreach ( $elements as $element ) {
			if ( is_object( $element ) && $element instanceof WP_SQLite_Expression ) {
				$new_elements = array_merge( $new_elements, $element->elements );
			} else {
				$new_elements[] = $element;
			}
		}
		$this->elements = $new_elements;
	}

	public function get_tokens() {
		return $this->elements;
	}

	public function add_token( WP_SQLite_Token $token ) {
		$this->elements[] = $token;
	}

	public function add_tokens( array $tokens ) {
		foreach ( $tokens as $token ) {
			$this->add_token( $token );
		}
	}

	public function add_expression( $expression ) {
		$this->add_token( $expression );
	}
}
