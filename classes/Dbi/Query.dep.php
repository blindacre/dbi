<?php
class Dbi_Query {
	private $_model;
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
	public function __construct(Dbi_Model $model) {
		$this->_model = $model;
	}
	/**
	 * The model on which this query will be executed.
	 * @return Dbi_Model
	 */
	public function model() {
		return $this->_model;
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
		if (get_class($this->_model) == $joined || in_array($joined, self::$_joinStack)) {
			return;
		}
		self::$_joinStack[] = get_class($this->_model);
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
		self::$_joinStack[] = get_class($this->_model);
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
	 * @return Dbi_Query_Criteria An object that can be used to chain clauses.
	 */
	public function where() {
		$args = func_get_args();
		return call_user_func_array(array($this, 'andWhere'), $args);
	}
	/**
	 * Add a where clause to the query.
	 * @param string $statement A parameterized statement (e.g., "name = ?")
	 * @param scalar|array $args,... Arguments for statement parameters.
	 * @return Dbi_Query_Criteria An object that can be used to chain clauses.
	 */
	public function andWhere() {
		$args = func_get_args();
		if ( (count($args) == 1) && (is_null($args[0])) ) {
			$this->_wheres = array();
			return null;
		}
		$criteria = new Dbi_Query_Criteria($this, new Dbi_Query_Expression($args));
		$this->_wheres[] = $criteria;
		return $criteria;
	}
	/**
	 * Append a where clause using OR to the most recently added where clause.
	 * @param string $statement A parameterized statement (e.g., "name = ?")
	 * @param scalar|array $args,... Arguments for statement parameters.
	 * @return Dbi_Query_Criteria An object that can be used to chain clauses.
	 */
	public function orWhere() {
		$args = func_get_args();
		if (count($this->_wheres)) {
			$criteria = call_user_func_array(array($this->_wheres[count($this->_wheres)- 1], 'orWhere'), $args);
		} else {
			$criteria = new Dbi_Query_Criteria($this, new Dbi_Query_Expression($args));
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
		if ( (count($args) == 1) && (is_null($args[0])) ) {
			$this->_haves = array();
			return null;
		}
		$criteria = new Dbi_Query_Criteria($this, new Dbi_Query_Expression($args));
		$this->_haves[] = $criteria;
		return $criteria;
	}
	public function orHaving() {
		$args = func_get_args();
		if (count($this->_haves)) {
		 call_user_func_array(array($this->_haves[count($this->_wheres)- 1], 'orWhere'), $args);
		} else {
			$criteria = new Dbi_Query_Criteria($this, new Dbi_Query_Expression($args));
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
	 * Get an associative array of all the query's components.
	 * @return Dbi_Query_Components A property iterator of the component data.
	 */
	public function components() {
		// TODO: The 'table' value passed in the components does NOT
		// include the prefix. This currently works for Dbi_Source_MySql,
		// but might not be the most intuitive way to handle prefixes.
		/*$components = array(
			'table' => $this->_model->name(),
			'where' => $this->_wheres,
			'fields' => $this->_fields,
			'subqueries' => $this->_subqueries,
			'innerJoins' => $this->_innerJoins,
			'leftJoins' => $this->_leftJoins,
			'orders' => $this->_orders,
			'limit' => $this->_limit,
			'groups' => $this->_group
		);*/
		// TODO: Make an internal Dbi_Query_Components object that can be
		// updated by relevant methods (e.g., $this->where()) and doesn't have
		// to be rebuilt for each call to the components() method.
		$components = new Dbi_Query_Components();
		$components->table = $this->_model->name();
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
}
