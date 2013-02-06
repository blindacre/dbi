<?php
class BuildSql_Delete extends BuildSql_Base {
	private $_table = '';
	private $_limit;
	private $_wheres = array();

	public function table($tbl) {
		$this->_table = $tbl;
	}

	/**
	 * Add where criteria to the query
	 * Multiple calls to this function will append the criteria using AND
	 * Arguments: expression, [parameters]
	 */
	public function where() {
		$args = func_get_args();
		$expr = array_shift($args);
		array_push($this->_wheres, '(' . $this->_parameterize($expr, $args) . ')');
		$this->_dirty = true;
	}
	/**
	 * Set the where clause, based on a simple key/value array.
	 *
	 * Will join them together with '='.
	 */
	public function whereArray($wheres){
		foreach($wheres as $k => $v){
			$this->where("`$k`=?", $v);
		}
	}
	public function order($fld) {
		$parts = explode(',', $fld);
		foreach ($parts as $p) {
			array_push($this->_orders, trim($p));
		}
		$this->_dirty = true;
	}
	public function group($fld) {
		$parts = explode(',', $fld);
		foreach ($parts as $p) {
			array_push($this->_groups, trim($p));
		}
		$this->_dirty = true;
	}
	public function limit($lim) {
		// TODO: Validate syntax
		$this->_limit = $lim;
		$this->_dirty = true;
	}
	public function query() {
		$sql = 'DELETE FROM ' . $this->_table;

		if (count($this->_wheres) > 0) {
			$sql .= "\nWHERE " . join(" AND ", $this->_wheres) . ' ';
		}
		if ($this->_limit) {
			$sql .= "\nLIMIT " . $this->_limit;
		}
		return $sql;
	}
}
