<?php
class BuildSql_Insert {
	private $_table = '';
	private $_data = array();
	public function __construct($escapeFunction = 'addslashes', $table = null, $data = null) {
		parent::__construct($escapeFunction);
		if (!is_null($table)) {
			$this->_table = $table;
		}
		if (!is_null($data)) {
			$this->_data = $data;
		}
	}
	public function query() {
		$sql = 'INSERT INTO ' . $this->_table . ' ';
		$fields = array();
		$values = array();
		foreach ($this->_data as $k => $v) {
			$fields[] = $k;
			$values[] = "'" . mysql_real_escape_string($v) . "'";
		}
		$sql .= '(`' . join('`, `', $fields) . '`)';
		$sql .= ' VALUES ';
		$sql .= '(' . join(', ', $values) . ')';
		return $sql;
	}
}
