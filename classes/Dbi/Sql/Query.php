<?php

abstract class Dbi_Sql_Query {
	protected $_tables = array();
	protected function makeArray($data) {
		if (is_array($data)) return $data;
		if (is_object($data)) return (array)$data;
		return preg_split('/[\s]?,[\s]?/', $data);
	}
	public function table($tbl) {
		$this->_tables = array_merge($this->_tables, $this->makeArray($tbl));
	}
	/*
	 * @return Dbi_Sql_Expression
	 */
	abstract public function expression();
}
