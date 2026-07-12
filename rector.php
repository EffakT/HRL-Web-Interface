<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/routes',
        __DIR__.'/database',
        __DIR__.'/tests',
    ])
    ->withSkip([
        // Trait relies on Livewire's dynamic property/computed-property conventions —
        // don't let Rector "clean up" patterns Livewire specifically expects.
        __DIR__.'/app/Livewire/Concerns/HasLapDetailModal.php',
    ])
    ->withPhpSets(php83: true)
    ->withSets([
        SetList::CODE_QUALITY,
        LaravelSetList::LARAVEL_130,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_IF_HELPERS,
    ])
    ->withImportNames(removeUnusedImports: true);
