<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $provider_id
 * @property GenerationStatus $status
 * @property string $prompt
 * @property ImageSize $size
 * @property ImageQuality $quality
 * @property list<string> $reference_images
 * @property int $estimated_cost Expected user price (nano-USD, incl. markup).
 * @property int $max_cost Worst-case user price, held from the balance (nano-USD).
 * @property int|null $provider_cost What Pixelforge charged us (nano-USD).
 * @property int|null $charged_cost What we charged the user (nano-USD).
 * @property array<string, string>|null $rates
 * @property array<string, int>|null $usage
 * @property string|null $image_url
 * @property Carbon|null $image_url_expires_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int $submit_attempts
 * @property Carbon|null $submitted_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class Generation extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'pending',
        'reference_images' => '[]',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'size' => ImageSize::class,
            'quality' => ImageQuality::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'usage' => 'array',
            'estimated_cost' => 'integer',
            'max_cost' => 'integer',
            'provider_cost' => 'integer',
            'charged_cost' => 'integer',
            'image_url_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<self> $query */
    public function scopeInFlight(Builder $query): void
    {
        $query->whereIn('status', GenerationStatus::inFlight());
    }
}
