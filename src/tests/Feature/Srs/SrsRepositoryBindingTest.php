<?php

use App\Modules\Srs\Application\Contracts\SrsRepositoryInterface;
use App\Modules\Srs\Infrastructure\Persistence\EloquentSrsRepository;

test('SRS persistence is resolved through its module-owned repository contract', function () {
    expect(app(SrsRepositoryInterface::class))
        ->toBeInstanceOf(EloquentSrsRepository::class);
});
