<?php

namespace App\Services\Media;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Models\User;

class LessonAccessService
{
    /**
     * Whether a user may open a lesson's video. Staff with course/media
     * permissions are allowed; students/guardians need an active enrollment
     * on a batch belonging to the lesson's course.
     */
    public function canView(User $user, Lesson $lesson): bool
    {
        if ($lesson->is_free_preview) {
            return true;
        }

        $isPortalLearner = $user->hasRole(RoleName::Student->value)
            || $user->hasRole(RoleName::Guardian->value);

        if (! $isPortalLearner && ($user->can('media.view_private') || $user->can('course.view'))) {
            return true;
        }

        $studentIds = $this->studentIdsFor($user);

        if ($studentIds === []) {
            return false;
        }

        return Enrollment::query()
            ->withoutGlobalScope(PortalStudentOwnedScope::class)
            ->whereIn('student_id', $studentIds)
            ->where('status', EnrollmentStatus::Active)
            ->whereHas('batch', fn ($query) => $query->where('course_id', $lesson->course_id))
            ->exists();
    }

    /**
     * @return list<int>
     */
    protected function studentIdsFor(User $user): array
    {
        $user->loadMissing(['student', 'guardian']);

        $ids = [];

        if ($user->student) {
            $ids[] = (int) $user->student->id;
        }

        if ($user->guardian && method_exists($user->guardian, 'students')) {
            $ids = array_merge($ids, $user->guardian->students()->pluck('students.id')->all());
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
