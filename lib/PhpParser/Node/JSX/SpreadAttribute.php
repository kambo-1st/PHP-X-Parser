<?php declare(strict_types=1);

namespace PhpParser\Node\JSX;

use PhpParser\Node;
use PhpParser\Node\Expr;

class SpreadAttribute extends Node {
    /** @var Expr */
    public $expression;

    public function __construct(Expr $expression, array $attributes = []) {
        parent::__construct($attributes);
        $this->expression = $expression;
    }

    public function getSubNodeNames(): array {
        return ['expression'];
    }

    public function getType(): string {
        return 'JSX_SpreadAttribute';
    }
} 