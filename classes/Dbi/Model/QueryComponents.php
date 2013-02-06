<?php
/**
 * show off @property
 *
 * @property string $table
 * @property array $where
 * @property array $fields
 * @property array $subqueries
 * @property array $innerJoins
 * @property array $leftJoins
 * @property array $orders
 * @property array $limit
 * @property array $groups
 * @property array $having
 */
class Dbi_Model_QueryComponents extends PropertyIteratorAbstract {
	public function __construct() {
		parent::__construct();
		$this->subqueries = array();
		$this->innerJoins = array();
		$this->leftJoins = array();
	}
}
