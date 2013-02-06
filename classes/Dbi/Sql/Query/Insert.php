<?php

class Dbi_Sql_Query_Insert extends Dbi_Sql_Query {
	private $_sets;
	public function set() {
		$args = func_get_args();
		$expr = array_shift($args);
		if (strpos($expr, '=') == false) $expr = "{$expr} = ?";
		if (is_null($expr)) return;
		$this->_sets[] = new Dbi_Sql_Expression($expr, $args);
	}
	public function expression() {
		$parameters = array();
		$sql = 'INSERT INTO ';
		$sql .= "\n" . join(', ', $this->_tables) . ' ';
		$append = "\nSET ";
		foreach ($this->_sets as $set) {
			$sql .= $append . $set->statement();
			$parameters = array_merge($parameters, $set->parameters());
			$append = ', ';
		}
		return new Dbi_Sql_Expression($sql, $parameters);
	}
}
