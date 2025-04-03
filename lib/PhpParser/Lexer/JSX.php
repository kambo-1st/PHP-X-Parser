<?php declare(strict_types=1);

namespace PhpParser\Lexer;

use PhpParser\Error;
use PhpParser\ErrorHandler;
use PhpParser\Lexer;
use PhpParser\Token;
use PhpParser\Parser\Tokens;

class JSX extends Lexer {
    const MODE_PHP = 0;
    const MODE_JSX = 1;
    const MODE_JSX_EXPR = 2;
    
    /** @var int */
    private $mode = self::MODE_PHP;
    
    /** @var int */
    private $jsxDepth = 0;
    
    /** @var string */
    private $buffer = '';
    
    /** @var array */
    private $tokens = [];

    /** @var array */
    private $options = [];

    /**
     * Creates a Lexer.
     *
     * @param array $options Options of the lexer. Currently only the `usedAttributes` option
     *                       is supported, which is a map of tokens to attributes that should be
     *                       preserved in the AST.
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Tokenizes the given source code.
     *
     * @param string $code The source code to tokenize
     * @param ErrorHandler|null $errorHandler Error handler to use for lexing errors. If null
     *                                        defaults to ErrorHandler\Throwing.
     * @return array Array of tokens
     */
    public function tokenize(string $code, ?ErrorHandler $errorHandler = null): array
    {
        $this->mode = self::MODE_PHP;
        $this->jsxDepth = 0;
        $this->buffer = '';
        $this->tokens = [];

        $length = strlen($code);
        $pos = 0;

        while ($pos < $length) {
            $char = $code[$pos];

            switch ($this->mode) {
                case self::MODE_PHP:
                    if ($char === '<' && $this->isJSXStart($code, $pos)) {
                        // Convert JSX element into array syntax
                        $this->tokens[] = new Token(Tokens::T_ARRAY, 'array', $pos);
                        $this->tokens[] = new Token(ord('('), '(', $pos);
                        $this->mode = self::MODE_JSX;
                        $this->jsxDepth++;
                        $pos++;
                        continue 2;
                    }
                    break;

                case self::MODE_JSX:
                    if ($char === '{') {
                        if ($this->buffer !== '') {
                            $this->tokens[] = new Token(Tokens::T_CONSTANT_ENCAPSED_STRING, '"' . $this->buffer . '"', $pos - strlen($this->buffer));
                            $this->buffer = '';
                        }
                        $this->mode = self::MODE_JSX_EXPR;
                        $pos++;
                        continue 2;
                    }
                    if ($char === '>') {
                        if ($this->buffer !== '') {
                            $this->tokens[] = new Token(Tokens::T_CONSTANT_ENCAPSED_STRING, '"' . $this->buffer . '"', $pos - strlen($this->buffer));
                            $this->buffer = '';
                        }
                        $this->tokens[] = new Token(ord(')'), ')', $pos);
                        $pos++;
                        continue 2;
                    }
                    if ($char === '/' && $pos + 1 < $length && $code[$pos + 1] === '>') {
                        $this->jsxDepth--;
                        if ($this->jsxDepth === 0) {
                            $this->mode = self::MODE_PHP;
                        }
                        if ($this->buffer !== '') {
                            $this->tokens[] = new Token(Tokens::T_CONSTANT_ENCAPSED_STRING, '"' . $this->buffer . '"', $pos - strlen($this->buffer));
                            $this->buffer = '';
                        }
                        $this->tokens[] = new Token(ord(')'), ')', $pos);
                        $pos += 2;
                        continue 2;
                    }
                    if ($char === '<' && $pos + 1 < $length && $code[$pos + 1] === '/') {
                        $this->jsxDepth--;
                        if ($this->jsxDepth === 0) {
                            $this->mode = self::MODE_PHP;
                        }
                        if ($this->buffer !== '') {
                            $this->tokens[] = new Token(Tokens::T_CONSTANT_ENCAPSED_STRING, '"' . $this->buffer . '"', $pos - strlen($this->buffer));
                            $this->buffer = '';
                        }
                        $this->tokens[] = new Token(ord(')'), ')', $pos);
                        $pos += 2;
                        continue 2;
                    }
                    if (ctype_space($char)) {
                        if ($this->buffer !== '') {
                            $this->tokens[] = new Token(Tokens::T_CONSTANT_ENCAPSED_STRING, '"' . $this->buffer . '"', $pos - strlen($this->buffer));
                            $this->buffer = '';
                        }
                        $pos++;
                        continue 2;
                    }
                    $this->buffer .= $char;
                    $pos++;
                    continue 2;

                case self::MODE_JSX_EXPR:
                    if ($char === '}') {
                        $this->mode = self::MODE_JSX;
                        $pos++;
                        continue 2;
                    }
                    break;
            }

            // Default PHP tokenization
            $token = parent::tokenize($code, $errorHandler);
            if ($token === null) {
                break;
            }
            $this->tokens = array_merge($this->tokens, $token);
            $pos += strlen($token[0]->text);
        }

        return $this->tokens;
    }

    private function isJSXStart(string $code, int $pos): bool
    {
        // Skip whitespace
        $i = $pos + 1;
        while ($i < strlen($code) && ctype_space($code[$i])) {
            $i++;
        }

        // Check if it's a valid JSX tag start
        return $i < strlen($code) && (
            ctype_alpha($code[$i]) ||
            $code[$i] === '_' ||
            $code[$i] === '>' ||
            $code[$i] === '/'
        );
    }
} 