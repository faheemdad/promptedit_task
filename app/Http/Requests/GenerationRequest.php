<?php

namespace App\Http\Requests;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validation for creating (and estimating) a generation; mirrors Pixelforge's limits. */
class GenerationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', Rule::enum(ImageSize::class)],
            'quality' => ['required', Rule::enum(ImageQuality::class)],
            'reference_images' => ['array', 'max:4'],
            'reference_images.*' => ['required', 'url:http,https', 'max:2048', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reference_images.max' => 'You can add up to 4 reference images.',
            'reference_images.*.url' => 'Each reference image must be an http(s) URL.',
            'reference_images.*.distinct' => 'This reference image is listed twice.',
        ];
    }

    public function prompt(): string
    {
        return (string) $this->validated('prompt');
    }

    public function size(): ImageSize
    {
        return ImageSize::from($this->validated('size'));
    }

    public function quality(): ImageQuality
    {
        return ImageQuality::from($this->validated('quality'));
    }

    /** @return list<string> */
    public function referenceImages(): array
    {
        return array_values($this->validated('reference_images', []));
    }
}
