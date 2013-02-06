<?php
/**
 * An object that enables chaining of multiple expressions with AND/OR.
 */
class Dbi_Sql_Criteria {
	private $_query;
	private $_expressions = array();
	public function __construct(Dbi_Model $query, Dbi_Sql_Expression $expression) {
		$this->_query = $query;
		$this->_expressions[] = $expression;
	}
	/**
	 * Add a where clause to the query.
	 * @param string $statement A parameterized statement (e.g., "name = ?")
	 * @param scalar|array $args,... Arguments for statement parameters.
	 * @return Dbi_Sql_Criteria An object that can be used to chain clauses.
	 */
	public function where() {
		$args = func_get_args();
		call_user_func_array(array($this, 'andWhere'), $args);
	}
	/**
	 * Add a where clause to the query.
	 * @param string $statement A parameterized statement (e.g., "name = ?")
	 * @param scalar|array $args,... Arguments for statement parameters.
	 * @return Dbi_Sql_Criteria An object that can be used to chain clauses.
	 */
	public function andWhere() {
		$args = func_get_args();
		return call_user_func_array(array($this->_query, 'andWhere'), $args);
	}
	/**
	 * Append a where clause using OR to the most recently added criteria.
	 * @param string $statement A parameterized statement (e.g., "name = ?")
	 * @param scalar|array $args,... Arguments for statement parameters.
	 * @return Dbi_Sql_Criteria An object that can be used to chain clauses.
	 */
	public function orWhere($expression) {
		$args = func_get_args();
		$expr = array_shift($args);
		$expression = new Dbi_Sql_Expression($expr, $args);
		$this->_expressions[] = $expression;
		return $this;
	}
	/**
	 * Get an array of the expressions in this criteria. In queries, each
	 * expression in a criteria is optional, e.g., WHERE (expression) OR (expression),
	 * while multiple criteria objects are chained with AND.
	 * @return Dbi_Sql_Expression[]
	 */
	public function expressions() {
		return $this->_expressions;
	}
	// TODO: For chaining purposes, it might be cool to let query functions cascade into $this->_query
	// (order, group, etc.)
}
