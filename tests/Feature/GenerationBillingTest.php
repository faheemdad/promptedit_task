<?php

namespace Tests\Feature;

use App\Actions\Generations\GenerationLifecycle;
use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\Billing\CreditLedger;
use App\Services\Pixelforge\PixelforgeClient;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerationBillingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakePixelforge();
        Queue::fake();

        $this->user = User::factory()->create();
        app(CreditLedger::class)->purchase($this->user, Money::fromDollars(5));
    }

    private function createGeneration(array $input = []): Generation
    {
        $this->actingAs($this->user)
            ->post('/generations', ['prompt' => 'A red fox', 'size' => '1024x1024', 'quality' => 'medium', ...$input])
            ->assertRedirect('/generations');

        return Generation::query()->latest()->firstOrFail();
    }

    private function balance(): int
    {
        return $this->user->fresh()->credit_balance;
    }

    public function test_submitting_holds_the_worst_case_price_and_queues_the_job(): void
    {
        $generation = $this->createGeneration();

        $this->assertSame(GenerationStatus::Pending, $generation->status);
        $this->assertSame(Money::fromDollars(5) - $generation->max_cost, $this->balance());
        $this->assertGreaterThan($generation->estimated_cost, $generation->max_cost);
        Queue::assertPushed(SubmitGeneration::class);
    }

    public function test_cannot_generate_without_enough_credits(): void
    {
        $this->user = User::factory()->create();

        $this->actingAs($this->user)
            ->post('/generations', ['prompt' => 'A red fox', 'size' => '1024x1024', 'quality' => 'high'])
            ->assertSessionHasErrors('credits');

        $this->assertDatabaseCount('generations', 0);
    }

    public function test_success_webhook_charges_actual_cost_plus_markup_exactly_once(): void
    {
        $generation = $this->createGeneration();
        $remote = $this->remoteGeneration($generation, 'succeeded');

        $this->postWebhook($this->event('evt_1', 'generation.succeeded', $remote))->assertOk();
        $this->postWebhook($this->event('evt_1', 'generation.succeeded', $remote))->assertOk()->assertJson(['duplicate' => true]);
        // Same outcome under a different event id (e.g. a poll racing a webhook) must not charge again either.
        $this->postWebhook($this->event('evt_2', 'generation.succeeded', $remote))->assertOk();

        $generation->refresh();
        // 10*5 + 1100*40 + 1234*12.5 per 1M = 0.059475 USD
        $this->assertSame(59_475_000, $generation->provider_cost);
        $this->assertSame(74_343_750, $generation->charged_cost);
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame(Money::fromDollars(5) - 74_343_750, $this->balance());
        $this->assertSame(1, $generation->user->creditTransactions()->where('type', CreditTransactionType::Charge)->count());
    }

    public function test_failed_generation_is_free(): void
    {
        $generation = $this->createGeneration(['prompt' => 'A red fox [fail]']);

        $this->postWebhook($this->event('evt_1', 'generation.failed', $this->remoteGeneration($generation, 'failed')))->assertOk();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('generation_failed', $generation->error_code);
        $this->assertNull($generation->charged_cost);
        $this->assertSame(Money::fromDollars(5), $this->balance());
    }

    public function test_out_of_order_events_never_move_a_generation_backwards(): void
    {
        $generation = $this->createGeneration();

        $this->postWebhook($this->event('evt_2', 'generation.succeeded', $this->remoteGeneration($generation, 'succeeded')))->assertOk();
        $this->postWebhook($this->event('evt_1', 'generation.processing', $this->remoteGeneration($generation, 'processing')))->assertOk();

        $this->assertSame(GenerationStatus::Succeeded, $generation->fresh()->status);
    }

    public function test_webhook_arriving_before_the_create_response_is_matched_by_metadata(): void
    {
        $generation = $this->createGeneration();
        $remote = $this->remoteGeneration($generation, 'queued');

        $this->postWebhook($this->event('evt_1', 'generation.processing', $this->remoteGeneration($generation, 'processing')))->assertOk();

        Http::fake(['pixelforge.test/v1/generations' => Http::response($remote, 202)]);
        (new SubmitGeneration($generation))->handle(app(PixelforgeClient::class), app(GenerationLifecycle::class));

        $generation->refresh();
        $this->assertSame(GenerationStatus::Processing, $generation->status);
        $this->assertSame($remote['id'], $generation->provider_id);
    }

    public function test_webhook_with_bad_or_stale_signature_is_rejected(): void
    {
        $generation = $this->createGeneration();
        $event = $this->event('evt_1', 'generation.succeeded', $this->remoteGeneration($generation, 'succeeded'));

        $this->postWebhook($event, secret: 'whsec_wrong')->assertStatus(400);
        $this->postWebhook($event, timestamp: time() - 600)->assertStatus(400);

        $this->assertSame(GenerationStatus::Pending, $generation->fresh()->status);
        $this->assertDatabaseCount('webhook_events', 0);
    }
}
