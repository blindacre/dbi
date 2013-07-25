<?php
class Dbi_Source_MySql extends Dbi_Source_SqlAbstract {
	private $_connection;
	public function select(Dbi_Model $query) {
		self::$queryCount++;
		$select = $this->_generateSql($query);
		$components = $query->components();
		return $this->_execute($select, $query);
	}
	public function analyze(Dbi_Model $query) {
		$select = $this->_generateSql($query);
		$code = $this->_bindParameters($select);
		return $code;
	}
	public function update(Dbi_Model $query, array $data) {
		self::$queryCount++;
		$components = $query->components();
		$update = new BuildSql_Update();
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
		foreach ($data as $key => $value) {
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
		$update->set($data);
		mysql_query($update->query());
		if (mysql_error()) {
			echo "{$update->query()}<br/>";
			throw new Exception(mysql_error());
		}
	}
	public function insert(Dbi_Record $record) {
		self::$queryCount++;
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
		$insert = new BuildSql_Insert($record->model()->prefix() . $record->model()->name(), $data);
		mysql_query($insert->query());
		if (mysql_error()) {
			throw new Exception(mysql_error());
		}
		$primary = $record->model()->index('primary');
		if ( (is_array($primary)) && (count($primary['fields']) == 1) ) {
			$data[$primary['fields'][0]] = mysql_insert_id();
		}
		// Return the data that was saved so Dbi_Record objects can update
		// automatically generated primary keys
		return $data;
	}
	public function delete(Dbi_Model $query) {
		self::$queryCount++;
		$components = $query->components();
		$delete = new BuildSql_Delete();
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
		mysql_query($delete->query());
		if (mysql_error()) {
			throw new Exception(mysql_error());
		}
	}
	public function configureSchema(Dbi_Schema $schema, $alterExistingFields = false) {
		$tablename = $schema->prefix() . $schema->name();
		$rs = mysql_query('SHOW TABLES LIKE \'' . mysql_real_escape_string($tablename) . '\'');
		if ($row = mysql_fetch_assoc($rs)) {
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
		$sql = 'CREATE TABLE `' . mysql_real_escape_string($tablename) . '`';
		$cols = array();
		foreach ($schema->fields() as $name => $field) {
			$cols[] = $this->_fieldDefinition($name, $field);
		}
		$sql .= ' (' . implode(', ', $cols) . ')';
		mysql_query($sql);
		if (mysql_error()) {
			throw new Exception(mysql_error() . "\n" . $sql);
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
			mysql_query($sql);
			if (mysql_error()) {
				echo "{$sql}\n";
				throw new Exception(mysql_error());
			}
			if ($name == 'primary') {
				if (count($def['fields']) == 1) {
					$field = $schema->field($def['fields'][0]);
					if (in_array('auto_increment', $field->arguments())) {
						// Primary key is an auto_increment field.
						$sql = 'ALTER TABLE `' . $tablename . '` MODIFY COLUMN ' . $this->_fieldDefinition($def['fields'][0], $field) . ' AUTO_INCREMENT';
						mysql_query($sql);
						if (mysql_error()) {
							throw new Exception(mysql_error());
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
		$sql = 'SHOW COLUMNS IN `' . mysql_real_escape_string($tablename) . '`';
		$rs = mysql_query($sql);
		$columns = array();
		$numeric = array();
		while ($row = mysql_fetch_assoc($rs)) {
			$columns[$row['Field']] = $row;
			$numeric[] = $row['Field'];
		}
		$previous = -1;
		foreach ($schema->fields() as $name => $field) {
			if (isset($columns[$name])) {
				if ($alterExistingFields) {
					// TODO: Check to see if the column definition is actually different
					$sql = 'ALTER TABLE `' . $tablename . '` MODIFY COLUMN ' . $this->_fieldDefinition($name, $field);
					mysql_query($sql);
					if (mysql_error()) {
						throw new Exception(mysql_error());
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
				mysql_query($sql);
				if (mysql_error()) {
					throw new Exception(mysql_error());
				}
			}
			$previous++;
		}
		foreach ($schema->indexes() as $name => $def) {
			$rs = mysql_query('SHOW INDEX IN `' . mysql_real_escape_string($tablename) . '` WHERE Key_name = \''. mysql_real_escape_string($name) . '\'');
			if (mysql_num_rows($rs)) {
				$cols = array();
				while ($row = mysql_fetch_assoc($rs)) {
					$cols[] = $row['Column_name'];
				}
				if ($cols == $def['fields']) continue;
				if ($name == 'primary') {
					$rs = mysql_query('ALTER TABLE `' . $tablename . '` DROP PRIMARY KEY');
				} else {
					$rs = mysql_query('ALTER TABLE `' . $tablename . '` DROP INDEX ' . $name);
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
			mysql_query($sql);
			if (mysql_error()) {
				throw new Exception(mysql_error());
			}
			if ($name == 'primary') {
				if (count($def['fields']) == 1) {
					$field = $schema->field($def['fields'][0]);
					if (in_array('auto_increment', $field->arguments())) {
						// Primary key is an auto_increment field.
						$sql = 'ALTER TABLE `' . $tablename . '` MODIFY COLUMN ' . $this->_fieldDefinition($def['fields'][0], $field) . ' AUTO_INCREMENT';
						mysql_query($sql);
						if (mysql_error()) {
							throw new Exception(mysql_error());
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
	private function _bindParameters(Dbi_Sql_Query $sql) {
		$expression = $sql->expression();
		$result = $expression->statement();
		$offset = 0;
		$parameters = $expression->parameters();
		while (count($parameters)) {
			$p = array_shift($parameters);
			if (!is_scalar($p)) $p = json_encode($p);
			$index = strpos($result, '?', $offset);
			$escaped = mysql_real_escape_string($p);
			$result = substr($result, 0, $index) . "'" . $escaped . "'" . substr($result, $index + 1);
			$offset += strlen($escaped);
		}
		return $result;
	}
	private function _execute(Dbi_Sql_Query $sql, Dbi_Model $model) {
		$code = $this->_bindParameters($sql);
		$rs = mysql_query($code);
		if (mysql_error()) {
			throw new Exception(mysql_error());
		}
		return new Dbi_Recordset_MySql($model, $rs);
	}
	public function __construct($host, $username, $password, $database) {
		$this->_connection = mysql_connect($host, $username, $password);
		mysql_select_db($database, $this->_connection);
	}
	public function connection() {
		return $this->_connection;
	}
	public function execute() {
		$args = func_get_args();
		$code = array_shift($args);
		// TODO: Should the prefix replacement be done here?
		$code = str_replace('#__', DBI_PREFIX, $code);
		$offset = 0;
		while (count($args)) {
			$p = array_shift($args);
			$index = strpos($code, '?', $offset);
			$escaped = mysql_real_escape_string($p);
			$code = substr($code, 0, $index) . "'" . $escaped . "'" . substr($code, $index + 1);
			$offset = $index + strlen($escaped);
		}
		$rs = mysql_query($code);
		if (mysql_error()) {
			throw new Exception(mysql_error());
		}
		return new Dbi_Recordset_MySql(new Dbi_Model_Anonymous(), $rs);
	}
}
