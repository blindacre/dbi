<?php
abstract class Dbi_Recordset implements Iterator, ArrayAccess, Countable {
	abstract public function count();
	abstract public function rewind();
	abstract public function current();
	abstract public function key();
	abstract public function next();
	abstract public function valid();
	abstract public function offsetExists($offset);
	abstract public function offsetGet($offset);
	final public function offsetSet($offset, $value) {
		throw new Exception('Modifying members of a recordset is not allowed');
	}
	final public function offsetUnset($offset) {
		throw new Exception('Unsetting members of a recordset is not allowed');
	}
}
