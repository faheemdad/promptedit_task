<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Pixelforge REST API. Returns decoded JSON arrays and
 * throws PixelforgeException for every non-2xx response.
 */
class PixelforgeClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly int $timeout = 15,
    ) {}

    /**
     * Submit a generation. The idempotency key makes retries safe: Pixelforge
     * returns the existing generation instead of creating (and billing) a new one.
     *
     * @param  array{prompt: string, size: string, quality: string, reference_images: list<string>, metadata: array<string, string>}  $params
     * @return array<string, mixed> The generation object.
     */
    public function createGeneration(array $params, string $idempotencyKey): array
    {
        return $this->send(fn (PendingRequest $http) => $http
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->post('generations', $params));
    }

    /** @return array<string, mixed> The generation object. */
    public function getGeneration(string $id): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get('generations/'.rawurlencode($id)));
    }

    /** @return array<string, mixed> */
    public function pricing(): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get('pricing'));
    }

    /** @return array<string, mixed> Daily usage for this API key (UTC dates, inclusive). */
    public function usage(string $startDate, string $endDate): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get('usage', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]));
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     * @return array<string, mixed>
     */
    private function send(callable $request): array
    {
        try {
            $response = $request($this->http());
        } catch (ConnectionException $e) {
            throw PixelforgeException::connection($e);
        }

        if ($response->failed()) {
            throw PixelforgeException::fromResponse($response);
        }

        return $response->json();
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/').'/')
            ->withToken((string) $this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);
    }
}
