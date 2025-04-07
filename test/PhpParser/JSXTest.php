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
use PhpParser\Node\Expr\Comment;

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
        $this->assertEmpty($jsxElement->jsxAttributes);
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
        $this->assertCount(2, $jsxElement->jsxAttributes);
        
        $this->assertInstanceOf(Attribute::class, $jsxElement->jsxAttributes[0]);
        $this->assertEquals('class', $jsxElement->jsxAttributes[0]->name);
        $this->assertInstanceOf(String_::class, $jsxElement->jsxAttributes[0]->value);
        $this->assertEquals('container', $jsxElement->jsxAttributes[0]->value->value);
        
        $this->assertInstanceOf(Attribute::class, $jsxElement->jsxAttributes[1]);
        $this->assertEquals('id', $jsxElement->jsxAttributes[1]->name);
        $this->assertInstanceOf(Variable::class, $jsxElement->jsxAttributes[1]->value);
        $this->assertEquals('id', $jsxElement->jsxAttributes[1]->value->name);
        
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
        $this->assertCount(1, $jsxElement->jsxAttributes);
        
        $this->assertInstanceOf(SpreadAttribute::class, $jsxElement->jsxAttributes[0]);
        $this->assertInstanceOf(Variable::class, $jsxElement->jsxAttributes[0]->expression);
        $this->assertEquals('props', $jsxElement->jsxAttributes[0]->expression->name);
        
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
        $this->assertEmpty($jsxElement->jsxAttributes);
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
        $this->assertCount(1, $jsxElement->jsxAttributes);
        
        $this->assertInstanceOf(Attribute::class, $jsxElement->jsxAttributes[0]);
        $this->assertEquals('src', $jsxElement->jsxAttributes[0]->name);
        $this->assertInstanceOf(String_::class, $jsxElement->jsxAttributes[0]->value);
        $this->assertEquals('image.jpg', $jsxElement->jsxAttributes[0]->value->value);
        
        $this->assertEmpty($jsxElement->children);
        $this->assertNull($jsxElement->closingName);
    }

    public function testParseJSXElementWithNestedElements() {
        $stmts = $this->parseAndTransform('<?php
        $element = <div><span>World</span></div>;
        ');
        
        $this->assertCount(1, $stmts);
        
        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);
        
        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);
        
        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('div', $jsxElement->name);
        $this->assertEmpty($jsxElement->jsxAttributes);
        $this->assertCount(1, $jsxElement->children);
        
        // Nested span element
        $this->assertInstanceOf(Element::class, $jsxElement->children[0]);
        $nestedElement = $jsxElement->children[0];
        $this->assertEquals('span', $nestedElement->name);
        $this->assertCount(1, $nestedElement->children);
        $this->assertInstanceOf(Text::class, $nestedElement->children[0]);
        $this->assertEquals('World', $nestedElement->children[0]->value);
        
        $this->assertEquals('div', $jsxElement->closingName);
    }

