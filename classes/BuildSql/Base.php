<?php
class BuildSql_Base {
	private $_escapeFunction;
	protected function _parameterize($expr, $args = null) {
		if ( (!preg_match('/\?/', $expr)) && (!$args) ) return $expr;
		$num_args = preg_match_all("/\?/", $expr, $matches);
		if ($num_args != count($args)) {
			$err = debug_backtrace();
			trigger_error("Error: wrong number of arguments passed to expression '{$expr}': {$num_args} expected, " . count($args) . " received from <strong>{$err[0]['file']}</strong>, line <strong>{$err[0]['line']}</strong>");
			$this->_broken = true;
			return false;
		}
		$final = $expr;
		// Find the first question mark in the prepared query
		$start_offset = $end_offset = strpos($final, '?');
		foreach ($args as $v) {
			if (is_null($v)) {
				$v = 'NULL';
			} elseif (is_array($v)) {
				$v = ("('" . implode("','", array_map($this->_escapeFunction, $v)) . "')");
				/*$start_offset = strrpos($final, '=');
				if ('!' == substr($final, ($start_offset - 1), 1)) {
					 --$start_offset;
					$v = "NOT $v";
				}*/
			} else {
				$v = ("'" . call_user_func($this->_escapeFunction, $v) . "'");
			}
			// Replace the question mark with the argument
			$final = substr($final, 0, $start_offset) . $v . substr($final, ($end_offset + 1));
			// Move to the next question mark after the last inserted argument
			$start_offset = $end_offset = strpos($final, '?', $start_offset + strlen($v));
		}
		return $final;
	}
	protected function _parseFields($text, $args = array()) {
		$fields = array();
		$stack = 0;
		$last = '';
		for ($i = 0; $i < strlen($text); $i++) {
			$chr = substr($text, $i, 1);
			if ($chr == ',') {
				if ($stack == 0) {
					if (trim($last)) {
						array_push($fields, trim($last));
						$last = '';
					}
				} else {
					$last .= $chr;
				}
			} else {
				if ($chr == '(') {
					$stack++;
				} elseif ($chr == ')') {
					$stack--;
				}
				$last .= $chr;
			}
			if ($stack < 0) {
				trigger_error('Unbalanced parentheses');
				$this->_broken = true;
				return array();
			}
		}
		if (trim($last)) {
			array_push($fields, trim($last));
		}
		if ($stack != 0) {
			trigger_error('Unbalanced parentheses');
			$this->_broken = true;
			return array();
		}
		if ($args) {
			$text = join('|||', $fields);
			$text = $this->_parameterize($text, $args);
			$fields = explode('|||', $text);
		}
		return $fields;
	}
	public function __construct($escapeFunction = 'addslashes') {
		$this->_escapeFunction = $escapeFunction;
	}
}
