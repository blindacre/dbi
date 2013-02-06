<?php
class Dbi_Sql_Tokenizer {
	private static function _Validate($token) {
		$lower = strtolower($token);
		if ( ($lower == 'and') || ($lower == 'or') ) {
			//throw new Exception('Invalid token ' . $token);
		}
	}
	/**
	 * Parse a statement into a token array.
	 * @param string $statement
	 * @return array
	 */
	public static function Tokenize($statement) {
		$tokens = array();
		$current = '';
		$parenthesis = 0;
		for ($i = 0; $i < strlen($statement); $i++) {
			$char = substr($statement, $i, 1);
			switch ($char) {
				case ' ':
					if ($current) {
						self::_Validate($current);
						$tokens[] = $current;
						$current = '';
					}
					break;
				case '!':
				case '+':
				case '/':
				case '*':
				case ',':
				case '%':
				case '>':
				case '<':
				case '?':
					if ($current) {
						self::_Validate($current);
						$tokens[] = $current;
						$current = '';
					}
					$tokens[] = $char;
					break;
				case '=':
					if ($current) {
						$tokens[] = $current;
					}
					$l = count($tokens) - 1;
					if ($l >= 0 && in_array($tokens[$l], array('!', '=', '<', '>'))) {
						$tokens[$l] .= '=';
					} else {
						$tokens[] = '=';
					}
					$current = '';
					break;
				case '(':
					$parenthesis++;
					if ($current) {
						self::_Validate($current);
						$tokens[] = $current;
						$current = '';
					}
					$tokens[] = $char;
					break;
				case ')':
					$parenthesis--;
					if ($parenthesis < 0) {
						throw new Exception('Unbalanced parentheses in statement ' . $statement);
					}
					if ($current) {
						self::_Validate($current);
						$tokens[] = $current;
						$current = '';
					}
					$tokens[] = $char;
					break;
				case '"':
				case "'":
					throw new Exception('Quoted strings not allowed in statement ' . $statement);
					break;
				default:
					$current .= $char;
			}
		}
		if ($parenthesis != 0) {
			throw new Exception('Unbalanced parentheses in statement ' . $statement);
		}
		if (trim($current) !== '') {
			self::_Validate($current);
			$tokens[] = $current;
			$current = '';
		}
		return $tokens;
	}
}
