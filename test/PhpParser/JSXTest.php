<?php declare(strict_types=1);

namespace PhpParser;

use PhpParser\Node\JSX\Element;
use PhpParser\Node\JSX\Attribute;
use PhpParser\Node\JSX\SpreadAttribute;
use PhpParser\Node\JSX\Text;
use PhpParser\Node\JSX\ExpressionContainer;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\JSXTransformer;

class JSXTest extends \PHPUnit\Framework\TestCase
{
    protected function createParser() {
        $lexer = new Lexer\JSX([
            'usedAttributes' => [
                'comments',
                'startLine',
                'endLine',
                'startTokenPos',
                'endTokenPos',
            ],
        ]);
        return new Parser\Php7($lexer);
    }

    protected function parseAndTransform(string $code) {
        $parser = $this->createParser();
        $stmts = $parser->parse($code);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new JSXTransformer());
        return $traverser->traverse($stmts);
    }

    public function testParseSimpleJSXElement() {
        $stmts = $this->parseAndTransform('<?php
        $element = <div>Hello World</div>;
        ');
        
        $this->assertCount(1, $stmts);
        
        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);
        
        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);
        
        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('div', $jsxElement->name);
        $this->assertEmpty($jsxElement->attributes);
        $this->assertCount(1, $jsxElement->children);
        $this->assertInstanceOf(Text::class, $jsxElement->children[0]);
        $this->assertEquals('Hello World', $jsxElement->children[0]->value);
        $this->assertEquals('div', $jsxElement->closingName);
    }

    public function testParseJSXElementWithAttributes() {
        $stmts = $this->parseAndTransform('<?php
        $element = <div class="container" id={$id}>Hello World</div>;
        ');
        
        $this->assertCount(1, $stmts);
        
        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);
        
        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);
        
        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('div', $jsxElement->name);
        $this->assertCount(2, $jsxElement->attributes);
        
        $this->assertInstanceOf(Attribute::class, $jsxElement->attributes[0]);
        $this->assertEquals('class', $jsxElement->attributes[0]->name);
        $this->assertInstanceOf(String_::class, $jsxElement->attributes[0]->value);
        $this->assertEquals('container', $jsxElement->attributes[0]->value->value);
        
        $this->assertInstanceOf(Attribute::class, $jsxElement->attributes[1]);
        $this->assertEquals('id', $jsxElement->attributes[1]->name);
        $this->assertInstanceOf(Variable::class, $jsxElement->attributes[1]->value);
        $this->assertEquals('id', $jsxElement->attributes[1]->value->name);
        
        $this->assertCount(1, $jsxElement->children);
        $this->assertInstanceOf(Text::class, $jsxElement->children[0]);
        $this->assertEquals('Hello World', $jsxElement->children[0]->value);
        $this->assertEquals('div', $jsxElement->closingName);
    }

    public function testParseJSXElementWithSpreadAttributes() {
        $stmts = $this->parseAndTransform('<?php
        $element = <div {...$props}>Hello World</div>;
        ');
        
        $this->assertCount(1, $stmts);
        
        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);
        
        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);
        
        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('div', $jsxElement->name);
        $this->assertCount(1, $jsxElement->attributes);
        
        $this->assertInstanceOf(SpreadAttribute::class, $jsxElement->attributes[0]);
        $this->assertInstanceOf(Variable::class, $jsxElement->attributes[0]->expression);
        $this->assertEquals('props', $jsxElement->attributes[0]->expression->name);
        
        $this->assertCount(1, $jsxElement->children);
        $this->assertInstanceOf(Text::class, $jsxElement->children[0]);
        $this->assertEquals('Hello World', $jsxElement->children[0]->value);
        $this->assertEquals('div', $jsxElement->closingName);
    }

    public function testParseJSXElementWithExpressions() {
        $stmts = $this->parseAndTransform('<?php
        $element = <div>{$greeting}</div>;
        ');
        
        $this->assertCount(1, $stmts);
        
        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);
        
        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);
        
        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('div', $jsxElement->name);
        $this->assertEmpty($jsxElement->attributes);
        $this->assertCount(1, $jsxElement->children);
        
        $this->assertInstanceOf(ExpressionContainer::class, $jsxElement->children[0]);
        $this->assertInstanceOf(Variable::class, $jsxElement->children[0]->expression);
        $this->assertEquals('greeting', $jsxElement->children[0]->expression->name);
        
        $this->assertEquals('div', $jsxElement->closingName);
    }

    public function testParseSelfClosingJSXElement() {
        $stmts = $this->parseAndTransform('<?php
        $element = <img src="image.jpg" />;
        ');
        
        $this->assertCount(1, $stmts);
        
        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);
        
        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);
        
        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('img', $jsxElement->name);
        $this->assertCount(1, $jsxElement->attributes);
        
        $this->assertInstanceOf(Attribute::class, $jsxElement->attributes[0]);
        $this->assertEquals('src', $jsxElement->attributes[0]->name);
        $this->assertInstanceOf(String_::class, $jsxElement->attributes[0]->value);
        $this->assertEquals('image.jpg', $jsxElement->attributes[0]->value->value);
        
        $this->assertEmpty($jsxElement->children);
        $this->assertNull($jsxElement->closingName);
    }
} 