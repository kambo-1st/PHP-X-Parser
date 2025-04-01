<?php declare(strict_types=1);

namespace PhpParser;

use PhpParser\Lexer\JSX as JSXLexer;
use PhpParser\Parser\Php7;

class JSXEmptyExpressionTest extends \PHPUnit\Framework\TestCase
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
     * Test single empty expression in JSX
     */
    public function testSingleEmptyExpression() {
        $code = '<?php $x = <div>{}</div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertInstanceOf(\PhpParser\Node\JSX\Element::class, $jsxElement);
        
        // Check that empty {} becomes null
        $exprContainer = $jsxElement->children[0];
        $this->assertInstanceOf(\PhpParser\Node\JSX\ExpressionContainer::class, $exprContainer);
        $this->assertInstanceOf(\PhpParser\Node\Expr\ConstFetch::class, $exprContainer->expression);
        $this->assertEquals('null', $exprContainer->expression->name->name);
    }
    
    /**
     * Test multiple consecutive empty expressions
     */
    public function testMultipleEmptyExpressions() {
        $code = '<?php $x = <div>{}{}{}</div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertCount(3, $jsxElement->children);
        
        // All should be null
        foreach ($jsxElement->children as $child) {
            $this->assertInstanceOf(\PhpParser\Node\JSX\ExpressionContainer::class, $child);
            $this->assertEquals('null', $child->expression->name->name);
        }
    }
    
    /**
     * Test empty expression mixed with text
     */
    public function testEmptyExpressionWithText() {
        $code = '<?php $x = <div>Before {} After</div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertCount(3, $jsxElement->children);
        
        $this->assertInstanceOf(\PhpParser\Node\JSX\Text::class, $jsxElement->children[0]);
        $this->assertEquals('Before ', $jsxElement->children[0]->value);
        
        $this->assertInstanceOf(\PhpParser\Node\JSX\ExpressionContainer::class, $jsxElement->children[1]);
        $this->assertEquals('null', $jsxElement->children[1]->expression->name->name);
        
        $this->assertInstanceOf(\PhpParser\Node\JSX\Text::class, $jsxElement->children[2]);
        $this->assertEquals('After', $jsxElement->children[2]->value);
    }
    
    /**
     * Test empty expression in attributes
     */
    public function testEmptyExpressionInAttribute() {
        $code = '<?php $x = <div title={}>Content</div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertCount(1, $jsxElement->jsxAttributes);
        
        $attr = $jsxElement->jsxAttributes[0];
        $this->assertEquals('title', $attr->name);
        $this->assertInstanceOf(\PhpParser\Node\Expr\ConstFetch::class, $attr->value);
        $this->assertEquals('null', $attr->value->name->name);
    }
    
    /**
     * Test nested empty expressions
     */
    public function testNestedEmptyExpressions() {
        $code = '<?php $x = <div>{<span>{}</span>}</div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $outerDiv = $stmts[0]->expr->expr;
        $this->assertCount(1, $outerDiv->children);
        
        $exprContainer = $outerDiv->children[0];
        $this->assertInstanceOf(\PhpParser\Node\JSX\ExpressionContainer::class, $exprContainer);
        
        $innerSpan = $exprContainer->expression;
        $this->assertInstanceOf(\PhpParser\Node\JSX\Element::class, $innerSpan);
        
        // Check inner empty expression
        $innerExpr = $innerSpan->children[0];
        $this->assertInstanceOf(\PhpParser\Node\JSX\ExpressionContainer::class, $innerExpr);
        $this->assertEquals('null', $innerExpr->expression->name->name);
    }
    
    /**
     * Test empty expression doesn't break ternary operators
     */
    public function testEmptyExpressionDoesntBreakTernary() {
        $code = '<?php $x = <div>{$cond ? "yes" : "no"}</div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $exprContainer = $jsxElement->children[0];
        
        $this->assertInstanceOf(\PhpParser\Node\Expr\Ternary::class, $exprContainer->expression);
    }
    
    /**
     * Test empty expression with spaces
     */
    public function testEmptyExpressionWithSpaces() {
        $code = '<?php $x = <div>{   }</div>;'; // Spaces inside braces
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $exprContainer = $jsxElement->children[0];
        
        // Should be converted to null
        $this->assertEquals('null', $exprContainer->expression->name->name);
    }
    
    /**
     * Test empty expression with newlines
     */
    public function testEmptyExpressionWithNewlines() {
        $code = '<?php $x = <div>{
        
    }</div>;'; // Newlines and spaces
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $exprContainer = $jsxElement->children[0];
        
        // Should be converted to null
        $this->assertEquals('null', $exprContainer->expression->name->name);
    }
}