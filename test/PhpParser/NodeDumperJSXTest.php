<?php declare(strict_types=1);

namespace PhpParser;

use PhpParser\Node\JSX\Element;
use PhpParser\Node\JSX\Attribute;
use PhpParser\Node\JSX\SpreadAttribute;
use PhpParser\Node\JSX\Text;
use PhpParser\Node\JSX\ExpressionContainer;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;

class NodeDumperJSXTest extends \PHPUnit\Framework\TestCase {
    private function canonicalize($string) {
        return str_replace("\r\n", "\n", $string);
    }

    /**
     * @dataProvider provideTestDumpJSX
     */
    public function testDumpJSX($node, $dump): void {
        $dumper = new NodeDumper();

        $this->assertSame($this->canonicalize($dump), $this->canonicalize($dumper->dump($node)));
    }

    public static function provideTestDumpJSX() {
        return [
            [
                new Element('div', [], [], 'div'),
'JSX_Element(
    name: div
    jsxAttributes: array(
    )
    children: array(
    )
    closingName: div
)'
            ],
            [
                new Element('div', [
                    new Attribute('class', new String_('container')),
                    new Attribute('id', new Variable('id'))
                ], [
                    new Text('Hello World')
                ], 'div'),
'JSX_Element(
    name: div
    jsxAttributes: array(
        0: JSX_Attribute(
            name: class
            value: Scalar_String(
                value: container
            )
        )
        1: JSX_Attribute(
            name: id
            value: Expr_Variable(
                name: id
            )
        )
    )
    children: array(
        0: JSX_Text(
            value: Hello World
        )
    )
    closingName: div
)'
            ],
            [
                new Element('div', [
                    new SpreadAttribute(new Variable('props'))
                ], [
                    new Text('Hello World')
                ], 'div'),
'JSX_Element(
    name: div
    jsxAttributes: array(
        0: JSX_SpreadAttribute(
            expression: Expr_Variable(
                name: props
            )
        )
    )
    children: array(
        0: JSX_Text(
            value: Hello World
        )
    )
    closingName: div
)'
            ],
            [
                new Element('div', [], [
                    new ExpressionContainer(new Variable('greeting'))
                ], 'div'),
'JSX_Element(
    name: div
    jsxAttributes: array(
    )
    children: array(
        0: JSX_ExpressionContainer(
            expression: Expr_Variable(
                name: greeting
            )
        )
    )
    closingName: div
)'
            ],
            [
                new Element('img', [
                    new Attribute('src', new String_('image.jpg'))
                ], [], null),
'JSX_Element(
    name: img
    jsxAttributes: array(
        0: JSX_Attribute(
            name: src
            value: Scalar_String(
                value: image.jpg
            )
        )
    )
    children: array(
    )
    closingName: null
)'
            ],
        ];
    }

    public function testDumpJSXWithPositions(): void {
        $lexer = new Lexer\JSX([
            'usedAttributes' => [
                'comments',
                'startLine',
                'endLine'
            ],
        ]);
        $parser = new Parser\Php7($lexer);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NodeVisitor\JSXTransformer());
        $dumper = new NodeDumper(['dumpPositions' => true]);

        // Test case 1: Basic JSX element
        $code1 = "<?php\n\$element = <div>Hello World</div>;";
        $stmts = $parser->parse($code1);
        $stmts = $traverser->traverse($stmts);
        $dump = $dumper->dump($stmts);
        $expected1 = <<<'OUT'
array(
    0: Stmt_Expression[2 - 2](
        expr: Expr_Assign[2 - 2](
            var: Expr_Variable[2 - 2](
                name: element
            )
            expr: JSX_Element[2 - 2](
                name: div
                jsxAttributes: array(
                )
                children: array(
                    0: JSX_Text[2 - 2](
                        value: Hello World
                    )
                )
                closingName: div
            )
        )
    )
)
OUT;
        $this->assertSame($this->canonicalize($expected1), $this->canonicalize($dump));

        // Test case 2: JSX with attributes
        $code2 = "<?php\n\$element = <div class=\"container\" id={\$id}>Content</div>;";
        $stmts = $parser->parse($code2);
        $stmts = $traverser->traverse($stmts);
        $dump = $dumper->dump($stmts);
        $expected2 = <<<'OUT'
array(
    0: Stmt_Expression[2 - 2](
        expr: Expr_Assign[2 - 2](
            var: Expr_Variable[2 - 2](
                name: element
            )
            expr: JSX_Element[2 - 2](
                name: div
                jsxAttributes: array(
                    0: JSX_Attribute[2 - 2](
                        name: class
                        value: Scalar_String[2 - 2](
                            value: container
                        )
                    )
                    1: JSX_Attribute[2 - 2](
                        name: id
                        value: Expr_Variable[2 - 2](
                            name: id
                        )
                    )
                )
                children: array(
                    0: JSX_Text[2 - 2](
                        value: Content
                    )
                )
                closingName: div
            )
        )
    )
)
OUT;
        $this->assertSame($this->canonicalize($expected2), $this->canonicalize($dump));

        // Test case 3: JSX with spread attributes and expressions
        $code3 = "<?php\n\$element = <div {...\$props}><span>{\$count + 1}</span></div>;";
        $stmts = $parser->parse($code3);
        $stmts = $traverser->traverse($stmts);
        $dump = $dumper->dump($stmts);
        $expected3 = <<<'OUT'
array(
    0: Stmt_Expression[2 - 2](
        expr: Expr_Assign[2 - 2](
            var: Expr_Variable[2 - 2](
                name: element
            )
            expr: JSX_Element[2 - 2](
                name: div
                jsxAttributes: array(
                    0: JSX_SpreadAttribute[2 - 2](
                        expression: Expr_Variable[2 - 2](
                            name: props
                        )
                    )
                )
                children: array(
                    0: JSX_Element[2 - 2](
                        name: span
                        jsxAttributes: array(
                        )
                        children: array(
                            0: JSX_ExpressionContainer[2 - 2](
                                expression: Expr_BinaryOp_Plus[2 - 2](
                                    left: Expr_Variable[2 - 2](
                                        name: count
                                    )
                                    right: Scalar_LNumber[2 - 2](
                                        value: 1
                                    )
                                )
                            )
                        )
                        closingName: span
                    )
                )
                closingName: div
            )
        )
    )
)
OUT;
        $this->assertSame($this->canonicalize($expected3), $this->canonicalize($dump));

        // Test case 4: Self-closing JSX with multiple attributes
        $code4 = "<?php\n\$element = <input type=\"text\" value={\$value} disabled />;";
        $stmts = $parser->parse($code4);
        $stmts = $traverser->traverse($stmts);
        $dump = $dumper->dump($stmts);
        $expected4 = <<<'OUT'
array(
    0: Stmt_Expression[2 - 2](
        expr: Expr_Assign[2 - 2](
            var: Expr_Variable[2 - 2](
                name: element
            )
            expr: JSX_Element[2 - 2](
                name: input
                jsxAttributes: array(
                    0: JSX_Attribute[2 - 2](
                        name: type
                        value: Scalar_String[2 - 2](
                            value: text
                        )
                    )
                    1: JSX_Attribute[2 - 2](
                        name: value
                        value: Expr_Variable[2 - 2](
                            name: value
                        )
                    )
                    2: JSX_Attribute[2 - 2](
                        name: disabled
                        value: null
                    )
                )
                children: array(
                )
                closingName: null
            )
        )
    )
)
OUT;
        $this->assertSame($this->canonicalize($expected4), $this->canonicalize($dump));
    }
} 