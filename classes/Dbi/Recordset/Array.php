<?php
class Dbi_Recordset_Array extends Dbi_Recordset {
	private $_model;
	private $_records;
	public function __construct(Dbi_Model $model, array $records) {
		$this->_model = $model;
		$this->_records = $records;
	}
	public function count() {
		return count($this->_records);
	}
	public function rewind() {
		reset($this->_records);
	}
	public function current() {
		$record = current($this->_records);
		return new Dbi_Record($this->_model, $record);
	}
	public function key() {
		return key($this->_records);
	}
	public function next() {
		$record = next($this->_records);
		if ($record) {
			return new Dbi_Record($this->_model, $record);
		}
	}
	public function valid() {
		return !is_null(key($this->_records));
	}
	public function offsetExists($offset) {
		return isset($this->_records[$offset]);
	}
	public function offsetGet($offset) {
		return new Dbi_Record($this->_model, $this->_records[$offset]);
	}
}
