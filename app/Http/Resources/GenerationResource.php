<?php

namespace App\Http\Resources;

use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Generation */
class GenerationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'prompt' => $this->prompt,
            'size' => $this->size,
            'quality' => $this->quality,
            'reference_images' => $this->reference_images,
            // Nano-USD integers, formatted in the UI.
            'estimated_cost' => $this->estimated_cost,
            'max_cost' => $this->max_cost,
            'charged_cost' => $this->charged_cost,
            'image_url' => $this->image_url,
            'image_url_expires_at' => $this->image_url_expires_at,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at,
            'completed_at' => $this->completed_at,
        ];
    }
}
