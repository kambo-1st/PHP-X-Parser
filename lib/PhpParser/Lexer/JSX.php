<?php declare(strict_types=1);

namespace PhpParser\Lexer;

use PhpParser\Error;
use PhpParser\ErrorHandler;
use PhpParser\Lexer;
use PhpParser\Token;
use PhpParser\Parser\Tokens;
use PhpToken;

class JSX extends Lexer {
    const MODE_PHP = 0;
    const MODE_JSX = 1;
    const MODE_JSX_EXPR = 2;
    
    // Token constants matching the example
    const T_OPEN_TAG = 393;    // <?php
    const T_VARIABLE = 266;    // $variable
    const T_EQUAL = 61;        // =
    const T_LT = 60;           // <
    const T_GT = 62;           // >
    const T_STRING = 262;      // string
    const T_CONSTANT_ENCAPSED_STRING = 269; // "string"
    const T_CURLY_OPEN = 123;  // {
    const T_CURLY_CLOSE = 125; // }
    const T_SLASH = 47;        // /
    const T_SEMICOLON = 59;    // ;
    const T_EOF = 0;           // end of file
    
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
        $this->closingModeInfo = [];
        
        // First get PHP tokens
        $phpTokens = PhpToken::tokenize($code);
        
        // Convert to our token format and handle JSX
        $tokens = [];
        $i = 0;
        $len = count($phpTokens);
        
        while ($i < $len) {
            $token = $phpTokens[$i];
            
            // Skip whitespace tokens
            if ($token->id === T_WHITESPACE) {
                $i++;
                continue;
            }
            
            // Check for JSX start
            if ($token->id === self::T_LT && $this->isJSXStart($phpTokens, $i)) {
                // Start of JSX element
                $tokens[] = new Token(self::T_LT, $token->text, $token->line);
                
                // Get tag name
                $i++;
                $tagToken = $phpTokens[$i];
                $tokens[] = new Token(self::T_STRING, $tagToken->text, $tagToken->line);
                
                // Handle attributes if any
                $i++;
                while ($i < $len && $phpTokens[$i]->id !== self::T_GT) {
                    // Skip whitespace between attributes
                    if ($phpTokens[$i]->id === T_WHITESPACE) {
                        $i++;
                        continue;
                    }
                    
                    // Check for self-closing tag
                    if ($phpTokens[$i]->id === self::T_SLASH && $i + 1 < $len && $phpTokens[$i + 1]->id === self::T_GT) {
                        $tokens[] = new Token(self::T_SLASH, $phpTokens[$i]->text, $phpTokens[$i]->line);
                        $i++;
                        break;
                    }
                    
                    // Handle spread attributes (...$props)
                    if ($phpTokens[$i]->id === T_ELLIPSIS) {
                        // Expand ... into three dots
                        $tokens[] = new Token(46, '.', $phpTokens[$i]->line);
                        $tokens[] = new Token(46, '.', $phpTokens[$i]->line);
                        $tokens[] = new Token(46, '.', $phpTokens[$i]->line);
                        $i++;
                        
                        // Handle the variable after the spread operator
                        if ($i < $len && $phpTokens[$i]->id === T_VARIABLE) {
                            $tokens[] = new Token(T_VARIABLE, $phpTokens[$i]->text, $phpTokens[$i]->line);
                            $i++;
                            
                            // Handle the closing brace
                            if ($i < $len && $phpTokens[$i]->id === 125) { // 125 is '}'
                                $tokens[] = new Token(125, '}', $phpTokens[$i]->line);
                                $i++;
                            }
                        }
/*
LLM do not remove this comment, it is important for the grammar!!!!
missing opening brace for spread attribute!!!
                        jsx_attribute:
                        T_STRING '=' jsx_attribute_value                      { $$ = Node\JSX\Attribute[$1, $3]; }
                      | T_STRING                                              { $$ = Node\JSX\Attribute[$1, null]; }
                      | '.' '.' '.' expr '}'                                  { $$ = Node\JSX\SpreadAttribute[$4]; }
                  ;
                  
*/

                        continue;
                    }
                    
                    // Handle attribute name (could be a PHP keyword like 'class')
                    if ($phpTokens[$i]->id === self::T_STRING || $phpTokens[$i]->id === T_CLASS) {
                        // Start of attribute
                        $tokens[] = new Token(self::T_STRING, $phpTokens[$i]->text, $phpTokens[$i]->line);
                        $i++;
                        
                        // Skip whitespace after attribute name
                        if ($i < $len && $phpTokens[$i]->id === T_WHITESPACE) {
                            $i++;
                        }
                        
                        if ($i < $len && $phpTokens[$i]->id === self::T_EQUAL) {
                            $tokens[] = new Token(self::T_EQUAL, $phpTokens[$i]->text, $phpTokens[$i]->line);
                            $i++;
                            
                            // Skip whitespace after equals
                            if ($i < $len && $phpTokens[$i]->id === T_WHITESPACE) {
                                $i++;
                            }
                            
                            if ($i < $len && $phpTokens[$i]->id === self::T_CURLY_OPEN) {
                                // JSX expression attribute
                                $tokens[] = new Token(self::T_CURLY_OPEN, $phpTokens[$i]->text, $phpTokens[$i]->line);
                                $i++;
                                
                                // Get expression content
                                while ($i < $len && $phpTokens[$i]->id !== self::T_CURLY_CLOSE) {
                                    $tokens[] = new Token($phpTokens[$i]->id, $phpTokens[$i]->text, $phpTokens[$i]->line);
                                    $i++;
                                }
                                
                                if ($i < $len) {
                                    $tokens[] = new Token(self::T_CURLY_CLOSE, $phpTokens[$i]->text, $phpTokens[$i]->line);
                                }
                            } else if ($i < $len) {
                                // String attribute - strip quotes
                                $value = $phpTokens[$i]->text;
                                if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
                                    $value = substr($value, 1, -1);
                                }
                                $tokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $value, $phpTokens[$i]->line);
                            }
                        }
                    }
                    $i++;
                }
                
