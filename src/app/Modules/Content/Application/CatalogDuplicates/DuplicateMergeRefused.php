<?php

namespace App\Modules\Content\Application\CatalogDuplicates;

use RuntimeException;

/** A duplicate could not be merged safely; nothing was written. */
final class DuplicateMergeRefused extends RuntimeException {}
