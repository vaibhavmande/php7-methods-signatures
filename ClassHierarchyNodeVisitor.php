<?php
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class ClassHierarchyNodeVisitor extends NodeVisitorAbstract
{
    private $classes;

    public function __construct(&$classes)
    {
        $this->classes = &$classes;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            array_push($this->classes, $node);
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }
    }
}
