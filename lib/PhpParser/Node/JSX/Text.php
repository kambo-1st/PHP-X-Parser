<?php declare(strict_types=1);

namespace PhpParser\Node\JSX;

use PhpParser\Node;

class Text extends Node {
    /** @var string */
    public $value;

    public function __construct(string $value, array $attributes = []) {
        parent::__construct($attributes);
        $this->value = $value;
    }

    public function getSubNodeNames(): array {
        return ['value'];
    }

    public function getType(): string {
        return 'JSX_Text';
    }
} 