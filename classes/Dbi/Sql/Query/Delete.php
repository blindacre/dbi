<?php

class Dbi_Sql_Query_Delete extends Dbi_Sql_QueryWhere {
	public function expression() {
		$parameters = array();
		$sql = 'DELETE FROM ';
		$sql .= "\n" . join(', ', $this->_tables) . ' ';
		$append = "\nWHERE ";
		foreach ($this->_wheres as $where) {
			$sql .= $append . $where->statement();
			$parameters = array_merge($parameters, $where->parameters());
			$append = ' AND ';
		}
		return new Dbi_Sql_Expression($sql, $parameters);
	}	
}
