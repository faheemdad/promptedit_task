# PromptEdit × Pixelforge

Laravel 12, Vue 3 and Inertia 2. Users write a prompt, see the price up front, and generate images through the
Pixelforge API. They are charged the exact provider cost plus 25%, and failed generations are free.

## Running locally

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# set PIXELFORGE_API_KEY and PIXELFORGE_WEBHOOK_SECRET (from the Pixelforge dashboard, per API key)
php artisan migrate
composer run dev            # web server, queue worker, scheduler (reconcile poller) and Vite
php artisan users:make-admin you@example.com   # grants access to /admin/report
php artisan test
```

For webhooks during development, expose the app with a tunnel and set the key's webhook URL to
`https://<tunnel>/webhooks/pixelforge`.

## How it works

```
POST /generations ─► CreateGeneration ─► generation row (pending) + credit hold   (one DB transaction)
                                        └► SubmitGeneration job ─► POST /v1/generations (Idempotency-Key = our UUID)
                                                                      │
          webhooks (/webhooks/pixelforge) ──┐                         ▼
          reconcile poller (every minute) ──┼──► GenerationLifecycle::applyRemote() ─► CreditLedger
          create response ──────────────────┘     (row lock, forward-only status)
```

| Piece | Responsibility |
| --- | --- |
| `Services/Pixelforge/PixelforgeClient` | Thin HTTP client. Every non-2xx becomes a `PixelforgeException` with `errorCode`, `retryAfter` and `isTransient()`. |
| `Services/Pricing/PricingCalculator` | Up-front estimate and exact actual cost. The single source of pricing truth; the UI calls it through `POST /generations/estimate`. |
| `Services/Billing/CreditLedger` | The only code that moves money: an append-only `credit_transactions` table plus a cached `users.credit_balance`. |
| `Actions/Generations/GenerationLifecycle` | Applies a Pixelforge generation object, from any source, to our record, and settles money on a terminal state. |
| `Jobs/SubmitGeneration` | Submits a generation and absorbs rate limits, concurrency limits, overloads and network errors. |
| `Console/Commands/ReconcileGenerations` | Polls generations that have been in flight with no news for 90 seconds or more (a safety net for missed webhooks). |
| `Queries/SpendReport` | Admin report: spend, provider cost and profit per day and per user. |

## Money

- **Integer nano-dollars everywhere** (1 USD = 10⁹). Pixelforge's rates come to whole nanos per token
  (e.g. $12.50 / 1M = 12,500 nanos per token), and `GET /v1/usage` reports 9 decimals. So every provider cost
  is stored exactly, and our daily totals can match Pixelforge's to the last digit. No floats touch money, and
  the browser only formats.
- **Actual cost** = Σ tokens × rate / 1M, using the `usage` and the `rates` that Pixelforge fixed on the
  generation itself. It never uses the current price sheet.
- **User price** = provider cost × 1.25, rounded **up** to the next nano. That is at most $0.000000001, so we
  never undercharge. Markup is configurable (`BILLING_MARKUP_BPS`).
- **Estimate.** Output and reference image tokens are exact up front. Text tokens are ≈ chars/4 (±15%) and
  reasoning tokens fall in a range that depends on quality. The UI shows an *expected* price (base text tokens,
  midpoint reasoning) and the *worst case* (+15% text, max reasoning).
- **Holds.** On submit, the worst-case price is reserved from the balance. A succeeded generation releases the
  hold and records the actual charge; a failed one only releases the hold. A user can never start more than
  they can afford, and concurrent submissions are serialised by a row lock on the user.
- **Never twice.** `credit_transactions` has a unique `(generation_id, type)` index, so a generation has at most
  one hold, one release and one charge. This is enforced by the database, not just the code.

## Robustness

- **Rate, concurrency and overload limits (429/503).** The request never waits on Pixelforge. The job
  `release()`s itself for `Retry-After` seconds plus jitter and retries for up to 30 minutes. The user sees
  "Sending: waiting for a free slot".
- **Retries can't double-create.** Every submit uses our generation UUID as the `Idempotency-Key`, so a
  timed-out request that actually succeeded is replayed, not duplicated.
- **Permanent rejections** (`content_policy_violation`, validation, daily limit) fail immediately and release
  the hold.
- **Webhooks.**
  - The HMAC-SHA256 signature over `"<t>.<raw body>"` is verified with `hash_equals` and a 300-second replay
    window. The route lives outside the `web` stack, so there is no session or CSRF.
  - The event id is recorded (unique index) in the same transaction that applies it. A duplicate delivery is
    acknowledged with 200 and skipped. If processing throws, the transaction rolls back and Pixelforge's retry
    is processed again.
  - **Out of order.** Statuses have a rank (pending < queued < processing < terminal) and only move forward,
    under a `SELECT … FOR UPDATE` lock. A late `processing` after `succeeded` does nothing.
  - **Webhook before the POST returns.** Our UUID is sent as `metadata.generation_id`, so events are matched
    even before we know Pixelforge's id.
- **Missed webhooks.** The scheduled poller fetches stale in-flight generations.
- **Edge case.** If we give up submitting but Pixelforge did accept it, its later real outcome is applied
  (Pixelforge billed us, so we bill the user).

## Admin report (`/admin/report`)

Spend, provider cost and profit per UTC **completion** day (the day Pixelforge bills on), per user, and per day
and user. Each day is shown next to Pixelforge's own `GET /v1/usage` figure, with a ✓ or ✗ when they match or
differ.

## Deploying (Laravel Cloud)

1. Push to GitHub and create a Laravel Cloud app from the repo. Attach a Postgres or MySQL database.
2. Environment: `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET` for a **separate** "production" API key.
3. Enable a **queue worker** and the **scheduler** (both are required).
4. Deploy commands: `php artisan migrate --force`.
5. In Pixelforge, set that key's webhook URL to `https://<app>/webhooks/pixelforge` and send a test ping.
6. `php artisan users:make-admin <email>` from the Cloud console.

## Testing against Pixelforge

Prompt keywords: `[fail]` (fails after acceptance → free), `[blocked]` (rejected at submission), `[overloaded]`
(503, retried), `[slow]` (60–90 s), `[duplicate]` (every webhook delivered twice), `[succeed]` (never fails
at random).
