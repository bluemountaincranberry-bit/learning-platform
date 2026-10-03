<?php

declare(strict_types=1);

$positional = array_values(array_filter(array_slice($argv, 1), fn (string $argument): bool => ! str_starts_with($argument, '--')));
$root = realpath($positional[0] ?? dirname(__DIR__, 2).'/app/Modules');
$baselinePath = $positional[1] ?? dirname(__DIR__, 2).'/architecture/php-boundary-baseline.json';
$writeBaseline = in_array('--write-baseline', $argv, true);
$selfTest = in_array('--self-test', $argv, true);
$strict = in_array('--strict', $argv, true);

if ($selfTest) {
    $fixture = sys_get_temp_dir().'/blue-php-boundary-'.bin2hex(random_bytes(4));
    mkdir($fixture.'/A/Contracts', 0777, true);
    mkdir($fixture.'/A/Application', 0777, true);
    mkdir($fixture.'/B/Domain/Models', 0777, true);
    file_put_contents($fixture.'/A/Application/Allowed.php', "<?php\nnamespace App\\Modules\\A\\Application; use App\\Modules\\B\\Contracts\\Port;\n");
    file_put_contents($fixture.'/A/Application/Forbidden.php', "<?php\nnamespace App\\Modules\\A\\Application; use App\\Modules\\B\\Domain\\Models\\Record;\n");
    file_put_contents($fixture.'/B/Domain/Models/Record.php', "<?php\nnamespace App\\Modules\\B\\Domain\\Models; use App\\Modules\\A\\Application\\Allowed;\n");
    [$violations] = inspectPhpBoundaries($fixture);
    removeFixture($fixture);
    $rules = array_column($violations, 'rule');
    if (! in_array('private-cross-module-import', $rules, true) || ! in_array('module-cycle', $rules, true)) {
        fwrite(STDERR, "Self-test failed: private import or cycle was not detected.\n");
        exit(1);
    }
    fwrite(STDOUT, "PHP boundary self-test passed.\n");
    exit(0);
}

if ($root === false || ! is_dir($root)) {
    fwrite(STDERR, "Modules directory not found.\n");
    exit(2);
}

[$violations, $files] = inspectPhpBoundaries($root);
$serialized = array_map('violationKey', $violations);
sort($serialized);

if ($writeBaseline) {
    if ($strict) {
        fwrite(STDERR, "--strict cannot be combined with --write-baseline.\n");
        exit(2);
    }
    if (! is_dir(dirname($baselinePath))) {
        mkdir(dirname($baselinePath), 0777, true);
    }
    file_put_contents($baselinePath, json_encode([
        'description' => 'Exact pre-existing PHP module boundary violations. New entries fail the check.',
        'violations' => $serialized,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    fwrite(STDOUT, sprintf("Wrote %d PHP baseline violations from %d files.\n", count($serialized), $files));
    exit(0);
}

$baseline = ! $strict && is_file($baselinePath) ? json_decode((string) file_get_contents($baselinePath), true) : [];
$known = array_fill_keys($baseline['violations'] ?? [], true);
$new = array_values(array_filter($violations, fn (array $violation): bool => ! isset($known[violationKey($violation)])));

if ($new !== []) {
    foreach ($new as $violation) {
        fwrite(STDERR, sprintf("%s: %s (%s)\n", $violation['file'], $violation['target'], $violation['rule']));
    }
    exit(1);
}

fwrite(STDOUT, sprintf("PHP boundaries passed: %d known violations, %d files.\n", count($violations), $files));

/** @return array{array<int, array{file:string,target:string,rule:string}>, int} */
function inspectPhpBoundaries(string $root): array
{
    $importsByModule = [];
    $violations = [];
    $files = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $files++;
        $contents = (string) file_get_contents($file->getPathname());
        preg_match('/namespace\s+App\\\\Modules\\\\([^\\\\;]+)\\\\/', $contents, $namespaceMatch);
        $sourceModule = $namespaceMatch[1] ?? null;
        if ($sourceModule === null) {
            continue;
        }
        preg_match_all('/(?:^|\s)use\s+App\\\\Modules\\\\([^\\\\;]+)\\\\([^;]+);/m', $contents, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $targetModule = $match[1];
            if ($targetModule === $sourceModule) {
                continue;
            }
            $target = 'App\\Modules\\'.$targetModule.'\\'.$match[2];
            $importsByModule[$sourceModule][$targetModule] = true;
            if (! preg_match('/^(Contracts|Application\\\\(Contracts|Data))\\\\/', $match[2])) {
                $violations[] = ['file' => relativePath($root, $file->getPathname()), 'target' => $target, 'rule' => 'private-cross-module-import'];
            }
        }
    }
    foreach ($importsByModule as $source => $targets) {
        foreach (array_keys($targets) as $target) {
            if (isset($importsByModule[$target][$source]) && strcmp($source, $target) < 0) {
                $violations[] = ['file' => $source, 'target' => $target, 'rule' => 'module-cycle'];
            }
        }
    }
    return [$violations, $files];
}

function violationKey(array $violation): string
{
    return implode('|', [$violation['rule'], $violation['file'], $violation['target']]);
}

function relativePath(string $root, string $path): string
{
    return ltrim(str_replace(rtrim($root, DIRECTORY_SEPARATOR), '', $path), DIRECTORY_SEPARATOR);
}

function removeFixture(string $path): void
{
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}
