<?php

namespace App\Policies;

use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\User\Models\User;
use Illuminate\Auth\Access\Response;

class LessonPolicy
{
    public function view(User $user, Lesson $lesson): Response
    {
        return $user->id === $lesson->user_id ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Lesson $lesson): Response
    {
        return $this->view($user, $lesson);
    }

    public function delete(User $user, Lesson $lesson): Response
    {
        return $this->view($user, $lesson);
    }

    public function restore(User $user, Lesson $lesson): Response
    {
        return $this->view($user, $lesson);
    }
}
