<?php

return [

    'base_url' => env('PIXELFORGE_BASE_URL', 'https://pixelforge.promptedit.dev/v1'),

    'api_key' => env('PIXELFORGE_API_KEY'),

    // Signing secret (whsec_...) of the API key above; each key has its own.
    'webhook_secret' => env('PIXELFORGE_WEBHOOK_SECRET'),

    // Reject webhook signatures older/newer than this many seconds (replay protection).
    'webhook_tolerance' => 300,

    'timeout' => 15,

    // How long the pricing catalogue from GET /v1/pricing is considered fresh.
    'pricing_cache_seconds' => 3600,

    // Give up submitting a generation that Pixelforge keeps rejecting with
    // rate/concurrency limits or overloads after this many minutes.
    'submit_give_up_minutes' => 30,

    // Poll Pixelforge for in-flight generations that have not changed for this
    // many seconds (covers missed webhooks).
    'reconcile_after_seconds' => 90,

];
