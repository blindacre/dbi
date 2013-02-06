<?php
class BuildSql_Update extends BuildSql_Base {
	private $_table = '';
	private $_data = array();
	private $_wheres = array();
	public function table($tbl) {
		$this->_table = $tbl;
	}
	public function set($data) {
		$this->_data = $data;
	}
	public function where() {
		$args = func_get_args();
		$expr = array_shift($args);
		array_push($this->_wheres, '(' . $this->_parameterize($expr, $args) . ')');
		$this->_dirty = true;
	}
	public function query() {
		$sql = 'UPDATE ' . $this->_table;
		$sets = array();
		foreach ($this->_data as $key => $value) {
			if (is_null($value))
				$sets[] = "`{$key}` = NULL";
			else
				$sets[] = "`{$key}` = '" . mysql_real_escape_string($value) . "'";
		}
		$sql .= "\nSET " . join(', ', $sets);
		if (count($this->_wheres) > 0) {
			$sql .= "\nWHERE " . join(" AND ", $this->_wheres) . ' ';
		}
		return $sql;
	}
}
