<?php
require_once 'ClassHierarchyNodeVisitor.php';

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

class Scanner
{
    public function scan($path)
    {
        $classes = $this->parseFiles($path);
        $trees = $this->buildHierarchy($classes);
        $this->analyzeSignatures($trees);
    }

    protected function parseFiles($path)
    {
        echo 'Parsing files...' . PHP_EOL;
        $classes = array();
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new ClassHierarchyNodeVisitor($classes));

        // get all classes and methods information
        $files = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)), '/.php$/');
        foreach ($files as $file) {
            $code = file_get_contents($file);
            if (stripos($code, 'class') === false) {
                continue;
            }
            try {
                $stmts = $parser->parse($code);
                $stmts = $traverser->traverse($stmts);
                echo '.';
            } catch (PhpParser\Error $e) {
                echo 'Parse error: ' . $e->getMessage();
            }
        }
        return $classes;
    }

    protected function buildHierarchy($classes)
    {
        echo PHP_EOL . 'Building hierarchy...' . PHP_EOL;
        $trees = array();
        $classMap = array();
        foreach ($classes as $class) {
            $node = new Tree\Node\Node($class);
            foreach ($trees as $key => $treeRoot) {
                if (!is_null($treeRoot->getValue()->extends) && $treeRoot->getValue()->extends->parts[0] == $class->name) {
                    $node->addChild($treeRoot);
                    unset($trees[$key]);
                }
            }
            if (is_null($class->extends)) {
                $trees[] = $node;
            } else {
                $found = false;
                if (array_search($class->extends->parts[0], $classMap)) {
                    foreach ($trees as $treeRoot) {
                        $visitor = new Tree\Visitor\PreOrderVisitor();
                        $yield = $treeRoot->accept($visitor);
                        foreach ($yield as $searchNode) {
                            if ($searchNode->getValue()->name == $class->extends->parts[0]) {
                                $searchNode->addChild($node);
                                $found = true;
                                break;
                            }
                        }
                        if ($found) {
                            break;
                        }
                    }
                }
                if (!$found) {
                    $trees[] = $node;
                }
            }
            $classMap[] = $class->name;
            echo '.';
        }
        return $trees;
    }
    
    protected function analyzeSignatures($trees)
    {
        echo PHP_EOL . 'Analyzing methods...' . PHP_EOL;
        // analyze methods signatures
        foreach ($trees as $treeRoot) {
            $visitor = new Tree\Visitor\PreOrderVisitor();
            $yield = $treeRoot->accept($visitor);
            foreach ($yield as $searchNode) {
                $methods = $searchNode->getValue()->getMethods();
                foreach ($methods as $method) {
                    $this->searchParentMethod($searchNode, $method);
                }
            }
        }
    }

    protected function searchParentMethod($node, $method)
    {
        if ($method->name == '__construct') {
            return;
        }
        $currentNode = $node;
        $found = false;

        while (!$found && ($currentNode = $currentNode->getParent())) {
            $methods = $currentNode->getValue()->getMethods();
            foreach ($methods as $parentMethod) {
                if ($parentMethod->name == $method->name) {
                    $result = $this->compareMethods($parentMethod, $method);
                    if ($result) {
                        $consoleTable = new Elkuku\Console\Helper\ConsoleTable;

                        echo PHP_EOL . "Signature mismatch between ".$currentNode->getValue()->name.'::'.$parentMethod->name.' and '.$node->getValue()->name.'::'.$method->name . PHP_EOL;
                        $consoleTable->setHeaders([
                            $currentNode->getValue()->name.'::'.$parentMethod->name,
                            $node->getValue()->name.'::'.$method->name
                        ]);

                        $maxParams = max(count($parentMethod->params), count($method->params));
                        for($iterator=0; $iterator<=$maxParams; $iterator++) {
                            $consoleTable->addRow([
                                (isset($parentMethod->params[$iterator])) ? $parentMethod->params[$iterator]->name : '---',
                                (isset($method->params[$iterator])) ? $method->params[$iterator]->name : '---'
                            ]);   
                        }
                        echo PHP_EOL . $consoleTable->getTable();
                        echo 'Result: ' . $result . PHP_EOL;
                    }
                    $found = true;
                    break;
                }
            }
        }
    }

    protected function compareMethods($parentMethod, $childMethod)
    {
        if ($childMethod->byRef != $parentMethod->byRef) {
            return 1;
        }
        if ($childMethod->returnType != $parentMethod->returnType) {
            return 2;
        }
        if (count($childMethod->params) < count($parentMethod->params)) {
            return 3;
        }
        for ($i = 0; $i < count($childMethod->params); $i ++) {
            if (!isset($parentMethod->params[$i])) {
                if (is_null($childMethod->params[$i]->default)) {
                    return 4;
                } else {
                    return 0;
                }
            }
            if ($parentMethod->params[$i]->byRef != $childMethod->params[$i]->byRef) {
                return 5;
            }
            if (is_null($parentMethod->params[$i]->type) != is_null($childMethod->params[$i]->type)) {
                return 6;
            }
            if (!is_null($parentMethod->params[$i]->type) && !is_null($childMethod->params[$i]->type)) {
                if (is_scalar($parentMethod->params[$i]->type)
                        || is_scalar($childMethod->params[$i]->type)) {
                    if ($parentMethod->params[$i]->type != $childMethod->params[$i]->type) {
                        return 7;
                    } else {
                        return 0;
                    }
                }
                if ($parentMethod->params[$i]->type->parts != $childMethod->params[$i]->type->parts) {
                    return 7;
                }
                if (!is_null($parentMethod->params[$i]->default) && is_null($childMethod->params[$i]->default)) {
                    return 8;
                }
            }
        }
        return 0;
    }
}
