<?php

namespace Framework\Console\Commands\CompileModels;

use PhpParser\ErrorHandler;
use PhpParser\Lexer\Emulative;

class MinimalPhpLexer extends Emulative {

	public function startLexing( string $code, ErrorHandler $errorHandler = null ) {
		parent::startLexing($code, $errorHandler);

		// Remove all tokens from the first class/interface/trait for a much smaller/faster AST
		foreach ( $this->tokens as $i => $token ) {
			if ( in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT], true) ) {
				$this->tokens = array_slice($this->tokens, 0, $i-1);
				return;
			}
		}
	}

}
