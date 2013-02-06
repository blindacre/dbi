<?php
class Dbi_Record implements ArrayAccess, Iterator {
	private $_model;
	private $_data = array();
	private $_init = array();
	private $_extradata = array();
	private $_dirty = false;
	private $_exists = false;
	private $_valid = false;
	private $_iteratorPos;
	private $_iteratorKeys;
	public function __construct(Dbi_Model $model, array $data = null) {
		$this->_model = $model;
		if ( (!is_null($data)) && (!empty($data)) ) {
			$this->_dirty = false;
			$this->_exists = true;
			$this->_valid = true;
			// Check for existence of primary key to ensure record exists / is not dirty
			if ($this->_model->primary()) {
				$primary = $this->_model->primary();
				foreach ($primary['fields'] as $key) {
					if (empty($data[$key])) {
						$this->_dirty = true;
						$this->_exists = false;
						break;
					}
				}
			}
			$this->_cleanArray($data);
			$this->_model->notify(Dbi_Model::EVENT_AFTERSELECT, $this);
			$this->_init = array_merge($this->_data, $this->_extradata);
			$this->_iteratorKeys = array_merge(array_keys($this->_data), array_keys($this->_extradata));
		} else if (!is_null($data)) {
			$this->_dirty = true;
			$this->_exists = false;
			$this->_valid = true;
		}
		if (is_a($this->_model, 'Dbi_Model_Anonymous')) {
			$this->_valid = false;
		} else {
			$this->_valid = true;
		}
	}
	/**
	 * Get the record's model.
	 * @return Dbi_Model
	 */
	public function model() {
		return $this->_model;
	}
	/**
	 * Determine whether the record can be saved to the database. (Records
	 * from certain types of queries -- e.g., queries with GROUP BY clasues --
	 * cannot be saved.)
	 * @return boolean True if the record can be saved.
	 */
	public function saveable() {
		if (is_a($this->model(), 'Dbi_Model_Anonymous')) {
			return false;
		}
		$components = $this->model()->query()->components();
		if ($components['groups']) {
			return false;
		}
		return true;
	}
	/**
	 * Save the record.
	 */
	public function save() {
		if (is_a($this->model(), 'Dbi_Model_Anonymous')) {
			throw new Exception('Unable to save anonymous record');
		}
		if (!$this->_valid) {
			throw new Exception('Unable to save invalid record');
		}
		$components = $this->model()->query()->components();
		if ($components['groups']) {
			throw new Exception("Unable to save record from grouped query");
			return;
		}
		// The beforeSave event gets triggered whether or not the record is
		// dirty. The beforeCreate and beforeUpdate events only get triggered
		// for dirty records. (Note that it's possible for a beforeSave event
		// to make changes that mark it dirty.)
		$this->model()->notify(Dbi_Model::EVENT_BEFORESAVE, $this);
		if ($this->_dirty) {
			if ($this->_exists) {
				$this->model()->notify(Dbi_Model::EVENT_BEFOREUPDATE, $this);
				$query = new Dbi_Query($this->_model);
				$primary = $this->_model->index('primary');
				if (is_null($primary)) {
					throw new Exception("Model does not have a primary key");
				}
				foreach ($primary['fields'] as $key) {
					$query->where("{$key} = ?", $this->_data[$key]);
				}
				Dbi_Source::GetModelSource($this->_model)->update($query, $this->_data);
			} else {
				$this->model()->notify(Dbi_Model::EVENT_BEFORECREATE, $this);
				// Model inserts should return the array of data that was saved.
				// This is necessary for the record to receive automatically
				// generated primary keys.
				$newData = Dbi_Source::GetModelSource($this->_model)->insert($this);
				$this->_cleanArray($newData);
			}
			$this->_init = array_merge($this->_data, $this->_extradata);
		}
		$this->_dirty = false;
		$this->_exists = true;
	}
	/**
	 * Delete the record.
	 */
	public function delete() {
		// TODO: Should we check to see if the record is dirty first?
		if (!$this->exists()) return;
		$this->_model->notify(Dbi_Model::EVENT_BEFOREDELETE, $this);
		$query = new Dbi_Query($this->model());
		$primary = $this->_model->primary();
		foreach ($primary['fields'] as $key) {
			$query->where("{$key} = ?", $this->get($key));
		}
		Dbi_Source::GetModelSource($this->_model)->delete($query);
		$this->_exists = false;
		$this->_valid = false;
	}
	/**
	 * Check if the record is dirty (i.e., changes have not been saved).
	 * @return boolean
	 */
	public function dirty() {
		return $this->_dirty;
	}
	/**
	 * Check if the record exists in the data source.
	 * @return boolean
	 */
	public function exists() {
		return $this->_exists;
	}
	/**
	 * Set a value in the record. Keys that are not defined in the model will
	 * be stored in the object, but they will not be saved with the record
	 * unless the data source does not enforce the schema.
	 * @param string $key
	 * @param mixed $value
	 */
	public function set($key, $value) {
		if (!is_null($this->_model->field($key))) {
			$this->_model->field($key)->notify(Dbi_Field::EVENT_BEFORESET, $this);
		}
		if ( ($key == 'id') && (!$this->model()->field($key)) ) {
			$p = $this->model()->primary();
			if (count($p) == 1) {
				$this->set($p[0], $value);
				return;
			}
		}
		if (!is_null($this->_model->field($key))) {
			$primary = $this->_model->index('primary');
			if ( (!is_null($primary)) && (count($primary['fields']) == 1) && ($primary['fields'][0] == $key) && ($this->exists()) && ($this->_data[$key] != $value) ) {
				throw new Exception('Cannot modify primary key ' . $key);
			} else {
				$this->_data[$key] = $value;
			}
		} else {
			$this->_extradata[$key] = $value;
		}
		// Mark the record dirty if the field is in the data schema.
		if ($this->_model->field($key)) {
			$this->_dirty = true;
		}
		$this->_iteratorKeys = array_merge(array_keys($this->_data), array_keys($this->_extradata));
	}
	/**
	 * Get a value from the record.
	 * @param string $key The name of the field.
	 * @return mixed
	 */
	public function &get($key) {
		// 'id' is a special field name that maps to the record's primary key
		// assuming that the primary key consists of one field.
		if ( ($key == 'id') && (!$this->model()->field($key)) ) {
			$p = $this->model()->primary();
			if (count($p['fields']) == 1) {
				return $this->get($p['fields'][0]);
			}
		}
		$value = null;
		if (isset($this->_data[$key])) {
			$value =& $this->_data[$key];
		} else if (isset($this->_extradata[$key])) {
			$value =& $this->_extradata[$key];
		}
		return $value;
	}
	/**
	 * Get the record's data as an associative array.
	 * @param boolean $withExtra True if the array should include extra data
	 * (fields that are not defined in the record's model).
	 * @return array An associative array of the data.
	 */
	public function getAsArray($withExtra = true) {
		if ($withExtra) {
			return array_merge($this->_data, $this->_extradata);
		} else {
			return $this->_data;
		}
	}
	/**
	 * An alias for getAsArray().
	 * @param boolean $withExtra True if the array should include extra data
	 * (fields that are not defined in the record's model).
	 * @return array An associative array of the data.
	 */
	public function getArray($withExtra = true) {
		return $this->getAsArray($withExtra);
	}
	/**
	 * Set the record's data from an associative array of key/value pairs.
	 * @param array $data An associative array of data.
	 * @param boolean $enforceWhitelist Ignore keys that are not permitted in the model's publicArrayWhitelist.
	 * @param boolean $enforceSchema Ignore keys that are not defined in the model's schema.
	 */
	public function setArray($data, $enforceWhitelist = true, $enforceSchema = true) {
		if ($enforceWhitelist) {
			$whitelist = $this->model()->publicArrayWhitelist();
		}
		foreach ($data as $key => $value) {
			if ( ( (!$enforceWhitelist) || (in_array($key, $whitelist)) ) && ( (!$enforceSchema) || ($this->_model->field($key)) ) ) {
				$this->set($key, $value);
			}
		}
	}
	/**
	 * Get an array of the record's initial data, i.e., a snapshot of what it
	 * contained when it was first loaded or after it was last saved.
	 * @return array
	 */
	public function initArray() {
		return $this->_init;
	}
	/**
	 * Set an array of data. This function is used internally for actions that
	 * are allowed to modify primary keys.
	 * @param array $data
	 */
	private function _cleanArray(array $data) {
		$joinModels = array();
		$components = $this->_model->components();
		foreach ($components['leftJoins'] as $join) {
			if (!empty($join['name'])) {
				$joinModels[$join['name']] = $join['model'];
			}
		}
		foreach ($components['innerJoins'] as $join) {
			if (!empty($join['name'])) {
				$joinModels[$join['name']] = $join['model'];
			}
		}
		foreach ($data as $key => $value) {
			/*$parts = explode('.', $key, 2);
			if (count($parts) > 1) {
				echo "Here's a subkey: {$parts[0]}<br/>";
				$subkey = $parts[0];
				$subprop = $parts[1];
				$components = $this->_model->query()->components();
				if (isset($components['innerJoins'][$subkey])) {
					if (!isset($joinModels[$subkey])) {
						$joinModels[$subkey] = get_class($components['innerJoins'][$subkey]['model']);
						$joinData[$subkey] = array();
					}
					$joinData[$subkey][$subprop] = $value;
				} else {
					throw new Exception('Bad join');
				}
			} else {
				if ($this->_model->field($key)) {
					$this->_data[$key] = $value;
				} else {
					$this->_extradata[$key] = $value;
				}
			}*/
			if (isset($joinModels[$key])) {
				$mod = $joinModels[$key];
				$value = new Dbi_Record($mod, $value);
			}
			if ($this->_model->field($key)) {
				$this->_data[$key] = $value;
			} else {
				$this->_extradata[$key] = $value;
			}
		}
		//foreach ($joinModels as $key => $value) {
		//	$this->_extradata[$key] = new Dbi_Record(new $value(), $joinData[$key]);
		//}
	}
	//##################   ArrayAccess special methods.  #####################\\
	public function offsetSet($offset, $value) {
		//echo "Calling offsetSet in " . __CLASS__ . "!<br/>"; // DEBUG //
        $this->set($offset, $value);
    }
    public function offsetExists($offset) {
    	// I need to use this method because it includes derived properties.
    	//$dat = $this->getAsArray();
        //return isset($dat[$offset]);
		return ( isset($this->_data[$offset]) || isset($this->_extradata[$offset]) );
    }
    public function offsetUnset($offset) {
    	// @todo Should this be disabled?...
        unset($this->_data[$offset]);
		unset($this->_extradata[$offset]);
		$this->_iteratorKeys = array_merge(array_keys($this->_data), array_keys($this->_extradata));
    }
    public function &offsetGet($offset) {
    	$value =& $this->get($offset);
		return $value;
    }
	//###################   Iterator special methods.  #######################\\
	public function rewind() {
		$this->_iteratorPos = 0;
	}
	public function current() {
		if (!isset($this->_iteratorKeys[$this->_iteratorPos])) return null;
		return $this->get($this->_iteratorKeys[$this->_iteratorPos]);
	}
	public function key() {
		if (!isset($this->_iteratorKeys[$this->_iteratorPos])) return null;
		return $this->_iteratorKeys[$this->_iteratorPos];
	}
	public function next() {
		$this->_iteratorPos++;
		//if ($this->_iteratorPos >= sizeof($this->_iteratorKeys)) $this->_iteratorPos = 0;
		return $this->current();
	}
	public function valid() {
		return ($this->key() !== null);
	}
}
