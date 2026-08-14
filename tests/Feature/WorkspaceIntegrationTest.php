<?php

namespace Tests\Feature;

use App\Models\AffiliatePartnership;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_private_google_and_apple_calendar_feed(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->unit($host, 'Calendar Cleaning', 'cleaning');
        $booking = $this->booking($unit, $client, now()->addDays(3), 'confirmed', 1800);

        $this->actingAs($host)->post(route('calendar.integration.refresh'))->assertRedirect();
        $host->refresh();
        $this->assertNotNull($host->calendar_feed_token);

        $feedUrl = route('calendar.feed', ['user' => $host, 'token' => $host->calendar_feed_token]);
        $this->get($feedUrl)->assertOk()
            ->assertHeader('content-type', 'text/calendar; charset=utf-8')
            ->assertSee('BEGIN:VCALENDAR')
            ->assertSee('Calendar Cleaning')
            ->assertSee('UID:booking-'.$booking->id.'@davaorentzone.com', false);
        $this->get(route('calendar.feed', ['user' => $host, 'token' => 'wrong-token']))->assertNotFound();

        $this->actingAs($host)->get(route('calendar.index', ['mode' => 'manage']))
            ->assertOk()->assertSee('Add to Google Calendar')->assertSee('Add to iPhone / Apple')->assertSee($feedUrl);
        $this->actingAs($client)->get(route('bookings.show', $booking))
            ->assertOk()->assertSee('calendar.google.com')->assertSee(route('bookings.calendar', $booking));
        $this->actingAs($client)->get(route('bookings.calendar', $booking))->assertOk()->assertSee('BEGIN:VEVENT');
    }

    public function test_inquiry_parties_can_negotiate_and_lock_an_accepted_booking_price(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->unit($host, 'Negotiable Massage', 'massage', 1000);
        $start = now()->addDays(4)->startOfHour();
        $inquiry = $this->inquiry($unit, $client, $start);

        $this->actingAs($client)->get(route('inquiries.show', $inquiry))
            ->assertOk()
            ->assertSeeInOrder(['Standard listing price', '₱1,000.00 / session', 'Request price negotiation'])
            ->assertSee('data-live-inquiry-context', false)
            ->assertDontSee('<details class="price-proposal-form" open', false);

        $this->actingAs($client)->post(route('inquiries.price-proposals.store', $inquiry), [
            'amount' => 750,
            'note' => 'Would you accept this price for the complete session?',
        ])->assertRedirect();
        $proposal = $inquiry->priceProposals()->firstOrFail();
        $this->assertSame('pending', $proposal->status);

        $this->actingAs($host)->patch(route('price-proposals.review', $proposal), ['decision' => 'accept'])->assertRedirect();
        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'agreed_price' => 750]);

        $this->actingAs($client)->post(route('bookings.store'), [
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $start->copy()->addHours(2)->toDateTimeString(),
            'party_size' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('bookings', ['inquiry_id' => $inquiry->id, 'total_amount' => 750]);

        $this->actingAs($client)->get(route('inquiries.show', $inquiry))->assertOk()->assertSee('Agreed at ₱750.00');
    }

    public function test_clients_workspace_only_contains_clients_with_confirmed_host_bookings(): void
    {
        $host = User::factory()->host()->create();
        $otherHost = User::factory()->host()->create();
        $confirmedClient = User::factory()->create(['name' => 'Confirmed Client']);
        $pendingClient = User::factory()->create(['name' => 'Pending Client']);
        $otherClient = User::factory()->create(['name' => 'Other Host Client']);
        $unit = $this->unit($host, 'Host Cleaning', 'cleaning');
        $otherUnit = $this->unit($otherHost, 'Other Cleaning', 'cleaning');
        $this->booking($unit, $confirmedClient, now()->addDay(), 'confirmed', 2200);
        $this->booking($unit, $pendingClient, now()->addDays(2), 'pending', 1500);
        $this->booking($otherUnit, $otherClient, now()->addDays(3), 'confirmed', 3200);

        $this->actingAs($host)->get(route('workspace.clients'))->assertOk()
            ->assertSee('Confirmed Client')->assertSee('₱2,200.00')
            ->assertDontSee('Pending Client')->assertDontSee('Other Host Client');
        $this->actingAs($confirmedClient)->get(route('workspace.clients'))->assertForbidden();
    }

    public function test_host_and_client_can_review_each_other_after_a_completed_confirmed_booking(): void
    {
        $host = User::factory()->host()->create(['name' => 'Reviewed Host']);
        $client = User::factory()->create(['name' => 'Reviewed Client']);
        $unit = $this->unit($host, 'Completed Consultancy', 'consultancy');
        $booking = $this->booking($unit, $client, now()->subDays(2), 'confirmed', 5000);

        $this->actingAs($client)->post(route('bookings.reviews.store', $booking), [
            'rating' => 5,
            'comment' => 'The host was professional and exceptionally helpful.',
        ])->assertRedirect();
        $this->actingAs($host)->post(route('bookings.reviews.store', $booking), [
            'rating' => 4,
            'comment' => 'The client communicated clearly and respected the schedule.',
        ])->assertRedirect();

        $this->assertDatabaseHas('reviews', ['booking_id' => $booking->id, 'reviewee_id' => $host->id, 'reviewee_context' => 'host', 'rating' => 5]);
        $this->assertDatabaseHas('reviews', ['booking_id' => $booking->id, 'reviewee_id' => $client->id, 'reviewee_context' => 'client', 'rating' => 4]);
        $this->actingAs($client)->get(route('profiles.show', $host))->assertOk()->assertSee('As a host')->assertSee('exceptionally helpful');
        $this->actingAs($host)->get(route('profiles.show', $client))->assertOk()->assertSee('As a client')->assertSee('communicated clearly');
    }

    public function test_affiliate_partners_can_publish_role_specific_profile_reviews(): void
    {
        $host = User::factory()->host()->create();
        $affiliateUser = User::factory()->create(['name' => 'Trusted Affiliate']);
        $partnership = AffiliatePartnership::create([
            'marketer_id' => $affiliateUser->id,
            'host_id' => $host->id,
            'status' => 'accepted',
            'commission_percentage' => 10,
            'referral_code' => 'TRUSTEDCODE12',
            'application_message' => 'I will market these listings professionally and transparently.',
            'reviewed_at' => now(),
        ]);

        $this->actingAs($host)->post(route('affiliates.reviews.store', $partnership), [
            'rating' => 5,
            'comment' => 'A dependable affiliate who brings qualified booking clients.',
        ])->assertRedirect();
        $this->assertDatabaseHas('reviews', ['affiliate_partnership_id' => $partnership->id, 'reviewee_id' => $affiliateUser->id, 'reviewee_context' => 'affiliate']);
        $this->actingAs($host)->get(route('profiles.show', $affiliateUser))->assertOk()->assertSee('As an affiliate')->assertSee('dependable affiliate');
    }

    public function test_sales_dashboard_has_category_tabs_metrics_charts_and_scoped_ledger(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $cleaning = $this->unit($host, 'Sales Cleaning', 'cleaning');
        $driving = $this->unit($host, 'Sales Driver', 'driving');
        $this->booking($cleaning, $client, now()->subMonth(), 'confirmed', 3000);
        $this->booking($driving, $client, now()->subDays(3), 'confirmed', 5000);
        $this->booking($cleaning, $client, now()->addDays(2), 'pending', 2000);

        $this->actingAs($host)->get(route('sales.index'))->assertOk()
            ->assertSee('₱8,000.00')->assertSee('₱2,000.00')
            ->assertSee('Sales by month')->assertSee('Sales by category')->assertSee('Booking status mix')
            ->assertSee('Sales Cleaning')->assertSee('Sales Driver');
        $this->actingAs($host)->get(route('sales.index', ['category' => 'cleaning']))->assertOk()
            ->assertSee('Sales Cleaning')->assertDontSee('Sales Driver')->assertSee('₱3,000.00');
    }

    private function unit(User $host, string $name, string $category, float $price = 900): Unit
    {
        return Unit::create([
            'host_id' => $host->id,
            'name' => $name,
            'kind' => 'service',
            'category' => $category,
            'location' => 'Davao City',
            'rules' => 'Respect the agreed schedule.',
            'capacity' => 5,
            'price' => $price,
            'pricing_unit' => 'session',
            'is_active' => true,
        ]);
    }

    private function inquiry(Unit $unit, User $client, mixed $start): Inquiry
    {
        return Inquiry::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'host_id' => $unit->host_id,
            'desired_start_at' => $start,
            'desired_end_at' => $start->copy()->addHours(2),
            'party_size' => 1,
            'status' => 'open',
        ]);
    }

    private function booking(Unit $unit, User $client, mixed $start, string $status, float $amount): Booking
    {
        return Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(2),
            'status' => $status,
            'total_amount' => $amount,
            'party_size' => 1,
        ]);
    }
}
