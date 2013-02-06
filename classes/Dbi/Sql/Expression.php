<?php
/**
 * A parameterized expression to be parsed into a query. The statement can be
 * a complete query (e.g., a SELECT statement) or a fragment of a query (e.g.,
 * a WHERE clause or a comparison).
 */
class Dbi_Sql_Expression {
	private $_statement;
	private $_parameters;
	/**
	 * @param $statement
	 * @param $parameters
	 */
	public function __construct($statement, $parameters) {
		$this->_statement = $statement;
		$this->_parameters = $parameters;
	}
	/**
	 * Get the parameterized statement.
	 * @return string
	 */
	public function statement() {
		return $this->_statement;
	}
	/**
	 * Get an array of statement parameters.
	 * @return array
	 */
	public function parameters() {
		return $this->_parameters;
	}
}
