<?php declare(strict_types=1);

namespace PhpParser\Node\JSX;

use PhpParser\Node;
use PhpParser\NodeAbstract;

class Element extends NodeAbstract {
    /** @var string */
    public $name;
    /** @var Node[] */
    public $jsxAttributes;
    /** @var Node[] */
    public $children;
    /** @var string|null */
    public $closingName;

    /**
     * @param string $name Tag name
     * @param Node[] $jsxAttributes List of attributes
     * @param Node[] $children List of children
     * @param string|null $closingName Closing tag name (null for self-closing tags)
     * @param array<string, mixed> $nodeAttributes Additional node attributes
     */
    public function __construct(
        string $name,
        array $jsxAttributes,
        array $children,
        ?string $closingName = null,
        array $nodeAttributes = []
    ) {
        $this->name = $name;
        $this->jsxAttributes = $jsxAttributes;
        $this->children = $children;
        $this->closingName = $closingName;
        parent::__construct($nodeAttributes);
    }

    public function getSubNodeNames(): array {
        return ['name', 'jsxAttributes', 'children', 'closingName'];
    }

    public function getType(): string {
        return 'JSX_Element';
    }
} 