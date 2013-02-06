<?php
/**
 * An abstract class for creating subclasses that act as simple containers but have
 * documentable magic properties. ArrayAccess is also implemented for portability.
 */
abstract class PropertyIteratorAbstract implements Iterator, ArrayAccess {
	private $_data;
	/**
	 * @param array $data An associative array of initial values.
	 */
	public function __construct(array $data = array()) {
		$this->_data = $data;
	}
	public function &__get($key) {
		$value = null;
		if (isset($this->_data[$key])) {
			$value =& $this->_data[$key];
		}
		return $value;
	}
	public function __set($key, $value) {
		if (is_null($value)) {
			unset($this->_data[$key]);
		} else {
			$this->_data[$key] = $value;
		}
	}
	public function rewind() {
		reset($this->_data);
	}
	public function current() {
		$var = current($this->_data);
		return $var;
	}
	public function key() {
		$var = key($this->_data);
		return $var;
	}
	public function next() {
		$var = next($this->_data);
		return $var;
	}
	public function valid() {
		$key = key($this->_data);
		$var = ($key !== null && $key !== false);
		return $var;
	}
	public function offsetSet($offset, $value) {
		//echo "Calling offsetSet in " . __CLASS__ . "!<br/>"; // DEBUG //
        $this->__set($offset, $value);
    }
    public function offsetExists($offset) {
    	// I need to use this method because it includes derived properties.
    	//$dat = $this->getAsArray();
        //return isset($dat[$offset]);
		return ( isset($this->_data[$offset]) );
    }
    public function offsetUnset($offset) {
    	// @todo Should this be disabled?...
        unset($this->_data[$offset]);
    }
    public function offsetGet($offset) {
    	$value = $this->__get($offset);
		return $value;
    }
}
