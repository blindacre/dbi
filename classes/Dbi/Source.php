<?php
abstract class Dbi_Source {
	private static $_GlobalSource = null;
	private static $_ModelSources = array();
	protected static $queryCount = 0;
	protected $enforceSchemas = true;
	/**
	 * Execute a select query on the data source.
	 * @return Dbi_Recordset The records returned by the query.
	 */
	abstract public function select(Dbi_Model $model);
	/**
	 * Get the code (e.g., the raw SQL query) that the DBI source would execute for a select().
	 * @return string A string containing the generated code.
	 */
	abstract public function analyze(Dbi_Model $model);
	/**
	 * Update the records in a query.
	 * @param Dbi_Query $query The query used to select records to be updated.
	 * @param array $data An associative array containing the record's fields
	 * and their corresponding new values.
	 */
	abstract public function update(Dbi_Model $model, array $data);
	/**
	 * Delete the records in a query
	 * @param Dbi_Query $query The query used to select records to be deleted.
	 */
	abstract public function delete(Dbi_Model $model);
	/**
	 * @return array An associative array of the data that was saved, including automatically generated values (e.g., UIDs)
	 */
	abstract public function insert(Dbi_Record $record);
	/**
	 * Build a table schema in the database.
	 * @param Dbi_Schema $schema The schema or model to use for the configuration.
	 * @param boolean $alterExistingFields If true, alter existing fields to match the provided schema.
	 */
	abstract public function configureSchema(Dbi_Schema $schema, $alterExistingFields = false);
	abstract public function execute();
	/**
	 * Set the DBI source to be used for all models that have not specified
	 * their own data source.
	 * @param Dbi_Source $source
	 */
	public static function SetGlobalSource(Dbi_Source $source = null) {
		self::$_GlobalSource = $source;
	}
	/**
	 * Get the global source to be used for all models that have not specified
	 * their own data source.
	 * @return Dbi_Source
	 */
	public static function GetGlobalSource() {
		return self::$_GlobalSource;
	}
	/**
	 * Set the DBI source for a particular model.
	 * @param Dbi_Model $model
	 * @param Dbi_Source $source
	 */
	public static function SetModelSource(Dbi_Model $model, Dbi_Source $source) {
		self::$_ModelSources[get_class($model)] = $source;
	}
	/**
	 * Get the DBI Source defined for a particular model.
	 * @param Dbi_Model $model
	 * @return Dbi_Source
	 */
	public static function GetModelSource(Dbi_Model $model) {
		if (isset(self::$_ModelSources[get_class($model)])) {
			return self::$_ModelSources[get_class($model)];
		}
		return self::$_GlobalSource;		
	}
	/**
	 * Get the number of executed queries.
	 * @return int
	 */
	public static function QueryCount() {
		return self::$queryCount;
	}
	/**
	 * Return true is the data source should enforce its models' schemas.
	 * @return boolean
	 */
	public function enforceSchemas() {
		return $this->enforceSchemas;
	}
}
