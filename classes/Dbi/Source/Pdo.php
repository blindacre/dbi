<?php

class Dbi_Source_Pdo extends Dbi_Source_SqlAbstract {
	private $_pdo;
	public function __construct($dsn, $username, $password) {
		$this->_pdo = new PDO($dsn, $username, $password);
	}
	public function select(Dbi_Model $model) {
		$select = $this->_generateSql($model);
		$stmt = $this->_execute($select);
		return new Dbi_Recordset_Pdo($model, $stmt);
	}
	public function analyze(Dbi_Model $model) {
		$select = $this->_generateSql($model);
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
		foreach ($data as $key => $value) {
			$update->set("`{$key}` = ?", $value);
		}
		$this->_execute($update);
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
		$stmt = $this->_execute($insert);
		$primary = $record->model()->index('primary');
		if ( (is_array($primary)) && (count($primary['fields']) == 1) ) {
			$data[$primary['fields'][0]] = $this->_pdo->lastInsertId();
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
		$this->_execute($delete);
	}
	public function configureSchema(Dbi_Schema $schema, $alterExistingFields = false) {
		
	}
	public function execute($code) {
		$args = func_get_args();
		$code = array_shift($args);
		// TODO: Should the prefix replacement be done here?
		$code = str_replace('#__', DBI_PREFIX, $code);
		$stmt = $this->_pdo->prepare($code);
		$stmt->execute($args);
		if (!$stmt) {
			$info = $this->_pdo->errorInfo();
			throw new Exception($info[2]);
		}
		return new Dbi_Recordset_Pdo(new Dbi_Model_Anonymous(), $stmt);
	}
	/**
	 * 
	 * @param Dbi_Sql_Query $query
	 * @return PDOStatement
	 */
	private function _execute(Dbi_Sql_Query $query) {
		self::$queryCount++;
		$expression = $query->expression();
		$stmt = $this->_pdo->prepare($expression->statement(), array(PDO::CURSOR_SCROLL));
		if (!$stmt) {
			throw new Exception($this->_pdo->errorInfo);
		}
		$parameters = $expression->parameters();
		if (count($parameters)) {
			$index = 1;
			foreach ($parameters as $p) {
				if (!is_scalar($p)) $p = json_encode($p);
				$stmt->bindValue($index, $p);
				$index++;
			}
		}
		$stmt->execute();
		if ($stmt->errorCode() != '00000') {
			$info = $stmt->errorInfo();
			throw new Exception($info[2]);
		}
		return $stmt;
	}
	public function connection() {
		return $this->_pdo;
	}
}
