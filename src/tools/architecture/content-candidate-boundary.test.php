<?php

declare(strict_types=1);

$files = [
    __DIR__.'/../../app/Modules/Content/Application',
    __DIR__.'/../../app/Modules/Content/Interfaces',
];

foreach ($files as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = (string) file_get_contents($file->getPathname());
        if (str_contains($contents, 'use App\\Modules\\Ai\\Application\\AiCandidateApplyService;')) {
            fwrite(STDERR, "Content candidate boundary violation: {$file->getPathname()}\n");
            exit(1);
        }
    }
}

fwrite(STDOUT, "Content candidate boundary passed.\n");
