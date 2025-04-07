PHPX Parser
==========

[![Coverage Status](https://coveralls.io/repos/github/nikic/PHP-Parser/badge.svg?branch=master)](https://coveralls.io/github/nikic/PHP-Parser?branch=master)

This is PHPX, a fork of PHP-Parser with added support for JSX syntax. It extends the original PHP parser to support static code analysis and manipulation of both PHP and JSX code.

[**Documentation for version 5.x**][doc_master] (current; for running on PHP >= 7.4; for parsing PHP 7.0 to PHP 8.4, with limited support for parsing PHP 5.x).

[Documentation for version 4.x][doc_4_x] (supported; for running on PHP >= 7.0; for parsing PHP 5.2 to PHP 8.3).

Features
--------

The main features provided by this library are:

 * Parsing PHP 7, PHP 8, and JSX code into an abstract syntax tree (AST).
   * Invalid code can be parsed into a partial AST.
   * The AST contains accurate location information.
   * Full support for JSX syntax and components.
 * Dumping the AST in human-readable form.
 * Converting an AST back to PHP/JSX code.
   * Formatting can be preserved for partially changed ASTs.
 * Infrastructure to traverse and modify ASTs.
 * Resolution of namespaced names.
 * Evaluation of constant expressions.
 * Builders to simplify AST construction for code generation.
 * Converting an AST into JSON and back.

Quick Start
-----------

Install the library using [composer](https://getcomposer.org):

    php composer.phar require kambo/php-x-parser

Parse some PHP/JSX code into an AST and dump the result in human-readable form:

```php
<?php
use PhpParser\Error;
use PhpParser\NodeDumper;
use PhpParser\ParserFactory;

$code = <<<'CODE'
<?php

function test($foo)
{
    return <div>
        <h1>Hello {$foo}</h1>
        <p>This is JSX in PHP!</p>
    </div>;
}
CODE;

$parser = (new ParserFactory())->createForNewestSupportedVersion();
try {
    $ast = $parser->parse($code);
} catch (Error $error) {
    echo "Parse error: {$error->getMessage()}\n";
    return;
}

$dumper = new NodeDumper;
echo $dumper->dump($ast) . "\n";
```

This dumps an AST looking something like this:

```
array(
    0: Stmt_Function(
        attrGroups: array(
        )
        byRef: false
        name: Identifier(
            name: test
        )
        params: array(
            0: Param(
                attrGroups: array(
                )
                flags: 0
                type: null
                byRef: false
                variadic: false
                var: Expr_Variable(
                    name: foo
                )
                default: null
            )
        )
        returnType: null
        stmts: array(
            0: Stmt_Return(
                expr: Expr_JSX(
                    tag: 'div'
                    attributes: array(
                    )
                    children: array(
                        0: Expr_JSX(
                            tag: 'h1'
                            attributes: array(
                            )
                            children: array(
                                0: Expr_JSX_Text(
                                    value: 'Hello '
                                )
                                1: Expr_JSX_Expression(
                                    expr: Expr_Variable(
                                        name: foo
                                    )
                                )
                            )
                        )
                        1: Expr_JSX(
                            tag: 'p'
                            attributes: array(
                            )
                            children: array(
                                0: Expr_JSX_Text(
                                    value: 'This is JSX in PHP!'
                                )
                            )
                        )
                    )
                )
            )
        )
    )
)
```

Let's traverse the AST and perform some kind of modification. For example, drop all function bodies:

```php
use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

$traverser = new NodeTraverser();
$traverser->addVisitor(new class extends NodeVisitorAbstract {
    public function enterNode(Node $node) {
        if ($node instanceof Function_) {
            // Clean out the function body
            $node->stmts = [];
        }
    }
});

$ast = $traverser->traverse($ast);
echo $dumper->dump($ast) . "\n";
```

This gives us an AST where the `Function_::$stmts` are empty:

```
array(
    0: Stmt_Function(
        attrGroups: array(
        )
        byRef: false
        name: Identifier(
            name: test
        )
        params: array(
            0: Param(
                attrGroups: array(
                )
                type: null
                byRef: false
                variadic: false
                var: Expr_Variable(
                    name: foo
                )
                default: null
            )
        )
        returnType: null
        stmts: array(
        )
    )
)
```

Finally, we can convert the new AST back to PHP code:

```php
use PhpParser\PrettyPrinter;

$prettyPrinter = new PrettyPrinter\Standard;
echo $prettyPrinter->prettyPrintFile($ast);
```

This gives us our original code, minus the JSX content inside the function:

```php
<?php

function test($foo)
{
}
```

For a more comprehensive introduction, see the documentation.

Documentation
-------------

 1. [Introduction](doc/0_Introduction.markdown)
 2. [Usage of basic components](doc/2_Usage_of_basic_components.markdown)

Component documentation:

 * [Walking the AST](doc/component/Walking_the_AST.markdown)
   * Node visitors
   * Modifying the AST from a visitor
   * Short-circuiting traversals
   * Interleaved visitors
   * Simple node finding API
   * Parent and sibling references
 * [Name resolution](doc/component/Name_resolution.markdown)
   * Name resolver options
   * Name resolution context
 * [Pretty printing](doc/component/Pretty_printing.markdown)
   * Converting AST back to PHP/JSX code
   * Customizing formatting
   * Formatting-preserving code transformations
 * [AST builders](doc/component/AST_builders.markdown)
   * Fluent builders for AST nodes
 * [Lexer](doc/component/Lexer.markdown)
   * Emulation
   * Tokens, positions and attributes
 * [Error handling](doc/component/Error_handling.markdown)
   * Column information for errors
   * Error recovery (parsing of syntactically incorrect code)
 * [Constant expression evaluation](doc/component/Constant_expression_evaluation.markdown)
   * Evaluating constant/property/etc initializers
   * Handling errors and unsupported expressions
 * [JSON representation](doc/component/JSON_representation.markdown)
   * JSON encoding and decoding of ASTs
 * [Performance](doc/component/Performance.markdown)
   * Disabling Xdebug
   * Reusing objects
   * Garbage collection impact
 * [Frequently asked questions](doc/component/FAQ.markdown)
   * Parent and sibling references
 * [JSX Support](doc/component/JSX_Support.markdown)
   * JSX syntax and components
   * JSX expressions and attributes
   * JSX transformation

 [doc_3_x]: https://github.com/kambo/PHP-X-Parser/tree/3.x/doc
 [doc_4_x]: https://github.com/kambo/PHP-X-Parser/tree/4.x/doc
 [doc_master]: https://github.com/kambo/PHP-X-Parser/tree/master/doc
