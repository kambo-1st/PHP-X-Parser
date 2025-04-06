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
        
        echo "DEBUG: Starting tokenization in mode: " . $this->mode . "\n";
        
        // First get PHP tokens
        $phpTokens = PhpToken::tokenize($code);
        
        // Convert to our token format and handle JSX
        $tokens = [];
        $i = 0;
        $len = count($phpTokens);
        
        while ($i < $len) {
            $token = $phpTokens[$i];
            
            echo "DEBUG: Processing token: " . $token->id . " (" . $token->text . ") in mode: " . $this->mode . "\n";
            
            // Skip whitespace tokens
            if ($token->id === T_WHITESPACE) {
                $i++;
                continue;
            }
            
            // Check for JSX start
            if ($token->id === self::T_LT && $this->isJSXStart($phpTokens, $i)) {
                echo "DEBUG: Found JSX start, switching to JSX mode\n";
                $this->mode = self::MODE_JSX;
                // Start of JSX element
                $tokens[] = new Token(self::T_LT, $token->text, $token->line);
                
                // Get tag name
                $i++;
                $tagToken = $phpTokens[$i];
                echo "DEBUG: JSX tag name: " . $tagToken->text . "\n";
                $tokens[] = new Token(self::T_STRING, $tagToken->text, $tagToken->line);
                
                // Handle attributes if any
                $i++;
                while ($i < $len && $phpTokens[$i]->id !== self::T_GT) {
                    // Skip whitespace between attributes
                    if ($phpTokens[$i]->id === T_WHITESPACE) {
                        $i++;
                        continue;
                    }
                    
                    echo "DEBUG: Processing attribute token: " . $phpTokens[$i]->id . " (" . $phpTokens[$i]->text . ")\n";
                    
                    // Check for self-closing tag
                    if ($phpTokens[$i]->id === self::T_SLASH && $i + 1 < $len && $phpTokens[$i + 1]->id === self::T_GT) {
                        $tokens[] = new Token(self::T_SLASH, $phpTokens[$i]->text, $phpTokens[$i]->line);
                        $i++;
                        break;
                    }
                    
                    // Handle spread attributes (...$props)
                    if ($phpTokens[$i]->id === T_ELLIPSIS) {
                        echo "DEBUG: Found spread attribute\n";
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
                        continue;
                    }
                    
                    // Handle attribute name (could be a PHP keyword like 'class')
                    if ($phpTokens[$i]->id === self::T_STRING || $phpTokens[$i]->id === T_CLASS) {
                        echo "DEBUG: Processing attribute name: " . $phpTokens[$i]->text . "\n";
                        // Start of attribute
                        $tokens[] = new Token(self::T_STRING, $phpTokens[$i]->text, $phpTokens[$i]->line);
                        $i++;
                        
                        // Skip whitespace after attribute name
                        if ($i < $len && $phpTokens[$i]->id === T_WHITESPACE) {
                            $i++;
                        }
                        
                        // Check for closing tag after attribute
                        if ($i < $len && 
                            (($phpTokens[$i]->id === self::T_SLASH && $i + 1 < $len && $phpTokens[$i + 1]->id === self::T_GT) ||
                             $phpTokens[$i]->id === self::T_GT)) {
                            // This was a boolean attribute, emit null value
                            if ($phpTokens[$i]->id === self::T_SLASH) {
                                $tokens[] = new Token(self::T_SLASH, $phpTokens[$i]->text, $phpTokens[$i]->line);
                                $i++;
                            }
                            break;
                        }
                        
                        if ($i < $len && $phpTokens[$i]->id === self::T_EQUAL) {
                            $tokens[] = new Token(self::T_EQUAL, $phpTokens[$i]->text, $phpTokens[$i]->line);
                            $i++;
                            
                            // Skip whitespace after equals
                            if ($i < $len && $phpTokens[$i]->id === T_WHITESPACE) {
                                $i++;
                            }
                            
                            if ($i < $len && $phpTokens[$i]->id === self::T_CURLY_OPEN) {
                                echo "DEBUG: Found JSX expression attribute, switching to JSX_EXPR mode\n";
                                $this->mode = self::MODE_JSX_EXPR;
                                // JSX expression attribute
                                $tokens[] = new Token(self::T_CURLY_OPEN, $phpTokens[$i]->text, $phpTokens[$i]->line);
                                $i++;
                                
                                // Get expression content
                                $exprDepth = 0;
                                while ($i < $len && ($phpTokens[$i]->id !== self::T_CURLY_CLOSE || $exprDepth > 0)) {
                                    echo "DEBUG: Processing expression token: " . $phpTokens[$i]->id . " (" . $phpTokens[$i]->text . ")\n";
                                    
                                    // Track expression depth for nested expressions
                                    if ($phpTokens[$i]->id === self::T_CURLY_OPEN) {
                                        $exprDepth++;
                                    } else if ($phpTokens[$i]->id === self::T_CURLY_CLOSE) {
                                        $exprDepth--;
                                    }
                                    
                                    // If we find a JSX element in the expression, stay in JSX_EXPR mode
                                    if ($phpTokens[$i]->id === self::T_LT && $this->isJSXStart($phpTokens, $i)) {
                                        echo "DEBUG: Found JSX element in expression\n";
                                        $tokens[] = $phpTokens[$i];
                                        $i++;
                                        
                                        // Get tag name
                                        $tagToken = $phpTokens[$i];
                                        echo "DEBUG: JSX tag name: " . $tagToken->text . "\n";
                                        $tokens[] = new Token(self::T_STRING, $tagToken->text, $tagToken->line);
                                        $i++;
                                        
                                        // Handle closing bracket
                                        if ($i < $len && $phpTokens[$i]->id === self::T_GT) {
                                            echo "DEBUG: Processing closing bracket\n";
                                            $tokens[] = $phpTokens[$i];
                                            $i++;
                                        }
                                        
                                        // Get content until closing tag
                                        $content = '';
                                        $lastWasSpace = false;
                                        while ($i < $len && !($phpTokens[$i]->id === self::T_LT && $i + 1 < $len && $phpTokens[$i + 1]->id === self::T_SLASH)) {
                                            if ($phpTokens[$i]->id === T_WHITESPACE) {
                                                if (!$lastWasSpace) {
                                                    $content .= ' ';
                                                    $lastWasSpace = true;
                                                }
                                            } else {
                                                $content .= $phpTokens[$i]->text;
                                                $lastWasSpace = false;
                                            }
                                            $i++;
                                        }
                                        
                                        if (!empty(trim($content))) {
                                            $tokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $content, $phpTokens[$i]->line);
                                        }
                                        
                                        // Handle closing tag
                                        if ($i < $len && $phpTokens[$i]->id === self::T_LT) {
                                            echo "DEBUG: Processing closing tag\n";
                                            $tokens[] = $phpTokens[$i];
                                            $i++;
                                            
                                            if ($i < $len && $phpTokens[$i]->id === self::T_SLASH) {
                                                $tokens[] = $phpTokens[$i];
                                                $i++;
                                                
                                                if ($i < $len && $phpTokens[$i]->id === self::T_STRING) {
                                                    $tokens[] = $phpTokens[$i];
                                                    $i++;
                                                    
                                                    if ($i < $len && $phpTokens[$i]->id === self::T_GT) {
                                                        $tokens[] = $phpTokens[$i];
                                                        $i++;
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        // For ternary operators, stay in JSX_EXPR mode
                                        if ($phpTokens[$i]->id === 63 || $phpTokens[$i]->id === 58) { // ? or :
                                            echo "DEBUG: Processing ternary operator token: " . $phpTokens[$i]->text . "\n";
                                            $tokens[] = $phpTokens[$i];
                                            $i++;
                                            continue;
                                        }
                                        
                                        $tokens[] = $phpTokens[$i];
                                        $i++;
                                    }
                                }
                                
                                if ($i < $len) {
                                    echo "DEBUG: Closing JSX expression, switching back to JSX mode\n";
                                    $this->mode = self::MODE_JSX;
                                    $content = ''; // Clear content buffer when switching back
                                    $tokens[] = $phpTokens[$i];
                                }
                            } else if ($i < $len) {
                                // String attribute - strip quotes
                                $value = $phpTokens[$i]->text;
                                if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
                                    $value = substr($value, 1, -1);
                                }
                                echo "DEBUG: Processing attribute value: " . $value . "\n";
                                $tokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $value, $phpTokens[$i]->line);
                            }
                        }
                    }
                    $i++;
                }
                
                // Closing bracket
                if ($i < $len) {
                    echo "DEBUG: Processing closing bracket\n";
                    $tokens[] = new Token(self::T_GT, $phpTokens[$i]->text, $phpTokens[$i]->line);
                }
                
                // Get content until closing tag
                $i++;
                $content = '';
                $lastWasSpace = false;
                
                echo "DEBUG: Processing JSX content\n";
                
                // Skip content processing if it's a self-closing tag
                if (end($tokens)->id === self::T_SLASH) {
                    // We've already processed the slash, just need to handle the closing >
                    if ($i < $len && $phpTokens[$i]->id === self::T_GT) {
                        $tokens[] = new Token(self::T_GT, $phpTokens[$i]->text, $phpTokens[$i]->line);
                        $i++;
                    }
                } else {
                    while ($i < $len && !($phpTokens[$i]->id === self::T_LT && $i + 1 < $len && $phpTokens[$i + 1]->id === self::T_SLASH)) {
                        // Check for nested JSX element
                        if ($phpTokens[$i]->id === self::T_LT && $i + 1 < $len && $phpTokens[$i + 1]->id === self::T_STRING) {
                            echo "DEBUG: Found nested JSX element in content\n";
                            // Emit any accumulated content if it's not just whitespace
                            if (!empty(trim($content))) {
                                $tokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $content, $phpTokens[$i]->line);
                                $content = '';
                            }
                            
                            // Start new JSX element
                            $tokens[] = new Token(self::T_LT, $phpTokens[$i]->text, $phpTokens[$i]->line);
                            $i++;
                            $tokens[] = new Token(self::T_STRING, $phpTokens[$i]->text, $phpTokens[$i]->line);
                            $i++;
                            
                            // Handle attributes if any
                            while ($i < $len && $phpTokens[$i]->id !== self::T_GT) {
                                if ($phpTokens[$i]->id === T_WHITESPACE) {
                                    $i++;
                                    continue;
                                }
                                
                                if ($phpTokens[$i]->id === self::T_STRING) {
                                    $tokens[] = new Token(self::T_STRING, $phpTokens[$i]->text, $phpTokens[$i]->line);
                                    $i++;
                                    
                                    if ($i < $len && $phpTokens[$i]->id === self::T_EQUAL) {
                                        $tokens[] = new Token(self::T_EQUAL, $phpTokens[$i]->text, $phpTokens[$i]->line);
                                        $i++;
                                        
                                        if ($i < $len) {
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
                            
                            if ($i < $len) {
                                $tokens[] = new Token(self::T_GT, $phpTokens[$i]->text, $phpTokens[$i]->line);
                            }
                        } else if ($phpTokens[$i]->id === T_WHITESPACE) {
                            if (!$lastWasSpace) {
                                $content .= ' ';
                                $lastWasSpace = true;
                            }
                        } else if ($phpTokens[$i]->id === self::T_CURLY_OPEN) {
                            echo "DEBUG: Found JSX expression in content, switching to JSX_EXPR mode\n";
                            $this->mode = self::MODE_JSX_EXPR;
                            $tokens[] = $phpTokens[$i];
                            $i++;
                            
                            // Get expression content
                            $exprDepth = 0;
                            while ($i < $len && ($phpTokens[$i]->id !== self::T_CURLY_CLOSE || $exprDepth > 0)) {
                                $token = $phpTokens[$i];
                                echo "DEBUG: Processing expression token: " . $token->id . " (" . $token->text . ")\n";
                                
                                // Track expression depth for nested expressions
                                if ($token->id === self::T_CURLY_OPEN) {
                                    $exprDepth++;
                                } else if ($token->id === self::T_CURLY_CLOSE) {
                                    $exprDepth--;
                                }
                                
                                // If we find a JSX element in the expression, stay in JSX_EXPR mode
                                if ($token->id === self::T_LT && $this->isJSXStart($phpTokens, $i)) {
                                    echo "DEBUG: Found JSX element in expression\n";
                                    $tokens[] = $token;
                                    $i++;
                                    
                                    // Get tag name
                                    $tagToken = $phpTokens[$i];
                                    echo "DEBUG: JSX tag name: " . $tagToken->text . "\n";
                                    $tokens[] = new Token(self::T_STRING, $tagToken->text, $tagToken->line);
                                    $i++;
                                    
                                    // Handle closing bracket
                                    if ($i < $len && $phpTokens[$i]->id === self::T_GT) {
                                        echo "DEBUG: Processing closing bracket\n";
                                        $tokens[] = $phpTokens[$i];
                                        $i++;
                                    }
                                    
                                    // Get content until closing tag
                                    $content = '';
                                    $lastWasSpace = false;
                                    while ($i < $len && !($phpTokens[$i]->id === self::T_LT && $i + 1 < $len && $phpTokens[$i + 1]->id === self::T_SLASH)) {
                                        if ($phpTokens[$i]->id === T_WHITESPACE) {
                                            if (!$lastWasSpace) {
                                                $content .= ' ';
                                                $lastWasSpace = true;
                                            }
                                        } else {
                                            $content .= $phpTokens[$i]->text;
                                            $lastWasSpace = false;
                                        }
                                        $i++;
                                    }
                                    
                                    if (!empty(trim($content))) {
                                        $tokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $content, $phpTokens[$i]->line);
                                    }
                                    
                                    // Handle closing tag
                                    if ($i < $len && $phpTokens[$i]->id === self::T_LT) {
                                        echo "DEBUG: Processing closing tag\n";
                                        $tokens[] = $phpTokens[$i];
                                        $i++;
                                        
                                        if ($i < $len && $phpTokens[$i]->id === self::T_SLASH) {
                                            $tokens[] = $phpTokens[$i];
                                            $i++;
                                            
                                            if ($i < $len && $phpTokens[$i]->id === self::T_STRING) {
                                                $tokens[] = $phpTokens[$i];
                                                $i++;
                                                
                                                if ($i < $len && $phpTokens[$i]->id === self::T_GT) {
                                                    $tokens[] = $phpTokens[$i];
                                                    $i++;
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    // For ternary operators, stay in JSX_EXPR mode
                                    if ($token->id === 63 || $token->id === 58) { // ? or :
                                        echo "DEBUG: Processing ternary operator token: " . $token->text . "\n";
                                        $tokens[] = $token;
                                        $i++;
                                        continue;
                                    }
                                    
                                    $tokens[] = $token;
                                    $i++;
                                }
                            }
                            
                            if ($i < $len) {
                                echo "DEBUG: Closing JSX expression, switching back to JSX mode\n";
                                $this->mode = self::MODE_JSX;
                                $content = ''; // Clear content buffer when switching back
                                $tokens[] = $phpTokens[$i];
                            }
                        } else if ($phpTokens[$i]->id === self::T_SEMICOLON) {
                            // Handle semicolon separately
                            if (!empty(trim($content))) {
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
                
                // Add any remaining content, but only if it's not just whitespace and we're not in JSX expression mode
                if (!empty(trim($content))) {
                    $tokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $content, $phpTokens[$i - 1]->line);
                }
                
                // Handle closing tag
                if ($i < $len && $phpTokens[$i]->id === self::T_LT) {
                    echo "DEBUG: Processing closing tag\n";
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