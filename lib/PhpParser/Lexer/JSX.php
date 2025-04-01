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
        
        // echo "DEBUG: Starting tokenization in mode: " . $this->mode . "\n";
        
        // First get PHP tokens
        $phpTokens = PhpToken::tokenize($code);
        
        // Convert to our token format and handle JSX
        $tokens = [];
        $i = 0;
        $len = count($phpTokens);
        $maxIterations = 10000; // Prevent infinite loops
        $iterationCount = 0;
        
        while ($i < $len && $iterationCount < $maxIterations) {
            $iterationCount++;
            $token = $phpTokens[$i];
            
            // echo "DEBUG: Processing token: " . $token->id . " (" . $token->text . ") in mode: " . $this->mode . "\n";
            
            // Skip whitespace tokens
            if ($token->id === T_WHITESPACE) {
                $i++;
                continue;
            }
            
            // Check for JSX start
            if ($token->id === self::T_LT && $this->isJSXStart($phpTokens, $i)) {
                $this->mode = self::MODE_JSX;
                $result = $this->processJSXElement($phpTokens, $i);
                $tokens = array_merge($tokens, $result['tokens']);
                $i = $result['position'];
                // Reset to PHP mode after processing JSX element
                $this->mode = self::MODE_PHP;
                continue;
            }
            
            // Check for JSX expression
            if ($token->id === self::T_CURLY_OPEN && $this->mode === self::MODE_JSX) {
                $result = $this->processJSXExpression($phpTokens, $i);
                $tokens = array_merge($tokens, $result['tokens']);
                $i = $result['position'];
                continue;
            }
            
            // Regular PHP token
            $tokenId = $token->id;
            // Map PHP token IDs to our custom IDs if needed
            if ($tokenId === T_OPEN_TAG) {
                $tokenId = self::T_OPEN_TAG;
                $this->mode = self::MODE_PHP;
            } elseif ($tokenId === T_VARIABLE) {
                $tokenId = self::T_VARIABLE;
            } elseif ($tokenId === T_STRING) {
                $tokenId = self::T_STRING;
            }
            
            $tokens[] = new Token($tokenId, $token->text, $token->line);
            $i++;
        }
        
        if ($iterationCount >= $maxIterations) {
            throw new \RuntimeException("Maximum iteration count reached in tokenization");
        }
        
        // Add EOF token with correct line number
        $tokens[] = new Token(self::T_EOF, '', $this->line + 1);
        
        // echo "DEBUG: Tokens after processing\n";
        // var_dump($tokens);
        
        return $tokens;
    }

    private function processJSXElement(array $tokens, int $i): array
    {
        $resultTokens = [];
        $len = count($tokens);
        
        // Start of JSX element
        $resultTokens[] = new Token(self::T_LT, $tokens[$i]->text, $tokens[$i]->line);
        $i++;
        
        // Get tag name
        $tagToken = $tokens[$i];
        // echo "DEBUG: JSX tag name: " . $tagToken->text . "\n";
        
        // Handle component names with dots (e.g. Some.Component)
        $componentName = $tagToken->text;
        while ($i + 1 < $len && $tokens[$i + 1]->id === 46) { // 46 is '.'
            $i += 2; // Skip the dot and get the next part
            if ($i < $len) {
                $componentName .= '.' . $tokens[$i]->text;
            }
        }
        
        $resultTokens[] = new Token(self::T_STRING, $componentName, $tagToken->line);
        
        // Process attributes
        $i++;
        $attributeResult = $this->processJSXAttributes($tokens, $i);
        $resultTokens = array_merge($resultTokens, $attributeResult['tokens']);
        $i = $attributeResult['position'];
        
        // Check if it's a self-closing tag (already processed in attributes)
        // If the last token is '>', and the one before is '/', it's self-closing
        $tokenCount = count($resultTokens);
        if ($tokenCount >= 2 && 
            $resultTokens[$tokenCount - 1]->id === self::T_GT && 
            $resultTokens[$tokenCount - 2]->id === self::T_SLASH) {
            return ['tokens' => $resultTokens, 'position' => $i];
        }
        
        // Process content if not self-closing
        if ($i < $len && $tokens[$i]->id === self::T_GT) {
            $resultTokens[] = new Token(self::T_GT, $tokens[$i]->text, $tokens[$i]->line);
            $i++;
            
            $contentResult = $this->processJSXContent($tokens, $i);
            $resultTokens = array_merge($resultTokens, $contentResult['tokens']);
            $i = $contentResult['position'];
        }
        
        return ['tokens' => $resultTokens, 'position' => $i];
    }

    private function processJSXAttributes(array $tokens, int $i): array
    {
        $resultTokens = [];
        $len = count($tokens);
        
        while ($i < $len && $tokens[$i]->id !== self::T_GT) {
            // Skip whitespace between attributes
            if ($tokens[$i]->id === T_WHITESPACE) {
                $i++;
                continue;
            }
            
            // echo "DEBUG: Processing attribute token: " . $tokens[$i]->id . " (" . $tokens[$i]->text . ")\n";
            
            // Check for self-closing tag
            if ($tokens[$i]->id === self::T_SLASH && $i + 1 < $len && $tokens[$i + 1]->id === self::T_GT) {
                $resultTokens[] = new Token(self::T_SLASH, $tokens[$i]->text, $tokens[$i]->line);
                $i++;
                $resultTokens[] = new Token(self::T_GT, $tokens[$i]->text, $tokens[$i]->line);
                $i++;
                break;
            }
            
            // Handle spread attributes {...$props}
            if ($tokens[$i]->id === self::T_CURLY_OPEN) {
                $i++;
                // Check for spread operator
                if ($i < $len && $tokens[$i]->text === '...') {
                    $resultTokens[] = new Token(self::T_CURLY_OPEN, '{', $tokens[$i]->line);
                    $resultTokens[] = new Token(T_ELLIPSIS, '...', $tokens[$i]->line);
                    $i++;
                    
                    // Process the variable or expression after spread
                    while ($i < $len && $tokens[$i]->id !== self::T_CURLY_CLOSE) {
                        $token = $tokens[$i];
                        if ($token->id === T_WHITESPACE) {
                            $i++;
                            continue;
                        }
                        $resultTokens[] = new Token($token->id, $token->text, $token->line);
                        $i++;
                    }
                    
                    if ($i < $len && $tokens[$i]->id === self::T_CURLY_CLOSE) {
                        $resultTokens[] = new Token(self::T_CURLY_CLOSE, '}', $tokens[$i]->line);
                        $i++;
                    }
                    continue;
                } else {
                    // Regular JSX expression
                    $i--;  // Move back to { for processJSXExpression
                    $exprResult = $this->processJSXExpression($tokens, $i);
                    $resultTokens = array_merge($resultTokens, $exprResult['tokens']);
                    $i = $exprResult['position'];
                    continue;
                }
            }
            
            // Check for invalid attribute names starting with numbers
            if ($tokens[$i]->id === T_LNUMBER && $i + 1 < $len && $tokens[$i + 1]->id === self::T_STRING) {
                throw new \PhpParser\Error("Invalid attribute name '{$tokens[$i]->text}{$tokens[$i + 1]->text}': attribute names cannot start with a number");
            }
            
            // Handle regular attribute name
            if ($tokens[$i]->id === self::T_STRING || $tokens[$i]->id === T_CLASS) {
                $attributeResult = $this->processJSXAttribute($tokens, $i);
                $resultTokens = array_merge($resultTokens, $attributeResult['tokens']);
                $i = $attributeResult['position'];
                continue;
            }
            
            // Skip any other tokens
            $i++;
        }
        
        return ['tokens' => $resultTokens, 'position' => $i];
    }

    private function processJSXAttribute(array $tokens, int $i): array
    {
        $resultTokens = [];
        $len = count($tokens);
        
        $attributeName = $tokens[$i]->text;
        $i++;
        
        // Check for hyphenated attribute names
        while ($i < $len && $tokens[$i]->id === 45) { // 45 is '-'
            $i++;
            if ($i < $len && $tokens[$i]->id === self::T_STRING) {
                $attributeName .= '-' . $tokens[$i]->text;
                $i++;
            }
        }
        
        $resultTokens[] = new Token(self::T_STRING, $attributeName, $tokens[$i]->line);
        
        // Skip whitespace after attribute name
        if ($i < $len && $tokens[$i]->id === T_WHITESPACE) {
            $i++;
        }
        
        // Check for closing tag after attribute
        if ($i < $len && 
            (($tokens[$i]->id === self::T_SLASH && $i + 1 < $len && $tokens[$i + 1]->id === self::T_GT) ||
             $tokens[$i]->id === self::T_GT)) {
            // Don't process the closing tokens here - let processJSXAttributes handle them
            // Just return the position before the closing tokens
            return ['tokens' => $resultTokens, 'position' => $i];
        }
        
        if ($i < $len && $tokens[$i]->id === self::T_EQUAL) {
            $resultTokens[] = new Token(self::T_EQUAL, $tokens[$i]->text, $tokens[$i]->line);
            $i++;
            
            // Skip whitespace after equals
            if ($i < $len && $tokens[$i]->id === T_WHITESPACE) {
                $i++;
            }
            
            if ($i < $len && $tokens[$i]->id === self::T_CURLY_OPEN) {
                $exprResult = $this->processJSXExpression($tokens, $i);
                $resultTokens = array_merge($resultTokens, $exprResult['tokens']);
                $i = $exprResult['position'];
            } else if ($i < $len) {
                $value = $tokens[$i]->text;
                if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
                    $value = substr($value, 1, -1);
                }
                // echo "DEBUG: Processing attribute value: " . $value . "\n";
                $resultTokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $value, $tokens[$i]->line);
                $i++;
            }
        }
        
        return ['tokens' => $resultTokens, 'position' => $i];
    }

    private function processJSXExpression(array $tokens, int $i): array
    {
        $resultTokens = [];
        $len = count($tokens);
        
        // echo "DEBUG: Found JSX expression attribute, switching to JSX_EXPR mode\n";
        $this->mode = self::MODE_JSX_EXPR;
        
        $resultTokens[] = new Token(self::T_CURLY_OPEN, $tokens[$i]->text, $tokens[$i]->line);
        $i++;
        
        $exprDepth = 0;
        $maxIterations = 1000; // Prevent infinite loops
        $iterationCount = 0;
        
        // Check if this is an empty expression {} or { } (with only whitespace)
        $tempI = $i;
        $hasOnlyWhitespace = true;
        while ($tempI < $len && $tokens[$tempI]->id !== self::T_CURLY_CLOSE) {
            if ($tokens[$tempI]->id !== T_WHITESPACE) {
                $hasOnlyWhitespace = false;
                break;
            }
            $tempI++;
        }
        
        if ($hasOnlyWhitespace && $tempI < $len && $tokens[$tempI]->id === self::T_CURLY_CLOSE) {
            // Insert a null token for empty expressions
            $resultTokens[] = new Token(T_STRING, 'null', $tokens[$i]->line);
            // Skip any whitespace tokens
            while ($i < $len && $tokens[$i]->id === T_WHITESPACE) {
                $i++;
            }
            // Now $i should be at the closing brace, let the normal flow handle it
        }
        
        while ($i < $len && $iterationCount < $maxIterations) {
            $iterationCount++;
            $token = $tokens[$i];
            // echo "DEBUG: Processing expression token: " . $token->id . " (" . $token->text . ")\n";
            
            // Check for closing brace at the right depth
            if ($token->id === self::T_CURLY_CLOSE && $exprDepth === 0) {
                break;
            }

            if($token->id === T_COMMENT){
                $resultTokens[] = new Token(self::T_SLASH, '/', $token->line);  
                $resultTokens[] = new Token(ord('*'), '*', $token->line);
                $commentContent = substr($token->text, 2, -2); // Remove /* and */
                $resultTokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $commentContent, $token->line);
                $resultTokens[] = new Token(ord('*'), '*', $token->line);
                $resultTokens[] = new Token(self::T_SLASH, '/', $token->line);
            }

            // Track expression depth for nested expressions
            if ($token->id === self::T_CURLY_OPEN) {
                $exprDepth++;
                $resultTokens[] = new Token(self::T_CURLY_OPEN, $token->text, $token->line);
                $i++;
                continue;
            } else if ($token->id === self::T_CURLY_CLOSE) {
                $exprDepth--;
                $resultTokens[] = new Token(self::T_CURLY_CLOSE, $token->text, $token->line);
                $i++;
                continue;
            }
            
            // If we find a JSX element in the expression, process it recursively
            if ($token->id === self::T_LT && $this->isJSXStart($tokens, $i)) {
                $elementResult = $this->processJSXElement($tokens, $i);
                $resultTokens = array_merge($resultTokens, $elementResult['tokens']);
                $i = $elementResult['position'];
                continue;
            }
            
            // For ternary operators, stay in JSX_EXPR mode
            if ($token->id === 63 || $token->id === 58) { // ? or :
                // echo "DEBUG: Processing ternary operator token: " . $token->text . "\n";
                $resultTokens[] = new Token($token->id, $token->text, $token->line);
                $i++;
                continue;
            }
            
            // Handle PHP variables and other tokens
            if ($token instanceof PhpToken) {
                $resultTokens[] = new Token($token->id, $token->text, $token->line);
            } else {
                $resultTokens[] = new Token($token->id, $token->text, $token->line);
            }
            $i++;
        }
        
        if ($iterationCount >= $maxIterations) {
            throw new \RuntimeException("Maximum iteration count reached in JSX expression processing");
        }
        
        if ($i < $len) {
            // echo "DEBUG: Closing JSX expression, switching back to JSX mode\n";
            $this->mode = self::MODE_JSX;
            $resultTokens[] = new Token(self::T_CURLY_CLOSE, $tokens[$i]->text, $tokens[$i]->line);
            $i++;
        }
        
        return ['tokens' => $resultTokens, 'position' => $i];
    }

    private function processJSXContent(array $tokens, int $i): array
    {
        $resultTokens = [];
        $len = count($tokens);
        $content = '';
        $lastWasSpace = false;
        
        // echo "DEBUG: Processing JSX content\n";
        
        while ($i < $len) {
            $token = $tokens[$i];
            
            // Check for closing tag
            if ($token->id === self::T_LT && $i + 1 < $len && $tokens[$i + 1]->id === self::T_SLASH) {
                break;
            }
            
            // Check for nested JSX element
            if ($token->id === self::T_LT && $i + 1 < $len && $tokens[$i + 1]->id === self::T_STRING) {
                if (!empty(trim($content))) {
                    $resultTokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $content, $token->line);
                    $content = '';
                }
                
                $elementResult = $this->processJSXElement($tokens, $i);
                $resultTokens = array_merge($resultTokens, $elementResult['tokens']);
                $i = $elementResult['position'];
                continue;
            }
            
            // Check for JSX expression
            if ($token->id === self::T_CURLY_OPEN) {
                if (!empty(trim($content))) {
                    $resultTokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $content, $token->line);
                    $content = '';
                }
                
                $exprResult = $this->processJSXExpression($tokens, $i);
                $resultTokens = array_merge($resultTokens, $exprResult['tokens']);
                $i = $exprResult['position'];
                continue;
            }
            
            // Handle whitespace
            if ($token->id === T_WHITESPACE) {
                if (!$lastWasSpace) {
                    $content .= ' ';
                    $lastWasSpace = true;
                }
                $i++;
                continue;
            }
            
            // Handle semicolon
            if ($token->id === self::T_SEMICOLON) {
                if (!empty(trim($content))) {
                    $resultTokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $content, $token->line);
                    $content = '';
                }
                $resultTokens[] = new Token(self::T_SEMICOLON, $token->text, $token->line);
                $lastWasSpace = false;
                $i++;
                continue;
            }
            
            // SOLUTION.md fix: Don't special-case ')' in JSX text content
            // Parentheses are just text characters, not structural tokens
            /*
            if ($i < $len && $tokens[$i]->id === 41) { // 41 is ')'
                // Don't change mode here - let the main tokenize loop handle it
                $resultTokens[] = new Token(41, $tokens[$i]->text, $tokens[$i]->line);
                $i++;
                break;//continue;
            }
            */

            // Regular content
            $content .= $token->text;
            $lastWasSpace = false;
            $i++;
        }
        
        // Add any remaining content
        if (!empty(trim($content))) {
            $resultTokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $content, $tokens[$i - 1]->line);
        }
        
        // Handle closing tag
        if ($i < $len && $tokens[$i]->id === self::T_LT) {
            // echo "DEBUG: Processing closing tag\n";
            $resultTokens[] = new Token(self::T_LT, $tokens[$i]->text, $tokens[$i]->line);
            $i++;
            
            if ($i < $len && $tokens[$i]->id === self::T_SLASH) {
                $resultTokens[] = new Token(self::T_SLASH, $tokens[$i]->text, $tokens[$i]->line);
                $i++;
                
                if ($i < $len && $tokens[$i]->id === self::T_STRING) {
                    // Handle component names with dots (e.g. Context.Provider)
                    $closingTagName = $tokens[$i]->text;
                    $startI = $i;
                    $i++;
                    
                    while ($i + 1 < $len && $tokens[$i]->id === 46) { // 46 is '.'
                        $closingTagName .= '.';
                        $i++;
                        if ($i < $len && $tokens[$i]->id === self::T_STRING) {
                            $closingTagName .= $tokens[$i]->text;
                            $i++;
                        }
                    }
                    
                    $resultTokens[] = new Token(self::T_STRING, $closingTagName, $tokens[$startI]->line);
                    
                    if ($i < $len && $tokens[$i]->id === self::T_GT) {
                        $resultTokens[] = new Token(self::T_GT, $tokens[$i]->text, $tokens[$i]->line);
                        $i++;
                    }
                }
            }
        }
        
        return ['tokens' => $resultTokens, 'position' => $i];
    }

    private function isJSXStart(array $tokens, int $i): bool
    {
        $next = $i + 1;
        if (!isset($tokens[$next])) {
            return false;
        }
        
        // Check for JSX fragment
        if ($tokens[$next]->id === self::T_GT) {
            return true;
        }
        
        // Check for regular JSX element
        if ($tokens[$next]->id === self::T_STRING &&
            ctype_alpha($tokens[$next]->text[0])) {
            return true;
        }
        
        // Check if we're in a return statement or arrow function
        $prev = $i - 1;
        while ($prev >= 0) {
            if ($tokens[$prev]->id === T_RETURN || $tokens[$prev]->id === T_DOUBLE_ARROW) {
                return true;
            }
            if ($tokens[$prev]->id !== T_WHITESPACE) {
                break;
            }
            $prev--;
        }
        
        return false;
    }

    private function isJSXComment(array $tokens, int $i): bool
    {
        if (!isset($tokens[$i])) {
            return false;
        }

        // Check for PHP comment /* ... */
        if ($tokens[$i]->id === 391) {
            return true;
        }
        
        // Check for JSX comment { /* ... */ }
        if ($tokens[$i]->id === self::T_CURLY_OPEN) {
            $next = $i + 1;
            if (!isset($tokens[$next]) || $tokens[$next]->id !== self::T_SLASH) {
                return false;
            }
            return true;
        }
        
        return false;
    }

    private function processJSXComment(array $tokens, int &$i): array
    {
        $commentTokens = [];
        
        // Handle PHP comment /* ... */
        if ($tokens[$i]->id === T_COMMENT) {
            $commentContent = substr($tokens[$i]->text, 2, -2); // Remove /* and */
            $commentTokens[] = new Token(self::T_SLASH, '/', $tokens[$i]->line);
            $commentTokens[] = new Token(ord('*'), '*', $tokens[$i]->line);
            $commentTokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $commentContent, $tokens[$i]->line);
            $commentTokens[] = new Token(ord('*'), '*', $tokens[$i]->line);
            $commentTokens[] = new Token(self::T_SLASH, '/', $tokens[$i]->line);
            $i++;
            return $commentTokens;
        }
        
        // Handle JSX comment { /* ... */ }
        $commentTokens[] = new Token(self::T_CURLY_OPEN, '{', $tokens[$i]->line);
        $i++;
        $commentTokens[] = new Token(self::T_SLASH, '/', $tokens[$i]->line);
        $i++;
        
        // Collect comment content until we find */
        $commentContent = '';
        while ($i < count($tokens)) {
            if ($tokens[$i]->id === self::T_SLASH && 
                isset($tokens[$i + 1]) && 
                $tokens[$i + 1]->id === self::T_CURLY_CLOSE) {
                break;
            }
            $commentContent .= $tokens[$i]->text;
            $i++;
        }
        
        if (!empty($commentContent)) {
            $commentTokens[] = new Token(self::T_CONSTANT_ENCAPSED_STRING, $commentContent, $tokens[$i]->line);
        }
        
        if ($i < count($tokens)) {
            $commentTokens[] = new Token(self::T_SLASH, '/', $tokens[$i]->line);
            $i++;
            $commentTokens[] = new Token(self::T_CURLY_CLOSE, '}', $tokens[$i]->line);
            $i++;
        }
        
        return $commentTokens;
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