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
        if ($errorHandler === null) {
            $errorHandler = new ErrorHandler\Throwing();
        }
        
        $this->tokens = [];
        $pos = 0;
        $len = strlen($code);
        $mode = self::MODE_PHP;
        $depth = 0;
        $buffer = '';
        $inJSXText = false;
        $inPhpTag = false;
        $line = 1;

        error_log("Starting tokenization of code: " . $code);

        while ($pos < $len) {
            error_log("Position $pos, Char '" . $code[$pos] . "', Mode $mode, Depth $depth, Buffer: '$buffer', InJSXText: " . ($inJSXText ? 'true' : 'false'));

            if ($pos === 0 && substr($code, 0, 5) === '<?php') {
                error_log("Found PHP opening tag at position 0");
                $this->tokens[] = new Token(T_OPEN_TAG, '<?php', $line);
                $pos += 5;
                $inPhpTag = true;
                continue;
            }

            $char = $code[$pos];

            if ($mode === self::MODE_PHP) {
                if ($char === '<' && $pos + 1 < $len && ctype_alpha($code[$pos + 1])) {
                    // Found potential JSX start
                    $mode = self::MODE_JSX;
                    $this->tokens[] = new Token(ord('<'), '<', $line);
                    
                    // Skip the opening angle bracket
                    $pos++;
                    
                    // Skip whitespace
                    while ($pos < $len && ctype_space($code[$pos])) {
                        if ($code[$pos] === "\n") {
                            $line++;
                        }
                        $pos++;
                    }
                    
                    // Collect tag name
                    $tagName = '';
                    while ($pos < $len && (ctype_alnum($code[$pos]) || $code[$pos] === '_' || $code[$pos] === '-')) {
                        $tagName .= $code[$pos];
                        $pos++;
                    }
                    
                    if (!empty($tagName)) {
                        $this->tokens[] = new Token(T_STRING, $tagName, $line);
                        $depth++;
                    }
                    continue;
                }
                
                // Handle whitespace
                if ($char === "\n") {
                    $line++;
                    $pos++;
                    continue;
                } else if (ctype_space($char)) {
                    $pos++;
                    continue;
                }
                
                // Handle variable declaration
                if ($char === '$') {
                    $varName = '';
                    $i = $pos;
                    while ($i < $len && (ctype_alnum($code[$i]) || $code[$i] === '_' || $code[$i] === '$')) {
                        $varName .= $code[$i];
                        $i++;
                    }
                    
                    if (!empty($varName)) {
                        $this->tokens[] = new Token(T_VARIABLE, $varName, $line);
                        $pos = $i;
                        continue;
                    }
                }
                
                // Handle assignment operator
                if ($char === '=') {
                    $this->tokens[] = new Token(ord('='), '=', $line);
                    $pos++;
                    continue;
                }
                
                // Handle semicolon
                if ($char === ';') {
                    $this->tokens[] = new Token(ord(';'), ';', $line);
                    $pos++;
                    continue;
                }
                
                $pos++;
            } else if ($mode === self::MODE_JSX) {
                if ($char === '<') {
                    if (!empty($buffer)) {
                        // Emit JSX text content as T_CONSTANT_ENCAPSED_STRING
                        $trimmedBuffer = trim($buffer);
                        if (!empty($trimmedBuffer)) {
                            $this->tokens[] = new Token(T_CONSTANT_ENCAPSED_STRING, '"' . addslashes($trimmedBuffer) . '"', $line);
                        }
                        $buffer = '';
                    }
                    
                    $this->tokens[] = new Token(ord('<'), '<', $line);
                    $pos++;
                    
                    // Check if it's a closing tag
                    $isClosingTag = false;
                    if ($pos < $len && $code[$pos] === '/') {
                        $isClosingTag = true;
                        $this->tokens[] = new Token(ord('/'), '/', $line);
                        $pos++;
                    }
                    
                    // Skip whitespace
                    while ($pos < $len && ctype_space($code[$pos])) {
                        if ($code[$pos] === "\n") {
                            $line++;
                        }
                        $pos++;
                    }
                    
                    // Collect tag name
                    $tagName = '';
                    while ($pos < $len && (ctype_alnum($code[$pos]) || $code[$pos] === '_' || $code[$pos] === '-')) {
                        $tagName .= $code[$pos];
                        $pos++;
                    }
                    
                    if (!empty($tagName)) {
                        $this->tokens[] = new Token(T_STRING, $tagName, $line);
                        if ($isClosingTag) {
                            $depth--;
                        } else {
                            $depth++;
                        }
                    }
                } else if ($char === '>') {
                    $this->tokens[] = new Token(ord('>'), '>', $line);
                    $pos++;
                    
                    if ($depth === 0) {
                        $mode = self::MODE_PHP;
                        $inJSXText = false;
                    } else {
                        $inJSXText = true;
                    }
                } else if ($inJSXText) {
                    if ($char === "\n") {
                        $line++;
                    }
                    $buffer .= $char;
                    $pos++;
                } else {
                    // Skip whitespace between attributes
                    if ($char === "\n") {
                        $line++;
                    }
                    $pos++;
                }
            }
        }

        if ($inPhpTag) {
            error_log("Adding closing PHP tag");
            $this->tokens[] = new Token(T_CLOSE_TAG, '?>', $line);
        }

        // Add sentinel token
        $this->tokens[] = new Token(0, "\0", $line, $pos);

        error_log("Final token stream: " . print_r($this->tokens, true));
        return $this->tokens;
    }

    private function isJSXStart(string $code, int $pos): bool
    {
        // Skip whitespace
        $i = $pos + 1;
        while ($i < strlen($code) && ctype_space($code[$i])) {
            $i++;
        }

        // Check if we're in a valid context for JSX
        $prevChar = $pos > 0 ? $code[$pos - 1] : null;
        $isValidContext = $prevChar === null || 
                         $prevChar === '=' || 
                         $prevChar === '(' || 
                         $prevChar === ',' || 
                         $prevChar === ';' || 
                         ctype_space($prevChar);

        // Check if next char is a valid JSX tag start
        $nextChar = $i < strlen($code) ? $code[$i] : null;
        $result = $isValidContext && $nextChar !== null && (
            ctype_alpha($nextChar) ||
            $nextChar === '_'
        );

        error_log(sprintf(
            "isJSXStart check at pos %d: prevChar='%s', nextChar='%s', result=%s",
            $pos,
            $prevChar,
            $nextChar,
            $result ? 'true' : 'false'
        ));

        return $result;
    }
} 