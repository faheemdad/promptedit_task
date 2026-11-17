<?php

namespace Tests\Feature;

use App\Actions\Generations\CreateGeneration;
use App\Actions\Generations\GenerationLifecycle;
use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\Billing\CreditLedger;
use App\Services\Pixelforge\PixelforgeClient;
use App\Support\Money;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SubmitGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Generation $generation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakePixelforge();
        Queue::fake();

        $this->user = User::factory()->create();
        app(CreditLedger::class)->purchase($this->user, Money::fromDollars(5));
        $this->generation = app(CreateGeneration::class)->handle($this->user, 'A fox', ImageSize::Square, ImageQuality::Low, []);
    }

    /** Run the job with a mocked queue job so we can observe release(). */
    private function runJob(): Job
    {
        $queueJob = Mockery::mock(Job::class)->shouldIgnoreMissing();
        $queueJob->shouldReceive('attempts')->andReturn(1);

        $job = new SubmitGeneration($this->generation);
        $job->setJob($queueJob);
        $job->handle(app(PixelforgeClient::class), app(GenerationLifecycle::class));

        return $queueJob;
    }

    public function test_accepted_generation_becomes_queued_using_our_id_as_idempotency_key(): void
    {
        $this->fakePixelforge([
            'pixelforge.test/v1/generations' => Http::response($this->remoteGeneration($this->generation, 'queued'), 202),
        ]);

        $this->runJob();

        $this->assertSame(GenerationStatus::Queued, $this->generation->fresh()->status);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://pixelforge.test/v1/generations'
            && $r->header('Idempotency-Key') === [$this->generation->id]
            && $r['metadata'] === ['generation_id' => $this->generation->id]);
    }

    public function test_concurrency_limit_waits_for_retry_after_and_keeps_the_hold(): void
    {
        $this->fakePixelforge([
            'pixelforge.test/v1/generations' => Http::response(
                ['error' => ['type' => 'rate_limit_error', 'code' => 'concurrency_limit_exceeded', 'message' => 'Too many']],
                429,
                ['Retry-After' => '5'],
            ),
        ]);

        $this->runJob()->shouldHaveReceived('release')->with(Mockery::on(fn ($delay) => $delay >= 5 && $delay <= 7));

        $this->assertSame(GenerationStatus::Pending, $this->generation->fresh()->status);
        $this->assertSame(Money::fromDollars(5) - $this->generation->max_cost, $this->user->fresh()->credit_balance);
    }

    public function test_overload_is_retried(): void
    {
        $this->fakePixelforge([
            'pixelforge.test/v1/generations' => Http::response(['error' => ['code' => 'overloaded', 'message' => 'Busy']], 503, ['Retry-After' => '2']),
        ]);

        $this->runJob()->shouldHaveReceived('release');

        $this->assertSame(GenerationStatus::Pending, $this->generation->fresh()->status);
    }

    public function test_content_policy_rejection_fails_and_refunds(): void
    {
        $this->fakePixelforge([
            'pixelforge.test/v1/generations' => Http::response(['error' => ['code' => 'content_policy_violation', 'message' => 'Blocked']], 400),
        ]);

        $this->runJob();

        $generation = $this->generation->fresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('content_policy_violation', $generation->error_code);
        $this->assertSame(Money::fromDollars(5), $this->user->fresh()->credit_balance);
    }

    public function test_giving_up_does_not_touch_a_generation_pixelforge_already_accepted(): void
    {
        $this->generation->update(['status' => GenerationStatus::Queued, 'provider_id' => 'gen_x']);

        (new SubmitGeneration($this->generation))->failed(null);

        $this->assertSame(GenerationStatus::Queued, $this->generation->fresh()->status);
    }

    public function test_reconcile_polls_stale_generations(): void
    {
        $this->generation->update(['status' => GenerationStatus::Queued, 'provider_id' => 'gen_abc']);
        Generation::query()->whereKey($this->generation->id)->update(['updated_at' => now()->subMinutes(5)]);

        $this->fakePixelforge([
            'pixelforge.test/v1/generations/gen_abc' => Http::response($this->remoteGeneration($this->generation, 'succeeded', ['id' => 'gen_abc'])),
        ]);

        $this->artisan('generations:reconcile')->assertSuccessful();

        $this->assertSame(GenerationStatus::Succeeded, $this->generation->fresh()->status);
        $this->assertNotNull($this->generation->fresh()->charged_cost);
    }
}
