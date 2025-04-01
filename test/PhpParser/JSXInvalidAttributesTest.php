<?php declare(strict_types=1);

namespace PhpParser;

use PhpParser\Lexer\JSX as JSXLexer;
use PhpParser\Parser\Php7;
use PhpParser\Node\JSX\Element;

class JSXInvalidAttributesTest extends \PHPUnit\Framework\TestCase
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
     * Test valid attribute names (should work)
     */
    public function testValidAttributeNames() {
        $code = '<?php $x = <div id="test" className="valid" data-value="123" aria-label="button" />;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertCount(4, $jsxElement->jsxAttributes);
        
        $expectedNames = ['id', 'className', 'data-value', 'aria-label'];
        foreach ($jsxElement->jsxAttributes as $i => $attr) {
            $this->assertEquals($expectedNames[$i], $attr->name);
        }
    }

    /**
     * Test attribute name starting with number (should fail)
     */
    public function testAttributeStartingWithNumber() {
        $code = '<?php $x = <div 123attr="value" />;';
        
        $parser = $this->createParser();
        
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Invalid attribute name '123attr': attribute names cannot start with a number");
        
        $stmts = $parser->parse($code);
    }

    /**
     * Test multiple invalid attributes starting with numbers
     */
    public function testMultipleInvalidAttributes() {
        $code = '<?php $x = <div 123abc="val1" 456def="val2" />;';
        
        $parser = $this->createParser();
        
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Invalid attribute name '123abc': attribute names cannot start with a number");
        
        $stmts = $parser->parse($code);
    }

    /**
     * Test mixed valid and invalid attributes
     */
    public function testMixedValidAndInvalidAttributes() {
        $code = '<?php $x = <div id="valid" 123invalid="value" />;';
        
        $parser = $this->createParser();
        
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Invalid attribute name '123invalid': attribute names cannot start with a number");
        
        $stmts = $parser->parse($code);
    }

    /**
     * Test attribute with only numbers (should fail)
     */
    /*public function testAttributeOnlyNumbers() {
        $this->markTestIncomplete('Pure numeric attributes may need additional handling');
        $code = '<?php $x = <div 123="value" />;';
        
        $parser = $this->createParser();

        try {
            $stmts = $parser->parse($code);
        } catch (Error $e) {
            $this->assertStringContainsString('attribute', $e->getMessage());
        }
    }*/

    /**
     * Test numeric attribute values (should work - only names are restricted)
     */
    public function testNumericAttributeValues() {
        $code = '<?php $x = <div tabindex="1" maxlength="100" />;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertCount(2, $jsxElement->jsxAttributes);
        
        $this->assertEquals('tabindex', $jsxElement->jsxAttributes[0]->name);
        $this->assertEquals('maxlength', $jsxElement->jsxAttributes[1]->name);
    }

    /**
     * Test invalid attribute in non-self-closing element
     */
    public function testInvalidAttributeInNonSelfClosing() {
        $code = '<?php $x = <div 789invalid="value">Content</div>;';
        
        $parser = $this->createParser();
        
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Invalid attribute name '789invalid': attribute names cannot start with a number");
        
        $stmts = $parser->parse($code);
    }

    /**
     * Test invalid attribute with numbers and underscores
     */
    public function testInvalidAttributeWithNumbersAndUnderscores() {
        $code = '<?php $x = <div 123_invalid="test" />;';
        
        $parser = $this->createParser();
        
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Invalid attribute name '123_invalid': attribute names cannot start with a number");
        
        $stmts = $parser->parse($code);
    }

    /**
     * Test valid underscore-prefixed attributes
     */
    public function testValidUnderscorePrefixedAttributes() {
        $code = '<?php $x = <div _private="value" __internal="value" />;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertCount(2, $jsxElement->jsxAttributes);
        
        $this->assertEquals('_private', $jsxElement->jsxAttributes[0]->name);
        $this->assertEquals('__internal', $jsxElement->jsxAttributes[1]->name);
    }

    /**
     * Test invalid attribute in nested elements
     */
    public function testInvalidAttributeInNestedElements() {
        $code = '<?php $x = <div><span 456test="value">Content</span></div>;';
        
        $parser = $this->createParser();
        
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Invalid attribute name '456test': attribute names cannot start with a number");
        
        $stmts = $parser->parse($code);
    }

    /**
     * Test invalid boolean attribute starting with number
     */
    public function testInvalidBooleanAttribute() {
        $code = '<?php $x = <input 123disabled />;';
        
        $parser = $this->createParser();
        
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Invalid attribute name '123disabled': attribute names cannot start with a number");
        
        $stmts = $parser->parse($code);
    }

    /**
     * Test attribute starting with number in expression context
     */
    public function testInvalidAttributeWithExpression() {
        $code = '<?php $x = <div 123attr={$value} />;';
        
        $parser = $this->createParser();
        
        $this->expectException(Error::class);
        $this->expectExceptionMessage("Invalid attribute name '123attr': attribute names cannot start with a number");
        
        $stmts = $parser->parse($code);
    }
}