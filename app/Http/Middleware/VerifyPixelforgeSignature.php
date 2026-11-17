<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the Pixelforge-Signature header: "t=<unix>,v1=<hex>", where v1 is
 * HMAC-SHA256("<t>.<raw body>") keyed with the webhook signing secret.
 */
class VerifyPixelforgeSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('pixelforge.webhook_secret');
        $parts = $this->parseHeader((string) $request->header('Pixelforge-Signature'));

        $timestamp = $parts['t'][0] ?? null;
        $signatures = $parts['v1'] ?? [];

        if ($secret === '' || ! ctype_digit((string) $timestamp) || $signatures === []) {
            abort(400, 'Missing or malformed signature.');
        }

        if (abs(time() - (int) $timestamp) > config('pixelforge.webhook_tolerance')) {
            abort(400, 'Signature timestamp outside tolerance.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return $next($request);
            }
        }

        abort(400, 'Invalid signature.');
    }

    /** @return array<string, list<string>> */
    private function parseHeader(string $header): array
    {
        $parts = [];

        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[$key][] = $value;
        }

        return $parts;
    }
}
