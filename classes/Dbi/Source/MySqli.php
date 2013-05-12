<?php
class Dbi_Source_MySqli extends Dbi_Source_SqlAbstract {
	private $_connection;
	public function select(Dbi_Model $query) {
		$select = $this->_generateSql($query);
		$components = $query->components();
		return new Dbi_Recordset_MySqli($query, $this->_execute($select));
	}
	public function analyze(Dbi_Model $query) {
		$select = $this->_generateSql($query);
		return $select->expression()->statement();
	}
	public function update(Dbi_Model $query, array $data) {
		$components = $query->components();
		$update = new Dbi_Sql_Query_Update();
		$update->table($query->prefix() . $components['table']);
		foreach ($components['where'] as $where) {
			$orStatements = array();
			$orParameters = array();
			foreach ($where->expressions() as $or) {
				$orStatements[] = $or->statement();
				$orParameters = array_merge($orParameters, $or->parameters());
			}
			$args = array_merge(array(implode(' OR ', $orStatements)), $orParameters);
			call_user_func_array(array($update, 'where'), $args);
		}
		// Get rid of fields that are not defined in the schema.
		// TODO: Should undefined fields generate an error?
		/*foreach ($data as $key => $value) {
			if (is_null($query->field($key))) {
				unset($data[$key]);
			} else {
				// Convert arrays to JSON
				// (Objects depend on __toString() for conversion)
				if ( (is_array($value)) ) {
					$data[$key] = json_encode($value);
				}
			}
		}
		$update->set($data);*/
		foreach ($data as $key => $value) {
			$update->set("`{$key}` = ?", $value);
		}
		//$this->_connection->query($update->query());
		$this->_execute($update, $query);
		if ($this->_connection->errno) {
			echo "{$update->query()}<br/>";
			throw new Exception($this->_connection->error);
		}
	}
	public function insert(Dbi_Record $record) {
		$data = $record->getArray(!$this->enforceSchemas);
		// Get rid of fields that are not defined in the schema.
		// TODO: Should undefined fields generate an error?
		foreach ($data as $key => $value) {
			if (is_null($record->model()->field($key))) {
				unset($data[$key]);
			} else {
				// Convert arrays to JSON
				// (Objects depend on __toString() for conversion)
				if ( (is_array($value)) ) {
					$data[$key] = json_encode($value);
				}
			}
		}
		$insert = new Dbi_Sql_Query_Insert(); //($record->model()->prefix() . $record->model()->name(), $data);
		//$this->_connection->query($insert->query());
		//if ($this->_connection->errno) {
		//	throw new Exception($this->_connection->error);
		//}
		$insert->table($record->model()->prefix() . $record->model()->name());
		foreach ($data as $key => $value) {
			$insert->set($key, $value);
		}
		$stmt = $this->_execute($insert, $record->model());
		if ($stmt->errno) {
			throw new Exception($stmt->error);
		}
		//var_dump($result);die;
		$primary = $record->model()->index('primary');
		if ( (is_array($primary)) && (count($primary['fields']) == 1) ) {
			$data[$primary['fields'][0]] = $stmt->insert_id;
		}
		// Return the data that was saved so Dbi_Record objects can update
		// automatically generated primary keys
		return $data;
	}
	public function delete(Dbi_Model $query) {
		$components = $query->components();
		$delete = new Dbi_Sql_Query_Delete();
		$delete->table($query->prefix() . $components['table']);
		foreach ($components['where'] as $where) {
			$orStatements = array();
			$orParameters = array();
			foreach ($where->expressions() as $or) {
				$orStatements[] = $or->statement();
				$orParameters = array_merge($orParameters, $or->parameters());
			}
			$args = array_merge(array(implode(' OR ', $orStatements)), $orParameters);
			call_user_func_array(array($delete, 'where'), $args);
		}
		$expression = $delete->expression();
		//var_dump($expression);die;
		$this->_execute($delete, $query);
		if ($this->_connection->errno) {
			throw new Exception($this->_connection->error);
		}
	}
	public function configureSchema(Dbi_Schema $schema, $alterExistingFields = false) {
		$tablename = $schema->prefix() . $schema->name();
		$rs = $this->_connection->query('SHOW TABLES LIKE \'' . mysqli_real_escape_string($tablename) . '\'');
		if ($row = $rs->fetch_assoc()) {
			// Table exists
			$this->_alterTable($schema, $alterExistingFields);
		} else {
			// Create new table
			$this->_createTable($schema);
		}
	}
	/**
	 * Create a new table from a schema.
	 * @param Dbi_Schema $schema
	 */
	private function _createTable(Dbi_Schema $schema) {
		$tablename = $schema->prefix() . $schema->name();
		$sql = 'CREATE TABLE `' . mysqli_real_escape_string($tablename) . '`';
		$cols = array();
		foreach ($schema->fields() as $name => $field) {
			$cols[] = $this->_fieldDefinition($name, $field);
		}
		$sql .= ' (' . implode(', ', $cols) . ')';
		$this->_connection->query($sql);
		if ($this->_connection->errno) {
			throw new Exception($this->_connection->error . "\n" . $sql);
		}
		foreach ($schema->indexes() as $name => $def) {
			$keyfields = array();
			foreach ($def['fields'] as $f) {
				$keyfields[] = "`{$f}`";
			}
			if ($name == 'primary') {
				$sql = 'ALTER TABLE `' . $tablename . '` ADD PRIMARY KEY (' . implode(', ', $keyfields) . ')';
			} else {
				$sql = 'CREATE ' . $def['type'] . ' INDEX `' . $name . '` ON `' . $tablename . '` (' . implode(', ', $keyfields) . ')';
			}
			$this->_connection->query($sql);
			if ($this->_connection->errno) {
				echo "{$sql}\n";
				throw new Exception($this->_connection->error);
			}
			if ($name == 'primary') {
				if (count($def['fields']) == 1) {
					$field = $schema->field($def['fields'][0]);
					if (in_array('auto_increment', $field->arguments())) {
						// Primary key is an auto_increment field.
						$sql = 'ALTER TABLE `' . $tablename . '` MODIFY COLUMN ' . $this->_fieldDefinition($def['fields'][0], $field) . ' AUTO_INCREMENT';
						$this->_connection->query($sql);
						if ($this->_connection->errno) {
							throw new Exception($this->_connection->error);
						}
					}
				}
			}
		}
	}
	/**
	 * Alter an existing table from a schema.
	 * @param Dbi_Schema $schema
	 * @param boolean $alterExistingFields
	 */
	private function _alterTable(Dbi_Schema $schema, $alterExistingFields) {
		$tablename = $schema->prefix() . $schema->name();
		$sql = 'SHOW COLUMNS IN `' . mysqli_real_escape_string($tablename) . '`';
		$rs = $this->_connection->query($sql);
		$columns = array();
		$numeric = array();
		while ($row = $rs->fetch_assoc()) {
			$columns[$row['Field']] = $row;
			$numeric[] = $row['Field'];
		}
		$previous = -1;
		foreach ($schema->fields() as $name => $field) {
			if (isset($columns[$name])) {
				if ($alterExistingFields) {
					// TODO: Check to see if the column definition is actually different
					$sql = 'ALTER TABLE `' . $tablename . '` MODIFY COLUMN ' . $this->_fieldDefinition($name, $field);
					$this->_connection->query($sql);
					if ($this->_connection->errno) {
						throw new Exception($this->_connection->error);
					}
				}
			} else {
				$sql = 'ALTER TABLE `' . $tablename . '` ADD COLUMN ' . $this->_fieldDefinition($name, $field);
				if ($previous == -1) {
					$sql .= ' FIRST';
				} else {
					if (isset($numeric[$previous])) {
						$sql .= ' AFTER `' . $numeric[$previous] . '`';
					}
				}
				$this->_connection->query($sql);
				if ($this->_connection->errno) {
					throw new Exception($this->_connection->error);
				}
			}
			$previous++;
		}
		foreach ($schema->indexes() as $name => $def) {
			$rs = $this->_connection->query('SHOW INDEX IN `' . mysqli_real_escape_string($tablename) . '` WHERE Key_name = \''. mysqli_real_escape_string($name) . '\'');
			if ($rs->num_rows()) {
				$cols = array();
				while ($row = $rs->fetch_assoc()) {
					$cols[] = $row['Column_name'];
				}
				if ($cols == $def['fields']) continue;
				if ($name == 'primary') {
					$rs = $this->_connection->query('ALTER TABLE `' . $tablename . '` DROP PRIMARY KEY');
				} else {
					$rs = $this->_connection->query('ALTER TABLE `' . $tablename . '` DROP INDEX ' . $name);
				}
			}
			$keyfields = array();
			foreach ($def['fields'] as $f) {
				$keyfields[] = "`{$f}`";
			}
			if ($name == 'primary') {
				$sql = 'ALTER TABLE `' . $tablename . '` ADD PRIMARY KEY (' . implode(', ', $keyfields) . ')';
			} else {
				$sql = 'CREATE ' . $def['type'] . ' INDEX `' . $name . '` ON `' . $tablename . '` (' . implode(', ', $keyfields) . ')';
			}
			$this->_connection->query($sql);
			if ($this->_connection->errno) {
				throw new Exception($this->_connection->error);
			}
			if ($name == 'primary') {
				if (count($def['fields']) == 1) {
					$field = $schema->field($def['fields'][0]);
					if (in_array('auto_increment', $field->arguments())) {
						// Primary key is an auto_increment field.
						$sql = 'ALTER TABLE `' . $tablename . '` MODIFY COLUMN ' . $this->_fieldDefinition($def['fields'][0], $field) . ' AUTO_INCREMENT';
						$this->_connection->query($sql);
						if ($this->_connection->errno) {
							throw new Exception($this->_connection->error);
						}
					}
				}
			}
		}
	}
	/**
	 * Get a column definition that can be used in a CREATE or ALTER TABLE query.
	 * @param string $name The name of the field.
	 * @param Dbi_Field $field The field definition.
	 * @return string The SQL for the CREATE or ALTER query.
	 */
	private function _fieldDefinition($name, Dbi_Field $field) {
		$col = '`' . $name . '` ' . $field->type();
		switch ($field->type()) {
			// Numeric columns
			case 'int':
			case 'smallint':
			case 'tinyint':
			case 'mediumint':
			case 'bigint':
			case 'float':
			case 'decimal':
			case 'double':
				$args = $field->arguments();
				// The first argument for a numeric field should always be its size
				if (count($args)) {
					$size = (int)array_shift($args);
					// Decimals can have multiple size arguments
					if ( ($field->type() == 'decimal') && (count($args)) && (is_int($args[0])) ) {
						$size .= ', ' . (int)array_shift($args);
					}
					$col .= '(' . $size . ')';
					foreach ($args as $arg) {
						if ($arg == 'unsigned') {
							$col .= ' UNSIGNED';
						} else if ($arg == 'zerofill') {
							$col .= ' ZEROFILL';
						} else if ($arg == 'auto_increment') {
							// Ignore this argument here. The auto_increment gets
							// set while defining the indexes.
						}
					}
				}
				break;
			// Columns that have 1 optional numeric argument
			case 'char':
			case 'varchar':
			case 'binary':
			case 'varbinary':
			case 'tinyblob':
			case 'blob':
			case 'mediumblob':
			case 'longblob':
			case 'bit':
				$args = $field->arguments();
				if (isset($args[0])) {
					$col .= '(' . (int)$args[0] . ')';
				}
				break;
			// Columns with optional appended arguments
			case 'tinytext':
			case 'text':
			case 'mediumtext':
			case 'longtext':
				foreach ($field->arguments() as $arg) {
					$col .= ' ' . $arg;
				}
				break;
			// Columns with multiple text arguments
			case 'set':
			case 'enum':
				$setArgs = array();
				foreach ($field->arguments() as $sa) {
					$setArgs[] = "'" . addslashes($sa) . "'";
				}
				$col .= '(' . implode(', ', $setArgs) . ')';
				break;
			// Columns with no arguments
			case 'date':
			case 'time':
			case 'timestamp':
			case 'datetime':
			case 'year':
				break;
			default:
				throw new Exception("Unrecognized field type '" . $field->type() . "'");
				break;
		}
		if ($field->allowNull()) {
			$col .= ' NULL';
		} else {
			$col .= ' NOT NULL';
		}
		return $col;
	}
	/**
	 * Execute a query.
	 * @param Dbi_Sql_Query $query
	 * @return mysqli_stmt
	 */
	private function _execute(Dbi_Sql_Query $query) {
		self::$queryCount++;
		$expression = $query->expression();
		$stmt = $this->_connection->prepare($expression->statement());
		if (!$stmt) {
			throw new Exception($this->_connection->error);
		}
		$parameters = $expression->parameters();
		if (count($parameters)) {
			$types = '';
			$refs = array();
			foreach ($parameters as &$p) {
				if (!is_scalar($p)) $p = json_encode($p);
				$types .= "s";
				$refs[] =& $p;
			}
			call_user_func_array(array($stmt, 'bind_param'), array_merge(array($types), $refs)) or die('hmm');
		}
		$stmt->execute();
		$stmt->store_result();
		return $stmt;
	}
	public function __construct($host, $username, $password, $database) {
		$this->_connection = mysqli_connect($host, $username, $password, $database);
	}
	public function connection() {
		return $this->_connection;
	}
	/**
	 * Execute raw code.
	 * @param string $code
	 * @return Dbi_Recordset[]
	 */
	public function execute($code) {
		$stmt = $this->_connection->prepare($code);
		$stmt->execute();
		$stmt->store_result();
		return new Dbi_Recordset_MySqli(new Dbi_Model_Anonymous(), $stmt);
	}
	/**
	 * Execute a query.
	 * @param Dbi_Sql $query
	 * @return Dbi_Recordset[]
	 */
	public function query(Dbi_Sql_Query $query) {
		$stmt = $this->_execute($query);
		return new Dbi_RecordSet_MySqli(new Dbi_Model_Anonymous(), $stmt);
	}
}
