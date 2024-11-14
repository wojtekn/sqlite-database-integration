<?php

// @TODO: Remove the namespace and use statements when replacing the old driver.
namespace WIP;

class WP_SQLite_Token {
	const TYPE_RAW        = 'TYPE_RAW';
	const TYPE_IDENTIFIER = 'TYPE_IDENTIFIER';
	const TYPE_VALUE      = 'TYPE_VALUE';
	const TYPE_OPERATOR   = 'TYPE_OPERATOR';

	public $type;
	public $value;

	public function __construct( string $type, $value ) {
		$this->type  = $type;
		$this->value = $value;
	}
}
