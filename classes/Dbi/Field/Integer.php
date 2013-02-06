<?php
class Dbi_Field_Integer extends Dbi_Field {
	private $_size;
	private $_unsigned;
	public function __construct($size, $unsigned = false, $defaultValue = '', $allowNull = false, $auto = '', $extras = array()) {
		parent::__construct($allowNull, $auto, $extras);
		$this->_size = $size;
		$this->_unsigned = $unsigned;
	}
	public function size() {
		return $this->_size;
	}
}
