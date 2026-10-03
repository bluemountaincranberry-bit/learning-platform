<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_consumptions', function (Blueprint $table): void {
            $table->uuid('event_id');
            $table->string('consumer', 150);
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamp('consumed_at');
            $table->primary(['event_id', 'consumer']);
            $table->index(['consumer', 'version']);
        });
    }

    public function down(): void { Schema::dropIfExists('event_consumptions'); }
};
