<?php

abstract class Dbi_Sql_QueryWhere extends Dbi_Sql_Query {
	protected $_wheres = array();
	public function where() {
		$args = func_get_args();
		$expr = array_shift($args);
		if (is_null($expr)) return;
		$modified = array();
		$index = 0;
		foreach ($args as $arg) {
			if (is_array($arg)) {
				preg_match_all('/\?/', $expr, $matches, PREG_OFFSET_CAPTURE);
				if (isset($matches[0][$index])) {
					$offset = $matches[0][$index][1];
					$count = count($arg);
					$params = array_fill(0, $count, '?');
					$expr = substr($expr, 0, $offset) . '(' . implode(',', $params) . ')' . substr($expr, $offset + 1);
					foreach ($arg as $inner) {
						$modified[] = $inner;
					}
					$index += $count;
				} else {
					throw new Exception('Unbalanced expression');
				}
			} else {
				$modified[] = $arg;
				$index++;
			}
		}
		$this->_wheres[] = new Dbi_Sql_Expression($expr, $modified);
	}	
}
