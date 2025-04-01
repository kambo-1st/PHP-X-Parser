<?php declare(strict_types=1);

namespace PhpParser\Node\JSX;

use PhpParser\NodeAbstract;

class Fragment extends NodeAbstract {
    /** @var Node[] */
    public $children;

    /**
     * @param Node[] $children List of children
     * @param array<string, mixed> $attributes Additional attributes
     */
    public function __construct(array $children, array $attributes = []) {
        parent::__construct($attributes);
        $this->children = $children;
    }

    public function getSubNodeNames(): array {
        return ['children'];
    }

    public function getType(): string {
        return 'JSX_Fragment';
    }
} 