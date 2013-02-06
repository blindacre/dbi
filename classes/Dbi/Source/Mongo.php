<?php
class Dbi_Source_Mongo extends Dbi_Source {
	private $_mongo;
	private $_db;
	public function __construct($db) {
		$this->enforceSchemas = false;
		$this->_mongo = new Mongo();
		$this->_db = $this->_mongo->{$db};
		$this->openSchema = true;
	}
	public function insert(Dbi_Record $record) {
		$collection = $this->_db->{$record->model()->name()};
		$data = $record->getArray(!$this->enforceSchemas);
		$result = $collection->insert($data, true);
		/*$record->set('id', "{$data['_id']}");
		$record->save();*/
		$primary = $record->model()->index('primary');
		if ( (!is_null($primary)) && (count($primary) == 1) && ($primary[0] == 'id') ) {
			$data['id'] = "{$data['_id']}";
		}
		return $data;
	}
	private static function _Parameterize($statement, $parameters) {
		$offset = strpos($statement, '?');
		foreach ($parameters as $p) {
			$p = "'" . addslashes($p) . "'";
			if ($offset === false) {
				throw new Exception ('Malformed');
			}
			$statement = substr($statement, 0, $offset) . $p . substr($statement, $offset + 1);
			$offset = strpos($statement, '?');
		}
		return $statement;
	}
	private static function _ModifyToken($token) {
		if ($token == '=') return '==';
		if ($token == '_id') return 'this._id.toString()';
		if (preg_match('/^[a-z][a-z0-9_\.]*?$/i', $token)) {
			return "this.{$token}";
		}
		return $token;
	}
	public function select(Dbi_Query $query) {
		$collection = $this->_db->{$query->model()->name()};
		$conditions = array();
		$components = $query->components();
		foreach ($components['where'] as $where) {
			$orStatements = array();
			$orParameters = array();
			foreach ($where->expressions() as $or) {
				$tokens = array_map(array('Dbi_Source_Mongo', '_ModifyToken'), Dbi_Query_Tokenizer::Tokenize($or->statement()));
				$orStatements[] = 'this.' . str_replace('=', '==', $or->statement());
				$orParameters = array_merge($orParameters, $or->parameters());
			}
			$args = array_merge(array(implode(' || ', $orStatements)), $orParameters);
			$conditions[] = '(' . self::_Parameterize(implode(' || ', $orStatements), $orParameters) . ')';
		}
		$func = 'function() { return ' . implode(' && ', $conditions) . ' }';
		//echo $func;
		$cursor = $collection->find(array('$where' => $func));
		$records = array();
		foreach ($cursor as $doc) {
			$records[] = new Dbi_Record($query->model(), $doc);
		}
		return $records;
	}
	public function update(Dbi_Query $query, array $data) {
		//throw new Exception('Not implemented.');
		$collection = $this->_db->{$query->model()->name()};
		$conditions = array();
		$components = $query->components();
		foreach ($components['where'] as $where) {
			$orStatements = array();
			$orParameters = array();
			foreach ($where->expressions() as $or) {
				$tokens = array_map(array('Dbi_Source_Mongo', '_ModifyToken'), Dbi_Query_Tokenizer::Tokenize($or->statement()));
				$orStatements[] = 'this.' . str_replace('=', '==', $or->statement());
				$orParameters = array_merge($orParameters, $or->parameters());
			}
			$args = array_merge(array(implode(' || ', $orStatements)), $orParameters);
			$conditions[] = '(' . self::_Parameterize(implode(' || ', $orStatements), $orParameters) . ')';
		}
		$func = 'function() { return ' . implode(' && ', $conditions) . ' }';
		$collection->update(array('$where' => $func), $data);
	}
	public function delete(Dbi_Query $query) {
		throw new Exception('Not implemented.');
	}
	public function configureSchema(Dbi_Schema $schema) {
		throw new Exception('Not implemented.');
	}
}
