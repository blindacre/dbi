<?php

abstract class Dbi_Source_SqlAbstract extends Dbi_Source {
	private function _build(Dbi_Sql_Query_Select $select, Dbi_Model $query, $components, $parent = '', $forceLeft = false) {
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
				$subquery = $innerJoin['model'];
				$subcomponents = $subquery->components();
				$subcomponents['table'] = $innerJoin['name'];
				$subs[] = array('query' => $subquery, 'components' => $subcomponents, 'forceLeft' => false);
			}
			foreach ($components['leftJoins'] as $join) {
				$subquery = $join['model'];
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
				$tokens = Dbi_Sql_Tokenizer::Tokenize($statement);
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
			$tokens = Dbi_Sql_Tokenizer::Tokenize($args[1]);
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
			$tokens = Dbi_Sql_Tokenizer::Tokenize($args[1]);
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
				$tokens = Dbi_Sql_Tokenizer::Tokenize($statement);
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
	protected function _generateSql(Dbi_Model $query) {
		//$select = new BuildSql_Select('mysqli_real_escape_string');
		$select = new Dbi_Sql_Query_Select();
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
}
