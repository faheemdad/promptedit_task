<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * A request to Pixelforge that did not succeed. Nothing was created or billed
 * on their side when this is thrown.
 */
class PixelforgeException extends RuntimeException
{
    /** Errors that clear up on their own if we wait and try again. */
    private const TRANSIENT_CODES = [
        'rate_limit_exceeded',
        'concurrency_limit_exceeded',
        'overloaded',
        'connection_error',
        'server_error',
    ];

    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 0,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        $error = $response->json('error') ?? [];
        $retryAfter = $response->header('Retry-After');

        return new self(
            errorCode: $error['code'] ?? ($response->serverError() ? 'server_error' : 'unknown_error'),
            message: $error['message'] ?? "Pixelforge responded with HTTP {$response->status()}.",
            httpStatus: $response->status(),
            retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
        );
    }

    public static function connection(\Throwable $previous): self
    {
        return new self('connection_error', 'Could not reach Pixelforge: '.$previous->getMessage());
    }

    public function isTransient(): bool
    {
        return in_array($this->errorCode, self::TRANSIENT_CODES, true);
    }
}
