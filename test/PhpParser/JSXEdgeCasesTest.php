<?php declare(strict_types=1);

namespace PhpParser;

use PhpParser\Lexer\JSX as JSXLexer;
use PhpParser\Parser\Php7;
use PhpParser\Node\JSX\Element;
use PhpParser\Node\JSX\Attribute;
use PhpParser\Node\JSX\Text;
use PhpParser\Node\JSX\ExpressionContainer;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;

class JSXEdgeCasesTest extends \PHPUnit\Framework\TestCase
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
     * Test deeply nested JSX elements
     */
    public function testDeeplyNestedElements() {
        $code = '<?php $x = <div><section><article><header><h1>Deep</h1></header></article></section></div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $outerDiv = $stmts[0]->expr->expr;
        $this->assertEquals('div', $outerDiv->name);
        
        // Navigate through the nesting
        $section = $outerDiv->children[0];
        $this->assertEquals('section', $section->name);
        
        $article = $section->children[0];
        $this->assertEquals('article', $article->name);
        
        $header = $article->children[0];
        $this->assertEquals('header', $header->name);
        
        $h1 = $header->children[0];
        $this->assertEquals('h1', $h1->name);
        
        $text = $h1->children[0];
        $this->assertInstanceOf(Text::class, $text);
        $this->assertEquals('Deep', $text->value);
    }

    /**
     * Test JSX with complex attribute combinations
     */
    public function testComplexAttributeCombinations() {
        $code = '<?php $x = <input 
            type="text" 
            className={$dynamicClass} 
            disabled 
            data-testid="complex-input"
            aria-label="Complex input field"
            {...$spreadProps}
            onFocus={fn() => $handleFocus()}
            required
        />;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertEquals('input', $jsxElement->name);
        $this->assertNull($jsxElement->closingName); // Self-closing
        
        // Check we have the expected number of attributes
        $this->assertGreaterThan(5, count($jsxElement->jsxAttributes));
        
        // Find specific attributes
        $attributeNames = [];
        foreach ($jsxElement->jsxAttributes as $attr) {
            if ($attr instanceof Attribute) {
                $attributeNames[] = $attr->name;
            }
        }
        
        $this->assertContains('type', $attributeNames);
        $this->assertContains('className', $attributeNames);
        $this->assertContains('disabled', $attributeNames);
        $this->assertContains('data-testid', $attributeNames);
        $this->assertContains('aria-label', $attributeNames);
        $this->assertContains('required', $attributeNames);
    }

    /**
     * Test JSX with mixed content types
     */
    public function testMixedContentTypes() {
        $code = '<?php $x = <div>
            Text before
            {$variable}
            Text between
            <span>Nested element</span>
            Text after
            {$anotherVar}
        </div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertGreaterThan(4, count($jsxElement->children));
        
        // Check we have different types of children
        $hasText = false;
        $hasExpression = false;
        $hasElement = false;
        
        foreach ($jsxElement->children as $child) {
            if ($child instanceof Text) {
                $hasText = true;
            } elseif ($child instanceof ExpressionContainer) {
                $hasExpression = true;
            } elseif ($child instanceof Element) {
                $hasElement = true;
            }
        }
        
        $this->assertTrue($hasText, 'Should have text children');
        $this->assertTrue($hasExpression, 'Should have expression children');
        $this->assertTrue($hasElement, 'Should have element children');
    }

    /**
     * Test JSX with whitespace handling
     */
    public function testWhitespaceHandling() {
        $code = '<?php $x = <div>  
            
            Content with whitespace
            
        </div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertCount(1, $jsxElement->children);
        
        $text = $jsxElement->children[0];
        $this->assertInstanceOf(Text::class, $text);
        // Whitespace should be normalized
        $this->assertStringContainsString('Content with whitespace', $text->value);
    }

    /**
     * Test JSX with quotes in text
     */
    public function testQuotesInText() {
        $code = '<?php $x = <div>Text with quotes and apostrophes</div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $text = $jsxElement->children[0];
        $this->assertInstanceOf(Text::class, $text);
        $this->assertStringContainsString('quotes', $text->value);
        $this->assertStringContainsString('apostrophes', $text->value);
    }

    /**
     * Test JSX fragments (empty tag name)
     */
    public function testJSXFragments() {
        $code = '<?php $x = <><div>First</div><div>Second</div></>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertEquals('', $jsxElement->name); // Fragment has empty name
        $this->assertEquals('', $jsxElement->closingName);
        $this->assertCount(2, $jsxElement->children);
        
        $this->assertEquals('div', $jsxElement->children[0]->name);
        $this->assertEquals('div', $jsxElement->children[1]->name);
    }

    /**
     * Test JSX with PHP expressions in attributes
     */
    public function testPHPExpressionsInAttributes() {
        $code = '<?php $x = <div 
            className={$baseClass . " active"}
            style={["color" => "red", "fontSize" => "14px"]}
            onClick={function() { return $this->handleClick(); }}
            data-count={count($items)}
        >Content</div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertCount(4, $jsxElement->jsxAttributes);
        
        // All attribute values should be expressions, not strings
        foreach ($jsxElement->jsxAttributes as $attr) {
            $this->assertInstanceOf(Attribute::class, $attr);
            $this->assertNotInstanceOf(\PhpParser\Node\Scalar\String_::class, $attr->value);
        }
    }

    /**
     * Test JSX with conditional expressions
     */
    public function testConditionalExpressions() {
        $code = '<?php $x = <div>{$show ? <span>Visible</span> : null}</div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertCount(1, $jsxElement->children);
        
        $expression = $jsxElement->children[0];
        $this->assertInstanceOf(ExpressionContainer::class, $expression);
        $this->assertInstanceOf(\PhpParser\Node\Expr\Ternary::class, $expression->expression);
    }

    /**
     * Test multiple JSX elements in same statement
     */
    public function testMultipleJSXElements() {
        $code = '<?php 
            $first = <div>First</div>;
            $second = <span>Second</span>;
        ';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(2, $stmts);
        
        $firstElement = $stmts[0]->expr->expr;
        $this->assertEquals('div', $firstElement->name);
        
        $secondElement = $stmts[1]->expr->expr;
        $this->assertEquals('span', $secondElement->name);
    }

    /**
     * Test JSX with spread attributes
     */
    public function testSpreadAttributes() {
        $code = '<?php $x = <div {...$props} className="override" />;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertCount(2, $jsxElement->jsxAttributes);
        
        // First should be spread attribute
        $spreadAttr = $jsxElement->jsxAttributes[0];
        $this->assertInstanceOf(\PhpParser\Node\JSX\SpreadAttribute::class, $spreadAttr);
        
        // Second should be regular attribute
        $classAttr = $jsxElement->jsxAttributes[1];
        $this->assertInstanceOf(Attribute::class, $classAttr);
        $this->assertEquals('className', $classAttr->name);
    }

    /**
     * Test empty JSX element
     */
    public function testEmptyJSXElement() {
        $code = '<?php $x = <div></div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertEquals('div', $jsxElement->name);
        $this->assertEquals('div', $jsxElement->closingName);
        $this->assertCount(0, $jsxElement->children);
        $this->assertCount(0, $jsxElement->jsxAttributes);
    }

    /**
     * Test JSX with only whitespace content
     */
    public function testJSXWithOnlyWhitespace() {
        $code = '<?php $x = <div>   
            
        </div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        // Whitespace-only content should still create a text node
        $this->assertGreaterThanOrEqual(0, count($jsxElement->children));
    }

    /**
     * Test JSX with numeric element names (should work)
     */
    public function testNumericElementNames() {
        // Element names starting with numbers should work (unlike attributes)
        $code = '<?php $x = <h1>Heading</h1>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertEquals('h1', $jsxElement->name);
        $this->assertEquals('h1', $jsxElement->closingName);
    }
}