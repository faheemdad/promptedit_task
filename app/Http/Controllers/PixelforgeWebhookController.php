<?php

namespace App\Http\Controllers;

use App\Actions\Generations\GenerationLifecycle;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request, GenerationLifecycle $lifecycle): JsonResponse
    {
        $event = $request->json()->all();
        $generation = $event['data']['object'] ?? null;

        if (! isset($event['id'], $event['type'])) {
            return response()->json(['error' => 'Malformed event.'], 400);
        }

        // Record the event and apply it in one transaction: a redelivered event
        // is skipped, and an event that fails midway rolls back so the retry
        // is processed again.
        $duplicate = DB::transaction(function () use ($event, $generation, $lifecycle) {
            $inserted = WebhookEvent::query()->insertOrIgnore([
                'event_id' => $event['id'],
                'type' => $event['type'],
                'provider_generation_id' => $generation['id'] ?? null,
                'payload' => json_encode($event),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return true;
            }

            if (str_starts_with($event['type'], 'generation.') && is_array($generation)) {
                $lifecycle->applyRemote($generation);
            }

            return false;
        });

        return response()->json(['received' => true, 'duplicate' => $duplicate]);
    }
}
