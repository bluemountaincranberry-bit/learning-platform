<?php

namespace App\Modules\Content\Application\Contracts;

interface GraphTestContentFactoryInterface
{
    public function create(string $title, string $language, string $transcript): int;
}
