<?php declare(strict_types=1);

namespace PhpParser;

use PHPUnit\Framework\TestCase;
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
        $code3 = "<?php\n\$element = <div {...\$props}>{\$count + 1}</div>;";
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
                    0: JSX_ExpressionContainer[2 - 2](
                        expression: Expr_BinaryOp_Plus[2 - 2](
                            left: Expr_Variable[2 - 2](
                                name: count
                            )
                            right: Scalar_Int[2 - 2](
                                value: 1
                            )
                        )
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
                        value: Expr_ConstFetch[2 - 2](
                            name: Name[2 - 2](
                                name: true
                            )
                        )
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

    public function testDumpJSXWithClassProperty(): void {
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

        $code = "<?php\nclass App extends Component {\n    private \$foo = '<div>Hi</div>';\n\n    public function render() {\n        return (\n            <div>{\$this->foo}</div>\n        );\n    }\n}";
        $stmts = $parser->parse($code);
        $stmts = $traverser->traverse($stmts);
        $dump = $dumper->dump($stmts);
        $expected = <<<'OUT'
array(
    0: Stmt_Class[2 - 2](
        attrGroups: array(
        )
        flags: 0
        name: Identifier[2 - 2](
            name: App
        )
        extends: Name[2 - 2](
            name: Component
        )
        implements: array(
        )
        stmts: array(
            0: Stmt_Property[3 - 5](
                attrGroups: array(
                )
                flags: PRIVATE (4)
                type: null
                props: array(
                    0: PropertyItem[3 - 3](
                        name: VarLikeIdentifier[3 - 3](
                            name: foo
                        )
                        default: Scalar_String[3 - 3](
                            value: <div>Hi</div>
                        )
                    )
                )
                hooks: array(
                )
            )
            1: Stmt_ClassMethod[5 - 10](
                attrGroups: array(
                )
                flags: PUBLIC (1)
                byRef: false
                name: Identifier[5 - 5](
                    name: render
                )
                params: array(
                )
                returnType: null
                stmts: array(
                    0: Stmt_Return[6 - 9](
                        expr: JSX_Element[7 - 8](
                            name: div
                            jsxAttributes: array(
                            )
                            children: array(
                                0: JSX_ExpressionContainer[7 - 7](
                                    expression: Expr_PropertyFetch[7 - 7](
                                        var: Expr_Variable[7 - 7](
                                            name: this
                                        )
                                        name: Identifier[7 - 7](
                                            name: foo
                                        )
                                    )
                                )
                            )
                            closingName: div
                        )
                    )
                )
            )
        )
    )
)
OUT;
        $this->assertSame($this->canonicalize($expected), $this->canonicalize($dump));
    }

    public function testDumpJSXInClassDefinition(): void {
        $lexer = new Lexer\JSX([
            'usedAttributes' => ['startLine', 'endLine']
        ]);
        $parser = new Parser\Php7($lexer);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NodeVisitor\JSXTransformer());
        $dumper = new NodeDumper(['dumpPositions' => true]);

        $code = "<?php\nclass App {\n    public function render() {\n        return <div>Hello</div>;\n    }\n}";
        $stmts = $parser->parse($code);
        $stmts = $traverser->traverse($stmts);
        $dump = $dumper->dump($stmts);
        $expected = <<<'OUT'
array(
    0: Stmt_Class[2 - 2](
        attrGroups: array(
        )
        flags: 0
        name: Identifier[2 - 2](
            name: App
        )
        extends: null
        implements: array(
        )
        stmts: array(
            0: Stmt_ClassMethod[3 - 6](
                attrGroups: array(
                )
                flags: PUBLIC (1)
                byRef: false
                name: Identifier[3 - 3](
                    name: render
                )
                params: array(
                )
                returnType: null
                stmts: array(
                    0: Stmt_Return[4 - 5](
                        expr: JSX_Element[4 - 4](
                            name: div
                            jsxAttributes: array(
                            )
                            children: array(
                                0: JSX_Text[4 - 4](
                                    value: Hello
                                )
                            )
                            closingName: div
                        )
                    )
                )
            )
        )
    )
)
OUT;
        $this->assertSame($this->canonicalize($expected), $this->canonicalize($dump));
    }

    public function testDumpJSXWithPropertyAccess(): void {
        $lexer = new Lexer\JSX([
            'usedAttributes' => ['startLine', 'endLine']
        ]);
        $parser = new Parser\Php7($lexer);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NodeVisitor\JSXTransformer());
        $dumper = new NodeDumper(['dumpPositions' => true]);

        $code = "<?php\n<div>{\$this->property}</div>;";
        $stmts = $parser->parse($code);
        $stmts = $traverser->traverse($stmts);
        $dump = $dumper->dump($stmts);
        $expected = <<<'OUT'
array(
    0: JSX_Element[2 - 2](
        name: div
        jsxAttributes: array(
        )
        children: array(
            0: JSX_ExpressionContainer[2 - 2](
                expression: Expr_PropertyFetch[2 - 2](
                    var: Expr_Variable[2 - 2](
                        name: this
                    )
                    name: Identifier[2 - 2](
                        name: property
                    )
                )
            )
        )
        closingName: div
    )
)
OUT;
        $this->assertSame($this->canonicalize($expected), $this->canonicalize($dump));
    }

    public function testDumpJSXWithHTMLStringProperty(): void {
        $lexer = new Lexer\JSX([
            'usedAttributes' => ['startLine', 'endLine']
        ]);
        $parser = new Parser\Php7($lexer);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NodeVisitor\JSXTransformer());
        $dumper = new NodeDumper(['dumpPositions' => true]);

        $code = "<?php\n\$html = '<div>Test</div>';\n<div>{\$html}</div>;";
        $stmts = $parser->parse($code);
        $stmts = $traverser->traverse($stmts);
        $dump = $dumper->dump($stmts);
        $expected = <<<'OUT'
array(
    0: Stmt_Expression[2 - 3](
        expr: Expr_Assign[2 - 2](
            var: Expr_Variable[2 - 2](
                name: html
            )
            expr: Scalar_String[2 - 2](
                value: <div>Test</div>
            )
        )
    )
    1: JSX_Element[3 - 3](
        name: div
        jsxAttributes: array(
        )
        children: array(
            0: JSX_ExpressionContainer[3 - 3](
                expression: Expr_Variable[3 - 3](
                    name: html
                )
            )
        )
        closingName: div
    )
)
OUT;
        $this->assertSame($this->canonicalize($expected), $this->canonicalize($dump));
    }
} 