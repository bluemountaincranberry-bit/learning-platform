<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_log', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 255);
            $table->integer('partition')->unsigned();
            $table->bigInteger('offset');
            $table->string('key', 255)->nullable();
            $table->text('payload');
            $table->timestamp('consumed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_log');
    }
};
