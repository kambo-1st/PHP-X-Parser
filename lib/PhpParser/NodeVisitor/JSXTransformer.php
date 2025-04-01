<?php declare(strict_types=1);

namespace PhpParser\NodeVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\JSX\Element;
use PhpParser\Node\JSX\Attribute;
use PhpParser\Node\JSX\SpreadAttribute;
use PhpParser\Node\JSX\Text;
use PhpParser\Node\JSX\ExpressionContainer;
use PhpParser\NodeVisitorAbstract;

class JSXTransformer extends NodeVisitorAbstract
{
    public function enterNode(Node $node)
    {
        if ($node instanceof Array_) {
            return $this->transformArrayToJSX($node);
        }
        return null;
    }

    private function transformArrayToJSX(Array_ $node)
    {
        // Check if this array represents a JSX element
        if (!$this->isJSXArray($node)) {
            return null;
        }

        $items = $node->items;
        if (empty($items)) {
            return null;
        }

        // First item should be the tag name
        $tagName = $items[0]->value->value;

        // Second item should be attributes array
        $attributes = [];
        if (isset($items[1]) && $items[1]->value instanceof Array_) {
            foreach ($items[1]->value->items as $item) {
                if ($item->key === null) {
                    // Spread attribute
                    $attributes[] = new SpreadAttribute($item->value);
                } else {
                    // Regular attribute
                    $attributes[] = new Attribute($item->key->value, $item->value);
                }
            }
        }

        // Rest of items are children
        $children = [];
        for ($i = 2; $i < count($items); $i++) {
            $child = $items[$i]->value;
            if ($child instanceof Node\Scalar\String_) {
                $children[] = new Text($child->value);
            } elseif ($child instanceof Array_) {
                $transformed = $this->transformArrayToJSX($child);
                if ($transformed !== null) {
                    $children[] = $transformed;
                }
            } else {
                $children[] = new ExpressionContainer($child);
            }
        }

        return new Element($tagName, $attributes, $children, $tagName);
    }

    private function isJSXArray(Array_ $node)
    {
        // Check if first item is a string (tag name)
        if (empty($node->items) || !$node->items[0]->value instanceof Node\Scalar\String_) {
            return false;
        }

        // Check if second item is an array (attributes)
        if (isset($node->items[1]) && !$node->items[1]->value instanceof Array_) {
            return false;
        }

        return true;
    }
} 