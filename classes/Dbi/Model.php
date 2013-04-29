<?php
abstract class Dbi_Model extends Dbi_Schema implements Event_SubjectInterface, Iterator, Countable {
	/**
	 * @var Dbi_Source
	 */
	protected $source;
	/**
	 * The event triggered any time a new or existing record gets saved. This
	 * event occurs first when saving a record, i.e., before the beforeCreate or
	 * beforeUpdate event. This event gets triggered even if the record is not
	 * dirty.
	 * The Event_ObserverInterface object's update() function receives a Dbi_Record.
	 */
	const EVENT_BEFORESAVE = 'beforeSave';
	/**
	 * The event triggered before an existing record gets updated. It only gets
	 * triggered if the record is dirty.
	 * The Event_ObserverInterface object's update() function receives a Dbi_Record.
	 */
	const EVENT_BEFOREUPDATE = 'beforeUpdate';
	/**
	 * The event triggered before a new record gets created. It only gets triggered
	 * if the record is dirty.
	 * The Event_ObserverInterface object's update() function receives a Dbi_Record.
	 */
	const EVENT_BEFORECREATE = 'beforeCreate';
	/**
	 * The event triggered before a record gets deleted.
	 * The Event_ObserverInterface object's update() function receives a Dbi_Record.
	 */
	const EVENT_BEFOREDELETE = 'beforeDelete';
	/**
	 * The event triggered after a record gets selected (i.e., a Dbi_Record is
	 * constructed from an existing record).
	 * The Event_ObserverInterface object's update() function receives a Dbi_Record.
	 */
	const EVENT_AFTERSELECT = 'afterSelect';
	private $_eventObservers = array();
	private $_query;
	private $_iteratorArray = null;
	private $_iteratorPos = null;
	
