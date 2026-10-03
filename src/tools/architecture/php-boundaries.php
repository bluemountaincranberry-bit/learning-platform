<?php

declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/** Resolve aliases, grouped imports, type declarations and fully qualified references. */
function phpDependencies(string $code): array
{
    $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);
    $traverser = new NodeTraverser(new NameResolver);
    $nodes = $traverser->traverse($nodes ?? []);
    $dependencies = [];
    $walk = function ($node) use (&$walk, &$dependencies): void {
        if (is_array($node)) {
            foreach ($node as $child) $walk($child);
        } elseif ($node instanceof Node) {
            if ($node instanceof Node\Stmt\GroupUse) {
                foreach ($node->uses as $use) $dependencies[] = $node->prefix.'\\'.$use->name;
                return;
            }
            if ($node instanceof Node\Name\FullyQualified) $dependencies[] = $node->toString();
            if ($node instanceof Node\Stmt\Use_) {
                foreach ($node->uses as $use) $dependencies[] = $use->name->toString();
                return;
            }
            foreach ($node->getSubNodeNames() as $key) $walk($node->$key);
        }
    };
    $walk($nodes);
    return array_values(array_unique($dependencies));
}

function phpViolations(array $files): array
{
    $edges = [];
    $graph = [];
    $violations = [];
    foreach ($files as $file => $code) {
        if (!preg_match('~(?:^|/)Modules/([^/]+)/~', $file, $owner)) continue;
        foreach (phpDependencies($code) as $dependency) {
            if (!preg_match('~^App\\\\Modules\\\\([^\\\\]+)\\\\(.+)$~', $dependency, $target) || $owner[1] === $target[1]) continue;
            $edge = ['file' => $file, 'dependency' => $dependency];
            $edges[] = [$owner[1], $target[1], $edge];
            $graph[$owner[1]][$target[1]] = true;
            if (!preg_match('~^(Contracts|Data)\\\\~', $target[2])) $violations[] = $edge + ['rule' => 'php-private-module'];
        }
    }
    $reachable = function (string $from, string $to, array $seen = []) use (&$reachable, $graph): bool {
        if ($from === $to) return true;
        if (isset($seen[$from])) return false;
        $seen[$from] = true;
        foreach (array_keys($graph[$from] ?? []) as $next) if ($reachable($next, $to, $seen)) return true;
        return false;
    };
    foreach ($edges as [$from, $to, $edge]) if ($reachable($to, $from)) $violations[] = $edge + ['rule' => 'php-module-cycle'];
    return $violations;
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    $root = realpath(__DIR__.'/../..');
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/Modules')) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') $files[substr($file->getPathname(), strlen($root) + 1)] = file_get_contents($file->getPathname());
    }
    $violations = phpViolations($files);
    usort($violations, fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
    if (in_array('--inventory', $argv, true)) { echo json_encode($violations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"; exit; }
    $baseline = json_decode(file_get_contents(__DIR__.'/php-baseline.json'), true, flags: JSON_THROW_ON_ERROR);
    $new = array_values(array_filter($violations, fn ($violation) => !in_array($violation, $baseline, true)));
    foreach ($new as $violation) echo implode(' | ', $violation)."\n";
    echo count($new).' new PHP boundary violations; '.count($violations)." existing violations.\n";
    exit($new ? 1 : 0);
}
