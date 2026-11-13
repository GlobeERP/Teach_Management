<?php

namespace App\Http\Controllers\Media;

use App\Enums\LessonVideoType;
use App\Enums\MediaCategory;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Media;
use App\Services\Media\MediaService;
use App\Services\Media\LessonAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonVideoController extends Controller
{
    public function __invoke(
        Request $request,
        Lesson $lesson,
        LessonAccessService $access,
        MediaService $mediaService,
    ): JsonResponse {
        abort_unless($access->canView($request->user(), $lesson), 403);

        if ($lesson->video_type === LessonVideoType::Embed) {
            return response()->json([
                'video_type' => LessonVideoType::Embed->value,
                'url' => $lesson->video_url_or_media_id,
            ]);
        }

        $media = Media::query()->find($lesson->video_url_or_media_id)
            ?? $lesson->getFirstMedia(MediaCategory::CourseVideo->value);

        abort_unless(
            $media instanceof Media
                && $media->category() === MediaCategory::CourseVideo
                && $media->model_type === $lesson->getMorphClass()
                && (int) $media->model_id === (int) $lesson->getKey(),
            404,
        );

        return response()->json([
            'video_type' => LessonVideoType::Upload->value,
            'url' => $mediaService->temporaryVideoUrl($media, $request->user()),
        ]);
    }
}
