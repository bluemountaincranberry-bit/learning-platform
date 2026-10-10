<?php

namespace App\Modules\Interview\Application;

use App\Modules\Interview\Domain\Models\InterviewProfile;
use Illuminate\Support\Facades\DB;

final class InterviewProfileService
{
    public function get(int $userId): InterviewProfile
    {
        return InterviewProfile::query()->firstOrCreate(['user_id' => $userId])->load(['milestones', 'observations']);
    }

    /** @param array<string, mixed> $data */
    public function save(int $userId, array $data): InterviewProfile
    {
        $profile = DB::transaction(function () use ($userId, $data): InterviewProfile {
            $profile = InterviewProfile::query()->firstOrNew(['user_id' => $userId]);
            $profile->fill(collect($data)->except('milestones')->all());
            $profile->save();
            foreach ($data['milestones'] ?? [] as $milestone) {
                if (isset($milestone['id'])) {
                    $profile->milestones()->whereKey($milestone['id'])->update(collect($milestone)->except('id')->all());
                } else {
                    $profile->milestones()->create(collect($milestone)->except('id')->all());
                }
            }

            return $profile;
        });

        return $profile->load(['milestones', 'observations']);
    }
}
