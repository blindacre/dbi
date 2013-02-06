<?php

class Dbi_Source_Pdo extends Dbi_Source {
	private $_pdo;
	public function __construct($dsn, $username, $password) {
		$this->_pdo = new PDO($dsn, $username, $password);
	}
	public function select(Dbi_Model $model) {
		
	}
	public function analyze(Dbi_Model $model) {
		
	}
	public function update(Dbi_Model $model, array $data) {
		
	}
	public function delete(Dbi_Model $model) {
		
	}
	public function insert(Dbi_Record $record) {
		
	}
	public function configureSchema(Dbi_Schema $schema, $alterExistingFields = false) {
		
	}
	public function execute($code) {
		
	}
}
