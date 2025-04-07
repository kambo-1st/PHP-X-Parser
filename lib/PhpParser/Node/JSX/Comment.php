<?php declare(strict_types=1);

namespace PhpParser\Node\JSX;

use PhpParser\NodeAbstract;

class Comment extends NodeAbstract {
    /** @var string */
    public $text;

    /**
     * @param string $text Comment text
     * @param array<string, mixed> $attributes Additional attributes
     */
    public function __construct(string $text, array $attributes = []) {
        $this->text = $text;
        parent::__construct($attributes);
    }

    public function getSubNodeNames(): array {
        return ['text'];
    }

    public function getType(): string {
        return 'JSX_Comment';
    }
} 