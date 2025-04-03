<?php declare(strict_types=1);

namespace PhpParser\Node\JSX;

use PhpParser\Node;

class Element extends Node {
    /** @var string */
    public $name;
    /** @var Node[] */
    public $attributes;
    /** @var Node[] */
    public $children;
    /** @var string|null */
    public $closingName;

    /**
     * @param string $name Tag name
     * @param Node[] $attributes List of attributes
     * @param Node[] $children List of children
     * @param string|null $closingName Closing tag name (null for self-closing tags)
     * @param array<string, mixed> $attributes Additional attributes
     */
    public function __construct(
        string $name,
        array $attributes,
        array $children,
        ?string $closingName = null,
        array $attributes = []
    ) {
        $this->name = $name;
        $this->attributes = $attributes;
        $this->children = $children;
        $this->closingName = $closingName;
        parent::__construct($attributes);
    }

    public function getSubNodeNames(): array {
        return ['name', 'attributes', 'children', 'closingName'];
    }

    public function getType(): string {
        return 'JSX_Element';
    }
} 