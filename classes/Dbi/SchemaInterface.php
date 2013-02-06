<?php
// This interface ensures that classes extending Dbi_Schema always have a constructor
// that does not require arguments. Optional arguments will still work.
interface Dbi_SchemaInterface {
	public function __construct();
}