//'<?php $element = <div>Hello <span>World</span></div>;'; 

    public function testParseJSXElementWithNestedElements2() {
        $stmts = $this->parseAndTransform('<?php
        $element = <div>Hello <span>World</span></div>;
        ');

        $this->assertCount(1, $stmts);

        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);

        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);

        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('div', $jsxElement->name);
        $this->assertCount(2, $jsxElement->children);

        $this->assertInstanceOf(Text::class, $jsxElement->children[0]);
        $this->assertEquals('Hello ', $jsxElement->children[0]->value);

        $this->assertInstanceOf(Element::class, $jsxElement->children[1]);
        $this->assertEquals('span', $jsxElement->children[1]->name);
        $this->assertCount(1, $jsxElement->children[1]->children);

        $this->assertInstanceOf(Text::class, $jsxElement->children[1]->children[0]);
        $this->assertEquals('World', $jsxElement->children[1]->children[0]->value);

        $this->assertEquals('div', $jsxElement->closingName);
    }

    // class with property conating JSX, generate test
    public function testParseClassWithJSXProperty() {
        $stmts = $this->parseAndTransform('<?php
        class MyComponent {
            public $jsx = <div>Hello World</div>;
        }
        ');
        
        $this->assertCount(1, $stmts);

        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Class_::class, $stmt);
        
        $property = $stmt->stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Property::class, $property);
        $this->assertEquals('jsx', $property->props[0]->name->name);
        $this->assertInstanceOf(Element::class, $property->props[0]->default);

        $jsxElement = $property->props[0]->default;
        $this->assertEquals('div', $jsxElement->name);
        $this->assertCount(1, $jsxElement->children);
        $this->assertInstanceOf(Text::class, $jsxElement->children[0]);
        $this->assertEquals('Hello World', $jsxElement->children[0]->value);
    }

    public function testParseClassWithJSXReturn() {
        $stmts = $this->parseAndTransform('<?php
        class App extends Component {
            public function render() {
                return (
                    <div>{$this->foo}</div>
                );
            }
        }
        ');

        $this->assertCount(1, $stmts);

        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Class_::class, $stmt);
        $this->assertEquals('App', $stmt->name->name);
        $this->assertEquals('Component', $stmt->extends->name);
        
        $method = $stmt->stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\ClassMethod::class, $method);
        $this->assertEquals('render', $method->name->name);
        
        $return = $method->stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Return_::class, $return);
        $this->assertInstanceOf(Element::class, $return->expr);

        $jsxElement = $return->expr;
        $this->assertEquals('div', $jsxElement->name);
        $this->assertEmpty($jsxElement->jsxAttributes);
        $this->assertCount(1, $jsxElement->children);
        
        $this->assertInstanceOf(ExpressionContainer::class, $jsxElement->children[0]);
        $this->assertInstanceOf(\PhpParser\Node\Expr\PropertyFetch::class, $jsxElement->children[0]->expression);
        
        $propertyFetch = $jsxElement->children[0]->expression;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Variable::class, $propertyFetch->var);
        $this->assertEquals('this', $propertyFetch->var->name);
        $this->assertEquals('foo', $propertyFetch->name->name);
        
        $this->assertEquals('div', $jsxElement->closingName);
    }

    // $element9 = <div>{ $isLoggedIn ? <span>Welcome</span> : <a href="/login">Login</a> }</div>;

    public function testParseJSXElementWithConditionalRendering() {
        $stmts = $this->parseAndTransform('<?php
        $element = <div>{$isLoggedIn ? <span>Welcome</span> : <a>Login</a>}</div>;
        ');
//$element = <div>{$isLoggedIn ? <span>Welcome</span> : <a>Login</a>}</div>;
        $this->assertCount(1, $stmts);

        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);

        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);

        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('div', $jsxElement->name);

        var_dump($jsxElement->children);

        $this->assertCount(1, $jsxElement->children);

        $this->assertInstanceOf(ExpressionContainer::class, $jsxElement->children[0]);
        $this->assertInstanceOf(\PhpParser\Node\Expr\Ternary::class, $jsxElement->children[0]->expression);

        $ternary = $jsxElement->children[0]->expression;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Ternary::class, $ternary);
        $this->assertInstanceOf(\PhpParser\Node\Expr\Variable::class, $ternary->cond);
        $this->assertEquals('isLoggedIn', $ternary->cond->name);
        $this->assertInstanceOf(\PhpParser\Node\JSX\Element::class, $ternary->if);
        $this->assertEquals('span', $ternary->if->name);
        $this->assertInstanceOf(\PhpParser\Node\JSX\Element::class, $ternary->else);
        $this->assertEquals('a', $ternary->else->name);
    }
