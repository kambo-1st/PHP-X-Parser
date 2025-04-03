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
        $dumper = new NodeDumper(['dumpPositions' => true]);

        $code = "<?php\n\$element = <div>Hello World</div>;";
        $expected = <<<'OUT'
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

        $stmts = $parser->parse($code);
        $dump = $dumper->dump($stmts);

        $this->assertSame($this->canonicalize($expected), $this->canonicalize($dump));
    }
} 