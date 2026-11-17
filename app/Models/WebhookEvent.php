<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Pixelforge webhook event we have already processed. The unique event_id
 * is what stops a redelivered event from being applied twice.
 */
class WebhookEvent extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