	// Query properties
	private $_innerJoins = array();
	private $_leftJoins = array();
	private $_wheres = array();
	private $_group = array();
	private $_haves = array();
	private $_orders = array();
	private $_fields = array();
	private $_subqueries = array();
	private $_limit;
	private static $_joinStack = array();	
	private static $_newest = array();
	private static $_lastModified = array();
	/**
	 * An array of field names that should be publicly modifiable through a
	 * Dbi_Record's setArray() function. This whitelist provides protection
	 * against injection of values that should be read-only for most users.
	 * @var array
	 */
	protected $publicArrayWhitelist = array();
	/**
	 * Get the array of field names that are allowed to be modified through a
	 * Dbi_Record's setArray function.
	 * @return array
	 */
	public function publicArrayWhitelist() {
		return $this->publicArrayWhitelist;
	}
	/**
	 * Attach an observer to an event.
	 * @param Event_ObserverInterface $observer The observer for the event.
	 * @param string $event The name of the event (see Dbi_Model constants).
	 */
	public function attach($event, Event_ObserverInterface $observer) {
		$this->_eventObservers[$event][] = $observer;
	}
	/**
	 * Detach an observer from an event.
	 * @param Event_ObserverInterface $observer The observer to detach.
	 * @param string $event The name of the event associated with the observer (see Dbi_Model constants).
	 */
	public function detach($event, Event_ObserverInterface $observer) {
		if (isset($this->_eventObservers[$event])) {
			$key = array_search($observer, $this->_eventObservers[$event], true);
			if ($key !== false) {
				array_splice($this->_eventObservers[$event], $key, 1);
			}
		}
	}
	/**
	 * Notify observers that an event has occurred.
	 * @param string $event The name of the event (see Dbi_Model constants).
	 * @param mixed $object The object that the observer will process (for most
	 * Dbi_Model events, the observer will expect this to be the Dbi_Record
	 * being selected, created, updated, or deleted).
	 */
	public function notify($event, $object = null) {
		if ($event == self::EVENT_BEFORECREATE) {
			self::$_newest[get_called_class()] = $object;
		} else if ($event == self::EVENT_BEFORESAVE) {
			self::$_lastModified[get_called_class()] = $object;
		}
		if (isset($this->_eventObservers[$event])) {
			if (is_null($object)) $object = $this;
			foreach ($this->_eventObservers[$event] as $observer) {
				$observer->update($object);
			}
		}
	}
	/**
	 * Fetch records from the database.
	 * @return Dbi_Record[]
	 */
	public function select() {
		//$src = Dbi_Source::GetModelSource($this);
		//return $src->select($this);
		return $this->source->select($this);
	}
	public function analyze() {
		//$src = Dbi_Source::GetModelSource($this);
		//return $src->analyze($this->query());
		return $this->source->analyze($this);
	}
	public function deleteQuery() {
		//$src = Dbi_Source::GetModelSource($this);
		//return $src->delete($this->query());
		return $this->source->delete($this);
	}
	public function updateQuery($data) {
		//$src = Dbi_Source::GetModelSource($this);
		//return $src->update($this->query(), $data);
		return $this->source->update($this);
	}
	/**
	 * Alias of select().
	 * @return Dbi_Record[]
	 */
	public function getAll() {
		return $this->select();
	}
	/**
	 * Get the number of records that the current query will return.
	 * @return int
	 */
	public function count() {
		//$src = Dbi_Source::GetModelSource($this);
		//return count($src->select($this->query()));
		return count($this->source->select($this));
	}
	/**
	 * Alias of count().
	 * @return int
	 */
	public function getTotal() {
		return $this->count();
	}
	/**
	 *
	 * @return Dbi_Record
	 */
	public function getFirst() {
		$rows = $this->select();
		if (count($rows)) {
			return $rows[0];
		}
		return self::Create();
	}
	public function paginate($page = 1, $perpage = 20) {
		$this->limit( ($page - 1) * $perpage, $perpage);
	}
	public function setPagination($page = 1, $perpage = 20) {
		$this->paginate($page, $perpage);
	}
	/**
	 * Fetch a single record by its primary key.
	 * @param scalar|array $key The primary key value. If the primary
	 * key contains more than one column, use an associative array.
	 * @return Dbi_Record
	 */
	public static function Get($key) {
		$cls = get_called_class();
		$model = new $cls();
		$primary = $model->index('primary');
		if (is_null($primary)) {
			throw new Exception("The schema for {$cls} does not identify a primary key");
		}
		if (!is_array($key)) {
			if (count($primary['fields']) > 1) {
				throw new Exception("The schema for {$cls} has more than one field in its primary key");
			}
			$key = array(
				$primary['fields'][0] => $key
			);
		}
		foreach ($primary['fields'] as $field) {
			if (!isset($key[$field])) {
				throw new Exception("No value provided for the {$field} field in the primary key for " . get_called_class());
			}
			$model->where("{$field} = ?", $key[$field]);
		}
		//$src = Dbi_Source::GetModelSource($model);
		$result = $model->select();
		if ($result->count()) {
			if ($result->count() > 1) {
				throw new Exception("{$cls} returned multiple records for primary key {$id}");
			}
			$record = $result[0];
		} else {
			$record = new Dbi_Record($model, null);
			$record->setArray($key, false);
		}
		return $record;
	}
	/**
	 * Delete a single record by its primary key.
	 * @param scalar|array $key The primary key value. If the primary
	 * key contains more than one column, use an associative array.
	 */
	public static function Delete($key) {
		$cls = get_called_class();
		$model = new $cls();
		$primary = $model->index('primary');
		if (is_null($primary)) {
			throw new Exception("The schema for {$cls} does not identify a primary key");
		}
		if (is_array($key)) {
			foreach ($primary['fields'] as $field) {
				if (!isset($key[$field])) {
					throw new Exception("No value provided for the {$field} field in the primary key");
				}
				$model->query()->where("{$field} = ?", $key[$field]);
			}
		} else {
			if (count($primary['fields']) > 1) {
				throw new Exception("The schema for {$cls} has more than one field in its primary key");
			}
			$model->where("{$primary['fields'][0]} = ?", $key);
		}
		$src = Dbi_Source::GetModelSource($model);
		$src->delete($model);
	}
	/**
	 * Create a new record.
	 * @return Dbi_Record
	 */
	public static function Create() {
		$cls = get_called_class();
		$model = new $cls();
		$record = new Dbi_Record($model, array());
		return $record;
	}
	/**
	 * Get the last Dbi_Record created using Dbi_Model::Create() on the
	 * called class. If no record has been created, or the created record
	 * was not saved, this method returns null.
	 * @return Dbi_Record|null The record, or null if none has been created.
	 */
	public static function Newest() {
		$cls = get_called_class();
		if (isset(self::$_newest[$cls]) && self::$_newest[$cls]->exists()) {
			return self::$_newest[$cls];
		}
		return null;		
	}
	/**
	 * Get the last Dbi_Record modified using Dbi_Record->save(). If no record
	 * has been created or updated, this method returns null.
	 * @return Dbi_Record|null The record, or null if none has been modified.
	 */
	public static function LastModified() {
		$cls = get_called_class();
		if (isset(self::$_lastModified[$cls]) && self::$_lastModified[$cls]->exists()) {
			return self::$_lastModified[$cls];
		}
		return null;		
	}
	//###################   Iterator special methods.  #######################\\
	public function rewind() {
		$this->_iteratorArray = $this->select();
		$this->_iteratorPos = 0;
	}
	public function current() {
		if (!isset($this->_iteratorArray[$this->_iteratorPos])) return null;
		return $this->_iteratorArray[$this->_iteratorPos];
	}
	public function key() {
		if (!isset($this->_iteratorArray[$this->_iteratorPos])) {
			return null;
		}
		return $this->_iteratorPos;
	}
	public function next() {
		$this->_iteratorPos++;
		return $this->current();
	}
	public function valid() {
		return ($this->key() !== null);
	}

