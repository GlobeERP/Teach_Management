<?php

namespace App\Models;

use App\Enums\LessonVideoType;
use App\Enums\MediaCategory;
use App\Models\Concerns\HasManagedMedia;
use App\Services\Media\VideoEmbedService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;

class Lesson extends Model implements HasMedia
{
    use HasFactory, HasManagedMedia;

    /**
     * @return list<MediaCategory>
     */
    public function mediaCategories(): array
    {
        return [MediaCategory::CourseVideo];
    }

    protected $fillable = [
        'course_id',
        'title',
        'order',
        'video_type',
        'video_url_or_media_id',
        'duration',
        'is_free_preview',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'video_type' => LessonVideoType::class,
            'duration' => 'integer',
            'is_free_preview' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $lesson): void {
            if ($lesson->video_type === LessonVideoType::Embed && filled($lesson->video_url_or_media_id)) {
                $embedUrl = app(VideoEmbedService::class)->embedUrl((string) $lesson->video_url_or_media_id);

                if ($embedUrl === null) {
                    throw ValidationException::withMessages([
                        'video_url_or_media_id' => 'Enter a valid YouTube or Vimeo URL.',
                    ]);
                }

                $lesson->video_url_or_media_id = $embedUrl;
            }

            if ($lesson->video_type === LessonVideoType::Upload && filled($lesson->video_url_or_media_id)) {
                $media = Media::query()->find($lesson->video_url_or_media_id);

                if (
                    ! $media
                    || $media->category() !== MediaCategory::CourseVideo
                    || $media->model_type !== self::class
                    || (int) $media->model_id !== (int) $lesson->getKey()
                ) {
                    throw ValidationException::withMessages([
                        'video_url_or_media_id' => 'Select an uploaded video belonging to this lesson.',
                    ]);
                }
            }
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The uploaded video media record, when the lesson uses the upload mode.
     */
    public function videoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'video_url_or_media_id');
    }

    public function isEmbed(): bool
    {
        return $this->video_type === LessonVideoType::Embed;
    }

    public function isUpload(): bool
    {
        return $this->video_type === LessonVideoType::Upload;
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('id');
    }
}
