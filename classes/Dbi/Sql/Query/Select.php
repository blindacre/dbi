<?php

class Dbi_Sql_Query_Select extends Dbi_Sql_QueryWhere {
	private $_fields = array();
	private $_joins = array();
	private $_orders = array();
	private $_groups = array();
	private $_havings = array();
	private $_limit = '';
	public function field($fld) {
		$this->_fields = array_merge($this->_fields, $this->makeArray($fld));
	}
	private function _join($type, $args) {
		if (count($args) < 2) {
			throw new Exception("Joins require at least two arguments: a table and an expression");
		}
		$tbl = array_shift($args);
		$on = array_shift($args);
		return new Dbi_Sql_Expression("{$type} JOIN {$tbl} ON {$on}", $args);
	}
	public function innerJoin() {
		$args = func_get_args();
		$this->_joins[] = $this->_join('INNER', $args);
	}
	public function leftJoin() {
		$args = func_get_args();
		$this->_joins[] = $this->_join('LEFT', $args);
	}
	public function rightJoin() {
		$args = func_get_args();
		$this->_joins[] = $this->_join('RIGHT', $args);
	}
	public function order($fld) {
		$this->_orders = array_merge($this->_orders, $this->makeArray($fld));
	}
	public function group($fld) {
		$this->_groups = array_merge($this->_groups, $this->makeArray($fld));
	}
	public function having() {
		$args = func_get_args();
		$expr = array_shift($args);
		if (is_null($expr)) return;
		$this->_havings[] = new Dbi_Sql_Expression($expr, $args);
	}
	public function limit($lim) {
		$this->_limit = $lim;
	}
	public function expression() {
		$parameters = array();
		$sql = 'SELECT ';
		$sql .= "\n" . join(', ', $this->_fields) . ' ';
		$sql .= "\nFROM " . join(", ", $this->_tables) . ' ';
		foreach ($this->_joins as $join) {
			$sql .= "\n" . $join->statement();
			$parameters = array_merge($parameters, $join->parameters());
		}
		if (count($this->_wheres)) {
			$append = "\nWHERE ";
			foreach ($this->_wheres as $where) {
				$sql .= "\n{$append} (" . $where->statement() . ")";
				$parameters = array_merge($parameters, $where->parameters());
				$append = ' AND ';
			}
		}
		if (count($this->_groups)) {
			$sql .= "\nGROUP BY " . join(", ", $this->_groups);
		}
		if (count($this->_havings)) {
			$append = "\nHAVING ";
			foreach ($this->_havings as $having) {
				$sql .= "\n{$append} (" . $having->statement() . ")";
				$parameters = array_merge($parameters, $having->parameters());
				$append = ' AND ';
			}
		}
		if (count($this->_orders)) {
			$sql .= "\nORDER BY " . join(", ", $this->_orders);
		}
		if ($this->_limit) {
			$sql .= "\nLIMIT " . $this->_limit;
		}
		return new Dbi_Sql_Expression($sql, $parameters);
	}
}
