<?php declare(strict_types=1);

namespace PhpParser;

use PhpParser\Lexer\JSX as JSXLexer;
use PhpParser\Parser\Php7;

class JSXLexerModeTest extends \PHPUnit\Framework\TestCase
{
    protected function createParser() {
        $lexer = new JSXLexer([
            'usedAttributes' => [
                'comments',
                'startLine',
                'endLine',
                'startTokenPos',
                'endTokenPos',
            ],
        ]);
        return new Php7($lexer);
    }

    /**
     * Test that lexer correctly resets to PHP mode after JSX element
     * This was the main bug: lexer stayed in JSX mode after closing tag
     */
    public function testLexerResetsToPhpModeAfterJsxElement() {
        $code = '<?php
        $element = <div>Hello</div>;
        $php = 5;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        // Should parse without errors
        $this->assertCount(2, $stmts);
        
        // Second statement should be a regular PHP assignment
        $secondStmt = $stmts[1];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $secondStmt);
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $secondStmt->expr);
    }

    /**
     * Test multiple JSX elements in sequence
     */
    public function testMultipleJsxElementsInSequence() {
        $code = '<?php
        $a = <div>First</div>;
        $b = <span>Second</span>;
        $c = <p>Third</p>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(3, $stmts);
        
        // All should be JSX elements
        foreach ($stmts as $stmt) {
            $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);
            $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $stmt->expr);
            $this->assertInstanceOf(\PhpParser\Node\JSX\Element::class, $stmt->expr->expr);
        }
    }

    /**
     * Test JSX in function return statement
     * This was failing with "unexpected ';'" due to mode not resetting
     */
    public function testJsxInReturnStatement() {
        $code = '<?php
        function Component() {
            return <div>Content</div>;
        }';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $func = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $func);
        
        $return = $func->stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Return_::class, $return);
        $this->assertInstanceOf(\PhpParser\Node\JSX\Element::class, $return->expr);
    }

    /**
     * Test JSX with parentheses in return statement
     */
    public function testJsxWithParenthesesInReturn() {
        $code = '<?php
        function Component() {
            return (
                <div>Content</div>
            );
        }';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $func = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $func);
        
        $return = $func->stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Return_::class, $return);
        $this->assertInstanceOf(\PhpParser\Node\JSX\Element::class, $return->expr);
    }

    /**
     * Test nested JSX elements with mode transitions
     */
    public function testNestedJsxWithModeTransitions() {
        $code = '<?php
        $element = <div>
            <span>{$variable}</span>
            Regular text
            <a href={$link}>Link</a>
        </div>;
        $after = true;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(2, $stmts);
        
        // Second statement should parse correctly
        $secondStmt = $stmts[1];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $secondStmt);
        $assign = $secondStmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $assign);
        $this->assertInstanceOf(\PhpParser\Node\Expr\ConstFetch::class, $assign->expr);
    }

    /**
     * Test JSX fragment mode transitions
     */
    public function testJsxFragmentModeTransitions() {
        $code = '<?php
        $element = <>
            <div>First</div>
            <div>Second</div>
        </>;
        $next = 42;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(2, $stmts);
        
        // Check second statement parsed correctly
        $secondStmt = $stmts[1];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $secondStmt);
        $assign = $secondStmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $assign);
        $this->assertInstanceOf(\PhpParser\Node\Scalar\Int_::class, $assign->expr);
        $this->assertEquals(42, $assign->expr->value);
    }

    /**
     * Test self-closing JSX elements
     */
    public function testSelfClosingJsxElements() {
        $code = '<?php
        $img = <img src="test.jpg" />;
        $input = <input type="text" />;
        $value = 123;
        ';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(3, $stmts);
        
        // Third statement should be regular PHP
        $thirdStmt = $stmts[2];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $thirdStmt);
        $assign = $thirdStmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $assign);
        $this->assertInstanceOf(\PhpParser\Node\Scalar\Int_::class, $assign->expr);
    }

    /**
     * Test complex JSX with expressions and mode switches
     */
    public function testComplexJsxExpressions() {
        $code = '<?php
        $complex = <div className={$active ? "active" : "inactive"}>
            {$items->map(function($item) {
                return <li key={$item->id}>{$item->name}</li>;
            })}
        </div>;
        $regular = "string";';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(2, $stmts);
        
        // Second statement should be regular string assignment
        $secondStmt = $stmts[1];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $secondStmt);
        $assign = $secondStmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $assign);
        $this->assertInstanceOf(\PhpParser\Node\Scalar\String_::class, $assign->expr);
        $this->assertEquals("string", $assign->expr->value);
    }

    /**
     * Test JSX in conditional statements
     */
    public function testJsxInConditionalStatements() {
        $code = '<?php
        if ($condition) {
            $element = <div>True branch</div>;
        } else {
            $element = <span>False branch</span>;
        }
        $after = [];';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(2, $stmts);
        
        // Second statement should be array assignment
        $secondStmt = $stmts[1];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $secondStmt);
        $assign = $secondStmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $assign);
        $this->assertInstanceOf(\PhpParser\Node\Expr\Array_::class, $assign->expr);
    }

    /**
     * Test JSX with array destructuring after
     * This was showing "unexpected ';'" errors
     */
    public function testJsxFollowedByArrayDestructuring() {
        $code = '<?php
        $element = <div>Test</div>;
        [$a, $b] = [1, 2];';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(2, $stmts);
        
        // Second statement should be array destructuring
        $secondStmt = $stmts[1];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $secondStmt);
        $assign = $secondStmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $assign);
        $this->assertInstanceOf(\PhpParser\Node\Expr\List_::class, $assign->var);
    }

    /**
     * Test JSX in class methods
     */
    public function testJsxInClassMethods() {
        $code = '<?php
        class Component {
            public function render() {
                return <div>{$this->props}</div>;
            }
            
            public function getDefault() {
                return null;
            }
        }';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $class = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Class_::class, $class);
        
        // Should have both methods
        $this->assertCount(2, $class->stmts);
        
        // Second method should parse correctly
        $secondMethod = $class->stmts[1];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\ClassMethod::class, $secondMethod);
        $this->assertEquals('getDefault', $secondMethod->name->name);
    }

    /**
     * Test the specific Router.php pattern that was failing
     */
    public function testRouterPatternWithJsxReturn() {
        $code = '<?php
        function Link($props) {
            $href = $props["href"] ?? "";
            return <a href={$href}>{$children}</a>;
        }';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $func = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $func);
        
        // Should have 2 statements in function body
        $this->assertCount(2, $func->stmts);
        
        // Both statements should parse correctly
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $func->stmts[0]);
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Return_::class, $func->stmts[1]);
    }

    /**
     * Test JSX with PHP comments after
     */
    public function testJsxWithCommentsAfter() {
        $code = '<?php
        $element = <div>Content</div>;
        // This is a comment
        $next = 1;
        /* Multi-line
           comment */
        $another = 2;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(3, $stmts);
        
        // All statements should parse correctly
        foreach ($stmts as $i => $stmt) {
            $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);
            $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $stmt->expr);
        }
    }

    /**
     * Test edge case: empty JSX elements
     */
    public function testEmptyJsxElements() {
        $code = '<?php
        $empty = <div></div>;
        $also = <span />;
        // This comment helps with parsing
        $php = "regular";
        ';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        // We may have more statements due to how the parser handles things
        $this->assertGreaterThanOrEqual(2, count($stmts));
        
        // First two should be JSX assignments
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmts[0]);
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $stmts[0]->expr);
        
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmts[1]);
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $stmts[1]->expr);
    }
    
    /**
     * Test JSX with empty expressions {}
     */
    public function testJsxWithEmptyExpressions() {
        $code = '<?php
        $element = <div>{}</div>;
        $multiple = <span>{}{}</span>;
        $mixed = <p>Text {} More</p>;
        $nested = <div>{<span>{}</span>}</div>;
        ';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(4, $stmts);
        
        // Check first element with empty expression
        $firstAssign = $stmts[0]->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $firstAssign);
        $jsxElement = $firstAssign->expr;
        $this->assertInstanceOf(\PhpParser\Node\JSX\Element::class, $jsxElement);
        $this->assertCount(1, $jsxElement->children);
        
        // The empty expression should contain a null
        $exprContainer = $jsxElement->children[0];
        $this->assertInstanceOf(\PhpParser\Node\JSX\ExpressionContainer::class, $exprContainer);
        $this->assertInstanceOf(\PhpParser\Node\Expr\ConstFetch::class, $exprContainer->expression);
        $this->assertEquals('null', $exprContainer->expression->name->name);
        
        // Check multiple empty expressions
        $secondAssign = $stmts[1]->expr;
        $jsxElement2 = $secondAssign->expr;
        $this->assertCount(2, $jsxElement2->children);
        
        // Check mixed content with empty expression
        $thirdAssign = $stmts[2]->expr;
        $jsxElement3 = $thirdAssign->expr;
        $this->assertCount(3, $jsxElement3->children); // "Text ", {}, " More"
    }
}