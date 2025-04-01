<?php declare(strict_types=1);

namespace PhpParser;

use PhpParser\Lexer\JSX as JSXLexer;
use PhpParser\Parser\Php7;

class JSXSelfClosingTest extends \PHPUnit\Framework\TestCase
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
     * Test self-closing JSX element parsing
     */
    public function testBasicSelfClosing() {
        $code = '<?php
        $x = <br />;
        ';
        
        $parser = $this->createParser();
        $stmts = $parser->parse($code);
        
        $this->assertCount(1, $stmts);
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmts[0]);
        $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $stmts[0]->expr);
        $this->assertInstanceOf(\PhpParser\Node\JSX\Element::class, $stmts[0]->expr->expr);
    }
    
    /**
     * Test self-closing followed by regular PHP
     */
    public function testSelfClosingFollowedByPhp() {
        $code = '<?php
        $a = <br />;
        $b = 10;
        ';
        
        $parser = $this->createParser();
        
        try {
            $stmts = $parser->parse($code);
            
            $this->assertCount(2, $stmts);
            
            // First should be JSX
            $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmts[0]);
            $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $stmts[0]->expr);
            $this->assertInstanceOf(\PhpParser\Node\JSX\Element::class, $stmts[0]->expr->expr);
            
            // Second should be regular assignment
            $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmts[1]);
            $this->assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $stmts[1]->expr);
            $this->assertInstanceOf(\PhpParser\Node\Scalar\Int_::class, $stmts[1]->expr->expr);
        } catch (\Exception $e) {
            $this->fail('Parser error: ' . $e->getMessage());
        }
    }
}