/*
$element11 = <>
    <div>Part A</div>
    <div>Part B</div>
</>;

*/
    public function testParseJSXElementWithMultipleChildren() {
        $stmts = $this->parseAndTransform('<?php
        $element = <>
            <div>Part A</div>
            <div>Part B</div>
        </>;
        ');

        $this->assertCount(1, $stmts);

        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);

        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);

        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('', $jsxElement->name);
        $this->assertCount(2, $jsxElement->children);

        $this->assertInstanceOf(Element::class, $jsxElement->children[0]);
        $this->assertEquals('div', $jsxElement->children[0]->name);
        $this->assertEquals('Part A', $jsxElement->children[0]->children[0]->value);

        $this->assertInstanceOf(Element::class, $jsxElement->children[1]);
        $this->assertEquals('div', $jsxElement->children[1]->name);
        $this->assertEquals('Part B', $jsxElement->children[1]->children[0]->value);
    }

    // $element10 = <div>{ $hasNotifications && <span>You have new messages</span> }</div>;

    public function testParseJSXElementWithConditionalAndRendering() {
        $stmts = $this->parseAndTransform('<?php
        $element = <div>{$hasNotifications && <span>You have new messages</span>}</div>;
        ');

        $this->assertCount(1, $stmts);

        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);

        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);

        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('div', $jsxElement->name);

        $this->assertCount(1, $jsxElement->children);

        $this->assertInstanceOf(ExpressionContainer::class, $jsxElement->children[0]);
        $this->assertInstanceOf(\PhpParser\Node\Expr\BinaryOp\BooleanAnd::class, $jsxElement->children[0]->expression);
        
        $booleanAnd = $jsxElement->children[0]->expression;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Variable::class, $booleanAnd->left);
        $this->assertEquals('hasNotifications', $booleanAnd->left->name);
        
        $this->assertInstanceOf(Element::class, $booleanAnd->right);
        $this->assertEquals('span', $booleanAnd->right->name);
        $this->assertCount(1, $booleanAnd->right->children);
        $this->assertInstanceOf(Text::class, $booleanAnd->right->children[0]);
        $this->assertEquals('You have new messages', $booleanAnd->right->children[0]->value);
    }

    public function testParseJSXElementWithComments() {
        $stmts = $this->parseAndTransform('<?php
        $element = <div>
            {/* This is a comment */}
            <span>Visible</span>
        </div>;
        ');

        $this->assertCount(1, $stmts);

        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);
        
        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);

        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('div', $jsxElement->name);

        $this->assertCount(2, $jsxElement->children);

        $this->assertInstanceOf(\PhpParser\Node\JSX\Comment::class, $jsxElement->children[0]);
        $this->assertEquals(' This is a comment ', $jsxElement->children[0]->text);

        $this->assertInstanceOf(Element::class, $jsxElement->children[1]);
        $this->assertEquals('span', $jsxElement->children[1]->name);
        $this->assertCount(1, $jsxElement->children[1]->children);
        $this->assertInstanceOf(Text::class, $jsxElement->children[1]->children[0]);
        $this->assertEquals('Visible', $jsxElement->children[1]->children[0]->value);
    }

    // $element = <div style={["color" => "red", "fontSize" => "16px"]}>Styled</div>;

    public function testParseJSXElementWithStyle() {
        $stmts = $this->parseAndTransform('<?php
        $element = <div style={["color" => "red", "fontSize" => "16px"]}>Styled</div>;
        ');

        $this->assertCount(1, $stmts);

        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);

        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);

        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('div', $jsxElement->name);
        
        $this->assertCount(1, $jsxElement->jsxAttributes);

        $this->assertInstanceOf(Attribute::class, $jsxElement->jsxAttributes[0]);
        $this->assertEquals('style', $jsxElement->jsxAttributes[0]->name);
 
        $this->assertInstanceOf(\PhpParser\Node\Expr\Array_::class, $jsxElement->jsxAttributes[0]->value);

        $array = $jsxElement->jsxAttributes[0]->value;
        $this->assertCount(2, $array->items);
        
        $this->assertInstanceOf(\PhpParser\Node\ArrayItem::class, $array->items[0]);
        $this->assertInstanceOf(\PhpParser\Node\ArrayItem::class, $array->items[1]);
        
        $this->assertInstanceOf(\PhpParser\Node\Scalar\String_::class, $array->items[0]->key);
        $this->assertInstanceOf(\PhpParser\Node\Scalar\String_::class, $array->items[0]->value);
        $this->assertEquals('color', $array->items[0]->key->value);
        $this->assertEquals('red', $array->items[0]->value->value);
        
        $this->assertInstanceOf(\PhpParser\Node\Scalar\String_::class, $array->items[1]->key);
        $this->assertInstanceOf(\PhpParser\Node\Scalar\String_::class, $array->items[1]->value);
        $this->assertEquals('fontSize', $array->items[1]->key->value);
        $this->assertEquals('16px', $array->items[1]->value->value);
    }

