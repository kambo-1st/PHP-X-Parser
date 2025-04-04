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

    /** @var bool */
    private $inJSXText = false;

    /** @var bool */
    private $inPhpTag = false;

    /** @var string */
    private $textBuffer = '';

    /** @var string */
    private $code = '';

    /** @var int */
    private $position = 0;

    /** @var int */
    private $line = 1;

    /** @var array */
    private $closingModeInfo = [];

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
        $this->code = $code;
        $this->position = 0;
        $this->tokens = [];
        $this->mode = self::MODE_PHP;
        $this->jsxDepth = 0;
        $this->inJSXText = false;
        $this->textBuffer = '';
        $this->line = 1;
        $this->closingModeInfo = [];

        $startPosition = $this->position;
        error_log("Starting tokenization with mode: " . $this->mode . ", depth: " . $this->jsxDepth);

        while ($this->position < strlen($this->code)) {
            $char = $this->code[$this->position];
            
            if ($this->mode === self::MODE_PHP) {
                // Handle PHP opening tag
                if (substr($this->code, $this->position, 5) === '<?php') {
                    $this->tokens[] = new Token(T_OPEN_TAG, '<?php', $this->line);
                    $this->position += 5;
                    continue;
                }

                if ($char === '<' && $this->isJSXStart()) {
                    error_log("Entering JSX mode at position " . $this->position . ", depth: " . $this->jsxDepth);

                    echo "Closing ".$this->mode." mode (0 - PHP):" .$startPosition . ' possition: '.$this->position;
                    $this->closingModeInfo[] = [$this->mode, $startPosition, $this->position];
                    $startPosition = $this->position;

                    if ($this->textBuffer !== '') {
                        $this->tokens[] = new Token(T_CONSTANT_ENCAPSED_STRING, $this->textBuffer, $this->line);
                        $this->textBuffer = '';
                    }
                    $this->mode = self::MODE_JSX;
                    $this->jsxDepth++;
                    $this->inJSXText = false;
                    $this->tokens[] = new Token(ord('<'), '<', $this->line);
                    $this->position++;
                    $tagName = $this->consumeJSXTagName();
                    $this->tokens[] = new Token(T_STRING, $tagName, $this->line);
                    continue;
                }

                // Handle basic PHP tokens
                if ($char === '$') {
                    $varName = '';
                    $this->position++;
                    while ($this->position < strlen($this->code) && (ctype_alnum($this->code[$this->position]) || $this->code[$this->position] === '_')) {
                        $varName .= $this->code[$this->position];
                        $this->position++;
                    }
                    if (!empty($varName)) {
                        $this->tokens[] = new Token(T_VARIABLE, '$' . $varName, $this->line);
                    }
                    continue;
                }

                if ($char === '=') {
                    $this->tokens[] = new Token(ord('='), '=', $this->line);
                    $this->position++;
                    continue;
                }

                if ($char === ';') {
                    $this->tokens[] = new Token(ord(';'), ';', $this->line);
                    $this->position++;
                    continue;
                }

                if (ctype_space($char)) {
                    if ($char === "\n") {
                        $this->line++;
                    }
                    $this->position++;
                    continue;
                }

                $this->position++;
                continue;
            }

            if ($this->mode === self::MODE_JSX) {
                if ($char === '{') {
                    if ($this->textBuffer !== '') {
                        $this->tokens[] = new Token(T_CONSTANT_ENCAPSED_STRING, $this->textBuffer, $this->line);
                        $this->textBuffer = '';
                    }

                    // Check for spread operator
                    if ($this->position + 3 < strlen($this->code) &&
                        $this->code[$this->position + 1] === '.' &&
                        $this->code[$this->position + 2] === '.' &&
                        $this->code[$this->position + 3] === '.') {

                        echo "Closing ".$this->mode." mode (0 - PHP):" .$startPosition . ' possition: '.$this->position;
                        $this->closingModeInfo[] = [$this->mode, $startPosition, $this->position-1];
                        $startPosition = $this->position-1;

                        $this->tokens[] = new Token(ord('.'), '.', $this->line);
                        $this->tokens[] = new Token(ord('.'), '.', $this->line);
                        $this->tokens[] = new Token(ord('.'), '.', $this->line);
                        $this->position += 4;
                        $this->mode = self::MODE_JSX_EXPR;



                        continue;
                    }

                    $this->tokens[] = new Token(ord('{'), '{', $this->line);

                    echo "Closing ".$this->mode." mode (0 - PHP):" .$startPosition . ' possition: '.$this->position;
                    $this->closingModeInfo[] = [$this->mode, $startPosition, $this->position-1];
                    $startPosition = $this->position-1;

                    $this->mode = self::MODE_JSX_EXPR;
                    $this->position++;
                    continue;
                }

                if ($char === '<') {
                    if ($this->textBuffer !== '') {
                        $this->tokens[] = new Token(T_CONSTANT_ENCAPSED_STRING, $this->textBuffer, $this->line);
                        $this->textBuffer = '';
                    }

                    // Check for closing tag
                    if ($this->position + 1 < strlen($this->code) && $this->code[$this->position + 1] === '/') {
                        error_log("Found closing tag at position " . $this->position . ", depth: " . $this->jsxDepth);
                        $this->tokens[] = new Token(ord('<'), '<', $this->line);
                        $this->tokens[] = new Token(ord('/'), '/', $this->line);
                        $this->position += 2;

                        // Get the closing tag name
                        $tagName = '';
                        while ($this->position < strlen($this->code) && (ctype_alnum($this->code[$this->position]) || $this->code[$this->position] === '_' || $this->code[$this->position] === '-')) {
                            $tagName .= $this->code[$this->position];
                            $this->position++;
                        }
                        if (!empty($tagName)) {
                            $this->tokens[] = new Token(T_STRING, $tagName, $this->line);
                        }

                        // Skip to closing >
                        while ($this->position < strlen($this->code) && $this->code[$this->position] !== '>') {
                            if ($this->code[$this->position] === "\n") $this->line++;
                            $this->position++;
                        }
                        if ($this->position < strlen($this->code) && $this->code[$this->position] === '>') {
                            $this->tokens[] = new Token(ord('>'), '>', $this->line);
                            $this->jsxDepth--;
                            if ($this->jsxDepth === 0) {
                                echo "Closing ".$this->mode." mode (0 - PHP):" .$startPosition . ' possition: '.$this->position;
                                $this->closingModeInfo[] = [$this->mode, $startPosition, $this->position+1];
                                $startPosition = $this->position+1;

                                $this->mode = self::MODE_PHP;
                            }
                            $this->position++;
                        }
                        continue;
                    }

                    // Handle opening tag
                    $this->tokens[] = new Token(ord('<'), '<', $this->line);
                    $this->position++;
                    $this->jsxDepth++;
                    $tagName = $this->consumeJSXTagName();
                    $this->tokens[] = new Token(T_STRING, $tagName, $this->line);
                    continue;
                }

                if ($char === '>' || ($char === '/' && $this->position + 1 < strlen($this->code) && $this->code[$this->position + 1] === '>')) {
                    error_log("Found closing bracket at position " . $this->position . ", depth: " . $this->jsxDepth . ", inJSXText: " . ($this->inJSXText ? "true" : "false"));
                    if ($char === '/') {
                        $this->tokens[] = new Token(ord('/'), '/', $this->line);
                        $this->position++;
                        $this->jsxDepth--;
                        if ($this->jsxDepth === 0) {
                            $this->tokens[] = new Token(ord('>'), '>', $this->line);
                            $this->position++;
                            echo "Closing ".$this->mode." mode (0 - PHP):" .$startPosition . ' possition: '.$this->position;
                            $this->closingModeInfo[] = [$this->mode, $startPosition, $this->position];
                            $startPosition = $this->position;
                            $this->mode = self::MODE_PHP;
                            continue;
                        }
                    }
                    $this->tokens[] = new Token(ord('>'), '>', $this->line);
                    $this->position++;
                    if ($char !== '/') {
                        $this->inJSXText = true;
                        $this->textBuffer = '';
                    }
                    error_log("After closing bracket at position " . $this->position . ", depth: " . $this->jsxDepth . ", inJSXText: " . ($this->inJSXText ? "true" : "false"));
                    continue;
                }

                if ($this->inJSXText) {
                    if (!ctype_space($char) || $this->textBuffer !== '') {
                        $this->textBuffer .= $char;
                    }
                    $this->position++;
                    continue;
                }

                if (ctype_alpha($char) || $char === '_' || $char === '-') {
                    $attrName = '';
                    while ($this->position < strlen($this->code) && (ctype_alnum($this->code[$this->position]) || $this->code[$this->position] === '_' || $this->code[$this->position] === '-')) {
                        $attrName .= $this->code[$this->position];
                        $this->position++;
                    }
                    if (!empty($attrName)) {
                        $this->tokens[] = new Token(T_STRING, $attrName, $this->line);
                    }

                    // Look for equals sign
                    while ($this->position < strlen($this->code) && ctype_space($this->code[$this->position])) {
                        if ($this->code[$this->position] === "\n") $this->line++;
                        $this->position++;
                    }

                    if ($this->position < strlen($this->code) && $this->code[$this->position] === '=') {
                        $this->tokens[] = new Token(ord('='), '=', $this->line);
                        $this->position++;

                        // Skip whitespace
                        while ($this->position < strlen($this->code) && ctype_space($this->code[$this->position])) {
                            if ($this->code[$this->position] === "\n") $this->line++;
                            $this->position++;
                        }

                        if ($this->code[$this->position] === '{') {
                            $this->tokens[] = new Token(ord('{'), '{', $this->line);
                            echo "Closing ".$this->mode." mode (0 - PHP):" .$startPosition . ' possition: '.$this->position;
                            $this->closingModeInfo[] = [$this->mode, $startPosition, $this->position];
                            $startPosition = $this->position;

                            $this->mode = self::MODE_JSX_EXPR;
                            $this->position++;
                        } else if ($this->code[$this->position] === '"') {
                            $this->position++;
                            $value = '';
                            while ($this->position < strlen($this->code) && $this->code[$this->position] !== '"') {
                                $value .= $this->code[$this->position];
                                $this->position++;
                            }
                            $this->tokens[] = new Token(T_CONSTANT_ENCAPSED_STRING, $value, $this->line);
                            $this->position++;
                        }
                    }
                    continue;
                }

                if (ctype_space($char)) {
                    if ($char === "\n") {
                        $this->line++;
                    }
                    $this->position++;
                    continue;
                }

                $this->position++;
                continue;
            }

            if ($this->mode === self::MODE_JSX_EXPR) {
                if ($char === '}') {
                    echo "Closing ".$this->mode." mode (0 - PHP):" .$startPosition . ' possition: '.$this->position;
                    $this->closingModeInfo[] = [$this->mode, $startPosition, $this->position + 1];
                    $startPosition = $this->position + 1;

                    $this->mode = self::MODE_JSX;
                    $this->tokens[] = new Token(ord('}'), '}', $this->line);
                    $this->position++;
                    continue;
                }

                if ($char === '$') {
                    $varName = '';
                    $this->position++;
                    while ($this->position < strlen($this->code) && (ctype_alnum($this->code[$this->position]) || $this->code[$this->position] === '_')) {
                        $varName .= $this->code[$this->position];
                        $this->position++;
                    }
                    if (!empty($varName)) {
                        $this->tokens[] = new Token(T_VARIABLE, '$' . $varName, $this->line);
                    }
                    continue;
                }

                if (ctype_space($char)) {
                    if ($char === "\n") {
                        $this->line++;
                    }
                    $this->position++;
                    continue;
                }

                $this->position++;
                continue;
            }
        }

        // Emit any remaining text buffer
        if ($this->textBuffer !== '') {
            $this->tokens[] = new Token(T_CONSTANT_ENCAPSED_STRING, $this->textBuffer, $this->line);
        }

        // Add EOF token
        $this->tokens[] = new Token(0, '', $this->line);

        echo "Closing ".$this->mode." mode (0 - PHP):" .$startPosition . ' possition: '.$this->position;
        $this->closingModeInfo[] = [$this->mode, $startPosition, $this->position];



        $code = '';
        foreach ($this->closingModeInfo as $info) {
            $code .= "\n BLOCK of type ".$info[0].":".substr($this->code, $info[1], $info[2] - $info[1])."\n";
        }
        echo "Code: " . $code;

        return $this->tokens;
    }

    private function isJSXStart(): bool
    {
        // Skip any whitespace after <
        $i = $this->position + 1;
        while ($i < strlen($this->code) && ctype_space($this->code[$i])) {
            if ($this->code[$i] === "\n") $this->line++;
            $i++;
        }

        // Check if next character is a valid JSX tag start
        return $i < strlen($this->code) && (ctype_alpha($this->code[$i]) || $this->code[$i] === '_');
    }

    private function consumeJSXTagName(): string
    {
        $tagName = '';
        while ($this->position < strlen($this->code) && (ctype_alnum($this->code[$this->position]) || $this->code[$this->position] === '_' || $this->code[$this->position] === '-')) {
            $tagName .= $this->code[$this->position];
            $this->position++;
        }

        return $tagName;
    }
    
    /**
     * Returns the collected closing mode information.
     * Each entry is an array with structure [mode, startPosition, endPosition].
     *
     * @return array The collected closing mode information
     */
    public function getClosingModeInfo(): array
    {
        return $this->closingModeInfo;
    }
} 