<?php declare(strict_types=1);

namespace PhpParser;

use PhpParser\Lexer\JSX as JSXLexer;
use PhpParser\Parser\Php7;
use PhpParser\Node\JSX\Element;
use PhpParser\Node\JSX\Attribute;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;

class JSXBooleanAttributesTest extends \PHPUnit\Framework\TestCase
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
     * Test single boolean attribute without value
     */
    public function testSingleBooleanAttribute() {
        $code = '<?php $x = <input disabled />;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        
        $this->assertCount(1, $jsxElement->jsxAttributes);
        $attr = $jsxElement->jsxAttributes[0];
        $this->assertInstanceOf(Attribute::class, $attr);
        $this->assertEquals('disabled', $attr->name);
        
        // Value should be ConstFetch with Name 'true'
        $this->assertInstanceOf(ConstFetch::class, $attr->value);
        $this->assertInstanceOf(Name::class, $attr->value->name);
        $this->assertEquals('true', $attr->value->name->name);
    }

    /**
     * Test multiple boolean attributes
     */
    public function testMultipleBooleanAttributes() {
        $code = '<?php $x = <input disabled checked required />;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertCount(3, $jsxElement->jsxAttributes);
        
        $expectedAttrs = ['disabled', 'checked', 'required'];
        foreach ($jsxElement->jsxAttributes as $i => $attr) {
            $this->assertEquals($expectedAttrs[$i], $attr->name);
            $this->assertInstanceOf(ConstFetch::class, $attr->value);
            $this->assertEquals('true', $attr->value->name->name);
        }
    }

    /**
     * Test boolean and non-boolean attributes mixed
     */
    public function testMixedBooleanAndValueAttributes() {
        $code = '<?php $x = <input type="text" disabled value="test" required />;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertCount(4, $jsxElement->jsxAttributes);
        
        // type="text" - string value
        $typeAttr = $jsxElement->jsxAttributes[0];
        $this->assertEquals('type', $typeAttr->name);
        $this->assertInstanceOf(\PhpParser\Node\Scalar\String_::class, $typeAttr->value);
        
        // disabled - boolean
        $disabledAttr = $jsxElement->jsxAttributes[1];
        $this->assertEquals('disabled', $disabledAttr->name);
        $this->assertInstanceOf(ConstFetch::class, $disabledAttr->value);
        $this->assertEquals('true', $disabledAttr->value->name->name);
        
        // value="test" - string value
        $valueAttr = $jsxElement->jsxAttributes[2];
        $this->assertEquals('value', $valueAttr->name);
        $this->assertInstanceOf(\PhpParser\Node\Scalar\String_::class, $valueAttr->value);
        
        // required - boolean
        $requiredAttr = $jsxElement->jsxAttributes[3];
        $this->assertEquals('required', $requiredAttr->name);
        $this->assertInstanceOf(ConstFetch::class, $requiredAttr->value);
        $this->assertEquals('true', $requiredAttr->value->name->name);
    }

    /**
     * Test boolean attributes with expression attributes
     */
    public function testBooleanWithExpressionAttributes() {
        $code = '<?php $x = <input disabled onClick={$handler} checked />;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertCount(3, $jsxElement->jsxAttributes);
        
        // disabled - boolean
        $disabledAttr = $jsxElement->jsxAttributes[0];
        $this->assertEquals('disabled', $disabledAttr->name);
        $this->assertInstanceOf(ConstFetch::class, $disabledAttr->value);
        $this->assertEquals('true', $disabledAttr->value->name->name);
        
        // onClick={$handler} - variable expression
        $onClickAttr = $jsxElement->jsxAttributes[1];
        $this->assertEquals('onClick', $onClickAttr->name);
        $this->assertInstanceOf(\PhpParser\Node\Expr\Variable::class, $onClickAttr->value);
        
        // checked - boolean
        $checkedAttr = $jsxElement->jsxAttributes[2];
        $this->assertEquals('checked', $checkedAttr->name);
        $this->assertInstanceOf(ConstFetch::class, $checkedAttr->value);
        $this->assertEquals('true', $checkedAttr->value->name->name);
    }

    /**
     * Test boolean attributes in self-closing elements
     */
    public function testBooleanAttributesInSelfClosing() {
        $code = '<?php $x = <br hidden />;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertEquals('br', $jsxElement->name);
        $this->assertNull($jsxElement->closingName); // Self-closing
        
        $this->assertCount(1, $jsxElement->jsxAttributes);
        $attr = $jsxElement->jsxAttributes[0];
        $this->assertEquals('hidden', $attr->name);
        $this->assertInstanceOf(ConstFetch::class, $attr->value);
        $this->assertEquals('true', $attr->value->name->name);
    }

    /**
     * Test hyphenated boolean attributes
     */
    public function testHyphenatedBooleanAttributes() {
        $code = '<?php $x = <div data-hidden aria-expanded />;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertCount(2, $jsxElement->jsxAttributes);
        
        $dataHiddenAttr = $jsxElement->jsxAttributes[0];
        $this->assertEquals('data-hidden', $dataHiddenAttr->name);
        $this->assertInstanceOf(ConstFetch::class, $dataHiddenAttr->value);
        $this->assertEquals('true', $dataHiddenAttr->value->name->name);
        
        $ariaExpandedAttr = $jsxElement->jsxAttributes[1];
        $this->assertEquals('aria-expanded', $ariaExpandedAttr->name);
        $this->assertInstanceOf(ConstFetch::class, $ariaExpandedAttr->value);
        $this->assertEquals('true', $ariaExpandedAttr->value->name->name);
    }
}