/* 
$element10 = <ul>
    { array_map(fn($item) => <li>{$item}</li>, $items) }
</ul>;


*/

    public function testParseJSXElementWithArrayMap() {
        $stmts = $this->parseAndTransform('<?php
        $element = <ul>
            { array_map(fn($item) => <li>{$item}</li>, $items) }
        </ul>;
        ');

        $this->assertCount(1, $stmts);

        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);
        
        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);

        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('ul', $jsxElement->name);
        
        $this->assertCount(1, $jsxElement->children);

        $this->assertInstanceOf(ExpressionContainer::class, $jsxElement->children[0]);
        $this->assertInstanceOf(\PhpParser\Node\Expr\FuncCall::class, $jsxElement->children[0]->expression);

        $funcCall = $jsxElement->children[0]->expression;

        $this->assertEquals('array_map', $funcCall->name->name);
        $this->assertCount(2, $funcCall->args);

        $this->assertInstanceOf(\PhpParser\Node\Expr\ArrowFunction::class, $funcCall->args[0]->value);
        $this->assertInstanceOf(\PhpParser\Node\Expr\Variable::class, $funcCall->args[1]->value);
    }


    public function testParseJSXElementWithComponent() {
        $stmts = $this->parseAndTransform('<?php
        $element = <Some.Component />;
        ');

        $this->assertCount(1, $stmts);

        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);

        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);

        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('Some.Component', $jsxElement->name);
    }

    public function testParseJSXElementWithHyphenatedAttributes()
    {
        $stmts = $this->parseAndTransform('<?php
        $element = <div data-null={null} data-undefined={$undefined}>Null test</div>;
        ');

        $this->assertCount(1, $stmts);

        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);

        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);

        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('div', $jsxElement->name);
        $this->assertCount(2, $jsxElement->jsxAttributes);

        // Check first attribute (data-null)
        $this->assertInstanceOf(Attribute::class, $jsxElement->jsxAttributes[0]);
        $this->assertEquals('data-null', $jsxElement->jsxAttributes[0]->name);
        $this->assertInstanceOf(\PhpParser\Node\Expr\ConstFetch::class, $jsxElement->jsxAttributes[0]->value);
        $this->assertEquals('null', $jsxElement->jsxAttributes[0]->value->name->toString());

        // Check second attribute (data-undefined)
        $this->assertInstanceOf(Attribute::class, $jsxElement->jsxAttributes[1]);
        $this->assertEquals('data-undefined', $jsxElement->jsxAttributes[1]->name);
        $this->assertInstanceOf(\PhpParser\Node\Expr\Variable::class, $jsxElement->jsxAttributes[1]->value);
        $this->assertEquals('undefined', $jsxElement->jsxAttributes[1]->value->name);

        // Check children
        $this->assertCount(1, $jsxElement->children);
        $this->assertInstanceOf(Text::class, $jsxElement->children[0]);
        $this->assertEquals('Null test', $jsxElement->children[0]->value);
    }

    // <?php $element = <div>Hello {$name}, you have {$count} messages</div>;

    public function testParseJSXElementWithMultipleExpressions()
    {
        $stmts = $this->parseAndTransform('<?php
        $element = <div>Hello {$name}, you have {$count} messages</div>;
        ');

        $this->assertCount(1, $stmts);

        $stmt = $stmts[0];
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmt);

        $expr = $stmt->expr;
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $expr);

        $jsxElement = $expr->expr;
        $this->assertInstanceOf(Element::class, $jsxElement);
        $this->assertEquals('div', $jsxElement->name);
        $this->assertEmpty($jsxElement->jsxAttributes);
        $this->assertCount(5, $jsxElement->children);

        // Check text node "Hello "
        $this->assertInstanceOf(Text::class, $jsxElement->children[0]);
        $this->assertEquals('Hello ', $jsxElement->children[0]->value);

        // Check first expression {$name}
        $this->assertInstanceOf(ExpressionContainer::class, $jsxElement->children[1]);
        $this->assertInstanceOf(\PhpParser\Node\Expr\Variable::class, $jsxElement->children[1]->expression);
        $this->assertEquals('name', $jsxElement->children[1]->expression->name);

        // Check text node ", you have "
        $this->assertInstanceOf(Text::class, $jsxElement->children[2]);
        $this->assertEquals(', you have ', $jsxElement->children[2]->value);

        // Check second expression {$count}
        $this->assertInstanceOf(ExpressionContainer::class, $jsxElement->children[3]);
        $this->assertInstanceOf(\PhpParser\Node\Expr\Variable::class, $jsxElement->children[3]->expression);
        $this->assertEquals('count', $jsxElement->children[3]->expression->name);

        // Check text node " messages"
        $this->assertInstanceOf(Text::class, $jsxElement->children[4]);
        $this->assertEquals('messages', $jsxElement->children[4]->value);
    }
}