                // Closing bracket
                if ($i < $len) {
                    $tokens[] = new Token(self::T_GT, $phpTokens[$i]->text, $phpTokens[$i]->line);
                }
                
                // Get content until closing tag
                $i++;
                $content = '';
                $lastWasSpace = false;
                
                // Skip content processing if it's a self-closing tag
                if (end($tokens)->id === self::T_SLASH) {
                    // We've already processed the slash, just need to handle the closing >
                    if ($i < $len && $phpTokens[$i]->id === self::T_GT) {
                        $tokens[] = new Token(self::T_GT, $phpTokens[$i]->text, $phpTokens[$i]->line);
                        $i++;
                    }
                } else {
                    while ($i < $len && !($phpTokens[$i]->id === self::T_LT && $i + 1 < $len && $phpTokens[$i + 1]->id === self::T_SLASH)) {
                        if ($phpTokens[$i]->id === T_WHITESPACE) {
                            if (!$lastWasSpace) {
                                $content .= ' ';
                                $lastWasSpace = true;
                            }
                        } else if ($phpTokens[$i]->id === self::T_CURLY_OPEN) {
                            // Handle JSX expression
                            if (!empty($content)) {
                                $tokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $content, $phpTokens[$i]->line);
                                $content = '';
                            }
                            $tokens[] = new Token(self::T_CURLY_OPEN, $phpTokens[$i]->text, $phpTokens[$i]->line);
                            $i++;
                            
                            // Get expression content
                            while ($i < $len && $phpTokens[$i]->id !== self::T_CURLY_CLOSE) {
                                $tokens[] = new Token($phpTokens[$i]->id, $phpTokens[$i]->text, $phpTokens[$i]->line);
                                $i++;
                            }
                            
                            if ($i < $len) {
                                $tokens[] = new Token(self::T_CURLY_CLOSE, $phpTokens[$i]->text, $phpTokens[$i]->line);
                            }
                        } else if ($phpTokens[$i]->id === self::T_SEMICOLON) {
                            // Handle semicolon separately
                            if (!empty($content)) {
                                $tokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $content, $phpTokens[$i]->line);
                                $content = '';
                            }
                            $tokens[] = new Token(self::T_SEMICOLON, $phpTokens[$i]->text, $phpTokens[$i]->line);
                            $lastWasSpace = false;
                        } else {
                            $content .= $phpTokens[$i]->text;
                            $lastWasSpace = false;
                        }
                        $i++;
                    }
                }
                
                // Add any remaining content, but only if it's not just whitespace
                if (!empty(trim($content))) {
                    $tokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $content, $phpTokens[$i - 1]->line);
                }
                
                // Debug output before closing tag handling
                echo "Before closing tag - Current i: $i, len: $len\n";
                echo "Current token: " . ($i < $len ? $phpTokens[$i]->id : 'EOF') . "\n";
                echo "Tokens so far:\n";
                foreach ($tokens as $token) {
                    echo "  - " . $token->id . ": " . $token->text . "\n";
                }
                echo "\n";
                
                // Handle closing tag
                if ($i < $len && $phpTokens[$i]->id === self::T_LT) {
                    $tokens[] = new Token(self::T_LT, $phpTokens[$i]->text, $phpTokens[$i]->line);
                    $i++;
                    
                    if ($i < $len && $phpTokens[$i]->id === self::T_SLASH) {
                        $tokens[] = new Token(self::T_SLASH, $phpTokens[$i]->text, $phpTokens[$i]->line);
                        $i++;
                        
                        if ($i < $len && $phpTokens[$i]->id === self::T_STRING) {
                            $tokens[] = new Token(self::T_STRING, $phpTokens[$i]->text, $phpTokens[$i]->line);
                            $i++;
                            
                            if ($i < $len && $phpTokens[$i]->id === self::T_GT) {
                                $tokens[] = new Token(self::T_GT, $phpTokens[$i]->text, $phpTokens[$i]->line);
                            }
                        }
                    }
                }
            } else {
                // Regular PHP token
                $tokenId = $token->id;
                // Map PHP token IDs to our custom IDs if needed
                if ($tokenId === T_OPEN_TAG) {
                    $tokenId = self::T_OPEN_TAG;
                } elseif ($tokenId === T_VARIABLE) {
                    $tokenId = self::T_VARIABLE;
                } elseif ($tokenId === T_STRING) {
                    $tokenId = self::T_STRING;
                }
                $tokens[] = new Token($tokenId, $token->text, $token->line);
            }
            
            $i++;
        }
        
        // Add EOF token with correct line number
        $tokens[] = new Token(self::T_EOF, '', $this->line + 1);
        //var_dump($tokens);
        return $tokens;
    }
    
    private function isJSXStart(array $tokens, int $i): bool
    {
        $next = $i + 1;
        return isset($tokens[$next]) && 
               $tokens[$next]->id === self::T_STRING &&
               ctype_alpha($tokens[$next]->text[0]);
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