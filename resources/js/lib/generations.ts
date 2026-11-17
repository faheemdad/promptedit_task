import type { Generation, GenerationStatus } from '@/types';

export const IN_FLIGHT: GenerationStatus[] = ['pending', 'queued', 'processing'];

export function isInFlight(generation: Generation): boolean {
    return IN_FLIGHT.includes(generation.status);
}

export const STATUS_LABELS: Record<GenerationStatus, string> = {
    pending: 'Sending',
    queued: 'Queued',
    processing: 'Processing',
    succeeded: 'Succeeded',
    failed: 'Failed',
};

export const STATUS_HINTS: Record<GenerationStatus, string> = {
    pending: 'Waiting for a free slot at Pixelforge. We retry automatically.',
    queued: 'Accepted by Pixelforge, waiting to start.',
    processing: 'Generating. This usually takes 5 to 30 seconds.',
    succeeded: 'Done.',
    failed: 'This generation failed. You were not charged.',
};

const ERROR_MESSAGES: Record<string, string> = {
    generation_failed: "The model couldn't produce an image for this prompt.",
    internal_error: 'Pixelforge hit an internal error. Trying again usually works.',
    content_policy_violation: 'This prompt was blocked by the content policy.',
    submission_timeout: 'Pixelforge was too busy to accept this generation for 30 minutes.',
    daily_limit_exceeded: 'The daily generation limit was reached. Try again after midnight UTC.',
};

export function errorMessage(generation: Generation): string {
    return (generation.error_code && ERROR_MESSAGES[generation.error_code]) || generation.error_message || 'Something went wrong.';
}

export function isImageExpired(generation: Generation): boolean {
    return generation.image_url_expires_at !== null && new Date(generation.image_url_expires_at) <= new Date();
}
