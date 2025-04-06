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
    
    // Token constants
    const T_LT = 60;           // <
    const T_GT = 62;           // >
    const T_EQUAL = 61;        // =
    const T_SLASH = 47;        // /
    const T_CURLY_OPEN = 123;  // {
    const T_CURLY_CLOSE = 125; // }
    
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
            
            // Check for JSX start
            if ($token->id === self::T_LT && $this->isJSXStart($phpTokens, $i)) {
                // Start of JSX element
                $tokens[] = new Token($token->id, $token->text, $token->line);
                
                // Get tag name
                $i++;
                $tagToken = $phpTokens[$i];
                $tokens[] = new Token(T_STRING, $tagToken->text, $tagToken->line);
                
                // Handle attributes if any
                $i++;
                while ($i < $len && $phpTokens[$i]->id !== self::T_GT) {
                    if ($phpTokens[$i]->id === T_STRING) {
                        // Attribute name
                        $tokens[] = new Token(T_STRING, $phpTokens[$i]->text, $phpTokens[$i]->line);
                        $i++;
                        
                        if ($phpTokens[$i]->id === self::T_EQUAL) {
                            $tokens[] = new Token($phpTokens[$i]->id, $phpTokens[$i]->text, $phpTokens[$i]->line);
                            $i++;
                            
                            if ($phpTokens[$i]->id === self::T_CURLY_OPEN) {
                                // JSX expression
                                $tokens[] = new Token($phpTokens[$i]->id, $phpTokens[$i]->text, $phpTokens[$i]->line);
                                $i++;
                                
                                // Get expression content
                                while ($i < $len && $phpTokens[$i]->id !== self::T_CURLY_CLOSE) {
                                    $tokens[] = new Token($phpTokens[$i]->id, $phpTokens[$i]->text, $phpTokens[$i]->line);
                                    $i++;
                                }
                                
                                if ($i < $len) {
                                    $tokens[] = new Token($phpTokens[$i]->id, $phpTokens[$i]->text, $phpTokens[$i]->line);
                                }
                            } else {
                                // String attribute
                                $tokens[] = new Token(T_CONSTANT_ENCAPSED_STRING, $phpTokens[$i]->text, $phpTokens[$i]->line);
                            }
                        }
                    }
                    $i++;
                }
                
                // Closing bracket
                if ($i < $len) {
                    $tokens[] = new Token($phpTokens[$i]->id, $phpTokens[$i]->text, $phpTokens[$i]->line);
                }
                
                // Get content until closing tag
                $i++;
                $content = '';
                while ($i < $len && !($phpTokens[$i]->id === self::T_LT && $phpTokens[$i + 1]->id === self::T_SLASH)) {
                    $content .= $phpTokens[$i]->text;
                    $i++;
                }
                
                if (!empty($content)) {
                    $tokens[] = new Token(T_CONSTANT_ENCAPSED_STRING, $content, $phpTokens[$i]->line);
                }
                
                // Handle closing tag
                if ($i < $len && $phpTokens[$i]->id === self::T_LT) {
                    $tokens[] = new Token($phpTokens[$i]->id, $phpTokens[$i]->text, $phpTokens[$i]->line);
                    $i++;
                    
                    if ($i < $len && $phpTokens[$i]->id === self::T_SLASH) {
                        $tokens[] = new Token($phpTokens[$i]->id, $phpTokens[$i]->text, $phpTokens[$i]->line);
                        $i++;
                        
                        if ($i < $len && $phpTokens[$i]->id === T_STRING) {
                            $tokens[] = new Token(T_STRING, $phpTokens[$i]->text, $phpTokens[$i]->line);
                            $i++;
                            
                            if ($i < $len && $phpTokens[$i]->id === self::T_GT) {
                                $tokens[] = new Token($phpTokens[$i]->id, $phpTokens[$i]->text, $phpTokens[$i]->line);
                            }
                        }
                    }
                }
            } else {
                // Regular PHP token
                $tokens[] = new Token($token->id, $token->text, $token->line);
            }
            
            $i++;
        }
        
        // Add EOF token
        $tokens[] = new Token(0, '', $this->line);
        
        return $tokens;
    }
    
    private function isJSXStart(array $tokens, int $i): bool
    {
        $next = $i + 1;
        return isset($tokens[$next]) && 
               $tokens[$next]->id === T_STRING &&
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

    /**
     * Tokenizes a specific range with the given mode.
     * 
     * @param int $mode The mode to use for tokenization (MODE_PHP, MODE_JSX, MODE_JSX_EXPR)
     * @param int $start The start position in the code
     * @param int $end The end position in the code
     * @return array Array of tokens for the specific range
     */
    public function tokenizeRange(int $mode, int $start, int $end): array
    {
        // Reset state for tokenizing specific range
        $this->position = 0;
        $this->tokens = [];
        $this->jsxDepth = 0;
        $this->inJSXText = false;
        $this->textBuffer = '';
        $this->line = 1;
        $this->closingModeInfo = [];
        
        // Tokenize the specific range with the provided mode
        return $this->_tokenize(false, [$mode, $start, $end]);
    }
} 