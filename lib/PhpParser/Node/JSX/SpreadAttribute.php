<?php declare(strict_types=1);

namespace PhpParser\Node\JSX;

use PhpParser\NodeAbstract;
use PhpParser\Node\Expr;

class SpreadAttribute extends NodeAbstract {
    /** @var Expr Expression to spread */
    public Expr $expression;

    /**
     * @param Expr $expression Expression to spread
     * @param array<string, mixed> $attributes Additional attributes
     */
    public function __construct(Expr $expression, array $attributes = []) {
        $this->expression = $expression;
        $this->attributes = $attributes;
    }

    public function getSubNodeNames(): array {
        return ['expression'];
    }

    public function getType(): string {
        return 'JSX_SpreadAttribute';
    }
} 