	/**
	 * Add an inner join to the query.
	 * @param string $name A label to identify the joined fields (e.g., name.field1, name.field2, etc.).
	 * @param string $model The name of the model to join.
	 * @param string $statement A parameterized statement to use for join criteria.
	 * @param scalar|array $args,... Statement parameters.
	 */
	public function innerJoin() {
		$args = func_get_args();
		$name = array_shift($args);
		$joined = array_shift($args);
		if (!$name) $name = '___' . $joined;
		//if (count(array_keys(self::$_joinStack, $joined)) > 1) {
		if (get_class($this) == $joined || in_array($joined, self::$_joinStack)) {
			return;
		}
		self::$_joinStack[] = get_class($this);
		self::$_joinStack[] = $joined;
		$model = null;
		if (is_string($joined)) {
			if (is_subclass_of($joined, 'Dbi_Model')) {
				$model = new $joined();
			}
		} else {
			if (is_subclass_of($joined, 'Dbi_Model')) {
				$model = $joined;
			}
		}
		array_pop(self::$_joinStack);
		array_pop(self::$_joinStack);
		if (is_null($model)) {
			throw new Exception('Queries can only join models.');
		}
		$this->_innerJoins[] = array(
			'name' => $name,
			'model' => $model,
			'args' => $args
		);
	}
	/**
	 * Add a left outer join to the query.
	 * @param string $name A label to identify the joined fields (e.g., name.field1, name.field2, etc.).
	 * @param Dbi_Model $model The model to join.
	 * @param string $statement A parameterized statement to use for join criteria.
	 * @param scalar|array $args,... Statement parameters.
	 */
	public function leftJoin() {
		$args = func_get_args();
		$name = array_shift($args);
		$joined = array_shift($args);
		if (!$name) $name = '___' . $joined;
		//if (count(array_keys(self::$_joinStack, $joined)) > 1) {
		if (in_array($joined, self::$_joinStack)) {
			return;
		}
		self::$_joinStack[] = get_class($this);
		self::$_joinStack[] = $joined;
		$model = null;
		if (is_string($joined)) {
			if (is_subclass_of($joined, 'Dbi_Model')) {
				$model = new $joined();
			}
		} else {
			if (is_subclass_of($joined, 'Dbi_Model')) {
				$model = $joined;
			}
		}
		array_pop(self::$_joinStack);
		array_pop(self::$_joinStack);
		if (is_null($model)) {
			throw new Exception('Queries can only join models.');
		}
		$this->_leftJoins[] = array(
			'name' => $name,
			'model' => $model,
			'args' => $args
		);
	}
	/**
	 * Add a subquery to the query. The subquery can be executed on demand for
	 * each record returned by the parent query.
	 * @param string $name A label to identify the joined fields (e.g., name.field1, name.field2, etc.).
	 * @param Dbi_Model $model  The model to query.
	 * @param string $statement A parameterized statement (e.g., "name = ?"). Use the value of $name
	 * to specify ambiguous subquery field names (e.g., "field1 = subqueryName.field1").
	 * @param scalar|array $args,... Arguments for statement parameters.
	 */
	public function subquery() {
		$args = func_get_args();
		$name = array_shift($args);
		$model = array_shift($args);
		$statement = array_shift($args);
		$this->_subqueries[] = array(
			'name' => $name,
			'model' => $model,
			'statement' => $statement,
			'args' => $args
		);
	}
	/**
	 * Add a where clause to the query.
	 * @param string $statement A parameterized statement (e.g., "name = ?")
	 * @param scalar|array $args,... Arguments for statement parameters.
	 * @return Dbi_Sql_Criteria An object that can be used to chain clauses.
	 */
	public function where() {
		$args = func_get_args();
		return call_user_func_array(array($this, 'andWhere'), $args);
	}
	/**
	 * Add a where clause to the query.
	 * @param string $statement A parameterized statement (e.g., "name = ?")
	 * @param scalar|array $args,... Arguments for statement parameters.
	 * @return Dbi_Sql_Criteria An object that can be used to chain clauses.
	 */
	public function andWhere() {
		$args = func_get_args();
		$statement = array_shift($args);
		//if ( (count($args) == 1) && (is_null($args[0])) ) {
		//	$this->_wheres = array();
		//	return null;
		//}
		$criteria = new Dbi_Sql_Criteria($this, new Dbi_Sql_Expression($statement, $args));
		$this->_wheres[] = $criteria;
		return $criteria;
	}
	/**
	 * Append a where clause using OR to the most recently added where clause.
	 * @param string $statement A parameterized statement (e.g., "name = ?")
	 * @param scalar|array $args,... Arguments for statement parameters.
	 * @return Dbi_Sql_Criteria An object that can be used to chain clauses.
	 */
	public function orWhere() {
		$args = func_get_args();
		if (count($this->_wheres)) {
			$criteria = call_user_func_array(array($this->_wheres[count($this->_wheres)- 1], 'orWhere'), $args);
		} else {
			$statement = array_shift($args);
			$criteria = new Dbi_Sql_Criteria($this, new Dbi_Sql_Expression($statement, $args));
			$this->_wheres[] = $criteria;
		}
		return $criteria;
	}
	public function group($fields) {
		$args = func_get_args();
		foreach ($args as $arg) {
			$this->_group[] = $arg;
		}
	}
	public function having() {
		$args = func_get_args();
		return call_user_func_array(array($this, 'andHaving'), $args);
	}
	public function andHaving() {
		$args = func_get_args();
		$statement = array_shift($args);
		$criteria = new Dbi_Sql_Criteria($this, new Dbi_Sql_Expression($statement, $args));
		$this->_haves[] = $criteria;
		return $criteria;
	}
	public function orHaving() {
		$args = func_get_args();
		if (count($this->_haves)) {
		 call_user_func_array(array($this->_haves[count($this->_wheres)- 1], 'orWhere'), $args);
		} else {
			$statement = array_shift($args);
			$criteria = new Dbi_Sql_Criteria($this, new Dbi_Sql_Expression($statement, $args));
			$this->_haves[] = $criteria;
		}
		return $criteria;
	}
	/**
	 * Set the fields by which the results will be sorted.
	 * @param scalar|array $fields,... The fields used to order the query.
	 */
	public function order() {
		$this->_orders = array();
		$args = func_get_args();
		foreach ($args as $arg) {
			if (is_array($arg)) {
				$this->order($arg);
			} else if (!empty($arg)) {
				$this->_orders[] = $arg;
			}
		}
	}
	public function selectFields() {
		$this->_fields = array();
		$args = func_get_args();
		foreach ($args as $arg) {
			if (is_array($arg)) {
				$this->select($arg);
			} else if (!empty($arg)) {
				$this->_fields[] = $arg;
			}
		}
	}
	/**
	 * Get an object containing the model's query components.
	 * @return Dbi_Model_QueryComponents A property iterator of the component data.
	 */
	public function components() {
		$components = new Dbi_Model_QueryComponents();
		$components->table = $this->name();
		$components->where = $this->_wheres;
		$components->fields = $this->_fields;
		$components->subqueries = $this->_subqueries;
		$components->innerJoins = $this->_innerJoins;
		$components->leftJoins = $this->_leftJoins;
		$components->orders = $this->_orders;
		$components->limit = $this->_limit;
		$components->groups = $this->_group;
		$components->having = $this->_haves;
		return $components;
	}
	/**
	 * Limit the number of records returned by the query.
	 * @param int $start The index at which to start.
	 * @param int $count The maximum number of records to return.
	 */
	public function limit($start, $count = null) {
		$this->_limit = array($start);
		if (!is_null($count)) {
			$this->_limit[] = $count;
		}
	}
	public function __construct() {
		$this->source = Dbi_Source::GetModelSource($this);
	}
}
