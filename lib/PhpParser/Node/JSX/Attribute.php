<?php declare(strict_types=1);

namespace PhpParser\Node\JSX;

use PhpParser\Node;
use PhpParser\Node\Expr;

class Attribute extends Node {
    /** @var string */
    public $name;
    /** @var Expr|null */
    public $value;

    public function __construct(string $name, ?Expr $value = null, array $attributes = []) {
        parent::__construct($attributes);
        $this->name = $name;
        $this->value = $value;
    }

    public function getSubNodeNames(): array {
        return ['name', 'value'];
    }

    public function getType(): string {
        return 'JSX_Attribute';
    }
} 