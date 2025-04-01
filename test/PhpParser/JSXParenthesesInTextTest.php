<?php declare(strict_types=1);

namespace PhpParser;

use PhpParser\Lexer\JSX as JSXLexer;
use PhpParser\Parser\Php7;

class JSXParenthesesInTextTest extends \PHPUnit\Framework\TestCase
{
    protected function createParser() {
        $lexer = new JSXLexer();
        return new Php7($lexer);
    }

    /**
     * Test parentheses in JSX text content
     */
    public function testParenthesesInPlainText() {
        $code = '<?php $x = <span>Name (count)</span>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertEquals('span', $jsxElement->name);
        $this->assertCount(1, $jsxElement->children);
        
        $text = $jsxElement->children[0];
        $this->assertInstanceOf(Node\JSX\Text::class, $text);
        $this->assertEquals('Name (count)', $text->value);
    }

    /**
     * Test parentheses after JSX expressions
     */
    public function testParenthesesAfterExpression() {
        $code = '<?php $x = <span>{$name} ({$count})</span>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $jsxElement = $stmts[0]->expr->expr;
        
        // Should have 4 children: expr, text " (", expr, text ")"
        $this->assertCount(4, $jsxElement->children);
        
        // First: expression {$name}
        $this->assertInstanceOf(Node\JSX\ExpressionContainer::class, $jsxElement->children[0]);
        
        // Second: text " ("
        $this->assertInstanceOf(Node\JSX\Text::class, $jsxElement->children[1]);
        $this->assertEquals(' (', $jsxElement->children[1]->value);
        
        // Third: expression {$count}
        $this->assertInstanceOf(Node\JSX\ExpressionContainer::class, $jsxElement->children[2]);
        
        // Fourth: text ")"
        $this->assertInstanceOf(Node\JSX\Text::class, $jsxElement->children[3]);
        $this->assertEquals(')', $jsxElement->children[3]->value);
    }

    /**
     * Test return statement with parentheses still works
     */
    public function testReturnStatementWithParentheses() {
        $code = '<?php function test() { return (<div>Hello</div>); }';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $this->assertInstanceOf(Node\Stmt\Function_::class, $stmts[0]);
        
        $returnStmt = $stmts[0]->stmts[0];
        $this->assertInstanceOf(Node\Stmt\Return_::class, $returnStmt);
        
        $jsxElement = $returnStmt->expr;
        $this->assertInstanceOf(Node\JSX\Element::class, $jsxElement);
        $this->assertEquals('div', $jsxElement->name);
    }

    /**
     * Test multiple parentheses patterns
     */
    public function testVariousParenthesesPatterns() {
        $patterns = [
            '<p>Math: (x + y)</p>',
            '<code>function()</code>',
            '<div>List (1) (2) (3)</div>',
            '<span>Complex (with (nested) parens)</span>',
            '<text>Mixed {$var} (text) patterns</text>',
        ];
        
        $parser = $this->createParser();
        
        foreach ($patterns as $pattern) {
            $code = "<?php \$x = $pattern;";
            try {
                $stmts = $parser->parse($code);
                $this->assertCount(1, $stmts, "Pattern should parse: $pattern");
            } catch (\Exception $e) {
                $this->fail("Pattern failed to parse: $pattern - " . $e->getMessage());
            }
        }
    }

    /**
     * Test parentheses don't break JSX in complex structures
     */
    public function testComplexStructureWithParentheses() {
        $code = '<?php 
        $element = (
            <div className="container">
                <h1>{$title} (Beta)</h1>
                <p>Items ({$count}):</p>
                {$items->map(function($item) {
                    return <li>Item ({$item->id})</li>;
                })}
            </div>
        );';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        // If it parses without exception, the fix is working
        $this->assertTrue(true);
    }
}