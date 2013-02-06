<?php
class Dbi_Source_MySqli extends Dbi_Source {
	private $_connection;
	private function _build(BuildSql_Select $select, Dbi_Model $query, $components, $parent = '', $forceLeft = false) {
		$subs = array();
		if (count($components['fields'])) {
			$select->field($components['fields']);
		} else {
			foreach ($query->fields() as $name => $field) {
				if ($parent) {
					$select->field('`' . substr($parent, 0, -1) . "`.`{$name}` AS `{$parent}{$name}`");
				} else {
					$select->field("`{$components['table']}`.`{$name}` AS `{$parent}{$name}`");
				}
			}
			foreach ($components['innerJoins'] as $innerJoin) {
				$subquery = $innerJoin['model']->query();
				$subcomponents = $subquery->components();
				$subcomponents['table'] = $innerJoin['name'];
				$subs[] = array('query' => $subquery, 'components' => $subcomponents, 'forceLeft' => false);
			}
			foreach ($components['leftJoins'] as $join) {
				$subquery = $join['model']->query();
				$subcomponents = $subquery->components();
				$subcomponents['table'] = $join['name'];
				$subs[] = array('query' => $subquery, 'components' => $subcomponents, 'forceLeft' => true);
			}
		}
		// Where criteria
		$fields = array_keys($query->fields());
		foreach ($components['where'] as $where) {
			$orStatements = array();
			$orParameters = array();
			foreach ($where->expressions() as $or) {
				$statement = $or->statement();
				$tokens = Dbi_Query_Tokenizer::Tokenize($statement);
				foreach ($tokens as &$token) {
					if (in_array($token, $fields)) {
						$token = "{$parent}{$components['table']}.{$token}";
					}
				}
				$compiled = implode(' ', $tokens);
				// Functions in MySql cannot have a space before the parenthesis
				$compiled = str_replace(' (', '(', $compiled);
				$orStatements[] = $compiled;
				$orParameters = array_merge($orParameters, $or->parameters());
			}
			$args = array_merge(array(implode(' OR ', $orStatements)), $orParameters);
			call_user_func_array(array($select, 'where'), $args);
		}
		foreach ($components['innerJoins'] as $join) {
			$args = $join['args'];
			array_unshift($args, $join['model']->prefix() . $join['model']->name() . ' AS `' . $parent . $join['name'] . '`');
			$tokens = Dbi_Query_Tokenizer::Tokenize($args[1]);
			foreach ($tokens as &$token) {
				if (in_array($token, $fields)) {
					$token = '`' . ($parent ? substr($parent, 0, -1) : $components['table']) . "`.`{$token}`";
				} else if (substr($token, 0, strlen($join['name']) + 1) == "{$join['name']}.") {
					$token = "`{$parent}{$join['name']}`.`" . substr($token, strlen($join['name']) + 1) . "`";
				} else {
					//echo "token {$token} for " . get_class($join['model']) . "<br/>";
				}
			}
			$args[1] = implode(' ', $tokens);
			if ($forceLeft) {
				call_user_func_array(array($select, 'leftJoin'), $args);
			} else {
				call_user_func_array(array($select, 'innerJoin'), $args);
			}
		}
		foreach ($components['leftJoins'] as $join) {
			$args = $join['args'];
			array_unshift($args, $join['model']->prefix() . $join['model']->name() . ' AS `' . $parent . $join['name'] . '`');
			$tokens = Dbi_Query_Tokenizer::Tokenize($args[1]);
			foreach ($tokens as &$token) {
				if (in_array($token, $fields)) {
					//$token = "`{$components['table']}`.`{$token}`";
					$token = '`' . ($parent ? substr($parent, 0, -1) : $components['table']) . "`.`{$token}`";
				} else if (substr($token, 0, strlen($join['name']) + 1) == "{$join['name']}.") {
					$token = "`{$parent}{$join['name']}`.`" . substr($token, strlen($join['name']) + 1) . "`";
				}
			}
			$args[1] = implode(' ', $tokens);
			call_user_func_array(array($select, 'leftJoin'), $args);
		}
		foreach ($components['groups'] as $group) {
			$select->group($group);
		}
		foreach ($components['having'] as $having) {
			$orStatements = array();
			$orParameters = array();
			foreach ($having->expressions() as $or) {
				$statement = $or->statement();
				$tokens = Dbi_Query_Tokenizer::Tokenize($statement);
				foreach ($tokens as &$token) {
					if (in_array($token, $fields)) {
						$token = "{$parent}{$components['table']}.{$token}";
					}
				}
				$compiled = implode(' ', $tokens);
				// Functions in MySql cannot have a space before the parenthesis
				$compiled = str_replace(' (', '(', $compiled);
				$orStatements[] = $compiled;
				$orParameters = array_merge($orParameters, $or->parameters());
			}
			$args = array_merge(array(implode(' OR ', $orStatements)), $orParameters);
			call_user_func_array(array($select, 'having'), $args);			
		}
		foreach ($subs as $sub) {
			$this->_build($select, $sub['query'], $sub['components'], $parent . ($sub['components']['table'] ? $sub['components']['table'] . '.' : ''), $sub['forceLeft']);
		}
	}
	private function _generateSql(Dbi_Model $query) {
		$select = new BuildSql_Select('mysqli_real_escape_string');
		$components = $query->components();
		// Table
		$select->table($query->prefix() . $components['table'] . ' AS ' . $components['table']);
		$this->_build($select, $query, $components);
		if (count($components['orders'])) {
			$fixedOrders = array();
			foreach ($components['orders'] as $order) {
				$parts = explode(' ', $order);
				if (strpos($parts[0], '.') === false && strpos($parts[0], '(') === false) {
					//$parts[0] = "{$components['table']}.{$parts[0]}";
				}
				$fixedOrders[] = implode(' ', $parts);
			}
			$select->order(implode(', ', $fixedOrders));
		}
		if (is_array($components['limit'])) {
			$select->limit(implode(',', $components['limit']));
		}
		return $select;
	}
	public function select(Dbi_Model $query) {
		self::$queryCount++;
		$select = $this->_generateSql($query);
		$components = $query->components();
		return $this->_execute($select->query(), $query);
	}
	public function analyze(Dbi_Model $query) {
		$select = $this->_generateSql($query);
		return $select->query();
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
		$this->_connection->query($update->query());
		if ($this->_connection->errno) {
			echo "{$update->query()}<br/>";
			throw new Exception($this->_connection->error);
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
		$this->_connection->query($insert->query());
		if ($this->_connection->errno) {
			throw new Exception($this->_connection->error);
		}
		$primary = $record->model()->index('primary');
		if ( (is_array($primary)) && (count($primary['fields']) == 1) ) {
			$data[$primary['fields'][0]] = $this->_connection->insert_id();
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
		$this->_conneciton->query($delete->query());
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
	private function _execute($sql, Dbi_Model $model) {
		//Typeframe::Database()->queries++;
		$rs = $this->_connection->query($sql);
		if ($this->_connection->errno) {
			echo "{$sql}<br/>";
			throw new Exception($this->_connection->error);
		}
		return new Dbi_Recordset_MySqli($model, $rs);
	}
	public function __construct($host, $username, $password, $database) {
		$this->_connection = mysqli_connect($host, $username, $password, $database);
	}
	public function connection() {
		return $this->_connection;
	}
	public function execute($code) {
		// TODO: This function should accept prepared statements and arguments.
		// OR maybe it should just accept a BuildSql object.
		//$code = str_replace('#__', DBI_PREFIX, $code);
		return $this->_execute($code, new Dbi_Model_Anonymous());
	}
}
