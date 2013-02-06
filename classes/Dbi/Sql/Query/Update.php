<?php

class Dbi_Sql_Query_Update extends Dbi_Sql_QueryWhere {
	private $_sets;
	public function set() {
		$args = func_get_args();
		$expr = array_shift($args);
		if (is_null($expr)) return;
		$this->_sets[] = new Dbi_Sql_Expression($expr, $args);
	}
	public function expression() {
		$parameters = array();
		$sql = 'UPDATE ';
		$sql .= "\n" . join(', ', $this->_tables) . ' ';
		$append = "\nSET ";
		foreach ($this->_sets as $set) {
			$sql .= $append . $set->statement();
			$parameters = array_merge($parameters, $set->parameters());
			$append = ', ';
		}
		$append = "\nWHERE ";
		foreach ($this->_wheres as $where) {
			$sql .= $append . $where->statement();
			$parameters = array_merge($parameters, $where->parameters());
			$append = ' AND ';
		}
		return new Dbi_Sql_Expression($sql, $parameters);
	}
}
