<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Node youtube-transcript worker treated the package's millisecond
     * offset/duration as seconds and multiplied by 1000, inflating every
     * youtube-transcript-node segment by 1000x (e.g. 13.96s stored as
     * 13960000ms and displayed as 3:52:40). The worker now stores
     * milliseconds directly; this repairs rows written before the fix.
     */
    public function up(): void
    {
        DB::table('transcript_segments')
            ->where('source', 'youtube-transcript-node')
            ->update([
                'start_ms' => DB::raw('CAST(start_ms / 1000 AS BIGINT)'),
                'end_ms' => DB::raw('CAST(end_ms / 1000 AS BIGINT)'),
            ]);
    }

    public function down(): void
    {
        DB::table('transcript_segments')
            ->where('source', 'youtube-transcript-node')
            ->update([
                'start_ms' => DB::raw('start_ms * 1000'),
                'end_ms' => DB::raw('end_ms * 1000'),
            ]);
    }
};
