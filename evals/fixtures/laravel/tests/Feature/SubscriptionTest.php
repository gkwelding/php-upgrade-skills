<?php

namespace Tests\Feature;

use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2024-06-01 12:00:00'));
    }

    private function subscription(array $attributes = []): Subscription
    {
        return Subscription::create($attributes + [
            'plan' => 'team',
            'options' => ['auto_renew' => true],
            'ends_at' => now()->addDays(9)->addHours(12),
        ]);
    }

    public function test_it_shows_whole_days_left(): void
    {
        $subscription = $this->subscription();

        $this->getJson("/api/subscriptions/{$subscription->id}")
            ->assertOk()
            ->assertExactJson(['plan' => 'team', 'seats' => 1, 'days_left' => 9, 'auto_renew' => true]);
    }

    public function test_an_ended_subscription_has_no_days_left(): void
    {
        $subscription = $this->subscription(['ends_at' => now()->subDays(3)]);

        $this->assertSame(0, $subscription->daysLeft());
    }

    public function test_seats_default_to_one_and_may_be_cleared(): void
    {
        $subscription = $this->subscription();
        $this->assertSame(1, $subscription->fresh()->seats);

        $subscription->update(['seats' => null]);
        $this->assertNull($subscription->fresh()->seats);
    }

    public function test_options_and_end_date_are_cast(): void
    {
        $subscription = $this->subscription()->fresh();

        $this->assertSame(['auto_renew' => true], $subscription->options);
        $this->assertInstanceOf(\DateTimeInterface::class, $subscription->ends_at);
    }

    public function test_renewing_adds_thirty_days(): void
    {
        $subscription = $this->subscription();

        $this->postJson("/api/subscriptions/{$subscription->id}/renew")
            ->assertOk()
            ->assertExactJson(['days_left' => 39]);
    }

    public function test_a_declined_card_is_rendered_as_402(): void
    {
        $subscription = $this->subscription(['options' => ['card' => 'declined']]);

        $this->postJson("/api/subscriptions/{$subscription->id}/renew")
            ->assertStatus(402)
            ->assertExactJson(['error' => 'payment_declined', 'reason' => 'Card declined']);
    }

    public function test_api_responses_carry_the_version_header(): void
    {
        $subscription = $this->subscription();

        $this->getJson("/api/subscriptions/{$subscription->id}")
            ->assertHeader('X-Api-Version', '2024-01');
    }

    public function test_exports_are_limited_to_two_per_five_minutes(): void
    {
        $this->getJson('/api/exports')->assertOk();
        $this->getJson('/api/exports')->assertOk();
        $this->getJson('/api/exports')->assertStatus(429);

        $this->travel(2)->minutes();
        $this->getJson('/api/exports')->assertStatus(429);

        $this->travel(4)->minutes();
        $this->getJson('/api/exports')->assertOk();
    }
}
