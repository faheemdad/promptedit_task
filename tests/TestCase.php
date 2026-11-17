<?php

namespace Tests;

use App\Models\Generation;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected const WEBHOOK_SECRET = 'whsec_test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pixelforge.base_url' => 'https://pixelforge.test/v1',
            'pixelforge.api_key' => 'pf_test',
            'pixelforge.webhook_secret' => self::WEBHOOK_SECRET,
        ]);

        Http::preventStrayRequests();
        $this->withoutVite();
    }

    /** The real published price sheet (GET /v1/pricing). */
    protected function pricingResponse(): array
    {
        return json_decode(<<<'JSON'
        {"object":"pricing","currency":"usd","unit":"per_1m_tokens",
         "rates":{"input_text_tokens":"5.00","input_image_tokens":"10.00","output_tokens":"40.00","reasoning_tokens":"12.50"},
         "tokens":{"characters_per_text_token":4,"text_token_variance":0.15,"per_reference_image":765,
           "output":{"low":{"1024x1024":280,"1536x1024":420,"1024x1536":420},"medium":{"1024x1024":1100,"1536x1024":1650,"1024x1536":1650},"high":{"1024x1024":4300,"1536x1024":6450,"1024x1536":6450}},
           "reasoning":{"low":{"min":0,"max":500},"medium":{"min":200,"max":2000},"high":{"min":1000,"max":8000}}}}
        JSON, true);
    }

    /** @param array<string, mixed> $responses Extra fakes, keyed by URL pattern. */
    protected function fakePixelforge(array $responses = []): void
    {
        Http::fake([
            'pixelforge.test/v1/pricing' => Http::response($this->pricingResponse()),
            ...$responses,
        ]);
    }

    /** A Pixelforge generation object for one of our generations. */
    protected function remoteGeneration(Generation $generation, string $status, array $overrides = []): array
    {
        return array_replace([
            'id' => 'gen_'.substr(md5($generation->id), 0, 12),
            'object' => 'generation',
            'status' => $status,
            'prompt' => $generation->prompt,
            'size' => $generation->size->value,
            'quality' => $generation->quality->value,
            'reference_images' => $generation->reference_images,
            'metadata' => ['generation_id' => $generation->id],
            'image_url' => $status === 'succeeded' ? 'https://img.test/a.png' : null,
            'image_url_expires_at' => $status === 'succeeded' ? '2026-11-18T10:00:30Z' : null,
            'usage' => $status === 'succeeded' ? [
                'input_text_tokens' => 10,
                'input_image_tokens' => 0,
                'output_tokens' => 1100,
                'reasoning_tokens' => 1234,
                'total_tokens' => 2344,
            ] : null,
            'rates' => $this->pricingResponse()['rates'],
            'error' => $status === 'failed' ? ['code' => 'generation_failed', 'message' => 'The model could not produce an image.'] : null,
            'created_at' => '2026-11-17T10:00:00Z',
            'started_at' => in_array($status, ['processing', 'succeeded', 'failed']) ? '2026-11-17T10:00:02Z' : null,
            'completed_at' => in_array($status, ['succeeded', 'failed']) ? '2026-11-17T10:00:30Z' : null,
        ], $overrides);
    }

    /** POST a correctly signed webhook. */
    protected function postWebhook(array $event, ?string $secret = null, ?int $timestamp = null): TestResponse
    {
        $body = json_encode($event);
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret ?? self::WEBHOOK_SECRET);

        return $this->call('POST', '/webhooks/pixelforge', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $body);
    }

    protected function event(string $id, string $type, array $generation): array
    {
        return [
            'id' => $id,
            'object' => 'event',
            'type' => $type,
            'created_at' => '2026-11-17T10:00:30Z',
            'data' => ['object' => $generation],
        ];
    }
}
