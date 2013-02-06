<?php
/**
 * A parameterized expression to be parsed as criteria in a query, e.g., a
 * SQL WHERE clause. Each expression should represent a single operation
 * (key = value). Multiple expressions can be chained with AND/OR logic using
 * Dbi_Query_Criteria objects.
 */
class Dbi_Query_Expression {
	private $_statement;
	private $_parameters;
	/**
	 * @param array An array where the first member is a parameterized
	 * statement (e.g., "name = ?") and the rest are the statement's parameters.
	 */
	public function __construct() {
		$args = func_get_args();
		if ( (count($args) == 1) && (is_array($args[0])) ) {
			$args = $args[0];
		}
		$this->_statement = array_shift($args);
		if ( (strpos($this->_statement, '"') !== false) || (strpos($this->_statement, "`") !== false) ) {
			throw new Exception("Query expression '{$this->_statement}' contains raw strings");
		}
		$this->_parameters = $args;
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
	 * @return scalar[]
	 */
	public function parameters() {
		return $this->_parameters;
	}
}
