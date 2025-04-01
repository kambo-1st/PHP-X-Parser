<?php declare(strict_types=1);

namespace PhpParser;

use PhpParser\Lexer\JSX as JSXLexer;
use PhpParser\Parser\Php7;

class JSXDotNotationTest extends \PHPUnit\Framework\TestCase
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
     * Test basic dot notation in component names
     */
    public function testBasicDotNotation() {
        $code = '<?php $element = <Form.Input type="text" />;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertInstanceOf(\PhpParser\Node\JSX\Element::class, $jsxElement);
        $this->assertEquals('Form.Input', $jsxElement->name);
    }
    
    /**
     * Test dot notation with opening and closing tags
     */
    public function testDotNotationWithClosingTag() {
        $code = '<?php $element = <Modal.Header>Title</Modal.Header>;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertEquals('Modal.Header', $jsxElement->name);
        $this->assertEquals('Modal.Header', $jsxElement->closingName);
        
        // Check content
        $this->assertCount(1, $jsxElement->children);
        $this->assertEquals('Title', $jsxElement->children[0]->value);
    }
    
    /**
     * Test React Context pattern
     */
    public function testReactContextPattern() {
        $code = '<?php
        $element = (
            <ThemeContext.Provider value={$darkTheme}>
                <UserContext.Consumer>
                    {$user}
                </UserContext.Consumer>
            </ThemeContext.Provider>
        );';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $provider = $stmts[0]->expr->expr;
        $this->assertEquals('ThemeContext.Provider', $provider->name);
        
        // Check nested consumer
        $consumer = $provider->children[0];
        $this->assertInstanceOf(\PhpParser\Node\JSX\Element::class, $consumer);
        $this->assertEquals('UserContext.Consumer', $consumer->name);
    }
    
    /**
     * Test multiple dots in component name
     */
    public function testMultipleDotsInName() {
        $code = '<?php $element = <App.Components.Button.Primary />;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertEquals('App.Components.Button.Primary', $jsxElement->name);
    }
    
    /**
     * Test dot notation with props
     */
    public function testDotNotationWithProps() {
        $code = '<?php $element = <Form.Field name="email" required={true} label={$label} />;';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        $this->assertEquals('Form.Field', $jsxElement->name);
        $this->assertCount(3, $jsxElement->jsxAttributes);
    }
    
    /**
     * Test nested dot notation components
     */
    public function testNestedDotNotationComponents() {
        $code = '<?php $element = (
            <Layout.Container>
                <Layout.Header>
                    <Nav.Menu>
                        <Nav.Item>Home</Nav.Item>
                    </Nav.Menu>
                </Layout.Header>
                <Layout.Body>{$content}</Layout.Body>
            </Layout.Container>
        );';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $container = $stmts[0]->expr->expr;
        $this->assertEquals('Layout.Container', $container->name);
        
        $header = $container->children[0];
        $this->assertEquals('Layout.Header', $header->name);
        
        $menu = $header->children[0];
        $this->assertEquals('Nav.Menu', $menu->name);
    }
    
    /**
     * Test dot notation in fragments
     */
    public function testDotNotationInFragment() {
        $code = '<?php $element = (
            <>
                <Form.Input name="first" />
                <Form.Input name="last" />
                <Form.Submit>Save</Form.Submit>
            </>
        );';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $jsxElement = $stmts[0]->expr->expr;
        // Fragments are represented as elements with empty name
        $this->assertInstanceOf(\PhpParser\Node\JSX\Element::class, $jsxElement);
        $this->assertEquals('', $jsxElement->name); // Empty name indicates fragment
        
        $formElementsFound = 0;
        foreach ($jsxElement->children as $child) {
            if ($child instanceof \PhpParser\Node\JSX\Element && strpos($child->name, 'Form.') === 0) {
                $formElementsFound++;
                $this->assertStringStartsWith('Form.', $child->name);
            }
        }
        $this->assertEquals(3, $formElementsFound); // Should find 3 Form.* elements
    }
}