<?php
abstract class Dbi_Schema implements Dbi_SchemaInterface {
	protected $name = null;
	protected $prefix = null;
	protected $fieldset = array();
	protected $indexes = array();
	public function __construct() {
		
	}
	/**
	 * The name of the schema (usually the database table's name).
	 * @return string
	 */
	public function name() {
		return $this->name;
	}
	/**
	 * An optional prefix for table names, sometimes used by certain DBI
	 * sources (e.g., MySQL). This prefix is typically ignored elsewhere.
	 * @return string The prefix.
	 */
	public function prefix() {
		return $this->prefix;
	}
	/**
	 * Add a field to the schema.
	 * @param string $name The name of the field.
	 * @param Dbi_Field $field The field's definition.
	 */
	protected function addField($name, Dbi_Field $field = null) {
		// TODO: Check if the name is already defined?
		if (is_null($field)) {
			$field = new Dbi_Field('varchar', array(255));
		}
		$this->fieldset[$name] = $field;
	}
	/**
	 * Get an associative array of all the fields in the schema.
	 * @return Dbi_Field[]
	 */
	public function fields() {
		return $this->fieldset;
	}
	/**
	 * Get the definition for a field in the schema.
	 * @param string $name The name of the field.
	 * @return Dbi_Field|null The field's definition.
	 */
	public function field($name) {
		if (isset($this->fieldset[$name])) {
			return $this->fieldset[$name];
		}
		return null;
	}
	/**
	 * Add an index to the schema.
	 * @param string $name The index's name.
	 * @param array $fields The names of the fields to be indexed.
	 * @param string $type The type of index (e.g., 'unique')
	 */
	protected function addIndex($name, $fields, $type = '') {
		if (!is_array($fields)) {
			$fields = array($fields);
		}
		$this->indexes[$name] = array('type' => $type, 'fields' => $fields);
	}
	/**
	 * Get an associative array of all the indexes in the schema.
	 * @return array The indexes.
	 */
	public function indexes() {
		return $this->indexes;
	}
	/**
	 * Get an array of the fields in the schema's primary index.
	 * @return array The primary index fields.
	 */
	public function primary() {
		return $this->index('primary');
	}
	/**
	 * Get an array of the fields in the specified index.
	 * @param string $name The name of the index.
	 * @return array The fields in the index.
	 */
	public function index($name) {
		if (isset($this->indexes[$name])) {
			return $this->indexes[$name];
		}
		return null;
	}
	/**
	 * Extend this schema with the field and index definitions from another schema.
	 * @param Dbi_Schema $schema
	 */
	protected function extendSchema(Dbi_Schema $schema) {
		$this->fieldset = array_merge($this->fieldset, $schema->fieldset);
		$this->indexes = array_merge($this->indexes, $schema->indexes);
	}
}
