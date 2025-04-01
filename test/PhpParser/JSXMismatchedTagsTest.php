<?php declare(strict_types=1);

namespace PhpParser;

use PhpParser\Lexer\JSX as JSXLexer;
use PhpParser\Parser\Php7;
use PhpParser\Node\JSX\Element;

class JSXMismatchedTagsTest extends \PHPUnit\Framework\TestCase
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
     * Test matching opening and closing tags (should work)
     */
    public function testMatchingTags() {
        $code = '<?php $x = <div>Hello World</div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('div', $jsxElement->name);
        $this->assertEquals('div', $jsxElement->closingName);
    }

    /**
     * Test self-closing tags (should work)
     */
    public function testSelfClosingTags() {
        $code = '<?php $x = <img src="test.jpg" />;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertEquals('img', $jsxElement->name);
        $this->assertNull($jsxElement->closingName); // Self-closing
    }

    /**
     * Test mismatched tag names (should throw error)
     */
    public function testMismatchedTagNames() {
        $code = '<?php $x = <div>Content</span>;';
        
        $parser = $this->createParser();
        
        $this->expectException(Error::class);
        $this->expectExceptionMessage("JSX element has mismatched opening and closing tags: '<div>' and '</span>'");
        
        $stmts = $parser->parse($code);
    }

    /**
     * Test case sensitivity in mismatched tags
     */
    public function testCaseSensitiveMismatch() {
        $code = '<?php $x = <div>Content</DIV>;';
        
        $parser = $this->createParser();
        
        $this->expectException(Error::class);
        $this->expectExceptionMessage("JSX element has mismatched opening and closing tags: '<div>' and '</DIV>'");
        
        $stmts = $parser->parse($code);
    }

    /**
     * Test mismatched custom component names
     */
    public function testMismatchedCustomComponents() {
        $code = '<?php $x = <Button>Click me</Link>;';
        
        $parser = $this->createParser();
        
        $this->expectException(Error::class);
        $this->expectExceptionMessage("JSX element has mismatched opening and closing tags: '<Button>' and '</Link>'");
        
        $stmts = $parser->parse($code);
    }

    /**
     * Test nested elements with correct matching
     */
    public function testNestedElementsMatching() {
        $code = '<?php $x = <div><span>Hello</span></div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $outerElement = $stmts[0]->expr->expr;
        $this->assertEquals('div', $outerElement->name);
        $this->assertEquals('div', $outerElement->closingName);
        
        // Check nested element
        $this->assertCount(1, $outerElement->children);
        $innerElement = $outerElement->children[0];
        $this->assertInstanceOf(Element::class, $innerElement);
        $this->assertEquals('span', $innerElement->name);
        $this->assertEquals('span', $innerElement->closingName);
    }

    /**
     * Test nested elements with mismatched outer tags
     */
    public function testNestedElementsMismatchedOuter() {
        $code = '<?php $x = <div><span>Hello</span></section>;';
        
        $parser = $this->createParser();
        
        $this->expectException(Error::class);
        $this->expectExceptionMessage("JSX element has mismatched opening and closing tags: '<div>' and '</section>'");
        
        $stmts = $parser->parse($code);
    }

    /**
     * Test nested elements with mismatched inner tags
     */
    public function testNestedElementsMismatchedInner() {
        $code = '<?php $x = <div><span>Hello</p></div>;';
        
        $parser = $this->createParser();
        
        $this->expectException(Error::class);
        $this->expectExceptionMessage("JSX element has mismatched opening and closing tags: '<span>' and '</p>'");
        
        $stmts = $parser->parse($code);
    }

    /**
     * Test complex nested structure with correct matching
     */
    public function testComplexNestedCorrect() {
        $code = '<?php $x = <div>
            <header>
                <h1>Title</h1>
                <nav><a>Link</a></nav>
            </header>
            <main>
                <section>Content</section>
            </main>
        </div>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        // Should parse without errors
        $this->assertCount(1, $stmts);
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertEquals('div', $jsxElement->name);
        $this->assertEquals('div', $jsxElement->closingName);
    }

    /**
     * Test camelCase tag names matching  
     */
    public function testCamelCaseTagsMatching() {
        $code = '<?php $x = <customElement>Content</customElement>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertEquals('customElement', $jsxElement->name);
        $this->assertEquals('customElement', $jsxElement->closingName);
    }

    /**
     * Test camelCase tag names mismatched
     */
    public function testCamelCaseTagsMismatched() {
        $code = '<?php $x = <customElement>Content</otherElement>;';
        
        $parser = $this->createParser();
        
        $this->expectException(Error::class);
        $this->expectExceptionMessage("JSX element has mismatched opening and closing tags: '<customElement>' and '</otherElement>'");
        
        $stmts = $parser->parse($code);
    }
}