<?php
class Dbi_Field_Varchar extends Dbi_Field {
	private $_size;
	public function __construct($size, $defaultValue = '', $allowNull = false, $auto = '', $extras = array()) {
		parent::__construct($allowNull, $auto, $extras);
		$this->_size = $size;
	}
	public function size() {
		return $this->_size;
	}
}
