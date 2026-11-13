<?php

namespace App\Http\Controllers\Media;

use App\Enums\MediaCategory;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Media;
use App\Services\Media\LessonAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams uploaded lesson videos. Access requires either a valid signed URL or
 * the media.view_private permission, and (for portal learners) an active
 * enrollment on the lesson's course.
 */
class VideoStreamController extends Controller
{
    public function __invoke(Request $request, Media $media, LessonAccessService $access): BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        abort_unless($user !== null, 403);
        abort_unless($media->category() === MediaCategory::CourseVideo, 404);

        $media->loadMissing('model');

        $lesson = $media->model instanceof Lesson ? $media->model : null;

        if ($lesson !== null && ! $access->canView($user, $lesson)) {
            abort(403);
        }

        $authorised = $request->hasValidSignature() || $user->can('media.view_private');

        abort_unless($authorised, 403);

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        abort_unless($disk->exists($path), 404);

        if (config("filesystems.disks.{$media->disk}.driver") !== 'local') {
            return redirect()->away($disk->temporaryUrl($path, now()->addMinutes((int) config('media.signed_url_ttl', 5))));
        }

        return response()->file($disk->path($path), [
            'Content-Type' => $media->mime_type ?: 'video/mp4',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
