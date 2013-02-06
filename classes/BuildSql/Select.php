<?php
class BuildSql_Select extends BuildSql_Base {
	private $_tables = array();
	private $_fields = array();
	private $_joins = array();
	private $_wheres = array();
	private $_orders = array();
	private $_groups = array();
	private $_havings = array();
	private $_limit = '';
	private $_broken = false;
	private $_cached = '';
	private $_dirty = false;
	private $_tableDefs = array();
	private $_tableJoins = array();
	public function table($tbl) {
		$parts = explode(',', $tbl);
		foreach ($parts as $p) {
			array_push($this->_tables, trim($p));
		}
		$this->_dirty = true;
	}
	public function getTables() {
		return $this->_tables;
	}
	public function field() {
		$args = func_get_args();
		if (!count($args)) return;
		$fld = array_shift($args);
		if (is_null($fld)) {
			$this->_fields = array();
		} else {
			if (is_array($fld)) {
				$fld = implode(',', $fld);
			}
			$newFields = $this->_parseFields($fld, $args);
			foreach ($newFields as $f) {
				$parts = explode('.', $f);
				if (count($f) > 1) {
					$column = array_pop($parts);
					$table = array_pop($parts);
					if (!isset($this->_tableDefs[$table])) {
						if ($column == '*') {
							$this->_tableDefs[$table] = '*';
						} else {
							$this->_tableDefs[$table] = array();
						}
					}
					if (is_array($this->_tableDefs[$table])) {
						if ($column == '*') {
							$this->_tableDefs[$table] = '*';
						} else {
							if (strpos($column, ' ') === false) {
								$this->_tableDefs[$table][] = $column;
							}
						}
					}
				}
			}
			//$this->_fields = array_merge($this->_fields, $this->_parseFields($fld));
			$this->_fields = array_merge($this->_fields, $newFields);
		}
		$this->_dirty = true;
	}
	/**
	 * Add an inner join to the query
	 * Arguments to all joins: table, expression, [parameters]
	 */
	public function innerJoin() {
		$args = func_get_args();
		if (count($args) < 2) {
			trigger_error("Joins require at least two arguments: a table and an expression");
			$this->_broken = true;
			return false;
		}
		$tbl = array_shift($args);
		$on = array_shift($args);
		$parameters = $this->_parameterize($on, $args);
		array_push($this->_joins, "INNER JOIN {$tbl} ON " . $parameters);
		$this->_tableJoins[$tbl] = $parameters;
		$this->_dirty = true;
	}
	/**
	 * Add an left outer join to the query
	 * Arguments to all joins: table, expression, [parameters]
	 */
	public function leftJoin($tbl, $on) {
		$args = func_get_args();
		if (count($args) < 2) {
			trigger_error("Joins require at least two arguments: a table and an expression");
			$this->_broken = true;
			return false;
		}
		$tbl = array_shift($args);
		$on = array_shift($args);
		$parameters = $this->_parameterize($on, $args);
		array_push($this->_joins, "LEFT JOIN {$tbl} ON " . $parameters);
		$this->_tableJoins[$tbl] = $parameters;
		$this->_dirty = true;
	}
	/**
	 * Add a right outer join to the query
	 * Arguments to all joins: table, expression, [parameters]
	 */
	public function rightJoin($tbl, $on) {
		$args = func_get_args();
		if (count($args) < 2) {
			trigger_error("Joins require at least two arguments: a table and an expression");
			$this->_broken = true;
			return false;
		}
		$tbl = array_shift($args);
		$on = array_shift($args);
		$parameters = $this->_parameterize($on, $args);
		array_push($this->_joins, "RIGHT JOIN {$tbl} ON " . $parameters);
		$this->_tableJoins[$tbl] = $parameters;
		$this->_dirty = true;
	}
	/**
	 * Add where criteria to the query
	 * Multiple calls to this function will append the criteria using AND
	 * Arguments: expression, [parameters]
	 */
	public function where() {
		$args = func_get_args();
		$expr = array_shift($args);
		if (is_null($expr))
		{
			$this->_wheres = array();
			return;
		}
		$expr = trim($expr);
		$oper = '';
		// Check for a conjuction.  Use AND if none is specified.  Make sure
		// the first clause does not start with a conjunction.
		if (substr(strtoupper($expr), 0, 4) == 'AND ') {
			$expr = substr($expr, 4);
			if (count($this->_wheres)) {
				$oper = 'AND ';
			}
		} else if (substr(strtoupper($expr), 0, 3) == 'OR ') {
			$expr = substr($expr, 3);
			if (count($this->_wheres)) {
				$oper = 'OR ';
			}
		} else if (count($this->_wheres)) {
			// AND is default
			$oper = 'AND ';
		}
		array_push($this->_wheres, $oper . '(' . $this->_parameterize($expr, $args) . ')');
		$this->_dirty = true;
	}
	public function order($fld) {
		// TODO: Refactor this.  New order criteria supercede existing ones.
		if (is_null($fld)) {
			$this->_orders = array();
		} else {
			$this->_orders = array();
			$parts = explode(',', $fld);
			foreach ($parts as $p) {
				array_push($this->_orders, trim($p));
			}
		}
		$this->_dirty = true;
	}
	public function group($fld) {
		// TODO: Refactor this.  New group criteria supercede existing ones.
		if (is_null($fld)) {
			$this->_groups = array();
		} else {
			if(is_array($fld)){
				$parts = $fld;
			}
			else{
				$parts = explode(',', $fld);
			}
			foreach ($parts as $p) {
				array_push($this->_groups, trim($p));
			}
		}
		$this->_dirty = true;
	}
	public function having() {
		$args = func_get_args();
		$expr = array_shift($args);
		array_push($this->_havings, '(' . $this->_parameterize($expr, $args) . ')');
		$this->_dirty = true;
	}
	public function limit($lim) {
		// TODO: Validate syntax
		$this->_limit = $lim;
		$this->_dirty = true;
	}
	public function query() {
		if ($this->_broken) {
			trigger_error('Cannot output malformed query', E_USER_WARNING);
			return '';
		}
		if (!$this->_dirty) return $this->_cached;
		if (count($this->_fields) == 0) {
			trigger_error('No fields specified for query');
			$this->_broken = true;
			return '';
		}
		if (!$this->_tables) {
			trigger_error('No tables specified for query');
			$this->_broken = true;
			return '';
		}
		$sql = 'SELECT ';
		$sql .= join(", ", $this->_fields) . ' ';
		$sql .= "\nFROM " . join(", ", $this->_tables) . ' ';
		if (count($this->_joins) > 0) {
			$sql .= "\n" . join(" \n", $this->_joins) . ' ';
		}
		if (count($this->_wheres) > 0) {
			$sql .= "\nWHERE " . join(" ", $this->_wheres) . ' ';
		}
		if (count($this->_groups) > 0) {
			$sql .= "\nGROUP BY " . join(", ", $this->_groups) . ' ';
		}
		if (count($this->_havings) > 0) {
			$sql .="\nHAVING " . join(" AND ", $this->_havings) . ' ';
		}
		if (count($this->_orders) > 0) {
			$sql .= "\nORDER BY " . join(", ", $this->_orders) . ' ';
		}
		if ($this->_limit) {
			$sql .= "\nLIMIT " . $this->_limit;
		}
		$this->_cached = $sql;
		$this->_dirty = false;
		//$sql = str_replace('#__', DBI_PREFIX, $sql);
		return $sql;
	}
	public function getFields() {
		return $this->_fields;
	}
	public function getTableDefs() {
		return $this->_tableDefs;
	}
	public function getJoinFor($table) {
		return (isset($this->_tableJoins[$table]) ? $this->_tableJoins[$table] : null);
	}
}
