<?php declare(strict_types=1);

namespace PhpParser;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\Lexer\JSX as JSXLexer;
use PhpParser\Parser\Php7;

class JSXBooleanAttributeTest extends \PHPUnit\Framework\TestCase
{
    protected function createParser() {
        $lexer = new JSXLexer();
        return new Php7($lexer);
    }

    public function testBooleanAttributeLastWithParentheses()
    {
        $code = <<<'PHP'
<?php
function test() {
    return (
        <input type="email" required/>
    );
}
PHP;
        $parser = $this->createParser();
        $stmts = $parser->parse($code);

        $this->assertCount(1, $stmts);
        $this->assertInstanceOf(Stmt\Function_::class, $stmts[0]);
        
        $returnStmt = $stmts[0]->stmts[0];
        $this->assertInstanceOf(Stmt\Return_::class, $returnStmt);
        
        $jsxElement = $returnStmt->expr;
        $this->assertInstanceOf(Node\JSX\Element::class, $jsxElement);
        
        $attributes = $jsxElement->jsxAttributes;
        $this->assertCount(2, $attributes);
        
        // Check type attribute
        $this->assertEquals('type', $attributes[0]->name);
        $this->assertInstanceOf(Node\Scalar\String_::class, $attributes[0]->value);
        $this->assertEquals('email', $attributes[0]->value->value);
        
        // Check required attribute
        $this->assertEquals('required', $attributes[1]->name);
        $this->assertInstanceOf(Expr\ConstFetch::class, $attributes[1]->value);
        $this->assertEquals('true', $attributes[1]->value->name->name);
    }

    public function testBooleanAttributeFirstWithParentheses()
    {
        $code = <<<'PHP'
<?php
function test() {
    return (
        <input required type="email"/>
    );
}
PHP;
        $parser = $this->createParser();
        $stmts = $parser->parse($code);

        $this->assertCount(1, $stmts);
        $returnStmt = $stmts[0]->stmts[0];
        $jsxElement = $returnStmt->expr;
        
        $attributes = $jsxElement->jsxAttributes;
        $this->assertCount(2, $attributes);
        
        // Check required attribute
        $this->assertEquals('required', $attributes[0]->name);
        $this->assertInstanceOf(Expr\ConstFetch::class, $attributes[0]->value);
        $this->assertEquals('true', $attributes[0]->value->name->name);
        
        // Check type attribute
        $this->assertEquals('type', $attributes[1]->name);
        $this->assertInstanceOf(Node\Scalar\String_::class, $attributes[1]->value);
        $this->assertEquals('email', $attributes[1]->value->value);
    }

    public function testMultipleBooleanAttributes()
    {
        // TODO: readonly is not supported now (a know limitation) connected with a reserved keyword
        $code = <<<'PHP'
<?php
return (<input type="text" disabled required/>);
PHP;
        $parser = $this->createParser();
        $stmts = $parser->parse($code);

        $returnStmt = $stmts[0];
        $jsxElement = $returnStmt->expr;
        
        $attributes = $jsxElement->jsxAttributes;
        $this->assertCount(3, $attributes);
        
        // Check all attributes
        $this->assertEquals('type', $attributes[0]->name);
        $this->assertInstanceOf(Node\Scalar\String_::class, $attributes[0]->value);
        $this->assertEquals('text', $attributes[0]->value->value);
        
        $this->assertEquals('disabled', $attributes[1]->name);
        $this->assertInstanceOf(Expr\ConstFetch::class, $attributes[1]->value);
        $this->assertEquals('true', $attributes[1]->value->name->name);
        
        $this->assertEquals('required', $attributes[2]->name);
        $this->assertInstanceOf(Expr\ConstFetch::class, $attributes[2]->value);
        $this->assertEquals('true', $attributes[2]->value->name->name);
    }

    public function testBooleanAttributeWithoutParentheses()
    {
        $code = <<<'PHP'
<?php
return <input type="email" required/>;
PHP;
        $parser = $this->createParser();
        $stmts = $parser->parse($code);

        $returnStmt = $stmts[0];
        $jsxElement = $returnStmt->expr;
        
        $attributes = $jsxElement->jsxAttributes;
        $this->assertCount(2, $attributes);
    }
}